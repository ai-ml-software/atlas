<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Speech and video providers: narration, presenter (avatar) videos and
 * generative clips.
 *
 * Unlike chat there is no common wire format here, so each vendor has its own
 * adapter, chosen by slug (or an "adapter" key in the registry entry). Any
 * provider whose registry TTS path is the OpenAI-style /audio/speech shares
 * one adapter, which covers OpenAI, Mistral, xAI, Groq, Together, OpenRouter
 * and others without per-vendor code.
 *
 * Long-running renders share one contract, so the job worker does not care
 * which vendor it drives:
 *
 *     *_start(...)  -> array('provider' => slug, 'ref' => vendor job id)
 *     *_poll(ref)   -> array('done' => bool, 'url' => ?string, 'bytes' => ?string, 'error' => ?string)
 *
 * Endpoints follow config/ha_ai_providers.php as researched on 2026-09-24:
 * HeyGen v3 (v2 generate is gone), Gemini TTS via the Interactions API, and
 * no OpenAI video adapter because OpenAI shut the Videos API down that day.
 */
class Ha_ai_media {

    private $CI;
    /** @var Ha_ai_gateway */
    private $gw;

    private static $supported = array(
        'tts'    => array('openai_speech' => 'OpenAI-compatible /audio/speech (OpenAI, Mistral, xAI, Groq, Together…)',
                          'elevenlabs' => 'ElevenLabs', 'azure_speech' => 'Azure AI Speech',
                          'google_tts' => 'Google Cloud Text-to-Speech', 'gemini' => 'Gemini TTS'),
        'avatar' => array('heygen' => 'HeyGen', 'did' => 'D-ID', 'synthesia' => 'Synthesia'),
        'clip'   => array('veo' => 'Google Veo', 'luma' => 'Luma', 'runway' => 'Runway'),
    );

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->library('ha_ai_gateway');
        $this->gw = $this->CI->ha_ai_gateway;
    }

    /** Adapter name for a provider and kind (tts | avatar | clip). */
    public static function adapter(array $p, $kind = null) {
        foreach (array('tts', 'video') as $k) {
            if (!empty($p[$k]['adapter'])) {
                return $p[$k]['adapter'];
            }
        }
        $s = strtolower(preg_replace('/[^a-z0-9]/i', '', $p['slug']));
        if ($kind === 'clip' && in_array($s, array('gemini', 'googleveo', 'veo'), true)) {
            return 'veo';
        }
        if ($kind === 'tts' && $s === 'gemini') {
            return 'gemini';
        }
        $map = array(
            'elevenlabs' => 'elevenlabs', 'heygen' => 'heygen', 'did' => 'did', 'synthesia' => 'synthesia',
            'luma' => 'luma', 'runway' => 'runway', 'azurespeech' => 'azure_speech', 'googletts' => 'google_tts',
            'googleveo' => 'veo',
        );
        if (isset($map[$s])) {
            return $map[$s];
        }
        if ($kind === 'tts' && isset($p['tts']['path']) && rtrim($p['tts']['path'], '/') === '/audio/speech') {
            return 'openai_speech';
        }
        return $s;
    }

    public static function supports(array $p, $kind) {
        return isset(self::$supported[$kind][self::adapter($p, $kind)]);
    }

    public static function supported_list($kind) {
        return array_values(isset(self::$supported[$kind]) ? self::$supported[$kind] : array());
    }

    // ================================================================= TTS

    /** @return array('bytes' => audio, 'ext' => 'mp3'|'wav') */
    public function speak($text, $locale = 'en') {
        list($p, $model, $route) = $this->gw->resolve('narration');
        $options = json_decode((string) $route['options_json'], true) ?: array();
        $voice = !empty($options['voice_' . $locale]) ? $options['voice_' . $locale]
               : (!empty($options['voice']) ? $options['voice'] : null);
        $adapter = self::adapter($p, 'tts');
        $method = 'tts_' . $adapter;
        if (!method_exists($this, $method)) {
            throw new RuntimeException('No text-to-speech adapter for ' . $p['name'] . '.');
        }
        $started = microtime(true);
        try {
            $out = $this->$method($p, $model, $voice, $text, $locale);
        } catch (Exception $e) {
            $this->gw->log_usage($p['slug'], $model, 'narration', 0, 0, mb_strlen($text), (int) ((microtime(true) - $started) * 1000), $this->gw->last_status, false, $e->getMessage());
            throw $e;
        }
        $this->gw->log_usage($p['slug'], $model, 'narration', 0, 0, mb_strlen($text), (int) ((microtime(true) - $started) * 1000), 200, true, null);
        return $out;
    }

    private function binary(array $res, array $p) {
        $type = isset($res['headers']['content-type']) ? $res['headers']['content-type'] : '';
        if ($res['status'] >= 200 && $res['status'] < 300 && $res['body'] !== '' && strpos($type, 'json') === false) {
            return $res['body'];
        }
        $this->gw->decode($res, $p);   // throws with the provider's own message
        throw new RuntimeException($p['name'] . ' returned no audio.');
    }

    private function tts_openai_speech(array $p, $model, $voice, $text, $locale) {
        $body = array('model' => $model ?: 'gpt-4o-mini-tts', 'voice' => $voice ?: 'alloy', 'input' => $text, 'response_format' => 'mp3');
        if ($locale === 'ar' && $p['slug'] === 'openai' && strpos($body['model'], 'tts-1') !== 0) {
            $body['instructions'] = 'Speak clear Modern Standard Arabic at a calm training pace.';
        }
        $headers = array_merge(array('Content-Type: application/json', 'Accept: audio/mpeg'), $this->gw->headers_for($p));
        $res = $this->gw->request('POST', $this->gw->endpoint($p) . '/audio/speech', $headers, $body);
        return array('bytes' => $this->binary($res, $p), 'ext' => 'mp3');
    }

    private function tts_elevenlabs(array $p, $model, $voice, $text, $locale) {
        if (!$voice) {
            throw new RuntimeException('ElevenLabs needs a voice id on the narration route (options: voice=…).');
        }
        $res = $this->gw->request('POST', $this->gw->endpoint($p) . '/v1/text-to-speech/' . rawurlencode($voice) . '?output_format=mp3_44100_128',
            array('Content-Type: application/json', 'Accept: audio/mpeg', 'xi-api-key: ' . $this->gw->key_for($p)),
            array('text' => $text, 'model_id' => $model ?: 'eleven_multilingual_v2'));
        return array('bytes' => $this->binary($res, $p), 'ext' => 'mp3');
    }

    private function tts_azure_speech(array $p, $model, $voice, $text, $locale) {
        $voice = $voice ?: ($locale === 'ar' ? 'ar-SA-ZariyahNeural' : 'en-US-JennyNeural');
        $lang = $locale === 'ar' ? 'ar-SA' : 'en-US';
        $ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="' . $lang . '"><voice name="'
              . htmlspecialchars($voice, ENT_XML1) . '">' . htmlspecialchars($text, ENT_XML1) . '</voice></speak>';
        $base = $this->gw->endpoint($p);
        if (strpos($base, '{') !== false || $base === '') {
            throw new RuntimeException('Azure AI Speech needs its region (e.g. uaenorth) in the provider settings.');
        }
        $res = $this->gw->request('POST', $base . '/cognitiveservices/v1', array(
            'Content-Type: application/ssml+xml',
            'X-Microsoft-OutputFormat: audio-24khz-96kbitrate-mono-mp3',
            'User-Agent: HospitalityAcademy',
            'Ocp-Apim-Subscription-Key: ' . $this->gw->key_for($p),
        ), $ssml);
        return array('bytes' => $this->binary($res, $p), 'ext' => 'mp3');
    }

    /** API key or, when the key field holds service-account JSON, an OAuth token. */
    private function tts_google_tts(array $p, $model, $voice, $text, $locale) {
        $body = array(
            'input' => array('text' => $text),
            'voice' => array('languageCode' => $locale === 'ar' ? 'ar-XA' : 'en-US') + ($voice ? array('name' => $voice) : array()),
            'audioConfig' => array('audioEncoding' => 'MP3'),
        );
        $token = $this->gw->bearer_for($p);
        $auth = $token ? 'Authorization: Bearer ' . $token : 'x-goog-api-key: ' . $this->gw->key_for($p);
        $headers = array('Content-Type: application/json', $auth);
        if (!empty($p['settings']['project_id'])) {
            $headers[] = 'x-goog-user-project: ' . $p['settings']['project_id'];
        }
        $data = $this->gw->decode($this->gw->request('POST', $this->gw->endpoint($p) . '/v1/text:synthesize', $headers, $body), $p);
        if (empty($data['audioContent'])) {
            throw new RuntimeException('Google Text-to-Speech returned no audio.');
        }
        return array('bytes' => base64_decode($data['audioContent']), 'ext' => 'mp3');
    }

    /** Gemini TTS through the Interactions API; returns 24 kHz 16-bit mono audio. */
    private function tts_gemini(array $p, $model, $voice, $text, $locale) {
        $base = $this->gw->endpoint($p);
        if (!preg_match('#/v1beta$#', $base)) {
            $base .= '/v1beta';
        }
        $body = array(
            'model' => preg_replace('#^models/#', '', $model ?: 'gemini-3.8-flash-tts'),
            'input' => array(array('type' => 'user_input', 'content' => array(array('type' => 'text', 'text' => $text)))),
            'response_format' => array('type' => 'audio'),
            'generation_config' => array('speech_config' => array(array('voice' => $voice ?: 'Kore'))),
        );
        $res = $this->gw->request('POST', $base . '/interactions',
            array('Content-Type: application/json', 'x-goog-api-key: ' . $this->gw->key_for($p)), $body);
        $type = isset($res['headers']['content-type']) ? $res['headers']['content-type'] : '';
        if ($res['status'] === 200 && strpos($type, 'json') === false && $res['body'] !== '') {
            $bytes = $res['body'];
            return array('bytes' => substr($bytes, 0, 4) === 'RIFF' ? $bytes : self::pcm_to_wav($bytes, 24000, 1, 16), 'ext' => 'wav');
        }
        $data = $this->gw->decode($res, $p);
        $b64 = self::find_audio($data);
        if (!$b64) {
            throw new RuntimeException('Gemini returned no audio in the interaction response.');
        }
        $bytes = base64_decode($b64);
        return array('bytes' => substr($bytes, 0, 4) === 'RIFF' ? $bytes : self::pcm_to_wav($bytes, 24000, 1, 16), 'ext' => 'wav');
    }

    /** First base64 audio payload anywhere in a response tree. */
    private static function find_audio($node) {
        if (!is_array($node)) {
            return null;
        }
        $mime = isset($node['mime_type']) ? $node['mime_type'] : (isset($node['mimeType']) ? $node['mimeType'] : '');
        if (isset($node['data']) && is_string($node['data']) && ($mime === '' || strpos($mime, 'audio') === 0) && strlen($node['data']) > 100) {
            return $node['data'];
        }
        foreach ($node as $child) {
            $found = self::find_audio($child);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    public static function pcm_to_wav($pcm, $rate, $channels, $bits) {
        $byte_rate = $rate * $channels * $bits / 8;
        $block = $channels * $bits / 8;
        return 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVE'
             . 'fmt ' . pack('VvvVVvv', 16, 1, $channels, $rate, $byte_rate, $block, $bits)
             . 'data' . pack('V', strlen($pcm)) . $pcm;
    }

    // ============================================================= avatar

    public function avatar_start($script, $locale, $title) {
        list($p, $model, $route) = $this->gw->resolve('avatar');
        $o = json_decode((string) $route['options_json'], true) ?: array();
        $key = $this->gw->key_for($p);
        $base = $this->gw->endpoint($p);
        $voice_for = function ($en_key, $ar_key) use ($o, $locale) {
            return ($locale === 'ar' && !empty($o[$ar_key])) ? $o[$ar_key] : (isset($o[$en_key]) ? $o[$en_key] : null);
        };

        switch (self::adapter($p, 'avatar')) {
            case 'heygen':
                $voice = $voice_for('voice_id', 'voice_id_ar');
                if (empty($o['avatar_id']) || !$voice) {
                    throw new RuntimeException('HeyGen needs avatar_id and voice_id (and voice_id_ar for Arabic) in the avatar route options.');
                }
                $data = $this->gw->decode($this->gw->request('POST', $base . '/v3/videos',
                    array('Content-Type: application/json', 'X-Api-Key: ' . $key), array(
                        'type' => 'avatar', 'avatar_id' => $o['avatar_id'], 'script' => $script, 'voice_id' => $voice,
                        'title' => mb_substr($title, 0, 100),
                    )), $p);
                $ref = isset($data['data']['video_id']) ? $data['data']['video_id'] : (isset($data['video_id']) ? $data['video_id'] : null);
                if (!$ref) {
                    throw new RuntimeException('HeyGen did not return a video id.');
                }
                return array('provider' => $p['slug'], 'ref' => $ref);

            case 'did':
                if (empty($o['source_url'])) {
                    throw new RuntimeException('D-ID needs source_url (a presenter image URL) in the avatar route options.');
                }
                $voice = $voice_for('voice_id', 'voice_id_ar') ?: ($locale === 'ar' ? 'ar-SA-HamedNeural' : 'en-US-JennyNeural');
                $data = $this->gw->decode($this->gw->request('POST', $base . '/talks',
                    array('Content-Type: application/json', 'Authorization: Basic ' . $key), array(
                        'source_url' => $o['source_url'],
                        'script' => array('type' => 'text', 'input' => $script, 'provider' => array('type' => 'microsoft', 'voice_id' => $voice)),
                    )), $p);
                if (empty($data['id'])) {
                    throw new RuntimeException('D-ID did not return a talk id.');
                }
                return array('provider' => $p['slug'], 'ref' => $data['id']);

            case 'synthesia':
                if (empty($o['avatar'])) {
                    throw new RuntimeException('Synthesia needs an avatar id in the avatar route options (avatar=…).');
                }
                $data = $this->gw->decode($this->gw->request('POST', $base . '/videos',
                    array('Content-Type: application/json', 'Authorization: ' . $key), array(
                        'test' => !empty($o['test']),
                        'title' => mb_substr($title, 0, 100),
                        'input' => array(array('scriptText' => $script, 'avatar' => $o['avatar'],
                            'background' => isset($o['background']) ? $o['background'] : 'off_white')),
                    )), $p);
                if (empty($data['id'])) {
                    throw new RuntimeException('Synthesia did not return a video id.');
                }
                return array('provider' => $p['slug'], 'ref' => $data['id']);
        }
        throw new RuntimeException('No avatar adapter for ' . $p['name'] . '.');
    }

    public function avatar_poll($provider_slug, $ref) {
        $p = $this->gw->provider($provider_slug);
        $key = $this->gw->key_for($p);
        $base = $this->gw->endpoint($p);
        $pending = array('done' => false, 'url' => null, 'bytes' => null, 'error' => null);

        switch (self::adapter($p, 'avatar')) {
            case 'heygen':
                $data = $this->gw->decode($this->gw->request('GET', $base . '/v3/videos/' . rawurlencode($ref), array('X-Api-Key: ' . $key)), $p);
                $d = isset($data['data']) ? $data['data'] : $data;
                if ($d['status'] === 'completed') {
                    return array('done' => true, 'url' => $d['video_url'], 'bytes' => null, 'error' => null);
                }
                if ($d['status'] === 'failed') {
                    return array('done' => true, 'url' => null, 'bytes' => null, 'error' => 'HeyGen: ' . json_encode(isset($d['error']) ? $d['error'] : 'failed'));
                }
                return $pending;

            case 'did':
                $data = $this->gw->decode($this->gw->request('GET', $base . '/talks/' . rawurlencode($ref), array('Authorization: Basic ' . $key)), $p);
                if ($data['status'] === 'done') {
                    return array('done' => true, 'url' => $data['result_url'], 'bytes' => null, 'error' => null);
                }
                if (in_array($data['status'], array('error', 'rejected'), true)) {
                    return array('done' => true, 'url' => null, 'bytes' => null, 'error' => 'D-ID: ' . json_encode(isset($data['error']) ? $data['error'] : $data['status']));
                }
                return $pending;

            case 'synthesia':
                $data = $this->gw->decode($this->gw->request('GET', $base . '/videos/' . rawurlencode($ref), array('Authorization: ' . $key)), $p);
                if ($data['status'] === 'complete') {
                    return array('done' => true, 'url' => $data['download'], 'bytes' => null, 'error' => null);
                }
                if (in_array($data['status'], array('error', 'rejected'), true)) {
                    return array('done' => true, 'url' => null, 'bytes' => null, 'error' => 'Synthesia: ' . $data['status']);
                }
                return $pending;
        }
        throw new RuntimeException('No avatar adapter for ' . $p['name'] . '.');
    }

    // =============================================================== clips

    public function clip_start($prompt, $seconds = 8) {
        list($p, $model, $route) = $this->gw->resolve('clip');
        $key = $this->gw->key_for($p);
        $base = $this->gw->endpoint($p);

        switch (self::adapter($p, 'clip')) {
            case 'veo':
                if (!preg_match('#/v1beta$#', $base)) {
                    $base .= '/v1beta';
                }
                $seconds = in_array((int) $seconds, array(4, 6, 8), true) ? (int) $seconds : 8;
                $data = $this->gw->decode($this->gw->request('POST', $base . '/models/' . rawurlencode(preg_replace('#^models/#', '', $model)) . ':predictLongRunning',
                    array('Content-Type: application/json', 'x-goog-api-key: ' . $key),
                    array('instances' => array(array('prompt' => $prompt)),
                          'parameters' => array('aspectRatio' => '16:9', 'resolution' => '720p', 'durationSeconds' => $seconds))), $p);
                return array('provider' => $p['slug'], 'ref' => $data['name']);

            case 'luma':
                $data = $this->gw->decode($this->gw->request('POST', $base . '/generations',
                    array('Content-Type: application/json', 'Authorization: Bearer ' . $key),
                    array('prompt' => $prompt, 'model' => $model, 'aspect_ratio' => '16:9', 'resolution' => '720p', 'duration' => '5s')), $p);
                return array('provider' => $p['slug'], 'ref' => $data['id']);

            case 'runway':
                $data = $this->gw->decode($this->gw->request('POST', $base . '/v1/text_to_video',
                    array('Content-Type: application/json', 'Authorization: Bearer ' . $key, 'X-Runway-Version: 2024-11-06'),
                    array('model' => $model, 'promptText' => mb_substr($prompt, 0, 1000), 'ratio' => '1280:720', 'duration' => max(2, min(10, (int) $seconds)))), $p);
                return array('provider' => $p['slug'], 'ref' => $data['id']);
        }
        throw new RuntimeException('No clip adapter for ' . $p['name'] . '. Supported: ' . implode(', ', self::supported_list('clip')) . '.');
    }

    public function clip_poll($provider_slug, $ref) {
        $p = $this->gw->provider($provider_slug);
        $key = $this->gw->key_for($p);
        $base = $this->gw->endpoint($p);
        $pending = array('done' => false, 'url' => null, 'bytes' => null, 'error' => null);

        switch (self::adapter($p, 'clip')) {
            case 'veo':
                if (!preg_match('#/v1beta$#', $base)) {
                    $base .= '/v1beta';
                }
                $data = $this->gw->decode($this->gw->request('GET', $base . '/' . ltrim($ref, '/'), array('x-goog-api-key: ' . $key)), $p);
                if (empty($data['done'])) {
                    return $pending;
                }
                if (!empty($data['error'])) {
                    return array('done' => true, 'url' => null, 'bytes' => null, 'error' => 'Veo: ' . json_encode($data['error']));
                }
                $uri = isset($data['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'])
                    ? $data['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] : null;
                if (!$uri) {
                    return array('done' => true, 'url' => null, 'bytes' => null, 'error' => 'Veo finished without a video (it may have been filtered).');
                }
                // The file URI needs the key, so it is fetched here rather than handed on.
                $res = $this->gw->request('GET', $uri, array('x-goog-api-key: ' . $key), null, 600, true);
                return array('done' => true, 'url' => null, 'bytes' => $res['status'] === 200 ? $res['body'] : null,
                    'error' => $res['status'] === 200 ? null : 'Veo download HTTP ' . $res['status']);

            case 'luma':
                $data = $this->gw->decode($this->gw->request('GET', $base . '/generations/' . rawurlencode($ref), array('Authorization: Bearer ' . $key)), $p);
                if ($data['state'] === 'completed') {
                    return array('done' => true, 'url' => $data['assets']['video'], 'bytes' => null, 'error' => null);
                }
                if ($data['state'] === 'failed') {
                    return array('done' => true, 'url' => null, 'bytes' => null, 'error' => 'Luma: ' . (isset($data['failure_reason']) ? $data['failure_reason'] : 'failed'));
                }
                return $pending;

            case 'runway':
                $data = $this->gw->decode($this->gw->request('GET', $base . '/v1/tasks/' . rawurlencode($ref),
                    array('Authorization: Bearer ' . $key, 'X-Runway-Version: 2024-11-06')), $p);
                if ($data['status'] === 'SUCCEEDED') {
                    return array('done' => true, 'url' => $data['output'][0], 'bytes' => null, 'error' => null);
                }
                if (in_array($data['status'], array('FAILED', 'CANCELLED'), true)) {
                    return array('done' => true, 'url' => null, 'bytes' => null, 'error' => 'Runway: ' . (isset($data['failure']) ? $data['failure'] : $data['status']));
                }
                return $pending;
        }
        throw new RuntimeException('No clip adapter for ' . $p['name'] . '.');
    }

    // ================================================================ test

    /** A cheap authenticated read proving the key works, per vendor. */
    public function test(array $p) {
        $key = $this->gw->key_for($p);
        $base = $this->gw->endpoint($p);
        $adapter = self::adapter($p, in_array('tts', $p['capabilities'], true) ? 'tts' : 'avatar');
        $checks = array(
            'elevenlabs'   => array($base . '/v2/voices?page_size=1', array('xi-api-key: ' . $key)),
            'heygen'       => array($base . '/v3/users/me', array('X-Api-Key: ' . $key)),
            'did'          => array($base . '/credits', array('Authorization: Basic ' . $key)),
            'synthesia'    => array($base . '/videos?limit=1', array('Authorization: ' . $key)),
            'luma'         => array($base . '/generations?limit=1', array('Authorization: Bearer ' . $key)),
            'runway'       => array($base . '/v1/organization', array('Authorization: Bearer ' . $key, 'X-Runway-Version: 2024-11-06')),
            'azure_speech' => array($base . '/cognitiveservices/voices/list', array('Ocp-Apim-Subscription-Key: ' . $key)),
        );
        if ($adapter === 'google_tts') {
            $token = $this->gw->bearer_for($p);
            $checks['google_tts'] = array($base . '/v1/voices?languageCode=ar-XA',
                array($token ? 'Authorization: Bearer ' . $token : 'x-goog-api-key: ' . $key));
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $p['slug']));
        if (isset($checks[$slug === 'did' ? 'did' : $adapter])) {
            list($url, $headers) = $checks[$slug === 'did' ? 'did' : $adapter];
        } elseif (isset($checks[$slug])) {
            list($url, $headers) = $checks[$slug];
        } else {
            throw new RuntimeException('No test available for ' . $p['name'] . '. Run a job to verify it.');
        }
        if (strpos($url, '{') !== false) {
            throw new RuntimeException($p['name'] . ' needs its endpoint settings filled in first.');
        }
        $this->gw->decode($this->gw->request('GET', $url, $headers), $p);
        return 'Key accepted';
    }

    /** Save a finished render (bytes or an https URL) under FCPATH. Returns the relative path. */
    public function store($bytes_or_url, $dir, $basename, $ext = 'mp4') {
        $bytes = $bytes_or_url;
        if (is_string($bytes_or_url) && preg_match('#^https://#i', $bytes_or_url) && strlen($bytes_or_url) < 4096) {
            $res = $this->gw->request('GET', $bytes_or_url, array('Accept: */*'), null, 600, true);
            if ($res['status'] !== 200) {
                throw new RuntimeException('Download of the rendered video failed with HTTP ' . $res['status'] . '.');
            }
            $bytes = $res['body'];
        }
        if (strlen((string) $bytes) < 1024) {
            throw new RuntimeException('The rendered file is empty or truncated.');
        }
        $dir = rtrim($dir, '/') . '/';
        if (!is_dir(FCPATH . $dir)) {
            mkdir(FCPATH . $dir, 0755, true);
        }
        $rel = $dir . preg_replace('/[^a-z0-9_\-]/i', '-', $basename) . '.' . $ext;
        file_put_contents(FCPATH . $rel, $bytes);
        return $rel;
    }
}
