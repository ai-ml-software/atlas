<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * GET /api/v1/mobile/config — remote configuration for the native app.
 *
 *   X-App-Key: altm_<prefix>_<secret>     (or Authorization: Bearer altm_…)
 *   If-None-Match: "<etag>"               → 304 when unchanged
 *
 * Envelope matches /mobile_api: {success, data, message}. Contains no personal
 * data; the key only identifies an app build and can be rotated from
 * /hkp/admin/mobile without shipping a new binary to existing signed-in users.
 */
class Mobile_config extends CI_Controller {

    const MAX_FAILURES_PER_IP = 30; // per 15 minutes

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->helper(array('ha_security'));
        $this->load->library(array('ha_mobile_config', 'ha_api_keys'));
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        $this->config->load('ha_mobile', true);
        $origins = (array) $this->config->item('web_origins', 'ha_mobile');
        if ($origin && in_array($origin, $origins, true)) {
            $this->output->set_header('Access-Control-Allow-Origin: ' . $origin);
            $this->output->set_header('Access-Control-Allow-Headers: X-App-Key, Authorization, If-None-Match, Accept');
            $this->output->set_header('Access-Control-Allow-Methods: GET, OPTIONS');
            $this->output->set_header('Access-Control-Expose-Headers: ETag');
        }
    }

    public function index() {
        if (strtoupper($this->input->method()) === 'OPTIONS') { return $this->output->set_status_header(204)->set_output(''); }
        $r = $this->handle(self::presented_key(), isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '', ha_client_ip());
        $this->output->set_content_type('application/json', 'utf-8')->set_status_header($r['status']);
        foreach ($r['headers'] as $k => $v) { $this->output->set_header($k . ': ' . $v); }
        $this->output->set_output($r['body']);
    }

    /** Pure request handling, used directly by the tests. */
    public function handle($presented, $if_none_match, $ip) {
        $headers = array('X-Content-Type-Options' => 'nosniff', 'Vary' => 'Origin, X-App-Key, Authorization');
        if (strtoupper((string) $this->input->method()) !== 'GET') { return $this->reply(405, null, 'Use GET.', $headers + array('Allow' => 'GET')); }
        $bucket = 'mobile_cfg_ip:' . $ip;
        if ($this->ha_api_keys->throttled($bucket, self::MAX_FAILURES_PER_IP, 900)) { return $this->reply(429, null, 'Too many failed attempts. Try again later.', $headers + array('Retry-After' => '900')); }
        $key = $this->ha_mobile_config->authenticate($presented);
        if (!$key) {
            $this->ha_api_keys->attempt($bucket, false, $ip);
            return $this->reply(401, null, 'A valid mobile app key is required.', $headers + array('Cache-Control' => 'no-store', 'WWW-Authenticate' => 'Bearer realm="altus-mobile"'));
        }
        $p = $this->ha_mobile_config->payload();
        $headers += array('ETag' => $p['etag'], 'Cache-Control' => 'private, max-age=300');
        $tags = array_map('trim', explode(',', str_replace('W/', '', (string) $if_none_match)));
        if (in_array($p['etag'], $tags, true)) { return array('status' => 304, 'headers' => $headers, 'body' => ''); }
        return $this->reply(200, $p['data'], 'Operation completed successfully', $headers);
    }

    public static function presented_key() {
        if (!empty($_SERVER['HTTP_X_APP_KEY'])) { return trim($_SERVER['HTTP_X_APP_KEY']); }
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '');
        return preg_match('/^Bearer\s+(altm_\S+)$/i', trim($auth), $m) ? $m[1] : null;
    }

    private function reply($status, $data, $message, array $headers) {
        return array('status' => $status, 'headers' => $headers,
            'body' => json_encode(array('success' => $status < 400, 'data' => $data, 'message' => $message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
