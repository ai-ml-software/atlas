<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Signed_media_integrity extends Ha_migration {
    public function up() {
        if ($this->db->table_exists('ha_translation_value') && !$this->db->field_exists('signed_media_sha256','ha_translation_value')) {
            $this->db->query('ALTER TABLE ha_translation_value ADD signed_media_sha256 CHAR(64) NULL');
        }
    }
    public function down() { /* Preserve reviewed media integrity on code rollback. */ }
}
