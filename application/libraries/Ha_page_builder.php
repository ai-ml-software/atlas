<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Section-based page builder for the public website.
 *
 * A page is its translation (title, subtitle, hero, body) plus an ordered list
 * of typed sections, each holding its own English and Arabic content. Sections
 * can be added, edited, hidden, duplicated, deleted and dragged into a new
 * order. Every save writes a revision snapshot, so any earlier state can be
 * restored. Section HTML passes through the same allow-list sanitiser as all
 * other authored content before it reaches a visitor.
 */
class Ha_page_builder {

    protected $CI;

    /** type => array(label, fields). Field suffix: * required, [] repeatable "a | b | c" lines. */
    public static function types() {
        return array(
            'hero'       => array('Hero banner', array('heading*', 'lede', 'button_label', 'button_url'), array('image')),
            'rich_text'  => array('Text', array('heading', 'body'), array()),
            'image_text' => array('Image and text', array('heading', 'body', 'image_alt'), array('image', 'image_side')),
            'image'      => array('Image', array('image_alt*', 'caption'), array('image')),
            'gallery'    => array('Gallery', array('heading', 'items[]:image|caption'), array()),
            'cards'      => array('Cards', array('heading', 'items[]:title|text|link'), array('columns')),
            'stats'      => array('Key figures', array('heading', 'items[]:title|text'), array()),
            'faq'        => array('FAQ (answer engines)', array('heading', 'items[]:q|a'), array()),
            'steps'      => array('Steps / how-to', array('heading', 'items[]:title|text'), array()),
            'cta'        => array('Call to action', array('heading*', 'body', 'button_label', 'button_url'), array()),
            'video'      => array('Video', array('heading', 'video_url*', 'caption'), array()),
            'quote'      => array('Quote', array('quote*', 'author'), array()),
            'html'       => array('Custom HTML (sanitised)', array('body*'), array()),
        );
    }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper('hkp');
        $this->CI->load->library(array('ha_audit'));
    }

    public function page($id) {
        $p = $this->CI->db->get_where('ha_page', array('id' => (int) $id))->row_array();
        if (!$p) {
            return null;
        }
        $p['tr'] = array();
        foreach ($this->CI->db->get_where('ha_page_translation', array('page_id' => (int) $id))->result_array() as $t) {
            $p['tr'][$t['locale']] = $t;
        }
        $p['seo'] = array();
        foreach ($this->CI->db->get_where('ha_seo_metadata', array('entity_type' => 'page', 'entity_id' => (int) $id))->result_array() as $s) {
            $p['seo'][$s['locale']] = $s;
        }
        $p['sections'] = $this->sections($id, false);
        return $p;
    }

    public function sections($page_id, $visible_only = true) {
        $db = $this->CI->db->where('page_id', (int) $page_id);
        if ($visible_only) {
            $db->where('is_visible', 1);
        }
        $rows = $db->order_by('sort_order')->order_by('id')->get('ha_page_section')->result_array();
        foreach ($rows as &$r) {
            $r['en'] = json_decode((string) $r['content_en'], true) ?: array();
            $r['ar'] = json_decode((string) $r['content_ar'], true) ?: array();
            $r['settings'] = json_decode((string) $r['settings_json'], true) ?: array();
        }
        return $rows;
    }

    /** Parses "a | b | c" lines into item arrays for repeatable fields. */
    public static function parse_items($text, array $keys) {
        $items = array();
        foreach (preg_split('/\r?\n/', (string) $text) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $item = array();
            foreach ($keys as $i => $k) {
                $item[$k] = isset($parts[$i]) ? $parts[$i] : '';
            }
            $items[] = $item;
        }
        return $items;
    }

    public static function items_to_text(array $items, array $keys) {
        $lines = array();
        foreach ($items as $it) {
            $row = array();
            foreach ($keys as $k) {
                $row[] = isset($it[$k]) ? str_replace(array("\r", "\n", '|'), array(' ', ' ', '/'), $it[$k]) : '';
            }
            $lines[] = implode(' | ', $row);
        }
        return implode("\n", $lines);
    }

    /** Builds the stored content for one locale from posted fields. */
    public function content_from_input($type, array $in) {
        $types = self::types();
        $out = array();
        foreach ($types[$type][1] as $f) {
            $f = rtrim($f, '*');
            if (strpos($f, 'items[]:') === 0) {
                $out['items'] = self::parse_items(isset($in['items']) ? $in['items'] : '', explode('|', substr($f, 8)));
                continue;
            }
            $out[$f] = isset($in[$f]) ? trim((string) $in[$f]) : '';
        }
        return $out;
    }

    public function save_section($page_id, $section_id, $type, array $en, array $ar, array $settings, $actor_id) {
        $types = self::types();
        if (!isset($types[$type])) {
            throw new InvalidArgumentException('Unknown section type.');
        }
        foreach ($types[$type][1] as $f) {
            if (substr($f, -1) === '*' && trim((string) (isset($en[rtrim($f, '*')]) ? $en[rtrim($f, '*')] : '')) === '') {
                throw new InvalidArgumentException('Fill in "' . str_replace('_', ' ', rtrim($f, '*')) . '" (English).');
            }
        }
        if (isset($en['video_url']) && $en['video_url'] !== '' && !preg_match('~^(https?://|/)~i', $en['video_url'])) {
            throw new InvalidArgumentException('The video must be a YouTube, Vimeo or MP4 link.');
        }
        foreach (array('button_url') as $u) {
            foreach (array('en' => $en, 'ar' => $ar) as $c) {
                if (!empty($c[$u]) && !preg_match('~^(https?://|/|#|[a-z0-9\-/]+$)~i', $c[$u])) {
                    throw new InvalidArgumentException('Button links must be a site path or an http(s) address.');
                }
            }
        }
        $clean = array();
        foreach (array('image', 'image_side', 'columns') as $k) {
            if (isset($settings[$k])) {
                $clean[$k] = mb_substr(trim((string) $settings[$k]), 0, 500);
            }
        }
        $row = array('section_type' => $type, 'content_en' => json_encode($en, JSON_UNESCAPED_UNICODE), 'content_ar' => json_encode($ar, JSON_UNESCAPED_UNICODE),
            'settings_json' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s'));
        if ($section_id) {
            $this->CI->db->where(array('id' => (int) $section_id, 'page_id' => (int) $page_id))->update('ha_page_section', $row);
        } else {
            $max = $this->CI->db->select_max('sort_order')->get_where('ha_page_section', array('page_id' => (int) $page_id))->row()->sort_order;
            $this->CI->db->insert('ha_page_section', $row + array('page_id' => (int) $page_id, 'sort_order' => (int) $max + 1, 'is_visible' => 1, 'created_at' => date('Y-m-d H:i:s')));
            $section_id = (int) $this->CI->db->insert_id();
        }
        $this->revision($page_id, $actor_id, 'Section ' . $type . ' saved');
        return (int) $section_id;
    }

    public function reorder($page_id, array $ids) {
        foreach (array_values($ids) as $i => $sid) {
            $this->CI->db->where(array('id' => (int) $sid, 'page_id' => (int) $page_id))->update('ha_page_section', array('sort_order' => $i));
        }
    }

    public function toggle($page_id, $section_id) {
        $this->CI->db->set('is_visible', '1 - is_visible', false)->where(array('id' => (int) $section_id, 'page_id' => (int) $page_id))->update('ha_page_section');
    }

    public function duplicate($page_id, $section_id, $actor_id) {
        $s = $this->CI->db->get_where('ha_page_section', array('id' => (int) $section_id, 'page_id' => (int) $page_id))->row_array();
        if (!$s) {
            return 0;
        }
        unset($s['id']);
        $s['sort_order'] = (int) $s['sort_order'] + 1;
        $s['created_at'] = $s['updated_at'] = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_page_section', $s);
        $this->revision($page_id, $actor_id, 'Section duplicated');
        return (int) $this->CI->db->insert_id();
    }

    public function delete($page_id, $section_id, $actor_id) {
        $this->revision($page_id, $actor_id, 'Before deleting a section');
        $this->CI->db->where(array('id' => (int) $section_id, 'page_id' => (int) $page_id))->delete('ha_page_section');
    }

    public function revision($page_id, $actor_id, $note) {
        $p = $this->page($page_id);
        unset($p['seo']);
        $this->CI->db->insert('ha_page_revision', array('page_id' => (int) $page_id, 'snapshot_json' => json_encode($p, JSON_UNESCAPED_UNICODE),
            'note' => mb_substr($note, 0, 255), 'created_by' => $actor_id, 'created_at' => date('Y-m-d H:i:s')));
        // Keep the latest 60 revisions per page.
        $old = $this->CI->db->select('id')->where('page_id', (int) $page_id)->order_by('id', 'DESC')->limit(1000, 60)->get('ha_page_revision')->result_array();
        if ($old) {
            $this->CI->db->where_in('id', array_column($old, 'id'))->delete('ha_page_revision');
        }
    }

    public function restore($page_id, $revision_id, $actor_id) {
        $r = $this->CI->db->get_where('ha_page_revision', array('id' => (int) $revision_id, 'page_id' => (int) $page_id))->row_array();
        if (!$r) {
            throw new InvalidArgumentException('Revision not found.');
        }
        $snap = json_decode($r['snapshot_json'], true);
        $this->revision($page_id, $actor_id, 'Before restoring revision #' . (int) $revision_id);
        $this->CI->db->trans_start();
        foreach ((array) $snap['tr'] as $loc => $t) {
            $row = array_intersect_key($t, array_flip(array('title', 'subtitle', 'body', 'hero_image', 'cta_label', 'cta_url')));
            $this->CI->db->where(array('page_id' => (int) $page_id, 'locale' => $loc))->update('ha_page_translation', $row);
        }
        $this->CI->db->where('page_id', (int) $page_id)->delete('ha_page_section');
        foreach ((array) $snap['sections'] as $s) {
            $this->CI->db->insert('ha_page_section', array('page_id' => (int) $page_id, 'section_type' => $s['section_type'], 'sort_order' => (int) $s['sort_order'],
                'is_visible' => (int) $s['is_visible'], 'settings_json' => $s['settings_json'], 'content_en' => $s['content_en'], 'content_ar' => $s['content_ar'],
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')));
        }
        $this->CI->db->trans_complete();
        $this->CI->ha_audit->log('update', 'page', (int) $page_id, array('description' => 'Restored revision #' . (int) $revision_id));
    }

    /** YouTube / Vimeo / file URL -> embeddable source. */
    public static function video_embed($url) {
        $url = trim((string) $url);
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return array('type' => 'iframe', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1]);
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return array('type' => 'iframe', 'src' => 'https://player.vimeo.com/video/' . $m[1]);
        }
        if ($url !== '') {
            return array('type' => 'video', 'src' => preg_match('~^https?://~', $url) ? $url : base_url(ltrim($url, '/')));
        }
        return null;
    }

    /** FAQ items across the visible sections, for FAQPage schema. */
    public function faq_items($page_id, $loc) {
        $out = array();
        foreach ($this->sections($page_id) as $s) {
            if ($s['section_type'] !== 'faq') {
                continue;
            }
            $c = $s[$loc] ?: $s['en'];
            foreach ((array) (isset($c['items']) ? $c['items'] : array()) as $it) {
                if (!empty($it['q']) && !empty($it['a'])) {
                    $out[] = array('question' => $it['q'], 'answer' => strip_tags($it['a']));
                }
            }
        }
        return $out;
    }
}
