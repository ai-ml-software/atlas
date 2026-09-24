<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_repository.php';

/** Job roles, the rungs of the career ladders in plan section 10. */
class Ha_repo_job_role extends Ha_repository {

    protected $table = 'ha_job_role';
    protected $entity = 'job_role';
    protected $searchable = array('ha_job_role.title_en', 'ha_job_role.title_ar', 'ha_job_role.code');
    protected $filterable = array(
        'organization_id' => 'ha_job_role.organization_id',
        'department_id'   => 'ha_job_role.department_id',
        'level'           => 'ha_job_role.level',
        'status'          => 'ha_job_role.status',
    );
    protected $sortable = array(
        'id'    => 'ha_job_role.id',
        'title' => 'ha_job_role.title_en',
        'level' => 'ha_job_role.level',
        'code'  => 'ha_job_role.code',
    );
    protected $default_sort = 'title';
    protected $default_direction = 'ASC';
    protected $fillable = array('organization_id', 'department_id', 'code', 'title_en', 'title_ar',
        'level', 'description_en', 'description_ar', 'status');
    protected $tenant_columns = array('organization_id' => 'ha_job_role.organization_id');

    public static $levels = array('entry', 'associate', 'senior', 'supervisor',
        'assistant_manager', 'manager', 'director');

    protected function scope_allows_global() {
        // Academy wide job roles have no organization and are shared.
        return true;
    }

    protected function base_query() {
        return $this->db
            ->select('ha_job_role.*, d.name_en AS department_name_en, d.name_ar AS department_name_ar, d.code AS department_code')
            ->select('(SELECT COUNT(*) FROM ha_profile pr WHERE pr.job_role_id = ha_job_role.id) AS people_count', false)
            ->from('ha_job_role')
            ->join('ha_department d', 'd.id = ha_job_role.department_id', 'left');
    }

    protected function can_delete(array $row) {
        return $this->db->where('job_role_id', $row['id'])->count_all_results('ha_profile') === 0;
    }

    protected function export_columns() {
        return array('id', 'code', 'title_en', 'title_ar', 'department_name_en', 'level', 'people_count', 'status');
    }

    public function options() {
        $db = $this->db->select('id, code, title_en, title_ar, level')->from('ha_job_role')
            ->where('status', 'active')->order_by('title_en', 'ASC');
        $this->CI->ha_auth->scope_query($db, array('organization_id' => 'ha_job_role.organization_id'), true);
        return $db->get()->result_array();
    }
}
