<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ha_lms_link: which academy record each mirrored Academy LMS row came from.
 *
 * Ha_bridge writes the legacy course / section / lesson / question rows from the
 * ha_* tables in English. The link lets the lesson player show the same row in
 * the learner's language (Ha_lms_i18n), and lets the bridge update a lesson in
 * place instead of deleting and re-inserting it, so the ids stored in a
 * learner's watch history keep pointing at the same lessons after a re-sync.
 *
 *   legacy_table  course | section | lesson | question
 *   ha_table      ha_course | ha_course_section | ha_lesson | ha_assessment | ha_question
 */
class Migration_Add_lms_link extends Ha_migration {

    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_lms_link (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            legacy_table VARCHAR(20) NOT NULL,
            legacy_id INT UNSIGNED NOT NULL,
            ha_table VARCHAR(40) NOT NULL,
            ha_id INT UNSIGNED NOT NULL,
            legacy_course_id INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ux_ha_lms_link_legacy (legacy_table, legacy_id),
            KEY ix_ha_lms_link_ha (ha_table, ha_id),
            KEY ix_ha_lms_link_course (legacy_course_id)
        ) " . $this->engine);
    }

    public function down() {
        $this->db->query('DROP TABLE IF EXISTS ha_lms_link');
    }
}
