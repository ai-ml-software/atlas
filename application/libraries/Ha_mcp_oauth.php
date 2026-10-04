<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/Ha_gateway.php';   // Ha_api_error

/** OAuth protocol error: rendered as {error, error_description} with an HTTP status. */
class Ha_oauth_error extends RuntimeException {
    public $status; public $error;
    public function __construct($error, $description, $status = 400) { parent::__construct($description); $this->error = $error; $this->status = (int) $status; }
}

/**
 * Native OAuth 2.1 authorization server for the PHP MCP endpoint.
 *
 *  - RFC 8414 / RFC 9728 metadata, RFC 7591 dynamic registration (public clients only)
 *  - authorization_code with mandatory PKCE S256, exact redirect_uri match, state + iss
 *  - refresh_token rotation with reuse detection (reuse revokes the whole grant)
 *  - RFC 7009 revocation, RFC 8707 resource indicators (audience = {issuer}/mcp)
 *  - opaque tokens, stored only as SHA-256 hashes
 * Sign-in (including two-factor) and consent happen in the ALTUS workspace: Hkp_cms::mcp_authorize().
 */
class Ha_mcp_oauth {
    const SCOPES = array('altus.read', 'altus.content.write', 'altus.course.write', 'altus.media.write', 'altus.publish', 'altus.admin');
    const SCOPE_TEXT = array(
        'altus.read' => 'Read pages, courses, articles, SOPs, media and approval status you can already see',
        'altus.content.write' => 'Create and edit private drafts of pages, articles, topics, programs, paths, SOPs, navigation and theme',
        'altus.course.write' => 'Create and edit private drafts of courses, sections, lessons, quizzes and questions',
        'altus.media.write' => 'Upload images to the media library and attach them to drafts',
        'altus.publish' => 'Request publication or archiving, and publish only after another person approves',
        'altus.admin' => 'Read the full MCP audit history (platform administrators)',
    );
    /**
     * Access levels: presets over the scopes. A level is a ceiling; the user's current
     * permissions are still applied on every call (allowed_scopes), so a level never grants more.
     */
    const LEVELS = array(
        'read'  => array('altus.read'),
        'write' => array('altus.read', 'altus.content.write', 'altus.course.write', 'altus.media.write'),
        'full'  => array('altus.read', 'altus.content.write', 'altus.course.write', 'altus.media.write', 'altus.publish', 'altus.admin'),
    );
    const LEVEL_RANK = array('read' => 1, 'write' => 2, 'full' => 3);
    const PERSONAL_PREFIX = 'altus_pt_';
    private $CI;

    public static function valid_level($l) { return is_string($l) && isset(self::LEVELS[$l]); }
    public static function level_scopes($level) { return self::LEVELS[$level] ?? self::LEVELS['read']; }
    /** Highest preset the scope set reaches (read < write < full). */
    public static function level_of(array $scopes) {
        if (in_array('altus.publish', $scopes, true)) return 'full';
        return array_intersect(array('altus.content.write', 'altus.course.write', 'altus.media.write'), $scopes) ? 'write' : 'read';
    }
    public static function cap(array $scopes, $level) { return self::valid_level($level) ? array_values(array_intersect($scopes, self::LEVELS[$level])) : array_values($scopes); }
    public static function lower_level($a, $b) { if (!self::valid_level($a)) return $b; if (!self::valid_level($b)) return $a; return self::LEVEL_RANK[$a] <= self::LEVEL_RANK[$b] ? $a : $b; }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->config->load('ha_publisher', true);
        $this->CI->load->helper('url');
    }
    public function cfg($k, $default = null) { $v = $this->CI->config->item($k, 'ha_publisher'); return $v === null ? $default : $v; }
    public function enabled() { return (bool) $this->cfg('mcp_enabled'); }
    public function issuer() { $i = (string) $this->cfg('mcp_issuer', ''); return $i !== '' ? rtrim($i, '/') : rtrim(base_url(), '/'); }
    public function resource() { return $this->issuer() . '/mcp'; }
    public function resource_metadata_url() { return $this->issuer() . '/.well-known/oauth-protected-resource/mcp'; }
    private static function now() { return gmdate('Y-m-d H:i:s'); }
    private static function at($ts) { return gmdate('Y-m-d H:i:s', $ts); }
    private static function past($datetime) { return strtotime($datetime . ' UTC') < time(); }
    public static function b64url($bytes) { return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='); }
    public static function random($prefix, $bytes = 32) { return $prefix . self::b64url(random_bytes($bytes)); }
    public static function h($secret) { return hash('sha256', (string) $secret); }

    // ------------------------------------------------------------- metadata
    /** RFC 8414 metadata. The openid-configuration alias (used by MCP clients when ALTUS runs in a sub-directory)
     *  adds the fields OIDC discovery parsers require; OpenID Connect sign-in itself is not offered (no openid scope, no ID tokens). */
    public function as_metadata($oidc_alias = false) {
        $i = $this->issuer();
        $extra = $oidc_alias ? array('jwks_uri' => $i . '/.well-known/jwks.json', 'subject_types_supported' => array('public'), 'id_token_signing_alg_values_supported' => array('RS256')) : array();
        return array('issuer' => $i, 'authorization_endpoint' => $i . '/oauth/authorize', 'token_endpoint' => $i . '/oauth/token',
            'registration_endpoint' => $i . '/oauth/register', 'revocation_endpoint' => $i . '/oauth/revoke',
            'response_types_supported' => array('code'), 'response_modes_supported' => array('query'),
            'grant_types_supported' => array('authorization_code', 'refresh_token'), 'code_challenge_methods_supported' => array('S256'),
            'token_endpoint_auth_methods_supported' => array('none'), 'revocation_endpoint_auth_methods_supported' => array('none'),
            'scopes_supported' => self::SCOPES, 'authorization_response_iss_parameter_supported' => true) + $extra;
    }
    public function pr_metadata() {
        return array('resource' => $this->resource(), 'authorization_servers' => array($this->issuer()), 'scopes_supported' => self::SCOPES,
            'bearer_methods_supported' => array('header'), 'resource_name' => 'ALTUS Publishing (PHP MCP)');
    }

    /** Scopes a user may hold right now, from current permissions (re-evaluated on every call). */
    public static function allowed_scopes(array $perms, $system) {
        $has = function (array $list) use ($perms) { return (bool) array_intersect($list, $perms); };
        $s = array('altus.read');
        if ($has(array('cms_pages.update', 'articles.update', 'knowledge.create', 'knowledge.update', 'programs.update', 'learning_paths.update'))) $s[] = 'altus.content.write';
        if ($has(array('courses.create', 'courses.update', 'lessons.create', 'lessons.update', 'assessments.update', 'question_banks.create'))) $s[] = 'altus.course.write';
        if ($has(array('cms_pages.update', 'media.create'))) $s[] = 'altus.media.write';
        if ($has(array('cms_pages.publish', 'courses.publish', 'articles.publish', 'programs.publish', 'learning_paths.publish'))) $s[] = 'altus.publish';
        if ($system && $has(array('system.configure'))) $s[] = 'altus.admin';
        return $s;
    }
    public static function parse_scope($scope) { return array_values(array_unique(array_filter(preg_split('/\s+/', trim((string) $scope))))); }

    // ------------------------------------------------------------- rate limit
    /** Fixed one-minute window. Returns seconds to wait, 0 when allowed. */
    public function rate_limited($bucket, $limit) {
        if ($limit <= 0 || !$this->CI->db->table_exists('ha_mcp_rate')) return 0;
        $win = (int) (floor(time() / 60) * 60); $bucket = substr($bucket, 0, 190);
        $this->CI->db->query('INSERT INTO ha_mcp_rate (bucket,window_start,hits) VALUES (?,?,1) ON DUPLICATE KEY UPDATE hits=IF(window_start<?,1,hits+1), window_start=GREATEST(window_start,?)', array($bucket, $win, $win, $win));
        $hits = (int) $this->CI->db->query('SELECT hits FROM ha_mcp_rate WHERE bucket=?', array($bucket))->row('hits');
        if (mt_rand(1, 200) === 1) $this->CI->db->query('DELETE FROM ha_mcp_rate WHERE window_start<?', array($win - 120));
        return $hits > $limit ? max(1, $win + 60 - time()) : 0;
    }

    // ------------------------------------------------------------- RFC 7591
    public static function valid_redirect_uri($u) {
        if (!is_string($u) || strlen($u) > 500 || strpos($u, '#') !== false || preg_match('/[\s<>"]/', $u)) return false;
        $p = parse_url($u); if (!$p || empty($p['scheme'])) return false;
        $scheme = strtolower($p['scheme']);
        if ($scheme === 'https') return !empty($p['host']);
        if ($scheme === 'http') return in_array(strtolower($p['host'] ?? ''), array('localhost', '127.0.0.1', '[::1]', '::1'), true);
        // Private-use URI schemes for native apps (RFC 8252 §7.1), e.g. cursor://.
        return !in_array($scheme, array('javascript', 'data', 'file', 'vbscript', 'about', 'blob', 'ftp', 'ws', 'wss'), true) && (bool) preg_match('/^[a-z][a-z0-9+.-]{1,40}$/', $scheme);
    }
    public function register(array $in, $ip) {
        if (!$this->enabled()) throw new Ha_oauth_error('temporarily_unavailable', 'ALTUS MCP is switched off.', 503);
        $uris = $in['redirect_uris'] ?? null;
        if (!is_array($uris) || !$uris || count($uris) > 10) throw new Ha_oauth_error('invalid_redirect_uri', 'Provide 1 to 10 redirect_uris.');
        foreach ($uris as $u) if (!self::valid_redirect_uri($u)) throw new Ha_oauth_error('invalid_redirect_uri', 'Redirect URIs must be https, http loopback or a private-use scheme, without fragments.');
        $method = (string) ($in['token_endpoint_auth_method'] ?? 'none');
        if ($method !== 'none') throw new Ha_oauth_error('invalid_client_metadata', 'Only public clients (token_endpoint_auth_method "none") with PKCE are supported.');
        $grants = isset($in['grant_types']) ? (array) $in['grant_types'] : array('authorization_code', 'refresh_token');
        if (array_diff($grants, array('authorization_code', 'refresh_token')) || !in_array('authorization_code', $grants, true)) throw new Ha_oauth_error('invalid_client_metadata', 'Supported grant_types: authorization_code, refresh_token.');
        $resp = isset($in['response_types']) ? (array) $in['response_types'] : array('code');
        if ($resp !== array('code')) throw new Ha_oauth_error('invalid_client_metadata', 'Supported response_types: code.');
        $scope = isset($in['scope']) ? self::parse_scope($in['scope']) : array();
        if (array_diff($scope, array_merge(self::SCOPES, array('offline_access')))) throw new Ha_oauth_error('invalid_client_metadata', 'Unknown scope requested.');
        $name = mb_substr(trim(strip_tags((string) ($in['client_name'] ?? 'MCP client'))), 0, 120) ?: 'MCP client';
        $id = 'mcp_' . bin2hex(random_bytes(16)); $now = time();
        $meta = array_intersect_key($in, array_flip(array('client_uri', 'logo_uri', 'software_id', 'software_version')));
        foreach ($meta as $k => $v) $meta[$k] = mb_substr((string) $v, 0, 300);
        $this->CI->db->insert('ha_mcp_client', array('client_id' => $id, 'client_name' => $name, 'redirect_uris' => json_encode(array_values($uris), JSON_UNESCAPED_SLASHES),
            'grant_types' => implode(' ', $grants), 'scope' => $scope ? implode(' ', $scope) : null, 'metadata_json' => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'created_ip' => substr((string) $ip, 0, 45), 'created_at' => self::at($now)));
        $this->audit('oauth.register', 0, 'MCP client registered: ' . $name . ' (' . $id . ')');
        return array('client_id' => $id, 'client_id_issued_at' => $now, 'client_name' => $name, 'redirect_uris' => array_values($uris),
            'grant_types' => $grants, 'response_types' => array('code'), 'token_endpoint_auth_method' => 'none') + ($scope ? array('scope' => implode(' ', $scope)) : array()) + $meta;
    }
    public function client($id) {
        if (!is_string($id) || $id === '' || strlen($id) > 64) return null;
        $c = $this->CI->db->get_where('ha_mcp_client', array('client_id' => $id))->row_array();
        if (!$c || $c['revoked_at']) return null;
        $c['redirect_uris'] = json_decode($c['redirect_uris'], true) ?: array();
        return $c;
    }

    // ------------------------------------------------------------- /oauth/authorize
    /**
     * Validates an authorization request. Returns array('page_error'=>msg) when the client or redirect
     * cannot be trusted (never redirect), array('redirect'=>url) for errors returned to the client,
     * or array('request_id'=>id) to continue to sign-in + consent.
     */
    public function authorize_request(array $q) {
        $client = $this->client((string) ($q['client_id'] ?? ''));
        if (!$client) return array('page_error' => 'Unknown or revoked MCP client. Register the client again.');
        $redirect = (string) ($q['redirect_uri'] ?? '');
        if ($redirect === '' && count($client['redirect_uris']) === 1) $redirect = $client['redirect_uris'][0];
        if (!in_array($redirect, $client['redirect_uris'], true)) return array('page_error' => 'The redirect_uri does not exactly match a registered redirect URI.');
        $state = isset($q['state']) ? (string) $q['state'] : null;
        $fail = function ($err, $desc) use ($redirect, $state) { return array('redirect' => $this->redirect_with($redirect, array('error' => $err, 'error_description' => $desc, 'state' => $state))); };
        if (!$this->enabled()) return $fail('temporarily_unavailable', 'ALTUS MCP is switched off.');
        if (($q['response_type'] ?? '') !== 'code') return $fail('unsupported_response_type', 'Only response_type=code is supported.');
        $challenge = (string) ($q['code_challenge'] ?? '');
        if (($q['code_challenge_method'] ?? '') !== 'S256' || !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge)) return $fail('invalid_request', 'PKCE with code_challenge_method=S256 is required.');
        $resource = isset($q['resource']) && $q['resource'] !== '' ? rtrim((string) $q['resource'], '/') : $this->resource();
        if ($resource !== $this->resource()) return $fail('invalid_target', 'The resource must be ' . $this->resource() . '.');
        $scope = self::parse_scope($q['scope'] ?? 'altus.read');
        $scope = array_values(array_diff($scope, array('offline_access', 'openid')));
        if (!$scope) $scope = array('altus.read');
        if (array_diff($scope, self::SCOPES)) return $fail('invalid_scope', 'Unknown scope. Supported: ' . implode(' ', self::SCOPES));
        $id = bin2hex(random_bytes(24));
        $this->CI->db->insert('ha_mcp_auth_request', array('id' => $id, 'client_id' => $client['client_id'], 'created_at' => self::now(), 'expires_at' => self::at(time() + 600),
            'params_json' => json_encode(array('redirect_uri' => $redirect, 'state' => $state, 'scope' => $scope, 'resource' => $resource, 'code_challenge' => $challenge), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        if (mt_rand(1, 50) === 1) $this->CI->db->where('expires_at <', self::now())->delete('ha_mcp_auth_request');
        return array('request_id' => $id);
    }
    public function pending($id) {
        if (!preg_match('/^[a-f0-9]{48}$/', (string) $id)) return null;
        $r = $this->CI->db->get_where('ha_mcp_auth_request', array('id' => $id))->row_array();
        if (!$r || self::past($r['expires_at'])) return null;
        $client = $this->client($r['client_id']); if (!$client) return null;
        return array('id' => $id, 'client' => $client) + json_decode($r['params_json'], true);
    }
    public function redirect_with($uri, array $params) {
        $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
        $params['iss'] = $this->issuer();   // RFC 9207
        return $uri . (strpos($uri, '?') === false ? '?' : '&') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
    /**
     * The signed-in user's decision. Scopes are capped by the user's current permissions.
     * Returns the URL to send the browser to (the client's exact redirect_uri).
     */
    /**
     * The signed-in user's decision. Scopes = requested ∩ the user's current permissions ∩ the chosen
     * access level, which is itself capped by the console's maximum and the client's maximum level.
     * $level null (legacy callers) means the highest level offered.
     */
    public function consent($request_id, $user_id, $allow, array $permissions, $system, $level = null) {
        $p = $this->pending($request_id); if (!$p) throw new Ha_oauth_error('invalid_request', 'This authorization request expired. Start the connection again from your client.');
        $this->CI->db->where('id', $request_id)->delete('ha_mcp_auth_request');   // single use
        if (!$this->enabled()) return $this->redirect_with($p['redirect_uri'], array('error' => 'temporarily_unavailable', 'state' => $p['state']));
        if (!$allow) { $this->audit('oauth.deny', $user_id, 'User declined MCP client ' . $p['client']['client_id']); return $this->redirect_with($p['redirect_uri'], array('error' => 'access_denied', 'error_description' => 'The ALTUS user declined access.', 'state' => $p['state'])); }
        $offer = $this->consent_offer($p['client']);
        $level = self::valid_level($level) ? self::lower_level($level, $offer['max']) : $offer['max'];
        $scope = self::cap(array_values(array_intersect($p['scope'], self::allowed_scopes($permissions, $system))), $level);
        if (!$scope) return $this->redirect_with($p['redirect_uri'], array('error' => 'access_denied', 'error_description' => 'Your ALTUS permissions do not allow any requested scope.', 'state' => $p['state']));
        $grant = 'g_' . bin2hex(random_bytes(16)); $now = self::now();
        $this->CI->db->insert('ha_mcp_grant', array('id' => $grant, 'client_id' => $p['client']['client_id'], 'user_id' => (int) $user_id, 'scope' => implode(' ', $scope), 'resource' => $p['resource'], 'created_at' => $now));
        $code = self::random('ac_');
        $this->CI->db->insert('ha_mcp_auth_code', array('code_hash' => self::h($code), 'grant_id' => $grant, 'client_id' => $p['client']['client_id'], 'user_id' => (int) $user_id,
            'redirect_uri' => $p['redirect_uri'], 'scope' => implode(' ', $scope), 'resource' => $p['resource'], 'code_challenge' => $p['code_challenge'],
            'expires_at' => self::at(time() + (int) $this->cfg('mcp_code_ttl', 60)), 'created_at' => $now));
        $this->audit('oauth.consent', $user_id, 'Approved MCP client ' . $p['client']['client_name'] . ' (' . $p['client']['client_id'] . ') level ' . $level . ' scopes: ' . implode(' ', $scope));
        return $this->redirect_with($p['redirect_uri'], array('code' => $code, 'state' => $p['state']));
    }

    // ------------------------------------------------------------- /oauth/token
    public function token(array $in) {
        if (!$this->enabled()) throw new Ha_oauth_error('temporarily_unavailable', 'ALTUS MCP is switched off.', 503);
        if (isset($in['client_secret'])) throw new Ha_oauth_error('invalid_client', 'Public clients must not send a client secret.', 401);
        $client = $this->client((string) ($in['client_id'] ?? ''));
        $type = (string) ($in['grant_type'] ?? '');
        if (!in_array($type, array('authorization_code', 'refresh_token'), true)) throw new Ha_oauth_error('unsupported_grant_type', 'Supported grant types: authorization_code, refresh_token.');
        if (!$client) throw new Ha_oauth_error('invalid_client', 'Unknown or revoked client_id.', 401);
        return $type === 'authorization_code' ? $this->exchange_code($client, $in) : $this->refresh($client, $in);
    }
    private function exchange_code(array $client, array $in) {
        $db = $this->CI->db; $code = (string) ($in['code'] ?? ''); $verifier = (string) ($in['code_verifier'] ?? '');
        if ($code === '' || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier)) throw new Ha_oauth_error('invalid_request', 'code and a valid code_verifier (43-128 characters) are required.');
        $db->trans_begin();
        try {
            $r = $db->query('SELECT * FROM ha_mcp_auth_code WHERE code_hash=? FOR UPDATE', array(self::h($code)))->row_array();
            if (!$r) throw new Ha_oauth_error('invalid_grant', 'Unknown authorization code.');
            if ($r['used_at']) { $this->revoke_grant($r['grant_id'], 'code_reuse'); $db->trans_commit(); throw new Ha_oauth_error('invalid_grant', 'Authorization code was already used; the grant has been revoked.'); }
            $db->where('code_hash', $r['code_hash'])->update('ha_mcp_auth_code', array('used_at' => self::now()));
            if (self::past($r['expires_at'])) throw new Ha_oauth_error('invalid_grant', 'Authorization code expired.');
            if ($r['client_id'] !== $client['client_id']) throw new Ha_oauth_error('invalid_grant', 'Authorization code was issued to another client.');
            if (!isset($in['redirect_uri']) || (string) $in['redirect_uri'] !== $r['redirect_uri']) throw new Ha_oauth_error('invalid_grant', 'redirect_uri does not match the authorization request.');
            if (!hash_equals($r['code_challenge'], self::b64url(hash('sha256', $verifier, true)))) throw new Ha_oauth_error('invalid_grant', 'PKCE verification failed.');
            if (isset($in['resource']) && $in['resource'] !== '' && rtrim((string) $in['resource'], '/') !== $r['resource']) throw new Ha_oauth_error('invalid_target', 'The resource does not match the authorization.');
            $g = $db->get_where('ha_mcp_grant', array('id' => $r['grant_id']))->row_array();
            if (!$g || $g['revoked_at']) throw new Ha_oauth_error('invalid_grant', 'The grant was revoked.');
            if (!$this->user_active((int) $g['user_id'])) throw new Ha_oauth_error('invalid_grant', 'The ALTUS user is inactive.');
            $out = $this->issue($g, $g['scope']);
            $db->trans_commit();
            $this->audit('oauth.token', (int) $g['user_id'], 'Tokens issued to MCP client ' . $client['client_id']);
            return $out;
        } catch (Throwable $e) { $db->trans_rollback(); if ($e instanceof Ha_oauth_error && $e->error === 'invalid_grant') $this->mark_used($code); throw $e; }
    }
    /** A failed exchange still consumes the code (one attempt per code). */
    private function mark_used($code) { $this->CI->db->where(array('code_hash' => self::h($code), 'used_at' => null))->update('ha_mcp_auth_code', array('used_at' => self::now())); }
    private function refresh(array $client, array $in) {
        $db = $this->CI->db; $token = (string) ($in['refresh_token'] ?? '');
        if ($token === '') throw new Ha_oauth_error('invalid_request', 'refresh_token is required.');
        $db->trans_begin();
        try {
            $r = $db->query("SELECT * FROM ha_mcp_token WHERE token_hash=? AND kind='refresh' FOR UPDATE", array(self::h($token)))->row_array();
            if (!$r || $r['client_id'] !== $client['client_id']) throw new Ha_oauth_error('invalid_grant', 'Unknown refresh token.');
            if ($r['used_at'] || $r['revoked_at']) {
                // Reuse of a rotated refresh token: assume theft and revoke every token of the grant.
                $this->revoke_grant($r['grant_id'], 'refresh_reuse'); $db->trans_commit();
                $this->audit('oauth.reuse', (int) $r['user_id'], 'Refresh token reuse detected for client ' . $client['client_id'] . '; grant revoked');
                throw new Ha_oauth_error('invalid_grant', 'Refresh token was already used; the grant has been revoked.');
            }
            if (self::past($r['expires_at'])) throw new Ha_oauth_error('invalid_grant', 'Refresh token expired.');
            if (isset($in['resource']) && $in['resource'] !== '' && rtrim((string) $in['resource'], '/') !== $r['resource']) throw new Ha_oauth_error('invalid_target', 'The resource does not match the grant.');
            $g = $db->get_where('ha_mcp_grant', array('id' => $r['grant_id']))->row_array();
            if (!$g || $g['revoked_at']) throw new Ha_oauth_error('invalid_grant', 'The grant was revoked.');
            if (!$this->user_active((int) $g['user_id'])) throw new Ha_oauth_error('invalid_grant', 'The ALTUS user is inactive.');
            $scope = isset($in['scope']) && trim((string) $in['scope']) !== '' ? self::parse_scope($in['scope']) : self::parse_scope($r['scope']);
            if (array_diff($scope, self::parse_scope($g['scope']))) throw new Ha_oauth_error('invalid_scope', 'A refresh cannot add scopes.');
            $db->where('token_hash', $r['token_hash'])->update('ha_mcp_token', array('used_at' => self::now()));
            $out = $this->issue($g, implode(' ', $scope));
            $db->trans_commit();
            return $out;
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    private function issue(array $g, $scope) {
        $access = self::random('altus_at_'); $refresh = self::random('altus_rt_'); $now = self::now(); $ttl = (int) $this->cfg('mcp_access_ttl', 300);
        $base = array('grant_id' => $g['id'], 'client_id' => $g['client_id'], 'user_id' => (int) $g['user_id'], 'scope' => $scope, 'resource' => $g['resource'], 'created_at' => $now);
        $this->CI->db->insert('ha_mcp_token', $base + array('token_hash' => self::h($access), 'kind' => 'access', 'expires_at' => self::at(time() + $ttl)));
        $this->CI->db->insert('ha_mcp_token', $base + array('token_hash' => self::h($refresh), 'kind' => 'refresh', 'expires_at' => self::at(time() + (int) $this->cfg('mcp_refresh_ttl', 2592000))));
        if (mt_rand(1, 50) === 1) $this->CI->db->where('expires_at <', self::at(time() - 86400))->delete('ha_mcp_token');
        return array('access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => $ttl, 'refresh_token' => $refresh, 'scope' => $scope);
    }

    // ------------------------------------------------------------- RFC 7009
    /** Always succeeds for unknown tokens (RFC 7009 §2.2). Revoking a refresh token revokes the grant. */
    public function revoke(array $in) {
        if (!$this->enabled()) throw new Ha_oauth_error('temporarily_unavailable', 'ALTUS MCP is switched off.', 503);
        $token = (string) ($in['token'] ?? ''); if ($token === '') throw new Ha_oauth_error('invalid_request', 'token is required.');
        $r = $this->CI->db->get_where('ha_mcp_token', array('token_hash' => self::h($token)))->row_array();
        if (!$r) return true;
        if (!empty($in['client_id']) && $in['client_id'] !== $r['client_id']) return true;   // not this client's token: ignore
        if ($r['kind'] === 'refresh') $this->revoke_grant($r['grant_id'], 'client_revoked');
        else $this->CI->db->where('token_hash', $r['token_hash'])->update('ha_mcp_token', array('revoked_at' => self::now()));
        $this->audit('oauth.revoke', (int) $r['user_id'], 'MCP ' . $r['kind'] . ' token revoked by client ' . $r['client_id']);
        return true;
    }
    public function revoke_grant($grant_id, $reason) {
        $now = self::now(); $db = $this->CI->db;
        $db->where(array('id' => $grant_id, 'revoked_at' => null))->update('ha_mcp_grant', array('revoked_at' => $now, 'revoked_reason' => substr($reason, 0, 60)));
        $db->where(array('grant_id' => $grant_id, 'revoked_at' => null))->update('ha_mcp_token', array('revoked_at' => $now));
        $db->where('grant_id', $grant_id)->delete('ha_mcp_session');
    }

    // ------------------------------------------------------------- resource server
    /** Verifies a bearer access token for /mcp. Throws Ha_api_error(401) on any failure. */
    public function authenticate($bearer) {
        if (is_string($bearer) && strpos($bearer, self::PERSONAL_PREFIX) === 0) return $this->authenticate_personal($bearer);
        if (!is_string($bearer) || !preg_match('/^altus_at_[A-Za-z0-9_-]{20,100}$/', $bearer)) throw new Ha_api_error(401, 'invalid_token', 'A valid bearer access token is required.');
        $db = $this->CI->db;
        $t = $db->get_where('ha_mcp_token', array('token_hash' => self::h($bearer), 'kind' => 'access'))->row_array();
        if (!$t || $t['revoked_at'] || self::past($t['expires_at'])) throw new Ha_api_error(401, 'invalid_token', 'The access token expired or was revoked.');
        if ($t['resource'] !== $this->resource()) throw new Ha_api_error(401, 'invalid_token', 'The access token was issued for a different resource (audience).');
        $g = $db->get_where('ha_mcp_grant', array('id' => $t['grant_id']))->row_array();
        if (!$g || $g['revoked_at']) throw new Ha_api_error(401, 'invalid_token', 'The OAuth grant was revoked.');
        $client = $this->client($t['client_id']);
        if (!$client) throw new Ha_api_error(401, 'invalid_token', 'The client was revoked.');
        if ((int) $g['user_id'] !== (int) $t['user_id'] || $g['client_id'] !== $t['client_id']) throw new Ha_api_error(401, 'invalid_token', 'Token and grant do not match.');
        $db->where('id', $g['id'])->update('ha_mcp_grant', array('last_used_at' => self::now()));
        // Token scope ∩ grant scope (an admin downgrade of the grant applies at once) ∩ the client's maximum level.
        $scope = array_values(array_intersect(self::parse_scope($t['scope']), self::parse_scope($g['scope'])));
        if (!empty($client['max_level'])) $scope = self::cap($scope, $client['max_level']);
        return array('user_id' => (int) $t['user_id'], 'client_id' => $t['client_id'], 'grant_id' => $g['id'], 'scope' => $scope, 'source' => 'oauth');
    }
    /** Admin-issued personal connection token (for clients without OAuth). The user's permissions are still re-checked per call by the server. */
    private function authenticate_personal($bearer) {
        if (!preg_match('/^altus_pt_[A-Za-z0-9_-]{30,100}$/', $bearer) || !$this->CI->db->table_exists('ha_mcp_personal_token')) throw new Ha_api_error(401, 'invalid_token', 'A valid bearer access token is required.');
        $db = $this->CI->db;
        $t = $db->get_where('ha_mcp_personal_token', array('token_hash' => self::h($bearer)))->row_array();
        if (!$t || $t['revoked_at']) throw new Ha_api_error(401, 'invalid_token', 'The personal connection token was revoked or does not exist.');
        if (self::past($t['expires_at'])) throw new Ha_api_error(401, 'invalid_token', 'The personal connection token expired.');
        if (!$t['last_used_at'] || strtotime($t['last_used_at'] . ' UTC') < time() - 60) $db->where('id', $t['id'])->update('ha_mcp_personal_token', array('last_used_at' => self::now()));
        return array('user_id' => (int) $t['user_id'], 'client_id' => 'personal_' . (int) $t['id'], 'grant_id' => 'pt_' . (int) $t['id'], 'scope' => self::parse_scope($t['scope']), 'source' => 'personal');
    }

    // ------------------------------------------------------------- console settings + access levels
    public function console_ready() { return $this->CI->db->table_exists('ha_mcp_personal_token') && $this->CI->db->table_exists('ha_mcp_setting') && $this->CI->db->table_exists('ha_mcp_call_log'); }
    public function setting($name, $default) {
        if (!$this->CI->db->table_exists('ha_mcp_setting')) return $default;
        $v = $this->CI->db->get_where('ha_mcp_setting', array('name' => $name))->row('value');
        return $v === null ? $default : $v;
    }
    public function save_consent_levels($default, $max, $by) {
        if (!self::valid_level($default) || !self::valid_level($max)) throw new InvalidArgumentException('Choose read, write or full access.');
        if (self::LEVEL_RANK[$default] > self::LEVEL_RANK[$max]) throw new InvalidArgumentException('The default level cannot be higher than the maximum level.');
        $now = self::now();
        foreach (array('consent_default_level' => $default, 'consent_max_level' => $max) as $k => $v) $this->CI->db->replace('ha_mcp_setting', array('name' => $k, 'value' => $v, 'updated_by' => (int) $by, 'updated_at' => $now));
        $this->audit('mcp.levels', $by, 'MCP consent levels: default ' . $default . ', maximum ' . $max);
        return true;
    }
    /** What the consent screen offers for this client: max = min(console maximum, client maximum); default capped by max. */
    public function consent_offer(array $client) {
        $max = $this->setting('consent_max_level', 'full'); if (!self::valid_level($max)) $max = 'full';
        if (!empty($client['max_level'])) $max = self::lower_level($max, $client['max_level']);
        $default = $this->setting('consent_default_level', 'write'); if (!self::valid_level($default)) $default = 'write';
        return array('max' => $max, 'default' => self::lower_level($default, $max));
    }
    public function set_client_max_level($client_id, $level, $by) {
        if ($level !== '' && $level !== null && !self::valid_level($level)) throw new InvalidArgumentException('Unknown access level.');
        if (!$this->CI->db->field_exists('max_level', 'ha_mcp_client')) throw new RuntimeException('Run migration 033 first.');
        $c = $this->client((string) $client_id); if (!$c) throw new InvalidArgumentException('Unknown or revoked MCP client.');
        $this->CI->db->where('client_id', $c['client_id'])->update('ha_mcp_client', array('max_level' => $level ?: null));
        $this->audit('mcp.client_level', $by, 'MCP client ' . $c['client_id'] . ' maximum level: ' . ($level ?: 'none'));
        return true;
    }
    public function revoke_client($client_id, $by) {
        $c = $this->client((string) $client_id); if (!$c) throw new InvalidArgumentException('Unknown or already revoked MCP client.');
        $this->CI->db->where('client_id', $c['client_id'])->update('ha_mcp_client', array('revoked_at' => self::now()));
        foreach ($this->CI->db->select('id')->get_where('ha_mcp_grant', array('client_id' => $c['client_id'], 'revoked_at' => null))->result_array() as $g) $this->revoke_grant($g['id'], 'admin_client_revoked');
        $this->audit('mcp.client_revoke', $by, 'MCP client revoked by administrator: ' . $c['client_id']);
        return true;
    }
    /** Downgrade only: the new scopes are a subset of what the user approved. Effective on the next request. */
    public function set_grant_level($grant_id, $level, $by) {
        if (!self::valid_level($level)) throw new InvalidArgumentException('Unknown access level.');
        $db = $this->CI->db; $g = $db->get_where('ha_mcp_grant', array('id' => (string) $grant_id))->row_array();
        if (!$g || $g['revoked_at']) throw new InvalidArgumentException('Unknown or revoked connection.');
        $current = self::parse_scope($g['scope']); $new = self::cap($current, $level);
        if (!$new) throw new InvalidArgumentException('That level would leave the connection without any access. Revoke it instead.');
        if (count($new) === count($current)) throw new InvalidArgumentException('The connection is already at or below that level. Levels can only be lowered; ask the user to reconnect for more access.');
        $db->where('id', $g['id'])->update('ha_mcp_grant', array('scope' => implode(' ', $new)));
        foreach ($db->get_where('ha_mcp_token', array('grant_id' => $g['id'], 'revoked_at' => null))->result_array() as $t)
            $db->where('token_hash', $t['token_hash'])->update('ha_mcp_token', array('scope' => implode(' ', array_values(array_intersect(self::parse_scope($t['scope']), $new)))));
        $this->audit('mcp.grant_level', $by, 'MCP connection ' . $g['id'] . ' lowered to ' . $level . ': ' . implode(' ', $new));
        return $new;
    }
    public function admin_revoke_grant($grant_id, $by) {
        $g = $this->CI->db->get_where('ha_mcp_grant', array('id' => (string) $grant_id))->row_array();
        if (!$g || $g['revoked_at']) throw new InvalidArgumentException('Unknown or already revoked connection.');
        $this->revoke_grant($g['id'], 'admin_revoked');
        if ($this->CI->db->table_exists('ha_mcp_connection')) $this->CI->db->where('grant_id', $g['id'])->delete('ha_mcp_connection');
        $this->audit('mcp.grant_revoke', $by, 'MCP connection revoked by administrator: ' . $g['id'] . ' (client ' . $g['client_id'] . ', user ' . (int) $g['user_id'] . ')');
        return true;
    }

    // ------------------------------------------------------------- personal connection tokens
    /** Returns the secret once; only its SHA-256 hash is stored. */
    public function create_personal_token($user_id, $name, $level, array $custom, $days, $by) {
        if (!$this->CI->db->table_exists('ha_mcp_personal_token')) throw new RuntimeException('Run migration 033 first.');
        $user_id = (int) $user_id; $days = (int) $days;
        if (!$user_id || !$this->user_active($user_id)) throw new InvalidArgumentException('Choose an active ALTUS user.');
        $name = mb_substr(trim(strip_tags((string) $name)), 0, 120); if ($name === '') throw new InvalidArgumentException('Give the connection a name, for example "Claude Code on Sara\'s laptop".');
        if ($days < 1 || $days > 365) throw new InvalidArgumentException('Expiry must be between 1 and 365 days.');
        if ($level === 'custom') {
            $scope = array_values(array_intersect(self::SCOPES, $custom));
            if (!$scope) throw new InvalidArgumentException('Select at least one scope for a custom level.');
            if (!in_array('altus.read', $scope, true)) array_unshift($scope, 'altus.read');
        } elseif (self::valid_level($level)) { $scope = self::LEVELS[$level]; }
        else throw new InvalidArgumentException('Choose read, write, full or custom access.');
        $secret = self::random(self::PERSONAL_PREFIX); $now = time();
        $this->CI->db->insert('ha_mcp_personal_token', array('token_hash' => self::h($secret), 'token_hint' => substr($secret, 0, 13) . '…' . substr($secret, -4),
            'name' => $name, 'user_id' => $user_id, 'level' => $level, 'scope' => implode(' ', $scope), 'created_by' => (int) $by,
            'created_at' => self::at($now), 'expires_at' => self::at($now + $days * 86400)));
        $id = (int) $this->CI->db->insert_id();
        $this->audit('mcp.personal_token', $by, 'Personal MCP connection #' . $id . ' "' . $name . '" for user ' . $user_id . ' (' . $level . ', ' . $days . ' days)');
        return array('id' => $id, 'token' => $secret, 'name' => $name, 'level' => $level, 'scope' => $scope, 'expires_at' => self::at($now + $days * 86400), 'user_id' => $user_id);
    }
    public function revoke_personal_token($id, $by) {
        $db = $this->CI->db; $t = $db->get_where('ha_mcp_personal_token', array('id' => (int) $id))->row_array();
        if (!$t || $t['revoked_at']) return false;
        $db->where('id', $t['id'])->update('ha_mcp_personal_token', array('revoked_at' => self::now(), 'revoked_by' => (int) $by));
        $db->where('grant_id', 'pt_' . (int) $t['id'])->delete('ha_mcp_session');
        $this->audit('mcp.personal_token_revoke', $by, 'Personal MCP connection #' . (int) $t['id'] . ' revoked');
        return true;
    }
    public function personal_tokens() {
        if (!$this->CI->db->table_exists('ha_mcp_personal_token')) return array();
        return $this->CI->db->select('t.id, t.token_hint, t.name, t.user_id, t.level, t.scope, t.created_at, t.expires_at, t.last_used_at, t.revoked_at, u.email, u.first_name, u.last_name')
            ->from('ha_mcp_personal_token t')->join('users u', 'u.id = t.user_id', 'left')->order_by('t.revoked_at IS NULL', 'DESC', false)->order_by('t.created_at', 'DESC')->limit(100)->get()->result_array();
    }
    public function user_active($uid) {
        $u = $this->CI->db->select('status')->get_where('users', array('id' => (int) $uid))->row_array();
        if (!$u || (int) $u['status'] !== 1) return false;
        $p = $this->CI->db->select('status')->get_where('ha_profile', array('user_id' => (int) $uid))->row_array();
        return !$p || $p['status'] === 'active';
    }

    // ------------------------------------------------------------- admin
    public function grants($user_id = null) {
        $db = $this->CI->db->select('g.*, c.client_name, u.email')->from('ha_mcp_grant g')->join('ha_mcp_client c', 'c.client_id = g.client_id', 'left')->join('users u', 'u.id = g.user_id', 'left')->where('g.revoked_at IS NULL', null, false);
        if ($user_id !== null) $db->where('g.user_id', (int) $user_id);
        return $db->order_by('g.created_at', 'DESC')->limit(100)->get()->result_array();
    }
    /** Admin health: fetch our own metadata endpoints over HTTP and check their contract. */
    public function self_check() {
        $out = array();
        $checks = array('protected_resource' => $this->resource_metadata_url(), 'authorization_server' => $this->issuer() . '/.well-known/oauth-authorization-server', 'mcp_challenge' => $this->resource());
        foreach ($checks as $k => $url) {
            $t = microtime(true); $ctx = stream_context_create(array('http' => array('timeout' => 3, 'ignore_errors' => true, 'method' => $k === 'mcp_challenge' ? 'POST' : 'GET', 'header' => "Content-Type: application/json\r\n", 'content' => $k === 'mcp_challenge' ? '{}' : '')));
            $http_response_header = array(); $body = @file_get_contents($url, false, $ctx); $status = 0; $www = '';
            foreach ((array) ($http_response_header ?? array()) as $h) { if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $status = (int) $m[1]; if (stripos($h, 'WWW-Authenticate:') === 0) $www = $h; }
            $json = $body !== false ? json_decode($body, true) : null;
            if ($k === 'protected_resource') $ok = $status === 200 && ($json['resource'] ?? '') === $this->resource();
            elseif ($k === 'authorization_server') $ok = $status === 200 && in_array('S256', (array) ($json['code_challenge_methods_supported'] ?? array()), true);
            else $ok = $status === 401 && strpos($www, 'resource_metadata=') !== false;
            $out[$k] = array('ok' => $ok, 'status' => $status, 'url' => $url, 'ms' => (int) round((microtime(true) - $t) * 1000));
        }
        return $out;
    }
    public function clients() { return $this->CI->db->select('client_id, client_name, redirect_uris, created_at, last_used_at, revoked_at' . ($this->CI->db->field_exists('max_level', 'ha_mcp_client') ? ', max_level' : ''))->order_by('created_at', 'DESC')->limit(100)->get('ha_mcp_client')->result_array(); }
    public function audit($action, $uid, $description) {
        try { $this->CI->load->library('ha_audit'); return $this->CI->ha_audit->log($action, 'users', (int) $uid, array('description' => mb_substr($description, 0, 250)) + ((int) $uid > 0 ? array('user_id' => (int) $uid) : array())); } catch (Throwable $e) { log_message('error', 'MCP audit: ' . $e->getMessage()); return null; }
    }
}
