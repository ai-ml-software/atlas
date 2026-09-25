<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Page builder, SEO/AEO/GEO scoring, lesson drip release, AI help permissions
 * and Arabic interface completeness.
 */
class Test_cms extends Ha_testcase {

    public function setUp() {
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_page_builder', 'ha_seo_score', 'ha_learning', 'ha_ai_assist'));
    }

    private function as_user($email) {
        $id = (int) $this->db->get_where('users', array('email' => $email))->row()->id;
        $this->CI->ha_auth->assume($id);
        return $id;
    }

    private function page_id() {
        return (int) $this->db->get_where('ha_page', array('code' => 'about'))->row()->id;
    }

    public function test_sections_save_reorder_and_restore() {
        $uid = $this->as_user('admin@hospitalityacademy.sa');
        $B = $this->CI->ha_page_builder;
        $pid = $this->page_id();
        $base = count($B->sections($pid, false));
        $base_faq = count($B->faq_items($pid, 'en'));
        $a = $B->save_section($pid, 0, 'rich_text', $B->content_from_input('rich_text', array('heading' => 'Why us', 'body' => '<p>Evidence over intuition.</p><script>x()</script>')),
            $B->content_from_input('rich_text', array('heading' => 'لماذا نحن')), array(), $uid);
        $b = $B->save_section($pid, 0, 'faq', $B->content_from_input('faq', array('heading' => 'FAQ', 'items' => "What is readiness? | Readiness shows whether evidence proves a person can work to standard.\nHow is it calculated? | From learning, assessments, practical competency and gaps.")),
            array(), array(), $uid);
        $B->reorder($pid, array($b, $a));
        $rows = array_values(array_filter($B->sections($pid, false), function ($r) use ($a, $b) { return in_array((int) $r['id'], array($a, $b), true); }));
        $this->assertEquals($b, (int) $rows[0]['id'], 'Sections follow the dragged order');
        $this->assertCount(2, $rows[0]['en']['items'], 'Repeatable lines become items');
        $this->assertNotContains('<script', hkp_safe_html($rows[1]['en']['body']), 'Section HTML is sanitised on output');
        $this->assertCount($base_faq + 2, $B->faq_items($pid, 'en'), 'FAQ sections feed FAQPage schema');
        $this->assertCount($base_faq + 2, $B->faq_items($pid, 'ar'), 'An empty Arabic FAQ falls back to English');
        $rev = (int) $this->db->select_max('id')->get_where('ha_page_revision', array('page_id' => $pid))->row()->id;
        $B->delete($pid, $a, $uid);
        $this->assertCount($base + 1, $B->sections($pid, false));
        $B->restore($pid, $rev, $uid);
        $this->assertCount($base + 2, $B->sections($pid, false), 'A revision restores the deleted section');
        $this->assertThrows(function () use ($B, $pid, $uid) { $B->save_section($pid, 0, 'cta', array(), array(), array(), $uid); }, 'Required fields are enforced');
    }

    public function test_optimisation_score_rises_as_fixes_are_applied() {
        $this->as_user('admin@hospitalityacademy.sa');
        $pid = $this->page_id();
        $before = $this->CI->ha_seo_score->score($pid, 'en');
        $this->assertCount(26, $before['checks'], 'Every check is reported');
        $this->db->where('id', $pid)->update('ha_page', array('focus_keyword_en' => 'hotel training', 'geo_region' => 'SA-01', 'geo_placename' => 'Riyadh',
            'geo_lat' => 24.7136, 'geo_lng' => 46.6753, 'schema_type' => 'AboutPage'));
        $after = $this->CI->ha_seo_score->score($pid, 'en');
        $this->assertGreaterThan($before['scores']['geo'], $after['scores']['geo'], 'Setting the place raises the GEO score');
        foreach ($after['checks'] as $c) {
            if (!$c['passed']) {
                $this->assertNotEmpty($c['fix'], 'A failing check says how to fix it');
            }
        }
        $this->assertDatabaseHas('ha_page', array('id' => $pid, 'seo_score_en' => $after['scores']['overall']));
    }

    public function test_drip_release_blocks_until_due() {
        $uid = $this->as_user('omar.learner@dyafagroup.sa');
        $lesson = $this->db->select('l.*')->from('ha_lesson l')->join('ha_course c', 'c.id = l.course_id')->where('c.status', 'published')
            ->where('l.status', 'published')->where('c.organization_id IS NULL', null, false)->limit(1)->get()->row_array();
        $this->db->where('id', $lesson['id'])->update('ha_lesson', array('drip_days' => 7));
        $e = $this->CI->ha_learning->enroll($uid, $lesson['course_id'], 'self');
        $this->db->where('id', $e)->update('ha_enrollment', array('created_at' => date('Y-m-d H:i:s')));
        $this->assertThrows(function () use ($uid, $lesson) { $this->CI->ha_learning->open_lesson($uid, $lesson['id']); }, 'A drip lesson is closed before its day');
        $this->db->where('id', $e)->update('ha_enrollment', array('created_at' => date('Y-m-d H:i:s', strtotime('-8 days'))));
        $open = $this->CI->ha_learning->open_lesson($uid, $lesson['id']);
        $this->assertNotEmpty($open['progress'], 'It opens once the drip period has passed');
        $this->db->where('id', $lesson['id'])->update('ha_lesson', array('drip_days' => null, 'available_from' => date('Y-m-d H:i:s', strtotime('+2 days'))));
        $this->assertNotNull($this->CI->ha_learning->release_at($this->db->get_where('ha_lesson', array('id' => $lesson['id']))->row_array(), $e), 'A fixed date also gates release');
        $this->db->where('id', $lesson['id'])->update('ha_lesson', array('available_from' => null));
    }

    public function test_ai_help_needs_permission_and_a_connected_model() {
        $this->as_user('omar.learner@dyafagroup.sa');
        $this->assertThrows(function () { $this->CI->ha_ai_assist->run(array('task' => 'section', 'prompt' => 'x')); }, 'Learners cannot use editor AI');
        $this->as_user('admin@hospitalityacademy.sa');
        try {
            $this->CI->ha_ai_assist->run(array('task' => 'section', 'prompt' => 'About us', 'provider' => 'definitely_not_enabled'));
            $this->fail('A disabled provider must be refused');
        } catch (RuntimeException $e) {
            $this->assertContains('not enabled', $e->getMessage());
        }
        $this->assertCount(9, Ha_ai_assist::tasks(), 'Enhance prompt plus eight writing tasks');
    }

    public function test_admin_lists_never_show_another_tenants_records() {
        $this->as_user('org.admin@dyafagroup.sa');
        $this->CI->load->library('ha_crud');
        $org = (int) $this->db->get_where('ha_profile', array('user_id' => $this->CI->ha_auth->id()))->row()->organization_id;
        $foreign = $this->db->where('organization_id IS NOT NULL', null, false)->where('organization_id !=', $org)->get('ha_track')->row_array();
        $e = $this->CI->ha_crud->entity('tracks');
        $list = $this->CI->ha_crud->rows($e, array());
        foreach ($list['rows'] as $r) {
            $this->assertTrue($r['organization_id'] === null || (int) $r['organization_id'] === $org, 'Track ' . $r['code'] . ' belongs to another client');
        }
        if ($foreign) {
            $this->assertNull($this->CI->ha_crud->find($e, $foreign['id']), 'Another client\'s track cannot be opened');
        }
        $visible = $this->CI->ha_auth->visible_user_ids();
        foreach ($this->CI->ha_crud->options('fk:users:email') as $o) {
            $this->assertContains((int) $o['id'], array_map('intval', $visible), 'User pickers only offer people in scope');
        }
    }

    public function test_performance_api_is_tenant_scoped() {
        $this->CI->load->library('ha_api_hkp');
        $api = $this->CI->ha_api_hkp;
        $learner = $this->as_user('omar.learner@dyafagroup.sa');
        $mine = $api->competencies();
        $this->assertEquals($learner, $mine['user_id'], 'Without user_id the caller\'s own profile is returned');
        $this->assertNotEmpty($mine['competencies'], 'The learner has role requirements');
        $this->assertTrue(!isset($mine['competencies'][0]['skill_id']), 'Internal ids are not exposed');
        $other = (int) $this->db->get_where('users', array('email' => 'demo.learner@altusdemo.sa'))->row()->id;
        $this->assertThrows(function () use ($api, $other) { $api->readiness($other); }, 'A learner cannot read someone else\'s readiness');
        foreach ($api->certificates() as $c) {
            $this->assertEquals($learner, $c['user_id'], 'A learner only lists their own certificates');
        }
        $this->as_user('demo.gm@altusdemo.sa');
        $people = array_map(function ($p) { return (int) $p['id']; }, $api->people());
        $this->assertContains($other, $people, 'The GM sees their own team');
        $this->assertNotContains($learner, $people, 'The GM never sees another client\'s staff');
        foreach ($api->gaps() as $g) {
            $this->assertContains($g['user_id'], $people, 'Gaps are limited to people in scope');
        }
        $this->assertThrows(function () use ($api, $learner) { $api->competencies($learner); }, 'Another client\'s employee is refused');
        $dyafa_property = (int) $this->db->select('property_id')->get_where('ha_profile', array('user_id' => $learner))->row()->property_id;
        $this->assertThrows(function () use ($api, $dyafa_property) { $api->kpis($dyafa_property); }, 'KPIs of another client\'s property are refused');
        $own = $api->kpis();
        $this->assertNotEmpty($own['kpis'], 'The GM\'s own property scorecard is returned');
    }

    public function test_every_interface_string_has_arabic() {
        require_once APPPATH . 'libraries/Ha_i18n_keys.php';
        $dict = include APPPATH . 'language/arabic/hkp_lang.php';
        $missing = array();
        foreach (Ha_i18n_keys::collect() as $k) {
            if (!isset($dict[$k]) || trim((string) $dict[$k]) === '') {
                $missing[] = $k;
            }
        }
        $this->assertEmpty($missing, count($missing) . ' strings have no Arabic: ' . implode(' | ', array_slice($missing, 0, 15)));
        foreach ($dict as $en => $ar) {
            if (preg_match_all('/\{[a-z_]+\}/', $en, $m)) {
                foreach ($m[0] as $ph) {
                    if (strpos($ar, $ph) === false) {
                        $this->fail('Placeholder ' . $ph . ' lost in the Arabic for "' . $en . '"');
                    }
                }
            }
        }
        hkp_locale('ar');
        $this->assertEquals($dict['Dashboard'], hkp_t('Dashboard'), 'Arabic is served in Arabic mode');
        hkp_locale('en');
    }
}
