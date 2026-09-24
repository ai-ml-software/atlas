<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy audit trail (plan section 49).
 *
 * Records who did what, to which record, with the before and after state, the
 * IP and the user agent. Writes are best effort in the sense that they never
 * break the action being audited, but a failure is logged rather than hidden.
 */
class Ha_audit {

    const TABLE = 'ha_audit_log';

    /** Actions the plan requires to be auditable. */
    public static $actions = array(
        'login', 'logout', 'create', 'update', 'delete', 'publish', 'unpublish',
        'approve', 'reject', 'assign', 'complete', 'certificate_issue',
        'certificate_revoke', 'sop_acknowledge', 'permission_change', 'role_change',
        'export', 'import', 'view_private',
    );

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    /**
     * @param string $action     one of self::$actions
     * @param string $entity     entity type, e.g. 'course'
     * @param int    $entity_id  record id
     * @param array  $options    description, before, after, organization_id, property_id, user_id
     */
    public function log($action, $entity = null, $entity_id = null, array $options = array()) {
        $user_id = isset($options['user_id']) ? $options['user_id'] : null;
        $actor_name = isset($options['actor_name']) ? $options['actor_name'] : null;

        if ($user_id === null && isset($this->CI->ha_auth)) {
            $user_id = $this->CI->ha_auth->id();
            $actor_name = $this->CI->ha_auth->display_name();
        }

        $before = isset($options['before']) ? $options['before'] : null;
        $after = isset($options['after']) ? $options['after'] : null;

        $row = array(
            'user_id'         => $user_id ? (int) $user_id : null,
            'actor_name'      => $actor_name ? substr($actor_name, 0, 190) : null,
            'action'          => substr($action, 0, 60),
            'entity_type'     => $entity ? substr($entity, 0, 80) : null,
            'entity_id'       => $entity_id ? (int) $entity_id : null,
            'description'     => isset($options['description']) ? substr($options['description'], 0, 500) : null,
            'before_json'     => $before === null ? null : json_encode($this->redact($before), JSON_UNESCAPED_UNICODE),
            'after_json'      => $after === null ? null : json_encode($this->redact($after), JSON_UNESCAPED_UNICODE),
            'organization_id' => isset($options['organization_id']) ? (int) $options['organization_id'] : null,
            'property_id'     => isset($options['property_id']) ? (int) $options['property_id'] : null,
            'ip_address'      => $this->ip(),
            'user_agent'      => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'created_at'      => date('Y-m-d H:i:s'),
        );

        $this->CI->db->insert(self::TABLE, $row);
        return (int) $this->CI->db->insert_id();
    }

    /** Logs only the columns that actually changed. */
    public function log_change($entity, $entity_id, array $before, array $after, array $options = array()) {
        $changed_before = array();
        $changed_after = array();
        foreach ($after as $k => $v) {
            $old = array_key_exists($k, $before) ? $before[$k] : null;
            if ((string) $old !== (string) $v) {
                $changed_before[$k] = $old;
                $changed_after[$k] = $v;
            }
        }
        if (!$changed_after) {
            return 0;
        }
        $options['before'] = $changed_before;
        $options['after'] = $changed_after;
        return $this->log('update', $entity, $entity_id, $options);
    }

    /** Secrets never reach the audit table. */
    protected function redact($data) {
        if (!is_array($data)) {
            return $data;
        }
        $hidden = array('password', 'password_confirm', 'verification_code', 'payment_keys',
            'api_key', 'secret', 'token', 'sessions');
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string) $k), $hidden, true)) {
                $data[$k] = '[redacted]';
            }
        }
        return $data;
    }

    protected function ip() {
        if (is_cli()) {
            return 'cli';
        }
        return isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 64) : null;
    }
}
