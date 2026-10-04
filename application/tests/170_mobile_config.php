<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Mobile_config_test_input {
    private $m; public function __construct($m) { $this->m = $m; } public function method() { return $this->m; }
}

require_once APPPATH . 'core/Hkp_Controller.php';
class Mobile_config_nav_probe extends Hkp_Controller {
    public function __construct() {}
    public function items() { return $this->navigation(); }
}

/** Mobile remote config: admin save + audit, app-key lifecycle and the HTTP contract (auth, ETag, 304). */
class Test_mobile_config extends Ha_testcase {
    private $admin;

    public function setUp() {
        $this->CI->load->library(array('ha_mobile_config', 'ha_api_keys', 'ha_audit'));
        require_once APPPATH . 'controllers/Mobile_config.php';
        $this->admin = (int) $this->db->get_where('users', array('email' => 'admin@hospitalityacademy.sa'))->row('id');
        $this->CI->ha_auth->assume($this->admin);
        $this->db->where('bucket', 'mobile_cfg_ip:203.0.113.9')->delete('ha_auth_attempt');
    }

    private function call($key, $etag = '', $method = 'get', $ip = '203.0.113.9') {
        $ref = new ReflectionClass('Mobile_config'); $c = $ref->newInstanceWithoutConstructor();
        foreach (array('db', 'load', 'ha_mobile_config', 'ha_api_keys') as $p) { $c->$p = $this->CI->$p; }
        $c->input = new Mobile_config_test_input($method);
        $r = $c->handle($key, $etag, $ip); $r['json'] = json_decode($r['body'], true);
        return $r;
    }

    public function test_tables_exist_and_defaults_are_complete() {
        $this->assertTrue($this->CI->ha_mobile_config->ready(), 'migration 031 tables');
        $d = Ha_mobile_config::defaults();
        foreach (array('api_base_url', 'branding', 'features', 'default_language', 'support', 'platforms', 'maintenance') as $k) { $this->assertTrue(array_key_exists($k, $d), $k); }
    }

    public function test_save_validates_versions_and_audits() {
        $M = $this->CI->ha_mobile_config; $before = $M->get();
        $in = Ha_mobile_config::defaults();
        $in['api_base_url'] = 'https://api.example.test/'; $in['branding']['primary_color'] = '#112233';
        $in['features']['assistant'] = '0'; $in['platforms']['android']['min_version'] = '1.0.0'; $in['platforms']['android']['latest_version'] = '1.2.0';
        $saved = $M->save($in, $this->admin);
        $this->assertEquals($before['version'] + 1, $saved['version']);
        $this->assertEquals('https://api.example.test', $saved['settings']['api_base_url'], 'trailing slash trimmed');
        $this->assertFalse($saved['settings']['features']['assistant']);
        $this->assertTrue($saved['settings']['features']['learning']);
        $this->assertDatabaseHas('ha_audit_log', array('entity_type' => 'mobile_app', 'action' => 'update'));
        $bad = $in; $bad['api_base_url'] = 'http://evil.test';
        $this->assertThrows(function () use ($M, $bad) { $M->save($bad, $this->admin); }, 'plain http rejected');
        $bad = $in; $bad['branding']['primary_color'] = 'red';
        $this->assertThrows(function () use ($M, $bad) { $M->save($bad, $this->admin); }, 'colour validated');
        $bad = $in; $bad['platforms']['ios']['min_version'] = '3.0.0'; $bad['platforms']['ios']['latest_version'] = '2.0.0';
        $this->assertThrows(function () use ($M, $bad) { $M->save($bad, $this->admin); }, 'min above latest rejected');
        $bad = $in; $bad['maintenance']['enabled'] = '1';
        $this->assertThrows(function () use ($M, $bad) { $M->save($bad, $this->admin); }, 'maintenance needs a message');
    }

    public function test_key_is_hashed_and_shown_once() {
        $M = $this->CI->ha_mobile_config; $k = $M->create_key('Test build', 'android', $this->admin);
        $this->assertMatches(Ha_mobile_config::KEY_PATTERN, $k['key']);
        $row = $this->db->get_where('ha_mobile_app_key', array('id' => $k['id']))->row_array();
        $this->assertNotContains(substr($k['key'], 18), json_encode($row), 'secret not stored in clear');
        foreach ($M->keys() as $listed) { $this->assertFalse(isset($listed['secret_hash']), 'listing hides the hash'); }
        $this->assertNotNull($M->authenticate($k['key']));
        $this->assertNull($M->authenticate($k['key'] . 'x'));
        $this->assertNull($M->authenticate(substr($k['key'], 0, -1) . (substr($k['key'], -1) === 'A' ? 'B' : 'A')), 'wrong secret');
    }

    public function test_endpoint_auth_etag_and_rotation() {
        $M = $this->CI->ha_mobile_config;
        $r = $this->call(null); $this->assertEquals(401, $r['status']); $this->assertFalse($r['json']['success']);
        $r = $this->call('ha_000000000000_' . str_repeat('a', 40)); $this->assertEquals(401, $r['status'], 'personal keys are not app keys');
        $k = $M->create_key('Endpoint', 'all', $this->admin);
        $r = $this->call($k['key']); $this->assertEquals(200, $r['status']); $this->assertTrue($r['json']['success']);
        $this->assertNotEmpty($r['headers']['ETag']); $this->assertContains('max-age', $r['headers']['Cache-Control']);
        foreach (array('version', 'branding', 'features', 'platforms', 'maintenance', 'support', 'default_language', 'api_base_url') as $f) { $this->assertTrue(array_key_exists($f, $r['json']['data']), 'payload.' . $f); }
        $this->assertNotContains('secret', strtolower($r['body']));
        $etag = $r['headers']['ETag'];
        $r = $this->call($k['key'], $etag); $this->assertEquals(304, $r['status']); $this->assertEquals('', $r['body']);
        $r = $this->call($k['key'], 'W/' . $etag); $this->assertEquals(304, $r['status'], 'weak validator accepted');
        $in = $M->get()['settings']; $in['support']['email'] = 'help@example.test'; $M->save($in, $this->admin);
        $r = $this->call($k['key'], $etag); $this->assertEquals(200, $r['status'], 'changed config invalidates ETag');
        $this->assertEquals('help@example.test', $r['json']['data']['support']['email']);
        $this->assertEquals(405, $this->call($k['key'], '', 'post')['status']);
        // Rotation: new key works, old one stops at once.
        $new = $M->rotate_key($k['id'], $this->admin);
        $this->assertEquals(401, $this->call($k['key'])['status'], 'old key rejected after rotation');
        $this->assertEquals(200, $this->call($new['key'])['status'], 'rotated key accepted');
        $this->assertTrue($M->revoke_key($new['id'], $this->admin));
        $this->assertEquals(401, $this->call($new['key'])['status'], 'revoked key rejected');
        $this->assertFalse($M->revoke_key($new['id'], $this->admin), 'double revoke is a no-op');
        $this->assertThrows(function () use ($M, $new) { $M->rotate_key($new['id'], $this->admin); }, 'cannot rotate a revoked key');
    }

    public function test_failed_attempts_are_throttled() {
        for ($i = 0; $i < Mobile_config::MAX_FAILURES_PER_IP; $i++) { $this->call('bad', '', 'get', '203.0.113.9'); }
        $this->assertEquals(429, $this->call('bad')['status']);
        $this->db->where('bucket', 'mobile_cfg_ip:203.0.113.9')->delete('ha_auth_attempt');
    }

    public function test_admin_route_is_permission_gated() {
        $src = file_get_contents(APPPATH . 'controllers/Hkp_admin.php');
        $this->assertMatches("/function mobile\\(.*?need\\('settings\\.view'\\).*?need\\('settings\\.update'\\).*?post_guard/s", $src);
    }

    public function test_menu_shows_mobile_settings_only_to_system_administrators() {
        $this->CI->load->helper('hkp');
        $probe = new Mobile_config_nav_probe(); $probe->ha_auth = $this->CI->ha_auth;
        $items = $probe->items(); $keys = array_column($items['platform']['items'], 'key');
        $this->assertTrue(in_array('mobile_app', $keys, true), 'system admin has a direct mobile settings entry');
        foreach (array('org.admin@dyafagroup.sa', 'demo.learner@altusdemo.sa') as $email) {
            $uid = (int) $this->db->get_where('users', array('email' => $email))->row('id');
            $this->CI->ha_auth->assume($uid);
            $all = array(); foreach ($probe->items() as $group) { $all = array_merge($all, array_column($group['items'], 'key')); }
            $this->assertFalse(in_array('mobile_app', $all, true), $email . ' cannot see global configuration');
        }
    }
}
