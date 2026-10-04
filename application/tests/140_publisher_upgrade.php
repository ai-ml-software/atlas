<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Test_publisher_upgrade extends Ha_testcase {
    public function setUp() { $this->CI->load->library(array('ha_content_studio','ha_publishing_service','ha_gateway','ha_document_jobs')); $this->CI->ha_auth->assume((int)$this->db->get_where('users',array('email'=>'admin@hospitalityacademy.sa'))->row('id')); }
    /** Separation of duties: a second administrator reviews MCP requests. */
    private function approve($id) { $me=$this->CI->ha_auth->id(); $this->CI->ha_auth->assume((int)$this->db->get_where('users',array('email'=>'academy.admin@hospitalityacademy.sa'))->row('id')); try { $this->CI->ha_publishing_service->review($id,true); } finally { $this->CI->ha_auth->assume($me); } }
    private function about() { return (int)$this->db->get_where('ha_page',array('code'=>'about'))->row('id'); }
    private function page_draft() { $id=$this->about(); $s=$this->CI->ha_website_studio->state($id); $s['payload']['tr']['en']['title']='Reviewed gateway title'; $v=$this->CI->ha_website_studio->save($id,$s['payload'],$s['version'],$s['base_hash']); return array($id,$v); }
    public function test_site_changes_are_private_and_stale_versions_fail() {
        $S=$this->CI->ha_content_studio; $s=$S->state('site',1); $p=$s['payload']; $p['accent']='#112233';
        $v=$S->save('site',1,$p,$s['version'],$s['base_hash']); $this->assertNotEquals('#112233',$S->snapshot('site',1)['accent']);
        $this->assertThrows(function()use($S,$p,$s){$S->save('site',1,$p,$s['version'],$s['base_hash']);});
        $this->assertTrue(strpos($S->css(true),'#112233')!==false); $this->assertTrue(strpos($S->css(false),'#112233')===false);
        $S->publish('site',1,$v); $this->assertEquals('#112233',$S->snapshot('site',1)['accent']);
    }
    public function test_theme_validation_rejects_css_injection() {
        $S=$this->CI->ha_content_studio; $p=Ha_content_studio::defaults(); $p['accent']='red;}body{display:none'; $this->assertThrows(function()use($S,$p){$S->validate('site',$p);});
        $p=Ha_content_studio::defaults();$p['body_font']='url(evil)';$this->assertThrows(function()use($S,$p){$S->validate('site',$p);});
    }
    public function test_catalogue_private_drafts_publish_and_restore_as_drafts() {
        $S=$this->CI->ha_content_studio; $id=(int)$this->db->get('ha_article')->row('id'); $s=$S->state('articles',$id); $old=$s['payload']['title_en'];$p=$s['payload'];$p['title_en']='Private article headline';
        $v=$S->save('articles',$id,$p,$s['version'],$s['base_hash']);$this->assertEquals($old,$S->snapshot('articles',$id)['title_en']);$S->publish('articles',$id,$v);$this->assertEquals('Private article headline',$S->snapshot('articles',$id)['title_en']);
        $r=(int)$this->db->where('object_type','articles')->where('object_id',$id)->order_by('id')->get('ha_studio_revision')->row('id');$s=$S->state('articles',$id);$S->restore('articles',$id,$r,$s['version'],$s['base_hash']);$this->assertEquals($old,$S->state('articles',$id)['payload']['title_en']);$this->assertEquals('Private article headline',$S->snapshot('articles',$id)['title_en']);
    }
    public function test_navigation_drafts_preserve_public_menu_until_review() {
        $S=$this->CI->ha_content_studio;$id=(int)$this->db->get('ha_menu')->row('id');$s=$S->state('navigation',$id);$p=$s['payload'];$old=$p['items'][0]['label_en'];$p['items'][0]['label_en']='Private menu label';$v=$S->save('navigation',$id,$p,$s['version'],$s['base_hash']);$this->assertEquals($old,$S->snapshot('navigation',$id)['items'][0]['label_en']);$S->publish('navigation',$id,$v);$this->assertEquals('Private menu label',$S->snapshot('navigation',$id)['items'][0]['label_en']);
    }
    public function test_page_section_ids_survive_reordering_and_publication() {
        list($id,$v)=$this->page_draft();$S=$this->CI->ha_website_studio;$s=$S->state($id);$s['payload']['sections'][]=array('studio_key'=>'stable-example','section_type'=>'rich_text','is_visible'=>1,'en'=>array('heading'=>'Stable','body'=>'<p>Text</p>'),'ar'=>array(),'settings'=>array());$v=$S->save($id,$s['payload'],$v,$s['base_hash']);$S->publish($id,$v);$sid=$this->db->get_where('ha_page_section',array('studio_key'=>'stable-example'))->row('id');$s=$S->state($id);$v=$S->save($id,$s['payload'],0,$s['base_hash']);$S->publish($id,$v);$this->assertEquals($sid,$this->db->get_where('ha_page_section',array('studio_key'=>'stable-example'))->row('id'));
    }
    public function test_approval_is_bound_to_client_user_version_and_single_use() {
        list($id,$v)=$this->page_draft();$P=$this->CI->ha_publishing_service;$a=$P->request_approval('page',$id,'publish','client-one');$this->assertThrows(function()use($P,$a){$P->approved_operation($a['approval_id'],'client-one');});$this->approve($a['approval_id']);$this->assertThrows(function()use($P,$a){$P->approved_operation($a['approval_id'],'other-client');});$P->approved_operation($a['approval_id'],'client-one');$this->assertEquals('Reviewed gateway title',$this->CI->ha_page_builder->page($id)['tr']['en']['title']);$this->assertThrows(function()use($P,$a){$P->approved_operation($a['approval_id'],'client-one');});
    }
    public function test_approval_expires_and_content_edits_invalidate_it() {
        list($id,$v)=$this->page_draft();$P=$this->CI->ha_publishing_service;$a=$P->request_approval('page',$id,'publish','client');$this->approve($a['approval_id']);$s=$this->CI->ha_website_studio->state($id);$s['payload']['tr']['en']['title']='Changed after approval';$this->CI->ha_website_studio->save($id,$s['payload'],$v,$s['base_hash']);$this->assertThrows(function()use($P,$a){$P->approved_operation($a['approval_id'],'client');});$a=$P->request_approval('page',$id,'publish','client');$this->db->where('id',$a['approval_id'])->update('ha_publisher_approval',array('expires_at'=>'2000-01-01 00:00:00'));$this->assertThrows(function()use($P,$a){$P->review($a['approval_id'],true);});
    }
    public function test_resource_packages_are_drafts_and_sop_uses_governance() {
        $P=$this->CI->ha_document_publisher;foreach(array('article','topic','sop') as $target){$id=$P->source(str_repeat('Follow the approved housekeeping safety procedure. ',3),$target.'.txt',$target,'en');$v=$P->save($id,array('title'=>'Resource '.$target,'summary'=>'Source facts','sections'=>array(array('heading'=>'Procedure','body'=>'<p>Wear PPE.</p>'))),1);$entity=$P->materialize($id,$v);$table=array('article'=>'ha_article','topic'=>'ha_topic','sop'=>'ha_sop_document')[$target];$this->assertEquals('draft',$this->db->get_where($table,array('id'=>$entity))->row('status'));$this->assertEquals($entity,$P->materialize($id,$v));if($target==='sop')$this->assertEquals('draft',$this->db->get_where('ha_sop_version',array('sop_id'=>$entity))->row('status'));}
    }
    public function test_queue_retains_source_and_supports_cancellation_and_retry() {
        $file=tempnam(sys_get_temp_dir(),'altus');file_put_contents($file,str_repeat('Approved safety instructions. ',3));$J=$this->CI->ha_document_jobs;$id=$J->enqueue($file,'source.txt','article','en');$this->assertEquals('queued',$J->status($id)['status']);$J->control($id,'cancel');$J->work();$this->assertEquals('cancelled',$J->status($id)['status']);$J->control($id,'retry');$J->work();$this->assertEquals('completed',$J->status($id)['status']);$this->assertNotEmpty($this->CI->ha_document_publisher->find($id)['source_text']);unlink($file);
    }
    public function test_tenant_cannot_open_platform_drafts_or_publish_approvals() {
        list($id,$v)=$this->page_draft();$P=$this->CI->ha_publishing_service;$a=$P->request_approval('page',$id,'publish','client');$this->CI->ha_auth->assume((int)$this->db->get_where('users',array('email'=>'org.admin@dyafagroup.sa'))->row('id'));$this->assertThrows(function()use($P,$a){$P->review($a['approval_id'],true);});$this->assertThrows(function(){$this->CI->ha_content_studio->state('site',1);});
    }
}
