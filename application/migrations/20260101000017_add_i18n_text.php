<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ha_i18n_text: translations for records that keep their text in en/ar column
 * pairs (topics, learning paths and steps, FAQs, menu items, SOP categories,
 * departments, domains, skills). One row per entity + field + language, so any
 * language can be added without new columns. Ha_catalog::overlay() applies them.
 */
class Migration_Add_i18n_text extends Ha_migration {

    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_i18n_text (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity VARCHAR(40) NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            field VARCHAR(40) NOT NULL,
            locale VARCHAR(12) NOT NULL,
            value LONGTEXT NULL,
            source ENUM('human','ai','import') NOT NULL DEFAULT 'human',
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ux_ha_i18n_text (entity, entity_id, field, locale),
            KEY ix_ha_i18n_text_locale (locale, entity)
        ) " . $this->engine);
    }

    public function down() {
        $this->db->query('DROP TABLE IF EXISTS ha_i18n_text');
    }
}
