<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlled CSV imports (ppt-features 67): users, departments, job roles,
 * competencies, role-competency requirements, KPI values.
 *
 *   upload -> parse -> validate every row -> preview (stored, nothing written)
 *          -> commit in one transaction -> created ids recorded -> rollback
 *
 * Duplicates are detected against the database and within the file. A commit
 * either writes every valid row or none. Rollback removes the rows the run
 * created (users are deactivated rather than deleted, because deleting a person
 * would delete their history).
 */
class Ha_importer {

    protected $CI;

    public static function types() {
        return array(
            'users'             => array('email', 'first_name', 'last_name', 'name_ar', 'employee_no', 'property_slug', 'department_code', 'job_role_code', 'role_code', 'locale', 'hire_date', 'manager_email'),
            'departments'       => array('code', 'name_en', 'name_ar', 'property_slug'),
            'job_roles'         => array('code', 'title_en', 'title_ar', 'department_code', 'level'),
            'competencies'      => array('code', 'name_en', 'name_ar', 'domain_code', 'criticality', 'assessment_method', 'default_required_level'),
            'role_competencies' => array('job_role_code', 'competency_code', 'required_level', 'is_critical', 'property_slug'),
            'kpi_values'        => array('kpi_code', 'property_slug', 'period_start', 'period_end', 'actual', 'target'),
        );
    }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->library(array('ha_auth', 'ha_audit'));
    }

    public function parse_csv($path) {
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new RuntimeException('The file could not be read.');
        }
        $first = fgets($fh);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first);
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(function ($h) { return strtolower(trim($h)); }, str_getcsv($first, $delim));
        $rows = array();
        while (($r = fgetcsv($fh, 0, $delim)) !== false) {
            if (count(array_filter($r, function ($v) { return trim((string) $v) !== ''; })) === 0) {
                continue;
            }
            $row = array();
            foreach ($header as $i => $h) {
                $row[$h] = isset($r[$i]) ? trim($r[$i]) : '';
            }
            $rows[] = $row;
            if (count($rows) > 5000) {
                throw new InvalidArgumentException('Import at most 5,000 rows per file.');
            }
        }
        fclose($fh);
        return array('header' => $header, 'rows' => $rows);
    }

    /** Validates and stores a preview. Nothing but the run record is written. */
    public function preview($type, $path, $file_name, $organization_id) {
        $types = self::types();
        if (!isset($types[$type])) {
            throw new InvalidArgumentException('Unknown import type.');
        }
        if (!$this->CI->ha_auth->has('imports.run')) {
            throw new RuntimeException('You do not have permission to import.');
        }
        if (!$organization_id || !$this->CI->ha_auth->can_organization($organization_id)) {
            throw new RuntimeException('Choose an organisation you manage.');
        }
        $csv = $this->parse_csv($path);
        $missing = array_diff(array($types[$type][0], $types[$type][1]), $csv['header']);
        if ($missing) {
            throw new InvalidArgumentException('Missing required columns: ' . implode(', ', $missing) . '. Expected: ' . implode(', ', $types[$type]));
        }
        $errors = array();
        $valid = array();
        $seen = array();
        foreach ($csv['rows'] as $i => $row) {
            $line = $i + 2;
            $e = $this->validate($type, $row, (int) $organization_id);
            $key = strtolower($row[$types[$type][0]] . '|' . (isset($row[$types[$type][1]]) && $type === 'role_competencies' ? $row[$types[$type][1]] : ''));
            if (isset($seen[$key])) {
                $e[] = 'Duplicate of line ' . $seen[$key] . ' in this file';
            }
            $seen[$key] = $line;
            if ($e) {
                $errors[] = array('line' => $line, 'errors' => $e, 'row' => $row);
            } else {
                $valid[] = $row;
            }
        }
        $this->CI->db->insert('ha_import_run', array('import_type' => $type, 'organization_id' => (int) $organization_id, 'file_name' => mb_substr($file_name, 0, 255),
            'status' => 'previewed', 'rows_total' => count($csv['rows']), 'rows_valid' => count($valid), 'rows_error' => count($errors),
            'errors_json' => json_encode($errors, JSON_UNESCAPED_UNICODE), 'payload_json' => json_encode($valid, JSON_UNESCAPED_UNICODE),
            'created_by' => $this->CI->ha_auth->id(), 'created_at' => date('Y-m-d H:i:s')));
        return (int) $this->CI->db->insert_id();
    }

    protected function validate($type, array $r, $org) {
        $db = $this->CI->db;
        $e = array();
        $prop = function ($slug) use ($db, $org) {
            return $slug === '' ? null : $db->get_where('ha_property', array('slug' => $slug, 'organization_id' => $org))->row_array();
        };
        switch ($type) {
            case 'users':
                if (!filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                    $e[] = 'Invalid email';
                } elseif ($db->where('email', $r['email'])->count_all_results('users')) {
                    $e[] = 'A user with this email already exists';
                }
                if ($r['first_name'] === '') {
                    $e[] = 'first_name is required';
                }
                if (!empty($r['property_slug']) && !$prop($r['property_slug'])) {
                    $e[] = 'Unknown property ' . $r['property_slug'] . ' in this organisation';
                }
                if (!empty($r['property_slug']) && ($p = $prop($r['property_slug'])) && !$this->CI->ha_auth->can_property($p['id'])) {
                    $e[] = 'You cannot add staff to ' . $r['property_slug'];
                }
                if (!empty($r['department_code']) && !$db->where(array('organization_id' => $org, 'code' => $r['department_code']))->count_all_results('ha_department')) {
                    $e[] = 'Unknown department ' . $r['department_code'];
                }
                if (!empty($r['job_role_code']) && !$db->where('code', $r['job_role_code'])->count_all_results('ha_job_role')) {
                    $e[] = 'Unknown job role ' . $r['job_role_code'];
                }
                $role = !empty($r['role_code']) ? $r['role_code'] : 'learner';
                $rr = $db->get_where('ha_role', array('code' => $role))->row_array();
                if (!$rr) {
                    $e[] = 'Unknown access role ' . $role;
                } elseif ($rr['scope'] === 'system' && !$this->CI->ha_auth->is_system_scoped()) {
                    $e[] = 'Only a platform administrator can grant ' . $role;
                }
                if (!empty($r['hire_date']) && !strtotime($r['hire_date'])) {
                    $e[] = 'hire_date is not a date';
                }
                break;
            case 'departments':
                if (!preg_match('/^[A-Za-z0-9_\-]{1,60}$/', $r['code'])) {
                    $e[] = 'code must be letters, numbers, - or _';
                }
                if ($r['name_en'] === '' || (isset($r['name_ar']) && $r['name_ar'] === '')) {
                    $e[] = 'name_en and name_ar are required';
                }
                $p = !empty($r['property_slug']) ? $prop($r['property_slug']) : null;
                if (!empty($r['property_slug']) && !$p) {
                    $e[] = 'Unknown property';
                }
                $w = array('organization_id' => $org, 'code' => $r['code']);
                $p ? $w['property_id'] = $p['id'] : $db->where('property_id IS NULL', null, false);
                if ($db->where($w)->count_all_results('ha_department')) {
                    $e[] = 'Department already exists';
                }
                break;
            case 'job_roles':
                if ($db->where('code', $r['code'])->count_all_results('ha_job_role')) {
                    $e[] = 'Job role code already exists';
                }
                if ($r['title_en'] === '' || !isset($r['title_ar']) || $r['title_ar'] === '') {
                    $e[] = 'title_en and title_ar are required';
                }
                if (!empty($r['level']) && !in_array($r['level'], array('entry', 'associate', 'senior', 'supervisor', 'assistant_manager', 'manager', 'director'), true)) {
                    $e[] = 'Unknown level';
                }
                break;
            case 'competencies':
                if ($db->where('code', $r['code'])->count_all_results('ha_skill')) {
                    $e[] = 'Competency code already exists';
                }
                if ($r['name_en'] === '' || !isset($r['name_ar']) || $r['name_ar'] === '') {
                    $e[] = 'name_en and name_ar are required';
                }
                if (!empty($r['domain_code']) && !$db->where('code', $r['domain_code'])->count_all_results('ha_domain')) {
                    $e[] = 'Unknown domain';
                }
                if (!empty($r['criticality']) && !in_array($r['criticality'], array('low', 'medium', 'high', 'critical'), true)) {
                    $e[] = 'Unknown criticality';
                }
                break;
            case 'role_competencies':
                if (!$db->where('code', $r['job_role_code'])->count_all_results('ha_job_role')) {
                    $e[] = 'Unknown job role';
                }
                if (!$db->where('code', $r['competency_code'])->count_all_results('ha_skill')) {
                    $e[] = 'Unknown competency';
                }
                if (!isset($r['required_level']) || !ctype_digit((string) $r['required_level']) || (int) $r['required_level'] < 1 || (int) $r['required_level'] > 5) {
                    $e[] = 'required_level must be 1-5';
                }
                break;
            case 'kpi_values':
                if (!$db->where('code', $r['kpi_code'])->count_all_results('ha_kpi')) {
                    $e[] = 'Unknown KPI';
                }
                $p = $prop(isset($r['property_slug']) ? $r['property_slug'] : '');
                if (!$p) {
                    $e[] = 'Unknown property';
                } elseif (!$this->CI->ha_auth->can_property($p['id'])) {
                    $e[] = 'Property outside your scope';
                }
                if (empty($r['period_start']) || !strtotime($r['period_start'])) {
                    $e[] = 'period_start is not a date';
                }
                if (!isset($r['actual']) || !is_numeric($r['actual'])) {
                    $e[] = 'actual must be a number';
                }
                break;
        }
        return $e;
    }

    /** Writes every valid row of a previewed run in one transaction. */
    public function commit($run_id) {
        $run = $this->CI->db->get_where('ha_import_run', array('id' => (int) $run_id))->row_array();
        if (!$run || $run['status'] !== 'previewed') {
            throw new InvalidArgumentException('That import is not awaiting commit.');
        }
        if (!$this->CI->ha_auth->can_organization($run['organization_id'])) {
            throw new RuntimeException('That import belongs to another organisation.');
        }
        $rows = json_decode((string) $run['payload_json'], true) ?: array();
        $org = (int) $run['organization_id'];
        $created = array();
        $db = $this->CI->db;
        $now = date('Y-m-d H:i:s');
        $db->trans_begin();
        try {
            foreach ($rows as $r) {
                $again = $this->validate($run['import_type'], $r, $org);
                if ($again) {
                    throw new RuntimeException('Row for ' . reset($r) . ' is no longer valid: ' . implode('; ', $again));
                }
                $created[] = $this->write($run['import_type'], $r, $org, $now);
            }
            if ($db->trans_status() === false) {
                throw new RuntimeException('The database rejected the import.');
            }
            $db->trans_commit();
        } catch (Exception $e) {
            $db->trans_rollback();
            $db->where('id', (int) $run_id)->update('ha_import_run', array('status' => 'failed', 'errors_json' => json_encode(array(array('line' => 0, 'errors' => array($e->getMessage()))))));
            throw $e;
        }
        $db->where('id', (int) $run_id)->update('ha_import_run', array('status' => 'committed', 'committed_at' => $now, 'created_ids_json' => json_encode($created)));
        $this->CI->ha_audit->log('import', $run['import_type'], (int) $run_id, array('description' => count($created) . ' ' . $run['import_type'] . ' imported', 'organization_id' => $org));
        if ($run['import_type'] === 'users') {
            $this->CI->load->library(array('ha_learning', 'ha_readiness'));
            foreach ($created as $uid) {
                $this->CI->ha_learning->sync_role_plan($uid);
                $this->CI->ha_readiness->calculate($uid);
            }
        }
        return count($created);
    }

    protected function write($type, array $r, $org, $now) {
        $db = $this->CI->db;
        $prop = !empty($r['property_slug']) ? $db->get_where('ha_property', array('slug' => $r['property_slug'], 'organization_id' => $org))->row_array() : null;
        switch ($type) {
            case 'users':
                $db->insert('users', array('first_name' => $r['first_name'], 'last_name' => isset($r['last_name']) ? $r['last_name'] : '', 'email' => $r['email'],
                    'password' => sha1(bin2hex(random_bytes(12))), 'role_id' => 2, 'status' => 1, 'is_instructor' => 0, 'skills' => '[]', 'payment_keys' => '[]',
                    'sessions' => '[]', 'social_links' => '{"facebook":"","twitter":"","linkedin":""}', 'wishlist' => '[]', 'date_added' => time(), 'last_modified' => time()));
                $uid = (int) $db->insert_id();
                $dept = !empty($r['department_code']) ? $db->get_where('ha_department', array('organization_id' => $org, 'code' => $r['department_code']))->row_array() : null;
                $job = !empty($r['job_role_code']) ? $db->get_where('ha_job_role', array('code' => $r['job_role_code']))->row_array() : null;
                $mgr = !empty($r['manager_email']) ? $db->get_where('users', array('email' => $r['manager_email']))->row_array() : null;
                $db->insert('ha_profile', array('user_id' => $uid, 'employee_no' => !empty($r['employee_no']) ? $r['employee_no'] : null,
                    'full_name_ar' => !empty($r['name_ar']) ? $r['name_ar'] : null, 'job_title_en' => $job ? $job['title_en'] : null, 'job_title_ar' => $job ? $job['title_ar'] : null,
                    'organization_id' => $org, 'property_id' => $prop ? $prop['id'] : null, 'department_id' => $dept ? $dept['id'] : null,
                    'job_role_id' => $job ? $job['id'] : null, 'manager_user_id' => $mgr ? $mgr['id'] : null,
                    'locale' => isset($r['locale']) && $r['locale'] === 'ar' ? 'ar' : 'en', 'hire_date' => !empty($r['hire_date']) ? date('Y-m-d', strtotime($r['hire_date'])) : null,
                    'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
                $role = $db->get_where('ha_role', array('code' => !empty($r['role_code']) ? $r['role_code'] : 'learner'))->row_array();
                $db->insert('ha_user_role', array('user_id' => $uid, 'role_id' => $role['id'], 'organization_id' => $org, 'property_id' => $prop ? $prop['id'] : null,
                    'department_id' => $dept ? $dept['id'] : null, 'created_at' => $now));
                return $uid;
            case 'departments':
                $db->insert('ha_department', array('organization_id' => $org, 'property_id' => $prop ? $prop['id'] : null, 'code' => $r['code'],
                    'name_en' => $r['name_en'], 'name_ar' => $r['name_ar'], 'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
                return (int) $db->insert_id();
            case 'job_roles':
                $dept = !empty($r['department_code']) ? $db->get_where('ha_department', array('organization_id' => $org, 'code' => $r['department_code']))->row_array() : null;
                $db->insert('ha_job_role', array('organization_id' => $org, 'department_id' => $dept ? $dept['id'] : null, 'code' => $r['code'], 'title_en' => $r['title_en'],
                    'title_ar' => $r['title_ar'], 'level' => !empty($r['level']) ? $r['level'] : 'entry', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
                return (int) $db->insert_id();
            case 'competencies':
                $dom = !empty($r['domain_code']) ? $db->get_where('ha_domain', array('code' => $r['domain_code']))->row_array() : null;
                $db->insert('ha_skill', array('code' => $r['code'], 'name_en' => $r['name_en'], 'name_ar' => $r['name_ar'], 'domain_id' => $dom ? $dom['id'] : null,
                    'organization_id' => $org, 'criticality' => !empty($r['criticality']) ? $r['criticality'] : 'medium',
                    'assessment_method' => !empty($r['assessment_method']) ? $r['assessment_method'] : 'theory_practical',
                    'default_required_level' => !empty($r['default_required_level']) ? (int) $r['default_required_level'] : 3, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
                return (int) $db->insert_id();
            case 'role_competencies':
                $job = $db->get_where('ha_job_role', array('code' => $r['job_role_code']))->row_array();
                $sk = $db->get_where('ha_skill', array('code' => $r['competency_code']))->row_array();
                $db->query('INSERT INTO ha_role_competency (job_role_id, skill_id, property_key, required_level, is_critical, created_at, updated_at) VALUES (?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE required_level = VALUES(required_level), is_critical = VALUES(is_critical), updated_at = VALUES(updated_at)',
                    array($job['id'], $sk['id'], $prop ? $prop['id'] : 0, (int) $r['required_level'], !empty($r['is_critical']) ? 1 : 0, $now, $now));
                return (int) $db->insert_id();
            case 'kpi_values':
                $this->CI->load->library('ha_kpi');
                return $this->CI->ha_kpi->record(array('kpi' => $r['kpi_code'], 'property_id' => $prop['id'], 'period_start' => $r['period_start'],
                    'period_end' => isset($r['period_end']) ? $r['period_end'] : null, 'actual' => $r['actual'], 'target' => isset($r['target']) ? $r['target'] : null,
                    'source' => 'csv', 'source_ref' => 'import'), $this->CI->ha_auth->id());
        }
        return null;
    }

    public function rollback($run_id) {
        $run = $this->CI->db->get_where('ha_import_run', array('id' => (int) $run_id))->row_array();
        if (!$run || $run['status'] !== 'committed' || !$this->CI->ha_auth->can_organization($run['organization_id'])) {
            throw new InvalidArgumentException('Only a committed import in your organisation can be rolled back.');
        }
        $ids = array_filter(array_map('intval', json_decode((string) $run['created_ids_json'], true) ?: array()));
        $tables = array('departments' => 'ha_department', 'job_roles' => 'ha_job_role', 'competencies' => 'ha_skill', 'role_competencies' => 'ha_role_competency', 'kpi_values' => 'ha_kpi_value');
        if ($ids) {
            if ($run['import_type'] === 'users') {
                $this->CI->db->where_in('id', $ids)->update('users', array('status' => 0));
                $this->CI->db->where_in('user_id', $ids)->update('ha_profile', array('status' => 'inactive'));
            } else {
                $this->CI->db->where_in('id', $ids)->delete($tables[$run['import_type']]);
            }
        }
        $this->CI->db->where('id', (int) $run_id)->update('ha_import_run', array('status' => 'rolled_back'));
        $this->CI->ha_audit->log('update', 'import_run', (int) $run_id, array('description' => 'Import rolled back (' . count($ids) . ' rows)'));
        return count($ids);
    }
}
