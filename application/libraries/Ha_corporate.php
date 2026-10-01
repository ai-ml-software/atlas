<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Altus Gulf corporate content for the public pages (About, Services, Ascent,
 * Market, Case Studies, Leadership, Altus Knowledge and Performance).
 *
 * The records are the same CMS tables the workspace edits (ha_corporate_block,
 * ha_service, ha_sector, ha_case_study, ha_leadership_profile); this library
 * only reads what is published and public, in the visitor's language. Arabic
 * reads the *_ar columns; other site languages read ha_i18n_text overlays when
 * present and fall back to English, the same rule the catalogue follows.
 */
class Ha_corporate {

    protected $CI;
    protected $locale = 'en';

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    public function locale($locale) {
        $this->locale = $locale;
        return $this;
    }

    /** Picks the field for the current language with English as the fallback. */
    protected function pick(array $row, $field) {
        if ($this->locale === 'ar' && isset($row[$field . '_ar']) && trim((string) $row[$field . '_ar']) !== '') {
            return $row[$field . '_ar'];
        }
        return isset($row[$field . '_en']) ? (string) $row[$field . '_en'] : '';
    }

    /** Overlay translations (ha_i18n_text) for languages other than en/ar. */
    protected function overlay($entity, array $rows, array $fields) {
        if ($this->locale === 'en' || $this->locale === 'ar' || !$rows || !$this->CI->db->table_exists('ha_i18n_text')) {
            return $rows;
        }
        $ids = array_column($rows, 'id');
        $map = array();
        foreach ($this->CI->db->where('entity', $entity)->where('locale', $this->locale)->where_in('entity_id', $ids)->where_in('field', $fields)
                     ->get('ha_i18n_text')->result_array() as $t) {
            $map[$t['entity_id']][$t['field']] = $t['value'];
        }
        foreach ($rows as &$r) {
            foreach ($fields as $f) {
                if (!empty($map[$r['id']][$f])) {
                    $r[$f] = $map[$r['id']][$f];
                }
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Published public blocks, grouped by section, each as id/code/title/body
     * (body split into paragraphs on blank lines, lines kept).
     */
    public function blocks(array $sections) {
        $rows = $this->CI->db->where_in('section', $sections)->where(array('status' => 'published', 'visibility' => 'public'))
            ->order_by('sort_order')->get('ha_corporate_block')->result_array();
        $out = array_fill_keys($sections, array());
        $list = array();
        foreach ($rows as $r) {
            $list[] = array('id' => (int) $r['id'], 'code' => $r['code'], 'section' => $r['section'],
                'title' => $this->pick($r, 'title'), 'body' => $this->pick($r, 'body'));
        }
        foreach ($this->overlay('corporate_block', $list, array('title', 'body')) as $b) {
            $out[$b['section']][] = $b;
        }
        return $out;
    }

    /** One block by code, or an empty shell so a view never needs isset() chains. */
    public function block($code) {
        $r = $this->CI->db->get_where('ha_corporate_block', array('code' => $code, 'status' => 'published', 'visibility' => 'public'))->row_array();
        if (!$r) {
            return array('id' => 0, 'code' => $code, 'title' => '', 'body' => '');
        }
        $b = array('id' => (int) $r['id'], 'code' => $code, 'title' => $this->pick($r, 'title'), 'body' => $this->pick($r, 'body'));
        $o = $this->overlay('corporate_block', array($b), array('title', 'body'));
        return $o[0];
    }

    public function services() {
        $out = array('hospitality' => array(), 'business_growth' => array());
        foreach ($this->CI->db->order_by('sort_order')->get_where('ha_service', array('status' => 'published'))->result_array() as $s) {
            $out[$s['division']][] = array('id' => (int) $s['id'], 'code' => $s['code'], 'title' => $this->pick($s, 'title'), 'summary' => $this->pick($s, 'summary'));
        }
        return $out;
    }

    public function sectors() {
        $out = array();
        foreach ($this->CI->db->order_by('sort_order')->get_where('ha_sector', array('status' => 'active'))->result_array() as $s) {
            $out[] = array('id' => (int) $s['id'], 'code' => $s['code'], 'name' => $this->pick($s, 'name'));
        }
        return $out;
    }

    public function cases() {
        $out = array();
        foreach ($this->CI->db->order_by('sort_order')->get_where('ha_case_study', array('status' => 'published', 'visibility' => 'public'))->result_array() as $c) {
            $m = json_decode((string) $c['metrics_json'], true);
            $metrics = array();
            // metrics_json is either the 2026 {kicker_ar, items:[{value,en,ar}]} or the older flat list of strings.
            if (isset($m['items'])) {
                foreach ($m['items'] as $i) {
                    $metrics[] = array('value' => $i['value'], 'label' => $this->locale === 'ar' && !empty($i['ar']) ? $i['ar'] : $i['en']);
                }
            } elseif (is_array($m)) {
                foreach ($m as $s) {
                    $metrics[] = array('value' => '', 'label' => (string) $s);
                }
            }
            $out[] = array('id' => (int) $c['id'], 'slug' => $c['slug'], 'title' => $this->pick($c, 'title'),
                'kicker' => $this->locale === 'ar' && !empty($m['kicker_ar']) ? $m['kicker_ar'] : (string) $c['category'],
                'client' => $this->pick($c, 'client_profile'), 'challenge' => $this->pick($c, 'challenge'),
                'approach' => $this->pick($c, 'approach'), 'results' => $this->pick($c, 'results'),
                'metrics' => $metrics, 'illustrative' => (bool) $c['is_illustrative']);
        }
        return $out;
    }

    public function leaders() {
        $out = array();
        foreach ($this->CI->db->order_by('sort_order')->get_where('ha_leadership_profile', array('status' => 'published'))->result_array() as $l) {
            $lines = function ($text) { return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text)), 'strlen')); };
            $out[] = array('id' => (int) $l['id'], 'slug' => $l['slug'], 'name' => $this->pick($l, 'name'), 'role' => $this->pick($l, 'role'),
                'bio' => $this->pick($l, 'biography'), 'track' => $lines($this->pick($l, 'track_record')),
                'recognition' => $lines($this->pick($l, 'recognition')), 'photo' => (string) $l['photo_path'], 'linkedin' => (string) $l['linkedin_url'],
                'email' => (string) ($l['email'] ?? ''), 'phone' => (string) ($l['phone'] ?? ''), 'phone_digits' => preg_replace('/\D/', '', (string) ($l['phone'] ?? '')),
                'social' => array_filter(array('linkedin' => (string) $l['linkedin_url'], 'facebook' => (string) ($l['facebook_url'] ?? ''),
                    'instagram' => (string) ($l['instagram_url'] ?? ''), 'x' => (string) ($l['x_url'] ?? '')), 'strlen'));
        }
        return $out;
    }
}
