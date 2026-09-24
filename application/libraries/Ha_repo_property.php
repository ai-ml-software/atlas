<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_repository.php';

/** Hotels and properties (plan section 5, Saudi localization). */
class Ha_repo_property extends Ha_repository {

    protected $table = 'ha_property';
    protected $entity = 'property';
    protected $searchable = array('ha_property.name_en', 'ha_property.name_ar',
        'ha_property.slug', 'ha_property.brand', 'ha_property.city');
    protected $filterable = array(
        'organization_id'    => 'ha_property.organization_id',
        'city'               => 'ha_property.city',
        'region'             => 'ha_property.region',
        'property_type'      => 'ha_property.property_type',
        'operational_status' => 'ha_property.operational_status',
        'status'             => 'ha_property.status',
    );
    protected $sortable = array(
        'id'         => 'ha_property.id',
        'name'       => 'ha_property.name_en',
        'city'       => 'ha_property.city',
        'rooms'      => 'ha_property.room_count',
        'status'     => 'ha_property.status',
        'created_at' => 'ha_property.created_at',
    );
    protected $default_sort = 'name';
    protected $default_direction = 'ASC';
    protected $fillable = array('organization_id', 'name_en', 'name_ar', 'slug', 'brand',
        'property_type', 'city', 'region', 'country', 'room_count', 'operational_status',
        'contact_email', 'contact_phone', 'manager_user_id', 'status');
    protected $tenant_columns = array(
        'organization_id' => 'ha_property.organization_id',
        'property_id'     => 'ha_property.id',
    );

    /** The Saudi cities the plan names, offered first in every picker. */
    public static $saudi_cities = array('Riyadh', 'Jeddah', 'Makkah', 'Madinah',
        'Al Khobar', 'Dammam', 'AlUla', 'Abha');

    public static $regions = array('Riyadh Region', 'Makkah Region', 'Madinah Region',
        'Eastern Province', 'Aseer Region', 'Qassim Region', 'Tabuk Region',
        'Hail Region', 'Jazan Region', 'Najran Region', 'Al Bahah Region',
        'Northern Borders Region', 'Al Jawf Region');

    protected function base_query() {
        return $this->db
            ->select('ha_property.*, o.name_en AS organization_name_en, o.name_ar AS organization_name_ar')
            ->select("TRIM(CONCAT(COALESCE(m.first_name,''),' ',COALESCE(m.last_name,''))) AS manager_name", false)
            ->select('(SELECT COUNT(*) FROM ha_profile pr WHERE pr.property_id = ha_property.id) AS people_count', false)
            ->from('ha_property')
            ->join('ha_organization o', 'o.id = ha_property.organization_id', 'left')
            ->join('users m', 'm.id = ha_property.manager_user_id', 'left');
    }

    protected function can_delete(array $row) {
        return $this->db->where('property_id', $row['id'])->count_all_results('ha_profile') === 0;
    }

    protected function export_columns() {
        return array('id', 'name_en', 'name_ar', 'brand', 'property_type', 'city', 'region',
            'country', 'room_count', 'operational_status', 'manager_name', 'people_count', 'status');
    }

    public function options() {
        $db = $this->db->select('id, name_en, name_ar, city')->from('ha_property')
            ->where('status', 'active')->order_by('name_en', 'ASC');
        $this->CI->ha_auth->scope_query($db, array(
            'organization_id' => 'ha_property.organization_id',
            'property_id'     => 'ha_property.id',
        ));
        return $db->get()->result_array();
    }

    /** Cities that actually have a property, for the public city pages. */
    public function active_cities() {
        return $this->db->select('city, COUNT(*) AS property_count', false)
            ->from('ha_property')->where('status', 'active')
            ->group_by('city')->order_by('city', 'ASC')->get()->result_array();
    }
}
