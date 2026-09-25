<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Practical competency assessment (ppt-features 17, 59, 104, 109, 110, 169).
 *
 * A supervisor observes real work against a weighted rubric. Each criterion is
 * rated Not demonstrated / Developing / Competent / Exceeds standard; the
 * rating earns a configurable share of the criterion's weight, and the weighted
 * percentage is placed against the rubric's own thresholds. A critical
 * criterion rated below Competent caps the outcome at Developing, because a
 * check-in that skips identity verification is not "mostly competent".
 *
 * Every step of the calculation is returned by explain() so the result is never
 * a black box (section 179), and a submitted assessment is never edited: a
 * reassessment is a new attempt that links back to the plan that required it.
 */
class Ha_practical {

    protected $CI;

    public static $ratings = array('not_demonstrated', 'developing', 'competent', 'exceeds');

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_tenant', 'ha_audit', 'ha_notify', 'ha_competency'));
    }

    public function rubric($id) {
        $r = $this->CI->db->get_where('ha_rubric', array('id' => (int) $id))->row_array();
        if (!$r) {
            return null;
        }
        $r['criteria'] = $this->CI->db->order_by('sort_order')->get_where('ha_rubric_criterion', array('rubric_id' => (int) $id))->result_array();
        return $r;
    }

    public function get($id) {
        $pa = $this->CI->db->select('pa.*, u.first_name, u.last_name, a.first_name AS assessor_first, a.last_name AS assessor_last')
            ->from('ha_practical_assessment pa')->join('users u', 'u.id = pa.user_id')->join('users a', 'a.id = pa.assessor_user_id', 'left')
            ->where('pa.id', (int) $id)->get()->row_array();
        if (!$pa) {
            return null;
        }
        $pa['rubric'] = $this->rubric($pa['rubric_id']);
        $scores = $this->CI->db->get_where('ha_practical_score', array('practical_id' => (int) $id))->result_array();
        $pa['scores'] = array();
        foreach ($scores as $s) {
            $pa['scores'][(int) $s['criterion_id']] = $s;
        }
        $pa['evidence'] = $this->CI->db->order_by('id')->get_where('ha_evidence', array('practical_id' => (int) $id))->result_array();
        return $pa;
    }

    /** The assessor must be allowed to assess and must be able to see the employee. */
    protected function guard($employee_id, $assessor_id) {
        if (!$this->CI->ha_auth->has('practicals.assess')) {
            throw new RuntimeException('You do not have permission to conduct practical assessments.');
        }
        if ((int) $employee_id === (int) $assessor_id && !$this->CI->ha_auth->is_super_admin()) {
            throw new RuntimeException('An employee cannot assess their own practical competency.');
        }
        if (!$this->CI->ha_auth->can_user($employee_id)) {
            throw new RuntimeException('That employee is outside your team.');
        }
    }

    /** Starts (or resumes) a draft. Resuming returns the existing draft rather than opening a second one. */
    public function start($rubric_id, $employee_id, $assessor_id, $reassessment_id = null) {
        $this->guard($employee_id, $assessor_id);
        $rubric = $this->rubric($rubric_id);
        if (!$rubric || $rubric['status'] !== 'published') {
            throw new InvalidArgumentException('That rubric is not published.');
        }
        if (!$rubric['criteria']) {
            throw new InvalidArgumentException('That rubric has no criteria.');
        }
        $draft = $this->CI->db->get_where('ha_practical_assessment', array('rubric_id' => (int) $rubric_id,
            'user_id' => (int) $employee_id, 'assessor_user_id' => (int) $assessor_id, 'status' => 'draft'))->row_array();
        if ($draft) {
            return (int) $draft['id'];
        }
        if ($reassessment_id) {
            $re = $this->CI->db->get_where('ha_reassessment', array('id' => (int) $reassessment_id))->row_array();
            if (!$re || (int) $re['user_id'] !== (int) $employee_id || $re['status'] !== 'approved') {
                throw new InvalidArgumentException('That reassessment has not been approved for this employee.');
            }
        }
        $attempt = 1 + (int) $this->CI->db->where(array('rubric_id' => (int) $rubric_id, 'user_id' => (int) $employee_id))
            ->where('status !=', 'voided')->count_all_results('ha_practical_assessment');
        $p = $this->CI->ha_competency->profile_row($employee_id);
        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_practical_assessment', array(
            'rubric_id' => (int) $rubric_id, 'rubric_version' => (int) $rubric['version_no'],
            'user_id' => (int) $employee_id, 'assessor_user_id' => (int) $assessor_id,
            'organization_id' => $p ? $p['organization_id'] : null, 'property_id' => $p ? $p['property_id'] : null,
            'department_id' => $p ? $p['department_id'] : null, 'attempt_no' => $attempt, 'status' => 'draft',
            'reassessment_id' => $reassessment_id ? (int) $reassessment_id : null,
            'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ));
        return (int) $this->CI->db->insert_id();
    }

    /** Saves ratings and comments on a draft. Safe to call repeatedly (save draft / resume). */
    public function save($practical_id, array $scores, $comments = null) {
        $pa = $this->get($practical_id);
        if (!$pa) {
            throw new InvalidArgumentException('Assessment not found.');
        }
        if ($pa['status'] !== 'draft') {
            throw new RuntimeException('A submitted assessment cannot be changed. Start a reassessment instead.');
        }
        $this->guard($pa['user_id'], $this->CI->ha_auth->id() ?: $pa['assessor_user_id']);
        $valid = array();
        foreach ($pa['rubric']['criteria'] as $c) {
            $valid[(int) $c['id']] = $c;
        }
        foreach ($scores as $criterion_id => $s) {
            $criterion_id = (int) $criterion_id;
            if (!isset($valid[$criterion_id])) {
                continue;
            }
            $rating = isset($s['rating']) && in_array($s['rating'], self::$ratings, true) ? $s['rating'] : null;
            $row = array('rating' => $rating, 'points' => $rating ? $this->points($valid[$criterion_id], $rating, $pa['property_id']) : 0,
                'comment' => isset($s['comment']) ? mb_substr(trim((string) $s['comment']), 0, 500) : null);
            $exists = $this->CI->db->get_where('ha_practical_score', array('practical_id' => (int) $practical_id, 'criterion_id' => $criterion_id))->row_array();
            if ($exists) {
                $this->CI->db->where('id', $exists['id'])->update('ha_practical_score', $row);
            } else {
                $this->CI->db->insert('ha_practical_score', $row + array('practical_id' => (int) $practical_id, 'criterion_id' => $criterion_id));
            }
        }
        $upd = array('updated_at' => date('Y-m-d H:i:s'));
        if ($comments !== null) {
            $upd['comments'] = mb_substr((string) $comments, 0, 5000);
        }
        $this->CI->db->where('id', (int) $practical_id)->update('ha_practical_assessment', $upd);
        return $this->get($practical_id);
    }

    public function points(array $criterion, $rating, $property_id = null) {
        return round((float) $criterion['weight'] * (float) $this->CI->ha_tenant->get('practical.fraction.' . $rating, $property_id), 2);
    }

    /**
     * The whole calculation, step by step: weights, ratings, points earned,
     * thresholds, the critical-criterion rule and the resulting outcome/level.
     */
    public function explain(array $pa) {
        $rubric = $pa['rubric'];
        $total = 0;
        $earned = 0;
        $rows = array();
        $critical_failed = false;
        $unrated = 0;
        foreach ($rubric['criteria'] as $c) {
            $s = isset($pa['scores'][(int) $c['id']]) ? $pa['scores'][(int) $c['id']] : null;
            $rating = $s ? $s['rating'] : null;
            $total += (float) $c['weight'];
            $pts = $rating ? $this->points($c, $rating, $pa['property_id']) : 0;
            $earned += $pts;
            if (!$rating) {
                $unrated++;
            }
            if ((int) $c['is_critical'] && in_array($rating, array('not_demonstrated', 'developing'), true)) {
                $critical_failed = true;
            }
            $rows[] = array('criterion' => hkp_pick($c, 'label'), 'weight' => (float) $c['weight'], 'critical' => (int) $c['is_critical'],
                'rating' => $rating, 'points' => $pts, 'comment' => $s ? $s['comment'] : null);
        }
        $pct = $total > 0 ? round(100 * $earned / $total, 2) : 0;
        if ($pct >= (float) $rubric['exceeds_threshold']) {
            $outcome = 'exceeds';
        } elseif ($pct >= (float) $rubric['pass_threshold']) {
            $outcome = 'competent';
        } elseif ($pct >= (float) $rubric['developing_threshold']) {
            $outcome = 'developing';
        } else {
            $outcome = 'not_demonstrated';
        }
        $capped = false;
        if ($critical_failed && in_array($outcome, array('competent', 'exceeds'), true)) {
            $outcome = 'developing';
            $capped = true;
        }
        $level = (int) $this->CI->ha_tenant->get('practical.level.' . $outcome, $pa['property_id']);
        return array(
            'rows' => $rows, 'total_weight' => $total, 'earned' => round($earned, 2), 'percentage' => $pct,
            'thresholds' => array('developing' => (float) $rubric['developing_threshold'], 'competent' => (float) $rubric['pass_threshold'], 'exceeds' => (float) $rubric['exceeds_threshold']),
            'fractions' => array_combine(self::$ratings, array_map(function ($r) use ($pa) {
                return (float) $this->CI->ha_tenant->get('practical.fraction.' . $r, $pa['property_id']);
            }, self::$ratings)),
            'critical_failed' => $critical_failed, 'capped' => $capped, 'unrated' => $unrated,
            'outcome' => $outcome, 'level' => $level,
        );
    }

    /**
     * Submits a draft: fixes the score, records the competency result (as a
     * practical, or as a reassessment when one was approved), stores the score
     * as evidence and closes the reassessment it answers.
     */
    public function submit($practical_id) {
        $pa = $this->get($practical_id);
        if (!$pa || $pa['status'] !== 'draft') {
            throw new RuntimeException('Only a draft assessment can be submitted.');
        }
        $this->guard($pa['user_id'], $this->CI->ha_auth->id() ?: $pa['assessor_user_id']);
        $x = $this->explain($pa);
        if ($x['unrated'] > 0) {
            throw new InvalidArgumentException('Rate every criterion before submitting (' . $x['unrated'] . ' left).');
        }
        $now = date('Y-m-d H:i:s');
        $this->CI->db->trans_start();
        $this->CI->db->where('id', (int) $practical_id)->update('ha_practical_assessment', array(
            'status' => 'submitted', 'weighted_score' => $x['percentage'], 'outcome' => $x['outcome'],
            'resulting_level' => $x['level'], 'critical_failed' => $x['critical_failed'] ? 1 : 0,
            'submitted_at' => $now, 'updated_at' => $now));
        $this->CI->db->insert('ha_evidence', array(
            'user_id' => (int) $pa['user_id'], 'skill_id' => $pa['rubric']['skill_id'], 'practical_id' => (int) $practical_id,
            'evidence_type' => 'practical_score', 'title' => hkp_pick($pa['rubric'], 'title') . ' — ' . $x['percentage'] . '%',
            'description' => $pa['comments'], 'uploaded_by' => (int) $pa['assessor_user_id'],
            'organization_id' => $pa['organization_id'], 'property_id' => $pa['property_id'], 'created_at' => $now));
        $evidence_id = (int) $this->CI->db->insert_id();
        $this->CI->db->trans_complete();

        if ($pa['rubric']['skill_id']) {
            $this->CI->ha_competency->record_result($pa['user_id'], $pa['rubric']['skill_id'], $x['level'],
                $pa['reassessment_id'] ? 'reassessment' : 'practical', array(
                    'source_id' => (int) $practical_id, 'assessor_id' => (int) $pa['assessor_user_id'], 'evidence_id' => $evidence_id,
                    'notes' => 'Practical: ' . $pa['rubric']['code'] . ' attempt ' . $pa['attempt_no'] . ' = ' . $x['outcome'] . ' (' . $x['percentage'] . '%)'));
        }
        if ($pa['reassessment_id']) {
            $this->CI->db->where('id', (int) $pa['reassessment_id'])->update('ha_reassessment', array(
                'status' => 'completed', 'practical_id' => (int) $practical_id, 'result_level' => $x['level'], 'updated_at' => $now));
        }
        $this->CI->ha_audit->log('complete', 'practical_assessment', (int) $practical_id, array(
            'description' => 'Practical ' . $pa['rubric']['code'] . ' for user ' . $pa['user_id'] . ': ' . $x['outcome'] . ' ' . $x['percentage'] . '%',
            'after' => array('outcome' => $x['outcome'], 'score' => $x['percentage'], 'level' => $x['level']),
            'property_id' => $pa['property_id'], 'organization_id' => $pa['organization_id']));
        $skill = $pa['rubric']['skill_id'] ? $this->CI->db->get_where('ha_skill', array('id' => $pa['rubric']['skill_id']))->row_array() : null;
        $this->CI->ha_notify->send($pa['user_id'], 'practical.result', array(
            'competency_name' => $skill ? $skill['name_en'] : $pa['rubric']['title_en'], 'outcome' => str_replace('_', ' ', $x['outcome']),
            'url' => hkp_url('competencies'), 'related_type' => 'practical', 'related_id' => (int) $practical_id, '_no_manager' => 1));

        $this->CI->load->library('ha_readiness');
        $this->CI->ha_readiness->calculate($pa['user_id']);
        return $x + array('id' => (int) $practical_id);
    }

    public function void($practical_id, $reason) {
        if (!$this->CI->ha_auth->has('practicals.void')) {
            throw new RuntimeException('You do not have permission to void assessments.');
        }
        $pa = $this->get($practical_id);
        if (!$pa || $pa['status'] !== 'draft') {
            throw new RuntimeException('Only a draft can be voided; a submitted result stays on record.');
        }
        $this->CI->db->where('id', (int) $practical_id)->update('ha_practical_assessment', array('status' => 'voided',
            'comments' => trim($pa['comments'] . "\nVoided: " . $reason), 'updated_at' => date('Y-m-d H:i:s')));
    }

    /** Assessor queue: drafts to resume, approved reassessments, required practicals not yet done. */
    public function queue($assessor_id, array $user_ids) {
        $user_ids = $user_ids ?: array(0);
        $drafts = $this->CI->db->select('pa.id, pa.user_id, pa.started_at, r.title_en, r.title_ar, u.first_name, u.last_name')
            ->from('ha_practical_assessment pa')->join('ha_rubric r', 'r.id = pa.rubric_id')->join('users u', 'u.id = pa.user_id')
            ->where(array('pa.assessor_user_id' => (int) $assessor_id, 'pa.status' => 'draft'))->order_by('pa.started_at')->get()->result_array();
        $reassess = $this->CI->db->select('re.*, s.name_en, s.name_ar, u.first_name, u.last_name')
            ->from('ha_reassessment re')->join('users u', 'u.id = re.user_id')->join('ha_skill s', 's.id = re.skill_id', 'left')
            ->where_in('re.user_id', $user_ids)->where_in('re.status', array('requested', 'approved'))->order_by('re.created_at')->get()->result_array();
        // Employees whose role requires a practically-assessed competency they have no practical result for.
        $awaiting = $this->CI->db->query("
            SELECT p.user_id, u.first_name, u.last_name, s.id AS skill_id, s.name_en, s.name_ar, r.id AS rubric_id, r.title_en AS rubric_en, r.title_ar AS rubric_ar
            FROM ha_profile p
            JOIN users u ON u.id = p.user_id
            JOIN ha_role_competency rc ON rc.job_role_id = p.job_role_id AND rc.property_key IN (0, IFNULL(p.property_id, 0))
            JOIN ha_skill s ON s.id = rc.skill_id AND s.assessment_method IN ('practical','theory_practical','observation')
            JOIN ha_rubric r ON r.skill_id = s.id AND r.status = 'published'
            LEFT JOIN ha_practical_assessment pa ON pa.user_id = p.user_id AND pa.rubric_id = r.id AND pa.status = 'submitted'
            WHERE p.user_id IN (" . implode(',', array_map('intval', $user_ids)) . ") AND p.status = 'active' AND pa.id IS NULL
            GROUP BY p.user_id, u.first_name, u.last_name, s.id, s.name_en, s.name_ar, r.id, r.title_en, r.title_ar
            ORDER BY u.first_name
            LIMIT 200")->result_array();
        return array('drafts' => $drafts, 'reassessments' => $reassess, 'awaiting' => $awaiting);
    }
}
