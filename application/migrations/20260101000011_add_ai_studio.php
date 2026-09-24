<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * AI Studio: provider credentials, the live model catalogue, which model runs
 * which task, a usage ledger, and the generation jobs that turn a prompt into
 * reviewed course content.
 *
 *  - ha_ai_provider  one row per configured provider. The registry of what a
 *                    provider is (endpoints, auth style, regions) lives in
 *                    config/ha_ai_providers.php; this table only holds what an
 *                    admin chose: the key (encrypted), region, overrides.
 *  - ha_ai_model     models fetched live from each provider's list endpoint.
 *                    A hard-coded model list is stale the week it ships, so
 *                    this is a cache of what the provider says, not a catalogue.
 *  - ha_ai_route     task -> provider + model. Tasks are named in
 *                    config/ha_ai.php so the generator never picks a model.
 *  - ha_ai_usage     one row per call: tokens, latency, outcome. Cost control
 *                    and debugging both start here.
 *  - ha_ai_job       a unit of generation work. AI output is always a draft:
 *                    nothing reaches a learner until a person approves it.
 */
class Migration_Add_ai_studio extends Ha_migration {

    public function up() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_ai_provider (
                id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug             VARCHAR(60) NOT NULL,
                enabled          TINYINT(1) NOT NULL DEFAULT 0,
                region           VARCHAR(60) NULL,
                base_url         VARCHAR(500) NULL,
                api_key_cipher   TEXT NULL,
                api_key_hint     VARCHAR(20) NULL,
                settings_json    TEXT NULL,
                last_tested_at   DATETIME NULL,
                last_test_ok     TINYINT(1) NULL,
                last_error       VARCHAR(500) NULL,
                models_synced_at DATETIME NULL,
                updated_by       INT UNSIGNED NULL,
                created_at       DATETIME NOT NULL,
                updated_at       DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ha_ai_provider_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_ai_model (
                id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider_slug  VARCHAR(60) NOT NULL,
                model_id       VARCHAR(190) NOT NULL,
                label          VARCHAR(255) NULL,
                owned_by       VARCHAR(190) NULL,
                context_tokens INT UNSIGNED NULL,
                capabilities   VARCHAR(255) NULL,
                is_available   TINYINT(1) NOT NULL DEFAULT 1,
                fetched_at     DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ha_ai_model (provider_slug, model_id),
                KEY ix_ha_ai_model_available (provider_slug, is_available)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_ai_route (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                task          VARCHAR(60) NOT NULL,
                provider_slug VARCHAR(60) NOT NULL,
                model_id      VARCHAR(190) NOT NULL,
                temperature   DECIMAL(3,2) NULL,
                max_tokens    INT UNSIGNED NULL,
                options_json  TEXT NULL,
                updated_by    INT UNSIGNED NULL,
                updated_at    DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ha_ai_route_task (task)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_ai_usage (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                provider_slug VARCHAR(60) NOT NULL,
                model_id      VARCHAR(190) NULL,
                task          VARCHAR(60) NULL,
                job_id        INT UNSIGNED NULL,
                user_id       INT UNSIGNED NULL,
                api_key_id    INT UNSIGNED NULL,
                input_tokens  INT UNSIGNED NOT NULL DEFAULT 0,
                output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
                characters    INT UNSIGNED NOT NULL DEFAULT 0,
                latency_ms    INT UNSIGNED NOT NULL DEFAULT 0,
                http_status   SMALLINT UNSIGNED NULL,
                ok            TINYINT(1) NOT NULL DEFAULT 0,
                error         VARCHAR(500) NULL,
                created_at    DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ix_ha_ai_usage_provider (provider_slug, created_at),
                KEY ix_ha_ai_usage_task (task, created_at),
                KEY ix_ha_ai_usage_user (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_ai_job (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                type          ENUM('course_draft','lesson_script','video_render','avatar_video') NOT NULL,
                status        ENUM('queued','running','draft','approved','rejected','published','failed') NOT NULL DEFAULT 'queued',
                title         VARCHAR(255) NOT NULL,
                course_id     INT UNSIGNED NULL,
                lesson_id     INT UNSIGNED NULL,
                parent_job_id INT UNSIGNED NULL,
                locales       VARCHAR(60) NOT NULL DEFAULT 'en,ar',
                input_json    MEDIUMTEXT NOT NULL,
                output_json   MEDIUMTEXT NULL,
                artifact_path VARCHAR(500) NULL,
                progress      TINYINT UNSIGNED NOT NULL DEFAULT 0,
                progress_note VARCHAR(255) NULL,
                attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
                error         TEXT NULL,
                created_by    INT UNSIGNED NULL,
                reviewed_by   INT UNSIGNED NULL,
                reviewed_at   DATETIME NULL,
                review_note   VARCHAR(500) NULL,
                locked_at     DATETIME NULL,
                started_at    DATETIME NULL,
                finished_at   DATETIME NULL,
                created_at    DATETIME NOT NULL,
                updated_at    DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ix_ha_ai_job_status (status, created_at),
                KEY ix_ha_ai_job_lesson (lesson_id),
                KEY ix_ha_ai_job_course (course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // An academy-produced video is a first-class source next to YouTube.
        $this->db->query("
            ALTER TABLE ha_lesson_video_source
            MODIFY provider ENUM('youtube','vimeo','dailymotion','url','upload','academy') NOT NULL DEFAULT 'youtube'
        ");
    }

    public function down() {
        $this->db->query("DELETE FROM ha_lesson_video_source WHERE provider = 'academy'");
        $this->db->query("
            ALTER TABLE ha_lesson_video_source
            MODIFY provider ENUM('youtube','vimeo','dailymotion','url','upload') NOT NULL DEFAULT 'youtube'
        ");
        $this->drop(array('ha_ai_job', 'ha_ai_usage', 'ha_ai_route', 'ha_ai_model', 'ha_ai_provider'));
    }
}
