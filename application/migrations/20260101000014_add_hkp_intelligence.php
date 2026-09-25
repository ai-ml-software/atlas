<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * altus Hospitality Knowledge & Performance - knowledge governance, governed
 * AI, advisory frameworks, quality audits, corporate CMS and operations
 * (ppt-features sections 10-12, 27-29, 33, 39-51, 62, 67, 115-117, 124-132,
 * 146-147, 166, 174-175).
 *
 * The SOP library already carries versioning, bilingual content and scope, so
 * it becomes the knowledge library: ha_sop_document gains an item type (policy,
 * standard, FAQ, job aid ...) and the two-stage review workflow instead of a
 * second, parallel set of knowledge tables.
 */
class Migration_Add_hkp_intelligence extends Ha_migration {

    private $tables = array(
        'ha_queue_job', 'ha_feature_flag', 'ha_report_run', 'ha_import_run',
        'ha_service', 'ha_sector', 'ha_partner', 'ha_leadership_profile', 'ha_case_study', 'ha_corporate_block',
        'ha_quality_finding', 'ha_quality_audit',
        'ha_framework_answer', 'ha_framework_assessment', 'ha_framework_question', 'ha_framework_dimension', 'ha_framework',
        'ha_engagement_task', 'ha_engagement_stage', 'ha_engagement',
        'ha_ai_query', 'ha_ai_chunk',
        'ha_content_view', 'ha_search_log', 'ha_knowledge_review',
    );

    public function up() {
        $e = $this->engine;

        // ----------------------------------------------------------- knowledge
        $this->db->query("ALTER TABLE ha_sop_document MODIFY status ENUM('draft','internal_review','quality_review','review','approved','published','rejected','archived') NOT NULL DEFAULT 'draft'");
        $this->db->query("ALTER TABLE ha_sop_version MODIFY status ENUM('draft','internal_review','quality_review','review','approved','published','rejected','superseded','archived') NOT NULL DEFAULT 'draft'");
        $this->add_columns('ha_sop_document', array(
            'item_type'        => "ENUM('sop','policy','standard','procedure','checklist','work_instruction','faq','job_aid','guide','reference','case_study','best_practice','knowledge_article') NOT NULL DEFAULT 'sop' AFTER code",
            'domain_id'        => "INT UNSIGNED NULL AFTER category_id",
            'reviewer_user_id' => "INT UNSIGNED NULL AFTER owner_user_id",
            'approver_user_id' => "INT UNSIGNED NULL AFTER reviewer_user_id",
            'ai_enabled'       => "TINYINT(1) NOT NULL DEFAULT 1",
            'expires_at'       => "DATE NULL",
            'tags'             => "VARCHAR(255) NULL",
        ));

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_knowledge_review (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sop_id INT UNSIGNED NOT NULL,
            version_id INT UNSIGNED NULL,
            actor_user_id INT UNSIGNED NULL,
            action ENUM('create','submit','approve_internal','approve_quality','approve','reject','request_changes','publish','archive','comment','new_version') NOT NULL,
            from_status VARCHAR(30) NULL,
            to_status VARCHAR(30) NULL,
            comment TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_krev_sop (sop_id, created_at),
            CONSTRAINT fk_ha_krev_sop FOREIGN KEY (sop_id) REFERENCES ha_sop_document (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_search_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            term VARCHAR(190) NOT NULL,
            locale ENUM('en','ar') NOT NULL DEFAULT 'en',
            results_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_slog_term (term),
            KEY ix_ha_slog_created (created_at)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_content_view (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            entity_type ENUM('knowledge','lesson','course','track') NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_cview_entity (entity_type, entity_id)
        )" . $e);

        // ---------------------------------------------------------- governed AI
        // Only approved, published, current content is ever written here, and
        // every row carries the tenant scope used to filter retrieval.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_ai_chunk (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type ENUM('knowledge','lesson') NOT NULL,
            source_id INT UNSIGNED NOT NULL,
            version_id INT UNSIGNED NULL,
            version_label VARCHAR(20) NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_code VARCHAR(60) NULL,
            job_role_id INT UNSIGNED NULL,
            locale ENUM('en','ar') NOT NULL,
            chunk_no INT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            heading VARCHAR(255) NULL,
            body TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            indexed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_chunk_source (source_type, source_id),
            KEY ix_ha_chunk_scope (is_active, organization_id, property_id),
            FULLTEXT KEY ft_ha_chunk (title, heading, body)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_ai_query (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            locale ENUM('en','ar') NOT NULL DEFAULT 'en',
            question TEXT NOT NULL,
            answer LONGTEXT NULL,
            coverage ENUM('answered','insufficient','disabled','no_model','error') NOT NULL,
            sources_json LONGTEXT NULL,
            retrieval_json LONGTEXT NULL,
            provider VARCHAR(60) NULL,
            model VARCHAR(190) NULL,
            input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_aiq_user (user_id, created_at),
            KEY ix_ha_aiq_org (organization_id, created_at)
        )" . $e);

        // ------------------------------------------------------------ advisory
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_engagement (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NOT NULL,
            property_id INT UNSIGNED NULL,
            code VARCHAR(60) NOT NULL,
            name VARCHAR(190) NOT NULL,
            engagement_type ENUM('pre_opening','operations_optimisation','owner_representation','feasibility','quality_audit','commercial_improvement','digital_transformation','leadership_development','strategic_planning','other') NOT NULL DEFAULT 'other',
            consultant_user_id INT UNSIGNED NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            status ENUM('proposed','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'proposed',
            objectives TEXT NULL,
            framework VARCHAR(40) NOT NULL DEFAULT 'ascent',
            deliverables TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_eng_code (code),
            KEY ix_ha_eng_org (organization_id),
            CONSTRAINT fk_ha_eng_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_engagement_stage (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            engagement_id INT UNSIGNED NOT NULL,
            stage ENUM('discover','assess','design','transform','optimise','scale') NOT NULL,
            objectives TEXT NULL,
            owner_user_id INT UNSIGNED NULL,
            due_date DATE NULL,
            status ENUM('not_started','in_progress','awaiting_signoff','signed_off') NOT NULL DEFAULT 'not_started',
            signed_off_by VARCHAR(190) NULL,
            signed_off_at DATETIME NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_eng_stage (engagement_id, stage),
            CONSTRAINT fk_ha_estage_eng FOREIGN KEY (engagement_id) REFERENCES ha_engagement (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_engagement_task (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            stage_id INT UNSIGNED NOT NULL,
            task_type ENUM('task','deliverable','milestone','evidence') NOT NULL DEFAULT 'task',
            title VARCHAR(255) NOT NULL,
            owner_user_id INT UNSIGNED NULL,
            due_date DATE NULL,
            status ENUM('open','in_progress','done','blocked') NOT NULL DEFAULT 'open',
            file_id INT UNSIGNED NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_etask_stage (stage_id),
            CONSTRAINT fk_ha_etask_stage FOREIGN KEY (stage_id) REFERENCES ha_engagement_stage (id) ON DELETE CASCADE
        )" . $e);

        // Performance Matrix, GOPPAR Value Stack, ESG and the capability model share one
        // assessment engine. Scoring thresholds live in config_json, never in code.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_framework (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            scale_max TINYINT UNSIGNED NOT NULL DEFAULT 5,
            config_json LONGTEXT NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_fw_code (code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_framework_dimension (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            framework_id INT UNSIGNED NOT NULL,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            axis VARCHAR(40) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_fwd (framework_id, code),
            CONSTRAINT fk_ha_fwd_fw FOREIGN KEY (framework_id) REFERENCES ha_framework (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_framework_question (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            dimension_id INT UNSIGNED NOT NULL,
            prompt_en VARCHAR(500) NOT NULL,
            prompt_ar VARCHAR(500) NOT NULL,
            weight DECIMAL(5,2) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            KEY ix_ha_fwq_dim (dimension_id),
            CONSTRAINT fk_ha_fwq_dim FOREIGN KEY (dimension_id) REFERENCES ha_framework_dimension (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_framework_assessment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            framework_id INT UNSIGNED NOT NULL,
            organization_id INT UNSIGNED NOT NULL,
            property_id INT UNSIGNED NULL,
            engagement_id INT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            assessed_by INT UNSIGNED NULL,
            status ENUM('draft','completed') NOT NULL DEFAULT 'draft',
            result_json LONGTEXT NULL,
            result_label VARCHAR(80) NULL,
            notes TEXT NULL,
            assessed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_fwa_scope (organization_id, property_id),
            CONSTRAINT fk_ha_fwa_fw FOREIGN KEY (framework_id) REFERENCES ha_framework (id) ON DELETE RESTRICT
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_framework_answer (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessment_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            score DECIMAL(4,2) NULL,
            evidence TEXT NULL,
            recommendation TEXT NULL,
            action_plan_id INT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_fwans (assessment_id, question_id),
            CONSTRAINT fk_ha_fwans_a FOREIGN KEY (assessment_id) REFERENCES ha_framework_assessment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_fwans_q FOREIGN KEY (question_id) REFERENCES ha_framework_question (id) ON DELETE CASCADE
        )" . $e);

        // ------------------------------------------------------ quality audits
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_quality_audit (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id INT UNSIGNED NOT NULL,
            property_id INT UNSIGNED NOT NULL,
            department_id INT UNSIGNED NULL,
            audit_type ENUM('internal','brand_compliance','mystery_guest','safety','sop_compliance') NOT NULL DEFAULT 'internal',
            title VARCHAR(190) NOT NULL,
            sop_id INT UNSIGNED NULL,
            auditor_user_id INT UNSIGNED NULL,
            conducted_at DATE NULL,
            score DECIMAL(5,2) NULL,
            status ENUM('planned','in_progress','completed') NOT NULL DEFAULT 'planned',
            summary TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_qa_property (property_id, status),
            CONSTRAINT fk_ha_qa_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_quality_finding (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            audit_id INT UNSIGNED NOT NULL,
            requirement VARCHAR(500) NOT NULL,
            compliance ENUM('compliant','partial','non_compliant','not_applicable') NOT NULL DEFAULT 'compliant',
            severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
            finding TEXT NULL,
            responsible_user_id INT UNSIGNED NULL,
            action_plan_id INT UNSIGNED NULL,
            recheck_status ENUM('not_required','pending','passed','failed') NOT NULL DEFAULT 'not_required',
            rechecked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_qf_audit (audit_id),
            CONSTRAINT fk_ha_qf_audit FOREIGN KEY (audit_id) REFERENCES ha_quality_audit (id) ON DELETE CASCADE
        )" . $e);

        // -------------------------------------------------------- corporate CMS
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_corporate_block (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            section VARCHAR(60) NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            title_ar VARCHAR(255) NOT NULL,
            body_en LONGTEXT NULL,
            body_ar LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            visibility ENUM('public','client','internal') NOT NULL DEFAULT 'public',
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_cblock_code (code),
            KEY ix_ha_cblock_section (section, sort_order)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_case_study (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(190) NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            title_ar VARCHAR(255) NOT NULL,
            category VARCHAR(80) NULL,
            sector_code VARCHAR(60) NULL,
            geography VARCHAR(120) NULL,
            case_type VARCHAR(120) NULL,
            client_profile_en TEXT NULL,
            client_profile_ar TEXT NULL,
            challenge_en TEXT NULL,
            challenge_ar TEXT NULL,
            approach_en TEXT NULL,
            approach_ar TEXT NULL,
            results_en TEXT NULL,
            results_ar TEXT NULL,
            metrics_json TEXT NULL,
            is_illustrative TINYINT(1) NOT NULL DEFAULT 1,
            visibility ENUM('public','client','internal','confidential') NOT NULL DEFAULT 'internal',
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_case_slug (slug)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_leadership_profile (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(190) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            role_en VARCHAR(255) NOT NULL,
            role_ar VARCHAR(255) NOT NULL,
            biography_en TEXT NULL,
            biography_ar TEXT NULL,
            track_record_en TEXT NULL,
            track_record_ar TEXT NULL,
            recognition_en TEXT NULL,
            recognition_ar TEXT NULL,
            education VARCHAR(500) NULL,
            credentials VARCHAR(500) NULL,
            photo_path VARCHAR(500) NULL,
            linkedin_url VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_lead_slug (slug)
        )" . $e);

        // is_official defaults to 0: the platform never implies a partnership nobody marked as real.
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_partner (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            category ENUM('hotel_operator','global_brand','technology','data','investor','fund','family_office','government','development_authority') NOT NULL,
            description TEXT NULL,
            geography VARCHAR(120) NULL,
            website VARCHAR(255) NULL,
            relationship_status ENUM('prospect','in_discussion','active','inactive') NOT NULL DEFAULT 'prospect',
            is_official TINYINT(1) NOT NULL DEFAULT 0,
            internal_notes TEXT NULL,
            visibility ENUM('public','internal') NOT NULL DEFAULT 'internal',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_partner_cat (category)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_sector (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(60) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_sector_code (code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_service (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(60) NOT NULL,
            division ENUM('hospitality','business_growth') NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            title_ar VARCHAR(255) NOT NULL,
            summary_en TEXT NULL,
            summary_ar TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_service_code (code)
        )" . $e);

        // ---------------------------------------------------------- operations
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_import_run (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            import_type VARCHAR(40) NOT NULL,
            organization_id INT UNSIGNED NULL,
            file_name VARCHAR(255) NOT NULL,
            status ENUM('previewed','committed','failed','rolled_back') NOT NULL DEFAULT 'previewed',
            rows_total INT UNSIGNED NOT NULL DEFAULT 0,
            rows_valid INT UNSIGNED NOT NULL DEFAULT 0,
            rows_error INT UNSIGNED NOT NULL DEFAULT 0,
            errors_json LONGTEXT NULL,
            payload_json LONGTEXT NULL,
            created_ids_json LONGTEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            committed_at DATETIME NULL,
            PRIMARY KEY (id)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_report_run (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            report_code VARCHAR(60) NOT NULL,
            format ENUM('html','csv','xlsx','pdf','print') NOT NULL DEFAULT 'html',
            filters_json TEXT NULL,
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            generated_by INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_rrun_code (report_code, created_at)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_feature_flag (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            description TEXT NULL,
            layer ENUM('experience','knowledge','intelligence','operations','governance') NOT NULL DEFAULT 'experience',
            status ENUM('planned','in_development','beta','released','retired') NOT NULL DEFAULT 'planned',
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            target_release VARCHAR(40) NULL,
            released_at DATE NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_flag_code (code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_queue_job (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type VARCHAR(60) NOT NULL,
            payload_json TEXT NULL,
            status ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            available_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_qjob_status (status, available_at)
        )" . $e);
    }

    public function down() {
        $this->drop($this->tables);
        $this->drop_columns('ha_sop_document', array('item_type', 'domain_id', 'reviewer_user_id', 'approver_user_id', 'ai_enabled', 'expires_at', 'tags'));
        $this->db->query("UPDATE ha_sop_version SET status = 'review' WHERE status IN ('internal_review','quality_review')");
        $this->db->query("UPDATE ha_sop_version SET status = 'draft' WHERE status = 'rejected'");
        $this->db->query("UPDATE ha_sop_version SET status = 'superseded' WHERE status = 'archived'");
        $this->db->query("ALTER TABLE ha_sop_version MODIFY status ENUM('draft','review','approved','published','superseded') NOT NULL DEFAULT 'draft'");
        $this->db->query("UPDATE ha_sop_document SET status = 'review' WHERE status IN ('internal_review','quality_review')");
        $this->db->query("UPDATE ha_sop_document SET status = 'draft' WHERE status = 'rejected'");
        $this->db->query("ALTER TABLE ha_sop_document MODIFY status ENUM('draft','review','approved','published','archived') NOT NULL DEFAULT 'draft'");
    }
}
