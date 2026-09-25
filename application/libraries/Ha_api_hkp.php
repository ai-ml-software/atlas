<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read models behind the HK&P endpoints of /api/v1 (spec sections 53, 135).
 *
 * Every method works for the identity Ha_auth currently holds (the API key's
 * owner) and applies the same tenant scope as the web screens: visible_user_ids()
 * for people, can_user() for one person, can_property() for a property. Output
 * is a public shape, never raw rows, so the database structure is not exposed.
 * A method throws Ha_api_denied when the person may not see what was asked.
 */
class Ha_api_denied extends RuntimeException {}

class Ha_api_hkp {

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper('hkp');
        $this->CI->load->library(array('ha_auth', 'ha_competency', 'ha_readiness'));
    }

    /** The person asked about: the caller, or someone in their scope. */
    protected function subject($user_id) {
        $me = (int) $this->CI->ha_auth->id();
        $uid = (int) $user_id ?: $me;
        if ($uid !== $me && (!$this->CI->ha_auth->has('learners.view') || !$this->CI->ha_auth->can_user($uid))) {
            throw new Ha_api_denied('That person is outside your scope.');
        }
        return $uid;
    }

    public function competencies($user_id = 0) {
        $uid = $this->subject($user_id);
        $out = array();
        foreach ($this->CI->ha_competency->profile($uid) as $c) {
            $out[] = array('code' => $c['code'], 'name_en' => $c['name_en'], 'name_ar' => $c['name_ar'], 'required_level' => $c['required'],
                'current_level' => $c['current'], 'gap_levels' => $c['gap'], 'severity' => $c['severity'], 'critical' => (bool) $c['critical'],
                'assessment_method' => $c['method'], 'evidence_count' => $c['evidence']);
        }
        return array('user_id' => $uid, 'competencies' => $out);
    }

    public function readiness($user_id = 0) {
        $uid = $this->subject($user_id);
        $r = $this->CI->ha_readiness->current($uid);
        if (!$r) {
            return array('user_id' => $uid, 'status' => null, 'calculated_at' => null, 'checks' => array());
        }
        $checks = array();
        foreach ((array) $r['checks'] as $c) {
            $checks[] = array('code' => $c['code'], 'label' => $c['label'], 'passed' => (bool) $c['passed'], 'on_fail' => $c['on_fail'], 'detail' => $c['detail']);
        }
        return array('user_id' => $uid, 'status' => $r['status'], 'calculated_at' => $r['calculated_at'], 'checks' => $checks);
    }

    public function gaps(array $f = array()) {
        $ids = $this->CI->ha_auth->visible_user_ids();
        $rows = $this->CI->ha_competency->open_gaps(array('user_ids' => $ids, 'severity' => isset($f['severity']) ? $f['severity'] : null, 'limit' => 500));
        $out = array();
        foreach ($rows as $g) {
            $out[] = array('id' => (int) $g['id'], 'user_id' => (int) $g['user_id'], 'person' => trim($g['first_name'] . ' ' . $g['last_name']),
                'competency' => $g['code'], 'competency_en' => $g['name_en'], 'competency_ar' => $g['name_ar'], 'severity' => $g['severity'],
                'status' => $g['status'], 'property_en' => $g['property_en'], 'department_en' => $g['dept_en'], 'detected_at' => $g['detected_at']);
        }
        return $out;
    }

    public function actions() {
        $this->CI->load->library('ha_action_plans');
        $ids = $this->CI->ha_auth->has('action_plans.review') ? $this->CI->ha_auth->visible_user_ids() : array((int) $this->CI->ha_auth->id());
        $out = array();
        foreach ($this->CI->ha_action_plans->for_users($ids ?: array(0)) as $a) {
            $out[] = array('id' => (int) $a['id'], 'user_id' => (int) $a['user_id'], 'title' => isset($a['title']) ? $a['title'] : null,
                'status' => $a['status'], 'due_at' => isset($a['due_at']) ? $a['due_at'] : null, 'created_at' => $a['created_at']);
        }
        return $out;
    }

    public function certificates() {
        $wide = $this->CI->ha_auth->has(array('certificates.export', 'certificates.issue'));
        $ids = $wide ? ($this->CI->ha_auth->visible_user_ids() ?: array(0)) : array((int) $this->CI->ha_auth->id());
        $rows = $this->CI->db->select('certificate_no, verification_code, user_id, recipient_name_en, recipient_name_ar, role_title_en, role_title_ar, subject_title_en, subject_title_ar, issued_at, expires_at, status')
            ->where_in('user_id', $ids)->order_by('issued_at', 'DESC')->limit(1000)->get('ha_certificate')->result_array();
        foreach ($rows as &$r) {
            $r['user_id'] = (int) $r['user_id'];
            $r['verify_url'] = site_url('verify/' . $r['verification_code']);
        }
        return $rows;
    }

    public function knowledge_search($term, $locale = null) {
        $this->CI->load->library('ha_knowledge');
        $out = array();
        foreach ($this->CI->ha_knowledge->search((int) $this->CI->ha_auth->id(), $term, array('limit' => 20, 'locale' => $locale)) as $h) {
            unset($h['score']);
            $out[] = $h;
        }
        return $out;
    }

    public function kpis($property_id = 0) {
        $this->CI->load->library('ha_kpi');
        $pid = (int) $property_id;
        if (!$pid) {
            $p = $this->CI->ha_auth->profile();
            $pid = $p ? (int) $p['property_id'] : 0;
        }
        if (!$pid || !$this->CI->ha_auth->can_property($pid)) {
            throw new Ha_api_denied('Name a property in your scope with ?property_id=.');
        }
        $out = array();
        foreach ($this->CI->ha_kpi->scorecard($pid) as $k) {
            $v = $k['value'];
            $out[] = array('code' => $k['code'], 'name_en' => $k['name_en'], 'name_ar' => $k['name_ar'], 'category' => $k['category'], 'unit' => $k['unit'],
                'direction' => $k['direction'], 'frequency' => $k['frequency'], 'status' => $k['status'], 'stale' => (bool) $k['stale'],
                'actual' => $v ? (float) $v['actual'] : null, 'target' => $v && $v['target'] !== null ? (float) $v['target'] : ($k['target'] !== null ? (float) $k['target'] : null),
                'period_start' => $v ? $v['period_start'] : null, 'period_end' => $v ? $v['period_end'] : null);
        }
        return array('property_id' => $pid, 'kpis' => $out);
    }

    public function people() {
        $ids = $this->CI->ha_auth->visible_user_ids() ?: array(0);
        $rows = $this->CI->db->select('u.id, u.first_name, u.last_name, p.employee_no, p.status, pr.name_en AS property_en, jr.title_en AS role_en, rr.status AS readiness')
            ->from('users u')->join('ha_profile p', 'p.user_id = u.id', 'left')->join('ha_property pr', 'pr.id = p.property_id', 'left')
            ->join('ha_job_role jr', 'jr.id = p.job_role_id', 'left')->join('ha_readiness_record rr', 'rr.user_id = u.id AND rr.is_current = 1', 'left')
            ->where_in('u.id', $ids)->order_by('u.first_name')->limit(2000)->get()->result_array();
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
        }
        return $rows;
    }
}
