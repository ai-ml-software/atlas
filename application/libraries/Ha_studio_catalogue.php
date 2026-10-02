<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Public catalogue editing; global public records require platform capabilities. */
class Ha_studio_catalogue {
    private $CI;
    public static function types() {
        return array(
            'programs' => array('title' => 'Programs', 'table' => 'ha_program', 'perm' => 'programs', 'route' => 'programs', 'translation' => 'ha_program_translation', 'fk' => 'program_id', 'summary' => 'short_description', 'body' => 'description', 'image' => 'thumbnail'),
            'paths' => array('title' => 'Learning paths', 'table' => 'ha_learning_path', 'perm' => 'learning_paths', 'route' => 'learning-paths', 'summary' => 'summary', 'body' => 'description', 'image' => 'thumbnail'),
            'articles' => array('title' => 'Articles', 'table' => 'ha_article', 'perm' => 'articles', 'route' => 'articles', 'translation' => 'ha_article_translation', 'fk' => 'article_id', 'summary' => 'excerpt', 'body' => 'body', 'image' => 'cover_image'),
            'topics' => array('title' => 'Hospitality topics', 'table' => 'ha_topic', 'perm' => 'cms_pages', 'route' => 'hospitality-topics', 'summary' => null, 'body' => 'intro', 'image' => 'hero_image')
        );
    }
    public function __construct() { $this->CI =& get_instance(); $this->CI->load->library(array('ha_auth', 'ha_audit')); $this->CI->load->helper('hkp'); }
    public function definition($type) { $d = self::types()[$type] ?? null; if (!$d) { throw new InvalidArgumentException('Unknown content type.'); } return $d; }
    public function authorize($type, $operation) { $d = $this->definition($type); if (!$this->CI->ha_auth->is_system_scoped() || !$this->CI->ha_auth->has($d['perm'] . '.' . $operation)) { throw new RuntimeException('Platform ' . $d['perm'] . '.' . $operation . ' permission is required.'); } return $d; }
    public function record($type, $id) {
        $d = $this->authorize($type, 'view'); $row = $this->CI->db->get_where($d['table'], array('id' => (int) $id))->row_array();
        if (!$row) { throw new InvalidArgumentException('Record not found.'); }
        $tr = array();
        if (isset($d['translation'])) { foreach ($this->CI->db->get_where($d['translation'], array($d['fk'] => (int) $id))->result_array() as $t) { $tr[$t['locale']] = $t; } }
        $record = array('row' => $row, 'tr' => $tr);
        if (in_array($type, array('programs', 'topics'), true)) { $record['courses'] = $this->CI->db->order_by('sort_order')->get_where($type === 'programs' ? 'ha_program_course' : 'ha_topic_course', array($type === 'programs' ? 'program_id' : 'topic_id' => (int) $id))->result_array(); }
        if ($type === 'paths') {
            $record['steps'] = $this->CI->db->order_by('sort_order')->order_by('id')->get_where('ha_path_step', array('path_id' => (int) $id))->result_array();
            foreach ($record['steps'] as &$s) { $s['items'] = $this->CI->db->order_by('sort_order')->get_where('ha_path_step_item', array('step_id' => $s['id']))->result_array(); } unset($s);
        }
        return $record;
    }
    public function hash(array $record) { return hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE)); }
    public function listing($type, $query, $page) {
        $d = $this->authorize($type, 'view'); $db = $this->CI->db;
        $db->select('r.*')->from($d['table'] . ' r');
        if (isset($d['translation'])) { $db->select('t.title AS title_en')->join($d['translation'] . ' t', 't.' . $d['fk'] . " = r.id AND t.locale = 'en'", 'left'); }
        if ($query !== '') { $db->group_start()->like(isset($d['translation']) ? 't.title' : 'r.title_en', $query)->or_like('r.slug_en', $query)->group_end(); }
        $count_db = clone $db; $total = $count_db->count_all_results();
        return array('rows' => $db->order_by('r.id', 'DESC')->limit(30, (max(1, $page) - 1) * 30)->get()->result_array(), 'total' => $total, 'pages' => max(1, (int) ceil($total / 30)));
    }
    public function save($type, $id, array $in) {
        $d = $this->authorize($type, $id ? 'update' : 'create');
        $status = (string) ($in['status'] ?? 'draft');
        if (!in_array($status, array('draft', 'review', 'published', 'archived'), true)) { throw new InvalidArgumentException('Invalid publication status.'); }
        if ($status === 'published') { $this->authorize($type, 'publish'); }
        $row = array('status' => $status, 'updated_at' => date('Y-m-d H:i:s'));
        foreach (array('en', 'ar') as $loc) {
            $title = trim((string) ($in['title_' . $loc] ?? '')); $slug = trim((string) ($in['slug_' . $loc] ?? ''));
            if ($title === '' || mb_strlen($title) > 190 || !preg_match('/^[\pL\pN_-]{1,190}$/u', $slug)) { throw new InvalidArgumentException('Both languages need a title and a valid unique address.'); }
            $row['slug_' . $loc] = $slug;
            if ($this->CI->db->where('slug_' . $loc, $slug)->where('id !=', (int) $id)->count_all_results($d['table'])) { throw new InvalidArgumentException('That address is already used.'); }
        }
        $image = trim((string) ($in['image'] ?? '')); Ha_website_studio::safe_url($image); $row[$d['image']] = mb_substr($image, 0, 255) ?: null;
        if ($type === 'programs') { $row['level'] = in_array($in['level'] ?? '', array('foundation', 'intermediate', 'advanced', 'leadership'), true) ? $in['level'] : 'foundation'; $row['duration_hours'] = max(0, min(10000, (float) ($in['duration_hours'] ?? 0))); }
        if ($type === 'paths') { $row['department_code'] = mb_substr(trim((string) ($in['department_code'] ?? '')), 0, 60) ?: null; }
        if ($type === 'topics') {
            $row['topic_type'] = in_array($in['topic_type'] ?? '', array('pillar', 'role', 'city', 'compliance'), true) ? $in['topic_type'] : 'pillar';
            $row['city'] = mb_substr(trim((string) ($in['city'] ?? '')), 0, 120) ?: null;
        }
        $translations = array();
        foreach (array('en', 'ar') as $loc) {
            $t = array('title' => trim($in['title_' . $loc]));
            if ($d['summary']) { $t[$d['summary']] = mb_substr((string) ($in['summary_' . $loc] ?? ''), 0, 500); }
            $t[$d['body']] = hkp_safe_html(mb_substr((string) ($in['body_' . $loc] ?? ''), 0, 100000));
            if (isset($d['translation'])) { $translations[$loc] = $t; }
            else { foreach ($t as $k => $v) { $row[$k . '_' . $loc] = $v; } }
        }
        $courses = array_values(array_unique(array_map('intval', (array) ($in['courses'] ?? array()))));
        if (in_array($type, array('programs', 'topics'), true)) {
            foreach ($courses as $course) { $c = $this->CI->db->get_where('ha_course', array('id' => $course))->row_array(); if (!$c || $c['organization_id'] || $c['property_id']) { throw new InvalidArgumentException('Public programs use global courses only.'); } }
        }
        $this->CI->db->trans_begin();
        try {
            if ($id) {
                $this->CI->db->query('SELECT id FROM ' . $d['table'] . ' WHERE id = ? FOR UPDATE', array((int) $id));
                $old = $this->record($type, $id);
                if (!hash_equals($this->hash($old), (string) ($in['version'] ?? ''))) { throw new DomainException('This record changed. Reload before saving.'); }
                if ($status === 'published' && $old['row']['status'] !== 'published') { $row['published_at'] = date('Y-m-d H:i:s'); }
                $this->CI->db->where('id', (int) $id)->update($d['table'], $row);
            } else {
                $row['created_at'] = date('Y-m-d H:i:s');
                if ($type !== 'articles') { $row['code'] = 'studio-' . bin2hex(random_bytes(8)); }
                if ($status === 'published') { $row['published_at'] = date('Y-m-d H:i:s'); }
                $this->CI->db->insert($d['table'], $row); $id = (int) $this->CI->db->insert_id();
            }
            foreach ($translations as $loc => $t) {
                $exists = $this->CI->db->where(array($d['fk'] => $id, 'locale' => $loc))->count_all_results($d['translation']);
                $exists ? $this->CI->db->where(array($d['fk'] => $id, 'locale' => $loc))->update($d['translation'], $t) : $this->CI->db->insert($d['translation'], $t + array($d['fk'] => $id, 'locale' => $loc));
            }
            if (in_array($type, array('programs', 'topics'), true)) {
                $relation = $type === 'programs' ? 'ha_program_course' : 'ha_topic_course'; $fk = $type === 'programs' ? 'program_id' : 'topic_id';
                $this->CI->db->where($fk, $id)->delete($relation);
                foreach ($courses as $i => $course) { $this->CI->db->insert($relation, array($fk => $id, 'course_id' => $course, 'sort_order' => $i)); }
            }
            if ($type === 'paths' && array_key_exists('steps_json', $in)) { $this->save_steps($id, (string) $in['steps_json']); }
            $this->CI->ha_audit->log($status === 'published' ? 'publish' : 'update', $type, $id, array('description' => 'Catalogue content saved: ' . $row['slug_en'], 'after' => array('status' => $status, 'hash' => hash('sha256', json_encode($row)))));
            if (!$this->CI->db->trans_status()) { throw new RuntimeException('Save failed and was rolled back.'); }
            $this->CI->db->trans_commit(); return $id;
        } catch (Throwable $e) { $this->CI->db->trans_rollback(); throw $e; }
    }
    public function path_items() {
        $this->authorize('paths', 'view'); $db = $this->CI->db;
        $out = array('course' => $db->select('c.id, t.title')->from('ha_course c')->join('ha_course_translation t', "t.course_id=c.id AND t.locale='en'")->where('c.organization_id IS NULL', null, false)->where('c.property_id IS NULL', null, false)->order_by('t.title')->get()->result_array());
        foreach (array('program' => array('ha_program', 'slug_en'), 'sop' => array('ha_sop_document', 'code'), 'assessment' => array('ha_assessment', 'title_en'), 'skill' => array('ha_skill', 'name_en')) as $type => $def) {
            $db->select('id, ' . $def[1] . ' AS title');
            if ($type === 'sop') { $db->where(array('organization_id' => null, 'property_id' => null, 'visibility' => 'public')); }
            if ($type === 'assessment') { $db->where('course_id IS NULL OR course_id IN (SELECT id FROM ha_course WHERE organization_id IS NULL AND property_id IS NULL)', null, false); }
            $out[$type] = $db->order_by($def[1])->get($def[0])->result_array();
        }
        return $out;
    }
    private function save_steps($id, $json) {
        if (strlen($json) > 200000) { throw new InvalidArgumentException('Path structure is too large.'); }
        $steps = json_decode($json, true);
        if (!is_array($steps) || count($steps) > 30) { throw new InvalidArgumentException('Use at most 30 path steps.'); }
        $db = $this->CI->db; $old = $db->get_where('ha_path_step', array('path_id' => $id))->result_array(); $old_ids = array_map('intval', array_column($old, 'id')); $seen = array(); $clean = array(); $allowed = $this->path_items();
        foreach ($steps as $s) {
            $sid = (int) ($s['id'] ?? 0);
            if ($sid && (!in_array($sid, $old_ids, true) || in_array($sid, $seen, true))) { throw new InvalidArgumentException('A path step is missing, repeated, or belongs to another path.'); }
            if ($sid) { $seen[] = $sid; }
            $r = array('path_id' => $id, 'sort_order' => count($clean));
            foreach (array('en', 'ar') as $loc) {
                $title = trim((string) ($s['title_' . $loc] ?? ''));
                if ($title === '' || mb_strlen($title) > 190) { throw new InvalidArgumentException('Each step needs an English and Arabic title.'); }
                $r['title_' . $loc] = $title; $r['description_' . $loc] = mb_substr((string) ($s['description_' . $loc] ?? ''), 0, 5000);
            }
            $items = $s['items'] ?? array(); if (!is_array($items) || count($items) > 100) { throw new InvalidArgumentException('A step supports at most 100 learning items.'); }
            $valid = array();
            foreach ($items as $i => $item) {
                $type = (string) ($item['item_type'] ?? ''); $item_id = (int) ($item['item_id'] ?? 0);
                if (!isset($allowed[$type]) || !in_array($item_id, array_map('intval', array_column($allowed[$type], 'id')), true)) { throw new InvalidArgumentException('Choose an available global learning item.'); }
                $valid[] = array('item_type' => $type, 'item_id' => $item_id, 'is_mandatory' => empty($item['is_mandatory']) ? 0 : 1, 'sort_order' => $i);
            }
            $clean[] = array($sid, $r, $valid);
        }
        $removed = array_diff($old_ids, $seen);
        if ($removed && $db->where('path_id', $id)->count_all_results('ha_path_enrollment')) { throw new InvalidArgumentException('This path has enrollments. Keep existing steps to preserve learner history.'); }
        if ($removed) { $db->where_in('id', $removed)->delete('ha_path_step'); }
        foreach ($clean as $s) {
            list($sid, $r, $items) = $s;
            if ($sid) { $db->where('id', $sid)->update('ha_path_step', $r); } else { $db->insert('ha_path_step', $r); $sid = (int) $db->insert_id(); }
            $db->where('step_id', $sid)->delete('ha_path_step_item');
            foreach ($items as $item) { $db->insert('ha_path_step_item', $item + array('step_id' => $sid)); }
        }
    }
}
