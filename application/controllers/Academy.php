<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * The public Hospitality Academy website.
 * Plan sections 25, 26, 27, 28, 29, 35, 40, 43.
 *
 * Every route exists in both locales and the locale is always explicit in the
 * URL (/en/... and /ar/...), which is what makes the hreflang pair honest and
 * lets a visitor share a link in the language they read.
 */
class Academy extends CI_Controller {

    /** @var string */
    private $locale = 'en';

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->helper(array('url', 'text', 'ha_media', 'ha_chrome', 'ha_locale'));
        $this->load->library('ha_catalog');
        $this->load->library('ha_seo');
        $this->load->library('form_validation');
        // Session is not autoloaded in this application, and the header needs
        // it to know whether to offer sign-in or a link to the learner area.
        $this->load->library('session');
    }

    // ------------------------------------------------------------------ setup

    private function boot($locale) {
        $this->locale = ha_locale_enabled($locale) ? $locale : ha_locale_default();
        ha_site_locale($this->locale);
        $this->content_security_policy();
        return $this->locale;
    }

    /**
     * Content-Security-Policy for the public academy pages.
     *
     * Set here rather than in .htaccess on purpose. A site-wide policy would
     * also land on the shipped LMS theme and the admin panel, which are full of
     * inline scripts and third-party widgets this project does not own; it
     * would break them, and the usual fix -- loosening the policy until nothing
     * breaks -- ends in a policy that permits everything. The academy pages are
     * ours end to end, so they can carry a policy that means something.
     *
     * script-src takes no 'unsafe-inline': the only inline scripts on these
     * pages are JSON-LD, which is data rather than executable script and is not
     * blocked. style-src does take it, for one reason worth recording -- the
     * administrator's custom CSS is emitted as an inline <style> block, and the
     * hero sets its background-image inline. Removing it needs a nonce through
     * both, which is a change to the admin contract, not a CSS tidy-up.
     */
    private function content_security_policy() {
        $policy = array(
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            // Lesson video is embedded from YouTube and credited to the
            // channel; the pipeline is built and simply has nothing assigned
            // yet. Naming the host here means enabling video is not also a
            // security-header change made in a hurry.
            "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com",
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        );
        $this->output->set_header('Content-Security-Policy: ' . implode('; ', $policy));
    }

    /**
     * Arabic slugs arrive percent encoded from the browser. Decode once, and
     * only once, so a lookup compares the same bytes that are in the database.
     */
    private function slug($value) {
        $value = (string) $value;
        return strpos($value, '%') === false ? $value : rawurldecode($value);
    }

    /**
     * The hero photograph, for preloading.
     *
     * The hero is a CSS background-image set inline on .ha-hero__media, which
     * is the latest a browser can possibly discover an image: it has to fetch
     * the HTML, parse the CSS and lay the element out before it even knows the
     * file exists. It is also the LCP element on every page that has one --
     * measured, not assumed. A preload link in the head moves the request to
     * the very start of the load instead.
     *
     * Resolved here rather than in each view because the head is rendered
     * before the view body runs, so the view cannot announce it in time.
     */
    private function hero_preload(array $data) {
        foreach (array(array('page', 'hero_image'), array('topic', 'hero_image'),
                       array('course', 'thumbnail')) as $where) {
            list($key, $field) = $where;
            if (!empty($data[$key][$field])) {
                $variant = ha_image_variant($data[$key][$field], 'wide');
                if ($variant) {
                    return base_url($variant);
                }
            }
        }
        return '';
    }

    /** Site languages that have this record's content in a translation table (plus en/ar). */
    private function content_locales($table, $fk, $id) {
        $have = array('en', 'ar');
        foreach ($this->db->select('locale')->distinct()->where($fk, (int) $id)->get($table)->result_array() as $r) {
            $have[] = $r['locale'];
        }
        return array_values(array_unique($have));
    }

    /** Same, for records translated through the ha_i18n_text overlay (topics, paths). */
    private function content_locales_i18n($entity, $id) {
        $have = array('en', 'ar');
        if ($this->db->table_exists('ha_i18n_text')) {
            foreach ($this->db->select('locale')->distinct()->where(array('entity' => $entity, 'entity_id' => (int) $id, 'field' => 'title'))->get('ha_i18n_text')->result_array() as $r) {
                $have[] = $r['locale'];
            }
        }
        return $have;
    }

    /**
     * Picks the best published site language from an Accept-Language header,
     * honouring q-values (\"ur-PK,ur;q=0.9,en;q=0.8\" -> ur). Falls back to English.
     */
    private function negotiate_locale($header) {
        $site = ha_site_locales();
        $best = 'en'; $best_q = -1.0;
        foreach (explode(',', strtolower((string) $header)) as $part) {
            $bits = explode(';q=', trim($part));
            $tag = trim($bits[0]);
            $q = isset($bits[1]) ? (float) $bits[1] : 1.0;
            $base = $tag === 'fil' ? 'tl' : preg_replace('/[-_].*/', '', $tag);
            if ($q > $best_q && in_array($base, $site, true)) {
                $best = $base; $best_q = $q;
            }
        }
        return $best;
    }

    /** Shared view data every public page needs. */
    private function shell(array $data) {
        $data['hero_preload'] = $this->hero_preload($data);
        $data['locale'] = $this->locale;
        $data['rtl'] = ha_locale_dir($this->locale) === 'rtl';
        $data['site_locales'] = ha_site_locales();
        $data['dir'] = $data['rtl'] ? 'rtl' : 'ltr';
        $data['seo'] = $this->ha_seo;
        $data['menu'] = $this->ha_catalog->menu('public_header', $this->locale);
        $data['t'] = $this->phrases();
        return $data;
    }

    private function render($view, array $data) {
        $this->load->view('academy/layout', $this->shell(array_merge($data, array('view' => $view))));
    }

    private function not_found($message = null) {
        $this->output->set_status_header(404);
        $this->ha_seo->prepare($this->locale, '404', array(), array(
            'title' => (ha_pt('Page not found')) . ' | ' . $this->ha_seo->brand(),
            'description' => '',
        ))->set('robots', 'noindex,follow');
        $this->render('404', array('message' => $message));
    }

    /** Interface strings. Content comes from the database; these are chrome. */
    private function phrases() {
        $en = array(
            'skip_to_content' => 'Skip to content',
            'search' => 'Search',
            'search_placeholder' => 'Search courses, topics and articles',
            'search_results_for' => 'Search results for',
            'no_results' => 'Nothing matched that search.',
            'courses' => 'Courses', 'programs' => 'Programs', 'learning_paths' => 'Learning Paths',
            'topics' => 'Hospitality Topics', 'sop' => 'SOP Resources', 'articles' => 'Articles',
            'certificates' => 'Certifications', 'about' => 'About Academy', 'for_hotels' => 'For Hotels',
            'contact' => 'Contact', 'home' => 'Home',
            'all_categories' => 'All categories', 'all_levels' => 'All levels',
            'level' => 'Level', 'duration' => 'Duration', 'minutes' => 'min', 'hours' => 'hours',
            'lessons' => 'lessons', 'free' => 'Free', 'certificate' => 'Certificate',
            'overview' => 'Overview', 'curriculum' => 'Curriculum', 'instructor' => 'Instructor',
            'outcomes' => 'What you will be able to do', 'requirements' => 'Requirements',
            'prerequisites' => 'Prerequisites', 'faq' => 'Frequently asked questions',
            'related_courses' => 'Related courses', 'part_of_programs' => 'Part of these programs',
            'skills_awarded' => 'Skills recorded on completion',
            'preview' => 'Preview', 'mandatory' => 'Mandatory', 'optional' => 'Optional',
            'enrol' => 'Start this course', 'sign_in_to_start' => 'Sign in to start',
            'read_more' => 'Read more', 'published' => 'Published', 'read_time' => 'min read',
            'verify_title' => 'Verify a certificate',
            'verify_help' => 'Enter the verification code printed on the certificate.',
            'verify_code' => 'Verification code', 'verify_button' => 'Check certificate',
            'verify_valid' => 'This certificate is valid.',
            'verify_expired' => 'This certificate has expired.',
            'verify_revoked' => 'This certificate has been revoked.',
            'verify_not_found' => 'No certificate matches that code.',
            'certificate_no' => 'Certificate number', 'issued_on' => 'Issued on',
            'expires_on' => 'Expires on', 'holder' => 'Holder', 'subject' => 'Course or program',
            'score' => 'Final score', 'status' => 'Status',
            'contact_name' => 'Your name', 'contact_email' => 'Work email', 'contact_phone' => 'Phone',
            'contact_org' => 'Hotel or organization', 'contact_city' => 'City',
            'contact_headcount' => 'Approximate number of employees',
            'contact_interest' => 'What you need', 'contact_message' => 'Message',
            'contact_submit' => 'Send enquiry',
            'contact_thanks' => 'Thank you. Your enquiry has been recorded and someone will reply by email.',
            'required_field' => 'This field is required.',
            'steps' => 'Steps', 'step' => 'Step', 'in_this_path' => 'Courses in this step',
            'version' => 'Version', 'effective_date' => 'Effective date', 'review_date' => 'Review date',
            'purpose' => 'Purpose', 'scope' => 'Scope', 'responsibilities' => 'Responsibilities',
            'required_tools' => 'Required tools', 'procedure' => 'Procedure', 'checklist' => 'Checklist',
            'safety_notes' => 'Safety notes', 'quality_standard' => 'Quality standard',
            'escalation' => 'Escalation', 'department' => 'Department',
            'showing' => 'Showing', 'of' => 'of', 'results' => 'results',
            'previous' => 'Previous', 'next' => 'Next',
            'not_found_title' => 'Page not found',
            'not_found_body' => 'The page you asked for does not exist. It may have been moved or the address may be mistyped.',
            'back_home' => 'Go to the home page',
            'language_switch' => 'العربية',
            'in_city' => 'Training emphasis in this city',
            'browse_all' => 'Browse all',
            'footer_note' => 'Hotel training, standard operating procedures and workforce certification.',
            'legal' => 'Legal', 'privacy' => 'Privacy', 'terms' => 'Terms',
        );
        // English is the source; application/language/site/{code}.php translates it.
        return array_map('ha_pt', $en);
    }

    /**
     * Headings for the listing pages. Kept separate from the SEO title, which
     * carries the brand suffix that belongs in a browser tab, not in an H1.
     */
    private function heading($key) {
        $map = array(
            'courses' => array(
                'en' => array('Hotel Training Courses',
                    'Courses by professional domain: front office, housekeeping, food and beverage, kitchen, sales and marketing, revenue and reservations, guest experience, quality and audit, security and safety, engineering and hotel management. Every course runs in Arabic and English.'),
            ),
            'programs' => array(
                'en' => array('Hospitality Programs',
                    'Programs group several courses into one qualification, from front office professional to food safety certified.'),
            ),
            'learning-paths' => array(
                'en' => array('Hospitality Career Paths',
                    'Each path sets out the courses for every rung of a hotel career, from the first day in the role to running the department.'),
            ),
            'hospitality-topics' => array(
                'en' => array('Hospitality Topics',
                    'Guides to hotel training by subject and by Saudi city, covering front office, housekeeping, food safety, procedures, certification and compliance.'),
            ),
            'sop' => array(
                'en' => array('Hotel SOP Resources',
                    'How a standard operating procedure is structured, versioned and acknowledged, with the procedures the academy publishes openly.'),
            ),
            'articles' => array(
                'en' => array('Hospitality Articles',
                    'Practical writing on hotel training, standard operating procedures, food safety records, compliance reporting and bilingual delivery.'),
            ),
        );
        $entry = isset($map[$key]) ? array_map('ha_pt', $map[$key]['en']) : array('', '');
        return array('title' => $entry[0], 'lede' => $entry[1]);
    }

    /**
     * The academy's operating loop, stated as four steps. This is what the
     * platform actually does end to end, which is the question a hotel asks
     * first and which no amount of feature listing answers.
     */
    private function journey_steps() {
        $en = array(
            array('Assign', 'A manager assigns a course, a programme or a procedure to a person, a department, a property or a job role, with a due date.'),
            array('Learn', 'The employee works through lessons in Arabic or English, on a phone during a quiet hour if that is when the shift allows.'),
            array('Assess', 'An assessment checks they can do the work, not that they sat through it. Attempts, timing and score are recorded.'),
            array('Certify', 'A certificate is issued with a verification code a third party can check, and the skill is recorded against the employee.'),
        );
        return array_map(function ($s) { return array_map('ha_pt', $s); }, $en);
    }

    /** The two audiences the site serves, kept explicitly apart. */
    private function audience_split() {
        $items = array(
            array(
                'eyebrow' => 'For hotels and groups',
                'title'   => 'Train your team, and prove it',
                'body'    => 'Assign training by property, department or job role. See completion, what is overdue, which certificates expire this month, and exactly who has acknowledged the current version of each procedure.',
                'cta'     => 'For hotels', 'url' => 'hotels',
            ),
            array(
                'eyebrow' => 'For individuals',
                'title'   => 'Build a hospitality career',
                'body'    => 'Start from the role you hold now and move along a path that names each step: room attendant to executive housekeeper, front office associate to front office manager.',
                'cta'     => 'Career paths', 'url' => 'learning-paths',
            ),
        );
        return array_map(function ($i) {
            foreach (array('eyebrow', 'title', 'body', 'cta') as $k) { $i[$k] = ha_pt($i[$k]); }
            return $i;
        }, $items);
    }

    public function level_label($level) {
        $en = array('foundation' => 'Foundation', 'intermediate' => 'Intermediate',
            'advanced' => 'Advanced', 'leadership' => 'Leadership');
        return isset($en[$level]) ? ha_pt($en[$level]) : $level;
    }

    // ------------------------------------------------------------------ home

    public function home($locale = 'en') {
        $this->boot($locale);

        $page = $this->ha_catalog->page('home', $this->locale);
        if (!$page) {
            return $this->not_found();
        }

        $faqs = $this->ha_catalog->faqs($this->locale);

        $this->ha_seo->prepare($this->locale, '', array('entity_type' => 'page', 'entity_id' => $page['id']),
            array('title' => $page['title'], 'description' => $page['subtitle']));
        $this->ha_seo->set_alternate(array('en' => '', 'ar' => ''));
        $this->ha_seo->set_locales($this->content_locales('ha_page_translation', 'page_id', $page['id']));
        $faq_schema = $this->ha_seo->faq_schema($faqs);
        if ($faq_schema) {
            $this->ha_seo->add_schema($faq_schema);
        }

        // The operating loop, so an answer engine can describe how the
        // platform works without paraphrasing the marketing copy.
        $steps = $this->journey_steps();
        $this->ha_seo->add_schema(array(
            '@type' => 'HowTo',
            'name'  => ha_pt('How hotel workforce training and certification works'),
            'inLanguage' => $this->locale,
            'step' => array_map(function ($step, $i) {
                return array(
                    '@type'    => 'HowToStep',
                    'position' => $i + 1,
                    'name'     => $step[0],
                    'text'     => $step[1],
                );
            }, $steps, array_keys($steps)),
        ));

        // Where the service is offered, which is the geographic claim the
        // city pages support.
        $cities = $this->ha_catalog->topics($this->locale, 'city');
        if ($cities) {
            $this->ha_seo->add_schema(array(
                '@type'       => 'Service',
                'serviceType' => ha_pt('Hotel workforce training'),
                'provider'    => array(
                    '@type' => 'EducationalOrganization',
                    'name'  => $this->ha_seo->brand(),
                    'url'   => $this->ha_seo->base_url(),
                ),
                'areaServed'  => array_map(function ($c) {
                    return array(
                        '@type' => 'City',
                        'name'  => $c['city'],
                        'containedInPlace' => array('@type' => 'Country', 'name' => 'Saudi Arabia'),
                    );
                }, $cities),
            ));
        }

        // The catalogue's shape, as a list a crawler can read directly.
        $categories = $this->ha_catalog->categories($this->locale);
        if ($categories) {
            $this->ha_seo->add_schema(array(
                '@type' => 'ItemList',
                'name'  => ha_pt('Hotel training departments'),
                'itemListElement' => array_map(function ($c, $i) {
                    return array(
                        '@type'    => 'ListItem',
                        'position' => $i + 1,
                        'name'     => $c['name'],
                        'url'      => $this->ha_seo->url('courses?category=' . rawurlencode($c['code'])),
                    );
                }, $categories, array_keys($categories)),
            ));
        }

        $this->render('home', array(
            'page'        => $page,
            'categories'  => $categories,
            'courses'     => $this->ha_catalog->courses($this->locale, array('sort' => 'newest', 'limit' => 6)),
            'programs'    => $this->ha_catalog->programs($this->locale, array('limit' => 3)),
            'paths'       => $this->ha_catalog->paths($this->locale, array('limit' => 3)),
            'articles'    => $this->ha_catalog->articles($this->locale, array('limit' => 3)),
            'faqs'        => $faqs,
            'facts'       => $this->ha_catalog->platform_facts(),
            'bilingual'   => $this->ha_catalog->bilingual_sample(),
            'procedure'   => $this->ha_catalog->procedure_sample($this->locale),
            'departments' => $this->ha_catalog->departments_with_courses($this->locale),
            'cities'      => $cities,
            'steps'       => $steps,
            'audiences'   => $this->audience_split(),
            'level_label' => array($this, 'level_label'),
        ));
    }

    // --------------------------------------------------------------- courses

    /**
     * Course filters that were retired when the catalogue was realigned to the
     * published professional domains. Category codes are query parameters, not
     * paths, so ha_redirect cannot carry them and the sitemap never listed
     * them -- but a bookmark or an inbound link still holds the old value, and
     * an unrecognised filter silently returns nothing. Mapping them keeps
     * those links landing on the domain the courses actually moved to.
     */
    private static $category_aliases = array(
        'safety-compliance'   => 'security-and-safety',
        'digital-hospitality' => 'revenue-and-reservations',
    );

    public function courses($locale = 'en') {
        $this->boot($locale);

        $category = $this->input->get('category', true);
        if ($category !== null && isset(self::$category_aliases[$category])) {
            $category = self::$category_aliases[$category];
        }

        $params = array(
            'category'   => $category,
            'department' => $this->input->get('department', true),
            'level'      => $this->input->get('level', true),
            'search'     => $this->input->get('q', true),
            'sort'       => $this->input->get('sort', true),
        );
        $per_page = 12;
        $page = max(1, (int) $this->input->get('page'));
        $params['limit'] = $per_page;
        $params['offset'] = ($page - 1) * $per_page;

        $total = $this->ha_catalog->count_courses($this->locale, $params);

        $this->ha_seo->prepare($this->locale, 'courses', array('route_key' => 'courses'), array(
            'title' => (ha_pt('Hotel Training Courses'))
                . ' | ' . $this->ha_seo->brand(),
        ));
        $this->ha_seo->breadcrumb($this->phrases()['courses'], 'courses');

        $course_rows = $this->ha_catalog->courses($this->locale, $params);
        $this->ha_seo->add_schema($this->ha_seo->item_list_schema(
            array_map(function ($c) {
                return array('name' => $c['title'], 'path' => 'courses/' . $c['slug']);
            }, $course_rows), $params['offset'], $total));

        $this->render('courses', array(
            'heading'    => $this->heading('courses'),
            'courses'    => $course_rows,
            'categories' => $this->ha_catalog->categories($this->locale),
            'filters'    => $params,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'pages'      => (int) ceil($total / $per_page),
            'level_label'=> array($this, 'level_label'),
        ));
    }

    public function course($locale, $slug) {
        $this->boot($locale);
        $slug = $this->slug($slug);
        $course = $this->ha_catalog->course($slug, $this->locale);
        if (!$course) {
            return $this->not_found();
        }

        $this->ha_seo->prepare($this->locale, 'courses/' . $course['slug'],
            array('entity_type' => 'course', 'entity_id' => $course['id']),
            array(
                'title' => $course['title'] . ' | ' . $this->ha_seo->brand(),
                'description' => $course['short_description'],
            ));
        $this->ha_seo->set_alternate(array('en' => 'courses/' . $course['slug_en'], 'ar' => 'courses/' . $course['slug_ar']));
        $this->ha_seo->set_locales($this->content_locales('ha_course_translation', 'course_id', $course['id']));
        $this->ha_seo->breadcrumb($this->phrases()['courses'], 'courses');
        if (!empty($course['category_name'])) {
            $this->ha_seo->breadcrumb($course['category_name'], 'courses?category=' . $course['category_code']);
        }
        $this->ha_seo->breadcrumb($course['title'], 'courses/' . $course['slug']);
        $this->ha_seo->add_schema($this->ha_seo->course_schema($course));
        $faq_schema = $this->ha_seo->faq_schema($course['faqs']);
        if ($faq_schema) {
            $this->ha_seo->add_schema($faq_schema);
        }

        $this->render('course', array('course' => $course, 'level_label' => array($this, 'level_label')));
    }

    // -------------------------------------------------------------- programs

    public function programs($locale = 'en') {
        $this->boot($locale);
        $this->ha_seo->prepare($this->locale, 'programs', array('route_key' => 'programs'), array(
            'title' => (ha_pt('Hospitality Programs'))
                . ' | ' . $this->ha_seo->brand(),
        ));
        $this->ha_seo->breadcrumb($this->phrases()['programs'], 'programs');
        $program_rows = $this->ha_catalog->programs($this->locale);
        $this->ha_seo->add_schema($this->ha_seo->item_list_schema(array_map(function ($r) {
            return array('name' => $r['title'], 'path' => 'programs/' . $r['slug']);
        }, $program_rows)));
        $this->render('programs', array(
            'heading'     => $this->heading('programs'),
            'programs'    => $program_rows,
            'level_label' => array($this, 'level_label'),
        ));
    }

    public function program($locale, $slug) {
        $this->boot($locale);
        $slug = $this->slug($slug);
        $program = $this->ha_catalog->program($slug, $this->locale);
        if (!$program) {
            return $this->not_found();
        }
        $this->ha_seo->prepare($this->locale, 'programs/' . $program['slug'],
            array('entity_type' => 'program', 'entity_id' => $program['id']),
            array('title' => $program['title'] . ' | ' . $this->ha_seo->brand(),
                  'description' => $program['short_description']));
        $this->ha_seo->set_alternate(array('en' => 'programs/' . $program['slug_en'], 'ar' => 'programs/' . $program['slug_ar']));
        $this->ha_seo->set_locales($this->content_locales('ha_program_translation', 'program_id', $program['id']));
        $this->ha_seo->breadcrumb($this->phrases()['programs'], 'programs');
        $this->ha_seo->breadcrumb($program['title'], 'programs/' . $program['slug']);
        $this->render('program', array('program' => $program, 'level_label' => array($this, 'level_label')));
    }

    // -------------------------------------------------------- learning paths

    public function paths($locale = 'en') {
        $this->boot($locale);
        $this->ha_seo->prepare($this->locale, 'learning-paths', array('route_key' => 'learning-paths'), array(
            'title' => (ha_pt('Hospitality Career Paths'))
                . ' | ' . $this->ha_seo->brand(),
        ));
        $this->ha_seo->breadcrumb($this->phrases()['learning_paths'], 'learning-paths');
        $path_rows = $this->ha_catalog->paths($this->locale);
        $this->ha_seo->add_schema($this->ha_seo->item_list_schema(array_map(function ($r) {
            return array('name' => $r['title'], 'path' => 'learning-paths/' . $r['slug']);
        }, $path_rows)));
        $this->render('paths', array('heading' => $this->heading('learning-paths'),
            'paths' => $path_rows));
    }

    public function path($locale, $slug) {
        $this->boot($locale);
        $slug = $this->slug($slug);
        $path = $this->ha_catalog->path($slug, $this->locale);
        if (!$path) {
            return $this->not_found();
        }
        $this->ha_seo->prepare($this->locale, 'learning-paths/' . $path['slug'],
            array('entity_type' => 'learning_path', 'entity_id' => $path['id']),
            array('title' => $path['title'] . ' | ' . $this->ha_seo->brand(),
                  'description' => $path['summary']));
        $this->ha_seo->set_alternate(array('en' => 'learning-paths/' . $path['slug_en'], 'ar' => 'learning-paths/' . $path['slug_ar']));
        $this->ha_seo->set_locales($this->content_locales_i18n('path', $path['id']));
        $this->ha_seo->breadcrumb($this->phrases()['learning_paths'], 'learning-paths');
        $this->ha_seo->breadcrumb($path['title'], 'learning-paths/' . $path['slug']);
        $this->render('path', array('path' => $path, 'level_label' => array($this, 'level_label')));
    }

    // ---------------------------------------------------------------- topics

    public function topics($locale = 'en') {
        $this->boot($locale);
        $this->ha_seo->prepare($this->locale, 'hospitality-topics', array('route_key' => 'hospitality-topics'), array(
            'title' => (ha_pt('Hospitality Topics'))
                . ' | ' . $this->ha_seo->brand(),
        ));
        $this->ha_seo->breadcrumb($this->phrases()['topics'], 'hospitality-topics');
        $all = $this->ha_catalog->topics($this->locale);
        $pillars = array();
        $cities = array();
        foreach ($all as $t) {
            if ($t['topic_type'] === 'city') {
                $cities[] = $t;
            } else {
                $pillars[] = $t;
            }
        }
        $this->ha_seo->add_schema($this->ha_seo->item_list_schema(array_map(function ($r) {
            return array('name' => $r['title'], 'path' => 'hospitality-topics/' . $r['slug']);
        }, array_merge($pillars, $cities))));
        $this->render('topics', array('heading' => $this->heading('hospitality-topics'),
            'pillars' => $pillars, 'cities' => $cities));
    }

    public function topic($locale, $slug) {
        $this->boot($locale);
        $slug = $this->slug($slug);
        $topic = $this->ha_catalog->topic($slug, $this->locale);
        if (!$topic) {
            return $this->not_found();
        }
        $this->ha_seo->prepare($this->locale, 'hospitality-topics/' . $topic['slug'],
            array('entity_type' => 'topic', 'entity_id' => $topic['id']),
            array('title' => $topic['title'] . ' | ' . $this->ha_seo->brand()));
        $this->ha_seo->set_alternate(array('en' => 'hospitality-topics/' . $topic['slug_en'], 'ar' => 'hospitality-topics/' . $topic['slug_ar']));
        $this->ha_seo->set_locales($this->content_locales_i18n('topic', $topic['id']));
        $this->ha_seo->breadcrumb($this->phrases()['topics'], 'hospitality-topics');
        $this->ha_seo->breadcrumb($topic['title'], 'hospitality-topics/' . $topic['slug']);
        $faq_schema = $this->ha_seo->faq_schema($topic['faqs']);
        if ($faq_schema) {
            $this->ha_seo->add_schema($faq_schema);
        }
        $this->render('topic', array('topic' => $topic, 'level_label' => array($this, 'level_label')));
    }

    // ------------------------------------------------------------------ SOPs

    public function sops($locale = 'en') {
        $this->boot($locale);
        $this->ha_seo->prepare($this->locale, 'sop', array('route_key' => 'sop'), array(
            'title' => (ha_pt('Hotel SOP Resources'))
                . ' | ' . $this->ha_seo->brand(),
        ));
        $this->ha_seo->breadcrumb($this->phrases()['sop'], 'sop');
        // The empty state points at the SOP topic guide, in the reader's
        // language, so the link is never a hardcoded English slug.
        $topic = $this->db->select('slug_' . $this->locale . ' AS slug')
            ->get_where('ha_topic', array('code' => 'hotel-sop-training'))->row_array();

        $this->render('sops', array(
            'heading'         => $this->heading('sop'),
            'sops'            => $this->ha_catalog->public_sops($this->locale),
            'categories'      => $this->ha_catalog->sop_categories($this->locale),
            'sop_topic_slug'  => $topic ? $topic['slug'] : 'hotel-sop-training',
        ));
    }

    public function sop($locale, $slug) {
        $this->boot($locale);
        $slug = $this->slug($slug);
        $sop = $this->ha_catalog->public_sop($slug, $this->locale);
        if (!$sop) {
            return $this->not_found();
        }
        $this->ha_seo->prepare($this->locale, 'sop/' . $sop['slug'],
            array('entity_type' => 'sop', 'entity_id' => $sop['id']),
            array('title' => $sop['title'] . ' | ' . $this->ha_seo->brand(),
                  'description' => $sop['purpose']));
        $this->ha_seo->set_alternate(array('en' => 'sop/' . $sop['slug_en'], 'ar' => 'sop/' . $sop['slug_ar']));
        $this->ha_seo->set_locales(array());   // procedure text is authored in English and Arabic only
        $this->ha_seo->breadcrumb($this->phrases()['sop'], 'sop');
        $this->ha_seo->breadcrumb($sop['title'], 'sop/' . $sop['slug']);
        $this->ha_seo->add_schema(array(
            '@type' => 'HowTo',
            'name'  => $sop['title'],
            'description' => $sop['purpose'],
            'inLanguage' => $this->locale,
            'step' => array_map(function ($text, $i) {
                return array('@type' => 'HowToStep', 'position' => $i + 1, 'text' => $text);
            }, $sop['procedure_steps'], array_keys($sop['procedure_steps'])),
        ));
        $this->render('sop', array('sop' => $sop));
    }

    // -------------------------------------------------------------- articles

    public function articles($locale = 'en') {
        $this->boot($locale);
        $params = array(
            'category' => $this->input->get('category', true),
            'search'   => $this->input->get('q', true),
        );
        $per_page = 9;
        $page = max(1, (int) $this->input->get('page'));
        $total = $this->ha_catalog->count_articles($this->locale, $params);
        $params['limit'] = $per_page;
        $params['offset'] = ($page - 1) * $per_page;

        $this->ha_seo->prepare($this->locale, 'articles', array('route_key' => 'articles'), array(
            'title' => (ha_pt('Hospitality Articles'))
                . ' | ' . $this->ha_seo->brand(),
        ));
        $this->ha_seo->breadcrumb($this->phrases()['articles'], 'articles');

        $article_rows = $this->ha_catalog->articles($this->locale, $params);
        $this->ha_seo->add_schema($this->ha_seo->item_list_schema(array_map(function ($r) {
            return array('name' => $r['title'], 'path' => 'articles/' . $r['slug']);
        }, $article_rows), $params['offset'], $total));

        $this->render('articles', array(
            'heading'    => $this->heading('articles'),
            'articles'   => $article_rows,
            'categories' => $this->ha_catalog->categories($this->locale),
            'filters'    => $params,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'pages'      => (int) ceil($total / $per_page),
        ));
    }

    public function article($locale, $slug) {
        $this->boot($locale);
        $slug = $this->slug($slug);
        $article = $this->ha_catalog->article($slug, $this->locale);
        if (!$article) {
            return $this->not_found();
        }
        $this->ha_catalog->increment_article_views($article['id']);
        $this->ha_seo->prepare($this->locale, 'articles/' . $article['slug'],
            array('entity_type' => 'article', 'entity_id' => $article['id']),
            array('title' => $article['title'] . ' | ' . $this->ha_seo->brand(),
                  'description' => $article['excerpt'],
                  // An article says so, rather than claiming to be a website.
                  'og_type'      => 'article',
                  'published_at' => isset($article['published_at']) ? $article['published_at'] : '',
                  'updated_at'   => isset($article['updated_at']) ? $article['updated_at'] : '',
                  'author'       => isset($article['author_name']) ? $article['author_name'] : ''));
        $this->ha_seo->set_alternate(array('en' => 'articles/' . $article['slug_en'], 'ar' => 'articles/' . $article['slug_ar']));
        $this->ha_seo->set_locales($this->content_locales('ha_article_translation', 'article_id', $article['id']));
        $this->ha_seo->breadcrumb($this->phrases()['articles'], 'articles');
        $this->ha_seo->breadcrumb($article['title'], 'articles/' . $article['slug']);
        $this->ha_seo->add_schema($this->ha_seo->article_schema($article));

        $this->render('article', array('article' => $article));
    }

    // -------------------------------------------------------- static pages

    public function page($locale, $code) {
        $this->boot($locale);
        $page = $this->ha_catalog->page($code, $this->locale);
        if (!$page) {
            return $this->not_found();
        }
        $this->ha_seo->prepare($this->locale, $page['slug'],
            array('entity_type' => 'page', 'entity_id' => $page['id']),
            array('title' => $page['title'] . ' | ' . $this->ha_seo->brand(),
                  'description' => $page['subtitle']));
        // Page slugs are translated, so the counterpart URL is the other
        // language's slug, never this one with the locale swapped.
        $this->ha_seo->set_alternate(array('en' => $page['slug_en'], 'ar' => $page['slug_ar']));
        $this->ha_seo->set_locales($this->content_locales('ha_page_translation', 'page_id', $page['id']));
        $this->ha_seo->breadcrumb($page['title'], $page['slug']);
        // Page-builder sections, their FAQ (AEO) and the page's place (GEO) become structured data.
        $sections = array();
        if ($this->db->table_exists('ha_page_section')) {
            $this->load->library('ha_page_builder');
            $sections = $this->ha_page_builder->sections($page['id']);
            $faq = $this->ha_page_builder->faq_items($page['id'], $this->locale);
            if ($faq && ($schema = $this->ha_seo->faq_schema($faq))) {
                $this->ha_seo->add_schema($schema);
            }
            $row = $this->db->select('schema_type, geo_region, geo_placename, geo_lat, geo_lng')->get_where('ha_page', array('id' => $page['id']))->row_array();
            if ($row && ($row['geo_placename'] || $row['schema_type'] === 'LocalBusiness')) {
                $place = array('@type' => $row['schema_type'] === 'LocalBusiness' ? 'LocalBusiness' : 'Place', 'name' => $page['title'],
                    'address' => array('@type' => 'PostalAddress', 'addressLocality' => $row['geo_placename'], 'addressRegion' => $row['geo_region'], 'addressCountry' => 'SA'));
                if ($row['geo_lat'] !== null && $row['geo_lng'] !== null) {
                    $place['geo'] = array('@type' => 'GeoCoordinates', 'latitude' => (float) $row['geo_lat'], 'longitude' => (float) $row['geo_lng']);
                }
                $this->ha_seo->add_schema($place);
            }
        }
        $this->render('page', array('page' => $page, 'sections' => $sections));
    }

    public function about($locale = 'en')       { $this->page($locale, 'about'); }
    public function hotels($locale = 'en')      { $this->page($locale, 'for-hotels'); }
    public function hotels_training($locale = 'en') { $this->page($locale, 'hotels-training'); }
    public function privacy($locale = 'en')     { $this->page($locale, 'privacy'); }
    public function terms($locale = 'en')       { $this->page($locale, 'terms'); }

    public function certificates($locale = 'en') {
        $this->boot($locale);
        $page = $this->ha_catalog->page('certificates-info', $this->locale);
        if (!$page) {
            return $this->not_found();
        }
        $this->ha_seo->prepare($this->locale, $page['slug'],
            array('entity_type' => 'page', 'entity_id' => $page['id']),
            array('title' => $page['title'] . ' | ' . $this->ha_seo->brand(),
                  'description' => $page['subtitle']));
        $this->ha_seo->set_alternate(array('en' => $page['slug_en'], 'ar' => $page['slug_ar']));
        $this->ha_seo->set_locales($this->content_locales('ha_page_translation', 'page_id', $page['id']));
        $this->ha_seo->breadcrumb($this->phrases()['certificates'], $page['slug']);
        $this->render('page', array('page' => $page));
    }

    // ---------------------------------------------------------- verification

    public function verify($locale = 'en', $code = null) {
        $this->boot($locale);

        $submitted = $code !== null ? urldecode($code) : $this->input->post('code', true);
        $result = null;
        if ($submitted !== null && $submitted !== '') {
            $result = $this->ha_catalog->verify_certificate($submitted);
        }

        $this->ha_seo->prepare($this->locale, 'verify', array('route_key' => 'verify'), array(
            'title' => (ha_pt('Verify a Certificate'))
                . ' | ' . $this->ha_seo->brand(),
        ));
        // A result page for one specific code should not be indexed on its own.
        if ($result) {
            $this->ha_seo->set('robots', 'noindex,follow');
        }
        $this->ha_seo->breadcrumb($this->phrases()['verify_title'], 'verify');

        $this->render('verify', array('result' => $result, 'submitted' => $submitted));
    }

    // ---------------------------------------------------------------- contact

    public function contact($locale = 'en') {
        $this->boot($locale);
        $page = $this->ha_catalog->page('contact', $this->locale);
        $sent = false;
        $errors = array();

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('name', 'name', 'required|trim|max_length[190]');
            $this->form_validation->set_rules('email', 'email', 'required|trim|valid_email|max_length[190]');
            $this->form_validation->set_rules('organization_name', 'organization', 'trim|max_length[190]');
            $this->form_validation->set_rules('phone', 'phone', 'trim|max_length[60]');
            $this->form_validation->set_rules('city', 'city', 'trim|max_length[120]');
            $this->form_validation->set_rules('message', 'message', 'required|trim|max_length[4000]');

            if ($this->form_validation->run()) {
                $interest = $this->input->post('interest', true);
                $allowed = array('hotel_training', 'course', 'program', 'sop', 'certification', 'other');
                $this->db->insert('ha_lead', array(
                    'name'              => $this->input->post('name', true),
                    'email'             => $this->input->post('email', true),
                    'phone'             => $this->input->post('phone', true),
                    'organization_name' => $this->input->post('organization_name', true),
                    'city'              => $this->input->post('city', true),
                    'headcount'         => $this->input->post('headcount', true),
                    'interest'          => in_array($interest, $allowed, true) ? $interest : 'other',
                    'message'           => $this->input->post('message', true),
                    'source_page'       => current_url(),
                    'locale'            => $this->locale,
                    'status'            => 'new',
                    'ip_address'        => $this->input->ip_address(),
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ));
                $sent = true;
            } else {
                $errors = $this->form_validation->error_array();
            }
        }

        $this->ha_seo->prepare($this->locale, $page ? $page['slug'] : 'contact',
            $page ? array('entity_type' => 'page', 'entity_id' => $page['id']) : array(),
            array('title' => ($page ? $page['title'] : $this->phrases()['contact']) . ' | ' . $this->ha_seo->brand(),
                  'description' => $page ? $page['subtitle'] : ''));
        if ($page) {
            $this->ha_seo->set_alternate(array('en' => $page['slug_en'], 'ar' => $page['slug_ar']));
            $this->ha_seo->set_locales($this->content_locales('ha_page_translation', 'page_id', $page['id']));
        }
        $this->ha_seo->breadcrumb($this->phrases()['contact'], $page ? $page['slug'] : 'contact');

        $this->render('contact', array(
            'page'   => $page,
            'sent'   => $sent,
            'errors' => $errors,
            'old'    => $this->input->post(null, true) ?: array(),
        ));
    }

    // ---------------------------------------------------------------- credits

    /**
     * Photograph credits. Several of the licences used on this site require
     * the author to be named, so the page exists as a condition of using the
     * images rather than as a courtesy.
     */
    public function credits($locale = 'en') {
        $this->boot($locale);

        $title = ha_pt('Photo credits');
        $lede = ha_pt('Every photograph on this site comes from Wikimedia Commons under a licence that permits this use. The images whose licence requires the photographer to be named are listed here.');

        $this->ha_seo->prepare($this->locale, 'credits', array(), array(
            'title' => $title . ' | ' . $this->ha_seo->brand(),
            'description' => $lede,
        ));
        $this->ha_seo->set_alternate(array('en' => 'credits', 'ar' => 'credits'));
        $this->ha_seo->breadcrumb($title, 'credits');

        $this->render('credits', array(
            'page_title'  => $title,
            'lede'        => $lede,
            'credits'     => $this->ha_catalog->image_credits(),
            'uncredited'  => $this->ha_catalog->uncredited_image_count(),
        ));
    }

    // ----------------------------------------------------------------- search

    public function search($locale = 'en') {
        $this->boot($locale);
        $term = (string) $this->input->get('q', true);
        $results = $term === '' ? array() : $this->ha_catalog->search($term, $this->locale);

        $this->ha_seo->prepare($this->locale, 'search', array(), array(
            'title' => $this->phrases()['search'] . ' | ' . $this->ha_seo->brand(),
        ))->set('robots', 'noindex,follow');
        $this->ha_seo->breadcrumb($this->phrases()['search'], 'search');

        $this->render('search', array('term' => $term, 'results' => $results));
    }

    // ------------------------------------------------------- technical routes

    public function sitemap() {
        $this->output
            ->set_content_type('application/xml', 'utf-8')
            ->set_output($this->ha_seo->render_sitemap());
    }

    public function robots() {
        $this->output
            ->set_content_type('text/plain', 'utf-8')
            ->set_output($this->ha_seo->render_robots());
    }

    /** Photography with its licence attached, for image search. */
    public function image_sitemap() {
        $this->output
            ->set_content_type('application/xml', 'utf-8')
            ->set_output($this->ha_seo->render_image_sitemap());
    }

    /**
     * An index over every sitemap the site publishes, so one address is enough
     * to submit to Search Console and Yandex Webmaster.
     */
    public function sitemap_index() {
        $base = $this->ha_seo->base_url();
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (array('academy-sitemap.xml', 'image-sitemap.xml', 'lms-sitemap.xml') as $map) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>' . html_escape($base . '/' . $map) . "</loc>\n";
            $xml .= '    <lastmod>' . date('Y-m-d') . "</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }
        $xml .= '</sitemapindex>';
        $this->output->set_content_type('application/xml', 'utf-8')->set_output($xml);
    }

    /** A plain-text map of the site for AI assistants. See Ha_seo::render_llms. */
    public function llms() {
        $this->output
            ->set_content_type('text/plain', 'utf-8')
            ->set_output($this->ha_seo->render_llms(false));
    }

    public function llms_full() {
        $this->output
            ->set_content_type('text/plain', 'utf-8')
            ->set_output($this->ha_seo->render_llms(true));
    }

    /** Entry point that sends a bare visit to the right locale. */
    public function index() {
        /*
         * The application ships two public front ends and the site root can
         * only serve one of them. Which one is an administrator's decision,
         * not a routing constant: with the academy site fixed at the root,
         * the Home Page Builder and the theme switcher both appeared broken,
         * because they configure the Academy LMS front end at /home and
         * nobody ever landed there.
         *
         *   academy  the bilingual SEO site in application/views/academy
         *   lms      the Academy LMS theme, which the builder and themes drive
         */
        if (get_frontend_settings('root_frontend') === 'lms') {
            redirect(base_url('home'), 'location', 302);
        }

        $locale = $this->negotiate_locale(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '');
        redirect(base_url($locale), 'location', 302);
    }

    /**
     * Last route in the table: resolve a static page by its slug in the
     * requested language, or render the academy 404. Extra segments arrive as
     * separate arguments because the router splits on "/", so they are joined
     * back into the slug that was actually requested.
     */
    public function page_by_slug($locale = 'en', ...$segments) {
        $this->boot($locale);
        $slug = $this->slug(implode('/', $segments));

        $page = $this->db
            ->select('code')
            ->from('ha_page')
            ->where('slug_' . $this->locale, $slug)
            ->where('status', 'published')
            ->get()->row_array();

        if (!$page) {
            return $this->not_found();
        }
        $this->page($this->locale, $page['code']);
    }

    /** Catch-all so an unknown academy URL gets the 404 page, not a blank screen. */
    public function missing($locale = 'en') {
        $this->boot($locale);
        $this->not_found();
    }
}
