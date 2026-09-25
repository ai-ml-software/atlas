<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Page builder, SEO/AEO/GEO optimisation data, lesson drip and media, AI prompt log.
 *
 *   ha_page_section   ordered, typed, bilingual blocks of a public page (drag to reorder)
 *   ha_page_revision  a snapshot on every save, so any earlier version can be restored
 *   ha_page           focus keywords, schema type, GEO fields, last optimisation score
 *   ha_lesson         drip release (days after enrolment or a date) and uploaded media
 *   ha_ai_prompt_log  every AI-assisted edit: model chosen, prompt, enhanced prompt, output
 */
class Migration_Add_cms_builder extends Ha_migration {

    public function up() {
        $e = $this->engine;
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_page_section (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_id INT UNSIGNED NOT NULL,
            section_type ENUM('hero','rich_text','image_text','image','gallery','cards','stats','faq','cta','video','quote','steps','html') NOT NULL DEFAULT 'rich_text',
            sort_order INT NOT NULL DEFAULT 0,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            settings_json TEXT NULL,
            content_en LONGTEXT NULL,
            content_ar LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_psec_page (page_id, sort_order),
            CONSTRAINT fk_ha_psec_page FOREIGN KEY (page_id) REFERENCES ha_page (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_page_revision (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_id INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            note VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_prev_page (page_id, id),
            CONSTRAINT fk_ha_prev_page FOREIGN KEY (page_id) REFERENCES ha_page (id) ON DELETE CASCADE
        )" . $e);

        $this->add_columns('ha_page', array(
            'focus_keyword_en' => "VARCHAR(120) NULL",
            'focus_keyword_ar' => "VARCHAR(120) NULL",
            'schema_type'      => "ENUM('WebPage','AboutPage','ContactPage','FAQPage','Service','LocalBusiness','Course','Article','CollectionPage') NOT NULL DEFAULT 'WebPage'",
            'geo_region'       => "VARCHAR(10) NULL",
            'geo_placename'    => "VARCHAR(120) NULL",
            'geo_lat'          => "DECIMAL(10,7) NULL",
            'geo_lng'          => "DECIMAL(10,7) NULL",
            'seo_score_en'     => "TINYINT UNSIGNED NULL",
            'seo_score_ar'     => "TINYINT UNSIGNED NULL",
            'seo_checked_at'   => "DATETIME NULL",
        ));

        $this->add_columns('ha_lesson', array(
            'drip_days'      => "INT UNSIGNED NULL AFTER sort_order",
            'available_from' => "DATETIME NULL AFTER drip_days",
            'media_type'     => "ENUM('none','video','audio','pdf','presentation','document') NOT NULL DEFAULT 'none' AFTER video_url",
            'media_path'     => "VARCHAR(500) NULL AFTER media_type",
        ));

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_ai_prompt_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            context VARCHAR(60) NOT NULL,
            entity_type VARCHAR(40) NULL,
            entity_id INT UNSIGNED NULL,
            provider VARCHAR(60) NULL,
            model VARCHAR(190) NULL,
            prompt TEXT NOT NULL,
            enhanced_prompt TEXT NULL,
            output LONGTEXT NULL,
            ok TINYINT(1) NOT NULL DEFAULT 1,
            error VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_aipl_user (user_id, created_at)
        )" . $e);
    }

    public function down() {
        $this->drop(array('ha_ai_prompt_log', 'ha_page_revision', 'ha_page_section'));
        $this->drop_columns('ha_lesson', array('drip_days', 'available_from', 'media_type', 'media_path'));
        $this->drop_columns('ha_page', array('focus_keyword_en', 'focus_keyword_ar', 'schema_type', 'geo_region', 'geo_placename', 'geo_lat', 'geo_lng',
            'seo_score_en', 'seo_score_ar', 'seo_checked_at'));
    }
}
