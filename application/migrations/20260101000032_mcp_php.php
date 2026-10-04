<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Additive: native PHP MCP server + OAuth 2.1 authorization server.
 * Every secret (authorization code, access token, refresh token) is stored
 * only as a SHA-256 hash. down() drops only the tables up() created.
 */
class Migration_Mcp_php extends Ha_migration {
    public function up() {
        // RFC 7591 dynamically registered public clients (PKCE, no client secret).
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_client (
            client_id VARCHAR(64) NOT NULL PRIMARY KEY, client_name VARCHAR(120) NOT NULL DEFAULT '',
            redirect_uris TEXT NOT NULL, grant_types VARCHAR(120) NOT NULL, scope VARCHAR(255) NULL,
            metadata_json TEXT NULL, created_ip VARCHAR(45) NULL, created_at DATETIME NOT NULL,
            last_used_at DATETIME NULL, revoked_at DATETIME NULL
        )" . $this->engine);
        // Pending /oauth/authorize requests waiting for ALTUS sign-in and consent.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_auth_request (
            id CHAR(48) NOT NULL PRIMARY KEY, client_id VARCHAR(64) NOT NULL, params_json TEXT NOT NULL,
            created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, KEY ix_mcp_auth_request_exp (expires_at)
        )" . $this->engine);
        // A user's consent to one client: the unit that revocation removes.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_grant (
            id VARCHAR(40) NOT NULL PRIMARY KEY, client_id VARCHAR(64) NOT NULL, user_id INT UNSIGNED NOT NULL,
            scope VARCHAR(255) NOT NULL, resource VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL,
            last_used_at DATETIME NULL, revoked_at DATETIME NULL, revoked_reason VARCHAR(60) NULL,
            KEY ix_mcp_grant_user (user_id), KEY ix_mcp_grant_client (client_id)
        )" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_auth_code (
            code_hash CHAR(64) NOT NULL PRIMARY KEY, grant_id VARCHAR(40) NOT NULL, client_id VARCHAR(64) NOT NULL,
            user_id INT UNSIGNED NOT NULL, redirect_uri VARCHAR(500) NOT NULL, scope VARCHAR(255) NOT NULL,
            resource VARCHAR(500) NOT NULL, code_challenge VARCHAR(128) NOT NULL, expires_at DATETIME NOT NULL,
            used_at DATETIME NULL, created_at DATETIME NOT NULL
        )" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_token (
            token_hash CHAR(64) NOT NULL PRIMARY KEY, kind VARCHAR(10) NOT NULL, grant_id VARCHAR(40) NOT NULL,
            client_id VARCHAR(64) NOT NULL, user_id INT UNSIGNED NOT NULL, scope VARCHAR(255) NOT NULL,
            resource VARCHAR(500) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
            revoked_at DATETIME NULL, created_at DATETIME NOT NULL,
            KEY ix_mcp_token_grant (grant_id), KEY ix_mcp_token_exp (expires_at)
        )" . $this->engine);
        // Streamable HTTP sessions (Mcp-Session-Id), bound to the grant that created them.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_session (
            id CHAR(48) NOT NULL PRIMARY KEY, grant_id VARCHAR(40) NOT NULL, client_id VARCHAR(64) NOT NULL,
            user_id INT UNSIGNED NOT NULL, protocol_version VARCHAR(20) NOT NULL, client_info VARCHAR(255) NULL,
            created_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, KEY ix_mcp_session_grant (grant_id)
        )" . $this->engine);
        // Fixed-window rate limit counters per client/user and per IP.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_rate (
            bucket VARCHAR(190) NOT NULL PRIMARY KEY, window_start INT UNSIGNED NOT NULL, hits INT UNSIGNED NOT NULL
        )" . $this->engine);
    }
    public function down() {
        $this->drop(array('ha_mcp_rate', 'ha_mcp_session', 'ha_mcp_token', 'ha_mcp_auth_code', 'ha_mcp_grant', 'ha_mcp_auth_request', 'ha_mcp_client'));
    }
}
