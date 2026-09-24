<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Automated SEO checks (plan section 53).
 *
 * These assert on the metadata the site will actually render: a title, a
 * description, a canonical, an hreflang pair, valid JSON-LD, a breadcrumb
 * trail, and a sitemap with no broken or duplicated entry. The rendered HTML
 * itself is checked separately by the HTTP sweep in the build report.
 */
class Test_seo extends Ha_testcase {

    /** @var Ha_seo */
    private $seo;

    /** @var Ha_catalog */
    private $catalog;

    public function setUp() {
        $this->CI->load->library('ha_seo');
        $this->CI->load->library('ha_catalog');
        $this->CI->load->helper('url');
        $this->seo = $this->CI->ha_seo;
        $this->catalog = $this->CI->ha_catalog;
    }

    // ------------------------------------------------------------- metadata

    public function test_every_seeded_route_has_metadata_in_both_locales() {
        $routes = array('courses', 'programs', 'learning-paths', 'hospitality-topics',
            'sop', 'articles', 'verify');
        foreach ($routes as $route) {
            foreach (array('en', 'ar') as $locale) {
                $this->assertDatabaseHas('ha_seo_metadata', array(
                    'entity_type' => 'route', 'route_key' => $route, 'locale' => $locale,
                ), 'Missing SEO record for route ' . $route . ' in ' . $locale);
            }
        }
    }

    public function test_every_page_topic_and_article_has_metadata_in_both_locales() {
        $checks = array(
            'page'    => 'ha_page',
            'topic'   => 'ha_topic',
            'article' => 'ha_article',
        );
        $missing = array();
        foreach ($checks as $entity => $table) {
            foreach ($this->db->select('id')->get($table)->result_array() as $row) {
                foreach (array('en', 'ar') as $locale) {
                    $found = $this->db->where(array(
                        'entity_type' => $entity, 'entity_id' => $row['id'], 'locale' => $locale,
                    ))->count_all_results('ha_seo_metadata');
                    if (!$found) {
                        $missing[] = $entity . '#' . $row['id'] . '/' . $locale;
                    }
                }
            }
        }
        $this->assertEmpty($missing, 'Entities without SEO metadata: ' . implode(', ', $missing));
    }

    public function test_metadata_titles_and_descriptions_are_within_sane_limits() {
        $rows = $this->db->get('ha_seo_metadata')->result_array();
        $this->assertGreaterThan(50, count($rows));
        foreach ($rows as $r) {
            $this->assertNotEmpty($r['meta_title'], 'A metadata row has no title');
            $this->assertLessThanOrEqual(190, mb_strlen($r['meta_title']),
                'Title too long: ' . $r['meta_title']);
            if ($r['meta_description'] !== null && $r['meta_description'] !== '') {
                $this->assertLessThanOrEqual(320, mb_strlen($r['meta_description']),
                    'Description too long for ' . $r['meta_title']);
            }
        }
    }

    public function test_no_page_is_accidentally_noindexed() {
        $noindex = $this->db->like('robots', 'noindex')->get('ha_seo_metadata')->result_array();
        $this->assertEmpty($noindex,
            'No seeded page should carry noindex: ' . implode(', ', array_column($noindex, 'meta_title')));
    }

    public function test_schema_json_is_valid_and_typed() {
        $rows = $this->db->where('schema_json IS NOT NULL')->where('schema_json !=', '')
            ->get('ha_seo_metadata')->result_array();
        $this->assertGreaterThan(30, count($rows), 'Most public pages should carry structured data');
        foreach ($rows as $r) {
            $decoded = json_decode($r['schema_json'], true);
            $this->assertNotNull($decoded, 'Invalid JSON-LD on ' . $r['meta_title']);
            $this->assertContains('@context', array_keys($decoded),
                'JSON-LD without @context on ' . $r['meta_title']);
            $has_type = isset($decoded['@type']) || isset($decoded['@graph']);
            $this->assertTrue($has_type, 'JSON-LD without a type on ' . $r['meta_title']);
        }
    }

    public function test_home_page_carries_faq_and_website_schema() {
        $page = $this->db->get_where('ha_page', array('code' => 'home'))->row_array();
        $row = $this->db->get_where('ha_seo_metadata', array(
            'entity_type' => 'page', 'entity_id' => $page['id'], 'locale' => 'en'))->row_array();
        $decoded = json_decode($row['schema_json'], true);
        $types = array();
        foreach ($decoded['@graph'] as $node) {
            $types[] = $node['@type'];
        }
        $this->assertContains('WebSite', $types);
        $this->assertContains('FAQPage', $types, 'The home page should answer questions directly');
        $this->assertContains('EducationalOrganization', $types);
    }

    public function test_city_pages_carry_geographic_schema() {
        $cities = $this->db->get_where('ha_topic', array('topic_type' => 'city'))->result_array();
        $this->assertCount(8, $cities);
        foreach ($cities as $c) {
            $row = $this->db->get_where('ha_seo_metadata', array(
                'entity_type' => 'topic', 'entity_id' => $c['id'], 'locale' => 'en'))->row_array();
            $this->assertNotNull($row, 'City page ' . $c['city'] . ' has no metadata');
            $decoded = json_decode($row['schema_json'], true);
            $json = json_encode($decoded);
            $this->assertContains('"City"', $json, 'City page ' . $c['city'] . ' lacks City schema');
            $this->assertContains($c['city'], $json, 'City schema does not name ' . $c['city']);
        }
    }

    // ------------------------------------------------------------- rendering

    public function test_head_block_contains_every_required_tag() {
        $this->seo->prepare('en', 'courses', array('route_key' => 'courses'),
            array('title' => 'Fallback', 'description' => 'Fallback description'));
        $this->seo->breadcrumb('Courses', 'courses');
        $head = $this->seo->render_head();

        foreach (array('<title>', 'name="description"', 'name="robots"', 'rel="canonical"',
                     'hreflang="en"', 'hreflang="ar"', 'hreflang="x-default"',
                     'property="og:title"', 'property="og:url"', 'name="twitter:card"',
                     'application/ld+json') as $needle) {
            $this->assertContains($needle, $head, 'Head block missing ' . $needle);
        }
    }

    public function test_breadcrumb_schema_is_ordered_and_starts_at_home() {
        $this->seo->prepare('en', 'courses/fo-check-in', array(), array('title' => 'X'));
        $this->seo->breadcrumb('Courses', 'courses');
        $this->seo->breadcrumb('Guest Check-in', 'courses/fo-check-in');
        $head = $this->seo->render_head();

        $this->assertContains('BreadcrumbList', $head);
        preg_match('/\{"@context":"https:\/\/schema.org","@type":"BreadcrumbList".*?\}(?=<\/script>)/', $head, $m);
        $this->assertNotEmpty($m, 'Breadcrumb JSON-LD should be renderable');
        $decoded = json_decode($m[0], true);
        $this->assertNotNull($decoded);
        $this->assertCount(3, $decoded['itemListElement'], 'Home plus two crumbs');
        $this->assertEquals('Home', $decoded['itemListElement'][0]['name']);
        $this->assertEquals(1, $decoded['itemListElement'][0]['position']);
        $this->assertEquals(3, $decoded['itemListElement'][2]['position']);
    }

    public function test_hreflang_points_at_the_translated_slug() {
        $course = $this->catalog->course('fo-check-in', 'en');
        $this->seo->prepare('en', 'courses/' . $course['slug'],
            array('entity_type' => 'course', 'entity_id' => $course['id']),
            array('title' => $course['title']));
        $this->seo->set_alternate(array(
            'en' => 'courses/' . $course['slug_en'],
            'ar' => 'courses/' . $course['slug_ar'],
        ));
        $head = $this->seo->render_head();
        $this->assertContains($course['slug_en'], $head);
        $this->assertContains(rawurlencode($course['slug_ar']) !== $course['slug_ar'] ? $course['slug_ar'] : $course['slug_ar'], $head,
            'The Arabic hreflang must point at the Arabic slug, not the English one');
    }

    public function test_course_schema_is_well_formed() {
        $course = $this->catalog->course('fo-check-in', 'en');
        $schema = $this->seo->prepare('en', 'courses/fo-check-in')->course_schema($course);
        $this->assertEquals('Course', $schema['@type']);
        $this->assertNotEmpty($schema['name']);
        $this->assertNotEmpty($schema['description']);
        $this->assertNotEmpty($schema['provider']['name']);
        $this->assertNotEmpty($schema['hasCourseInstance'], 'Course schema needs a course instance');
        $this->assertEquals('en', $schema['inLanguage']);
    }

    public function test_faq_schema_only_renders_when_there_are_real_questions() {
        $this->seo->prepare('en', 'x');
        $this->assertNull($this->seo->faq_schema(array()), 'No FAQs means no FAQ schema');
        $schema = $this->seo->faq_schema(array(
            array('question' => 'Q?', 'answer' => 'A.'),
            array('question' => '', 'answer' => 'orphan'),
        ));
        $this->assertEquals('FAQPage', $schema['@type']);
        $this->assertCount(1, $schema['mainEntity'], 'An entry with no question is dropped');
    }

    // --------------------------------------------------------------- sitemap

    public function test_sitemap_lists_every_public_url_in_both_locales() {
        $this->seo->prepare('en', '');
        $urls = $this->seo->sitemap_urls();
        $this->assertGreaterThan(90, count($urls), 'The sitemap should cover the whole public site');

        $expected_courses = $this->db->where('status', 'published')->count_all_results('ha_course');
        $course_entries = 0;
        foreach ($urls as $u) {
            if (strpos($u['en'], '/courses/') !== false) {
                $course_entries++;
            }
        }
        $this->assertEquals($expected_courses, $course_entries,
            'Every published course belongs in the sitemap');

        foreach ($urls as $u) {
            $this->assertContains('/en', $u['en']);
            $this->assertContains('/ar', $u['ar']);
            $this->assertMatches('/^\d{4}-\d{2}-\d{2}$/', $u['lastmod'], 'lastmod must be a date');
        }
    }

    public function test_sitemap_has_no_duplicate_urls() {
        $this->seo->prepare('en', '');
        $seen = array();
        foreach ($this->seo->sitemap_urls() as $u) {
            $seen[] = $u['en'];
            $seen[] = $u['ar'];
        }
        $this->assertEquals(count($seen), count(array_unique($seen)),
            'The sitemap contains a duplicate URL');
    }

    public function test_sitemap_excludes_unpublished_content() {
        $course = $this->db->get_where('ha_course', array('code' => 'fo-night-audit'))->row_array();
        $this->db->where('id', $course['id'])->update('ha_course', array('status' => 'draft'));

        $this->seo->prepare('en', '');
        $found = false;
        foreach ($this->seo->sitemap_urls() as $u) {
            if (strpos($u['en'], $course['slug_en']) !== false) {
                $found = true;
            }
        }
        $this->assertFalse($found, 'A draft course must not appear in the sitemap');

        $this->db->where('id', $course['id'])->update('ha_course', array('status' => 'published'));
    }

    public function test_sitemap_xml_is_well_formed() {
        $this->seo->prepare('en', '');
        $xml = $this->seo->render_sitemap();
        $this->assertContains('<?xml version="1.0"', $xml);
        $this->assertContains('<urlset', $xml);
        $this->assertContains('hreflang="x-default"', $xml);

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        $this->assertNotEquals(false, $doc, 'Sitemap XML must parse');
        libxml_clear_errors();
    }

    public function test_robots_allows_the_site_and_blocks_the_admin() {
        $this->seo->prepare('en', '');
        $robots = $this->seo->render_robots();
        $this->assertContains('User-agent: *', $robots);
        $this->assertContains('Allow: /', $robots);
        $this->assertContains('Disallow: /admin', $robots);
        $this->assertContains('Sitemap:', $robots);
    }

    // ------------------------------------------------------------- redirects

    public function test_redirect_manager_resolves_and_counts_hits() {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('ha_redirect', array(
            'source_path' => '/en/old-course-page', 'target_path' => '/en/courses',
            'status_code' => 301, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ));

        $this->seo->prepare('en', '');
        $hit = $this->seo->redirect_for('/en/old-course-page');
        $this->assertNotNull($hit);
        $this->assertEquals('/en/courses', $hit['target']);
        $this->assertEquals(301, $hit['status']);

        $row = $this->db->get_where('ha_redirect', array('source_path' => '/en/old-course-page'))->row_array();
        $this->assertEquals(1, (int) $row['hit_count'], 'A redirect hit must be counted');

        $this->assertNull($this->seo->redirect_for('/en/never-configured'));

        $this->db->where('source_path', '/en/old-course-page')->delete('ha_redirect');
    }

    public function test_inactive_redirect_is_ignored() {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('ha_redirect', array(
            'source_path' => '/en/disabled', 'target_path' => '/en/courses',
            'status_code' => 301, 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now,
        ));
        $this->seo->prepare('en', '');
        $this->assertNull($this->seo->redirect_for('/en/disabled'));
        $this->db->where('source_path', '/en/disabled')->delete('ha_redirect');
    }

    // -------------------------------------------------------- keyword record

    public function test_tracked_keywords_map_to_a_real_page() {
        $keywords = $this->db->get('ha_competitor_keyword')->result_array();
        $this->assertGreaterThan(20, count($keywords), 'The plan keyword list should be tracked');
        foreach ($keywords as $k) {
            $this->assertNotEmpty($k['our_page'],
                'Keyword "' . $k['keyword'] . '" is tracked with no target page');
            $this->assertContains($k['search_intent'],
                array('informational', 'commercial', 'transactional', 'navigational'));
        }
    }

    public function test_competitor_records_carry_evidence_and_a_date() {
        $competitors = $this->db->get('ha_competitor')->result_array();
        $this->assertGreaterThan(0, count($competitors));
        foreach ($competitors as $c) {
            $this->assertNotEmpty($c['evidence_url'], $c['name'] . ' has no evidence URL');
            $this->assertMatches('/^https?:\/\//', $c['evidence_url'], $c['name'] . ' evidence must be a URL');
            $this->assertMatches('/^\d{4}-\d{2}-\d{2}$/', $c['last_checked_at'],
                $c['name'] . ' has no checked date');
        }

        // Capabilities must be yes, no or unknown; a guess is recorded as unknown.
        foreach ($this->db->get('ha_competitor_capability')->result_array() as $cap) {
            $this->assertContains($cap['is_present'], array('yes', 'no', 'unknown'));
        }
    }
}
