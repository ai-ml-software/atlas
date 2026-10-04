<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Structured publisher API error: HTTP status, stable code, details and retry hint. */
if (!class_exists('Ha_api_error', false)) {
    class Ha_api_error extends RuntimeException {
        public $status; public $error_code; public $details; public $retryable;
        public function __construct($status, $code, $message, array $details = array(), $retryable = false) {
            parent::__construct($message); $this->status=(int)$status; $this->error_code=$code; $this->details=$details; $this->retryable=(bool)$retryable;
        }
    }
}

/**
 * Gateway signatures bind identity, audience, OAuth grant and a short expiry.
 * Keys: HS256 with a key id ("kid"). The current secret signs; the previous
 * secret (ALTUS_MCP_SECRET_PREVIOUS) still verifies during a rotation window.
 */
class Ha_gateway {
    /** Maximum lifetime per audience, seconds. Delegated native credentials are ≤60s. */
    const TTL = array('altus-native-publisher'=>60,'altus-login-exchange'=>60,'altus-login'=>300);
    private $CI;
    public function __construct() { $this->CI =& get_instance(); $this->CI->config->load('ha_publisher',true); }
    private function cfg($k) { return $this->CI->config->item($k,'ha_publisher'); }
    public function switched_on() { return (bool)$this->cfg('mcp_enabled'); }
    public function enabled() { return $this->switched_on() && strlen((string)$this->cfg('gateway_secret'))>=32; }
    /** Throws a 503 when the MCP feature switch is off. */
    public function require_enabled() {
        if (!$this->switched_on()) throw new Ha_api_error(503,'mcp_disabled','MCP access is switched off (ALTUS_MCP_ENABLED).');
        if (!$this->enabled()) throw new Ha_api_error(503,'mcp_not_configured','The MCP gateway secret is not configured.');
    }
    /** kid => secret for every key that may verify. */
    public function keys() {
        $keys=array((string)($this->cfg('gateway_key_id') ?: 'current')=>(string)$this->cfg('gateway_secret'));
        $prev=(string)$this->cfg('gateway_secret_previous');
        if (strlen($prev)>=32) $keys[(string)($this->cfg('gateway_key_id_previous') ?: 'previous')]=$prev;
        return $keys;
    }
    private function decode($v) { $r=base64_decode(strtr($v,'-_','+/'),true); if ($r===false) throw new Ha_api_error(401,'invalid_credential','Invalid gateway credential.'); return $r; }
    public function verify($token,$audience) {
        $this->require_enabled();
        $parts=explode('.',(string)$token); if (count($parts)!==3) throw new Ha_api_error(401,'invalid_credential','Invalid gateway credential.');
        $header=json_decode($this->decode($parts[0]),true); $claims=json_decode($this->decode($parts[1]),true);
        if (!is_array($header) || !is_array($claims) || ($header['alg']??'')!=='HS256') throw new Ha_api_error(401,'invalid_credential','Invalid gateway credential.');
        $keys=$this->keys(); $kid=$header['kid']??null;
        if ($kid!==null) { if (!isset($keys[$kid])) throw new Ha_api_error(401,'unknown_key','Gateway key id is not recognised; check key rotation.'); $candidates=array($keys[$kid]); }
        else $candidates=array_values($keys);
        $ok=false; foreach ($candidates as $secret) if (hash_equals(hash_hmac('sha256',$parts[0].'.'.$parts[1],$secret,true),$this->decode($parts[2]))) { $ok=true; break; }
        $max=self::TTL[$audience]??60; $now=time();
        if (!$ok || ($claims['aud']??'')!==$audience || ($claims['iss']??'')!==$this->cfg('gateway_url') || (int)($claims['exp']??0)<$now || (int)($claims['iat']??0)>$now+5 || (int)($claims['exp']??0)-(int)($claims['iat']??0)>$max) {
            throw new Ha_api_error(401,'invalid_credential','Expired or invalid gateway credential.');
        }
        return $claims;
    }
    public function authenticate($token) {
        $c=$this->verify($token,'altus-native-publisher');
        $db=$this->CI->db; $r=$db->get_where('ha_oauth_store',array('model'=>'AccessToken','id'=>$c['source_id']??''))->row_array();
        if (!$r || ($r['expires_at'] && (int)$r['expires_at']<time())) throw new Ha_api_error(401,'token_revoked','OAuth token expired or revoked.');
        $p=json_decode($r['payload'],true);
        $grant=$db->get_where('ha_oauth_store',array('model'=>'Grant','id'=>$p['grantId']??''))->row_array();
        if (!$grant || (string)($p['accountId']??'')!==(string)($c['sub']??'') || ($p['clientId']??'')!==($c['client_id']??'')) throw new Ha_api_error(401,'grant_revoked','OAuth grant is no longer valid.');
        $g=json_decode($grant['payload'],true); if (($g['accountId']??'')!==($p['accountId']??'') || ($g['clientId']??'')!==($p['clientId']??'')) throw new Ha_api_error(401,'grant_revoked','OAuth grant mismatch.');
        $requested=array_filter(explode(' ',trim($c['scope']??''))); $actual=explode(' ',trim($p['scope']??'')); if (array_diff($requested,$actual)) throw new Ha_api_error(403,'insufficient_scope','Delegated scopes exceed the OAuth grant.');
        $c['verified']=true; $c['grant_id']=(string)$p['grantId']; return $c;
    }
    /** Connection health: last request per OAuth grant. Never fails the request. */
    public function seen(array $auth,$action) {
        if (empty($auth['grant_id']) || !$this->CI->db->table_exists('ha_mcp_connection')) return;
        try { $this->CI->db->query('INSERT INTO ha_mcp_connection (grant_id,client_id,user_id,last_action,last_seen_at,requests) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE last_action=VALUES(last_action),last_seen_at=VALUES(last_seen_at),requests=requests+1',array($auth['grant_id'],(string)$auth['client_id'],(int)$auth['sub'],substr((string)$action,0,60),gmdate('Y-m-d H:i:s'))); } catch (Throwable $e) { log_message('error','MCP last-seen: '.$e->getMessage()); }
    }
    public function exchange($code,$binding) {
        $db=$this->CI->db; $db->trans_begin();
        try { $r=$db->query('SELECT * FROM ha_oauth_bridge WHERE code_hash=? FOR UPDATE',array(hash('sha256',$code)))->row_array();
            if (!$r || strtotime($r['expires_at'].' UTC')<time() || !hash_equals($r['binding_hash'],hash('sha256',$binding))) throw new Ha_api_error(401,'login_expired','Login approval expired or was already used.');
            $db->where('code_hash',$r['code_hash'])->delete('ha_oauth_bridge'); $db->trans_commit(); return (int)$r['user_id'];
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    /** Admin health probe of the gateway: /health and OAuth metadata. */
    public function probe() {
        $base=rtrim((string)$this->cfg('gateway_url'),'/'); $out=array('gateway_url'=>$base);
        foreach (array('health'=>'/health','protected_resource'=>'/.well-known/oauth-protected-resource/mcp','authorization_server'=>'/oauth/.well-known/openid-configuration') as $k=>$path) {
            $t=microtime(true); $ctx=stream_context_create(array('http'=>array('timeout'=>3,'ignore_errors'=>true)));
            $http_response_header=array(); $body=@file_get_contents($base.$path,false,$ctx); $status=0; foreach ((array)($http_response_header??array()) as $h) if (preg_match('~^HTTP/\S+\s+(\d+)~',$h,$m)) $status=(int)$m[1];
            $json=$body!==false?json_decode($body,true):null;
            $ok=$status===200 && is_array($json);
            if ($k==='protected_resource') $ok=$ok && ($json['resource']??'')===$base.'/mcp' && !empty($json['authorization_servers']);
            if ($k==='authorization_server') $ok=$ok && in_array('S256',(array)($json['code_challenge_methods_supported']??array()),true);
            $out[$k]=array('ok'=>$ok,'status'=>$status,'ms'=>(int)round((microtime(true)-$t)*1000));
        }
        return $out;
    }
}
