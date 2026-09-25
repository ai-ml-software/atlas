<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Competency engine (ppt-features 18, 19, 70, 71, 105-107, 170, 171).
 *
 *   Role -> required competency + level  (ha_role_competency, property override)
 *   Evidence -> competency result          (ha_competency_result, append-only)
 *   Current level                          (ha_person_skill snapshot)
 *   Required vs current -> gap             (ha_competency_gap, configurable severity)
 *
 * Course completion never raises a competency level here: only a theory pass
 * mapped to the competency, a practical assessment, a reassessment, a manager
 * decision with evidence, or an import does. That is the product's first rule,
 * "training completion is not competency", enforced in code.
 */
class Ha_competency {

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_tenant', 'ha_audit', 'ha_notify'));
    }

    // ------------------------------------------------------------------ levels

    /** Level scale for an organisation, falling back to the global scale. */
    public function levels($organization_id = null) {
        $rows = array();
        if ($organization_id) {
            $rows = $this->CI->db->order_by('level_no')->get_where('ha_competency_level', array('organization_id' => (int) $organization_id))->result_array();
        }
        if (!$rows) {
            $rows = $this->CI->db->order_by('level_no')->get_where('ha_competency_level', array('organization_id' => 0))->result_array();
        }
        $out = array();
        foreach ($rows as $r) {
            $out[(int) $r['level_no']] = $r;
        }
        return $out;
    }

    public function level_name($level_no, $organization_id = null) {
        $levels = $this->levels($organization_id);
        if (isset($levels[(int) $level_no])) {
            return hkp_pick($levels[(int) $level_no], 'name');
        }
        return (int) $level_no === 0 ? hkp_t('Not assessed') : (string) (int) $level_no;
    }

    // ------------------------------------------------------------ requirements

    /**
     * Required competencies for a user, from their job role. A property row for
     * the same competency overrides the organisation-wide row, so a property can
     * raise or lower a level without editing the master matrix.
     */
    public function requirements($user_id) {
        $p = $this->profile_row($user_id);
        if (!$p || !$p['job_role_id']) {
            return array();
        }
        $rows = $this->CI->db->select('rc.*, s.code, s.name_en, s.name_ar, s.criticality, s.assessment_method, s.domain_id')
            ->from('ha_role_competency rc')->join('ha_skill s', 's.id = rc.skill_id')
            ->where('rc.job_role_id', (int) $p['job_role_id'])
            ->where_in('rc.property_key', array(0, (int) $p['property_id']))
            ->where('s.status', 'active')
            ->order_by('rc.property_key', 'ASC')->get()->result_array();
        $out = array();
        foreach ($rows as $r) {
            $out[(int) $r['skill_id']] = $r;   // property_key ASC: the property row overwrites the global one
        }
        return $out;
    }

    public function profile_row($user_id) {
        return $this->CI->db->get_where('ha_profile', array('user_id' => (int) $user_id))->row_array();
    }

    public function current_level($user_id, $skill_id) {
        $row = $this->CI->db->select('level_no')->get_where('ha_person_skill', array('user_id' => (int) $user_id, 'skill_id' => (int) $skill_id))->row_array();
        return $row ? (int) $row['level_no'] : 0;
    }

    // ----------------------------------------------------------------- results

    /**
     * Records an assessed level. Never updates an earlier result: every
     * assessment adds a row, and the snapshot points at the newest one.
     *
     * @param string $source theory|practical|reassessment|manager|import|audit
     * @return int result id
     */
    public function record_result($user_id, $skill_id, $level_no, $source, array $opts = array()) {
        $allowed = array('theory', 'practical', 'reassessment', 'manager', 'import', 'audit');
        if (!in_array($source, $allowed, true)) {
            throw new InvalidArgumentException('A competency level can only come from assessment evidence, not from ' . $source . '.');
        }
        if ($source === 'manager' && empty($opts['evidence_id']) && empty($opts['notes'])) {
            throw new InvalidArgumentException('A manager decision on a competency needs evidence or a written reason.');
        }
        $level_no = max(0, min(10, (int) $level_no));
        $p = $this->profile_row($user_id);
        $previous = $this->current_level($user_id, $skill_id);
        $now = date('Y-m-d H:i:s');

        $this->CI->db->trans_start();
        $this->CI->db->insert('ha_competency_result', array(
            'user_id' => (int) $user_id, 'skill_id' => (int) $skill_id, 'level_no' => $level_no,
            'previous_level_no' => $previous, 'source' => $source,
            'source_id' => isset($opts['source_id']) ? (int) $opts['source_id'] : null,
            'assessor_user_id' => isset($opts['assessor_id']) ? (int) $opts['assessor_id'] : null,
            'evidence_id' => isset($opts['evidence_id']) ? (int) $opts['evidence_id'] : null,
            'organization_id' => $p ? $p['organization_id'] : null,
            'property_id' => $p ? $p['property_id'] : null,
            'notes' => isset($opts['notes']) ? mb_substr((string) $opts['notes'], 0, 500) : null,
            'assessed_at' => isset($opts['assessed_at']) ? $opts['assessed_at'] : $now, 'created_at' => $now,
        ));
        $result_id = (int) $this->CI->db->insert_id();

        // A theory pass proves knowledge, not performance: it may lift a level
        // but never lowers one a practical assessment established.
        $apply = !($source === 'theory' && $level_no < $previous);
        if ($apply) {
            $snap = array('level_no' => $level_no, 'level' => $this->legacy_level($level_no), 'source' => $this->legacy_source($source),
                'last_result_id' => $result_id, 'assessed_by' => isset($opts['assessor_id']) ? (int) $opts['assessor_id'] : null,
                'achieved_at' => $now, 'updated_at' => $now);
            $exists = $this->CI->db->get_where('ha_person_skill', array('user_id' => (int) $user_id, 'skill_id' => (int) $skill_id))->row_array();
            if ($exists) {
                $this->CI->db->where('id', $exists['id'])->update('ha_person_skill', $snap);
            } else {
                $this->CI->db->insert('ha_person_skill', $snap + array('user_id' => (int) $user_id, 'skill_id' => (int) $skill_id, 'created_at' => $now));
            }
        }
        $this->CI->db->trans_complete();

        $this->CI->ha_audit->log('update', 'competency', (int) $skill_id, array(
            'description' => 'Competency level ' . $previous . ' -> ' . $level_no . ' for user ' . (int) $user_id . ' (' . $source . ')',
            'before' => array('level' => $previous), 'after' => array('level' => $apply ? $level_no : $previous, 'result_id' => $result_id),
            'organization_id' => $p ? $p['organization_id'] : null, 'property_id' => $p ? $p['property_id'] : null,
        ));
        $this->recalculate_gaps($user_id, $result_id);
        return $result_id;
    }

    protected function legacy_level($n) {
        $map = array(0 => 'not_started', 1 => 'learning', 2 => 'basic', 3 => 'competent', 4 => 'advanced');
        return isset($map[$n]) ? $map[$n] : 'certified';
    }

    protected function legacy_source($s) {
        return in_array($s, array('manager', 'import'), true) ? $s : 'assessment';
    }

    public function history($user_id, $skill_id = null) {
        $db = $this->CI->db->select('r.*, s.name_en, s.name_ar, s.code, u.first_name AS assessor_first, u.last_name AS assessor_last')
            ->from('ha_competency_result r')->join('ha_skill s', 's.id = r.skill_id')
            ->join('users u', 'u.id = r.assessor_user_id', 'left')->where('r.user_id', (int) $user_id);
        if ($skill_id) {
            $db->where('r.skill_id', (int) $skill_id);
        }
        return $db->order_by('r.assessed_at', 'DESC')->order_by('r.id', 'DESC')->get()->result_array();
    }

    // -------------------------------------------------------------------- gaps

    public function severity($gap_levels, $is_critical, $property_id = null) {
        if ($gap_levels <= 0) {
            return 'none';
        }
        $t = $this->CI->ha_tenant;
        if ($is_critical && $t->get('gap.critical_competency_escalates', $property_id)) {
            return 'critical';
        }
        if ($gap_levels <= $t->get('gap.minor_max', $property_id)) {
            return 'minor';
        }
        if ($gap_levels <= $t->get('gap.moderate_max', $property_id)) {
            return 'moderate';
        }
        return 'critical';
    }

    /**
     * Compares every required competency with the current level. Opens a gap
     * where the person falls short (one open gap per competency), updates its
     * levels if it is already open, and closes it once the level is reached,
     * recording which result closed it.
     *
     * @return array('opened' => n, 'closed' => n, 'open' => n)
     */
    public function recalculate_gaps($user_id, $closing_result_id = null) {
        $p = $this->profile_row($user_id);
        $req = $this->requirements($user_id);
        $now = date('Y-m-d H:i:s');
        $opened = 0;
        $closed = 0;
        $open_now = 0;

        foreach ($req as $skill_id => $r) {
            $current = $this->current_level($user_id, $skill_id);
            $required = (int) $r['required_level'];
            $gap = max(0, $required - $current);
            $critical = (int) $r['is_critical'] || $r['criticality'] === 'critical';
            $existing = $this->CI->db->where(array('user_id' => (int) $user_id, 'skill_id' => (int) $skill_id))
                ->where_in('status', array('open', 'in_action'))->get('ha_competency_gap')->row_array();

            if ($gap > 0) {
                $open_now++;
                $severity = $this->severity($gap, $critical, $p['property_id']);
                $reason = $this->gap_reason($user_id, $skill_id, $current);
                if ($existing) {
                    $this->CI->db->where('id', $existing['id'])->update('ha_competency_gap', array(
                        'required_level' => $required, 'current_level' => $current, 'gap_levels' => $gap,
                        'severity' => $severity, 'reason' => $reason, 'updated_at' => $now));
                } else {
                    $this->CI->db->insert('ha_competency_gap', array(
                        'user_id' => (int) $user_id, 'skill_id' => (int) $skill_id, 'job_role_id' => $p['job_role_id'],
                        'organization_id' => $p['organization_id'], 'property_id' => $p['property_id'], 'department_id' => $p['department_id'],
                        'required_level' => $required, 'current_level' => $current, 'gap_levels' => $gap,
                        'severity' => $severity, 'reason' => $reason, 'status' => 'open', 'detected_at' => $now, 'updated_at' => $now));
                    $gap_id = (int) $this->CI->db->insert_id();
                    $opened++;
                    // Alert only when assessment evidence revealed the gap; a new hire who has not
                    // been assessed yet is "not ready", not an incident for their manager.
                    if ($severity === 'critical' && $closing_result_id !== null) {
                        $this->CI->ha_notify->send($user_id, 'gap.critical', array(
                            'competency_name' => $r['name_en'], 'current_level' => $current, 'required_level' => $required,
                            'url' => hkp_url('team/employee/' . (int) $user_id), 'related_type' => 'gap', 'related_id' => $gap_id,
                        ));
                        $this->CI->ha_notify->alert(array(
                            'type' => 'critical_gap', 'severity' => 'critical', 'dedupe_key' => 'gap:' . $gap_id,
                            'organization_id' => $p['organization_id'], 'property_id' => $p['property_id'], 'department_id' => $p['department_id'],
                            'title_en' => 'Critical gap: ' . $r['name_en'], 'title_ar' => 'فجوة حرجة: ' . $r['name_ar'],
                            'entity_type' => 'gap', 'entity_id' => $gap_id, 'url' => hkp_url('team/employee/' . (int) $user_id),
                        ));
                    }
                }
            } elseif ($existing) {
                $this->CI->db->where('id', $existing['id'])->update('ha_competency_gap', array(
                    'current_level' => $current, 'gap_levels' => 0, 'severity' => 'none', 'status' => 'closed',
                    'closed_at' => $now, 'closed_by_result_id' => $closing_result_id, 'updated_at' => $now));
                $this->CI->ha_notify->resolve_alert('gap:' . $existing['id']);
                $closed++;
            }
        }
        return array('opened' => $opened, 'closed' => $closed, 'open' => $open_now);
    }

    /** Why the person is short: failed assessment, missing practical, or plain level. */
    protected function gap_reason($user_id, $skill_id, $current) {
        $fails = (int) $this->CI->db->where(array('user_id' => (int) $user_id, 'skill_id' => (int) $skill_id))
            ->where('level_no <', 'previous_level_no + 0', false)->count_all_results('ha_competency_result');
        $practical_fails = $this->CI->db->select('COUNT(*) n', false)->from('ha_practical_assessment pa')
            ->join('ha_rubric r', 'r.id = pa.rubric_id')
            ->where(array('pa.user_id' => (int) $user_id, 'r.skill_id' => (int) $skill_id, 'pa.status' => 'submitted'))
            ->where_in('pa.outcome', array('not_demonstrated', 'developing'))->get()->row()->n;
        if ((int) $practical_fails >= 2) {
            return 'repeated_weakness';
        }
        if ((int) $practical_fails === 1) {
            return 'failed_assessment';
        }
        if ($current === 0) {
            $has_practical = $this->CI->db->where(array('user_id' => (int) $user_id, 'skill_id' => (int) $skill_id, 'source' => 'practical'))
                ->count_all_results('ha_competency_result');
            return $has_practical ? 'below_level' : 'missing_practical';
        }
        return $fails >= 2 ? 'repeated_weakness' : 'below_level';
    }

    public function open_gaps(array $filter = array()) {
        $db = $this->CI->db->select('g.*, s.name_en, s.name_ar, s.code, u.first_name, u.last_name, pr.name_en AS property_en, pr.name_ar AS property_ar, d.name_en AS dept_en, d.name_ar AS dept_ar')
            ->from('ha_competency_gap g')->join('ha_skill s', 's.id = g.skill_id')->join('users u', 'u.id = g.user_id')
            ->join('ha_property pr', 'pr.id = g.property_id', 'left')->join('ha_department d', 'd.id = g.department_id', 'left')
            ->where_in('g.status', isset($filter['status']) ? (array) $filter['status'] : array('open', 'in_action'));
        if (isset($filter['user_ids'])) {
            $ids = $filter['user_ids'] ?: array(0);
            $db->where_in('g.user_id', $ids);
        }
        if (!empty($filter['severity'])) {
            $db->where('g.severity', $filter['severity']);
        }
        if (!empty($filter['property_id'])) {
            $db->where('g.property_id', (int) $filter['property_id']);
        }
        return $db->order_by("FIELD(g.severity,'critical','moderate','minor','none')", '', false)->order_by('g.detected_at', 'DESC')
            ->limit(isset($filter['limit']) ? (int) $filter['limit'] : 500)->get()->result_array();
    }

    // ----------------------------------------------------------------- profile

    /** Employee competency profile: every requirement with level, gap, severity and evidence count. */
    public function profile($user_id) {
        $req = $this->requirements($user_id);
        $p = $this->profile_row($user_id);
        $out = array();
        foreach ($req as $skill_id => $r) {
            $current = $this->current_level($user_id, $skill_id);
            $gap = max(0, (int) $r['required_level'] - $current);
            $critical = (int) $r['is_critical'] || $r['criticality'] === 'critical';
            $out[] = array(
                'skill_id' => (int) $skill_id, 'code' => $r['code'], 'name_en' => $r['name_en'], 'name_ar' => $r['name_ar'],
                'required' => (int) $r['required_level'], 'current' => $current, 'gap' => $gap,
                'critical' => $critical, 'method' => $r['assessment_method'],
                'severity' => $this->severity($gap, $critical, $p ? $p['property_id'] : null),
                'evidence' => (int) $this->CI->db->where(array('user_id' => (int) $user_id, 'skill_id' => (int) $skill_id))->count_all_results('ha_competency_result'),
            );
        }
        usort($out, function ($a, $b) {
            return $b['gap'] - $a['gap'] ?: strcmp($a['name_en'], $b['name_en']);
        });
        return $out;
    }

    /**
     * Matrix for heatmaps: rows are users, columns competencies, cells the gap
     * and severity. One query per table rather than per cell.
     */
    public function matrix(array $user_ids) {
        if (!$user_ids) {
            return array('users' => array(), 'skills' => array(), 'cells' => array());
        }
        $profiles = $this->CI->db->select('p.user_id, p.job_role_id, p.property_id, p.department_id, u.first_name, u.last_name, j.title_en, j.title_ar')
            ->from('ha_profile p')->join('users u', 'u.id = p.user_id')->join('ha_job_role j', 'j.id = p.job_role_id', 'left')
            ->where_in('p.user_id', $user_ids)->where('p.job_role_id IS NOT NULL', null, false)->get()->result_array();
        $role_ids = array_values(array_unique(array_map('intval', array_column($profiles, 'job_role_id'))));
        if (!$role_ids) {
            return array('users' => array(), 'skills' => array(), 'cells' => array());
        }
        $reqs = $this->CI->db->select('rc.*, s.name_en, s.name_ar, s.code, s.criticality')->from('ha_role_competency rc')
            ->join('ha_skill s', 's.id = rc.skill_id')->where_in('rc.job_role_id', $role_ids)->get()->result_array();
        $levels = $this->CI->db->select('user_id, skill_id, level_no')->where_in('user_id', $user_ids)->get('ha_person_skill')->result_array();
        $lvl = array();
        foreach ($levels as $l) {
            $lvl[$l['user_id'] . ':' . $l['skill_id']] = (int) $l['level_no'];
        }
        $by_role = array();
        $skills = array();
        foreach ($reqs as $r) {
            $by_role[(int) $r['job_role_id']][(int) $r['property_key']][(int) $r['skill_id']] = $r;
            $skills[(int) $r['skill_id']] = array('id' => (int) $r['skill_id'], 'code' => $r['code'], 'name_en' => $r['name_en'], 'name_ar' => $r['name_ar']);
        }
        $cells = array();
        foreach ($profiles as $p) {
            $role = (int) $p['job_role_id'];
            $set = isset($by_role[$role][0]) ? $by_role[$role][0] : array();
            if (isset($by_role[$role][(int) $p['property_id']])) {
                $set = $by_role[$role][(int) $p['property_id']] + $set;
            }
            foreach ($set as $sid => $r) {
                $cur = isset($lvl[$p['user_id'] . ':' . $sid]) ? $lvl[$p['user_id'] . ':' . $sid] : 0;
                $gap = max(0, (int) $r['required_level'] - $cur);
                $critical = (int) $r['is_critical'] || $r['criticality'] === 'critical';
                $cells[(int) $p['user_id']][$sid] = array('required' => (int) $r['required_level'], 'current' => $cur, 'gap' => $gap,
                    'severity' => $this->severity($gap, $critical, $p['property_id']));
            }
        }
        uasort($skills, function ($a, $b) { return strcmp($a['name_en'], $b['name_en']); });
        return array('users' => $profiles, 'skills' => $skills, 'cells' => $cells);
    }

    // ---------------------------------------------------------- recommendations

    /**
     * Content recommended for a user's open gaps: modules that develop the
     * competency, knowledge items in its domain, and practice rubrics. Every
     * item passes the same visibility rule as direct access.
     */
    public function recommendations($user_id, $limit = 8) {
        $gaps = $this->open_gaps(array('user_ids' => array((int) $user_id), 'limit' => 20));
        if (!$gaps) {
            return array();
        }
        $skill_ids = array_map('intval', array_column($gaps, 'skill_id'));
        $p = $this->profile_row($user_id);
        $out = array();

        $courses = $this->CI->db->select('c.id, c.code, t.title, cs.skill_id')->from('ha_course_skill cs')
            ->join('ha_course c', 'c.id = cs.course_id')
            ->join('ha_course_translation t', "t.course_id = c.id AND t.locale = " . $this->CI->db->escape(hkp_locale()), 'left')
            ->where_in('cs.skill_id', $skill_ids)->where('c.status', 'published')
            ->group_start()->where('c.organization_id IS NULL', null, false)->or_where('c.organization_id', (int) $p['organization_id'])->group_end()
            ->group_start()->where('c.property_id IS NULL', null, false)->or_where('c.property_id', (int) $p['property_id'])->group_end()
            ->limit($limit)->get()->result_array();
        foreach ($courses as $c) {
            $out[] = array('type' => 'course', 'id' => (int) $c['id'], 'title' => $c['title'] ?: $c['code'], 'skill_id' => (int) $c['skill_id'],
                'url' => hkp_url('learn/module/' . (int) $c['id']));
        }

        $this->CI->load->library('ha_knowledge');
        $domains = $this->CI->db->select('domain_id')->where_in('id', $skill_ids)->where('domain_id IS NOT NULL', null, false)->get('ha_skill')->result_array();
        $domain_ids = array_values(array_unique(array_map('intval', array_column($domains, 'domain_id'))));
        if ($domain_ids) {
            foreach ($this->CI->ha_knowledge->visible_items($user_id, array('domain_ids' => $domain_ids, 'limit' => 4)) as $k) {
                $out[] = array('type' => 'knowledge', 'id' => (int) $k['id'], 'title' => $k['title'], 'skill_id' => null,
                    'url' => hkp_url('knowledge/item/' . (int) $k['id']));
            }
        }

        $rubrics = $this->CI->db->select('id, code, title_en, title_ar, skill_id')->where_in('skill_id', $skill_ids)
            ->where('status', 'published')->limit(4)->get('ha_rubric')->result_array();
        foreach ($rubrics as $r) {
            $out[] = array('type' => 'practice', 'id' => (int) $r['id'], 'title' => hkp_pick($r, 'title'), 'skill_id' => (int) $r['skill_id'],
                'url' => hkp_url('competencies/rubric/' . (int) $r['id']));
        }
        return array_slice($out, 0, $limit);
    }
}
