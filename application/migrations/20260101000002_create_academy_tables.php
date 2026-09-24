<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hospitality Academy - programs, courses, lessons, enrollment and progress.
 * Plan sections 6, 7, 8, 9, 10, 20, 37.
 */
class Migration_Create_academy_tables extends Ha_migration {

    private $tables = array(
        'ha_path_step_item', 'ha_path_step', 'ha_path_enrollment', 'ha_learning_path',
        'ha_person_skill', 'ha_course_skill', 'ha_skill',
        'ha_lesson_progress', 'ha_enrollment',
        'ha_lesson_attachment', 'ha_lesson_translation', 'ha_lesson', 'ha_course_section',
        'ha_course_prerequisite', 'ha_course_outcome', 'ha_course_faq', 'ha_course_translation', 'ha_course',
        'ha_program_course', 'ha_program_translation', 'ha_program',
        'ha_category_translation', 'ha_category',
    );

    public function up() {
        $e = $this->engine;

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_category (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id INT UNSIGNED NULL,
            code VARCHAR(80) NOT NULL,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            icon VARCHAR(80) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_cat_code (code),
            UNIQUE KEY uq_ha_cat_slug_en (slug_en),
            UNIQUE KEY uq_ha_cat_slug_ar (slug_ar),
            KEY ix_ha_cat_parent (parent_id)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_category_translation (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_cat_tr (category_id, locale),
            CONSTRAINT fk_ha_cat_tr FOREIGN KEY (category_id) REFERENCES ha_category (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_program (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            category_id INT UNSIGNED NULL,
            level ENUM('foundation','intermediate','advanced','leadership') NOT NULL DEFAULT 'foundation',
            duration_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
            thumbnail VARCHAR(255) NULL,
            certificate_template_id INT UNSIGNED NULL,
            final_assessment_id INT UNSIGNED NULL,
            completion_rule ENUM('all_courses','all_courses_and_exam','percentage') NOT NULL DEFAULT 'all_courses',
            completion_percentage TINYINT UNSIGNED NOT NULL DEFAULT 100,
            status ENUM('draft','review','approved','published','archived') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_prog_code (code),
            UNIQUE KEY uq_ha_prog_slug_en (slug_en),
            UNIQUE KEY uq_ha_prog_slug_ar (slug_ar),
            KEY ix_ha_prog_status (status),
            CONSTRAINT fk_ha_prog_cat FOREIGN KEY (category_id) REFERENCES ha_category (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_program_translation (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            title VARCHAR(190) NOT NULL,
            short_description VARCHAR(500) NULL,
            description LONGTEXT NULL,
            outcomes LONGTEXT NULL,
            prerequisites LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_prog_tr (program_id, locale),
            CONSTRAINT fk_ha_prog_tr FOREIGN KEY (program_id) REFERENCES ha_program (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_course (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            category_id INT UNSIGNED NULL,
            department_code VARCHAR(60) NULL,
            instructor_user_id INT UNSIGNED NULL,
            level ENUM('foundation','intermediate','advanced','leadership') NOT NULL DEFAULT 'foundation',
            duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
            thumbnail VARCHAR(255) NULL,
            preview_video VARCHAR(255) NULL,
            is_free TINYINT(1) NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'SAR',
            certificate_eligible TINYINT(1) NOT NULL DEFAULT 1,
            certificate_template_id INT UNSIGNED NULL,
            pass_percentage TINYINT UNSIGNED NOT NULL DEFAULT 70,
            status ENUM('draft','review','approved','published','archived') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            approved_by INT UNSIGNED NULL,
            approved_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
            rating_count INT UNSIGNED NOT NULL DEFAULT 0,
            enrollment_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_course_code (code),
            UNIQUE KEY uq_ha_course_slug_en (slug_en),
            UNIQUE KEY uq_ha_course_slug_ar (slug_ar),
            KEY ix_ha_course_status (status),
            KEY ix_ha_course_cat (category_id),
            KEY ix_ha_course_instructor (instructor_user_id),
            CONSTRAINT fk_ha_course_cat FOREIGN KEY (category_id) REFERENCES ha_category (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_course_translation (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            title VARCHAR(190) NOT NULL,
            short_description VARCHAR(500) NULL,
            description LONGTEXT NULL,
            requirements LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_course_tr (course_id, locale),
            CONSTRAINT fk_ha_course_tr FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_course_outcome (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            body VARCHAR(500) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_ha_outcome_course (course_id, locale),
            CONSTRAINT fk_ha_outcome_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_course_faq (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            question VARCHAR(500) NOT NULL,
            answer TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_ha_course_faq (course_id, locale),
            CONSTRAINT fk_ha_course_faq FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_course_prerequisite (
            course_id INT UNSIGNED NOT NULL,
            prerequisite_course_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (course_id, prerequisite_course_id),
            CONSTRAINT fk_ha_prereq_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_prereq_target FOREIGN KEY (prerequisite_course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_program_course (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            program_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_prog_course (program_id, course_id),
            CONSTRAINT fk_ha_pc_program FOREIGN KEY (program_id) REFERENCES ha_program (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_pc_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_course_section (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_ha_section_course (course_id),
            CONSTRAINT fk_ha_section_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_lesson (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id INT UNSIGNED NOT NULL,
            section_id INT UNSIGNED NULL,
            lesson_type ENUM('video','audio','text','pdf','presentation','external','checklist','sop','interactive') NOT NULL DEFAULT 'text',
            video_source ENUM('upload','youtube','vimeo','url') NULL,
            video_url VARCHAR(500) NULL,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            sop_document_id INT UNSIGNED NULL,
            checklist_id INT UNSIGNED NULL,
            external_url VARCHAR(500) NULL,
            is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
            is_preview TINYINT(1) NOT NULL DEFAULT 0,
            completion_rule ENUM('open','watch_percentage','quiz','acknowledge','assignment') NOT NULL DEFAULT 'open',
            required_watch_percentage TINYINT UNSIGNED NOT NULL DEFAULT 90,
            assessment_id INT UNSIGNED NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_lesson_course (course_id),
            KEY ix_ha_lesson_section (section_id),
            CONSTRAINT fk_ha_lesson_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_lesson_section FOREIGN KEY (section_id) REFERENCES ha_course_section (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_lesson_translation (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id INT UNSIGNED NOT NULL,
            locale ENUM('en','ar') NOT NULL,
            title VARCHAR(190) NOT NULL,
            objective VARCHAR(500) NULL,
            body LONGTEXT NULL,
            transcript LONGTEXT NULL,
            captions_url VARCHAR(500) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_lesson_tr (lesson_id, locale),
            CONSTRAINT fk_ha_lesson_tr FOREIGN KEY (lesson_id) REFERENCES ha_lesson (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_lesson_attachment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id INT UNSIGNED NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size INT UNSIGNED NOT NULL DEFAULT 0,
            is_downloadable TINYINT(1) NOT NULL DEFAULT 1,
            is_private TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ix_ha_attach_lesson (lesson_id),
            CONSTRAINT fk_ha_attach_lesson FOREIGN KEY (lesson_id) REFERENCES ha_lesson (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_enrollment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            program_id INT UNSIGNED NULL,
            training_assignment_id INT UNSIGNED NULL,
            source ENUM('self','assigned','program','path','purchase','import') NOT NULL DEFAULT 'self',
            status ENUM('active','completed','expired','cancelled') NOT NULL DEFAULT 'active',
            progress_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            lessons_total INT UNSIGNED NOT NULL DEFAULT 0,
            lessons_completed INT UNSIGNED NOT NULL DEFAULT 0,
            time_spent_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            final_score DECIMAL(5,2) NULL,
            passed TINYINT(1) NULL,
            due_at DATETIME NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_enrollment (user_id, course_id),
            KEY ix_ha_enroll_course (course_id),
            KEY ix_ha_enroll_status (status),
            KEY ix_ha_enroll_due (due_at),
            CONSTRAINT fk_ha_enroll_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_enroll_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_lesson_progress (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            enrollment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            lesson_id INT UNSIGNED NOT NULL,
            status ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
            watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            watched_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            last_position_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            acknowledged_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_lesson_progress (enrollment_id, lesson_id),
            KEY ix_ha_lp_user (user_id),
            KEY ix_ha_lp_lesson (lesson_id),
            CONSTRAINT fk_ha_lp_enroll FOREIGN KEY (enrollment_id) REFERENCES ha_enrollment (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_lp_lesson FOREIGN KEY (lesson_id) REFERENCES ha_lesson (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_skill (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            name_en VARCHAR(190) NOT NULL,
            name_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            department_code VARCHAR(60) NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_skill_code (code),
            KEY ix_ha_skill_dept (department_code)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_course_skill (
            course_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NOT NULL,
            awards_level ENUM('learning','basic','competent','advanced','certified') NOT NULL DEFAULT 'competent',
            PRIMARY KEY (course_id, skill_id),
            CONSTRAINT fk_ha_cs_course FOREIGN KEY (course_id) REFERENCES ha_course (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_cs_skill FOREIGN KEY (skill_id) REFERENCES ha_skill (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_person_skill (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            skill_id INT UNSIGNED NOT NULL,
            level ENUM('not_started','learning','basic','competent','advanced','certified') NOT NULL DEFAULT 'not_started',
            source ENUM('course','assessment','manager','import') NOT NULL DEFAULT 'course',
            evidence_course_id INT UNSIGNED NULL,
            achieved_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_person_skill (user_id, skill_id),
            KEY ix_ha_ps_skill (skill_id),
            CONSTRAINT fk_ha_ps_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_ps_skill FOREIGN KEY (skill_id) REFERENCES ha_skill (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_learning_path (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(80) NOT NULL,
            slug_en VARCHAR(190) NOT NULL,
            slug_ar VARCHAR(190) NOT NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            summary_en VARCHAR(500) NULL,
            summary_ar VARCHAR(500) NULL,
            description_en LONGTEXT NULL,
            description_ar LONGTEXT NULL,
            department_code VARCHAR(60) NULL,
            thumbnail VARCHAR(255) NULL,
            status ENUM('draft','review','approved','published','archived') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_path_code (code),
            UNIQUE KEY uq_ha_path_slug_en (slug_en),
            UNIQUE KEY uq_ha_path_slug_ar (slug_ar),
            KEY ix_ha_path_status (status)
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_path_step (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            path_id INT UNSIGNED NOT NULL,
            job_role_id INT UNSIGNED NULL,
            title_en VARCHAR(190) NOT NULL,
            title_ar VARCHAR(190) NOT NULL,
            description_en TEXT NULL,
            description_ar TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_ha_step_path (path_id),
            CONSTRAINT fk_ha_step_path FOREIGN KEY (path_id) REFERENCES ha_learning_path (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_step_job FOREIGN KEY (job_role_id) REFERENCES ha_job_role (id) ON DELETE SET NULL
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_path_step_item (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            step_id INT UNSIGNED NOT NULL,
            item_type ENUM('course','program','sop','assessment','skill') NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY ix_ha_step_item (step_id, item_type),
            CONSTRAINT fk_ha_step_item FOREIGN KEY (step_id) REFERENCES ha_path_step (id) ON DELETE CASCADE
        )" . $e);

        $this->db->query("CREATE TABLE IF NOT EXISTS ha_path_enrollment (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            path_id INT UNSIGNED NOT NULL,
            current_step_id INT UNSIGNED NULL,
            status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
            progress_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ha_path_enroll (user_id, path_id),
            CONSTRAINT fk_ha_pe_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ha_pe_path FOREIGN KEY (path_id) REFERENCES ha_learning_path (id) ON DELETE CASCADE
        )" . $e);
    }

    public function down() {
        $this->drop($this->tables);
    }
}
