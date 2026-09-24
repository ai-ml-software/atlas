<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * One interface over every AI provider.
 *
 * What a provider IS (endpoints, auth style, regions, capabilities) comes from
 * config/ha_ai_providers.php. What the admin CHOSE (key, region, overrides,
 * on/off) lives in ha_ai_provider. Which model runs which task lives in
 * ha_ai_route. Callers ask for a task: $gateway->chat('lesson_script', ...).
 *
 * Wire formats. Almost every provider now speaks the OpenAI chat format, so
 * 'openai' covers dozens of them; the rest get a dedicated adapter:
 *   openai        POST {base}/chat/completions, GET {base}/models
 *   anthropic     POST {base}/v1/messages,       GET {base}/v1/models
 *   gemini        POST {base}/v1beta/models/{m}:generateContent, GET {base}/v1beta/models
 *   cohere        POST {base}/v2/chat,           GET {base}/v1/models
 *   azure_openai  POST {endpoint}/openai/v1/chat/completions (api-key header)
 *   bedrock       POST bedrock-runtime.{region}/model/{m}/converse (API key or SigV4)
 *
 * API keys are read from, in order: the HA_AI_KEY_<SLUG> environment variable
 * (so production can keep them out of the database entirely), then the
 * encrypted column. They are never logged, echoed or returned to a browser.
 */
class Ha_ai_gateway {

    private $CI;
    private $crypto;
    private $registry;
    private $tasks;
    private $limits;
    private $rows = null;

    /** Set by callers so usage rows carry who and what. */
    public $context = array('user_id' => null, 'job_id' => null, 'api_key_id' => null);

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->config->load('ha_ai', true);
        if (is_file(APPPATH . 'config/ha_ai_providers.php')) {
            $this->CI->config->load('ha_ai_providers', true);
        }
        require_once APPPATH . 'libraries/Ha_crypto.php';
        $this->crypto = new Ha_crypto();
        $this->registry = (array) $this->CI->config->item('ha_ai_providers', 'ha_ai_providers');
        $this->tasks = (array) $this->CI->config->item('ha_ai_tasks', 'ha_ai');
        $this->limits = (array) $this->CI->config->item('ha_ai_limits', 'ha_ai');
    }

    // ============================================================ providers

    public function tasks() {
        return $this->tasks;
    }

    /** Every known provider, registry merged with the admin's saved choices. */
    public function providers() {
        $out = array();
        foreach ($this->registry as $slug => $meta) {
            $out[$slug] = $this->describe($slug, $meta);
        }
        // Custom providers an admin added that are not in the registry.
        foreach ($this->saved_rows() as $slug => $row) {
            if (!isset($out[$slug])) {
                $settings = json_decode((string) $row['settings_json'], true) ?: array();
                $meta = array(
                    'name' => isset($settings['name']) ? $settings['name'] : $slug,
                    'company' => isset($settings['company']) ? $settings['company'] : 'Custom',
                    'hq_country' => null, 'api_style' => isset($settings['api_style']) ? $settings['api_style'] : 'openai',
                    'base_url' => $row['base_url'], 'capabilities' => array('chat'), 'custom' => true,
                );
                $out[$slug] = $this->describe($slug, $meta);
            }
        }
        return $out;
    }

    public function provider($slug) {
        $all = $this->providers();
        return isset($all[$slug]) ? $all[$slug] : null;
    }

    private function describe($slug, array $meta) {
        $rows = $this->saved_rows();
        $row = isset($rows[$slug]) ? $rows[$slug] : null;
        $settings = $row ? (json_decode((string) $row['settings_json'], true) ?: array()) : array();
        $env_key = getenv('HA_AI_KEY_' . strtoupper(preg_replace('/[^a-z0-9]/i', '_', $slug)));

        $defaults = array(
            'name' => $slug, 'company' => null, 'hq_country' => null, 'docs_url' => null,
            'api_style' => 'openai', 'base_url' => null, 'regional_base_urls' => array(),
            'auth' => array(), 'chat_path' => null, 'models_path' => null,
            'capabilities' => array('chat'), 'tts' => null, 'video' => null,
            'available_regions' => null, 'restrictions' => null, 'default_models' => array(),
            'key_url' => null, 'note' => null, 'custom' => false,
        );
        $p = array_merge($defaults, $meta);
        $p['slug'] = $slug;
        $p['capabilities'] = array_values((array) $p['capabilities']);
        $p['regional_base_urls'] = (array) $p['regional_base_urls'];
        $p['enabled'] = $row ? (bool) $row['enabled'] : false;
        $p['region'] = $row ? $row['region'] : null;
        $p['base_url_override'] = $row ? $row['base_url'] : null;
        $p['settings'] = $settings;
        $p['has_key'] = (bool) $env_key || ($row && !empty($row['api_key_cipher']));
        $p['key_source'] = $env_key ? 'environment' : (($row && !empty($row['api_key_cipher'])) ? 'database' : null);
        $p['key_hint'] = $env_key ? Ha_crypto::hint($env_key) : ($row ? $row['api_key_hint'] : null);
        $p['last_tested_at'] = $row ? $row['last_tested_at'] : null;
        $p['last_test_ok'] = $row ? $row['last_test_ok'] : null;
        $p['last_error'] = $row ? $row['last_error'] : null;
        $p['models_synced_at'] = $row ? $row['models_synced_at'] : null;
        $p['needs_key'] = !in_array($p['api_style'], array('local'), true) && !$this->is_local_url($this->endpoint($p));
        $p['placeholders'] = $this->placeholders($p);
        $p['deprecated'] = !empty($p['deprecated']);
        return $p;
    }

    /**
     * table_exists() answers from a per-process cache of the table list, so a
     * long-running worker started before a migration (or the test runner,
     * which migrates after connecting) would never see the new tables. Ask
     * the server, and remember only a positive answer.
     */
    private function has_table($table) {
        static $known = array();
        if (!empty($known[$table])) {
            return true;
        }
        $found = $this->CI->db->query('SHOW TABLES LIKE ' . $this->CI->db->escape($table))->num_rows() > 0;
        if ($found) {
            $known[$table] = true;
        }
        return $found;
    }

    private function saved_rows() {
        if ($this->rows === null) {
            $this->rows = array();
            if ($this->has_table('ha_ai_provider')) {
                foreach ($this->CI->db->get('ha_ai_provider')->result_array() as $r) {
                    $this->rows[$r['slug']] = $r;
                }
            }
        }
        return $this->rows;
    }

    /**
     * Save an admin's settings for a provider. An empty api_key keeps the
     * stored one; the literal "__clear__" removes it.
     */
    public function save_provider($slug, array $input, $actor_id = null) {
        $slug = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $slug));
        if ($slug === '') {
            throw new InvalidArgumentException('Provider slug is required.');
        }
        $known = isset($this->registry[$slug]);
        if (!$known && empty($input['custom'])) {
            throw new InvalidArgumentException('Unknown provider: ' . $slug);
        }

        $base_url = isset($input['base_url']) ? trim((string) $input['base_url']) : '';
        if ($base_url !== '') {
            $base_url = rtrim($base_url, '/');
            if (!preg_match('#^https://#i', $base_url) && !$this->is_local_url($base_url)) {
                throw new InvalidArgumentException('Base URL must use https (http is allowed only for localhost).');
            }
            if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Base URL is not a valid URL.');
            }
        }

        $existing = $this->CI->db->get_where('ha_ai_provider', array('slug' => $slug))->row_array();
        $settings = $existing ? (json_decode((string) $existing['settings_json'], true) ?: array()) : array();
        foreach ((array) (isset($input['settings']) ? $input['settings'] : array()) as $k => $v) {
            $k = preg_replace('/[^a-z0-9_]/i', '', (string) $k);
            if ($k === '') {
                continue;
            }
            // Secret settings (AWS secret key and the like) follow the same
            // blank-keeps-it rule as the API key and are stored encrypted.
            if (substr($k, -7) === '_secret') {
                if ($v === '__clear__') {
                    unset($settings[$k]);
                } elseif ((string) $v !== '') {
                    $settings[$k] = $this->crypto->encrypt((string) $v);
                }
                continue;
            }
            $settings[$k] = is_string($v) ? trim($v) : $v;
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'enabled'       => empty($input['enabled']) ? 0 : 1,
            'region'        => isset($input['region']) && $input['region'] !== '' ? substr((string) $input['region'], 0, 60) : null,
            'base_url'      => $base_url !== '' ? $base_url : null,
            'settings_json' => $settings ? json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'updated_by'    => $actor_id,
            'updated_at'    => $now,
        );
        $key = isset($input['api_key']) ? trim((string) $input['api_key']) : '';
        if ($key === '__clear__') {
            $data['api_key_cipher'] = null;
            $data['api_key_hint'] = null;
        } elseif ($key !== '') {
            $data['api_key_cipher'] = $this->crypto->encrypt($key);
            $data['api_key_hint'] = Ha_crypto::hint($key);
        }

        if ($existing) {
            $this->CI->db->where('id', $existing['id'])->update('ha_ai_provider', $data);
        } else {
            $data['slug'] = $slug;
            $data['created_at'] = $now;
            $this->CI->db->insert('ha_ai_provider', $data);
        }
        $this->rows = null;
        return $this->provider($slug);
    }

    private function api_key(array $p) {
        $env = getenv('HA_AI_KEY_' . strtoupper(preg_replace('/[^a-z0-9]/i', '_', $p['slug'])));
        if ($env) {
            return $env;
        }
        $rows = $this->saved_rows();
        if (empty($rows[$p['slug']]['api_key_cipher'])) {
            return null;
        }
        return $this->crypto->decrypt($rows[$p['slug']]['api_key_cipher']);
    }

    private function secret_setting(array $p, $name) {
        if (empty($p['settings'][$name])) {
            return null;
        }
        return $this->crypto->decrypt($p['settings'][$name]);
    }

    /** Effective base URL: admin override, then chosen region, then the registry default. */
    public function endpoint(array $p) {
        if (!empty($p['base_url_override'])) {
            $url = $p['base_url_override'];
        } elseif (!empty($p['region']) && isset($p['regional_base_urls'][$p['region']])
                  && preg_match('#^https?://#', (string) $p['regional_base_urls'][$p['region']])) {
            $url = $p['regional_base_urls'][$p['region']];
        } else {
            $url = (string) $p['base_url'];
        }
        return rtrim($this->fill($url, $p), '/');
    }

    /**
     * Replace {placeholders} in registry URLs and headers ({region},
     * {resource}, {project}, {folder_id}, {account_id}, {version}) with the
     * admin's per-provider settings. {region} also falls back to the AWS
     * region field and to a region choice that is itself a region code.
     */
    public function fill($text, array $p) {
        return preg_replace_callback('/\{([a-z_]+)\}/i', function ($m) use ($p) {
            $name = $m[1];
            if (!empty($p['settings'][$name]) && substr($name, -7) !== '_secret') {
                return $p['settings'][$name];
            }
            if ($name === 'region') {
                if (!empty($p['settings']['aws_region'])) {
                    return $p['settings']['aws_region'];
                }
                if (!empty($p['region']) && preg_match('/^[a-z0-9\-]+$/', $p['region'])) {
                    return $p['region'];
                }
            }
            if ($name === 'version') {
                return '2024-10-08';
            }
            return $m[0];
        }, (string) $text);
    }

    /** Placeholders an admin must fill for this provider, found in its URLs and headers. */
    public function placeholders(array $p) {
        $haystack = json_encode(array($p['base_url'], $p['regional_base_urls'], $p['models_path'], $p['chat_path'], $p['auth']));
        preg_match_all('/\{([a-z_]+)\}/i', $haystack, $m);
        $skip = array('model', 'modelId', 'voice_id', 'operation_name', 'id', 'video_id', 'provider', 'app_name');
        return array_values(array_diff(array_unique($m[1]), $skip));
    }

    /** True when an endpoint still contains an unfilled placeholder. */
    private function unresolved($url) {
        return (bool) preg_match('/\{[a-z_]+\}/i', $url);
    }

    // ---------------------------------------------------- token exchanges

    /**
     * Providers whose API key is not the bearer credential: the key is
     * exchanged for a short-lived access token, cached encrypted in the
     * provider's settings until a minute before it expires.
     */
    private function access_token(array $p) {
        $slug = $p['slug'];
        $cache_key = '_token_cipher';
        if (!empty($p['settings'][$cache_key]) && !empty($p['settings']['_token_expires'])
            && (int) $p['settings']['_token_expires'] > time() + 60) {
            $cached = $this->crypto->decrypt($p['settings'][$cache_key]);
            if ($cached) {
                return $cached;
            }
        }
        $key = $this->api_key($p);
        if (!$key) {
            throw new RuntimeException($p['name'] . ' has no credential saved.');
        }

        if ($slug === 'watsonx') {
            $res = $this->request('POST', 'https://iam.cloud.ibm.com/identity/token',
                array('Content-Type: application/x-www-form-urlencoded'),
                http_build_query(array('grant_type' => 'urn:ibm:params:oauth:grant-type:apikey', 'apikey' => $key)));
            $data = $this->decode($res, $p);
            $token = $data['access_token'];
            $expires = time() + (int) (isset($data['expires_in']) ? $data['expires_in'] : 3600);
        } elseif ($slug === 'gigachat') {
            $uuid = vsprintf('%s%s-%s-4%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
            $this->cainfo = !empty($p['settings']['ca_bundle']) ? $p['settings']['ca_bundle'] : null;
            $res = $this->request('POST', 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth', array(
                'Content-Type: application/x-www-form-urlencoded', 'Authorization: Basic ' . $key, 'RqUID: ' . $uuid,
            ), http_build_query(array('scope' => !empty($p['settings']['scope']) ? $p['settings']['scope'] : 'GIGACHAT_API_PERS')));
            $data = $this->decode($res, $p);
            $token = $data['access_token'];
            $expires = isset($data['expires_at']) ? (int) floor($data['expires_at'] / 1000) : time() + 1800;
        } elseif (in_array($slug, array('vertex_ai', 'google_tts'), true)) {
            $token_data = $this->google_service_account_token($key, $p);
            $token = $token_data['access_token'];
            $expires = time() + (int) $token_data['expires_in'];
        } else {
            return $key;
        }

        $settings = $p['settings'];
        $settings[$cache_key] = $this->crypto->encrypt($token);
        $settings['_token_expires'] = $expires;
        $this->CI->db->where('slug', $slug)->update('ha_ai_provider',
            array('settings_json' => json_encode($settings, JSON_UNESCAPED_SLASHES)));
        $this->rows = null;
        return $token;
    }

    /** OAuth 2.0 JWT bearer grant with a Google service account key (the "API key" field holds the JSON). */
    private function google_service_account_token($json, array $p) {
        $sa = json_decode($json, true);
        if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new RuntimeException($p['name'] . ' needs a service account JSON key pasted into the API key field.');
        }
        $b64 = function ($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); };
        $aud = isset($sa['token_uri']) ? $sa['token_uri'] : 'https://oauth2.googleapis.com/token';
        $now = time();
        $unsigned = $b64(json_encode(array('alg' => 'RS256', 'typ' => 'JWT'))) . '.' . $b64(json_encode(array(
            'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => $aud, 'iat' => $now, 'exp' => $now + 3600,
        )));
        $sig = '';
        if (!openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign with the service account key.');
        }
        $res = $this->request('POST', $aud, array('Content-Type: application/x-www-form-urlencoded'), http_build_query(array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . $b64($sig),
        )));
        return $this->decode($res, $p);
    }

    /** The credential to send: exchanged token where the provider needs one, else the key itself. */
    public function bearer_for(array $p) {
        if (in_array($p['slug'], array('watsonx', 'gigachat', 'vertex_ai'), true)) {
            return $this->access_token($p);
        }
        if ($p['slug'] === 'google_tts') {
            $key = (string) $this->api_key($p);
            return strpos(ltrim($key), '{') === 0 ? $this->access_token($p) : null;
        }
        return $this->api_key($p);
    }

    /** One-shot CA bundle for providers that chain to a national root (GigaChat). */
    private $cainfo = null;

    private function is_local_url($url) {
        $host = parse_url((string) $url, PHP_URL_HOST);
        return in_array($host, array('localhost', '127.0.0.1', '::1', 'host.docker.internal'), true);
    }

    // =============================================================== routes

    public function routes() {
        $out = array();
        foreach ($this->CI->db->get('ha_ai_route')->result_array() as $r) {
            $out[$r['task']] = $r;
        }
        return $out;
    }

    public function route($task) {
        $r = $this->CI->db->get_where('ha_ai_route', array('task' => $task))->row_array();
        return $r ?: null;
    }

    public function set_route($task, $provider_slug, $model_id, array $opts = array(), $actor_id = null) {
        if (!isset($this->tasks[$task])) {
            throw new InvalidArgumentException('Unknown task: ' . $task);
        }
        if ($provider_slug === '' || $provider_slug === null) {
            $this->CI->db->where('task', $task)->delete('ha_ai_route');
            return null;
        }
        $p = $this->provider($provider_slug);
        if (!$p) {
            throw new InvalidArgumentException('Unknown provider: ' . $provider_slug);
        }
        $kind = $this->tasks[$task]['kind'];
        $need = array('text' => 'chat', 'tts' => 'tts', 'avatar' => 'video', 'clip' => 'video');
        if (!in_array($need[$kind], $p['capabilities'], true)) {
            throw new InvalidArgumentException($p['name'] . ' does not offer ' . $need[$kind] . ', which the task "' . $task . '" needs.');
        }
        if ($kind !== 'text') {
            require_once APPPATH . 'libraries/Ha_ai_media.php';
            if (!Ha_ai_media::supports($p, $kind)) {
                throw new InvalidArgumentException($p['name'] . ' offers ' . $need[$kind] . ', but AI Studio has no ' . $kind
                    . ' adapter for it yet. Supported: ' . implode(', ', Ha_ai_media::supported_list($kind)) . '.');
            }
        }
        $model_id = trim((string) $model_id);
        if ($model_id === '') {
            throw new InvalidArgumentException('Choose a model for ' . $task . '.');
        }
        $row = array(
            'task' => $task, 'provider_slug' => $provider_slug, 'model_id' => substr($model_id, 0, 190),
            'temperature' => isset($opts['temperature']) && $opts['temperature'] !== '' ? max(0, min(2, (float) $opts['temperature'])) : null,
            'max_tokens' => isset($opts['max_tokens']) && $opts['max_tokens'] !== '' ? max(1, (int) $opts['max_tokens']) : null,
            'options_json' => !empty($opts['options']) ? json_encode($opts['options'], JSON_UNESCAPED_UNICODE) : null,
            'updated_by' => $actor_id, 'updated_at' => date('Y-m-d H:i:s'),
        );
        if ($this->route($task)) {
            $this->CI->db->where('task', $task)->update('ha_ai_route', $row);
        } else {
            $this->CI->db->insert('ha_ai_route', $row);
        }
        return $this->route($task);
    }

    /** @return array(provider, model, route) ready to call, or throws with a message an admin can act on. */
    public function resolve($task) {
        $route = $this->route($task);
        if (!$route) {
            throw new RuntimeException('No model is assigned to "' . $task . '". Set one under AI Studio → Task routing.');
        }
        $p = $this->provider($route['provider_slug']);
        if (!$p) {
            throw new RuntimeException('The provider "' . $route['provider_slug'] . '" routed for ' . $task . ' no longer exists.');
        }
        if (!$p['enabled']) {
            throw new RuntimeException($p['name'] . ' is switched off. Enable it under AI Studio → Providers.');
        }
        if ($p['needs_key'] && !$p['has_key'] && $p['api_style'] !== 'bedrock') {
            throw new RuntimeException($p['name'] . ' has no API key saved.');
        }
        return array($p, $route['model_id'], $route);
    }

    // ================================================================= chat

    /**
     * @param string $task     a task name from config/ha_ai.php
     * @param array  $messages list of array('role' => system|user|assistant, 'content' => string)
     * @return array('text', 'input_tokens', 'output_tokens', 'provider', 'model', 'latency_ms')
     */
    public function chat($task, array $messages, array $opts = array()) {
        list($p, $model, $route) = $this->resolve($task);
        $cfg = isset($this->tasks[$task]) ? $this->tasks[$task] : array();
        $opts += array(
            'temperature' => $route['temperature'] !== null ? (float) $route['temperature'] : (isset($cfg['temperature']) ? $cfg['temperature'] : 0.5),
            'max_tokens'  => $route['max_tokens'] !== null ? (int) $route['max_tokens'] : (isset($cfg['max_tokens']) ? $cfg['max_tokens'] : 2000),
            'json'        => !empty($cfg['json']),
        );
        return $this->chat_with($p, $model, $messages, $opts, $task);
    }

    /** Direct call against a named provider and model (used by "test" and by the assistant playground). */
    public function chat_with(array $p, $model, array $messages, array $opts = array(), $task = null) {
        $opts += array('temperature' => 0.5, 'max_tokens' => 1024, 'json' => false);
        if (!in_array('chat', $p['capabilities'], true)) {
            throw new RuntimeException($p['name'] . ' does not offer chat.');
        }
        if ($this->unresolved($this->endpoint($p)) || $this->endpoint($p) === '') {
            throw new RuntimeException($p['name'] . ' needs its endpoint settings filled in first (' . implode(', ', $this->placeholders($p)) . ').');
        }
        $style = $p['api_style'];
        if ($p['slug'] === 'watsonx') {
            $style = 'watsonx';
        }
        $method = 'chat_' . ($style === 'local' || $style === 'custom' ? 'openai' : $style);
        if (!method_exists($this, $method)) {
            throw new RuntimeException('No chat adapter for API style "' . $style . '" (' . $p['name'] . ').');
        }
        $started = microtime(true);
        try {
            $result = $this->$method($p, $model, $messages, $opts);
        } catch (Exception $e) {
            $this->log_usage($p['slug'], $model, $task, 0, 0, 0, (int) ((microtime(true) - $started) * 1000), $this->last_status, false, $e->getMessage());
            throw $e;
        }
        $result['latency_ms'] = (int) ((microtime(true) - $started) * 1000);
        $result['provider'] = $p['slug'];
        $result['model'] = $model;
        $this->log_usage($p['slug'], $model, $task, $result['input_tokens'], $result['output_tokens'], 0, $result['latency_ms'], 200, true, null);
        return $result;
    }

    /**
     * Chat that must come back as a JSON object. Models wrap JSON in prose or
     * code fences often enough that parsing strictly would fail real jobs, so
     * the object is extracted; if that still fails the model is asked once to
     * repair its own output before the job is failed.
     */
    public function chat_json($task, $system, $user, array $opts = array()) {
        $messages = array(
            array('role' => 'system', 'content' => $system . "\n\nRespond with a single JSON object only. No prose, no markdown fences."),
            array('role' => 'user', 'content' => $user),
        );
        $res = $this->chat($task, $messages, $opts);
        $data = self::extract_json($res['text']);
        if ($data === null) {
            $messages[] = array('role' => 'assistant', 'content' => $res['text']);
            $messages[] = array('role' => 'user', 'content' => 'That was not valid JSON. Return the same content as one valid JSON object and nothing else.');
            $res2 = $this->chat($task, $messages, $opts);
            $data = self::extract_json($res2['text']);
            $res['input_tokens'] += $res2['input_tokens'];
            $res['output_tokens'] += $res2['output_tokens'];
            if ($data === null) {
                throw new RuntimeException('The model did not return valid JSON after a repair attempt.');
            }
        }
        $res['data'] = $data;
        return $res;
    }

    public static function extract_json($text) {
        $text = trim((string) $text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    // ------------------------------------------------------------ adapters

    private function split_system(array $messages) {
        $system = array();
        $rest = array();
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system[] = $m['content'];
            } else {
                $rest[] = $m;
            }
        }
        return array(implode("\n\n", $system), $rest);
    }

    private function auth_headers(array $p, $key) {
        $h = array();
        $auth = (array) $p['auth'];
        $header = isset($auth['header']) ? $auth['header'] : 'Authorization';
        $prefix = isset($auth['prefix']) ? $auth['prefix'] : ($header === 'Authorization' ? 'Bearer ' : '');
        if ($key !== null && $key !== '') {
            $h[] = $header . ': ' . $prefix . $key;
        }
        foreach ((array) (isset($auth['extra_headers']) ? $auth['extra_headers'] : array()) as $k => $v) {
            if (!is_int($k) && strtolower($k) === 'content-type') {
                continue;   // set by the adapter for the body it actually sends
            }
            $line = $this->fill(is_int($k) ? $v : ($k . ': ' . $v), $p);
            if (!$this->unresolved($line)) {
                $h[] = $line;
            }
        }
        return $h;
    }

    private function chat_openai(array $p, $model, array $messages, array $opts) {
        $base = $this->endpoint($p);
        $path = $p['chat_path'] ?: '/chat/completions';
        $body = array(
            'model'    => $model,
            'messages' => array_values($messages),
        );
        // Reasoning models reject temperature and max_tokens; they take
        // max_completion_tokens. Sending both breaks older gateways, so the
        // newer field is used only where the model name says it is needed.
        if (preg_match('/^(o\d|gpt-5)/i', $model) && in_array($p['slug'], array('openai', 'azure_openai'), true)) {
            $body['max_completion_tokens'] = (int) $opts['max_tokens'];
        } else {
            $body['max_tokens'] = (int) $opts['max_tokens'];
            $body['temperature'] = (float) $opts['temperature'];
        }
        if ($opts['json'] && !empty($p['settings']['json_mode'])) {
            $body['response_format'] = array('type' => 'json_object');
        }
        $headers = array_merge(array('Content-Type: application/json'), $this->auth_headers($p, $this->bearer_for($p)));
        if ($p['slug'] === 'openrouter') {
            $headers[] = 'HTTP-Referer: ' . base_url();
            $headers[] = 'X-OpenRouter-Title: Hospitality Academy';
        }
        if ($p['slug'] === 'gigachat' && !empty($p['settings']['ca_bundle'])) {
            $this->cainfo = $p['settings']['ca_bundle'];
        }
        if (!empty($p['settings']['organization'])) {
            $headers[] = 'OpenAI-Organization: ' . $p['settings']['organization'];
        }
        $res = $this->request('POST', $base . $path, $headers, $body);
        $data = $this->decode($res, $p);
        $text = '';
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            $text = is_array($content) ? implode('', array_column($content, 'text')) : (string) $content;
        }
        return array(
            'text' => $text,
            'input_tokens' => isset($data['usage']['prompt_tokens']) ? (int) $data['usage']['prompt_tokens'] : 0,
            'output_tokens' => isset($data['usage']['completion_tokens']) ? (int) $data['usage']['completion_tokens'] : 0,
        );
    }

    private function chat_azure_openai(array $p, $model, array $messages, array $opts) {
        // Azure's v1 surface is OpenAI-compatible with an api-key header and
        // the deployment name in "model". Older resources still need the
        // dated api-version path, selectable per provider.
        $endpoint = preg_replace('#/openai/v1$#', '', $this->endpoint($p));
        $version = !empty($p['settings']['api_version']) ? $p['settings']['api_version'] : 'v1';
        $url = $version === 'v1'
            ? $endpoint . '/openai/v1/chat/completions'
            : $endpoint . '/openai/deployments/' . rawurlencode($model) . '/chat/completions?api-version=' . rawurlencode($version);
        $body = array('model' => $model, 'messages' => array_values($messages));
        if (preg_match('/^(o\d|gpt-5)/i', $model)) {
            $body['max_completion_tokens'] = (int) $opts['max_tokens'];
        } else {
            $body['max_tokens'] = (int) $opts['max_tokens'];
            $body['temperature'] = (float) $opts['temperature'];
        }
        $res = $this->request('POST', $url, array('Content-Type: application/json', 'api-key: ' . $this->api_key($p)), $body);
        $data = $this->decode($res, $p);
        return array(
            'text' => isset($data['choices'][0]['message']['content']) ? (string) $data['choices'][0]['message']['content'] : '',
            'input_tokens' => isset($data['usage']['prompt_tokens']) ? (int) $data['usage']['prompt_tokens'] : 0,
            'output_tokens' => isset($data['usage']['completion_tokens']) ? (int) $data['usage']['completion_tokens'] : 0,
        );
    }

    private function chat_anthropic(array $p, $model, array $messages, array $opts) {
        list($system, $rest) = $this->split_system($messages);
        $body = array(
            'model' => $model,
            'max_tokens' => (int) $opts['max_tokens'],
            'temperature' => min(1.0, (float) $opts['temperature']),
            'messages' => array_map(function ($m) {
                return array('role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $m['content']);
            }, $rest),
        );
        if ($system !== '') {
            $body['system'] = $system;
        }
        $headers = array(
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key($p),
            'anthropic-version: 2023-06-01',
        );
        $res = $this->request('POST', $this->endpoint($p) . '/v1/messages', $headers, $body);
        $data = $this->decode($res, $p);
        $text = '';
        foreach ((array) (isset($data['content']) ? $data['content'] : array()) as $block) {
            if (isset($block['type']) && $block['type'] === 'text') {
                $text .= $block['text'];
            }
        }
        if (isset($data['stop_reason']) && $data['stop_reason'] === 'max_tokens') {
            throw new RuntimeException('The model stopped at the max_tokens limit (' . $opts['max_tokens'] . '). Raise it on the task route.');
        }
        return array(
            'text' => $text,
            'input_tokens' => isset($data['usage']['input_tokens']) ? (int) $data['usage']['input_tokens'] : 0,
            'output_tokens' => isset($data['usage']['output_tokens']) ? (int) $data['usage']['output_tokens'] : 0,
        );
    }

    private function chat_gemini(array $p, $model, array $messages, array $opts) {
        list($system, $rest) = $this->split_system($messages);
        $contents = array();
        foreach ($rest as $m) {
            $contents[] = array('role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => array(array('text' => $m['content'])));
        }
        $body = array(
            'contents' => $contents,
            'generationConfig' => array('temperature' => (float) $opts['temperature'], 'maxOutputTokens' => (int) $opts['max_tokens']),
        );
        if ($opts['json']) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }
        if ($system !== '') {
            $body['systemInstruction'] = array('parts' => array(array('text' => $system)));
        }
        $model = preg_replace('#^models/#', '', $model);
        if ($p['slug'] === 'vertex_ai') {
            // Vertex: project/location scoped URL, OAuth token from a service account.
            $url = $this->endpoint($p) . '/publishers/google/models/' . rawurlencode($model) . ':generateContent';
            $auth = 'Authorization: Bearer ' . $this->access_token($p);
        } else {
            $url = $this->gemini_root($p) . '/v1beta/models/' . rawurlencode($model) . ':generateContent';
            $auth = 'x-goog-api-key: ' . $this->api_key($p);
        }
        $res = $this->request('POST', $url, array('Content-Type: application/json', $auth), $body);
        $data = $this->decode($res, $p);
        $text = '';
        foreach ((array) (isset($data['candidates'][0]['content']['parts']) ? $data['candidates'][0]['content']['parts'] : array()) as $part) {
            if (isset($part['text']) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }
        if ($text === '' && isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] !== 'STOP') {
            throw new RuntimeException('Gemini returned no text (finishReason ' . $data['candidates'][0]['finishReason'] . ').');
        }
        return array(
            'text' => $text,
            'input_tokens' => isset($data['usageMetadata']['promptTokenCount']) ? (int) $data['usageMetadata']['promptTokenCount'] : 0,
            'output_tokens' => isset($data['usageMetadata']['candidatesTokenCount']) ? (int) $data['usageMetadata']['candidatesTokenCount'] : 0,
        );
    }

    /** Gemini API registry URLs end in /v1beta; adapters add the version themselves. */
    private function gemini_root(array $p) {
        return preg_replace('#/v1(beta)?$#', '', $this->endpoint($p));
    }

    /** IBM watsonx.ai: OpenAI-shaped messages, IAM bearer token, project_id in the body. */
    private function chat_watsonx(array $p, $model, array $messages, array $opts) {
        if (empty($p['settings']['project_id']) && empty($p['settings']['space_id'])) {
            throw new RuntimeException('watsonx.ai needs a project_id (or space_id) in the provider settings.');
        }
        $version = !empty($p['settings']['version']) ? $p['settings']['version'] : '2024-10-08';
        $body = array(
            'model_id' => $model,
            'messages' => array_values($messages),
            'max_tokens' => (int) $opts['max_tokens'],
            'temperature' => (float) $opts['temperature'],
        );
        if (!empty($p['settings']['project_id'])) {
            $body['project_id'] = $p['settings']['project_id'];
        } else {
            $body['space_id'] = $p['settings']['space_id'];
        }
        $res = $this->request('POST', $this->endpoint($p) . '/ml/v1/text/chat?version=' . rawurlencode($version),
            array('Content-Type: application/json', 'Authorization: Bearer ' . $this->access_token($p)), $body);
        $data = $this->decode($res, $p);
        return array(
            'text' => isset($data['choices'][0]['message']['content']) ? (string) $data['choices'][0]['message']['content'] : '',
            'input_tokens' => isset($data['usage']['prompt_tokens']) ? (int) $data['usage']['prompt_tokens'] : 0,
            'output_tokens' => isset($data['usage']['completion_tokens']) ? (int) $data['usage']['completion_tokens'] : 0,
        );
    }

    private function chat_cohere(array $p, $model, array $messages, array $opts) {
        $body = array(
            'model' => $model,
            'messages' => array_values($messages),
            'temperature' => (float) $opts['temperature'],
            'max_tokens' => (int) $opts['max_tokens'],
        );
        if ($opts['json']) {
            $body['response_format'] = array('type' => 'json_object');
        }
        $res = $this->request('POST', $this->endpoint($p) . '/v2/chat',
            array('Content-Type: application/json', 'Authorization: Bearer ' . $this->api_key($p)), $body);
        $data = $this->decode($res, $p);
        $text = '';
        foreach ((array) (isset($data['message']['content']) ? $data['message']['content'] : array()) as $c) {
            if (isset($c['text'])) {
                $text .= $c['text'];
            }
        }
        return array(
            'text' => $text,
            'input_tokens' => isset($data['usage']['tokens']['input_tokens']) ? (int) $data['usage']['tokens']['input_tokens'] : 0,
            'output_tokens' => isset($data['usage']['tokens']['output_tokens']) ? (int) $data['usage']['tokens']['output_tokens'] : 0,
        );
    }

    /**
     * Bedrock Converse API. Authenticates with a Bedrock API key (bearer) when
     * one is saved, otherwise with an IAM access key pair signed with SigV4.
     */
    private function chat_bedrock(array $p, $model, array $messages, array $opts) {
        list($system, $rest) = $this->split_system($messages);
        $region = $p['region'] ?: (isset($p['settings']['aws_region']) ? $p['settings']['aws_region'] : 'us-east-1');
        $host = 'bedrock-runtime.' . $region . '.amazonaws.com';
        $path = '/model/' . rawurlencode($model) . '/converse';
        $body = array(
            'messages' => array_map(function ($m) {
                return array('role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => array(array('text' => $m['content'])));
            }, $rest),
            'inferenceConfig' => array('maxTokens' => (int) $opts['max_tokens'], 'temperature' => min(1.0, (float) $opts['temperature'])),
        );
        if ($system !== '') {
            $body['system'] = array(array('text' => $system));
        }
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers = $this->bedrock_headers($p, 'POST', $host, $path, $json, $region, 'bedrock');
        $res = $this->request('POST', 'https://' . $host . $path, $headers, $json);
        $data = $this->decode($res, $p);
        $text = '';
        foreach ((array) (isset($data['output']['message']['content']) ? $data['output']['message']['content'] : array()) as $c) {
            if (isset($c['text'])) {
                $text .= $c['text'];
            }
        }
        return array(
            'text' => $text,
            'input_tokens' => isset($data['usage']['inputTokens']) ? (int) $data['usage']['inputTokens'] : 0,
            'output_tokens' => isset($data['usage']['outputTokens']) ? (int) $data['usage']['outputTokens'] : 0,
        );
    }

    private function bedrock_headers(array $p, $method, $host, $path, $payload, $region, $service) {
        $bearer = $this->api_key($p);
        if ($bearer) {
            return array('Content-Type: application/json', 'Authorization: Bearer ' . $bearer);
        }
        $access = isset($p['settings']['aws_access_key_id']) ? $p['settings']['aws_access_key_id'] : null;
        $secret = $this->secret_setting($p, 'aws_access_key_secret');
        if (!$access || !$secret) {
            throw new RuntimeException('Amazon Bedrock needs either a Bedrock API key or an IAM access key id and secret.');
        }
        return self::sigv4($method, $host, $path, '', $payload, $region, $service, $access, $secret,
            isset($p['settings']['aws_session_token']) ? $p['settings']['aws_session_token'] : null);
    }

    /** AWS Signature Version 4 for a JSON request. */
    public static function sigv4($method, $host, $path, $query, $payload, $region, $service, $access, $secret, $token = null, $time = null) {
        $time = $time === null ? time() : $time;
        $amz_date = gmdate('Ymd\THis\Z', $time);
        $date = gmdate('Ymd', $time);
        $payload_hash = hash('sha256', (string) $payload);
        $canonical_uri = implode('/', array_map(function ($seg) {
            return rawurlencode(rawurldecode($seg));
        }, explode('/', $path)));

        $signed = array('content-type' => 'application/json', 'host' => $host, 'x-amz-date' => $amz_date);
        if ($token) {
            $signed['x-amz-security-token'] = $token;
        }
        ksort($signed);
        $canonical_headers = '';
        foreach ($signed as $k => $v) {
            $canonical_headers .= $k . ':' . trim($v) . "\n";
        }
        $signed_list = implode(';', array_keys($signed));
        $canonical = implode("\n", array($method, $canonical_uri, $query, $canonical_headers, $signed_list, $payload_hash));
        $scope = $date . '/' . $region . '/' . $service . '/aws4_request';
        $to_sign = "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $scope . "\n" . hash('sha256', $canonical);

        $k = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $k = hash_hmac('sha256', $region, $k, true);
        $k = hash_hmac('sha256', $service, $k, true);
        $k = hash_hmac('sha256', 'aws4_request', $k, true);
        $signature = hash_hmac('sha256', $to_sign, $k);

        $headers = array(
            'Content-Type: application/json',
            'X-Amz-Date: ' . $amz_date,
            'Authorization: AWS4-HMAC-SHA256 Credential=' . $access . '/' . $scope . ', SignedHeaders=' . $signed_list . ', Signature=' . $signature,
        );
        if ($token) {
            $headers[] = 'X-Amz-Security-Token: ' . $token;
        }
        return $headers;
    }

    // =============================================================== models

    /**
     * Ask the provider which models it offers right now and cache the answer.
     * Returns the list; throws with the provider's own error on failure.
     */
    public function sync_models($slug) {
        $p = $this->provider($slug);
        if (!$p) {
            throw new InvalidArgumentException('Unknown provider: ' . $slug);
        }
        $models = $this->fetch_models($p);
        $now = date('Y-m-d H:i:s');

        $this->CI->db->where('provider_slug', $slug)->update('ha_ai_model', array('is_available' => 0));
        foreach ($models as $m) {
            $row = array(
                'provider_slug' => $slug,
                'model_id' => substr($m['id'], 0, 190),
                'label' => isset($m['label']) ? substr((string) $m['label'], 0, 255) : null,
                'owned_by' => isset($m['owned_by']) ? substr((string) $m['owned_by'], 0, 190) : null,
                'context_tokens' => isset($m['context']) ? (int) $m['context'] : null,
                'capabilities' => isset($m['capabilities']) ? substr(implode(',', (array) $m['capabilities']), 0, 255) : null,
                'is_available' => 1,
                'fetched_at' => $now,
            );
            $exists = $this->CI->db->where(array('provider_slug' => $slug, 'model_id' => $row['model_id']))->count_all_results('ha_ai_model');
            if ($exists) {
                $this->CI->db->where(array('provider_slug' => $slug, 'model_id' => $row['model_id']))->update('ha_ai_model', $row);
            } else {
                $this->CI->db->insert('ha_ai_model', $row);
            }
        }
        $this->CI->db->where('slug', $slug)->update('ha_ai_provider', array('models_synced_at' => $now));
        $this->rows = null;
        return $models;
    }

    /** Cached models, falling back to the registry's defaults when never synced. */
    public function models($slug, $available_only = true) {
        if ($available_only) {
            $this->CI->db->where('is_available', 1);
        }
        $rows = $this->CI->db->where('provider_slug', $slug)->order_by('model_id', 'ASC')->get('ha_ai_model')->result_array();
        if ($rows) {
            return $rows;
        }
        $p = $this->provider($slug);
        $out = array();
        foreach ((array) ($p ? $p['default_models'] : array()) as $id) {
            $out[] = array('model_id' => $id, 'label' => $id . ' (registry default)', 'owned_by' => null, 'context_tokens' => null, 'capabilities' => null);
        }
        return $out;
    }

    private function fetch_models(array $p) {
        $base = $this->endpoint($p);
        if ($base === '' || $this->unresolved($base)) {
            throw new RuntimeException($p['name'] . ' needs its endpoint settings filled in first (' . implode(', ', $this->placeholders($p)) . ').');
        }
        $key = $this->api_key($p);
        if ($p['slug'] === 'watsonx') {
            $version = !empty($p['settings']['version']) ? $p['settings']['version'] : '2024-10-08';
            $data = $this->decode($this->request('GET', $base . '/ml/v1/foundation_model_specs?version=' . rawurlencode($version) . '&limit=200&filters=function_text_chat',
                array('Authorization: Bearer ' . $this->access_token($p))), $p);
            $out = array();
            foreach ((array) (isset($data['resources']) ? $data['resources'] : array()) as $m) {
                $out[] = array('id' => $m['model_id'], 'label' => isset($m['label']) ? $m['label'] : $m['model_id'], 'owned_by' => isset($m['provider']) ? $m['provider'] : null);
            }
            return $out;
        }
        if ($p['slug'] === 'vertex_ai') {
            throw new RuntimeException('Vertex AI has no key-scoped model list. Type the model id (e.g. ' . (isset($p['default_models'][0]) ? $p['default_models'][0] : 'gemini model id') . ') on the task route.');
        }
        switch ($p['api_style']) {
            case 'anthropic':
                $out = array();
                $after = null;
                do {
                    $url = $base . '/v1/models?limit=1000' . ($after ? '&after_id=' . rawurlencode($after) : '');
                    $data = $this->decode($this->request('GET', $url, array('x-api-key: ' . $key, 'anthropic-version: 2023-06-01')), $p);
                    foreach ((array) (isset($data['data']) ? $data['data'] : array()) as $m) {
                        $out[] = array('id' => $m['id'], 'label' => isset($m['display_name']) ? $m['display_name'] : $m['id'], 'owned_by' => 'anthropic');
                    }
                    $after = !empty($data['has_more']) ? $data['last_id'] : null;
                } while ($after);
                return $out;

            case 'gemini':
                $out = array();
                $token = null;
                do {
                    $url = $this->gemini_root($p) . '/v1beta/models?pageSize=1000' . ($token ? '&pageToken=' . rawurlencode($token) : '');
                    $data = $this->decode($this->request('GET', $url, array('x-goog-api-key: ' . $key)), $p);
                    foreach ((array) (isset($data['models']) ? $data['models'] : array()) as $m) {
                        $methods = isset($m['supportedGenerationMethods']) ? $m['supportedGenerationMethods'] : array();
                        $out[] = array(
                            'id' => preg_replace('#^models/#', '', $m['name']),
                            'label' => isset($m['displayName']) ? $m['displayName'] : $m['name'],
                            'owned_by' => 'google',
                            'context' => isset($m['inputTokenLimit']) ? $m['inputTokenLimit'] : null,
                            'capabilities' => $methods,
                        );
                    }
                    $token = isset($data['nextPageToken']) ? $data['nextPageToken'] : null;
                } while ($token);
                return $out;

            case 'cohere':
                $data = $this->decode($this->request('GET', $base . '/v1/models?page_size=1000', array('Authorization: Bearer ' . $key)), $p);
                $out = array();
                foreach ((array) (isset($data['models']) ? $data['models'] : array()) as $m) {
                    $out[] = array('id' => $m['name'], 'label' => $m['name'], 'owned_by' => 'cohere',
                        'context' => isset($m['context_length']) ? $m['context_length'] : null,
                        'capabilities' => isset($m['endpoints']) ? $m['endpoints'] : null);
                }
                return $out;

            case 'azure_openai':
                // With a key, Azure lists the base models available to the
                // resource; deployments (what "model" actually names) are an
                // ARM call. Deployment names are typed in by the admin.
                $data = $this->decode($this->request('GET', preg_replace('#/openai/v1$#', '', $base) . '/openai/v1/models', array('api-key: ' . $key)), $p);
                return $this->openai_model_list($data);

            case 'bedrock':
                $region = $p['region'] ?: (isset($p['settings']['aws_region']) ? $p['settings']['aws_region'] : 'us-east-1');
                $host = 'bedrock.' . $region . '.amazonaws.com';
                $headers = $this->bedrock_headers($p, 'GET', $host, '/foundation-models', '', $region, 'bedrock');
                $data = $this->decode($this->request('GET', 'https://' . $host . '/foundation-models', $headers), $p);
                $out = array();
                foreach ((array) (isset($data['modelSummaries']) ? $data['modelSummaries'] : array()) as $m) {
                    $out[] = array('id' => $m['modelId'], 'label' => (isset($m['providerName']) ? $m['providerName'] . ' ' : '') . (isset($m['modelName']) ? $m['modelName'] : $m['modelId']),
                        'owned_by' => isset($m['providerName']) ? $m['providerName'] : null,
                        'capabilities' => isset($m['outputModalities']) ? $m['outputModalities'] : null);
                }
                return $out;

            default:
                if (empty($p['models_path']) && $p['api_style'] !== 'openai' && $p['api_style'] !== 'local' && $p['api_style'] !== 'custom') {
                    throw new RuntimeException($p['name'] . ' publishes no model-list endpoint. Type the model id on the task route.');
                }
                $path = $p['models_path'] ?: '/models';
                $url = preg_match('#^https?://#', $path) ? $this->fill($path, $p) : $base . $path;
                if ($this->unresolved($url)) {
                    throw new RuntimeException($p['name'] . ' model list needs: ' . implode(', ', $this->placeholders($p)) . '.');
                }
                if ($p['slug'] === 'gigachat' && !empty($p['settings']['ca_bundle'])) {
                    $this->cainfo = $p['settings']['ca_bundle'];
                }
                $headers = $this->auth_headers($p, $this->bearer_for($p));
                $data = $this->decode($this->request('GET', $url, $headers), $p);
                return $this->openai_model_list($data);
        }
    }

    private function openai_model_list($data) {
        $list = isset($data['data']) ? $data['data'] : (isset($data['models']) ? $data['models'] : (array_values($data) === $data ? $data : array()));
        $out = array();
        foreach ((array) $list as $m) {
            if (is_string($m)) {
                $out[] = array('id' => $m, 'label' => $m);
                continue;
            }
            $id = isset($m['id']) ? $m['id'] : (isset($m['name']) ? $m['name'] : (isset($m['model']) ? $m['model'] : null));
            if (!$id) {
                continue;
            }
            $out[] = array(
                'id' => $id,
                'label' => isset($m['name']) && $m['name'] !== $id ? $m['name'] : $id,
                'owned_by' => isset($m['owned_by']) ? $m['owned_by'] : null,
                'context' => isset($m['context_length']) ? $m['context_length'] : (isset($m['context_window']) ? $m['context_window'] : null),
            );
        }
        return $out;
    }

    /** Connectivity check an admin can run from the provider page. */
    public function test($slug) {
        $p = $this->provider($slug);
        if (!$p) {
            throw new InvalidArgumentException('Unknown provider: ' . $slug);
        }
        $ok = false;
        $error = null;
        $detail = null;
        try {
            if (in_array('chat', $p['capabilities'], true)) {
                try {
                    $models = $this->sync_models($slug);
                    $detail = count($models) . ' models available';
                    $ok = true;
                } catch (Exception $e) {
                    // No list endpoint: prove the key with a one-token call on the default model.
                    $model = !empty($p['default_models']) ? $p['default_models'][0] : null;
                    if (!$model) {
                        throw $e;
                    }
                    $this->chat_with($p, $model, array(array('role' => 'user', 'content' => 'Reply with OK.')), array('max_tokens' => 5, 'temperature' => 0));
                    $detail = 'Key accepted by ' . $model;
                    $ok = true;
                }
            } else {
                $this->CI->load->library('ha_ai_media');
                $detail = $this->CI->ha_ai_media->test($p);
                $ok = true;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        $this->CI->db->where('slug', $slug)->update('ha_ai_provider', array(
            'last_tested_at' => date('Y-m-d H:i:s'),
            'last_test_ok' => $ok ? 1 : 0,
            'last_error' => $error ? substr($error, 0, 500) : null,
        ));
        $this->rows = null;
        return array('ok' => $ok, 'detail' => $detail, 'error' => $error);
    }

    // ============================================================ transport

    public $last_status = null;

    /**
     * @param string|array|null $body arrays are JSON encoded
     * @return array('status', 'body', 'headers', 'error')
     */
    public function request($method, $url, array $headers = array(), $body = null, $timeout = null, $follow = false) {
        $timeout = $timeout ?: (isset($this->limits['http_timeout_seconds']) ? (int) $this->limits['http_timeout_seconds'] : 180);
        $ch = curl_init($url);
        $response_headers = array();
        $opts = array(
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => array_merge(array('Accept: application/json', 'User-Agent: HospitalityAcademy-AIStudio/1.0'), $headers),
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$response_headers) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
            // API calls never follow redirects: a provider that redirects an
            // API call is misconfigured, and following it could forward the
            // key. File downloads opt in, because signed media URLs redirect.
            CURLOPT_FOLLOWLOCATION => (bool) $follow,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        );
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $body;
        }
        if ($this->cainfo && is_file($this->cainfo)) {
            $opts[CURLOPT_CAINFO] = $this->cainfo;
        } elseif (!ini_get('curl.cainfo') && is_file(APPPATH . 'third_party/cacert.pem')) {
            $opts[CURLOPT_CAINFO] = APPPATH . 'third_party/cacert.pem';
        }
        $this->cainfo = null;
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $this->last_status = $status;
        if ($raw === false) {
            throw new RuntimeException('Could not reach ' . parse_url($url, PHP_URL_HOST) . ': ' . $error);
        }
        return array('status' => $status, 'body' => (string) $raw, 'headers' => $response_headers, 'error' => $error);
    }

    /** Decode JSON, turning provider error bodies into one readable message. */
    public function decode(array $res, array $p) {
        $data = json_decode($res['body'], true);
        if ($res['status'] >= 200 && $res['status'] < 300 && is_array($data)) {
            return $data;
        }
        $message = null;
        if (is_array($data)) {
            if (isset($data['error']['message'])) {
                $message = $data['error']['message'];
            } elseif (isset($data['error']) && is_string($data['error'])) {
                $message = $data['error'];
            } elseif (isset($data['message'])) {
                $message = is_string($data['message']) ? $data['message'] : json_encode($data['message']);
            } elseif (isset($data['detail'])) {
                $message = is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
            }
        }
        if ($message === null) {
            $message = substr(trim(strip_tags($res['body'])), 0, 300) ?: 'empty response';
        }
        $hint = '';
        if ($res['status'] === 401 || $res['status'] === 403) {
            $hint = ' Check the API key' . (!empty($p['regional_base_urls']) ? ' and that the region matches the account' : '') . '.';
        } elseif ($res['status'] === 429) {
            $hint = ' Rate limit or quota reached on the provider account.';
        } elseif ($res['status'] === 404) {
            $hint = ' The model id or endpoint does not exist for this account.';
        }
        throw new RuntimeException($p['name'] . ' HTTP ' . $res['status'] . ': ' . $message . $hint);
    }

    public function log_usage($slug, $model, $task, $in, $out, $chars, $ms, $status, $ok, $error) {
        if (!$this->has_table('ha_ai_usage')) {
            return;
        }
        $this->CI->db->insert('ha_ai_usage', array(
            'provider_slug' => $slug, 'model_id' => $model ? substr($model, 0, 190) : null, 'task' => $task,
            'job_id' => $this->context['job_id'], 'user_id' => $this->context['user_id'], 'api_key_id' => $this->context['api_key_id'],
            'input_tokens' => (int) $in, 'output_tokens' => (int) $out, 'characters' => (int) $chars,
            'latency_ms' => (int) $ms, 'http_status' => $status ?: null, 'ok' => $ok ? 1 : 0,
            'error' => $error ? substr($error, 0, 500) : null, 'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    /** Auth headers exactly as the chat adapters send them, for the media library. */
    public function headers_for(array $p) {
        return $this->auth_headers($p, $this->bearer_for($p));
    }

    /** Needed by the media library for its own calls. */
    public function key_for(array $p) {
        return $this->api_key($p);
    }

    public function secret_for(array $p, $name) {
        return $this->secret_setting($p, $name);
    }
}
