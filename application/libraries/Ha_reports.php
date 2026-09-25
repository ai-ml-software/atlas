<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reporting engine (ppt-features 24, 66, 120, 177).
 *
 * Every report is a dataset built for the signed-in user's visible people only,
 * with bilingual column titles, and can be shown on screen, exported to CSV
 * (UTF-8 with BOM so Excel reads Arabic), exported to a real .xlsx workbook, or
 * printed / saved as PDF from a print layout that carries the client and
 * property identity, the period, the filters, when and by whom it was
 * generated. Every export is logged to ha_report_run and the audit trail.
 */
class Ha_reports {

    protected $CI;

    public static function catalogue() {
        return array(
            'learning'      => array('Learning completion', 'إكمال التعلم'),
            'assessments'   => array('Assessment results', 'نتائج التقييمات'),
            'competency'    => array('Competency', 'الكفاءات'),
            'gaps'          => array('Competency gaps', 'فجوات الكفاءة'),
            'actions'       => array('Action plans', 'خطط التحسين'),
            'readiness'     => array('Readiness', 'الجاهزية'),
            'certification' => array('Certification', 'الشهادات'),
            'practicals'    => array('Practical assessments', 'التقييمات العملية'),
        );
    }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_audit'));
    }

    /** People the user may report on, narrowed by the filters. */
    public function people(array $f) {
        $ids = $this->CI->ha_auth->visible_user_ids();
        if (!$ids) {
            return array();
        }
        $db = $this->CI->db->select('user_id')->where_in('user_id', $ids);
        foreach (array('property_id', 'department_id', 'job_role_id') as $k) {
            if (!empty($f[$k])) {
                $db->where($k, (int) $f[$k]);
            }
        }
        if (empty($f['include_inactive'])) {
            $db->where_in('status', array('active', 'on_leave'));
        }
        return array_map('intval', array_column($db->get('ha_profile')->result_array(), 'user_id'));
    }

    /** @return array(header => array, rows => array, title => string) */
    public function dataset($code, array $f) {
        $cat = self::catalogue();
        if (!isset($cat[$code])) {
            throw new InvalidArgumentException('Unknown report.');
        }
        $ids = $this->people($f);
        $in = $ids ? implode(',', $ids) : '0';
        $db = $this->CI->db;
        $ar = hkp_locale() === 'ar';
        $from = !empty($f['from']) ? date('Y-m-d', strtotime($f['from'])) : null;
        $to = !empty($f['to']) ? date('Y-m-d 23:59:59', strtotime($f['to'])) : null;
        $period = function ($col) use ($from, $to, $db) {
            $s = '';
            if ($from) {
                $s .= ' AND ' . $col . ' >= ' . $db->escape($from);
            }
            if ($to) {
                $s .= ' AND ' . $col . ' <= ' . $db->escape($to);
            }
            return $s;
        };
        $person = "CONCAT(u.first_name, ' ', u.last_name)";
        $prop = $ar ? 'pr.name_ar' : 'pr.name_en';
        $dept = $ar ? 'd.name_ar' : 'd.name_en';
        $base = "FROM ha_profile p JOIN users u ON u.id = p.user_id LEFT JOIN ha_property pr ON pr.id = p.property_id
            LEFT JOIN ha_department d ON d.id = p.department_id LEFT JOIN ha_job_role j ON j.id = p.job_role_id";
        switch ($code) {
            case 'learning':
                $header = array('Employee', 'Property', 'Department', 'Module', 'Assigned', 'Completed', 'Due', 'Progress %', 'Status');
                $sql = "SELECT $person, $prop, $dept, COALESCE(ct.title, c.code), DATE(e.created_at), DATE(e.completed_at), DATE(e.due_at), e.progress_percentage,
                    CASE WHEN e.status = 'completed' THEN 'completed' WHEN e.due_at < NOW() THEN 'overdue' WHEN e.progress_percentage > 0 THEN 'in_progress' ELSE 'assigned' END
                    $base JOIN ha_enrollment e ON e.user_id = p.user_id JOIN ha_course c ON c.id = e.course_id
                    LEFT JOIN ha_course_translation ct ON ct.course_id = c.id AND ct.locale = " . $db->escape($ar ? 'ar' : 'en') . "
                    WHERE p.user_id IN ($in) AND e.status != 'cancelled'" . $period('e.created_at') . " ORDER BY 1, 4";
                break;
            case 'assessments':
                $header = array('Employee', 'Property', 'Assessment', 'Attempt', 'Score %', 'Result', 'Date');
                $sql = "SELECT $person, $prop, " . ($ar ? 'a.title_ar' : 'a.title_en') . ", at.attempt_no, at.percentage,
                    CASE WHEN at.status != 'graded' THEN 'pending' WHEN at.passed = 1 THEN 'passed' ELSE 'failed' END, DATE(at.submitted_at)
                    $base JOIN ha_assessment_attempt at ON at.user_id = p.user_id JOIN ha_assessment a ON a.id = at.assessment_id
                    WHERE p.user_id IN ($in) AND at.status IN ('submitted','graded')" . $period('at.submitted_at') . " ORDER BY 1, 3, 4";
                break;
            case 'competency':
                $header = array('Employee', 'Role', 'Competency', 'Required', 'Current', 'Gap', 'Status');
                $sql = "SELECT $person, " . ($ar ? 'j.title_ar' : 'j.title_en') . ", " . ($ar ? 's.name_ar' : 's.name_en') . ", rc.required_level, IFNULL(ps.level_no, 0),
                    GREATEST(rc.required_level - IFNULL(ps.level_no, 0), 0), IF(IFNULL(ps.level_no, 0) >= rc.required_level, 'competent', 'gap')
                    $base JOIN ha_role_competency rc ON rc.job_role_id = p.job_role_id AND rc.property_key IN (0, IFNULL(p.property_id, 0))
                    JOIN ha_skill s ON s.id = rc.skill_id LEFT JOIN ha_person_skill ps ON ps.user_id = p.user_id AND ps.skill_id = rc.skill_id
                    WHERE p.user_id IN ($in) ORDER BY 1, 3";
                break;
            case 'gaps':
                $header = array('Employee', 'Property', 'Department', 'Competency', 'Required', 'Current', 'Severity', 'Reason', 'Status', 'Detected');
                $sql = "SELECT $person, $prop, $dept, " . ($ar ? 's.name_ar' : 's.name_en') . ", g.required_level, g.current_level, g.severity, g.reason, g.status, DATE(g.detected_at)
                    $base JOIN ha_competency_gap g ON g.user_id = p.user_id JOIN ha_skill s ON s.id = g.skill_id
                    WHERE p.user_id IN ($in)" . (empty($f['all']) ? " AND g.status IN ('open','in_action')" : '') . $period('g.detected_at') . "
                    ORDER BY FIELD(g.severity,'critical','moderate','minor','none'), 1";
                break;
            case 'actions':
                $header = array('Employee', 'Property', 'Action', 'Type', 'Priority', 'Status', 'Due', 'Completed');
                $sql = "SELECT $person, $prop, ap.title, ap.action_type, ap.priority, ap.status, ap.due_at, DATE(ap.completed_at)
                    $base JOIN ha_action_plan ap ON ap.user_id = p.user_id WHERE p.user_id IN ($in)" . $period('ap.created_at') . " ORDER BY ap.due_at";
                break;
            case 'readiness':
                $header = array('Employee', 'Property', 'Department', 'Role', 'Readiness', 'Missing requirements', 'Calculated');
                $sql = "SELECT $person, $prop, $dept, " . ($ar ? 'j.title_ar' : 'j.title_en') . ", IFNULL(rr.status, 'not_calculated'), rr.reasons_json, rr.calculated_at
                    $base LEFT JOIN ha_readiness_record rr ON rr.user_id = p.user_id AND rr.is_current = 1
                    WHERE p.user_id IN ($in) AND p.job_role_id IS NOT NULL ORDER BY FIELD(IFNULL(rr.status,'x'),'not_ready','conditional','ready'), 1";
                break;
            case 'certification':
                $header = array('Certificate', 'Employee', 'Property', 'Programme', 'Issued', 'Expires', 'Status');
                $sql = "SELECT c.certificate_no, $person, $prop, " . ($ar ? 'c.subject_title_ar' : 'c.subject_title_en') . ", DATE(c.issued_at), DATE(c.expires_at), c.status
                    FROM ha_certificate c JOIN users u ON u.id = c.user_id LEFT JOIN ha_property pr ON pr.id = c.property_id
                    WHERE c.user_id IN ($in)" . $period('c.issued_at') . " ORDER BY c.issued_at DESC";
                break;
            case 'practicals':
                $header = array('Employee', 'Rubric', 'Attempt', 'Score %', 'Outcome', 'Assessor', 'Date');
                $sql = "SELECT $person, " . ($ar ? 'r.title_ar' : 'r.title_en') . ", pa.attempt_no, pa.weighted_score, pa.outcome, CONCAT(asr.first_name, ' ', asr.last_name), DATE(pa.submitted_at)
                    FROM ha_practical_assessment pa JOIN users u ON u.id = pa.user_id JOIN ha_rubric r ON r.id = pa.rubric_id JOIN users asr ON asr.id = pa.assessor_user_id
                    WHERE pa.user_id IN ($in) AND pa.status = 'submitted'" . $period('pa.submitted_at') . " ORDER BY pa.submitted_at DESC";
                break;
        }
        $rows = array();
        foreach ($db->query($sql . ' LIMIT 20000')->result_array() as $r) {
            $r = array_values($r);
            if ($code === 'readiness') {
                $missing = array();
                foreach ((array) json_decode((string) $r[5], true) as $c) {
                    if (empty($c['passed'])) {
                        $missing[] = $c['detail'];
                    }
                }
                $r[5] = implode('; ', $missing);
            }
            foreach ($r as $i => $v) {
                if (is_string($v) && preg_match('/^[a-z_]+$/', $v) && in_array($v, array('completed', 'overdue', 'in_progress', 'assigned', 'passed', 'failed', 'pending',
                    'competent', 'gap', 'critical', 'moderate', 'minor', 'open', 'in_action', 'closed', 'ready', 'conditional', 'not_ready', 'not_calculated', 'issued',
                    'expired', 'revoked', 'developing', 'not_demonstrated', 'exceeds', 'below_level', 'failed_assessment', 'missing_practical', 'repeated_weakness',
                    'submitted', 'under_review', 'rejected', 'training', 'coaching', 'practice', 'low', 'medium', 'high'), true)) {
                    $r[$i] = hkp_label($v);
                }
            }
            $rows[] = $r;
        }
        return array('title' => $ar ? $cat[$code][1] : $cat[$code][0], 'header' => array_map('hkp_t', $header), 'rows' => $rows, 'people' => count($ids));
    }

    /** Header lines identifying client, property, period, filters, generator and time. */
    public function context_lines(array $f) {
        $lines = array();
        $brand = null;
        if (!empty($f['property_id'])) {
            $p = $this->CI->db->get_where('ha_property', array('id' => (int) $f['property_id']))->row_array();
            if ($p) {
                $o = $this->CI->db->get_where('ha_organization', array('id' => $p['organization_id']))->row_array();
                $lines[] = hkp_t('Client') . ': ' . hkp_pick($o, 'name') . ' — ' . hkp_t('Property') . ': ' . hkp_pick($p, 'name');
            }
        } else {
            $org = $this->CI->ha_auth->default_organization_id();
            $o = $org ? $this->CI->db->get_where('ha_organization', array('id' => $org))->row_array() : null;
            $lines[] = hkp_t('Scope') . ': ' . ($o ? hkp_pick($o, 'name') : hkp_t('All clients (platform)'));
        }
        $lines[] = hkp_t('Period') . ': ' . (!empty($f['from']) ? $f['from'] : hkp_t('all time')) . ' → ' . (!empty($f['to']) ? $f['to'] : date('Y-m-d'));
        $filters = array();
        foreach (array('department_id' => 'ha_department', 'job_role_id' => 'ha_job_role') as $k => $t) {
            if (!empty($f[$k])) {
                $row = $this->CI->db->get_where($t, array('id' => (int) $f[$k]))->row_array();
                $filters[] = $row ? (isset($row['name_en']) ? hkp_pick($row, 'name') : hkp_pick($row, 'title')) : $f[$k];
            }
        }
        $lines[] = hkp_t('Filters') . ': ' . ($filters ? implode(', ', $filters) : hkp_t('none'));
        $lines[] = hkp_t('Generated') . ': ' . date('Y-m-d H:i') . ' — ' . hkp_t('by') . ' ' . $this->CI->ha_auth->display_name();
        return $lines;
    }

    public function log_run($code, $format, array $f, $rows) {
        $this->CI->db->insert('ha_report_run', array('report_code' => $code, 'format' => $format, 'filters_json' => json_encode($f),
            'row_count' => (int) $rows, 'generated_by' => $this->CI->ha_auth->id(), 'organization_id' => $this->CI->ha_auth->default_organization_id(),
            'created_at' => date('Y-m-d H:i:s')));
        if ($format !== 'html') {
            $this->CI->ha_audit->log('export', 'report', null, array('description' => 'Report ' . $code . ' exported as ' . $format . ' (' . (int) $rows . ' rows)'));
        }
    }

    public function csv(array $data, array $lines) {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array($data['title']));
        foreach ($lines as $l) {
            fputcsv($fh, array($l));
        }
        fputcsv($fh, array());
        fputcsv($fh, $data['header']);
        foreach ($data['rows'] as $r) {
            fputcsv($fh, array_map(function ($v) {
                // Neutralise spreadsheet formula injection from user-entered text.
                return is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
            }, $r));
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);
        return $out;
    }

    public function xlsx(array $data, array $lines) {
        $this->CI->load->library('ha_xlsx');
        return $this->CI->ha_xlsx->build(array('title' => $data['title'], 'lines' => $lines, 'rtl' => hkp_locale() === 'ar', 'sheet' => $data['title']),
            $data['header'], $data['rows']);
    }

    /** Board / executive summary for a set of people (section 177). */
    public function board(array $f) {
        $ids = $this->people($f);
        $this->CI->load->library(array('ha_kpi', 'ha_readiness', 'ha_competency'));
        $k = $this->CI->ha_kpi->platform_kpis($ids);
        $gaps = $this->CI->ha_competency->open_gaps(array('user_ids' => $ids, 'limit' => 500));
        $by_skill = array();
        foreach ($gaps as $g) {
            $key = hkp_pick($g, 'name');
            if (!isset($by_skill[$key])) {
                $by_skill[$key] = array('n' => 0, 'critical' => 0);
            }
            $by_skill[$key]['n']++;
            if ($g['severity'] === 'critical') {
                $by_skill[$key]['critical']++;
            }
        }
        uasort($by_skill, function ($a, $b) { return $b['critical'] - $a['critical'] ?: $b['n'] - $a['n']; });
        $trend = $this->CI->db->query("SELECT DATE_FORMAT(completed_at, '%Y-%m') m, COUNT(*) n FROM ha_enrollment
            WHERE status = 'completed' AND completed_at >= ? AND user_id IN (" . ($ids ? implode(',', $ids) : '0') . ") GROUP BY m ORDER BY m",
            array(date('Y-m-01', strtotime('-5 months'))))->result_array();
        return array('kpis' => $k, 'readiness' => $this->CI->ha_readiness->summary($ids, 'property'), 'risks' => array_slice($by_skill, 0, 8, true),
            'overdue_actions' => $k['overdue_actions'], 'trend' => $trend, 'people' => count($ids),
            'sources' => array('ha_enrollment', 'ha_assessment_attempt', 'ha_competency_result', 'ha_competency_gap', 'ha_readiness_record', 'ha_certificate', 'ha_kpi_value'));
    }
}
