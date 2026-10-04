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
    /** Corporate homepage block sections shown on home_altus and editable through the home draft. */
    public static function corporate_sections() {
        return array('home_seo', 'home_answer', 'about', 'why', 'goppar', 'market', 'market_kpi', 'market_sources', 'platform', 'platform_features', 'platform_domains', 'home_faq', 'closing');
    }
    /** Published corporate blocks as draft rows keyed by block id. */
    public function corporate_rows() {
        if (!$this->CI->db->table_exists('ha_corporate_block')) { return array(); }
        $out = array();
        foreach ($this->CI->db->where_in('section', self::corporate_sections())->where(array('status' => 'published', 'visibility' => 'public'))->order_by('section')->order_by('sort_order')->order_by('id')->get('ha_corporate_block')->result_array() as $r) {
            $out[(string) $r['id']] = array('section' => $r['section'], 'code' => $r['code'], 'title_en' => (string) $r['title_en'], 'body_en' => (string) $r['body_en'], 'title_ar' => (string) $r['title_ar'], 'body_ar' => (string) $r['body_ar']);
        }
        return $out;
    }
    public function hash($id) {
        $p = $this->CI->ha_page_builder->page($id);
        if ($p && $p['code'] === 'home' && $this->CI->db->table_exists('ha_corporate_block')) {
            $p['corporate_source'] = $this->CI->db->where_in('section', array_merge(array('home_hero'), self::corporate_sections()))->order_by('id')->get('ha_corporate_block')->result_array();
        }
        return hash('sha256', json_encode($p, JSON_UNESCAPED_UNICODE));
    }
    public function state($id) {
        $this->authorize();
        $p = $this->CI->ha_page_builder->page($id);
        if (!$p) { throw new InvalidArgumentException('Page not found.'); }
        $draft = $this->CI->db->get_where('ha_website_draft', array('page_id' => (int) $id))->row_array();
        $payload = $draft ? json_decode($draft['payload_json'], true) : array('tr' => $p['tr'], 'sections' => $p['sections']);
        if (!$draft && $p['code'] === 'home' && empty($p['studio_enabled']) && $this->CI->db->table_exists('ha_corporate_block')) {
            $this->CI->load->library('ha_corporate');
            foreach (array('en', 'ar') as $loc) {
                $blocks = $this->CI->ha_corporate->locale($loc)->blocks(array('home_hero'));
                if (!empty($blocks['home_hero'][0])) {
                    $payload['tr'][$loc] = array('title' => $blocks['home_hero'][0]['title'], 'subtitle' => $blocks['home_hero'][0]['body'], 'body' => '', 'hero_image' => '', 'cta_label' => '', 'cta_url' => '');
                }
            }
        }
        if ($p['code'] === 'home') {
            // Every corporate homepage block is part of the home draft; drafts saved before this keep working.
            $live = $this->corporate_rows();
            $draft_rows = isset($payload['corporate']) && is_array($payload['corporate']) ? $payload['corporate'] : array();
            foreach ($live as $bid => &$row) { if (isset($draft_rows[$bid]) && is_array($draft_rows[$bid])) { foreach (array('title_en', 'body_en', 'title_ar', 'body_ar') as $k) { if (isset($draft_rows[$bid][$k])) { $row[$k] = (string) $draft_rows[$bid][$k]; } } } }
            unset($row);
            $payload['corporate'] = $live;
        }
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
        if (isset($payload['corporate'])) {
            if (!is_array($payload['corporate']) || count($payload['corporate']) > 300) { throw new InvalidArgumentException('Invalid homepage blocks.'); }
            $clean['corporate'] = array();
            foreach ($payload['corporate'] as $bid => $row) {
                if (!ctype_digit((string) $bid) || !is_array($row)) { throw new InvalidArgumentException('Invalid homepage block.'); }
                $out = array('section' => mb_substr((string) ($row['section'] ?? ''), 0, 60), 'code' => mb_substr((string) ($row['code'] ?? ''), 0, 100));
                foreach (array('title_en', 'body_en', 'title_ar', 'body_ar') as $k) {
                    $v = str_replace("\0", '', (string) ($row[$k] ?? ''));
                    if (mb_strlen($v) > (strpos($k, 'title') === 0 ? 500 : 20000)) { throw new InvalidArgumentException('Homepage block text is too long.'); }
                    $out[$k] = $v;
                }
                $clean['corporate'][(string) $bid] = $out;
            }
        }
        foreach ($payload['sections'] as $s) {
            $type = (string) ($s['section_type'] ?? '');
            if (!isset(Ha_page_builder::types()[$type])) { throw new InvalidArgumentException('Unknown section type.'); }
            $key = (string) ($s['studio_key'] ?? ('section-' . ($s['id'] ?? bin2hex(random_bytes(8)))));
            if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $key) || in_array($key, array_column($clean['sections'], 'studio_key'), true)) { throw new InvalidArgumentException('Section identifiers must be unique.'); }
            $row = array('studio_key' => $key, 'section_type' => $type, 'is_visible' => !empty($s['is_visible']) ? 1 : 0, 'settings' => array());
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
                $key = array('page_id' => (int) $id, 'locale' => $loc);
                if ($this->CI->db->where($key)->count_all_results('ha_page_translation')) { $this->CI->db->where($key)->update('ha_page_translation', $t); }
                elseif (trim($t['title']) !== '') { $this->CI->db->insert('ha_page_translation', $t + $key); }
            }
            $old_sections = array_column($this->CI->db->get_where('ha_page_section', array('page_id' => (int) $id))->result_array(), null, 'studio_key');
            $kept = array();
            $now = date('Y-m-d H:i:s');
            foreach ($payload['sections'] as $i => $s) {
                $row = array('page_id' => (int) $id, 'studio_key' => $s['studio_key'], 'section_type' => $s['section_type'], 'is_visible' => $s['is_visible'],
                    'sort_order' => $i, 'content_en' => json_encode($s['en'], JSON_UNESCAPED_UNICODE), 'content_ar' => json_encode($s['ar'], JSON_UNESCAPED_UNICODE),
                    'settings_json' => json_encode($s['settings']), 'updated_at' => $now);
                if (isset($old_sections[$s['studio_key']])) { $sid = $old_sections[$s['studio_key']]['id']; $this->CI->db->where('id', $sid)->update('ha_page_section', $row); }
                else { $this->CI->db->insert('ha_page_section', $row + array('created_at' => $now)); $sid = $this->CI->db->insert_id(); }
                $kept[] = $sid;
            }
            $this->CI->db->where('page_id', (int) $id); if ($kept) { $this->CI->db->where_not_in('id', $kept); } $this->CI->db->delete('ha_page_section');
            if ($s = $this->CI->db->get_where('ha_page',array('id'=>(int)$id))->row_array()) {
                if ($s['code']==='home' && $this->CI->db->table_exists('ha_corporate_block')) {
                    foreach ($payload['tr'] as $locale=>$copy) $this->CI->db->where('section','home_hero')->update('ha_corporate_block',array('title_'.$locale=>$copy['title'],'body_'.$locale=>$copy['subtitle']));
                    // Write every edited corporate block back to its native record (only blocks of the homepage sections).
                    foreach ((array) ($payload['corporate'] ?? array()) as $bid => $row) {
                        $this->CI->db->where('id', (int) $bid)->where_in('section', self::corporate_sections())
                            ->update('ha_corporate_block', array('title_en' => $row['title_en'], 'body_en' => $row['body_en'], 'title_ar' => $row['title_ar'], 'body_ar' => $row['body_ar']));
                    }
                }
            }
            $this->CI->db->where('id', (int) $id)->update('ha_page', array('status' => 'published', 'published_at' => $now, 'updated_at' => $now, 'studio_enabled' => 1));
            $this->CI->db->where('page_id', (int) $id)->delete('ha_website_draft');
            $this->CI->ha_page_builder->revision($id, $this->CI->ha_auth->id(), 'Live editor published');
            $this->CI->ha_audit->log('publish', 'page', $id, array('description' => 'Website draft explicitly published'));
            if (!$this->CI->db->trans_status()) { throw new RuntimeException('Publication failed; no changes were published.'); }
            $this->CI->db->trans_commit();
        } catch (Throwable $e) { $this->CI->db->trans_rollback(); throw $e; }
    }
    public function discard($id, $version) {
        $this->authorize(); $this->CI->db->trans_begin();
        try {
            $this->CI->db->query('SELECT id FROM ha_page WHERE id=? FOR UPDATE', array((int) $id));
            $d = $this->CI->db->get_where('ha_website_draft', array('page_id' => (int) $id))->row_array();
            if (!$d || (int) $d['version'] !== (int) $version) { throw new DomainException('This draft changed. Reload before discarding.'); }
            $this->CI->db->where('page_id', (int) $id)->delete('ha_website_draft');
            $this->CI->ha_audit->log('update', 'website_draft', $id, array('description' => 'Private website draft discarded; published page retained'));
            if (!$this->CI->db->trans_status()) { throw new RuntimeException('Draft could not be discarded.'); }
            $this->CI->db->trans_commit();
        } catch (Throwable $e) { $this->CI->db->trans_rollback(); throw $e; }
    }
}
