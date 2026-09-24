<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Authorization is the control, not the UI. These tests assert the eight roles
 * from plan section 2 exist with real grants, that the permission checks match
 * those grants, and that tenant scope actually stops cross organization,
 * cross property and cross department access (plan sections 38, 45, 52).
 */
class Test_rbac extends Ha_testcase {

    /** @var Ha_auth */
    private $auth;

    public function setUp() {
        $this->CI->load->library('ha_auth');
        $this->auth = $this->CI->ha_auth;
    }

    private function user_id($email) {
        $row = $this->db->get_where('users', array('email' => $email))->row_array();
        return $row ? (int) $row['id'] : null;
    }

    private function as_user($email) {
        $id = $this->user_id($email);
        $this->assertNotNull($id, 'Seeded account missing: ' . $email);
        $this->auth->assume($id);
        return $id;
    }

    // ------------------------------------------------------------------ roles

    public function test_all_eight_roles_are_seeded() {
        $expected = array('super_admin', 'academy_admin', 'instructor', 'org_admin',
            'property_manager', 'department_manager', 'learner', 'auditor');
        foreach ($expected as $code) {
            $this->assertDatabaseHas('ha_role', array('code' => $code), 'Role missing: ' . $code);
        }
        $this->assertDatabaseCount('ha_role', count($expected));
    }

    public function test_every_role_except_learner_has_grants() {
        $roles = $this->db->get('ha_role')->result_array();
        foreach ($roles as $role) {
            $count = $this->db->where('role_id', $role['id'])->count_all_results('ha_role_permission');
            $this->assertGreaterThan(0, $count, 'Role ' . $role['code'] . ' has no permissions');
        }
    }

    public function test_every_permission_belongs_to_a_known_module() {
        $rows = $this->db->select('code, module')->get('ha_permission')->result_array();
        $this->assertGreaterThan(100, count($rows), 'Expected a full permission matrix');
        foreach ($rows as $r) {
            $this->assertEquals($r['module'], substr($r['code'], 0, strrpos($r['code'], '.')),
                'Permission code must be module.action: ' . $r['code']);
        }
    }

    // ------------------------------------------------------------ permissions

    public function test_super_admin_has_everything() {
        $this->as_user('admin@hospitalityacademy.sa');
        $this->assertTrue($this->auth->is_super_admin(), 'Seeded admin should be super admin');
        $this->assertTrue($this->auth->has('settings.update'));
        $this->assertTrue($this->auth->has('competitors.delete'));
        $this->assertTrue($this->auth->has('audit_logs.view'));
        $this->assertTrue($this->auth->is_system_scoped());
    }

    public function test_learner_cannot_administer_anything() {
        $this->as_user('omar.learner@dyafagroup.sa');
        $this->assertTrue($this->auth->has('courses.view'), 'A learner must be able to view courses');
        $this->assertTrue($this->auth->has('sops.acknowledge'), 'A learner must be able to acknowledge SOPs');
        $this->assertFalse($this->auth->has('courses.create'));
        $this->assertFalse($this->auth->has('users.create'));
        $this->assertFalse($this->auth->has('settings.update'));
        $this->assertFalse($this->auth->has('audit_logs.view'));
        $this->assertFalse($this->auth->is_system_scoped());
    }

    public function test_auditor_is_read_only() {
        $this->as_user('auditor@dyafagroup.sa');
        $this->assertTrue($this->auth->has('reports.view'));
        $this->assertTrue($this->auth->has('audit_logs.view'));
        $this->assertTrue($this->auth->has('certificates.view'));
        foreach (array('courses.create', 'courses.update', 'courses.delete', 'users.create',
                     'sops.update', 'settings.update', 'certificates.issue') as $forbidden) {
            $this->assertFalse($this->auth->has($forbidden), 'Auditor must not hold ' . $forbidden);
        }
    }

    public function test_instructor_can_build_courses_but_not_manage_the_organization() {
        $this->as_user('instructor.fo@hospitalityacademy.sa');
        $this->assertTrue($this->auth->has('courses.create'));
        $this->assertTrue($this->auth->has('lessons.create'));
        $this->assertTrue($this->auth->has('assessments.grade'));
        $this->assertFalse($this->auth->has('organizations.create'));
        $this->assertFalse($this->auth->has('properties.create'));
        $this->assertFalse($this->auth->has('settings.update'));
    }

    public function test_property_manager_cannot_create_organizations() {
        $this->as_user('gm.riyadh@dyafagroup.sa');
        $this->assertTrue($this->auth->has('training_assignments.assign'));
        $this->assertTrue($this->auth->has('reports.view'));
        $this->assertFalse($this->auth->has('organizations.create'));
        $this->assertFalse($this->auth->has('users.delete'));
    }

    public function test_has_module_reports_module_level_access() {
        $this->as_user('omar.learner@dyafagroup.sa');
        $this->assertTrue($this->auth->has_module('courses'));
        $this->assertFalse($this->auth->has_module('competitors'));
        $this->assertFalse($this->auth->has_module('settings'));
    }

    public function test_unknown_permission_is_denied() {
        $this->as_user('omar.learner@dyafagroup.sa');
        $this->assertFalse($this->auth->has('does_not_exist.at_all'));
        $this->assertFalse($this->auth->has(array('nope.one', 'nope.two')));
    }

    public function test_signed_out_user_has_no_permissions() {
        $this->auth->assume(null);
        $this->assertFalse($this->auth->check());
        $this->assertFalse($this->auth->has('courses.view'));
        $this->assertFalse($this->auth->has('dashboard.view'));
    }

    public function test_suspended_profile_loses_access() {
        $id = $this->user_id('bandar.learner@dyafagroup.sa');
        $this->db->where('user_id', $id)->update('ha_profile', array('status' => 'suspended'));
        $this->auth->assume($id);
        $this->assertFalse($this->auth->check(), 'A suspended profile must not resolve');
        $this->assertFalse($this->auth->has('courses.view'));
        $this->db->where('user_id', $id)->update('ha_profile', array('status' => 'active'));
        $this->auth->assume($id);
        $this->assertTrue($this->auth->check(), 'Reactivating restores access');
    }

    public function test_deactivated_user_account_loses_access() {
        $id = $this->user_id('noura.learner@dyafagroup.sa');
        $this->db->where('id', $id)->update('users', array('status' => 0));
        $this->auth->assume($id);
        $this->assertFalse($this->auth->check());
        $this->db->where('id', $id)->update('users', array('status' => 1));
        $this->auth->assume($id);
        $this->assertTrue($this->auth->check());
    }

    // ----------------------------------------------------------- tenant scope

    public function test_department_manager_sees_only_their_department() {
        $this->as_user('fom.riyadh@dyafagroup.sa');
        $front_office = $this->db->get_where('ha_department', array('code' => 'FO'))->row_array();
        $housekeeping = $this->db->get_where('ha_department', array('code' => 'HK'))->row_array();

        $this->assertTrue($this->auth->can_department($front_office['id']),
            'Front Office manager must reach their own department');
        $this->assertFalse($this->auth->can_department($housekeeping['id']),
            'Front Office manager must not reach Housekeeping');
    }

    public function test_property_manager_cannot_reach_another_property() {
        $this->as_user('gm.riyadh@dyafagroup.sa');
        $riyadh = $this->db->get_where('ha_property', array('slug' => 'riyadh-business-tower'))->row_array();
        $jeddah = $this->db->get_where('ha_property', array('slug' => 'jeddah-corniche'))->row_array();

        $this->assertTrue($this->auth->can_property($riyadh['id']));
        $this->assertFalse($this->auth->can_property($jeddah['id']),
            'Riyadh manager must not reach the Jeddah property');
    }

    public function test_organization_admin_reaches_every_property_in_their_organization() {
        $this->as_user('org.admin@dyafagroup.sa');
        $properties = $this->db->get('ha_property')->result_array();
        $this->assertGreaterThan(1, count($properties));
        foreach ($properties as $p) {
            $this->assertTrue($this->auth->can_property($p['id']),
                'Organization admin should reach property ' . $p['slug']);
        }
    }

    public function test_organization_admin_cannot_reach_a_foreign_organization() {
        $this->db->insert('ha_organization', array(
            'name_en' => 'Other Group', 'name_ar' => 'مجموعة أخرى', 'slug' => 'other-group-test',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ));
        $other_org = (int) $this->db->insert_id();

        $this->as_user('org.admin@dyafagroup.sa');
        $own = (int) $this->db->get_where('ha_organization', array('slug' => 'dyafa-hospitality-group'))->row()->id;
        $this->assertTrue($this->auth->can_organization($own));
        $this->assertFalse($this->auth->can_organization($other_org),
            'An organization admin must never reach another tenant');

        $this->db->where('id', $other_org)->delete('ha_organization');
    }

    public function test_learner_can_only_see_their_own_record() {
        $mine = $this->as_user('omar.learner@dyafagroup.sa');
        $other = $this->user_id('saud.learner@dyafagroup.sa');
        $this->assertTrue($this->auth->can_user($mine));
        $this->assertFalse($this->auth->can_user($other),
            'A learner must not read another learner');
    }

    public function test_manager_can_see_their_direct_reports() {
        $this->as_user('fom.riyadh@dyafagroup.sa');
        $report = $this->user_id('omar.learner@dyafagroup.sa');   // Front Office, Riyadh
        $outsider = $this->user_id('saud.learner@dyafagroup.sa'); // F&B, Jeddah
        $this->assertTrue($this->auth->can_user($report), 'A manager must see their own team');
        $this->assertFalse($this->auth->can_user($outsider), 'A manager must not see another department');
    }

    public function test_visible_user_ids_are_limited_by_scope() {
        $this->as_user('fom.riyadh@dyafagroup.sa');
        $visible = $this->auth->visible_user_ids();
        $this->assertContains($this->user_id('omar.learner@dyafagroup.sa'), $visible);
        $this->assertNotContains($this->user_id('saud.learner@dyafagroup.sa'), $visible);

        $this->as_user('admin@hospitalityacademy.sa');
        $all = $this->auth->visible_user_ids();
        $this->assertGreaterThan(count($visible), count($all),
            'A super admin sees more people than one department manager');
    }

    public function test_scope_query_constrains_results() {
        $this->as_user('gm.riyadh@dyafagroup.sa');
        $db = $this->db->select('id, slug')->from('ha_property');
        $this->auth->scope_query($db, array('property_id' => 'ha_property.id'));
        $rows = $db->get()->result_array();
        $this->assertCount(1, $rows, 'Property manager should see exactly their property');
        $this->assertEquals('riyadh-business-tower', $rows[0]['slug']);
    }

    public function test_scope_query_returns_nothing_when_scope_is_empty() {
        // A learner has no organization grant on their role rows beyond their
        // own tenant, so an unscoped listing must return nothing, not everything.
        $this->as_user('omar.learner@dyafagroup.sa');
        $db = $this->db->select('id')->from('ha_competitor');
        $this->auth->scope_query($db, array('department_id' => 'ha_competitor.id'));
        $rows = $db->get()->result_array();
        $this->assertCount(0, $rows);
    }
}
