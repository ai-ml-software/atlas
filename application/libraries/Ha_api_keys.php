<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Personal API keys for /api/v1.
 *
 * Format: ha_<12-char prefix>_<40-char secret>. The prefix is stored in clear
 * and indexed, so lookup is one indexed read rather than a scan of hashes;
 * the secret is stored only as an HMAC and compared in constant time. The
 * full key is returned exactly once, from create().
 *
 * A key acts as its owner, never as more: every request is still checked
 * against the owner's live role, so revoking a permission from a person
 * revokes it from their keys in the same moment.
 */
class Ha_api_keys {

    /** scope => description. A key carries only the scopes it was given. */
    public static function scopes() {
        return array(
            'profile:read'     => 'Read the key owner\'s profile',
            'courses:read'     => 'Read the published catalogue',
            'enrollments:read' => 'Read the owner\'s enrolments and progress',
            'ai:generate'      => 'Queue AI generation jobs (needs the AI Studio generate permission)',
            'ai:jobs'          => 'Read the status and output of the owner\'s AI jobs',
            'performance:read' => 'Read competencies, readiness, action plans and certificates (own, or the team the owner manages)',
            'team:read'        => 'Read people and capability gaps in the owner\'s scope',
            'knowledge:read'   => 'Search approved knowledge the owner may see',
            'kpis:read'        => 'Read KPI scorecards for properties in the owner\'s scope',
        );
    }

    const MAX_FAILURES_PER_IP = 30;   // per 15 minutes

    private $CI;
    private $crypto;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        require_once APPPATH . 'libraries/Ha_crypto.php';
        $this->crypto = new Ha_crypto();
    }

    /** @return array('id' => int, 'key' => plaintext shown once) */
    public function create($user_id, $name, array $scopes, $expires_at = null, $allowed_ips = null) {
        $name = trim((string) $name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Give the key a name of 1 to 120 characters.');
        }
        $known = array_keys(self::scopes());
        $scopes = array_values(array_intersect($known, $scopes));
        if (!$scopes) {
            throw new InvalidArgumentException('Choose at least one scope.');
        }
        if ($expires_at !== null && $expires_at !== '' && strtotime($expires_at) <= time()) {
            throw new InvalidArgumentException('The expiry date must be in the future.');
        }
        $ips = $this->parse_ips($allowed_ips);

        $prefix = substr(bin2hex(random_bytes(8)), 0, 12);
        $secret = rtrim(strtr(base64_encode(random_bytes(30)), '+/', 'AZ'), '=');
        $now = date('Y-m-d H:i:s');

        $this->CI->db->insert('ha_api_key', array(
            'user_id'     => (int) $user_id,
            'name'        => $name,
            'prefix'      => $prefix,
            'secret_hash' => $this->crypto->hmac($secret),
            'scopes'      => implode(' ', $scopes),
            'allowed_ips' => $ips ? implode(',', $ips) : null,
            'expires_at'  => ($expires_at ? date('Y-m-d 23:59:59', strtotime($expires_at)) : null),
            'created_at'  => $now,
        ));
        return array('id' => (int) $this->CI->db->insert_id(), 'key' => 'ha_' . $prefix . '_' . $secret);
    }

    public function for_user($user_id) {
        return $this->CI->db->where('user_id', (int) $user_id)
            ->order_by('revoked_at IS NULL', 'DESC', false)
            ->order_by('created_at', 'DESC')
            ->get('ha_api_key')->result_array();
    }

    /** A user may only revoke their own keys; an admin passes $any = true. */
    public function revoke($key_id, $actor_id, $any = false) {
        $this->CI->db->where('id', (int) $key_id)->where('revoked_at IS NULL', null, false);
        if (!$any) {
            $this->CI->db->where('user_id', (int) $actor_id);
        }
        $this->CI->db->update('ha_api_key', array(
            'revoked_at' => date('Y-m-d H:i:s'),
            'revoked_by' => (int) $actor_id,
        ));
        return $this->CI->db->affected_rows() > 0;
    }

    /** Pull a key from "Authorization: Bearer ha_…" or "X-API-Key: ha_…". */
    public static function from_request() {
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION']
              : (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '');
        if ($auth === '' && function_exists('apache_request_headers')) {
            $headers = array_change_key_case((array) apache_request_headers(), CASE_LOWER);
            $auth = isset($headers['authorization']) ? $headers['authorization'] : '';
        }
        if (preg_match('/^Bearer\s+(ha_\S+)$/i', trim($auth), $m)) {
            return $m[1];
        }
        return isset($_SERVER['HTTP_X_API_KEY']) ? trim($_SERVER['HTTP_X_API_KEY']) : null;
    }

    /**
     * @return array('ok' => bool, 'status' => http, 'error' => string, 'key' => row, 'user' => row)
     */
    public function authenticate($presented, $ip) {
        if ($this->throttled('api_ip:' . $ip, self::MAX_FAILURES_PER_IP, 900)) {
            return $this->refuse(429, 'Too many failed key attempts from this address. Try again later.');
        }
        if (!is_string($presented) || !preg_match('/^ha_([a-f0-9]{12})_([A-Za-z0-9]{40})$/', $presented, $m)) {
            $this->attempt('api_ip:' . $ip, false, $ip);
            return $this->refuse(401, 'Missing or malformed API key.');
        }
        $row = $this->CI->db->get_where('ha_api_key', array('prefix' => $m[1]))->row_array();
        if (!$row || !hash_equals($row['secret_hash'], $this->crypto->hmac($m[2]))) {
            $this->attempt('api_ip:' . $ip, false, $ip);
            return $this->refuse(401, 'Invalid API key.');
        }
        if ($row['revoked_at'] !== null) {
            return $this->refuse(401, 'This API key has been revoked.');
        }
        if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
            return $this->refuse(401, 'This API key has expired.');
        }
        if ($row['allowed_ips'] && !in_array($ip, explode(',', $row['allowed_ips']), true)) {
            return $this->refuse(403, 'This API key is not allowed from ' . $ip . '.');
        }
        $user = $this->CI->db->get_where('users', array('id' => (int) $row['user_id']))->row_array();
        if (!$user || (int) $user['status'] !== 1) {
            return $this->refuse(401, 'The owner of this API key is not an active account.');
        }

        $this->CI->db->set('use_count', 'use_count + 1', false)
            ->set('last_used_at', date('Y-m-d H:i:s'))
            ->set('last_used_ip', $ip)
            ->where('id', $row['id'])->update('ha_api_key');

        return array('ok' => true, 'status' => 200, 'error' => null, 'key' => $row, 'user' => $user);
    }

    public static function has_scope(array $key, $scope) {
        return in_array($scope, explode(' ', (string) $key['scopes']), true);
    }

    // ------------------------------------------------------------ throttling

    public function attempt($bucket, $ok, $ip = null) {
        $this->CI->db->insert('ha_auth_attempt', array(
            'bucket' => substr($bucket, 0, 120), 'ok' => $ok ? 1 : 0, 'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function throttled($bucket, $max_failures, $seconds) {
        $since = date('Y-m-d H:i:s', time() - $seconds);
        $n = $this->CI->db->where('bucket', $bucket)->where('ok', 0)
            ->where('created_at >=', $since)->count_all_results('ha_auth_attempt');
        return $n >= $max_failures;
    }

    private function refuse($status, $error) {
        return array('ok' => false, 'status' => $status, 'error' => $error, 'key' => null, 'user' => null);
    }

    private function parse_ips($raw) {
        $out = array();
        foreach (preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new InvalidArgumentException('Not an IP address: ' . $ip);
            }
            $out[] = $ip;
        }
        return $out;
    }
}
