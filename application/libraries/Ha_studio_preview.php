<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Signed, expiring links to private draft previews.
 *
 * A link carries "<expiry>.<hmac>" bound to the object kind and id. The
 * signature alone never grants access: the public controllers still require a
 * signed-in user with edit permission for that object (preview links require
 * authorization). Expired, altered or foreign links simply show the published
 * page, so ordinary visitors keep seeing published content.
 */
class Ha_studio_preview {
    const TTL = 86400;
    private $CI;
    private static $secret;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    /** Per-installation secret, created once and kept in the settings table. */
    private function secret() {
        if (self::$secret) { return self::$secret; }
        $key = (string) $this->CI->config->item('encryption_key');
        if ($key === '' && $this->CI->db->table_exists('settings')) {
            $row = $this->CI->db->get_where('settings', array('key' => 'studio_preview_secret'))->row_array();
            if (!$row) {
                $this->CI->db->insert('settings', array('key' => 'studio_preview_secret', 'value' => bin2hex(random_bytes(32))));
                $row = $this->CI->db->get_where('settings', array('key' => 'studio_preview_secret'))->row_array();
            }
            $key = (string) $row['value'];
        }
        if ($key === '') { throw new RuntimeException('Preview signing is not configured.'); }
        return self::$secret = hash('sha256', 'altus-studio-preview|' . $key, true);
    }

    private function mac($kind, $id, $expires) {
        return hash_hmac('sha256', $kind . '|' . (int) $id . '|' . (int) $expires, $this->secret());
    }

    /** Token for one object, valid for $ttl seconds (at most 24 hours). */
    public function token($kind, $id, $ttl = self::TTL) {
        $expires = time() + max(60, min(self::TTL, (int) $ttl));
        return $expires . '.' . $this->mac($kind, $id, $expires);
    }

    public function verify($kind, $id, $token) {
        if (!is_string($token) || !preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $m)) { return false; }
        $expires = (int) $m[1];
        if ($expires < time() || $expires > time() + self::TTL + 60) { return false; }
        return hash_equals($this->mac($kind, $id, $expires), $m[2]);
    }

    /** Adds the signature (and expiry) to a preview URL. */
    public function sign_url($url, $kind, $id, $ttl = self::TTL) {
        return $url . (strpos($url, '?') === false ? '?' : '&') . 'studio_sig=' . rawurlencode($this->token($kind, $id, $ttl));
    }

    public static function expires_at($token) {
        return preg_match('/^(\d{10})\./', (string) $token, $m) ? (int) $m[1] : 0;
    }
}
