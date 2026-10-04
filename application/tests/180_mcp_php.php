<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Native PHP MCP server + OAuth 2.1 authorization server, driven through the
 * real Ha_mcp_server::dispatch() HTTP boundary (status, headers, JSON bodies).
 * Consent is the Hkp_cms::mcp_authorize() decision, called through Ha_mcp_oauth::consent().
 */
class Test_mcp_php extends Ha_testcase {
    private $saved; private $admin; private $reviewer; private $tenant; private $S; private $O;

    public function setUp() {
        $this->CI->load->library(array('ha_mcp_server', 'ha_mcp_oauth', 'ha_publishing_service', 'ha_page_builder', 'ha_audit')); $this->CI->load->helper(array('hkp', 'ha_security'));
        $this->S = $this->CI->ha_mcp_server; $this->O = $this->CI->ha_mcp_oauth;
        $this->saved = $this->CI->config->config['ha_publisher'];
        $this->CI->config->config['ha_publisher'] = array_merge($this->saved, array('mcp_enabled' => true, 'mcp_issuer' => 'http://altus.test', 'allow_self_approval' => false, 'approval_ttl' => 600, 'mcp_rate_limit' => 1000, 'mcp_ip_rate_limit' => 100000));
        $this->db->truncate('ha_mcp_rate');
        $this->admin = $this->user('admin@hospitalityacademy.sa'); $this->reviewer = $this->user('academy.admin@hospitalityacademy.sa'); $this->tenant = $this->user('org.admin@dyafagroup.sa');
        $this->CI->ha_auth->assume($this->admin);
    }
    public function tearDown() { $this->CI->config->config['ha_publisher'] = $this->saved; $this->CI->ha_auth->assume($this->admin); }

    // ------------------------------------------------------------------ helpers
    private function user($email) { return (int) $this->db->get_where('users', array('email' => $email))->row('id'); }
    private function http($method, $path, $body = '', array $headers = array(), array $query = array()) {
        $r = $this->S->dispatch(array('method' => $method, 'path' => $path, 'headers' => $headers, 'query' => $query, 'body' => is_array($body) ? json_encode($body) : $body, 'ip' => '127.0.0.9'));
        $this->CI->ha_auth->assume($this->admin);
        $r['json'] = json_decode($r['body'], true); $r['headers'] = array_change_key_case($r['headers'], CASE_LOWER); return $r;
    }
    private static function b64($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    private function register($redirect = 'http://127.0.0.1:33418/callback') { $r = $this->http('POST', 'oauth/register', array('client_name' => 'Test client', 'redirect_uris' => array($redirect)), array('content-type' => 'application/json')); $this->assertEquals(201, $r['status'], $r['body']); return $r['json']; }
    private function code_flow($uid, $scope = 'altus.read altus.content.write altus.course.write altus.media.write altus.publish', array $extra = array()) {
        $c = $this->register(); $verifier = self::b64(random_bytes(40)); $state = bin2hex(random_bytes(6));
        $q = array('response_type' => 'code', 'client_id' => $c['client_id'], 'redirect_uri' => $c['redirect_uris'][0], 'scope' => $scope, 'state' => $state, 'code_challenge' => self::b64(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256', 'resource' => 'http://altus.test/mcp') + $extra;
        $r = $this->http('GET', 'oauth/authorize', '', array(), $q); $this->assertEquals(302, $r['status'], $r['body']);
        preg_match('/request=([a-f0-9]{48})/', $r['headers']['location'], $m); $this->assertNotEmpty($m, 'redirects to ALTUS consent');
        $this->CI->ha_auth->assume($uid); $A = $this->CI->ha_auth; $perms = $A->permissions(); if ($A->is_super_admin()) $perms = array_merge($perms, array('cms_pages.update', 'courses.update', 'media.create', 'cms_pages.publish', 'system.configure', 'knowledge.create'));
        $url = $this->O->consent($m[1], $uid, true, $perms, $A->is_system_scoped()); $this->CI->ha_auth->assume($this->admin);
        parse_str(parse_url($url, PHP_URL_QUERY), $p); $this->assertEquals($state, $p['state'] ?? null); $this->assertEquals('http://altus.test', $p['iss'] ?? null);
        return array('client' => $c, 'verifier' => $verifier, 'code' => $p['code'] ?? null, 'redirect' => $c['redirect_uris'][0], 'params' => $p);
    }
    private function token(array $form) { return $this->http('POST', 'oauth/token', http_build_query($form), array('content-type' => 'application/x-www-form-urlencoded')); }
    private function login($uid, $scope = 'altus.read altus.content.write altus.course.write altus.media.write altus.publish') {
        $f = $this->code_flow($uid, $scope);
        $t = $this->token(array('grant_type' => 'authorization_code', 'code' => $f['code'], 'redirect_uri' => $f['redirect'], 'client_id' => $f['client']['client_id'], 'code_verifier' => $f['verifier'], 'resource' => 'http://altus.test/mcp'));
        $this->assertEquals(200, $t['status'], $t['body']); return $t['json'] + array('client_id' => $f['client']['client_id']);
    }
    private function rpc($token, $payload, array $headers = array()) { return $this->http('POST', 'mcp', $payload, $headers + array('authorization' => 'Bearer ' . $token, 'content-type' => 'application/json', 'mcp-protocol-version' => '2025-06-18')); }
    private function tool($token, $name, array $args = array()) {
        $r = $this->rpc($token, array('jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => array('name' => $name, 'arguments' => (object) $args)));
        $this->assertEquals(200, $r['status'], $r['body']); $res = $r['json']['result'] ?? null; $this->assertNotNull($res, 'tool result: ' . $r['body']);
        return array('error' => !empty($res['isError']), 'data' => $res['structuredContent'] ?? array(), 'code' => $res['structuredContent']['error']['code'] ?? null, 'text' => $res['content'][0]['text'] ?? '');
    }
    private function ok($token, $name, array $args = array()) { $r = $this->tool($token, $name, $args); $this->assertFalse($r['error'], $name . ': ' . $r['text']); return $r['data']; }
    private function about() { return (int) $this->db->get_where('ha_page', array('code' => 'about'))->row('id'); }
    private function approve($id, $as = null) { $this->CI->ha_auth->assume($as ?: $this->reviewer); try { $this->CI->ha_publishing_service->review($id, true); } finally { $this->CI->ha_auth->assume($this->admin); } }
    private static function key($p) { return $p . '-' . bin2hex(random_bytes(5)); }

    // ------------------------------------------------------------------ OAuth
    public function test_metadata_and_401_challenge() {
        $as = $this->http('GET', '.well-known/oauth-authorization-server'); $this->assertEquals(200, $as['status']);
        $this->assertEquals('http://altus.test', $as['json']['issuer']); $this->assertEquals(array('S256'), $as['json']['code_challenge_methods_supported']); $this->assertEquals('http://altus.test/oauth/register', $as['json']['registration_endpoint']);
        $this->assertEquals(200, $this->http('GET', '.well-known/openid-configuration')['status']);
        foreach (array('.well-known/oauth-protected-resource', '.well-known/oauth-protected-resource/mcp') as $p) { $pr = $this->http('GET', $p); $this->assertEquals('http://altus.test/mcp', $pr['json']['resource']); $this->assertEquals(array('http://altus.test'), $pr['json']['authorization_servers']); }
        $r = $this->http('POST', 'mcp', array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array()));
        $this->assertEquals(401, $r['status']); $this->assertContains('resource_metadata="http://altus.test/.well-known/oauth-protected-resource/mcp"', $r['headers']['www-authenticate']);
        $this->assertEquals(401, $this->http('POST', 'mcp', '{}', array(), array('access_token' => 'x'))['status'], 'query tokens refused');
        $bad = $this->rpc('altus_at_' . str_repeat('A', 43), array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping')); $this->assertEquals(401, $bad['status']); $this->assertContains('error="invalid_token"', $bad['headers']['www-authenticate']);
    }
    public function test_registration_validation() {
        $bad = function ($meta) { return $this->http('POST', 'oauth/register', $meta, array('content-type' => 'application/json')); };
        $this->assertEquals('invalid_redirect_uri', $bad(array('redirect_uris' => array('http://evil.example/cb')))['json']['error'], 'plain http only for loopback');
        $this->assertEquals('invalid_redirect_uri', $bad(array('redirect_uris' => array('https://ok.example/cb#frag')))['json']['error']);
        $this->assertEquals('invalid_redirect_uri', $bad(array('redirect_uris' => array('javascript:alert(1)')))['json']['error']);
        $this->assertEquals('invalid_client_metadata', $bad(array('redirect_uris' => array('https://ok.example/cb'), 'token_endpoint_auth_method' => 'client_secret_basic'))['json']['error']);
        $ok = $bad(array('redirect_uris' => array('https://claude.ai/api/mcp/auth_callback', 'cursor://anysphere.cursor-retrieval/oauth/callback'), 'client_name' => 'Claude'));
        $this->assertEquals(201, $ok['status']); $this->assertEquals('none', $ok['json']['token_endpoint_auth_method']); $this->assertMatches('/^mcp_[a-f0-9]{32}$/', $ok['json']['client_id']);
    }
    public function test_full_flow_pkce_refresh_rotation_reuse_and_revoke() {
        // Authorization request validation.
        $c = $this->register();
        $base = array('response_type' => 'code', 'client_id' => $c['client_id'], 'redirect_uri' => $c['redirect_uris'][0], 'state' => 's1', 'code_challenge' => self::b64(hash('sha256', str_repeat('v', 50), true)), 'code_challenge_method' => 'S256');
        $r = $this->http('GET', 'oauth/authorize', '', array(), array('redirect_uri' => 'http://127.0.0.1:33418/other') + $base); $this->assertEquals(400, $r['status'], 'unregistered redirect is never followed');
        $r = $this->http('GET', 'oauth/authorize', '', array(), array('code_challenge_method' => 'plain') + $base); $this->assertEquals(302, $r['status']); $this->assertContains('error=invalid_request', $r['headers']['location']); $this->assertContains('state=s1', $r['headers']['location']);
        $r = $this->http('GET', 'oauth/authorize', '', array(), array('scope' => 'altus.root') + $base); $this->assertContains('error=invalid_scope', $r['headers']['location']);
        // Wrong verifier burns the code.
        $f = $this->code_flow($this->admin);
        $form = array('grant_type' => 'authorization_code', 'code' => $f['code'], 'redirect_uri' => $f['redirect'], 'client_id' => $f['client']['client_id']);
        $w = $this->token($form + array('code_verifier' => self::b64(random_bytes(40)))); $this->assertEquals(400, $w['status']); $this->assertEquals('invalid_grant', $w['json']['error']);
        $this->assertEquals('invalid_grant', $this->token($form + array('code_verifier' => $f['verifier']))['json']['error'], 'a code is single-use even after a failed attempt');
        // Correct exchange.
        $f = $this->code_flow($this->admin);
        $form = array('grant_type' => 'authorization_code', 'code' => $f['code'], 'redirect_uri' => $f['redirect'], 'client_id' => $f['client']['client_id'], 'code_verifier' => $f['verifier']);
        $this->assertEquals('invalid_grant', $this->token(array('redirect_uri' => 'http://127.0.0.1:1/x') + $form)['json']['error'], 'redirect_uri must match');
        $f = $this->code_flow($this->admin);
        $form = array('grant_type' => 'authorization_code', 'code' => $f['code'], 'redirect_uri' => $f['redirect'], 'client_id' => $f['client']['client_id'], 'code_verifier' => $f['verifier']);
        $t = $this->token($form); $this->assertEquals(200, $t['status'], $t['body']); $this->assertEquals('Bearer', $t['json']['token_type']); $this->assertEquals(300, $t['json']['expires_in']); $this->assertEquals('no-store', $t['headers']['cache-control']);
        $this->assertDatabaseMissing('ha_mcp_token', array('token_hash' => $t['json']['access_token']), 'tokens are stored hashed');
        $this->assertDatabaseHas('ha_mcp_token', array('token_hash' => hash('sha256', $t['json']['access_token']), 'kind' => 'access'));
        $this->assertEquals('invalid_grant', $this->token($form)['json']['error'], 'code replay rejected');
        $this->assertEquals(401, $this->rpc($t['json']['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'))['status'], 'code replay revoked the grant');
        // Fresh grant: refresh rotation and reuse detection.
        $t = $this->login($this->admin); $cid = $t['client_id'];
        $this->assertEquals(200, $this->rpc($t['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'))['status']);
        $r1 = $this->token(array('grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $cid)); $this->assertEquals(200, $r1['status'], $r1['body']);
        $this->assertNotEquals($t['refresh_token'], $r1['json']['refresh_token'], 'refresh token rotates');
        $this->assertEquals('invalid_scope', $this->token(array('grant_type' => 'refresh_token', 'refresh_token' => $r1['json']['refresh_token'], 'client_id' => $cid, 'scope' => 'altus.admin'))['json']['error'], 'refresh cannot add scopes');
        $reuse = $this->token(array('grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $cid));
        $this->assertEquals('invalid_grant', $reuse['json']['error']);
        $this->assertEquals(401, $this->rpc($r1['json']['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'))['status'], 'reuse revokes every token of the grant');
        $this->assertEquals('invalid_grant', $this->token(array('grant_type' => 'refresh_token', 'refresh_token' => $r1['json']['refresh_token'], 'client_id' => $cid))['json']['error']);
        // RFC 7009 revocation.
        $t = $this->login($this->admin);
        $this->assertEquals(200, $this->http('POST', 'oauth/revoke', http_build_query(array('token' => 'unknown-token')), array('content-type' => 'application/x-www-form-urlencoded'))['status'], 'unknown tokens revoke quietly');
        $this->assertEquals(200, $this->http('POST', 'oauth/revoke', http_build_query(array('token' => $t['refresh_token'], 'client_id' => $t['client_id'])), array('content-type' => 'application/x-www-form-urlencoded'))['status']);
        $this->assertEquals(401, $this->rpc($t['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'))['status'], 'revoking the refresh token revokes the grant');
        // Expired access token.
        $t = $this->login($this->admin); $this->db->where('token_hash', hash('sha256', $t['access_token']))->update('ha_mcp_token', array('expires_at' => gmdate('Y-m-d H:i:s', time() - 1)));
        $this->assertEquals(401, $this->rpc($t['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'))['status']);
        // Declined consent.
        $c = $this->register(); $r = $this->http('GET', 'oauth/authorize', '', array(), array('client_id' => $c['client_id'], 'redirect_uri' => $c['redirect_uris'][0]) + $base); preg_match('/request=([a-f0-9]{48})/', $r['headers']['location'], $m);
        $this->assertContains('error=access_denied', $this->O->consent($m[1], $this->admin, false, array(), true));
        $this->assertThrows(function () use ($m) { $this->O->consent($m[1], $this->admin, true, array(), true); }, 'consent requests are single-use');
    }
    public function test_audience_resource_indicator() {
        $c = $this->register(); $v = str_repeat('a', 43);
        $r = $this->http('GET', 'oauth/authorize', '', array(), array('response_type' => 'code', 'client_id' => $c['client_id'], 'redirect_uri' => $c['redirect_uris'][0], 'code_challenge' => self::b64(hash('sha256', $v, true)), 'code_challenge_method' => 'S256', 'resource' => 'https://evil.example/mcp'));
        $this->assertContains('error=invalid_target', $r['headers']['location']);
        $f = $this->code_flow($this->admin);
        $t = $this->token(array('grant_type' => 'authorization_code', 'code' => $f['code'], 'redirect_uri' => $f['redirect'], 'client_id' => $f['client']['client_id'], 'code_verifier' => $f['verifier'], 'resource' => 'https://evil.example/mcp'));
        $this->assertEquals('invalid_target', $t['json']['error']);
        $t = $this->login($this->admin); $this->db->where('token_hash', hash('sha256', $t['access_token']))->update('ha_mcp_token', array('resource' => 'https://other.example/mcp'));
        $r = $this->rpc($t['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping')); $this->assertEquals(401, $r['status'], 'token for another audience is rejected'); $this->assertContains('audience', $r['json']['error']['message']);
    }
    public function test_scope_capping_and_restriction() {
        $f = $this->code_flow($this->tenant, 'altus.read altus.admin altus.publish');
        $g = $this->db->get_where('ha_mcp_grant', array('client_id' => $f['client']['client_id']))->row_array();
        $this->assertNotContains('altus.admin', explode(' ', $g['scope']), 'scopes are capped by the user\'s permissions');
        $t = $this->login($this->admin, 'altus.read');
        $list = $this->rpc($t['access_token'], array('jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'));
        $names = array_column($list['json']['result']['tools'], 'name'); $this->assertContains('altus_get', $names); $this->assertNotContains('altus_update', $names, 'write tools hidden without write scopes');
        $r = $this->tool($t['access_token'], 'altus_create', array('type' => 'articles', 'data' => array('title_en' => 'x')));
        $this->assertTrue($r['error']); $this->assertEquals('insufficient_scope', $r['code']); $this->assertEquals('altus.content.write', $r['data']['error']['details']['required_scope']);
    }
    public function test_revoked_user_and_tenant_isolation() {
        $t = $this->login($this->reviewer); $this->assertEquals(200, $this->rpc($t['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'))['status']);
        $this->db->where('id', $this->reviewer)->update('users', array('status' => 0));
        try {
            $r = $this->rpc($t['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping')); $this->assertEquals(401, $r['status'], 'inactive user rejected on every call');
            $this->assertEquals('invalid_grant', $this->token(array('grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $t['client_id']))['json']['error']);
        } finally { $this->db->where('id', $this->reviewer)->update('users', array('status' => 1)); }
        $tt = $this->login($this->tenant, 'altus.read altus.content.write altus.course.write');
        $r = $this->tool($tt['access_token'], 'altus_get', array('type' => 'page', 'id' => $this->about())); $this->assertTrue($r['error'], 'tenant cannot read platform pages'); $this->assertEquals('forbidden', $r['code']);
        $other = (int) $this->db->select('id')->where('organization_id IS NOT NULL', null, false)->where('organization_id !=', (int) $this->db->select('organization_id')->get_where('ha_user_role', array('user_id' => $this->tenant))->row('organization_id'))->get('ha_course')->row('id');
        if ($other) { $r = $this->tool($tt['access_token'], 'altus_list', array('type' => 'lessons', 'parent_id' => $other)); $this->assertTrue($r['error'], 'other organisation course hidden'); }
        $r = $this->tool($tt['access_token'], 'altus_create', array('type' => 'articles', 'data' => array('title_en' => 'T', 'organization_id' => 5))); $this->assertEquals('validation_failed', $r['code'], 'tenant identity cannot be chosen');
    }

    // ------------------------------------------------------------------ protocol
    public function test_protocol_session_batch_and_errors() {
        $t = $this->login($this->admin)['access_token'];
        $i = $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array('protocolVersion' => '2025-03-26', 'capabilities' => new stdClass(), 'clientInfo' => array('name' => 'test', 'version' => '1'))));
        $this->assertEquals(200, $i['status']); $this->assertEquals('2025-03-26', $i['json']['result']['protocolVersion'], 'older supported version accepted');
        $this->assertTrue(isset($i['json']['result']['capabilities']['tools'])); $sid = $i['headers']['mcp-session-id']; $this->assertMatches('/^[a-f0-9]{48}$/', $sid);
        $n = $this->rpc($t, array('jsonrpc' => '2.0', 'method' => 'notifications/initialized'), array('mcp-session-id' => $sid)); $this->assertEquals(202, $n['status']); $this->assertEquals('', $n['body']);
        $b = $this->rpc($t, array(array('jsonrpc' => '2.0', 'id' => 'a', 'method' => 'ping'), array('jsonrpc' => '2.0', 'id' => 'b', 'method' => 'tools/list'), array('jsonrpc' => '2.0', 'method' => 'notifications/cancelled')), array('mcp-session-id' => $sid));
        $this->assertCount(2, $b['json'], 'batch answers requests only'); $this->assertEquals('a', $b['json'][0]['id']); $this->assertGreaterThan(20, count($b['json'][1]['result']['tools']));
        foreach ($b['json'][1]['result']['tools'] as $tool) { $this->assertEquals('object', $tool['inputSchema']['type']); $this->assertTrue(isset($tool['annotations']['readOnlyHint'], $tool['annotations']['destructiveHint'])); $this->assertFalse(stripos($tool['name'], 'delete') !== false || stripos($tool['name'], 'sql') !== false, 'no delete/sql tools'); }
        $this->assertEquals(-32601, $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 3, 'method' => 'nope'))['json']['error']['code']);
        $this->assertEquals(-32602, $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => array('name' => 'altus_drop_everything')))['json']['error']['code']);
        $p = $this->rpc($t, '{not json'); $this->assertEquals(400, $p['status']); $this->assertEquals(-32700, $p['json']['error']['code']);
        $this->assertEquals(-32600, $this->rpc($t, array('id' => 1, 'method' => 'ping'))['json']['error']['code']);
        $this->assertEquals(400, $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'), array('mcp-protocol-version' => '1999-01-01'))['status']);
        $this->assertEquals(404, $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'), array('mcp-session-id' => str_repeat('0', 48)))['status']);
        $this->assertEquals(403, $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'), array('origin' => 'https://evil.example'))['status'], 'Origin validated');
        $this->assertEquals(200, $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'), array('origin' => 'http://altus.test'))['status']);
        $this->assertEquals(405, $this->http('GET', 'mcp', '', array('authorization' => 'Bearer ' . $t))['status']);
        $this->assertEquals(204, $this->http('DELETE', 'mcp', '', array('authorization' => 'Bearer ' . $t, 'mcp-session-id' => $sid))['status']);
        $this->assertEquals(404, $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'), array('mcp-session-id' => $sid))['status'], 'terminated session');
        $res = $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 9, 'method' => 'resources/read', 'params' => array('uri' => 'altus://docs/rules'))); $this->assertContains('approves', $res['json']['result']['contents'][0]['text']);
        $bad = $this->tool($t, 'altus_get', array('type' => 'planets', 'id' => 'x')); $this->assertTrue($bad['error']); $this->assertEquals('validation_failed', $bad['code']);
        $info = $this->ok($t, 'altus_site_info'); $this->assertEquals('http://altus.test/mcp', $info['server']['endpoint']); $this->assertFalse($info['server']['node_required']);
    }
    public function test_mcp_disabled_and_rate_limit() {
        $t = $this->login($this->admin)['access_token'];
        $this->CI->config->config['ha_publisher']['mcp_enabled'] = false;
        $r = $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping')); $this->assertEquals(503, $r['status']); $this->assertEquals('mcp_disabled', $r['json']['error']['data']['code']);
        $this->assertEquals(503, $this->token(array('grant_type' => 'refresh_token'))['status']); $this->assertEquals(503, $this->http('GET', '.well-known/oauth-authorization-server')['status']);
        $this->CI->config->config['ha_publisher']['mcp_enabled'] = true; $this->CI->config->config['ha_publisher']['mcp_rate_limit'] = 3; $this->db->truncate('ha_mcp_rate');
        $codes = array(); for ($i = 0; $i < 4; $i++) $codes[] = $this->rpc($t, array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'))['status'];
        $this->assertEquals(array(200, 200, 200, 429), $codes, 'per client+user rate limit');
    }

    // ------------------------------------------------------------------ CRUD
    public function test_catalogue_crud_conflict_and_idempotency() {
        $t = $this->login($this->admin)['access_token'];
        foreach (array('articles', 'topics', 'programs', 'paths', 'courses') as $type) {
            $slug = 'mcp-php-' . $type . '-' . bin2hex(random_bytes(4)); $key = self::key('c');
            $args = array('type' => $type, 'data' => array('title_en' => 'MCP ' . $type, 'title_ar' => 'MCP AR ' . $type, 'slug_en' => $slug, 'slug_ar' => $slug . '-ar', 'body_en' => '<p>Body</p>', 'body_ar' => '<p>AR</p>', 'status' => 'published'), 'idempotency_key' => $key);
            $c = $this->ok($t, 'altus_create', $args); $id = $c['object_id'];
            $this->assertGreaterThan(0, $id, $type); $this->assertNotEmpty($c['audit_id'], 'writes return audit id'); $this->assertNotEmpty($c['edit_url']);
            $again = $this->ok($t, 'altus_create', $args); $this->assertEquals($id, $again['object_id']); $this->assertTrue($again['idempotent_replayed'], 'replay, not a second record');
            $args['data']['title_en'] = 'Changed'; $this->assertEquals('idempotency_conflict', $this->tool($t, 'altus_create', $args)['code']);
            $g = $this->ok($t, 'altus_get', array('type' => $type, 'id' => $id)); $this->assertNotEquals('published', $g['state']['published']['status'], $type . ' never created as published');
            $u = $this->ok($t, 'altus_update', array('type' => $type, 'id' => $id, 'data' => array('title_en' => 'MCP updated ' . $type), 'expected_version' => $g['version']));
            $this->assertEquals($g['version'] + 1, $u['version']); $this->assertEquals(array('title_en'), $u['changed_fields']);
            $stale = $this->tool($t, 'altus_update', array('type' => $type, 'id' => $id, 'data' => array('title_en' => 'Stale'), 'expected_version' => $g['version']));
            $this->assertEquals('conflict', $stale['code']); $this->assertEquals($g['version'] + 1, $stale['data']['error']['details']['current_version']); $this->assertEquals(409, $stale['data']['error']['status']);
            $this->assertEquals('MCP updated ' . $type, $this->ok($t, 'altus_get', array('type' => $type, 'id' => $id))['state']['payload']['title_en']);
            $this->assertTrue(is_array($this->ok($t, 'altus_list_revisions', array('type' => $type, 'id' => $id))['revisions']));
        }
        $s = $this->ok($t, 'altus_search', array('query' => 'MCP')); $this->assertGreaterThan(0, count($s['results']));
        $this->assertDatabaseHas('ha_audit_log', array('action' => 'mcp.altus_get'), 'reads are audited too');
    }
    public function test_page_crud_and_sections() {
        $t = $this->login($this->admin)['access_token'];
        $c = $this->ok($t, 'altus_create', array('type' => 'page', 'data' => array('title_en' => 'MCP page ' . bin2hex(random_bytes(3)))));
        $id = $c['object_id']; $this->assertEquals('draft', $this->db->get_where('ha_page', array('id' => $id))->row('status'));
        $g = $this->ok($t, 'altus_get', array('type' => 'page', 'id' => $id)); $this->assertEquals(0, $g['version']);
        $u = $this->ok($t, 'altus_update', array('type' => 'page', 'id' => $id, 'data' => array('tr' => array('en' => array('subtitle' => 'From MCP'))), 'expected_version' => 0));
        $this->assertEquals(1, $u['version']); $this->assertEquals(array('tr.en.subtitle'), $u['changed_fields']);
        $ins = $this->ok($t, 'altus_page_section', array('page_id' => $id, 'operation' => 'insert', 'section' => array('section_type' => 'rich_text', 'en' => array('heading' => 'Intro', 'body' => '<p>Hello</p>'), 'ar' => array('heading' => 'AR', 'body' => '<p>AR</p>')), 'expected_version' => 1));
        $k = $ins['section_key']; $this->assertEquals(2, $ins['version']);
        $dup = $this->ok($t, 'altus_page_section', array('page_id' => $id, 'operation' => 'duplicate', 'key' => $k, 'expected_version' => 2));
        $this->ok($t, 'altus_page_section', array('page_id' => $id, 'operation' => 'hide', 'key' => $dup['section_key'], 'expected_version' => 3));
        $this->ok($t, 'altus_page_section', array('page_id' => $id, 'operation' => 'update', 'key' => $k, 'section' => array('en' => array('heading' => 'Intro edited')), 'expected_version' => 4));
        $this->ok($t, 'altus_page_section', array('page_id' => $id, 'operation' => 'reorder', 'order' => array($dup['section_key'], $k), 'expected_version' => 5));
        $l = $this->ok($t, 'altus_list', array('type' => 'page_sections', 'parent_id' => $id));
        $this->assertEquals(array($dup['section_key'], $k), array_column($l['sections'], 'key')); $this->assertFalse($l['sections'][0]['is_visible']);
        $one = $this->ok($t, 'altus_get', array('type' => 'page_sections', 'id' => $id, 'key' => $k)); $this->assertEquals('Intro edited', $one['sections'][0]['en']['heading']);
        $this->assertEquals('conflict', $this->tool($t, 'altus_page_section', array('page_id' => $id, 'operation' => 'show', 'key' => $k, 'expected_version' => 1))['code']);
        $this->assertEquals(0, $this->db->where('page_id', $id)->count_all_results('ha_page_section'), 'live page untouched: sections exist only in the draft');
        $p = $this->ok($t, 'altus_preview_link', array('type' => 'page', 'id' => $id)); $this->assertNotEmpty($p['preview_url']);
    }
    public function test_structure_crud_locks_and_reorder() {
        $t = $this->login($this->admin)['access_token']; $slug = 'mcp-course-' . bin2hex(random_bytes(4));
        $course = $this->ok($t, 'altus_create', array('type' => 'courses', 'data' => array('title_en' => 'MCP course', 'title_ar' => 'MCP AR', 'slug_en' => $slug, 'slug_ar' => $slug . '-ar')))['object_id'];
        $s1 = $this->ok($t, 'altus_create', array('type' => 'course_sections', 'parent_id' => $course, 'data' => array('title_en' => 'Part 1')))['object_id'];
        $s2 = $this->ok($t, 'altus_create', array('type' => 'course_sections', 'parent_id' => $course, 'data' => array('title_en' => 'Part 2')))['object_id'];
        $this->ok($t, 'altus_reorder', array('type' => 'course_sections', 'parent_id' => $course, 'ids' => array($s2, $s1)));
        $this->assertEquals(0, (int) $this->db->get_where('ha_course_section', array('id' => $s2))->row('sort_order'));
        $l = $this->ok($t, 'altus_create', array('type' => 'lessons', 'parent_id' => $course, 'data' => array('title_en' => 'Lesson A', 'title_ar' => 'AR', 'body_en' => '<p>Text<script>x</script></p>', 'section_id' => $s1, 'status' => 'published')));
        $lid = $l['object_id']; $this->assertEquals('draft', $l['status'], 'lessons are created as drafts'); $this->assertNotContains('<script', (string) $this->db->get_where('ha_lesson_translation', array('lesson_id' => $lid, 'locale' => 'en'))->row('body'));
        $g = $this->ok($t, 'altus_get', array('type' => 'lessons', 'id' => $lid));
        $u = $this->ok($t, 'altus_update', array('type' => 'lessons', 'id' => $lid, 'data' => array('title_en' => 'Lesson A1', 'duration_minutes' => 12), 'expected_version' => $g['version']));
        $this->assertNotEquals($g['version'], $u['version']);
        $this->assertEquals('conflict', $this->tool($t, 'altus_update', array('type' => 'lessons', 'id' => $lid, 'data' => array('title_en' => 'x'), 'expected_version' => $g['version']))['code']);
        $this->assertEquals('validation_failed', $this->tool($t, 'altus_update', array('type' => 'lessons', 'id' => $lid, 'data' => array('section_id' => 999999), 'expected_version' => $u['version']))['code']);
        $rev = $this->ok($t, 'altus_list_revisions', array('type' => 'lessons', 'id' => $lid)); $this->assertNotEmpty($rev['revisions']);
        $back = $this->ok($t, 'altus_restore_revision', array('type' => 'lessons', 'id' => $lid, 'revision_id' => (int) $rev['revisions'][0]['id'], 'expected_version' => $u['version']));
        $this->assertEquals('Lesson A', $this->ok($t, 'altus_get', array('type' => 'lessons', 'id' => $lid))['data']['title_en'], 'revision restored');
        $q = $this->ok($t, 'altus_create', array('type' => 'quizzes', 'parent_id' => $course, 'data' => array('title_en' => 'Check', 'pass_percentage' => 80)))['object_id'];
        $qa = $this->ok($t, 'altus_create', array('type' => 'questions', 'parent_id' => $q, 'data' => array('body_en' => 'Q1?', 'options' => array(array('body_en' => 'Yes', 'is_correct' => true), array('body_en' => 'No')))))['object_id'];
        $qb = $this->ok($t, 'altus_create', array('type' => 'questions', 'parent_id' => $q, 'data' => array('question_type' => 'true_false', 'body_en' => 'Q2?', 'options' => array(array('body_en' => 'True', 'is_correct' => true), array('body_en' => 'False')))))['object_id'];
        $this->assertEquals('validation_failed', $this->tool($t, 'altus_create', array('type' => 'questions', 'parent_id' => $q, 'data' => array('body_en' => 'Bad', 'options' => array(array('body_en' => 'Only')))))['code']);
        $this->ok($t, 'altus_reorder', array('type' => 'questions', 'parent_id' => $q, 'ids' => array($qb, $qa)));
        $qg = $this->ok($t, 'altus_get', array('type' => 'quizzes', 'id' => $q)); $this->assertEquals(array($qb, $qa), $qg['data']['question_ids']);
        $qq = $this->ok($t, 'altus_get', array('type' => 'questions', 'id' => $qa));
        $this->ok($t, 'altus_update', array('type' => 'questions', 'id' => $qa, 'data' => array('options' => array(array('body_en' => 'A'), array('body_en' => 'B', 'is_correct' => true))), 'expected_version' => $qq['version']));
        $this->assertEquals('B', $this->ok($t, 'altus_get', array('type' => 'questions', 'id' => $qa))['data']['options'][1]['body_en']);
        $this->ok($t, 'altus_update', array('type' => 'quizzes', 'id' => $q, 'data' => array('pass_percentage' => 70), 'expected_version' => $qg['version']));
        $this->assertCount(2, $this->ok($t, 'altus_list', array('type' => 'questions', 'parent_id' => $q))['rows']);
        // Published objects without a draft layer are locked.
        $this->db->where('id', $lid)->update('ha_lesson', array('status' => 'published')); $v = $this->ok($t, 'altus_get', array('type' => 'lessons', 'id' => $lid))['version'];
        $this->assertEquals('published_locked', $this->tool($t, 'altus_update', array('type' => 'lessons', 'id' => $lid, 'data' => array('title_en' => 'Live edit'), 'expected_version' => $v))['code']);
        $this->db->where('id', $course)->update('ha_course', array('status' => 'published'));
        $this->assertEquals('published_locked', $this->tool($t, 'altus_create', array('type' => 'course_sections', 'parent_id' => $course, 'data' => array('title_en' => 'Late')))['code']);
        $this->db->where('id', $lid)->update('ha_lesson', array('status' => 'draft'));
    }
    public function test_sop_site_navigation_media_and_audit() {
        $t = $this->login($this->admin)['access_token'];
        $sop = $this->ok($t, 'altus_create', array('type' => 'sops', 'data' => array('title_en' => 'MCP SOP', 'title_ar' => 'MCP AR', 'item_type' => 'sop', 'sections' => array('en' => array('purpose' => 'Why', 'procedure' => "Step 1\nStep 2")))));
        $sid = $sop['object_id']; $g = $this->ok($t, 'altus_get', array('type' => 'sops', 'id' => $sid)); $this->assertEquals('Why', $g['data']['sections']['en']['purpose']);
        $u = $this->ok($t, 'altus_update', array('type' => 'sops', 'id' => $sid, 'data' => array('sections' => array('en' => array('scope' => 'Front office'))), 'expected_version' => $g['version']));
        $g2 = $this->ok($t, 'altus_get', array('type' => 'sops', 'id' => $sid)); $this->assertEquals('Why', $g2['data']['sections']['en']['purpose'], 'partial update keeps other sections'); $this->assertEquals('Front office', $g2['data']['sections']['en']['scope']);
        $this->assertEquals('conflict', $this->tool($t, 'altus_update', array('type' => 'sops', 'id' => $sid, 'data' => array('title_en' => 'x'), 'expected_version' => $g['version']))['code']);
        $this->ok($t, 'altus_sop_workflow', array('id' => $sid, 'action' => 'submit'));
        $this->assertEquals('sop_governance_required', $this->tool($t, 'altus_request_publish', array('type' => 'sops', 'id' => $sid, 'operation' => 'publish'))['code']);
        $site = $this->ok($t, 'altus_get', array('type' => 'site', 'id' => 1));
        $su = $this->ok($t, 'altus_update', array('type' => 'site', 'id' => 1, 'data' => array('accent' => '#123456'), 'expected_version' => $site['version'])); $this->assertEquals($site['version'] + 1, $su['version']);
        $this->assertEquals('validation_failed', $this->tool($t, 'altus_update', array('type' => 'site', 'id' => 1, 'data' => array('accent' => 'red'), 'expected_version' => $su['version']))['code']);
        $menus = $this->ok($t, 'altus_list', array('type' => 'navigation'))['items']; $menu = (int) $menus[0]['id'];
        $nav = $this->ok($t, 'altus_get', array('type' => 'navigation', 'id' => $menu));
        $this->ok($t, 'altus_update', array('type' => 'navigation', 'id' => $menu, 'data' => array('items' => $nav['state']['payload']['items']), 'expected_version' => $nav['version']));
        $png = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $m = $this->tool($t, 'altus_upload_media', array('name' => 'dot.png', 'base64' => $png, 'alt_en' => 'Dot'));
        if (!$m['error']) {
            $slug = 'mcp-media-' . bin2hex(random_bytes(4)); $a = $this->ok($t, 'altus_create', array('type' => 'articles', 'data' => array('title_en' => 'Media', 'title_ar' => 'M', 'slug_en' => $slug, 'slug_ar' => $slug . '-ar')));
            $at = $this->ok($t, 'altus_attach_media', array('type' => 'articles', 'id' => $a['object_id'], 'media_id' => $m['data']['object_id'], 'target' => 'image', 'expected_version' => $a['version']));
            $this->assertContains('image', $at['changed_fields']); $this->assertNotEmpty($at['url']);
        } else { $this->assertContains($m['code'], array('validation_failed', 'forbidden', 'internal_error'), 'media upload error is structured: ' . $m['text']); }
        $mine = $this->ok($t, 'altus_audit_list', array('action_prefix' => 'mcp.')); $this->assertEquals('your own actions', $mine['scope']); $this->assertNotEmpty($mine['rows']);
        foreach ($mine['rows'] as $row) $this->assertFalse(strpos((string) $row['description'], $t) !== false || strpos((string) $row['description'], 'iVBOR') !== false, 'no secrets or payloads in audit');
    }

    // ------------------------------------------------------------------ approvals
    public function test_approval_flow_single_use_change_expiry_and_self_approval() {
        $t = $this->login($this->admin)['access_token']; $id = $this->about();
        $edit = function ($title) use ($t, $id) { $g = $this->ok($t, 'altus_get', array('type' => 'page', 'id' => $id)); return $this->ok($t, 'altus_update', array('type' => 'page', 'id' => $id, 'data' => array('tr' => array('en' => array('title' => $title))), 'expected_version' => $g['version'])); };
        $edit('MCP PHP approved headline');
        $a = $this->ok($t, 'altus_request_publish', array('type' => 'page', 'id' => $id, 'operation' => 'publish', 'idempotency_key' => self::key('rq')));
        $this->assertEquals('pending', $a['status']); $this->assertNotEmpty($a['review_url']);
        $this->assertEquals('approval_required', $this->tool($t, 'altus_publish_approved', array('approval_id' => $a['approval_id']))['code'], 'cannot publish before review');
        $this->assertThrows(function () use ($a) { $this->approve($a['approval_id'], $this->admin); }, 'requester cannot approve own request');
        $this->approve($a['approval_id']);
        $this->assertEquals('approved', $this->ok($t, 'altus_approval_status', array('approval_id' => $a['approval_id']))['status']);
        $other = $this->login($this->admin)['access_token'];
        $this->assertEquals('not_found', $this->tool($other, 'altus_publish_approved', array('approval_id' => $a['approval_id']))['code'], 'bound to the requesting client');
        $this->assertEquals('approval_mismatch', $this->tool($t, 'altus_publish_approved', array('approval_id' => $a['approval_id'], 'operation' => 'archive'))['code']);
        $p = $this->ok($t, 'altus_publish_approved', array('approval_id' => $a['approval_id']));
        $this->assertEquals('MCP PHP approved headline', $this->CI->ha_page_builder->page($id)['tr']['en']['title']); $this->assertNotEmpty($p['audit_id']);
        $this->assertEquals('approval_consumed', $this->tool($t, 'altus_publish_approved', array('approval_id' => $a['approval_id']))['code'], 'single-use');
        $edit('Second'); $b = $this->ok($t, 'altus_request_publish', array('type' => 'page', 'id' => $id, 'operation' => 'publish')); $this->approve($b['approval_id']);
        $edit('Changed after approval');
        $c = $this->tool($t, 'altus_publish_approved', array('approval_id' => $b['approval_id'])); $this->assertEquals('conflict', $c['code'], 'invalid after change'); $this->assertEquals($b['version'], $c['data']['error']['details']['approved_version']);
        $d = $this->ok($t, 'altus_request_publish', array('type' => 'page', 'id' => $id, 'operation' => 'publish')); $this->approve($d['approval_id']);
        $this->db->where('id', $d['approval_id'])->update('ha_publisher_approval', array('expires_at' => gmdate('Y-m-d H:i:s', time() - 1)));
        $this->assertEquals('approval_expired', $this->tool($t, 'altus_publish_approved', array('approval_id' => $d['approval_id']))['code']);
        $this->assertEquals('expired', $this->ok($t, 'altus_approval_status', array('approval_id' => $d['approval_id']))['status']);
        // Lessons publish through the same approval.
        $course = (int) $this->db->select('id')->where('organization_id IS NULL', null, false)->get('ha_course')->row('id');
        $l = $this->ok($t, 'altus_create', array('type' => 'lessons', 'parent_id' => $course, 'data' => array('title_en' => 'Approved lesson')))['object_id'];
        $la = $this->ok($t, 'altus_request_publish', array('type' => 'lessons', 'id' => $l, 'operation' => 'publish')); $this->approve($la['approval_id']);
        $this->ok($t, 'altus_publish_approved', array('approval_id' => $la['approval_id'])); $this->assertEquals('published', $this->db->get_where('ha_lesson', array('id' => $l))->row('status'));
        $ar = $this->ok($t, 'altus_request_publish', array('type' => 'lessons', 'id' => $l, 'operation' => 'archive')); $this->approve($ar['approval_id']); $this->ok($t, 'altus_publish_approved', array('approval_id' => $ar['approval_id']));
        $this->assertEquals('archived', $this->db->get_where('ha_lesson', array('id' => $l))->row('status'));
        $this->ok($t, 'altus_restore_archived', array('type' => 'lessons', 'id' => $l)); $this->assertEquals('draft', $this->db->get_where('ha_lesson', array('id' => $l))->row('status'));
    }
    public function test_admin_integrations_screen_and_native_revoke() {
        $tok = $this->login($this->admin); $P = $this->CI->ha_publishing_service;
        foreach (array('connections', 'health') as $tab) { $v = $P->admin_view(array('tab' => $tab)); $html = $this->CI->load->view('hkp/studio_integrations', $v, true); $this->assertContains('http://altus.test/mcp', $html); }
        $v = $P->admin_view(array('tab' => 'connections')); $this->assertNotEmpty($v['php']['grants']); $this->assertNotEmpty($v['php']['clients']);
        $html = $this->CI->load->view('hkp/studio_integrations', $v, true); $this->assertContains('claude mcp add --transport http altus http://altus.test/mcp', $html); $this->assertNotContains($tok['access_token'], $html);
        $grant = $this->db->get_where('ha_mcp_token', array('token_hash' => hash('sha256', $tok['access_token'])))->row('grant_id');
        $this->assertEquals('revoked', $P->admin_post(array('action' => 'revoke_native', 'grant' => $grant)));
        $this->assertEquals(401, $this->rpc($tok['access_token'], array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'))['status']);
    }
    public function test_migration_032_down_and_up() {
        require_once APPPATH . 'migrations/20260101000032_mcp_php.php'; $m = new Migration_Mcp_php();
        $m->down(); $this->assertFalse($this->db->table_exists('ha_mcp_token')); $this->assertTrue($this->db->table_exists('ha_publisher_approval'), 'down() drops only its own tables');
        $m->up(); $this->db->data_cache = array(); $this->assertTrue($this->db->table_exists('ha_mcp_token'));
        // 033 extends ha_mcp_client; re-apply it so later suites see the full schema.
        require_once APPPATH . 'migrations/20260101000033_mcp_console.php'; (new Migration_Mcp_console())->up(); $this->db->data_cache = array();
    }
}
