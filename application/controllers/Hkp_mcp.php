<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Hkp_Controller.php';

/**
 * MCP & AI connections console (platform administrators).
 *
 *   GET  /hkp/mcp?tab=overview|levels|connections|console|activity|setup
 *   POST /hkp/mcp/action        settings, client/grant levels, revocations, personal tokens (form + CSRF)
 *   POST /hkp/mcp/rpc           in-browser tester: one JSON-RPC message as the signed-in admin (AJAX + CSRF)
 *   POST /hkp/mcp/smoke         read / write / full smoke suite, writes dry-run only (AJAX + CSRF)
 *   GET  /hkp/mcp/health        live metadata + 401-challenge checks (AJAX)
 *   GET  /hkp/mcp/call/{id}     one Activity row with its audit entries (AJAX)
 *
 * Gate: settings.update + platform (system) scope, checked here and again in Ha_mcp_console.
 */
class Hkp_mcp extends Hkp_Controller {
    const TABS = array('overview', 'levels', 'connections', 'console', 'activity', 'setup');

    private function gate() {
        $this->load->library(array('ha_mcp_oauth', 'ha_mcp_console'));
        $this->need('settings.update');
        if ($this->ha_mcp_console->can_manage()) return;
        if ($this->input->is_ajax_request() || $this->input->method() === 'post') { $this->json(array('ok' => false, 'error' => hkp_t('You do not have permission to do that.')), 403); $this->output->_display(); exit; }
        $this->output->set_status_header(403);
        $this->render('forbidden', array('permission' => 'settings.update (platform)'), hkp_t('Access denied'));
        $this->output->_display(); exit;
    }

    public function index($tab = null) {
        $this->gate();
        $tab = $tab ?: (string) $this->input->get('tab');
        $this->page(in_array($tab, self::TABS, true) ? $tab : 'overview');
    }

    private function page($tab, array $extra = array()) {
        $C = $this->ha_mcp_console; $O = $this->ha_mcp_oauth;
        $data = array('tab' => $tab, 'enabled' => $O->enabled(), 'endpoint' => $O->resource(), 'issuer' => $O->issuer(), 'ready' => $O->console_ready(),
            'installed' => $this->db->table_exists('ha_mcp_client'), 'csrf' => ha_csrf_token()) + $extra;
        if ($tab === 'overview') $data['o'] = $C->overview();
        if ($tab === 'levels' || $tab === 'console') $data['catalogue'] = $C->catalogue();
        if ($tab === 'levels') {
            $data['offer'] = array('default' => $O->setting('consent_default_level', 'write'), 'max' => $O->setting('consent_max_level', 'full'));
            $data['personal'] = $O->personal_tokens(); $data['users'] = $C->users();
        }
        if ($tab === 'connections') $data['c'] = $C->connections();
        if ($tab === 'activity') {
            $f = array(); foreach (array('tool', 'client', 'user', 'status', 'source', 'from', 'to') as $k) $f[$k] = mb_substr(trim((string) $this->input->get($k)), 0, 190);
            $data['filters'] = $f; $data['facets'] = $C->facets(); $data['log'] = $C->activity($f, (int) $this->input->get('page'));
        }
        $this->render('mcp_console', $data, hkp_t('MCP & AI connections'), 'mcp_console');
    }

    public function action() {
        $this->gate(); $this->post_guard();
        $O = $this->ha_mcp_oauth; $in = (array) $this->input->post(); $act = (string) ($in['action'] ?? '');
        $back = array('settings' => 'levels', 'pt_create' => 'levels', 'pt_revoke' => 'levels', 'client_level' => 'connections', 'client_revoke' => 'connections', 'grant_level' => 'connections', 'grant_revoke' => 'connections');
        if (!isset($back[$act])) { $this->back(hkp_t('Unknown action.'), false); return; }
        if ($act === 'pt_create') {
            // Rendered directly (no redirect) so the secret is shown exactly once and never stored in the session.
            try { $new = $O->create_personal_token((int) ($in['user_id'] ?? 0), (string) ($in['name'] ?? ''), (string) ($in['level'] ?? ''), (array) ($in['scopes'] ?? array()), (int) ($in['days'] ?? 30), $this->uid); }
            catch (RuntimeException $e) { $this->back($e->getMessage(), false, hkp_url('mcp') . '?tab=levels'); return; }
            catch (InvalidArgumentException $e) { $this->back($e->getMessage(), false, hkp_url('mcp') . '?tab=levels'); return; }
            $u = $this->db->select('email, first_name, last_name')->get_where('users', array('id' => $new['user_id']))->row_array();
            $new['email'] = $u['email'] ?? '';
            $this->page('levels', array('new_token' => $new)); return;
        }
        $this->attempt(function () use ($O, $in, $act) {
            switch ($act) {
                case 'settings': return $O->save_consent_levels((string) ($in['default_level'] ?? ''), (string) ($in['max_level'] ?? ''), $this->uid);
                case 'pt_revoke': if (!$O->revoke_personal_token((int) ($in['id'] ?? 0), $this->uid)) throw new InvalidArgumentException(hkp_t('That personal connection is already revoked.')); return true;
                case 'client_level': return $O->set_client_max_level((string) ($in['client_id'] ?? ''), (string) ($in['max_level'] ?? ''), $this->uid);
                case 'client_revoke': return $O->revoke_client((string) ($in['client_id'] ?? ''), $this->uid);
                case 'grant_level': return $O->set_grant_level((string) ($in['grant_id'] ?? ''), (string) ($in['level'] ?? ''), $this->uid);
                case 'grant_revoke': return $O->admin_revoke_grant((string) ($in['grant_id'] ?? ''), $this->uid);
            }
        }, hkp_t('MCP access updated.'), hkp_url('mcp') . '?tab=' . $back[$act]);
    }

    private function body() { $j = json_decode((string) $this->input->raw_input_stream, true); return is_array($j) ? $j : array(); }

    public function rpc() {
        $this->gate(); $this->post_guard();
        $b = $this->body();
        if (!isset($b['message']) || !is_array($b['message'])) { $this->json(array('ok' => false, 'error' => hkp_t('Send a JSON-RPC message.')), 422); return; }
        try { $this->json($this->ha_mcp_console->rpc($b['message'], (string) ($b['level'] ?? 'read'), !array_key_exists('dry_run', $b) || $b['dry_run'] !== false)); }
        catch (Throwable $e) { log_message('error', 'MCP console rpc: ' . $e->getMessage()); $this->json(array('ok' => false, 'error' => $e->getMessage()), 500); }
    }
    public function smoke() {
        $this->gate(); $this->post_guard();
        try { $this->json($this->ha_mcp_console->smoke()); }
        catch (Throwable $e) { log_message('error', 'MCP smoke: ' . $e->getMessage()); $this->json(array('ok' => false, 'error' => $e->getMessage()), 500); }
    }
    public function health() {
        $this->gate();
        $this->json($this->ha_mcp_console->health($this->input->get('http') !== '0'));
    }
    public function call($id = 0) {
        $this->gate();
        $r = $this->ha_mcp_console->call_detail((int) $id);
        $r ? $this->json(array('ok' => true, 'call' => $r)) : $this->json(array('ok' => false, 'error' => hkp_t('Not found.')), 404);
    }
}
