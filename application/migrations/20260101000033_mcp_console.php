<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Additive: MCP admin console.
 *  - ha_mcp_personal_token  admin-issued "personal connection" tokens (SHA-256 hash only, expiry, revocable)
 *  - ha_mcp_setting         consent defaults (default / maximum access level offered)
 *  - ha_mcp_call_log        one structured row per MCP JSON-RPC call (console Activity, counts, sparkline)
 *  - ha_mcp_client.max_level  per-client access-level cap, enforced on every request
 * down() removes exactly what up() added.
 */
class Migration_Mcp_console extends Ha_migration {
    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_personal_token (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, token_hash CHAR(64) NOT NULL, token_hint VARCHAR(20) NOT NULL,
            name VARCHAR(120) NOT NULL, user_id INT UNSIGNED NOT NULL, level VARCHAR(10) NOT NULL, scope VARCHAR(255) NOT NULL,
            created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
            last_used_at DATETIME NULL, revoked_at DATETIME NULL, revoked_by INT UNSIGNED NULL,
            UNIQUE KEY ux_mcp_pt_hash (token_hash), KEY ix_mcp_pt_user (user_id)
        )" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_setting (
            name VARCHAR(60) NOT NULL PRIMARY KEY, value VARCHAR(255) NOT NULL, updated_by INT UNSIGNED NULL, updated_at DATETIME NOT NULL
        )" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_call_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, created_at DATETIME NOT NULL, source VARCHAR(10) NOT NULL,
            user_id INT UNSIGNED NULL, client_id VARCHAR(190) NULL, grant_id VARCHAR(64) NULL, method VARCHAR(60) NOT NULL,
            tool VARCHAR(60) NULL, status VARCHAR(10) NOT NULL, error_code VARCHAR(60) NULL, ms INT UNSIGNED NOT NULL DEFAULT 0,
            request_id VARCHAR(24) NULL, summary VARCHAR(255) NULL, dry_run TINYINT(1) NOT NULL DEFAULT 0,
            KEY ix_mcp_call_time (created_at), KEY ix_mcp_call_tool (tool), KEY ix_mcp_call_client (client_id),
            KEY ix_mcp_call_user (user_id), KEY ix_mcp_call_status (status)
        )" . $this->engine);
        unset($this->db->data_cache['table_names']);   // 032 created tables with raw SQL in the same run
        if ($this->db->query("SHOW TABLES LIKE 'ha_mcp_client'")->num_rows()) $this->add_columns('ha_mcp_client', array('max_level' => 'VARCHAR(10) NULL'));
    }
    public function down() {
        if ($this->db->table_exists('ha_mcp_client')) $this->drop_columns('ha_mcp_client', array('max_level'));
        $this->drop(array('ha_mcp_call_log', 'ha_mcp_setting', 'ha_mcp_personal_token'));
    }
}
