<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/Ha_mcp_oauth.php';

/**
 * Read models and actions behind the MCP admin console (/hkp/mcp).
 * Every public method except can_manage() re-checks the permission itself, so the
 * controller gate is never the only control.
 */
class Ha_mcp_console {
    private $CI;
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_mcp_oauth'));
    }
    private function O() { return $this->CI->ha_mcp_oauth; }
    private function db() { return $this->CI->db; }

    /** Platform administrators who may change settings manage MCP. */
    public function can_manage() { $A = $this->CI->ha_auth; return (bool) $A->id() && $A->is_system_scoped() && $A->has('settings.update'); }
    public function guard() { if (!$this->can_manage()) throw new RuntimeException('Managing MCP connections requires platform settings access.'); }

    // ------------------------------------------------------------------ overview
    public function overview() {
        $this->guard(); $db = $this->db(); $O = $this->O(); $since = gmdate('Y-m-d H:i:s', time() - 86400);
        $installed = $db->table_exists('ha_mcp_client'); $console = $O->console_ready();
        $n = function ($sql, array $b = array()) use ($db) { return (int) $db->query($sql, $b)->row('n'); };
        $o = array('enabled' => $O->enabled(), 'installed' => $installed, 'console_ready' => $console, 'endpoint' => $O->resource(), 'issuer' => $O->issuer(),
            'metadata' => $O->resource_metadata_url(), 'clients' => 0, 'grants' => 0, 'personal' => 0, 'calls' => 0, 'errors' => 0, 'denied' => 0, 'pending' => 0,
            'tools' => count($this->catalogue()), 'avg_ms' => 0, 'spark' => array_fill(0, 24, 0), 'spark_err' => array_fill(0, 24, 0), 'top_tools' => array(), 'recent' => array());
        if ($installed) {
            $o['clients'] = $n('SELECT COUNT(*) n FROM ha_mcp_client WHERE revoked_at IS NULL');
            $o['grants'] = $n('SELECT COUNT(*) n FROM ha_mcp_grant WHERE revoked_at IS NULL');
        }
        if ($db->table_exists('ha_publisher_approval')) $o['pending'] = $n("SELECT COUNT(*) n FROM ha_publisher_approval WHERE status='pending' AND expires_at > ?", array(gmdate('Y-m-d H:i:s')));
        if ($console) {
            $o['personal'] = $n('SELECT COUNT(*) n FROM ha_mcp_personal_token WHERE revoked_at IS NULL AND expires_at > ?', array(gmdate('Y-m-d H:i:s')));
            $row = $db->query("SELECT COUNT(*) calls, SUM(status='error') errors, SUM(status='denied') denied, AVG(ms) avg_ms FROM ha_mcp_call_log WHERE created_at >= ?", array($since))->row_array();
            $o['calls'] = (int) $row['calls']; $o['errors'] = (int) $row['errors']; $o['denied'] = (int) $row['denied']; $o['avg_ms'] = (int) round((float) $row['avg_ms']);
            $now = time();
            foreach ($db->query("SELECT created_at, status FROM ha_mcp_call_log WHERE created_at >= ?", array($since))->result_array() as $r) {
                $i = 23 - (int) floor(($now - strtotime($r['created_at'] . ' UTC')) / 3600); if ($i < 0 || $i > 23) continue;
                $o['spark'][$i]++; if ($r['status'] !== 'ok') $o['spark_err'][$i]++;
            }
            $o['top_tools'] = $db->query("SELECT tool, COUNT(*) n, SUM(status<>'ok') bad FROM ha_mcp_call_log WHERE created_at >= ? AND tool IS NOT NULL GROUP BY tool ORDER BY n DESC LIMIT 5", array($since))->result_array();
            $o['recent'] = $this->activity(array(), 1, 6)['rows'];
        }
        return $o;
    }

    /** In-process contract checks (always available) and the same checks over real HTTP. */
    public function health($http = true) {
        $this->guard(); $this->CI->load->library('ha_mcp_server'); $S = $this->CI->ha_mcp_server; $O = $this->O(); $out = array();
        $run = function ($key, $label, $method, $path, $test) use ($S, &$out) {
            $t = microtime(true);
            $r = $S->dispatch(array('method' => $method, 'path' => $path, 'headers' => array('content-type' => 'application/json'), 'query' => array(), 'body' => $method === 'POST' ? '{"jsonrpc":"2.0","id":1,"method":"ping"}' : '', 'ip' => 'console-health'));
            $j = json_decode($r['body'], true); $h = array_change_key_case($r['headers'], CASE_LOWER);
            $out[] = array('key' => $key, 'label' => $label, 'mode' => 'in-process', 'ok' => (bool) $test($r['status'], $j, $h), 'status' => $r['status'], 'ms' => (int) round((microtime(true) - $t) * 1000), 'url' => $path);
        };
        $res = $O->resource();
        $run('pr', 'Protected resource metadata (RFC 9728)', 'GET', '.well-known/oauth-protected-resource/mcp', function ($s, $j) use ($res) { return $s === 200 && ($j['resource'] ?? '') === $res; });
        $run('as', 'Authorization server metadata (RFC 8414)', 'GET', '.well-known/oauth-authorization-server', function ($s, $j) { return $s === 200 && in_array('S256', (array) ($j['code_challenge_methods_supported'] ?? array()), true); });
        $run('challenge', '401 challenge with resource_metadata', 'POST', 'mcp', function ($s, $j, $h) { return $s === 401 && strpos((string) ($h['www-authenticate'] ?? ''), 'resource_metadata=') !== false; });
        if ($http && $O->enabled()) {
            $labels = array('protected_resource' => 'Protected resource metadata over HTTP', 'authorization_server' => 'Authorization server metadata over HTTP', 'mcp_challenge' => '401 challenge over HTTP');
            foreach ($O->self_check() as $k => $c) $out[] = array('key' => 'http_' . $k, 'label' => $labels[$k] ?? $k, 'mode' => 'http', 'ok' => $c['ok'], 'status' => $c['status'], 'ms' => $c['ms'], 'url' => $c['url']);
        }
        return array('enabled' => $O->enabled(), 'checks' => $out, 'ok' => !array_filter($out, function ($c) { return !$c['ok']; }), 'checked_at' => gmdate('Y-m-d\TH:i:s\Z'));
    }

    // ------------------------------------------------------------------ connections
    public function connections() {
        $this->guard(); $O = $this->O(); $db = $this->db();
        $grants = $db->table_exists('ha_mcp_grant') ? $O->grants() : array();
        $seen = array();
        if ($grants && $db->table_exists('ha_mcp_connection')) foreach ($db->where_in('grant_id', array_column($grants, 'id'))->get('ha_mcp_connection')->result_array() as $s) $seen[$s['grant_id']] = $s;
        foreach ($grants as &$g) { $g['scopes'] = Ha_mcp_oauth::parse_scope($g['scope']); $g['level'] = Ha_mcp_oauth::level_of($g['scopes']); $g['requests'] = (int) ($seen[$g['id']]['requests'] ?? 0); $g['last_action'] = $seen[$g['id']]['last_action'] ?? null; }
        unset($g);
        $clients = $db->table_exists('ha_mcp_client') ? $O->clients() : array();
        $per_client = array(); foreach ($grants as $g) $per_client[$g['client_id']] = ($per_client[$g['client_id']] ?? 0) + 1;
        foreach ($clients as &$c) { $c['grants'] = $per_client[$c['client_id']] ?? 0; $c['redirects'] = json_decode($c['redirect_uris'], true) ?: array(); }
        unset($c);
        return array('grants' => $grants, 'clients' => $clients, 'personal' => $O->personal_tokens());
    }
    /** Active users an administrator can issue a personal connection for (newest platform staff first). */
    public function users($q = '') {
        $this->guard();
        $db = $this->db()->select('u.id, u.email, u.first_name, u.last_name')->from('users u')->where('u.status', 1);
        if ($q !== '') $db->group_start()->like('u.email', $q)->or_like('u.first_name', $q)->or_like('u.last_name', $q)->group_end();
        return $db->order_by('u.first_name')->limit(300)->get()->result_array();
    }

    // ------------------------------------------------------------------ activity
    public function activity(array $f, $page = 1, $per = 25) {
        $this->guard(); $db = $this->db();
        if (!$db->table_exists('ha_mcp_call_log')) return array('rows' => array(), 'total' => 0, 'page' => 1, 'pages' => 1);
        $apply = function () use ($db, $f) {
            $db->from('ha_mcp_call_log l');
            if (!empty($f['tool'])) $db->where('l.tool', (string) $f['tool']);
            if (!empty($f['client'])) $db->where('l.client_id', (string) $f['client']);
            if (!empty($f['user'])) $db->where('l.user_id', (int) $f['user']);
            if (!empty($f['status']) && in_array($f['status'], array('ok', 'error', 'denied'), true)) $db->where('l.status', $f['status']);
            if (!empty($f['source']) && in_array($f['source'], array('oauth', 'personal', 'console'), true)) $db->where('l.source', $f['source']);
            if (!empty($f['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) $db->where('l.created_at >=', $f['from'] . ' 00:00:00');
            if (!empty($f['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) $db->where('l.created_at <=', $f['to'] . ' 23:59:59');
        };
        $apply(); $total = (int) $db->count_all_results();
        $pages = max(1, (int) ceil($total / $per)); $page = min(max(1, (int) $page), $pages);
        $apply();
        $rows = $db->select('l.*, u.email, u.first_name, u.last_name, c.client_name')->join('users u', 'u.id = l.user_id', 'left')->join('ha_mcp_client c', 'c.client_id = l.client_id', 'left')
            ->order_by('l.id', 'DESC')->limit($per, ($page - 1) * $per)->get()->result_array();
        return array('rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages);
    }
    public function facets() {
        $this->guard(); $db = $this->db();
        if (!$db->table_exists('ha_mcp_call_log')) return array('tools' => array(), 'clients' => array(), 'users' => array());
        return array('tools' => array_column($db->query('SELECT DISTINCT tool FROM ha_mcp_call_log WHERE tool IS NOT NULL ORDER BY tool')->result_array(), 'tool'),
            'clients' => $db->query('SELECT DISTINCT l.client_id, c.client_name FROM ha_mcp_call_log l LEFT JOIN ha_mcp_client c ON c.client_id = l.client_id WHERE l.client_id IS NOT NULL ORDER BY l.client_id LIMIT 200')->result_array(),
            'users' => $db->query('SELECT DISTINCT l.user_id, u.email FROM ha_mcp_call_log l JOIN users u ON u.id = l.user_id ORDER BY u.email LIMIT 200')->result_array());
    }
    /** One call plus the matching audit rows (same request id). */
    public function call_detail($id) {
        $this->guard(); $db = $this->db();
        $r = $db->select('l.*, u.email, c.client_name')->from('ha_mcp_call_log l')->join('users u', 'u.id = l.user_id', 'left')->join('ha_mcp_client c', 'c.client_id = l.client_id', 'left')->where('l.id', (int) $id)->get()->row_array();
        if (!$r) return null;
        $r['audit'] = $r['request_id'] ? $db->select('id, action, description, created_at')->like('description', 'request ' . $r['request_id'], 'before')->order_by('id', 'DESC')->limit(5)->get('ha_audit_log')->result_array() : array();
        return $r;
    }

    // ------------------------------------------------------------------ tester
    /** Tool catalogue with the scope each needs, grouped for the tester (independent of the chosen level). */
    public function catalogue() {
        $this->guard(); $this->CI->load->library(array('ha_mcp_tools', 'ha_mcp_server')); $out = array();
        foreach ($this->CI->ha_mcp_tools->definitions() as $name => $d) {
            $scope = is_string($d['scope']) ? $d['scope'] : 'altus.content.write|altus.course.write';
            $group = $scope === 'altus.read' ? 'read' : ($scope === 'altus.publish' ? 'full' : 'write');
            $out[] = array('name' => $name, 'title' => $d['annotations']['title'], 'description' => $d['description'], 'scope' => $scope, 'group' => $group,
                'write' => !empty($d['write']), 'annotations' => $d['annotations'], 'inputSchema' => $d['schema'], 'dry_run_unsafe' => in_array($name, Ha_mcp_server::DRY_RUN_UNSAFE, true));
        }
        return $out;
    }
    public function rpc(array $msg, $level, $dry_run) {
        $this->guard(); $this->CI->load->library('ha_mcp_server'); $S = $this->CI->ha_mcp_server;
        $ctx = $S->console_ctx($level, $dry_run); $t = microtime(true);
        $res = $S->console_rpc($msg, $ctx);
        return array('ok' => true, 'level' => $ctx['level'], 'dry_run' => $ctx['dry_run'], 'effective_scopes' => $ctx['scopes'], 'ms' => (int) round((microtime(true) - $t) * 1000),
            'request' => $msg, 'response' => $res, 'request_id' => $S->last_request_id());
    }
    /**
     * Safe suite at every level: initialize + tools/list, two reads, a dry-run draft create (rolled back)
     * and a publish request on an id that cannot exist (so passing the level gate still changes nothing).
     */
    public function smoke() {
        $this->guard(); $suffix = bin2hex(random_bytes(3));
        $cases = array(
            array('tool' => 'altus_site_info', 'args' => array(), 'kind' => 'read'),
            array('tool' => 'altus_list', 'args' => array('type' => 'articles'), 'kind' => 'read'),
            array('tool' => 'altus_create', 'args' => array('type' => 'articles', 'data' => array('title_en' => 'MCP smoke test ' . $suffix, 'title_ar' => 'اختبار MCP ' . $suffix, 'slug_en' => 'mcp-smoke-' . $suffix, 'slug_ar' => 'mcp-smoke-ar-' . $suffix)), 'kind' => 'write'),
            array('tool' => 'altus_request_publish', 'args' => array('type' => 'articles', 'id' => 2147480000, 'operation' => 'publish'), 'kind' => 'full'),
        );
        $levels = array(); $rows = array(); $passed = 0; $total = 0; $t0 = microtime(true);
        foreach (array('read', 'write', 'full') as $level) {
            $init = $this->rpc(array('jsonrpc' => '2.0', 'id' => 'init', 'method' => 'initialize', 'params' => array('protocolVersion' => '2025-06-18', 'clientInfo' => array('name' => 'altus-console', 'version' => '1'))), $level, true);
            $list = $this->rpc(array('jsonrpc' => '2.0', 'id' => 'list', 'method' => 'tools/list', 'params' => array()), $level, true);
            $listed = array_column($list['response']['result']['tools'] ?? array(), 'name');
            $levels[$level] = array('initialized' => isset($init['response']['result']['protocolVersion']), 'tools' => count($listed), 'scopes' => $list['effective_scopes']);
            foreach ($cases as $c) {
                $r = $this->rpc(array('jsonrpc' => '2.0', 'id' => $c['tool'], 'method' => 'tools/call', 'params' => array('name' => $c['tool'], 'arguments' => $c['args'])), $level, true);
                $res = json_decode(json_encode($r['response']['result'] ?? null), true); $code = $res['structuredContent']['error']['code'] ?? (isset($r['response']['error']) ? 'rpc_error' : null);
                $outcome = !$res ? 'fail' : (empty($res['isError']) ? 'ok' : ($code === 'insufficient_scope' ? 'blocked' : 'allowed'));
                $required = $c['tool'] === 'altus_create' ? 'altus.content.write' : ($c['kind'] === 'full' ? 'altus.publish' : 'altus.read');
                $expect = in_array($required, $r['effective_scopes'], true) ? 'allowed' : 'blocked';
                $pass = $outcome !== 'fail' && (($expect === 'allowed') === ($outcome !== 'blocked')) && (in_array($c['tool'], $listed, true) === ($expect === 'allowed'));
                $total++; if ($pass) $passed++;
                $rows[] = array('level' => $level, 'tool' => $c['tool'], 'kind' => $c['kind'], 'expect' => $expect, 'outcome' => $outcome, 'code' => $code, 'listed' => in_array($c['tool'], $listed, true), 'pass' => $pass, 'ms' => $r['ms'],
                    'dry_run' => !empty($res['structuredContent']['dry_run']));
            }
        }
        return array('ok' => $passed === $total, 'passed' => $passed, 'total' => $total, 'levels' => $levels, 'rows' => $rows, 'ms' => (int) round((microtime(true) - $t0) * 1000));
    }
}
