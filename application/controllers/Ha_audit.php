<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rendered-page audit (plan sections 16, 53, 54).
 *
 * The test suite checks the model. This walks the real HTTP responses for
 * every URL in the sitemap and asserts what a crawler or a screen reader
 * actually receives: one H1, a title, a description, a canonical, an hreflang
 * pair, valid JSON-LD, the right lang and dir attributes, and no broken
 * internal link.
 *
 *   HA_AUDIT_BASE=http://127.0.0.1:8899 php index.php ha_audit run
 */
class Ha_audit extends CI_Controller {

    private $base;
    private $checked = 0;
    private $problems = array();
    private $seen_titles = array();
    private $seen_canonicals = array();

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        ini_set('memory_limit', '512M');
        $this->load->database();
        $this->load->library('ha_seo');
        $this->load->helper('url');
    }

    private function out($line = '') {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    private function problem($url, $message) {
        $this->problems[] = $url . ' :: ' . $message;
    }

    private function fetch($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'HospitalityAcademyAudit/1.0',
        ));
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return array('status' => $status, 'body' => (string) $body, 'error' => $error);
    }

    private function post($url, array $fields) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_USERAGENT      => 'HospitalityAcademyAudit/1.0',
        ));
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array('status' => $status, 'body' => (string) $body);
    }

    /** Every public URL, taken from the sitemap so the audit cannot drift from it. */
    private function urls() {
        $this->ha_seo->prepare('en', '');
        $urls = array();
        foreach ($this->ha_seo->sitemap_urls() as $u) {
            foreach ($u['urls'] as $url) {
                $urls[] = $url;
            }
        }
        return array_values(array_unique($urls));
    }

    /**
     * CodeIgniter's CLI router splits arguments on "/", so a URL cannot be
     * passed as an argument. The host comes from HA_AUDIT_BASE instead, and
     * falls back to the configured base URL.
     */
    public function run() {
        $base = getenv('HA_AUDIT_BASE');
        $this->base = rtrim($base ?: base_url(), '/');
        $site_base = rtrim(base_url(), '/');

        $this->out('Hospitality Academy rendered page audit');
        $this->out('base: ' . $this->base);
        $this->out(str_repeat('-', 72));

        $urls = $this->urls();
        $this->out(count($urls) . ' URLs from the sitemap');

        foreach ($urls as $url) {
            // The sitemap is built from the configured base URL; rewrite it to
            // the host being audited so a local run works unchanged.
            $target = str_replace($site_base, $this->base, $url);
            $this->audit_page($target);
        }

        $this->audit_status('/academy-sitemap.xml', 200, 'sitemap');
        $this->audit_status('/academy-robots.txt', 200, 'robots');
        $this->audit_status('/en/this-page-does-not-exist', 404, 'unknown URL returns 404');
        $this->audit_status('/ar/this-page-does-not-exist', 404, 'unknown Arabic URL returns 404');

        // The machine-readable endpoints, checked by what they actually SERVE.
        //
        // /robots.txt resolved through the catch-all and returned the home page
        // as HTML with a 200 for the life of this project. Test_seo passed
        // throughout, because it calls Ha_seo::render_robots() rather than
        // fetching the URL, and a status-only check here would have passed too.
        // These assert the body, not the code.
        $this->audit_endpoint('/robots.txt', 'User-agent:', 'robots.txt is served at the site root');
        $this->audit_endpoint('/robots.txt', 'Disallow: /admin', 'robots.txt blocks the admin');
        $this->audit_endpoint('/llms.txt', '# ', 'llms.txt is served');
        $this->audit_endpoint('/llms-full.txt', '## Course catalogue', 'llms-full.txt carries the catalogue');
        $this->audit_endpoint('/sitemap.xml', '<sitemapindex', 'sitemap.xml is an index');
        $this->audit_endpoint('/image-sitemap.xml', '<image:license>', 'image sitemap carries licences');

        $this->report();
    }

    private function audit_status($path, $expected, $label) {
        $res = $this->fetch($this->base . $path);
        $this->checked++;
        if ($res['status'] !== $expected) {
            $this->problem($path, $label . ': expected ' . $expected . ', got ' . $res['status']);
        }
    }

    /**
     * A non-HTML endpoint, checked on its body rather than its status code.
     * An endpoint swallowed by the catch-all answers 200 with a web page, so
     * the status alone proves nothing about what a crawler receives.
     */
    private function audit_endpoint($path, $must_contain, $label) {
        $res = $this->fetch($this->base . $path);
        $this->checked++;
        if ($res['status'] !== 200) {
            $this->problem($path, $label . ': expected 200, got ' . $res['status']);
            return;
        }
        $body = (string) $res['body'];
        if (stripos(ltrim($body), '<!doctype html') === 0 || stripos(ltrim($body), '<html') === 0) {
            $this->problem($path, $label . ': served an HTML page, not the expected file');
            return;
        }
        if (strpos($body, $must_contain) === false) {
            $this->problem($path, $label . ': body does not contain ' . var_export($must_contain, true));
        }
    }

    private function audit_page($url) {
        $this->checked++;
        $res = $this->fetch($url);

        if ($res['error']) {
            return $this->problem($url, 'request failed: ' . $res['error']);
        }
        if ($res['status'] !== 200) {
            return $this->problem($url, 'status ' . $res['status']);
        }

        $html = $res['body'];
        // The locale is the first segment after the base path. The site does
        // not necessarily sit at the domain root: under Laragon it lives at
        // /atlas-lms/Academy-LMS/, so the base has to come off first.
        $path = (string) parse_url($url, PHP_URL_PATH);
        $base_path = (string) parse_url($this->base, PHP_URL_PATH);
        if ($base_path !== '' && strpos($path, $base_path) === 0) {
            $path = substr($path, strlen($base_path));
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        $is_arabic = (isset($segments[0]) && $segments[0] === 'ar');

        // Language and direction
        if (!preg_match('/<html[^>]*lang="(en|ar)"/', $html, $lang)) {
            $this->problem($url, 'no lang attribute on <html>');
        } elseif ($is_arabic && $lang[1] !== 'ar') {
            $this->problem($url, 'Arabic URL served with lang="' . $lang[1] . '"');
        }
        if (preg_match('/<html[^>]*dir="(ltr|rtl)"/', $html, $dir)) {
            $expected_dir = $is_arabic ? 'rtl' : 'ltr';
            if ($dir[1] !== $expected_dir) {
                $this->problem($url, 'dir is "' . $dir[1] . '", expected "' . $expected_dir . '"');
            }
        } else {
            $this->problem($url, 'no dir attribute on <html>');
        }

        // Title
        if (!preg_match('/<title>(.*?)<\/title>/s', $html, $title) || trim($title[1]) === '') {
            $this->problem($url, 'missing title');
        } else {
            $clean = trim(html_entity_decode($title[1], ENT_QUOTES, 'UTF-8'));
            if (mb_strlen($clean) > 190) {
                $this->problem($url, 'title longer than 190 characters');
            }
            if (isset($this->seen_titles[$clean])) {
                $this->problem($url, 'duplicate title, also on ' . $this->seen_titles[$clean]);
            } else {
                $this->seen_titles[$clean] = $url;
            }
        }

        // Description
        if (!preg_match('/<meta name="description" content="(.*?)"/s', $html, $desc)
            || trim($desc[1]) === '') {
            $this->problem($url, 'missing meta description');
        }

        // Canonical
        if (!preg_match('/<link rel="canonical" href="(.*?)"/', $html, $canonical)) {
            $this->problem($url, 'missing canonical');
        } else {
            $c = $canonical[1];
            if (isset($this->seen_canonicals[$c]) && $this->seen_canonicals[$c] !== $url) {
                $this->problem($url, 'canonical collides with ' . $this->seen_canonicals[$c]);
            }
            $this->seen_canonicals[$c] = $url;
        }

        // hreflang pair plus x-default
        foreach (array('en', 'ar', 'x-default') as $hl) {
            if (strpos($html, 'hreflang="' . $hl . '"') === false) {
                $this->problem($url, 'missing hreflang="' . $hl . '"');
            }
        }

        // Exactly one H1
        preg_match_all('/<h1[\s>]/i', $html, $h1s);
        $count = count($h1s[0]);
        if ($count === 0) {
            $this->problem($url, 'no H1');
        } elseif ($count > 1) {
            $this->problem($url, $count . ' H1 elements, expected one');
        }

        // Heading order: no jump from h1 straight past h2
        preg_match_all('/<h([1-6])[\s>]/i', $html, $levels);
        $previous = 0;
        foreach ($levels[1] as $level) {
            $level = (int) $level;
            if ($previous && $level > $previous + 1) {
                $this->problem($url, 'heading level jumps from h' . $previous . ' to h' . $level);
                break;
            }
            $previous = $level;
        }

        // Structured data
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $blocks);
        if (!$blocks[1]) {
            $this->problem($url, 'no JSON-LD');
        }
        foreach ($blocks[1] as $block) {
            $decoded = json_decode(trim($block), true);
            if ($decoded === null) {
                $this->problem($url, 'invalid JSON-LD: ' . json_last_error_msg());
            } elseif (!isset($decoded['@context'])) {
                $this->problem($url, 'JSON-LD without @context');
            }
        }

        // OpenGraph
        foreach (array('og:title', 'og:url', 'og:site_name') as $og) {
            if (strpos($html, 'property="' . $og . '"') === false) {
                $this->problem($url, 'missing ' . $og);
            }
        }

        // Accessibility basics that can be checked from markup
        if (strpos($html, 'class="ha-skip"') === false) {
            $this->problem($url, 'no skip link');
        }
        preg_match_all('/<img\b(?![^>]*\balt=)[^>]*>/i', $html, $imgs);
        if ($imgs[0]) {
            $this->problem($url, count($imgs[0]) . ' image(s) without an alt attribute');
        }
        preg_match_all('/<input\b(?![^>]*\btype="(hidden|submit|button)")[^>]*\bid="([^"]+)"/i', $html, $inputs);
        foreach ($inputs[2] as $id) {
            if (strpos($html, 'for="' . $id . '"') === false) {
                $this->problem($url, 'input #' . $id . ' has no label');
            }
        }

        // Internal links must resolve. Only check unique paths once.
        preg_match_all('/href="' . preg_quote($this->base, '/') . '([^"#?]*)"/', $html, $links);
        foreach (array_unique($links[1]) as $path) {
            if ($path === '' || strpos($path, '/assets/') === 0) {
                continue;
            }
            if (isset($this->seen_links[$path])) {
                continue;
            }
            $this->seen_links[$path] = true;
            $code = $this->fetch($this->base . $path)['status'];
            if ($code !== 200) {
                $this->problem($url, 'internal link ' . $path . ' returns ' . $code);
            }
        }
    }

    private $seen_links = array();

    /**
     * Exercises the interactive parts of the public site end to end: the
     * contact form, certificate verification, search and the language
     * switch. A page sweep proves a form renders; only a submission proves
     * it works.
     *
     *   HA_AUDIT_BASE=http://127.0.0.1:8899 php index.php ha_audit flows
     */
    public function flows() {
        $this->base = rtrim(getenv('HA_AUDIT_BASE') ?: base_url(), '/');
        $this->out('Hospitality Academy public flow checks');
        $this->out('base: ' . $this->base);
        $this->out(str_repeat('-', 72));

        $this->flow_contact_rejects_bad_input();
        $this->flow_contact_records_a_lead();
        $this->flow_verification_reports_each_status();
        $this->flow_search_finds_content();
        $this->flow_language_switch_keeps_the_page();

        $this->report();
    }

    private function flow_contact_rejects_bad_input() {
        $this->checked++;
        $before = $this->db->count_all_results('ha_lead');
        $res = $this->post($this->base . '/en/contact', array(
            'name' => '', 'email' => 'not-an-email', 'message' => '',
        ));
        $after = $this->db->count_all_results('ha_lead');

        if ($res['status'] !== 200) {
            $this->problem('/en/contact', 'invalid submission returned ' . $res['status']);
        }
        if ($after !== $before) {
            $this->problem('/en/contact', 'an invalid submission created a lead row');
        }
        if (strpos($res['body'], 'ha-field__error') === false) {
            $this->problem('/en/contact', 'invalid submission showed no validation error');
        }
        $this->out('  contact form rejects invalid input');
    }

    private function flow_contact_records_a_lead() {
        $this->checked++;
        $email = 'audit-' . time() . '@example.test';
        $res = $this->post($this->base . '/en/contact', array(
            'name'              => 'Audit Runner',
            'email'             => $email,
            'phone'             => '+966500000000',
            'organization_name' => 'Audit Hotel',
            'city'              => 'Riyadh',
            'headcount'         => '120',
            'interest'          => 'hotel_training',
            'message'           => 'Submitted by the automated public flow audit.',
        ));

        if ($res['status'] !== 200) {
            $this->problem('/en/contact', 'valid submission returned ' . $res['status']);
        }
        $row = $this->db->get_where('ha_lead', array('email' => $email))->row_array();
        if (!$row) {
            $this->problem('/en/contact', 'a valid submission did not create a lead row');
        } else {
            if ($row['status'] !== 'new') {
                $this->problem('/en/contact', 'lead status is ' . $row['status'] . ', expected new');
            }
            if ($row['interest'] !== 'hotel_training') {
                $this->problem('/en/contact', 'lead interest was not stored');
            }
            if ($row['locale'] !== 'en') {
                $this->problem('/en/contact', 'lead locale was not stored');
            }
            $this->db->where('id', $row['id'])->delete('ha_lead');
        }
        $this->out('  contact form records a lead');
    }

    private function flow_verification_reports_each_status() {
        $this->checked++;
        $user = $this->db->get('users')->row_array();
        $now = date('Y-m-d H:i:s');
        $codes = array(
            'AUDITVALID'   => array('issued', null, 'valid'),
            'AUDITEXPIRED' => array('issued', date('Y-m-d H:i:s', strtotime('-1 day')), 'expired'),
            'AUDITREVOKED' => array('revoked', null, 'revoked'),
        );
        foreach ($codes as $code => $spec) {
            $this->db->insert('ha_certificate', array(
                'certificate_no' => 'AUDIT-' . $code, 'verification_code' => $code,
                'user_id' => $user['id'], 'subject_title_en' => 'Guest Check-in',
                'subject_title_ar' => 'تسجيل الوصول', 'recipient_name_en' => 'Audit Runner',
                'issued_at' => $now, 'expires_at' => $spec[1], 'status' => $spec[0],
                'created_at' => $now, 'updated_at' => $now,
            ));
        }

        foreach ($codes as $code => $spec) {
            $res = $this->post($this->base . '/en/verify', array('code' => $code));
            if ($res['status'] !== 200) {
                $this->problem('/en/verify', $code . ' returned ' . $res['status']);
                continue;
            }
            if (strpos($res['body'], 'ha-result--' . $spec[2]) === false) {
                $this->problem('/en/verify', $code . ' did not report "' . $spec[2] . '"');
            }
            if ($spec[2] === 'valid' && strpos($res['body'], 'Audit Runner') === false) {
                $this->problem('/en/verify', 'a valid certificate did not show the holder name');
            }
        }

        $res = $this->post($this->base . '/en/verify', array('code' => 'NOTHINGMATCHESTHIS'));
        if (strpos($res['body'], 'ha-result--not_found') === false) {
            $this->problem('/en/verify', 'an unknown code did not report not_found');
        }
        if (strpos($res['body'], 'noindex') === false) {
            $this->problem('/en/verify', 'a verification result page is indexable');
        }

        $this->db->where_in('verification_code', array_keys($codes))->delete('ha_certificate');
        $this->out('  certificate verification reports valid, expired, revoked and not found');
    }

    private function flow_search_finds_content() {
        $this->checked++;
        $terms = array('en' => 'housekeeping', 'ar' => 'التدبير');
        foreach ($terms as $locale => $term) {
            $res = $this->fetch($this->base . '/' . $locale . '/search?q=' . rawurlencode($term));
            if ($res['status'] !== 200) {
                $this->problem('/' . $locale . '/search', 'returned ' . $res['status']);
                continue;
            }
            if (strpos($res['body'], 'ha-card') === false) {
                $this->problem('/' . $locale . '/search', 'search for "' . $term . '" returned no results');
            }
            if (strpos($res['body'], 'noindex') === false) {
                $this->problem('/' . $locale . '/search', 'search results should not be indexable');
            }
        }

        $empty = $this->fetch($this->base . '/en/search?q=zzzznothingmatches');
        if (strpos($empty['body'], 'ha-empty') === false) {
            $this->problem('/en/search', 'an empty result set showed no empty state');
        }
        $this->out('  search returns results in both languages and an empty state');
    }

    private function flow_language_switch_keeps_the_page() {
        $this->checked++;
        $pages = array('/en/courses/fo-check-in', '/en/privacy', '/en/articles');
        foreach ($pages as $path) {
            $res = $this->fetch($this->base . $path);
            // Keyed off a data attribute, not a CSS class: the switch has moved
            // and been restyled, and the check should survive that.
            if (!preg_match('/<a[^>]*data-ha-lang-switch[^>]*href="([^"]+)"/', $res['body'], $m)
                && !preg_match('/<a[^>]*href="([^"]+)"[^>]*data-ha-lang-switch/', $res['body'], $m)) {
                $this->problem($path, 'no language switch link');
                continue;
            }
            $target = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (strpos($target, '/ar') === false) {
                $this->problem($path, 'language switch does not point at Arabic');
                continue;
            }
            $switched = $this->fetch($target);
            if ($switched['status'] !== 200) {
                $this->problem($path, 'language switch target ' . $target . ' returned ' . $switched['status']);
            } elseif (strpos($switched['body'], 'dir="rtl"') === false) {
                $this->problem($path, 'language switch did not land on an RTL page');
            }
        }
        $this->out('  language switch lands on the translated page');
    }

    /**
     * Teaching-content audit.
     *
     * The page audit proves the website renders. It says nothing about
     * whether a course would actually teach anybody anything, and those are
     * different questions: a catalogue can be fully published, fully
     * translated, fully illustrated and still be empty of instruction.
     *
     * This reports, per course, what a production catalogue needs and does
     * not yet have: lesson bodies with real depth, a video file behind a
     * video lesson, a document behind a document lesson, a transcript for
     * accessibility and search, and an assessment at the end.
     *
     *     php index.php ha_audit content
     */
    public function content() {
        $this->out('Hospitality Academy teaching-content audit');
        $this->out(str_repeat('-', 72));

        // Below this a lesson body is a placeholder, not a lesson. Chosen
        // against what the seeded bodies actually contain: they run 132 to
        // 218 characters, which is a sentence or two of scene setting.
        $min_body = 400;

        $courses = $this->db
            ->select('c.id, c.code, t.title')
            ->from('ha_course c')
            ->join('ha_course_translation t', "t.course_id = c.id AND t.locale = 'en'", 'left')
            ->order_by('c.code')
            ->get()->result_array();

        $totals = array(
            'courses' => 0, 'lessons' => 0, 'thin' => 0, 'no_media' => 0,
            'no_transcript' => 0, 'no_assessment' => 0, 'distinct_shapes' => 0,
        );
        // The course name is interpolated into every body, so comparing the
        // strings finds them all unique and proves nothing. Compare the
        // lesson titles with the course name stripped instead: that is what
        // exposes one template wearing seventy-four names.
        $shapes = array();

        foreach ($courses as $course) {
            $totals['courses']++;

            $lessons = $this->db
                ->select('l.id, l.lesson_type, tr.title, tr.body, tr.transcript')
                ->from('ha_lesson l')
                ->join('ha_lesson_translation tr', "tr.lesson_id = l.id AND tr.locale = 'en'", 'left')
                ->where('l.course_id', $course['id'])
                ->get()->result_array();

            $thin = 0; $no_media = 0; $no_transcript = 0;
            foreach ($lessons as $lesson) {
                $totals['lessons']++;
                $shapes[trim(explode(':', (string) $lesson['title'])[0])] = true;

                if (strlen(strip_tags((string) $lesson['body'])) < $min_body) {
                    $thin++;
                }
                // A lesson that announces a video or a document and carries
                // neither is a broken promise to the learner, not a draft.
                if (in_array($lesson['lesson_type'], array('video', 'pdf'), true)) {
                    $has = $this->db->where('lesson_id', $lesson['id'])
                        ->count_all_results('ha_lesson_attachment');
                    // A verified, still-playable third-party video counts as
                    // media. One recorded but since taken down does not: the
                    // learner sees the same empty box either way.
                    if ($has === 0 && $this->db->table_exists('ha_lesson_video_source')) {
                        $has = $this->db->where('lesson_id', $lesson['id'])
                            ->where('status', 'live')
                            ->count_all_results('ha_lesson_video_source');
                    }
                    if ($has === 0) {
                        $no_media++;
                    }
                }
                if (trim((string) $lesson['transcript']) === '') {
                    $no_transcript++;
                }
            }

            // Assessments are published into the Academy LMS lesson/question
            // tables rather than authored in ha_assessment, so counting only
            // the academy side reported every course as untested while a
            // learner was being graded on four questions. Count what a
            // learner actually meets, and say where it lives.
            $assessments = $this->db->where('course_id', $course['id'])
                ->count_all_results('ha_assessment');
            if ($assessments === 0) {
                $legacy = $this->db
                    ->select('l.id')
                    ->from('lesson l')
                    ->join('course c', 'c.id = l.course_id')
                    ->where('l.lesson_type', 'quiz')
                    ->like('c.meta_keywords', 'ha:' . $course['code'])
                    ->get()->result_array();
                foreach ($legacy as $quiz) {
                    $assessments += $this->db->where('quiz_id', $quiz['id'])
                        ->count_all_results('question') > 0 ? 1 : 0;
                }
            }

            $totals['thin'] += $thin;
            $totals['no_media'] += $no_media;
            $totals['no_transcript'] += $no_transcript;
            if ($assessments === 0) {
                $totals['no_assessment']++;
                $this->problem($course['code'], 'no assessment: a learner can finish and be certified without being tested');
            }
            if ($thin > 0) {
                $this->problem($course['code'], $thin . ' of ' . count($lessons) . ' lessons are under ' . $min_body . ' characters');
            }
            if ($no_media > 0) {
                $this->problem($course['code'], $no_media . ' video or document lessons carry no file');
            }
        }

        $totals['distinct_shapes'] = count($shapes);
        $this->checked = $totals['courses'];

        $this->out('courses:              ' . $totals['courses']);
        $this->out('lessons:              ' . $totals['lessons']);
        $this->out('distinct lesson shapes: ' . $totals['distinct_shapes'] . '  (one template across every course shows up here)');
        $this->out('lessons under ' . $min_body . 'ch:  ' . $totals['thin']);
        $this->out('media promised, absent: ' . $totals['no_media']);
        $this->out('lessons w/o transcript: ' . $totals['no_transcript']);
        $this->out('courses w/o assessment: ' . $totals['no_assessment']);
        $this->report();
    }

    private function report() {
        $this->out(str_repeat('-', 72));
        $this->out('checked: ' . $this->checked . '   problems: ' . count($this->problems));

        // Always write the full list to a file: a long run's tail can be lost
        // to output buffering, and the list is the point of the audit.
        $report = FCPATH . '.lab/audit-report.txt';
        @mkdir(dirname($report), 0777, true);
        file_put_contents($report, implode(PHP_EOL, $this->problems) . PHP_EOL);
        $this->out('report: ' . $report);

        if ($this->problems) {
            $this->out('');
            foreach ($this->problems as $p) {
                $this->out('  ' . $p);
            }
            flush();
            exit(1);
        }
        $this->out('OK');
    }
}
