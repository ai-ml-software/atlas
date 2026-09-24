<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base repository for Hospitality Academy modules.
 *
 * Gives every module the same list behaviour the plan requires of admin
 * screens (search, filters, sort, pagination, bulk actions, export) and the
 * same write behaviour (validated column whitelist, tenant ownership stamped
 * server side, audit entry on every change). Plan sections 41, 38, 49.
 *
 * Subclasses declare the table, the searchable and filterable columns, and the
 * columns a request is allowed to write. Anything not declared is ignored, so
 * a hidden form field cannot reach the database.
 */
abstract class Ha_repository {

    /** @var CI_Controller */
    protected $CI;

    /** @var CI_DB_query_builder */
    protected $db;

    /** Table name, e.g. 'ha_course'. */
    protected $table;

    /** Entity name used in the audit log, e.g. 'course'. */
    protected $entity;

    /** Columns matched by the free text search box. */
    protected $searchable = array();

    /** Columns that may be filtered on, mapped filter key => column. */
    protected $filterable = array();

    /** Columns a list may be sorted by, mapped sort key => column. */
    protected $sortable = array();

    /** Default ordering applied when the request asks for none. */
    protected $default_sort = 'id';
    protected $default_direction = 'DESC';

    /** Columns that may be written from request input. */
    protected $fillable = array();

    /** Columns holding the tenant path, used to scope reads and stamp writes. */
    protected $tenant_columns = array();

    /** Set true when the table has created_at / updated_at. */
    protected $timestamps = true;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->db = $this->CI->db;
        $this->CI->load->library('ha_auth');
        $this->CI->load->library('ha_audit');
    }

    public function table() {
        return $this->table;
    }

    public function entity() {
        return $this->entity;
    }

    // ------------------------------------------------------------------ reads

    /** Hook for subclasses that need joins or computed columns on a listing. */
    protected function base_query() {
        return $this->db->from($this->table);
    }

    /** Hook applied to every read, so scoping cannot be forgotten per method. */
    protected function apply_scope($db) {
        if (!$this->tenant_columns) {
            return $db;
        }
        $columns = array();
        foreach ($this->tenant_columns as $level => $column) {
            $columns[$level] = $column;
        }
        return $this->CI->ha_auth->scope_query($db, $columns, $this->scope_allows_global());
    }

    /**
     * True when rows with no organization are shared academy content that any
     * authorized user may read, such as the published course catalogue.
     */
    protected function scope_allows_global() {
        return false;
    }

    /**
     * @param array $params search, filters, sort, direction, page, per_page
     * @return array rows, total, page, per_page, pages
     */
    public function paginate(array $params = array()) {
        $per_page = isset($params['per_page']) ? max(1, min(200, (int) $params['per_page'])) : 20;
        $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;

        $count_db = $this->base_query();
        $this->apply_filters($count_db, $params);
        $this->apply_scope($count_db);
        $total = $count_db->count_all_results('', false);

        $this->apply_sort($count_db, $params);
        $rows = $count_db->limit($per_page, ($page - 1) * $per_page)->get()->result_array();

        return array(
            'rows'     => $this->decorate_many($rows),
            'total'    => (int) $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => (int) ceil($total / $per_page),
        );
    }

    /** Every matching row with no page limit, for exports. */
    public function all(array $params = array()) {
        $db = $this->base_query();
        $this->apply_filters($db, $params);
        $this->apply_scope($db);
        $this->apply_sort($db, $params);
        return $this->decorate_many($db->get()->result_array());
    }

    public function count(array $params = array()) {
        $db = $this->base_query();
        $this->apply_filters($db, $params);
        $this->apply_scope($db);
        return (int) $db->count_all_results('', false);
    }

    /** One row, scoped. Returns null when the row exists but is out of scope. */
    public function find($id) {
        $db = $this->base_query()->where($this->table . '.id', (int) $id);
        $this->apply_scope($db);
        $row = $db->get()->row_array();
        return $row ? $this->decorate($row) : null;
    }

    /** One row ignoring scope, for internal jobs. Never call from a controller. */
    public function find_unscoped($id) {
        $row = $this->db->get_where($this->table, array('id' => (int) $id))->row_array();
        return $row ? $this->decorate($row) : null;
    }

    public function find_by(array $where) {
        $db = $this->base_query()->where($where);
        $this->apply_scope($db);
        $row = $db->get()->row_array();
        return $row ? $this->decorate($row) : null;
    }

    protected function apply_filters($db, array $params) {
        if (!empty($params['search']) && $this->searchable) {
            $term = trim($params['search']);
            $first = true;
            foreach ($this->searchable as $column) {
                if ($first) {
                    $db->group_start()->like($column, $term);
                    $first = false;
                } else {
                    $db->or_like($column, $term);
                }
            }
            if (!$first) {
                $db->group_end();
            }
        }

        foreach ($this->filterable as $key => $column) {
            if (!isset($params[$key]) || $params[$key] === '' || $params[$key] === null) {
                continue;
            }
            $value = $params[$key];
            if (is_array($value)) {
                $db->where_in($column, $value);
            } else {
                $db->where($column, $value);
            }
        }

        if (!empty($params['created_from'])) {
            $db->where($this->table . '.created_at >=', $params['created_from'] . ' 00:00:00');
        }
        if (!empty($params['created_to'])) {
            $db->where($this->table . '.created_at <=', $params['created_to'] . ' 23:59:59');
        }
        if (!empty($params['ids']) && is_array($params['ids'])) {
            $db->where_in($this->table . '.id', array_map('intval', $params['ids']));
        }
        return $db;
    }

    protected function apply_sort($db, array $params) {
        $key = isset($params['sort']) ? $params['sort'] : null;
        $column = ($key !== null && isset($this->sortable[$key]))
            ? $this->sortable[$key]
            : (isset($this->sortable[$this->default_sort])
                ? $this->sortable[$this->default_sort]
                : $this->table . '.' . $this->default_sort);

        $direction = strtoupper(isset($params['direction']) ? $params['direction'] : $this->default_direction);
        if (!in_array($direction, array('ASC', 'DESC'), true)) {
            $direction = 'DESC';
        }
        return $db->order_by($column, $direction);
    }

    /** Hook for computed fields on a single row. */
    protected function decorate(array $row) {
        return $row;
    }

    protected function decorate_many(array $rows) {
        foreach ($rows as $i => $row) {
            $rows[$i] = $this->decorate($row);
        }
        return $rows;
    }

    // ----------------------------------------------------------------- writes

    /** Keeps only declared columns, so unexpected input cannot be written. */
    protected function filter_input(array $data) {
        $clean = array();
        foreach ($this->fillable as $column) {
            if (array_key_exists($column, $data)) {
                $clean[$column] = $data[$column] === '' ? null : $data[$column];
            }
        }
        return $clean;
    }

    /** Stamps tenant ownership from the signed in user, never from the request. */
    protected function stamp_tenant(array $data) {
        if (isset($this->tenant_columns['organization_id'])
            && in_array('organization_id', $this->fillable, true)
            && empty($data['organization_id'])) {
            $data['organization_id'] = $this->CI->ha_auth->default_organization_id();
        }
        return $data;
    }

    public function create(array $data, array $audit = array()) {
        $row = $this->stamp_tenant($this->filter_input($data));
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        $this->db->insert($this->table, $row);
        $id = (int) $this->db->insert_id();

        $this->CI->ha_audit->log('create', $this->entity, $id, array_merge(array(
            'after'       => $row,
            'description' => 'Created ' . $this->entity . ' #' . $id,
        ), $audit));

        $this->after_write($id, 'create', null, $row);
        return $id;
    }

    /** @return bool false when the row is out of the caller's scope. */
    public function update($id, array $data, array $audit = array()) {
        $id = (int) $id;
        $before = $this->find($id);
        if (!$before) {
            return false;
        }
        $row = $this->filter_input($data);
        if (!$row) {
            return true;
        }
        if ($this->timestamps) {
            $row['updated_at'] = date('Y-m-d H:i:s');
        }
        $this->db->where('id', $id)->update($this->table, $row);

        $this->CI->ha_audit->log_change($this->entity, $id, $before, $row, array_merge(array(
            'description' => 'Updated ' . $this->entity . ' #' . $id,
        ), $audit));

        $this->after_write($id, 'update', $before, $row);
        return true;
    }

    public function delete($id, array $audit = array()) {
        $id = (int) $id;
        $before = $this->find($id);
        if (!$before) {
            return false;
        }
        if (!$this->can_delete($before)) {
            return false;
        }
        $this->db->where('id', $id)->delete($this->table);

        $this->CI->ha_audit->log('delete', $this->entity, $id, array_merge(array(
            'before'      => $before,
            'description' => 'Deleted ' . $this->entity . ' #' . $id,
        ), $audit));

        $this->after_write($id, 'delete', $before, null);
        return true;
    }

    /** Deletes several rows, skipping any the caller may not touch. */
    public function delete_many(array $ids) {
        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete($id)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /** Applies one column to many rows, respecting scope. */
    public function bulk_update(array $ids, array $data) {
        $changed = 0;
        foreach ($ids as $id) {
            if ($this->update($id, $data)) {
                $changed++;
            }
        }
        return $changed;
    }

    /** Subclasses block deletion of rows that are still in use. */
    protected function can_delete(array $row) {
        return true;
    }

    /** Hook for cascading work such as recalculating counts. */
    protected function after_write($id, $action, $before, $after) {}

    // ----------------------------------------------------------------- export

    /** Rows shaped for CSV export, with the header row first. */
    public function export_rows(array $params = array()) {
        $rows = $this->all($params);
        if (!$rows) {
            return array();
        }
        $columns = $this->export_columns() ?: array_keys($rows[0]);
        $out = array($columns);
        foreach ($rows as $row) {
            $line = array();
            foreach ($columns as $c) {
                $line[] = isset($row[$c]) ? $row[$c] : '';
            }
            $out[] = $line;
        }
        return $out;
    }

    /** Subclasses narrow the export to meaningful columns. */
    protected function export_columns() {
        return array();
    }
}
