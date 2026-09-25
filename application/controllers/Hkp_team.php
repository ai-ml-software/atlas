<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Hkp_Controller.php';

/**
 * Property Management experience (ppt-features 4B, 57, 71, 72, 139-141, 172-177:
 * "Where are the operational capability gaps?").
 *
 * Every list here is limited to the people the signed-in manager may see
 * (Ha_auth::visible_user_ids), narrowed to the chosen property. Drill-down goes
 * Property -> Department -> Employee -> Competency -> Assessment -> Evidence.
 */
class Hkp_team extends Hkp_Controller {

    protected function property_id() {
        if ($this->ctx['property_id']) {
            return (int) $this->ctx['property_id'];
        }
        $ids = $this->ha_auth->property_ids();
        return $ids ? (int) $ids[0] : null;
    }

    public function index() {
        $this->need(array('learners.view', 'practicals.assess'));
        $this->load->library(array('ha_kpi', 'ha_readiness', 'ha_competency', 'ha_certification'));
        $ids = $this->team_ids();
        $in = $ids ? implode(',', $ids) : '0';
        $k = $this->ha_kpi->platform_kpis($ids);
        $assigned = (int) $this->db->query("SELECT COUNT(*) n FROM ha_training_recipient WHERE user_id IN ($in)")->row()->n;
        $overdue = (int) $this->db->query("SELECT COUNT(*) n FROM ha_training_recipient WHERE user_id IN ($in) AND status = 'overdue'")->row()->n;
        $open_actions = (int) $this->db->query("SELECT COUNT(*) n FROM ha_action_plan WHERE user_id IN ($in) AND status NOT IN ('completed')")->row()->n;
        $dept = $this->db->query("SELECT d.id, d.name_en, d.name_ar, COUNT(p.user_id) staff,
                ROUND(100 * SUM(rr.status = 'ready') / GREATEST(COUNT(rr.id), 1), 1) ready_pct,
                (SELECT COUNT(*) FROM ha_competency_gap g WHERE g.department_id = d.id AND g.user_id IN ($in) AND g.status IN ('open','in_action') AND g.severity = 'critical') critical,
                (SELECT ROUND(AVG(e.progress_percentage), 1) FROM ha_enrollment e JOIN ha_profile p2 ON p2.user_id = e.user_id WHERE p2.department_id = d.id AND e.user_id IN ($in)) completion
            FROM ha_profile p JOIN ha_department d ON d.id = p.department_id
            LEFT JOIN ha_readiness_record rr ON rr.user_id = p.user_id AND rr.is_current = 1
            WHERE p.user_id IN ($in) AND p.status = 'active' GROUP BY d.id ORDER BY d.name_en")->result_array();
        $alerts = $this->db->where('status', 'open');
        $this->ha_auth->scope_query($alerts, array('organization_id' => 'organization_id', 'property_id' => 'property_id'), true);
        $alerts = $alerts->order_by("FIELD(severity,'critical','warning','info')", '', false)->order_by('created_at', 'DESC')->limit(12)->get('ha_alert')->result_array();
        $pid = $this->property_id();
        $this->render('team_dashboard', array('k' => $k, 'people' => count($ids), 'assigned' => $assigned, 'overdue' => $overdue, 'open_actions' => $open_actions,
            'dept' => $dept, 'alerts' => $alerts, 'readiness' => $this->ha_readiness->summary($ids),
            'gaps' => $this->ha_competency->open_gaps(array('user_ids' => $ids, 'limit' => 8)),
            'cert' => $this->ha_certification->dashboard($ids),
            'property' => $pid ? $this->db->get_where('ha_property', array('id' => $pid))->row_array() : null), hkp_t('Team dashboard'), 'team_home');
    }

    // ----------------------------------------------------------------- people

    public function people() {
        $this->need('learners.view');
        $ids = $this->team_ids();
        $f = array('department_id' => (int) $this->input->get('department'), 'status' => $this->input->get('status'), 'q' => trim((string) $this->input->get('q')));
        $db = $this->db->select("u.id, u.first_name, u.last_name, u.email, p.employee_no, p.status, p.hire_date, j.title_en AS role_en, j.title_ar AS role_ar,
                d.name_en AS dept_en, d.name_ar AS dept_ar, pr.name_en AS prop_en, pr.name_ar AS prop_ar, rr.status AS readiness,
                (SELECT ROUND(AVG(e.progress_percentage)) FROM ha_enrollment e WHERE e.user_id = u.id AND e.status != 'cancelled') AS progress,
                (SELECT COUNT(*) FROM ha_competency_gap g WHERE g.user_id = u.id AND g.status IN ('open','in_action')) AS gaps", false)
            ->from('ha_profile p')->join('users u', 'u.id = p.user_id')->join('ha_job_role j', 'j.id = p.job_role_id', 'left')
            ->join('ha_department d', 'd.id = p.department_id', 'left')->join('ha_property pr', 'pr.id = p.property_id', 'left')
            ->join('ha_readiness_record rr', 'rr.user_id = p.user_id AND rr.is_current = 1', 'left')->where_in('p.user_id', $ids ?: array(0));
        if ($f['department_id']) {
            $db->where('p.department_id', $f['department_id']);
        }
        if ($f['status']) {
            $db->where('p.status', $f['status']);
        }
        if ($f['q'] !== '') {
            $db->group_start()->like('u.first_name', $f['q'])->or_like('u.last_name', $f['q'])->or_like('u.email', $f['q'])->or_like('p.employee_no', $f['q'])->group_end();
        }
        $rows = $db->order_by('u.first_name')->limit(500)->get()->result_array();
        $this->render('team_people', array('rows' => $rows, 'f' => $f, 'depts' => $this->departments()), hkp_t('Team'), 'team');
    }

    protected function departments() {
        $db = $this->db->where('status', 'active');
        $org = $this->ctx['organization_id'];
        if ($org && !$this->ha_auth->is_system_scoped()) {
            $db->where('organization_id', (int) $org);
        }
        return $db->order_by('name_en')->get('ha_department')->result_array();
    }

    /** The auditability chain for one person (section 141). */
    public function employee($id = 0) {
        $this->need('learners.view');
        if (!$this->ha_auth->can_user($id)) {
            show_404();
        }
        $this->load->library(array('ha_competency', 'ha_readiness', 'ha_learning', 'ha_action_plans'));
        $u = $this->db->select('u.id, u.first_name, u.last_name, u.email, p.*, j.title_en AS role_en, j.title_ar AS role_ar, d.name_en AS dept_en, d.name_ar AS dept_ar, pr.name_en AS prop_en, pr.name_ar AS prop_ar')
            ->from('users u')->join('ha_profile p', 'p.user_id = u.id')->join('ha_job_role j', 'j.id = p.job_role_id', 'left')
            ->join('ha_department d', 'd.id = p.department_id', 'left')->join('ha_property pr', 'pr.id = p.property_id', 'left')->where('u.id', (int) $id)->get()->row_array();
        if (!$u) {
            show_404();
        }
        $this->render('team_employee', array('u' => $u, 'plan' => $this->ha_learning->plan($id), 'comp' => $this->ha_competency->profile($id),
            'history' => $this->ha_competency->history($id), 'gaps' => $this->ha_competency->open_gaps(array('user_ids' => array((int) $id), 'status' => array('open', 'in_action', 'closed'))),
            'actions' => $this->ha_action_plans->for_users(array((int) $id)), 'readiness' => $this->ha_readiness->evaluate($id), 'current' => $this->ha_readiness->current($id),
            'attempts' => $this->db->select('at.*, a.title_en, a.title_ar')->from('ha_assessment_attempt at')->join('ha_assessment a', 'a.id = at.assessment_id')->where('at.user_id', (int) $id)->order_by('at.id', 'DESC')->get()->result_array(),
            'practicals' => $this->db->select('pa.*, r.title_en, r.title_ar')->from('ha_practical_assessment pa')->join('ha_rubric r', 'r.id = pa.rubric_id')->where('pa.user_id', (int) $id)->order_by('pa.id', 'DESC')->get()->result_array(),
            'certs' => $this->db->get_where('ha_certificate', array('user_id' => (int) $id))->result_array(),
            'recipients' => $this->db->select('tr.*, ta.title_en, ta.title_ar')->from('ha_training_recipient tr')->join('ha_training_assignment ta', 'ta.id = tr.assignment_id')->where('tr.user_id', (int) $id)->get()->result_array(),
            'rubrics' => $this->db->get_where('ha_rubric', array('status' => 'published'))->result_array(),
            'roles' => $this->db->where('status', 'active')->order_by('title_en')->get('ha_job_role')->result_array(),
            'depts' => $this->departments()), trim($u['first_name'] . ' ' . $u['last_name']), 'team');
    }

    /** Create or update an employee (section 93). Terminating keeps every record. */
    public function person($id = 0) {
        $this->need($id ? 'learners.update' : 'users.create');
        $this->post_guard();
        $d = $this->input->post();
        $this->attempt(function () use ($id, $d) {
            $pid = !empty($d['property_id']) ? (int) $d['property_id'] : $this->property_id();
            if (!$pid || !$this->ha_auth->can_property($pid)) {
                throw new RuntimeException(hkp_t('Choose a property you manage.'));
            }
            $prop = $this->db->get_where('ha_property', array('id' => $pid))->row_array();
            $status = isset($d['status']) && in_array($d['status'], array('active', 'inactive', 'on_leave', 'terminated', 'archived'), true) ? $d['status'] : 'active';
            $profile = array('employee_no' => isset($d['employee_no']) ? mb_substr(trim($d['employee_no']), 0, 60) : null, 'full_name_ar' => isset($d['name_ar']) ? mb_substr(trim($d['name_ar']), 0, 190) : null,
                'property_id' => $pid, 'organization_id' => $prop['organization_id'], 'department_id' => !empty($d['department_id']) ? (int) $d['department_id'] : null,
                'job_role_id' => !empty($d['job_role_id']) ? (int) $d['job_role_id'] : null, 'manager_user_id' => !empty($d['manager_user_id']) ? (int) $d['manager_user_id'] : null,
                'hire_date' => !empty($d['hire_date']) ? date('Y-m-d', strtotime($d['hire_date'])) : null, 'locale' => isset($d['locale']) && $d['locale'] === 'ar' ? 'ar' : 'en',
                'employment_type' => isset($d['employment_type']) && in_array($d['employment_type'], array('full_time', 'part_time', 'contract', 'intern', 'seasonal'), true) ? $d['employment_type'] : 'full_time',
                'status' => $status, 'terminated_at' => $status === 'terminated' ? date('Y-m-d') : null, 'updated_at' => date('Y-m-d H:i:s'));
            if ($profile['job_role_id']) {
                $j = $this->db->get_where('ha_job_role', array('id' => $profile['job_role_id']))->row_array();
                $profile['job_title_en'] = $j['title_en'];
                $profile['job_title_ar'] = $j['title_ar'];
            }
            if ($id) {
                if (!$this->ha_auth->can_user($id)) {
                    throw new RuntimeException(hkp_t('That employee is outside your team.'));
                }
                $before = $this->db->get_where('ha_profile', array('user_id' => (int) $id))->row_array();
                $this->db->where('user_id', (int) $id)->update('ha_profile', $profile);
                $this->db->where('id', (int) $id)->update('users', array('status' => in_array($status, array('terminated', 'archived', 'inactive'), true) ? 0 : 1,
                    'first_name' => trim($d['first_name']), 'last_name' => trim($d['last_name'])));
                $this->ha_audit->log_change('profile', (int) $id, $before, $profile);
                $uid = (int) $id;
            } else {
                $email = strtolower(trim((string) $d['email']));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $this->db->where('email', $email)->count_all_results('users')) {
                    throw new InvalidArgumentException(hkp_t('Enter a new, valid email address.'));
                }
                if (trim((string) $d['first_name']) === '') {
                    throw new InvalidArgumentException(hkp_t('First name is required.'));
                }
                $temp = bin2hex(random_bytes(5));
                $this->db->insert('users', array('first_name' => trim($d['first_name']), 'last_name' => trim($d['last_name']), 'email' => $email,
                    'password' => sha1($temp), 'role_id' => 2, 'status' => 1, 'is_instructor' => 0, 'skills' => '[]', 'payment_keys' => '[]', 'sessions' => '[]',
                    'social_links' => '{"facebook":"","twitter":"","linkedin":""}', 'wishlist' => '[]', 'date_added' => time(), 'last_modified' => time()));
                $uid = (int) $this->db->insert_id();
                $this->db->insert('ha_profile', $profile + array('user_id' => $uid, 'created_at' => date('Y-m-d H:i:s')));
                $role = $this->db->get_where('ha_role', array('code' => 'learner'))->row_array();
                $this->db->insert('ha_user_role', array('user_id' => $uid, 'role_id' => $role['id'], 'organization_id' => $prop['organization_id'],
                    'property_id' => $pid, 'department_id' => $profile['department_id'], 'created_at' => date('Y-m-d H:i:s')));
                $this->ha_audit->log('create', 'user', $uid, array('description' => 'Employee ' . $email . ' created', 'property_id' => $pid));
                $this->session->set_flashdata('hkp_temp_password', $temp);
            }
            $this->load->library(array('ha_learning', 'ha_readiness'));
            if ($status === 'active' && $profile['job_role_id']) {
                $this->ha_learning->sync_role_plan($uid);
            }
            $this->ha_readiness->calculate($uid);
            return $uid;
        }, $id ? hkp_t('Employee saved.') : hkp_t('Employee created. Share the temporary password shown on their page.'), function ($uid) { return hkp_url('team/employee/' . $uid); });
    }

    public function new_person() {
        $this->need('users.create');
        $this->render('team_person_new', array('depts' => $this->departments(), 'roles' => $this->db->where('status', 'active')->order_by('title_en')->get('ha_job_role')->result_array(),
            'props' => $this->switchable_properties() ?: ($this->property_id() ? array($this->db->get_where('ha_property', array('id' => $this->property_id()))->row_array()) : array())),
            hkp_t('Add employee'), 'team');
    }

    // --------------------------------------------------------------- assign

    public function assign() {
        $this->need('training_assignments.assign');
        $this->load->library('ha_learning');
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $items = array();
            foreach ((array) $this->input->post('tracks') as $t) { $items[] = array('track', (int) $t); }
            foreach ((array) $this->input->post('courses') as $c) { $items[] = array('course', (int) $c); }
            $targets = array();
            foreach (array('users' => 'user', 'departments' => 'department', 'roles' => 'job_role', 'cohorts' => 'cohort') as $k => $t) {
                foreach ((array) $this->input->post($k) as $v) { $targets[] = array($t, (int) $v); }
            }
            if ($this->input->post('whole_property') && $this->property_id()) {
                $targets[] = array('property', $this->property_id());
            }
            $this->attempt(function () use ($items, $targets) {
                return $this->ha_learning->assign(array('title_en' => $this->input->post('title_en'), 'title_ar' => $this->input->post('title_ar'),
                    'due_at' => $this->input->post('due_at'), 'is_mandatory' => $this->input->post('is_mandatory') ? 1 : 0, 'priority' => $this->input->post('priority'),
                    'property_id' => $this->property_id(), 'items' => $items, 'targets' => $targets), $this->uid);
            }, hkp_t('Learning assigned.'), hkp_url('team/assign'));
            return;
        }
        $ids = $this->team_ids();
        $loc = hkp_locale();
        $this->render('team_assign', array(
            'tracks' => $this->db->where('status', 'published')->group_start()->where('organization_id IS NULL', null, false)->or_where('organization_id', (int) $this->ctx['organization_id'])->group_end()->order_by('title_en')->get('ha_track')->result_array(),
            'courses' => $this->db->select("c.id, COALESCE(NULLIF(t.title, ''), te.title) title", false)->from('ha_course c')->join('ha_course_translation t', 't.course_id = c.id AND t.locale = ' . $this->db->escape($loc), 'left')
                ->join('ha_course_translation te', "te.course_id = c.id AND te.locale = 'en'", 'left')->where('c.status', 'published')->order_by('title')->get()->result_array(),
            'people' => $ids ? $this->db->select('id, first_name, last_name')->where_in('id', $ids)->order_by('first_name')->get('users')->result_array() : array(),
            'depts' => $this->departments(), 'roles' => $this->db->where('status', 'active')->order_by('title_en')->get('ha_job_role')->result_array(),
            'cohorts' => $this->db->where('status', 'active')->where('organization_id', (int) $this->ctx['organization_id'])->get('ha_cohort')->result_array(),
            'recent' => $this->db->where('organization_id', (int) $this->ctx['organization_id'])->order_by('id', 'DESC')->limit(15)->get('ha_training_assignment')->result_array(),
        ), hkp_t('Assign learning'), 'assign');
    }

    public function exempt($recipient_id = 0) {
        $this->post_guard();
        $this->load->library('ha_learning');
        $this->attempt(function () use ($recipient_id) { $this->ha_learning->exempt((int) $recipient_id, $this->input->post('reason'), $this->uid); }, hkp_t('Exemption recorded.'));
    }

    // -------------------------------------------------------------- cohorts

    public function cohorts($id = 0) {
        $this->need('cohorts.view');
        $org = (int) $this->ctx['organization_id'];
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $this->need('cohorts.create');
            $this->attempt(function () use ($id, $org) {
                if ($id) {
                    $c = $this->db->get_where('ha_cohort', array('id' => (int) $id))->row_array();
                    if (!$c || !$this->ha_auth->can_organization($c['organization_id']) && !($c['property_id'] && $this->ha_auth->can_property($c['property_id']))) {
                        throw new RuntimeException(hkp_t('That cohort is outside your scope.'));
                    }
                    $allowed = array_flip($this->visible_users());
                    foreach ((array) $this->input->post('members') as $uid) {
                        if (isset($allowed[(int) $uid])) {
                            $this->db->query('INSERT IGNORE INTO ha_cohort_member (cohort_id, user_id, wave, joined_at) VALUES (?,?,?,?)', array((int) $id, (int) $uid, $this->input->post('wave') ?: null, date('Y-m-d H:i:s')));
                        }
                    }
                    return $id;
                }
                $name = trim((string) $this->input->post('name_en'));
                if ($name === '') {
                    throw new InvalidArgumentException(hkp_t('Name the cohort.'));
                }
                $this->db->insert('ha_cohort', array('organization_id' => $org, 'property_id' => $this->property_id(), 'code' => 'c-' . substr(bin2hex(random_bytes(4)), 0, 8),
                    'name_en' => $name, 'name_ar' => trim((string) $this->input->post('name_ar')) ?: $name,
                    'cohort_type' => in_array($this->input->post('cohort_type'), array('general', 'new_joiners', 'pre_opening', 'management', 'department', 'role'), true) ? $this->input->post('cohort_type') : 'general',
                    'opening_date' => $this->input->post('opening_date') ?: null, 'status' => 'active', 'created_by' => $this->uid, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')));
                return $this->db->insert_id();
            }, hkp_t('Cohort saved.'), function ($cid) { return hkp_url('team/cohorts/' . $cid); });
            return;
        }
        $ids = $this->team_ids();
        $cohort = $id ? $this->db->get_where('ha_cohort', array('id' => (int) $id))->row_array() : null;
        $this->render('team_cohorts', array('cohorts' => $this->db->select('c.*, (SELECT COUNT(*) FROM ha_cohort_member m WHERE m.cohort_id = c.id) members', false)
                ->where('c.organization_id', $org)->order_by('c.id', 'DESC')->get('ha_cohort c')->result_array(),
            'cohort' => $cohort, 'members' => $cohort ? $this->db->select('m.*, u.first_name, u.last_name, rr.status AS readiness')->from('ha_cohort_member m')->join('users u', 'u.id = m.user_id')
                ->join('ha_readiness_record rr', 'rr.user_id = m.user_id AND rr.is_current = 1', 'left')->where('m.cohort_id', (int) $id)->get()->result_array() : array(),
            'people' => $ids ? $this->db->select('id, first_name, last_name')->where_in('id', $ids)->order_by('first_name')->get('users')->result_array() : array()), hkp_t('Cohorts'), 'cohorts');
    }

    // ----------------------------------------------------------------- gaps

    public function gaps() {
        $this->need('gaps.view');
        $this->load->library('ha_competency');
        $ids = $this->team_ids();
        $sev = $this->input->get('severity');
        $this->render('team_gaps', array('gaps' => $this->ha_competency->open_gaps(array('user_ids' => $ids, 'severity' => $sev)),
            'matrix' => $this->ha_competency->matrix($ids), 'sev' => $sev,
            'courses' => $this->db->select('c.id, t.title')->from('ha_course c')->join('ha_course_translation t', "t.course_id = c.id AND t.locale = 'en'")->where('c.status', 'published')->order_by('t.title')->get()->result_array()),
            hkp_t('Competency gaps'), 'gaps');
    }

    public function action_create() {
        $this->need('action_plans.create');
        $this->post_guard();
        $this->load->library('ha_action_plans');
        $this->attempt(function () { return $this->ha_action_plans->create($this->input->post(), $this->uid); }, hkp_t('Action plan assigned.'),
            function ($id) { return hkp_url('actions/view/' . $id); });
    }

    public function actions() {
        $this->need(array('action_plans.review', 'action_plans.view'));
        $this->load->library('ha_action_plans');
        $st = $this->input->get('status');
        $this->render('team_actions', array('rows' => $this->ha_action_plans->for_users($this->team_ids(), $st ? array($st) : array()), 'st' => $st), hkp_t('Action plans'), 'team_actions');
    }

    // ------------------------------------------------------------ readiness

    public function readiness() {
        $this->need('readiness.view');
        $this->load->library('ha_readiness');
        $ids = $this->team_ids();
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $this->need('readiness.calculate');
            $n = $this->ha_readiness->recalculate_all($ids);
            $this->back(hkp_t('Readiness recalculated for {n} people.', array('n' => $n)));
            return;
        }
        $in = $ids ? implode(',', $ids) : '0';
        $grid = $this->db->query("SELECT d.id AS dept_id, d.name_en AS dept_en, d.name_ar AS dept_ar, j.id AS role_id, j.title_en AS role_en, j.title_ar AS role_ar,
                SUM(rr.status = 'ready') ready, SUM(rr.status = 'conditional') conditional, SUM(rr.status = 'not_ready') not_ready, COUNT(*) total
            FROM ha_profile p LEFT JOIN ha_department d ON d.id = p.department_id JOIN ha_job_role j ON j.id = p.job_role_id
            LEFT JOIN ha_readiness_record rr ON rr.user_id = p.user_id AND rr.is_current = 1
            WHERE p.user_id IN ($in) AND p.status = 'active' GROUP BY d.id, j.id ORDER BY d.name_en, j.title_en")->result_array();
        $people = $this->db->query("SELECT u.id, u.first_name, u.last_name, rr.status, rr.reasons_json, rr.calculated_at, j.title_en, j.title_ar
            FROM ha_profile p JOIN users u ON u.id = p.user_id LEFT JOIN ha_job_role j ON j.id = p.job_role_id
            LEFT JOIN ha_readiness_record rr ON rr.user_id = p.user_id AND rr.is_current = 1
            WHERE p.user_id IN ($in) AND p.status = 'active' AND p.job_role_id IS NOT NULL ORDER BY FIELD(IFNULL(rr.status,'x'),'not_ready','conditional','ready'), u.first_name")->result_array();
        $this->render('team_readiness', array('grid' => $grid, 'people' => $people, 'summary' => $this->ha_readiness->summary($ids)), hkp_t('Readiness'), 'readiness');
    }

    public function opening() {
        $this->need('readiness.view');
        $this->load->library('ha_readiness');
        $pid = $this->property_id();
        if (!$pid) {
            $this->back(hkp_t('Choose a property first.'), false, hkp_url('team'));
            return;
        }
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $this->need('readiness.calculate');
            foreach ((array) $this->input->post('item') as $iid => $v) {
                $row = $this->db->get_where('ha_opening_readiness_item', array('id' => (int) $iid, 'property_id' => $pid))->row_array();
                if ($row) {
                    $this->db->where('id', $row['id'])->update('ha_opening_readiness_item', array('current_value' => (float) $v['current'], 'required_value' => (float) $v['required'],
                        'due_date' => $v['due'] ?: null, 'notes' => mb_substr((string) $v['notes'], 0, 500), 'updated_at' => date('Y-m-d H:i:s')));
                }
            }
            if (trim((string) $this->input->post('new_label')) !== '') {
                $this->db->insert('ha_opening_readiness_item', array('property_id' => $pid, 'category' => $this->input->post('new_category'),
                    'label_en' => $this->input->post('new_label'), 'label_ar' => $this->input->post('new_label_ar') ?: $this->input->post('new_label'),
                    'required_value' => (float) $this->input->post('new_required') ?: 100, 'current_value' => 0, 'is_critical' => $this->input->post('new_critical') ? 1 : 0,
                    'owner_user_id' => $this->uid, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')));
            }
            $this->back(hkp_t('Opening readiness saved.'));
            return;
        }
        $this->render('team_opening', array('o' => $this->ha_readiness->opening($pid)), hkp_t('Opening readiness'), 'opening');
    }

    // --------------------------------------------------------- certification

    public function certifications($a = '', $id = 0) {
        $this->need(array('certificates.export', 'certificates.issue', 'certificates.view'));
        $this->load->library('ha_certification');
        $C = $this->ha_certification;
        if ($a === 'issue') {
            $this->post_guard();
            $this->need('certificates.issue');
            $uid = (int) $this->input->post('user_id');
            if (!$this->ha_auth->can_user($uid)) {
                show_404();
            }
            $this->attempt(function () use ($C, $uid) { return $C->issue((int) $this->input->post('program_id'), $uid, $this->uid); }, hkp_t('Certificate issued.'));
            return;
        }
        if ($a === 'revoke') {
            $this->post_guard();
            $this->need('certificates.revoke');
            $c = $this->db->get_where('ha_certificate', array('id' => (int) $id))->row_array();
            if (!$c || !$this->ha_auth->can_user($c['user_id'])) {
                show_404();
            }
            $this->attempt(function () use ($C, $id) { $C->revoke((int) $id, $this->input->post('reason'), $this->uid); }, hkp_t('Certificate revoked.'));
            return;
        }
        $ids = $this->team_ids();
        $programs = $this->db->get_where('ha_certification_program', array('status' => 'published'))->result_array();
        $eligible = array();
        foreach ($programs as $p) {
            if (!$p['job_role_id']) {
                continue;
            }
            foreach ($this->db->select('p.user_id, u.first_name, u.last_name')->from('ha_profile p')->join('users u', 'u.id = p.user_id')
                ->where('p.job_role_id', $p['job_role_id'])->where_in('p.user_id', $ids ?: array(0))->get()->result_array() as $person) {
                if ($C->valid_certificate($p['id'], $person['user_id'])) {
                    continue;
                }
                $checks = $C->check($p, $person['user_id']);
                $met = count(array_filter($checks, function ($c) { return $c['passed']; }));
                $eligible[] = array('program' => $p, 'person' => $person, 'met' => $met, 'total' => count($checks), 'ready' => $checks && $met === count($checks));
            }
        }
        $this->render('team_certifications', array('d' => $C->dashboard($ids), 'eligible' => $eligible,
            'rows' => $this->db->select('c.*, u.first_name, u.last_name')->from('ha_certificate c')->join('users u', 'u.id = c.user_id')->where_in('c.user_id', $ids ?: array(0))->order_by('c.issued_at', 'DESC')->limit(200)->get()->result_array()),
            hkp_t('Certifications'), 'team_certs');
    }

    // ---------------------------------------------------------------- audits

    public function audits($a = '', $id = 0) {
        $this->need('quality_audits.view');
        $this->load->library('ha_advisory');
        $A = $this->ha_advisory;
        if ($a === 'create') {
            $this->post_guard();
            $this->attempt(function () use ($A) { return $A->create_audit($this->input->post() + array('property_id' => $this->property_id()), $this->uid); }, hkp_t('Audit created.'),
                function ($aid) { return hkp_url('team/audits/view/' . $aid); });
            return;
        }
        if ($a === 'finding') {
            $this->post_guard();
            $this->attempt(function () use ($A, $id) { return $A->add_finding((int) $id, $this->input->post(), $this->uid); }, hkp_t('Finding recorded.'));
            return;
        }
        if ($a === 'recheck') {
            $this->post_guard();
            $this->attempt(function () use ($A, $id) { $A->recheck((int) $id, $this->input->post('passed') === '1'); }, hkp_t('Recheck recorded.'));
            return;
        }
        if ($a === 'view') {
            $audit = $A->audit($id);
            if (!$audit) {
                show_404();
            }
            $this->render('team_audit', array('a' => $audit, 'people' => $this->db->select('id, first_name, last_name')->where_in('id', $this->team_ids() ?: array(0))->get('users')->result_array()), $audit['title'], 'audits');
            return;
        }
        $db = $this->db->select('a.*, p.name_en AS prop_en, p.name_ar AS prop_ar')->from('ha_quality_audit a')->join('ha_property p', 'p.id = a.property_id');
        $this->ha_auth->scope_query($db, array('organization_id' => 'a.organization_id', 'property_id' => 'a.property_id'));
        $this->render('team_audits', array('rows' => $db->order_by('a.id', 'DESC')->limit(100)->get()->result_array(),
            'sops' => $this->db->select('d.id, t.title')->from('ha_sop_document d')->join('ha_sop_version_translation t', "t.version_id = d.current_version_id AND t.locale = 'en'")->where('d.status', 'published')->get()->result_array()),
            hkp_t('Quality audits'), 'audits');
    }

    // ------------------------------------------------------------------ KPIs

    public function kpis() {
        $this->need('kpis.view');
        $this->load->library('ha_kpi');
        $pid = $this->property_id();
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $this->need('kpis.update');
            $this->attempt(function () use ($pid) {
                return $this->ha_kpi->record(array('kpi' => (int) $this->input->post('kpi_id'), 'property_id' => $pid, 'period_start' => $this->input->post('period_start'),
                    'period_end' => $this->input->post('period_end'), 'actual' => $this->input->post('actual'), 'target' => $this->input->post('target'), 'source' => 'manual'), $this->uid);
            }, hkp_t('KPI value saved.'));
            return;
        }
        $this->render('team_kpis', array('card' => $pid ? $this->ha_kpi->scorecard($pid, $this->ctx['organization_id']) : array(),
            'platform' => $this->ha_kpi->platform_kpis($this->team_ids()), 'defs' => $this->ha_kpi->definitions($this->ctx['organization_id']), 'pid' => $pid), hkp_t('KPIs'), 'kpis');
    }

    // --------------------------------------------------------------- reports

    public function reports($code = '') {
        $this->need('reports.view');
        $this->load->library('ha_reports');
        $f = array('property_id' => (int) $this->input->get('property_id') ?: $this->property_id(), 'department_id' => (int) $this->input->get('department_id'),
            'job_role_id' => (int) $this->input->get('job_role_id'), 'from' => $this->input->get('from'), 'to' => $this->input->get('to'));
        if ($f['property_id'] && !$this->ha_auth->can_property($f['property_id'])) {
            $f['property_id'] = null;
        }
        if (!$code) {
            $this->render('team_reports', array('catalogue' => Ha_reports::catalogue(), 'f' => $f, 'depts' => $this->departments(),
                'roles' => $this->db->where('status', 'active')->order_by('title_en')->get('ha_job_role')->result_array(), 'props' => $this->switchable_properties()), hkp_t('Reports'), 'reports');
            return;
        }
        $data = $this->ha_reports->dataset($code, $f);
        $lines = $this->ha_reports->context_lines($f);
        $format = $this->input->get('format') ?: 'html';
        if ($format !== 'html') {
            $this->need('reports.export');
        }
        $this->ha_reports->log_run($code, $format, $f, count($data['rows']));
        $name = $code . '-' . date('Ymd-His');
        if ($format === 'csv') {
            $this->output->set_content_type('text/csv', 'utf-8')->set_header('Content-Disposition: attachment; filename="' . $name . '.csv"')->set_output($this->ha_reports->csv($data, $lines));
            return;
        }
        if ($format === 'xlsx') {
            $this->output->set_content_type('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->set_header('Content-Disposition: attachment; filename="' . $name . '.xlsx"')->set_output($this->ha_reports->xlsx($data, $lines));
            return;
        }
        $this->render('team_report', array('data' => $data, 'lines' => $lines, 'code' => $code, 'f' => $f, 'print' => $format === 'print'), $data['title'], 'reports');
    }

    // --------------------------------------------------------------- brand

    public function branding() {
        $this->need('branding.update');
        $pid = $this->property_id();
        $scope = $this->input->get('scope') === 'organization' && $this->ha_auth->can_organization($this->ctx['organization_id']) ? 'organization' : 'property';
        $sid = $scope === 'organization' ? (int) $this->ctx['organization_id'] : (int) $pid;
        if (!$sid) {
            $this->back(hkp_t('Choose a property first.'), false, hkp_url('team'));
            return;
        }
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $in = $this->input->post();
            $this->attempt(function () use ($scope, $sid, $in) {
                if (!empty($_FILES['logo']['name'])) {
                    $in['logo_path'] = $this->store_image('logo');
                }
                if (!empty($_FILES['favicon']['name'])) {
                    $in['favicon_path'] = $this->store_image('favicon');
                }
                $this->ha_tenant->save_brand($scope, $sid, $in, $this->uid);
            }, hkp_t('Branding saved.'));
            return;
        }
        $row = $this->db->get_where('ha_branding', array('scope_type' => $scope, 'scope_id' => $sid))->row_array() ?: array();
        $settings = array();
        foreach (Ha_tenant::registry() as $k => $meta) {
            if (in_array($meta[2], array('competency', 'readiness', 'assessment', 'certification', 'learning', 'ai'), true) && $k !== 'ai.system_instructions') {
                $ov = $this->db->get_where('ha_setting', array('scope_type' => 'property', 'scope_id' => (int) $pid, 'setting_key' => $k))->row_array();
                $settings[$k] = array('meta' => $meta, 'effective' => $this->ha_tenant->get($k, $pid), 'override' => $ov ? $ov['value'] : null);
            }
        }
        $this->render('team_branding', array('row' => $row, 'scope' => $scope, 'effective' => $this->ha_tenant->brand($pid, $this->ctx['organization_id']),
            'settings' => $settings, 'pid' => $pid, 'can_settings' => $this->can(array('settings.update', 'readiness.configure'))), hkp_t('Branding & settings'), 'branding');
    }

    public function settings() {
        $this->need(array('settings.update', 'readiness.configure', 'branding.update'));
        $this->post_guard();
        $pid = $this->property_id();
        $this->attempt(function () use ($pid) {
            foreach ((array) $this->input->post('s') as $k => $v) {
                if (!isset(Ha_tenant::registry()[$k])) {
                    continue;
                }
                if ($v === '' || $v === null) {
                    $this->ha_tenant->reset($k, 'property', $pid);
                } else {
                    $this->ha_tenant->set($k, $v, 'property', $pid, $this->uid);
                }
            }
        }, hkp_t('Property settings saved.'));
    }

    protected function store_image($field) {
        $f = $_FILES[$field];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('png', 'jpg', 'jpeg', 'webp', 'ico'), true) || $f['size'] > 2 * 1048576 || !is_uploaded_file($f['tmp_name'])) {
            throw new InvalidArgumentException(hkp_t('Upload a PNG, JPG or WebP image under 2 MB.'));
        }
        if ($ext !== 'ico' && !@getimagesize($f['tmp_name'])) {
            throw new InvalidArgumentException(hkp_t('That file is not an image.'));
        }
        $dir = FCPATH . 'uploads/branding/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = $field . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        move_uploaded_file($f['tmp_name'], $dir . $name);
        return 'uploads/branding/' . $name;
    }
}
