<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_repository.php';

/** Organizations (plan section 5). */
class Ha_repo_organization extends Ha_repository {

    protected $table = 'ha_organization';
    protected $entity = 'organization';
    protected $searchable = array('ha_organization.name_en', 'ha_organization.name_ar',
        'ha_organization.slug', 'ha_organization.contact_email', 'ha_organization.city');
    protected $filterable = array(
        'status'  => 'ha_organization.status',
        'city'    => 'ha_organization.city',
        'country' => 'ha_organization.country',
    );
    protected $sortable = array(
        'id'         => 'ha_organization.id',
        'name'       => 'ha_organization.name_en',
        'city'       => 'ha_organization.city',
        'status'     => 'ha_organization.status',
        'created_at' => 'ha_organization.created_at',
    );
    protected $default_sort = 'name';
    protected $default_direction = 'ASC';
    protected $fillable = array('name_en', 'name_ar', 'slug', 'legal_name', 'registration_no',
        'country', 'city', 'contact_name', 'contact_email', 'contact_phone', 'logo',
        'locale', 'timezone', 'status');
    protected $tenant_columns = array('organization_id' => 'ha_organization.id');

    protected function base_query() {
        return $this->db
            ->select('ha_organization.*')
            ->select('(SELECT COUNT(*) FROM ha_property p WHERE p.organization_id = ha_organization.id) AS property_count', false)
            ->select('(SELECT COUNT(*) FROM ha_profile pr WHERE pr.organization_id = ha_organization.id) AS people_count', false)
            ->from('ha_organization');
    }

    /** An organization with people or properties is archived, never deleted. */
    protected function can_delete(array $row) {
        $properties = $this->db->where('organization_id', $row['id'])->count_all_results('ha_property');
        $people = $this->db->where('organization_id', $row['id'])->count_all_results('ha_profile');
        return $properties === 0 && $people === 0;
    }

    protected function export_columns() {
        return array('id', 'name_en', 'name_ar', 'slug', 'city', 'country',
            'contact_name', 'contact_email', 'contact_phone', 'status', 'property_count', 'people_count');
    }

    /** Options for a select box, scoped to what the user may pick. */
    public function options() {
        $db = $this->db->select('id, name_en, name_ar')->from('ha_organization')
            ->where('status', 'active')->order_by('name_en', 'ASC');
        $this->CI->ha_auth->scope_query($db, array('organization_id' => 'ha_organization.id'));
        return $db->get()->result_array();
    }
}
