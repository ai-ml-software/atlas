<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Account-bound mobile sessions and expiring, single-use login challenges. */
class Migration_Mobile_session_security extends Ha_migration {
    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mobile_session (
            key_id INT UNSIGNED NOT NULL PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
            credential_stamp VARCHAR(128) NOT NULL, created_at DATETIME NOT NULL,
            KEY ix_mobile_session_user (user_id)
        )" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_mobile_login_challenge (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, token_hash VARCHAR(128) NOT NULL,
            user_id INT UNSIGNED NOT NULL, credential_stamp VARCHAR(128) NOT NULL,
            stage VARCHAR(20) NOT NULL, device_verified TINYINT NOT NULL DEFAULT 0,
            code_hash VARCHAR(128) NULL, attempts TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
            UNIQUE KEY ux_mobile_challenge_token (token_hash), KEY ix_mobile_challenge_expiry (expires_at)
        )" . $this->engine);
        // Earlier mobile sessions had no password/MFA binding. Require a fresh sign-in.
        $this->db->where('revoked_at IS NULL', null, false)->like('name', 'Mobile app session', 'after')
            ->update('ha_api_key', array('revoked_at' => date('Y-m-d H:i:s')));
    }
    public function down() {
        $this->drop(array('ha_mobile_login_challenge', 'ha_mobile_session'));
    }
}
