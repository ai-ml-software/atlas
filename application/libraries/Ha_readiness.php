<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Readiness engine (ppt-features 22, 72, 111, 141, 172, 173, 179).
 *
 * Readiness is evaluated from evidence, never from a single score. A policy
 * (per role, optionally per property) lists checks; each check reports whether
 * it passed, why, the numbers it used and what a failure means (not ready, or
 * only conditional). The status is the worst consequence among failed checks:
 *
 *   all checks pass                      -> ready
 *   only "conditional" checks fail       -> conditional
 *   any "not_ready" check fails          -> not ready
 *
 * The full list of checks is stored with every record so a manager can read
 * exactly why someone is not ready, and when that was calculated.
 */
class Ha_readiness {

    protected $CI;

    /** Guards the readiness -> certification -> readiness loop to one level. */
    protected static $in_certification = false;

    public static function default_rules() {
        return array(
            'mandatory_learning' => array('enabled' => 1, 'min_pct' => 100, 'on_fail' => 'not_ready'),
            'theory'             => array('enabled' => 1, 'on_fail' => 'not_ready'),
            'practical'          => array('enabled' => 1, 'on_fail' => 'not_ready'),
            'critical_gaps'      => array('enabled' => 1, 'on_fail' => 'not_ready'),
            'other_gaps'         => array('enabled' => 1, 'max' => 0, 'on_fail' => 'conditional'),
            'certification'      => array('enabled' => 1, 'on_fail' => 'conditional'),
            'overdue_actions'    => array('enabled' => 1, 'on_fail' => 'conditional'),
        );
    }

    public static function check_labels() {
        return array(
            'mandatory_learning' => 'Mandatory learning complete',
            'theory'             => 'Required theory assessments passed',
            'practical'          => 'Required practical competencies verified',
            'critical_gaps'      => 'No critical competency gaps',
            'other_gaps'         => 'Other competency gaps within tolerance',
            'certification'      => 'Required certification current',
            'overdue_actions'    => 'No overdue action plans',
        );
    }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_competency', 'ha_notify', 'ha_tenant'));
    }

    /** The policy that applies: role + property, then role + organisation, then role, then the default. */
    public function policy_for(array $profile) {
        $db = $this->CI->db;
        $tries = array();
        if ($profile['job_role_id']) {
            $tries[] = array('job_role_id' => $profile['job_role_id'], 'property_id' => $profile['property_id']);
            $tries[] = array('job_role_id' => $profile['job_role_id'], 'organization_id' => $profile['organization_id'], 'property_id' => null);
            $tries[] = array('job_role_id' => $profile['job_role_id'], 'organization_id' => null, 'property_id' => null);
        }
        foreach ($tries as $w) {
            foreach ($w as $k => $v) {
                $v === null ? $db->where($k . ' IS NULL', null, false) : $db->where($k, $v);
            }
            $row = $db->where('status', 'active')->get('ha_readiness_policy')->row_array();
            if ($row) {
                return $row;
            }
        }
        $row = $db->get_where('ha_readiness_policy', array('code' => 'default', 'status' => 'active'))->row_array();
        return $row ?: array('id' => null, 'code' => 'built_in', 'name_en' => 'Default readiness policy', 'name_ar' => 'سياسة الجاهزية الافتراضية',
            'rules_json' => json_encode(self::default_rules()));
    }

    public function rules(array $policy) {
        $rules = json_decode((string) $policy['rules_json'], true);
        $defaults = self::default_rules();
        if (!is_array($rules)) {
            return $defaults;
        }
        foreach ($defaults as $k => $d) {
            $rules[$k] = isset($rules[$k]) && is_array($rules[$k]) ? $rules[$k] + $d : $d;
        }
        return $rules;
    }

    /**
     * Evaluates without saving.
     * @param array $opts skip => array(check codes) (certification rules skip the certification check)
     */
    public function evaluate($user_id, array $opts = array()) {
        $comp = $this->CI->ha_competency;
        $profile = $comp->profile_row($user_id);
        if (!$profile) {
            return array('status' => 'not_ready', 'checks' => array(), 'policy' => null, 'inputs' => array(), 'profile' => null);
        }
        $policy = $this->policy_for($profile);
        $rules = $this->rules($policy);
        $skip = isset($opts['skip']) ? (array) $opts['skip'] : array();
        $labels = self::check_labels();
        $checks = array();
        $inputs = array();

        // --- mandatory learning
        $learning = $this->mandatory_learning($user_id, $profile);
        $inputs['learning'] = $learning;
        if ($rules['mandatory_learning']['enabled'] && !in_array('mandatory_learning', $skip, true)) {
            $pct = $learning['total'] ? round(100 * $learning['completed'] / $learning['total'], 1) : 100;
            $pass = $pct >= (float) $rules['mandatory_learning']['min_pct'];
            $checks[] = $this->check('mandatory_learning', $pass, $rules,
                $learning['total'] ? hkp_t('{done} of {total} mandatory modules complete ({pct}%)', array('done' => $learning['completed'], 'total' => $learning['total'], 'pct' => $pct))
                    : hkp_t('No mandatory learning is defined for this role'),
                array('pct' => $pct, 'required_pct' => (float) $rules['mandatory_learning']['min_pct'], 'missing' => $learning['missing']));
        }

        // --- theory
        $theory = $this->theory($user_id, $learning['course_ids'], $profile);
        $inputs['theory'] = $theory;
        if ($rules['theory']['enabled'] && !in_array('theory', $skip, true)) {
            $checks[] = $this->check('theory', !$theory['missing'], $rules,
                $theory['total'] ? hkp_t('{passed} of {total} required assessments passed', array('passed' => $theory['total'] - count($theory['missing']), 'total' => $theory['total']))
                    : hkp_t('No theory assessment is required for this role'),
                array('missing' => $theory['missing']));
        }

        // --- competencies
        $profile_rows = $comp->profile($user_id);
        $critical = array();
        $other = array();
        $unverified = array();
        foreach ($profile_rows as $r) {
            if ($r['gap'] > 0) {
                if ($r['severity'] === 'critical') {
                    $critical[] = $r['name_en'];
                } else {
                    $other[] = $r['name_en'];
                }
            }
            if (in_array($r['method'], array('practical', 'theory_practical', 'observation'), true)) {
                $has = $this->CI->db->where(array('user_id' => (int) $user_id, 'skill_id' => $r['skill_id']))
                    ->where_in('source', array('practical', 'reassessment', 'manager', 'audit'))->count_all_results('ha_competency_result');
                if (!$has) {
                    $unverified[] = $r['name_en'];
                }
            }
        }
        $inputs['competencies'] = array('required' => count($profile_rows), 'critical_gaps' => $critical, 'other_gaps' => $other, 'unverified' => $unverified);
        if ($rules['practical']['enabled'] && !in_array('practical', $skip, true)) {
            $checks[] = $this->check('practical', !$unverified, $rules,
                $unverified ? hkp_t('Not yet assessed in practice: {list}', array('list' => implode(', ', $unverified)))
                    : hkp_t('Every practical competency has assessment evidence'), array('missing' => $unverified));
        }
        if ($rules['critical_gaps']['enabled'] && !in_array('critical_gaps', $skip, true)) {
            $checks[] = $this->check('critical_gaps', !$critical, $rules,
                $critical ? hkp_t('Critical gap: {list}', array('list' => implode(', ', $critical))) : hkp_t('No critical gaps'), array('gaps' => $critical));
        }
        if ($rules['other_gaps']['enabled'] && !in_array('other_gaps', $skip, true)) {
            $max = (int) $rules['other_gaps']['max'];
            $checks[] = $this->check('other_gaps', count($other) <= $max, $rules,
                $other ? hkp_t('Below required level: {list}', array('list' => implode(', ', $other))) : hkp_t('All competencies at the required level'),
                array('gaps' => $other, 'allowed' => $max));
        }

        // --- certification
        $cert = $this->certification($user_id, $profile);
        $inputs['certification'] = $cert;
        if ($rules['certification']['enabled'] && !in_array('certification', $skip, true)) {
            $checks[] = $this->check('certification', !$cert['missing'], $rules,
                $cert['required'] ? ($cert['missing'] ? hkp_t('Certification outstanding: {list}', array('list' => implode(', ', $cert['missing']))) : hkp_t('Required certification is current'))
                    : hkp_t('No certification is required for this role'), array('missing' => $cert['missing']));
        }

        // --- overdue actions
        $overdue = (int) $this->CI->db->where(array('user_id' => (int) $user_id, 'status' => 'overdue'))->count_all_results('ha_action_plan');
        $inputs['overdue_actions'] = $overdue;
        if ($rules['overdue_actions']['enabled'] && !in_array('overdue_actions', $skip, true)) {
            $checks[] = $this->check('overdue_actions', $overdue === 0, $rules,
                $overdue ? hkp_t('{n} action plan(s) overdue', array('n' => $overdue)) : hkp_t('No overdue action plans'), array('count' => $overdue));
        }

        $status = 'ready';
        foreach ($checks as $c) {
            if (!$c['passed']) {
                if ($c['on_fail'] === 'not_ready') {
                    $status = 'not_ready';
                    break;
                }
                $status = 'conditional';
            }
        }
        return array('status' => $status, 'checks' => $checks, 'policy' => $policy, 'inputs' => $inputs, 'profile' => $profile);
    }

    protected function check($code, $passed, array $rules, $detail, array $data) {
        $labels = self::check_labels();
        return array('code' => $code, 'label' => $labels[$code], 'passed' => (bool) $passed,
            'on_fail' => $rules[$code]['on_fail'] === 'conditional' ? 'conditional' : 'not_ready', 'detail' => $detail, 'data' => $data);
    }

    /** Mandatory modules: role requirements (tracks expand to their mandatory modules) plus mandatory assignments. */
    public function mandatory_learning($user_id, array $profile) {
        $course_ids = array();
        if ($profile['job_role_id']) {
            $req = $this->CI->db->where('job_role_id', (int) $profile['job_role_id'])->where_in('property_key', array(0, (int) $profile['property_id']))
                ->where('is_mandatory', 1)->where_in('item_type', array('track', 'course'))->get('ha_role_requirement')->result_array();
            foreach ($req as $r) {
                if ($r['item_type'] === 'course') {
                    $course_ids[] = (int) $r['item_id'];
                } else {
                    foreach ($this->CI->db->select('course_id')->get_where('ha_track_module', array('track_id' => $r['item_id'], 'is_mandatory' => 1))->result_array() as $m) {
                        $course_ids[] = (int) $m['course_id'];
                    }
                }
            }
        }
        $assigned = $this->CI->db->select('ti.item_type, ti.item_id')->from('ha_training_recipient tr')
            ->join('ha_training_assignment ta', 'ta.id = tr.assignment_id')->join('ha_training_item ti', 'ti.assignment_id = ta.id')
            ->where('tr.user_id', (int) $user_id)->where('ta.is_mandatory', 1)->where_in('ta.status', array('active', 'scheduled'))
            ->where('tr.status !=', 'waived')->get()->result_array();
        foreach ($assigned as $a) {
            if ($a['item_type'] === 'course') {
                $course_ids[] = (int) $a['item_id'];
            } elseif ($a['item_type'] === 'track') {
                foreach ($this->CI->db->select('course_id')->get_where('ha_track_module', array('track_id' => $a['item_id'], 'is_mandatory' => 1))->result_array() as $m) {
                    $course_ids[] = (int) $m['course_id'];
                }
            }
        }
        $course_ids = array_values(array_unique($course_ids));
        $completed = array();
        if ($course_ids) {
            $rows = $this->CI->db->select('course_id')->where('user_id', (int) $user_id)->where_in('course_id', $course_ids)
                ->where('status', 'completed')->get('ha_enrollment')->result_array();
            $completed = array_map('intval', array_column($rows, 'course_id'));
        }
        $missing = array();
        foreach (array_diff($course_ids, $completed) as $cid) {
            $t = $this->CI->db->select('title')->get_where('ha_course_translation', array('course_id' => $cid, 'locale' => 'en'))->row_array();
            $missing[] = $t ? $t['title'] : '#' . $cid;
        }
        return array('total' => count($course_ids), 'completed' => count($completed), 'course_ids' => $course_ids, 'missing' => $missing);
    }

    /** Theory: explicitly required assessments plus the published assessments of mandatory modules. */
    public function theory($user_id, array $course_ids, array $profile) {
        $ids = array();
        if ($profile['job_role_id']) {
            foreach ($this->CI->db->select('item_id')->where(array('job_role_id' => (int) $profile['job_role_id'], 'item_type' => 'assessment', 'is_mandatory' => 1))
                ->where_in('property_key', array(0, (int) $profile['property_id']))->get('ha_role_requirement')->result_array() as $r) {
                $ids[] = (int) $r['item_id'];
            }
        }
        if ($course_ids) {
            foreach ($this->CI->db->select('id')->where_in('course_id', $course_ids)->where('status', 'published')
                ->where_in('assessment_type', array('quiz', 'exam'))->get('ha_assessment')->result_array() as $a) {
                $ids[] = (int) $a['id'];
            }
        }
        $ids = array_values(array_unique($ids));
        $missing = array();
        foreach ($ids as $aid) {
            $passed = $this->CI->db->where(array('assessment_id' => $aid, 'user_id' => (int) $user_id, 'passed' => 1, 'status' => 'graded'))
                ->count_all_results('ha_assessment_attempt');
            if (!$passed) {
                $a = $this->CI->db->select('title_en')->get_where('ha_assessment', array('id' => $aid))->row_array();
                $missing[] = $a ? $a['title_en'] : '#' . $aid;
            }
        }
        return array('total' => count($ids), 'assessment_ids' => $ids, 'missing' => $missing);
    }

    public function certification($user_id, array $profile) {
        $programs = array();
        if ($profile['job_role_id']) {
            foreach ($this->CI->db->get_where('ha_certification_program', array('job_role_id' => (int) $profile['job_role_id'], 'status' => 'published'))->result_array() as $p) {
                $programs[(int) $p['id']] = $p;
            }
            foreach ($this->CI->db->select('item_id')->where(array('job_role_id' => (int) $profile['job_role_id'], 'item_type' => 'certification', 'is_mandatory' => 1))
                ->where_in('property_key', array(0, (int) $profile['property_id']))->get('ha_role_requirement')->result_array() as $r) {
                $p = $this->CI->db->get_where('ha_certification_program', array('id' => $r['item_id'], 'status' => 'published'))->row_array();
                if ($p) {
                    $programs[(int) $p['id']] = $p;
                }
            }
        }
        $missing = array();
        foreach ($programs as $pid => $p) {
            $ok = $this->CI->db->where(array('user_id' => (int) $user_id, 'program_id' => $pid, 'status' => 'issued'))
                ->group_start()->where('expires_at IS NULL', null, false)->or_where('expires_at >', date('Y-m-d H:i:s'))->group_end()
                ->count_all_results('ha_certificate');
            if (!$ok) {
                $missing[] = $p['title_en'];
            }
        }
        return array('required' => count($programs), 'program_ids' => array_keys($programs), 'missing' => $missing);
    }

    /**
     * Evaluates and stores. A new record is written whenever the status changes,
     * so the history of someone's readiness is kept; an unchanged status only
     * refreshes the current record's reasons and timestamp.
     */
    public function calculate($user_id) {
        $this->CI->ha_competency->recalculate_gaps($user_id);
        $r = $this->evaluate($user_id);
        if (!$r['profile']) {
            return $r;
        }
        $p = $r['profile'];
        $now = date('Y-m-d H:i:s');
        $current = $this->CI->db->get_where('ha_readiness_record', array('user_id' => (int) $user_id, 'is_current' => 1))->row_array();
        $row = array('policy_id' => $r['policy']['id'], 'organization_id' => $p['organization_id'], 'property_id' => $p['property_id'],
            'department_id' => $p['department_id'], 'job_role_id' => $p['job_role_id'], 'status' => $r['status'],
            'reasons_json' => json_encode($r['checks'], JSON_UNESCAPED_UNICODE), 'inputs_json' => json_encode($r['inputs'], JSON_UNESCAPED_UNICODE),
            'calculated_at' => $now);
        if ($current && $current['status'] === $r['status']) {
            $this->CI->db->where('id', $current['id'])->update('ha_readiness_record', $row);
        } else {
            if ($current) {
                $this->CI->db->where('id', $current['id'])->update('ha_readiness_record', array('is_current' => 0));
            }
            $this->CI->db->insert('ha_readiness_record', $row + array('user_id' => (int) $user_id, 'is_current' => 1));
            if ($current) {
                $first_fail = '';
                foreach ($r['checks'] as $c) {
                    if (!$c['passed']) {
                        $first_fail = $c['detail'];
                        break;
                    }
                }
                $this->CI->ha_notify->send($user_id, 'readiness.changed', array('status' => str_replace('_', ' ', $r['status']),
                    'reason' => $first_fail ?: 'All readiness requirements are met.', 'url' => hkp_url('readiness/me'),
                    'related_type' => 'readiness', 'related_id' => (int) $user_id, '_no_manager' => 1));
            }
        }
        // Readiness can now unlock certification; the engine re-checks and issues only when every rule passes.
        if (!self::$in_certification) {
            self::$in_certification = true;
            $this->CI->load->library('ha_certification');
            try {
                $issued = $this->CI->ha_certification->auto_issue($user_id);
            } finally {
                self::$in_certification = false;
            }
            if ($issued) {
                return $this->calculate($user_id);
            }
        }
        return $r + array('calculated_at' => $now);
    }

    public function current($user_id) {
        $row = $this->CI->db->get_where('ha_readiness_record', array('user_id' => (int) $user_id, 'is_current' => 1))->row_array();
        if ($row) {
            $row['checks'] = json_decode((string) $row['reasons_json'], true) ?: array();
            $row['inputs'] = json_decode((string) $row['inputs_json'], true) ?: array();
        }
        return $row;
    }

    /**
     * Readiness distribution for a set of users, grouped by department, role or
     * property, from each person's current record. Users never calculated are
     * counted as "not calculated" rather than silently dropped.
     */
    public function summary(array $user_ids, $group = null) {
        $user_ids = $user_ids ?: array(0);
        $col = array('department' => 'p.department_id', 'role' => 'p.job_role_id', 'property' => 'p.property_id');
        $gcol = isset($col[$group]) ? $col[$group] : "'all'";
        $rows = $this->CI->db->query('SELECT ' . $gcol . ' AS g, IFNULL(rr.status, \'not_calculated\') AS status, COUNT(*) AS n
            FROM ha_profile p LEFT JOIN ha_readiness_record rr ON rr.user_id = p.user_id AND rr.is_current = 1
            WHERE p.user_id IN (' . implode(',', array_map('intval', $user_ids)) . ') AND p.status = \'active\' AND p.job_role_id IS NOT NULL
            GROUP BY g, status')->result_array();
        $out = array();
        foreach ($rows as $r) {
            $g = $r['g'] === null ? 0 : $r['g'];
            if (!isset($out[$g])) {
                $out[$g] = array('ready' => 0, 'conditional' => 0, 'not_ready' => 0, 'not_calculated' => 0, 'total' => 0);
            }
            $out[$g][$r['status']] += (int) $r['n'];
            $out[$g]['total'] += (int) $r['n'];
        }
        foreach ($out as $g => &$s) {
            $s['ready_pct'] = $s['total'] ? round(100 * $s['ready'] / $s['total'], 1) : 0;
        }
        return $out;
    }

    /** Recalculates everyone in a set; used by the scheduler and after bulk changes. */
    public function recalculate_all(array $user_ids) {
        $n = 0;
        foreach ($user_ids as $uid) {
            $this->calculate($uid);
            $n++;
        }
        return $n;
    }

    // ------------------------------------------------------ opening readiness

    public static function opening_categories() {
        return array('recruitment', 'training', 'competency', 'sop', 'systems', 'safety', 'quality', 'commercial');
    }

    /** Property opening readiness with automatic categories calculated from platform data. */
    public function opening($property_id) {
        $staff = array_map('intval', array_column($this->CI->db->select('user_id')->get_where('ha_profile',
            array('property_id' => (int) $property_id, 'status' => 'active'))->result_array(), 'user_id'));
        $auto = array(
            'training'      => $this->auto_training($staff),
            'competency'    => $this->auto_competency($staff),
            'sop'           => $this->auto_sop($staff),
            'certification' => $this->auto_certification($staff),
        );
        $items = $this->CI->db->order_by('category')->order_by('id')->get_where('ha_opening_readiness_item', array('property_id' => (int) $property_id))->result_array();
        foreach ($items as &$it) {
            if ($it['auto_source'] !== 'manual' && isset($auto[$it['auto_source']]) && $auto[$it['auto_source']] !== null) {
                $it['current_value'] = $auto[$it['auto_source']];
                $it['calculated'] = true;
            }
        }
        unset($it);
        $min = (float) $this->CI->ha_tenant->get('opening.conditional_min_pct', $property_id);
        $cats = array();
        foreach (self::opening_categories() as $c) {
            $cats[$c] = array('required' => 0, 'current' => 0, 'items' => array(), 'status' => null);
        }
        foreach ($items as $it) {
            $cats[$it['category']]['items'][] = $it;
        }
        $overall = 'ready';
        foreach ($cats as $c => &$cat) {
            if (!$cat['items']) {
                continue;
            }
            $req = 0;
            $cur = 0;
            $critical_short = false;
            foreach ($cat['items'] as $it) {
                $req += (float) $it['required_value'];
                $cur += min((float) $it['current_value'], (float) $it['required_value']);
                if ((int) $it['is_critical'] && (float) $it['current_value'] < (float) $it['required_value']) {
                    $critical_short = true;
                }
            }
            $pct = $req > 0 ? round(100 * $cur / $req, 1) : 100;
            $cat['pct'] = $pct;
            if ($pct >= 100 && !$critical_short) {
                $cat['status'] = 'ready';
            } elseif ($pct >= $min && !$critical_short) {
                $cat['status'] = 'conditional';
            } else {
                $cat['status'] = 'not_ready';
            }
            if ($cat['status'] === 'not_ready') {
                $overall = 'not_ready';
            } elseif ($cat['status'] === 'conditional' && $overall === 'ready') {
                $overall = 'conditional';
            }
        }
        unset($cat);
        $critical_gaps = $staff ? (int) $this->CI->db->where_in('user_id', $staff)->where('severity', 'critical')
            ->where_in('status', array('open', 'in_action'))->count_all_results('ha_competency_gap') : 0;
        $prop = $this->CI->db->get_where('ha_property', array('id' => (int) $property_id))->row_array();
        $countdown = null;
        if ($prop && $prop['opening_date']) {
            $countdown = (int) floor((strtotime($prop['opening_date']) - strtotime(date('Y-m-d'))) / 86400);
        }
        return array('categories' => $cats, 'overall' => $overall, 'critical_gaps' => $critical_gaps, 'staff' => count($staff),
            'auto' => $auto, 'countdown' => $countdown, 'conditional_min' => $min, 'property' => $prop);
    }

    protected function auto_training(array $staff) {
        if (!$staff) {
            return null;
        }
        $rows = $this->CI->db->select('inputs_json')->where_in('user_id', $staff)->where('is_current', 1)->get('ha_readiness_record')->result_array();
        $t = 0;
        $c = 0;
        foreach ($rows as $r) {
            $in = json_decode((string) $r['inputs_json'], true);
            if (isset($in['learning'])) {
                $t += (int) $in['learning']['total'];
                $c += (int) $in['learning']['completed'];
            }
        }
        return $t ? round(100 * $c / $t, 1) : null;
    }

    protected function auto_competency(array $staff) {
        if (!$staff) {
            return null;
        }
        $m = $this->CI->ha_competency->matrix($staff);
        $t = 0;
        $ok = 0;
        foreach ($m['cells'] as $cells) {
            foreach ($cells as $cell) {
                $t++;
                if ($cell['gap'] === 0) {
                    $ok++;
                }
            }
        }
        return $t ? round(100 * $ok / $t, 1) : null;
    }

    protected function auto_sop(array $staff) {
        if (!$staff) {
            return null;
        }
        $total = (int) $this->CI->db->where_in('user_id', $staff)->where('status !=', 'superseded')->count_all_results('ha_sop_acknowledgement');
        if (!$total) {
            return null;
        }
        $done = (int) $this->CI->db->where_in('user_id', $staff)->where('status', 'acknowledged')->count_all_results('ha_sop_acknowledgement');
        return round(100 * $done / $total, 1);
    }

    protected function auto_certification(array $staff) {
        if (!$staff) {
            return null;
        }
        $rows = $this->CI->db->select('inputs_json')->where_in('user_id', $staff)->where('is_current', 1)->get('ha_readiness_record')->result_array();
        $req = 0;
        $ok = 0;
        foreach ($rows as $r) {
            $in = json_decode((string) $r['inputs_json'], true);
            if (!empty($in['certification']['required'])) {
                $req++;
                if (empty($in['certification']['missing'])) {
                    $ok++;
                }
            }
        }
        return $req ? round(100 * $ok / $req, 1) : null;
    }
}
