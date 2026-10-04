<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Test_admin_studio extends Ha_testcase {
    public function setUp() { $this->CI->load->library(array('ha_document_publisher', 'ha_website_studio', 'ha_website_navigation', 'ha_studio_catalogue')); $this->as_user('admin@hospitalityacademy.sa'); }
    private function as_user($email) { $id = (int) $this->db->get_where('users', array('email' => $email))->row('id'); $this->CI->ha_auth->assume($id); return $id; }
    private function package($title = 'Publisher integration example') {
        return array('title' => $title, 'summary' => 'Source supported knowledge.', 'modules' => array(array('title' => 'Preparation', 'lessons' => array(array('title' => 'Prepare safely', 'body' => '<p>Wear suitable PPE.</p><script>bad()</script>', 'minutes' => 5)))),
            'passing_score' => 80, 'quiz' => array(array('question' => 'What comes first?', 'options' => array('Wear PPE', 'Mix chemicals'), 'correct' => 0, 'explanation' => 'Protect yourself first.')));
    }
    public function test_publisher_package_is_atomic_idempotent_and_unpublished() {
        $P = $this->CI->ha_document_publisher;
        $id = $P->source(str_repeat('Wear PPE before cleaning. ', 10), 'Safe cleaning.txt', 'course', 'en');
        $version = $P->save($id, $this->package(), 1);
        $course = $P->materialize($id, $version);
        $this->assertEquals($course, $P->materialize($id, $version));
        $this->assertEquals('draft', $this->db->get_where('ha_course', array('id' => $course))->row('status'));
        $this->assertEquals(1, $this->db->where('course_id', $course)->count_all_results('ha_lesson'));
        $this->assertEquals(1, $this->db->where('course_id', $course)->count_all_results('ha_assessment'));
        $this->assertEquals('draft', $this->db->get_where('ha_assessment', array('course_id' => $course))->row('status'));
        $body = $this->db->select('t.body')->from('ha_lesson_translation t')->join('ha_lesson l', 'l.id = t.lesson_id')->where('l.course_id', $course)->get()->row('body');
        $this->assertTrue(strpos($body, '<script') === false);
    }
    public function test_publisher_conflicts_and_invalid_quizzes_make_no_content() {
        $P = $this->CI->ha_document_publisher;
        $id = $P->source(str_repeat('Safety standard. ', 10), 'Source.txt', 'course', 'en');
        $P->save($id, $this->package('Conflict example'), 1);
        $this->assertThrows(function () use ($P, $id) { $P->save($id, $this->package(), 1); });
        $bad = $this->package(); $bad['quiz'][0]['correct'] = 9;
        $this->assertThrows(function () use ($P, $bad) { $P->validate($bad, 'course'); });
        $this->assertThrows(function () use ($P, $id) { $P->materialize($id, 1); });
        $this->assertNull($P->find($id)['entity_id']);
    }
    public function test_publisher_source_is_private_and_permissions_are_enforced() {
        $P = $this->CI->ha_document_publisher;
        $id = $P->source(str_repeat('Private standard. ', 10), 'Private.txt', 'course', 'en');
        $this->as_user('instructor.fo@hospitalityacademy.sa');
        $this->assertThrows(function () use ($P, $id) { $P->find($id); });
        $this->as_user('demo.learner@altusdemo.sa');
        $this->assertThrows(function () use ($P) { $P->source(str_repeat('A standard. ', 10), 'Source', 'course', 'en'); });
    }
    public function test_extraction_rejects_mime_spoof_and_executable_extensions() {
        $path = tempnam(sys_get_temp_dir(), 'altus'); file_put_contents($path, str_repeat('Plain text source ', 10));
        $P = $this->CI->ha_document_publisher;
        $this->assertNotEmpty($P->extract($path, 'source.md'));
        $this->assertThrows(function () use ($P, $path) { $P->extract($path, 'source.pdf'); });
        $this->assertThrows(function () use ($P, $path) { $P->extract($path, 'source.php'); }); unlink($path);
    }
    public function test_live_drafts_do_not_change_public_content_and_stale_updates_fail() {
        $S = $this->CI->ha_website_studio; $id = (int) $this->db->get_where('ha_page', array('code' => 'about'))->row('id');
        $state = $S->state($id); $old = $state['payload']['tr']['en']['title'];
        $state['payload']['tr']['en']['title'] = 'Private website title';
        $v = $S->save($id, $state['payload'], $state['version'], $state['base_hash']);
        $this->assertEquals($old, $this->db->get_where('ha_page_translation', array('page_id' => $id, 'locale' => 'en'))->row('title'));
        $this->assertThrows(function () use ($S, $id, $state) { $S->save($id, $state['payload'], $state['version'], $state['base_hash']); });
        $S->publish($id, $v);
        $this->assertEquals('Private website title', $this->db->get_where('ha_page_translation', array('page_id' => $id, 'locale' => 'en'))->row('title'));
        $this->assertEquals(0, $S->state($id)['version']);
        $this->assertGreaterThan(0, $this->db->where('page_id', $id)->count_all_results('ha_page_revision'));
    }
    public function test_live_publishing_rechecks_the_public_revision_and_platform_scope() {
        $S = $this->CI->ha_website_studio; $id = (int) $this->db->get_where('ha_page', array('code' => 'contact'))->row('id');
        $s = $S->state($id); $v = $S->save($id, $s['payload'], $s['version'], $s['base_hash']);
        $this->db->where(array('page_id' => $id, 'locale' => 'en'))->update('ha_page_translation', array('subtitle' => 'Changed by another editor'));
        $this->assertThrows(function () use ($S, $id, $v) { $S->publish($id, $v); });
        $this->as_user('org.admin@dyafagroup.sa'); $this->assertThrows(function () use ($S, $id) { $S->state($id); });
    }
    public function test_live_validation_blocks_unsafe_links_and_cleans_html() {
        $S = $this->CI->ha_website_studio;
        $p = array('tr' => array('en' => array('title' => 'A page', 'body' => '<p>Clean</p><script>bad()</script>')), 'sections' => array());
        $this->assertTrue(strpos($S->validate($p)['tr']['en']['body'], '<script') === false);
        $p['tr']['en']['cta_url'] = 'javascript:alert(1)'; $this->assertThrows(function () use ($S, $p) { $S->validate($p); });
    }
    public function test_corporate_home_initial_state_preserves_the_native_source_and_binding_is_explicit() {
        $S = $this->CI->ha_website_studio; $id = (int) $this->db->get_where('ha_page', array('code' => 'home'))->row('id');
        $s = $S->state($id); $native = $this->db->get_where('ha_corporate_block', array('section' => 'home_hero'))->row_array();
        $this->assertEquals($native['title_en'], $s['payload']['tr']['en']['title']); $this->assertEquals(0, $s['page']['studio_enabled']);
        $s['payload']['tr']['en']['title'] = 'Reviewed corporate homepage'; $v = $S->save($id, $s['payload'], 0, $s['base_hash']); $S->publish($id, $v);
        $this->assertEquals(1, $S->state($id)['page']['studio_enabled']); $this->assertEquals('Reviewed corporate homepage', $S->state($id)['payload']['tr']['en']['title']);
        // Explicit publication writes the reviewed hero back to its native corporate record (gap: corporate homepage binding).
        $this->assertEquals('Reviewed corporate homepage', $this->db->get_where('ha_corporate_block', array('id' => $native['id']))->row('title_en'));
    }
    public function test_stale_private_draft_can_be_discarded_without_changing_public_content() {
        $S = $this->CI->ha_website_studio; $id = (int) $this->db->get_where('ha_page', array('code' => 'credits'))->row('id');
        $s = $S->state($id); $v = $S->save($id, $s['payload'], 0, $s['base_hash']);
        $this->db->where(array('page_id' => $id, 'locale' => 'en'))->update('ha_page_translation', array('title' => 'Newer published credits'));
        $this->assertThrows(function () use ($S, $id, $v) { $S->discard($id, $v + 1); }); $S->discard($id, $v);
        $s = $S->state($id); $this->assertEquals(0, $s['version']); $this->assertEquals('Newer published credits', $s['payload']['tr']['en']['title']);
        $this->assertGreaterThan(0, $S->save($id, $s['payload'], 0, $s['base_hash']));
    }
    public function test_live_publication_creates_a_missing_reviewed_translation() {
        $P = $this->CI->ha_document_publisher; $S = $this->CI->ha_website_studio;
        $source = $P->source(str_repeat('Source page content. ', 10), 'Source page.txt', 'page', 'en');
        $v = $P->save($source, array('title' => 'Bilingual publisher page', 'summary' => 'A source-based page.', 'sections' => array(array('heading' => 'Introduction', 'body' => '<p>Content.</p>'))), 1);
        $id = $P->materialize($source, $v); $state = $S->state($id);
        $this->assertFalse(isset($state['payload']['tr']['ar']));
        $state['payload']['tr']['ar'] = array('title' => 'صفحة ثنائية اللغة', 'body' => '<p>محتوى تمت مراجعته.</p>');
        $v = $S->save($id, $state['payload'], 0, $state['base_hash']); $S->publish($id, $v);
        $this->assertEquals('صفحة ثنائية اللغة', $S->state($id)['payload']['tr']['ar']['title']);
        $this->assertEquals('published', $this->db->get_where('ha_page', array('id' => $id))->row('status'));
    }
    public function test_navigation_preserves_required_urls_and_foreign_items() {
        $N = $this->CI->ha_website_navigation; $menu = $this->db->get_where('ha_menu', array('code' => 'footer_learn'))->row('id'); $items = $N->items($menu);
        foreach ($items as &$item) { $item['visible'] = 1; } unset($item);
        $items[0]['url_en'] = 'unknown'; $this->assertThrows(function () use ($N, $menu, $items) { $N->save($menu, $items); });
        $items[0]['url_en'] = 'courses'; $items[0]['label_en'] = 'Professional courses'; $N->save($menu, $items);
        $this->assertEquals('Professional courses', $N->items($menu)[0]['label_en']);
        $items[0]['id'] = 999999; $this->assertThrows(function () use ($N, $menu, $items) { $N->save($menu, $items); });
    }
    public function test_path_sequence_preserves_ids_and_rejects_foreign_steps_and_items() {
        $C = $this->CI->ha_studio_catalogue; $course = (int) $C->path_items()['course'][0]['id'];
        $step = array('id' => 0, 'title_en' => 'Preparation', 'title_ar' => 'الاستعداد', 'items' => array(array('item_type' => 'course', 'item_id' => $course, 'is_mandatory' => 1)));
        $in = array('title_en' => 'Studio path', 'title_ar' => 'مسار', 'slug_en' => 'studio-path-unit', 'slug_ar' => 'studio-path-unit-ar', 'status' => 'draft', 'steps_json' => json_encode(array($step)));
        $id = $C->save('paths', 0, $in); $record = $C->record('paths', $id); $sid = $record['steps'][0]['id'];
        $this->assertEquals(1, count($record['steps'][0]['items'])); $this->assertEquals($course, $record['steps'][0]['items'][0]['item_id']);
        $in['version'] = $C->hash($record); $step['id'] = $sid; $step['title_en'] = 'Updated preparation'; $in['steps_json'] = json_encode(array($step)); $C->save('paths', $id, $in);
        $record = $C->record('paths', $id); $this->assertEquals($sid, $record['steps'][0]['id']); $this->assertEquals('Updated preparation', $record['steps'][0]['title_en']);
        $in['version'] = $C->hash($record); $step['id'] = 999999; $in['steps_json'] = json_encode(array($step)); $this->assertThrows(function () use ($C, $id, $in) { $C->save('paths', $id, $in); });
        $step['id'] = $sid; $step['items'][0]['item_id'] = 999999; $in['steps_json'] = json_encode(array($step)); $this->assertThrows(function () use ($C, $id, $in) { $C->save('paths', $id, $in); });
        $this->assertEquals($course, $C->record('paths', $id)['steps'][0]['items'][0]['item_id']);
    }
    public function test_enrolled_path_steps_cannot_be_removed() {
        $C = $this->CI->ha_studio_catalogue; $id = (int) $this->db->get('ha_learning_path')->row('id'); $r = $C->record('paths', $id);
        $this->db->insert('ha_path_enrollment', array('user_id' => $this->CI->ha_auth->id(), 'path_id' => $id, 'current_step_id' => $r['steps'][0]['id'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')));
        $in = array('title_en' => $r['row']['title_en'], 'title_ar' => $r['row']['title_ar'], 'slug_en' => $r['row']['slug_en'], 'slug_ar' => $r['row']['slug_ar'], 'version' => $C->hash($r), 'status' => 'draft', 'steps_json' => '[]');
        $this->assertThrows(function () use ($C, $id, $in) { $C->save('paths', $id, $in); });
        $this->assertEquals(count($r['steps']), count($C->record('paths', $id)['steps']));
    }
    public function test_catalogue_drafts_have_independent_translations_and_conflict_checks() {
        $C = $this->CI->ha_studio_catalogue;
        $in = array('title_en' => 'Draft article', 'title_ar' => 'مقالة مسودة', 'slug_en' => 'draft-article-studio', 'slug_ar' => 'draft-article-studio-ar', 'body_en' => '<p>Source.</p>', 'body_ar' => '<p>المصدر.</p>', 'status' => 'draft');
        $id = $C->save('articles', 0, $in); $r = $C->record('articles', $id);
        $this->assertEquals('draft', $r['row']['status']); $this->assertEquals('مقالة مسودة', $r['tr']['ar']['title']);
        $this->assertThrows(function () use ($C, $id, $in) { $C->save('articles', $id, $in); });
        $in['version'] = $C->hash($r); $in['title_en'] = 'Updated article'; $C->save('articles', $id, $in);
        $this->assertEquals('مقالة مسودة', $C->record('articles', $id)['tr']['ar']['title']);
        $this->as_user('org.admin@dyafagroup.sa'); $this->assertThrows(function () use ($C, $id) { $C->record('articles', $id); });
    }
}
