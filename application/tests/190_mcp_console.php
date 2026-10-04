<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/Hkp_Controller.php';
class Mcp_console_nav_probe extends Hkp_Controller {
    public function __construct() {}
    public function items() { return $this->navigation(); }
}

/**
 * MCP console (migration 033): access levels enforced server-side, personal connection tokens,
 * grant downgrade, per-client caps, consent levels, tester + smoke suite, sidebar visibility, gates.
 */
class Test_mcp_console extends Ha_testcase {
    private $saved; private $admin; private $tenant; private $learner; private $S; private $O; private $C;

    public function setUp() {
        $this->CI->load->library(array('ha_mcp_server', 'ha_mcp_oauth', 'ha_mcp_console', 'ha_audit')); $this->CI->load->helper(array('hkp', 'ha_security'));
        $this->S = $this->CI->ha_mcp_server; $this->O = $this->CI->ha_mcp_oauth; $this->C = $this->CI->ha_mcp_console;
        $this->saved = $this->CI->config->config['ha_publisher'];
        $this->CI->config->config['ha_publisher'] = array_merge($this->saved, array('mcp_enabled' => true, 'mcp_issuer' => 'http://altus.test', 'mcp_rate_limit' => 1000, 'mcp_ip_rate_limit' => 100000));
        $this->db->truncate('ha_mcp_rate'); $this->db->truncate('ha_mcp_setting');
        $this->admin = $this->user('admin@hospitalityacademy.sa'); $this->tenant = $this->user('org.admin@dyafagroup.sa'); $this->learner = $this->user('demo.learner@altusdemo.sa');
        $this->CI->ha_auth->assume($this->admin);
    }
    public function tearDown() { $this->CI->config->config['ha_publisher'] = $this->saved; $this->CI->ha_auth->assume($this->admin); }

    private function user($email) { return (int) $this->db->get_where('users', array('email' => $email))->row('id'); }
    private static function b64($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    private function http($method, $path, $body = '', array $headers = array(), array $query = array()) {
        $r = $this->S->dispatch(array('method' => $method, 'path' => $path, 'headers' => $headers, 'query' => $query, 'body' => is_array($body) ? json_encode($body) : $body, 'ip' => '127.0.0.19'));
        $this->CI->ha_auth->assume($this->admin);
        $r['json'] = json_decode($r['body'], true); $r['headers'] = array_change_key_case($r['headers'], CASE_LOWER); return $r;
    }
    /** Full OAuth flow; returns tokens + client id. $level is the consent-screen choice. */
    private function login($uid, $level = null) {
        $c = $this->http('POST', 'oauth/register', array('client_name' => 'Console test', 'redirect_uris' => array('http://127.0.0.1:4100/cb')), array('content-type' => 'application/json'))['json'];
        $v = self::b64(random_bytes(40));
        $r = $this->http('GET', 'oauth/authorize', '', array(), array('response_type' => 'code', 'client_id' => $c['client_id'], 'redirect_uri' => 'http://127.0.0.1:4100/cb', 'scope' => 'altus.read altus.content.write altus.course.write altus.media.write altus.publish', 'state' => 's', 'code_challenge' => self::b64(hash('sha256', $v, true)), 'code_challenge_method' => 'S256'));
        preg_match('/request=([a-f0-9]{48})/', $r['headers']['location'], $m);
        $this->CI->ha_auth->assume($uid); $A = $this->CI->ha_auth; $perms = $A->permissions(); if ($A->is_super_admin()) $perms = array_merge($perms, array('cms_pages.update', 'courses.update', 'media.create', 'cms_pages.publish', 'system.configure', 'knowledge.create'));
        $url = $this->O->consent($m[1], $uid, true, $perms, $A->is_system_scoped(), $level); $this->CI->ha_auth->assume($this->admin);
        parse_str(parse_url($url, PHP_URL_QUERY), $p);
        $t = $this->http('POST', 'oauth/token', http_build_query(array('grant_type' => 'authorization_code', 'code' => $p['code'] ?? '', 'redirect_uri' => 'http://127.0.0.1:4100/cb', 'client_id' => $c['client_id'], 'code_verifier' => $v)), array('content-type' => 'application/x-www-form-urlencoded'));
        $this->assertEquals(200, $t['status'], $t['body']);
        return $t['json'] + array('client_id' => $c['client_id'], 'grant_id' => $this->db->get_where('ha_mcp_grant', array('client_id' => $c['client_id']))->row('id'));
    }
    private function rpc($token, $method, $params = array()) { return $this->http('POST', 'mcp', array('jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params), array('authorization' => 'Bearer ' . $token, 'content-type' => 'application/json', 'mcp-protocol-version' => '2025-06-18')); }
    private function tools($token) { $r = $this->rpc($token, 'tools/list'); $this->assertEquals(200, $r['status'], $r['body']); return array_column($r['json']['result']['tools'], 'name'); }
    private function call($token, $name, array $args) { $r = $this->rpc($token, 'tools/call', array('name' => $name, 'arguments' => (object) $args)); $this->assertEquals(200, $r['status'], $r['body']); $res = $r['json']['result']; return $res['isError'] ? ($res['structuredContent']['error']['code'] ?? 'error') : 'ok'; }

    public function test_migration_033_tables() {
        foreach (array('ha_mcp_personal_token', 'ha_mcp_setting', 'ha_mcp_call_log') as $t) $this->assertTrue($this->db->table_exists($t), $t);
        $this->assertTrue($this->db->field_exists('max_level', 'ha_mcp_client'));
        $src = file_get_contents(APPPATH . 'migrations/20260101000033_mcp_console.php');
        $this->assertMatches('/function down\(\).*drop_columns\(\'ha_mcp_client\'.*ha_mcp_personal_token/s', $src, 'down() reverses up()');
        require_once APPPATH . 'migrations/20260101000033_mcp_console.php'; $m = new Migration_Mcp_console();
        $m->down(); $this->db->data_cache = array();
        $this->assertFalse($this->db->table_exists('ha_mcp_call_log')); $this->assertFalse($this->db->field_exists('max_level', 'ha_mcp_client')); $this->assertTrue($this->db->table_exists('ha_mcp_token'), '032 tables kept');
        $m->up(); $this->db->data_cache = array();
        $this->assertTrue($this->db->table_exists('ha_mcp_call_log')); $this->assertTrue($this->db->field_exists('max_level', 'ha_mcp_client'));
    }

    public function test_levels_read_write_full_enforced_on_the_server() {
        $this->assertEquals('read', Ha_mcp_oauth::level_of(array('altus.read')));
        $this->assertEquals('write', Ha_mcp_oauth::level_of(Ha_mcp_oauth::LEVELS['write']));
        $this->assertEquals('full', Ha_mcp_oauth::level_of(Ha_mcp_oauth::LEVELS['full']));
        $read = $this->login($this->admin, 'read');
        $this->assertEquals('altus.read', $this->db->get_where('ha_mcp_grant', array('id' => $read['grant_id']))->row('scope'));
        $tools = $this->tools($read['access_token']);
        $this->assertTrue(in_array('altus_get', $tools, true)); $this->assertFalse(in_array('altus_create', $tools, true)); $this->assertFalse(in_array('altus_request_publish', $tools, true));
        $this->assertEquals('insufficient_scope', $this->call($read['access_token'], 'altus_create', array('type' => 'articles', 'data' => array('title_en' => 'x'))));
        $write = $this->login($this->admin, 'write');
        $tools = $this->tools($write['access_token']);
        $this->assertTrue(in_array('altus_create', $tools, true)); $this->assertFalse(in_array('altus_publish_approved', $tools, true));
        $this->assertEquals('insufficient_scope', $this->call($write['access_token'], 'altus_request_publish', array('type' => 'articles', 'id' => 2147480000, 'operation' => 'publish')));
        $full = $this->login($this->admin, 'full');
        $this->assertTrue(in_array('altus_publish_approved', $this->tools($full['access_token']), true));
        $this->assertNotEquals('insufficient_scope', $this->call($full['access_token'], 'altus_request_publish', array('type' => 'articles', 'id' => 2147480000, 'operation' => 'publish')), 'full passes the level gate');
        // Full never exceeds the user's own permissions.
        $tenant = $this->login($this->tenant, 'full');
        $this->assertNotContains('altus.admin', explode(' ', $this->db->get_where('ha_mcp_grant', array('id' => $tenant['grant_id']))->row('scope')));
        // Call log rows (denied vs ok).
        $this->assertDatabaseHas('ha_mcp_call_log', array('grant_id' => $read['grant_id'], 'tool' => 'altus_create', 'status' => 'denied', 'error_code' => 'insufficient_scope'));
        $this->assertDatabaseHas('ha_mcp_call_log', array('grant_id' => $read['grant_id'], 'method' => 'tools/list', 'status' => 'ok'));
    }

    public function test_consent_caps_and_settings() {
        $this->assertThrows(function () { $this->O->save_consent_levels('full', 'read', $this->admin); }, 'default above max rejected');
        $this->O->save_consent_levels('read', 'write', $this->admin);
        $this->assertEquals(array('max' => 'write', 'default' => 'read'), $this->O->consent_offer(array()));
        $t = $this->login($this->admin, 'full');
        $this->assertEquals('write', Ha_mcp_oauth::level_of(explode(' ', $this->db->get_where('ha_mcp_grant', array('id' => $t['grant_id']))->row('scope'))), 'choice capped by console maximum');
        $this->assertEquals(array('max' => 'read', 'default' => 'read'), $this->O->consent_offer(array('max_level' => 'read')));
        $this->assertDatabaseHas('ha_audit_log', array('action' => 'mcp.levels'));
    }

    public function test_per_client_cap_applies_to_existing_connections() {
        $t = $this->login($this->admin, 'full');
        $this->assertTrue(in_array('altus_create', $this->tools($t['access_token']), true));
        $this->O->set_client_max_level($t['client_id'], 'read', $this->admin);
        $this->assertFalse(in_array('altus_create', $this->tools($t['access_token']), true), 'cap is immediate');
        $this->assertEquals('insufficient_scope', $this->call($t['access_token'], 'altus_create', array('type' => 'articles', 'data' => array('title_en' => 'x'))));
        $this->O->set_client_max_level($t['client_id'], '', $this->admin);
        $this->assertTrue(in_array('altus_create', $this->tools($t['access_token']), true), 'removing the cap restores the approved scopes');
        $this->assertThrows(function () use ($t) { $this->O->set_client_max_level($t['client_id'], 'root', $this->admin); });
        $this->O->revoke_client($t['client_id'], $this->admin);
        $this->assertEquals(401, $this->rpc($t['access_token'], 'ping')['status'], 'revoked app');
    }

    public function test_grant_downgrade_is_immediate_and_one_way() {
        $t = $this->login($this->admin, 'full');
        $this->assertEquals(array('altus.read', 'altus.content.write', 'altus.course.write', 'altus.media.write'), $this->O->set_grant_level($t['grant_id'], 'write', $this->admin));
        $this->assertFalse(in_array('altus_request_publish', $this->tools($t['access_token']), true), 'same access token, lower level');
        $this->assertThrows(function () use ($t) { $this->O->set_grant_level($t['grant_id'], 'full', $this->admin); }, 'no upgrades');
        $this->O->set_grant_level($t['grant_id'], 'read', $this->admin);
        $r = $this->http('POST', 'oauth/token', http_build_query(array('grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $t['client_id'])), array('content-type' => 'application/x-www-form-urlencoded'));
        $this->assertEquals(200, $r['status'], 'refresh still works after a downgrade: ' . $r['body']); $this->assertEquals('altus.read', $r['json']['scope']);
        $this->O->admin_revoke_grant($t['grant_id'], $this->admin);
        $this->assertEquals(401, $this->rpc($r['json']['access_token'], 'ping')['status']);
        $this->assertDatabaseHas('ha_audit_log', array('action' => 'mcp.grant_level'));
    }

    public function test_personal_token_lifecycle() {
        $this->assertThrows(function () { $this->O->create_personal_token($this->admin, '', 'read', array(), 30, $this->admin); }, 'name required');
        $this->assertThrows(function () { $this->O->create_personal_token($this->admin, 'x', 'read', array(), 900, $this->admin); }, 'expiry bounded');
        $this->assertThrows(function () { $this->O->create_personal_token($this->admin, 'x', 'root', array(), 30, $this->admin); }, 'level validated');
        $pt = $this->O->create_personal_token($this->admin, 'Claude Code laptop', 'write', array(), 30, $this->admin);
        $this->assertMatches('/^altus_pt_[A-Za-z0-9_-]{40,}$/', $pt['token']);
        $row = $this->db->get_where('ha_mcp_personal_token', array('id' => $pt['id']))->row_array();
        $this->assertEquals(hash('sha256', $pt['token']), $row['token_hash']); $this->assertNotContains(substr($pt['token'], 9), json_encode($row), 'secret never stored');
        foreach ($this->O->personal_tokens() as $l) $this->assertFalse(isset($l['token_hash']), 'listing hides hashes');
        $init = $this->rpc($pt['token'], 'initialize', array('protocolVersion' => '2025-06-18'));
        $this->assertEquals(200, $init['status'], $init['body']); $this->assertNotEmpty($init['headers']['mcp-session-id']);
        $tools = $this->tools($pt['token']);
        $this->assertTrue(in_array('altus_create', $tools, true)); $this->assertFalse(in_array('altus_request_publish', $tools, true), 'write level');
        $this->assertDatabaseHas('ha_mcp_call_log', array('source' => 'personal', 'grant_id' => 'pt_' . $pt['id']));
        // Permissions are re-checked per call: a learner's full-level token still cannot write.
        $lt = $this->O->create_personal_token($this->learner, 'Learner', 'full', array(), 7, $this->admin);
        $this->assertFalse(in_array('altus_create', $this->tools($lt['token']), true), 'level never exceeds the person\'s permissions');
        // Custom scopes always include read.
        $ct = $this->O->create_personal_token($this->admin, 'Custom', 'custom', array('altus.media.write'), 7, $this->admin);
        $this->assertEquals(array('altus.read', 'altus.media.write'), $ct['scope']);
        // Expiry and revocation.
        $this->db->where('id', $ct['id'])->update('ha_mcp_personal_token', array('expires_at' => gmdate('Y-m-d H:i:s', time() - 5)));
        $this->assertEquals(401, $this->rpc($ct['token'], 'ping')['status'], 'expired');
        $this->assertTrue($this->O->revoke_personal_token($pt['id'], $this->admin));
        $this->assertFalse($this->O->revoke_personal_token($pt['id'], $this->admin), 'double revoke is a no-op');
        $this->assertEquals(401, $this->rpc($pt['token'], 'ping')['status'], 'revoked');
        $this->assertDatabaseMissing('ha_mcp_session', array('grant_id' => 'pt_' . $pt['id']));
        $this->assertEquals(401, $this->rpc('altus_pt_' . str_repeat('A', 43), 'ping')['status'], 'unknown');
    }

    public function test_console_tester_dry_run_and_smoke() {
        $cat = $this->C->catalogue(); $this->assertTrue(count($cat) >= 27);
        foreach ($cat as $t) { $this->assertTrue(isset($t['inputSchema'], $t['annotations'], $t['group'])); }
        $r = $this->C->rpc(array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array()), 'read', true);
        $this->assertEquals('2025-06-18', $r['response']['result']['protocolVersion']);
        $r = $this->C->rpc(array('jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'), 'read', true);
        $this->assertEquals(array('altus.read'), $r['effective_scopes']);
        $slug = 'mcp-console-' . bin2hex(random_bytes(3));
        $before = (int) $this->db->count_all('ha_article');
        $r = $this->C->rpc(array('jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => array('name' => 'altus_create', 'arguments' => array('type' => 'articles', 'data' => array('title_en' => 'Dry', 'title_ar' => 'تجربة', 'slug_en' => $slug, 'slug_ar' => $slug . '-ar')))), 'write', true);
        $this->assertFalse($r['response']['result']['isError'], json_encode($r['response']));
        $this->assertTrue($r['response']['result']['structuredContent']->dry_run);
        $this->assertEquals($before, (int) $this->db->count_all('ha_article'), 'dry-run rolled back');
        $r = $this->C->rpc(array('jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => array('name' => 'altus_upload_media', 'arguments' => array('name' => 'a.png', 'base64' => 'AAAA'))), 'write', true);
        $this->assertEquals('dry_run_unsupported', $r['response']['result']['structuredContent']['error']['code']);
        $bad = $this->C->rpc(array('method' => 'ping'), 'read', true); $this->assertEquals(-32600, $bad['response']['error']['code']);
        $s = $this->C->smoke();
        $this->assertTrue($s['ok'], json_encode($s['rows'])); $this->assertEquals(12, $s['total']);
        $by = array(); foreach ($s['rows'] as $row) $by[$row['level'] . ':' . $row['tool']] = $row['outcome'];
        $this->assertEquals('blocked', $by['read:altus_create']); $this->assertEquals('ok', $by['write:altus_create']);
        $this->assertEquals('blocked', $by['write:altus_request_publish']); $this->assertNotEquals('blocked', $by['full:altus_request_publish']);
        $this->assertDatabaseHas('ha_mcp_call_log', array('source' => 'console', 'dry_run' => 1));
        $o = $this->C->overview(); $this->assertTrue($o['calls'] > 0); $this->assertEquals(24, count($o['spark']));
        $a = $this->C->activity(array('source' => 'console', 'status' => 'denied'), 1);
        $this->assertTrue($a['total'] > 0); $this->assertNotNull($this->C->call_detail($a['rows'][0]['id']));
        $h = $this->C->health(false); $this->assertTrue($h['ok'], json_encode($h['checks'])); $this->assertEquals(3, count($h['checks']));
    }

    public function test_console_is_gated_for_non_platform_users() {
        foreach (array($this->tenant, $this->learner) as $uid) {
            $this->CI->ha_auth->assume($uid);
            $this->assertFalse($this->C->can_manage());
            $this->assertThrows(function () { $this->C->rpc(array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'), 'read', true); }, 'library re-checks');
            $this->assertThrows(function () { $this->C->smoke(); });
        }
        $this->CI->ha_auth->assume($this->admin); $this->assertTrue($this->C->can_manage());
        $src = file_get_contents(APPPATH . 'controllers/Hkp_mcp.php');
        foreach (array('rpc', 'smoke', 'action') as $m) $this->assertMatches('/function ' . $m . '\(\) \{\s*\$this->gate\(\); \$this->post_guard\(\);/', $src, $m . ' needs gate + CSRF');
        $this->assertMatches("/need\\('settings\\.update'\\)/", $src);
    }

    public function test_sidebar_shows_mcp_only_to_managers() {
        $probe = new Mcp_console_nav_probe(); $probe->ha_auth = $this->CI->ha_auth;
        $keys = array_column($probe->items()['platform']['items'], 'key');
        $this->assertTrue(in_array('mcp_console', $keys, true), 'platform group entry');
        $this->assertContains("'mcp_console'", file_get_contents(APPPATH . 'views/hkp/layout.php'), 'pinned in Create & manage');
        foreach (array($this->tenant, $this->learner) as $uid) {
            $this->CI->ha_auth->assume($uid);
            $all = array(); foreach ($probe->items() as $g) $all = array_merge($all, array_column($g['items'], 'key'));
            $this->assertFalse(in_array('mcp_console', $all, true), 'hidden for user ' . $uid);
        }
    }
}
