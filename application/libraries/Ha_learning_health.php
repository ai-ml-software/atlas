<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Read-only diagnostics. Curriculum changes can need review; never rewrite learner history. */
class Ha_learning_health {
    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    public function report($limit = 200) {
        $limit = max(1, min(5000, (int) $limit));
        $checks = array(
            'progress_identity' => "SELECT lp.id AS progress_id, lp.enrollment_id, lp.lesson_id, lp.user_id,
                e.user_id AS enrollment_user_id, e.course_id, l.course_id AS lesson_course_id
                FROM ha_lesson_progress lp LEFT JOIN ha_enrollment e ON e.id=lp.enrollment_id
                LEFT JOIN ha_lesson l ON l.id=lp.lesson_id
                WHERE e.id IS NULL OR l.id IS NULL OR lp.user_id<>e.user_id OR l.course_id<>e.course_id",
            'progress_bounds' => "SELECT lp.id AS progress_id, lp.enrollment_id, lp.lesson_id, lp.watched_percentage
                FROM ha_lesson_progress lp WHERE lp.watched_percentage<0 OR lp.watched_percentage>100",
            'enrollment_totals' => "SELECT e.id AS enrollment_id, e.user_id, e.course_id,
                e.lessons_total AS stored_total, e.lessons_completed AS stored_completed,
                e.progress_percentage AS stored_percentage, COALESCE(p.total,0) AS actual_total,
                COALESCE(p.done,0) AS actual_completed,
                COALESCE(ROUND(100*p.done/NULLIF(p.total,0),2),0) AS actual_percentage
                FROM ha_enrollment e JOIN ha_course c ON c.id=e.course_id
                LEFT JOIN (SELECT e2.id, COUNT(l.id) AS total, SUM(lp.status='completed') AS done
                    FROM ha_enrollment e2 JOIN ha_lesson l ON l.course_id=e2.course_id AND l.status='published'
                    LEFT JOIN ha_lesson_progress lp ON lp.enrollment_id=e2.id AND lp.lesson_id=l.id AND lp.user_id=e2.user_id
                    GROUP BY e2.id) p ON p.id=e.id
                WHERE c.status='published' AND e.status<>'cancelled' AND
                    (e.lessons_total<>COALESCE(p.total,0) OR e.lessons_completed<>COALESCE(p.done,0)
                    OR ABS(e.progress_percentage-COALESCE(ROUND(100*p.done/NULLIF(p.total,0),2),0))>0.01)",
            'lesson_checkpoint' => "SELECT lp.id AS progress_id, lp.enrollment_id, lp.user_id, lp.lesson_id, l.assessment_id
                FROM ha_lesson_progress lp JOIN ha_lesson l ON l.id=lp.lesson_id
                JOIN ha_course c ON c.id=l.course_id
                WHERE c.status='published' AND l.status='published' AND lp.status='completed'
                    AND l.completion_rule='quiz' AND (l.assessment_id IS NULL OR NOT EXISTS
                    (SELECT 1 FROM ha_assessment_attempt a WHERE a.assessment_id=l.assessment_id
                     AND a.user_id=lp.user_id AND a.status='graded' AND a.passed=1))",
            'completed_evidence' => "SELECT e.id AS enrollment_id, e.user_id, e.course_id
                FROM ha_enrollment e JOIN ha_course c ON c.id=e.course_id
                WHERE c.status='published' AND e.status='completed' AND
                (NOT EXISTS (SELECT 1 FROM ha_lesson l WHERE l.course_id=e.course_id AND l.status='published')
                 OR EXISTS (SELECT 1 FROM ha_lesson l WHERE l.course_id=e.course_id AND l.status='published'
                    AND l.is_mandatory=1 AND NOT EXISTS (SELECT 1 FROM ha_lesson_progress lp
                    WHERE lp.enrollment_id=e.id AND lp.user_id=e.user_id AND lp.lesson_id=l.id AND lp.status='completed'))
                 OR EXISTS (SELECT 1 FROM ha_assessment a WHERE a.course_id=e.course_id AND a.status='published'
                    AND NOT EXISTS (SELECT 1 FROM ha_assessment_attempt atp WHERE atp.assessment_id=a.id
                    AND atp.user_id=e.user_id AND atp.status='graded' AND atp.passed=1)))",
        );
        $report = array('checked_at' => gmdate('c'), 'read_only' => true, 'scope' => 'workspace_learning',
            'enrollments' => $this->CI->db->count_all('ha_enrollment'), 'issues_total' => 0, 'checks' => array());
        foreach ($checks as $code => $sql) {
            $total = (int) $this->CI->db->query('SELECT COUNT(*) AS n FROM (' . $sql . ') issues')->row('n');
            $rows = $total ? $this->CI->db->query($sql . ' ORDER BY 1 LIMIT ' . $limit)->result_array() : array();
            $report['checks'][$code] = array('total' => $total, 'records' => $rows, 'truncated' => $total > count($rows));
            $report['issues_total'] += $total;
        }
        $report['unavailable_enrollments'] = (int) $this->CI->db->query("SELECT COUNT(*) AS n FROM ha_enrollment e
            JOIN ha_course c ON c.id=e.course_id WHERE c.status<>'published' AND e.status<>'cancelled'")->row('n');
        $report['ok'] = $report['issues_total'] === 0;
        return $report;
    }
}
