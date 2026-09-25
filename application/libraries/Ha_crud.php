<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Schema-driven administration for catalogue entities (ppt-features 79: no
 * copy/paste controllers). Each entity declares its table, permission module,
 * list columns, form fields and tenant column once; list, create, edit and
 * archive/delete all go through the same validated, scoped, audited code.
 *
 * Field types: text, textarea, html, number, date, bool, color, json, url,
 * enum:<a,b,c>, fk:<table>:<label column>, scope (organisation picker).
 */
class Ha_crud {

    protected $CI;

    public static function entities() {
        $status2 = 'enum:active,archived';
        return array(
            'organizations' => array('table' => 'ha_organization', 'perm' => 'organizations', 'title' => 'Organisations', 'scope' => 'id',
                'list' => array('name_en', 'city', 'industry', 'plan', 'status'), 'search' => array('name_en', 'name_ar', 'legal_name'),
                'fields' => array('name_en' => 'text*', 'name_ar' => 'text*', 'slug' => 'slug*', 'legal_name' => 'text', 'registration_no' => 'text', 'industry' => 'text',
                    'country' => 'text', 'city' => 'text', 'contact_name' => 'text', 'contact_email' => 'email', 'contact_phone' => 'text', 'plan' => 'enum:starter,professional,enterprise,portfolio',
                    'currency' => 'text', 'locale' => 'enum:en,ar', 'timezone' => 'text', 'status' => 'enum:active,suspended,archived')),
            'portfolios' => array('table' => 'ha_portfolio', 'perm' => 'organizations', 'title' => 'Portfolios', 'scope' => 'organization_id',
                'list' => array('name_en', 'organization_id', 'status'), 'search' => array('name_en', 'name_ar', 'code'),
                'fields' => array('organization_id' => 'scope*', 'code' => 'slug*', 'name_en' => 'text*', 'name_ar' => 'text*', 'description_en' => 'textarea', 'description_ar' => 'textarea', 'status' => $status2)),
            'properties' => array('table' => 'ha_property', 'perm' => 'properties', 'title' => 'Properties', 'scope' => 'organization_id', 'property_scope' => 'id',
                'list' => array('name_en', 'organization_id', 'city', 'property_type', 'room_count', 'operational_status', 'status'), 'search' => array('name_en', 'name_ar', 'city', 'code'),
                'fields' => array('organization_id' => 'scope*', 'portfolio_id' => 'fk:ha_portfolio:name_en', 'name_en' => 'text*', 'name_ar' => 'text*', 'slug' => 'slug*', 'code' => 'text',
                    'brand' => 'text', 'property_type' => 'enum:hotel,resort,serviced_apartment,furnished_apartment,boutique,chalet,mixed_use,restaurant,cafe,catering,other',
                    'star_rating' => 'number', 'room_count' => 'number', 'country' => 'text', 'region' => 'text', 'city' => 'text*', 'address' => 'text',
                    'contact_email' => 'email', 'contact_phone' => 'text', 'manager_user_id' => 'fk:users:email', 'opening_date' => 'date',
                    'operational_status' => 'enum:operational,pre_opening,renovation,closed', 'timezone' => 'text', 'default_locale' => 'enum:en,ar', 'currency' => 'text',
                    'status' => 'enum:active,suspended,archived')),
            'departments' => array('table' => 'ha_department', 'perm' => 'departments', 'title' => 'Departments', 'scope' => 'organization_id',
                'list' => array('code', 'name_en', 'organization_id', 'property_id', 'status'), 'search' => array('code', 'name_en', 'name_ar'),
                'fields' => array('organization_id' => 'scope*', 'property_id' => 'fk:ha_property:name_en', 'code' => 'slug*', 'name_en' => 'text*', 'name_ar' => 'text*',
                    'description_en' => 'textarea', 'description_ar' => 'textarea', 'head_user_id' => 'fk:users:email', 'status' => $status2)),
            'job_roles' => array('table' => 'ha_job_role', 'perm' => 'job_roles', 'title' => 'Job roles', 'scope' => 'organization_id', 'allow_global' => true,
                'list' => array('code', 'title_en', 'level', 'organization_id', 'status'), 'search' => array('code', 'title_en', 'title_ar'),
                'fields' => array('organization_id' => 'scope', 'department_id' => 'fk:ha_department:name_en', 'code' => 'slug*', 'title_en' => 'text*', 'title_ar' => 'text*',
                    'level' => 'enum:entry,associate,senior,supervisor,assistant_manager,manager,director', 'description_en' => 'textarea', 'description_ar' => 'textarea', 'status' => $status2)),
            'domains' => array('table' => 'ha_domain', 'perm' => 'curriculum', 'title' => 'Professional domains',
                'list' => array('code', 'name_en', 'domain_group', 'is_core', 'status'), 'search' => array('code', 'name_en', 'name_ar'),
                'fields' => array('code' => 'slug*', 'name_en' => 'text*', 'name_ar' => 'text*', 'description_en' => 'textarea', 'description_ar' => 'textarea',
                    'domain_group' => 'enum:core,esg,advisory,leadership,other', 'is_core' => 'bool', 'sort_order' => 'number', 'status' => $status2)),
            'tracks' => array('table' => 'ha_track', 'perm' => 'curriculum', 'title' => 'Tracks', 'scope' => 'organization_id', 'allow_global' => true,
                'list' => array('code', 'title_en', 'domain_id', 'organization_id', 'status'), 'search' => array('code', 'title_en', 'title_ar'),
                'fields' => array('domain_id' => 'fk:ha_domain:name_en*', 'code' => 'slug*', 'title_en' => 'text*', 'title_ar' => 'text*', 'summary_en' => 'textarea', 'summary_ar' => 'textarea',
                    'level' => 'enum:foundation,intermediate,advanced,leadership', 'organization_id' => 'scope', 'property_id' => 'fk:ha_property:name_en', 'sort_order' => 'number',
                    'status' => 'enum:draft,review,approved,published,archived')),
            'competencies' => array('table' => 'ha_skill', 'perm' => 'competencies', 'title' => 'Competency library', 'scope' => 'organization_id', 'allow_global' => true,
                'list' => array('code', 'name_en', 'domain_id', 'criticality', 'assessment_method', 'default_required_level', 'status'), 'search' => array('code', 'name_en', 'name_ar'),
                'fields' => array('code' => 'slug*', 'name_en' => 'text*', 'name_ar' => 'text*', 'description_en' => 'textarea', 'description_ar' => 'textarea', 'domain_id' => 'fk:ha_domain:name_en',
                    'department_code' => 'text', 'criticality' => 'enum:low,medium,high,critical', 'assessment_method' => 'enum:theory,practical,theory_practical,observation',
                    'evidence_type' => 'text', 'default_required_level' => 'number', 'organization_id' => 'scope', 'status' => $status2)),
            'kpis' => array('table' => 'ha_kpi', 'perm' => 'kpis', 'title' => 'KPI definitions', 'scope' => 'organization_id', 'allow_global' => true,
                'list' => array('code', 'name_en', 'category', 'unit', 'direction', 'target', 'source', 'status'), 'search' => array('code', 'name_en', 'name_ar'),
                'fields' => array('code' => 'slug*', 'name_en' => 'text*', 'name_ar' => 'text*', 'category' => 'enum:revenue,cost,guest,people,learning,quality,sustainability,governance,other',
                    'definition_en' => 'textarea', 'definition_ar' => 'textarea', 'formula' => 'text', 'unit' => 'text', 'direction' => 'enum:higher_better,lower_better',
                    'target' => 'number', 'threshold_warning' => 'number', 'threshold_critical' => 'number', 'frequency' => 'enum:daily,weekly,monthly,quarterly,annual',
                    'owner_user_id' => 'fk:users:email', 'source' => 'enum:manual,csv,api,pms,pos,bi,warehouse,platform', 'value_stack' => 'enum:,top_line,distribution,cost,asset',
                    'esg_category' => 'enum:,governance,community,tourism,sustainability', 'capability_category' => 'text', 'organization_id' => 'scope', 'status' => $status2)),
            'sectors' => array('table' => 'ha_sector', 'perm' => 'corporate', 'title' => 'Industry sectors',
                'list' => array('code', 'name_en', 'status'), 'search' => array('name_en', 'name_ar'),
                'fields' => array('code' => 'slug*', 'name_en' => 'text*', 'name_ar' => 'text*', 'sort_order' => 'number', 'status' => $status2)),
            'services' => array('table' => 'ha_service', 'perm' => 'corporate', 'title' => 'Services',
                'list' => array('code', 'title_en', 'division', 'status'), 'search' => array('title_en', 'title_ar'),
                'fields' => array('code' => 'slug*', 'division' => 'enum:hospitality,business_growth', 'title_en' => 'text*', 'title_ar' => 'text*', 'summary_en' => 'textarea', 'summary_ar' => 'textarea', 'sort_order' => 'number', 'status' => 'enum:draft,published')),
            'case_studies' => array('table' => 'ha_case_study', 'perm' => 'corporate', 'title' => 'Case studies',
                'list' => array('title_en', 'geography', 'is_illustrative', 'visibility', 'status'), 'search' => array('title_en', 'title_ar'),
                'fields' => array('slug' => 'slug*', 'title_en' => 'text*', 'title_ar' => 'text*', 'category' => 'text', 'sector_code' => 'text', 'geography' => 'text', 'case_type' => 'text',
                    'client_profile_en' => 'textarea', 'client_profile_ar' => 'textarea', 'challenge_en' => 'textarea', 'challenge_ar' => 'textarea', 'approach_en' => 'textarea', 'approach_ar' => 'textarea',
                    'results_en' => 'textarea', 'results_ar' => 'textarea', 'metrics_json' => 'json', 'is_illustrative' => 'bool', 'visibility' => 'enum:public,client,internal,confidential',
                    'status' => 'enum:draft,published,archived', 'sort_order' => 'number')),
            'leadership' => array('table' => 'ha_leadership_profile', 'perm' => 'corporate', 'title' => 'Leadership profiles',
                'list' => array('name_en', 'role_en', 'status'), 'search' => array('name_en', 'name_ar'),
                'fields' => array('slug' => 'slug*', 'name_en' => 'text*', 'name_ar' => 'text*', 'role_en' => 'text*', 'role_ar' => 'text*', 'biography_en' => 'textarea', 'biography_ar' => 'textarea',
                    'track_record_en' => 'textarea', 'track_record_ar' => 'textarea', 'recognition_en' => 'textarea', 'recognition_ar' => 'textarea', 'education' => 'text', 'credentials' => 'text',
                    'photo_path' => 'text', 'linkedin_url' => 'url', 'sort_order' => 'number', 'status' => 'enum:draft,published')),
            'partners' => array('table' => 'ha_partner', 'perm' => 'corporate', 'title' => 'Partnership directory',
                'list' => array('name', 'category', 'relationship_status', 'is_official', 'visibility'), 'search' => array('name', 'geography'),
                'fields' => array('name' => 'text*', 'category' => 'enum:hotel_operator,global_brand,technology,data,investor,fund,family_office,government,development_authority',
                    'description' => 'textarea', 'geography' => 'text', 'website' => 'url', 'relationship_status' => 'enum:prospect,in_discussion,active,inactive',
                    'is_official' => 'bool', 'internal_notes' => 'textarea', 'visibility' => 'enum:public,internal')),
            'corporate_blocks' => array('table' => 'ha_corporate_block', 'perm' => 'corporate', 'title' => 'Corporate content blocks',
                'list' => array('code', 'section', 'title_en', 'visibility', 'status'), 'search' => array('code', 'title_en', 'title_ar', 'section'),
                'fields' => array('code' => 'slug*', 'section' => 'text*', 'title_en' => 'text*', 'title_ar' => 'text*', 'body_en' => 'textarea', 'body_ar' => 'textarea', 'sort_order' => 'number',
                    'visibility' => 'enum:public,client,internal', 'status' => 'enum:draft,published')),
            'roadmap' => array('table' => 'ha_feature_flag', 'perm' => 'system', 'title' => 'Product roadmap & feature flags',
                'list' => array('code', 'name_en', 'layer', 'status', 'enabled', 'target_release'), 'search' => array('code', 'name_en'),
                'fields' => array('code' => 'slug*', 'name_en' => 'text*', 'name_ar' => 'text*', 'description' => 'textarea', 'layer' => 'enum:experience,knowledge,intelligence,operations,governance',
                    'status' => 'enum:planned,in_development,beta,released,retired', 'enabled' => 'bool', 'target_release' => 'text', 'released_at' => 'date')),
        );
    }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->library(array('ha_auth', 'ha_audit'));
    }

    public function entity($key) {
        $e = self::entities();
        if (!isset($e[$key])) {
            return null;
        }
        $e[$key]['key'] = $key;
        return $e[$key];
    }

    /**
     * Tenant condition for an entity, or null when unrestricted. It runs its
     * own lookups, so call it before starting a query-builder chain.
     */
    protected function scope_sql(array $e, $alias = 't') {
        if (empty($e['scope']) || $this->CI->ha_auth->is_system_scoped()) {
            return null;
        }
        $orgs = $this->CI->ha_auth->organization_ids();
        $p = $this->CI->ha_auth->profile();
        if ($p && $p['organization_id']) {
            $orgs[] = (int) $p['organization_id'];
        }
        $orgs = array_values(array_unique(array_map('intval', $orgs))) ?: array(0);
        $col = $alias . '.' . $e['scope'];
        $sql = $col . ' IN (' . implode(',', $orgs) . ')';
        if (!empty($e['allow_global'])) {
            $sql = '(' . $sql . ' OR ' . $col . ' IS NULL)';
        }
        return $sql;
    }

    protected function scoped($db, $sql) {
        return $sql === null ? $db : $db->where($sql, null, false);
    }

    public function rows(array $e, array $f) {
        $scope = $this->scope_sql($e);
        $db = $this->CI->db->from($e['table'] . ' t');
        $this->scoped($db, $scope);
        if (!empty($f['q'])) {
            $db->group_start();
            foreach ($e['search'] as $c) {
                $db->or_like('t.' . $c, $f['q']);
            }
            $db->group_end();
        }
        $total = $db->count_all_results('', false);
        $page = max(1, (int) (isset($f['page']) ? $f['page'] : 1));
        $rows = $db->order_by('t.id', 'DESC')->limit(50, ($page - 1) * 50)->get()->result_array();
        return array('rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 50)));
    }

    public function find(array $e, $id) {
        $scope = $this->scope_sql($e);
        $db = $this->CI->db->from($e['table'] . ' t')->where('t.id', (int) $id);
        $this->scoped($db, $scope);
        return $db->get()->row_array();
    }

    /** Options for fk fields, scoped to what the user may see. */
    public function options($spec) {
        list(, $table, $label) = explode(':', rtrim($spec, '*'));
        // Scope lookups run their own queries, so resolve them before the builder starts.
        $A = $this->CI->ha_auth;
        $system = $A->is_system_scoped();
        $user_ids = $table === 'users' ? ($A->visible_user_ids() ?: array(0)) : null;
        $org_ids = !$system && in_array($table, array('ha_portfolio', 'ha_department'), true)
            ? ($A->organization_ids() ?: array((int) $A->default_organization_id())) : null;
        $db = $this->CI->db->select('id, ' . $label . ' AS label')->from($table);
        if ($user_ids !== null) {
            $db->where_in('id', $user_ids);
        } elseif ($table === 'ha_property' && !$system) {
            $A->scope_query($db, array('organization_id' => 'organization_id', 'property_id' => 'id'));
        } elseif ($org_ids !== null) {
            $db->where_in('organization_id', $org_ids);
        }
        return $db->order_by($label)->limit(1000)->get()->result_array();
    }

    public function org_options() {
        $A = $this->CI->ha_auth;
        $ids = $A->is_system_scoped() ? null : ($A->organization_ids() ?: array((int) $A->default_organization_id()));
        $db = $this->CI->db->select('id, name_en AS label')->from('ha_organization');
        if ($ids !== null) {
            $db->where_in('id', $ids);
        }
        return $db->order_by('name_en')->get()->result_array();
    }

    /** Validates and saves. Returns the id. */
    public function save(array $e, $id, array $input) {
        $perm = $e['perm'] . '.' . ($id ? 'update' : 'create');
        if (!$this->CI->ha_auth->has($perm)) {
            throw new RuntimeException('You need the ' . $perm . ' permission.');
        }
        $before = $id ? $this->find($e, $id) : null;
        if ($id && !$before) {
            throw new RuntimeException('Record not found in your scope.');
        }
        $row = array();
        $errors = array();
        $fields = $this->CI->db->list_fields($e['table']);
        foreach ($e['fields'] as $name => $type) {
            $required = substr($type, -1) === '*';
            $type = rtrim($type, '*');
            $v = isset($input[$name]) ? $input[$name] : null;
            if ($type === 'bool') {
                $row[$name] = !empty($v) ? 1 : 0;
                continue;
            }
            $v = is_string($v) ? trim($v) : $v;
            if ($v === '' || $v === null) {
                if ($required) {
                    $errors[] = ucfirst(str_replace('_', ' ', $name)) . ' is required.';
                }
                $row[$name] = null;
                continue;
            }
            if ($type === 'number' && !is_numeric($v)) {
                $errors[] = ucfirst(str_replace('_', ' ', $name)) . ' must be a number.';
            } elseif ($type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ucfirst(str_replace('_', ' ', $name)) . ' must be an email address.';
            } elseif ($type === 'url' && !preg_match('~^https?://~i', $v)) {
                $errors[] = ucfirst(str_replace('_', ' ', $name)) . ' must start with http:// or https://.';
            } elseif ($type === 'date' && !strtotime($v)) {
                $errors[] = ucfirst(str_replace('_', ' ', $name)) . ' must be a date.';
            } elseif ($type === 'slug' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]{0,79}$/', $v)) {
                $errors[] = ucfirst(str_replace('_', ' ', $name)) . ' may contain letters, numbers, - and _ only.';
            } elseif (strpos($type, 'enum:') === 0 && !in_array($v, explode(',', substr($type, 5)), true)) {
                $errors[] = ucfirst(str_replace('_', ' ', $name)) . ' has an invalid value.';
            } elseif ($type === 'json') {
                $lines = is_array($v) ? $v : array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $v))));
                $decoded = json_decode(is_string($v) ? $v : '', true);
                $v = json_encode(is_array($decoded) ? $decoded : $lines, JSON_UNESCAPED_UNICODE);
            } elseif ($type === 'scope' && !$this->CI->ha_auth->can_organization((int) $v)) {
                $errors[] = 'You cannot write records for that organisation.';
            }
            $row[$name] = $type === 'date' ? date('Y-m-d', strtotime($v)) : $v;
        }
        if (!empty($e['scope']) && $e['scope'] !== 'id' && isset($e['fields'][$e['scope']]) && $row[$e['scope']] === null
            && empty($e['allow_global']) && !$this->CI->ha_auth->is_system_scoped()) {
            $row[$e['scope']] = $this->CI->ha_auth->default_organization_id();
        }
        if (!empty($e['allow_global']) && $row[$e['scope']] === null && !$this->CI->ha_auth->is_system_scoped()) {
            $errors[] = 'Only the Altus team can create global (all-client) records.';
        }
        foreach (array('code', 'slug') as $uk) {
            if (isset($row[$uk]) && $row[$uk] !== null) {
                $dup = $this->CI->db->where($uk, $row[$uk])->where('id !=', (int) $id)->count_all_results($e['table']);
                if ($dup && !in_array($e['table'], array('ha_department', 'ha_portfolio'), true)) {
                    $errors[] = ucfirst($uk) . ' "' . $row[$uk] . '" is already used.';
                }
            }
        }
        if ($errors) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
        $row = array_intersect_key($row, array_flip($fields));
        $now = date('Y-m-d H:i:s');
        if (in_array('updated_at', $fields, true)) {
            $row['updated_at'] = $now;
        }
        if ($id) {
            $this->CI->db->where('id', (int) $id)->update($e['table'], $row);
            $this->CI->ha_audit->log_change($e['key'], (int) $id, $before, $row);
        } else {
            if (in_array('created_at', $fields, true)) {
                $row['created_at'] = $now;
            }
            $this->CI->db->insert($e['table'], $row);
            $id = (int) $this->CI->db->insert_id();
            $this->CI->ha_audit->log('create', $e['key'], $id, array('after' => $row));
        }
        return (int) $id;
    }

    /** Archives when the table has a status column; deletes only when nothing references the row. */
    public function remove(array $e, $id) {
        if (!$this->CI->ha_auth->has($e['perm'] . '.' . ($e['perm'] === 'corporate' || $e['perm'] === 'system' ? 'update' : 'delete'))) {
            throw new RuntimeException('You do not have permission to remove this.');
        }
        $row = $this->find($e, $id);
        if (!$row) {
            throw new RuntimeException('Record not found in your scope.');
        }
        $fields = $this->CI->db->list_fields($e['table']);
        if (in_array('status', $fields, true)) {
            $this->CI->db->where('id', (int) $id)->update($e['table'], array('status' => in_array('archived', $this->enum_values($e['table'], 'status'), true) ? 'archived' : 'draft'));
            $this->CI->ha_audit->log('update', $e['key'], (int) $id, array('description' => 'Archived'));
            return 'archived';
        }
        $this->CI->db->where('id', (int) $id)->delete($e['table']);
        $this->CI->ha_audit->log('delete', $e['key'], (int) $id, array('before' => $row));
        return 'deleted';
    }

    protected function enum_values($table, $col) {
        $r = $this->CI->db->query('SHOW COLUMNS FROM ' . $table . ' LIKE ' . $this->CI->db->escape($col))->row_array();
        if ($r && preg_match("/^enum\((.*)\)$/", $r['Type'], $m)) {
            return array_map(function ($v) { return trim($v, "'"); }, explode(',', $m[1]));
        }
        return array();
    }
}
