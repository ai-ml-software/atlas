<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * /api/publisher/v1/{action} — native publishing API used only by the MCP gateway.
 * Success: {success:true,data,error:null,request_id}
 * Failure: {success:false,data:null,error:{code,message,details,retryable},request_id}
 * See docs/publisher-api.md for the status-code contract.
 */
class Publisher_api extends CI_Controller {
    const READ = array('health','list','get','job_status','media_list','approval_status','validate');
    private $request_id;
    private $type = null; private $id = 0;

    public function dispatch() {
        $this->load->database(); $this->load->helper(array('hkp','ha_security')); $this->load->library(array('ha_gateway','ha_auth','ha_publishing_service'));
        $rid=(string)$this->input->get_request_header('X-Request-Id',true); $this->request_id=preg_match('/^[A-Za-z0-9_.:-]{8,100}$/',$rid)?$rid:bin2hex(random_bytes(12));
        $this->output->set_content_type('application/json')->set_header('Cache-Control: no-store')->set_header('X-Request-Id: '.$this->request_id);
        try {
            $this->ha_gateway->require_enabled();
            $token=preg_replace('/^Bearer\s+/i','',(string)$this->input->get_request_header('Authorization',true));
            if ($token==='') throw new Ha_api_error(401,'missing_credential','A delegated gateway credential is required.');
            $path=preg_replace('~^api/publisher/v1/?~','',$this->uri->uri_string());
            $raw=(string)$this->input->raw_input_stream;
            if (strlen($raw)>22*1048576) throw new Ha_api_error(413,'payload_too_large','Request is too large.');
            if ($this->input->method()!=='post') throw new Ha_api_error(405,'method_not_allowed','Publisher operations require POST with a JSON body.');
            $in=$raw==='' ? array() : json_decode($raw,true);
            if (!is_array($in)) throw new Ha_api_error(422,'validation_failed','The request body must be a JSON object.');
            if ($path==='bridge') {
                $machine=$this->ha_gateway->verify($token,'altus-login-exchange');
                if (!hash_equals((string)($machine['body_hash']??''),hash('sha256',$raw))) throw new Ha_api_error(401,'invalid_credential','Bridge body mismatch.');
                $uid=$this->ha_gateway->exchange((string)($in['code']??''),(string)($in['binding']??''));
                $this->identify(array('verified'=>true,'sub'=>$uid));
                return $this->respond(array('user_id'=>$uid,'permissions'=>$this->ha_auth->permissions()));
            }
            $auth=$this->ha_gateway->authenticate($token); $this->identify($auth);
            if (isset($in['user_id']) || isset($in['organization_id']) || isset($in['property_id'])) throw new Ha_api_error(422,'validation_failed','Identity and tenant scope come from the authenticated grant.');
            $action=$path ?: 'health'; $type=$in['type']??'page'; $this->type=$type; $this->id=(int)($in['id']??0);
            $this->ha_gateway->seen($auth,$action);
            if (in_array($action,array('generate','translate','source_correction','import','job_status','job_control'),true) || ($type==='publisher' && !in_array($action,array('list','source','upload_document','health','validate'),true))) { $type='publisher';$this->type='publisher';$draft=$this->ha_document_publisher->find((int)($in['id']??0));$in['target']=$draft['target']; }
            if (in_array($action,array('source','upload_document'),true)) $type='publisher';
            $scope=in_array($action,self::READ,true)&&$action!=='validate'?'altus.read':(in_array($action,array('publish','request_publish','unarchive'),true)?'altus.publish':(($type==='courses' || ($type==='publisher' && !in_array($action,array('list','source','upload_document','health','validate'),true))) && ($in['target']??'course')==='course'?'altus.course.write':'altus.content.write'));
            if ($action==='media') $scope='altus.media.write';
            if ($action==='validate') $scope='altus.read';
            if (!in_array($scope,explode(' ',$auth['scope']??''),true)) throw new Ha_api_error(403,'insufficient_scope','Required OAuth scope: '.$scope,array('required_scope'=>$scope));
            if (in_array($action,self::READ,true)) return $this->respond($this->ha_publishing_service->execute($action,$in,$auth['client_id']));
            $key=(string)$this->input->get_request_header('Idempotency-Key',true); if (!preg_match('/^[a-zA-Z0-9_-]{8,100}$/',$key)) throw new Ha_api_error(422,'idempotency_key_required','Writes require an Idempotency-Key of 8–100 characters.');
            $hash=hash('sha256',$action.'\n'.$raw); $db=$this->db; $db->trans_begin();
            try {
                $db->query('INSERT IGNORE INTO ha_publisher_request (actor_id,client_id,request_key,request_hash,created_at) VALUES (?,?,?,?,?)',array($this->ha_auth->id(),$auth['client_id'],$key,$hash,date('Y-m-d H:i:s')));
                $r=$db->query('SELECT * FROM ha_publisher_request WHERE actor_id=? AND client_id=? AND request_key=? FOR UPDATE',array($this->ha_auth->id(),$auth['client_id'],$key))->row_array();
                if (!hash_equals($r['request_hash'],$hash)) throw new Ha_api_error(409,'idempotency_conflict','This idempotency key belongs to a different request.',array('request_key'=>$key));
                if ($r['response_json']) { $db->trans_commit(); $this->output->set_header('Idempotent-Replayed: true'); return $this->respond(json_decode($r['response_json'],true)); }
                $result=$this->ha_publishing_service->execute($action,$in,$auth['client_id']);
                $this->load->library('ha_audit'); $audit=$this->ha_audit->log('mcp.'.$action,'publisher_api',(int)($in['id']??0),array('description'=>'Authenticated MCP client '.$auth['client_id'].' · request '.$this->request_id));
                $result['audit_reference']=array('audit_id'=>$audit,'actor_id'=>$this->ha_auth->id(),'client_id'=>$auth['client_id'],'request_key'=>$key,'request_id'=>$this->request_id);
                $db->where(array('actor_id'=>$this->ha_auth->id(),'client_id'=>$auth['client_id'],'request_key'=>$key))->update('ha_publisher_request',array('response_json'=>json_encode($result,JSON_UNESCAPED_UNICODE)));
                if (!$db->trans_status()) throw new Ha_api_error(500,'internal_error','Operation failed and was rolled back.',array(),true);
                $db->trans_commit(); return $this->respond($result);
            } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
        } catch (Throwable $e) { $this->fail($e); }
    }
    /** Revoked or inactive users are authentication failures, not permission failures. */
    private function identify(array $claims) {
        try { $this->ha_auth->from_gateway($claims); } catch (Throwable $e) { throw new Ha_api_error(401,'user_revoked',$e->getMessage()); }
    }
    /** Exception → status code. Library exceptions keep their existing types. */
    private function fail(Throwable $e) {
        if ($e instanceof Ha_api_error) return $this->error($e->status,$e->error_code,$e->getMessage(),$e->details,$e->retryable);
        $m=$e->getMessage();
        if (preg_match('/not found/i',$m)) return $this->error(404,'not_found',$m);
        if ($e instanceof DomainException) return $this->error(409,'conflict',$m,$this->type?$this->ha_publishing_service->current($this->type,$this->id):array());
        if ($e instanceof InvalidArgumentException) return $this->error(422,'validation_failed',$m);
        if ($e instanceof RuntimeException && !($e instanceof mysqli_sql_exception)) return $this->error(403,'forbidden',$m);
        log_message('error','Publisher API '.$this->request_id.': '.$m);
        return $this->error(500,'internal_error','The request could not be completed.',array(),true);
    }
    private function error($status,$code,$message,array $details=array(),$retryable=false) {
        if ($status===401) $this->output->set_header('WWW-Authenticate: Bearer error="invalid_token"');
        if ($status===503) $retryable=false;
        $this->output->set_status_header($status)->set_output(json_encode(array('success'=>false,'data'=>null,'error'=>array('code'=>$code,'message'=>$message,'details'=>(object)$details,'retryable'=>(bool)$retryable),'request_id'=>$this->request_id),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    private function respond($data) { $this->output->set_status_header(200)->set_output(json_encode(array('success'=>true,'data'=>$data,'error'=>null,'request_id'=>$this->request_id),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
}
