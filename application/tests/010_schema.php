<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * The migrations must produce every table the plan's data model lists
 * (section 37 / PHASE 1), with the constraints that keep tenant data honest.
 */
class Test_schema extends Ha_testcase {

    /** Plan section 37 core table list, mapped onto the ha_ namespace. */
    public static $required_tables = array(
        'ha_role', 'ha_permission', 'ha_role_permission', 'ha_user_role', 'ha_profile',
        'ha_organization', 'ha_property', 'ha_department', 'ha_job_role',
        'ha_program', 'ha_program_translation', 'ha_program_course',
        'ha_course', 'ha_course_translation', 'ha_course_section', 'ha_course_prerequisite',
        'ha_course_outcome', 'ha_course_faq',
        'ha_lesson', 'ha_lesson_translation', 'ha_lesson_attachment',
        'ha_enrollment', 'ha_lesson_progress',
        'ha_question_bank', 'ha_question', 'ha_question_option',
        'ha_assessment', 'ha_assessment_question', 'ha_assessment_attempt', 'ha_assessment_answer',
        'ha_assignment', 'ha_assignment_submission',
        'ha_exam_session', 'ha_exam_registration',
        'ha_certificate', 'ha_certificate_template', 'ha_certificate_verification',
        'ha_learning_path', 'ha_path_step', 'ha_path_step_item', 'ha_path_enrollment',
        'ha_skill', 'ha_course_skill', 'ha_person_skill',
        'ha_sop_category', 'ha_sop_document', 'ha_sop_version', 'ha_sop_version_translation',
        'ha_sop_attachment', 'ha_sop_related', 'ha_sop_acknowledgement',
        'ha_checklist', 'ha_checklist_item', 'ha_checklist_run', 'ha_checklist_run_item',
        'ha_training_assignment', 'ha_training_item', 'ha_training_target', 'ha_training_recipient',
        'ha_attendance_session', 'ha_attendance_record',
        'ha_notification', 'ha_notification_preference',
        'ha_article', 'ha_article_translation', 'ha_author', 'ha_tag', 'ha_article_tag',
        'ha_category', 'ha_category_translation',
        'ha_page', 'ha_page_translation', 'ha_topic', 'ha_topic_course',
        'ha_faq', 'ha_testimonial', 'ha_media', 'ha_menu', 'ha_menu_item',
        'ha_seo_metadata', 'ha_redirect', 'ha_lead',
        'ha_competitor', 'ha_competitor_capability', 'ha_competitor_page',
        'ha_competitor_keyword', 'ha_competitor_observation',
        'ha_audit_log',
    );

    private function tables() {
        $rows = $this->db->query('SHOW TABLES')->result_array();
        $out = array();
        foreach ($rows as $r) {
            $out[] = strtolower(current($r));
        }
        return $out;
    }

    public function test_every_planned_table_exists() {
        $present = $this->tables();
        $missing = array();
        foreach (self::$required_tables as $t) {
            if (!in_array($t, $present, true)) {
                $missing[] = $t;
            }
        }
        $this->assertEmpty($missing, 'Missing tables: ' . implode(', ', $missing));
        $this->assertGreaterThanOrEqual(80, count(self::$required_tables), 'Table list should cover the whole data model');
    }

    public function test_tables_use_innodb_and_utf8mb4() {
        $rows = $this->db->query(
            "SELECT table_name, engine, table_collation FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name LIKE 'ha\\_%'")->result_array();
        $this->assertNotEmpty($rows, 'Expected academy tables to exist');
        $bad = array();
        foreach ($rows as $r) {
            $name = isset($r['table_name']) ? $r['table_name'] : $r['TABLE_NAME'];
            $engine = isset($r['engine']) ? $r['engine'] : $r['ENGINE'];
            $collation = isset($r['table_collation']) ? $r['table_collation'] : $r['TABLE_COLLATION'];
            if (strtolower($engine) !== 'innodb' || strpos($collation, 'utf8mb4') !== 0) {
                $bad[] = $name . ' (' . $engine . '/' . $collation . ')';
            }
        }
        $this->assertEmpty($bad, 'Tables not InnoDB/utf8mb4: ' . implode(', ', $bad));
    }

    public function test_tenant_tables_have_foreign_keys() {
        $rows = $this->db->query(
            "SELECT table_name, column_name, referenced_table_name
             FROM information_schema.key_column_usage
             WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL")->result_array();
        $map = array();
        foreach ($rows as $r) {
            $t = strtolower(isset($r['table_name']) ? $r['table_name'] : $r['TABLE_NAME']);
            $c = strtolower(isset($r['column_name']) ? $r['column_name'] : $r['COLUMN_NAME']);
            $map[$t . '.' . $c] = true;
        }
        $expected = array(
            'ha_property.organization_id',
            'ha_department.organization_id',
            'ha_profile.user_id',
            'ha_enrollment.user_id',
            'ha_enrollment.course_id',
            'ha_lesson.course_id',
            'ha_sop_version.sop_id',
            'ha_sop_acknowledgement.version_id',
            'ha_certificate.user_id',
            'ha_training_recipient.assignment_id',
        );
        $missing = array();
        foreach ($expected as $key) {
            if (!isset($map[$key])) {
                $missing[] = $key;
            }
        }
        $this->assertEmpty($missing, 'Missing foreign keys: ' . implode(', ', $missing));
    }

    public function test_bilingual_content_tables_have_both_locales() {
        // Every *_translation table must key on (parent, locale) so English and
        // Arabic are parallel rows rather than one row with two columns.
        $translation_tables = array(
            'ha_course_translation' => 'course_id',
            'ha_lesson_translation' => 'lesson_id',
            'ha_program_translation' => 'program_id',
            'ha_article_translation' => 'article_id',
            'ha_page_translation' => 'page_id',
            'ha_sop_version_translation' => 'version_id',
            'ha_category_translation' => 'category_id',
        );
        foreach ($translation_tables as $table => $parent) {
            $fields = $this->db->list_fields($table);
            $this->assertContains('locale', $fields, $table . ' must have a locale column');
            $this->assertContains($parent, $fields, $table . ' must reference ' . $parent);
        }
    }

    public function test_certificate_verification_code_is_unique() {
        $rows = $this->db->query("SHOW INDEX FROM ha_certificate WHERE Key_name = 'uq_ha_cert_code'")->result_array();
        $this->assertNotEmpty($rows, 'Certificate verification codes must be unique');
        $this->assertEquals(0, (int) $rows[0]['Non_unique'], 'uq_ha_cert_code must be a unique index');
    }

    public function test_enrollment_cannot_duplicate_for_same_course() {
        $rows = $this->db->query("SHOW INDEX FROM ha_enrollment WHERE Key_name = 'uq_ha_enrollment'")->result_array();
        $this->assertNotEmpty($rows, 'A learner must not be enrolled twice on one course');
        $this->assertEquals(0, (int) $rows[0]['Non_unique'], 'uq_ha_enrollment must be unique');
    }
}
