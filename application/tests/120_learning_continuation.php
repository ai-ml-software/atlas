<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test_learning_continuation extends Ha_testcase {
    private $user;
    private $course;
    private $other;
    private $enrollment;
    private $lesson;

    public function setUp() {
        $this->CI->load->library(array('ha_learning', 'ha_learning_health', 'ha_auth'));
        $this->db->trans_begin();
        hkp_locale('en');
        $this->user = (int) $this->db->get_where('users', array('email' => 'omar.learner@dyafagroup.sa'))->row('id');
        $this->CI->ha_auth->assume($this->user);
        $this->course = (int) $this->db->get_where('ha_course', array('code' => 'dy-active-listening'))->row('id');
        $this->other = (int) $this->db->like('code', 'dy-', 'after')->where('id !=', $this->course)->get('ha_course')->row('id');
        foreach (array($this->course, $this->other) as $id) {
            $this->db->where('id', $id)->update('ha_course', array('status' => 'published', 'organization_id' => null, 'property_id' => null));
            $this->db->where('course_id', $id)->update('ha_lesson', array('status' => 'published'));
        }
        $this->enrollment = $this->CI->ha_learning->enroll($this->user, $this->course, 'self');
        $this->lesson = $this->CI->ha_learning->next_lesson($this->enrollment, $this->course);
    }

    public function tearDown() {
        $this->db->trans_rollback();
        $this->CI->ha_auth->assume(null);
        hkp_locale('en');
    }

    private function own_plan() {
        return array_values(array_filter($this->CI->ha_learning->plan($this->user), function ($r) {
            return in_array((int) $r['course_id'], array($this->course, $this->other), true);
        }));
    }

    private function progress($lesson = null, $data = array()) {
        $row = array('enrollment_id' => $this->enrollment, 'user_id' => $this->user, 'lesson_id' => $lesson ?: $this->lesson,
            'status' => 'in_progress', 'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-02 10:00:00');
        $this->db->insert('ha_lesson_progress', array_merge($row, $data));
        return (int) $this->db->insert_id();
    }

    private function findings($code) {
        return $this->CI->ha_learning_health->report()['checks'][$code]['records'];
    }

    public function test_recent_selection_ignores_unavailable_assigned_courses() {
        $other = $this->CI->ha_learning->enroll($this->user, $this->other, 'assigned');
        $this->db->where('id', $this->other)->update('ha_course', array('status' => 'draft'));
        $plan = $this->own_plan();
        $next = $this->CI->ha_learning->continuation($this->user, $plan);
        $this->assertEquals($this->course, $next['course_id']);
        $this->assertEquals('Self selected', $next['selection_label']);
        foreach ($plan as $r) {
            if ((int) $r['id'] === $other) {
                $this->assertFalse($r['course_available']);
                $this->assertFalse($r['can_resume']);
                $this->assertContains('saved progress is retained', $r['availability_note']);
                $this->assertEquals('Assigned', $r['selection_label']);
            }
        }
    }

    public function test_actual_learning_activity_wins_over_enrollment_update_time_and_restores_position() {
        $other = $this->CI->ha_learning->enroll($this->user, $this->other, 'self');
        $this->db->where_in('id', array($other, $this->enrollment))->update('ha_enrollment', array('created_at' => '2026-01-01 00:00:00'));
        $this->db->where('id', $other)->update('ha_enrollment', array('updated_at' => '2099-01-01 00:00:00'));
        $this->progress(null, array('last_position_seconds' => 83));
        $before = $this->db->get_where('ha_enrollment', array('id' => $this->enrollment))->row_array();
        $next = $this->CI->ha_learning->continuation($this->user, $this->own_plan());
        $this->assertEquals($this->course, $next['course_id']);
        $this->assertEquals(83, $next['saved_position']);
        $this->assertContains('/learn/lesson/' . $this->lesson, $next['continue_url']);
        $this->assertNotEmpty($next['lesson_title']);
        $this->assertSame($before, $this->db->get_where('ha_enrollment', array('id' => $this->enrollment))->row_array());
    }

    public function test_completed_expired_cancelled_and_scope_changes_cannot_be_suggested() {
        foreach (array('completed', 'expired', 'cancelled') as $status) {
            $this->db->where('id', $this->enrollment)->update('ha_enrollment', array('status' => $status));
            $this->assertNull($this->CI->ha_learning->continuation($this->user, $this->own_plan()), $status);
        }
        $this->db->where('id', $this->enrollment)->update('ha_enrollment', array('status' => 'active'));
        $this->db->where('id', $this->course)->update('ha_course', array('property_id' => 999999));
        $this->assertNull($this->CI->ha_learning->continuation($this->user, $this->own_plan()));
    }

    public function test_timezone_mismatch_cannot_move_activity_before_enrollment() {
        $other = $this->CI->ha_learning->enroll($this->user, $this->other, 'assigned');
        $this->db->where('id', $other)->update('ha_enrollment', array('created_at' => '2026-01-01 06:00:00'));
        $this->db->where('id', $this->enrollment)->update('ha_enrollment', array('created_at' => '2026-01-01 07:00:00'));
        $this->progress(null, array('updated_at' => '2026-01-01 03:00:00'));
        $next = $this->CI->ha_learning->continuation($this->user, $this->own_plan());
        $this->assertEquals($this->course, $next['course_id']);
        $this->assertEquals('2026-01-01 07:00:00', $next['last_activity_at']);
    }

    public function test_drip_prerequisites_and_quiz_sequence_are_respected() {
        $this->db->where('id', $this->lesson)->update('ha_lesson', array('available_from' => '2099-01-01 00:00:00'));
        $this->assertNull($this->CI->ha_learning->continuation($this->user, $this->own_plan()));
        $this->db->where('id', $this->lesson)->update('ha_lesson', array('available_from' => null));
        $this->db->insert('ha_course_prerequisite', array('course_id' => $this->course, 'prerequisite_course_id' => $this->other));
        $this->assertNull($this->CI->ha_learning->continuation($this->user, $this->own_plan()));
        $this->db->where('course_id', $this->course)->delete('ha_course_prerequisite');
        $this->progress(null, array('status' => 'completed'));
        $plan = $this->own_plan();
        $this->assertNotEmpty($plan[0]['next_lesson_id']);
        $this->assertFalse($plan[0]['can_resume'], 'Missing earlier quiz evidence still blocks the following lesson');
    }

    public function test_finished_lessons_with_pending_assessment_link_to_course() {
        foreach ($this->db->get_where('ha_lesson', array('course_id' => $this->course, 'status' => 'published'))->result_array() as $lesson) {
            $this->progress($lesson['id'], array('status' => 'completed'));
        }
        $next = $this->CI->ha_learning->continuation($this->user, $this->own_plan());
        $this->assertNull($next['next_lesson_id']);
        $this->assertFalse($next['can_resume']);
        $this->assertContains('/learn/module/' . $this->course, $next['continue_url']);
    }

    public function test_health_reports_exact_wrong_owner_and_course_without_writes() {
        $otherLesson = (int) $this->db->get_where('ha_lesson', array('course_id' => $this->other))->row('id');
        $id = $this->progress($otherLesson, array('user_id' => 1));
        $before = $this->db->get_where('ha_lesson_progress', array('id' => $id))->row_array();
        $report = $this->CI->ha_learning_health->report();
        $this->assertFalse($report['ok']);
        $this->assertTrue($report['read_only']);
        $this->assertContains($id, array_column($report['checks']['progress_identity']['records'], 'progress_id'));
        $this->assertSame($before, $this->db->get_where('ha_lesson_progress', array('id' => $id))->row_array());
    }

    public function test_health_detects_stale_rollup_then_accepts_engine_refresh() {
        $this->db->where('id', $this->lesson)->update('ha_lesson', array('completion_rule' => 'manual'));
        $this->progress(null, array('status' => 'completed'));
        $this->assertContains($this->enrollment, array_column($this->findings('enrollment_totals'), 'enrollment_id'));
        $this->CI->ha_learning->refresh_enrollment($this->enrollment);
        $this->assertFalse(in_array($this->enrollment, array_column($this->findings('enrollment_totals'), 'enrollment_id')));
    }

    public function test_optional_lessons_do_not_invalidate_legitimate_course_completion() {
        $this->db->where('course_id', $this->course)->update('ha_lesson', array('is_mandatory' => 0));
        $this->db->where('course_id', $this->course)->update('ha_assessment', array('status' => 'draft'));
        $this->db->where('id', $this->enrollment)->update('ha_enrollment', array('status' => 'completed'));
        $this->assertFalse(in_array($this->enrollment, array_column($this->findings('completed_evidence'), 'enrollment_id')));
    }

    public function test_health_checks_percentage_and_missing_quiz_evidence() {
        $id = $this->progress(null, array('status' => 'completed', 'watched_percentage' => 123));
        $this->assertContains($id, array_column($this->findings('progress_bounds'), 'progress_id'));
        $this->assertContains($id, array_column($this->findings('lesson_checkpoint'), 'progress_id'));
        $this->db->where('id', $this->enrollment)->update('ha_enrollment', array('status' => 'completed'));
        $this->assertContains($this->enrollment, array_column($this->findings('completed_evidence'), 'enrollment_id'));
    }

    public function test_arabic_selection_label_is_translated() {
        hkp_locale('ar');
        $next = $this->CI->ha_learning->continuation($this->user, $this->own_plan());
        $this->assertEquals('اخترتها بنفسك', $next['selection_label']);
    }
}
