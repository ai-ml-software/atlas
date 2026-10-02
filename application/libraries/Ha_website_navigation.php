<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Ha_website_navigation {
    private $CI;
    public function __construct() { $this->CI =& get_instance(); $this->CI->load->library(array('ha_auth', 'ha_audit')); }
    public function menus() { return $this->CI->db->order_by('id')->get('ha_menu')->result_array(); }
    public function items($id) { return $this->CI->db->order_by('sort_order')->order_by('id')->get_where('ha_menu_item', array('menu_id' => (int) $id))->result_array(); }
    public function save($id, array $items) {
        if (!$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has('cms_pages.update')) { throw new RuntimeException('Platform website permission required.'); }
        if (!$this->CI->db->get_where('ha_menu', array('id' => $id))->row_array() || count($items) > 80) { throw new InvalidArgumentException('Invalid menu.'); }
        $old = array_column($this->items($id), null, 'id'); $clean = array(); $seen = array();
        $protected = array('', 'courses', 'programs', 'learning-paths', 'certificates', 'sop', 'hospitality-topics', 'articles', 'verify', 'about', 'hotels', 'contact', 'credits');
        foreach ($items as $i => $in) {
            $key = (int) ($in['id'] ?? 0);
            if (($key && (!isset($old[$key]) || isset($seen[$key]))) || !isset($in['label_en'], $in['url_en'])) { throw new InvalidArgumentException('Invalid menu item.'); }
            if ($key) { $seen[$key] = true; }
            $row = array('sort_order' => count($clean), 'status' => !empty($in['visible']) ? 'active' : 'hidden');
            foreach (array('en', 'ar') as $loc) {
                $label = trim((string) ($in['label_' . $loc] ?? ''));
                $url = trim((string) ($in['url_' . $loc] ?? $in['url_en']), '/');
                if ($label === '' && $loc === 'en') { throw new InvalidArgumentException('Every link needs an English label.'); }
                if (mb_strlen($label) > 190 || mb_strlen($url) > 500 || preg_match('~[^\pL\pN_./#?=&%-]~u', $url) || strpos($url, '..') !== false || strpos($url, '//') !== false) { throw new InvalidArgumentException('Use a valid site path without the language prefix.'); }
                if ($key && in_array($old[$key]['url_' . $loc], $protected, true) && $url !== $old[$key]['url_' . $loc]) { throw new InvalidArgumentException('Required system links keep their original URL. Change their label or visibility instead.'); }
                $row['label_' . $loc] = $label; $row['url_' . $loc] = $url;
            }
            $clean[] = array('id' => $key, 'row' => $row);
        }
        if (count($seen) !== count($old)) { throw new InvalidArgumentException('Keep existing links; hide them instead of deleting.'); }
        $this->CI->db->trans_start();
        foreach ($clean as $c) { $c['id'] ? $this->CI->db->where(array('id' => $c['id'], 'menu_id' => $id))->update('ha_menu_item', $c['row']) : $this->CI->db->insert('ha_menu_item', $c['row'] + array('menu_id' => $id)); }
        $this->CI->ha_audit->log('update', 'navigation', $id, array('description' => 'Website menu labels, visibility and order saved'));
        $this->CI->db->trans_complete();
        if (!$this->CI->db->trans_status()) { throw new RuntimeException('Navigation could not be saved.'); }
    }
}
