<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Theory assessment delivery (ppt-features 16, 102, 103, 168).
 *
 * Question types: multiple choice, multiple answer, true/false, matching,
 * ordering, scenario, short answer, essay. Fixed or random selection from a
 * bank, shuffled questions and options, time limit, attempt limit, automatic
 * grading with partial credit for matching and ordering, manual grading for
 * essays and unmatched short answers, feedback and explanations.
 *
 * The question order and option order a learner saw are stored on the attempt,
 * so grading and review always use exactly what was shown. Correct answers
 * never leave the server until the attempt is submitted.
 */
class Ha_theory {

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_audit', 'ha_notify', 'ha_tenant'));
    }

    public function assessment($id) {
        return $this->CI->db->get_where('ha_assessment', array('id' => (int) $id))->row_array();
    }

    public function attempts($assessment_id, $user_id) {
        return $this->CI->db->order_by('attempt_no', 'DESC')->get_where('ha_assessment_attempt',
            array('assessment_id' => (int) $assessment_id, 'user_id' => (int) $user_id))->result_array();
    }

    /** Starts an attempt or resumes the one in progress. */
    public function start($assessment_id, $user_id) {
        $a = $this->assessment($assessment_id);
        if (!$a || $a['status'] !== 'published') {
            throw new RuntimeException('That assessment is not available.');
        }
        $now = time();
        if ($a['available_from'] && strtotime($a['available_from']) > $now) {
            throw new RuntimeException('This assessment opens on ' . substr($a['available_from'], 0, 16) . '.');
        }
        if ($a['available_until'] && strtotime($a['available_until']) < $now) {
            throw new RuntimeException('This assessment closed on ' . substr($a['available_until'], 0, 16) . '.');
        }
        if ($a['course_id']) {
            $this->CI->load->library('ha_learning');
            if (!$this->CI->ha_learning->course_visible($a['course_id'], $user_id)) {
                throw new RuntimeException('That assessment is not available to you.');
            }
        }
        $open = $this->CI->db->get_where('ha_assessment_attempt', array('assessment_id' => (int) $assessment_id, 'user_id' => (int) $user_id, 'status' => 'in_progress'))->row_array();
        if ($open) {
            if ($open['expires_at'] && strtotime($open['expires_at']) < $now) {
                $this->submit($open['id'], array(), $user_id);
            } else {
                return (int) $open['id'];
            }
        }
        $used = (int) $this->CI->db->where(array('assessment_id' => (int) $assessment_id, 'user_id' => (int) $user_id))->count_all_results('ha_assessment_attempt');
        if ((int) $a['max_attempts'] > 0 && $used >= (int) $a['max_attempts']) {
            throw new RuntimeException('You have used all ' . (int) $a['max_attempts'] . ' attempts.');
        }
        if ($this->CI->db->where(array('assessment_id' => (int) $assessment_id, 'user_id' => (int) $user_id, 'passed' => 1))->count_all_results('ha_assessment_attempt')) {
            throw new RuntimeException('You have already passed this assessment.');
        }
        $qids = $this->select_questions($a);
        if (!$qids) {
            throw new RuntimeException('This assessment has no questions yet.');
        }
        $order = array();
        foreach ($qids as $qid) {
            $opts = array_map('intval', array_column($this->CI->db->select('id')->order_by('sort_order')->get_where('ha_question_option', array('question_id' => $qid))->result_array(), 'id'));
            $q = $this->CI->db->select('question_type')->get_where('ha_question', array('id' => $qid))->row_array();
            if ((int) $a['shuffle_options'] || in_array($q['question_type'], array('ordering', 'matching'), true)) {
                shuffle($opts);
            }
            $order[] = array('q' => $qid, 'o' => $opts);
        }
        $p = $this->CI->db->get_where('ha_profile', array('user_id' => (int) $user_id))->row_array();
        $d = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_assessment_attempt', array(
            'assessment_id' => (int) $assessment_id, 'user_id' => (int) $user_id, 'property_id' => $p ? $p['property_id'] : null,
            'attempt_no' => $used + 1, 'question_order' => json_encode($order), 'status' => 'in_progress',
            'started_at' => $d, 'expires_at' => (int) $a['time_limit_minutes'] > 0 ? date('Y-m-d H:i:s', $now + 60 * (int) $a['time_limit_minutes']) : null,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 64) : 'cli',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'created_at' => $d, 'updated_at' => $d));
        return (int) $this->CI->db->insert_id();
    }

    protected function select_questions(array $a) {
        $fixed = array_map('intval', array_column($this->CI->db->select('question_id')->order_by('sort_order')
            ->get_where('ha_assessment_question', array('assessment_id' => $a['id']))->result_array(), 'question_id'));
        if ($a['question_selection'] === 'random' && $a['bank_id']) {
            $pool = array_map('intval', array_column($this->CI->db->select('id')->get_where('ha_question', array('bank_id' => $a['bank_id'], 'status' => 'active'))->result_array(), 'id'));
            shuffle($pool);
            $n = (int) $a['random_question_count'] ?: count($pool);
            $ids = array_slice(array_values(array_unique(array_merge($fixed, $pool))), 0, max($n, count($fixed)));
        } else {
            $ids = $fixed;
        }
        if ((int) $a['shuffle_questions']) {
            shuffle($ids);
        }
        return $ids;
    }

    /** The attempt as the learner sees it: questions and options, without correctness. */
    public function paper($attempt_id, $user_id) {
        $at = $this->CI->db->get_where('ha_assessment_attempt', array('id' => (int) $attempt_id))->row_array();
        if (!$at || ((int) $at['user_id'] !== (int) $user_id && !$this->CI->ha_auth->can_user($at['user_id']))) {
            return null;
        }
        $a = $this->assessment($at['assessment_id']);
        $order = json_decode((string) $at['question_order'], true) ?: array();
        $answers = array();
        foreach ($this->CI->db->get_where('ha_assessment_answer', array('attempt_id' => (int) $attempt_id))->result_array() as $r) {
            $answers[(int) $r['question_id']] = $r;
        }
        $questions = array();
        $show = $at['status'] !== 'in_progress';
        foreach ($order as $o) {
            $q = $this->CI->db->get_where('ha_question', array('id' => $o['q']))->row_array();
            if (!$q) {
                continue;
            }
            $opts = array();
            $all = array();
            foreach ($this->CI->db->get_where('ha_question_option', array('question_id' => $o['q']))->result_array() as $op) {
                $all[(int) $op['id']] = $op;
            }
            foreach ($o['o'] as $oid) {
                if (!isset($all[$oid])) {
                    continue;
                }
                $op = $all[$oid];
                $row = array('id' => (int) $op['id'], 'body' => hkp_pick($op, 'body'), 'match' => hkp_pick($op, 'match_key'));
                if ($show && (int) $a['show_correct_answers']) {
                    $row['is_correct'] = (int) $op['is_correct'];
                }
                $opts[] = $row;
            }
            $questions[] = array('id' => (int) $q['id'], 'type' => $q['question_type'], 'body' => hkp_pick($q, 'body'),
                'translated' => hkp_locale() === 'en' || trim((string) $q['body_ar']) !== '',
                'marks' => (float) $q['marks'], 'options' => $opts,
                'explanation' => $show ? hkp_pick($q, 'explanation') : null,
                'answer' => isset($answers[$q['id']]) ? $answers[$q['id']] : null);
        }
        return array('attempt' => $at, 'assessment' => $a, 'questions' => $questions);
    }

    /**
     * Grades and closes an attempt.
     * $responses: question_id => option id | array of ids | ordered ids | array(option_id => match_key) | text
     */
    public function submit($attempt_id, array $responses, $user_id, array $timings = array()) {
        $at = $this->CI->db->get_where('ha_assessment_attempt', array('id' => (int) $attempt_id))->row_array();
        if (!$at || (int) $at['user_id'] !== (int) $user_id) {
            throw new RuntimeException('Attempt not found.');
        }
        if ($at['status'] !== 'in_progress') {
            throw new RuntimeException('This attempt has already been submitted.');
        }
        $a = $this->assessment($at['assessment_id']);
        $order = json_decode((string) $at['question_order'], true) ?: array();
        $late = $at['expires_at'] && strtotime($at['expires_at']) + 30 < time();
        $score = 0;
        $max = 0;
        $manual = false;
        $now = date('Y-m-d H:i:s');
        $this->CI->db->trans_start();
        foreach ($order as $o) {
            $q = $this->CI->db->get_where('ha_question', array('id' => $o['q']))->row_array();
            if (!$q) {
                continue;
            }
            $opts = $this->CI->db->get_where('ha_question_option', array('question_id' => $q['id']))->result_array();
            $marks = (float) $q['marks'];
            $max += $marks;
            $resp = $late ? null : (isset($responses[$q['id']]) ? $responses[$q['id']] : null);
            list($awarded, $correct, $needs_manual, $stored) = $this->grade($q, $opts, $resp, $marks);
            if ($needs_manual) {
                $manual = true;
            }
            $score += $awarded;
            $row = array('attempt_id' => (int) $attempt_id, 'question_id' => (int) $q['id'],
                'selected_option_ids' => $stored['ids'], 'answer_text' => $stored['text'], 'match_payload' => $stored['match'],
                'is_correct' => $needs_manual ? null : ($correct ? 1 : 0), 'awarded_marks' => $awarded, 'max_marks' => $marks,
                'response_ms' => isset($timings[$q['id']]) ? max(0, (int) $timings[$q['id']]) : null, 'answered_at' => $now);
            $this->CI->db->replace('ha_assessment_answer', $row);
        }
        $pct = $max > 0 ? round(100 * $score / $max, 2) : 0;
        $pass_mark = (int) $a['pass_percentage'] ?: (int) $this->CI->ha_tenant->get('theory.default_pass');
        $this->CI->db->where('id', (int) $attempt_id)->update('ha_assessment_attempt', array(
            'status' => $manual ? 'submitted' : 'graded', 'score' => $score, 'max_score' => $max, 'percentage' => $pct,
            'passed' => $manual ? null : ($pct >= $pass_mark ? 1 : 0), 'requires_manual_grading' => $manual ? 1 : 0,
            'submitted_at' => $now, 'updated_at' => $now));
        $this->CI->db->trans_complete();
        if (!$manual) {
            $this->after_grading($attempt_id);
        }
        return $this->CI->db->get_where('ha_assessment_attempt', array('id' => (int) $attempt_id))->row_array();
    }

    /** @return array(awarded, fully_correct, needs_manual, stored) */
    public function grade(array $q, array $opts, $resp, $marks) {
        $stored = array('ids' => null, 'text' => null, 'match' => null);
        $correct_ids = array();
        foreach ($opts as $op) {
            if ((int) $op['is_correct']) {
                $correct_ids[] = (int) $op['id'];
            }
        }
        switch ($q['question_type']) {
            case 'multiple_choice':
            case 'true_false':
            case 'scenario':
                $pick = is_array($resp) ? (int) reset($resp) : (int) $resp;
                $stored['ids'] = $pick ? (string) $pick : null;
                $ok = $pick && in_array($pick, $correct_ids, true);
                return array($ok ? $marks : 0, $ok, false, $stored);
            case 'multiple_response':
                $picked = array_values(array_unique(array_map('intval', (array) $resp)));
                sort($picked);
                $c = $correct_ids;
                sort($c);
                $stored['ids'] = implode(',', $picked);
                $ok = $picked && $picked === $c;
                return array($ok ? $marks : 0, $ok, false, $stored);
            case 'ordering':
                // Correct order is the options' sort_order; credit for each item in its correct place.
                $by = $opts;
                usort($by, function ($x, $y) { return (int) $x['sort_order'] - (int) $y['sort_order']; });
                $expected = array_map('intval', array_column($by, 'id'));
                $given = array_values(array_map('intval', (array) $resp));
                $stored['ids'] = implode(',', $given);
                $right = 0;
                foreach ($expected as $i => $id) {
                    if (isset($given[$i]) && $given[$i] === $id) {
                        $right++;
                    }
                }
                $n = max(1, count($expected));
                return array(round($marks * $right / $n, 2), $right === count($expected), false, $stored);
            case 'matching':
                // Each option carries its correct match_key_en; the learner maps option id => chosen key.
                $given = is_array($resp) ? $resp : array();
                $stored['match'] = json_encode($given, JSON_UNESCAPED_UNICODE);
                $right = 0;
                foreach ($opts as $op) {
                    $want = mb_strtolower(trim((string) $op['match_key_en']));
                    $got = isset($given[$op['id']]) ? mb_strtolower(trim((string) $given[$op['id']])) : '';
                    $want_ar = mb_strtolower(trim((string) $op['match_key_ar']));
                    if ($got !== '' && ($got === $want || ($want_ar !== '' && $got === $want_ar))) {
                        $right++;
                    }
                }
                $n = max(1, count($opts));
                return array(round($marks * $right / $n, 2), $right === count($opts), false, $stored);
            case 'short_answer':
                $text = trim((string) (is_array($resp) ? reset($resp) : $resp));
                $stored['text'] = $text;
                $norm = function ($s) { return preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $s))); };
                foreach ($opts as $op) {
                    if ((int) $op['is_correct'] && $text !== '' && ($norm($text) === $norm($op['body_en']) || $norm($text) === $norm($op['body_ar']))) {
                        return array($marks, true, false, $stored);
                    }
                }
                return array(0, false, $text !== '', $stored);   // an unmatched answer goes to a person, not to zero
            case 'essay':
            default:
                $text = trim((string) (is_array($resp) ? reset($resp) : $resp));
                $stored['text'] = $text;
                return array(0, false, $text !== '', $stored);
        }
    }

    /** Manual grading of essays / unmatched short answers. $marks: question_id => array(marks, comment) */
    public function grade_manual($attempt_id, array $marks, $grader_id) {
        if (!$this->CI->ha_auth->has('assessments.grade')) {
            throw new RuntimeException('You do not have permission to grade assessments.');
        }
        $at = $this->CI->db->get_where('ha_assessment_attempt', array('id' => (int) $attempt_id))->row_array();
        if (!$at || $at['status'] !== 'submitted') {
            throw new InvalidArgumentException('That attempt is not awaiting grading.');
        }
        if (!$this->CI->ha_auth->can_user($at['user_id']) || (int) $at['user_id'] === (int) $grader_id) {
            throw new RuntimeException('You cannot grade this attempt.');
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->CI->db->get_where('ha_assessment_answer', array('attempt_id' => (int) $attempt_id))->result_array() as $ans) {
            if ($ans['is_correct'] !== null || !isset($marks[$ans['question_id']])) {
                continue;
            }
            $m = max(0, min((float) $ans['max_marks'], (float) $marks[$ans['question_id']][0]));
            $this->CI->db->where('id', $ans['id'])->update('ha_assessment_answer', array('awarded_marks' => $m,
                'is_correct' => $m >= (float) $ans['max_marks'] ? 1 : 0, 'grader_comment' => isset($marks[$ans['question_id']][1]) ? $marks[$ans['question_id']][1] : null,
                'graded_by' => (int) $grader_id, 'graded_at' => $now));
        }
        $left = (int) $this->CI->db->where('attempt_id', (int) $attempt_id)->where('is_correct IS NULL', null, false)->where("(answer_text IS NOT NULL AND answer_text != '')", null, false)
            ->count_all_results('ha_assessment_answer');
        if ($left > 0) {
            throw new InvalidArgumentException($left . ' answer(s) still need a mark.');
        }
        $sum = $this->CI->db->select('SUM(awarded_marks) s, SUM(max_marks) m', false)->get_where('ha_assessment_answer', array('attempt_id' => (int) $attempt_id))->row_array();
        $a = $this->assessment($at['assessment_id']);
        $pct = (float) $sum['m'] > 0 ? round(100 * (float) $sum['s'] / (float) $sum['m'], 2) : 0;
        $this->CI->db->where('id', (int) $attempt_id)->update('ha_assessment_attempt', array('status' => 'graded', 'score' => (float) $sum['s'],
            'percentage' => $pct, 'passed' => $pct >= (int) $a['pass_percentage'] ? 1 : 0, 'graded_by' => (int) $grader_id, 'graded_at' => $now, 'updated_at' => $now));
        $this->CI->ha_audit->log('update', 'assessment_attempt', (int) $attempt_id, array('description' => 'Manually graded: ' . $pct . '%'));
        $this->after_grading($attempt_id);
    }

    /** Competency evidence, notifications, repeated-fail alerts and progress roll-up after a final grade. */
    protected function after_grading($attempt_id) {
        $at = $this->CI->db->get_where('ha_assessment_attempt', array('id' => (int) $attempt_id))->row_array();
        $a = $this->assessment($at['assessment_id']);
        $uid = (int) $at['user_id'];
        $vars = array('assessment_name' => $a['title_en'], 'score' => $at['percentage'], 'pass_mark' => $a['pass_percentage'],
            'url' => hkp_url('assess/result/' . (int) $attempt_id), 'related_type' => 'attempt', 'related_id' => (int) $attempt_id, '_no_manager' => 1);
        $this->CI->load->library('ha_competency');
        if ((int) $at['passed']) {
            $this->CI->ha_notify->send($uid, 'assessment.passed', $vars);
            foreach ($this->CI->db->get_where('ha_assessment_competency', array('assessment_id' => $a['id']))->result_array() as $m) {
                $this->CI->ha_competency->record_result($uid, $m['skill_id'], (int) $m['level_on_pass'], 'theory',
                    array('source_id' => (int) $attempt_id, 'notes' => 'Theory: ' . $a['code'] . ' ' . $at['percentage'] . '%'));
            }
        } else {
            $this->CI->ha_notify->send($uid, 'assessment.failed', $vars);
            $fails = (int) $this->CI->db->where(array('assessment_id' => $a['id'], 'user_id' => $uid, 'passed' => 0))->count_all_results('ha_assessment_attempt');
            if ($fails >= (int) $this->CI->ha_tenant->get('theory.repeat_fail_alert')) {
                $p = $this->CI->db->get_where('ha_profile', array('user_id' => $uid))->row_array();
                $this->CI->ha_notify->send($uid, 'assessment.repeated_fail', array('assessment_name' => $a['title_en'], 'attempts' => $fails,
                    'url' => hkp_url('team/employee/' . $uid)));
                $this->CI->ha_notify->alert(array('type' => 'repeated_fail', 'severity' => 'warning', 'dedupe_key' => 'fail:' . $a['id'] . ':' . $uid,
                    'organization_id' => $p ? $p['organization_id'] : null, 'property_id' => $p ? $p['property_id'] : null,
                    'title_en' => 'Repeated fail: ' . $a['title_en'], 'title_ar' => 'إخفاق متكرر: ' . $a['title_ar'],
                    'entity_type' => 'user', 'entity_id' => $uid, 'url' => hkp_url('team/employee/' . $uid)));
            }
        }
        if ($a['course_id']) {
            $this->CI->load->library('ha_learning');
            $e = $this->CI->db->get_where('ha_enrollment', array('user_id' => $uid, 'course_id' => $a['course_id']))->row_array();
            if ($e) {
                $this->CI->ha_learning->refresh_enrollment($e['id']);
            }
        }
        $this->CI->load->library('ha_readiness');
        $this->CI->ha_readiness->calculate($uid);
    }

    /** Per-question statistics for curriculum review (section 168). No automatic "bad question" label. */
    public function question_stats($assessment_id) {
        $rows = $this->CI->db->query("SELECT q.id, q.body_en, q.question_type, COUNT(an.id) attempts,
                SUM(an.is_correct = 1) correct, SUM(an.is_correct = 0) incorrect, ROUND(AVG(an.response_ms)) avg_ms
            FROM ha_assessment_answer an JOIN ha_question q ON q.id = an.question_id
            JOIN ha_assessment_attempt at ON at.id = an.attempt_id
            WHERE at.assessment_id = ? GROUP BY q.id ORDER BY (SUM(an.is_correct = 1) / GREATEST(COUNT(an.id),1)) ASC", array((int) $assessment_id))->result_array();
        foreach ($rows as &$r) {
            $r['correct_pct'] = $r['attempts'] ? round(100 * $r['correct'] / $r['attempts'], 1) : null;
            $dist = array();
            foreach ($this->CI->db->select('an.selected_option_ids')->from('ha_assessment_answer an')->join('ha_assessment_attempt at', 'at.id = an.attempt_id')
                ->where(array('an.question_id' => $r['id'], 'at.assessment_id' => (int) $assessment_id))->get()->result_array() as $s) {
                foreach (array_filter(explode(',', (string) $s['selected_option_ids'])) as $oid) {
                    $dist[$oid] = isset($dist[$oid]) ? $dist[$oid] + 1 : 1;
                }
            }
            $r['distractors'] = array();
            foreach ($this->CI->db->get_where('ha_question_option', array('question_id' => $r['id']))->result_array() as $op) {
                $r['distractors'][] = array('body' => $op['body_en'], 'correct' => (int) $op['is_correct'], 'chosen' => isset($dist[$op['id']]) ? $dist[$op['id']] : 0);
            }
        }
        return $rows;
    }
}
