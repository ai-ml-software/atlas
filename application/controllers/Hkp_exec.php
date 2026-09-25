<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Hkp_Controller.php';

/**
 * Owner / executive view (ppt-features 25, 121, 176-178: "Are capabilities
 * improving and where is risk?"). Aggregates only: no employee names, so
 * executive information stays separate from learner information (rule 13).
 */
class Hkp_exec extends Hkp_Controller {

    public function index() {
        $this->need('executive.view');
        $this->load->library(array('ha_reports', 'ha_kpi'));
        $f = $this->filters();
        $b = $this->ha_reports->board($f);
        $props = $this->scoped_properties();
        $kpis = array();
        foreach ($props as $p) {
            foreach ($this->ha_kpi->scorecard($p['id'], $p['organization_id']) as $k) {
                if ($k['value'] && in_array($k['code'], array('revpar', 'goppar', 'occupancy', 'pickup', 'guest_sentiment', 'payroll_productivity'), true)) {
                    $kpis[$p['id']][$k['code']] = $k;
                }
            }
        }
        $this->render('exec_dashboard', array('b' => $b, 'props' => $props, 'kpis' => $kpis, 'f' => $f,
            'depts' => $this->db->where('status', 'active')->get('ha_department')->result_array()), hkp_t('Executive overview'), 'exec');
    }

    public function board() {
        $this->need('executive.view');
        $this->load->library('ha_reports');
        $f = $this->filters();
        $this->ha_reports->log_run('board', 'print', $f, 0);
        // Resolve the scoped lists before building each query: they run queries of their own.
        $people = $this->ha_reports->people($f) ?: array(0);
        $orgs = $this->ha_auth->organization_ids() ?: array((int) $this->ctx['organization_id']);
        $board = $this->ha_reports->board($f);
        $lines = $this->ha_reports->context_lines($f);
        $this->render('exec_board', array('b' => $board, 'lines' => $lines, 'f' => $f,
            'actions' => $this->db->select('ap.status, COUNT(*) n', false)->from('ha_action_plan ap')->where_in('ap.user_id', $people)->group_by('ap.status')->get()->result_array(),
            'engagements' => $this->db->where_in('organization_id', $orgs)->where('status', 'active')->get('ha_engagement')->result_array()),
            hkp_t('Board report'), 'board');
    }

    protected function filters() {
        $f = array('property_id' => (int) $this->input->get('property_id') ?: ($this->session->userdata('hkp_property') ?: null),
            'department_id' => (int) $this->input->get('department_id') ?: null, 'from' => $this->input->get('from'), 'to' => $this->input->get('to'));
        if ($f['property_id'] && !$this->ha_auth->can_property($f['property_id'])) {
            $f['property_id'] = null;
        }
        return $f;
    }

    protected function scoped_properties() {
        $db = $this->db->select('p.id, p.name_en, p.name_ar, p.organization_id')->from('ha_property p')->where('p.status', 'active');
        $this->ha_auth->scope_query($db, array('organization_id' => 'p.organization_id', 'property_id' => 'p.id'));
        return $db->get()->result_array();
    }
}
