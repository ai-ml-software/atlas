<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Ha_gateway.php';
/** Native operations. Both MCP and browser approvals call these same services. */
class Ha_publishing_service {
    private $CI;
    public function __construct() { $this->CI =& get_instance(); $this->CI->load->library(array('ha_auth','ha_audit','ha_website_studio','ha_content_studio','ha_document_publisher','ha_document_jobs')); }
    private static function structure($type) { return in_array($type,array('course_sections','lessons','quizzes','questions'),true); }
    private function mcp_content() { $this->CI->load->library('ha_mcp_content'); return $this->CI->ha_mcp_content; }
    public function state($type,$id) {
        if (self::structure($type)) { return $this->mcp_content()->state($type,$id); }
        if ($type==='page') { return $this->CI->ha_website_studio->state($id); }
        if ($type==='publisher') { $d=$this->CI->ha_document_publisher->find($id); return array('payload'=>json_decode($d['payload_json'],true),'source'=>$d['source_text'],'target'=>$d['target'],'locale'=>$d['locale'],'version'=>(int)$d['version'],'status'=>$d['status'],'entity_id'=>$d['entity_id']); }
        return $this->CI->ha_content_studio->state($type,$id);
    }
    public function digest($type,$id) { $s=$this->state($type,$id); return hash('sha256',json_encode(array($s['payload'],$s['version'],$s['base_hash']??null),JSON_UNESCAPED_UNICODE)); }
    public function describe($type,$id) {
        if (self::structure($type)) { return $this->mcp_content()->describe($type,$id)+array('hash'=>$this->digest($type,$id)); }
        $s=$this->state($type,$id); $url=$type==='page'?hkp_url('cms/live/'.$id):($type==='publisher'?hkp_url('cms/publisher/'.$id):($type==='site'?hkp_url('cms/theme'):($type==='navigation'?hkp_url('cms/navigation').'?menu='.$id:hkp_url('cms/catalogue/'.$type.'/'.$id))));
        $status=$type==='publisher'?($s['status']??'draft'):(!empty($s['version'])?'draft':($s['published']['status']??$s['page']['status']??'published'));
        $preview=$type==='page'?hkp_url('cms/preview/'.$id):$url;
        if (isset(Ha_studio_catalogue::types()[$type])) {
            $url=hkp_url('cms/catalogue_live/'.$type.'/'.$id); $preview=hkp_url('cms/entity_preview/'.$type.'/'.$id);
            if (($s['published']['status']??'')==='published') {
                $this->CI->load->library('ha_studio_preview'); $def=Ha_studio_catalogue::types()[$type];
                $preview=$this->CI->ha_studio_preview->sign_url(base_url('en/'.$def['route'].'/'.$s['published']['slug_en']).'?studio_entity_preview='.$type.':'.(int)$id,$type,$id);
            }
        }
        return array('object_id'=>(int)$id,'object_type'=>$type,'status'=>$status,'version'=>$s['version'],'hash'=>$this->digest($type,$id),'state'=>$s,'edit_url'=>$url,'preview_url'=>$preview,'warnings'=>array('Changes require human review before publication.'));
    }
    /** Current object metadata for conflict responses; never throws. */
    public function current($type,$id) {
        try { $s=$this->state($type,$id); return array('object_type'=>$type,'object_id'=>(int)$id,'current_version'=>(int)$s['version'],'current_base_hash'=>$s['base_hash']??null,'current_hash'=>$this->digest($type,$id)); } catch (Throwable $e) { return array('object_type'=>$type,'object_id'=>(int)$id); }
    }
    private function conflict($message,array $a) { return new Ha_api_error(409,'conflict',$message,array('approval_id'=>(int)$a['id'],'approved_version'=>isset($a['object_version'])?(int)$a['object_version']:null)+$this->current($a['object_type'],$a['object_id'])); }
    private static function utc($datetime) { return gmdate('Y-m-d\TH:i:s\Z',strtotime($datetime.' UTC')); }
    private static function expired(array $a) { return strtotime($a['expires_at'].' UTC')<time(); }
    /** Types that may be published through an MCP approval. SOPs keep their own governance workflow. */
    private function approvable($type) { return in_array($type,array('page','site','navigation','lessons','quizzes'),true) || isset(Ha_studio_catalogue::types()[$type]); }
    public function request_approval($type,$id,$operation,$client) {
        if (in_array($type,array('sop','sops'),true)) throw new Ha_api_error(422,'sop_governance_required','SOP documents are published only through the existing SOP approval workflow.');
        if (!in_array($operation,array('publish','archive'),true) || !$this->approvable($type)) throw new Ha_api_error(422,'validation_failed','Unsupported approval operation.',array('operation'=>$operation,'type'=>$type));
        $s=$this->state($type,$id); if ($operation==='publish' && !$s['version']) throw new Ha_api_error(422,'validation_failed','Save a draft before requesting publication.');
        $ttl=(int)($this->CI->config->item('approval_ttl','ha_publisher') ?: 600); $expires=time()+$ttl;
        $row=array('object_type'=>$type,'object_id'=>(int)$id,'operation'=>$operation,'requester_id'=>$this->CI->ha_auth->id(),'client_id'=>$client,'content_hash'=>$this->digest($type,$id),'expires_at'=>gmdate('Y-m-d H:i:s',$expires),'created_at'=>gmdate('Y-m-d H:i:s'));
        if ($this->CI->db->field_exists('object_version','ha_publisher_approval')) $row['object_version']=(int)$s['version'];
        $this->CI->db->insert('ha_publisher_approval',$row);
        $approval=$this->CI->db->insert_id(); $this->CI->ha_audit->log('create','publisher_approval',$approval,array('description'=>'MCP '.$operation.' requested for '.$type.' '.$id));
        return array('approval_id'=>$approval,'status'=>'pending','object_type'=>$type,'object_id'=>(int)$id,'operation'=>$operation,'version'=>(int)$s['version'],'expires_at'=>gmdate('Y-m-d\TH:i:s\Z',$expires),'review_url'=>hkp_url('cms/integrations').'?approval='.$approval);
    }
    private function publish_permission($type) {
        if (in_array($type,array('lessons','quizzes'),true)) { $this->mcp_content()->publish_permission($type); return; }
        if ($type==='page') { if (!$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has('cms_pages.publish')) throw new RuntimeException('Website publication permission required.'); }
        else { $this->CI->ha_content_studio->authorize($type,true); }
    }
    public function review($id,$approve) {
        $db=$this->CI->db; $db->trans_begin();
        try { $a=$db->query('SELECT * FROM ha_publisher_approval WHERE id=? FOR UPDATE',array((int)$id))->row_array();
            if (!$a) throw new Ha_api_error(404,'not_found','Approval not found.');
            if ($a['status']!=='pending' || self::expired($a)) throw new Ha_api_error(409,'approval_unavailable','Approval expired or was already reviewed.',array('approval_id'=>(int)$id,'status'=>self::expired($a)&&$a['status']==='pending'?'expired':$a['status'],'expires_at'=>self::utc($a['expires_at'])));
            if ((int)$a['requester_id']===(int)$this->CI->ha_auth->id() && !$this->CI->config->item('allow_self_approval','ha_publisher')) throw new Ha_api_error(403,'self_approval_forbidden','Separation of duties: another administrator must review a request you made.');
            $this->publish_permission($a['object_type']);
            if ($approve && !hash_equals($a['content_hash'],$this->digest($a['object_type'],$a['object_id']))) throw $this->conflict('Content changed. Request a new approval.',$a);
            $set=array('status'=>$approve?'approved':'declined','reviewed_by'=>$this->CI->ha_auth->id()); if ($db->field_exists('reviewed_at','ha_publisher_approval')) $set['reviewed_at']=gmdate('Y-m-d H:i:s');
            $db->where('id',$id)->update('ha_publisher_approval',$set); $this->CI->ha_audit->log('update','publisher_approval',$id,array('description'=>$approve?'Human approved MCP request':'Human declined MCP request')); $db->trans_commit();
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    /** Single-use: executes the approved operation for the same client, user, operation, object and version. */
    public function approved_operation($id,$client,array $expect=array()) {
        $db=$this->CI->db; $db->trans_begin();
        try { $a=$db->query('SELECT * FROM ha_publisher_approval WHERE id=? FOR UPDATE',array((int)$id))->row_array();
            if (!$a || (int)$a['requester_id']!==(int)$this->CI->ha_auth->id() || !hash_equals($a['client_id'],(string)$client)) throw new Ha_api_error(404,'not_found','No approval for this user and client.');
            if ($a['status']==='consumed') throw new Ha_api_error(409,'approval_consumed','This approval was already used. Approvals are single-use.',array('approval_id'=>(int)$id));
            if ($a['status']!=='approved' || self::expired($a)) throw new Ha_api_error(self::expired($a)?409:403,self::expired($a)?'approval_expired':'approval_required','A current human approval is required.',array('approval_id'=>(int)$id,'status'=>self::expired($a)?'expired':$a['status'],'expires_at'=>self::utc($a['expires_at'])));
            foreach (array('type'=>'object_type','id'=>'object_id','operation'=>'operation') as $k=>$col) if (isset($expect[$k]) && (string)$expect[$k]!==(string)$a[$col]) throw new Ha_api_error(409,'approval_mismatch','The approval covers a different '.$k.'.',array('approval_id'=>(int)$id,$k=>$a[$col]));
            $this->apply($a,$expect);
            $db->trans_commit(); return $this->describe($a['object_type'],$a['object_id']);
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    /** Runs a locked, approved request (publish or archive) and marks it consumed. Caller owns the transaction. */
    private function apply(array $a,array $expect=array()) {
        $db=$this->CI->db; $id=(int)$a['id'];
        $this->publish_permission($a['object_type']); $s=$this->state($a['object_type'],$a['object_id']);
        if ((isset($a['object_version']) && $a['object_version']!==null && (int)$a['object_version']!==(int)$s['version']) || (isset($expect['version']) && (int)$expect['version']!==(int)$s['version']) || !hash_equals($a['content_hash'],$this->digest($a['object_type'],$a['object_id']))) throw $this->conflict('The approved content changed. Request a new approval.',$a);
        try { if ($a['operation']==='archive') { $this->archive($a['object_type'],$a['object_id']); }
            elseif (in_array($a['object_type'],array('lessons','quizzes'),true)) { $this->mcp_content()->set_status($a['object_type'],$a['object_id'],'published'); }
            elseif ($a['object_type']==='page') { $this->CI->ha_website_studio->publish($a['object_id'],$s['version']); }
            else { $this->CI->ha_content_studio->publish($a['object_type'],$a['object_id'],$s['version']); } }
        catch (DomainException $e) { throw $this->conflict($e->getMessage(),$a); }
        $set=array('status'=>'consumed'); if ($db->field_exists('consumed_at','ha_publisher_approval')) $set['consumed_at']=gmdate('Y-m-d H:i:s');
        $db->where(array('id'=>$id,'status'=>'approved'))->update('ha_publisher_approval',$set); if ($db->affected_rows()!==1) throw new Ha_api_error(409,'approval_consumed','This approval was already used.');
    }
    /** Admin "Approve": human review and publication in one step, so the change is live immediately. */
    public function approve_and_publish($id) {
        $this->review($id,true);
        $db=$this->CI->db; $db->trans_begin();
        try { $a=$db->query('SELECT * FROM ha_publisher_approval WHERE id=? FOR UPDATE',array((int)$id))->row_array();
            if (!$a || $a['status']!=='approved') throw new Ha_api_error(409,'approval_unavailable','Approval was not recorded.');
            $this->apply($a);
            $this->CI->ha_audit->log('publish',$a['object_type'],(int)$a['object_id'],array('description'=>'Published on approval of MCP request #'.(int)$id));
            $db->trans_commit(); return $this->describe($a['object_type'],$a['object_id']);
        } catch (Throwable $e) { $db->trans_rollback(); throw $e; }
    }
    /** Archived content back to draft status (never deletes). */
    public function unarchive($type,$id) {
        if ($type==='page') { if (!$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has('cms_pages.publish')) throw new Ha_api_error(403,'forbidden','Website publication permission required.');
            $p=$this->CI->db->get_where('ha_page',array('id'=>(int)$id))->row_array(); if (!$p) throw new Ha_api_error(404,'not_found','Page not found.'); if ($p['status']!=='archived') throw new Ha_api_error(409,'conflict','Only archived content can be restored.',array('status'=>$p['status']));
            $this->CI->db->where('id',(int)$id)->update('ha_page',array('status'=>'draft')); }
        elseif (isset(Ha_studio_catalogue::types()[$type])) { $this->CI->ha_content_studio->authorize($type,true); $p=$this->CI->ha_content_studio->snapshot($type,$id); if (($p['status']??'')!=='archived') throw new Ha_api_error(409,'conflict','Only archived content can be restored.',array('status'=>$p['status']??null)); $p['status']='draft'; $this->CI->ha_studio_catalogue->save($type,$id,$p); }
        elseif (in_array($type,array('lessons','quizzes'),true)) { $this->mcp_content()->publish_permission($type); $st=$this->mcp_content()->state($type,$id); if (($st['payload']['status']??'')!=='archived') throw new Ha_api_error(409,'conflict','Only archived content can be restored.',array('status'=>$st['payload']['status']??null)); $this->mcp_content()->set_status($type,$id,'draft'); }
        else throw new Ha_api_error(422,'validation_failed','This object cannot be restored from the archive.');
        $this->CI->ha_audit->log('restore',$type,$id,array('description'=>'Restored from archive to draft'));
        return array('object_type'=>$type,'object_id'=>(int)$id,'status'=>'draft');
    }
    private function archive($type,$id) {
        if ($type==='page') { $p=$this->CI->db->get_where('ha_page',array('id'=>$id))->row_array(); if ($p['is_system']) throw new InvalidArgumentException('Required landing pages cannot be archived.'); $this->CI->ha_page_builder->revision($id,$this->CI->ha_auth->id(),'Before approved archive'); $this->CI->db->where('id',$id)->update('ha_page',array('status'=>'archived')); }
        elseif (isset(Ha_studio_catalogue::types()[$type])) { $p=$this->CI->ha_content_studio->snapshot($type,$id); $p['status']='archived'; $this->CI->ha_studio_catalogue->save($type,$id,$p); }
        elseif (in_array($type,array('lessons','quizzes'),true)) { $this->mcp_content()->set_status($type,$id,'archived'); }
        else throw new InvalidArgumentException('This object cannot be archived.');
        $this->CI->ha_audit->log('archive',$type,$id,array('description'=>'Approved archive operation'));
    }
    // ------------------------------------------------------------ admin screens
    const ADMIN_TABS = array('approvals','connections','health','audit','archive');
    /** Read-only data for the Integrations screens; every tab is permission-gated. */
    public function admin_view(array $get) {
        $this->CI->load->library('ha_gateway'); $A=$this->CI->ha_auth; $db=$this->CI->db; $G=$this->CI->ha_gateway;
        $tab=in_array($get['tab']??'',self::ADMIN_TABS,true)?$get['tab']:'approvals';
        $can=array('publish'=>$A->has('cms_pages.publish'),'audit'=>$A->has('audit_logs.view'));
        if ($tab==='audit' && !$can['audit']) throw new Ha_api_error(403,'forbidden','Audit history requires the audit_logs.view permission.');
        $v=array('tab'=>$tab,'can'=>$can,'enabled'=>$G->enabled(),'switched_on'=>$G->switched_on(),'gateway'=>$this->CI->config->item('gateway_url','ha_publisher'),'self_approval'=>(bool)$this->CI->config->item('allow_self_approval','ha_publisher'),'viewer_id'=>(int)$A->id());
        if ($tab==='approvals') {
            $status=in_array($get['status']??'',array('pending','approved','declined','consumed','all'),true)?$get['status']:'pending';
            if ($status!=='all') $db->where('status',$status);
            $v['status']=$status; $v['approvals']=$db->order_by('id','DESC')->limit(50)->get('ha_publisher_approval')->result_array();
            $v['detail']=null; $sel=(int)($get['approval']??0);
            if ($sel) { $a=$db->get_where('ha_publisher_approval',array('id'=>$sel))->row_array(); if ($a) { try { $v['detail']=array('approval'=>$a,'content'=>$this->describe($a['object_type'],$a['object_id'])); } catch (Throwable $e) { $v['detail']=array('approval'=>$a,'error'=>$e->getMessage()); } } }
        }
        if ($tab==='connections') {
            $grants=$db->where('model','Grant')->order_by('id')->limit(100)->get('ha_oauth_store')->result_array();
            if (!$can['audit']) $grants=array_values(array_filter($grants,function($r)use($v){$p=json_decode($r['payload'],true);return (int)($p['accountId']??0)===$v['viewer_id'];}));
            $seen=array(); if ($db->table_exists('ha_mcp_connection') && $grants) foreach ($db->where_in('grant_id',array_column($grants,'id'))->get('ha_mcp_connection')->result_array() as $s) $seen[$s['grant_id']]=$s;
            $v['grants']=$grants; $v['seen']=$seen;
        }
        // Native PHP MCP server: endpoint, clients and grants (always shown), self-check on the health tab.
        $this->CI->load->library('ha_mcp_oauth'); $O=$this->CI->ha_mcp_oauth;
        $v['php']=array('enabled'=>$O->enabled(),'endpoint'=>$O->resource(),'issuer'=>$O->issuer(),'installed'=>$db->table_exists('ha_mcp_grant'),'grants'=>array(),'clients'=>array(),'check'=>null);
        if ($v['php']['installed']) { $v['php']['grants']=$O->grants($can['audit']?null:(int)$A->id()); if ($can['audit']) $v['php']['clients']=$O->clients(); }
        if ($tab==='health' && $v['php']['installed'] && $O->enabled()) $v['php']['check']=$O->self_check();
        if ($tab==='health') { $v['keys']=array_keys($G->keys()); $v['probe']=$v['enabled']?$G->probe():null; $v['recent']=$db->table_exists('ha_mcp_connection')?$db->order_by('last_seen_at','DESC')->limit(10)->get('ha_mcp_connection')->result_array():array(); }
        if ($tab==='audit') {
            $f=array('action'=>preg_replace('/[^a-z0-9_.]/','',strtolower((string)($get['action']??''))),'user'=>(int)($get['user']??0),'from'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',$get['from']??'')?$get['from']:'','to'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',$get['to']??'')?$get['to']:'','q'=>mb_substr((string)($get['q']??''),0,100));
            $page=max(1,(int)($get['page']??1)); $per=25;
            $scope=function() use ($db,$f) { $db->from('ha_audit_log')->group_start()->like('action','mcp.','after')->or_like('action','oauth.','after')->or_where('entity_type','publisher_approval')->group_end();
                if ($f['action']) $db->where('action',$f['action']); if ($f['user']) $db->where('user_id',$f['user']); if ($f['from']) $db->where('created_at >=',$f['from'].' 00:00:00'); if ($f['to']) $db->where('created_at <=',$f['to'].' 23:59:59'); if ($f['q']!=='') $db->like('description',$f['q']); };
            $scope(); $total=$db->count_all_results(); $scope();
            $v['audit']=$db->select('id,created_at,user_id,actor_name,action,entity_type,entity_id,description,ip_address')->order_by('id','DESC')->limit($per,($page-1)*$per)->get()->result_array();
            $v['filters']=$f; $v['page']=$page; $v['pages']=max(1,(int)ceil($total/$per)); $v['total']=$total;
        }
        if ($tab==='archive') $v['archived']=$db->select('id,code,slug_en,status')->where('status','archived')->order_by('id')->limit(100)->get('ha_page')->result_array();
        return $v;
    }
    /** Mutations from the Integrations screens: review, revoke, restore from archive. No deletes of content. */
    public function admin_post(array $post) {
        $A=$this->CI->ha_auth; $db=$this->CI->db; $action=(string)($post['action']??'review');
        if ($action==='revoke') {
            if (!$A->has('cms_pages.publish')) throw new Ha_api_error(403,'forbidden','Revoking connections requires publication permission.');
            $grant=(string)($post['grant']??''); $r=$db->get_where('ha_oauth_store',array('model'=>'Grant','id'=>$grant))->row_array(); if (!$r) throw new Ha_api_error(404,'not_found','Grant not found.');
            $p=json_decode($r['payload'],true); if ((int)($p['accountId']??0)!==(int)$A->id() && !$A->has('audit_logs.view')) throw new Ha_api_error(403,'forbidden','This grant belongs to another user.');
            $db->group_start()->where('grant_id',$grant)->or_group_start()->where('model','Grant')->where('id',$grant)->group_end()->group_end()->delete('ha_oauth_store');
            if ($db->table_exists('ha_mcp_connection')) $db->where('grant_id',$grant)->delete('ha_mcp_connection');
            $this->CI->ha_audit->log('oauth.revoke','users',(int)($p['accountId']??0),array('description'=>'MCP grant revoked for client '.($p['clientId']??'')));
            return 'revoked';
        }
        if ($action==='revoke_native') {
            if (!$A->has('cms_pages.publish') && !$A->has('cms_pages.update')) throw new Ha_api_error(403,'forbidden','Revoking connections requires website permission.');
            $grant=(string)($post['grant']??''); $g=$db->get_where('ha_mcp_grant',array('id'=>$grant))->row_array(); if (!$g || $g['revoked_at']) throw new Ha_api_error(404,'not_found','Grant not found.');
            if ((int)$g['user_id']!==(int)$A->id() && !$A->has('audit_logs.view')) throw new Ha_api_error(403,'forbidden','This grant belongs to another user.');
            $this->CI->load->library('ha_mcp_oauth'); $this->CI->ha_mcp_oauth->revoke_grant($grant,'admin_revoked');
            $this->CI->ha_audit->log('oauth.revoke','users',(int)$g['user_id'],array('description'=>'PHP MCP grant revoked for client '.$g['client_id']));
            return 'revoked';
        }
        if ($action==='bulk_approve') {
            // Row "Approve" button sends `single`; "Approve selected" sends the checked `approvals[]`. Each goes through review() (permission, expiry, separation of duties, content hash).
            $ids=isset($post['single'])?array((int)$post['single']):array_map('intval',(array)($post['approvals']??array()));
            $ids=array_values(array_unique(array_filter($ids)));
            if (!$ids) throw new Ha_api_error(422,'validation_failed','Select at least one request to approve.');
            // Each approved request is published straight away (content hash and version are re-checked).
            $failed=array(); foreach ($ids as $id) { try { $this->approve_and_publish($id); } catch (Throwable $e) { $failed[]='#'.$id.': '.$e->getMessage(); } }
            if ($failed) throw new Ha_api_error(409,'partial_approval',(count($ids)-count($failed)).' of '.count($ids).' approved and published. '.implode(' ',$failed));
            return 'published';
        }
        if ($action==='unarchive') { $this->unarchive((string)($post['type']??'page'),(int)($post['id']??0)); return 'restored'; }
        $id=(int)($post['approval']??0);
        if (($post['decision']??'')==='approve') { $this->approve_and_publish($id); return 'published'; }
        $this->review($id,false); return 'reviewed';
    }
    public function execute($action,array $in,$client) {
        $type=(string)($in['type']??'page'); $id=(int)($in['id']??0);
        if ($action==='create') {
            if (!isset(Ha_studio_catalogue::types()[$type])) throw new Ha_api_error(422,'validation_failed','Create supports courses, articles, topics, programs and paths. Use a website package to create a page.');
            $payload=(array)($in['payload']??array());
            // Clients cannot create published records or choose a tenant identity.
            $payload['status']='draft';
            $id=$this->CI->ha_studio_catalogue->save($type,0,$payload);
            $state=$this->CI->ha_content_studio->state($type,$id);
            $this->CI->ha_content_studio->save($type,$id,$state['payload'],0,$state['base_hash']);
            return $this->describe($type,$id);
        }
        if ($action==='media_list') { $this->CI->load->library('ha_studio_media'); return $this->CI->ha_studio_media->listing($in['query']??''); }
        if (in_array($action,array('media','upload_document'),true)) {
            $bytes=base64_decode((string)($in['base64']??''),true); if ($bytes===false || strlen($bytes)<1 || strlen($bytes)>15*1048576) throw new InvalidArgumentException('Provide a base64 file up to 15 MB.');
            $temp=tempnam(sys_get_temp_dir(),'altus');file_put_contents($temp,$bytes);
            try { if ($action==='media') { $this->CI->load->library('ha_studio_media');return $this->CI->ha_studio_media->image($temp,(string)($in['name']??''),(string)($in['alt_en']??''),(string)($in['alt_ar']??'')); }
                $draft=$this->CI->ha_document_jobs->enqueue($temp,(string)($in['name']??''),(string)($in['target']??'course'),(string)($in['locale']??'en')); return $this->describe('publisher',$draft);
            } finally { unlink($temp); }
        }
        if ($action==='health') {
            $report=$this->CI->ha_document_jobs->health(); $checks=array();
            foreach ($report['checks'] as $check) $checks[$check['key']]=$check['ok'];
            return array('native'=>'ready','drafts'=>$this->CI->db->table_exists('ha_studio_draft'),
                'ocr_configured'=>!empty($checks['tesseract']) && !empty($checks['tessdata_eng']) && !empty($checks['tessdata_ara']),
                'ai_configured'=>!empty($checks['ai']),'worker_running'=>!empty($checks['worker']));
        }
        if ($action==='get') return $this->describe($type,$id);
        if ($action==='list') {
            if (in_array($type,array('site','navigation'),true)) {
                $this->CI->ha_content_studio->authorize($type);
                return $type==='site'?array(array('id'=>1,'title'=>'Website theme and site settings')):$this->CI->db->select('id,code,name_en,name_ar')->order_by('id')->get('ha_menu')->result_array();
            }
            if ($type==='page') { if (!$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has('cms_pages.view')) throw new RuntimeException('Website read permission required.'); return $this->CI->db->select('id,code,slug_en,status')->like('code',mb_substr($in['query']??'',0,100))->limit(50)->get('ha_page')->result_array(); }
            if ($type==='publisher') { if (!$this->CI->ha_auth->has('ai.generate')) throw new RuntimeException('AI permission required.'); return $this->CI->db->select('id,source_name,target,locale,status,version')->where('created_by',$this->CI->ha_auth->id())->limit(50)->get('ha_publisher_draft')->result_array(); }
            return $this->CI->ha_studio_catalogue->listing($type,mb_substr($in['query']??'',0,100),max(1,(int)($in['page']??1)));
        }
        if ($action==='save') {
            if ($type==='page') $v=$this->CI->ha_website_studio->save($id,(array)($in['payload']??array()),(int)($in['version']??-1),(string)($in['base_hash']??''));
            elseif ($type==='publisher') $v=$this->CI->ha_document_publisher->save($id,(array)($in['payload']??array()),(int)($in['version']??-1));
            else $v=$this->CI->ha_content_studio->save($type,$id,(array)($in['payload']??array()),(int)($in['version']??-1),(string)($in['base_hash']??''));
            return $this->describe($type,$id);
        }
        if ($action==='source') { $id=$this->CI->ha_document_publisher->source((string)($in['source']??''),(string)($in['name']??'MCP source'),(string)($in['target']??'course'),(string)($in['locale']??'en')); return $this->describe('publisher',$id); }
        if ($action==='generate') { $this->CI->ha_document_publisher->generate($id,(int)($in['version']??-1),(string)($in['provider']??''),(string)($in['model']??''),(string)($in['brief']??''),$in['selection']??null); return $this->describe('publisher',$id); }
        if ($action==='validate') { $p=$this->CI->ha_document_publisher->validate((array)($in['payload']??array()),(string)($in['target']??'course')); return array('valid'=>true,'payload'=>$p,'dry_run'=>true); }
        if ($action==='import') { $d=$this->CI->ha_document_publisher->find($id); $entity=$this->CI->ha_document_publisher->materialize($id,(int)($in['version']??-1)); return array('object_id'=>$entity,'object_type'=>$d['target'],'status'=>'draft','edit_url'=>$this->CI->ha_document_publisher->edit_url($d,$entity)); }
        if ($action==='translate') { $new=$this->CI->ha_document_publisher->translate($id,(int)($in['version']??-1),(string)($in['provider']??''),(string)($in['model']??''),(string)($in['locale']??''));return $this->describe('publisher',$new); }
        if ($action==='source_correction') { $this->CI->ha_document_publisher->correct_source($id,(string)($in['source']??''),(int)($in['version']??-1));return $this->describe('publisher',$id); }
        if ($action==='approval_status') { $a=$this->CI->db->get_where('ha_publisher_approval',array('id'=>$id,'requester_id'=>$this->CI->ha_auth->id(),'client_id'=>$client))->row_array();if(!$a)throw new Ha_api_error(404,'not_found','Approval not found.');$this->state($a['object_type'],$a['object_id']);return array('approval_id'=>$id,'object_type'=>$a['object_type'],'object_id'=>(int)$a['object_id'],'operation'=>$a['operation'],'version'=>isset($a['object_version'])?(int)$a['object_version']:null,'status'=>self::expired($a) && in_array($a['status'],array('pending','approved'),true)?'expired':$a['status'],'expires_at'=>self::utc($a['expires_at']),'review_url'=>hkp_url('cms/integrations').'?approval='.$id); }
        if ($action==='job_status') return $this->CI->ha_document_jobs->status($id);
        if ($action==='job_control') { $this->CI->ha_document_jobs->control($id,$in['operation']??''); return $this->CI->ha_document_jobs->status($id); }
        if ($action==='request_publish') return $this->request_approval($type,$id,(string)($in['operation']??'publish'),$client);
        if ($action==='publish') return $this->approved_operation((int)($in['approval_id']??0),$client,array_intersect_key($in,array('type'=>1,'id'=>1,'operation'=>1,'version'=>1)));
        if ($action==='unarchive') return $this->unarchive($type,$id);
        if ($action==='restore' && $type==='page') { $s=$this->CI->ha_website_studio->state($id);$r=$this->CI->db->get_where('ha_page_revision',array('id'=>(int)($in['revision']??0),'page_id'=>$id))->row_array();if (!$r) throw new Ha_api_error(404,'not_found','Revision not found.');$p=json_decode($r['snapshot_json'],true);$this->CI->ha_website_studio->save($id,array('tr'=>$p['tr'],'sections'=>$p['sections']),(int)($in['version']??-1),(string)($in['base_hash']??''));return $this->describe($type,$id); }
        if ($action==='restore') { $v=$this->CI->ha_content_studio->restore($type,$id,(int)($in['revision']??0),(int)($in['version']??-1),(string)($in['base_hash']??'')); return $this->describe($type,$id); }
        throw new Ha_api_error(404,'unknown_operation','Unknown publisher operation.',array('operation'=>$action));
    }
}
