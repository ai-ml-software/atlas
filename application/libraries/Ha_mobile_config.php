<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Remote configuration for the native ALTUS mobile app.
 *
 * One settings document (ha_mobile_config, id = 1) edited from
 * /hkp/admin/mobile and served to the app at GET /api/v1/mobile/config.
 *
 * App keys (ha_mobile_app_key) identify an app build, not a person: they only
 * unlock the public remote config. Format altm_<12 hex>_<40 alnum>. The prefix
 * is stored in clear for an indexed lookup; the secret only as an HMAC,
 * compared in constant time. The plaintext is returned once, from create_key().
 */
class Ha_mobile_config {

    const KEY_PATTERN = '/^altm_([a-f0-9]{12})_([A-Za-z0-9]{40})$/';

    private $CI;
    private $crypto;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        require_once APPPATH . 'libraries/Ha_crypto.php';
        $this->crypto = new Ha_crypto();
    }

    /** Feature toggles = app tabs / modules the admin may hide. */
    public static function features() {
        return array(
            'learning'      => 'Learning (courses, plan, lessons)',
            'knowledge'     => 'Knowledge & SOPs',
            'assessments'   => 'Assessments',
            'assistant'     => 'AI assistant',
            'qr'            => 'QR scanner',
            'notifications' => 'Notifications',
            'team'          => 'Team & manager tools',
            'performance'   => 'Competencies, readiness & certificates',
            'demo'          => 'Demo mode on the welcome screen',
        );
    }

    public static function defaults() {
        $features = array();
        foreach (array_keys(self::features()) as $f) { $features[$f] = true; }
        return array(
            'api_base_url'     => '',
            'branding'         => array('app_name' => 'ALTUS Knowledge & Performance', 'app_name_ar' => 'ألتوس للمعرفة والأداء',
                                        'primary_color' => '#C45B2F', 'accent_color' => '#1F3A37', 'background_color' => '#F7F5F1',
                                        'logo_url' => '', 'splash_url' => ''),
            'features'         => $features,
            'default_language' => 'en',
            'support'          => array('email' => '', 'phone' => '', 'url' => ''),
            'platforms'        => array(
                'ios'     => array('min_version' => '1.0.0', 'latest_version' => '1.0.0', 'store_url' => ''),
                'android' => array('min_version' => '1.0.0', 'latest_version' => '1.0.0', 'store_url' => ''),
            ),
            'maintenance'      => array('enabled' => false, 'message_en' => '', 'message_ar' => ''),
        );
    }

    public function ready() {
        return $this->CI->db->table_exists('ha_mobile_config') && $this->CI->db->table_exists('ha_mobile_app_key');
    }

    /** @return array('settings' => array, 'version' => int, 'updated_at' => string|null) */
    public function get() {
        $row = $this->ready() ? $this->CI->db->get_where('ha_mobile_config', array('id' => 1))->row_array() : null;
        $saved = $row ? (json_decode((string) $row['settings_json'], true) ?: array()) : array();
        return array('settings' => self::merge(self::defaults(), $saved),
            'version' => $row ? (int) $row['version'] : 0, 'updated_at' => $row ? $row['updated_at'] : null);
    }

    private static function merge(array $base, array $over) {
        foreach ($base as $k => $v) {
            if (!array_key_exists($k, $over)) { continue; }
            $base[$k] = is_array($v) && is_array($over[$k]) ? self::merge($v, $over[$k]) : $over[$k];
        }
        return $base;
    }

    /** Validates and normalises admin input. Throws InvalidArgumentException with a readable message. */
    public static function validate(array $in) {
        $d = self::defaults();
        $s = self::merge($d, $in);
        $url = function ($v, $label, $allow_http_local = false) {
            $v = trim((string) $v);
            if ($v === '') { return ''; }
            $p = parse_url($v);
            $local = $p && isset($p['host']) && in_array($p['host'], array('localhost', '127.0.0.1', '10.0.2.2'), true);
            if (!$p || empty($p['host']) || !isset($p['scheme']) || !($p['scheme'] === 'https' || ($allow_http_local && $p['scheme'] === 'http' && $local))
                || isset($p['user']) || isset($p['pass']) || mb_strlen($v) > 500) {
                throw new InvalidArgumentException($label . ': use a full https:// address.');
            }
            return $allow_http_local ? rtrim($v, '/') : $v;
        };
        $color = function ($v, $label) {
            $v = trim((string) $v);
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) { throw new InvalidArgumentException($label . ': use a colour like #C45B2F.'); }
            return strtoupper($v);
        };
        $text = function ($v, $max) { return mb_substr(trim(strip_tags((string) $v)), 0, $max); };
        $ver = function ($v, $label) {
            $v = trim((string) $v);
            if (!preg_match('/^\d{1,4}(\.\d{1,4}){0,2}$/', $v)) { throw new InvalidArgumentException($label . ': use a version like 1.2.0.'); }
            return $v;
        };
        $out = array();
        $out['api_base_url'] = $url($s['api_base_url'], 'API base URL', true);
        $b = $s['branding'];
        $out['branding'] = array('app_name' => $text($b['app_name'], 60) ?: $d['branding']['app_name'],
            'app_name_ar' => $text($b['app_name_ar'], 60) ?: $d['branding']['app_name_ar'],
            'primary_color' => $color($b['primary_color'], 'Primary colour'), 'accent_color' => $color($b['accent_color'], 'Accent colour'),
            'background_color' => $color($b['background_color'], 'Background colour'),
            'logo_url' => $url($b['logo_url'], 'Logo URL'), 'splash_url' => $url($b['splash_url'], 'Splash image URL'));
        $out['features'] = array();
        foreach (array_keys(self::features()) as $f) { $out['features'][$f] = !empty($s['features'][$f]) && $s['features'][$f] !== '0'; }
        $out['default_language'] = in_array($s['default_language'], array('en', 'ar', 'device'), true) ? $s['default_language'] : 'en';
        $email = trim((string) $s['support']['email']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Support email is not valid.'); }
        $phone = trim((string) $s['support']['phone']);
        if ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{5,25}$/', $phone)) { throw new InvalidArgumentException('Support phone is not valid.'); }
        $out['support'] = array('email' => $email, 'phone' => $phone, 'url' => $url($s['support']['url'], 'Support URL'));
        $out['platforms'] = array();
        foreach (array('ios' => 'iOS', 'android' => 'Android') as $p => $label) {
            $min = $ver($s['platforms'][$p]['min_version'], $label . ' minimum version');
            $latest = $ver($s['platforms'][$p]['latest_version'], $label . ' latest version');
            if (version_compare($min, $latest, '>')) { throw new InvalidArgumentException($label . ': minimum version cannot be above the latest version.'); }
            $out['platforms'][$p] = array('min_version' => $min, 'latest_version' => $latest, 'store_url' => $url($s['platforms'][$p]['store_url'], $label . ' store link'));
        }
        $m = $s['maintenance'];
        $out['maintenance'] = array('enabled' => !empty($m['enabled']) && $m['enabled'] !== '0',
            'message_en' => $text($m['message_en'], 500), 'message_ar' => $text($m['message_ar'], 500));
        if ($out['maintenance']['enabled'] && $out['maintenance']['message_en'] === '' && $out['maintenance']['message_ar'] === '') {
            throw new InvalidArgumentException('Write a maintenance message before turning maintenance on.');
        }
        return $out;
    }

    /** Simple save (no drafts). Bumps the version and writes an audit row with before/after. */
    public function save(array $input, $actor_id) {
        if (!$this->ready()) { throw new RuntimeException('Run migration 031 (mobile app settings) first.'); }
        $before = $this->get();
        $clean = self::validate($input);
        $now = date('Y-m-d H:i:s');
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($before['version']) {
            $this->CI->db->where('id', 1)->update('ha_mobile_config', array('settings_json' => $json, 'version' => $before['version'] + 1, 'updated_by' => (int) $actor_id, 'updated_at' => $now));
        } else {
            $this->CI->db->insert('ha_mobile_config', array('id' => 1, 'settings_json' => $json, 'version' => 1, 'updated_by' => (int) $actor_id, 'updated_at' => $now));
        }
        $this->audit('update', 1, 'Mobile app settings saved', array('before' => $before['settings'], 'after' => $clean));
        return $this->get();
    }

    // ---------------------------------------------------------------- keys

    public function keys() {
        if (!$this->ready()) { return array(); }
        return $this->CI->db->select('id,name,prefix,platform,created_by,created_at,last_used_at,revoked_at,revoked_by')
            ->order_by('revoked_at IS NULL', 'DESC', false)->order_by('id', 'DESC')->get('ha_mobile_app_key')->result_array();
    }

    /** @return array('id' => int, 'key' => plaintext shown once) */
    public function create_key($name, $platform, $actor_id) {
        if (!$this->ready()) { throw new RuntimeException('Run migration 031 (mobile app settings) first.'); }
        $name = trim((string) $name);
        if ($name === '' || mb_strlen($name) > 120) { throw new InvalidArgumentException('Give the key a name of 1 to 120 characters.'); }
        $platform = in_array($platform, array('ios', 'android', 'all'), true) ? $platform : 'all';
        $prefix = substr(bin2hex(random_bytes(8)), 0, 12);
        $secret = substr(preg_replace('/[^A-Za-z0-9]/', '', base64_encode(random_bytes(60))), 0, 40);
        $this->CI->db->insert('ha_mobile_app_key', array('name' => $name, 'prefix' => $prefix, 'secret_hash' => $this->crypto->hmac($secret),
            'platform' => $platform, 'created_by' => (int) $actor_id, 'created_at' => date('Y-m-d H:i:s')));
        $id = (int) $this->CI->db->insert_id();
        $this->audit('create', $id, 'Mobile app key created: ' . $name . ' (altm_' . $prefix . ')');
        return array('id' => $id, 'key' => 'altm_' . $prefix . '_' . $secret);
    }

    public function revoke_key($id, $actor_id) {
        if (!$this->ready()) { return false; }
        $this->CI->db->where('id', (int) $id)->where('revoked_at IS NULL', null, false)
            ->update('ha_mobile_app_key', array('revoked_at' => date('Y-m-d H:i:s'), 'revoked_by' => (int) $actor_id));
        $ok = $this->CI->db->affected_rows() > 0;
        if ($ok) { $this->audit('delete', (int) $id, 'Mobile app key revoked'); }
        return $ok;
    }

    /** Issue a replacement with the same name/platform, then revoke the old key. */
    public function rotate_key($id, $actor_id) {
        $old = $this->CI->db->get_where('ha_mobile_app_key', array('id' => (int) $id))->row_array();
        if (!$old || $old['revoked_at']) { throw new InvalidArgumentException('That key is not active.'); }
        $new = $this->create_key($old['name'], $old['platform'], $actor_id);
        $this->revoke_key($id, $actor_id);
        return $new;
    }

    /** @return array|null the active key row */
    public function authenticate($presented) {
        if (!$this->ready() || !is_string($presented) || !preg_match(self::KEY_PATTERN, $presented, $m)) { return null; }
        $row = $this->CI->db->get_where('ha_mobile_app_key', array('prefix' => $m[1]))->row_array();
        if (!$row || $row['revoked_at'] || !hash_equals($row['secret_hash'], $this->crypto->hmac($m[2]))) { return null; }
        if (!$row['last_used_at'] || strtotime($row['last_used_at']) < time() - 300) {
            $this->CI->db->where('id', $row['id'])->update('ha_mobile_app_key', array('last_used_at' => date('Y-m-d H:i:s')));
        }
        return $row;
    }

    /** The public payload and its strong ETag. */
    public function payload() {
        $c = $this->get();
        $data = array('version' => $c['version'], 'updated_at' => $c['updated_at']) + $c['settings'];
        return array('data' => $data, 'etag' => '"' . substr(hash('sha256', json_encode($data)), 0, 32) . '"');
    }

    private function audit($action, $id, $description, array $extra = array()) {
        $this->CI->load->library('ha_audit');
        $this->CI->ha_audit->log($action, 'mobile_app', $id, array('description' => $description) + $extra);
    }
}
