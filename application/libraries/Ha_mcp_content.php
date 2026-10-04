<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/Ha_gateway.php';   // Ha_api_error

/**
 * Native content services used by the PHP MCP server for objects that have no
 * draft layer of their own: course sections, lessons, quizzes and questions,
 * plus SOPs (through Ha_knowledge governance), page creation, page-section
 * operations on the website draft, revisions, previews and audit reads.
 *
 * Rules (enforced here, not in the MCP layer):
 *  - Identity, permissions and tenant scope come from Ha_auth (the OAuth user).
 *  - New objects are always drafts. Nothing here publishes; publication goes
 *    through Ha_publishing_service approvals (or SOP governance).
 *  - Objects without a draft layer can be edited only while unpublished, so an
 *    MCP edit never changes what learners see without a human approval.
 *  - Optimistic concurrency: every object reports a version; writes require
 *    the expected version and fail with 409 + current version on mismatch.
 *  - No permanent deletes.
 */
class Ha_mcp_content {
    const STRUCTURE = array('course_sections', 'lessons', 'quizzes', 'questions');
    const LESSON_TYPES = array('text', 'video', 'audio', 'pdf', 'presentation', 'external', 'checklist', 'sop', 'interactive');
    const QUESTION_TYPES = array('multiple_choice', 'true_false', 'multiple_response', 'matching', 'ordering', 'scenario', 'short_answer', 'essay');
    private $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_audit'));
    }
    private function A() { return $this->CI->ha_auth; }
    private function db() { return $this->CI->db; }
    private function need($perms, $message) { if (!$this->A()->has_any((array) $perms)) throw new Ha_api_error(403, 'forbidden', $message, array('required_permission' => implode(' | ', (array) $perms))); }
    private static function now() { return date('Y-m-d H:i:s'); }
    private static function text($v, $max) { return mb_substr(trim(str_replace("\0", '', (string) $v)), 0, $max); }
    public static function version_of($data) { return (int) sprintf('%u', crc32(json_encode($data, JSON_UNESCAPED_UNICODE))); }
    private function conflict($type, $id, $current, $expected) {
        return new Ha_api_error(409, 'conflict', 'The object changed since you read it. Re-read it and apply your change to the current version.', array('object_type' => $type, 'object_id' => (int) $id, 'current_version' => $current, 'expected_version' => $expected));
    }
    private function locked($type, $id, $why) {
        return new Ha_api_error(409, 'published_locked', $why . ' Edits through MCP never change live learning content; edit it in the ALTUS module editor, or archive it through an approval first.', array('object_type' => $type, 'object_id' => (int) $id));
    }

    // ================================================================= courses (scope)
    /** Course row the current user may read ($write=false) or change ($write=true). */
    public function course($id, $write = false) {
        $this->need($write ? array('courses.update', 'lessons.create', 'lessons.update', 'assessments.create', 'assessments.update', 'question_banks.create', 'question_banks.update') : array('courses.view', 'courses.update', 'lessons.view', 'lessons.update', 'assessments.view', 'question_banks.view'), 'Course permission required.');
        $c = $this->db()->get_where('ha_course', array('id' => (int) $id))->row_array();
        if (!$c) throw new Ha_api_error(404, 'not_found', 'Course not found.', array('object_type' => 'courses', 'object_id' => (int) $id));
        $A = $this->A();
        if ($c['organization_id']) {
            if (!$A->is_system_scoped() && !$A->can_organization($c['organization_id']) && !($c['property_id'] && $A->can_property($c['property_id']))) throw new Ha_api_error(404, 'not_found', 'Course not found.', array('object_type' => 'courses', 'object_id' => (int) $id));
        } elseif ($write && !$A->is_system_scoped()) {
            throw new Ha_api_error(403, 'forbidden', 'Global ALTUS courses can be changed only by platform staff.');
        }
        return $c;
    }

    // ================================================================= structure reads
    private function row($type, $id) {
        $table = array('course_sections' => 'ha_course_section', 'lessons' => 'ha_lesson', 'quizzes' => 'ha_assessment', 'questions' => 'ha_question')[$type];
        $r = $this->db()->get_where($table, array('id' => (int) $id))->row_array();
        if (!$r) throw new Ha_api_error(404, 'not_found', 'Object not found.', array('object_type' => $type, 'object_id' => (int) $id));
        return $r;
    }
    /** Quizzes a question belongs to (questions live in banks and are linked to quizzes). */
    private function question_quizzes($qid) { return $this->db()->select('a.*')->from('ha_assessment_question aq')->join('ha_assessment a', 'a.id = aq.assessment_id')->where('aq.question_id', (int) $qid)->get()->result_array(); }
    private function question_course($q, $write) {
        $quizzes = $this->question_quizzes($q['id']);
        $bank = $this->db()->get_where('ha_question_bank', array('id' => (int) $q['bank_id']))->row_array();
        $course = $quizzes && $quizzes[0]['course_id'] ? $quizzes[0]['course_id'] : ($bank['course_id'] ?? null);
        if (!$course) throw new Ha_api_error(404, 'not_found', 'Question is not attached to a course quiz.', array('object_type' => 'questions', 'object_id' => (int) $q['id']));
        $this->course($course, $write);
        return array($quizzes, (int) $course);
    }
    /** Full current data of a structure object, with its course scope enforced. */
    public function data($type, $id, $write = false) {
        $db = $this->db(); $r = $this->row($type, $id);
        if ($type === 'course_sections') { $this->course($r['course_id'], $write); return array('row' => $r, 'course_id' => (int) $r['course_id'], 'data' => array('title_en' => $r['title_en'], 'title_ar' => $r['title_ar'], 'sort_order' => (int) $r['sort_order'])); }
        if ($type === 'lessons') {
            $this->need($write ? array('lessons.create', 'lessons.update') : array('lessons.view', 'lessons.update', 'courses.view', 'courses.update'), 'Lesson permission required.');
            $this->course($r['course_id'], $write);
            $d = array_intersect_key($r, array_flip(array('lesson_type', 'section_id', 'video_url', 'external_url', 'duration_seconds', 'is_mandatory', 'is_preview', 'completion_rule', 'required_watch_percentage', 'assessment_id', 'sort_order', 'status', 'media_path', 'drip_days', 'available_from')));
            foreach ($db->get_where('ha_lesson_translation', array('lesson_id' => (int) $id))->result_array() as $t) foreach (array('title', 'objective', 'body', 'transcript') as $f) $d[$f . '_' . $t['locale']] = $t[$f];
            ksort($d);
            return array('row' => $r, 'course_id' => (int) $r['course_id'], 'data' => $d);
        }
        if ($type === 'quizzes') {
            $this->need($write ? array('assessments.create', 'assessments.update') : array('assessments.view', 'assessments.update', 'courses.update'), 'Assessment permission required.');
            if (!$r['course_id']) throw new Ha_api_error(404, 'not_found', 'Only course quizzes are available through MCP.', array('object_type' => 'quizzes', 'object_id' => (int) $id));
            $this->course($r['course_id'], $write);
            $d = array_intersect_key($r, array_flip(array('code', 'title_en', 'title_ar', 'instructions_en', 'instructions_ar', 'pass_percentage', 'max_attempts', 'time_limit_minutes', 'shuffle_questions', 'shuffle_options', 'show_correct_answers', 'status')));
            $d['question_ids'] = array_map('intval', array_column($db->order_by('sort_order')->order_by('id')->get_where('ha_assessment_question', array('assessment_id' => (int) $id))->result_array(), 'question_id'));
            return array('row' => $r, 'course_id' => (int) $r['course_id'], 'data' => $d);
        }
        $this->need($write ? array('question_banks.create', 'question_banks.update') : array('question_banks.view', 'assessments.view', 'question_banks.update'), 'Question bank permission required.');
        list($quizzes, $course) = $this->question_course($r, $write);
        $d = array_intersect_key($r, array_flip(array('question_type', 'body_en', 'body_ar', 'explanation_en', 'explanation_ar', 'marks', 'difficulty', 'status')));
        $d['options'] = array();
        foreach ($db->order_by('sort_order')->where('ha_retired_at IS NULL', null, false)->get_where('ha_question_option', array('question_id' => (int) $id))->result_array() as $o) $d['options'][] = array('id' => (int) $o['id'], 'body_en' => $o['body_en'], 'body_ar' => $o['body_ar'], 'is_correct' => (bool) $o['is_correct'], 'match_key_en' => $o['match_key_en']);
        $d['quiz_ids'] = array_map('intval', array_column($quizzes, 'id'));
        return array('row' => $r, 'course_id' => $course, 'data' => $d, 'quizzes' => $quizzes);
    }
    public function version($type, $id) { return self::version_of($this->data($type, $id)['data']); }
    /** Ha_publishing_service-compatible state (approval digest). */
    public function state($type, $id) { $d = $this->data($type, $id); return array('payload' => $d['data'], 'version' => self::version_of($d['data']), 'base_hash' => null, 'published' => array('status' => $d['data']['status'] ?? null)); }
    public function describe($type, $id) {
        $d = $this->data($type, $id); $status = $d['data']['status'] ?? ($this->db()->get_where('ha_course', array('id' => $d['course_id']))->row('status'));
        $urls = array('course_sections' => hkp_url('cms/module/' . $d['course_id']), 'lessons' => hkp_url('cms/lesson/' . (int) $id), 'quizzes' => hkp_url('admin/assessments/view/' . (int) $id), 'questions' => hkp_url('admin/assessments/view/' . (int) ($d['data']['quiz_ids'][0] ?? 0)));
        $preview = $type === 'lessons' ? hkp_url('cms/view/lessons/' . (int) $id . '?draft=1') : hkp_url('cms/view/modules/' . $d['course_id'] . '?draft=1');
        return array('object_id' => (int) $id, 'object_type' => $type, 'course_id' => $d['course_id'], 'status' => $status, 'version' => self::version_of($d['data']), 'data' => $d['data'], 'edit_url' => $urls[$type], 'preview_url' => $preview, 'warnings' => array());
    }
    public function listing($type, $parent, $query = '') {
        $db = $this->db(); $q = self::text($query, 100);
        if ($type === 'questions') {
            $quiz = $this->data('quizzes', $parent); $this->need(array('question_banks.view', 'assessments.view', 'question_banks.update'), 'Question bank permission required.');
            $db->select('q.id, q.question_type, q.body_en, q.body_ar, q.status, aq.sort_order')->from('ha_assessment_question aq')->join('ha_question q', 'q.id = aq.question_id')->where('aq.assessment_id', (int) $parent);
            if ($q !== '') $db->like('q.body_en', $q);
            return array('quiz_id' => (int) $parent, 'course_id' => $quiz['course_id'], 'rows' => $db->order_by('aq.sort_order')->order_by('aq.id')->get()->result_array());
        }
        $c = $this->course($parent);
        if ($type === 'course_sections') return array('course_id' => (int) $c['id'], 'rows' => $db->order_by('sort_order')->get_where('ha_course_section', array('course_id' => (int) $c['id']))->result_array());
        if ($type === 'lessons') {
            $this->need(array('lessons.view', 'lessons.update', 'courses.view', 'courses.update'), 'Lesson permission required.');
            $db->select('l.id, l.section_id, l.lesson_type, l.status, l.sort_order, te.title AS title_en, ta.title AS title_ar')->from('ha_lesson l')
                ->join('ha_lesson_translation te', "te.lesson_id = l.id AND te.locale = 'en'", 'left')->join('ha_lesson_translation ta', "ta.lesson_id = l.id AND ta.locale = 'ar'", 'left')->where('l.course_id', (int) $c['id']);
            if ($q !== '') $db->like('te.title', $q);
            return array('course_id' => (int) $c['id'], 'rows' => $db->order_by('l.sort_order')->get()->result_array());
        }
        $this->need(array('assessments.view', 'assessments.update', 'courses.update'), 'Assessment permission required.');
        return array('course_id' => (int) $c['id'], 'rows' => $db->select('a.id, a.code, a.title_en, a.title_ar, a.status, a.pass_percentage, (SELECT COUNT(*) FROM ha_assessment_question x WHERE x.assessment_id = a.id) questions', false)->from('ha_assessment a')->where('a.course_id', (int) $c['id'])->order_by('a.id')->get()->result_array());
    }

    // ================================================================= structure writes
    private function lesson_row(array $in, $course_id, array $current = array()) {
        $row = array();
        if (array_key_exists('lesson_type', $in)) { if (!in_array($in['lesson_type'], self::LESSON_TYPES, true)) throw new InvalidArgumentException('lesson_type must be one of: ' . implode(', ', self::LESSON_TYPES)); $row['lesson_type'] = $in['lesson_type']; }
        if (array_key_exists('section_id', $in)) { $s = $in['section_id'] ? (int) $in['section_id'] : null; if ($s && !$this->db()->where(array('id' => $s, 'course_id' => (int) $course_id))->count_all_results('ha_course_section')) throw new InvalidArgumentException('section_id must be a section of the same course.'); $row['section_id'] = $s; }
        if (array_key_exists('assessment_id', $in)) { $a = $in['assessment_id'] ? (int) $in['assessment_id'] : null; if ($a && !$this->db()->where(array('id' => $a, 'course_id' => (int) $course_id))->count_all_results('ha_assessment')) throw new InvalidArgumentException('assessment_id must be a quiz of the same course.'); $row['assessment_id'] = $a; }
        if (array_key_exists('video_url', $in)) { $v = self::text($in['video_url'], 500); if ($v !== '' && !preg_match('~^(https://|/)~i', $v)) throw new InvalidArgumentException('video_url must start with https://'); $row['video_url'] = $v ?: null; $row['video_source'] = $v === '' ? null : (preg_match('~youtu~i', $v) ? 'youtube' : (preg_match('~vimeo~i', $v) ? 'vimeo' : 'url')); }
        if (array_key_exists('external_url', $in)) { $v = self::text($in['external_url'], 500); if ($v !== '' && !preg_match('~^https://~i', $v)) throw new InvalidArgumentException('external_url must start with https://'); $row['external_url'] = $v ?: null; }
        if (array_key_exists('duration_minutes', $in)) $row['duration_seconds'] = max(0, min(100000, (int) $in['duration_minutes'])) * 60;
        foreach (array('is_mandatory', 'is_preview') as $k) if (array_key_exists($k, $in)) $row[$k] = !empty($in[$k]) ? 1 : 0;
        if (array_key_exists('completion_rule', $in)) { if (!in_array($in['completion_rule'], array('open', 'watch_percentage', 'quiz', 'acknowledge', 'assignment'), true)) throw new InvalidArgumentException('Unsupported completion_rule.'); $row['completion_rule'] = $in['completion_rule']; }
        if (array_key_exists('required_watch_percentage', $in)) $row['required_watch_percentage'] = max(10, min(100, (int) $in['required_watch_percentage']));
        return $row;
    }
    private function lesson_translations($id, array $in, $title_fallback) {
        $db = $this->db();
        foreach (array('en', 'ar') as $loc) {
            $t = array();
            if (array_key_exists('title_' . $loc, $in)) $t['title'] = self::text($in['title_' . $loc], 190) ?: $title_fallback;
            if (array_key_exists('objective_' . $loc, $in)) $t['objective'] = self::text($in['objective_' . $loc], 500);
            if (array_key_exists('body_' . $loc, $in)) { if (mb_strlen((string) $in['body_' . $loc]) > 200000) throw new InvalidArgumentException('Lesson body is too long.'); $t['body'] = hkp_safe_html((string) $in['body_' . $loc]); }
            if (array_key_exists('transcript_' . $loc, $in)) $t['transcript'] = mb_substr((string) $in['transcript_' . $loc], 0, 200000);
            $ex = $db->get_where('ha_lesson_translation', array('lesson_id' => (int) $id, 'locale' => $loc))->row_array();
            if ($ex) { if ($t) $db->where('id', $ex['id'])->update('ha_lesson_translation', $t); }
            else $db->insert('ha_lesson_translation', $t + array('lesson_id' => (int) $id, 'locale' => $loc, 'title' => $title_fallback, 'objective' => '', 'body' => '', 'transcript' => ''));
        }
    }
    public function snapshot_lesson($id, $note) {
        $db = $this->db(); $l = $db->get_where('ha_lesson', array('id' => (int) $id))->row_array(); $tr = $db->get_where('ha_lesson_translation', array('lesson_id' => (int) $id))->result_array();
        $v = 1 + (int) $db->select_max('version_no')->get_where('ha_lesson_version', array('lesson_id' => (int) $id))->row()->version_no;
        $db->insert('ha_lesson_version', array('lesson_id' => (int) $id, 'version_no' => $v, 'snapshot_json' => json_encode(array('lesson' => $l, 'translations' => $tr), JSON_UNESCAPED_UNICODE), 'change_summary' => mb_substr($note, 0, 500), 'changed_by' => $this->A()->id(), 'created_at' => self::now()));
        return (int) $db->insert_id();
    }
    private function question_options($qid, array $opts, $type) {
        if (count($opts) > 20) throw new InvalidArgumentException('At most 20 options.');
        $db = $this->db(); $n = 0;
        $db->where(array('question_id' => (int) $qid))->where('ha_retired_at IS NULL', null, false)->update('ha_question_option', array('ha_retired_at' => self::now()));   // retire, never delete: past answers keep their option rows
        foreach ($opts as $o) {
            $o = (array) $o; if (trim((string) ($o['body_en'] ?? '')) === '') continue;
            $db->insert('ha_question_option', array('question_id' => (int) $qid, 'body_en' => self::text($o['body_en'], 500), 'body_ar' => self::text($o['body_ar'] ?? '', 500), 'match_key_en' => isset($o['match_key_en']) ? self::text($o['match_key_en'], 255) : null, 'is_correct' => !empty($o['is_correct']) || $type === 'matching' ? 1 : 0, 'sort_order' => $n++));
        }
        if (in_array($type, array('multiple_choice', 'true_false', 'multiple_response'), true) && ($n < 2 || !$db->where(array('question_id' => (int) $qid, 'is_correct' => 1))->where('ha_retired_at IS NULL', null, false)->count_all_results('ha_question_option'))) throw new InvalidArgumentException('Choice questions need at least two options and one correct option.');
    }

    /** Creates a draft structure object. $parent: course id (sections, lessons, quizzes) or quiz id (questions). */
    public function create($type, $parent, array $in) {
        $db = $this->db(); $now = self::now();
        if ($type === 'course_sections') {
            $this->need('courses.update', 'courses.update permission required.'); $c = $this->course($parent, true);
            if ($c['status'] === 'published') throw $this->locked('courses', $c['id'], 'The course is published, so its outline is live.');
            $t = self::text($in['title_en'] ?? '', 190); if ($t === '') throw new InvalidArgumentException('title_en is required.');
            $max = (int) $db->select_max('sort_order')->get_where('ha_course_section', array('course_id' => (int) $c['id']))->row()->sort_order;
            $db->insert('ha_course_section', array('course_id' => (int) $c['id'], 'title_en' => $t, 'title_ar' => self::text($in['title_ar'] ?? '', 190) ?: $t, 'sort_order' => $max + 1));
            $id = (int) $db->insert_id(); $this->CI->ha_audit->log('create', 'course_section', $id, array('description' => 'MCP: section added to course ' . $c['id'])); return $id;
        }
        if ($type === 'lessons') {
            $this->need('lessons.create', 'lessons.create permission required.'); $c = $this->course($parent, true);
            $title = self::text($in['title_en'] ?? '', 190); if ($title === '') throw new InvalidArgumentException('title_en is required.');
            $row = $this->lesson_row($in, $c['id']);
            $max = (int) $db->select_max('sort_order')->get_where('ha_lesson', array('course_id' => (int) $c['id']))->row()->sort_order;
            $db->insert('ha_lesson', $row + array('course_id' => (int) $c['id'], 'lesson_type' => 'text', 'media_type' => 'none', 'duration_seconds' => 0, 'is_mandatory' => 1, 'is_preview' => 0, 'completion_rule' => 'open', 'required_watch_percentage' => 90, 'sort_order' => $max + 1, 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now));
            $id = (int) $db->insert_id(); $this->lesson_translations($id, $in, $title);
            $this->CI->ha_audit->log('create', 'lesson', $id, array('description' => 'MCP: draft lesson ' . $title . ' created')); return $id;
        }
        if ($type === 'quizzes') {
            $this->need('assessments.create', 'assessments.create permission required.'); $c = $this->course($parent, true);
            $title = self::text($in['title_en'] ?? '', 190); if ($title === '') throw new InvalidArgumentException('title_en is required.');
            $bank = $db->get_where('ha_question_bank', array('course_id' => (int) $c['id']))->row_array();
            if ($bank) $bank_id = (int) $bank['id'];
            else { $db->insert('ha_question_bank', array('code' => 'qb-' . $c['code'] . '-' . substr(bin2hex(random_bytes(3)), 0, 5), 'name_en' => $title, 'name_ar' => self::text($in['title_ar'] ?? '', 190), 'course_id' => (int) $c['id'], 'status' => 'active', 'created_at' => $now, 'updated_at' => $now)); $bank_id = (int) $db->insert_id(); }
            $n = 1 + (int) $db->where('course_id', (int) $c['id'])->count_all_results('ha_assessment'); $code = 'as-' . $c['code'] . '-q' . $n;
            while ($db->where('code', $code)->count_all_results('ha_assessment')) $code = 'as-' . $c['code'] . '-q' . (++$n);
            $db->insert('ha_assessment', array('code' => $code, 'title_en' => $title, 'title_ar' => self::text($in['title_ar'] ?? '', 190), 'instructions_en' => isset($in['instructions_en']) ? self::text($in['instructions_en'], 5000) : null, 'instructions_ar' => isset($in['instructions_ar']) ? self::text($in['instructions_ar'], 5000) : null,
                'assessment_type' => 'quiz', 'course_id' => (int) $c['id'], 'bank_id' => $bank_id, 'question_selection' => 'fixed', 'pass_percentage' => max(1, min(100, (int) ($in['pass_percentage'] ?? 75))), 'max_attempts' => max(0, (int) ($in['max_attempts'] ?? 0)),
                'time_limit_minutes' => max(0, (int) ($in['time_limit_minutes'] ?? 0)), 'show_correct_answers' => isset($in['show_correct_answers']) ? (int) (bool) $in['show_correct_answers'] : 1, 'status' => 'draft', 'created_by' => $this->A()->id(), 'created_at' => $now, 'updated_at' => $now));
            $id = (int) $db->insert_id(); $this->CI->ha_audit->log('create', 'assessment', $id, array('description' => 'MCP: draft quiz ' . $code . ' created')); return $id;
        }
        if ($type === 'questions') {
            $this->need('question_banks.create', 'question_banks.create permission required.');
            $quiz = $this->data('quizzes', $parent, true);
            if ($quiz['data']['status'] === 'published') throw $this->locked('quizzes', $parent, 'The quiz is published, so its questions are live.');
            $qt = (string) ($in['question_type'] ?? 'multiple_choice'); if (!in_array($qt, self::QUESTION_TYPES, true)) throw new InvalidArgumentException('question_type must be one of: ' . implode(', ', self::QUESTION_TYPES));
            $body = self::text($in['body_en'] ?? '', 5000); if ($body === '') throw new InvalidArgumentException('body_en is required.');
            $db->insert('ha_question', array('bank_id' => (int) $quiz['row']['bank_id'], 'question_type' => $qt, 'body_en' => $body, 'body_ar' => self::text($in['body_ar'] ?? '', 5000), 'explanation_en' => isset($in['explanation_en']) ? self::text($in['explanation_en'], 5000) : null, 'explanation_ar' => isset($in['explanation_ar']) ? self::text($in['explanation_ar'], 5000) : null,
                'marks' => max(0.5, min(100, (float) ($in['marks'] ?? 1))), 'difficulty' => in_array($in['difficulty'] ?? '', array('easy', 'medium', 'hard'), true) ? $in['difficulty'] : 'medium', 'domain_id' => $quiz['row']['domain_id'], 'requires_manual_grading' => $qt === 'essay' ? 1 : 0, 'status' => 'active', 'version_no' => 1, 'created_at' => $now, 'updated_at' => $now));
            $id = (int) $db->insert_id(); $this->question_options($id, (array) ($in['options'] ?? array()), $qt);
            $order = (int) $db->where('assessment_id', (int) $parent)->count_all_results('ha_assessment_question');
            $db->insert('ha_assessment_question', array('assessment_id' => (int) $parent, 'question_id' => $id, 'sort_order' => $order));
            $this->CI->ha_audit->log('create', 'question', $id, array('description' => 'MCP: question added to quiz ' . (int) $parent)); return $id;
        }
        throw new InvalidArgumentException('Unsupported type.');
    }

    /** Partial update with optimistic concurrency. Returns changed field names. */
    public function update($type, $id, array $in, $expected) {
        $db = $this->db(); $cur = $this->data($type, $id, true); $version = self::version_of($cur['data']);
        if ((string) $expected !== (string) $version) throw $this->conflict($type, $id, $version, $expected);
        unset($in['status'], $in['id'], $in['course_id']);   // status changes only through approvals
        $changed = array_keys($in); $now = self::now();
        if ($type === 'course_sections') {
            $this->need('courses.update', 'courses.update permission required.');
            if ($db->get_where('ha_course', array('id' => $cur['course_id']))->row('status') === 'published') throw $this->locked('courses', $cur['course_id'], 'The course is published, so its outline is live.');
            $u = array(); foreach (array('title_en', 'title_ar') as $k) if (array_key_exists($k, $in)) { $u[$k] = self::text($in[$k], 190); if ($u[$k] === '') throw new InvalidArgumentException($k . ' cannot be empty.'); }
            if ($u) $db->where('id', (int) $id)->update('ha_course_section', $u);
        } elseif ($type === 'lessons') {
            $this->need('lessons.update', 'lessons.update permission required.');
            if ($cur['row']['status'] === 'published') throw $this->locked('lessons', $id, 'The lesson is published.');
            $row = $this->lesson_row($in, $cur['course_id']);
            $this->snapshot_lesson($id, 'MCP: before edit');
            if ($row) $db->where('id', (int) $id)->update('ha_lesson', $row + array('updated_at' => $now));
            $this->lesson_translations($id, $in, $cur['data']['title_en'] ?? 'Lesson');
            $db->where('id', (int) $id)->update('ha_lesson', array('updated_at' => $now));
        } elseif ($type === 'quizzes') {
            $this->need('assessments.update', 'assessments.update permission required.');
            if ($cur['row']['status'] === 'published') throw $this->locked('quizzes', $id, 'The quiz is published.');
            $u = array();
            foreach (array('title_en' => 190, 'title_ar' => 190, 'instructions_en' => 5000, 'instructions_ar' => 5000) as $k => $max) if (array_key_exists($k, $in)) $u[$k] = self::text($in[$k], $max);
            if (isset($u['title_en']) && $u['title_en'] === '') throw new InvalidArgumentException('title_en cannot be empty.');
            foreach (array('pass_percentage' => array(1, 100), 'max_attempts' => array(0, 100), 'time_limit_minutes' => array(0, 1440)) as $k => $r) if (array_key_exists($k, $in)) $u[$k] = max($r[0], min($r[1], (int) $in[$k]));
            foreach (array('shuffle_questions', 'shuffle_options', 'show_correct_answers') as $k) if (array_key_exists($k, $in)) $u[$k] = !empty($in[$k]) ? 1 : 0;
            if ($u) $db->where('id', (int) $id)->update('ha_assessment', $u + array('updated_at' => $now));
        } else {
            $this->need('question_banks.update', 'question_banks.update permission required.');
            foreach ($cur['quizzes'] as $qz) if ($qz['status'] === 'published') throw $this->locked('quizzes', $qz['id'], 'The question belongs to a published quiz.');
            $u = array();
            foreach (array('body_en' => 5000, 'body_ar' => 5000, 'explanation_en' => 5000, 'explanation_ar' => 5000) as $k => $max) if (array_key_exists($k, $in)) $u[$k] = self::text($in[$k], $max);
            if (isset($u['body_en']) && $u['body_en'] === '') throw new InvalidArgumentException('body_en cannot be empty.');
            if (array_key_exists('marks', $in)) $u['marks'] = max(0.5, min(100, (float) $in['marks']));
            if (array_key_exists('difficulty', $in)) { if (!in_array($in['difficulty'], array('easy', 'medium', 'hard'), true)) throw new InvalidArgumentException('difficulty: easy, medium or hard.'); $u['difficulty'] = $in['difficulty']; }
            $qt = $cur['row']['question_type'];
            if (array_key_exists('question_type', $in)) { if (!in_array($in['question_type'], self::QUESTION_TYPES, true)) throw new InvalidArgumentException('Unsupported question_type.'); $u['question_type'] = $qt = $in['question_type']; $u['requires_manual_grading'] = $qt === 'essay' ? 1 : 0; }
            if ($u) $db->where('id', (int) $id)->update('ha_question', $u + array('updated_at' => $now, 'version_no' => (int) $cur['row']['version_no'] + 1));
            if (array_key_exists('options', $in)) $this->question_options($id, (array) $in['options'], $qt);
        }
        $this->CI->ha_audit->log('update', rtrim($type, 's'), (int) $id, array('description' => 'MCP: updated ' . implode(', ', $changed)));
        return $changed;
    }

    /** Reorders children. Course outline/lesson order is live, so it requires an unpublished course; quiz order an unpublished quiz. */
    public function reorder($type, $parent, array $ids) {
        $db = $this->db(); $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($type === 'questions') {
            $this->need(array('assessments.update', 'question_banks.update'), 'Assessment permission required.');
            $quiz = $this->data('quizzes', $parent, true); if ($quiz['data']['status'] === 'published') throw $this->locked('quizzes', $parent, 'The quiz is published.');
            $existing = $quiz['data']['question_ids'];
            if (count($ids) !== count($existing) || array_diff($ids, $existing)) throw new InvalidArgumentException('Provide every question id of the quiz exactly once.');
            foreach ($ids as $i => $qid) $db->where(array('assessment_id' => (int) $parent, 'question_id' => $qid))->update('ha_assessment_question', array('sort_order' => $i));
        } else {
            $this->need($type === 'lessons' ? 'lessons.update' : 'courses.update', 'Course permission required.');
            $c = $this->course($parent, true); if ($c['status'] === 'published') throw $this->locked('courses', $c['id'], 'The course is published, so its order is live.');
            $table = $type === 'lessons' ? 'ha_lesson' : 'ha_course_section';
            $existing = array_map('intval', array_column($db->select('id')->get_where($table, array('course_id' => (int) $c['id']))->result_array(), 'id'));
            if (count($ids) !== count($existing) || array_diff($ids, $existing)) throw new InvalidArgumentException('Provide every id of the course exactly once.');
            foreach ($ids as $i => $oid) $db->where(array('id' => $oid, 'course_id' => (int) $c['id']))->update($table, array('sort_order' => $i));
        }
        $this->CI->ha_audit->log('update', $type, (int) $parent, array('description' => 'MCP: reordered ' . $type));
        return $ids;
    }

    /** Approved publication / archive of lessons and quizzes (called by Ha_publishing_service). */
    public function set_status($type, $id, $status) {
        $this->data($type, $id, true);
        if (!in_array($type, array('lessons', 'quizzes'), true)) throw new InvalidArgumentException('This object cannot be published on its own.');
        $this->need($type === 'lessons' ? 'courses.publish' : array('assessments.publish', 'courses.publish'), 'Publication permission required.');
        if ($type === 'lessons') $this->snapshot_lesson($id, 'Before approved ' . $status);
        $this->db()->where('id', (int) $id)->update($type === 'lessons' ? 'ha_lesson' : 'ha_assessment', array('status' => $status, 'updated_at' => self::now()));
        if ($type === 'lessons') { $l = $this->row('lessons', $id); $total = (int) $this->db()->where(array('course_id' => $l['course_id'], 'status' => 'published'))->count_all_results('ha_lesson'); if ($this->db()->table_exists('ha_enrollment')) $this->db()->where('course_id', $l['course_id'])->update('ha_enrollment', array('lessons_total' => $total)); }
    }
    public function publish_permission($type) { $this->need($type === 'lessons' ? 'courses.publish' : array('assessments.publish', 'courses.publish'), 'Publication permission required.'); }

    // ================================================================= revisions
    public function revisions($type, $id) {
        $db = $this->db();
        if ($type === 'page') { $this->CI->load->library('ha_website_studio'); $this->CI->ha_website_studio->state($id); return $db->select('id, note, created_by, created_at')->order_by('id', 'DESC')->limit(50)->get_where('ha_page_revision', array('page_id' => (int) $id))->result_array(); }
        if ($type === 'lessons') { $this->data('lessons', $id); return $db->select('id, version_no, change_summary, changed_by, created_at')->order_by('version_no', 'DESC')->limit(50)->get_where('ha_lesson_version', array('lesson_id' => (int) $id))->result_array(); }
        if ($type === 'sops') { $doc = $this->sop($id); return array_map(function ($v) { return array_intersect_key($v, array_flip(array('id', 'version_label', 'status', 'change_summary', 'author_user_id', 'created_at', 'published_at'))); }, $doc['versions']); }
        $this->CI->load->library('ha_content_studio'); $this->CI->ha_content_studio->state($type, $id);
        return $db->select('id, actor_id, created_at')->order_by('id', 'DESC')->limit(50)->get_where('ha_studio_revision', array('object_type' => $type, 'object_id' => (int) $id))->result_array();
    }
    /** Lesson revision restore: only into an unpublished lesson, after snapshotting the current state. */
    public function restore_lesson($id, $revision, $expected) {
        $cur = $this->data('lessons', $id, true); $v = self::version_of($cur['data']);
        if ((string) $expected !== (string) $v) throw $this->conflict('lessons', $id, $v, $expected);
        if ($cur['row']['status'] === 'published') throw $this->locked('lessons', $id, 'The lesson is published.');
        $r = $this->db()->get_where('ha_lesson_version', array('id' => (int) $revision, 'lesson_id' => (int) $id))->row_array(); if (!$r) throw new Ha_api_error(404, 'not_found', 'Revision not found.');
        $snap = json_decode($r['snapshot_json'], true); $in = array();
        foreach (array('lesson_type', 'section_id', 'video_url', 'external_url', 'is_mandatory', 'is_preview', 'completion_rule', 'required_watch_percentage', 'assessment_id') as $k) if (array_key_exists($k, $snap['lesson'] ?? array())) $in[$k] = $snap['lesson'][$k];
        if (isset($snap['lesson']['duration_seconds'])) $in['duration_minutes'] = (int) round($snap['lesson']['duration_seconds'] / 60);
        foreach ((array) ($snap['translations'] ?? array()) as $t) foreach (array('title', 'objective', 'body', 'transcript') as $f) $in[$f . '_' . $t['locale']] = (string) $t[$f];
        foreach (array('section_id', 'assessment_id') as $k) if (!empty($in[$k]) && !$this->db()->where(array('id' => (int) $in[$k]))->count_all_results($k === 'section_id' ? 'ha_course_section' : 'ha_assessment')) $in[$k] = null;
        return $this->update('lessons', $id, $in, $v);
    }

    // ================================================================= SOPs (existing governance)
    private function K() { $this->CI->load->library('ha_knowledge'); return $this->CI->ha_knowledge; }
    public function sop($id) {
        $this->need(array('knowledge.view', 'knowledge.create', 'knowledge.update', 'knowledge.review'), 'Knowledge permission required.');
        if (!$this->K()->can_view($id, $this->A()->id())) throw new Ha_api_error(404, 'not_found', 'SOP not found.', array('object_type' => 'sops', 'object_id' => (int) $id));
        $doc = $this->K()->get($id); if (!$doc) throw new Ha_api_error(404, 'not_found', 'SOP not found.', array('object_type' => 'sops', 'object_id' => (int) $id));
        return $doc;
    }
    private function sop_working_text($doc) {
        $w = $doc['working']; $out = array('title_en' => '', 'title_ar' => '', 'sections' => array('en' => array(), 'ar' => array()));
        if (!$w) return $out;
        foreach ($this->db()->get_where('ha_sop_version_translation', array('version_id' => $w['id']))->result_array() as $t) {
            if (!in_array($t['locale'], array('en', 'ar'), true)) continue;
            $out['title_' . $t['locale']] = $t['title']; foreach (Ha_knowledge::$sections as $s) $out['sections'][$t['locale']][$s] = $t[$s];
        }
        return $out;
    }
    public function sop_describe($id) {
        $doc = $this->sop($id); $text = $this->sop_working_text($doc);
        $data = array('code' => $doc['code'], 'item_type' => $doc['item_type'], 'status' => $doc['status'], 'working_version' => $doc['working'] ? array('id' => (int) $doc['working']['id'], 'label' => $doc['working']['version_label'], 'status' => $doc['working']['status']) : null) + $text;
        return array('object_id' => (int) $id, 'object_type' => 'sops', 'status' => $doc['working']['status'] ?? $doc['status'], 'version' => self::version_of($data), 'data' => $data, 'edit_url' => hkp_url('admin/content/edit/' . (int) $id), 'preview_url' => hkp_url('admin/content/edit/' . (int) $id),
            'warnings' => array('SOPs are published only through the SOP governance workflow (review, quality approval, publication by a different person).'));
    }
    public function sop_list($query) {
        $this->need(array('knowledge.view', 'knowledge.create', 'knowledge.update', 'knowledge.review'), 'Knowledge permission required.');
        $vis = $this->K()->visibility_sql($this->A()->id(), 'd'); $db = $this->db(); $q = self::text($query, 100);
        $db->select('d.id, d.code, d.item_type, d.status, d.organization_id, d.updated_at')->from('ha_sop_document d')->where($vis, null, false);
        if ($q !== '') $db->like('d.code', $q);
        return $db->order_by('d.updated_at', 'DESC')->limit(50)->get()->result_array();
    }
    public function sop_create(array $in) {
        $data = array('title_en' => self::text($in['title_en'] ?? '', 190), 'title_ar' => self::text($in['title_ar'] ?? '', 190), 'item_type' => $in['item_type'] ?? 'sop', 'sections' => $this->sop_sections($in));
        foreach (array('code', 'change_summary', 'tags') as $k) if (isset($in[$k])) $data[$k] = self::text($in[$k], 255);
        unset($in['organization_id'], $in['property_id']);   // tenant comes from the signed-in user
        return (int) $this->K()->create($data, $this->A()->id());
    }
    private function sop_sections(array $in, array $base = array('en' => array(), 'ar' => array())) {
        foreach (array('en', 'ar') as $loc) foreach ((array) ($in['sections'][$loc] ?? array()) as $k => $v) { if (!in_array($k, Ha_knowledge::$sections, true)) throw new InvalidArgumentException('Unknown SOP section: ' . $k . '. Use: ' . implode(', ', Ha_knowledge::$sections)); $base[$loc][$k] = mb_substr((string) $v, 0, 100000); }
        return $base;
    }
    public function sop_update($id, array $in, $expected) {
        $d = $this->sop_describe($id); if ((string) $expected !== (string) $d['version']) throw $this->conflict('sops', $id, $d['version'], $expected);
        $doc = $this->sop($id); $text = $this->sop_working_text($doc);
        $data = array('title_en' => array_key_exists('title_en', $in) ? self::text($in['title_en'], 190) : $text['title_en'], 'title_ar' => array_key_exists('title_ar', $in) ? self::text($in['title_ar'], 190) : $text['title_ar'], 'sections' => $this->sop_sections($in, $text['sections']));
        foreach (array('change_summary', 'tags', 'item_type') as $k) if (isset($in[$k])) $data[$k] = self::text($in[$k], 500);
        $this->K()->update_draft($id, $data, $this->A()->id());
        return array_keys($in);
    }
    public function sop_workflow($id, $action, $comment, $major) {
        $this->sop($id);
        if ($action === 'new_version') return array('version_id' => $this->K()->new_version($id, $this->A()->id(), (bool) $major, $comment ? self::text($comment, 500) : null));
        if ($action === 'submit') { $this->K()->act($id, 'submit', $this->A()->id(), $comment ? self::text($comment, 1000) : null); return array('submitted' => true); }
        throw new InvalidArgumentException('Supported SOP actions through MCP: new_version, submit. Review, approval and publication stay with people in ALTUS.');
    }

    // ================================================================= pages
    public function page_create(array $in) {
        $this->need('cms_pages.create', 'cms_pages.create permission required.');
        if (!$this->A()->is_system_scoped()) throw new Ha_api_error(403, 'forbidden', 'Website pages require platform access.');
        $db = $this->db(); $title = self::text($in['title_en'] ?? '', 190);
        $slug = trim(preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) ($in['slug_en'] ?? $title))), '-');
        if ($title === '' || $slug === '') throw new InvalidArgumentException('title_en is required.');
        if ($db->where('slug_en', $slug)->or_where('code', $slug)->count_all_results('ha_page')) throw new Ha_api_error(409, 'conflict', 'That address is already used by another page.', array('slug_en' => $slug));
        $now = self::now(); $title_ar = self::text($in['title_ar'] ?? '', 190) ?: $title;
        $slug_ar = trim(preg_replace('/[^\p{Arabic}a-z0-9\-]+/u', '-', mb_strtolower($title_ar)), '-') ?: $slug . '-ar';
        if ($db->where('slug_ar', $slug_ar)->count_all_results('ha_page')) $slug_ar .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $db->insert('ha_page', array('code' => $slug, 'slug_en' => $slug, 'slug_ar' => $slug_ar, 'template' => 'standard', 'status' => 'draft', 'created_by' => $this->A()->id(), 'created_at' => $now, 'updated_at' => $now));
        $id = (int) $db->insert_id();
        foreach (array('en' => $title, 'ar' => $title_ar) as $loc => $t) $db->insert('ha_page_translation', array('page_id' => $id, 'locale' => $loc, 'title' => $t));
        $this->CI->ha_audit->log('create', 'page', $id, array('description' => 'MCP: draft page ' . $slug . ' created'));
        return $id;
    }
    private static function section_key(array $s) { return (string) ($s['studio_key'] ?? ('section-' . ($s['id'] ?? ''))); }
    /** Page section operations applied to the private website draft (never the live page). */
    public function page_section($page_id, $op, array $in, $expected) {
        $this->CI->load->library(array('ha_website_studio', 'ha_page_builder'));
        $W = $this->CI->ha_website_studio; $s = $W->state($page_id);
        if ((string) $expected !== (string) $s['version']) throw new Ha_api_error(409, 'conflict', 'The page draft changed. Re-read it and retry.', array('object_type' => 'page', 'object_id' => (int) $page_id, 'current_version' => (int) $s['version'], 'expected_version' => $expected, 'current_base_hash' => $s['base_hash']));
        $p = $s['payload']; $secs = array_values((array) $p['sections']);
        foreach ($secs as &$x) $x['studio_key'] = self::section_key($x); unset($x);
        $keys = array_column($secs, 'studio_key'); $key = (string) ($in['key'] ?? ''); $pos = array_search($key, $keys, true);
        if (in_array($op, array('update', 'duplicate', 'hide', 'show', 'move'), true) && $pos === false) throw new Ha_api_error(404, 'not_found', 'Section key not found on this page draft.', array('key' => $key, 'available_keys' => $keys));
        $new = function () use ($keys) { do { $k = 'mcp-' . bin2hex(random_bytes(5)); } while (in_array($k, $keys, true)); return $k; };
        $result_key = $key;
        if ($op === 'insert') {
            $sec = (array) ($in['section'] ?? array()); $type = (string) ($sec['section_type'] ?? '');
            if (!isset(Ha_page_builder::types()[$type])) throw new InvalidArgumentException('section.section_type must be one of: ' . implode(', ', array_keys(Ha_page_builder::types())));
            $row = array('studio_key' => $result_key = $new(), 'section_type' => $type, 'is_visible' => isset($sec['is_visible']) ? (int) (bool) $sec['is_visible'] : 1, 'settings' => (array) ($sec['settings'] ?? array()), 'en' => (array) ($sec['en'] ?? array()), 'ar' => (array) ($sec['ar'] ?? array()));
            $at = isset($in['position']) ? max(0, min(count($secs), (int) $in['position'])) : count($secs);
            array_splice($secs, $at, 0, array($row));
        } elseif ($op === 'update') {
            $sec = (array) ($in['section'] ?? array());
            foreach (array('en', 'ar', 'settings') as $k) if (isset($sec[$k])) $secs[$pos][$k] = array_merge((array) ($secs[$pos][$k] ?? array()), (array) $sec[$k]);
            if (isset($sec['is_visible'])) $secs[$pos]['is_visible'] = (int) (bool) $sec['is_visible'];
        } elseif ($op === 'duplicate') { $copy = $secs[$pos]; $copy['studio_key'] = $result_key = $new(); unset($copy['id']); array_splice($secs, $pos + 1, 0, array($copy)); }
        elseif ($op === 'hide' || $op === 'show') $secs[$pos]['is_visible'] = $op === 'show' ? 1 : 0;
        elseif ($op === 'move') { $row = $secs[$pos]; array_splice($secs, $pos, 1); $at = max(0, min(count($secs), (int) ($in['position'] ?? 0))); array_splice($secs, $at, 0, array($row)); }
        elseif ($op === 'reorder') {
            $order = array_values(array_map('strval', (array) ($in['order'] ?? array())));
            if (count($order) !== count($keys) || array_diff($order, $keys) || count(array_unique($order)) !== count($order)) throw new InvalidArgumentException('order must list every section key exactly once.', 0);
            $by = array_combine($keys, $secs); $secs = array(); foreach ($order as $k) $secs[] = $by[$k];
        } else throw new InvalidArgumentException('Unsupported section operation.');
        $p['sections'] = $secs;
        $W->save($page_id, $p, (int) $s['version'], (string) $s['base_hash']);
        $this->CI->ha_audit->log('update', 'page', (int) $page_id, array('description' => 'MCP: page draft section ' . $op . ($result_key ? ' (' . $result_key . ')' : '')));
        return $result_key;
    }

    // ================================================================= media, previews, audit, search
    public function media_url($media_id) {
        $m = $this->db()->get_where('ha_media', array('id' => (int) $media_id))->row_array();
        if (!$m || ($m['disk'] ?? 'public') !== 'public') throw new Ha_api_error(404, 'not_found', 'Media item not found in the public library.', array('media_id' => (int) $media_id));
        return base_url(ltrim($m['file_path'], '/'));
    }
    /** Signed or authenticated preview link of the current draft. */
    public function preview_link($type, $id) {
        $this->CI->load->library(array('ha_publishing_service', 'ha_studio_preview'));
        if (in_array($type, self::STRUCTURE, true)) { $d = $this->describe($type, $id); return array('preview_url' => $d['preview_url'], 'signed' => false, 'note' => 'Opens the draft preview for a signed-in ALTUS editor.'); }
        if ($type === 'sops') { $d = $this->sop_describe($id); return array('preview_url' => $d['preview_url'], 'signed' => false); }
        $d = $this->CI->ha_publishing_service->describe($type, $id); $url = $d['preview_url']; $signed = strpos($url, hkp_url('')) !== 0;   // describe() signs public previews of published entities
        if ($type === 'page' && ($d['state']['page']['status'] ?? '') === 'published') {
            $pg = $d['state']['page']; $slug = $pg['code'] === 'home' ? '' : ($pg['code'] === 'for-hotels' ? 'hotels' : $pg['slug_en']);
            $url = $this->CI->ha_studio_preview->sign_url(base_url('en' . ($slug !== '' ? '/' . $slug : '')) . '?studio_preview=' . (int) $id, 'page', (int) $id); $signed = true;
        }
        return array('preview_url' => $url, 'signed' => $signed, 'note' => $signed ? 'Signed, time-limited preview link.' : 'Opens the draft preview for a signed-in ALTUS editor.');
    }
    public function audit_list(array $f, $all) {
        $db = $this->db(); $limit = max(1, min(100, (int) ($f['limit'] ?? 25)));
        $db->select('id, created_at, user_id, action, entity_type, entity_id, description')->from('ha_audit_log');
        if (!$all) $db->where('user_id', (int) $this->A()->id());
        if (!empty($f['object_type'])) $db->where('entity_type', self::text($f['object_type'], 80));
        if (!empty($f['object_id'])) $db->where('entity_id', (int) $f['object_id']);
        if (!empty($f['action_prefix'])) $db->like('action', preg_replace('/[^a-z0-9_.]/', '', strtolower($f['action_prefix'])), 'after');
        return array('scope' => $all ? 'all users' : 'your own actions', 'rows' => $db->order_by('id', 'DESC')->limit($limit)->get()->result_array());
    }
}
