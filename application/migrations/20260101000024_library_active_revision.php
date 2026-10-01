<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Library_active_revision extends Ha_migration {
    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_library_active_revision (
            course_id INT UNSIGNED NOT NULL, signature CHAR(64) NOT NULL, released_at DATETIME NOT NULL,
            PRIMARY KEY(course_id)
        ) " . $this->engine);
    }
    public function down() { /* Keep released content identities and history. */ }
}
