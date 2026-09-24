<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Attribution columns for the media library.
 *
 * Every photograph on the public site comes from Wikimedia Commons under a
 * named licence. Several of those licences (CC BY, CC BY-SA) require credit,
 * so the credit has to travel with the file rather than live in someone's
 * notes. These columns are what the public credits page is built from, and
 * what makes it possible to prove the site has the right to show an image.
 */
class Migration_Add_media_attribution extends Ha_migration {

    public function up() {
        $columns = $this->db->list_fields('ha_media');

        $add = array(
            'subject'        => "VARCHAR(120) NULL AFTER alt_ar",
            'source'         => "VARCHAR(60) NOT NULL DEFAULT 'upload' AFTER subject",
            'source_page'    => "VARCHAR(500) NULL AFTER source",
            'source_file'    => "VARCHAR(500) NULL AFTER source_page",
            'author'         => "VARCHAR(255) NULL AFTER source_file",
            'license'        => "VARCHAR(120) NULL AFTER author",
            'license_url'    => "VARCHAR(500) NULL AFTER license",
            'credit_required'=> "TINYINT(1) NOT NULL DEFAULT 0 AFTER license_url",
        );

        foreach ($add as $column => $definition) {
            if (!in_array($column, $columns, true)) {
                $this->db->query('ALTER TABLE ha_media ADD COLUMN ' . $column . ' ' . $definition);
            }
        }

        $indexes = $this->db->query("SHOW INDEX FROM ha_media")->result_array();
        $names = array();
        foreach ($indexes as $i) {
            $names[] = $i['Key_name'];
        }
        if (!in_array('ix_ha_media_subject', $names, true)) {
            $this->db->query('ALTER TABLE ha_media ADD KEY ix_ha_media_subject (subject)');
        }
        if (!in_array('ix_ha_media_credit', $names, true)) {
            $this->db->query('ALTER TABLE ha_media ADD KEY ix_ha_media_credit (credit_required)');
        }
    }

    public function down() {
        foreach (array('subject', 'source', 'source_page', 'source_file', 'author',
                     'license', 'license_url', 'credit_required') as $column) {
            $columns = $this->db->list_fields('ha_media');
            if (in_array($column, $columns, true)) {
                $this->db->query('ALTER TABLE ha_media DROP COLUMN ' . $column);
            }
        }
    }
}
