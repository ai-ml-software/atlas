<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Additive: approval binding/review metadata and per-grant connection health.
 * down() removes only what up() added; no existing rows are rewritten.
 */
class Migration_Publisher_api_gaps extends Ha_migration {
    public function up() {
        if ($this->db->table_exists('ha_publisher_approval')) {
            $this->add_columns('ha_publisher_approval', array(
                'object_version' => 'INT UNSIGNED NULL AFTER object_id',
                'reviewed_at'    => 'DATETIME NULL AFTER reviewed_by',
                'consumed_at'    => 'DATETIME NULL AFTER reviewed_at',
            ));
        }
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mcp_connection (grant_id VARCHAR(190) NOT NULL PRIMARY KEY, client_id VARCHAR(190) NOT NULL, user_id INT UNSIGNED NOT NULL, last_action VARCHAR(60) NULL, last_seen_at DATETIME NOT NULL, requests INT UNSIGNED NOT NULL DEFAULT 0, KEY mcp_connection_user(user_id), KEY mcp_connection_seen(last_seen_at))" . $this->engine);
    }
    public function down() {
        $this->drop(array('ha_mcp_connection'));
        $this->drop_columns('ha_publisher_approval', array('object_version', 'reviewed_at', 'consumed_at'));
    }
}
