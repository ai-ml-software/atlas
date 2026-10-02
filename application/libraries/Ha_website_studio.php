<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Separate website drafts, optimistic locking and explicit publication. */
class Ha_website_studio {
    private $CI;
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->library(array('ha_auth', 'ha_page_builder', 'ha_audit'));
        $this->CI->load->helper('hkp');
    }
    private function authorize($publish = false) {
        if (!$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has($publish ? 'cms_pages.publish' : 'cms_pages.update')) {
            throw new RuntimeException('Website editing requires platform website permission.');
        }
    }
    public function hash($id) {
        $p = $this->CI->ha_page_builder->page($id);
        return hash('sha256', json_encode($p, JSON_UNESCAPED_UNICODE));
    }
    public function state($id) {
        $this->authorize();
        $p = $this->CI->ha_page_builder->page($id);
        if (!$p) { throw new InvalidArgumentException('Page not found.'); }
        $draft = $this->CI->db->get_where('ha_website_draft', array('page_id' => (int) $id))->row_array();
        $payload = $draft ? json_decode($draft['payload_json'], true) : array('tr' => $p['tr'], 'sections' => $p['sections']);
        return array('page' => $p, 'payload' => $payload, 'version' => $draft ? (int) $draft['version'] : 0,
            'base_hash' => $draft ? $draft['base_hash'] : $this->hash($id), 'updated_at' => $draft['updated_at'] ?? null);
    }
    public function validate(array $payload) {
        if (!isset($payload['tr'], $payload['sections']) || !is_array($payload['tr']) || !is_array($payload['sections']) || count($payload['sections']) > 80) {
            throw new InvalidArgumentException('A page needs translations and at most 80 sections.');
        }
        $clean = array('tr' => array(), 'sections' => array());
        foreach (array('en', 'ar') as $loc) {
            $t = (array) ($payload['tr'][$loc] ?? array());
            $clean['tr'][$loc] = array();
            foreach (array('title', 'subtitle', 'body', 'hero_image', 'cta_label', 'cta_url') as $field) {
                $v = (string) ($t[$field] ?? '');
                if (mb_strlen($v) > ($field === 'body' ? 60000 : ($field === 'title' ? 190 : 500))) { throw new InvalidArgumentException('Page field is too long: ' . $field); }
                if (in_array($field, array('hero_image', 'cta_url'), true)) { self::safe_url($v); }
                $clean['tr'][$loc][$field] = $field === 'body' ? hkp_safe_html($v) : $v;
            }
        }
        if (trim($clean['tr']['en']['title']) === '') { throw new InvalidArgumentException('An English title is required.'); }
        foreach ($payload['sections'] as $s) {
            $type = (string) ($s['section_type'] ?? '');
            if (!isset(Ha_page_builder::types()[$type])) { throw new InvalidArgumentException('Unknown section type.'); }
            $row = array('section_type' => $type, 'is_visible' => !empty($s['is_visible']) ? 1 : 0, 'settings' => array());
            foreach (array('en', 'ar') as $loc) {
                $in = (array) ($s[$loc] ?? array());
                if (isset($in['items']) && is_array($in['items'])) {
                    foreach (Ha_page_builder::types()[$type][1] as $f) {
                        if (strpos($f, 'items[]:') === 0) { $in['items'] = Ha_page_builder::items_to_text($in['items'], explode('|', substr($f, 8))); }
                    }
                }
                $row[$loc] = $this->CI->ha_page_builder->content_from_input($type, $in);
                foreach ($row[$loc] as $k => &$v) {
                    if ($k === 'items') {
                        if (count($v) > 100) { throw new InvalidArgumentException('Too many section items.'); }
                        foreach ($v as &$item) { foreach ($item as $ik => $iv) { if (in_array($ik, array('image', 'link'), true)) { self::safe_url($iv); } } } unset($item);
                    } elseif (in_array($k, array('button_url', 'video_url'), true)) { self::safe_url($v); }
                    elseif ($k === 'body') { $v = hkp_safe_html($v); }
                    elseif (mb_strlen($v) > 20000) { throw new InvalidArgumentException('Section text is too long.'); }
                } unset($v);
            }
            foreach (array('image', 'image_side', 'columns') as $k) {
                if (isset($s['settings'][$k])) { $row['settings'][$k] = mb_substr((string) $s['settings'][$k], 0, 500); }
            }
            self::safe_url($row['settings']['image'] ?? '');
            $clean['sections'][] = $row;
        }
        return $clean;
    }
    public static function safe_url($v) {
        if ($v !== '' && (!preg_match('~^(https?://|/(?!/)|#|[a-zA-Z0-9][a-zA-Z0-9_./?=&%#-]*$)~', $v) || preg_match('/[\x00-\x20\\\\]/', $v))) {
            throw new InvalidArgumentException('Use a site path or an HTTP(S) URL.');
        }
    }
    public function save($id, array $payload, $version, $base_hash) {
        $this->authorize();
        $payload = $this->validate($payload);
        $this->CI->db->trans_begin();
        try {
            $p = $this->CI->db->query('SELECT id FROM ha_page WHERE id = ? FOR UPDATE', array((int) $id))->row_array();
            if (!$p) { throw new InvalidArgumentException('Page not found.'); }
            $d = $this->CI->db->get_where('ha_website_draft', array('page_id' => (int) $id))->row_array();
            if ((int) $version !== ($d ? (int) $d['version'] : 0) || !$base_hash || !hash_equals($this->hash($id), $base_hash)) {
                throw new DomainException('This page changed in another editor. Reload before saving.');
            }
            $row = array('version' => (int) $version + 1, 'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'base_hash' => $base_hash, 'updated_by' => $this->CI->ha_auth->id(), 'updated_at' => date('Y-m-d H:i:s'));
            $d ? $this->CI->db->where('page_id', (int) $id)->update('ha_website_draft', $row) : $this->CI->db->insert('ha_website_draft', $row + array('page_id' => (int) $id));
            if (!$this->CI->db->trans_status()) { throw new RuntimeException('Draft could not be saved.'); }
            $this->CI->ha_audit->log('update', 'website_draft', $id, array('description' => 'Website draft saved', 'after' => array('version' => $row['version'], 'hash' => hash('sha256', $row['payload_json']))));
            $this->CI->db->trans_commit();
            return $row['version'];
        } catch (Throwable $e) { $this->CI->db->trans_rollback(); throw $e; }
    }
    public function publish($id, $version) {
        $this->authorize(true);
        $this->CI->db->trans_begin();
        try {
            $this->CI->db->query('SELECT id FROM ha_page WHERE id = ? FOR UPDATE', array((int) $id));
            $s = $this->state($id);
            if (!$version || (int) $version !== $s['version'] || !hash_equals($this->hash($id), $s['base_hash'])) {
                throw new DomainException('The draft or published page changed. Reload and review before publishing.');
            }
            $payload = $this->validate($s['payload']);
            $this->CI->ha_page_builder->revision($id, $this->CI->ha_auth->id(), 'Before live editor publication');
            foreach ($payload['tr'] as $loc => $t) {
                $this->CI->db->where(array('page_id' => (int) $id, 'locale' => $loc))->update('ha_page_translation', $t);
            }
            $this->CI->db->where('page_id', (int) $id)->delete('ha_page_section');
            $now = date('Y-m-d H:i:s');
            foreach ($payload['sections'] as $i => $s) {
                $this->CI->db->insert('ha_page_section', array('page_id' => (int) $id, 'section_type' => $s['section_type'], 'is_visible' => $s['is_visible'],
                    'sort_order' => $i, 'content_en' => json_encode($s['en'], JSON_UNESCAPED_UNICODE), 'content_ar' => json_encode($s['ar'], JSON_UNESCAPED_UNICODE),
                    'settings_json' => json_encode($s['settings']), 'created_at' => $now, 'updated_at' => $now));
            }
            $this->CI->db->where('id', (int) $id)->update('ha_page', array('status' => 'published', 'published_at' => $now, 'updated_at' => $now));
            $this->CI->db->where('page_id', (int) $id)->delete('ha_website_draft');
            $this->CI->ha_page_builder->revision($id, $this->CI->ha_auth->id(), 'Live editor published');
            $this->CI->ha_audit->log('publish', 'page', $id, array('description' => 'Website draft explicitly published'));
            if (!$this->CI->db->trans_status()) { throw new RuntimeException('Publication failed; no changes were published.'); }
            $this->CI->db->trans_commit();
        } catch (Throwable $e) { $this->CI->db->trans_rollback(); throw $e; }
    }
}
