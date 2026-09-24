<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_repository.php';

/** Departments (plan section 5). */
class Ha_repo_department extends Ha_repository {

    protected $table = 'ha_department';
    protected $entity = 'department';
    protected $searchable = array('ha_department.name_en', 'ha_department.name_ar', 'ha_department.code');
    protected $filterable = array(
        'organization_id' => 'ha_department.organization_id',
        'property_id'     => 'ha_department.property_id',
        'status'          => 'ha_department.status',
        'code'            => 'ha_department.code',
    );
    protected $sortable = array(
        'id'     => 'ha_department.id',
        'name'   => 'ha_department.name_en',
        'code'   => 'ha_department.code',
        'status' => 'ha_department.status',
    );
    protected $default_sort = 'name';
    protected $default_direction = 'ASC';
    protected $fillable = array('organization_id', 'property_id', 'code', 'name_en', 'name_ar',
        'description_en', 'description_ar', 'head_user_id', 'status');
    protected $tenant_columns = array(
        'organization_id' => 'ha_department.organization_id',
        'property_id'     => 'ha_department.property_id',
        'department_id'   => 'ha_department.id',
    );

    protected function base_query() {
        return $this->db
            ->select('ha_department.*, p.name_en AS property_name_en, o.name_en AS organization_name_en')
            ->select("TRIM(CONCAT(COALESCE(h.first_name,''),' ',COALESCE(h.last_name,''))) AS head_name", false)
            ->select('(SELECT COUNT(*) FROM ha_profile pr WHERE pr.department_id = ha_department.id) AS people_count', false)
            ->from('ha_department')
            ->join('ha_property p', 'p.id = ha_department.property_id', 'left')
            ->join('ha_organization o', 'o.id = ha_department.organization_id', 'left')
            ->join('users h', 'h.id = ha_department.head_user_id', 'left');
    }

    protected function can_delete(array $row) {
        $people = $this->db->where('department_id', $row['id'])->count_all_results('ha_profile');
        $roles = $this->db->where('department_id', $row['id'])->count_all_results('ha_job_role');
        return $people === 0 && $roles === 0;
    }

    protected function export_columns() {
        return array('id', 'code', 'name_en', 'name_ar', 'organization_name_en',
            'property_name_en', 'head_name', 'people_count', 'status');
    }

    public function options() {
        $db = $this->db->select('id, code, name_en, name_ar')->from('ha_department')
            ->where('status', 'active')->order_by('name_en', 'ASC');
        $this->CI->ha_auth->scope_query($db, array(
            'organization_id' => 'ha_department.organization_id',
            'property_id'     => 'ha_department.property_id',
            'department_id'   => 'ha_department.id',
        ));
        return $db->get()->result_array();
    }

    /** Department codes used to tag courses, SOPs and skills. */
    public function code_options() {
        return $this->db->select('code, name_en, name_ar')->distinct()
            ->from('ha_department')->where('status', 'active')
            ->order_by('name_en', 'ASC')->get()->result_array();
    }
}
