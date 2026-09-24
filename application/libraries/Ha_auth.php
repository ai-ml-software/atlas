<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy authorization.
 *
 * Every permission decision in the academy goes through this library. It reads
 * the grants stored in ha_user_role / ha_role_permission rather than trusting
 * anything supplied by the request, and it derives the tenant scope (which
 * organizations, properties and departments the signed in user may touch) from
 * those same grants. Hiding a button is never the control: controllers call
 * require() and queries call scope_*() before they read or write.
 *
 * Plan sections 2, 38, 45.
 */
class Ha_auth {

    /** @var CI_Controller */
    protected $CI;

    protected $loaded = false;
    protected $user_id = null;
    protected $user = null;
    protected $profile = null;
    protected $roles = array();        // role code => grant rows
    protected $permissions = array();  // permission code => true
    protected $scope = array(
        'system'        => false,
        'organizations' => array(),
        'properties'    => array(),
        'departments'   => array(),
    );

    /** Set by assume() so CLI runs and tests can act as a given user. */
    protected $assumed_user_id = null;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        if (!is_cli()) {
            $this->CI->load->library('session');
        }
    }

    /**
     * Acts as the given user for the rest of the request. Only available on
     * the command line: HTTP identity always comes from the session, never
     * from anything the caller can supply.
     */
    public function assume($user_id) {
        if (!is_cli()) {
            show_error('Ha_auth::assume() is only available on the command line.', 500);
        }
        $this->assumed_user_id = $user_id === null ? null : (int) $user_id;
        return $this->refresh();
    }

    /**
     * HTTP identity for /api/v1: the owner of an API key that
     * Ha_api_keys::authenticate() has just verified. It takes that method's
     * result, not a user id, so the identity still comes from a checked
     * credential and never from a value the caller supplied.
     */
    public function from_api_key(array $auth) {
        if (empty($auth['ok']) || empty($auth['key']['id']) || empty($auth['user']['id'])
            || (int) $auth['key']['user_id'] !== (int) $auth['user']['id']) {
            show_error('Ha_auth::from_api_key() needs a verified API key.', 500);
        }
        $this->assumed_user_id = (int) $auth['user']['id'];
        return $this->refresh();
    }

    // ---------------------------------------------------------------- identity

    /** Loads the signed in user's roles, permissions and tenant scope once per request. */
    public function load() {
        if ($this->loaded) {
            return $this;
        }
        $this->loaded = true;

        $session_user = $this->assumed_user_id !== null
            ? $this->assumed_user_id
            : (isset($this->CI->session) ? $this->CI->session->userdata('user_id') : null);
        if (!$session_user) {
            return $this;
        }
        $user = $this->CI->db->get_where('users', array('id' => (int) $session_user))->row_array();
        if (!$user || (int) $user['status'] !== 1) {
            return $this;
        }

        $this->user_id = (int) $user['id'];
        $this->user = $user;
        $this->profile = $this->CI->db->get_where('ha_profile', array('user_id' => $this->user_id))->row_array();

        if ($this->profile && $this->profile['status'] !== 'active') {
            // A suspended profile keeps the login but loses every academy grant.
            $this->user_id = null;
            $this->user = null;
            return $this;
        }

        $grants = $this->CI->db
            ->select('ur.organization_id, ur.property_id, ur.department_id, r.code, r.scope')
            ->from('ha_user_role ur')
            ->join('ha_role r', 'r.id = ur.role_id')
            ->where('ur.user_id', $this->user_id)
            ->get()->result_array();

        // A grant row records the whole tenant path for context (organization,
        // property, department), but only the role's own scope level grants
        // access. A property manager's row names their organization so reports
        // can be labelled; it must not hand them the rest of that organization.
        foreach ($grants as $g) {
            $this->roles[$g['code']][] = $g;

            switch ($g['scope']) {
                case 'system':
                    $this->scope['system'] = true;
                    break;
                case 'organization':
                    if (!empty($g['organization_id'])) {
                        $this->scope['organizations'][] = (int) $g['organization_id'];
                    }
                    break;
                case 'property':
                    if (!empty($g['property_id'])) {
                        $this->scope['properties'][] = (int) $g['property_id'];
                    }
                    break;
                case 'department':
                    if (!empty($g['department_id'])) {
                        $this->scope['departments'][] = (int) $g['department_id'];
                    }
                    break;
                case 'self':
                default:
                    // Self scoped roles reach their own records only.
                    break;
            }
        }
        $this->scope['organizations'] = array_values(array_unique($this->scope['organizations']));
        $this->scope['properties'] = array_values(array_unique($this->scope['properties']));
        $this->scope['departments'] = array_values(array_unique($this->scope['departments']));

        if ($this->roles) {
            $rows = $this->CI->db
                ->select('p.code')
                ->distinct()
                ->from('ha_role_permission rp')
                ->join('ha_role r', 'r.id = rp.role_id')
                ->join('ha_permission p', 'p.id = rp.permission_id')
                ->where_in('r.code', array_keys($this->roles))
                ->get()->result_array();
            foreach ($rows as $r) {
                $this->permissions[$r['code']] = true;
            }
        }

        return $this;
    }

    /** Forces a reload, for use straight after a role change. */
    public function refresh() {
        $this->loaded = false;
        $this->user_id = null;
        $this->user = null;
        $this->profile = null;
        $this->roles = array();
        $this->permissions = array();
        $this->scope = array('system' => false, 'organizations' => array(), 'properties' => array(), 'departments' => array());
        return $this->load();
    }

    public function check() {
        $this->load();
        return $this->user_id !== null;
    }

    public function id() {
        $this->load();
        return $this->user_id;
    }

    public function user() {
        $this->load();
        return $this->user;
    }

    public function profile() {
        $this->load();
        return $this->profile;
    }

    public function display_name() {
        $this->load();
        if (!$this->user) {
            return '';
        }
        return trim($this->user['first_name'] . ' ' . $this->user['last_name']);
    }

    public function locale() {
        $this->load();
        if ($this->profile && !empty($this->profile['locale'])) {
            return $this->profile['locale'];
        }
        return 'en';
    }

    public function role_codes() {
        $this->load();
        return array_keys($this->roles);
    }

    public function is($role_code) {
        $this->load();
        return isset($this->roles[$role_code]);
    }

    public function is_super_admin() {
        return $this->is('super_admin');
    }

    /** True when the user's grants are not limited to a tenant subtree. */
    public function is_system_scoped() {
        $this->load();
        return (bool) $this->scope['system'];
    }

    // ------------------------------------------------------------- permissions

    /**
     * @param string|array $permission one code, or a list where any match passes.
     */
    public function has($permission) {
        $this->load();
        if (!$this->user_id) {
            return false;
        }
        if ($this->is_super_admin()) {
            return true;
        }
        foreach ((array) $permission as $code) {
            if (isset($this->permissions[$code])) {
                return true;
            }
        }
        return false;
    }

    public function has_any(array $permissions) {
        return $this->has($permissions);
    }

    public function has_all(array $permissions) {
        foreach ($permissions as $code) {
            if (!$this->has($code)) {
                return false;
            }
        }
        return true;
    }

    /** True when the user holds any permission at all in a module. */
    public function has_module($module) {
        $this->load();
        if ($this->is_super_admin()) {
            return true;
        }
        $prefix = $module . '.';
        foreach (array_keys($this->permissions) as $code) {
            if (strpos($code, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    public function permissions() {
        $this->load();
        return array_keys($this->permissions);
    }

    /**
     * Stops the request unless the permission is held. HTML requests are sent
     * to the academy dashboard with a message; JSON requests get a 403 body.
     */
    public function require_permission($permission) {
        if ($this->has($permission)) {
            return true;
        }
        $this->deny(is_array($permission) ? implode(' | ', $permission) : $permission);
        return false;
    }

    public function require_login() {
        if ($this->check()) {
            return true;
        }
        if ($this->wants_json()) {
            $this->json_error(401, 'Authentication required.');
        }
        redirect(site_url('login'), 'refresh');
        return false;
    }

    protected function deny($permission) {
        if ($this->wants_json()) {
            $this->json_error(403, 'You do not have permission to perform this action.', $permission);
        }
        $this->CI->session->set_flashdata('error_message',
            'You are not authorized to access that page.');
        redirect(site_url('academy-admin/dashboard'), 'refresh');
    }

    protected function wants_json() {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        return strpos($uri, '/api/') !== false;
    }

    protected function json_error($status, $message, $permission = null) {
        $payload = array('status' => 'error', 'message' => $message);
        if ($permission) {
            $payload['required_permission'] = $permission;
        }
        $this->CI->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload))
            ->_display();
        exit;
    }

    // ------------------------------------------------------------ tenant scope

    public function scope() {
        $this->load();
        return $this->scope;
    }

    public function organization_ids() {
        $this->load();
        return $this->scope['organizations'];
    }

    public function property_ids() {
        $this->load();
        return $this->scope['properties'];
    }

    public function department_ids() {
        $this->load();
        return $this->scope['departments'];
    }

    /** The organization a new record should default to. */
    public function default_organization_id() {
        $this->load();
        if ($this->scope['organizations']) {
            return $this->scope['organizations'][0];
        }
        if ($this->profile && !empty($this->profile['organization_id'])) {
            return (int) $this->profile['organization_id'];
        }
        return null;
    }

    /**
     * Constrains a query builder to the rows this user may see.
     *
     * $columns maps the scope level to the column on the table being queried,
     * e.g. array('organization_id' => 'c.organization_id', 'property_id' => 'c.property_id').
     * A system scoped user is unconstrained. A user with no scope at all sees
     * nothing, which is deliberately safer than seeing everything.
     */
    public function scope_query($db, array $columns, $allow_null = false) {
        $this->load();
        if ($this->is_system_scoped()) {
            return $db;
        }

        $clauses = array();
        $binds = array();

        if (isset($columns['organization_id']) && $this->scope['organizations']) {
            $clauses[] = $columns['organization_id'] . ' IN (' . $this->placeholders($this->scope['organizations']) . ')';
            $binds = array_merge($binds, $this->scope['organizations']);
        }
        if (isset($columns['property_id']) && $this->scope['properties']) {
            $clauses[] = $columns['property_id'] . ' IN (' . $this->placeholders($this->scope['properties']) . ')';
            $binds = array_merge($binds, $this->scope['properties']);
        }
        if (isset($columns['department_id']) && $this->scope['departments']) {
            $clauses[] = $columns['department_id'] . ' IN (' . $this->placeholders($this->scope['departments']) . ')';
            $binds = array_merge($binds, $this->scope['departments']);
        }
        if (isset($columns['user_id'])) {
            $clauses[] = $columns['user_id'] . ' = ?';
            $binds[] = (int) $this->user_id;
        }

        if (!$clauses) {
            // No usable scope: return nothing rather than everything.
            return $db->where('1 = 0', null, false);
        }

        $sql = '(' . implode(' OR ', $clauses) . ')';
        if ($allow_null && isset($columns['organization_id'])) {
            $sql = '(' . $sql . ' OR ' . $columns['organization_id'] . ' IS NULL)';
        }
        return $db->where($this->CI->db->compile_binds($sql, $binds), null, false);
    }

    protected function placeholders(array $values) {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** Can this user act on the given organization? */
    public function can_organization($organization_id) {
        $this->load();
        if ($this->is_system_scoped()) {
            return true;
        }
        return in_array((int) $organization_id, $this->scope['organizations'], true);
    }

    public function can_property($property_id) {
        $this->load();
        if ($this->is_system_scoped()) {
            return true;
        }
        if (in_array((int) $property_id, $this->scope['properties'], true)) {
            return true;
        }
        if (!$this->scope['organizations']) {
            return false;
        }
        // Only an organization scoped grant reaches properties it does not name.
        $row = $this->CI->db->select('organization_id')
            ->get_where('ha_property', array('id' => (int) $property_id))->row_array();
        return $row && in_array((int) $row['organization_id'], $this->scope['organizations'], true);
    }

    public function can_department($department_id) {
        $this->load();
        if ($this->is_system_scoped()) {
            return true;
        }
        if (in_array((int) $department_id, $this->scope['departments'], true)) {
            return true;
        }
        $row = $this->CI->db->select('organization_id, property_id')
            ->get_where('ha_department', array('id' => (int) $department_id))->row_array();
        if (!$row) {
            return false;
        }
        if ($row['property_id'] && in_array((int) $row['property_id'], $this->scope['properties'], true)) {
            return true;
        }
        return in_array((int) $row['organization_id'], $this->scope['organizations'], true);
    }

    /**
     * Can this user see another person's learning record? Own record always,
     * then department, property and organization scope, then system scope.
     */
    public function can_user($target_user_id) {
        $this->load();
        $target_user_id = (int) $target_user_id;
        if ($target_user_id === (int) $this->user_id) {
            return true;
        }
        if ($this->is_system_scoped()) {
            return true;
        }
        $p = $this->CI->db->select('organization_id, property_id, department_id, manager_user_id')
            ->get_where('ha_profile', array('user_id' => $target_user_id))->row_array();
        if (!$p) {
            return false;
        }
        if ((int) $p['manager_user_id'] === (int) $this->user_id) {
            return true;
        }
        if ($p['department_id'] && in_array((int) $p['department_id'], $this->scope['departments'], true)) {
            return true;
        }
        if ($p['property_id'] && in_array((int) $p['property_id'], $this->scope['properties'], true)) {
            return true;
        }
        if ($p['organization_id'] && in_array((int) $p['organization_id'], $this->scope['organizations'], true)) {
            return true;
        }
        return false;
    }

    /** Every user id this user is allowed to report on, including themselves. */
    public function visible_user_ids() {
        $this->load();
        if (!$this->user_id) {
            return array();
        }
        if ($this->is_system_scoped()) {
            $rows = $this->CI->db->select('user_id')->get('ha_profile')->result_array();
            return array_map('intval', array_column($rows, 'user_id'));
        }

        $db = $this->CI->db->select('user_id')->from('ha_profile');
        $parts = array();
        $binds = array();
        if ($this->scope['organizations']) {
            $parts[] = 'organization_id IN (' . $this->placeholders($this->scope['organizations']) . ')';
            $binds = array_merge($binds, $this->scope['organizations']);
        }
        if ($this->scope['properties']) {
            $parts[] = 'property_id IN (' . $this->placeholders($this->scope['properties']) . ')';
            $binds = array_merge($binds, $this->scope['properties']);
        }
        if ($this->scope['departments']) {
            $parts[] = 'department_id IN (' . $this->placeholders($this->scope['departments']) . ')';
            $binds = array_merge($binds, $this->scope['departments']);
        }
        $parts[] = 'manager_user_id = ?';
        $binds[] = (int) $this->user_id;
        $parts[] = 'user_id = ?';
        $binds[] = (int) $this->user_id;

        $sql = '(' . implode(' OR ', $parts) . ')';
        $rows = $db->where($this->CI->db->compile_binds($sql, $binds), null, false)->get()->result_array();
        return array_map('intval', array_column($rows, 'user_id'));
    }
}
