<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Password login with expiring account-bound tokens, single-use MFA challenges and
 * the existing new-device confirmation policy. Requires migration 34. */
class Ha_mobile_session {
    const SESSION_NAME = 'Mobile app session';
    const TTL_DAYS = 7;
    const CHALLENGE_SECONDS = 300;
    const MAX_FAILURES_PER_EMAIL = 5;
    const MAX_FAILURES_PER_IP = 20;
    const WINDOW_SECONDS = 900;
    private $CI;
    private $crypto;
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->library(array('ha_api_keys','ha_two_factor','ha_auth'));
        require_once APPPATH.'libraries/Ha_crypto.php';
        $this->crypto = new Ha_crypto();
    }
    public function login($email,$password,$ip,$device='') {
        $this->schema();
        $email=mb_strtolower(trim((string)$email)); $password=(string)$password;
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254 || $password==='' || strlen($password)>1024) {
            return $this->out(422,'Enter your email address and password.');
        }
        $api=$this->CI->ha_api_keys; $bucket='mobile_login_email:'.sha1($email);
        if ($this->throttled($bucket,$ip)) { return $this->out(429,'Too many sign-in attempts. Wait 15 minutes and try again.'); }
        $user=$this->CI->db->where('LOWER(email)',$email)->get('users')->row_array();
        $valid=$user && (preg_match('/^[a-f0-9]{40}$/i',(string)$user['password'])
            ? hash_equals(strtolower($user['password']),sha1($password)) : password_verify($password,$user['password']));
        if (!$valid || !$this->active($user)) {
            $api->attempt($bucket,false,$ip); $api->attempt('mobile_login_ip:'.$ip,false,$ip);
            return $this->out(401,'The email address or password is incorrect.');
        }
        // Never silently disable a configured browser captcha policy.
        $captcha=$this->CI->db->get_where('frontend_settings',array('key'=>'recaptcha_status'))->row('value');
        if ((int)$captcha===1) { return $this->out(403,'This platform requires browser verification. Use the secure website sign-in.'); }
        $this->CI->db->where('bucket',$bucket)->where('ok',0)->delete('ha_auth_attempt');
        return $this->next($user,$ip,$device,false,false);
    }
    /** Password-bound challenge; consumed once, including concurrent requests. */
    public function verify_two_factor($token,$code,$ip,$device='') {
        $this->schema();
        if ($this->throttled(null,$ip)) { return $this->out(429,'Too many sign-in attempts. Wait 15 minutes and try again.'); }
        if (!preg_match('/^[a-f0-9]{64}$/D',(string)$token)) { return $this->out(401,'The sign-in step expired. Sign in again.'); }
        $db=$this->CI->db; $db->trans_begin();
        try {
            $c=$db->query('SELECT * FROM ha_mobile_login_challenge WHERE token_hash=? FOR UPDATE',array($this->crypto->hmac($token)))->row_array();
            // Serialize second-factor verification across challenges for the account.
            $user=$c ? $db->query('SELECT * FROM users WHERE id=? FOR UPDATE',array((int)$c['user_id']))->row_array() : null;
            if (!$c || $c['used_at'] || strtotime($c['expires_at'])<=time() || $c['attempts']>=5 || !$this->active($user)
                || !hash_equals($c['credential_stamp'],$this->stamp($user))) {
                $db->trans_rollback(); return $this->out(401,'The sign-in step expired. Sign in again.');
            }
            $code=trim((string)$code);
            if ($c['stage']==='email') { $ok=preg_match('/^\d{6}$/D',$code) && hash_equals($c['code_hash'],$this->crypto->hmac('device:'.$code)); }
            else { $r=$this->CI->ha_two_factor->check($user['id'],$code,$ip); $ok=$r['ok']; }
            if (!$ok) {
                $db->where('id',$c['id'])->set('attempts','attempts + 1',false)->update('ha_mobile_login_challenge');
                $this->CI->ha_api_keys->attempt('mobile_login_ip:'.$ip,false,$ip);
                $db->trans_commit(); return $this->out(401,'That verification code is not valid. Please try again.');
            }
            $db->where('id',$c['id'])->update('ha_mobile_login_challenge',array('used_at'=>date('Y-m-d H:i:s')));
            $db->trans_commit();
            return $this->next($user,$ip,$device,$c['stage']==='email' || (bool)$c['device_verified'],$c['stage']==='authenticator',$c['credential_stamp']);
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    /** Password, email and confirmed MFA state are rechecked on every request. */
    public function valid(array $key,array $user) {
        if (!$this->CI->db->table_exists('ha_mobile_session')) { return false; }
        $s=$this->CI->db->get_where('ha_mobile_session',array('key_id'=>(int)$key['id'],'user_id'=>(int)$user['id']))->row_array();
        return $s && $this->active($user) && hash_equals($s['credential_stamp'],$this->stamp($user));
    }
    public function logout(array $key) { return $this->CI->ha_api_keys->revoke((int)$key['id'],(int)$key['user_id']); }
    public static function is_session_key(array $key) { return strpos((string)$key['name'],self::SESSION_NAME)===0; }
    private function next(array $user,$ip,$device,$device_verified,$factor_verified,$expected_stamp=null) {
        $expected_stamp=$expected_stamp ?: $this->stamp($user);
        if (!hash_equals($expected_stamp,$this->stamp($user))) { return $this->out(401,'The sign-in step expired. Sign in again.'); }
        if (!$device_verified && $this->device_challenge_needed($user)) { return $this->challenge($user,'email',false,$expected_stamp); }
        if (!$factor_verified && $this->CI->ha_two_factor->is_enabled($user['id'])) { return $this->challenge($user,'authenticator',$device_verified,$expected_stamp); }
        return $this->issue($user,$ip,$device,$device_verified,$factor_verified,$expected_stamp);
    }
    private function issue(array $user,$ip,$device,$device_verified,$factor_verified,$expected_stamp) {
        $db=$this->CI->db; $db->trans_begin();
        try {
            $current=$db->query('SELECT * FROM users WHERE id=? FOR UPDATE',array((int)$user['id']))->row_array();
            if (!$this->active($current) || !hash_equals($expected_stamp,$this->stamp($current))
                || ($this->CI->ha_two_factor->is_enabled($user['id']) && !$factor_verified)) {
                $db->trans_rollback(); return $this->out(401,'The sign-in step expired. Sign in again.');
            }
            if (!$device_verified && $this->device_challenge_needed($current)) {
                $db->trans_rollback(); return $this->challenge($current,'email',false,$expected_stamp);
            }
            $label=trim(preg_replace('/[^\p{L}\p{N} ._\-()]/u','',(string)$device));
            $name=self::SESSION_NAME.($label!=='' ? ' · '.mb_substr($label,0,60) : '');
            // Transport scopes enable native routes; every route checks live permissions.
            // No AI generation or job-authoring scope can be granted to a phone.
            $scopes=array('profile:read','courses:read','enrollments:read','performance:read','knowledge:read','team:read','kpis:read','mobile:write');
            $created=$this->CI->ha_api_keys->create((int)$user['id'],$name,$scopes,date('Y-m-d',strtotime('+'.self::TTL_DAYS.' days')));
            $expires=date('Y-m-d H:i:s',time()+self::TTL_DAYS*86400);
            $db->where('id',$created['id'])->update('ha_api_key',array('expires_at'=>$expires));
            $db->insert('ha_mobile_session',array('key_id'=>$created['id'],'user_id'=>$user['id'],'credential_stamp'=>$this->stamp($current),'created_at'=>date('Y-m-d H:i:s')));
            if ((int)$user['role_id']!==1) { $this->enforce_device_limit($current,$created['id']); }
            if (!$db->trans_status()) { throw new RuntimeException('Sign-in could not be completed.'); }
            $db->trans_commit();
            return $this->out(200,'Signed in.',array('two_factor_required'=>false,'token'=>$created['key'],'expires_at'=>date('c',strtotime($expires)),'scopes'=>$scopes));
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    private function challenge(array $user,$stage,$device_verified,$expected_stamp) {
        $bucket='mobile_challenge_user:'.(int)$user['id'];
        $recent=$this->CI->db->where('bucket',$bucket)->where('created_at >=',date('Y-m-d H:i:s',time()-self::CHALLENGE_SECONDS))->count_all_results('ha_auth_attempt');
        if($recent>=5){return $this->out(429,'Too many verification requests. Wait five minutes and sign in again.');}
        $this->CI->ha_api_keys->attempt($bucket,true);
        $token=bin2hex(random_bytes(32)); $code_hash=null;
        if ($stage==='email') {
            $code=(string)random_int(100000,999999);
            $this->send_device_code($user['id'],$code);
            $code_hash=$this->crypto->hmac('device:'.$code);
        }
        $this->CI->db->insert('ha_mobile_login_challenge',array('token_hash'=>$this->crypto->hmac($token),'user_id'=>$user['id'],
            'credential_stamp'=>$expected_stamp,'stage'=>$stage,'device_verified'=>$device_verified?1:0,'code_hash'=>$code_hash,
            'created_at'=>date('Y-m-d H:i:s'),'expires_at'=>date('Y-m-d H:i:s',time()+self::CHALLENGE_SECONDS)));
        return $this->out(200,$stage==='email'?'Enter the new-device code sent to your email.':'Enter your authenticator or recovery code.',
            array('two_factor_required'=>true,'challenge_type'=>$stage,'challenge'=>$token,'expires_in'=>self::CHALLENGE_SECONDS));
    }
    private function device_limit() {
        return max(1,(int)$this->CI->db->get_where('settings',array('key'=>'allowed_device_number_of_loging'))->row('value'));
    }
    /** Uses the platform's existing device alert template and notification preferences. */
    protected function send_device_code($user_id,$code) {
        $this->CI->load->model('email_model'); $this->CI->load->library('session');
        $this->CI->session->set_userdata('new_device_verification_code',$code);
        try { $this->CI->email_model->new_device_login_alert($user_id); }
        finally { $this->CI->session->unset_userdata(array('new_device_verification_code','new_device_code_expiration_time','new_device_user_email','new_device_user_id')); }
    }
    private function devices(array $user) {
        $web=array(); $ids=json_decode((string)$user['sessions'],true);
        if (is_array($ids) && $ids && $this->CI->db->table_exists('ci_sessions')) {
            $web=$this->CI->db->select('id')->where_in('id',$ids)->order_by('timestamp','DESC')->get('ci_sessions')->result_array();
        }
        $mobile=$this->CI->db->select('k.id')->from('ha_api_key k')->join('ha_mobile_session s','s.key_id=k.id')
            ->where('k.user_id',(int)$user['id'])->where('k.revoked_at IS NULL',null,false)->where('k.expires_at >',date('Y-m-d H:i:s'))
            ->order_by('k.id','DESC')->get()->result_array();
        return array($web,$mobile);
    }
    private function device_challenge_needed(array $user) {
        if ((int)$user['role_id']===1) { return false; }
        list($web,$mobile)=$this->devices($user);
        return count($web)+count($mobile)>=$this->device_limit();
    }
    private function enforce_device_limit(array $user,$keep_id) {
        list($web,$mobile)=$this->devices($user); $remaining=$this->device_limit()-1;
        foreach ($mobile as $row) {
            if ((int)$row['id']===(int)$keep_id) { continue; }
            if ($remaining>0) { $remaining--; continue; }
            $this->CI->ha_api_keys->revoke((int)$row['id'],(int)$user['id']);
        }
        $keep_web=array();
        foreach ($web as $row) {
            if ($remaining>0) { $remaining--; $keep_web[]=$row['id']; }
            else { $this->CI->db->where('id',$row['id'])->delete('ci_sessions'); }
        }
        $this->CI->db->where('id',$user['id'])->update('users',array('sessions'=>json_encode($keep_web)));
    }
    private function stamp(array $user) {
        $factor=$this->CI->db->get_where('ha_user_2fa',array('user_id'=>(int)$user['id']))->row_array();
        $binding=$factor && $factor['confirmed_at']!==null ? $factor['confirmed_at'].'|'.$factor['secret_cipher'] : 'off';
        $profile=$this->CI->db->select('organization_id,property_id,department_id')->get_where('ha_profile',array('user_id'=>(int)$user['id']))->row_array();
        $tenant=$profile ? implode(':',array_map('intval',array_values($profile))) : 'none';
        return $this->crypto->hmac('mobile:'.$user['id'].'|'.$user['email'].'|'.$user['password'].'|'.$binding.'|'.$tenant);
    }
    private function active($user) {
        if (!$user || (int)$user['status']!==1) { return false; }
        $status=$this->CI->db->get_where('ha_profile',array('user_id'=>(int)$user['id']))->row('status');
        return $status===null || $status==='active';
    }
    private function schema() {
        if (!$this->CI->db->table_exists('ha_mobile_session') || !$this->CI->db->table_exists('ha_mobile_login_challenge')) {
            throw new RuntimeException('Mobile sign-in is awaiting the server update. Contact your administrator.');
        }
    }
    private function throttled($email_bucket,$ip) {
        $api=$this->CI->ha_api_keys;
        return $api->throttled('mobile_login_ip:'.$ip,self::MAX_FAILURES_PER_IP,self::WINDOW_SECONDS)
            || ($email_bucket && $api->throttled($email_bucket,self::MAX_FAILURES_PER_EMAIL,self::WINDOW_SECONDS));
    }
    private function out($status,$message,$data=null) { return array('status'=>$status,'message'=>$message,'data'=>$data); }
}
