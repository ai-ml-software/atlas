<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Records what the controller sends: status, headers and body. */
class Publisher_api_test_output {
    public $status=200; public $headers=array(); public $body='';
    public function set_content_type($t) { return $this; }
    public function set_header($h) { $p=explode(':',$h,2); $this->headers[strtolower(trim($p[0]))]=trim($p[1]??''); return $this; }
    public function set_status_header($c) { $this->status=(int)$c; return $this; }
    public function set_output($o) { $this->body=$o; return $this; }
}
class Publisher_api_test_input {
    public $raw_input_stream; private $h; private $m;
    public function __construct($body,array $headers,$method) { $this->raw_input_stream=$body; $this->h=array_change_key_case($headers); $this->m=$method; }
    public function get_request_header($n,$x=false) { return $this->h[strtolower($n)]??null; }
    public function method() { return $this->m; }
}
class Publisher_api_test_uri { private $p; public function __construct($p) { $this->p=$p; } public function uri_string() { return $this->p; } }

/**
 * Publisher API contract at the HTTP boundary: each call runs the real
 * Publisher_api::dispatch() with a request (method, headers, raw JSON body)
 * and asserts on the status code, headers and JSON envelope it emits.
 */
class Test_publisher_api_gaps extends Ha_testcase {
    private $saved_config; private $admin; private $reviewer; private $tenant; private $secret; private $prev;

    public function setUp() {
        $this->CI->load->library(array('ha_gateway','ha_publishing_service','ha_content_studio','ha_website_studio','ha_document_publisher','ha_audit'));
        require_once APPPATH.'controllers/Publisher_api.php';
        $this->saved_config=$this->CI->config->config['ha_publisher'];
        $this->secret=str_repeat('a1',24); $this->prev=str_repeat('b2',24);
        $this->CI->config->config['ha_publisher']=array_merge($this->saved_config,array('mcp_enabled'=>true,'gateway_secret'=>$this->secret,'gateway_key_id'=>'k2','gateway_secret_previous'=>$this->prev,'gateway_key_id_previous'=>'k1','gateway_url'=>'http://gateway.test','allow_self_approval'=>false,'approval_ttl'=>600));
        $this->admin=$this->user('admin@hospitalityacademy.sa'); $this->reviewer=$this->user('academy.admin@hospitalityacademy.sa'); $this->tenant=$this->user('org.admin@dyafagroup.sa');
        $this->CI->ha_auth->assume($this->admin);
    }
    public function tearDown() { $this->CI->config->config['ha_publisher']=$this->saved_config; $this->CI->ha_auth->assume($this->admin); }

    private function user($email) { return (int)$this->db->get_where('users',array('email'=>$email))->row('id'); }
    private static function b64($s) { return rtrim(strtr(base64_encode($s),'+/','-_'),'='); }
    private function jwt(array $claims,$secret=null,$kid='k2') {
        $h=self::b64(json_encode(array('alg'=>'HS256','typ'=>'JWT','kid'=>$kid))); $c=self::b64(json_encode($claims));
        return $h.'.'.$c.'.'.self::b64(hash_hmac('sha256',$h.'.'.$c,$secret?:$this->secret,true));
    }
    /** Creates an OAuth grant + access token as oidc-provider would persist them. */
    private function grant($uid,$scope='altus.read altus.content.write altus.course.write altus.media.write altus.publish',$client='test-client') {
        $g='grant-'.bin2hex(random_bytes(6)); $t='token-'.bin2hex(random_bytes(6));
        $this->db->insert('ha_oauth_store',array('model'=>'Grant','id'=>$g,'payload'=>json_encode(array('accountId'=>(string)$uid,'clientId'=>$client)),'expires_at'=>time()+3600));
        $this->db->insert('ha_oauth_store',array('model'=>'AccessToken','id'=>$t,'grant_id'=>$g,'payload'=>json_encode(array('accountId'=>(string)$uid,'clientId'=>$client,'grantId'=>$g,'scope'=>$scope)),'expires_at'=>time()+300));
        return array('uid'=>$uid,'client'=>$client,'scope'=>$scope,'token'=>$t,'grant'=>$g);
    }
    private function bearer(array $g,$ttl=60,$secret=null,$kid='k2') { $now=time(); return $this->jwt(array('iss'=>'http://gateway.test','aud'=>'altus-native-publisher','iat'=>$now,'exp'=>$now+$ttl,'sub'=>(string)$g['uid'],'client_id'=>$g['client'],'scope'=>$g['scope'],'source_id'=>$g['token']),$secret,$kid); }
    /** One HTTP request through the real controller. */
    private function call($action,array $body,$bearer,$key=null,$method='post') {
        $ref=new ReflectionClass('Publisher_api'); $c=$ref->newInstanceWithoutConstructor();
        foreach (array('db','load','config','ha_gateway','ha_auth','ha_publishing_service','ha_document_publisher','ha_audit') as $p) $c->$p=$this->CI->$p;
        $headers=array('Authorization'=>'Bearer '.$bearer,'Content-Type'=>'application/json','X-Request-Id'=>'test-'.bin2hex(random_bytes(6))); if ($key) $headers['Idempotency-Key']=$key;
        $c->input=new Publisher_api_test_input(json_encode($body),$headers,$method); $c->uri=new Publisher_api_test_uri('api/publisher/v1/'.$action); $c->output=new Publisher_api_test_output();
        $c->dispatch(); $json=json_decode($c->output->body,true);
        $this->CI->ha_auth->assume($this->admin);
        return array('status'=>$c->output->status,'headers'=>$c->output->headers,'json'=>$json,'data'=>$json['data']??null,'error'=>$json['error']??null);
    }
    private function key($p) { return $p.'-'.bin2hex(random_bytes(5)); }
    private function about() { return (int)$this->db->get_where('ha_page',array('code'=>'about'))->row('id'); }
    private function page_draft(array $g,$title) {
        $o=$this->call('get',array('type'=>'page','id'=>$this->about()),$this->bearer($g))['data']; $o['state']['payload']['tr']['en']['title']=$title;
        return $this->call('save',array('type'=>'page','id'=>$this->about(),'version'=>$o['version'],'base_hash'=>$o['state']['base_hash'],'payload'=>$o['state']['payload']),$this->bearer($g),$this->key('save'));
    }
    private function approve($approval) { $this->CI->ha_auth->assume($this->reviewer); try { $this->CI->ha_publishing_service->review($approval,true); } finally { $this->CI->ha_auth->assume($this->admin); } }

    public function test_catalogue_creation_stays_private_and_replays_without_duplicates() {
        $g=$this->grant($this->admin); $slug='mcp-create-'.bin2hex(random_bytes(5)); $key=$this->key('create');
        $body=array('type'=>'articles','payload'=>array('title_en'=>'Private MCP article','title_ar'=>'Private article AR','slug_en'=>$slug,'slug_ar'=>$slug.'-ar','body_en'=>'<p>Reviewed source text.</p>','body_ar'=>'<p>Source text.</p>','status'=>'published'));
        $r=$this->call('create',$body,$this->bearer($g),$key); $this->assertEquals(200,$r['status']); $id=$r['data']['object_id'];
        $this->assertEquals('draft',$this->db->get_where('ha_article',array('id'=>$id))->row('status')); $this->assertEquals(1,$r['data']['version']);
        $again=$this->call('create',$body,$this->bearer($g),$key); $this->assertEquals($id,$again['data']['object_id']); $this->assertEquals('true',$again['headers']['idempotent-replayed']);
        $tenant=$this->grant($this->tenant); $denied=$this->call('create',$body,$this->bearer($tenant),$this->key('denied')); $this->assertEquals(403,$denied['status']);
        foreach (array('site','navigation') as $type) { $listed=$this->call('list',array('type'=>$type),$this->bearer($g)); $this->assertEquals(200,$listed['status']); $this->assertNotEmpty($listed['data']); }
    }

    public function test_error_envelope_and_feature_switch() {
        $g=$this->grant($this->admin); $r=$this->call('get',array('type'=>'page','id'=>$this->about()),$this->bearer($g));
        $this->assertEquals(200,$r['status']); $this->assertTrue($r['json']['success']); $this->assertNotEmpty($r['json']['request_id']);
        $this->CI->config->config['ha_publisher']['mcp_enabled']=false;
        $r=$this->call('get',array('type'=>'page','id'=>$this->about()),$this->bearer($g)); $this->assertEquals(503,$r['status']); $this->assertEquals('mcp_disabled',$r['error']['code']); $this->assertFalse($r['json']['success']);
        $r=$this->call('bridge',array('code'=>'x','binding'=>'y'),'x.y.z'); $this->assertEquals(503,$r['status'],'bridge also obeys the switch');
        $this->CI->config->config['ha_publisher']['mcp_enabled']=true;
        $r=$this->call('get',array('type'=>'page','id'=>999999),$this->bearer($g)); $this->assertEquals(404,$r['status']); $this->assertEquals('not_found',$r['error']['code']);
        foreach (array('code','message','details','retryable') as $k) $this->assertTrue(array_key_exists($k,$r['error']),'error.'.$k);
        $r=$this->call('nonsense',array(),$this->bearer($g),$this->key('n')); $this->assertEquals(404,$r['status']);
        $r=$this->call('save',array('type'=>'page','id'=>$this->about(),'payload'=>array()),$this->bearer($g)); $this->assertEquals(422,$r['status']); $this->assertEquals('idempotency_key_required',$r['error']['code']);
        $r=$this->call('get',array('type'=>'page','id'=>1,'organization_id'=>5),$this->bearer($g)); $this->assertEquals(422,$r['status']);
        $r=$this->call('get',array(),$this->bearer($g),null,'get'); $this->assertEquals(405,$r['status']);
    }
    public function test_delegated_credential_lifetime_and_key_rotation() {
        $g=$this->grant($this->admin); $body=array('type'=>'page','id'=>$this->about());
        $this->assertEquals(401,$this->call('get',$body,$this->bearer($g,120))['status'],'over 60s lifetime rejected');
        $this->assertEquals(200,$this->call('get',$body,$this->bearer($g,60,$this->prev,'k1'))['status'],'previous key verifies during rotation');
        $r=$this->call('get',$body,$this->bearer($g,60,$this->secret,'k9')); $this->assertEquals(401,$r['status']); $this->assertEquals('unknown_key',$r['error']['code']);
        $this->assertEquals(401,$this->call('get',$body,$this->bearer($g,60,$this->prev,'k2'))['status'],'wrong secret for kid rejected');
        $this->assertEquals(401,$this->call('get',$body,'')['status']);
        $this->assertNotEmpty($this->db->get_where('ha_mcp_connection',array('grant_id'=>$g['grant']))->row_array(),'last-seen recorded per grant');
    }
    public function test_page_conflict_returns_409_with_current_version() {
        $g=$this->grant($this->admin); $o=$this->call('get',array('type'=>'page','id'=>$this->about()),$this->bearer($g))['data'];
        $body=array('type'=>'page','id'=>$this->about(),'version'=>$o['version'],'base_hash'=>$o['state']['base_hash'],'payload'=>$o['state']['payload']);
        $this->assertEquals(200,$this->call('save',$body,$this->bearer($g),$this->key('p1'))['status']);
        $r=$this->call('save',$body,$this->bearer($g),$this->key('p2')); $this->assertEquals(409,$r['status']); $this->assertEquals('conflict',$r['error']['code']);
        $this->assertEquals($o['version']+1,$r['error']['details']['current_version']); $this->assertNotEmpty($r['error']['details']['current_hash']);
    }
    public function test_publisher_draft_conflict_returns_409() {
        $g=$this->grant($this->admin); $id=$this->CI->ha_document_publisher->source(str_repeat('Follow the housekeeping safety procedure carefully. ',3),'conflict.txt','article','en');
        $d=$this->CI->ha_document_publisher->find($id); $payload=array('title'=>'Draft','summary'=>'S','sections'=>array(array('heading'=>'H','body'=>'<p>B</p>')));
        $this->assertEquals(200,$this->call('save',array('type'=>'publisher','id'=>$id,'version'=>(int)$d['version'],'payload'=>$payload),$this->bearer($g),$this->key('d1'))['status']);
        $r=$this->call('save',array('type'=>'publisher','id'=>$id,'version'=>(int)$d['version'],'payload'=>$payload),$this->bearer($g),$this->key('d2'));
        $this->assertEquals(409,$r['status']); $this->assertEquals('publisher',$r['error']['details']['object_type']);
    }
    public function test_entity_conflict_returns_409() {
        $g=$this->grant($this->admin); $id=(int)$this->db->get('ha_article')->row('id'); $o=$this->call('get',array('type'=>'articles','id'=>$id),$this->bearer($g))['data'];
        $body=array('type'=>'articles','id'=>$id,'version'=>$o['version'],'base_hash'=>$o['state']['base_hash'],'payload'=>$o['state']['payload']);
        $this->assertEquals(200,$this->call('save',$body,$this->bearer($g),$this->key('e1'))['status']);
        $r=$this->call('save',$body,$this->bearer($g),$this->key('e2')); $this->assertEquals(409,$r['status']); $this->assertEquals('articles',$r['error']['details']['object_type']);
    }
    public function test_idempotency_replay_mismatch_and_real_audit_reference() {
        $g=$this->grant($this->admin); $o=$this->call('get',array('type'=>'page','id'=>$this->about()),$this->bearer($g))['data'];
        $body=array('type'=>'page','id'=>$this->about(),'version'=>$o['version'],'base_hash'=>$o['state']['base_hash'],'payload'=>$o['state']['payload']); $k=$this->key('idem');
        $a=$this->call('save',$body,$this->bearer($g),$k); $b=$this->call('save',$body,$this->bearer($g),$k);
        $this->assertEquals(200,$b['status']); $this->assertEquals('true',$b['headers']['idempotent-replayed']??null); $this->assertEquals($a['data']['version'],$b['data']['version']); $this->assertEquals($a['data']['audit_reference']['audit_id'],$b['data']['audit_reference']['audit_id']);
        $audit=$this->db->get_where('ha_audit_log',array('id'=>$a['data']['audit_reference']['audit_id']))->row_array(); $this->assertEquals('mcp.save',$audit['action']); $this->assertEquals($this->admin,(int)$audit['user_id']);
        $body['payload']['tr']['en']['title']='Other body, same key'; $r=$this->call('save',$body,$this->bearer($g),$k); $this->assertEquals(409,$r['status']); $this->assertEquals('idempotency_conflict',$r['error']['code']);
    }
    public function test_scope_restriction() {
        $g=$this->grant($this->admin,'altus.read'); $r=$this->call('save',array('type'=>'page','id'=>$this->about(),'version'=>0,'payload'=>array()),$this->bearer($g),$this->key('s'));
        $this->assertEquals(403,$r['status']); $this->assertEquals('insufficient_scope',$r['error']['code']); $this->assertEquals('altus.content.write',$r['error']['details']['required_scope']);
        $g2=$g; $g2['scope']='altus.read altus.publish'; $this->assertEquals(403,$this->call('get',array('type'=>'page','id'=>$this->about()),$this->bearer($g2))['status'],'delegated scope cannot exceed grant');
    }
    public function test_self_approval_blocked_unless_configured() {
        $g=$this->grant($this->admin); $this->page_draft($g,'Self approval headline');
        $a=$this->call('request_publish',array('type'=>'page','id'=>$this->about(),'operation'=>'publish'),$this->bearer($g),$this->key('rq'))['data'];
        $this->assertMatches('/Z$/',$a['expires_at']); $this->assertLessThanOrEqual(5,abs(strtotime($a['expires_at'])-(time()+600)),'expires 10 minutes from now, GMT');
        $this->assertLessThanOrEqual(5,abs(strtotime($this->db->get_where('ha_publisher_approval',array('id'=>$a['approval_id']))->row('expires_at').' UTC')-(time()+600)),'stored expiry is UTC');
        $P=$this->CI->ha_publishing_service; $caught=null; try { $P->review($a['approval_id'],true); } catch (Ha_api_error $e) { $caught=$e; }
        $this->assertNotNull($caught); $this->assertEquals('self_approval_forbidden',$caught?$caught->error_code:null); $this->assertEquals('pending',$this->db->get_where('ha_publisher_approval',array('id'=>$a['approval_id']))->row('status'));
        $this->CI->config->config['ha_publisher']['allow_self_approval']=true; $P->review($a['approval_id'],true); $this->assertEquals('approved',$this->db->get_where('ha_publisher_approval',array('id'=>$a['approval_id']))->row('status'));
    }
    public function test_admin_bulk_approve_publishes_immediately() {
        $g=$this->grant($this->admin); $this->page_draft($g,'Bulk approved headline');
        $a=$this->call('request_publish',array('type'=>'page','id'=>$this->about(),'operation'=>'publish'),$this->bearer($g),$this->key('bulk'))['data'];
        $this->CI->config->config['ha_publisher']['allow_self_approval']=true; $P=$this->CI->ha_publishing_service;
        $this->assertEquals('published',$P->admin_post(array('action'=>'bulk_approve','approvals'=>array((string)$a['approval_id']))));
        $this->assertEquals('consumed',$this->db->get_where('ha_publisher_approval',array('id'=>$a['approval_id']))->row('status'),'approval is used by the publication');
        $this->assertEquals('Bulk approved headline',$this->CI->ha_page_builder->page($this->about())['tr']['en']['title'],'checked + Approve makes the change live');
        $caught=null; try { $P->admin_post(array('action'=>'bulk_approve','single'=>(string)$a['approval_id'])); } catch (Ha_api_error $e) { $caught=$e; }
        $this->assertEquals('partial_approval',$caught?$caught->error_code:null,'a used request cannot be approved twice');
    }
    public function test_approval_expiry_change_single_use_and_duplicate_publication() {
        $g=$this->grant($this->admin); $this->page_draft($g,'Approved MCP headline');
        $a=$this->call('request_publish',array('type'=>'page','id'=>$this->about(),'operation'=>'publish'),$this->bearer($g),$this->key('rq'))['data'];
        $r=$this->call('publish',array('approval_id'=>$a['approval_id']),$this->bearer($g),$this->key('early')); $this->assertEquals(403,$r['status'],'unreviewed approval cannot publish'); $this->assertEquals('approval_required',$r['error']['code']);
        $this->approve($a['approval_id']);
        $other=$this->grant($this->admin,$g['scope'],'other-client'); $this->assertEquals(404,$this->call('publish',array('approval_id'=>$a['approval_id']),$this->bearer($other),$this->key('oc'))['status'],'bound to client');
        $this->assertEquals(409,$this->call('publish',array('approval_id'=>$a['approval_id'],'operation'=>'archive'),$this->bearer($g),$this->key('op'))['status'],'bound to operation');
        $k=$this->key('pub'); $ok=$this->call('publish',array('approval_id'=>$a['approval_id']),$this->bearer($g),$k); $this->assertEquals(200,$ok['status'],json_encode($ok['error']));
        $this->assertEquals('Approved MCP headline',$this->CI->ha_page_builder->page($this->about())['tr']['en']['title']);
        $dup=$this->call('publish',array('approval_id'=>$a['approval_id']),$this->bearer($g),$k); $this->assertEquals(200,$dup['status']); $this->assertEquals('true',$dup['headers']['idempotent-replayed']??null,'duplicate publication replays, does not republish');
        $this->assertEquals(1,$this->db->where(array('action'=>'mcp.publish','id'=>$ok['data']['audit_reference']['audit_id']))->count_all_results('ha_audit_log'));
        $again=$this->call('publish',array('approval_id'=>$a['approval_id']),$this->bearer($g),$this->key('again')); $this->assertEquals(409,$again['status']); $this->assertEquals('approval_consumed',$again['error']['code']);
        // Changed content after approval.
        $this->page_draft($g,'Second headline'); $b=$this->call('request_publish',array('type'=>'page','id'=>$this->about(),'operation'=>'publish'),$this->bearer($g),$this->key('rq2'))['data']; $this->approve($b['approval_id']);
        $this->page_draft($g,'Edited after approval'); $r=$this->call('publish',array('approval_id'=>$b['approval_id']),$this->bearer($g),$this->key('chg'));
        $this->assertEquals(409,$r['status']); $this->assertEquals('conflict',$r['error']['code']); $this->assertEquals($b['version'],$r['error']['details']['approved_version']); $this->assertEquals($b['version']+1,$r['error']['details']['current_version']);
        // Expiry.
        $c=$this->call('request_publish',array('type'=>'page','id'=>$this->about(),'operation'=>'publish'),$this->bearer($g),$this->key('rq3'))['data']; $this->approve($c['approval_id']);
        $this->db->where('id',$c['approval_id'])->update('ha_publisher_approval',array('expires_at'=>gmdate('Y-m-d H:i:s',time()-1)));
        $r=$this->call('publish',array('approval_id'=>$c['approval_id']),$this->bearer($g),$this->key('exp')); $this->assertEquals(409,$r['status']); $this->assertEquals('approval_expired',$r['error']['code']);
        $this->assertEquals('expired',$this->call('approval_status',array('id'=>$c['approval_id']),$this->bearer($g))['data']['status']);
    }
    public function test_sop_publication_keeps_sop_governance() {
        $g=$this->grant($this->admin); $r=$this->call('request_publish',array('type'=>'sop','id'=>1,'operation'=>'publish'),$this->bearer($g),$this->key('sop'));
        $this->assertEquals(422,$r['status']); $this->assertEquals('sop_governance_required',$r['error']['code']);
    }
    public function test_tenant_isolation() {
        $admin=$this->grant($this->admin); $this->page_draft($admin,'Tenant isolation headline');
        $a=$this->call('request_publish',array('type'=>'page','id'=>$this->about(),'operation'=>'publish'),$this->bearer($admin),$this->key('rq'))['data'];
        $t=$this->grant($this->tenant); $r=$this->call('get',array('type'=>'page','id'=>$this->about()),$this->bearer($t)); $this->assertEquals(403,$r['status'],'tenant cannot read platform pages');
        $this->assertEquals(404,$this->call('approval_status',array('id'=>$a['approval_id']),$this->bearer($t))['status'],'tenant cannot see another user\'s approval');
        $this->assertEquals(404,$this->call('publish',array('approval_id'=>$a['approval_id']),$this->bearer($t),$this->key('tp'))['status']);
        $this->CI->ha_auth->assume($this->tenant); $this->assertThrows(function()use($a){$this->CI->ha_publishing_service->review($a['approval_id'],true);}); $this->CI->ha_auth->assume($this->admin);
    }
    public function test_admin_screens_are_permission_gated_and_render() {
        $P=$this->CI->ha_publishing_service; $g=$this->grant($this->admin); $this->call('get',array('type'=>'page','id'=>$this->about()),$this->bearer($g));
        $this->assertEquals(200,$this->page_draft($g,'Audit headline')['status']);
        foreach (Ha_publishing_service::ADMIN_TABS as $tab) { $v=$P->admin_view(array('tab'=>$tab)); $this->assertEquals($tab,$v['tab']); $html=$this->CI->load->view('hkp/studio_integrations',$v,true); $this->assertTrue(strlen($html)>500,'renders '.$tab); }
        $v=$P->admin_view(array('tab'=>'connections')); $this->assertTrue(isset($v['seen'][$g['grant']]),'connections show last seen');
        $v=$P->admin_view(array('tab'=>'audit','action'=>'mcp.get')); $this->assertEquals(0,$v['total'],'reads are not audited writes'); $v=$P->admin_view(array('tab'=>'audit','user'=>$this->admin)); $this->assertGreaterThan(0,$v['total']);
        $this->CI->ha_auth->assume($this->user('instructor.fo@hospitalityacademy.sa')); $this->assertThrows(function()use($P){$P->admin_view(array('tab'=>'audit'));},'audit tab requires audit_logs.view'); $this->CI->ha_auth->assume($this->admin);
        $id=$this->about(); $this->db->where('id',$id)->update('ha_page',array('status'=>'archived'));
        $this->assertEquals('restored',$P->admin_post(array('action'=>'unarchive','type'=>'page','id'=>$id))); $this->assertEquals('draft',$this->db->get_where('ha_page',array('id'=>$id))->row('status'));
        $this->db->where('id',$id)->update('ha_page',array('status'=>'published'));
        $this->assertEquals('revoked',$P->admin_post(array('action'=>'revoke','grant'=>$g['grant']))); $this->assertEquals(401,$this->call('get',array('type'=>'page','id'=>$id),$this->bearer($g))['status']);
    }
    public function test_revoked_grant_and_user() {
        $g=$this->grant($this->admin); $body=array('type'=>'page','id'=>$this->about()); $this->assertEquals(200,$this->call('get',$body,$this->bearer($g))['status']);
        $this->db->where(array('model'=>'Grant','id'=>$g['grant']))->delete('ha_oauth_store');
        $r=$this->call('get',$body,$this->bearer($g)); $this->assertEquals(401,$r['status']); $this->assertEquals('grant_revoked',$r['error']['code']); $this->assertNotEmpty($r['headers']['www-authenticate']??null);
        $u=$this->reviewer; $g2=$this->grant($u); $this->assertEquals(200,$this->call('get',$body,$this->bearer($g2))['status']);
        $this->db->where('id',$u)->update('users',array('status'=>0)); $this->CI->ha_auth->assume($this->admin);
        try { $r=$this->call('get',$body,$this->bearer($g2)); $this->assertEquals(401,$r['status']); $this->assertEquals('user_revoked',$r['error']['code']); }
        finally { $this->db->where('id',$u)->update('users',array('status'=>1)); }
    }
}
