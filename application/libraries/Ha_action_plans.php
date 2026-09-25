<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Corrective action plans and reassessment (ppt-features 20, 21, 108, 109, 110).
 *
 *   Gap -> Action plan -> Training / practice -> Evidence -> Supervisor review
 *       -> Reassessment -> Competency updated
 *
 * Status moves only along the transitions below, each one recorded in
 * ha_action_plan_event with the actor and a note, so a plan's history can be
 * read back step by step. The employee may start and submit; reviewing,
 * completing and rejecting belong to someone holding action_plans.review who
 * can see that employee.
 */
class Ha_action_plans {

    protected $CI;

    public static $types = array('training', 'coaching', 'shadowing', 'practice', 'sop_review', 'knowledge_assessment', 'practical_exercise', 'observation', 'mentoring');

    /** from => array(to => who) where who is 'employee' or 'reviewer' or 'system' */
    public static function transitions() {
        return array(
            'open'         => array('assigned' => 'reviewer', 'in_progress' => 'employee', 'overdue' => 'system'),
            'assigned'     => array('in_progress' => 'employee', 'submitted' => 'employee', 'overdue' => 'system'),
            'in_progress'  => array('submitted' => 'employee', 'overdue' => 'system'),
            'overdue'      => array('in_progress' => 'employee', 'submitted' => 'employee'),
            'submitted'    => array('under_review' => 'reviewer', 'completed' => 'reviewer', 'rejected' => 'reviewer'),
            'under_review' => array('completed' => 'reviewer', 'rejected' => 'reviewer'),
            'rejected'     => array('in_progress' => 'employee', 'submitted' => 'employee'),
            'completed'    => array(),
        );
    }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_audit', 'ha_notify', 'ha_competency'));
    }

    public function get($id) {
        $ap = $this->CI->db->select('ap.*, u.first_name, u.last_name, s.name_en AS skill_en, s.name_ar AS skill_ar, m.first_name AS mgr_first, m.last_name AS mgr_last')
            ->from('ha_action_plan ap')->join('users u', 'u.id = ap.user_id')
            ->join('ha_skill s', 's.id = ap.skill_id', 'left')->join('users m', 'm.id = ap.assigned_manager_id', 'left')
            ->where('ap.id', (int) $id)->get()->row_array();
        if (!$ap) {
            return null;
        }
        $ap['events'] = $this->CI->db->select('e.*, u.first_name, u.last_name')->from('ha_action_plan_event e')
            ->join('users u', 'u.id = e.actor_user_id', 'left')->where('e.action_plan_id', (int) $id)->order_by('e.id')->get()->result_array();
        $ap['evidence'] = $this->CI->db->select('ev.*, f.token, f.original_name')->from('ha_evidence ev')
            ->join('ha_file f', 'f.id = ev.file_id', 'left')->where('ev.action_plan_id', (int) $id)->order_by('ev.id')->get()->result_array();
        $ap['gap'] = $ap['gap_id'] ? $this->CI->db->get_where('ha_competency_gap', array('id' => $ap['gap_id']))->row_array() : null;
        $ap['reassessment'] = $this->CI->db->order_by('id', 'DESC')->get_where('ha_reassessment', array('action_plan_id' => (int) $id))->row_array();
        return $ap;
    }

    /**
     * Creates a plan for a gap (or a standalone plan when gap_id is empty).
     * $data: title, action_type, required_action, due_at, priority, linked_type, linked_id, user_id (standalone)
     */
    public function create(array $data, $actor_id) {
        if (!$this->CI->ha_auth->has('action_plans.create') && !$this->CI->ha_auth->has('action_plans.assign')) {
            throw new RuntimeException('You do not have permission to create action plans.');
        }
        $gap = null;
        if (!empty($data['gap_id'])) {
            $gap = $this->CI->db->get_where('ha_competency_gap', array('id' => (int) $data['gap_id']))->row_array();
            if (!$gap) {
                throw new InvalidArgumentException('Gap not found.');
            }
            $user_id = (int) $gap['user_id'];
        } else {
            $user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        }
        if (!$user_id || !$this->CI->ha_auth->can_user($user_id)) {
            throw new RuntimeException('That employee is outside your team.');
        }
        $title = trim((string) (isset($data['title']) ? $data['title'] : ''));
        if ($title === '') {
            throw new InvalidArgumentException('Give the action plan a title.');
        }
        $type = isset($data['action_type']) && in_array($data['action_type'], self::$types, true) ? $data['action_type'] : 'training';
        $due = !empty($data['due_at']) && strtotime($data['due_at']) ? date('Y-m-d', strtotime($data['due_at'])) : date('Y-m-d', strtotime('+14 days'));
        $linked_type = isset($data['linked_type']) && in_array($data['linked_type'], array('none', 'course', 'sop', 'assessment', 'rubric'), true) ? $data['linked_type'] : 'none';
        $p = $this->CI->ha_competency->profile_row($user_id);
        $now = date('Y-m-d H:i:s');
        $this->CI->db->trans_start();
        $this->CI->db->insert('ha_action_plan', array(
            'gap_id' => $gap ? (int) $gap['id'] : null, 'finding_id' => isset($data['finding_id']) ? (int) $data['finding_id'] : null,
            'user_id' => $user_id, 'skill_id' => $gap ? (int) $gap['skill_id'] : (isset($data['skill_id']) ? (int) $data['skill_id'] : null),
            'organization_id' => $p ? $p['organization_id'] : null, 'property_id' => $p ? $p['property_id'] : null, 'department_id' => $p ? $p['department_id'] : null,
            'title' => mb_substr($title, 0, 190), 'action_type' => $type,
            'required_action' => isset($data['required_action']) ? trim((string) $data['required_action']) : null,
            'linked_type' => $linked_type, 'linked_id' => $linked_type !== 'none' && !empty($data['linked_id']) ? (int) $data['linked_id'] : null,
            'assigned_manager_id' => (int) $actor_id, 'created_by' => (int) $actor_id,
            'priority' => isset($data['priority']) && in_array($data['priority'], array('low', 'medium', 'high', 'critical'), true) ? $data['priority'] : ($gap && $gap['severity'] === 'critical' ? 'critical' : 'medium'),
            'status' => 'assigned', 'due_at' => $due, 'created_at' => $now, 'updated_at' => $now,
        ));
        $id = (int) $this->CI->db->insert_id();
        $this->event($id, $actor_id, null, 'assigned', 'Plan created');
        if ($gap) {
            $this->CI->db->where('id', $gap['id'])->update('ha_competency_gap', array('status' => 'in_action', 'updated_at' => $now));
        }
        $this->CI->db->trans_complete();
        $this->CI->ha_audit->log('assign', 'action_plan', $id, array('description' => 'Action plan "' . $title . '" for user ' . $user_id,
            'property_id' => $p ? $p['property_id'] : null, 'organization_id' => $p ? $p['organization_id'] : null));
        $this->CI->ha_notify->send($user_id, 'action.assigned', array('action_title' => $title, 'due_date' => $due,
            'url' => hkp_url('actions/view/' . $id), 'related_type' => 'action_plan', 'related_id' => $id, '_no_manager' => 1));
        return $id;
    }

    protected function event($id, $actor_id, $from, $to, $note = null) {
        $this->CI->db->insert('ha_action_plan_event', array('action_plan_id' => (int) $id, 'actor_user_id' => $actor_id ? (int) $actor_id : null,
            'from_status' => $from, 'to_status' => $to, 'note' => $note ? mb_substr($note, 0, 500) : null, 'created_at' => date('Y-m-d H:i:s')));
    }

    /** Can this actor move the plan to $to? Returns '' when allowed, otherwise the reason. */
    public function why_not(array $ap, $to, $actor_id) {
        $map = self::transitions();
        if (!isset($map[$ap['status']][$to])) {
            return 'A plan that is ' . str_replace('_', ' ', $ap['status']) . ' cannot move to ' . str_replace('_', ' ', $to) . '.';
        }
        $who = $map[$ap['status']][$to];
        if ($who === 'system') {
            return $actor_id === null ? '' : 'Only the scheduler marks plans overdue.';
        }
        if ($who === 'employee') {
            $own = (int) $ap['user_id'] === (int) $actor_id;
            if ($own || ($this->CI->ha_auth->has('action_plans.update') && $this->CI->ha_auth->can_user($ap['user_id']))) {
                return '';
            }
            return 'Only the employee (or their manager) can do that.';
        }
        if ((int) $ap['user_id'] === (int) $actor_id && !$this->CI->ha_auth->is_super_admin()) {
            return 'You cannot review your own action plan.';
        }
        if (!$this->CI->ha_auth->has('action_plans.review') || !$this->CI->ha_auth->can_user($ap['user_id'])) {
            return 'You do not have permission to review this action plan.';
        }
        return '';
    }

    public function transition($id, $to, $actor_id, $note = null) {
        $ap = $this->get($id);
        if (!$ap) {
            throw new InvalidArgumentException('Action plan not found.');
        }
        $why = $this->why_not($ap, $to, $actor_id);
        if ($why !== '') {
            throw new RuntimeException($why);
        }
        if ($to === 'submitted' && !$ap['evidence'] && trim((string) $note) === '') {
            throw new InvalidArgumentException('Add evidence or a note describing what you did before submitting.');
        }
        if ($to === 'rejected' && trim((string) $note) === '') {
            throw new InvalidArgumentException('Give the employee a reason when returning a plan.');
        }
        $now = date('Y-m-d H:i:s');
        $upd = array('status' => $to, 'updated_at' => $now);
        if ($to === 'submitted') {
            $upd['submitted_at'] = $now;
        }
        if ($to === 'completed') {
            $upd['completed_at'] = $now;
        }
        if ($to === 'rejected') {
            $upd['rejection_reason'] = mb_substr((string) $note, 0, 500);
        }
        $this->CI->db->where('id', (int) $id)->update('ha_action_plan', $upd);
        $this->event($id, $actor_id, $ap['status'], $to, $note);
        $this->CI->ha_audit->log('update', 'action_plan', (int) $id, array('description' => 'Action plan ' . $ap['status'] . ' -> ' . $to,
            'before' => array('status' => $ap['status']), 'after' => array('status' => $to), 'property_id' => $ap['property_id']));

        $vars = array('action_title' => $ap['title'], 'url' => hkp_url('actions/view/' . (int) $id), 'related_type' => 'action_plan', 'related_id' => (int) $id);
        if ($to === 'submitted' && $ap['assigned_manager_id']) {
            $this->CI->ha_notify->send($ap['assigned_manager_id'], 'action.submitted', $vars + array('employee_name' => trim($ap['first_name'] . ' ' . $ap['last_name']), '_no_manager' => 1));
        }
        if ($to === 'rejected') {
            $this->CI->ha_notify->send($ap['user_id'], 'action.rejected', $vars + array('reason' => (string) $note, '_no_manager' => 1));
        }
        if ($to === 'completed') {
            $this->CI->ha_notify->send($ap['user_id'], 'action.completed', $vars + array('_no_manager' => 1));
            // Completing the work does not close the gap: only reassessment evidence does.
            if ($ap['skill_id']) {
                $this->request_reassessment(array('action_plan_id' => (int) $id), $actor_id, true);
            }
        }
        return $this->get($id);
    }

    /** Adds evidence (file and/or note) to a plan. The employee or a reviewer may add it. */
    public function add_evidence($id, array $data, $actor_id, $upload = null) {
        $ap = $this->get($id);
        if (!$ap) {
            throw new InvalidArgumentException('Action plan not found.');
        }
        if ((int) $ap['user_id'] !== (int) $actor_id && !$this->CI->ha_auth->can_user($ap['user_id'])) {
            throw new RuntimeException('You cannot add evidence to this plan.');
        }
        if ($ap['status'] === 'completed') {
            throw new RuntimeException('The plan is completed; its evidence is closed.');
        }
        $file_id = null;
        if ($upload && isset($upload['error']) && (int) $upload['error'] !== UPLOAD_ERR_NO_FILE) {
            $this->CI->load->library('ha_files');
            $file = $this->CI->ha_files->store_upload($upload, array('organization_id' => $ap['organization_id'], 'property_id' => $ap['property_id'],
                'owner_user_id' => $actor_id, 'entity_type' => 'action_plan', 'entity_id' => (int) $id));
            $file_id = $file['id'];
        }
        $title = trim((string) (isset($data['title']) ? $data['title'] : ''));
        $description = trim((string) (isset($data['description']) ? $data['description'] : ''));
        if (!$file_id && $description === '') {
            throw new InvalidArgumentException('Attach a file or describe the evidence.');
        }
        $type = isset($data['evidence_type']) ? $data['evidence_type'] : ($file_id ? 'document' : 'supervisor_note');
        $allowed = array('document', 'photo', 'video', 'supervisor_note', 'observation', 'external_certificate');
        $this->CI->db->insert('ha_evidence', array(
            'user_id' => (int) $ap['user_id'], 'skill_id' => $ap['skill_id'], 'action_plan_id' => (int) $id,
            'evidence_type' => in_array($type, $allowed, true) ? $type : 'document',
            'title' => mb_substr($title !== '' ? $title : ($file_id ? 'Uploaded evidence' : 'Note'), 0, 190),
            'description' => $description !== '' ? $description : null, 'file_id' => $file_id, 'uploaded_by' => (int) $actor_id,
            'organization_id' => $ap['organization_id'], 'property_id' => $ap['property_id'], 'created_at' => date('Y-m-d H:i:s')));
        $eid = (int) $this->CI->db->insert_id();
        if ($ap['status'] === 'assigned' && (int) $ap['user_id'] === (int) $actor_id) {
            $this->CI->db->where('id', (int) $id)->update('ha_action_plan', array('status' => 'in_progress', 'updated_at' => date('Y-m-d H:i:s')));
            $this->event($id, $actor_id, 'assigned', 'in_progress', 'Evidence added');
        }
        return $eid;
    }

    public function for_users(array $user_ids, array $status = array(), $limit = 300) {
        $user_ids = $user_ids ?: array(0);
        $db = $this->CI->db->select('ap.*, u.first_name, u.last_name, s.name_en AS skill_en, s.name_ar AS skill_ar')
            ->from('ha_action_plan ap')->join('users u', 'u.id = ap.user_id')->join('ha_skill s', 's.id = ap.skill_id', 'left')
            ->where_in('ap.user_id', $user_ids);
        if ($status) {
            $db->where_in('ap.status', $status);
        }
        return $db->order_by("FIELD(ap.status,'overdue','submitted','under_review','assigned','in_progress','open','rejected','completed')", '', false)
            ->order_by('ap.due_at')->limit($limit)->get()->result_array();
    }

    /** Marks every plan past its due date overdue and tells the employee and their manager. */
    public function sweep_overdue() {
        $rows = $this->CI->db->where_in('status', array('open', 'assigned', 'in_progress'))
            ->where('due_at <', date('Y-m-d'))->get('ha_action_plan')->result_array();
        foreach ($rows as $ap) {
            $this->CI->db->where('id', $ap['id'])->update('ha_action_plan', array('status' => 'overdue', 'updated_at' => date('Y-m-d H:i:s')));
            $this->event($ap['id'], null, $ap['status'], 'overdue', 'Past due date ' . $ap['due_at']);
            $this->CI->ha_notify->send($ap['user_id'], 'action.overdue', array('action_title' => $ap['title'], 'due_date' => $ap['due_at'],
                'url' => hkp_url('actions/view/' . $ap['id']), 'related_type' => 'action_plan', 'related_id' => $ap['id']));
        }
        return count($rows);
    }

    // --------------------------------------------------------- reassessment

    /**
     * Requests a reassessment. From a completed plan the request is opened by the
     * system on the reviewer's behalf; an employee may also ask for one.
     */
    public function request_reassessment(array $data, $actor_id, $auto_approve = false) {
        $ap = !empty($data['action_plan_id']) ? $this->CI->db->get_where('ha_action_plan', array('id' => (int) $data['action_plan_id']))->row_array() : null;
        $gap = !empty($data['gap_id']) ? $this->CI->db->get_where('ha_competency_gap', array('id' => (int) $data['gap_id']))->row_array() : null;
        if (!$ap && !$gap) {
            throw new InvalidArgumentException('A reassessment answers an action plan or a gap.');
        }
        $user_id = $ap ? (int) $ap['user_id'] : (int) $gap['user_id'];
        $skill_id = $ap ? $ap['skill_id'] : $gap['skill_id'];
        if ((int) $actor_id !== $user_id && !$this->CI->ha_auth->can_user($user_id)) {
            throw new RuntimeException('You cannot request a reassessment for that employee.');
        }
        $open = $this->CI->db->where(array('user_id' => $user_id, 'skill_id' => (int) $skill_id))
            ->where_in('status', array('requested', 'approved'))->get('ha_reassessment')->row_array();
        if ($open) {
            return (int) $open['id'];
        }
        $rubric = $skill_id ? $this->CI->db->get_where('ha_rubric', array('skill_id' => (int) $skill_id, 'status' => 'published'))->row_array() : null;
        $now = date('Y-m-d H:i:s');
        $status = $auto_approve ? 'approved' : 'requested';
        $this->CI->db->insert('ha_reassessment', array(
            'action_plan_id' => $ap ? (int) $ap['id'] : null, 'gap_id' => $ap ? $ap['gap_id'] : (int) $gap['id'],
            'user_id' => $user_id, 'skill_id' => $skill_id ? (int) $skill_id : null, 'rubric_id' => $rubric ? (int) $rubric['id'] : null,
            'requested_by' => (int) $actor_id, 'status' => $status, 'approved_by' => $auto_approve ? (int) $actor_id : null,
            'decided_at' => $auto_approve ? $now : null, 'note' => isset($data['note']) ? mb_substr((string) $data['note'], 0, 500) : null,
            'created_at' => $now, 'updated_at' => $now));
        $id = (int) $this->CI->db->insert_id();
        if ($auto_approve) {
            $skill = $skill_id ? $this->CI->db->get_where('ha_skill', array('id' => (int) $skill_id))->row_array() : null;
            $this->CI->ha_notify->send($user_id, 'reassessment.available', array('competency_name' => $skill ? $skill['name_en'] : '',
                'url' => hkp_url('competencies'), 'related_type' => 'reassessment', 'related_id' => $id, '_no_manager' => 1));
        }
        return $id;
    }

    public function decide_reassessment($id, $approve, $actor_id, $note = null) {
        if (!$this->CI->ha_auth->has('reassessments.approve')) {
            throw new RuntimeException('You do not have permission to approve reassessments.');
        }
        $re = $this->CI->db->get_where('ha_reassessment', array('id' => (int) $id))->row_array();
        if (!$re || $re['status'] !== 'requested') {
            throw new InvalidArgumentException('That reassessment is not awaiting a decision.');
        }
        if (!$this->CI->ha_auth->can_user($re['user_id'])) {
            throw new RuntimeException('That employee is outside your team.');
        }
        $this->CI->db->where('id', (int) $id)->update('ha_reassessment', array('status' => $approve ? 'approved' : 'declined',
            'approved_by' => (int) $actor_id, 'decided_at' => date('Y-m-d H:i:s'),
            'note' => $note ? mb_substr($note, 0, 500) : $re['note'], 'updated_at' => date('Y-m-d H:i:s')));
        if ($approve) {
            $skill = $re['skill_id'] ? $this->CI->db->get_where('ha_skill', array('id' => $re['skill_id']))->row_array() : null;
            $this->CI->ha_notify->send($re['user_id'], 'reassessment.available', array('competency_name' => $skill ? $skill['name_en'] : '',
                'url' => hkp_url('competencies'), 'related_type' => 'reassessment', 'related_id' => (int) $id, '_no_manager' => 1));
        }
    }
}
