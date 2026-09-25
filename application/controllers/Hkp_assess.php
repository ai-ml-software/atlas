<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Hkp_Controller.php';

/**
 * Assessment: theory delivery for learners, practical assessment for
 * supervisors (ppt-features 16, 17, 59, 104, 110: "Who needs my attention?").
 *
 *   /hkp/assess                     my assessments
 *   /hkp/assess/theory/{id}         start or resume, answer, submit
 *   /hkp/assess/result/{attempt}    result with feedback and explanations
 *   /hkp/assess/grading[/{attempt}] manual grading of essays / short answers
 *   /hkp/assess/queue               assessor dashboard
 *   /hkp/assess/practical/{id}      rubric scoring: save draft, resume, submit
 */
class Hkp_assess extends Hkp_Controller {

    public function index() {
        $this->need('assessments.view');
        $rows = $this->db->select('at.*, a.title_en, a.title_ar, a.pass_percentage')->from('ha_assessment_attempt at')
            ->join('ha_assessment a', 'a.id = at.assessment_id')->where('at.user_id', $this->uid)->order_by('at.id', 'DESC')->limit(100)->get()->result_array();
        $practicals = $this->db->select('pa.*, r.title_en, r.title_ar')->from('ha_practical_assessment pa')->join('ha_rubric r', 'r.id = pa.rubric_id')
            ->where(array('pa.user_id' => $this->uid, 'pa.status' => 'submitted'))->order_by('pa.submitted_at', 'DESC')->get()->result_array();
        $this->render('assess_mine', array('rows' => $rows, 'practicals' => $practicals), hkp_t('My assessments'), 'assess');
    }

    public function theory($id = 0) {
        $this->need('assessments.view');
        $this->load->library('ha_theory');
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $attempt = (int) $this->input->post('attempt_id');
            $answers = array();
            foreach ((array) $this->input->post('q') as $qid => $v) {
                $answers[(int) $qid] = $v;
            }
            foreach ((array) $this->input->post('order') as $qid => $csv) {
                $answers[(int) $qid] = array_filter(array_map('intval', explode(',', (string) $csv)));
            }
            $this->attempt(function () use ($attempt, $answers) { return $this->ha_theory->submit($attempt, $answers, $this->uid); },
                hkp_t('Assessment submitted.'), function ($r) { return hkp_url('assess/result/' . $r['id']); });
            return;
        }
        try {
            $attempt = $this->ha_theory->start((int) $id, $this->uid);
        } catch (RuntimeException $e) {
            $this->back($e->getMessage(), false, hkp_url('assess'));
            return;
        }
        $paper = $this->ha_theory->paper($attempt, $this->uid);
        $this->render('assess_theory', array('paper' => $paper), hkp_pick($paper['assessment'], 'title'), 'assess');
    }

    public function result($attempt = 0) {
        $this->need('assessments.view');
        $this->load->library('ha_theory');
        $paper = $this->ha_theory->paper((int) $attempt, $this->uid);
        if (!$paper || $paper['attempt']['status'] === 'in_progress') {
            show_404();
        }
        $this->render('assess_result', array('paper' => $paper), hkp_t('Result'), 'assess');
    }

    public function grading($attempt = 0) {
        $this->need('assessments.grade');
        $this->load->library('ha_theory');
        if ($attempt && $this->input->method() === 'post') {
            $this->post_guard();
            $marks = array();
            foreach ((array) $this->input->post('marks') as $qid => $m) {
                $marks[(int) $qid] = array($m, $this->input->post('comment')[$qid] ?? null);
            }
            $this->attempt(function () use ($attempt, $marks) { $this->ha_theory->grade_manual((int) $attempt, $marks, $this->uid); }, hkp_t('Grades saved.'), hkp_url('assess/grading'));
            return;
        }
        if ($attempt) {
            $at = $this->db->get_where('ha_assessment_attempt', array('id' => (int) $attempt))->row_array();
            if (!$at || !$this->ha_auth->can_user($at['user_id'])) {
                show_404();
            }
            $this->render('assess_grade', array('paper' => $this->ha_theory->paper((int) $attempt, $this->uid)), hkp_t('Grade attempt'), 'assessor');
            return;
        }
        $ids = $this->visible_users() ?: array(0);
        $rows = $this->db->select('at.*, a.title_en, a.title_ar, u.first_name, u.last_name')->from('ha_assessment_attempt at')->join('ha_assessment a', 'a.id = at.assessment_id')
            ->join('users u', 'u.id = at.user_id')->where('at.status', 'submitted')->where('at.requires_manual_grading', 1)->where_in('at.user_id', $ids)->get()->result_array();
        $this->render('assess_grading', array('rows' => $rows), hkp_t('Grading'), 'assessor');
    }

    // ------------------------------------------------------------ practical

    public function queue() {
        $this->need('practicals.assess');
        $this->load->library(array('ha_practical', 'ha_certification'));
        $ids = $this->team_ids();
        $q = $this->ha_practical->queue($this->uid, $ids);
        $rubrics = $this->db->order_by('title_en')->get_where('ha_rubric', array('status' => 'published'))->result_array();
        $people = $ids ? $this->db->select('u.id, u.first_name, u.last_name, j.title_en, j.title_ar')->from('users u')->join('ha_profile p', 'p.user_id = u.id')
            ->join('ha_job_role j', 'j.id = p.job_role_id', 'left')->where_in('u.id', $ids)->where('p.status', 'active')->order_by('u.first_name')->get()->result_array() : array();
        $failed = $ids ? $this->db->select('pa.*, r.title_en, r.title_ar, u.first_name, u.last_name')->from('ha_practical_assessment pa')->join('ha_rubric r', 'r.id = pa.rubric_id')
            ->join('users u', 'u.id = pa.user_id')->where_in('pa.user_id', $ids)->where('pa.status', 'submitted')->where_in('pa.outcome', array('not_demonstrated', 'developing'))
            ->order_by('pa.submitted_at', 'DESC')->limit(30)->get()->result_array() : array();
        $expiring = $ids ? $this->db->select('c.*, u.first_name, u.last_name')->from('ha_certificate c')->join('users u', 'u.id = c.user_id')->where_in('c.user_id', $ids)
            ->where('c.status', 'issued')->where('c.expires_at <=', date('Y-m-d H:i:s', strtotime('+60 days')))->get()->result_array() : array();
        $evidence = $ids ? $this->db->select('ap.*, u.first_name, u.last_name')->from('ha_action_plan ap')->join('users u', 'u.id = ap.user_id')
            ->where_in('ap.user_id', $ids)->where_in('ap.status', array('submitted', 'under_review'))->get()->result_array() : array();
        $this->load->library('ha_readiness');
        $this->render('assess_queue', $q + array('rubrics' => $rubrics, 'people' => $people, 'failed' => $failed, 'expiring' => $expiring,
            'evidence' => $evidence, 'readiness' => $this->ha_readiness->summary($ids, 'department'),
            'depts' => $this->db->get('ha_department')->result_array()), hkp_t('Assessor queue'), 'assessor');
    }

    public function practical_start() {
        $this->need('practicals.assess');
        $this->post_guard();
        $this->load->library('ha_practical');
        $this->attempt(function () {
            return $this->ha_practical->start((int) $this->input->post('rubric_id'), (int) $this->input->post('user_id'), $this->uid,
                $this->input->post('reassessment_id') ? (int) $this->input->post('reassessment_id') : null);
        }, hkp_t('Assessment started. Your ratings save as a draft until you submit.'), function ($id) { return hkp_url('assess/practical/' . $id); });
    }

    public function practical($id = 0) {
        $this->load->library(array('ha_practical', 'ha_files'));
        $pa = $this->ha_practical->get($id);
        if (!$pa || !$this->ha_auth->can_user($pa['user_id'])) {
            show_404();
        }
        if ((int) $pa['user_id'] !== $this->uid) {
            $this->need(array('practicals.assess', 'practicals.view'));   // the employee may always read their own result
        }
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $this->need('practicals.assess');
            $scores = array();
            foreach ((array) $this->input->post('rating') as $cid => $r) {
                $scores[(int) $cid] = array('rating' => $r, 'comment' => $this->input->post('comment')[$cid] ?? null);
            }
            $action = $this->input->post('do');
            $this->attempt(function () use ($id, $scores, $action, $pa) {
                if ($pa['status'] === 'draft') {
                    $this->ha_practical->save((int) $id, $scores, $this->input->post('comments'));
                }
                if (!empty($_FILES['evidence']['name'])) {
                    $f = $this->ha_files->store_upload($_FILES['evidence'], array('organization_id' => $pa['organization_id'], 'property_id' => $pa['property_id'],
                        'owner_user_id' => $this->uid, 'entity_type' => 'evidence'));
                    $this->db->insert('ha_evidence', array('user_id' => $pa['user_id'], 'skill_id' => $pa['rubric']['skill_id'], 'practical_id' => (int) $id,
                        'evidence_type' => strpos($f['mime_type'], 'image/') === 0 ? 'photo' : (strpos($f['mime_type'], 'video/') === 0 ? 'video' : 'document'),
                        'title' => $f['original_name'], 'file_id' => $f['id'], 'uploaded_by' => $this->uid,
                        'organization_id' => $pa['organization_id'], 'property_id' => $pa['property_id'], 'created_at' => date('Y-m-d H:i:s')));
                    $this->ha_files->attach($f['id'], 'evidence', $this->db->insert_id());
                }
                if ($action === 'submit') {
                    return $this->ha_practical->submit((int) $id);
                }
                return null;
            }, $action === 'submit' ? hkp_t('Practical assessment submitted. Competency, gaps and readiness are updated.') : hkp_t('Draft saved.'),
                hkp_url('assess/practical/' . (int) $id));
            return;
        }
        $fractions = array();
        foreach (Ha_practical::$ratings as $r) {
            $fractions[$r] = (float) $this->ha_tenant->get('practical.fraction.' . $r, $pa['property_id']);
        }
        $pa['evidence'] = $this->db->select('e.*, f.token, f.original_name')->from('ha_evidence e')->join('ha_file f', 'f.id = e.file_id', 'left')
            ->where('e.practical_id', (int) $id)->get()->result_array();
        $history = $this->db->select('pa.id, pa.attempt_no, pa.outcome, pa.weighted_score, pa.submitted_at')->where(array('rubric_id' => $pa['rubric_id'], 'user_id' => $pa['user_id'], 'status' => 'submitted'))
            ->order_by('attempt_no')->get('ha_practical_assessment pa')->result_array();
        $this->render('assess_practical', array('pa' => $pa, 'x' => $this->ha_practical->explain($pa), 'fractions' => $fractions, 'history' => $history,
            'editable' => $pa['status'] === 'draft' && $this->can('practicals.assess')), hkp_pick($pa['rubric'], 'title'), 'assessor');
    }

    public function reassessment($id = 0) {
        $this->post_guard();
        $this->load->library('ha_action_plans');
        $approve = $this->input->post('decision') === 'approve';
        $this->attempt(function () use ($id, $approve) { $this->ha_action_plans->decide_reassessment((int) $id, $approve, $this->uid, $this->input->post('note')); },
            $approve ? hkp_t('Reassessment approved.') : hkp_t('Reassessment declined.'));
    }
}
