<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Key-based authentication.
 *
 *  - ha_api_key       personal API keys for /api/v1. Only a SHA-256 HMAC of
 *                     the secret is stored; the plaintext is shown once at
 *                     creation. The prefix is kept so a leaked key found in a
 *                     log can be identified and revoked without the secret.
 *  - ha_user_2fa      TOTP second factor (RFC 6238). The shared secret is
 *                     encrypted at rest; recovery codes are stored hashed.
 *                     last_step blocks replay of a code inside its window.
 *  - ha_auth_attempt  throttling for second-factor and API-key failures, so a
 *                     six-digit code cannot be brute forced.
 */
class Migration_Add_key_auth extends Ha_migration {

    public function up() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_api_key (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id       INT UNSIGNED NOT NULL,
                name          VARCHAR(120) NOT NULL,
                prefix        CHAR(12) NOT NULL,
                secret_hash   CHAR(64) NOT NULL,
                scopes        VARCHAR(500) NOT NULL,
                allowed_ips   VARCHAR(500) NULL,
                expires_at    DATETIME NULL,
                last_used_at  DATETIME NULL,
                last_used_ip  VARCHAR(45) NULL,
                use_count     INT UNSIGNED NOT NULL DEFAULT 0,
                revoked_at    DATETIME NULL,
                revoked_by    INT UNSIGNED NULL,
                created_at    DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ha_api_key_prefix (prefix),
                KEY ix_ha_api_key_user (user_id, revoked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_user_2fa (
                user_id         INT UNSIGNED NOT NULL,
                secret_cipher   TEXT NOT NULL,
                confirmed_at    DATETIME NULL,
                last_step       BIGINT UNSIGNED NULL,
                recovery_hashes TEXT NULL,
                created_at      DATETIME NOT NULL,
                updated_at      DATETIME NOT NULL,
                PRIMARY KEY (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_auth_attempt (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bucket     VARCHAR(120) NOT NULL,
                ok         TINYINT(1) NOT NULL DEFAULT 0,
                ip         VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ix_ha_auth_attempt_bucket (bucket, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down() {
        $this->drop(array('ha_auth_attempt', 'ha_user_2fa', 'ha_api_key'));
    }
}
