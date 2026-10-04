<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Publisher: chosen featured media on a draft, and scheduled retry time for extraction jobs. */
class Migration_Publisher_completion extends Ha_migration {
    public function up() {
        if (!$this->db->field_exists('media_id', 'ha_publisher_draft')) { $this->db->query('ALTER TABLE ha_publisher_draft ADD media_id INT UNSIGNED NULL'); }
        if (!$this->db->field_exists('next_attempt_at', 'ha_document_job')) { $this->db->query('ALTER TABLE ha_document_job ADD next_attempt_at DATETIME NULL'); }
    }
    public function down() {
        if ($this->db->field_exists('media_id', 'ha_publisher_draft')) { $this->db->query('ALTER TABLE ha_publisher_draft DROP COLUMN media_id'); }
        if ($this->db->field_exists('next_attempt_at', 'ha_document_job')) { $this->db->query('ALTER TABLE ha_document_job DROP COLUMN next_attempt_at'); }
    }
}
