<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy - question engine, assessments, assignments, exams,
 * certificates and public verification. Plan sections 16, 17, 18, 19.
 */
class Migration_Create_assessment_tables extends Ha_migration {

    private $tables = array(
        'ha_certificate_verification', 'ha_certificate', 'ha_certificate_template',
        'ha_exam_registration', 'ha_exam_session',
        'ha_assignment_submission', 'ha_assignment',
        'ha_assessment_answer', 'ha_assessment_attempt', 'ha_assessment_question', 'ha_assessment',
        'ha_question_option', 'ha_question', 'ha_question_bank',
    );

    public function up() {
        $e = $this->engine;

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_question_bank (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            department_code VARCHAR(60) NULL,
            course_id INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_bank_code (code),
            KEY ix_ha_bank_course (course_id),
            CONSTRAINT fk_ha_bank_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_question (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            bank_id INT UNSIGNED NOT NULL,
            question_type ENUM('multiple_choice','true_false','multiple_response','matching','scenario','short_answer','essay') NOT NULL DEFAULT 'multiple_choice',
            body_en TEXT NOT NULL,
            body_ar TEXT NOT NULL,
            explanation_en TEXT NULL,
            explanation_ar TEXT NULL,
            media_path VARCHAR(500) NULL,
            marks DECIMAL(6,2) NOT NULL DEFAULT 1,
            difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
            requires_manual_grading TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_q_bank (bank_id),
            KEY ix_ha_q_type (question_type),
            CONSTRAINT fk_ha_q_bank FOREIGN KEY (bank_id) REFERENCES ha_question_bank (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_question_option (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id INT UNSIGNED NOT NULL,
            body_en VARCHAR(500) NOT NULL,
            body_ar VARCHAR(500) NOT NULL,
            match_key_en VARCHAR(255) NULL,
            match_key_ar VARCHAR(255) NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_ha_opt_question (question_id),
            CONSTRAINT fk_ha_opt_question FOREIGN KEY (question_id) REFERENCES ha_question (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_assessment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            instructions_en TEXT NULL,
            instructions_ar TEXT NULL,
            assessment_type ENUM('quiz','exam','survey','practical') NOT NULL DEFAULT 'quiz',
            course_id INT UNSIGNED NULL,
            program_id INT UNSIGNED NULL,
            bank_id INT UNSIGNED NULL,
            question_selection ENUM('fixed','random') NOT NULL DEFAULT 'fixed',
            random_question_count INT UNSIGNED NOT NULL DEFAULT 0,
            shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
            shuffle_options TINYINT(1) NOT NULL DEFAULT 1,
            time_limit_minutes INT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
            pass_percentage TINYINT UNSIGNED NOT NULL DEFAULT 70,
            show_correct_answers TINYINT(1) NOT NULL DEFAULT 1,
            available_from DATETIME NULL,
            available_until DATETIME NULL,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_assessment_code (code),
            KEY ix_ha_assess_course (course_id),
            KEY ix_ha_assess_status (status),
            CONSTRAINT fk_ha_assess_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_assess_bank FOREIGN KEY (bank_id) REFERENCES ha_question_bank (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_assessment_question (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessment_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            marks DECIMAL(6,2) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_aq (assessment_id, question_id),
            CONSTRAINT fk_ha_aq_assessment FOREIGN KEY (assessment_id) REFERENCES ha_assessment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_aq_question FOREIGN KEY (question_id) REFERENCES ha_question (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_assessment_attempt (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            enrollment_id INT UNSIGNED NULL,
            exam_session_id INT UNSIGNED NULL,
            attempt_no INT UNSIGNED NOT NULL DEFAULT 1,
            question_order LONGTEXT NULL,
            status ENUM('in_progress','submitted','graded','expired','abandoned') NOT NULL DEFAULT 'in_progress',
            score DECIMAL(7,2) NOT NULL DEFAULT 0,
            max_score DECIMAL(7,2) NOT NULL DEFAULT 0,
            percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            passed TINYINT(1) NULL,
            requires_manual_grading TINYINT(1) NOT NULL DEFAULT 0,
            graded_by INT UNSIGNED NULL,
            graded_at DATETIME NULL,
            started_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            submitted_at DATETIME NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_attempt (assessment_id, user_id, attempt_no),
            KEY ix_ha_attempt_user (user_id),
            KEY ix_ha_attempt_status (status),
            CONSTRAINT fk_ha_attempt_assessment FOREIGN KEY (assessment_id) REFERENCES ha_assessment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_attempt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_assessment_answer (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            selected_option_ids VARCHAR(500) NULL,
            answer_text LONGTEXT NULL,
            match_payload LONGTEXT NULL,
            is_correct TINYINT(1) NULL,
            awarded_marks DECIMAL(6,2) NOT NULL DEFAULT 0,
            max_marks DECIMAL(6,2) NOT NULL DEFAULT 0,
            grader_comment TEXT NULL,
            graded_by INT UNSIGNED NULL,
            graded_at DATETIME NULL,
            answered_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_answer (attempt_id, question_id),
            CONSTRAINT fk_ha_answer_attempt FOREIGN KEY (attempt_id) REFERENCES ha_assessment_attempt (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_answer_question FOREIGN KEY (question_id) REFERENCES ha_question (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_assignment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NULL,
            lesson_id INT UNSIGNED NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            brief_en LONGTEXT NULL,
            brief_ar LONGTEXT NULL,
            submission_type ENUM('file','text','practical','photo') NOT NULL DEFAULT 'text',
            requires_manager_verification TINYINT(1) NOT NULL DEFAULT 0,
            max_score DECIMAL(6,2) NOT NULL DEFAULT 100,
            pass_score DECIMAL(6,2) NOT NULL DEFAULT 60,
            due_days INT UNSIGNED NOT NULL DEFAULT 7,
            allowed_extensions VARCHAR(255) NOT NULL DEFAULT 'pdf,jpg,jpeg,png,docx,pptx,mp4',
            max_file_mb INT UNSIGNED NOT NULL DEFAULT 25,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_assignment_course (course_id),
            CONSTRAINT fk_ha_assignment_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_assignment_lesson FOREIGN KEY (lesson_id) REFERENCES ha_lesson (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_assignment_submission (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            enrollment_id INT UNSIGNED NULL,
            body TEXT NULL,
            file_path VARCHAR(500) NULL,
            file_mime VARCHAR(120) NULL,
            file_size INT UNSIGNED NULL,
            status ENUM('assigned','submitted','under_review','changes_requested','approved','rejected') NOT NULL DEFAULT 'assigned',
            score DECIMAL(6,2) NULL,
            passed TINYINT(1) NULL,
            reviewer_user_id INT UNSIGNED NULL,
            reviewer_comment TEXT NULL,
            manager_verified_by INT UNSIGNED NULL,
            manager_verified_at DATETIME NULL,
            due_at DATETIME NULL,
            submitted_at DATETIME NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_submission (assignment_id, user_id),
            KEY ix_ha_sub_status (status),
            CONSTRAINT fk_ha_sub_assignment FOREIGN KEY (assignment_id) REFERENCES ha_assignment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_sub_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_exam_session (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessment_id INT UNSIGNED NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            property_id INT UNSIGNED NULL,
            delivery ENUM('online','onsite','blended') NOT NULL DEFAULT 'online',
            location VARCHAR(255) NULL,
            proctor_user_id INT UNSIGNED NULL,
            capacity INT UNSIGNED NOT NULL DEFAULT 0,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            status ENUM('scheduled','open','closed','cancelled') NOT NULL DEFAULT 'scheduled',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_exam_assessment (assessment_id),
            KEY ix_ha_exam_start (starts_at),
            CONSTRAINT fk_ha_exam_assessment FOREIGN KEY (assessment_id) REFERENCES ha_assessment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_exam_property FOREIGN KEY (property_id) REFERENCES ha_property (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_exam_registration (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            attempt_id INT UNSIGNED NULL,
            status ENUM('registered','attended','absent','cancelled') NOT NULL DEFAULT 'registered',
            registered_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_exam_reg (session_id, user_id),
            CONSTRAINT fk_ha_reg_session FOREIGN KEY (session_id) REFERENCES ha_exam_session (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_reg_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_certificate_template (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            orientation ENUM('landscape','portrait') NOT NULL DEFAULT 'landscape',
            background_path VARCHAR(500) NULL,
            logo_path VARCHAR(500) NULL,
            signature_path VARCHAR(500) NULL,
            signatory_name_en VARCHAR(190) NULL,
            signatory_name_ar VARCHAR(190) NULL,
            signatory_title_en VARCHAR(190) NULL,
            signatory_title_ar VARCHAR(190) NULL,
            body_en LONGTEXT NULL,
            body_ar LONGTEXT NULL,
            validity_months INT UNSIGNED NOT NULL DEFAULT 0,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_cert_tpl_code (code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_certificate (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            certificate_no VARCHAR(40) NOT NULL,
            verification_code VARCHAR(64) NOT NULL,
            template_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NULL,
            program_id INT UNSIGNED NULL,
            path_id INT UNSIGNED NULL,
            enrollment_id INT UNSIGNED NULL,
            subject_title_en VARCHAR(190) NOT NULL,
            subject_title_ar VARCHAR(190) NOT NULL,
            recipient_name_en VARCHAR(190) NOT NULL,
            recipient_name_ar VARCHAR(190) NULL,
            instructor_name VARCHAR(190) NULL,
            final_score DECIMAL(5,2) NULL,
            issued_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            status ENUM('issued','expired','revoked') NOT NULL DEFAULT 'issued',
            revoked_reason VARCHAR(500) NULL,
            revoked_by INT UNSIGNED NULL,
            revoked_at DATETIME NULL,
            pdf_path VARCHAR(500) NULL,
            issued_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_cert_no (certificate_no),
            UNIQUE KEY uq_ha_cert_code (verification_code),
            KEY ix_ha_cert_user (user_id),
            KEY ix_ha_cert_status (status),
            KEY ix_ha_cert_expiry (expires_at),
            CONSTRAINT fk_ha_cert_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_cert_template FOREIGN KEY (template_id) REFERENCES ha_certificate_template (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_certificate_verification (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            certificate_id INT UNSIGNED NULL,
            submitted_code VARCHAR(64) NOT NULL,
            result ENUM('valid','expired','revoked','not_found') NOT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            verified_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_verify_cert (certificate_id),
            KEY ix_ha_verify_code (submitted_code),
            CONSTRAINT fk_ha_verify_cert FOREIGN KEY (certificate_id) REFERENCES ha_certificate (id) ON DELETE SET NULL
        )" . $e);
    }

    public function down() {
        $this->drop($this->tables);
    }
}
