<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Additive: mobile app remote-config document and app keys.
 * down() drops only the two tables up() created.
 */
class Migration_Mobile_app_settings extends Ha_migration {
    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mobile_config (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY, settings_json MEDIUMTEXT NOT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1, updated_by INT UNSIGNED NULL, updated_at DATETIME NOT NULL
        )" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mobile_app_key (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL,
            prefix CHAR(12) NOT NULL, secret_hash VARCHAR(128) NOT NULL, platform VARCHAR(10) NOT NULL DEFAULT 'all',
            created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL, last_used_at DATETIME NULL,
            revoked_at DATETIME NULL, revoked_by INT UNSIGNED NULL,
            UNIQUE KEY ux_mobile_app_key_prefix (prefix)
        )" . $this->engine);
    }
    public function down() {
        $this->drop(array('ha_mobile_app_key', 'ha_mobile_config'));
    }
}
