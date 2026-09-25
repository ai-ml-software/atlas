<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * The repository layer is what every admin list and form runs on, so it has to
 * search, filter, sort, paginate, export, write only declared columns, refuse
 * out of scope rows and write an audit entry (plan sections 38, 41, 49).
 */
class Test_repositories extends Ha_testcase {

    /** @var Ha_auth */
    private $auth;
    /** @var Ha_repo_organization */
    private $orgs;
    /** @var Ha_repo_property */
    private $properties;
    /** @var Ha_repo_department */
    private $departments;
    /** @var Ha_repo_job_role */
    private $job_roles;

    public function setUp() {
        $this->CI->load->library('ha_auth');
        $this->CI->load->library('ha_audit');
        $this->CI->load->library('ha_repo_organization');
        $this->CI->load->library('ha_repo_property');
        $this->CI->load->library('ha_repo_department');
        $this->CI->load->library('ha_repo_job_role');
        $this->auth = $this->CI->ha_auth;
        $this->orgs = $this->CI->ha_repo_organization;
        $this->properties = $this->CI->ha_repo_property;
        $this->departments = $this->CI->ha_repo_department;
        $this->job_roles = $this->CI->ha_repo_job_role;
    }

    private function as_user($email) {
        $row = $this->db->get_where('users', array('email' => $email))->row_array();
        $this->auth->assume((int) $row['id']);
        return (int) $row['id'];
    }

    // ------------------------------------------------------------------ lists

    public function test_pagination_returns_a_page_and_a_total() {
        $this->as_user('admin@hospitalityacademy.sa');
        $page = $this->properties->paginate(array('per_page' => 3, 'page' => 1, 'organization_id' => $this->org_id()));
        $this->assertCount(3, $page['rows'], 'Expected a page of three properties');
        $this->assertEquals(8, $page['total'], 'All eight seeded properties should be counted');
        $this->assertEquals(3, $page['pages']);

        $page2 = $this->properties->paginate(array('per_page' => 3, 'page' => 3, 'organization_id' => $this->org_id()));
        $this->assertCount(2, $page2['rows'], 'Last page holds the remainder');
    }

    public function test_search_matches_english_and_arabic() {
        $this->as_user('admin@hospitalityacademy.sa');
        $en = $this->properties->paginate(array('search' => 'AlUla'));
        $this->assertEquals(1, $en['total'], 'Searching English names should match');

        $ar = $this->properties->paginate(array('search' => 'كورنيش'));
        $this->assertEquals(1, $ar['total'], 'Searching Arabic names should match');
        $this->assertEquals('jeddah-corniche', $ar['rows'][0]['slug']);
    }

    public function test_filters_narrow_the_list() {
        $this->as_user('admin@hospitalityacademy.sa');
        $resorts = $this->properties->paginate(array('property_type' => 'resort'));
        $this->assertEquals(2, $resorts['total'], 'Two seeded resorts');

        $preopening = $this->properties->paginate(array('operational_status' => 'pre_opening', 'organization_id' => $this->org_id()));
        $this->assertEquals(1, $preopening['total']);
        $this->assertEquals('abha-highlands', $preopening['rows'][0]['slug']);

        $riyadh = $this->properties->paginate(array('city' => 'Riyadh', 'organization_id' => $this->org_id()));
        $this->assertEquals(1, $riyadh['total']);
    }

    public function test_sorting_is_limited_to_declared_columns() {
        $this->as_user('admin@hospitalityacademy.sa');
        $asc = $this->properties->paginate(array('sort' => 'rooms', 'direction' => 'ASC', 'per_page' => 50));
        $first = (int) $asc['rows'][0]['room_count'];
        $last = (int) $asc['rows'][count($asc['rows']) - 1]['room_count'];
        $this->assertTrue($first <= $last, 'Ascending sort should order by room count');

        // An undeclared sort key must fall back, not reach the database.
        $injected = $this->properties->paginate(array('sort' => 'id; DROP TABLE ha_property', 'per_page' => 2));
        $this->assertCount(2, $injected['rows'], 'An unknown sort key falls back to the default');
        $this->assertDatabaseHas('ha_property', array('slug' => 'riyadh-business-tower'));
    }

    public function test_listings_are_scoped_to_the_signed_in_user() {
        $this->as_user('gm.riyadh@dyafagroup.sa');
        $page = $this->properties->paginate(array('per_page' => 50));
        $this->assertEquals(1, $page['total'], 'A property manager lists only their property');
        $this->assertEquals('riyadh-business-tower', $page['rows'][0]['slug']);

        $this->as_user('org.admin@dyafagroup.sa');
        $all = $this->properties->paginate(array('per_page' => 50));
        $this->assertEquals(8, $all['total'], 'An organization admin lists the whole group');
    }

    public function test_find_refuses_a_row_outside_scope() {
        $jeddah = $this->db->get_where('ha_property', array('slug' => 'jeddah-corniche'))->row_array();
        $this->as_user('gm.riyadh@dyafagroup.sa');
        $this->assertNull($this->properties->find($jeddah['id']),
            'Changing the id in the URL must not expose another property');

        $this->as_user('org.admin@dyafagroup.sa');
        $this->assertNotNull($this->properties->find($jeddah['id']));
    }

    public function test_listing_includes_computed_counts() {
        $this->as_user('admin@hospitalityacademy.sa');
        $page = $this->properties->paginate(array('search' => 'Riyadh Business'));
        $this->assertEquals(1, $page['total']);
        $this->assertGreaterThan(0, (int) $page['rows'][0]['people_count'],
            'Riyadh has seeded staff, so the people count must not be zero');
        $this->assertNotEmpty($page['rows'][0]['organization_name_en']);
    }

    // ----------------------------------------------------------------- writes

    public function test_create_writes_only_declared_columns_and_audits() {
        $this->as_user('admin@hospitalityacademy.sa');
        $before_audit = $this->db->count_all_results('ha_audit_log');

        $id = $this->properties->create(array(
            'organization_id' => $this->org_id(),
            'name_en'         => 'Test Property',
            'name_ar'         => 'فندق اختبار',
            'slug'            => 'test-property-repo',
            'city'            => 'Riyadh',
            'room_count'      => 50,
            // Neither of these is declared fillable and must be ignored.
            'id'              => 999999,
            'created_at'      => '1990-01-01 00:00:00',
        ));

        $this->assertGreaterThan(0, $id);
        $row = $this->db->get_where('ha_property', array('id' => $id))->row_array();
        $this->assertEquals('Test Property', $row['name_en']);
        $this->assertNotEquals('1990-01-01 00:00:00', $row['created_at'],
            'created_at must be stamped by the repository, not by the request');
        $this->assertNotEquals(999999, (int) $row['id'], 'A request must not choose its own id');

        $this->assertEquals($before_audit + 1, $this->db->count_all_results('ha_audit_log'));
        $this->assertDatabaseHas('ha_audit_log', array('action' => 'create', 'entity_type' => 'property', 'entity_id' => $id));

        $this->properties->delete($id);
    }

    public function test_update_records_only_changed_columns() {
        $this->as_user('admin@hospitalityacademy.sa');
        $id = $this->properties->create(array(
            'organization_id' => $this->org_id(),
            'name_en' => 'Before', 'name_ar' => 'قبل', 'slug' => 'test-update-repo',
            'city' => 'Jeddah', 'room_count' => 10,
        ));

        $ok = $this->properties->update($id, array('name_en' => 'After', 'city' => 'Jeddah'));
        $this->assertTrue($ok);

        $audit = $this->db->where('entity_type', 'property')->where('entity_id', $id)
            ->where('action', 'update')->order_by('id', 'DESC')->get('ha_audit_log')->row_array();
        $this->assertNotNull($audit, 'An update must be audited');
        $after = json_decode($audit['after_json'], true);
        $this->assertContains('name_en', array_keys($after), 'The changed column is recorded');
        $this->assertNotContains('city', array_keys($after), 'An unchanged column is not recorded');

        $this->properties->delete($id);
    }

    public function test_update_refuses_a_row_outside_scope() {
        $jeddah = $this->db->get_where('ha_property', array('slug' => 'jeddah-corniche'))->row_array();
        $this->as_user('gm.riyadh@dyafagroup.sa');
        $result = $this->properties->update($jeddah['id'], array('name_en' => 'Hijacked'));
        $this->assertFalse($result, 'A scoped user must not update another property');

        $fresh = $this->db->get_where('ha_property', array('id' => $jeddah['id']))->row_array();
        $this->assertNotEquals('Hijacked', $fresh['name_en']);
    }

    public function test_delete_is_blocked_while_the_record_is_in_use() {
        $this->as_user('admin@hospitalityacademy.sa');
        $riyadh = $this->db->get_where('ha_property', array('slug' => 'riyadh-business-tower'))->row_array();
        $this->assertFalse($this->properties->delete($riyadh['id']),
            'A property with staff must not be deleted');
        $this->assertDatabaseHas('ha_property', array('id' => $riyadh['id']));
    }

    public function test_delete_removes_an_unused_record_and_audits() {
        $this->as_user('admin@hospitalityacademy.sa');
        $id = $this->properties->create(array(
            'organization_id' => $this->org_id(),
            'name_en' => 'Disposable', 'name_ar' => 'مؤقت', 'slug' => 'test-delete-repo',
            'city' => 'Dammam',
        ));
        $this->assertTrue($this->properties->delete($id));
        $this->assertDatabaseMissing('ha_property', array('id' => $id));
        $this->assertDatabaseHas('ha_audit_log', array('action' => 'delete', 'entity_id' => $id, 'entity_type' => 'property'));
    }

    public function test_bulk_update_respects_scope() {
        $this->as_user('admin@hospitalityacademy.sa');
        $ids = array();
        foreach (array('bulk-a', 'bulk-b') as $i => $slug) {
            $ids[] = $this->properties->create(array(
                'organization_id' => $this->org_id(),
                'name_en' => 'Bulk ' . $slug, 'name_ar' => 'مجموعة', 'slug' => 'test-' . $slug,
                'city' => 'Abha',
            ));
        }
        $changed = $this->properties->bulk_update($ids, array('status' => 'suspended'));
        $this->assertEquals(2, $changed);
        $this->assertDatabaseCount('ha_property', 2, array('status' => 'suspended'));

        $this->properties->delete_many($ids);
        $this->assertDatabaseCount('ha_property', 0, array('status' => 'suspended'));
    }

    public function test_export_rows_have_a_header_and_declared_columns() {
        $this->as_user('admin@hospitalityacademy.sa');
        $rows = $this->properties->export_rows(array('city' => 'Riyadh'));
        $this->assertGreaterThanOrEqual(2, count($rows), 'Header plus at least one row');
        $this->assertContains('name_en', $rows[0]);
        $this->assertContains('room_count', $rows[0]);
        $this->assertCount(count($rows[0]), $rows[1], 'Every row matches the header width');
    }

    // ------------------------------------------------------------- other repos

    public function test_department_options_are_scoped() {
        $this->as_user('fom.riyadh@dyafagroup.sa');
        $options = $this->departments->options();
        $this->assertCount(1, $options, 'A department manager picks only their own department');
        $this->assertEquals('FO', $options[0]['code']);

        $this->as_user('org.admin@dyafagroup.sa');
        $this->assertCount(16, $this->departments->options(), 'The organization admin sees all sixteen');
    }

    public function test_job_roles_include_shared_academy_roles() {
        $this->as_user('org.admin@dyafagroup.sa');
        $page = $this->job_roles->paginate(array('per_page' => 100));
        $this->assertEquals(28, $page['total'], 'All seeded career ladder roles');
        $levels = array_column($page['rows'], 'level');
        $this->assertContains('manager', $levels);
        $this->assertContains('entry', $levels);
    }

    public function test_organization_listing_counts_properties() {
        $this->as_user('admin@hospitalityacademy.sa');
        $page = $this->orgs->paginate(array('sort' => 'name_en', 'dir' => 'desc'));
        // Dyafa plus the "Altus Demo Client" second tenant used by the HK&P isolation tests.
        $this->assertEquals(2, $page['total']);
        $counts = array();
        foreach ($page['rows'] as $r) {
            $counts[$r['slug']] = (int) $r['property_count'];
        }
        $this->assertEquals(8, $counts['dyafa-hospitality-group']);
        $this->assertEquals(1, $counts['altus-demo-client']);
    }

    public function test_organization_with_properties_cannot_be_deleted() {
        $this->as_user('admin@hospitalityacademy.sa');
        $this->assertFalse($this->orgs->delete($this->org_id()));
        $this->assertDatabaseHas('ha_organization', array('id' => $this->org_id()));
    }

    private function org_id() {
        return (int) $this->db->get_where('ha_organization', array('slug' => 'dyafa-hospitality-group'))->row()->id;
    }
}
