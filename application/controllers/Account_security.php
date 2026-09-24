<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Account security for every signed-in user: authenticator-app two-factor
 * login and personal API keys.
 *
 *   /account_security                    overview
 *   POST /account_security/twofa_begin   start enrolment (QR code)
 *   POST /account_security/twofa_confirm confirm with a code; shows recovery codes once
 *   POST /account_security/twofa_disable needs the password AND a current code
 *   POST /account_security/twofa_codes   new recovery codes (needs a current code)
 *   POST /account_security/key_create    shows the new key once
 *   POST /account_security/key_revoke
 *
 * Plaintext secrets (recovery codes, a new API key) are handed to the view
 * through flashdata exactly once and are never stored in a readable form.
 */
class Account_security extends CI_Controller {

    private $user;

    public function __construct() {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->load->helper('ha_security');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->output->set_header('X-Frame-Options: DENY');

        if (!$this->session->userdata('admin_login') && !$this->session->userdata('user_login')) {
            redirect(site_url('login'), 'refresh');
        }
        $this->user = $this->db->get_where('users', array('id' => (int) $this->session->userdata('user_id')))->row_array();
        if (!$this->user) {
            redirect(site_url('login'), 'refresh');
        }
        $this->load->library('ha_two_factor');
        $this->load->library('ha_api_keys');
        $this->load->library('ha_auth');
        $this->load->library('ha_audit');
    }

    private function can_keys() {
        return (int) $this->user['role_id'] === 1 || $this->ha_auth->has('api_keys.create');
    }

    private function guard() {
        if ($this->input->method() !== 'post' || !ha_csrf_valid()) {
            $this->session->set_flashdata('error_message', 'Your session expired. Please try again.');
            redirect(site_url('account_security'), 'refresh');
        }
    }

    private function done($message, $ok = true) {
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message', $message);
        redirect(site_url('account_security'), 'refresh');
    }

    private function audit($action, $description) {
        $this->ha_audit->log($action, 'users', $this->user['id'], array('user_id' => $this->user['id'], 'description' => $description));
    }

    public function index() {
        $uid = (int) $this->user['id'];
        $data = array(
            'page_title' => 'Account security',
            'twofa_on' => $this->ha_two_factor->is_enabled($uid),
            'twofa_pending' => $this->ha_two_factor->pending($uid, $this->user['email']),
            'recovery_left' => $this->ha_two_factor->remaining_codes($uid),
            'new_codes' => $this->session->flashdata('ha_new_codes'),
            'new_key' => $this->session->flashdata('ha_new_key'),
            'can_keys' => $this->can_keys(),
            'keys' => $this->can_keys() ? $this->ha_api_keys->for_user($uid) : array(),
            'scopes' => Ha_api_keys::scopes(),
            'account' => $this->user,
        );
        if ($this->session->userdata('admin_login') || (int) $this->user['is_instructor'] === 1) {
            $data['page_name'] = '../ha_security/panel';
            $this->load->view('backend/index', $data);
        } else {
            $data['page_name'] = 'account_security';
            $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $data);
        }
    }

    // ------------------------------------------------------------ 2FA

    public function twofa_begin() {
        $this->guard();
        try {
            $this->ha_two_factor->begin($this->user['id'], $this->user['email']);
            $this->done('Scan the QR code with your authenticator app, then enter the 6-digit code to finish.');
        } catch (Exception $e) {
            $this->done($e->getMessage(), false);
        }
    }

    public function twofa_confirm() {
        $this->guard();
        $codes = $this->ha_two_factor->confirm($this->user['id'], $this->input->post('code'));
        if (!$codes) {
            $this->done('That code did not match. Check the time on your phone is automatic and try the next code.', false);
        }
        $this->audit('update', 'Two-factor login enabled');
        $this->session->set_flashdata('ha_new_codes', $codes);
        // This session just proved the second factor; do not ask again today.
        $this->session->set_userdata('ha_2fa_verified', (int) $this->user['id']);
        $this->done('Two-factor login is on. Save your recovery codes now: they are shown only once.');
    }

    public function twofa_disable() {
        $this->guard();
        if (!hash_equals((string) $this->user['password'], sha1((string) $this->input->post('password')))) {
            $this->done('The password is not correct.', false);
        }
        $check = $this->ha_two_factor->check($this->user['id'], $this->input->post('code'), ha_client_ip());
        if (!$check['ok']) {
            $this->done($check['error'], false);
        }
        $this->ha_two_factor->disable($this->user['id']);
        $this->audit('update', 'Two-factor login disabled');
        $this->done('Two-factor login is off.');
    }

    public function twofa_cancel() {
        $this->guard();
        if (!$this->ha_two_factor->is_enabled($this->user['id'])) {
            $this->ha_two_factor->disable($this->user['id']);
        }
        $this->done('Set-up cancelled.');
    }

    public function twofa_codes() {
        $this->guard();
        $check = $this->ha_two_factor->check($this->user['id'], $this->input->post('code'), ha_client_ip());
        if (!$check['ok'] || $check['used_recovery']) {
            $this->done($check['ok'] ? 'Use a code from your app, not a recovery code, to generate new recovery codes.' : $check['error'], false);
        }
        $codes = $this->ha_two_factor->regenerate_recovery($this->user['id']);
        $this->audit('update', 'Two-factor recovery codes regenerated');
        $this->session->set_flashdata('ha_new_codes', $codes);
        $this->done('New recovery codes generated. The old ones no longer work.');
    }

    // ---------------------------------------------------------- API keys

    public function key_create() {
        $this->guard();
        if (!$this->can_keys()) {
            $this->done('Your role cannot create API keys.', false);
        }
        try {
            $active = $this->db->where('user_id', $this->user['id'])->where('revoked_at IS NULL', null, false)->count_all_results('ha_api_key');
            if ($active >= 10) {
                throw new InvalidArgumentException('You already have 10 active keys. Revoke one first.');
            }
            $key = $this->ha_api_keys->create($this->user['id'], $this->input->post('name'), (array) $this->input->post('scopes'),
                $this->input->post('expires_at') ?: null, $this->input->post('allowed_ips'));
            $this->audit('create', 'API key "' . mb_substr((string) $this->input->post('name'), 0, 80) . '" created');
            $this->session->set_flashdata('ha_new_key', $key['key']);
            $this->done('API key created. Copy it now: it will not be shown again.');
        } catch (Exception $e) {
            $this->done($e->getMessage(), false);
        }
    }

    public function key_revoke() {
        $this->guard();
        $id = (int) $this->input->post('key_id');
        if ($this->ha_api_keys->revoke($id, $this->user['id'], false)) {
            $this->audit('delete', 'API key #' . $id . ' revoked');
            $this->done('Key revoked. Requests using it now fail with 401.');
        }
        $this->done('That key was not found or is already revoked.', false);
    }
}
