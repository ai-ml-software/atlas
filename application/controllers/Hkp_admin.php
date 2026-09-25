<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Hkp_Controller.php';

/**
 * The Altus Team experience (ppt-features 4C, 56, 60, 77, 137, 166, 180:
 * "What is happening across the portfolio?").
 *
 *   /hkp/admin                       portfolio dashboard + cross-property comparison
 *   /hkp/admin/crud/{entity}[/edit/{id}|/save/{id}|/remove/{id}]   catalogue administration
 *   /hkp/admin/users                 identity and role grants
 *   /hkp/admin/curriculum            Domain -> Track -> Module -> Lesson tree, lesson builder
 *   /hkp/admin/content               knowledge QC dashboard, editor, workflow, diff
 *   /hkp/admin/assessments           question banks, question builder, analytics
 *   /hkp/admin/competencies          role -> competency matrix, rubrics
 *   /hkp/admin/rules                 readiness policies, certification programmes
 *   /hkp/admin/ai                    AI governance, sources, index, query log
 *   /hkp/admin/engagements           Altus Ascent engagements
 *   /hkp/admin/frameworks            Performance Matrix, GOPPAR, ESG, capability assessments
 *   /hkp/admin/corporate             corporate CMS hub
 *   /hkp/admin/imports               CSV import with preview / commit / rollback
 *   /hkp/admin/audit                 audit log
 *   /hkp/admin/system                health, settings, notifications, roadmap
 */
class Hkp_admin extends Hkp_Controller {

    public function index() {
        $this->need(array('organizations.view', 'analytics.view'));
        $this->load->library(array('ha_kpi', 'ha_readiness', 'ha_knowledge'));
        $db = $this->db;
        $users = $this->visible_users();
        $in = $users ? implode(',', $users) : '0';
        $count = function ($t, $w = '1=1') use ($db) { return (int) $db->query("SELECT COUNT(*) n FROM $t WHERE $w")->row()->n; };
        $stats = array(
            'organisations' => count($this->ha_auth->is_system_scoped() ? $db->select('id')->get('ha_organization')->result_array() : $this->ha_auth->organization_ids()),
            'properties' => $count('ha_property', $this->ha_auth->is_system_scoped() ? "status = 'active'" : "status = 'active' AND organization_id IN (" . implode(',', $this->ha_auth->organization_ids() ?: array(0)) . ')'),
            'users' => count($users), 'active' => $count('users', "id IN ($in) AND status = 1 AND last_modified > " . (time() - 30 * 86400)),
            'learners' => $count('ha_user_role ur JOIN ha_role r ON r.id = ur.role_id', "r.code = 'learner' AND ur.user_id IN ($in)"),
            'managers' => $count('ha_user_role ur JOIN ha_role r ON r.id = ur.role_id', "r.code IN ('property_manager','department_manager','supervisor','training_manager','org_admin') AND ur.user_id IN ($in)"),
            'courses' => $count('ha_course', "status = 'published'"), 'domains' => $count('ha_domain', "status = 'active'"), 'tracks' => $count('ha_track', "status = 'published'"),
            'lessons' => $count('ha_lesson', "status = 'published'"), 'assessments' => $count('ha_assessment', "status = 'published'"), 'competencies' => $count('ha_skill', "status = 'active'"),
            'gaps' => $count('ha_competency_gap', "status IN ('open','in_action') AND user_id IN ($in)"), 'actions' => $count('ha_action_plan', "status != 'completed' AND user_id IN ($in)"),
            'certificates' => $count('ha_certificate', "status = 'issued' AND user_id IN ($in)"),
            'expiring' => $count('ha_certificate', "status = 'issued' AND user_id IN ($in) AND expires_at <= '" . date('Y-m-d H:i:s', strtotime('+30 days')) . "'"),
            'awaiting' => $count('ha_sop_version', "status IN ('internal_review','quality_review','approved','review')"),
            'ai_week' => $count('ha_ai_query', "created_at >= '" . date('Y-m-d H:i:s', strtotime('-7 days')) . "'"),
        );
        $compare = $db->query("SELECT pr.id, pr.name_en, pr.name_ar, pr.operational_status, COUNT(DISTINCT p.user_id) staff,
                ROUND(100 * SUM(rr.status = 'ready') / GREATEST(COUNT(rr.id),1), 1) ready_pct,
                (SELECT ROUND(100 * SUM(e.status = 'completed') / GREATEST(COUNT(*),1), 1) FROM ha_enrollment e JOIN ha_profile p2 ON p2.user_id = e.user_id WHERE p2.property_id = pr.id) completion,
                (SELECT COUNT(*) FROM ha_competency_gap g WHERE g.property_id = pr.id AND g.status IN ('open','in_action') AND g.severity = 'critical') critical,
                (SELECT COUNT(*) FROM ha_certificate c WHERE c.property_id = pr.id AND c.status = 'issued') certs
            FROM ha_property pr LEFT JOIN ha_profile p ON p.property_id = pr.id AND p.status = 'active' AND p.user_id IN ($in)
            LEFT JOIN ha_readiness_record rr ON rr.user_id = p.user_id AND rr.is_current = 1
            WHERE pr.status = 'active' GROUP BY pr.id ORDER BY critical DESC, pr.name_en")->result_array();
        $recent = $db->order_by('id', 'DESC')->limit(12)->get('ha_audit_log')->result_array();
        $this->render('admin_dashboard', array('s' => $stats, 'compare' => $compare, 'recent' => $recent, 'k' => $this->ha_kpi->platform_kpis($users)),
            hkp_t('Portfolio dashboard'), 'altus_home');
    }

    // --------------------------------------------------------------- CRUD

    public function crud($key = '', $op = 'list', $id = 0) {
        $this->load->library('ha_crud');
        $e = $this->ha_crud->entity($key);
        if (!$e) {
            show_404();
        }
        $this->need($e['perm'] . '.view');
        $nav = array('organizations' => 'orgs', 'properties' => 'props', 'portfolios' => 'orgs', 'departments' => 'orgs', 'job_roles' => 'orgs',
            'domains' => 'curriculum', 'tracks' => 'curriculum', 'competencies' => 'comp_admin', 'kpis' => 'altus_home', 'roadmap' => 'system');
        $active = isset($nav[$key]) ? $nav[$key] : 'corporate';
        if ($op === 'save') {
            $this->post_guard();
            $this->attempt(function () use ($e, $id) { return $this->ha_crud->save($e, (int) $id, $this->input->post()); }, hkp_t('Saved.'),
                function () use ($key) { return hkp_url('admin/crud/' . $key); });
            return;
        }
        if ($op === 'remove') {
            $this->post_guard();
            $this->attempt(function () use ($e, $id) { return $this->ha_crud->remove($e, (int) $id); }, hkp_t('Removed.'), hkp_url('admin/crud/' . $key));
            return;
        }
        if ($op === 'edit' || $op === 'new') {
            $row = $op === 'edit' ? $this->ha_crud->find($e, $id) : array();
            if ($op === 'edit' && !$row) {
                show_404();
            }
            $opts = array();
            foreach ($e['fields'] as $f => $type) {
                if (strpos($type, 'fk:') === 0) {
                    $opts[$f] = $this->ha_crud->options($type);
                } elseif (rtrim($type, '*') === 'scope') {
                    $opts[$f] = $this->ha_crud->org_options();
                }
            }
            $this->render('admin_crud_form', array('e' => $e, 'row' => $row, 'opts' => $opts, 'id' => (int) $id), hkp_t($e['title']), $active);
            return;
        }
        $data = $this->ha_crud->rows($e, array('q' => $this->input->get('q'), 'page' => $this->input->get('page')));
        $orgs = array();
        foreach ($this->db->select('id, name_en')->get('ha_organization')->result_array() as $o) {
            $orgs[$o['id']] = $o['name_en'];
        }
        $this->render('admin_crud_list', array('e' => $e, 'data' => $data, 'orgs' => $orgs, 'q' => $this->input->get('q')), hkp_t($e['title']), $active);
    }

    // -------------------------------------------------------------- users

    public function users($op = '', $id = 0) {
        $this->need('users.view');
        if ($op === 'grant' || $op === 'revoke') {
            $this->post_guard();
            $this->need('users.update');
            $this->attempt(function () use ($op, $id) {
                if (!$this->ha_auth->can_user($id)) {
                    throw new RuntimeException(hkp_t('That person is outside your scope.'));
                }
                if ($op === 'revoke') {
                    $g = $this->db->get_where('ha_user_role', array('id' => (int) $this->input->post('grant_id'), 'user_id' => (int) $id))->row_array();
                    if ($g) {
                        $this->db->where('id', $g['id'])->delete('ha_user_role');
                        $this->ha_audit->log('role_change', 'user', (int) $id, array('description' => 'Role grant removed', 'before' => $g));
                    }
                    return;
                }
                $role = $this->db->get_where('ha_role', array('id' => (int) $this->input->post('role_id')))->row_array();
                if (!$role) {
                    throw new InvalidArgumentException(hkp_t('Choose a role.'));
                }
                if ($role['scope'] === 'system' && !$this->ha_auth->is_super_admin() && !$this->ha_auth->is('altus_admin')) {
                    throw new RuntimeException(hkp_t('Only a platform administrator can grant platform roles.'));
                }
                if ($role['code'] === 'super_admin' && !$this->ha_auth->is_super_admin()) {
                    throw new RuntimeException(hkp_t('Only a super admin can grant super admin.'));
                }
                $org = $this->input->post('organization_id') ? (int) $this->input->post('organization_id') : null;
                $prop = $this->input->post('property_id') ? (int) $this->input->post('property_id') : null;
                $dept = $this->input->post('department_id') ? (int) $this->input->post('department_id') : null;
                if (($org && !$this->ha_auth->can_organization($org)) || ($prop && !$this->ha_auth->can_property($prop))) {
                    throw new RuntimeException(hkp_t('That scope is outside yours.'));
                }
                $this->db->query('INSERT IGNORE INTO ha_user_role (user_id, role_id, organization_id, property_id, department_id, created_at) VALUES (?,?,?,?,?,?)',
                    array((int) $id, $role['id'], $org, $prop, $dept, date('Y-m-d H:i:s')));
                $this->ha_audit->log('role_change', 'user', (int) $id, array('description' => 'Granted ' . $role['code'], 'after' => compact('org', 'prop', 'dept')));
            }, hkp_t('Access updated.'));
            return;
        }
        $ids = $this->visible_users();
        $q = trim((string) $this->input->get('q'));
        $db = $this->db->select('u.id, u.first_name, u.last_name, u.email, u.status AS login_status, p.status, o.name_en AS org, pr.name_en AS prop')->from('users u')
            ->join('ha_profile p', 'p.user_id = u.id', 'left')->join('ha_organization o', 'o.id = p.organization_id', 'left')->join('ha_property pr', 'pr.id = p.property_id', 'left');
        if (!$this->ha_auth->is_system_scoped()) {
            $db->where_in('u.id', $ids ?: array(0));
        }
        if ($q !== '') {
            $db->group_start()->like('u.email', $q)->or_like('u.first_name', $q)->or_like('u.last_name', $q)->group_end();
        }
        $rows = $db->order_by('u.first_name')->limit(300)->get()->result_array();
        $grants = array();
        foreach ($this->db->select('ur.*, r.code, r.name_en, r.name_ar, r.scope')->from('ha_user_role ur')->join('ha_role r', 'r.id = ur.role_id')
            ->where_in('ur.user_id', array_column($rows, 'id') ?: array(0))->get()->result_array() as $g) {
            $grants[$g['user_id']][] = $g;
        }
        $this->render('admin_users', array('rows' => $rows, 'grants' => $grants, 'q' => $q, 'roles' => $this->db->order_by('scope')->get('ha_role')->result_array(),
            'orgs' => $this->db->get('ha_organization')->result_array(), 'props' => $this->switchable_properties() ?: $this->db->get('ha_property')->result_array(),
            'depts' => $this->db->where('status', 'active')->get('ha_department')->result_array()), hkp_t('Users & access'), 'people_admin');
    }

    // ---------------------------------------------------------- curriculum

    public function curriculum($op = '', $id = 0) {
        $this->need('curriculum.view');
        // Global tracks and modules plus those of the viewer's own organisations.
        $orgs = $this->ha_auth->is_system_scoped() ? null : implode(',', array_map('intval', $this->ha_auth->organization_ids() ?: array((int) $this->ctx['organization_id'])));
        $own = function ($col) use ($orgs) { return $orgs === null ? '1=1' : '(' . $col . ' IS NULL OR ' . $col . ' IN (' . $orgs . '))'; };
        if (in_array($op, array('order', 'attach', 'detach'), true)) {
            $track_ok = $this->db->where('id', (int) $id)->where($own('organization_id'), null, false)->count_all_results('ha_track');
            if (!$track_ok || ($op === 'attach' && !$this->db->where('id', (int) $this->input->post('course_id'))->where($own('organization_id'), null, false)->count_all_results('ha_course'))) {
                show_404();
            }
        }
        if ($op === 'order') {
            $this->post_guard();
            $this->need('curriculum.update');
            $ids = array_filter(array_map('intval', explode(',', (string) $this->input->post('order'))));
            foreach ($ids as $i => $cid) {
                $this->db->where(array('track_id' => (int) $id, 'course_id' => $cid))->update('ha_track_module', array('sort_order' => $i));
            }
            $this->json(array('ok' => true));
            return;
        }
        if ($op === 'attach') {
            $this->post_guard();
            $this->need('curriculum.update');
            $this->db->query('INSERT IGNORE INTO ha_track_module (track_id, course_id, sort_order, is_mandatory) VALUES (?, ?, 99, ?)',
                array((int) $id, (int) $this->input->post('course_id'), $this->input->post('is_mandatory') ? 1 : 0));
            $this->back(hkp_t('Module added to track.'));
            return;
        }
        if ($op === 'detach') {
            $this->post_guard();
            $this->need('curriculum.update');
            $this->db->where(array('track_id' => (int) $id, 'course_id' => (int) $this->input->post('course_id')))->delete('ha_track_module');
            $this->back(hkp_t('Module removed from track.'));
            return;
        }
        $loc = hkp_locale();
        $domains = $this->db->order_by('sort_order')->get('ha_domain')->result_array();
        $tracks = $this->db->where($own('organization_id'), null, false)->order_by('sort_order')->get('ha_track')->result_array();
        $mods = $this->db->where($own('c.organization_id'), null, false)->select("tm.track_id, tm.course_id, tm.is_mandatory, c.code, c.status, COALESCE(NULLIF(t.title, ''), te.title) AS title,
                (SELECT COUNT(*) FROM ha_lesson l WHERE l.course_id = c.id) lessons, (SELECT COUNT(*) FROM ha_assessment a WHERE a.course_id = c.id) assessments", false)
            ->from('ha_track_module tm')->join('ha_course c', 'c.id = tm.course_id')
            ->join('ha_course_translation t', 't.course_id = c.id AND t.locale = ' . $this->db->escape($loc), 'left')
            ->join('ha_course_translation te', "te.course_id = c.id AND te.locale = 'en'", 'left')->order_by('tm.sort_order')->get()->result_array();
        $by_track = array();
        foreach ($mods as $m) {
            $by_track[$m['track_id']][] = $m;
        }
        $courses = $this->db->where($own('c.organization_id'), null, false)->select('c.id, c.code, t.title')->from('ha_course c')->join('ha_course_translation t', "t.course_id = c.id AND t.locale = 'en'", 'left')->order_by('t.title')->get()->result_array();
        $this->render('admin_curriculum', array('domains' => $domains, 'tracks' => $tracks, 'mods' => $by_track, 'courses' => $courses), hkp_t('Curriculum'), 'curriculum');
    }

    // ------------------------------------------------------------ knowledge

    public function content($op = '', $id = 0) {
        $this->need(array('knowledge.create', 'knowledge.review', 'knowledge.approve', 'knowledge.view'));
        $this->load->library('ha_knowledge');
        $K = $this->ha_knowledge;
        $form = function () {
            $d = $this->input->post();
            $d['sections'] = array('en' => (array) $this->input->post('en'), 'ar' => (array) $this->input->post('ar'));
            return $d;
        };
        if ($op === 'create') {
            $this->post_guard();
            $this->attempt(function () use ($K, $form) { return $K->create($form(), $this->uid); }, hkp_t('Draft created.'), function ($sid) { return hkp_url('admin/content/edit/' . $sid); });
            return;
        }
        if ($op === 'save') {
            $this->post_guard();
            $this->attempt(function () use ($K, $form, $id) { $K->update_draft((int) $id, $form(), $this->uid); }, hkp_t('Draft saved.'));
            return;
        }
        if ($op === 'act') {
            $this->post_guard();
            $action = (string) $this->input->post('action');
            $this->attempt(function () use ($K, $id, $action) {
                if ($action === 'new_version') {
                    return $K->new_version((int) $id, $this->uid, (bool) $this->input->post('major'), $this->input->post('comment'));
                }
                if ($action === 'comment') {
                    return $K->comment((int) $id, $this->uid, $this->input->post('comment'));
                }
                return $K->act((int) $id, $action, $this->uid, $this->input->post('comment'));
            }, hkp_t('Done.'), hkp_url('admin/content/edit/' . (int) $id));
            return;
        }
        if ($op === 'ai_toggle') {
            $this->post_guard();
            $this->need('ai.govern');
            $doc = $this->db->get_where('ha_sop_document', array('id' => (int) $id))->row_array();
            if ($doc) {
                $this->db->where('id', $doc['id'])->update('ha_sop_document', array('ai_enabled' => (int) $doc['ai_enabled'] ? 0 : 1));
                $this->ha_audit->log('update', 'knowledge', $doc['id'], array('description' => 'AI source ' . ((int) $doc['ai_enabled'] ? 'excluded' : 'included')));
            }
            $this->back(hkp_t('AI source setting changed.'));
            return;
        }
        if ($op === 'new' || $op === 'edit') {
            $doc = $op === 'edit' ? $K->get($id, $this->input->get('v') ? (int) $this->input->get('v') : null) : null;
            if ($op === 'edit' && (!$doc || !$K->can_view($id, $this->uid))) {
                show_404();
            }
            $working = $doc ? $K->get($id, $doc['working']['id']) : null;
            $diff = null;
            if ($doc && $this->input->get('compare')) {
                $diff = $K->compare((int) $this->input->get('compare'), (int) ($this->input->get('with') ?: $doc['working']['id']));
            }
            $this->render('admin_content_edit', array('doc' => $doc, 'w' => $working, 'diff' => $diff, 'can_edit' => $doc ? $K->can_edit($doc, $this->uid) : $this->can('knowledge.create'),
                'types' => Ha_knowledge::$types, 'domains' => $this->db->order_by('sort_order')->get('ha_domain')->result_array(),
                'orgs' => $this->db->get('ha_organization')->result_array(), 'props' => $this->switchable_properties(),
                'roles' => $this->db->where('status', 'active')->get('ha_job_role')->result_array(),
                'reviewers' => $this->db->select('u.id, u.first_name, u.last_name')->from('users u')->join('ha_user_role ur', 'ur.user_id = u.id')->join('ha_role r', 'r.id = ur.role_id')
                    ->where_in('r.code', array('quality_reviewer', 'academy_admin', 'altus_admin', 'org_admin', 'super_admin'))->group_by(array('u.id', 'u.first_name', 'u.last_name'))->get()->result_array()),
                $doc ? hkp_t('Knowledge item') : hkp_t('New knowledge item'), 'content_review');
            return;
        }
        $this->render('admin_content', array('h' => $K->health($this->uid)), hkp_t('Content review & quality control'), 'content_review');
    }

    // ---------------------------------------------------------- assessments

    public function assessments($op = '', $id = 0) {
        $this->need(array('assessments.create', 'question_banks.view'));
        $this->load->library('ha_theory');
        if ($op === 'question') {
            $this->post_guard();
            $this->need('question_banks.create');
            $a = $this->db->get_where('ha_assessment', array('id' => (int) $id))->row_array();
            $this->attempt(function () use ($a) {
                if (!$a) {
                    throw new InvalidArgumentException('Assessment not found.');
                }
                $type = $this->input->post('question_type');
                if (!in_array($type, array('multiple_choice', 'true_false', 'multiple_response', 'matching', 'ordering', 'scenario', 'short_answer', 'essay'), true)) {
                    throw new InvalidArgumentException(hkp_t('Choose a question type.'));
                }
                $body = trim((string) $this->input->post('body_en'));
                if ($body === '') {
                    throw new InvalidArgumentException(hkp_t('Write the question.'));
                }
                $now = date('Y-m-d H:i:s');
                $this->db->insert('ha_question', array('bank_id' => $a['bank_id'], 'question_type' => $type, 'body_en' => $body, 'body_ar' => (string) $this->input->post('body_ar'),
                    'explanation_en' => $this->input->post('explanation_en'), 'explanation_ar' => $this->input->post('explanation_ar'),
                    'marks' => max(0.5, (float) $this->input->post('marks') ?: 1), 'difficulty' => $this->input->post('difficulty') ?: 'medium',
                    'skill_id' => $this->input->post('skill_id') ?: null, 'domain_id' => $a['domain_id'],
                    'requires_manual_grading' => $type === 'essay' ? 1 : 0, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
                $qid = (int) $this->db->insert_id();
                $opts = (array) $this->input->post('opt');
                $correct = array_map('intval', (array) $this->input->post('correct'));
                $n = 0;
                foreach ($opts as $i => $o) {
                    if (trim((string) $o['en']) === '') {
                        continue;
                    }
                    $this->db->insert('ha_question_option', array('question_id' => $qid, 'body_en' => trim($o['en']), 'body_ar' => trim((string) $o['ar']),
                        'match_key_en' => isset($o['match']) ? trim($o['match']) : null, 'is_correct' => in_array((int) $i, $correct, true) || $type === 'matching' ? 1 : 0, 'sort_order' => $n++));
                }
                $this->db->insert('ha_assessment_question', array('assessment_id' => $a['id'], 'question_id' => $qid, 'sort_order' => 999, 'section_label' => $this->input->post('section') ?: null));
                $this->ha_audit->log('create', 'question', $qid, array('description' => 'Question added to ' . $a['code']));
                return $qid;
            }, hkp_t('Question added.'));
            return;
        }
        if ($op === 'settings') {
            $this->post_guard();
            $this->need('assessments.update');
            $upd = array();
            foreach (array('pass_percentage', 'max_attempts', 'time_limit_minutes', 'random_question_count') as $k) {
                if ($this->input->post($k) !== null) {
                    $upd[$k] = max(0, (int) $this->input->post($k));
                }
            }
            foreach (array('shuffle_questions', 'shuffle_options', 'show_correct_answers') as $k) {
                $upd[$k] = $this->input->post($k) ? 1 : 0;
            }
            $upd['question_selection'] = $this->input->post('question_selection') === 'random' ? 'random' : 'fixed';
            $upd['status'] = in_array($this->input->post('status'), array('draft', 'published', 'archived'), true) ? $this->input->post('status') : 'draft';
            $upd['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', (int) $id)->update('ha_assessment', $upd);
            foreach ((array) $this->input->post('map') as $sid => $lvl) {
                if ((int) $lvl > 0) {
                    $this->db->query('REPLACE INTO ha_assessment_competency (assessment_id, skill_id, level_on_pass) VALUES (?,?,?)', array((int) $id, (int) $sid, (int) $lvl));
                } else {
                    $this->db->where(array('assessment_id' => (int) $id, 'skill_id' => (int) $sid))->delete('ha_assessment_competency');
                }
            }
            $this->ha_audit->log('update', 'assessment', (int) $id, array('after' => $upd));
            $this->back(hkp_t('Assessment saved.'));
            return;
        }
        if ($op === 'view') {
            $a = $this->db->get_where('ha_assessment', array('id' => (int) $id))->row_array();
            if (!$a) {
                show_404();
            }
            $qs = $this->db->select('q.*, aq.section_label')->from('ha_assessment_question aq')->join('ha_question q', 'q.id = aq.question_id')
                ->where('aq.assessment_id', (int) $id)->order_by('aq.sort_order')->get()->result_array();
            foreach ($qs as &$q) {
                $q['options'] = $this->db->order_by('sort_order')->get_where('ha_question_option', array('question_id' => $q['id']))->result_array();
            }
            unset($q);
            $map = array();
            foreach ($this->db->get_where('ha_assessment_competency', array('assessment_id' => (int) $id))->result_array() as $m) {
                $map[$m['skill_id']] = $m['level_on_pass'];
            }
            $this->render('admin_assessment', array('a' => $a, 'qs' => $qs, 'stats' => $this->ha_theory->question_stats($id), 'map' => $map,
                'skills' => $this->db->order_by('name_en')->get_where('ha_skill', array('status' => 'active'))->result_array()), hkp_pick($a, 'title'), 'assess_admin');
            return;
        }
        $rows = $this->db->select('a.*, (SELECT COUNT(*) FROM ha_assessment_question q WHERE q.assessment_id = a.id) questions,
                (SELECT COUNT(*) FROM ha_assessment_attempt t WHERE t.assessment_id = a.id AND t.status = \'graded\') attempts,
                (SELECT ROUND(100 * AVG(t.passed), 1) FROM ha_assessment_attempt t WHERE t.assessment_id = a.id AND t.status = \'graded\') pass_rate', false)
            ->from('ha_assessment a')->order_by('a.title_en')->get()->result_array();
        $this->render('admin_assessments', array('rows' => $rows), hkp_t('Assessments'), 'assess_admin');
    }

    // --------------------------------------------------------- competencies

    public function competencies($op = '', $id = 0) {
        $this->need(array('competencies.create', 'competencies.view'));
        if ($op === 'matrix_save') {
            $this->post_guard();
            $this->need('competencies.update');
            $role = (int) $id;
            $pk = (int) $this->input->post('property_key');
            if ($pk && !$this->ha_auth->can_property($pk)) {
                show_404();
            }
            foreach ((array) $this->input->post('req') as $sid => $lvl) {
                $lvl = (int) $lvl;
                if ($lvl <= 0) {
                    $this->db->where(array('job_role_id' => $role, 'skill_id' => (int) $sid, 'property_key' => $pk))->delete('ha_role_competency');
                    continue;
                }
                $crit = !empty($this->input->post('crit')[$sid]) ? 1 : 0;
                $this->db->query('INSERT INTO ha_role_competency (job_role_id, skill_id, property_key, required_level, is_critical, created_at, updated_at) VALUES (?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE required_level = VALUES(required_level), is_critical = VALUES(is_critical), updated_at = VALUES(updated_at)',
                    array($role, (int) $sid, $pk, min(5, $lvl), $crit, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')));
            }
            foreach (array('track', 'course', 'assessment', 'certification') as $t) {
                $this->db->where(array('job_role_id' => $role, 'property_key' => $pk, 'item_type' => $t))->delete('ha_role_requirement');
                foreach ((array) $this->input->post('req_' . $t) as $i => $item) {
                    $this->db->insert('ha_role_requirement', array('job_role_id' => $role, 'property_key' => $pk, 'item_type' => $t, 'item_id' => (int) $item,
                        'is_mandatory' => 1, 'due_days' => max(1, (int) $this->input->post('due_days') ?: 30), 'sort_order' => $i, 'created_at' => date('Y-m-d H:i:s')));
                }
            }
            $this->ha_audit->log('update', 'role_matrix', $role, array('description' => 'Role requirements saved (property ' . $pk . ')'));
            $this->back(hkp_t('Role requirements saved. Recalculate readiness to apply them.'));
            return;
        }
        if ($op === 'rubric_save') {
            $this->post_guard();
            $this->need('rubrics.update');
            $this->attempt(function () use ($id) {
                $d = $this->input->post();
                $row = array('title_en' => trim($d['title_en']), 'title_ar' => trim($d['title_ar']) ?: trim($d['title_en']), 'skill_id' => $d['skill_id'] ?: null,
                    'description_en' => $d['description_en'], 'developing_threshold' => (float) $d['developing_threshold'], 'pass_threshold' => (float) $d['pass_threshold'],
                    'exceeds_threshold' => (float) $d['exceeds_threshold'], 'status' => in_array($d['status'], array('draft', 'published', 'archived'), true) ? $d['status'] : 'draft',
                    'updated_at' => date('Y-m-d H:i:s'));
                if ($row['title_en'] === '' || !($row['developing_threshold'] < $row['pass_threshold'] && $row['pass_threshold'] < $row['exceeds_threshold'])) {
                    throw new InvalidArgumentException(hkp_t('Give a title, and thresholds in rising order (developing < competent < exceeds).'));
                }
                if ($id) {
                    $has_results = $this->db->where(array('rubric_id' => (int) $id, 'status' => 'submitted'))->count_all_results('ha_practical_assessment');
                    if ($has_results) {
                        $this->db->set('version_no', 'version_no + 1', false);
                    }
                    $this->db->where('id', (int) $id)->update('ha_rubric', $row);
                } else {
                    $row['code'] = 'rub-' . substr(bin2hex(random_bytes(4)), 0, 8);
                    $row['created_by'] = $this->uid;
                    $row['created_at'] = $row['updated_at'];
                    $this->db->insert('ha_rubric', $row);
                    $id = (int) $this->db->insert_id();
                }
                $this->db->where('rubric_id', (int) $id)->where('id NOT IN (SELECT criterion_id FROM ha_practical_score)', null, false)->delete('ha_rubric_criterion');
                foreach ((array) $this->input->post('crit') as $i => $c) {
                    if (trim((string) $c['label_en']) === '') {
                        continue;
                    }
                    $data = array('rubric_id' => (int) $id, 'label_en' => trim($c['label_en']), 'label_ar' => trim((string) $c['label_ar']) ?: trim($c['label_en']),
                        'weight' => max(0, (float) $c['weight']), 'is_critical' => !empty($c['critical']) ? 1 : 0, 'sort_order' => (int) $i);
                    if (!empty($c['id']) && $this->db->where(array('id' => (int) $c['id'], 'rubric_id' => (int) $id))->count_all_results('ha_rubric_criterion')) {
                        $this->db->where('id', (int) $c['id'])->update('ha_rubric_criterion', $data);
                    } else {
                        $this->db->insert('ha_rubric_criterion', $data);
                    }
                }
                $this->ha_audit->log('update', 'rubric', (int) $id, array('description' => 'Rubric saved'));
                return $id;
            }, hkp_t('Rubric saved.'), function ($rid) { return hkp_url('admin/competencies/rubric/' . $rid); });
            return;
        }
        if ($op === 'rubric') {
            $this->load->library('ha_practical');
            $r = $id ? $this->ha_practical->rubric($id) : null;
            $this->render('admin_rubric', array('r' => $r, 'skills' => $this->db->order_by('name_en')->get_where('ha_skill', array('status' => 'active'))->result_array()),
                $r ? hkp_pick($r, 'title') : hkp_t('New rubric'), 'comp_admin');
            return;
        }
        $role_id = (int) $this->input->get('role');
        $pk = (int) $this->input->get('property');
        $roles = $this->db->where('status', 'active')->order_by('title_en')->get('ha_job_role')->result_array();
        $req = array();
        $items = array();
        if ($role_id) {
            foreach ($this->db->get_where('ha_role_competency', array('job_role_id' => $role_id, 'property_key' => $pk))->result_array() as $r) {
                $req[$r['skill_id']] = $r;
            }
            foreach ($this->db->get_where('ha_role_requirement', array('job_role_id' => $role_id, 'property_key' => $pk))->result_array() as $r) {
                $items[$r['item_type']][] = (int) $r['item_id'];
            }
        }
        $this->render('admin_competencies', array('roles' => $roles, 'role_id' => $role_id, 'pk' => $pk, 'req' => $req, 'items' => $items,
            'skills' => $this->db->order_by('name_en')->get_where('ha_skill', array('status' => 'active'))->result_array(),
            'tracks' => $this->db->order_by('title_en')->get_where('ha_track', array('status' => 'published'))->result_array(),
            'assessments' => $this->db->order_by('title_en')->get_where('ha_assessment', array('status' => 'published'))->result_array(),
            'programs' => $this->db->get_where('ha_certification_program', array('status' => 'published'))->result_array(),
            'rubrics' => $this->db->order_by('title_en')->get('ha_rubric')->result_array(), 'props' => $this->switchable_properties()), hkp_t('Competencies'), 'comp_admin');
    }

    // --------------------------------------------------------------- rules

    public function rules($op = '', $id = 0) {
        $this->need(array('readiness.configure', 'certificates.issue', 'readiness.view'));
        $this->load->library(array('ha_readiness'));
        if ($op === 'policy_save') {
            $this->post_guard();
            $this->need('readiness.configure');
            $rules = array();
            $posted = (array) $this->input->post('r');
            foreach (Ha_readiness::default_rules() as $k => $d) {
                $in = isset($posted[$k]) ? (array) $posted[$k] : array();
                $rules[$k] = array('enabled' => !empty($in['enabled']) ? 1 : 0, 'on_fail' => isset($in['on_fail']) && $in['on_fail'] === 'conditional' ? 'conditional' : 'not_ready');
                if (isset($d['min_pct'])) {
                    $rules[$k]['min_pct'] = max(0, min(100, (float) (isset($in['min_pct']) ? $in['min_pct'] : 100)));
                }
                if (isset($d['max'])) {
                    $rules[$k]['max'] = max(0, (int) (isset($in['max']) ? $in['max'] : 0));
                }
            }
            $row = array('name_en' => trim((string) $this->input->post('name_en')) ?: 'Readiness policy', 'name_ar' => trim((string) $this->input->post('name_ar')) ?: 'سياسة الجاهزية',
                'job_role_id' => $this->input->post('job_role_id') ?: null, 'property_id' => $this->input->post('property_id') ?: null,
                'rules_json' => json_encode($rules), 'status' => 'active', 'updated_at' => date('Y-m-d H:i:s'));
            if ($id) {
                $this->db->where('id', (int) $id)->update('ha_readiness_policy', $row);
            } else {
                $row['code'] = 'pol-' . substr(bin2hex(random_bytes(4)), 0, 8);
                $row['created_at'] = $row['updated_at'];
                $this->db->insert('ha_readiness_policy', $row);
                $id = $this->db->insert_id();
            }
            $this->ha_audit->log('update', 'readiness_policy', (int) $id, array('after' => $rules));
            $this->back(hkp_t('Policy saved.'), true, hkp_url('admin/rules'));
            return;
        }
        if ($op === 'program_save') {
            $this->post_guard();
            $this->need('certificates.issue');
            $rules = array('tracks' => array_map('intval', (array) $this->input->post('tracks')), 'rubrics' => array_map('intval', (array) $this->input->post('rubrics')),
                'assessments' => array(), 'competencies' => array(), 'require_readiness' => $this->input->post('require_readiness') ?: null);
            foreach ((array) $this->input->post('assessments') as $aid) {
                $rules['assessments'][] = array('id' => (int) $aid, 'min_score' => (float) $this->input->post('min_score') ?: null);
            }
            foreach ((array) $this->input->post('comp') as $sid => $lvl) {
                if ((int) $lvl > 0) {
                    $rules['competencies'][] = array('skill_id' => (int) $sid, 'level' => (int) $lvl);
                }
            }
            $row = array('title_en' => trim((string) $this->input->post('title_en')), 'title_ar' => trim((string) $this->input->post('title_ar')),
                'job_role_id' => $this->input->post('job_role_id') ?: null, 'domain_id' => $this->input->post('domain_id') ?: null,
                'number_prefix' => strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $this->input->post('number_prefix'))) ?: 'ALTUS',
                'validity_months' => max(0, (int) $this->input->post('validity_months')), 'issuing_authority_en' => $this->input->post('issuing_authority_en') ?: 'Altus Advisory',
                'issuing_authority_ar' => $this->input->post('issuing_authority_ar') ?: 'ألتوس للاستشارات', 'rules_json' => json_encode($rules),
                'status' => in_array($this->input->post('status'), array('draft', 'published', 'archived'), true) ? $this->input->post('status') : 'draft', 'updated_at' => date('Y-m-d H:i:s'));
            if ($row['title_en'] === '') {
                $this->back(hkp_t('Give the programme a title.'), false);
                return;
            }
            if ($id) {
                $this->db->where('id', (int) $id)->update('ha_certification_program', $row);
            } else {
                $row['code'] = 'cert-' . substr(bin2hex(random_bytes(4)), 0, 8);
                $row['created_at'] = $row['updated_at'];
                $this->db->insert('ha_certification_program', $row);
            }
            $this->ha_audit->log('update', 'certification_program', (int) $id, array('after' => $rules));
            $this->back(hkp_t('Programme saved.'), true, hkp_url('admin/rules'));
            return;
        }
        $this->render('admin_rules', array('policies' => $this->db->get('ha_readiness_policy')->result_array(), 'programs' => $this->db->get('ha_certification_program')->result_array(),
            'edit_policy' => $op === 'policy' ? $this->db->get_where('ha_readiness_policy', array('id' => (int) $id))->row_array() : null,
            'edit_program' => $op === 'program' ? $this->db->get_where('ha_certification_program', array('id' => (int) $id))->row_array() : null,
            'new' => $op, 'roles' => $this->db->where('status', 'active')->order_by('title_en')->get('ha_job_role')->result_array(),
            'tracks' => $this->db->order_by('title_en')->get_where('ha_track', array('status' => 'published'))->result_array(),
            'rubrics' => $this->db->get_where('ha_rubric', array('status' => 'published'))->result_array(), 'domains' => $this->db->get('ha_domain')->result_array(),
            'assessments' => $this->db->order_by('title_en')->get_where('ha_assessment', array('status' => 'published'))->result_array(),
            'skills' => $this->db->order_by('name_en')->get_where('ha_skill', array('status' => 'active'))->result_array(), 'props' => $this->switchable_properties()),
            hkp_t('Readiness & certification rules'), 'rules');
    }

    // ------------------------------------------------------------------ AI

    public function ai($op = '') {
        $this->need('ai.govern');
        $this->load->library(array('ha_governed_ai', 'ha_ai_gateway'));
        if ($op === 'save') {
            $this->post_guard();
            $this->attempt(function () {
                foreach (array('ai.enabled', 'ai.max_sources', 'ai.min_relevance', 'ai.max_answer_tokens', 'ai.daily_limit_per_user', 'ai.system_instructions') as $k) {
                    $v = $this->input->post(str_replace('.', '_', $k));
                    if ($k === 'ai.enabled') {
                        $v = $v ? 1 : 0;
                    }
                    if ($v !== null) {
                        $this->ha_tenant->set($k, $v, 'global', 0, $this->uid);
                    }
                }
                foreach ((array) $this->input->post('org_enabled') as $org => $on) {
                    $this->ha_tenant->set('ai.enabled', $on ? 1 : 0, 'organization', (int) $org, $this->uid);
                }
            }, hkp_t('AI governance saved.'));
            return;
        }
        if ($op === 'reindex') {
            $this->post_guard();
            $r = $this->ha_governed_ai->reindex_all();
            $this->ha_audit->log('update', 'ai_index', null, array('description' => 'Reindexed: ' . json_encode($r)));
            $this->back(hkp_t('Index rebuilt: {k} knowledge and {l} lesson passages.', array('k' => $r['knowledge_chunks'], 'l' => $r['lesson_chunks'])));
            return;
        }
        $route = null;
        foreach (array('knowledge_answer', 'assistant') as $t) {
            if (!$route && ($r = $this->ha_ai_gateway->route($t))) {
                $route = $r + array('task' => $t);
            }
        }
        $orgs = array();
        foreach ($this->db->get('ha_organization')->result_array() as $o) {
            $o['ai_enabled'] = $this->ha_tenant->get('ai.enabled', null, $o['id']);
            $orgs[] = $o;
        }
        $this->render('admin_ai', array('status' => $this->ha_governed_ai->index_status(), 'route' => $route, 'orgs' => $orgs,
            'queries' => $this->db->select('q.*, u.first_name, u.last_name')->from('ha_ai_query q')->join('users u', 'u.id = q.user_id', 'left')->order_by('q.id', 'DESC')->limit(60)->get()->result_array(),
            'coverage' => $this->db->select('coverage, COUNT(*) n', false)->where('created_at >=', date('Y-m-d H:i:s', strtotime('-30 days')))->group_by('coverage')->get('ha_ai_query')->result_array(),
            'excluded' => $this->db->select('d.id, d.code, t.title')->from('ha_sop_document d')->join('ha_sop_version_translation t', "t.version_id = d.current_version_id AND t.locale = 'en'", 'left')
                ->where(array('d.status' => 'published', 'd.ai_enabled' => 0))->get()->result_array(),
            'prompt' => $this->ha_governed_ai->governance_prompt('en'), 'sources' => $this->db->select('d.id, d.code, d.ai_enabled, t.title')->from('ha_sop_document d')
                ->join('ha_sop_version_translation t', "t.version_id = d.current_version_id AND t.locale = 'en'", 'left')->where('d.status', 'published')->order_by('t.title')->get()->result_array()),
            hkp_t('AI governance'), 'ai_gov');
    }

    // --------------------------------------------------------- engagements

    public function engagements($op = '', $id = 0) {
        $this->need('engagements.view');
        $this->load->library(array('ha_advisory', 'ha_files'));
        $A = $this->ha_advisory;
        if ($op === 'create') {
            $this->post_guard();
            $this->attempt(function () use ($A) { return $A->create_engagement($this->input->post(), $this->uid); }, hkp_t('Engagement created with the six Ascent stages.'),
                function ($eid) { return hkp_url('admin/engagements/view/' . $eid); });
            return;
        }
        if ($op === 'stage') {
            $this->post_guard();
            $this->attempt(function () use ($A, $id) { $A->update_stage((int) $id, $this->input->post(), $this->uid); }, hkp_t('Stage updated.'));
            return;
        }
        if ($op === 'task') {
            $this->post_guard();
            $this->attempt(function () use ($A, $id) {
                $d = $this->input->post();
                if (!empty($_FILES['file']['name'])) {
                    $f = $this->ha_files->store_upload($_FILES['file'], array('owner_user_id' => $this->uid, 'entity_type' => 'engagement'));
                    $d['file_id'] = $f['id'];
                }
                return $A->add_task((int) $id, $d, $this->uid);
            }, hkp_t('Task added.'));
            return;
        }
        if ($op === 'task_status') {
            $this->post_guard();
            $this->attempt(function () use ($A, $id) { $A->set_task_status((int) $id, $this->input->post('status')); }, hkp_t('Task updated.'));
            return;
        }
        if ($op === 'view') {
            $e = $A->engagement($id);
            if (!$e) {
                show_404();
            }
            $this->render('admin_engagement', array('e' => $e, 'frameworks' => $this->db->get('ha_framework')->result_array()), $e['name'], 'engagements');
            return;
        }
        $db = $this->db->select('e.*, o.name_en AS org_en, p.name_en AS prop_en')->from('ha_engagement e')->join('ha_organization o', 'o.id = e.organization_id')->join('ha_property p', 'p.id = e.property_id', 'left');
        $this->ha_auth->scope_query($db, array('organization_id' => 'e.organization_id'));
        $this->render('admin_engagements', array('rows' => $db->order_by('e.id', 'DESC')->get()->result_array(), 'orgs' => $this->db->get('ha_organization')->result_array(),
            'props' => $this->db->get('ha_property')->result_array()), hkp_t('Engagements'), 'engagements');
    }

    public function frameworks($op = '', $id = 0) {
        $this->need('frameworks.view');
        $this->load->library('ha_advisory');
        $A = $this->ha_advisory;
        if ($op === 'start') {
            $this->post_guard();
            $this->attempt(function () use ($A) { return $A->start_assessment((int) $this->input->post('framework_id'), $this->input->post(), $this->uid); },
                hkp_t('Assessment started.'), function ($aid) { return hkp_url('admin/frameworks/assess/' . $aid); });
            return;
        }
        if ($op === 'assess') {
            $a = $A->assessment($id);
            if (!$a) {
                show_404();
            }
            if ($this->input->method() === 'post') {
                $this->post_guard();
                $this->attempt(function () use ($A, $id) { return $A->save_answers((int) $id, (array) $this->input->post('a'), $this->input->post('complete') === '1', $this->input->post('notes')); },
                    hkp_t('Assessment saved.'));
                return;
            }
            $this->render('admin_framework_assess', array('a' => $a), $a['title'], 'frameworks');
            return;
        }
        if ($op === 'config') {
            $this->post_guard();
            $this->need('frameworks.configure');
            $f = $A->framework($id);
            $cfg = json_decode((string) $this->input->post('config_json'), true);
            if (!$f || !is_array($cfg)) {
                $this->back(hkp_t('The configuration must be valid JSON.'), false);
                return;
            }
            $this->db->where('id', $f['id'])->update('ha_framework', array('config_json' => json_encode($cfg, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')));
            $this->ha_audit->log('update', 'framework', $f['id'], array('after' => $cfg));
            $this->back(hkp_t('Scoring configuration saved.'));
            return;
        }
        $fw = array();
        foreach ($this->db->get('ha_framework')->result_array() as $f) {
            $fw[] = $A->framework($f['id']);
        }
        $db = $this->db->select('a.*, f.name_en AS fw_en, f.name_ar AS fw_ar, o.name_en AS org_en')->from('ha_framework_assessment a')->join('ha_framework f', 'f.id = a.framework_id')->join('ha_organization o', 'o.id = a.organization_id');
        $this->ha_auth->scope_query($db, array('organization_id' => 'a.organization_id'));
        $this->render('admin_frameworks', array('fw' => $fw, 'rows' => $db->order_by('a.id', 'DESC')->get()->result_array(), 'orgs' => $this->db->get('ha_organization')->result_array(),
            'props' => $this->db->get('ha_property')->result_array(), 'engagements' => $this->db->get('ha_engagement')->result_array()), hkp_t('Advisory frameworks'), 'frameworks');
    }

    // ------------------------------------------------------------ corporate

    public function corporate() {
        $this->need('corporate.view');
        $counts = array();
        foreach (array('corporate_blocks' => 'ha_corporate_block', 'case_studies' => 'ha_case_study', 'leadership' => 'ha_leadership_profile', 'partners' => 'ha_partner',
            'sectors' => 'ha_sector', 'services' => 'ha_service') as $k => $t) {
            $counts[$k] = $this->db->count_all($t);
        }
        $this->render('admin_corporate', array('counts' => $counts), hkp_t('Corporate CMS'), 'corporate');
    }

    // --------------------------------------------------------------- imports

    public function imports($op = '', $id = 0) {
        $this->need('imports.run');
        $this->load->library('ha_importer');
        if ($op === 'preview') {
            $this->post_guard();
            $this->attempt(function () {
                if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                    throw new InvalidArgumentException(hkp_t('Choose a CSV file.'));
                }
                if (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
                    throw new InvalidArgumentException(hkp_t('Save the sheet as CSV (UTF-8) first.'));
                }
                return $this->ha_importer->preview($this->input->post('type'), $_FILES['file']['tmp_name'], $_FILES['file']['name'],
                    (int) ($this->input->post('organization_id') ?: $this->ctx['organization_id']));
            }, hkp_t('Preview ready. Nothing has been written yet.'), function ($rid) { return hkp_url('admin/imports/view/' . $rid); });
            return;
        }
        if ($op === 'commit' || $op === 'rollback') {
            $this->post_guard();
            $this->attempt(function () use ($op, $id) { return $op === 'commit' ? $this->ha_importer->commit((int) $id) : $this->ha_importer->rollback((int) $id); },
                $op === 'commit' ? hkp_t('Import committed.') : hkp_t('Import rolled back.'), hkp_url('admin/imports/view/' . (int) $id));
            return;
        }
        if ($op === 'template') {
            $t = Ha_importer::types();
            $type = $this->input->get('type');
            if (!isset($t[$type])) {
                show_404();
            }
            $this->output->set_content_type('text/csv', 'utf-8')->set_header('Content-Disposition: attachment; filename="' . $type . '-template.csv"')
                ->set_output("\xEF\xBB\xBF" . implode(',', $t[$type]) . "\n");
            return;
        }
        $run = $op === 'view' ? $this->db->get_where('ha_import_run', array('id' => (int) $id))->row_array() : null;
        if ($run && !$this->ha_auth->can_organization($run['organization_id'])) {
            show_404();
        }
        $this->render('admin_imports', array('types' => Ha_importer::types(), 'run' => $run, 'type' => $this->input->get('type'),
            'runs' => $this->db->order_by('id', 'DESC')->limit(30)->get('ha_import_run')->result_array(), 'orgs' => $this->db->get('ha_organization')->result_array()),
            hkp_t('Imports'), 'imports');
    }

    // ----------------------------------------------------------------- audit

    public function audit() {
        $this->need('audit_logs.view');
        $f = array('action' => $this->input->get('action'), 'entity' => $this->input->get('entity'), 'user' => $this->input->get('user'), 'from' => $this->input->get('from'));
        $db = $this->db->from('ha_audit_log');
        if (!$this->ha_auth->is_system_scoped()) {
            $this->ha_auth->scope_query($db, array('organization_id' => 'organization_id', 'property_id' => 'property_id'), true);
        }
        foreach (array('action' => 'action', 'entity' => 'entity_type') as $k => $c) {
            if ($f[$k]) {
                $db->where($c, $f[$k]);
            }
        }
        if ($f['user']) {
            $db->like('actor_name', $f['user']);
        }
        if ($f['from']) {
            $db->where('created_at >=', $f['from']);
        }
        $rows = $db->order_by('id', 'DESC')->limit(300)->get()->result_array();
        if ($this->input->get('format') === 'csv') {
            $this->need('audit_logs.export');
            $out = "\xEF\xBB\xBFid,time,actor,action,entity,entity_id,description,ip\n";
            foreach ($rows as $r) {
                $out .= implode(',', array_map(function ($v) { return '"' . str_replace('"', '""', (string) $v) . '"'; },
                    array($r['id'], $r['created_at'], $r['actor_name'], $r['action'], $r['entity_type'], $r['entity_id'], $r['description'], $r['ip_address']))) . "\n";
            }
            $this->ha_audit->log('export', 'audit_log', null, array('description' => count($rows) . ' audit rows exported'));
            $this->output->set_content_type('text/csv')->set_header('Content-Disposition: attachment; filename="audit.csv"')->set_output($out);
            return;
        }
        $this->render('admin_audit', array('rows' => $rows, 'f' => $f), hkp_t('Audit logs'), 'audit_log');
    }

    // ---------------------------------------------------------------- system

    public function system($op = '') {
        $this->need(array('system.health', 'settings.update'));
        if ($op === 'settings') {
            $this->post_guard();
            $this->need(array('system.configure', 'settings.update'));
            $this->attempt(function () {
                foreach ((array) $this->input->post('s') as $k => $v) {
                    if (isset(Ha_tenant::registry()[$k]) && $v !== '') {
                        $this->ha_tenant->set($k, $v, 'global', 0, $this->uid);
                    }
                }
            }, hkp_t('Settings saved.'));
            return;
        }
        if ($op === 'rules') {
            $this->post_guard();
            $this->need(array('notifications.update', 'system.configure', 'settings.update'));
            foreach ((array) $this->input->post('rule') as $ev => $r) {
                $this->db->query('REPLACE INTO ha_notification_rule (event_code, organization_id, enabled, channels, notify_manager, updated_at) VALUES (?, 0, ?, ?, ?, ?)',
                    array($ev, !empty($r['enabled']) ? 1 : 0, implode(',', array_intersect(array('in_app', 'email', 'sms', 'whatsapp'), (array) (isset($r['ch']) ? $r['ch'] : array()))) ?: 'in_app',
                        !empty($r['mgr']) ? 1 : 0, date('Y-m-d H:i:s')));
            }
            foreach ((array) $this->input->post('tpl') as $tid => $t) {
                $this->db->where('id', (int) $tid)->update('ha_notification_template', array('subject' => mb_substr($t['subject'], 0, 255), 'body' => $t['body'], 'updated_by' => $this->uid, 'updated_at' => date('Y-m-d H:i:s')));
            }
            $this->back(hkp_t('Notification rules saved.'));
            return;
        }
        if ($op === 'run') {
            $this->post_guard();
            $this->load->library('ha_cli_runner');
            $ok = $this->ha_cli_runner->spawn(array('hkp_cli', 'daily'));
            $this->back($ok ? hkp_t('Daily jobs started in the background.') : hkp_t('This host does not allow background processes. Schedule the cron job shown below.'), $ok);
            return;
        }
        $db = $this->db;
        $health = array(
            'database' => array('ok' => (bool) $db->conn_id, 'detail' => $db->platform() . ' ' . $db->version()),
            'queue' => array('ok' => true, 'detail' => $db->where('status', 'queued')->count_all_results('ha_queue_job') . ' queued · ' . $db->where('status', 'failed')->count_all_results('ha_queue_job') . ' failed'),
            'email' => array('ok' => !$db->where(array('channel' => 'email', 'delivery_status' => 'failed'))->where('created_at >=', date('Y-m-d H:i:s', strtotime('-1 day')))->count_all_results('ha_notification'),
                'detail' => $db->where(array('channel' => 'email', 'delivery_status' => 'pending'))->count_all_results('ha_notification') . ' pending · ' . $db->where(array('channel' => 'email', 'delivery_status' => 'failed'))->count_all_results('ha_notification') . ' failed'),
            'storage' => array('ok' => is_writable(APPPATH . 'storage') || @mkdir(APPPATH . 'storage/private', 0775, true), 'detail' => round(@disk_free_space(FCPATH) / 1073741824, 1) . ' GB free'),
            'ai' => array('ok' => (bool) $db->where('enabled', 1)->count_all_results('ha_ai_provider'),
                'detail' => $db->where('enabled', 1)->count_all_results('ha_ai_provider') . ' AI providers enabled · ' . $db->count_all('ha_ai_chunk') . ' approved passages indexed'),
            'scheduler' => array('ok' => is_file(APPPATH . 'cache/hkp_daily.json') && filemtime(APPPATH . 'cache/hkp_daily.json') > time() - 2 * 86400,
                'detail' => is_file(APPPATH . 'cache/hkp_daily.json') ? 'last run ' . date('Y-m-d H:i', filemtime(APPPATH . 'cache/hkp_daily.json')) : 'never run'),
            'backup' => array('ok' => (bool) glob(FCPATH . 'backups/*'), 'detail' => ($b = glob(FCPATH . 'backups/*')) ? count($b) . ' files, latest ' . date('Y-m-d', max(array_map('filemtime', $b))) : 'no backup files found in /backups'),
            'api' => array('ok' => true, 'detail' => $db->where('revoked_at IS NULL', null, false)->count_all_results('ha_api_key') . ' active API keys'),
        );
        $errors = array();
        $logs = glob(APPPATH . 'logs/log-*.php');
        if ($logs) {
            rsort($logs);
            foreach (array_slice(file($logs[0]), -40) as $l) {
                if (strpos($l, 'ERROR') === 0) {
                    $errors[] = mb_substr(preg_replace('~[A-Z]:\\\\[^ ]+~', '[path]', $l), 0, 240);   // never show file paths
                }
            }
        }
        $settings = array();
        foreach (Ha_tenant::registry() as $k => $meta) {
            $settings[$k] = array('meta' => $meta, 'value' => $this->ha_tenant->get($k));
        }
        $this->render('admin_system', array('health' => $health, 'errors' => array_slice(array_reverse($errors), 0, 12), 'settings' => $settings,
            'rules' => $this->db->get_where('ha_notification_rule', array('organization_id' => 0))->result_array(),
            'templates' => $this->db->order_by('event_code')->get_where('ha_notification_template', array('organization_id' => 0))->result_array()), hkp_t('System'), 'system');
    }
}
