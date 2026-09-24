<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy - SOP hub, version control, acknowledgement, checklists,
 * training assignment engine, attendance, notifications and audit log.
 * Plan sections 11, 12, 13, 14, 15, 21, 22, 49.
 */
class Migration_Create_operations_tables extends Ha_migration {

    private $tables = array(
        'ha_audit_log',
        'ha_notification_preference', 'ha_notification',
        'ha_attendance_record', 'ha_attendance_session',
        'ha_training_recipient', 'ha_training_target', 'ha_training_item', 'ha_training_assignment',
        'ha_checklist_run_item', 'ha_checklist_run', 'ha_checklist_item', 'ha_checklist',
        'ha_sop_acknowledgement', 'ha_sop_related', 'ha_sop_attachment',
        'ha_sop_version_translation', 'ha_sop_version', 'ha_sop_document', 'ha_sop_category',
    );

    public function up() {
        $e = $this->engine;

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_sop_category (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_sopcat_code (code),
            UNIQUE KEY uq_ha_sopcat_slug_en (slug_en),
            UNIQUE KEY uq_ha_sopcat_slug_ar (slug_ar)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_sop_document (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            category_id INT UNSIGNED NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_code VARCHAR(60) NULL,
            job_role_id INT UNSIGNED NULL,
            owner_user_id INT UNSIGNED NULL,
            current_version_id INT UNSIGNED NULL,
            visibility ENUM('private','organization','public') NOT NULL DEFAULT 'organization',
            is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
            requires_acknowledgement TINYINT(1) NOT NULL DEFAULT 1,
            review_interval_months INT UNSIGNED NOT NULL DEFAULT 12,
            status ENUM('draft','review','approved','published','archived') NOT NULL DEFAULT 'draft',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_sop_code (code),
            UNIQUE KEY uq_ha_sop_slug_en (slug_en),
            UNIQUE KEY uq_ha_sop_slug_ar (slug_ar),
            KEY ix_ha_sop_org (organization_id),
            KEY ix_ha_sop_property (property_id),
            KEY ix_ha_sop_status (status),
            KEY ix_ha_sop_visibility (visibility),
            CONSTRAINT fk_ha_sop_cat FOREIGN KEY (category_id) REFERENCES ha_sop_category (id) ON DELETE SET NULL,
            CONSTRAINT fk_ha_sop_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_sop_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_sop_version (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sop_id INT UNSIGNED NOT NULL,
            version_label VARCHAR(20) NOT NULL,
            version_major INT UNSIGNED NOT NULL DEFAULT 1,
            version_minor INT UNSIGNED NOT NULL DEFAULT 0,
            change_summary VARCHAR(500) NULL,
            author_user_id INT UNSIGNED NULL,
            approver_user_id INT UNSIGNED NULL,
            status ENUM('draft','review','approved','published','superseded') NOT NULL DEFAULT 'draft',
            effective_date DATE NULL,
            review_date DATE NULL,
            approved_at DATETIME NULL,
            published_at DATETIME NULL,
            superseded_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_sopver (sop_id, version_label),
            KEY ix_ha_sopver_status (status),
            KEY ix_ha_sopver_review (review_date),
            CONSTRAINT fk_ha_sopver_sop FOREIGN KEY (sop_id) REFERENCES ha_sop_document (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_sop_version_translation (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            version_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            title VARCHAR(190) NOT NULL,
            purpose TEXT NULL,
            scope TEXT NULL,
            responsibilities TEXT NULL,
            required_tools TEXT NULL,
            `procedure` LONGTEXT NULL,
            checklist LONGTEXT NULL,
            safety_notes TEXT NULL,
            quality_standard TEXT NULL,
            escalation TEXT NULL,
            related_documents TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_sopver_tr (version_id, locale),
            CONSTRAINT fk_ha_sopver_tr FOREIGN KEY (version_id) REFERENCES ha_sop_version (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_sop_attachment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            version_id INT UNSIGNED NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size INT UNSIGNED NOT NULL DEFAULT 0,
            is_private TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_sopatt_version (version_id),
            CONSTRAINT fk_ha_sopatt_version FOREIGN KEY (version_id) REFERENCES ha_sop_version (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_sop_related (
            sop_id INT UNSIGNED NOT NULL,
            related_sop_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (sop_id, related_sop_id),
            CONSTRAINT fk_ha_sopr_sop FOREIGN KEY (sop_id) REFERENCES ha_sop_document (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_sopr_target FOREIGN KEY (related_sop_id) REFERENCES ha_sop_document (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_sop_acknowledgement (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sop_id INT UNSIGNED NOT NULL,
            version_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            training_assignment_id INT UNSIGNED NULL,
            status ENUM('required','acknowledged','superseded') NOT NULL DEFAULT 'required',
            due_at DATETIME NULL,
            acknowledged_at DATETIME NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_sopack (version_id, user_id),
            KEY ix_ha_sopack_user (user_id),
            KEY ix_ha_sopack_sop (sop_id),
            KEY ix_ha_sopack_status (status),
            CONSTRAINT fk_ha_sopack_version FOREIGN KEY (version_id) REFERENCES ha_sop_version (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_sopack_sop FOREIGN KEY (sop_id) REFERENCES ha_sop_document (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_sopack_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_checklist (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_code VARCHAR(60) NULL,
            sop_id INT UNSIGNED NULL,
            recurrence ENUM('none','daily','weekly','monthly','per_shift') NOT NULL DEFAULT 'none',
            requires_photo TINYINT(1) NOT NULL DEFAULT 0,
            requires_signature TINYINT(1) NOT NULL DEFAULT 0,
            escalate_after_hours INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_checklist_code (code),
            KEY ix_ha_checklist_property (property_id),
            CONSTRAINT fk_ha_checklist_sop FOREIGN KEY (sop_id) REFERENCES ha_sop_document (id) ON DELETE SET NULL,
            CONSTRAINT fk_ha_checklist_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_checklist_item (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            checklist_id INT UNSIGNED NOT NULL,
            label_en VARCHAR(255) NOT NULL,
            label_ar VARCHAR(255) NOT NULL,
            help_en VARCHAR(500) NULL,
            help_ar VARCHAR(500) NULL,
            is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
            requires_photo TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_ha_cli_checklist (checklist_id),
            CONSTRAINT fk_ha_cli_checklist FOREIGN KEY (checklist_id) REFERENCES ha_checklist (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_checklist_run (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            checklist_id INT UNSIGNED NOT NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            assigned_user_id INT UNSIGNED NULL,
            performed_by INT UNSIGNED NULL,
            status ENUM('pending','in_progress','completed','overdue','escalated') NOT NULL DEFAULT 'pending',
            due_at DATETIME NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            notes TEXT NULL,
            signature_path VARCHAR(500) NULL,
            items_total INT UNSIGNED NOT NULL DEFAULT 0,
            items_done INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_run_checklist (checklist_id),
            KEY ix_ha_run_status (status),
            KEY ix_ha_run_due (due_at),
            CONSTRAINT fk_ha_run_checklist FOREIGN KEY (checklist_id) REFERENCES ha_checklist (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_checklist_run_item (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            is_done TINYINT(1) NOT NULL DEFAULT 0,
            note VARCHAR(500) NULL,
            photo_path VARCHAR(500) NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_run_item (run_id, item_id),
            CONSTRAINT fk_ha_runitem_run FOREIGN KEY (run_id) REFERENCES ha_checklist_run (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_runitem_item FOREIGN KEY (item_id) REFERENCES ha_checklist_item (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_training_assignment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            instructions_en TEXT NULL,
            instructions_ar TEXT NULL,
            organization_id INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
            requires_certificate TINYINT(1) NOT NULL DEFAULT 0,
            pass_percentage TINYINT UNSIGNED NOT NULL DEFAULT 70,
            starts_at DATETIME NULL,
            due_at DATETIME NULL,
            reminder_days_before INT UNSIGNED NOT NULL DEFAULT 3,
            escalate_days_after INT UNSIGNED NOT NULL DEFAULT 3,
            status ENUM('draft','scheduled','active','completed','cancelled') NOT NULL DEFAULT 'draft',
            dispatched_at DATETIME NULL,
            recipients_total INT UNSIGNED NOT NULL DEFAULT 0,
            recipients_completed INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_ta_org (organization_id),
            KEY ix_ha_ta_status (status),
            KEY ix_ha_ta_due (due_at),
            CONSTRAINT fk_ha_ta_org FOREIGN KEY (organization_id) REFERENCES ha_organization (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_training_item (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            item_type ENUM('course','program','path','sop','assessment','checklist') NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_ti (assignment_id, item_type, item_id),
            CONSTRAINT fk_ha_ti_assignment FOREIGN KEY (assignment_id) REFERENCES ha_training_assignment (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_training_target (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            target_type ENUM('user','department','property','job_role','organization') NOT NULL,
            target_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_tt (assignment_id, target_type, target_id),
            CONSTRAINT fk_ha_tt_assignment FOREIGN KEY (assignment_id) REFERENCES ha_training_assignment (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_training_recipient (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            status ENUM('assigned','in_progress','completed','overdue','waived') NOT NULL DEFAULT 'assigned',
            progress_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            due_at DATETIME NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            last_reminded_at DATETIME NULL,
            escalated_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_tr (assignment_id, user_id),
            KEY ix_ha_tr_user (user_id),
            KEY ix_ha_tr_status (status),
            KEY ix_ha_tr_due (due_at),
            CONSTRAINT fk_ha_tr_assignment FOREIGN KEY (assignment_id) REFERENCES ha_training_assignment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_tr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_attendance_session (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            course_id INT UNSIGNED NULL,
            program_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            department_id INT UNSIGNED NULL,
            instructor_user_id INT UNSIGNED NULL,
            delivery ENUM('classroom','online','blended','on_the_job') NOT NULL DEFAULT 'classroom',
            location VARCHAR(255) NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            capacity INT UNSIGNED NOT NULL DEFAULT 0,
            counts_towards_completion TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('scheduled','running','completed','cancelled') NOT NULL DEFAULT 'scheduled',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_att_start (starts_at),
            KEY ix_ha_att_course (course_id),
            CONSTRAINT fk_ha_att_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE SET NULL,
            CONSTRAINT fk_ha_att_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_attendance_record (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            status ENUM('registered','present','absent','late','excused') NOT NULL DEFAULT 'registered',
            minutes_attended INT UNSIGNED NOT NULL DEFAULT 0,
            note VARCHAR(500) NULL,
            recorded_by INT UNSIGNED NULL,
            recorded_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_attrec (session_id, user_id),
            KEY ix_ha_attrec_user (user_id),
            CONSTRAINT fk_ha_attrec_session FOREIGN KEY (session_id) REFERENCES ha_attendance_session (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_attrec_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_notification (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            category ENUM('training','certificates','sop','assessment','system') NOT NULL DEFAULT 'system',
            event_code VARCHAR(80) NOT NULL,
            title_en VARCHAR(255) NOT NULL,
            title_ar VARCHAR(255) NOT NULL,
            body_en TEXT NULL,
            body_ar TEXT NULL,
            action_url VARCHAR(500) NULL,
            related_type VARCHAR(60) NULL,
            related_id INT UNSIGNED NULL,
            channel ENUM('in_app','email','sms','whatsapp') NOT NULL DEFAULT 'in_app',
            delivery_status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
            delivery_error VARCHAR(500) NULL,
            read_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_notif_user (user_id, read_at),
            KEY ix_ha_notif_category (category),
            KEY ix_ha_notif_status (delivery_status),
            CONSTRAINT fk_ha_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_notification_preference (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            event_code VARCHAR(80) NOT NULL,
            in_app TINYINT(1) NOT NULL DEFAULT 1,
            email TINYINT(1) NOT NULL DEFAULT 1,
            sms TINYINT(1) NOT NULL DEFAULT 0,
            whatsapp TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_notifpref (user_id, event_code),
            CONSTRAINT fk_ha_notifpref_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            actor_name VARCHAR(190) NULL,
            action VARCHAR(60) NOT NULL,
            entity_type VARCHAR(80) NULL,
            entity_id INT UNSIGNED NULL,
            description VARCHAR(500) NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            organization_id INT UNSIGNED NULL,
            property_id INT UNSIGNED NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_audit_user (user_id),
            KEY ix_ha_audit_entity (entity_type, entity_id),
            KEY ix_ha_audit_action (action),
            KEY ix_ha_audit_created (created_at)
        )" . $e);
    }

    public function down() {
        $this->drop($this->tables);
    }
}
