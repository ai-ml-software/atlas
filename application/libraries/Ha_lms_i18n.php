<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'helpers/ha_locale_helper.php';

/**
 * Shows mirrored Academy LMS rows in the learner's language.
 *
 * Ha_bridge writes the legacy course / section / lesson / question tables in
 * English and records in ha_lms_link which academy row each one came from.
 * This reads the academy translations for the learner's language and swaps
 * the text in, row by row, falling back to the English already in the row.
 * Grading is untouched: options keep their positions, so correct answers
 * (stored by position) are the same in every language.
 *
 *   $this->load->library('ha_lms_i18n');
 *   $course   = $this->ha_lms_i18n->course($course);
 *   $sections = $this->ha_lms_i18n->sections($sections);
 *   $lessons  = $this->ha_lms_i18n->lessons($lessons);
 *   $rows     = $this->ha_lms_i18n->questions($rows);
 *
 * The language comes from the legacy session column (english, arabic, hindi…)
 * mapped through config/ha_locales.php.
 */
class Ha_lms_i18n {

    private $CI;
    private $locale;
    private $ready;
    /** "legacy_table:legacy_id" => array(ha_table, ha_id) */
    private $links = array();
    private $loaded_courses = array();
    private $cache = array();

    public function __construct() {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $this->ready = $this->CI->db->table_exists('ha_lms_link');
    }

    /** The learner's language code (en, ar, hi, …). */
    public function locale() {
        if ($this->locale === null) {
            $this->locale = 'en';
            $column = (string) $this->CI->session->userdata('language');
            foreach ((array) ha_locale_config()['legacy'] as $code => $col) {
                if ($col === $column && ha_locale_enabled($code)) {
                    $this->locale = $code;
                }
            }
        }
        return $this->locale;
    }

    /** Force a language (tests, previews). */
    public function set_locale($code) {
        $this->locale = ha_locale_enabled($code) ? $code : 'en';
        $this->cache = array();
    }

    private function active() {
        return $this->ready && $this->locale() !== 'en';
    }

    /** Loads every link of one legacy course in a single query. */
    private function load($legacy_course_id) {
        $legacy_course_id = (int) $legacy_course_id;
        if (isset($this->loaded_courses[$legacy_course_id])) {
            return;
        }
        $this->loaded_courses[$legacy_course_id] = true;
        foreach ($this->CI->db->get_where('ha_lms_link', array('legacy_course_id' => $legacy_course_id))->result_array() as $l) {
            $this->links[$l['legacy_table'] . ':' . $l['legacy_id']] = array($l['ha_table'], (int) $l['ha_id']);
        }
    }

    private function find($table, $id, $course_id) {
        $this->load($course_id);
        $key = $table . ':' . (int) $id;
        return isset($this->links[$key]) ? $this->links[$key] : null;
    }

    /** ha_i18n_text value, or null. */
    private function overlay($entity, $id, $field) {
        $key = "o:$entity:$id:$field";
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->CI->db->select('value')->get_where('ha_i18n_text',
                array('entity' => $entity, 'entity_id' => (int) $id, 'field' => $field, 'locale' => $this->locale()))->row('value');
        }
        return $this->cache[$key];
    }

    /** Text from an en/ar column pair, with ha_i18n_text for every other language. */
    private function pair(array $row, $prefix, $entity, $id, $field) {
        $loc = $this->locale();
        if ($loc === 'ar') {
            return isset($row[$prefix . '_ar']) && trim((string) $row[$prefix . '_ar']) !== '' ? $row[$prefix . '_ar'] : null;
        }
        $v = $this->overlay($entity, $id, $field);
        return ($v !== null && trim((string) $v) !== '') ? $v : null;
    }

    // ------------------------------------------------------------------ rows

    public function course($course) {
        if (!$this->active() || !is_array($course) || empty($course['id'])) {
            return $course;
        }
        $link = $this->find('course', $course['id'], $course['id']);
        if (!$link) {
            return $course;
        }
        $t = $this->CI->db->get_where('ha_course_translation', array('course_id' => $link[1], 'locale' => $this->locale()))->row_array();
        if ($t) {
            foreach (array('title', 'short_description', 'description') as $f) {
                if (trim((string) $t[$f]) !== '') {
                    $course[$f] = $t[$f];
                }
            }
            $outcomes = array_column($this->CI->db->select('body')->order_by('sort_order', 'ASC')
                ->get_where('ha_course_outcome', array('course_id' => $link[1], 'locale' => $this->locale()))->result_array(), 'body');
            if ($outcomes) {
                $course['outcomes'] = json_encode($outcomes, JSON_UNESCAPED_UNICODE);
            }
            if (trim((string) $t['requirements']) !== '') {
                $course['requirements'] = json_encode(array($t['requirements']), JSON_UNESCAPED_UNICODE);
            }
            $faqs = array();
            foreach ($this->CI->db->select('question, answer')->order_by('sort_order', 'ASC')
                         ->get_where('ha_course_faq', array('course_id' => $link[1], 'locale' => $this->locale()))->result_array() as $f) {
                $faqs[$f['question']] = $f['answer'];
            }
            if ($faqs) {
                $course['faqs'] = json_encode($faqs, JSON_UNESCAPED_UNICODE);
            }
            $course['ha_translated'] = true;
        }
        return $course;
    }

    public function sections($rows) {
        if (!$this->active() || !is_array($rows)) {
            return $rows;
        }
        foreach ($rows as $i => $s) {
            $rows[$i] = $this->section($s);
        }
        return $rows;
    }

    public function section($s) {
        if (!$this->active() || !is_array($s) || empty($s['id'])) {
            return $s;
        }
        $link = $this->find('section', $s['id'], $s['course_id']);
        if (!$link) {
            return $s;
        }
        $row = $this->CI->db->get_where('ha_course_section', array('id' => $link[1]))->row_array();
        $v = $row ? $this->pair($row, 'title', 'course_section', $link[1], 'title') : null;
        if ($v !== null) {
            $s['title'] = $v;
        }
        return $s;
    }

    public function lessons($rows) {
        if (!$this->active() || !is_array($rows)) {
            return $rows;
        }
        foreach ($rows as $i => $l) {
            $rows[$i] = $this->lesson($l);
        }
        return $rows;
    }

    public function lesson($l) {
        if (!$this->active() || !is_array($l) || empty($l['id'])) {
            return $l;
        }
        $link = $this->find('lesson', $l['id'], $l['course_id']);
        if (!$link) {
            return $l;
        }
        list($table, $id) = $link;
        if ($table === 'ha_lesson') {
            $t = $this->CI->db->get_where('ha_lesson_translation', array('lesson_id' => $id, 'locale' => $this->locale()))->row_array();
            if ($t && trim((string) $t['title']) !== '') {
                $l['title'] = $t['title'];
                if (trim(strip_tags((string) $t['body'])) !== '') {
                    // The bridge appends a credit line to a borrowed video; keep it.
                    $credit = '';
                    if (preg_match('~<p class="lesson-video-credit">.*$~s', (string) $l['summary'], $m)) {
                        $credit = $m[0];
                    }
                    $l['summary'] = $t['body'] . $credit;
                }
                $l['ha_translated'] = true;
            }
        } elseif ($table === 'ha_assessment') {
            $a = $this->CI->db->get_where('ha_assessment', array('id' => $id))->row_array();
            if ($a) {
                $title = $this->pair($a, 'title', 'assessment', $id, 'title');
                if ($title !== null) {
                    $l['title'] = $title;
                    $l['ha_translated'] = true;
                }
                $instructions = $this->pair($a, 'instructions', 'assessment', $id, 'instructions');
                if ($instructions !== null) {
                    $l['summary'] = $instructions;
                }
            }
        }
        return $l;
    }

    /** Legacy question rows: title and options (JSON, by position) in the learner's language. */
    public function questions($rows) {
        if (!$this->active() || !is_array($rows) || !$rows) {
            return $rows;
        }
        $course_id = null;
        foreach ($rows as $i => $q) {
            if ($course_id === null) {
                $course_id = (int) $this->CI->db->select('course_id')->get_where('lesson', array('id' => $q['quiz_id']))->row('course_id');
            }
            $link = $this->find('question', $q['id'], $course_id);
            if (!$link) {
                continue;
            }
            $hq = $this->CI->db->get_where('ha_question', array('id' => $link[1]))->row_array();
            if (!$hq) {
                continue;
            }
            $body = $this->pair($hq, 'body', 'question', $link[1], 'body');
            if ($body !== null) {
                $rows[$i]['title'] = $body;
            }
            $explanation = $this->pair($hq, 'explanation', 'question', $link[1], 'explanation');
            $rows[$i]['explanation'] = $explanation !== null ? $explanation : $hq['explanation_en'];
            $opts = $this->CI->db->order_by('sort_order', 'ASC')->get_where('ha_question_option', array('question_id' => $link[1]))->result_array();
            $current = json_decode((string) $q['options'], true);
            if ($opts && is_array($current) && count($opts) === count($current)) {
                $out = array();
                foreach ($opts as $oi => $o) {
                    $v = $this->pair($o, 'body', 'question_option', $o['id'], 'body');
                    $out[] = $v !== null ? $v : $current[$oi];
                }
                $rows[$i]['options'] = json_encode($out, JSON_UNESCAPED_UNICODE);
            }
        }
        return $rows;
    }

    /** The explanation for a legacy question (English when the learner reads English). */
    public function explanation(array $q) {
        if (isset($q['explanation'])) {
            return $q['explanation'];
        }
        if (!$this->ready) {
            return '';
        }
        $link = $this->CI->db->get_where('ha_lms_link', array('legacy_table' => 'question', 'legacy_id' => (int) $q['id']))->row_array();
        if (!$link) {
            return '';
        }
        $hq = $this->CI->db->get_where('ha_question', array('id' => $link['ha_id']))->row_array();
        if (!$hq) {
            return '';
        }
        if ($this->locale() === 'en') {
            return (string) $hq['explanation_en'];
        }
        $v = $this->pair($hq, 'explanation', 'question', $link['ha_id'], 'explanation');
        return $v !== null ? $v : (string) $hq['explanation_en'];
    }
}
