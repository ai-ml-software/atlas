<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Translation_surface_review extends Ha_migration {
    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_translation_qa (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT, locale VARCHAR(64) NOT NULL, scope VARCHAR(190) NOT NULL,
            fingerprint CHAR(64) NOT NULL, reviewer VARCHAR(190) NOT NULL, checklist_json TEXT NOT NULL,
            reviewed_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE KEY ux_translation_qa(locale,scope)
        ) " . $this->engine);
    }
    public function down() { /* Keep review history during code rollback. */ }
}
