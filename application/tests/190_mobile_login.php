<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Native mobile email + password sign-in: session keys, throttling, 2FA, device limit, logout. */
require_once APPPATH . 'libraries/Ha_mobile_session.php';
/** Test delivery double: never sends mail to seeded addresses. */
class Mobile_login_fixture_session extends Ha_mobile_session {
    public $device_code;
    protected function send_device_code($user_id,$code) { $this->device_code=$code; }
}
class Test_mobile_login extends Ha_testcase {

    private $uid;
    const EMAIL = 'omar.learner@dyafagroup.sa';
    const PASSWORD = 'Mobile#2026';

    public function setUp() {
        $this->CI->load->library(array('ha_api_keys', 'ha_two_factor', 'ha_mobile_session', 'ha_auth'));
        $this->CI->ha_mobile_session = new Mobile_login_fixture_session();
        $this->uid = (int) $this->db->get_where('users', array('email' => self::EMAIL))->row('id');
        $this->db->where('id', $this->uid)->update('users', array('password' => sha1(self::PASSWORD), 'status' => 1));
        $this->db->where('user_id', $this->uid)->delete('ha_user_2fa');
        $this->db->where('user_id', $this->uid)->delete('ha_api_key');
        $this->db->where('user_id', $this->uid)->delete('ha_mobile_session');
        $this->db->where('user_id', $this->uid)->delete('ha_mobile_login_challenge');
        $this->db->where('id',$this->uid)->update('users',array('sessions'=>'[]'));
        $this->db->where('key','allowed_device_number_of_loging')->update('settings',array('value'=>'2'));
        $this->db->where('key','recaptcha_status')->update('frontend_settings',array('value'=>'0'));
        $this->db->empty_table('ha_auth_attempt');
    }

    private function login($password = self::PASSWORD, $email = self::EMAIL, $ip = '198.51.100.7') {
        return $this->CI->ha_mobile_session->login($email, $password, $ip, 'Pixel 8');
    }

    public function test_password_login_issues_a_scoped_session_key() {
        $r = $this->login();
        $this->assertEquals(200, $r['status']);
        $this->assertFalse($r['data']['two_factor_required']);
        $this->assertMatches('/^ha_[a-f0-9]{12}_[A-Za-z0-9]{40}$/', $r['data']['token']);
        $this->assertTrue(in_array('profile:read', $r['data']['scopes'], true));
        $this->assertFalse(in_array('ai:generate', $r['data']['scopes'], true), 'mobile token has no AI authoring scope');
        $auth = $this->CI->ha_api_keys->authenticate($r['data']['token'], '198.51.100.7');
        $this->assertTrue($auth['ok']);
        $this->assertEquals($this->uid, (int) $auth['user']['id']);
        $this->assertTrue(Ha_mobile_session::is_session_key($auth['key']));
        $this->assertNotNull($auth['key']['expires_at'], 'session keys expire');
        $this->assertNotContains(substr($r['data']['token'], 16), json_encode($auth['key']), 'secret not stored');
        $r2 = $this->login(strtoupper(self::PASSWORD) . 'x', strtoupper(self::EMAIL));
        $this->assertEquals(401, $r2['status']);
        $this->assertEquals(200, $this->login(self::PASSWORD, ' ' . strtoupper(self::EMAIL))['status'], 'email is case-insensitive');
    }

    public function test_wrong_password_inactive_and_validation() {
        $this->assertEquals(422, $this->login('', '')['status']);
        $this->assertEquals(401, $this->login('wrong')['status']);
        $this->assertEquals(401, $this->login(self::PASSWORD, 'nobody@example.invalid')['status']);
        $this->db->where('id', $this->uid)->update('users', array('status' => 0));
        $this->assertEquals(401, $this->login()['status'], 'inactive account refused');
        $this->db->where('id', $this->uid)->update('users', array('status' => 1));
    }

    public function test_failures_lock_the_account_for_fifteen_minutes() {
        for ($i = 0; $i < Ha_mobile_session::MAX_FAILURES_PER_EMAIL; $i++) { $this->login('wrong', self::EMAIL, '198.51.100.' . $i); }
        $r = $this->login();
        $this->assertEquals(429, $r['status'], 'right password refused while locked');
        $this->assertNull($r['data']);
    }

    public function test_two_factor_accounts_need_a_code() {
        $tf = $this->CI->ha_two_factor;
        $start = $tf->begin($this->uid, 'omar');
        $codes = $tf->confirm($this->uid, $tf->totp()->code($start['secret']));
        $r = $this->login();
        $this->assertEquals(200, $r['status']);
        $this->assertTrue($r['data']['two_factor_required']);
        $this->assertFalse(isset($r['data']['token']), 'no token before the second factor');
        $challenge = $r['data']['challenge'];
        $bad = $this->CI->ha_mobile_session->verify_two_factor($challenge, '000000', '198.51.100.7');
        $this->assertEquals(401, $bad['status']);
        $forged = preg_replace('/^\d+/', (string) ($this->uid + 1), $challenge);
        $this->assertEquals(401, $this->CI->ha_mobile_session->verify_two_factor($forged, $codes[1], '198.51.100.7')['status'], 'tampered challenge');
        $ok = $this->CI->ha_mobile_session->verify_two_factor($challenge, $codes[0], '198.51.100.7');
        $this->assertEquals(200, $ok['status']);
        $this->assertMatches('/^ha_/', $ok['data']['token']);
        $this->assertEquals(401,$this->CI->ha_mobile_session->verify_two_factor($challenge,$codes[1],'198.51.100.7')['status'],'challenge cannot be replayed with another recovery code');
    }

    public function test_device_limit_revokes_the_oldest_session_and_logout_revokes() {
        $limit = (int) $this->db->get_where('settings', array('key' => 'allowed_device_number_of_loging'))->row('value');
        if ($limit < 1) { $limit = 5; $this->db->replace('settings', array('key' => 'allowed_device_number_of_loging', 'value' => '5')); }
        $tokens = array();
        for ($i = 0; $i <= $limit; $i++) {
            $r=$this->login();
            if ($i===$limit) {
                $this->assertTrue($r['data']['two_factor_required'],'new device must be confirmed');
                $this->assertEquals('email',$r['data']['challenge_type']);
                $this->assertFalse(isset($r['data']['token']),'no token before confirmation');
                $this->assertTrue($this->CI->ha_api_keys->authenticate($tokens[0],'198.51.100.7')['ok'],'old phone is retained until confirmation');
                $r=$this->CI->ha_mobile_session->verify_two_factor($r['data']['challenge'],$this->CI->ha_mobile_session->device_code,'198.51.100.7');
            }
            $tokens[]=$r['data']['token'];
        }
        $active = $this->db->where('user_id', $this->uid)->where('revoked_at IS NULL', null, false)->count_all_results('ha_api_key');
        $this->assertEquals($limit, $active);
        $this->assertFalse($this->CI->ha_api_keys->authenticate($tokens[0], '198.51.100.7')['ok'], 'oldest session revoked');
        $last = $this->CI->ha_api_keys->authenticate($tokens[$limit], '198.51.100.7');
        $this->assertTrue($last['ok']);
        $this->assertTrue($this->CI->ha_mobile_session->logout($last['key']));
        $this->assertFalse($this->CI->ha_api_keys->authenticate($tokens[$limit], '198.51.100.7')['ok'], 'logout revokes the session key');
    }

    public function test_controller_route_uses_the_same_rules() {
        require_once APPPATH . 'controllers/Mobile_api.php';
        $ref = new ReflectionClass('Mobile_api'); $c = $ref->newInstanceWithoutConstructor();
        foreach (array('db', 'load') as $p) { $c->$p = $this->CI->$p; }
        $c->ha_mobile_session = $this->CI->ha_mobile_session;
        $r = $c->sign_in('login', array('email' => self::EMAIL, 'password' => self::PASSWORD, 'device' => '<b>Phone</b>'), '198.51.100.8');
        $this->assertEquals(200, $r['status']);
        $row = $this->db->where('user_id', $this->uid)->order_by('id', 'DESC')->get('ha_api_key')->row_array();
        $this->assertNotContains('<', $row['name'], 'device label sanitised');
        $this->assertEquals(401, $c->sign_in('login_2fa', array('challenge' => 'x', 'code' => '1'), '198.51.100.8')['status']);
    }

    public function test_password_changes_and_profile_suspension_invalidate_sessions() {
        $token=$this->login()['data']['token'];
        $this->db->where('id',$this->uid)->update('users',array('password'=>sha1('Changed#2026')));
        $this->assertFalse($this->CI->ha_api_keys->authenticate($token,'198.51.100.7')['ok'],'password change revokes mobile session');
        $this->db->where('id',$this->uid)->update('users',array('password'=>sha1(self::PASSWORD)));
        $token=$this->login()['data']['token'];
        $this->db->where('user_id',$this->uid)->update('ha_profile',array('status'=>'suspended'));
        $this->assertFalse($this->CI->ha_api_keys->authenticate($token,'198.51.100.7')['ok']);
        $this->db->where('user_id',$this->uid)->update('ha_profile',array('status'=>'active'));
    }

    public function test_mfa_changes_and_expired_challenges_require_password_again() {
        $token=$this->login()['data']['token'];
        $tf=$this->CI->ha_two_factor;
        $start=$tf->begin($this->uid,'omar'); $codes=$tf->confirm($this->uid,$tf->totp()->code($start['secret']));
        $this->assertFalse($this->CI->ha_api_keys->authenticate($token,'198.51.100.7')['ok'],'MFA enablement invalidates existing mobile session');
        $r=$this->login();
        $this->db->where('user_id',$this->uid)->update('ha_mobile_login_challenge',array('expires_at'=>date('Y-m-d H:i:s',time()-1)));
        $this->assertEquals(401,$this->CI->ha_mobile_session->verify_two_factor($r['data']['challenge'],$codes[0],'198.51.100.7')['status']);
        $r=$this->login();
        $this->db->where('id',$this->uid)->update('users',array('password'=>sha1('Changed#2026')));
        $this->assertEquals(401,$this->CI->ha_mobile_session->verify_two_factor($r['data']['challenge'],$codes[0],'198.51.100.7')['status'],'password change invalidates pending challenge');
    }

    public function test_tenant_context_changes_invalidate_the_mobile_session() {
        $token=$this->login()['data']['token'];
        $old=(int)$this->db->get_where('ha_profile',array('user_id'=>$this->uid))->row('property_id');
        $other=(int)$this->db->select('id')->where('id !=',$old)->get('ha_property')->row('id');
        $this->assertTrue($other>0,'second property fixture exists');
        $this->db->where('user_id',$this->uid)->update('ha_profile',array('property_id'=>$other));
        $this->assertFalse($this->CI->ha_api_keys->authenticate($token,'198.51.100.7')['ok'],'moving tenants revokes the previous mobile session');
        $this->db->where('user_id',$this->uid)->update('ha_profile',array('property_id'=>$old));
    }

    public function test_device_confirmation_is_followed_by_mfa_and_wrong_codes_are_bounded() {
        $this->db->where('key','allowed_device_number_of_loging')->update('settings',array('value'=>'1'));
        $this->login();
        $tf=$this->CI->ha_two_factor;
        $start=$tf->begin($this->uid,'omar'); $codes=$tf->confirm($this->uid,$tf->totp()->code($start['secret']));
        $r=$this->login();
        $this->assertEquals('email',$r['data']['challenge_type']);
        $r=$this->CI->ha_mobile_session->verify_two_factor($r['data']['challenge'],$this->CI->ha_mobile_session->device_code,'198.51.100.7');
        $this->assertEquals('authenticator',$r['data']['challenge_type']);
        $this->assertFalse(isset($r['data']['token']));
        $ok=$this->CI->ha_mobile_session->verify_two_factor($r['data']['challenge'],$codes[0],'198.51.100.7');
        $this->assertEquals(200,$ok['status']);
        $r=$this->login();
        for ($i=0;$i<5;$i++) { $this->CI->ha_mobile_session->verify_two_factor($r['data']['challenge'],'000000','198.51.100.7'); }
        $this->assertEquals(401,$this->CI->ha_mobile_session->verify_two_factor($r['data']['challenge'],$this->CI->ha_mobile_session->device_code,'198.51.100.7')['status'],'locked challenge rejects even correct code');
    }
}
