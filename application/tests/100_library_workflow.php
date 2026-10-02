<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test_library_workflow extends Ha_testcase {
    public function setUp() { $this->CI->load->library(array('ha_library_review','ha_global_translation','ha_library_video')); $this->CI->load->helper(array('ha_locale','ha_reviewed_translation')); }
    private function unit($scope, $text = 'Welcome {name}') {
        $T = $this->CI->ha_global_translation;
        $id = $T->unit($scope,'test/value',$text,array('type'=>'ui','domain'=>'literal','key'=>$text));
        return $this->db->get_where('ha_translation_unit',array('id'=>$id))->row_array();
    }
    private function package($unit, $locale, $value, $status = 'ready', $reviewer = 'Test reviewer') {
        return array('version'=>1,'locale'=>$locale,'units'=>array(array('key'=>$unit['unit_key'],'source_hash'=>$unit['source_hash'],'value'=>$value,'status'=>$status,'reviewer'=>$reviewer)));
    }
    public function test_inventory_and_source_manifest_account_for_every_pdf() {
        $R = $this->CI->ha_library_review; $m = $R->manifest();
        $this->assertEquals(41,count($m['sources'])); $this->assertEquals(368,array_sum(array_column($m['sources'],'page_count')));
        $this->assertEquals(40,count($R->course_files()));
        $this->assertGreaterThan(7000,$this->db->count_all('ha_language_inventory'));
        $this->assertGreaterThan(100,$this->db->where('modality','signed')->count_all_results('ha_language_inventory'));
        foreach (array('ils','sfb','vgt') as $code) { $this->assertEquals('signed',$this->db->get_where('ha_language_inventory',array('locale'=>$code))->row('modality')); }
        $this->assertEquals(1,count(array_filter($m['sources'],function($s){ return !empty($s['duplicate_of']); })));
        $this->assertThrows(function()use($R){$R->course('unrelated-course');});
    }
    public function test_translation_import_is_atomic_and_rejects_stale_sources() {
        $T=$this->CI->ha_global_translation; $u=$this->unit('test:atomic');
        $p=$this->package($u,'ar','مرحباً {name}'); $p['units'][]=array('key'=>str_repeat('0',64),'source_hash'=>$u['source_hash'],'value'=>'bad');
        $this->assertThrows(function()use($T,$p){$T->import($p);});
        $this->assertEquals(0,$this->db->where(array('unit_id'=>$u['id'],'locale'=>'ar'))->count_all_results('ha_translation_value'));
        $T->import($this->package($u,'ar','مرحباً {name}'));
        $this->assertTrue($T->coverage('ar','test:atomic')['ready']);
        $T->unit('test:atomic','test/value','Welcome again {name}',array('type'=>'ui'));
        $this->assertFalse($T->coverage('ar','test:atomic')['ready']);
        $this->assertThrows(function()use($T,$u){$T->import($this->package($u,'ar','مرحباً {name}'));});
    }
    public function test_placeholders_markup_and_glossary_survive_translation() {
        Ha_global_translation::validate_text('<p>Altus Gulf welcomes {name}</p>','<p>Altus Gulf ترحب بـ {name}</p>');
        $this->assertTrue(true);
        foreach (array('<p>Altus Gulf</p>','<div>Altus Gulf {name}</div>','<p>Other Brand {name}</p>') as $bad) {
            $this->assertThrows(function()use($bad){Ha_global_translation::validate_text('<p>Altus Gulf welcomes {name}</p>',$bad);});
        }
    }
    public function test_ai_cannot_approve_and_signed_languages_need_signed_assets() {
        $T=$this->CI->ha_global_translation; $u=$this->unit('test:review');
        $p=$this->package($u,'ar','مرحباً {name}');
        $this->assertThrows(function()use($T,$p){$T->import($p,'ai');});
        $this->assertThrows(function()use($T,$u){$T->import($this->package($u,'ar','مرحباً {name}','ready',''));});
        $signed=$this->db->select('locale')->where('modality','signed')->get('ha_language_inventory')->row('locale');
        $this->assertThrows(function()use($T,$u,$signed){$T->import($this->package($u,$signed,''));});
        $this->assertFalse($T->coverage($signed,'test:review')['ready']);
    }
    public function test_region_restrictions_and_provider_urls() {
        $this->assertTrue(Ha_library_video::country_allowed('{"allowed":["SA"]}','SA'));
        $this->assertFalse(Ha_library_video::country_allowed('{"allowed":["SA"]}','FR'));
        $this->assertFalse(Ha_library_video::country_allowed('{"blocked":["SA"]}','SA'));
        $this->assertTrue(Ha_library_video::country_allowed('{}','SA'));
        $this->assertEquals('https://www.dailymotion.com/embed/video/x123abc',Ha_library_video::urls('dailymotion','x123abc')[1]);
        $this->assertThrows(function(){Ha_library_video::urls('upload','uploads/academy/library/videos/../../private.mp4');});
        $this->assertThrows(function(){Ha_library_video::urls('youtube','https://attacker.example');});
        $item=array('status'=>array('embeddable'=>false,'privacyStatus'=>'public'),'contentDetails'=>array('duration'=>'PT2M30S','regionRestriction'=>array('blocked'=>array('SA'))),
            'snippet'=>array('title'=>'Test','channelTitle'=>'Creator','channelId'=>'123'));
        $data=Ha_library_video::youtube_details($item);
        $this->assertEquals('unavailable',$data['status']); $this->assertEquals(150,$data['duration_seconds']);
    }
    public function test_seeding_stages_changes_without_overwriting_published_data() {
        require_once APPPATH.'seeds/009_library.php';
        $seed=new Seed_library();
        $row=$this->db->get_where('ha_course',array('code'=>'dy-active-listening'))->row_array();
        $this->db->where('id',$row['id'])->update('ha_course',array('status'=>'published'));
        $this->db->where(array('course_id'=>$row['id'],'locale'=>'en'))->update('ha_course_translation',array('title'=>'Published editorial title'));
        $ids=array_column($this->db->select('id')->get_where('ha_lesson',array('course_id'=>$row['id']))->result_array(),'id');
        $seed->run($this->db); $seed->run($this->db);
        $this->assertEquals('Published editorial title',$this->db->get_where('ha_course_translation',array('course_id'=>$row['id'],'locale'=>'en'))->row('title'));
        $this->assertEquals($ids,array_column($this->db->select('id')->get_where('ha_lesson',array('course_id'=>$row['id']))->result_array(),'id'));
        $this->assertEquals('published',$this->db->get_where('ha_course',array('id'=>$row['id']))->row('status'));
        $this->assertGreaterThan(0,$this->db->where('course_code','dy-active-listening')->count_all_results('ha_library_revision'));
    }
    public function test_unreviewed_course_cannot_publish_or_gain_language_alternates() {
        $R=$this->CI->ha_library_review;
        $before=$this->db->get_where('ha_course',array('code'=>'dy-welcome-home'))->row_array();
        $report=$R->ready('dy-welcome-home','en'); $this->assertFalse($report['ready']); $this->assertNotEmpty($report['issues']);
        $this->assertThrows(function()use($R){$R->publish('dy-welcome-home','en');});
        $this->assertEquals($before,$this->db->get_where('ha_course',array('id'=>$before['id']))->row_array());
        $this->CI->load->library('ha_catalog');
        $this->assertFalse($this->CI->ha_catalog->is_translated('ha_course_translation','course_id',$before['id'],'hi'));
        $this->assertEquals('sr-Latn',ha_locale_filename('sr-Latn')); $this->assertEquals('en',ha_locale_filename('../private'));
        $this->assertEquals(null,ha_hreflang('ase')); $this->assertEquals('ar',ha_hreflang('ar'));
    }
    public function test_phrase_fallback_never_creates_columns_or_fake_translations() {
        $before=$this->db->list_fields('language'); $this->CI->load->library('session'); $this->CI->session->set_userdata('language','ase');
        $phrase=ha_legacy_phrase('unseen_test_phrase');
        $this->assertEquals('Unseen test phrase',$phrase); $this->assertEquals($before,$this->db->list_fields('language'));
        $this->assertEquals(0,$this->db->where('phrase','unseen_test_phrase')->count_all_results('language'));
    }
    public function test_stable_keys_survive_reordering_and_reject_collisions() {
        $R=$this->CI->ha_library_review; $c=$R->course('dy-active-listening'); $keys=$R->keys($c);
        $c['locales']['en']['chapters']=array_reverse($c['locales']['en']['chapters']);
        $after=$R->keys($c);
        $before_keys=array_keys($keys['lessons']); $after_keys=array_keys($after['lessons']); sort($before_keys); sort($after_keys);
        $this->assertEquals($before_keys,$after_keys);
        $c['locales']['en']['chapters'][]=$c['locales']['en']['chapters'][0];
        $this->assertThrows(function()use($R,$c){$R->keys($c);});
    }
    public function test_reviewed_import_reuses_ids_and_keeps_other_languages() {
        require_once APPPATH.'seeds/009_library.php'; $R=$this->CI->ha_library_review;
        $c=$R->course('dy-professional-compliments'); $code='dy-'.$c['slug']; $keys=$R->keys($c);
        $ids=array(); foreach ($keys['lessons'] as $key=>$unused) { $ids[$key]=$R->identity('lesson',$key,$code); }
        $first=reset($ids); $this->db->where(array('lesson_id'=>$first,'locale'=>'ar'))->update('ha_lesson_translation',array('title'=>'Existing Arabic review'));
        $c['locales']['en']['chapters']=array_reverse($c['locales']['en']['chapters']);
        $c['locales']['en']['chapters'][0]['lessons'][0]['objectives']=array('Recognize appropriate compliments');
        $objective_key=$c['locales']['en']['chapters'][0]['lessons'][0]['source_key'];
        $seed=new Seed_library(); $seed->import_reviewed($this->db,$c,'en'); $seed->import_reviewed($this->db,$c,'en');
        foreach ($ids as $key=>$id) { $this->assertEquals($id,$R->identity('lesson',$key,$code)); }
        $this->assertEquals('Recognize appropriate compliments',$this->db->get_where('ha_lesson_translation',array('lesson_id'=>$R->identity('lesson',$objective_key,$code),'locale'=>'en'))->row('objective'));
        $this->assertEquals('Existing Arabic review',$this->db->get_where('ha_lesson_translation',array('lesson_id'=>$first,'locale'=>'ar'))->row('title'));
        $this->assertEquals(count($ids),$this->db->where(array('course_id'=>$this->db->get_where('ha_course',array('code'=>$code))->row('id'),'status'=>'published'))->count_all_results('ha_lesson'));
    }
    public function test_pdf_quiz_grading_retries_unlocking_and_localized_answers() {
        $this->CI->load->library(array('ha_auth','ha_learning','ha_theory')); $this->CI->load->helper('hkp');
        $uid=(int)$this->db->get_where('users',array('email'=>'demo.learner@altusdemo.sa'))->row('id');
        $this->CI->ha_auth->assume($uid);
        require_once APPPATH.'seeds/009_library.php'; $c=$this->CI->ha_library_review->course('dy-offering-service');
        $seed=new Seed_library(); $seed->import_reviewed($this->db,$c);
        $course=$this->db->get_where('ha_course',array('code'=>'dy-offering-service'))->row_array();
        $lessons=$this->db->order_by('sort_order')->get_where('ha_lesson',array('course_id'=>$course['id'],'status'=>'published'))->result_array();
        $L=$this->CI->ha_learning; $T=$this->CI->ha_theory;
        $this->assertThrows(function()use($L,$uid,$lessons){$L->open_lesson($uid,$lessons[1]['id']);});
        $this->assertThrows(function()use($T,$uid,$lessons){$T->start($lessons[1]['assessment_id'],$uid);});
        $L->open_lesson($uid,$lessons[0]['id']);
        $this->assertThrows(function()use($L,$uid,$lessons){$L->complete_lesson($uid,$lessons[0]['id']);});
        $failed=$T->start($lessons[0]['assessment_id'],$uid); $result=$T->submit($failed,array(),$uid);
        $this->assertEquals(0,(int)$result['passed']);
        $att=$T->start($lessons[0]['assessment_id'],$uid); $this->assertTrue($att!==$failed);
        $paper=$T->paper($att,$uid); $answers=array(); $ids=array();
        foreach ($paper['questions'] as $q) {
            $ids[$q['id']]=array_column($q['options'],'id');
            $answers[$q['id']]=(int)$this->db->get_where('ha_question_option',array('question_id'=>$q['id'],'is_correct'=>1))->row('id');
            $this->db->insert('ha_i18n_text',array('entity'=>'question','entity_id'=>$q['id'],'field'=>'body','locale'=>'hi','value'=>'Reviewed Hindi question','source'=>'human','updated_at'=>date('Y-m-d H:i:s')));
        }
        hkp_locale('hi');
        $localized=$T->paper($att,$uid);
        foreach ($localized['questions'] as $q) { $this->assertEquals($ids[$q['id']],array_column($q['options'],'id')); $this->assertEquals('Reviewed Hindi question',$q['body']); }
        hkp_locale('en');
        $result=$T->submit($att,$answers,$uid); $this->assertEquals(1,(int)$result['passed']);
        $L->complete_lesson($uid,$lessons[0]['id']); $this->assertNotEmpty($L->open_lesson($uid,$lessons[1]['id']));
        $this->assertEquals(75,(int)$T->assessment($lessons[0]['assessment_id'])['pass_percentage']);
        $this->assertEquals(10,(int)$T->assessment($lessons[0]['assessment_id'])['max_attempts']);
        for ($n=0;$n<10;$n++) { $id=$T->start($lessons[1]['assessment_id'],$uid); $T->submit($id,array(),$uid); }
        $this->assertThrows(function()use($T,$uid,$lessons){$T->start($lessons[1]['assessment_id'],$uid);});
    }
    public function test_legacy_bridge_selects_one_video_and_retires_records_without_losing_ids() {
        require_once APPPATH.'controllers/Ha_bridge.php'; require_once APPPATH.'seeds/009_library.php';
        $c=$this->CI->ha_library_review->course('dy-guest-empathy'); $seed=new Seed_library(); $seed->import_reviewed($this->db,$c);
        $course=$this->db->select('c.*,t.title,t.description,t.short_description,t.requirements,cat.code AS category_code')->from('ha_course c')
            ->join('ha_course_translation t',"t.course_id=c.id AND t.locale='en'")->join('ha_category cat','cat.id=c.category_id','left')->where('c.code','dy-guest-empathy')->get()->row_array();
        $reflection=new ReflectionClass('Ha_bridge'); $bridge=$reflection->newInstanceWithoutConstructor(); $bridge->db=$this->db;
        $now=$reflection->getProperty('now'); $now->setAccessible(true); $now->setValue($bridge,time());
        $make=$reflection->getMethod('sync_course'); $make->setAccessible(true); $legacy=(int)$make->invoke($bridge,$course,array(),array());
        $sync=$reflection->getMethod('sync_curriculum'); $sync->setAccessible(true);
        $lesson=$this->db->order_by('sort_order')->get_where('ha_lesson',array('course_id'=>$course['id'],'status'=>'published'))->row_array();
        foreach (array('en','ar') as $loc) {
            $this->db->insert('ha_lesson_video_source',array('lesson_id'=>$lesson['id'],'provider'=>'youtube','video_id'=>'cSohjlYQI2A','locale'=>$loc,'watch_url'=>'https://www.youtube.com/watch?v=cSohjlYQI2A','embed_url'=>'https://www.youtube-nocookie.com/embed/cSohjlYQI2A','status'=>'live','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')));
        }
        $counts=$sync->invoke($bridge,$course,$legacy);
        $total=(int)$this->db->where(array('course_id'=>$course['id'],'status'=>'published'))->count_all_results('ha_lesson');
        $this->assertEquals(2*$total,$counts['lessons']);
        // Sync refreshes updated_at; compare stable identities and ordering explicitly.
        $links=$this->db->select('legacy_table,legacy_id,ha_table,ha_id,legacy_course_id')->order_by('legacy_id')
            ->get_where('ha_lms_link',array('legacy_course_id'=>$legacy,'legacy_table'=>'lesson'))->result_array();
        $sync->invoke($bridge,$course,$legacy);
        $this->assertEquals($links,$this->db->select('legacy_table,legacy_id,ha_table,ha_id,legacy_course_id')->order_by('legacy_id')
            ->get_where('ha_lms_link',array('legacy_course_id'=>$legacy,'legacy_table'=>'lesson'))->result_array());
        $linked=$this->db->get_where('ha_lms_link',array('legacy_course_id'=>$legacy,'legacy_table'=>'lesson','ha_table'=>'ha_lesson','ha_id'=>$lesson['id']))->row_array();
        $this->db->where('id',$lesson['id'])->update('ha_lesson',array('status'=>'archived'));
        $sync->invoke($bridge,$course,$legacy);
        $retired=$this->db->get_where('lesson',array('id'=>$linked['legacy_id']))->row_array();
        $this->assertNotEmpty($retired); $this->assertNotEmpty($retired['ha_retired_at']);
        $this->CI->load->model('crud_model');
        $visible=array_column($this->CI->crud_model->get_lessons('course',$legacy)->result_array(),'id');
        $this->assertFalse(in_array($linked['legacy_id'],$visible));
    }
    public function test_surface_qa_expires_after_translation_changes() {
        $T=$this->CI->ha_global_translation; $u=$this->unit('test:qa','Welcome');
        $T->import($this->package($u,'ar','مرحبا'));
        $qa=$T->qa_package('ar','test:qa');
        $this->assertThrows(function()use($T,$qa){$T->qa_import($qa);});
        $qa['reviewer']='Human QA reviewer'; foreach ($qa['checklist'] as &$checked) { $checked=true; } unset($checked);
        $T->qa_import($qa); $this->assertTrue($T->qa_ready('ar','test:qa'));
        $T->import($this->package($u,'ar','أهلا'));
        $this->assertFalse($T->qa_ready('ar','test:qa'));
        $this->assertThrows(function()use($T,$qa){$T->qa_import($qa);});
    }
    public function test_release_versions_exclude_stale_language_claims() {
        $R=$this->CI->ha_library_review;
        $this->CI->load->library('ha_catalog');
        $id=(int)$this->db->select('id')->get_where('ha_course',array('code'=>'dy-active-listening'))->row('id');
        $this->db->replace('ha_course_locale_release',array('course_id'=>$id,'locale'=>'ar','status'=>'baseline'));
        $this->assertTrue($R->translation_current($id,'ar'));
        $signature=str_repeat('a',64);
        $this->db->replace('ha_library_active_revision',array('course_id'=>$id,'signature'=>$signature,'released_at'=>date('Y-m-d H:i:s')));
        $this->db->replace('ha_course_locale_release',array('course_id'=>$id,'locale'=>'en','status'=>'released','signature'=>$signature));
        $this->assertEquals(array('en'),$R->released_locales($id));
        $this->assertFalse($R->translation_current($id,'ar'));
        $this->assertFalse($this->CI->ha_catalog->is_translated('ha_course_translation','course_id',$id,'ar'));
        $this->db->where(array('course_id'=>$id,'locale'=>'ar'))->update('ha_course_locale_release',array('status'=>'released','signature'=>$signature));
        $this->assertTrue($R->translation_current($id,'ar'));
        $this->db->where('course_id',$id)->delete('ha_library_active_revision');
    }
    public function test_owned_video_changes_invalidate_playback_evidence() {
        $path='uploads/academy/library/videos/test-integrity-'.bin2hex(random_bytes(6)).'.mp4';
        if (!is_dir(dirname(FCPATH.$path))) { mkdir(dirname(FCPATH.$path),0775,true); }
        file_put_contents(FCPATH.$path,'test fixture');
        try {
            $row=array('provider'=>'upload','video_id'=>$path,'verification_json'=>json_encode(array('sha256'=>hash_file('sha256',FCPATH.$path))));
            $this->assertTrue(Ha_library_video::media_unchanged($row));
            file_put_contents(FCPATH.$path,'changed fixture');
            $this->assertFalse(Ha_library_video::media_unchanged($row));
        } finally { unlink(FCPATH.$path); }
    }
    public function test_release_source_references_follow_stable_lesson_and_question_keys() {
        $c=$this->CI->ha_library_review->course('dy-active-listening');
        $fixture=new Library_reference_fixture(); $fixture->candidate=$c;
        $method=new ReflectionMethod('Ha_library_review','annotate_sources'); $method->setAccessible(true);
        $result=$method->invoke($fixture,$c,'ar');
        foreach (array('en','ar') as $locale) {
            $lesson=$result['locales'][$locale]['chapters'][0]['lessons'][0];
            $this->assertTrue(strpos($lesson['body'],'#page=2')!==false);
            $this->assertTrue(strpos($lesson['quiz']['questions'][0]['explanation'],'Training.pdf · 2')!==false);
            $this->assertEquals($c['locales']['en']['chapters'][0]['lessons'][0]['quiz']['questions'][0]['answer'],$lesson['quiz']['questions'][0]['answer']);
        }
    }
}

/** In-memory provenance fixture, never modifies the real review package. */
class Library_reference_fixture extends Ha_library_review {
    public $candidate;
    public function manifest() { return array('sources'=>array(array('course_code'=>'dy-active-listening','duplicate_of'=>null,'sha256'=>str_repeat('a',64),'filename'=>'Training.pdf'))); }
    public function reviews() {
        $keys=$this->keys($this->candidate);
        return array('sources'=>array(str_repeat('a',64)=>array('pages'=>array('2'=>array('points'=>array(array('lesson_keys'=>array_keys($keys['lessons']),'question_keys'=>array_keys($keys['questions']))))))));
    }
}
