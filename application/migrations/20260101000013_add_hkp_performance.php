<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * altus Hospitality Knowledge & Performance - platform foundation and the
 * performance evidence chain (ppt-features sections 5-8, 13-26, 31, 52, 69-72,
 * 88-123, 133-134).
 *
 *   tenancy     portfolios, teams, richer property / organisation / profile fields
 *   white-label per organisation and per property branding, layered settings
 *   curriculum  Professional Domain -> Track -> Module (ha_course) -> Lesson
 *               blocks and lesson versions, so content is never overwritten
 *   capability  competency levels, role -> competency / requirement matrices,
 *               append-only competency results, practical rubrics and scores,
 *               evidence, secure files
 *   performance gaps, corrective action plans with history, reassessment,
 *               readiness policies and records, certification programmes,
 *               cohorts, notification templates and rules, alerts, KPIs
 *
 * Existing tables are reused and extended rather than duplicated: ha_skill is
 * the competency library, ha_person_skill the current-level snapshot,
 * ha_certificate the certificate register, ha_notification the store.
 */
class Migration_Add_hkp_performance extends Ha_migration {

    private $tables = array(
        'ha_competency_kpi', 'ha_kpi_value', 'ha_kpi', 'ha_alert',
        'ha_notification_rule', 'ha_notification_template',
        'ha_cohort_member', 'ha_cohort',
        'ha_certification_program',
        'ha_opening_readiness_item', 'ha_readiness_record', 'ha_readiness_policy',
        'ha_reassessment', 'ha_action_plan_event', 'ha_action_plan', 'ha_competency_gap',
        'ha_evidence', 'ha_practical_score', 'ha_practical_assessment', 'ha_rubric_criterion', 'ha_rubric',
        'ha_assessment_competency', 'ha_competency_result', 'ha_role_requirement', 'ha_role_competency', 'ha_competency_level',
        'ha_file',
        'ha_lesson_version', 'ha_lesson_block',
        'ha_track_module', 'ha_track', 'ha_domain',
        'ha_setting', 'ha_branding', 'ha_team', 'ha_portfolio',
    );

    public function up() {
        $e = $this->engine;

        // ------------------------------------------------------------ tenancy
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_portfolio (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NOT NULL,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_portfolio (organization_id, code),
            CONSTRAINT fk_ha_portfolio_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE CASCADE
        )" . $e);

        $this->add_columns('ha_organization', array(
            'industry'              => "VARCHAR(80) NULL AFTER registration_no",
            'plan'                  => "VARCHAR(40) NULL",
            'account_owner_user_id' => "INT UNSIGNED NULL",
            'currency'              => "CHAR(3) NOT NULL DEFAULT 'SAR'",
        ));

        $this->db->query("ALTER TABLE ha_property MODIFY property_type ENUM('hotel','resort','serviced_apartment','furnished_apartment','boutique','chalet','mixed_use','restaurant','cafe','catering','other') NOT NULL DEFAULT 'hotel'");
        $this->add_columns('ha_property', array(
            'portfolio_id'   => "INT UNSIGNED NULL AFTER organization_id",
            'code'           => "VARCHAR(40) NULL AFTER slug",
            'star_rating'    => "TINYINT UNSIGNED NULL",
            'address'        => "VARCHAR(255) NULL",
            'opening_date'   => "DATE NULL",
            'timezone'       => "VARCHAR(64) NOT NULL DEFAULT 'Asia/Riyadh'",
            'default_locale' => "ENUM('en','ar') NOT NULL DEFAULT 'en'",
            'currency'       => "CHAR(3) NOT NULL DEFAULT 'SAR'",
        ));

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_team (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NOT NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            lead_user_id INT UNSIGNED NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_team_property (property_id),
            CONSTRAINT fk_ha_team_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE CASCADE
        )" . $e);

        // Terminated staff keep every record; they only lose access (Ha_auth refuses a non-active profile).
        $this->db->query("ALTER TABLE ha_profile MODIFY status ENUM('active','inactive','on_leave','suspended','terminated','archived') NOT NULL DEFAULT 'active'");
        $this->add_columns('ha_profile', array(
            'team_id'         => "INT UNSIGNED NULL AFTER department_id",
            'full_name_ar'    => "VARCHAR(190) NULL AFTER employee_no",
            'employment_type' => "ENUM('full_time','part_time','contract','intern','seasonal') NOT NULL DEFAULT 'full_time'",
            'terminated_at'   => "DATE NULL",
        ));

        // --------------------------------------------------------- white-label
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_branding (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope_type ENUM('platform','organization','property') NOT NULL,
            scope_id INT UNSIGNED NOT NULL DEFAULT 0,
            brand_name_en VARCHAR(190) NULL,
            brand_name_ar VARCHAR(190) NULL,
            logo_path VARCHAR(500) NULL,
            favicon_path VARCHAR(500) NULL,
            color_primary CHAR(7) NULL,
            color_secondary CHAR(7) NULL,
            color_accent CHAR(7) NULL,
            color_surface CHAR(7) NULL,
            font_latin VARCHAR(80) NULL,
            font_arabic VARCHAR(80) NULL,
            login_headline_en VARCHAR(255) NULL,
            login_headline_ar VARCHAR(255) NULL,
            welcome_en TEXT NULL,
            welcome_ar TEXT NULL,
            email_footer_en TEXT NULL,
            email_footer_ar TEXT NULL,
            signatory_name_en VARCHAR(190) NULL,
            signatory_name_ar VARCHAR(190) NULL,
            signatory_title_en VARCHAR(190) NULL,
            signatory_title_ar VARCHAR(190) NULL,
            signature_path VARCHAR(500) NULL,
            custom_domain VARCHAR(190) NULL,
            show_altus ENUM('altus','client','both') NOT NULL DEFAULT 'both',
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_branding_scope (scope_type, scope_id),
            UNIQUE KEY uq_ha_branding_domain (custom_domain)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_setting (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope_type ENUM('global','organization','property') NOT NULL DEFAULT 'global',
            scope_id INT UNSIGNED NOT NULL DEFAULT 0,
            setting_key VARCHAR(120) NOT NULL,
            value TEXT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_setting (scope_type, scope_id, setting_key)
        )" . $e);

        // ---------------------------------------------------------- curriculum
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_domain (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            icon VARCHAR(60) NULL,
            domain_group ENUM('core','esg','advisory','leadership','other') NOT NULL DEFAULT 'core',
            is_core TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_domain_code (code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_track (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            domain_id INT UNSIGNED NOT NULL,
            code VARCHAR(80) NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            summary_en TEXT NULL,
            summary_ar TEXT NULL,
            level ENUM('foundation','intermediate','advanced','leadership') NOT NULL DEFAULT 'foundation',
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('draft','review','approved','published','archived') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_track_code (code),
            KEY ix_ha_track_domain (domain_id),
            KEY ix_ha_track_scope (organization_id, property_id),
            CONSTRAINT fk_ha_track_domain FOREIGN KEY (domain_id) REFERENCES ha_domain (id) ON DELETE RESTRICT
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_track_module (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            track_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_track_module (track_id, course_id),
            KEY ix_ha_tm_course (course_id),
            CONSTRAINT fk_ha_tm_track FOREIGN KEY (track_id) REFERENCES ha_track (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_tm_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        // A module (ha_course) may belong to one client or property; NULL is Altus global content.
        $this->add_columns('ha_course', array(
            'domain_id'       => "INT UNSIGNED NULL AFTER category_id",
            'organization_id' => "INT UNSIGNED NULL AFTER domain_id",
            'property_id'     => "INT UNSIGNED NULL AFTER organization_id",
        ));

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_lesson_block (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar','both') NOT NULL DEFAULT 'both',
            block_type ENUM('text','image','video','audio','pdf','download','quote','checklist','scenario','checkpoint','question','callout','table','embed') NOT NULL DEFAULT 'text',
            title VARCHAR(190) NULL,
            content LONGTEXT NULL,
            media_path VARCHAR(500) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_block_lesson (lesson_id, sort_order),
            CONSTRAINT fk_ha_block_lesson FOREIGN KEY (lesson_id) REFERENCES ha_lesson (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_lesson_version (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id INT UNSIGNED NOT NULL,
            version_no INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            change_summary VARCHAR(500) NULL,
            changed_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_lesson_version (lesson_id, version_no),
            CONSTRAINT fk_ha_lv_lesson FOREIGN KEY (lesson_id) REFERENCES ha_lesson (id) ON DELETE CASCADE
        )" . $e);

        // Secure files: never a guessable public URL; served by token after an authorisation check.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_file (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            token CHAR(40) NOT NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            owner_user_id INT UNSIGNED NULL,
            entity_type VARCHAR(60) NULL,
            entity_id INT UNSIGNED NULL,
            version_no INT UNSIGNED NOT NULL DEFAULT 1,
            original_name VARCHAR(255) NOT NULL,
            stored_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            sha256 CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_file_token (token),
            KEY ix_ha_file_entity (entity_type, entity_id)
        )" . $e);

        // ---------------------------------------------------------- competency
        $this->add_columns('ha_skill', array(
            'domain_id'              => "INT UNSIGNED NULL AFTER department_code",
            'organization_id'        => "INT UNSIGNED NULL AFTER domain_id",
            'criticality'            => "ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium'",
            'assessment_method'      => "ENUM('theory','practical','theory_practical','observation') NOT NULL DEFAULT 'theory_practical'",
            'evidence_type'          => "VARCHAR(120) NULL",
            'default_required_level' => "TINYINT UNSIGNED NOT NULL DEFAULT 3",
        ));
        $this->add_columns('ha_person_skill', array(
            'level_no'       => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
            'last_result_id' => "BIGINT UNSIGNED NULL",
            'assessed_by'    => "INT UNSIGNED NULL",
        ));

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competency_level (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NOT NULL DEFAULT 0,
            level_no TINYINT UNSIGNED NOT NULL,
            code VARCHAR(40) NOT NULL,
            name_en VARCHAR(120) NOT NULL,
            name_ar VARCHAR(120) NOT NULL,
            description_en VARCHAR(500) NULL,
            description_ar VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_comp_level (organization_id, level_no)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_role_competency (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_role_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NOT NULL,
            property_key INT UNSIGNED NOT NULL DEFAULT 0,
            required_level TINYINT UNSIGNED NOT NULL DEFAULT 3,
            is_critical TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_role_comp (job_role_id, skill_id, property_key),
            KEY ix_ha_rc_skill (skill_id),
            CONSTRAINT fk_ha_rc_role FOREIGN KEY (job_role_id) REFERENCES ha_job_role (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_rc_skill FOREIGN KEY (skill_id) REFERENCES ha_skill (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_role_requirement (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_role_id INT UNSIGNED NOT NULL,
            property_key INT UNSIGNED NOT NULL DEFAULT 0,
            item_type ENUM('track','course','assessment','sop','rubric','certification') NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
            due_days INT UNSIGNED NOT NULL DEFAULT 30,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_role_req (job_role_id, property_key, item_type, item_id),
            CONSTRAINT fk_ha_rr_role FOREIGN KEY (job_role_id) REFERENCES ha_job_role (id) ON DELETE CASCADE
        )" . $e);

        // Append-only. The current level lives in ha_person_skill; history is never overwritten.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competency_result (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NOT NULL,
            level_no TINYINT UNSIGNED NOT NULL,
            previous_level_no TINYINT UNSIGNED NOT NULL DEFAULT 0,
            source ENUM('theory','practical','reassessment','manager','import','course','audit') NOT NULL,
            source_id INT UNSIGNED NULL,
            assessor_user_id INT UNSIGNED NULL,
            evidence_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            notes VARCHAR(500) NULL,
            assessed_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_cres_user (user_id, skill_id, assessed_at),
            KEY ix_ha_cres_property (property_id),
            CONSTRAINT fk_ha_cres_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_cres_skill FOREIGN KEY (skill_id) REFERENCES ha_skill (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_assessment_competency (
            assessment_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NOT NULL,
            level_on_pass TINYINT UNSIGNED NOT NULL DEFAULT 2,
            PRIMARY KEY (assessment_id, skill_id),
            CONSTRAINT fk_ha_ac_assessment FOREIGN KEY (assessment_id) REFERENCES ha_assessment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_ac_skill FOREIGN KEY (skill_id) REFERENCES ha_skill (id) ON DELETE CASCADE
        )" . $e);

        // Theory engine extensions: ordering questions, competency-tagged items, versioned questions.
        $this->db->query("ALTER TABLE ha_question MODIFY question_type ENUM('multiple_choice','true_false','multiple_response','matching','ordering','scenario','short_answer','essay') NOT NULL DEFAULT 'multiple_choice'");
        $this->add_columns('ha_question', array(
            'skill_id'   => "INT UNSIGNED NULL AFTER bank_id",
            'domain_id'  => "INT UNSIGNED NULL AFTER skill_id",
            'version_no' => "INT UNSIGNED NOT NULL DEFAULT 1",
            'tags'       => "VARCHAR(255) NULL",
        ));
        $this->add_columns('ha_assessment', array(
            'domain_id'       => "INT UNSIGNED NULL AFTER bank_id",
            'organization_id' => "INT UNSIGNED NULL AFTER domain_id",
            'property_id'     => "INT UNSIGNED NULL AFTER organization_id",
        ));
        $this->add_columns('ha_assessment_question', array('section_label' => "VARCHAR(120) NULL"));
        $this->add_columns('ha_assessment_attempt', array('property_id' => "INT UNSIGNED NULL AFTER user_id"));
        $this->add_columns('ha_assessment_answer', array('response_ms' => "INT UNSIGNED NULL"));

        // ----------------------------------------------------------- practical
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_rubric (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            skill_id INT UNSIGNED NULL,
            domain_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            developing_threshold DECIMAL(5,2) NOT NULL DEFAULT 50,
            pass_threshold DECIMAL(5,2) NOT NULL DEFAULT 75,
            exceeds_threshold DECIMAL(5,2) NOT NULL DEFAULT 90,
            version_no INT UNSIGNED NOT NULL DEFAULT 1,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_rubric_code (code),
            KEY ix_ha_rubric_skill (skill_id)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_rubric_criterion (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            rubric_id INT UNSIGNED NOT NULL,
            label_en VARCHAR(255) NOT NULL,
            label_ar VARCHAR(255) NOT NULL,
            guidance_en VARCHAR(500) NULL,
            guidance_ar VARCHAR(500) NULL,
            weight DECIMAL(6,2) NOT NULL DEFAULT 10,
            is_critical TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_ha_crit_rubric (rubric_id),
            CONSTRAINT fk_ha_crit_rubric FOREIGN KEY (rubric_id) REFERENCES ha_rubric (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_practical_assessment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            rubric_id INT UNSIGNED NOT NULL,
            rubric_version INT UNSIGNED NOT NULL DEFAULT 1,
            user_id INT UNSIGNED NOT NULL,
            assessor_user_id INT UNSIGNED NOT NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            attempt_no INT UNSIGNED NOT NULL DEFAULT 1,
            status ENUM('draft','submitted','voided') NOT NULL DEFAULT 'draft',
            weighted_score DECIMAL(5,2) NULL,
            outcome ENUM('not_demonstrated','developing','competent','exceeds') NULL,
            resulting_level TINYINT UNSIGNED NULL,
            critical_failed TINYINT(1) NOT NULL DEFAULT 0,
            comments TEXT NULL,
            reassessment_id INT UNSIGNED NULL,
            started_at DATETIME NOT NULL,
            submitted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_prac_user (user_id, rubric_id),
            KEY ix_ha_prac_assessor (assessor_user_id, status),
            KEY ix_ha_prac_property (property_id),
            CONSTRAINT fk_ha_prac_rubric FOREIGN KEY (rubric_id) REFERENCES ha_rubric (id) ON DELETE RESTRICT,
            CONSTRAINT fk_ha_prac_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_practical_score (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            practical_id INT UNSIGNED NOT NULL,
            criterion_id INT UNSIGNED NOT NULL,
            rating ENUM('not_demonstrated','developing','competent','exceeds') NULL,
            points DECIMAL(6,2) NOT NULL DEFAULT 0,
            comment VARCHAR(500) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_pscore (practical_id, criterion_id),
            CONSTRAINT fk_ha_pscore_prac FOREIGN KEY (practical_id) REFERENCES ha_practical_assessment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_pscore_crit FOREIGN KEY (criterion_id) REFERENCES ha_rubric_criterion (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_evidence (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NULL,
            action_plan_id INT UNSIGNED NULL,
            practical_id INT UNSIGNED NULL,
            finding_id INT UNSIGNED NULL,
            evidence_type ENUM('document','photo','video','supervisor_note','assessment_result','observation','practical_score','external_certificate','audit_score','mystery_guest','complaint_theme') NOT NULL DEFAULT 'document',
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            file_id INT UNSIGNED NULL,
            uploaded_by INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_evid_user (user_id, skill_id),
            KEY ix_ha_evid_action (action_plan_id),
            KEY ix_ha_evid_practical (practical_id),
            CONSTRAINT fk_ha_evid_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        // ---------------------------------------------------- gaps and actions
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competency_gap (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NOT NULL,
            job_role_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            required_level TINYINT UNSIGNED NOT NULL,
            current_level TINYINT UNSIGNED NOT NULL,
            gap_levels TINYINT UNSIGNED NOT NULL,
            severity ENUM('none','minor','moderate','critical') NOT NULL DEFAULT 'minor',
            reason ENUM('below_level','failed_assessment','missing_practical','expired_certification','incomplete_learning','repeated_weakness') NOT NULL DEFAULT 'below_level',
            status ENUM('open','in_action','closed','waived') NOT NULL DEFAULT 'open',
            detected_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            closed_by_result_id BIGINT UNSIGNED NULL,
            notes VARCHAR(500) NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_gap_user (user_id, status),
            KEY ix_ha_gap_skill (skill_id, status),
            KEY ix_ha_gap_property (property_id, status, severity),
            CONSTRAINT fk_ha_gap_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_gap_skill FOREIGN KEY (skill_id) REFERENCES ha_skill (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_action_plan (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            gap_id INT UNSIGNED NULL,
            finding_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            action_type ENUM('training','coaching','shadowing','practice','sop_review','knowledge_assessment','practical_exercise','observation','mentoring') NOT NULL DEFAULT 'training',
            required_action TEXT NULL,
            linked_type ENUM('none','course','sop','assessment','rubric') NOT NULL DEFAULT 'none',
            linked_id INT UNSIGNED NULL,
            assigned_manager_id INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            status ENUM('open','assigned','in_progress','submitted','under_review','completed','overdue','rejected') NOT NULL DEFAULT 'open',
            due_at DATE NULL,
            submitted_at DATETIME NULL,
            completed_at DATETIME NULL,
            rejection_reason VARCHAR(500) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_ap_user (user_id, status),
            KEY ix_ha_ap_gap (gap_id),
            KEY ix_ha_ap_property (property_id, status),
            KEY ix_ha_ap_due (due_at),
            CONSTRAINT fk_ha_ap_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_action_plan_event (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            action_plan_id INT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NULL,
            from_status VARCHAR(30) NULL,
            to_status VARCHAR(30) NOT NULL,
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_ape_plan (action_plan_id),
            CONSTRAINT fk_ha_ape_plan FOREIGN KEY (action_plan_id) REFERENCES ha_action_plan (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_reassessment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            action_plan_id INT UNSIGNED NULL,
            gap_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NULL,
            rubric_id INT UNSIGNED NULL,
            requested_by INT UNSIGNED NULL,
            status ENUM('requested','approved','declined','completed') NOT NULL DEFAULT 'requested',
            approved_by INT UNSIGNED NULL,
            decided_at DATETIME NULL,
            practical_id INT UNSIGNED NULL,
            result_level TINYINT UNSIGNED NULL,
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_reas_user (user_id, status),
            CONSTRAINT fk_ha_reas_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        // ----------------------------------------------------------- readiness
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_readiness_policy (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            job_role_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            rules_json LONGTEXT NOT NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_rpol_code (code),
            KEY ix_ha_rpol_role (job_role_id)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_readiness_record (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            policy_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            job_role_id INT UNSIGNED NULL,
            status ENUM('ready','conditional','not_ready') NOT NULL,
            reasons_json LONGTEXT NULL,
            inputs_json LONGTEXT NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 1,
            calculated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_rrec_user (user_id, is_current),
            KEY ix_ha_rrec_property (property_id, is_current, status),
            CONSTRAINT fk_ha_rrec_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_opening_readiness_item (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id INT UNSIGNED NOT NULL,
            category ENUM('recruitment','training','competency','sop','systems','safety','quality','commercial') NOT NULL,
            label_en VARCHAR(255) NOT NULL,
            label_ar VARCHAR(255) NOT NULL,
            auto_source ENUM('manual','training','competency','sop','certification') NOT NULL DEFAULT 'manual',
            required_value DECIMAL(10,2) NOT NULL DEFAULT 100,
            current_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            is_critical TINYINT(1) NOT NULL DEFAULT 0,
            owner_user_id INT UNSIGNED NULL,
            due_date DATE NULL,
            action_plan_id INT UNSIGNED NULL,
            notes VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_ori_property (property_id, category),
            CONSTRAINT fk_ha_ori_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE CASCADE
        )" . $e);

        // -------------------------------------------------------- certification
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_certification_program (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            domain_id INT UNSIGNED NULL,
            job_role_id INT UNSIGNED NULL,
            template_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            number_prefix VARCHAR(20) NOT NULL DEFAULT 'ALTUS',
            rules_json LONGTEXT NOT NULL,
            validity_months INT UNSIGNED NOT NULL DEFAULT 24,
            issuing_authority_en VARCHAR(190) NOT NULL DEFAULT 'Altus Advisory',
            issuing_authority_ar VARCHAR(190) NOT NULL DEFAULT 'ألتوس للاستشارات',
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_certprog_code (code)
        )" . $e);

        $this->add_columns('ha_certificate', array(
            'program_id'        => "INT UNSIGNED NULL AFTER path_id",
            'organization_id'   => "INT UNSIGNED NULL AFTER program_id",
            'property_id'       => "INT UNSIGNED NULL AFTER organization_id",
            'role_title_en'     => "VARCHAR(190) NULL",
            'role_title_ar'     => "VARCHAR(190) NULL",
            'domain_title_en'   => "VARCHAR(190) NULL",
            'domain_title_ar'   => "VARCHAR(190) NULL",
            'issuer_en'         => "VARCHAR(190) NULL",
            'issuer_ar'         => "VARCHAR(190) NULL",
            'locale'            => "ENUM('en','ar','bilingual') NOT NULL DEFAULT 'bilingual'",
            'evidence_json'     => "LONGTEXT NULL",
        ));

        // ------------------------------------------------------------- cohorts
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_cohort (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NOT NULL,
            property_id INT UNSIGNED NULL,
            code VARCHAR(80) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            cohort_type ENUM('general','new_joiners','pre_opening','management','department','role') NOT NULL DEFAULT 'general',
            opening_date DATE NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_cohort_code (organization_id, code),
            CONSTRAINT fk_ha_cohort_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_cohort_member (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cohort_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            wave VARCHAR(40) NULL,
            joined_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_cohort_member (cohort_id, user_id),
            CONSTRAINT fk_ha_cm_cohort FOREIGN KEY (cohort_id) REFERENCES ha_cohort (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_cm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("ALTER TABLE ha_training_target MODIFY target_type ENUM('user','department','property','job_role','organization','cohort') NOT NULL");
        $this->db->query("ALTER TABLE ha_training_item MODIFY item_type ENUM('course','program','path','sop','assessment','checklist','track','rubric') NOT NULL");
        $this->db->query("ALTER TABLE ha_training_recipient MODIFY status ENUM('assigned','in_progress','completed','overdue','waived','failed','expired') NOT NULL DEFAULT 'assigned'");
        $this->add_columns('ha_training_assignment', array(
            'property_id' => "INT UNSIGNED NULL AFTER organization_id",
            'priority'    => "ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium'",
            'auto_enrol'  => "TINYINT(1) NOT NULL DEFAULT 1",
            'source'      => "ENUM('manual','role_requirement','action_plan','cohort') NOT NULL DEFAULT 'manual'",
        ));
        $this->add_columns('ha_training_recipient', array('exemption_reason' => "VARCHAR(255) NULL"));

        // ------------------------------------------------------- notifications
        $this->db->query("ALTER TABLE ha_notification MODIFY category ENUM('training','certificates','sop','assessment','system','competency','action','readiness','knowledge','alert') NOT NULL DEFAULT 'system'");

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_notification_template (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_code VARCHAR(80) NOT NULL,
            channel ENUM('in_app','email','sms','whatsapp') NOT NULL DEFAULT 'in_app',
            locale ENUM('en','ar') NOT NULL,
            organization_id INT UNSIGNED NOT NULL DEFAULT 0,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_ntpl (event_code, channel, locale, organization_id)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_notification_rule (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_code VARCHAR(80) NOT NULL,
            organization_id INT UNSIGNED NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            channels VARCHAR(60) NOT NULL DEFAULT 'in_app,email',
            notify_manager TINYINT(1) NOT NULL DEFAULT 0,
            days_offset INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_nrule (event_code, organization_id)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_alert (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            alert_type VARCHAR(60) NOT NULL,
            severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
            title_en VARCHAR(255) NOT NULL,
            title_ar VARCHAR(255) NOT NULL,
            detail VARCHAR(500) NULL,
            entity_type VARCHAR(60) NULL,
            entity_id INT UNSIGNED NULL,
            url VARCHAR(500) NULL,
            dedupe_key VARCHAR(190) NOT NULL,
            status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_alert_dedupe (dedupe_key),
            KEY ix_ha_alert_scope (property_id, status, severity)
        )" . $e);

        // ----------------------------------------------------------------- KPI
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_kpi (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            category ENUM('revenue','cost','guest','people','learning','quality','sustainability','governance','other') NOT NULL DEFAULT 'other',
            definition_en TEXT NULL,
            definition_ar TEXT NULL,
            formula VARCHAR(255) NULL,
            unit VARCHAR(20) NULL,
            direction ENUM('higher_better','lower_better') NOT NULL DEFAULT 'higher_better',
            target DECIMAL(16,4) NULL,
            threshold_warning DECIMAL(16,4) NULL,
            threshold_critical DECIMAL(16,4) NULL,
            frequency ENUM('daily','weekly','monthly','quarterly','annual') NOT NULL DEFAULT 'monthly',
            owner_user_id INT UNSIGNED NULL,
            source ENUM('manual','csv','api','pms','pos','bi','warehouse','platform') NOT NULL DEFAULT 'manual',
            value_stack ENUM('top_line','distribution','cost','asset') NULL,
            esg_category ENUM('governance','community','tourism','sustainability') NULL,
            capability_category VARCHAR(40) NULL,
            organization_id INT UNSIGNED NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_kpi_code (code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_kpi_value (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            kpi_id INT UNSIGNED NOT NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NOT NULL,
            department_key INT UNSIGNED NOT NULL DEFAULT 0,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            actual DECIMAL(16,4) NOT NULL,
            target DECIMAL(16,4) NULL,
            source ENUM('manual','csv','api','pms','pos','bi','warehouse','platform') NOT NULL DEFAULT 'manual',
            source_ref VARCHAR(190) NULL,
            entered_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_kpi_value (kpi_id, property_id, department_key, period_start),
            KEY ix_ha_kpiv_property (property_id, period_start),
            CONSTRAINT fk_ha_kpiv_kpi FOREIGN KEY (kpi_id) REFERENCES ha_kpi (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_kpiv_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_competency_kpi (
            skill_id INT UNSIGNED NOT NULL,
            kpi_id INT UNSIGNED NOT NULL,
            note VARCHAR(255) NULL,
            PRIMARY KEY (skill_id, kpi_id),
            CONSTRAINT fk_ha_ckpi_skill FOREIGN KEY (skill_id) REFERENCES ha_skill (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_ckpi_kpi FOREIGN KEY (kpi_id) REFERENCES ha_kpi (id) ON DELETE CASCADE
        )" . $e);
    }

    public function down() {
        $this->drop($this->tables);
        $this->drop_columns('ha_training_recipient', array('exemption_reason'));
        $this->drop_columns('ha_training_assignment', array('property_id', 'priority', 'auto_enrol', 'source'));
        $this->drop_columns('ha_certificate', array('program_id', 'organization_id', 'property_id', 'role_title_en', 'role_title_ar',
            'domain_title_en', 'domain_title_ar', 'issuer_en', 'issuer_ar', 'locale', 'evidence_json'));
        $this->drop_columns('ha_assessment_answer', array('response_ms'));
        $this->drop_columns('ha_assessment_attempt', array('property_id'));
        $this->drop_columns('ha_assessment_question', array('section_label'));
        $this->drop_columns('ha_assessment', array('domain_id', 'organization_id', 'property_id'));
        $this->drop_columns('ha_question', array('skill_id', 'domain_id', 'version_no', 'tags'));
        $this->drop_columns('ha_person_skill', array('level_no', 'last_result_id', 'assessed_by'));
        $this->drop_columns('ha_skill', array('domain_id', 'organization_id', 'criticality', 'assessment_method', 'evidence_type', 'default_required_level'));
        $this->drop_columns('ha_course', array('domain_id', 'organization_id', 'property_id'));
        $this->drop_columns('ha_profile', array('team_id', 'full_name_ar', 'employment_type', 'terminated_at'));
        $this->drop_columns('ha_property', array('portfolio_id', 'code', 'star_rating', 'address', 'opening_date', 'timezone', 'default_locale', 'currency'));
        $this->drop_columns('ha_organization', array('industry', 'plan', 'account_owner_user_id', 'currency'));

        // Narrow the widened enums back, mapping the new values onto their nearest originals first.
        $this->db->query("UPDATE ha_notification SET category = 'system' WHERE category IN ('competency','action','readiness','knowledge','alert')");
        $this->db->query("ALTER TABLE ha_notification MODIFY category ENUM('training','certificates','sop','assessment','system') NOT NULL DEFAULT 'system'");
        $this->db->query("UPDATE ha_training_recipient SET status = 'assigned' WHERE status IN ('failed','expired')");
        $this->db->query("ALTER TABLE ha_training_recipient MODIFY status ENUM('assigned','in_progress','completed','overdue','waived') NOT NULL DEFAULT 'assigned'");
        $this->db->query("DELETE FROM ha_training_item WHERE item_type IN ('track','rubric')");
        $this->db->query("ALTER TABLE ha_training_item MODIFY item_type ENUM('course','program','path','sop','assessment','checklist') NOT NULL");
        $this->db->query("DELETE FROM ha_training_target WHERE target_type = 'cohort'");
        $this->db->query("ALTER TABLE ha_training_target MODIFY target_type ENUM('user','department','property','job_role','organization') NOT NULL");
        $this->db->query("UPDATE ha_question SET question_type = 'multiple_response' WHERE question_type = 'ordering'");
        $this->db->query("ALTER TABLE ha_question MODIFY question_type ENUM('multiple_choice','true_false','multiple_response','matching','scenario','short_answer','essay') NOT NULL DEFAULT 'multiple_choice'");
        $this->db->query("UPDATE ha_profile SET status = 'inactive' WHERE status IN ('on_leave','terminated','archived')");
        $this->db->query("ALTER TABLE ha_profile MODIFY status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active'");
        $this->db->query("UPDATE ha_property SET property_type = 'other' WHERE property_type IN ('furnished_apartment','boutique','chalet','mixed_use')");
        $this->db->query("ALTER TABLE ha_property MODIFY property_type ENUM('hotel','resort','serviced_apartment','restaurant','cafe','catering','other') NOT NULL DEFAULT 'hotel'");
    }
}
