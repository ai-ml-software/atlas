<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/Ha_gateway.php';     // Ha_api_error
require_once __DIR__ . '/Ha_mcp_oauth.php';   // Ha_oauth_error

/**
 * Native PHP MCP server (no Node): Streamable HTTP transport, MCP 2025-06-18
 * (also 2025-03-26 and 2024-11-05), JSON-RPC 2.0 with batches, plus the HTTP
 * side of the OAuth 2.1 authorization server.
 *
 * dispatch() takes a plain request array and returns a plain response array,
 * so the controller stays a thin adapter and tests drive the real server.
 *   request:  method, path, headers (lower-case keys), query, body (raw), ip
 *   response: status, headers (name => value), body (string)
 */
class Ha_mcp_server {
    const PROTOCOLS = array('2025-06-18', '2025-03-26', '2024-11-05');
    const LATEST = '2025-06-18';
    const SERVER = array('name' => 'altus-publisher-php', 'title' => 'ALTUS Publishing', 'version' => '2.0.0');
    private $CI; private $request_id;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_mcp_oauth', 'ha_auth'));
    }
    private function O() { return $this->CI->ha_mcp_oauth; }

    // ================================================================== HTTP helpers
    private static function json($status, $data, array $headers = array()) {
        return array('status' => $status, 'headers' => $headers + array('Content-Type' => 'application/json', 'Cache-Control' => 'no-store'), 'body' => $data === null ? '' : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    private static function cors() { return array('Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Mcp-Session-Id, MCP-Protocol-Version, Last-Event-ID', 'Access-Control-Expose-Headers' => 'Mcp-Session-Id, WWW-Authenticate', 'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS'); }
    private static function oauth_error(Ha_oauth_error $e) { return self::json($e->status, array('error' => $e->error, 'error_description' => $e->getMessage()), self::cors() + array('Pragma' => 'no-cache')); }
    private static function form($req) {
        $ct = strtolower((string) ($req['headers']['content-type'] ?? ''));
        if (strpos($ct, 'application/json') !== false) { $d = json_decode((string) $req['body'], true); return is_array($d) ? $d : array(); }
        $out = array(); parse_str((string) $req['body'], $out); return $out;
    }

    // ================================================================== router
    public function dispatch(array $req) {
        $req += array('method' => 'GET', 'path' => '', 'headers' => array(), 'query' => array(), 'body' => '', 'ip' => '');
        $req['headers'] = array_change_key_case($req['headers'], CASE_LOWER);
        $path = trim($req['path'], '/'); $m = strtoupper($req['method']);
        $this->request_id = bin2hex(random_bytes(10));
        if ($m === 'OPTIONS') return array('status' => 204, 'headers' => self::cors() + array('Access-Control-Max-Age' => '600'), 'body' => '');
        if (!$this->O()->enabled()) {
            if ($path === 'mcp') return self::json(503, array('jsonrpc' => '2.0', 'id' => null, 'error' => array('code' => -32000, 'message' => 'ALTUS MCP is switched off (ALTUS_MCP_ENABLED).', 'data' => array('code' => 'mcp_disabled'))), array('Retry-After' => '3600'));
            return self::json(503, array('error' => 'temporarily_unavailable', 'error_description' => 'ALTUS MCP is switched off (ALTUS_MCP_ENABLED).'), self::cors());
        }
        if ($wait = $this->O()->rate_limited('ip:' . $req['ip'], (int) $this->O()->cfg('mcp_ip_rate_limit', 300))) return self::json(429, array('error' => 'rate_limited', 'error_description' => 'Too many requests.'), self::cors() + array('Retry-After' => (string) $wait));
        try {
            if (preg_match('~^\.well-known/(oauth-authorization-server|openid-configuration)(/.*)?$~', $path, $wk)) return $m === 'GET' ? self::json(200, $this->O()->as_metadata($wk[1] === 'openid-configuration'), self::cors() + array('Cache-Control' => 'public, max-age=300')) : self::json(405, array('error' => 'method_not_allowed'), array('Allow' => 'GET'));
            if ($path === '.well-known/jwks.json') return self::json(200, array('keys' => array()), self::cors());   // no ID tokens are issued; empty set for OIDC-discovery parsers
            if (preg_match('~^\.well-known/oauth-protected-resource(/mcp)?$~', $path)) return $m === 'GET' ? self::json(200, $this->O()->pr_metadata(), self::cors() + array('Cache-Control' => 'public, max-age=300')) : self::json(405, array('error' => 'method_not_allowed'), array('Allow' => 'GET'));
            if ($path === 'oauth/register') { if ($m !== 'POST') return self::json(405, array('error' => 'method_not_allowed'), array('Allow' => 'POST')); $in = json_decode((string) $req['body'], true); if (!is_array($in)) throw new Ha_oauth_error('invalid_client_metadata', 'Send the client metadata as a JSON object.'); return self::json(201, $this->O()->register($in, $req['ip']), self::cors()); }
            if ($path === 'oauth/token') { if ($m !== 'POST') return self::json(405, array('error' => 'method_not_allowed'), array('Allow' => 'POST')); if ($wait = $this->O()->rate_limited('token:' . $req['ip'], 60)) return self::json(429, array('error' => 'slow_down', 'error_description' => 'Too many token requests.'), self::cors() + array('Retry-After' => (string) $wait)); return self::json(200, $this->O()->token(self::form($req)), self::cors() + array('Pragma' => 'no-cache')); }
            if ($path === 'oauth/revoke') { if ($m !== 'POST') return self::json(405, array('error' => 'method_not_allowed'), array('Allow' => 'POST')); $this->O()->revoke(self::form($req)); return self::json(200, new stdClass(), self::cors()); }
            if ($path === 'oauth/authorize') return $this->authorize($req);
            if ($path === 'mcp') return $this->mcp($req);
        } catch (Ha_oauth_error $e) { return self::oauth_error($e); }
        return self::json(404, array('error' => 'not_found'));
    }
    private function authorize(array $req) {
        $r = $this->O()->authorize_request((array) $req['query']);
        if (isset($r['page_error'])) return array('status' => 400, 'headers' => array('Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store'), 'body' => '<!doctype html><meta charset="utf-8"><title>Authorization error</title><body style="font-family:system-ui;max-width:560px;margin:48px auto;padding:0 16px"><h1>Cannot connect this client</h1><p>' . htmlspecialchars($r['page_error'], ENT_QUOTES, 'UTF-8') . '</p></body>');
        if (isset($r['redirect'])) return array('status' => 302, 'headers' => array('Location' => $r['redirect'], 'Cache-Control' => 'no-store'), 'body' => '');
        return array('status' => 302, 'headers' => array('Location' => hkp_url('cms/mcp_authorize') . '?request=' . $r['request_id'], 'Cache-Control' => 'no-store'), 'body' => '');
    }

    // ================================================================== /mcp
    private function challenge($error = null, $desc = null) {
        // scope lists what a full connection may ask for; the consent screen caps it by the user's permissions.
        $v = 'Bearer resource_metadata="' . $this->O()->resource_metadata_url() . '", scope="' . implode(' ', array_diff(Ha_mcp_oauth::SCOPES, array('altus.admin'))) . '"';
        if ($error) $v .= ', error="' . $error . '"' . ($desc ? ', error_description="' . str_replace('"', "'", $desc) . '"' : '');
        return $v;
    }
    private function origin_ok($origin) {
        if ($origin === null || $origin === '') return true;
        $p = parse_url($this->O()->issuer()); $own = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $allowed = array_merge(array($own), (array) $this->O()->cfg('mcp_allowed_origins', array()));
        return in_array(rtrim($origin, '/'), array_map(function ($o) { return rtrim($o, '/'); }, $allowed), true);
    }
    private static function rpc_error($id, $code, $message, $data = null) { $e = array('code' => $code, 'message' => $message); if ($data !== null) $e['data'] = $data; return array('jsonrpc' => '2.0', 'id' => $id, 'error' => $e); }

    private function mcp(array $req) {
        $h = $req['headers']; $m = strtoupper($req['method']);
        if (!$this->origin_ok($h['origin'] ?? null)) return self::json(403, self::rpc_error(null, -32000, 'Origin not allowed.'));
        $pv = $h['mcp-protocol-version'] ?? null;
        if ($pv !== null && !in_array($pv, self::PROTOCOLS, true)) return self::json(400, self::rpc_error(null, -32600, 'Unsupported MCP-Protocol-Version: ' . $pv, array('supported' => self::PROTOCOLS)));
        if (!in_array($m, array('POST', 'GET', 'DELETE'), true)) return self::json(405, self::rpc_error(null, -32000, 'Method not allowed.'), array('Allow' => 'POST, GET, DELETE'));
        // Authentication: bearer header only (never query strings).
        $auth = (string) ($h['authorization'] ?? '');
        if (isset($req['query']['access_token']) || !preg_match('/^Bearer\s+(\S+)$/i', $auth, $mm)) return self::json(401, self::rpc_error(null, -32001, 'Authentication required.'), array('WWW-Authenticate' => $this->challenge()) + self::cors());
        try {
            $claims = $this->O()->authenticate($mm[1]);
            if (!$this->O()->user_active($claims['user_id'])) throw new Ha_api_error(401, 'invalid_token', 'The ALTUS user is inactive.');
            $this->CI->ha_auth->from_gateway(array('verified' => true, 'sub' => $claims['user_id']));
        } catch (Throwable $e) {
            $msg = $e instanceof Ha_api_error ? $e->getMessage() : 'The ALTUS user is inactive.';
            $this->log_call(array('source' => strpos($mm[1], Ha_mcp_oauth::PERSONAL_PREFIX) === 0 ? 'personal' : 'oauth', 'user_id' => null, 'client_id' => null, 'grant_id' => null), 'auth', null, 'denied', 'invalid_token', 0, mb_substr($msg, 0, 200));
            return self::json(401, self::rpc_error(null, -32001, $msg), array('WWW-Authenticate' => $this->challenge('invalid_token', $msg)) + self::cors());
        }
        if ($wait = $this->O()->rate_limited('cu:' . $claims['client_id'] . ':' . $claims['user_id'], (int) $this->O()->cfg('mcp_rate_limit', 120))) return self::json(429, self::rpc_error(null, -32000, 'Rate limit exceeded. Retry later.'), array('Retry-After' => (string) $wait));
        $A = $this->CI->ha_auth; $perms = $A->permissions();
        if ($A->is_super_admin()) $perms = array_merge($perms, array('cms_pages.update', 'courses.update', 'media.create', 'cms_pages.publish', 'system.configure', 'knowledge.create'));
        $ctx = array('client_id' => $claims['client_id'], 'grant_id' => $claims['grant_id'], 'user_id' => $claims['user_id'], 'granted' => $claims['scope'],
            'scopes' => array_values(array_intersect($claims['scope'], Ha_mcp_oauth::allowed_scopes($perms, $A->is_system_scoped()))), 'resource' => $this->O()->resource(), 'request_id' => $this->request_id, 'source' => $claims['source'] ?? 'oauth');
        $this->seen($ctx, 'mcp');
        $sid = $h['mcp-session-id'] ?? null; $db = $this->CI->db;
        if ($sid !== null) {
            $s = preg_match('/^[a-f0-9]{48}$/', $sid) ? $db->get_where('ha_mcp_session', array('id' => $sid))->row_array() : null;
            if (!$s || $s['grant_id'] !== $ctx['grant_id']) return self::json(404, self::rpc_error(null, -32001, 'Session not found. Initialize a new session.'));
            if (strtotime($s['last_seen_at'] . ' UTC') < time() - 60) $db->where('id', $sid)->update('ha_mcp_session', array('last_seen_at' => gmdate('Y-m-d H:i:s')));
        }
        if ($m === 'GET') return self::json(405, self::rpc_error(null, -32000, 'This server does not offer a server-to-client SSE stream; use POST.'), array('Allow' => 'POST, DELETE'));
        if ($m === 'DELETE') { if (!$sid) return self::json(400, self::rpc_error(null, -32600, 'Mcp-Session-Id header required.')); $db->where('id', $sid)->delete('ha_mcp_session'); return array('status' => 204, 'headers' => array(), 'body' => ''); }
        // POST: one JSON-RPC message or a batch.
        $raw = (string) $req['body'];
        if (strlen($raw) > 22 * 1048576) return self::json(413, self::rpc_error(null, -32600, 'Request too large.'));
        $msg = json_decode($raw, true);
        if ($raw === '' || ($msg === null && json_last_error() !== JSON_ERROR_NONE) || !is_array($msg)) return self::json(400, self::rpc_error(null, -32700, 'Parse error: the body must be JSON-RPC 2.0.'));
        $batch = $msg !== array() && array_keys($msg) === range(0, count($msg) - 1);
        if ($msg === array()) return self::json(400, self::rpc_error(null, -32600, 'Invalid Request: empty batch.'));
        $messages = $batch ? $msg : array($msg);
        $responses = array(); $headers = array(); $version = $pv ?: '2025-03-26';
        foreach ($messages as $one) {
            if (!is_array($one) || ($one['jsonrpc'] ?? null) !== '2.0') { $responses[] = self::rpc_error(is_array($one) && isset($one['id']) ? $one['id'] : null, -32600, 'Invalid Request.'); continue; }
            if (!isset($one['method'])) continue;   // a response/error sent by the client: accepted, nothing to answer
            $has_id = array_key_exists('id', $one);
            if ($has_id && !(is_string($one['id']) || is_int($one['id']))) { $responses[] = self::rpc_error(null, -32600, 'Invalid Request: id must be a string or integer.'); continue; }
            if (!is_string($one['method'])) { if ($has_id) $responses[] = self::rpc_error($one['id'], -32600, 'Invalid Request.'); continue; }
            $params = $one['params'] ?? array();
            if (!is_array($params)) { if ($has_id) $responses[] = self::rpc_error($one['id'], -32602, 'Invalid params.'); continue; }
            if (!$has_id) continue;   // notifications (notifications/initialized, cancelled, ...) need no answer
            if ($one['method'] === 'initialize') {
                if ($batch && count($messages) > 1) { $responses[] = self::rpc_error($one['id'], -32600, 'initialize must not be part of a batch.'); continue; }
                $asked = (string) ($params['protocolVersion'] ?? ''); $version = in_array($asked, self::PROTOCOLS, true) ? $asked : self::LATEST;
                $sid = bin2hex(random_bytes(24));
                $ci = isset($params['clientInfo']) && is_array($params['clientInfo']) ? mb_substr(($params['clientInfo']['name'] ?? '') . ' ' . ($params['clientInfo']['version'] ?? ''), 0, 255) : null;
                $db->insert('ha_mcp_session', array('id' => $sid, 'grant_id' => $ctx['grant_id'], 'client_id' => $ctx['client_id'], 'user_id' => $ctx['user_id'], 'protocol_version' => $version, 'client_info' => $ci, 'created_at' => gmdate('Y-m-d H:i:s'), 'last_seen_at' => gmdate('Y-m-d H:i:s')));
                if (mt_rand(1, 50) === 1) $db->where('last_seen_at <', gmdate('Y-m-d H:i:s', time() - 86400))->delete('ha_mcp_session');
                $headers['Mcp-Session-Id'] = $sid;
                $responses[] = array('jsonrpc' => '2.0', 'id' => $one['id'], 'result' => $this->init_result($version));
                $this->log_call($ctx, 'initialize', null, 'ok', null, 0, $ci);
                continue;
            }
            $responses[] = $this->logged_call($one['id'], $one['method'], $params, $ctx);
        }
        if (!$responses) return array('status' => 202, 'headers' => self::cors(), 'body' => '');
        return self::json(200, $batch ? $responses : $responses[0], $headers + self::cors());
    }

    private function init_result($version) {
        return array('protocolVersion' => $version, 'capabilities' => array('tools' => array('listChanged' => false), 'resources' => array('subscribe' => false, 'listChanged' => false)),
            'serverInfo' => self::SERVER, 'instructions' => 'ALTUS publishing tools. Start with altus_site_info. Read with altus_get to obtain "version", pass it as expected_version on updates, and pass an idempotency_key on writes. All writes are private drafts; publication needs altus_request_publish and a human approval, then altus_publish_approved. There are no delete tools.');
    }

    /** call() plus one structured ha_mcp_call_log row (status, error code, latency). */
    private function logged_call($id, $method, array $params, array $ctx) {
        $t = microtime(true);
        $r = $this->call($id, $method, $params, $ctx);
        $tool = $method === 'tools/call' && isset($params['name']) && is_string($params['name']) ? mb_substr($params['name'], 0, 60) : null;
        $status = 'ok'; $code = null;
        if (isset($r['error'])) { $status = 'error'; $code = 'rpc_' . $r['error']['code']; }
        elseif (is_array($r['result'] ?? null) && !empty($r['result']['isError'])) { $code = $r['result']['structuredContent']['error']['code'] ?? 'tool_error'; $status = $code === 'insufficient_scope' ? 'denied' : 'error'; }
        $summary = $tool && isset($params['arguments']) && is_array($params['arguments']) ? self::summarize($params['arguments']) : null;
        $this->log_call($ctx, $method, $tool, $status, $code, (int) round((microtime(true) - $t) * 1000), $summary);
        return $r;
    }
    private static function summarize(array $args) {
        $what = array(); foreach (array('type', 'id', 'parent_id', 'page_id', 'operation', 'action', 'approval_id', 'target', 'query') as $k) if (isset($args[$k]) && is_scalar($args[$k])) $what[] = $k . '=' . mb_substr((string) $args[$k], 0, 40);
        return $what ? implode(' ', $what) : null;
    }
    public function log_call(array $ctx, $method, $tool, $status, $code, $ms, $summary = null) {
        try {
            $db = $this->CI->db; if (!$db->table_exists('ha_mcp_call_log')) return;
            $db->insert('ha_mcp_call_log', array('created_at' => gmdate('Y-m-d H:i:s'), 'source' => substr((string) ($ctx['source'] ?? 'oauth'), 0, 10), 'user_id' => !empty($ctx['user_id']) ? (int) $ctx['user_id'] : null,
                'client_id' => isset($ctx['client_id']) ? substr((string) $ctx['client_id'], 0, 190) : null, 'grant_id' => isset($ctx['grant_id']) ? substr((string) $ctx['grant_id'], 0, 64) : null,
                'method' => substr((string) $method, 0, 60), 'tool' => $tool, 'status' => $status, 'error_code' => $code ? substr($code, 0, 60) : null, 'ms' => max(0, (int) $ms),
                'request_id' => $this->request_id, 'summary' => $summary !== null ? mb_substr($summary, 0, 255) : null, 'dry_run' => !empty($ctx['dry_run']) ? 1 : 0));
            if (mt_rand(1, 500) === 1) $db->where('created_at <', gmdate('Y-m-d H:i:s', time() - 90 * 86400))->delete('ha_mcp_call_log');
        } catch (Throwable $e) { log_message('error', 'MCP call log: ' . $e->getMessage()); }
    }

    // ================================================================== admin test console (in-process, the admin's own session)
    /** Tools that touch files or paid AI providers cannot be rolled back, so they are refused in dry-run. */
    const DRY_RUN_UNSAFE = array('altus_upload_media', 'altus_document_upload', 'altus_document_generate', 'altus_translate', 'altus_document_control');
    /** Context for the signed-in administrator at a chosen access level (the level is a ceiling over their own permissions). */
    public function console_ctx($level, $dry_run = true) {
        $A = $this->CI->ha_auth; $perms = $A->permissions();
        if ($A->is_super_admin()) $perms = array_merge($perms, array('cms_pages.update', 'courses.update', 'media.create', 'cms_pages.publish', 'system.configure', 'knowledge.create'));
        $level = Ha_mcp_oauth::valid_level($level) ? $level : 'read';
        $granted = Ha_mcp_oauth::level_scopes($level);
        return array('client_id' => 'altus-console', 'grant_id' => 'console_' . (int) $A->id(), 'user_id' => (int) $A->id(), 'granted' => $granted,
            'scopes' => array_values(array_intersect($granted, Ha_mcp_oauth::allowed_scopes($perms, $A->is_system_scoped()))), 'resource' => $this->O()->resource(),
            'request_id' => null, 'source' => 'console', 'dry_run' => (bool) $dry_run, 'level' => $level);
    }
    /** One JSON-RPC message through the same call() / run_tool() path the HTTP endpoint uses. */
    public function console_rpc(array $msg, array $ctx) {
        $this->request_id = bin2hex(random_bytes(10)); $ctx['request_id'] = $this->request_id;
        $id = isset($msg['id']) && (is_int($msg['id']) || is_string($msg['id'])) ? $msg['id'] : null;
        if (($msg['jsonrpc'] ?? null) !== '2.0' || !isset($msg['method']) || !is_string($msg['method']) || $id === null) return self::rpc_error($id, -32600, 'Invalid Request: send {"jsonrpc":"2.0","id":1,"method":"…","params":{…}}.');
        $params = $msg['params'] ?? array(); if (!is_array($params)) return self::rpc_error($id, -32602, 'Invalid params.');
        if ($msg['method'] === 'initialize') { $this->log_call($ctx, 'initialize', null, 'ok', null, 0, 'console'); return array('jsonrpc' => '2.0', 'id' => $id, 'result' => $this->init_result(self::LATEST)); }
        return $this->logged_call($id, $msg['method'], $params, $ctx);
    }
    public function last_request_id() { return $this->request_id; }

    private function seen(array $ctx, $action) {
        if (($ctx['source'] ?? '') === 'console') return;
        try { $this->CI->db->query('INSERT INTO ha_mcp_connection (grant_id,client_id,user_id,last_action,last_seen_at,requests) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE last_action=VALUES(last_action),last_seen_at=VALUES(last_seen_at),requests=requests+1', array($ctx['grant_id'], $ctx['client_id'], (int) $ctx['user_id'], substr($action, 0, 60), gmdate('Y-m-d H:i:s'))); } catch (Throwable $e) { log_message('error', 'MCP last-seen: ' . $e->getMessage()); }
    }

    // ================================================================== JSON-RPC methods
    public function call($id, $method, array $params, array $ctx) {
        $ok = function ($result) use ($id) { return array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result); };
        switch ($method) {
            case 'ping': return $ok(new stdClass());
            case 'tools/list': $this->CI->load->library('ha_mcp_tools'); return $ok(array('tools' => $this->CI->ha_mcp_tools->listing_for($ctx['scopes'])));
            case 'tools/call':
                $name = $params['name'] ?? null; $args = $params['arguments'] ?? array();
                $this->CI->load->library('ha_mcp_tools'); $defs = $this->CI->ha_mcp_tools->definitions();
                if (!is_string($name) || !isset($defs[$name])) return self::rpc_error($id, -32602, 'Unknown tool: ' . (is_string($name) ? $name : '(none)'));
                if (!is_array($args)) return self::rpc_error($id, -32602, 'arguments must be an object.');
                $this->seen($ctx, $name);
                return $ok($this->run_tool($name, $defs[$name], $args, $ctx));
            case 'resources/list': return $ok(array('resources' => array(
                array('uri' => 'altus://site/info', 'name' => 'site-info', 'title' => 'Connection and site information', 'mimeType' => 'application/json'),
                array('uri' => 'altus://docs/rules', 'name' => 'publishing-rules', 'title' => 'ALTUS publishing rules for assistants', 'mimeType' => 'text/markdown'))));
            case 'resources/templates/list': return $ok(array('resourceTemplates' => array()));
            case 'resources/read':
                $uri = (string) ($params['uri'] ?? '');
                if ($uri === 'altus://site/info') { $this->CI->load->library('ha_mcp_tools'); $r = $this->run_tool('altus_site_info', $this->CI->ha_mcp_tools->definitions()['altus_site_info'], array(), $ctx); return $ok(array('contents' => array(array('uri' => $uri, 'mimeType' => 'application/json', 'text' => json_encode($r['structuredContent'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))))); }
                if ($uri === 'altus://docs/rules') return $ok(array('contents' => array(array('uri' => $uri, 'mimeType' => 'text/markdown', 'text' => "# ALTUS publishing rules\n\n- Every write creates or changes a private draft.\n- Read first (altus_get) and send `expected_version` on updates; a conflict returns the current version.\n- Send an `idempotency_key` with writes so retries are safe.\n- Publishing or archiving: altus_request_publish -> another person approves in ALTUS (10 minutes, single-use) -> altus_publish_approved.\n- SOPs follow the SOP governance workflow (altus_sop_workflow submit).\n- There are no delete, SQL or shell tools."))));
                return self::rpc_error($id, -32002, 'Resource not found.', array('uri' => $uri));
            case 'prompts/list': return $ok(array('prompts' => array()));
            case 'logging/setLevel': return $ok(new stdClass());
            case 'completion/complete': return $ok(array('completion' => array('values' => array(), 'hasMore' => false)));
        }
        return self::rpc_error($id, -32601, 'Method not found: ' . $method);
    }

    /** Validation, scope, idempotency, transaction, audit, error mapping. Always returns a CallToolResult. */
    public function run_tool($name, array $def, array $args, array $ctx) {
        $T = $this->CI->ha_mcp_tools;
        $errors = Ha_mcp_tools::validate($def['schema'], $args);
        if ($errors) return $this->tool_error($name, $args, $ctx, new Ha_api_error(422, 'validation_failed', 'Invalid arguments: ' . implode('; ', array_slice($errors, 0, 8)), array('errors' => $errors)));
        $scope = is_string($def['scope']) ? $def['scope'] : call_user_func($def['scope'], $args);
        if (!in_array($scope, $ctx['scopes'], true)) {
            $why = in_array($scope, $ctx['granted'], true) ? 'Your current ALTUS permissions no longer allow this scope.' : 'The connection was not granted this scope. Reconnect and approve it.';
            return $this->tool_error($name, $args, $ctx, new Ha_api_error(403, 'insufficient_scope', 'Required scope: ' . $scope . '. ' . $why, array('required_scope' => $scope, 'effective_scopes' => $ctx['scopes'])));
        }
        $key = $args['idempotency_key'] ?? null; unset($args['idempotency_key']);
        $db = $this->CI->db; $uid = (int) $this->CI->ha_auth->id();
        if (empty($def['write'])) {
            try { $result = call_user_func($def['run'], $args, $ctx); $this->audit($name, $args, $ctx, 'ok'); return self::tool_ok($result); }
            catch (Throwable $e) { return $this->tool_error($name, $args, $ctx, $e); }
        }
        if (!empty($ctx['dry_run'])) {
            // Console dry-run: the real write runs inside a transaction that is always rolled back.
            if (in_array($name, self::DRY_RUN_UNSAFE, true)) return $this->tool_error($name, $args, $ctx, new Ha_api_error(422, 'dry_run_unsupported', 'This tool stores files or calls an AI provider, which cannot be rolled back. Untick dry-run to run it for real.'));
            $db->trans_begin();
            try { $result = call_user_func($def['run'], $args, $ctx); $db->trans_rollback(); }
            catch (Throwable $e) { $db->trans_rollback(); return $this->tool_error($name, $args, $ctx, $e); }
            $result = is_array($result) ? $result : array('result' => $result);
            $result['dry_run'] = true; $result['dry_run_note'] = 'Rolled back: nothing was saved.'; $result['request_id'] = $this->request_id;
            return self::tool_ok($result);
        }
        $hash = hash('sha256', $name . "\n" . json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $db->trans_begin();
        try {
            if ($key !== null) {
                $db->query('INSERT IGNORE INTO ha_publisher_request (actor_id,client_id,request_key,request_hash,created_at) VALUES (?,?,?,?,?)', array($uid, $ctx['client_id'], $key, $hash, date('Y-m-d H:i:s')));
                $r = $db->query('SELECT * FROM ha_publisher_request WHERE actor_id=? AND client_id=? AND request_key=? FOR UPDATE', array($uid, $ctx['client_id'], $key))->row_array();
                if (!hash_equals($r['request_hash'], $hash)) throw new Ha_api_error(409, 'idempotency_conflict', 'This idempotency_key was already used with different arguments.', array('idempotency_key' => $key));
                if ($r['response_json']) { $db->trans_commit(); $prev = json_decode($r['response_json'], true); $prev['idempotent_replayed'] = true; return self::tool_ok($prev); }
            }
            $result = call_user_func($def['run'], $args, $ctx);
            $result = is_array($result) ? $result : array('result' => $result);
            $result['audit_id'] = $this->audit($name, $args, $ctx, 'ok');
            $result['request_id'] = $this->request_id;
            if ($key !== null) $db->where(array('actor_id' => $uid, 'client_id' => $ctx['client_id'], 'request_key' => $key))->update('ha_publisher_request', array('response_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            if ($db->trans_status() === false) throw new Ha_api_error(500, 'internal_error', 'The change was rolled back.', array(), true);
            $db->trans_commit();
            return self::tool_ok($result);
        } catch (Throwable $e) { $db->trans_rollback(); return $this->tool_error($name, $args, $ctx, $e); }
    }
    private static function tool_ok($result) {
        $structured = is_array($result) && ($result === array() || array_keys($result) !== range(0, count($result) - 1)) ? $result : array('items' => $result);
        return array('content' => array(array('type' => 'text', 'text' => json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))), 'structuredContent' => (object) $structured, 'isError' => false);
    }
    /** Maps service exceptions to the publisher API's error codes. */
    public function map_error(Throwable $e, array $args) {
        if ($e instanceof Ha_api_error) return array($e->status, $e->error_code, $e->getMessage(), $e->details, $e->retryable);
        $m = $e->getMessage();
        if (preg_match('/not found/i', $m)) return array(404, 'not_found', $m, array(), false);
        if ($e instanceof DomainException) { $cur = array(); if (!empty($args['type']) && !empty($args['id'])) { try { $this->CI->load->library('ha_publishing_service'); $cur = $this->CI->ha_publishing_service->current($args['type'], (int) $args['id']); } catch (Throwable $x) {} } return array(409, 'conflict', $m, $cur, false); }
        if ($e instanceof InvalidArgumentException) return array(422, 'validation_failed', $m, array(), false);
        if ($e instanceof RuntimeException && !($e instanceof mysqli_sql_exception)) return array(403, 'forbidden', $m, array(), false);
        log_message('error', 'MCP tool ' . $this->request_id . ': ' . get_class($e) . ' ' . $m);
        return array(500, 'internal_error', 'The request could not be completed.', array(), true);
    }
    private function tool_error($name, array $args, array $ctx, Throwable $e) {
        list($status, $code, $message, $details, $retry) = $this->map_error($e, $args);
        $this->audit($name, $args, $ctx, 'error ' . $code);
        $err = array('ok' => false, 'error' => array('code' => $code, 'message' => $message, 'status' => $status, 'details' => (object) $details, 'retryable' => (bool) $retry), 'request_id' => $this->request_id);
        return array('content' => array(array('type' => 'text', 'text' => json_encode($err, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))), 'structuredContent' => $err, 'isError' => true);
    }
    /** One audit row per tool call. Arguments are summarised (type/id/operation), never logged raw. */
    private function audit($name, array $args, array $ctx, $outcome) {
        try {
            $this->CI->load->library('ha_audit');
            $what = array(); foreach (array('type', 'id', 'parent_id', 'page_id', 'operation', 'action', 'approval_id', 'target') as $k) if (isset($args[$k]) && is_scalar($args[$k])) $what[] = $k . '=' . mb_substr((string) $args[$k], 0, 40);
            return $this->CI->ha_audit->log('mcp.' . substr($name, 0, 50), 'mcp', (int) ($args['id'] ?? $args['page_id'] ?? $args['approval_id'] ?? 0), array('description' => 'MCP (PHP) client ' . $ctx['client_id'] . ' · ' . $name . ' ' . implode(' ', $what) . ' · ' . $outcome . ' · request ' . $this->request_id));
        } catch (Throwable $e) { log_message('error', 'MCP audit: ' . $e->getMessage()); return null; }
    }
}
