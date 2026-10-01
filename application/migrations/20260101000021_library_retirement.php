<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Retain retired learner-facing records and their historical references. */
class Migration_Library_retirement extends Ha_migration {
    public function up() {
        foreach (array('lesson','section','question','ha_question_option') as $table) {
            if ($this->db->table_exists($table) && !$this->db->field_exists('ha_retired_at', $table)) {
                $this->db->query('ALTER TABLE `' . $table . '` ADD `ha_retired_at` DATETIME NULL DEFAULT NULL');
            }
        }
    }
    public function down() {
        // Code rollback preserves retirement and historical records; see deployment instructions.
    }
}
