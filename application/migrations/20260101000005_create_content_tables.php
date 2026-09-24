<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy - CMS, SEO centre, redirects, topic hub, leads and
 * competitor intelligence. Plan sections 25-33.
 */
class Migration_Create_content_tables extends Ha_migration {

    private $tables = array(
        'ha_competitor_observation', 'ha_competitor_keyword', 'ha_competitor_page',
        'ha_competitor_capability', 'ha_competitor',
        'ha_lead', 'ha_redirect', 'ha_seo_metadata',
        'ha_menu_item', 'ha_menu', 'ha_media', 'ha_testimonial', 'ha_faq',
        'ha_topic_course', 'ha_topic',
        'ha_article_tag', 'ha_tag', 'ha_article_translation', 'ha_article', 'ha_author',
        'ha_page_translation', 'ha_page',
    );

    public function up() {
        $e = $this->engine;

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_page (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            template ENUM('standard','landing','contact','for_hotels','legal','home') NOT NULL DEFAULT 'standard',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('draft','review','scheduled','published','archived') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_page_code (code),
            UNIQUE KEY uq_ha_page_slug_en (slug_en),
            UNIQUE KEY uq_ha_page_slug_ar (slug_ar),
            KEY ix_ha_page_status (status)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_page_translation (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            title VARCHAR(190) NOT NULL,
            subtitle VARCHAR(500) NULL,
            body LONGTEXT NULL,
            hero_image VARCHAR(500) NULL,
            cta_label VARCHAR(120) NULL,
            cta_url VARCHAR(500) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_page_tr (page_id, locale),
            CONSTRAINT fk_ha_page_tr FOREIGN KEY (page_id) REFERENCES ha_page (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_author (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            slug VARCHAR(190) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            title_en VARCHAR(190) NULL,
            title_ar VARCHAR(190) NULL,
            bio_en TEXT NULL,
            bio_ar TEXT NULL,
            avatar VARCHAR(500) NULL,
            linkedin_url VARCHAR(255) NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_author_slug (slug)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_article (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            category_id INT UNSIGNED NULL,
            topic_id INT UNSIGNED NULL,
            author_id INT UNSIGNED NULL,
            cover_image VARCHAR(500) NULL,
            cover_image_alt_en VARCHAR(255) NULL,
            cover_image_alt_ar VARCHAR(255) NULL,
            reading_minutes INT UNSIGNED NOT NULL DEFAULT 0,
            related_course_id INT UNSIGNED NULL,
            related_program_id INT UNSIGNED NULL,
            related_sop_id INT UNSIGNED NULL,
            related_path_id INT UNSIGNED NULL,
            cta_label_en VARCHAR(120) NULL,
            cta_label_ar VARCHAR(120) NULL,
            cta_url VARCHAR(500) NULL,
            status ENUM('draft','review','seo_review','scheduled','published','archived') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            scheduled_for DATETIME NULL,
            reviewed_by INT UNSIGNED NULL,
            approved_by INT UNSIGNED NULL,
            view_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_article_slug_en (slug_en),
            UNIQUE KEY uq_ha_article_slug_ar (slug_ar),
            KEY ix_ha_article_status (status),
            KEY ix_ha_article_published (published_at),
            CONSTRAINT fk_ha_article_cat FOREIGN KEY (category_id) REFERENCES ha_category (id) ON DELETE SET NULL,
            CONSTRAINT fk_ha_article_author FOREIGN KEY (author_id) REFERENCES ha_author (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_article_translation (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            article_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            title VARCHAR(190) NOT NULL,
            excerpt VARCHAR(500) NULL,
            body LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_article_tr (article_id, locale),
            CONSTRAINT fk_ha_article_tr FOREIGN KEY (article_id) REFERENCES ha_article (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_tag (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(190) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_tag_slug (slug)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_article_tag (
            article_id INT UNSIGNED NOT NULL,
            tag_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (article_id, tag_id),
            CONSTRAINT fk_ha_at_article FOREIGN KEY (article_id) REFERENCES ha_article (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_at_tag FOREIGN KEY (tag_id) REFERENCES ha_tag (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_topic (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            intro_en LONGTEXT NULL,
            intro_ar LONGTEXT NULL,
            hero_image VARCHAR(500) NULL,
            city VARCHAR(120) NULL,
            topic_type ENUM('pillar','role','city','compliance') NOT NULL DEFAULT 'pillar',
            parent_id INT UNSIGNED NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_topic_code (code),
            UNIQUE KEY uq_ha_topic_slug_en (slug_en),
            UNIQUE KEY uq_ha_topic_slug_ar (slug_ar),
            KEY ix_ha_topic_status (status)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_topic_course (
            topic_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (topic_id, course_id),
            CONSTRAINT fk_ha_tc_topic FOREIGN KEY (topic_id) REFERENCES ha_topic (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_tc_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_faq (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope_type ENUM('global','course','program','page','topic') NOT NULL DEFAULT 'global',
            scope_id INT UNSIGNED NULL,
            question_en VARCHAR(500) NOT NULL,
            question_ar VARCHAR(500) NOT NULL,
            answer_en TEXT NOT NULL,
            answer_ar TEXT NOT NULL,
            include_in_schema TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_faq_scope (scope_type, scope_id)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_testimonial (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            person_name VARCHAR(190) NOT NULL,
            person_title_en VARCHAR(190) NULL,
            person_title_ar VARCHAR(190) NULL,
            organization_name VARCHAR(190) NULL,
            quote_en TEXT NOT NULL,
            quote_ar TEXT NOT NULL,
            avatar VARCHAR(500) NULL,
            consent_reference VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_testimonial_status (status)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_media (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            disk ENUM('public','private') NOT NULL DEFAULT 'public',
            file_path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            extension VARCHAR(20) NOT NULL,
            file_size INT UNSIGNED NOT NULL DEFAULT 0,
            width INT UNSIGNED NULL,
            height INT UNSIGNED NULL,
            alt_en VARCHAR(255) NULL,
            alt_ar VARCHAR(255) NULL,
            checksum CHAR(40) NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_media_mime (mime_type),
            KEY ix_ha_media_checksum (checksum)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_menu (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(120) NOT NULL,
            name_ar VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_menu_code (code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_menu_item (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            menu_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED NULL,
            label_en VARCHAR(190) NOT NULL,
            label_ar VARCHAR(190) NOT NULL,
            url_en VARCHAR(500) NOT NULL,
            url_ar VARCHAR(500) NOT NULL,
            open_in_new_tab TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('active','hidden') NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            KEY ix_ha_menuitem_menu (menu_id),
            CONSTRAINT fk_ha_menuitem_menu FOREIGN KEY (menu_id) REFERENCES ha_menu (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_seo_metadata (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(60) NOT NULL,
            entity_id INT UNSIGNED NULL,
            route_key VARCHAR(190) NULL,
            locale ENUM('en','ar') NOT NULL,
            meta_title VARCHAR(190) NULL,
            meta_description VARCHAR(320) NULL,
            canonical_url VARCHAR(500) NULL,
            robots VARCHAR(80) NOT NULL DEFAULT 'index,follow',
            og_title VARCHAR(190) NULL,
            og_description VARCHAR(320) NULL,
            og_image VARCHAR(500) NULL,
            twitter_card ENUM('summary','summary_large_image') NOT NULL DEFAULT 'summary_large_image',
            schema_json LONGTEXT NULL,
            focus_keyword VARCHAR(190) NULL,
            secondary_keywords VARCHAR(500) NULL,
            related_keywords VARCHAR(500) NULL,
            internal_link_targets VARCHAR(500) NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_seo (entity_type, entity_id, route_key, locale),
            KEY ix_ha_seo_entity (entity_type, entity_id)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_redirect (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_path VARCHAR(500) NOT NULL,
            target_path VARCHAR(500) NOT NULL,
            status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
            hit_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_hit_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            note VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_redirect_source (source_path),
            KEY ix_ha_redirect_active (is_active)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_lead (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(60) NULL,
            organization_name VARCHAR(190) NULL,
            city VARCHAR(120) NULL,
            headcount VARCHAR(60) NULL,
            interest ENUM('hotel_training','course','program','sop','certification','other') NOT NULL DEFAULT 'other',
            message TEXT NULL,
            source_page VARCHAR(500) NULL,
            locale ENUM('en','ar') NOT NULL DEFAULT 'en',
            status ENUM('new','contacted','qualified','converted','closed') NOT NULL DEFAULT 'new',
            handled_by INT UNSIGNED NULL,
            handled_at DATETIME NULL,
            internal_note TEXT NULL,
            ip_address VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_lead_status (status),
            KEY ix_ha_lead_created (created_at)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competitor (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            website VARCHAR(255) NOT NULL,
            country VARCHAR(80) NULL,
            target_audience VARCHAR(255) NULL,
            languages VARCHAR(190) NULL,
            content_type VARCHAR(190) NULL,
            pricing_model VARCHAR(190) NULL,
            pricing_evidence_url VARCHAR(500) NULL,
            content_frequency VARCHAR(120) NULL,
            strengths TEXT NULL,
            gaps TEXT NULL,
            evidence_url VARCHAR(500) NOT NULL,
            last_checked_at DATE NOT NULL,
            checked_by INT UNSIGNED NULL,
            notes TEXT NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_competitor_slug (slug),
            KEY ix_ha_competitor_checked (last_checked_at)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competitor_capability (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            competitor_id INT UNSIGNED NOT NULL,
            capability VARCHAR(80) NOT NULL,
            is_present ENUM('yes','no','unknown') NOT NULL DEFAULT 'unknown',
            evidence_url VARCHAR(500) NULL,
            observed_at DATE NULL,
            note VARCHAR(500) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_cc (competitor_id, capability),
            CONSTRAINT fk_ha_cc_competitor FOREIGN KEY (competitor_id) REFERENCES ha_competitor (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competitor_page (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            competitor_id INT UNSIGNED NOT NULL,
            url VARCHAR(500) NOT NULL,
            observed_title VARCHAR(255) NULL,
            observed_topic VARCHAR(255) NULL,
            content_type VARCHAR(80) NULL,
            language ENUM('en','ar','other') NOT NULL DEFAULT 'en',
            last_checked_at DATE NOT NULL,
            note VARCHAR(500) NULL,
            PRIMARY KEY (id),
            KEY ix_ha_cp_competitor (competitor_id),
            CONSTRAINT fk_ha_cp_competitor FOREIGN KEY (competitor_id) REFERENCES ha_competitor (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competitor_keyword (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword VARCHAR(190) NOT NULL,
            language ENUM('en','ar') NOT NULL DEFAULT 'en',
            country VARCHAR(80) NOT NULL DEFAULT 'Saudi Arabia',
            search_intent ENUM('informational','commercial','transactional','navigational') NOT NULL DEFAULT 'informational',
            cluster VARCHAR(120) NULL,
            our_page VARCHAR(500) NULL,
            content_gap TEXT NULL,
            topic_gap TEXT NULL,
            internal_link_gap TEXT NULL,
            status ENUM('tracking','covered','ignored') NOT NULL DEFAULT 'tracking',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_ck (keyword, language, country),
            KEY ix_ha_ck_cluster (cluster)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competitor_observation (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword_id INT UNSIGNED NOT NULL,
            competitor_id INT UNSIGNED NULL,
            url VARCHAR(500) NOT NULL,
            observed_title VARCHAR(255) NULL,
            observed_position SMALLINT UNSIGNED NULL,
            content_type VARCHAR(80) NULL,
            source VARCHAR(190) NOT NULL,
            observed_at DATE NOT NULL,
            note VARCHAR(500) NULL,
            recorded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_co_keyword (keyword_id),
            KEY ix_ha_co_competitor (competitor_id),
            CONSTRAINT fk_ha_co_keyword FOREIGN KEY (keyword_id) REFERENCES ha_competitor_keyword (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_co_competitor FOREIGN KEY (competitor_id) REFERENCES ha_competitor (id) ON DELETE SET NULL
        )" . $e);
    }

    public function down() {
        $this->drop($this->tables);
    }
}
