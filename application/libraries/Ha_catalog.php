<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Public read model for the academy website.
 *
 * Everything here returns published content only. Draft, review and archived
 * records never leave this class, which is what keeps the plan's rule that
 * unpublished content must never appear as public (section 7) enforced in one
 * place rather than in every template.
 *
 * Every method takes a locale and returns rows already resolved to that
 * language, so a view never has to choose between a _en and an _ar column.
 */
class Ha_catalog {

    /** @var CI_Controller */
    protected $CI;

    /** @var CI_DB_query_builder */
    protected $db;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->db = $this->CI->db;
    }

    /** Any enabled language (config/ha_locales.php); anything else is English. */
    private function locale($locale) {
        if (!function_exists('ha_locale_enabled')) {
            require_once APPPATH . 'helpers/ha_locale_helper.php';
        }
        return ha_locale_enabled($locale) ? $locale : 'en';
    }

    /**
     * Suffix for bilingual column pairs (slug_en/slug_ar, title_en/title_ar ...).
     * Only English and Arabic have columns; every other language uses the English
     * column (URLs stay stable: /hi/courses/{english-slug}) and gets its text from
     * translation tables or the ha_i18n_text overlay instead.
     */
    private function col($locale) {
        return $locale === 'ar' ? 'ar' : 'en';
    }

    /**
     * Translation table to join for $locale. English joins the table filtered by
     * locale (tr_on). Any other language joins a derived table holding that
     * language's rows plus the English row for every entity not yet translated,
     * so an untranslated course shows in English instead of disappearing.
     */
    private function tr($table, $fk, $locale) {
        if ($locale === 'en') {
            return $table;
        }
        $l = $this->db->escape($locale);
        return "(SELECT * FROM $table WHERE locale = $l UNION ALL SELECT f.* FROM $table f WHERE f.locale = 'en'"
            . " AND NOT EXISTS (SELECT 1 FROM $table x WHERE x.$fk = f.$fk AND x.locale = $l))";
    }

    private function tr_on($alias, $locale) {
        return $locale === 'en' ? " AND $alias.locale = 'en'" : '';
    }

    /** Rows of a per-locale child table (outcomes, FAQs), falling back to English when none exist in $locale. */
    private function localised_rows($table, $select, $course_id, $locale) {
        foreach (array_unique(array($locale, 'en')) as $l) {
            $rows = $this->db->select($select)->where(array('course_id' => (int) $course_id, 'locale' => $l))
                ->order_by('sort_order', 'ASC')->get($table)->result_array();
            if ($rows) {
                return $rows;
            }
        }
        return array();
    }

    /**
     * Whether $locale has its own text for an entity (not the English fallback).
     * Used to keep untranslated pages out of hreflang/sitemaps (noindex) so a
     * language never publishes thin duplicates of the English site.
     */
    public function is_translated($table, $fk, $id, $locale) {
        if ($locale === 'en' || $locale === 'ar') {
            return true;
        }
        return (bool) $this->db->where(array($fk => (int) $id, 'locale' => $locale))->count_all_results($table);
    }

    /**
     * Overlays translations for entities that store text in en/ar column pairs
     * (topics, learning paths and steps, FAQs, menu items, SOP categories, departments):
     * rows of ha_i18n_text (entity, entity_id, field, locale, value) replace the
     * English value for $locale. Rows must carry `id`.
     */
    public function overlay($entity, array $rows, array $fields, $locale, $single = false) {
        if ($locale === 'en' || $locale === 'ar' || !$rows || !$this->db->table_exists('ha_i18n_text')) {
            return $rows;
        }
        $list = $single ? array($rows) : $rows;
        $ids = array_filter(array_map(function ($r) { return isset($r['id']) ? (int) $r['id'] : 0; }, $list));
        if (!$ids) {
            return $rows;
        }
        $map = array();
        foreach ($this->db->select('entity_id, field, value')->where('entity', $entity)->where('locale', $locale)
            ->where_in('entity_id', $ids)->where_in('field', $fields)->get('ha_i18n_text')->result_array() as $t) {
            $map[$t['entity_id']][$t['field']] = $t['value'];
        }
        foreach ($list as &$r) {
            if (isset($r['id'], $map[$r['id']])) {
                foreach ($map[$r['id']] as $field => $value) {
                    if (trim((string) $value) !== '') {
                        $r[$field] = $value;
                    }
                }
            }
        }
        unset($r);
        return $single ? $list[0] : $list;
    }

    // --------------------------------------------------------------- courses

    public function courses($locale = 'en', array $params = array()) {
        $locale = $this->locale($locale);
        $db = $this->db
            ->select('c.id, c.code, c.level, c.duration_minutes, c.thumbnail, c.is_free, c.price,
                      c.currency, c.certificate_eligible, c.department_code, c.rating_avg, c.rating_count,
                      c.enrollment_count, c.published_at')
            ->select('c.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.short_description', false)
            ->select('ct.name AS category_name, cat.code AS category_code', false)
            ->select('cat.slug_' . $this->col($locale) . ' AS category_slug', false)
            ->from('ha_course c')
            ->join($this->tr('ha_course_translation', 'course_id', $locale) . ' t', 't.course_id = c.id' . $this->tr_on('t', $locale), 'left', false)
            ->join('ha_category cat', 'cat.id = c.category_id', 'left')
            ->join($this->tr('ha_category_translation', 'category_id', $locale) . ' ct', 'ct.category_id = cat.id' . $this->tr_on('ct', $locale), 'left', false)
            ->where('c.status', 'published');

        if (!empty($params['category'])) {
            $db->where('cat.code', $params['category']);
        }
        if (!empty($params['department'])) {
            $db->where('c.department_code', $params['department']);
        }
        if (!empty($params['level'])) {
            $db->where('c.level', $params['level']);
        }
        if (!empty($params['search'])) {
            $db->group_start()->like('t.title', $params['search'])
                ->or_like('t.short_description', $params['search'])
                ->or_like('c.code', $params['search'])->group_end();
        }
        if (!empty($params['ids'])) {
            $db->where_in('c.id', array_map('intval', $params['ids']));
        }

        $sort = isset($params['sort']) ? $params['sort'] : 'title';
        $map = array(
            'title'    => 't.title ASC',
            'newest'   => 'c.published_at DESC',
            'duration' => 'c.duration_minutes ASC',
            'level'    => 'FIELD(c.level, "foundation","intermediate","advanced","leadership") ASC',
        );
        $db->order_by(isset($map[$sort]) ? $map[$sort] : $map['title'], '', false);

        if (!empty($params['limit'])) {
            $db->limit((int) $params['limit'], isset($params['offset']) ? (int) $params['offset'] : 0);
        }
        return $db->get()->result_array();
    }

    public function count_courses($locale = 'en', array $params = array()) {
        $locale = $this->locale($locale);
        $db = $this->db->from('ha_course c')
            ->join($this->tr('ha_course_translation', 'course_id', $locale) . ' t', 't.course_id = c.id' . $this->tr_on('t', $locale), 'left', false)
            ->join('ha_category cat', 'cat.id = c.category_id', 'left')
            ->where('c.status', 'published');
        if (!empty($params['category'])) {
            $db->where('cat.code', $params['category']);
        }
        if (!empty($params['department'])) {
            $db->where('c.department_code', $params['department']);
        }
        if (!empty($params['level'])) {
            $db->where('c.level', $params['level']);
        }
        if (!empty($params['search'])) {
            $db->group_start()->like('t.title', $params['search'])
                ->or_like('t.short_description', $params['search'])
                ->or_like('c.code', $params['search'])->group_end();
        }
        return (int) $db->count_all_results();
    }

    /** One published course with everything its page needs. */
    public function course($slug, $locale = 'en') {
        $locale = $this->locale($locale);
        $course = $this->db
            ->select('c.*')
            ->select('c.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.short_description, t.description, t.requirements', false)
            ->select('ct.name AS category_name, cat.code AS category_code', false)
            ->select('cat.slug_' . $this->col($locale) . ' AS category_slug', false)
            ->select("TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS instructor_name", false)
            ->select('u.biography AS instructor_bio, u.image AS instructor_image', false)
            ->from('ha_course c')
            ->join($this->tr('ha_course_translation', 'course_id', $locale) . ' t', 't.course_id = c.id' . $this->tr_on('t', $locale), 'left', false)
            ->join('ha_category cat', 'cat.id = c.category_id', 'left')
            ->join($this->tr('ha_category_translation', 'category_id', $locale) . ' ct', 'ct.category_id = cat.id' . $this->tr_on('ct', $locale), 'left', false)
            ->join('users u', 'u.id = c.instructor_user_id', 'left')
            ->where('c.slug_' . $this->col($locale), $slug)
            ->where('c.status', 'published')
            ->get()->row_array();

        if (!$course) {
            return null;
        }

        $course['outcomes'] = array_column($this->localised_rows('ha_course_outcome', 'body', $course['id'], $locale), 'body');
        $course['faqs'] = $this->localised_rows('ha_course_faq', 'question, answer', $course['id'], $locale);

        $course['curriculum'] = $this->curriculum($course['id'], $locale);

        $course['skills'] = $this->db
            ->select('s.code, s.name_' . $this->col($locale) . ' AS name, cs.awards_level', false)
            ->from('ha_course_skill cs')
            ->join('ha_skill s', 's.id = cs.skill_id')
            ->where('cs.course_id', $course['id'])
            ->order_by('s.name_' . $this->col($locale), 'ASC')
            ->get()->result_array();

        $course['prerequisites'] = $this->db
            ->select('c2.slug_' . $this->col($locale) . ' AS slug, t2.title', false)
            ->from('ha_course_prerequisite p')
            ->join('ha_course c2', 'c2.id = p.prerequisite_course_id')
            ->join($this->tr('ha_course_translation', 'course_id', $locale) . ' t2', 't2.course_id = c2.id' . $this->tr_on('t2', $locale), 'left', false)
            ->where('p.course_id', $course['id'])
            ->where('c2.status', 'published')
            ->get()->result_array();

        $course['related'] = $this->courses($locale, array(
            'category' => $course['category_code'],
            'limit'    => 4,
        ));
        foreach ($course['related'] as $i => $r) {
            if ((int) $r['id'] === (int) $course['id']) {
                unset($course['related'][$i]);
            }
        }
        $course['related'] = array_slice(array_values($course['related']), 0, 3);

        $course['programs'] = $this->db
            ->select('p.slug_' . $this->col($locale) . ' AS slug, pt.title', false)
            ->from('ha_program_course pc')
            ->join('ha_program p', 'p.id = pc.program_id')
            ->join($this->tr('ha_program_translation', 'program_id', $locale) . ' pt', 'pt.program_id = p.id' . $this->tr_on('pt', $locale), 'left', false)
            ->where('pc.course_id', $course['id'])
            ->where('p.status', 'published')
            ->get()->result_array();

        return $course;
    }

    /** Sections with their published lessons, preview flag intact. */
    public function curriculum($course_id, $locale = 'en') {
        $locale = $this->locale($locale);
        $sections = $this->db
            ->select('id, title_' . $this->col($locale) . ' AS title, sort_order', false)
            ->where('course_id', (int) $course_id)
            ->order_by('sort_order', 'ASC')
            ->get('ha_course_section')->result_array();

        $lessons = $this->db
            ->select('l.id, l.section_id, l.lesson_type, l.duration_seconds, l.is_preview,
                      l.is_mandatory, l.completion_rule, l.sort_order')
            ->select('lt.title, lt.objective', false)
            ->from('ha_lesson l')
            ->join($this->tr('ha_lesson_translation', 'lesson_id', $locale) . ' lt', 'lt.lesson_id = l.id' . $this->tr_on('lt', $locale), 'left', false)
            ->where('l.course_id', (int) $course_id)
            ->where('l.status', 'published')
            ->order_by('l.sort_order', 'ASC')
            ->get()->result_array();

        foreach ($sections as $i => $section) {
            $sections[$i]['lessons'] = array();
            $sections[$i]['duration_seconds'] = 0;
            foreach ($lessons as $lesson) {
                if ((int) $lesson['section_id'] === (int) $section['id']) {
                    $sections[$i]['lessons'][] = $lesson;
                    $sections[$i]['duration_seconds'] += (int) $lesson['duration_seconds'];
                }
            }
        }
        return $sections;
    }

    // -------------------------------------------------------------- programs

    public function programs($locale = 'en', array $params = array()) {
        $locale = $this->locale($locale);
        $db = $this->db
            ->select('p.id, p.code, p.level, p.duration_hours, p.thumbnail')
            ->select('p.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.short_description', false)
            ->select('(SELECT COUNT(*) FROM ha_program_course pc WHERE pc.program_id = p.id) AS course_count', false)
            ->from('ha_program p')
            ->join($this->tr('ha_program_translation', 'program_id', $locale) . ' t', 't.program_id = p.id' . $this->tr_on('t', $locale), 'left', false)
            ->where('p.status', 'published')
            ->order_by('t.title', 'ASC');
        if (!empty($params['limit'])) {
            $db->limit((int) $params['limit']);
        }
        return $db->get()->result_array();
    }

    public function program($slug, $locale = 'en') {
        $locale = $this->locale($locale);
        $program = $this->db
            ->select('p.*')
            ->select('p.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.short_description, t.description, t.outcomes, t.prerequisites', false)
            ->from('ha_program p')
            ->join($this->tr('ha_program_translation', 'program_id', $locale) . ' t', 't.program_id = p.id' . $this->tr_on('t', $locale), 'left', false)
            ->where('p.slug_' . $this->col($locale), $slug)
            ->where('p.status', 'published')
            ->get()->row_array();
        if (!$program) {
            return null;
        }
        $program['courses'] = $this->db
            ->select('c.id, c.level, c.duration_minutes, pc.is_mandatory, pc.sort_order')
            ->select('c.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.short_description', false)
            ->from('ha_program_course pc')
            ->join('ha_course c', 'c.id = pc.course_id')
            ->join($this->tr('ha_course_translation', 'course_id', $locale) . ' t', 't.course_id = c.id' . $this->tr_on('t', $locale), 'left', false)
            ->where('pc.program_id', $program['id'])
            ->where('c.status', 'published')
            ->order_by('pc.sort_order', 'ASC')
            ->get()->result_array();
        return $program;
    }

    // --------------------------------------------------------- learning paths

    public function paths($locale = 'en', array $params = array()) {
        $locale = $this->locale($locale);
        $db = $this->db
            ->select('p.id, p.code, p.department_code, p.thumbnail')
            ->select('p.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('p.title_' . $this->col($locale) . ' AS title', false)
            ->select('p.summary_' . $this->col($locale) . ' AS summary', false)
            ->select('(SELECT COUNT(*) FROM ha_path_step s WHERE s.path_id = p.id) AS step_count', false)
            ->from('ha_learning_path p')
            ->where('p.status', 'published')
            ->order_by('p.title_' . $this->col($locale), 'ASC');
        if (!empty($params['limit'])) {
            $db->limit((int) $params['limit']);
        }
        return $this->overlay('path', $db->get()->result_array(), array('title', 'summary'), $locale);
    }

    public function path($slug, $locale = 'en') {
        $locale = $this->locale($locale);
        $path = $this->db
            ->select('p.*')
            ->select('p.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('p.title_' . $this->col($locale) . ' AS title', false)
            ->select('p.summary_' . $this->col($locale) . ' AS summary', false)
            ->select('p.description_' . $this->col($locale) . ' AS description', false)
            ->from('ha_learning_path p')
            ->where('p.slug_' . $this->col($locale), $slug)
            ->where('p.status', 'published')
            ->get()->row_array();
        if (!$path) {
            return null;
        }
        $path = $this->overlay('path', $path, array('title', 'summary', 'description'), $locale, true);

        $steps = $this->db
            ->select('s.id, s.sort_order')
            ->select('s.title_' . $this->col($locale) . ' AS title', false)
            ->select('s.description_' . $this->col($locale) . ' AS description', false)
            ->select('j.title_' . $this->col($locale) . ' AS job_title, j.level AS job_level', false)
            ->from('ha_path_step s')
            ->join('ha_job_role j', 'j.id = s.job_role_id', 'left')
            ->where('s.path_id', $path['id'])
            ->order_by('s.sort_order', 'ASC')
            ->get()->result_array();
        $steps = $this->overlay('path_step', $steps, array('title', 'description'), $locale);

        foreach ($steps as $i => $step) {
            $steps[$i]['courses'] = $this->db
                ->select('c.id, c.duration_minutes, c.level, i.is_mandatory')
                ->select('c.slug_' . $this->col($locale) . ' AS slug', false)
                ->select('t.title', false)
                ->from('ha_path_step_item i')
                ->join('ha_course c', 'c.id = i.item_id')
                ->join($this->tr('ha_course_translation', 'course_id', $locale) . ' t', 't.course_id = c.id' . $this->tr_on('t', $locale), 'left', false)
                ->where('i.step_id', $step['id'])
                ->where('i.item_type', 'course')
                ->where('c.status', 'published')
                ->order_by('i.sort_order', 'ASC')
                ->get()->result_array();
        }
        $path['steps'] = $steps;
        return $path;
    }

    // ---------------------------------------------------------------- topics

    public function topics($locale = 'en', $type = null) {
        $locale = $this->locale($locale);
        $db = $this->db
            ->select('id, code, city, topic_type, sort_order, hero_image')
            ->select('slug_' . $this->col($locale) . ' AS slug', false)
            ->select('title_' . $this->col($locale) . ' AS title', false)
            ->select('intro_' . $this->col($locale) . ' AS intro', false)
            ->from('ha_topic')
            ->where('status', 'published')
            ->order_by('sort_order', 'ASC');
        if ($type !== null) {
            $db->where('topic_type', $type);
        }
        return $this->overlay('topic', $db->get()->result_array(), array('title', 'intro'), $locale);
    }

    public function topic($slug, $locale = 'en') {
        $locale = $this->locale($locale);
        $topic = $this->db
            ->select('*')
            ->select('slug_' . $this->col($locale) . ' AS slug', false)
            ->select('title_' . $this->col($locale) . ' AS title', false)
            ->select('intro_' . $this->col($locale) . ' AS intro', false)
            ->from('ha_topic')
            ->where('slug_' . $this->col($locale), $slug)
            ->where('status', 'published')
            ->get()->row_array();
        if (!$topic) {
            return null;
        }
        $topic = $this->overlay('topic', $topic, array('title', 'intro'), $locale, true);
        $topic['courses'] = $this->db
            ->select('c.id, c.level, c.duration_minutes')
            ->select('c.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.short_description', false)
            ->from('ha_topic_course tc')
            ->join('ha_course c', 'c.id = tc.course_id')
            ->join($this->tr('ha_course_translation', 'course_id', $locale) . ' t', 't.course_id = c.id' . $this->tr_on('t', $locale), 'left', false)
            ->where('tc.topic_id', $topic['id'])
            ->where('c.status', 'published')
            ->order_by('tc.sort_order', 'ASC')
            ->get()->result_array();

        $topic['faqs'] = $this->db
            ->select('id')
            ->select('question_' . $this->col($locale) . ' AS question', false)
            ->select('answer_' . $this->col($locale) . ' AS answer', false)
            ->from('ha_faq')
            ->where(array('scope_type' => 'topic', 'scope_id' => $topic['id'], 'status' => 'published'))
            ->order_by('sort_order', 'ASC')
            ->get()->result_array();
        $topic['faqs'] = $this->overlay('faq', $topic['faqs'], array('question', 'answer'), $locale);

        $topic['articles'] = $this->articles($locale, array('topic_id' => $topic['id'], 'limit' => 4));
        return $topic;
    }

    // -------------------------------------------------------------- articles

    public function articles($locale = 'en', array $params = array()) {
        $locale = $this->locale($locale);
        $db = $this->db
            ->select('a.id, a.cover_image, a.reading_minutes, a.published_at, a.view_count')
            // The card renders the cover image, so it needs the alt text with it.
            ->select('a.cover_image_alt_en, a.cover_image_alt_ar')
            ->select('a.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.excerpt', false)
            ->select('au.name_' . $this->col($locale) . ' AS author_name', false)
            ->select('ct.name AS category_name', false)
            ->select('cat.slug_' . $this->col($locale) . ' AS category_slug, cat.code AS category_code', false)
            ->from('ha_article a')
            ->join($this->tr('ha_article_translation', 'article_id', $locale) . ' t', 't.article_id = a.id' . $this->tr_on('t', $locale), 'left', false)
            ->join('ha_author au', 'au.id = a.author_id', 'left')
            ->join('ha_category cat', 'cat.id = a.category_id', 'left')
            ->join($this->tr('ha_category_translation', 'category_id', $locale) . ' ct', 'ct.category_id = cat.id' . $this->tr_on('ct', $locale), 'left', false)
            ->where('a.status', 'published')
            ->where('a.published_at <=', date('Y-m-d H:i:s'))
            ->order_by('a.published_at', 'DESC');

        if (!empty($params['topic_id'])) {
            $db->where('a.topic_id', (int) $params['topic_id']);
        }
        if (!empty($params['category'])) {
            $db->where('cat.code', $params['category']);
        }
        if (!empty($params['search'])) {
            $db->group_start()->like('t.title', $params['search'])
                ->or_like('t.excerpt', $params['search'])->group_end();
        }
        if (!empty($params['exclude_id'])) {
            $db->where('a.id !=', (int) $params['exclude_id']);
        }
        if (!empty($params['limit'])) {
            $db->limit((int) $params['limit'], isset($params['offset']) ? (int) $params['offset'] : 0);
        }
        return $db->get()->result_array();
    }

    public function count_articles($locale = 'en', array $params = array()) {
        $locale = $this->locale($locale);
        $db = $this->db->from('ha_article a')
            ->join($this->tr('ha_article_translation', 'article_id', $locale) . ' t', 't.article_id = a.id' . $this->tr_on('t', $locale), 'left', false)
            ->join('ha_category cat', 'cat.id = a.category_id', 'left')
            ->where('a.status', 'published')
            ->where('a.published_at <=', date('Y-m-d H:i:s'));
        if (!empty($params['category'])) {
            $db->where('cat.code', $params['category']);
        }
        if (!empty($params['search'])) {
            $db->group_start()->like('t.title', $params['search'])
                ->or_like('t.excerpt', $params['search'])->group_end();
        }
        return (int) $db->count_all_results();
    }

    public function article($slug, $locale = 'en') {
        $locale = $this->locale($locale);
        $article = $this->db
            ->select('a.*')
            ->select('a.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.excerpt, t.body', false)
            ->select('au.name_' . $this->col($locale) . ' AS author_name, au.title_' . $this->col($locale) . ' AS author_title', false)
            ->select('au.bio_' . $this->col($locale) . ' AS author_bio, au.avatar AS author_avatar', false)
            ->select('ct.name AS category_name, cat.code AS category_code', false)
            ->select('cat.slug_' . $this->col($locale) . ' AS category_slug', false)
            ->from('ha_article a')
            ->join($this->tr('ha_article_translation', 'article_id', $locale) . ' t', 't.article_id = a.id' . $this->tr_on('t', $locale), 'left', false)
            ->join('ha_author au', 'au.id = a.author_id', 'left')
            ->join('ha_category cat', 'cat.id = a.category_id', 'left')
            ->join($this->tr('ha_category_translation', 'category_id', $locale) . ' ct', 'ct.category_id = cat.id' . $this->tr_on('ct', $locale), 'left', false)
            ->where('a.slug_' . $this->col($locale), $slug)
            ->where('a.status', 'published')
            ->where('a.published_at <=', date('Y-m-d H:i:s'))
            ->get()->row_array();
        if (!$article) {
            return null;
        }
        $article['tags'] = $this->db
            ->select('tg.slug, tg.name_' . $this->col($locale) . ' AS name', false)
            ->from('ha_article_tag at')
            ->join('ha_tag tg', 'tg.id = at.tag_id')
            ->where('at.article_id', $article['id'])
            ->get()->result_array();

        $article['related'] = $this->articles($locale, array(
            'category'   => $article['category_code'],
            'exclude_id' => $article['id'],
            'limit'      => 3,
        ));

        if (!empty($article['related_course_id'])) {
            $article['related_course'] = $this->db
                ->select('c.slug_' . $this->col($locale) . ' AS slug, t.title', false)
                ->from('ha_course c')
                ->join($this->tr('ha_course_translation', 'course_id', $locale) . ' t', 't.course_id = c.id' . $this->tr_on('t', $locale), 'left', false)
                ->where('c.id', $article['related_course_id'])
                ->where('c.status', 'published')
                ->get()->row_array();
        }
        return $article;
    }

    public function increment_article_views($article_id) {
        $this->db->set('view_count', 'view_count + 1', false)
            ->where('id', (int) $article_id)->update('ha_article');
    }

    // ------------------------------------------------------------------ SOPs

    /**
     * Only SOPs explicitly marked public are exposed here. An organization
     * scoped procedure must never become readable by changing a URL, which is
     * the rule in plan section 39.
     */
    public function public_sops($locale = 'en', array $params = array()) {
        $locale = $this->locale($locale);
        $db = $this->db
            ->select('s.id, s.code, s.department_code')
            ->select('s.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('v.version_label, v.effective_date, v.review_date', false)
            ->select('vt.title, vt.purpose', false)
            ->select('cat.name_' . $this->col($locale) . ' AS category_name, cat.code AS category_code', false)
            ->from('ha_sop_document s')
            ->join('ha_sop_version v', 'v.id = s.current_version_id')
            ->join($this->tr('ha_sop_version_translation', 'version_id', $locale) . ' vt', 'vt.version_id = v.id' . $this->tr_on('vt', $locale), 'left', false)
            ->join('ha_sop_category cat', 'cat.id = s.category_id', 'left')
            ->where('s.status', 'published')
            ->where('s.visibility', 'public')
            ->where('v.status', 'published')
            ->order_by('cat.sort_order', 'ASC')
            ->order_by('vt.title', 'ASC');
        if (!empty($params['category'])) {
            $db->where('cat.code', $params['category']);
        }
        return $db->get()->result_array();
    }

    public function public_sop($slug, $locale = 'en') {
        $locale = $this->locale($locale);
        $sop = $this->db
            ->select('s.id, s.code, s.department_code, s.review_interval_months')
            ->select('s.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('v.id AS version_id, v.version_label, v.effective_date, v.review_date, v.change_summary', false)
            ->select('vt.*', false)
            ->select('cat.name_' . $this->col($locale) . ' AS category_name, cat.code AS category_code', false)
            ->from('ha_sop_document s')
            ->join('ha_sop_version v', 'v.id = s.current_version_id')
            ->join($this->tr('ha_sop_version_translation', 'version_id', $locale) . ' vt', 'vt.version_id = v.id' . $this->tr_on('vt', $locale), 'left', false)
            ->join('ha_sop_category cat', 'cat.id = s.category_id', 'left')
            ->where('s.slug_' . $this->col($locale), $slug)
            ->where('s.status', 'published')
            ->where('s.visibility', 'public')
            ->where('v.status', 'published')
            ->get()->row_array();
        if (!$sop) {
            return null;
        }
        $sop['procedure_steps'] = $sop['procedure'] ? json_decode($sop['procedure'], true) : array();
        $sop['checklist_items'] = $sop['checklist'] ? json_decode($sop['checklist'], true) : array();
        return $sop;
    }

    public function sop_categories($locale = 'en') {
        $locale = $this->locale($locale);
        return $this->db
            ->select('c.code, c.name_' . $this->col($locale) . ' AS name', false)
            ->select('COUNT(s.id) AS sop_count', false)
            ->from('ha_sop_category c')
            ->join('ha_sop_document s', "s.category_id = c.id AND s.status = 'published' AND s.visibility = 'public'", 'left')
            ->where('c.status', 'active')
            ->group_by('c.id')
            ->order_by('c.sort_order', 'ASC')
            ->get()->result_array();
    }

    // ------------------------------------------------------------ categories

    public function categories($locale = 'en') {
        $locale = $this->locale($locale);
        return $this->db
            ->select('c.id, c.code, c.icon')
            ->select('c.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.name, t.description', false)
            ->select("(SELECT COUNT(*) FROM ha_course co WHERE co.category_id = c.id AND co.status = 'published') AS course_count", false)
            ->from('ha_category c')
            ->join($this->tr('ha_category_translation', 'category_id', $locale) . ' t', 't.category_id = c.id' . $this->tr_on('t', $locale), 'left', false)
            ->where('c.status', 'active')
            ->order_by('c.sort_order', 'ASC')
            ->get()->result_array();
    }

    /** Global FAQs, used on the home page and in FAQ schema. */
    public function faqs($locale = 'en', $scope_type = 'global', $scope_id = null) {
        $locale = $this->locale($locale);
        $db = $this->db
            ->select('id')
            ->select('question_' . $this->col($locale) . ' AS question', false)
            ->select('answer_' . $this->col($locale) . ' AS answer', false)
            ->from('ha_faq')
            ->where('scope_type', $scope_type)
            ->where('status', 'published')
            ->order_by('sort_order', 'ASC');
        if ($scope_id === null) {
            $db->where('scope_id IS NULL', null, false);
        } else {
            $db->where('scope_id', (int) $scope_id);
        }
        return $this->overlay('faq', $db->get()->result_array(), array('question', 'answer'), $locale);
    }

    /**
     * Countable facts about the platform, read live. These are the only
     * numbers the home page is allowed to show: each one is a COUNT over
     * published rows, so it cannot become a claim the catalogue does not
     * support.
     */
    public function platform_facts() {
        $minutes = (int) $this->db->select_sum('duration_minutes', 'm')
            ->where('status', 'published')->get('ha_course')->row('m');

        return array(
            'courses'    => $this->db->where('status', 'published')->count_all_results('ha_course'),
            'lessons'    => $this->db->where('status', 'published')->count_all_results('ha_lesson'),
            'procedures' => $this->db->where('status', 'published')->count_all_results('ha_sop_document'),
            'checks'     => $this->db->count_all_results('ha_checklist_item'),
            'skills'     => $this->db->where('status', 'active')->count_all_results('ha_skill'),
            'roles'      => $this->db->where('status', 'active')->count_all_results('ha_job_role'),
            'cities'     => (int) $this->db->select('COUNT(DISTINCT city) AS c', false)
                                ->where('topic_type', 'city')->where('status', 'published')
                                ->get('ha_topic')->row('c'),
            'hours'      => (int) round($minutes / 60),
        );
    }

    /**
     * One lesson resolved in both languages at once, for the side by side
     * comparison on the home page. It proves the bilingual claim instead of
     * asserting it.
     */
    public function bilingual_sample($course_code = 'fo-check-in') {
        $course = $this->db->select('id, slug_en, slug_ar')
            ->get_where('ha_course', array('code' => $course_code, 'status' => 'published'))->row_array();
        if (!$course) {
            return null;
        }

        $lesson = $this->db->select('id')
            ->where('course_id', $course['id'])
            ->where('status', 'published')
            ->where('lesson_type', 'text')
            ->order_by('sort_order', 'ASC')
            ->get('ha_lesson')->row_array();
        if (!$lesson) {
            return null;
        }

        $out = array('course' => $course, 'en' => null, 'ar' => null);
        foreach (array('en', 'ar') as $locale) {
            $out[$locale] = $this->db
                ->select('title, objective, body')
                ->get_where('ha_lesson_translation',
                    array('lesson_id' => $lesson['id'], 'locale' => $locale))->row_array();
            $out[$locale . '_course_title'] = $this->db->select('title')
                ->get_where('ha_course_translation',
                    array('course_id' => $course['id'], 'locale' => $locale))->row('title');
        }
        return ($out['en'] && $out['ar']) ? $out : null;
    }

    /**
     * A published procedure with its first few steps, to show what the SOP
     * hub actually contains rather than describing it.
     */
    public function procedure_sample($locale = 'en', $code = 'sop-fo-checkin') {
        $locale = $this->locale($locale);
        $row = $this->db
            ->select('s.code, s.department_code')
            ->select('s.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('v.version_label, v.effective_date, v.review_date', false)
            ->select('vt.title, vt.purpose, vt.procedure AS steps, vt.checklist', false)
            ->from('ha_sop_document s')
            ->join('ha_sop_version v', 'v.id = s.current_version_id')
            ->join($this->tr('ha_sop_version_translation', 'version_id', $locale) . ' vt', 'vt.version_id = v.id' . $this->tr_on('vt', $locale), 'left', false)
            ->where('s.code', $code)
            ->where('s.status', 'published')
            ->get()->row_array();

        if (!$row) {
            return null;
        }
        $row['steps'] = $row['steps'] ? json_decode($row['steps'], true) : array();
        $row['checklist'] = $row['checklist'] ? json_decode($row['checklist'], true) : array();
        return $row;
    }

    /** Departments that actually have published courses, for the role picker. */
    public function departments_with_courses($locale = 'en') {
        $locale = $this->locale($locale);
        return $this->db
            ->select('d.code')
            ->select('d.name_' . $this->col($locale) . ' AS name', false)
            ->select('COUNT(c.id) AS course_count', false)
            ->from('ha_department d')
            ->join('ha_course c', "c.department_code = d.code AND c.status = 'published'", 'inner')
            // MySQL 8 runs with only_full_group_by, so every selected column
            // that is not aggregated has to be grouped as well.
            ->group_by(array('d.code', 'd.name_' . $this->col($locale)))
            ->having('course_count >', 0)
            ->order_by('course_count', 'DESC')
            ->get()->result_array();
    }

    public function page($code, $locale = 'en') {
        $locale = $this->locale($locale);
        return $this->db
            ->select('p.id, p.code, p.template, p.slug_en, p.slug_ar')
            ->select('p.slug_' . $this->col($locale) . ' AS slug', false)
            ->select('t.title, t.subtitle, t.body, t.hero_image, t.cta_label, t.cta_url', false)
            ->from('ha_page p')
            ->join($this->tr('ha_page_translation', 'page_id', $locale) . ' t', 't.page_id = p.id' . $this->tr_on('t', $locale), 'left', false)
            ->where('p.code', $code)
            ->where('p.status', 'published')
            ->get()->row_array();
    }

    /**
     * Every photograph whose licence requires the author to be credited.
     * This is what the public credits page is built from; a CC BY or CC BY-SA
     * image used without that credit is a licence breach, not a detail.
     */
    public function image_credits() {
        return $this->db
            ->select('subject, file_path, original_name, author, license, license_url, source_page')
            ->from('ha_media')
            ->where('credit_required', 1)
            ->order_by('subject', 'ASC')
            ->get()->result_array();
    }

    /** Photographs that need no credit, counted for the credits page note. */
    public function uncredited_image_count() {
        return (int) $this->db->where('credit_required', 0)->count_all_results('ha_media');
    }

    public function menu($code, $locale = 'en') {
        $locale = $this->locale($locale);
        $items = $this->db
            ->select('i.id')
            ->select('i.label_' . $this->col($locale) . ' AS label', false)
            ->select('i.url_' . $this->col($locale) . ' AS url', false)
            ->select('i.open_in_new_tab')
            ->from('ha_menu_item i')
            ->join('ha_menu m', 'm.id = i.menu_id')
            ->where('m.code', $code)
            ->where('i.status', 'active')
            ->order_by('i.sort_order', 'ASC')
            ->get()->result_array();
        return $this->overlay('menu_item', $items, array('label'), $locale);
    }

    // ----------------------------------------------------- certificate lookup

    /**
     * Public certificate verification. Returns a status and only the fields a
     * third party is entitled to see, and records the attempt either way.
     */
    public function verify_certificate($code) {
        $code = trim((string) $code);
        $result = array('status' => 'not_found', 'certificate' => null);

        if ($code === '') {
            return $result;
        }

        $row = $this->db
            ->select('c.id, c.certificate_no, c.verification_code, c.status, c.issued_at, c.expires_at,
                      c.final_score, c.recipient_name_en, c.recipient_name_ar,
                      c.subject_title_en, c.subject_title_ar, c.instructor_name')
            ->from('ha_certificate c')
            ->where('c.verification_code', $code)
            ->get()->row_array();

        if ($row) {
            if ($row['status'] === 'revoked') {
                $result['status'] = 'revoked';
            } elseif ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
                $result['status'] = 'expired';
            } else {
                $result['status'] = 'valid';
            }
            $result['certificate'] = $row;
        }

        $this->db->insert('ha_certificate_verification', array(
            'certificate_id' => $row ? $row['id'] : null,
            'submitted_code' => mb_substr($code, 0, 64),
            'result'         => $result['status'],
            'ip_address'     => is_cli() ? 'cli' : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null),
            'user_agent'     => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'verified_at'    => date('Y-m-d H:i:s'),
        ));

        return $result;
    }

    // ------------------------------------------------------------ site search

    /** Global search across the public content only (plan section 35). */
    public function search($term, $locale = 'en', $limit = 8) {
        $term = trim((string) $term);
        if ($term === '') {
            return array();
        }
        $results = array();

        foreach ($this->courses($locale, array('search' => $term, 'limit' => $limit)) as $r) {
            $results[] = array('type' => 'course', 'title' => $r['title'],
                'excerpt' => $r['short_description'], 'slug' => $r['slug']);
        }
        foreach ($this->articles($locale, array('search' => $term, 'limit' => $limit)) as $r) {
            $results[] = array('type' => 'article', 'title' => $r['title'],
                'excerpt' => $r['excerpt'], 'slug' => $r['slug']);
        }
        $locale_col = $this->col($locale);
        $topics = $this->db
            ->select('slug_' . $locale_col . ' AS slug, title_' . $locale_col . ' AS title', false)
            ->from('ha_topic')->where('status', 'published')
            ->like('title_' . $locale_col, $term)
            ->limit($limit)->get()->result_array();
        foreach ($topics as $r) {
            $results[] = array('type' => 'topic', 'title' => $r['title'], 'excerpt' => '', 'slug' => $r['slug']);
        }
        return $results;
    }
}
