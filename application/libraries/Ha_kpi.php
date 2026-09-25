<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * KPI framework (ppt-features 25, 26, 52, 122, 171, 178).
 *
 * Definitions carry the unit, direction, target and warning/critical
 * thresholds; values arrive per property and period from manual entry, CSV
 * import or an integration, and every value records its source. Nothing here
 * invents operational numbers: a KPI with no value shows "no data", and each
 * value is shown with its period and when it was last updated so stale data is
 * never presented as live.
 *
 * Platform KPIs (learning completion, competency coverage, readiness,
 * certification) are calculated from the evidence chain itself.
 */
class Ha_kpi {

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_audit', 'ha_notify'));
    }

    public function definitions($organization_id = null) {
        $db = $this->CI->db->where('status', 'active')->group_start()->where('organization_id IS NULL', null, false);
        if ($organization_id) {
            $db->or_where('organization_id', (int) $organization_id);
        }
        return $db->group_end()->order_by('category')->order_by('code')->get('ha_kpi')->result_array();
    }

    /** on_target | warning | critical | no_target, respecting the KPI's direction. */
    public function status(array $kpi, $actual, $target = null) {
        if ($actual === null) {
            return 'no_data';
        }
        $t = $target !== null ? (float) $target : ($kpi['target'] !== null ? (float) $kpi['target'] : null);
        $w = $kpi['threshold_warning'] !== null ? (float) $kpi['threshold_warning'] : null;
        $c = $kpi['threshold_critical'] !== null ? (float) $kpi['threshold_critical'] : null;
        $v = (float) $actual;
        $higher = $kpi['direction'] === 'higher_better';
        if ($c !== null && ($higher ? $v <= $c : $v >= $c)) {
            return 'critical';
        }
        if ($w !== null && ($higher ? $v <= $w : $v >= $w)) {
            return 'warning';
        }
        if ($t === null) {
            return 'no_target';
        }
        return ($higher ? $v >= $t : $v <= $t) ? 'on_target' : 'warning';
    }

    /** Stores one value (upsert on KPI + property + department + period start). */
    public function record(array $v, $actor_id = null) {
        $kpi = is_numeric($v['kpi']) ? $this->CI->db->get_where('ha_kpi', array('id' => (int) $v['kpi']))->row_array()
            : $this->CI->db->get_where('ha_kpi', array('code' => $v['kpi']))->row_array();
        if (!$kpi) {
            throw new InvalidArgumentException('Unknown KPI ' . $v['kpi'] . '.');
        }
        $prop = (int) $v['property_id'];
        if (!$this->CI->ha_auth->can_property($prop)) {
            throw new RuntimeException('You cannot record KPIs for that property.');
        }
        if (!is_numeric($v['actual'])) {
            throw new InvalidArgumentException('The actual value must be a number.');
        }
        $start = date('Y-m-d', strtotime($v['period_start']));
        $end = !empty($v['period_end']) ? date('Y-m-d', strtotime($v['period_end'])) : date('Y-m-t', strtotime($start));
        if ($end < $start) {
            throw new InvalidArgumentException('The period ends before it starts.');
        }
        $p = $this->CI->db->select('organization_id')->get_where('ha_property', array('id' => $prop))->row_array();
        $match = array('kpi_id' => (int) $kpi['id'], 'property_id' => $prop, 'department_key' => (int) (isset($v['department_id']) ? $v['department_id'] : 0), 'period_start' => $start);
        $row = array('organization_id' => $p ? $p['organization_id'] : null, 'period_end' => $end, 'actual' => (float) $v['actual'],
            'target' => isset($v['target']) && $v['target'] !== '' && $v['target'] !== null ? (float) $v['target'] : null,
            'source' => isset($v['source']) && in_array($v['source'], array('manual', 'csv', 'api', 'pms', 'pos', 'bi', 'warehouse', 'platform'), true) ? $v['source'] : 'manual',
            'source_ref' => isset($v['source_ref']) ? mb_substr((string) $v['source_ref'], 0, 190) : null,
            'entered_by' => $actor_id ? (int) $actor_id : null, 'updated_at' => date('Y-m-d H:i:s'));
        $ex = $this->CI->db->get_where('ha_kpi_value', $match)->row_array();
        if ($ex) {
            $this->CI->db->where('id', $ex['id'])->update('ha_kpi_value', $row);
            $id = (int) $ex['id'];
        } else {
            $this->CI->db->insert('ha_kpi_value', $match + $row + array('created_at' => date('Y-m-d H:i:s')));
            $id = (int) $this->CI->db->insert_id();
        }
        $status = $this->status($kpi, $row['actual'], $row['target']);
        $key = 'kpi:' . $kpi['id'] . ':' . $prop;
        if ($status === 'critical') {
            $this->CI->ha_notify->alert(array('type' => 'kpi_critical', 'severity' => 'critical', 'dedupe_key' => $key,
                'organization_id' => $row['organization_id'], 'property_id' => $prop,
                'title_en' => $kpi['name_en'] . ' is below the critical threshold', 'title_ar' => $kpi['name_ar'] . ' دون الحد الحرج',
                'detail' => $kpi['code'] . ' = ' . $row['actual'] . ' (' . $start . ')', 'entity_type' => 'kpi', 'entity_id' => $kpi['id'], 'url' => hkp_url('kpis')));
        } else {
            $this->CI->ha_notify->resolve_alert($key);
        }
        return $id;
    }

    /** Latest value of every KPI for a property, with status and freshness. */
    public function scorecard($property_id, $organization_id = null) {
        $out = array();
        foreach ($this->definitions($organization_id) as $k) {
            $v = $this->CI->db->where(array('kpi_id' => $k['id'], 'property_id' => (int) $property_id, 'department_key' => 0))
                ->order_by('period_start', 'DESC')->limit(1)->get('ha_kpi_value')->row_array();
            $trend = $this->CI->db->select('period_start, actual')->where(array('kpi_id' => $k['id'], 'property_id' => (int) $property_id, 'department_key' => 0))
                ->order_by('period_start', 'DESC')->limit(6)->get('ha_kpi_value')->result_array();
            $out[] = $k + array('value' => $v, 'status' => $this->status($k, $v ? $v['actual'] : null, $v ? $v['target'] : null),
                'trend' => array_reverse($trend), 'stale' => $v ? $this->is_stale($k, $v) : false);
        }
        return $out;
    }

    public function is_stale(array $kpi, array $v) {
        $grace = array('daily' => 3, 'weekly' => 14, 'monthly' => 45, 'quarterly' => 120, 'annual' => 400);
        $days = isset($grace[$kpi['frequency']]) ? $grace[$kpi['frequency']] : 45;
        return strtotime($v['period_end']) < strtotime('-' . $days . ' days');
    }

    /** Evidence-chain KPIs calculated live for a set of users. */
    public function platform_kpis(array $user_ids) {
        $user_ids = $user_ids ?: array(0);
        $db = $this->CI->db;
        $in = implode(',', array_map('intval', $user_ids));
        $learn = $db->query("SELECT COUNT(*) t, SUM(status = 'completed') c FROM ha_enrollment WHERE user_id IN ($in) AND status != 'cancelled'")->row_array();
        $ready = $db->query("SELECT COUNT(*) t, SUM(status = 'ready') r FROM ha_readiness_record WHERE user_id IN ($in) AND is_current = 1")->row_array();
        $gaps = (int) $db->query("SELECT COUNT(*) n FROM ha_competency_gap WHERE user_id IN ($in) AND status IN ('open','in_action') AND severity = 'critical'")->row()->n;
        $pass = $db->query("SELECT COUNT(*) t, SUM(passed = 1) p FROM ha_assessment_attempt WHERE user_id IN ($in) AND status = 'graded'")->row_array();
        $overdue = (int) $db->query("SELECT COUNT(*) n FROM ha_action_plan WHERE user_id IN ($in) AND status = 'overdue'")->row()->n;
        $certs = (int) $db->query("SELECT COUNT(DISTINCT user_id) n FROM ha_certificate WHERE user_id IN ($in) AND status = 'issued'")->row()->n;
        $this->CI->load->library('ha_competency');
        $m = $this->CI->ha_competency->matrix($user_ids);
        $cells = 0;
        $met = 0;
        foreach ($m['cells'] as $row) {
            foreach ($row as $c) {
                $cells++;
                $met += $c['gap'] === 0 ? 1 : 0;
            }
        }
        return array(
            'learning_completion' => (int) $learn['t'] ? round(100 * $learn['c'] / $learn['t'], 1) : null,
            'competency_coverage' => $cells ? round(100 * $met / $cells, 1) : null,
            'readiness_rate' => (int) $ready['t'] ? round(100 * $ready['r'] / $ready['t'], 1) : null,
            'assessment_pass_rate' => (int) $pass['t'] ? round(100 * $pass['p'] / $pass['t'], 1) : null,
            'critical_gaps' => $gaps, 'overdue_actions' => $overdue,
            'certification_rate' => count($user_ids) ? round(100 * $certs / count($user_ids), 1) : null,
            'people' => count($user_ids), 'calculated_at' => date('Y-m-d H:i:s'),
        );
    }
}
