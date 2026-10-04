<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Publisher_upgrade extends Ha_migration {
    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_studio_draft (object_type VARCHAR(30) NOT NULL, object_id INT UNSIGNED NOT NULL DEFAULT 0, version INT UNSIGNED NOT NULL DEFAULT 1, base_hash CHAR(64) NOT NULL, payload_json LONGTEXT NOT NULL, updated_by INT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(object_type,object_id))" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_studio_revision (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, object_type VARCHAR(30) NOT NULL, object_id INT UNSIGNED NOT NULL, payload_json LONGTEXT NOT NULL, actor_id INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, KEY object_lookup(object_type,object_id,id))" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_site_studio (id INT UNSIGNED PRIMARY KEY, payload_json LONGTEXT NOT NULL, updated_at DATETIME NOT NULL)" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_document_job (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, draft_id INT UNSIGNED NOT NULL, source_path VARCHAR(500) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'queued', progress INT NOT NULL DEFAULT 0, cancel_requested TINYINT NOT NULL DEFAULT 0, error TEXT NULL, attempts INT NOT NULL DEFAULT 0, pages_json MEDIUMTEXT NULL, worker_token CHAR(32) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, KEY job_queue(status,id), KEY job_draft(draft_id))" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_publisher_approval (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, object_type VARCHAR(30) NOT NULL, object_id INT UNSIGNED NOT NULL, operation VARCHAR(30) NOT NULL, requester_id INT UNSIGNED NOT NULL, client_id VARCHAR(190) NOT NULL, content_hash CHAR(64) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'pending', reviewed_by INT UNSIGNED NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, KEY approval_status(status,id))" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_publisher_request (actor_id INT UNSIGNED NOT NULL, client_id VARCHAR(190) NOT NULL, request_key VARCHAR(100) NOT NULL, request_hash CHAR(64) NOT NULL, response_json LONGTEXT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(actor_id,client_id,request_key))" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_oauth_bridge (code_hash CHAR(64) PRIMARY KEY, user_id INT UNSIGNED NOT NULL, binding_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL)");
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_oauth_store (model VARCHAR(60) NOT NULL, id VARCHAR(190) NOT NULL, payload LONGTEXT NOT NULL, expires_at BIGINT NULL, consumed_at BIGINT NULL, grant_id VARCHAR(190) NULL, user_code VARCHAR(190) NULL, uid VARCHAR(190) NULL, PRIMARY KEY(model,id), KEY oauth_grant(grant_id), KEY oauth_uid(uid), KEY oauth_user_code(user_code))" . $this->engine);
        if (!$this->db->field_exists('studio_key', 'ha_page_section')) {
            $this->db->query('ALTER TABLE ha_page_section ADD studio_key VARCHAR(64) NULL');
            $this->db->query("UPDATE ha_page_section SET studio_key=CONCAT('section-',id) WHERE studio_key IS NULL");
        }
    }
    public function down() {
        $this->drop_columns('ha_page_section', array('studio_key'));
        $this->drop(array('ha_oauth_store', 'ha_oauth_bridge', 'ha_publisher_request', 'ha_publisher_approval', 'ha_document_job', 'ha_site_studio', 'ha_studio_revision', 'ha_studio_draft'));
    }
}
