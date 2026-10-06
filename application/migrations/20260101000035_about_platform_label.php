<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Renames the "About Academy" navigation label to "About Platform" (header, footer and Company menus). */
class Migration_About_platform_label extends Ha_migration {
    public function up() {
        if (!$this->db->table_exists('ha_menu_item')) return;
        $this->db->where('label_en', 'About Academy')
            ->update('ha_menu_item', array('label_en' => 'About Platform', 'label_ar' => 'عن المنصة'));
    }
    public function down() {
        if (!$this->db->table_exists('ha_menu_item')) return;
        $this->db->where('label_en', 'About Platform')->where('url_en', 'about')
            ->update('ha_menu_item', array('label_en' => 'About Academy', 'label_ar' => 'عن الأكاديمية'));
    }
}
