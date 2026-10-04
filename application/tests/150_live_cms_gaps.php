<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Live CMS gaps: explicit click-to-edit markers, corporate homepage drafts,
 * theme/site settings, revision restore, signed preview links, dashboard
 * comparison and reversible 026/027 migrations.
 */
class Test_live_cms_gaps extends Ha_testcase {
    public function setUp() {
        $this->CI->load->library(array('ha_website_studio', 'ha_content_studio', 'ha_studio_preview'));
        $this->CI->load->helper('ha_studio');
        $this->as_user('admin@hospitalityacademy.sa');
    }
    private function as_user($email) { $id = (int) $this->db->get_where('users', array('email' => $email))->row('id'); $this->CI->ha_auth->assume($id); return $id; }
    private function home() { return (int) $this->db->get_where('ha_page', array('code' => 'home'))->row('id'); }

    public function test_click_to_edit_markers_are_explicit_and_only_in_preview() {
        ha_studio_mode(false);
        $this->assertEquals('', ha_studio('title'));
        ha_studio_mode(true);
        $this->assertEquals(' data-studio-field="title" data-studio-kind="text"', ha_studio('title'));
        $this->assertContains('data-studio-kind="image"', ha_studio('hero_image', 'image'));
        $this->assertContains('&quot;', ha_studio('x"y'), 'field names are escaped');
        $this->assertContains('data-studio-kind="text"', ha_studio('title', 'script'), 'unknown kinds fall back to text');
        ha_studio_mode(false);
        $layout = file_get_contents(APPPATH . 'views/academy/layout.php');
        $this->assertFalse(strpos($layout, 'preg_replace') !== false, 'layout no longer tags markup with regular expressions');
        foreach (array('_hero' => "ha_studio('title')", 'page' => "ha_studio('body', 'html')", 'home_altus' => '$cf(', '_sections' => "'s:' . \$sk", 'article' => "ha_studio('hero_image', 'image')",
                       'course' => "ha_studio('subtitle')", 'topic' => "ha_studio('body', 'html')", 'program' => "ha_studio('body')", 'path' => "ha_studio('body')") as $view => $marker) {
            $this->assertContains($marker, file_get_contents(APPPATH . 'views/academy/' . $view . '.php'), $view . ' carries explicit markers');
        }
    }

    public function test_sections_render_inner_markers_for_cards_and_images_in_preview_only() {
        $section = array('studio_key' => 'section-test', 'section_type' => 'cards', 'is_visible' => 1, 'settings' => array('columns' => 3),
            'en' => array('heading' => 'Cards heading', 'items' => array(array('title' => 'First card', 'text' => 'Card text', 'link' => ''))), 'ar' => array());
        $hero = array('studio_key' => 'section-hero', 'section_type' => 'hero', 'is_visible' => 1, 'settings' => array('image' => 'uploads/x.webp'), 'en' => array('heading' => 'Hero', 'lede' => 'Lede'), 'ar' => array());
        ha_studio_mode(true);
        $html = $this->CI->load->view('academy/_sections', array('sections' => array($section, $hero), 'locale' => 'en'), true);
        ha_studio_mode(false);
        $this->assertContains('data-studio-section="section-test"', $html);
        $this->assertContains('data-studio-field="s:section-test:heading"', $html);
        $this->assertContains('data-studio-field="s:section-test:items:0:title"', $html);
        $this->assertContains('data-studio-field="s:section-test:items:0:text"', $html);
        $this->assertContains('data-studio-field="s:section-hero:image" data-studio-kind="image"', $html);
        $public = $this->CI->load->view('academy/_sections', array('sections' => array($section), 'locale' => 'en'), true);
        $this->assertNotContains('data-studio', $public, 'visitors receive no editor markup');
    }

    public function test_corporate_homepage_blocks_are_drafted_privately_and_written_back_on_publish() {
        $S = $this->CI->ha_website_studio; $id = $this->home();
        $block = $this->db->where_in('section', Ha_website_studio::corporate_sections())->where(array('status' => 'published', 'visibility' => 'public'))->where('section', 'about')->get('ha_corporate_block')->row_array()
            ?: $this->db->where_in('section', Ha_website_studio::corporate_sections())->where(array('status' => 'published', 'visibility' => 'public'))->get('ha_corporate_block')->row_array();
        $this->assertNotEmpty($block, 'seeded corporate homepage blocks exist');
        $s = $S->state($id);
        $this->assertTrue(isset($s['payload']['corporate'][(string) $block['id']]), 'home draft carries every corporate block');
        $s['payload']['corporate'][(string) $block['id']]['body_en'] = 'Private corporate body';
        $s['payload']['corporate'][(string) $block['id']]['title_ar'] = 'عنوان خاص';
        $v = $S->save($id, $s['payload'], $s['version'], $s['base_hash']);
        $this->assertEquals($block['body_en'], $this->db->get_where('ha_corporate_block', array('id' => $block['id']))->row('body_en'), 'draft is private');
        $this->assertEquals('Private corporate body', $S->state($id)['payload']['corporate'][(string) $block['id']]['body_en']);
        $S->publish($id, $v);
        $row = $this->db->get_where('ha_corporate_block', array('id' => $block['id']))->row_array();
        $this->assertEquals('Private corporate body', $row['body_en']);
        $this->assertEquals('عنوان خاص', $row['title_ar']);
        $bad = $S->state($id)['payload']; $bad['corporate']['not-a-number'] = array('title_en' => 'x');
        $this->assertThrows(function () use ($S, $bad) { $S->validate($bad); });
        $bad = $S->state($id)['payload']; $bad['corporate'][(string) $block['id']]['title_en'] = str_repeat('a', 501);
        $this->assertThrows(function () use ($S, $bad) { $S->validate($bad); });
        // A block outside the homepage sections is never written, even when injected into a draft.
        $other = $this->db->where_not_in('section', array_merge(array('home_hero'), Ha_website_studio::corporate_sections()))->get('ha_corporate_block')->row_array();
        if ($other) {
            $s = $S->state($id); $s['payload']['corporate'][(string) $other['id']] = array('section' => 'x', 'code' => 'x', 'title_en' => 'Injected', 'body_en' => '', 'title_ar' => '', 'body_ar' => '');
            $S->publish($id, $S->save($id, $s['payload'], $s['version'], $s['base_hash']));
            $this->assertNotEquals('Injected', $this->db->get_where('ha_corporate_block', array('id' => $other['id']))->row('title_en'));
        }
    }

    public function test_corporate_change_elsewhere_invalidates_the_home_draft() {
        $S = $this->CI->ha_website_studio; $id = $this->home(); $s = $S->state($id);
        $v = $S->save($id, $s['payload'], $s['version'], $s['base_hash']);
        $bid = (int) key($s['payload']['corporate']);
        $this->db->where('id', $bid)->update('ha_corporate_block', array('body_en' => 'Edited in the workspace'));
        $this->assertThrows(function () use ($S, $id, $v) { $S->publish($id, $v); }, 'concurrent corporate edit is a conflict');
        $S->discard($id, $v);
    }

    public function test_theme_site_settings_validate_and_stay_private_until_published() {
        $S = $this->CI->ha_content_studio;
        $p = Ha_content_studio::defaults();
        foreach (array('heading_font' => 'Comic Sans', 'navigation_style' => 'marquee', 'spacing' => 'huge', 'contact_email' => 'not-an-email', 'logo' => 'javascript:alert(1)', 'surface' => 'blue', 'logo2' => null) as $k => $v) {
            if ($v === null) { continue; }
            $bad = $p; $bad[$k] = $v; $this->assertThrows(function () use ($S, $bad) { $S->validate('site', $bad); }, 'rejects ' . $k);
        }
        $clean = $S->validate('site', array('seo_description' => '<b>Hotel</b> training', 'accent' => '#AABBCC') + $p);
        $this->assertEquals('Hotel training', $clean['seo_description']);
        $this->assertEquals('#aabbcc', $clean['accent']);
        $this->assertTrue(isset(Ha_content_studio::fonts()['fraunces']) && !isset(Ha_content_studio::fonts()['comic']));
        $s = $S->state('site', 1); $d = array_merge(Ha_content_studio::defaults(), $s['payload']);
        $d['site_name'] = 'Studio Draft Name'; $d['heading_font'] = 'fraunces'; $d['navigation_style'] = 'pill'; $d['contact_phone'] = '+966 500 000 000'; $d['logo'] = 'uploads/system/altus-logo-horizontal.png';
        $v = $S->save('site', 1, $d, $s['version'], $s['base_hash']);
        $published = $S->site_settings(false);
        $this->assertTrue($published === null || $published['site_name'] !== 'Studio Draft Name', 'visitors never see the draft');
        $this->assertEquals('Studio Draft Name', $S->site_settings(true)['site_name'], 'authorized preview sees the draft');
        $this->assertContains('Fraunces', $S->css(true));
        $S->publish('site', 1, $v);
        $this->assertEquals('Studio Draft Name', $S->site_settings(false)['site_name']);
        $css = $S->css(false);
        $this->assertContains("'Fraunces'", $css); $this->assertContains('border-radius:999px', $css); $this->assertContains('--ha-space-section', $css);
        $this->as_user('demo.learner@altusdemo.sa');
        $this->assertEquals('Studio Draft Name', $S->site_settings(true)['site_name'], 'a non-editor asking for preview gets the published version');
        $this->as_user('admin@hospitalityacademy.sa');
    }

    public function test_revision_restore_is_version_checked_and_permission_checked() {
        $S = $this->CI->ha_content_studio;
        $s = $S->state('site', 1); $d = array_merge(Ha_content_studio::defaults(), $s['payload']); $d['site_name'] = 'Revision one';
        $S->publish('site', 1, $S->save('site', 1, $d, $s['version'], $s['base_hash']));
        $rev = (int) $this->db->where(array('object_type' => 'site'))->order_by('id', 'DESC')->get('ha_studio_revision')->row('id');
        $s = $S->state('site', 1); $d['site_name'] = 'Revision two';
        $S->publish('site', 1, $S->save('site', 1, $d, $s['version'], $s['base_hash']));
        $s = $S->state('site', 1);
        $this->assertThrows(function () use ($S, $rev, $s) { $S->restore('site', 1, $rev, $s['version'] + 3, $s['base_hash']); }, 'stale version');
        $this->assertThrows(function () use ($S, $rev, $s) { $S->restore('site', 1, $rev, $s['version'], str_repeat('0', 64)); }, 'stale hash');
        $this->assertThrows(function () use ($S, $s) { $S->restore('site', 1, 99999999, $s['version'], $s['base_hash']); }, 'unknown revision');
        $this->as_user('org.admin@dyafagroup.sa');
        $this->assertThrows(function () use ($S, $rev, $s) { $S->restore('site', 1, $rev, $s['version'], $s['base_hash']); }, 'tenant users cannot restore platform revisions');
        $this->as_user('admin@hospitalityacademy.sa');
        $S->restore('site', 1, $rev, $s['version'], $s['base_hash']);
        $this->assertEquals('Revision one', $S->state('site', 1)['payload']['site_name'], 'restored into the draft');
        $this->assertEquals('Revision two', $S->snapshot('site', 1)['site_name'], 'published version unchanged until publication');
        $html = $this->CI->load->view('hkp/studio_revisions', array('rows' => array(), 'types' => array('site' => 'Theme'), 'type' => 'site', 'object_id' => 0, 'offset' => 0, 'more' => false), true);
        $this->assertContains('studio/revisions', $html);
    }

    public function test_preview_links_are_signed_bound_and_expire() {
        $P = $this->CI->ha_studio_preview;
        $t = $P->token('page', 12);
        $this->assertTrue($P->verify('page', 12, $t));
        $this->assertFalse($P->verify('page', 13, $t), 'bound to the object id');
        $this->assertFalse($P->verify('articles', 12, $t), 'bound to the object kind');
        $this->assertFalse($P->verify('page', 12, substr($t, 0, -1) . (substr($t, -1) === 'a' ? 'b' : 'a')), 'tampered');
        $this->assertFalse($P->verify('page', 12, ''), 'missing');
        $this->assertLessThanOrEqual(time() + Ha_studio_preview::TTL, Ha_studio_preview::expires_at($t));
        $mac = new ReflectionMethod('Ha_studio_preview', 'mac'); $mac->setAccessible(true);
        $past = time() - 10; $this->assertFalse($P->verify('page', 12, $past . '.' . $mac->invoke($P, 'page', 12, $past)), 'expired');
        $far = time() + 3 * 86400; $this->assertFalse($P->verify('page', 12, $far . '.' . $mac->invoke($P, 'page', 12, $far)), 'longer than 24 hours');
        $this->assertContains('studio_sig=', $P->sign_url('http://x/en/about?studio_preview=12', 'page', 12));
        $academy = file_get_contents(APPPATH . 'controllers/Academy.php');
        $this->assertContains("ha_studio_preview->verify('page'", $academy, 'page previews require a signature as well as an editor session');
        $this->assertContains('ha_studio_preview->verify($entity_types[$view]', $academy, 'entity previews require a signature');
    }

    public function test_dashboard_matches_reference_and_has_no_mojibake() {
        foreach (array_merge(glob(APPPATH . 'views/hkp/*.php'), glob(FCPATH . 'assets/hkp/*.{js,css}', GLOB_BRACE)) as $file) {
            $src = file_get_contents($file);
            if (substr($file, -4) === '.php') { $this->assertFalse((bool) preg_match('/>[^<>]*[A-Za-z] \? [A-Za-z][^<>]*</', $src), basename($file) . ' has no "?" separator glitch'); }
            $this->assertFalse((bool) preg_match('/\xC3\xA2\xE2\x82\xAC|\xEF\xBF\xBD/', $src), basename($file) . ' has no mojibake');
        }
        $keys = array('organisations', 'properties', 'users', 'active', 'learners', 'managers', 'courses', 'domains', 'tracks', 'lessons', 'assessments', 'competencies', 'gaps', 'actions', 'certificates', 'expiring', 'awaiting', 'ai_week');
        $s = array_fill_keys($keys, 5); $prev = array_fill_keys($keys, 4); $prev['active'] = null; $prev['users'] = 0; $prev['gaps'] = 10;
        $html = $this->CI->load->view('hkp/admin_dashboard', array('s' => $s, 'prev' => $prev, 'k' => array('readiness_rate' => 50, 'competency_coverage' => 5, 'learning_completion' => 1, 'critical_gaps' => 0),
            'compare' => array(), 'recent' => array(array('actor_name' => 'Lama', 'description' => 'exported a report', 'action' => 'export', 'created_at' => date('Y-m-d H:i:s')))), true);
        $this->assertEquals(20, substr_count($html, 'studio-metric '), 'twenty KPI tiles as in the reference');
        $this->assertContains('+25%', $html); $this->assertContains('-50%', $html); $this->assertContains('studio-delta is-none', $html);
        $this->assertContains('studio-dash-head__photo', $html); $this->assertContains('Cross-property comparison', $html); $this->assertContains('Recent activity', $html);
        $this->assertNotContains('ALTUS ?', $html);
    }

    public function test_migration_026_down_is_reversible() {
        require_once APPPATH . 'migrations/20260101000026_studio_home_binding.php';
        $saved = $this->db->select('id, studio_enabled')->get('ha_page')->result_array();
        $m = new Migration_Studio_home_binding();
        try { $m->down(); $this->assertFalse((bool) $this->db->query("SHOW COLUMNS FROM ha_page LIKE 'studio_enabled'")->num_rows(), 'column dropped'); } finally { $m->up(); }
        $this->assertTrue((bool) $this->db->query("SHOW COLUMNS FROM ha_page LIKE 'studio_enabled'")->num_rows(), 'column restored');
        foreach ($saved as $r) { $this->db->where('id', $r['id'])->update('ha_page', array('studio_enabled' => $r['studio_enabled'])); }
    }

    public function test_migration_027_down_is_reversible_in_a_scratch_schema() {
        require_once APPPATH . 'migrations/20260101000027_publisher_upgrade.php';
        $scratch = $this->db->database . '_mig027';
        $this->db->query('DROP DATABASE IF EXISTS `' . $scratch . '`'); $this->db->query('CREATE DATABASE `' . $scratch . '`');
        try {
            $db2 = $this->CI->load->database(array('hostname' => $this->db->hostname, 'username' => $this->db->username, 'password' => $this->db->password, 'database' => $scratch,
                'dbdriver' => $this->db->dbdriver, 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'db_debug' => true), true);
            $db2->query('CREATE TABLE ha_page_section (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY)');
            $m = new Migration_Publisher_upgrade();
            $prop = new ReflectionProperty('Ha_migration', 'db'); $prop->setAccessible(true); $prop->setValue($m, $db2);
            $tables = array('ha_studio_draft', 'ha_studio_revision', 'ha_site_studio', 'ha_document_job', 'ha_publisher_approval', 'ha_publisher_request', 'ha_oauth_bridge', 'ha_oauth_store');
            $m->up();
            foreach ($tables as $t) { $this->assertTrue((bool) $db2->query('SHOW TABLES LIKE ' . $db2->escape($t))->num_rows(), $t . ' created'); }
            $this->assertTrue((bool) $db2->query("SHOW COLUMNS FROM ha_page_section LIKE 'studio_key'")->num_rows(), 'studio_key added');
            $m->down();
            $db2->data_cache = array();
            foreach ($tables as $t) { $this->assertFalse((bool) $db2->query('SHOW TABLES LIKE ' . $db2->escape($t))->num_rows(), $t . ' dropped'); }
            $this->assertFalse((bool) $db2->query("SHOW COLUMNS FROM ha_page_section LIKE 'studio_key'")->num_rows(), 'studio_key dropped');
            $m->up(); $this->assertTrue((bool) $db2->query("SHOW TABLES LIKE 'ha_studio_revision'")->num_rows(), 'up() runs again after down()');
            $db2->close();
        } finally {
            $this->db->query('DROP DATABASE IF EXISTS `' . $scratch . '`');
        }
    }
}
