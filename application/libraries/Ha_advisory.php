<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Advisory layer (ppt-features 39-43, 124-128, 172-175): Altus Ascent
 * engagements, framework assessments and operational quality audits.
 *
 * Frameworks (Performance Matrix, GOPPAR Value Stack, ESG, capability model)
 * share one engine: dimensions, weighted questions scored on the framework's
 * scale, and a result computed from thresholds held in the framework's
 * config_json. The profile describes the Performance Matrix quadrants but not
 * a scoring formula, so the axis threshold ships as an editable, labelled
 * default rather than a number presented as Altus methodology.
 */
class Ha_advisory {

    protected $CI;

    public static $stages = array('discover', 'assess', 'design', 'transform', 'optimise', 'scale');

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_audit'));
    }

    // ---------------------------------------------------------- engagements

    public function create_engagement(array $d, $actor_id) {
        if (!$this->CI->ha_auth->has('engagements.create')) {
            throw new RuntimeException('You do not have permission to create engagements.');
        }
        $org = (int) $d['organization_id'];
        if (!$org || !$this->CI->ha_auth->can_organization($org)) {
            throw new InvalidArgumentException('Choose a client organisation you can access.');
        }
        $name = trim((string) $d['name']);
        if ($name === '') {
            throw new InvalidArgumentException('Name the engagement.');
        }
        $now = date('Y-m-d H:i:s');
        $code = !empty($d['code']) ? $d['code'] : 'ENG-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $this->CI->db->trans_start();
        $this->CI->db->insert('ha_engagement', array('organization_id' => $org, 'property_id' => !empty($d['property_id']) ? (int) $d['property_id'] : null,
            'code' => $code, 'name' => mb_substr($name, 0, 190), 'engagement_type' => !empty($d['engagement_type']) ? $d['engagement_type'] : 'other',
            'consultant_user_id' => !empty($d['consultant_user_id']) ? (int) $d['consultant_user_id'] : (int) $actor_id,
            'start_date' => !empty($d['start_date']) ? $d['start_date'] : date('Y-m-d'), 'end_date' => !empty($d['end_date']) ? $d['end_date'] : null,
            'status' => 'active', 'objectives' => isset($d['objectives']) ? $d['objectives'] : null, 'deliverables' => isset($d['deliverables']) ? $d['deliverables'] : null,
            'created_by' => (int) $actor_id, 'created_at' => $now, 'updated_at' => $now));
        $id = (int) $this->CI->db->insert_id();
        foreach (self::$stages as $i => $s) {
            $this->CI->db->insert('ha_engagement_stage', array('engagement_id' => $id, 'stage' => $s, 'status' => $i === 0 ? 'in_progress' : 'not_started', 'sort_order' => $i));
        }
        $this->CI->db->trans_complete();
        $this->CI->ha_audit->log('create', 'engagement', $id, array('description' => 'Engagement ' . $code . ' created', 'organization_id' => $org));
        return $id;
    }

    public function engagement($id) {
        $e = $this->CI->db->select('e.*, o.name_en AS org_en, o.name_ar AS org_ar, p.name_en AS prop_en, p.name_ar AS prop_ar, u.first_name, u.last_name')
            ->from('ha_engagement e')->join('ha_organization o', 'o.id = e.organization_id')->join('ha_property p', 'p.id = e.property_id', 'left')
            ->join('users u', 'u.id = e.consultant_user_id', 'left')->where('e.id', (int) $id)->get()->row_array();
        if (!$e || !$this->CI->ha_auth->can_organization($e['organization_id'])) {
            return null;
        }
        $e['stages'] = $this->CI->db->order_by('sort_order')->get_where('ha_engagement_stage', array('engagement_id' => (int) $id))->result_array();
        foreach ($e['stages'] as &$s) {
            $s['tasks'] = $this->CI->db->select('t.*, f.token, f.original_name')->from('ha_engagement_task t')->join('ha_file f', 'f.id = t.file_id', 'left')
                ->where('t.stage_id', $s['id'])->order_by('t.due_date IS NULL', '', false)->order_by('t.due_date')->get()->result_array();
        }
        unset($s);
        $e['assessments'] = $this->CI->db->select('a.*, f.name_en AS fw_en, f.name_ar AS fw_ar, f.code AS fw_code')->from('ha_framework_assessment a')
            ->join('ha_framework f', 'f.id = a.framework_id')->where('a.engagement_id', (int) $id)->get()->result_array();
        return $e;
    }

    public function add_task($stage_id, array $d, $actor_id) {
        $stage = $this->CI->db->get_where('ha_engagement_stage', array('id' => (int) $stage_id))->row_array();
        if (!$stage || !$this->engagement($stage['engagement_id']) || !$this->CI->ha_auth->has('engagements.update')) {
            throw new RuntimeException('You cannot change that engagement.');
        }
        if (trim((string) $d['title']) === '') {
            throw new InvalidArgumentException('Describe the task.');
        }
        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_engagement_task', array('stage_id' => (int) $stage_id,
            'task_type' => in_array($d['task_type'], array('task', 'deliverable', 'milestone', 'evidence'), true) ? $d['task_type'] : 'task',
            'title' => mb_substr(trim($d['title']), 0, 255), 'owner_user_id' => !empty($d['owner_user_id']) ? (int) $d['owner_user_id'] : (int) $actor_id,
            'due_date' => !empty($d['due_date']) ? $d['due_date'] : null, 'status' => 'open', 'file_id' => !empty($d['file_id']) ? (int) $d['file_id'] : null,
            'notes' => isset($d['notes']) ? $d['notes'] : null, 'created_at' => $now, 'updated_at' => $now));
        return (int) $this->CI->db->insert_id();
    }

    public function update_stage($stage_id, array $d, $actor_id) {
        $stage = $this->CI->db->get_where('ha_engagement_stage', array('id' => (int) $stage_id))->row_array();
        if (!$stage || !$this->engagement($stage['engagement_id']) || !$this->CI->ha_auth->has('engagements.update')) {
            throw new RuntimeException('You cannot change that engagement.');
        }
        $upd = array();
        foreach (array('objectives', 'due_date', 'owner_user_id') as $k) {
            if (array_key_exists($k, $d)) {
                $upd[$k] = $d[$k] === '' ? null : $d[$k];
            }
        }
        if (!empty($d['status']) && in_array($d['status'], array('not_started', 'in_progress', 'awaiting_signoff', 'signed_off'), true)) {
            $upd['status'] = $d['status'];
            if ($d['status'] === 'signed_off') {
                if (trim((string) (isset($d['signed_off_by']) ? $d['signed_off_by'] : '')) === '') {
                    throw new InvalidArgumentException('Record the client representative who signed off the stage.');
                }
                $upd['signed_off_by'] = mb_substr($d['signed_off_by'], 0, 190);
                $upd['signed_off_at'] = date('Y-m-d H:i:s');
            }
        }
        if ($upd) {
            $this->CI->db->where('id', (int) $stage_id)->update('ha_engagement_stage', $upd);
            $this->CI->ha_audit->log('update', 'engagement_stage', (int) $stage_id, array('description' => 'Stage ' . $stage['stage'] . ' updated', 'after' => $upd));
        }
    }

    public function set_task_status($task_id, $status) {
        $t = $this->CI->db->get_where('ha_engagement_task', array('id' => (int) $task_id))->row_array();
        $stage = $t ? $this->CI->db->get_where('ha_engagement_stage', array('id' => $t['stage_id']))->row_array() : null;
        if (!$stage || !$this->engagement($stage['engagement_id']) || !$this->CI->ha_auth->has('engagements.update')) {
            throw new RuntimeException('You cannot change that task.');
        }
        if (!in_array($status, array('open', 'in_progress', 'done', 'blocked'), true)) {
            throw new InvalidArgumentException('Unknown status.');
        }
        $this->CI->db->where('id', (int) $task_id)->update('ha_engagement_task', array('status' => $status, 'updated_at' => date('Y-m-d H:i:s')));
    }

    // ------------------------------------------------------------ frameworks

    public function framework($code_or_id) {
        $f = is_numeric($code_or_id) ? $this->CI->db->get_where('ha_framework', array('id' => (int) $code_or_id))->row_array()
            : $this->CI->db->get_where('ha_framework', array('code' => $code_or_id))->row_array();
        if (!$f) {
            return null;
        }
        $f['config'] = json_decode((string) $f['config_json'], true) ?: array();
        $f['dimensions'] = $this->CI->db->order_by('sort_order')->get_where('ha_framework_dimension', array('framework_id' => $f['id']))->result_array();
        foreach ($f['dimensions'] as &$d) {
            $d['questions'] = $this->CI->db->order_by('sort_order')->get_where('ha_framework_question', array('dimension_id' => $d['id'], 'status' => 'active'))->result_array();
        }
        unset($d);
        return $f;
    }

    public function start_assessment($framework_id, array $d, $actor_id) {
        if (!$this->CI->ha_auth->has('frameworks.assess')) {
            throw new RuntimeException('You do not have permission to run framework assessments.');
        }
        $org = (int) $d['organization_id'];
        if (!$this->CI->ha_auth->can_organization($org)) {
            throw new RuntimeException('That organisation is outside your scope.');
        }
        $f = $this->framework($framework_id);
        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_framework_assessment', array('framework_id' => (int) $f['id'], 'organization_id' => $org,
            'property_id' => !empty($d['property_id']) ? (int) $d['property_id'] : null, 'engagement_id' => !empty($d['engagement_id']) ? (int) $d['engagement_id'] : null,
            'title' => mb_substr(trim((string) $d['title']) ?: $f['name_en'] . ' ' . date('Y-m-d'), 0, 190), 'assessed_by' => (int) $actor_id,
            'status' => 'draft', 'created_at' => $now, 'updated_at' => $now));
        return (int) $this->CI->db->insert_id();
    }

    public function assessment($id) {
        $a = $this->CI->db->select('a.*, o.name_en AS org_en, o.name_ar AS org_ar, p.name_en AS prop_en, p.name_ar AS prop_ar')->from('ha_framework_assessment a')
            ->join('ha_organization o', 'o.id = a.organization_id')->join('ha_property p', 'p.id = a.property_id', 'left')->where('a.id', (int) $id)->get()->row_array();
        if (!$a || !$this->CI->ha_auth->can_organization($a['organization_id'])) {
            return null;
        }
        $a['framework'] = $this->framework($a['framework_id']);
        $a['answers'] = array();
        foreach ($this->CI->db->get_where('ha_framework_answer', array('assessment_id' => (int) $id))->result_array() as $r) {
            $a['answers'][(int) $r['question_id']] = $r;
        }
        $a['result'] = json_decode((string) $a['result_json'], true);
        return $a;
    }

    /** Saves answers; $complete computes and stores the result. */
    public function save_answers($id, array $answers, $complete, $notes = null) {
        $a = $this->assessment($id);
        if (!$a || !$this->CI->ha_auth->has('frameworks.assess')) {
            throw new RuntimeException('You cannot edit that assessment.');
        }
        $max = (int) $a['framework']['scale_max'];
        foreach ($answers as $qid => $v) {
            $score = isset($v['score']) && $v['score'] !== '' ? max(0, min($max, (float) $v['score'])) : null;
            $row = array('score' => $score, 'evidence' => isset($v['evidence']) ? $v['evidence'] : null, 'recommendation' => isset($v['recommendation']) ? $v['recommendation'] : null);
            if (isset($a['answers'][(int) $qid])) {
                $this->CI->db->where('id', $a['answers'][(int) $qid]['id'])->update('ha_framework_answer', $row);
            } else {
                $this->CI->db->insert('ha_framework_answer', $row + array('assessment_id' => (int) $id, 'question_id' => (int) $qid));
            }
        }
        $upd = array('notes' => $notes, 'updated_at' => date('Y-m-d H:i:s'));
        if ($complete) {
            $res = $this->score($this->assessment($id));
            $upd += array('status' => 'completed', 'result_json' => json_encode($res, JSON_UNESCAPED_UNICODE), 'result_label' => $res['label'], 'assessed_at' => date('Y-m-d H:i:s'));
        }
        $this->CI->db->where('id', (int) $id)->update('ha_framework_assessment', $upd);
        return $this->assessment($id);
    }

    /**
     * Weighted mean per dimension and per axis; the label comes from the
     * framework configuration. Unanswered questions are excluded and reported.
     */
    public function score(array $a) {
        $f = $a['framework'];
        $dims = array();
        $axes = array();
        $unanswered = 0;
        foreach ($f['dimensions'] as $d) {
            $sum = 0;
            $w = 0;
            foreach ($d['questions'] as $q) {
                $ans = isset($a['answers'][(int) $q['id']]) ? $a['answers'][(int) $q['id']] : null;
                if (!$ans || $ans['score'] === null) {
                    $unanswered++;
                    continue;
                }
                $sum += (float) $ans['score'] * (float) $q['weight'];
                $w += (float) $q['weight'];
            }
            $mean = $w > 0 ? round($sum / $w, 2) : null;
            $dims[$d['code']] = array('name_en' => $d['name_en'], 'name_ar' => $d['name_ar'], 'score' => $mean, 'axis' => $d['axis']);
            if ($d['axis'] && $mean !== null) {
                $axes[$d['axis']][] = $mean;
            }
        }
        foreach ($axes as $k => $vals) {
            $axes[$k] = round(array_sum($vals) / count($vals), 2);
        }
        $cfg = $f['config'];
        $label = null;
        if ($f['code'] === 'performance_matrix' && isset($axes['operational'], $axes['digital'])) {
            $t = isset($cfg['axis_threshold']) ? (float) $cfg['axis_threshold'] : ($f['scale_max'] / 2);
            $op = $axes['operational'] >= $t;
            $dg = $axes['digital'] >= $t;
            $label = $op && $dg ? 'altus_zone' : ($op ? 'legacy_operator' : ($dg ? 'digital_veneer' : 'undermanaged_asset'));
        } else {
            $scored = array_filter(array_column($dims, 'score'), function ($s) { return $s !== null; });
            $overall = $scored ? round(array_sum($scored) / count($scored), 2) : null;
            $bands = isset($cfg['bands']) ? $cfg['bands'] : array();
            foreach ($bands as $b) {
                if ($overall !== null && $overall >= (float) $b['min']) {
                    $label = $b['label'];
                }
            }
            $axes['overall'] = $overall;
        }
        $weakest = $dims;
        uasort($weakest, function ($x, $y) { return ($x['score'] === null ? 99 : $x['score']) <=> ($y['score'] === null ? 99 : $y['score']); });
        return array('dimensions' => $dims, 'axes' => $axes, 'label' => $label, 'unanswered' => $unanswered,
            'weakest' => array_slice(array_keys($weakest), 0, 2), 'config' => $cfg, 'scale_max' => (int) $f['scale_max']);
    }

    // --------------------------------------------------------- quality audits

    public function create_audit(array $d, $actor_id) {
        if (!$this->CI->ha_auth->has('quality_audits.create')) {
            throw new RuntimeException('You do not have permission to create audits.');
        }
        $prop = (int) $d['property_id'];
        if (!$this->CI->ha_auth->can_property($prop)) {
            throw new RuntimeException('That property is outside your scope.');
        }
        $p = $this->CI->db->get_where('ha_property', array('id' => $prop))->row_array();
        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_quality_audit', array('organization_id' => $p['organization_id'], 'property_id' => $prop,
            'department_id' => !empty($d['department_id']) ? (int) $d['department_id'] : null,
            'audit_type' => in_array($d['audit_type'], array('internal', 'brand_compliance', 'mystery_guest', 'safety', 'sop_compliance'), true) ? $d['audit_type'] : 'internal',
            'title' => mb_substr(trim((string) $d['title']) ?: 'Audit ' . date('Y-m-d'), 0, 190), 'sop_id' => !empty($d['sop_id']) ? (int) $d['sop_id'] : null,
            'auditor_user_id' => (int) $actor_id, 'conducted_at' => !empty($d['conducted_at']) ? $d['conducted_at'] : date('Y-m-d'),
            'status' => 'in_progress', 'created_at' => $now, 'updated_at' => $now));
        return (int) $this->CI->db->insert_id();
    }

    public function audit($id) {
        $a = $this->CI->db->select('a.*, p.name_en AS prop_en, p.name_ar AS prop_ar')->from('ha_quality_audit a')->join('ha_property p', 'p.id = a.property_id')
            ->where('a.id', (int) $id)->get()->row_array();
        if (!$a || !$this->CI->ha_auth->can_property($a['property_id'])) {
            return null;
        }
        $a['findings'] = $this->CI->db->select('f.*, u.first_name, u.last_name')->from('ha_quality_finding f')->join('users u', 'u.id = f.responsible_user_id', 'left')
            ->where('f.audit_id', (int) $id)->order_by('f.id')->get()->result_array();
        return $a;
    }

    /**
     * Adds a finding. A non-compliant finding with a responsible person opens a
     * corrective action plan for them, so the quality function feeds the same
     * gap -> action -> recheck loop as competency.
     */
    public function add_finding($audit_id, array $d, $actor_id) {
        $a = $this->audit($audit_id);
        if (!$a || !$this->CI->ha_auth->has('quality_audits.update')) {
            throw new RuntimeException('You cannot change that audit.');
        }
        $compliance = in_array($d['compliance'], array('compliant', 'partial', 'non_compliant', 'not_applicable'), true) ? $d['compliance'] : 'compliant';
        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_quality_finding', array('audit_id' => (int) $audit_id, 'requirement' => mb_substr(trim((string) $d['requirement']), 0, 500),
            'compliance' => $compliance, 'severity' => in_array($d['severity'], array('low', 'medium', 'high', 'critical'), true) ? $d['severity'] : 'low',
            'finding' => isset($d['finding']) ? $d['finding'] : null, 'responsible_user_id' => !empty($d['responsible_user_id']) ? (int) $d['responsible_user_id'] : null,
            'recheck_status' => in_array($compliance, array('partial', 'non_compliant'), true) ? 'pending' : 'not_required', 'created_at' => $now, 'updated_at' => $now));
        $fid = (int) $this->CI->db->insert_id();
        if (in_array($compliance, array('partial', 'non_compliant'), true) && !empty($d['responsible_user_id'])) {
            $this->CI->load->library('ha_action_plans');
            $apid = $this->CI->ha_action_plans->create(array('user_id' => (int) $d['responsible_user_id'], 'finding_id' => $fid,
                'title' => 'Audit finding: ' . mb_substr(trim((string) $d['requirement']), 0, 150), 'action_type' => 'sop_review',
                'required_action' => isset($d['finding']) ? $d['finding'] : null, 'priority' => $d['severity'] === 'critical' ? 'critical' : 'high',
                'linked_type' => $a['sop_id'] ? 'sop' : 'none', 'linked_id' => $a['sop_id']), $actor_id);
            $this->CI->db->where('id', $fid)->update('ha_quality_finding', array('action_plan_id' => $apid));
        }
        $this->recompute_audit_score($audit_id);
        return $fid;
    }

    public function recheck($finding_id, $passed) {
        $f = $this->CI->db->get_where('ha_quality_finding', array('id' => (int) $finding_id))->row_array();
        if (!$f || !$this->audit($f['audit_id']) || !$this->CI->ha_auth->has('quality_audits.update')) {
            throw new RuntimeException('You cannot recheck that finding.');
        }
        $this->CI->db->where('id', (int) $finding_id)->update('ha_quality_finding', array('recheck_status' => $passed ? 'passed' : 'failed',
            'compliance' => $passed ? 'compliant' : $f['compliance'], 'rechecked_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')));
        $this->recompute_audit_score($f['audit_id']);
    }

    /** Audit score = share of applicable requirements that are compliant (partial counts half). */
    public function recompute_audit_score($audit_id) {
        $rows = $this->CI->db->get_where('ha_quality_finding', array('audit_id' => (int) $audit_id))->result_array();
        $n = 0;
        $s = 0;
        foreach ($rows as $r) {
            if ($r['compliance'] === 'not_applicable') {
                continue;
            }
            $n++;
            $s += $r['compliance'] === 'compliant' ? 1 : ($r['compliance'] === 'partial' ? 0.5 : 0);
        }
        $this->CI->db->where('id', (int) $audit_id)->update('ha_quality_audit', array('score' => $n ? round(100 * $s / $n, 2) : null, 'updated_at' => date('Y-m-d H:i:s')));
    }
}
