<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller for the altus Hospitality Knowledge & Performance workspace.
 *
 * Every workspace request passes through here: sign-in check (the same session
 * the Academy LMS login writes), locale, tenant branding, security headers,
 * CSRF on every POST, and the permission-generated navigation. Controllers
 * call need() before reading or writing; the navigation only mirrors those
 * checks, it is never the control (ppt-features 35, 63, 77, 152).
 */
abstract class Hkp_Controller extends CI_Controller {

    protected $uid;
    protected $brand;
    protected $ctx;

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper(array('url', 'hkp', 'ha_security'));
        $this->load->library(array('ha_auth', 'ha_tenant', 'ha_notify', 'ha_audit'));
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->output->set_header('X-Frame-Options: SAMEORIGIN');
        $this->output->set_header('X-Content-Type-Options: nosniff');
        $this->output->set_header('Referrer-Policy: same-origin');
        $this->output->set_header('X-Robots-Tag: noindex, nofollow');   // authenticated pages never become search results
        if (function_exists('get_settings') && get_settings('timezone')) {
            @date_default_timezone_set(get_settings('timezone'));
        }
        $lang = $this->input->get('lang');
        if ($lang === 'en' || $lang === 'ar') {
            $this->session->set_userdata('hkp_locale', $lang);
        }
        if ($this->public_action()) {
            hkp_locale();
            return;
        }
        if (!$this->ha_auth->check()) {
            if ($this->input->is_ajax_request()) {
                $this->json(array('ok' => false, 'error' => 'Please sign in again.'), 401);
                $this->output->_display();
                exit;
            }
            $this->session->set_userdata('hkp_return', current_url());
            redirect(site_url('login'), 'refresh');
        }
        $this->uid = (int) $this->ha_auth->id();
        $idle = (int) $this->ha_tenant->get('security.idle_minutes');
        $last = (int) $this->session->userdata('hkp_seen');
        if ($idle > 0 && $last && time() - $last > $idle * 60) {
            $this->session->sess_destroy();
            redirect(site_url('login'), 'refresh');
        }
        $this->session->set_userdata('hkp_seen', time());
        hkp_locale();
        $this->ctx = $this->ha_tenant->context();
        $this->brand = $this->ha_tenant->brand($this->ctx['property_id'], $this->ctx['organization_id']);
    }

    /** Actions reachable without signing in (certificate verification, manifest). */
    protected function public_action() {
        return false;
    }

    // ------------------------------------------------------------- guards

    protected function can($perm) {
        return $this->ha_auth->has($perm);
    }

    protected function need($perm) {
        if ($this->ha_auth->has($perm)) {
            return true;
        }
        if ($this->input->is_ajax_request() || $this->input->method() === 'post') {
            $this->json(array('ok' => false, 'error' => hkp_t('You do not have permission to do that.')), 403);
            $this->output->_display();
            exit;
        }
        $this->output->set_status_header(403);
        $this->render('forbidden', array('permission' => is_array($perm) ? implode(' | ', $perm) : $perm), hkp_t('Access denied'));
        $this->output->_display();
        exit;
    }

    protected function post_guard() {
        if ($this->input->method() !== 'post' || !ha_csrf_valid()) {
            if ($this->input->is_ajax_request()) {
                $this->json(array('ok' => false, 'error' => hkp_t('Your session expired. Reload the page and try again.')), 403);
                $this->output->_display();
                exit;
            }
            $this->back(hkp_t('Your session expired. Reload the page and try again.'), false);
            $this->output->_display();
            exit;
        }
    }

    protected function json($data, $status = 200) {
        $this->output->set_status_header($status)->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function back($message, $ok = true, $to = null) {
        $this->session->set_flashdata($ok ? 'hkp_ok' : 'hkp_error', $message);
        $ref = $this->input->server('HTTP_REFERER');
        $base = site_url();
        if (!$to && $ref && strpos($ref, $base) === 0) {
            $to = $ref;
        }
        redirect($to ?: hkp_url(), 'refresh');
    }

    /** Runs a mutating action and turns validation / permission exceptions into a message. */
    protected function attempt(callable $fn, $success, $to = null) {
        try {
            $result = $fn();
            $this->back($success, true, is_callable($to) ? $to($result) : $to);
        } catch (InvalidArgumentException $e) {
            $this->back($e->getMessage(), false);
        } catch (RuntimeException $e) {
            $this->back($e->getMessage(), false);
        } catch (Exception $e) {
            log_message('error', 'hkp: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->back(hkp_t('Something went wrong. Please try again or contact your administrator.'), false);
        }
    }

    protected function visible_users() {
        return $this->ha_auth->visible_user_ids();
    }

    /** Team members only (excludes the signed-in user) inside the current property context when one is chosen. */
    protected function team_ids() {
        $ids = $this->visible_users();
        $chosen = (int) $this->session->userdata('hkp_property');
        if ($chosen && $ids && $this->ha_auth->can_property($chosen)) {
            $rows = $this->db->select('user_id')->where_in('user_id', $ids)->where('property_id', $chosen)->get('ha_profile')->result_array();
            $ids = array_map('intval', array_column($rows, 'user_id'));
        }
        return array_values(array_diff($ids, array($this->uid)));
    }

    // ------------------------------------------------------------ render

    protected function render($view, array $data = array(), $title = '', $active = '') {
        $data['page_title'] = $title;
        $data['active'] = $active;
        $data['brand'] = $this->brand;
        $data['ctx'] = $this->ctx;
        $data['nav'] = $this->navigation();
        $data['me'] = $this->ha_auth->user();
        $data['me_profile'] = $this->ha_auth->profile();
        $data['unread'] = $this->uid ? $this->ha_notify->unread_count($this->uid) : 0;
        $data['properties'] = $this->switchable_properties();
        $data['ok'] = $this->session->flashdata('hkp_ok');
        $data['error'] = $this->session->flashdata('hkp_error');
        $data['content_view'] = 'hkp/' . $view;
        $data['is_admin_login'] = (bool) $this->session->userdata('admin_login');
        $this->load->view('hkp/layout', $data);
    }

    protected function switchable_properties() {
        if (!$this->uid) {
            return array();
        }
        $auth = $this->ha_auth;
        if (!$auth->is_system_scoped() && !$auth->organization_ids() && count($auth->property_ids()) < 2) {
            return array();
        }
        $db = $this->db->select('p.id, p.name_en, p.name_ar, o.name_en AS org_en, o.name_ar AS org_ar')->from('ha_property p')
            ->join('ha_organization o', 'o.id = p.organization_id')->where('p.status', 'active');
        $auth->scope_query($db, array('organization_id' => 'p.organization_id', 'property_id' => 'p.id'));
        return $db->order_by('o.name_en')->order_by('p.name_en')->get()->result_array();
    }

    /**
     * The navigation, generated from permissions (section 77). Each item is
     * array(key, label, path, icon, permission|null). A section appears when at
     * least one of its items is allowed.
     */
    protected function navigation() {
        $sections = array(
            'learner' => array(hkp_t('My workspace'), array(
                array('home', hkp_t('Dashboard'), '', 'home', null),
                array('learn', hkp_t('My learning'), 'learn', 'book', 'courses.view'),
                array('paths', hkp_t('Learning paths'), 'learn/paths', 'route', 'courses.view'),
                array('knowledge', hkp_t('Knowledge'), 'knowledge', 'library', 'knowledge.view'),
                array('assess', hkp_t('Assessments'), 'assess', 'check', 'assessments.view'),
                array('competencies', hkp_t('Competencies'), 'competencies', 'target', 'competencies.view'),
                array('actions', hkp_t('Action plans'), 'actions', 'flag', 'action_plans.view'),
                array('certificates', hkp_t('Certificates'), 'certificates', 'award', 'certificates.view'),
                array('assistant', hkp_t('AI assistant'), 'assistant', 'spark', 'ai.use'),
            )),
            'manager' => array(hkp_t('Property management'), array(
                array('team_home', hkp_t('Team dashboard'), 'team', 'chart', array('learners.view', 'practicals.assess')),
                array('assessor', hkp_t('Assessor queue'), 'assess/queue', 'clipboard', 'practicals.assess'),
                array('team', hkp_t('Team'), 'team/people', 'users', 'learners.view'),
                array('assign', hkp_t('Assign learning'), 'team/assign', 'send', 'training_assignments.assign'),
                array('cohorts', hkp_t('Cohorts'), 'team/cohorts', 'group', 'cohorts.view'),
                array('gaps', hkp_t('Gaps'), 'team/gaps', 'alert', 'gaps.view'),
                array('team_actions', hkp_t('Actions'), 'team/actions', 'flag', 'action_plans.review'),
                array('readiness', hkp_t('Readiness'), 'team/readiness', 'gauge', 'readiness.view'),
                array('opening', hkp_t('Opening readiness'), 'team/opening', 'building', 'readiness.view'),
                array('team_certs', hkp_t('Certifications'), 'team/certifications', 'award', 'certificates.export'),
                array('audits', hkp_t('Quality audits'), 'team/audits', 'shield', 'quality_audits.view'),
                array('kpis', hkp_t('KPIs'), 'team/kpis', 'pulse', 'kpis.view'),
                array('reports', hkp_t('Reports'), 'team/reports', 'file', 'reports.view'),
                array('branding', hkp_t('Branding & settings'), 'team/branding', 'palette', 'branding.update'),
            )),
            'altus' => array(hkp_t('Altus team'), array(
                array('altus_home', hkp_t('Portfolio dashboard'), 'admin', 'globe', array('organizations.view', 'analytics.view')),
                array('orgs', hkp_t('Organisations'), 'admin/crud/organizations', 'building', 'organizations.view'),
                array('props', hkp_t('Properties'), 'admin/crud/properties', 'home', 'properties.view'),
                array('people_admin', hkp_t('Users'), 'admin/users', 'users', 'users.view'),
                array('curriculum', hkp_t('Curriculum'), 'admin/curriculum', 'layers', 'curriculum.view'),
                array('content_review', hkp_t('Content review'), 'admin/content', 'check', array('knowledge.review', 'knowledge.approve', 'knowledge.create')),
                array('assess_admin', hkp_t('Assessments'), 'admin/assessments', 'clipboard', 'assessments.create'),
                array('comp_admin', hkp_t('Competencies'), 'admin/competencies', 'target', 'competencies.create'),
                array('rules', hkp_t('Readiness & certification'), 'admin/rules', 'gauge', array('readiness.configure', 'certificates.issue')),
                array('ai_gov', hkp_t('AI governance'), 'admin/ai', 'spark', 'ai.govern'),
                array('engagements', hkp_t('Engagements'), 'admin/engagements', 'compass', 'engagements.view'),
                array('frameworks', hkp_t('Frameworks'), 'admin/frameworks', 'grid', 'frameworks.view'),
                array('cms_pages', hkp_t('Website pages'), 'cms', 'pen', 'cms_pages.view'),
                array('cms_modules', hkp_t('Modules & lessons'), 'cms/modules', 'book', array('courses.update', 'lessons.update')),
                array('corporate', hkp_t('Corporate CMS'), 'admin/corporate', 'library', 'corporate.view'),
                array('imports', hkp_t('Imports'), 'admin/imports', 'upload', 'imports.run'),
                array('audit_log', hkp_t('Audit logs'), 'admin/audit', 'list', 'audit_logs.view'),
                array('system', hkp_t('System'), 'admin/system', 'cog', array('system.health', 'settings.update')),
            )),
            'executive' => array(hkp_t('Executive'), array(
                array('exec', hkp_t('Executive overview'), 'exec', 'crown', 'executive.view'),
                array('board', hkp_t('Board report'), 'exec/board', 'file', 'executive.view'),
            )),
        );
        $out = array();
        foreach ($sections as $key => $s) {
            $items = array();
            foreach ($s[1] as $it) {
                if ($it[4] === null || $this->ha_auth->has($it[4])) {
                    $items[] = array('key' => $it[0], 'label' => $it[1], 'url' => hkp_url($it[2]), 'icon' => $it[3]);
                }
            }
            if ($items) {
                $out[$key] = array('label' => $s[0], 'items' => $items);
            }
        }
        return $out;
    }
}
