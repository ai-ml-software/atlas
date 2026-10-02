<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test_course_entry extends Ha_testcase {
    private $user;
    private $course;

    public function setUp() {
        $this->CI->load->library(array('session', 'ha_course_entry'));
        $this->db->trans_begin();
        $this->user = (int) $this->db->get_where('users', array('email' => 'omar.learner@dyafagroup.sa'))->row('id');
        $this->course = (int) $this->db->get_where('ha_course', array('code' => 'dy-active-listening'))->row('id');
        // Imports intentionally remain drafts; this fixture represents an available course.
        $this->db->where('id', $this->course)->update('ha_course', array('status' => 'published'));
        $this->db->where('course_id', $this->course)->update('ha_lesson', array('status' => 'published'));
        $this->db->where('course_id', $this->course)->update('ha_assessment', array('status' => 'published'));
        $this->CI->ha_auth->assume($this->user);
        $this->CI->session->unset_userdata(array('ha_course_start', 'hkp_return', 'url_history'));
    }

    public function tearDown() {
        $this->db->trans_rollback();
        $this->CI->session->unset_userdata(array('ha_course_start', 'hkp_return', 'url_history'));
        $this->CI->ha_auth->assume(null);
    }

    public function test_selected_course_is_added_once_and_visible_in_my_learning() {
        $this->CI->ha_course_entry->remember($this->course, 'ar');
        $url = $this->CI->ha_course_entry->finish($this->user);
        $this->assertContains('hkp/learn/module/' . $this->course . '?lang=ar', $url);
        $e = $this->db->get_where('ha_enrollment', array('user_id' => $this->user, 'course_id' => $this->course))->row_array();
        $this->assertNotEmpty($e);
        $this->assertContains($this->course, array_column($this->CI->ha_learning->plan($this->user), 'course_id'));
        $this->CI->ha_course_entry->remember($this->course, 'en');
        $this->CI->ha_course_entry->finish($this->user);
        $this->assertEquals(1, $this->db->where(array('user_id' => $this->user, 'course_id' => $this->course))->count_all_results('ha_enrollment'));
        $this->assertNull($this->CI->ha_course_entry->finish($this->user), 'Intent is consumed');
    }

    public function test_existing_progress_and_enrollment_identity_survive_start() {
        $id = $this->CI->ha_learning->enroll($this->user, $this->course, 'self');
        $this->db->where('id', $id)->update('ha_enrollment', array('status' => 'completed',
            'progress_percentage' => 100, 'lessons_completed' => 4, 'time_spent_seconds' => 321, 'completed_at' => '2026-09-30 12:00:00'));
        $before = $this->db->get_where('ha_enrollment', array('id' => $id))->row_array();
        $this->CI->ha_course_entry->remember($this->course, 'en');
        $this->CI->ha_course_entry->finish($this->user);
        $after = $this->db->get_where('ha_enrollment', array('id' => $id))->row_array();
        foreach (array('id', 'status', 'progress_percentage', 'lessons_completed', 'time_spent_seconds', 'completed_at', 'source') as $key) {
            $this->assertSame($before[$key], $after[$key], $key . ' preserved');
        }
    }

    public function test_invalid_draft_paid_and_private_courses_are_rejected() {
        foreach (array(array('status' => 'draft'), array('is_free' => 0), array('organization_id' => 1), array('property_id' => 1)) as $change) {
            $original = $this->db->get_where('ha_course', array('id' => $this->course))->row_array();
            $this->db->where('id', $this->course)->update('ha_course', $change);
            $this->assertThrows(function () { $this->CI->ha_course_entry->remember($this->course, 'en'); }, 'RuntimeException');
            $this->db->where('id', $this->course)->update('ha_course', array_intersect_key($original, $change));
        }
        $this->assertThrows(function () { $this->CI->ha_course_entry->remember(2147483647, 'en'); }, 'RuntimeException');
    }

    public function test_course_changed_during_login_is_rechecked() {
        $this->CI->ha_course_entry->remember($this->course, 'en');
        $this->db->where('id', $this->course)->update('ha_course', array('status' => 'draft'));
        $this->assertThrows(function () { $this->CI->ha_course_entry->finish($this->user); }, 'RuntimeException');
        $this->assertNull($this->CI->session->userdata('ha_course_start'));
    }

    public function test_expired_or_unauthenticated_intents_do_not_enroll() {
        $this->CI->ha_course_entry->remember($this->course, 'en');
        $intent = $this->CI->session->userdata('ha_course_start');
        $intent['expires'] = time() - 1;
        $this->CI->session->set_userdata('ha_course_start', $intent);
        $this->assertThrows(function () { $this->CI->ha_course_entry->finish($this->user); }, 'RuntimeException');
        $this->CI->ha_course_entry->remember($this->course, 'en');
        $this->CI->ha_auth->assume(0);
        $this->assertThrows(function () { $this->CI->ha_course_entry->finish($this->user); }, 'RuntimeException');
    }

    public function test_return_link_keeps_query_and_is_consumed() {
        $link = site_url('hkp/learn/module/' . $this->course) . '?lang=ar';
        $this->CI->session->set_userdata('hkp_return', $link);
        $this->CI->session->set_userdata('url_history', site_url('en/courses'));
        $this->assertSame($link, $this->CI->ha_course_entry->return_url());
        $this->assertSame(site_url('hkp'), $this->CI->ha_course_entry->return_url());
    }

    public function test_new_registered_learner_can_start_with_no_management_or_tenant_grants() {
        $this->CI->load->model('user_model');
        $this->CI->ha_course_entry->remember($this->course, 'ar');
        $id = (int) $this->CI->user_model->register_user(array('first_name' => 'Test', 'last_name' => 'Learner',
            'email' => 'new-learner-' . bin2hex(random_bytes(5)) . '@example.invalid',
            'password' => sha1('fixture'), 'role_id' => 2, 'status' => 1));
        $this->CI->ha_auth->assume($id);
        $this->assertTrue($this->CI->ha_auth->has('courses.view'));
        $this->assertFalse($this->CI->ha_auth->has('users.update'));
        $this->assertFalse($this->CI->ha_auth->is_system_scoped());
        $profile = $this->CI->ha_auth->profile();
        $this->assertEquals('ar', $profile['locale']);
        $this->assertNull($profile['organization_id']);
        $this->assertNull($profile['property_id']);
        $this->assertContains('learn/module/' . $this->course, $this->CI->ha_course_entry->finish($id));
    }

    public function test_return_link_cannot_leave_site_or_loop_through_login() {
        foreach (array('https://example.com/hkp', '//example.com/hkp', site_url('login'),
            site_url('logout'), site_url('sign_up'), base_url('../other'),
            base_url('%2e%2e/other'), base_url('hkp') . "\r\nLocation: https://example.com",
            base_url('hkp') . '%5cother') as $url) {
            $this->assertFalse($this->CI->ha_course_entry->safe_return($url), $url);
        }
        $this->assertTrue($this->CI->ha_course_entry->safe_return(site_url('hkp/learn') . '?lang=ar'));
    }
}
