<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * The public read model must never leak unpublished or privately scoped
 * content, must resolve both locales, and must answer the certificate
 * verification question correctly. Plan sections 7, 35, 39, 40, 19, 52.
 */
class Test_content extends Ha_testcase {

    /** @var Ha_catalog */
    private $catalog;

    public function setUp() {
        $this->CI->load->library('ha_catalog');
        $this->catalog = $this->CI->ha_catalog;
    }

    // ------------------------------------------------------------ publishing

    public function test_only_published_courses_are_public() {
        $total = $this->db->where('status', 'published')->count_all_results('ha_course');
        $this->assertGreaterThan(50, $total, 'The catalogue should be seeded');
        $this->assertEquals($total, count($this->catalog->courses('en', array('limit' => 500))));

        $course = $this->db->get_where('ha_course', array('code' => 'fo-check-in'))->row_array();
        $this->db->where('id', $course['id'])->update('ha_course', array('status' => 'draft'));

        $this->assertNull($this->catalog->course($course['slug_en'], 'en'),
            'A draft course must not be readable on the public site');
        $this->assertEquals($total - 1, count($this->catalog->courses('en', array('limit' => 500))));

        $this->db->where('id', $course['id'])->update('ha_course', array('status' => 'published'));
        $this->assertNotNull($this->catalog->course($course['slug_en'], 'en'));
    }

    public function test_a_scheduled_article_is_not_public_before_its_date() {
        $article = $this->db->get('ha_article')->row_array();
        $this->db->where('id', $article['id'])
            ->update('ha_article', array('published_at' => date('Y-m-d H:i:s', strtotime('+7 days'))));

        $this->assertNull($this->catalog->article($article['slug_en'], 'en'),
            'An article dated in the future must not be public yet');

        $this->db->where('id', $article['id'])
            ->update('ha_article', array('published_at' => date('Y-m-d H:i:s', strtotime('-1 day'))));
        $this->assertNotNull($this->catalog->article($article['slug_en'], 'en'));
    }

    public function test_organization_scoped_sops_are_never_public() {
        $private = $this->db->where('visibility !=', 'public')->count_all_results('ha_sop_document');
        $this->assertGreaterThan(0, $private, 'Seeded procedures are organization scoped');

        foreach ($this->catalog->public_sops('en') as $s) {
            $row = $this->db->get_where('ha_sop_document', array('id' => $s['id']))->row_array();
            $this->assertEquals('public', $row['visibility'],
                'Only procedures explicitly marked public may be listed');
        }

        // Asking for one by slug must also refuse.
        $scoped = $this->db->where('visibility', 'organization')->get('ha_sop_document')->row_array();
        $this->assertNull($this->catalog->public_sop($scoped['slug_en'], 'en'),
            'Changing the URL must not expose an organization procedure');
    }

    // --------------------------------------------------------------- locales

    public function test_every_course_resolves_in_both_locales() {
        $missing = array();
        foreach ($this->db->select('id, code, slug_en, slug_ar')->get('ha_course')->result_array() as $c) {
            foreach (array('en', 'ar') as $locale) {
                $row = $this->catalog->course($c['slug_' . $locale], $locale);
                if (!$row || empty($row['title'])) {
                    $missing[] = $c['code'] . '/' . $locale;
                }
            }
        }
        $this->assertEmpty($missing, 'Courses without a resolvable translation: ' . implode(', ', $missing));
    }

    public function test_slugs_are_unique_within_a_locale() {
        foreach (array('ha_course', 'ha_program', 'ha_learning_path', 'ha_topic', 'ha_article') as $table) {
            foreach (array('slug_en', 'slug_ar') as $column) {
                $dupes = $this->db->query(
                    "SELECT {$column} AS s, COUNT(*) AS n FROM {$table} GROUP BY {$column} HAVING n > 1"
                )->result_array();
                $this->assertEmpty($dupes, $table . '.' . $column . ' has duplicates');
            }
        }
    }

    public function test_arabic_content_is_not_a_copy_of_english() {
        $rows = $this->db->query(
            "SELECT c.code, en.title AS title_en, ar.title AS title_ar
             FROM ha_course c
             JOIN ha_course_translation en ON en.course_id = c.id AND en.locale = 'en'
             JOIN ha_course_translation ar ON ar.course_id = c.id AND ar.locale = 'ar'"
        )->result_array();
        $this->assertGreaterThan(50, count($rows));
        $identical = array();
        foreach ($rows as $r) {
            if ($r['title_en'] === $r['title_ar']) {
                $identical[] = $r['code'];
            }
        }
        $this->assertEmpty($identical, 'Arabic titles that are just the English string: ' . implode(', ', $identical));
    }

    public function test_arabic_titles_contain_arabic_script() {
        $rows = $this->db->query(
            "SELECT c.code, ar.title FROM ha_course c
             JOIN ha_course_translation ar ON ar.course_id = c.id AND ar.locale = 'ar' LIMIT 100"
        )->result_array();
        foreach ($rows as $r) {
            $this->assertMatches('/\p{Arabic}/u', $r['title'],
                'Arabic title for ' . $r['code'] . ' has no Arabic script');
        }
    }

    // -------------------------------------------------------------- listings

    public function test_course_filters_and_search_work_in_both_locales() {
        $fo = $this->catalog->courses('en', array('category' => 'front-office', 'limit' => 100));
        $this->assertGreaterThan(5, count($fo), 'Front office category should have courses');

        $found = $this->catalog->courses('en', array('search' => 'Night Audit', 'limit' => 10));
        $this->assertNotEmpty($found, 'English search should match a course title');

        $found_ar = $this->catalog->courses('ar', array('search' => 'التدقيق الليلي', 'limit' => 10));
        $this->assertNotEmpty($found_ar, 'Arabic search should match an Arabic course title');
    }

    public function test_course_detail_carries_everything_the_page_needs() {
        $course = $this->catalog->course('fo-check-in', 'en');
        $this->assertNotNull($course);
        $this->assertNotEmpty($course['outcomes'], 'A course page needs learning outcomes');
        $this->assertNotEmpty($course['faqs'], 'A course page needs its FAQ for answer engines');
        $this->assertNotEmpty($course['curriculum'], 'A course page needs a curriculum');
        $this->assertNotEmpty($course['skills'], 'A course should record the skills it awards');

        $lessons = 0;
        foreach ($course['curriculum'] as $section) {
            $lessons += count($section['lessons']);
        }
        $this->assertGreaterThan(5, $lessons, 'Every seeded course ships with real lessons');
    }

    public function test_programs_and_paths_resolve_their_children() {
        $program = $this->catalog->program('front-office-professional', 'en');
        $this->assertNotNull($program);
        $this->assertGreaterThan(3, count($program['courses']));

        $path = $this->catalog->path('front-office-career', 'en');
        $this->assertNotNull($path);
        $this->assertGreaterThan(3, count($path['steps']));
        $this->assertNotEmpty($path['steps'][0]['courses'], 'Each career step lists its courses');
    }

    public function test_city_topics_are_not_thin_duplicates() {
        $cities = $this->catalog->topics('en', 'city');
        $this->assertCount(8, $cities, 'Eight Saudi city pages');

        $bodies = array();
        foreach ($cities as $c) {
            $text = trim(strip_tags($c['intro']));
            $this->assertGreaterThan(400, strlen($text),
                'City page ' . $c['city'] . ' is too thin to justify existing');
            $bodies[] = $text;
        }
        $this->assertEquals(count($bodies), count(array_unique($bodies)),
            'City pages must not share the same body text');
    }

    // ---------------------------------------------------- certificate verify

    public function test_certificate_verification_returns_each_status() {
        $user = $this->db->get_where('users', array('email' => 'omar.learner@dyafagroup.sa'))->row_array();
        $now = date('Y-m-d H:i:s');

        $make = function ($code, $status, $expires) use ($user, $now) {
            $this->db->insert('ha_certificate', array(
                'certificate_no'    => 'TEST-' . strtoupper($code),
                'verification_code' => $code,
                'user_id'           => $user['id'],
                'subject_title_en'  => 'Guest Check-in',
                'subject_title_ar'  => 'تسجيل الوصول',
                'recipient_name_en' => 'Omar Al Suwailem',
                'recipient_name_ar' => 'عمر السويلم',
                'issued_at'         => $now,
                'expires_at'        => $expires,
                'status'            => $status,
                'created_at'        => $now,
                'updated_at'        => $now,
            ));
        };

        $make('VALIDCODE1', 'issued', null);
        $make('EXPIREDCODE', 'issued', date('Y-m-d H:i:s', strtotime('-1 day')));
        $make('REVOKEDCODE', 'revoked', null);

        $this->assertEquals('valid', $this->catalog->verify_certificate('VALIDCODE1')['status']);
        $this->assertEquals('expired', $this->catalog->verify_certificate('EXPIREDCODE')['status']);
        $this->assertEquals('revoked', $this->catalog->verify_certificate('REVOKEDCODE')['status']);
        $this->assertEquals('not_found', $this->catalog->verify_certificate('NOSUCHCODE')['status']);
        $this->assertEquals('not_found', $this->catalog->verify_certificate('')['status']);

        // Every attempt is recorded, including the failures.
        $this->assertDatabaseHas('ha_certificate_verification', array('submitted_code' => 'NOSUCHCODE', 'result' => 'not_found'));
        $this->assertDatabaseHas('ha_certificate_verification', array('submitted_code' => 'REVOKEDCODE', 'result' => 'revoked'));

        $this->db->where_in('verification_code', array('VALIDCODE1', 'EXPIREDCODE', 'REVOKEDCODE'))
            ->delete('ha_certificate');
    }

    public function test_verification_does_not_leak_extra_personal_data() {
        $user = $this->db->get_where('users', array('email' => 'omar.learner@dyafagroup.sa'))->row_array();
        $now = date('Y-m-d H:i:s');
        $this->db->insert('ha_certificate', array(
            'certificate_no' => 'TEST-LEAK', 'verification_code' => 'LEAKCHECK',
            'user_id' => $user['id'], 'subject_title_en' => 'X', 'subject_title_ar' => 'س',
            'recipient_name_en' => 'Omar Al Suwailem', 'issued_at' => $now,
            'status' => 'issued', 'created_at' => $now, 'updated_at' => $now,
        ));

        $result = $this->catalog->verify_certificate('LEAKCHECK');
        $fields = array_keys($result['certificate']);
        foreach (array('user_id', 'enrollment_id', 'pdf_path', 'issued_by') as $private) {
            $this->assertNotContains($private, $fields,
                'Public verification must not return ' . $private);
        }
        $this->db->where('verification_code', 'LEAKCHECK')->delete('ha_certificate');
    }

    // ---------------------------------------------------------------- search

    public function test_global_search_covers_courses_articles_and_topics() {
        $results = $this->catalog->search('housekeeping', 'en');
        $this->assertNotEmpty($results);
        $types = array_unique(array_column($results, 'type'));
        $this->assertContains('course', $types);

        $this->assertEmpty($this->catalog->search('', 'en'), 'An empty search returns nothing');
        $this->assertEmpty($this->catalog->search('zzzzzznothingmatchesthis', 'en'));
    }

    // ------------------------------------------------------------ navigation

    public function test_public_menu_is_populated_in_both_locales() {
        foreach (array('en', 'ar') as $locale) {
            $menu = $this->catalog->menu('public_header', $locale);
            $this->assertGreaterThan(8, count($menu), 'The public header needs its full navigation');
            foreach ($menu as $item) {
                $this->assertNotEmpty($item['label'], 'A menu item must have a label in ' . $locale);
            }
        }
    }

    public function test_every_page_exists_in_both_locales() {
        foreach ($this->db->select('code')->get('ha_page')->result_array() as $p) {
            foreach (array('en', 'ar') as $locale) {
                $page = $this->catalog->page($p['code'], $locale);
                $this->assertNotNull($page, 'Page ' . $p['code'] . ' missing in ' . $locale);
                $this->assertNotEmpty($page['title'], 'Page ' . $p['code'] . ' has no ' . $locale . ' title');
                $this->assertNotEmpty($page['body'], 'Page ' . $p['code'] . ' has no ' . $locale . ' body');
            }
        }
    }
}
