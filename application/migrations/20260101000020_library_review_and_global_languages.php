<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Additive workflow tables. Existing published content is grandfathered. */
class Migration_Library_review_and_global_languages extends Ha_migration {
    public function up() {
        $e = $this->engine;
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_language_inventory (
            locale VARCHAR(64) NOT NULL, iso6393 CHAR(3) NULL, name VARCHAR(190) NOT NULL,
            modality ENUM('spoken','signed') NOT NULL DEFAULT 'spoken', direction ENUM('ltr','rtl') NOT NULL DEFAULT 'ltr',
            status ENUM('pending','translating','reviewing','ready') NOT NULL DEFAULT 'pending',
            enabled TINYINT NOT NULL DEFAULT 0, source_version VARCHAR(64) NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY(locale), KEY ix_language_status(status,enabled)
        ) $e");
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_library_revision (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT, course_code VARCHAR(190) NOT NULL, signature CHAR(64) NOT NULL,
            candidate_json LONGTEXT NOT NULL, status ENUM('draft','released') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL, released_at DATETIME NULL, PRIMARY KEY(id),
            UNIQUE KEY ux_library_revision(course_code,signature)
        ) $e");
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_library_identity (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT, entity VARCHAR(40) NOT NULL, source_key VARCHAR(190) NOT NULL,
            course_code VARCHAR(190) NOT NULL, entity_id INT UNSIGNED NOT NULL,
            PRIMARY KEY(id), UNIQUE KEY ux_library_identity(entity,source_key), KEY ix_identity_course(course_code)
        ) $e");
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_translation_unit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT, unit_key CHAR(64) NOT NULL, scope VARCHAR(190) NOT NULL,
            locator VARCHAR(500) NOT NULL, source_text LONGTEXT NOT NULL, source_hash CHAR(64) NOT NULL,
            target_json LONGTEXT NULL, active TINYINT NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL,
            PRIMARY KEY(id), UNIQUE KEY ux_translation_unit(unit_key), KEY ix_translation_scope(scope,active)
        ) $e");
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_translation_value (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT, unit_id INT UNSIGNED NOT NULL, locale VARCHAR(64) NOT NULL,
            value LONGTEXT NULL, signed_media VARCHAR(500) NULL, source_hash CHAR(64) NOT NULL,
            status ENUM('translating','reviewing','ready') NOT NULL DEFAULT 'reviewing',
            source ENUM('human','ai','import') NOT NULL DEFAULT 'import', reviewer VARCHAR(190) NULL,
            updated_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY ux_translation_value(unit_id,locale),
            KEY ix_translation_locale(locale,status),
            CONSTRAINT fk_translation_unit FOREIGN KEY(unit_id) REFERENCES ha_translation_unit(id) ON DELETE CASCADE
        ) $e");
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_course_locale_release (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT, course_id INT UNSIGNED NOT NULL, locale VARCHAR(64) NOT NULL,
            signature CHAR(64) NULL, status ENUM('baseline','released') NOT NULL DEFAULT 'baseline',
            released_at DATETIME NULL, PRIMARY KEY(id), UNIQUE KEY ux_course_locale_release(course_id,locale)
        ) $e");
        $this->db->query("INSERT IGNORE INTO ha_course_locale_release(course_id,locale,status)
            SELECT c.id,t.locale,'baseline' FROM ha_course c JOIN ha_course_translation t ON t.course_id=c.id
            WHERE c.code LIKE 'dy-%' AND c.status='published'");
        $this->add_columns('ha_lesson_video_source', array(
            'locale' => "VARCHAR(64) NOT NULL DEFAULT 'en'", 'sort_order' => 'INT NOT NULL DEFAULT 0',
            'relevance_reason' => 'TEXT NULL', 'editorial_score' => 'TINYINT UNSIGNED NULL',
            'reviewer' => 'VARCHAR(190) NULL', 'embeddable' => 'TINYINT NULL',
            'region_restrictions' => 'TEXT NULL', 'captions_authorized' => 'TINYINT NOT NULL DEFAULT 0',
            'captions_path' => 'VARCHAR(500) NULL', 'verification_json' => 'LONGTEXT NULL'
        ));
        $indexes = $this->db->query('SHOW INDEX FROM ha_lesson_video_source')->result_array();
        $names = array_column($indexes, 'Key_name');
        if (in_array('uq_ha_lesson_video_lesson', $names, true)) {
            $this->db->query('ALTER TABLE ha_lesson_video_source DROP INDEX uq_ha_lesson_video_lesson');
        }
        if (!in_array('ux_lesson_video_locale', $names, true)) {
            $this->db->query('ALTER TABLE ha_lesson_video_source ADD UNIQUE KEY ux_lesson_video_locale(lesson_id,provider,video_id,locale)');
        }
        $this->db->query("ALTER TABLE ha_lesson MODIFY video_source ENUM('upload','youtube','vimeo','dailymotion','url') NULL");
        // Seed timestamps were not evidence of an availability check. Preserve the
        // player URL, but require actual evidence before a new release.
        $this->db->query("UPDATE ha_lesson_video_source v JOIN ha_lesson l ON l.id=v.lesson_id
            JOIN ha_course c ON c.id=l.course_id SET v.status='unchecked',v.last_checked_at=NULL
            WHERE c.code LIKE 'dy-%' AND v.verification_json IS NULL");
        foreach ($this->db->query("SELECT table_name AS t,is_nullable AS n,column_default AS d FROM information_schema.columns
            WHERE table_schema=DATABASE() AND table_name LIKE 'ha\\_%' AND column_name='locale' AND data_type='varchar' AND character_maximum_length<64")->result_array() as $col) {
            $default = $col['d'] !== null ? ' DEFAULT ' . $this->db->escape($col['d']) : '';
            $this->db->query('ALTER TABLE `' . $col['t'] . '` MODIFY locale VARCHAR(64) ' . ($col['n'] === 'YES' ? 'NULL' : 'NOT NULL') . $default);
        }
    }
    public function down() {
        // A workflow rollback must not erase reviewed translations, release
        // history, or additional video sources. Restore code from the package
        // backup; these additive tables remain compatible with the older code.
    }
}
