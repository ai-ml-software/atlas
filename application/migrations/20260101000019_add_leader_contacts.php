<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Leadership profiles carry their own contact lines and social links, managed in
 * the workspace (Admin → Leadership profiles): e-mail, phone (call + WhatsApp)
 * and Facebook / Instagram / X next to the existing LinkedIn.
 */
class Migration_Add_leader_contacts extends Ha_migration {

    private $cols = array(
        'email'         => 'VARCHAR(190) NULL',
        'phone'         => 'VARCHAR(40) NULL',
        'facebook_url'  => 'VARCHAR(255) NULL',
        'instagram_url' => 'VARCHAR(255) NULL',
        'x_url'         => 'VARCHAR(255) NULL',
    );

    public function up() {
        if (!$this->db->table_exists('ha_leadership_profile')) {
            return;
        }
        foreach ($this->cols as $name => $def) {
            if (!$this->db->field_exists($name, 'ha_leadership_profile')) {
                $this->db->query("ALTER TABLE ha_leadership_profile ADD COLUMN `$name` $def");
            }
        }
    }

    public function down() {
        foreach (array_keys($this->cols) as $name) {
            if ($this->db->field_exists($name, 'ha_leadership_profile')) {
                $this->db->query("ALTER TABLE ha_leadership_profile DROP COLUMN `$name`");
            }
        }
    }
}
