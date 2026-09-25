<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Governed knowledge library (ppt-features 10-12, 27, 32, 33, 60, 62, 95,
 * 114, 150, 157, 164-166).
 *
 * The SOP library is the knowledge library: ha_sop_document is the item (SOP,
 * policy, standard, FAQ, job aid ...), ha_sop_version the immutable versions
 * and ha_sop_version_translation the English and Arabic text of each.
 *
 *   Draft -> Internal review -> Quality review -> Approved -> Published
 *   Published -> (new version) Draft -> ... -> Published, old one Superseded
 *   Published -> Archived
 *
 * Editing never overwrites a published version: it opens a new draft version.
 * Only the published, current version of an item is authoritative, and only
 * that version is written to the search/AI index.
 *
 * ONE visibility rule (visibility_sql) decides what a person may see, and it is
 * used by the library list, direct access, search, recommendations and AI
 * retrieval alike, so a restricted item cannot be discovered by searching for
 * its title or by asking the assistant about it (section 150).
 */
class Ha_knowledge {

    protected $CI;

    public static $types = array('sop', 'policy', 'standard', 'procedure', 'checklist', 'work_instruction', 'faq', 'job_aid',
        'guide', 'reference', 'case_study', 'best_practice', 'knowledge_article');

    public static $sections = array('purpose', 'scope', 'responsibilities', 'required_tools', 'procedure', 'checklist',
        'safety_notes', 'quality_standard', 'escalation', 'related_documents');

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_audit', 'ha_notify'));
    }

    // -------------------------------------------------------------- visibility

    /**
     * SQL condition limiting rows to what the user may read, for any table with
     * organization_id, property_id, department_code and job_role_id columns
     * under $alias (ha_sop_document and ha_ai_chunk both qualify).
     *
     *   organization_id NULL      Altus global content, visible to every client
     *   property_id               only that property (or wider scope over it)
     *   department_code/job_role  only that department / role unless the user's
     *                             grant covers more than their own department
     */
    public function visibility_sql($user_id, $alias) {
        $auth = $this->CI->ha_auth;
        if ($auth->id() !== (int) $user_id) {
            if (!is_cli()) {
                throw new RuntimeException('Visibility is always computed for the signed-in user.');
            }
            $auth->assume($user_id);
        }
        if ($auth->is_system_scoped()) {
            return '1 = 1';
        }
        $p = $auth->profile() ?: array('organization_id' => null, 'property_id' => null, 'department_id' => null, 'job_role_id' => null);
        $db = $this->CI->db;
        $orgs = $auth->organization_ids();
        if ($p['organization_id']) {
            $orgs[] = (int) $p['organization_id'];
        }
        $orgs = array_values(array_unique(array_map('intval', $orgs)));
        $props = $auth->property_ids();
        if ($p['property_id']) {
            $props[] = (int) $p['property_id'];
        }
        $props = array_values(array_unique(array_map('intval', $props)));

        $a = $alias . '.';
        $org_sql = $a . 'organization_id IS NULL' . ($orgs ? ' OR ' . $a . 'organization_id IN (' . implode(',', $orgs) . ')' : '');
        // Organisation-scoped grants see every property of their organisations.
        $scope_orgs = array_map('intval', $auth->organization_ids());
        $prop_parts = array($a . 'property_id IS NULL');
        if ($props) {
            $prop_parts[] = $a . 'property_id IN (' . implode(',', $props) . ')';
        }
        if ($scope_orgs) {
            $prop_parts[] = $a . 'property_id IN (SELECT id FROM ha_property WHERE organization_id IN (' . implode(',', $scope_orgs) . '))';
        }
        $wide = (bool) ($scope_orgs || $auth->property_ids());
        $dept_code = null;
        if (!$wide && $p['department_id']) {
            $d = $db->select('code')->get_where('ha_department', array('id' => $p['department_id']))->row_array();
            $dept_code = $d ? $d['code'] : null;
        }
        $dept_sql = $wide ? '1 = 1' : ($a . 'department_code IS NULL' . ($dept_code ? ' OR ' . $a . 'department_code = ' . $db->escape($dept_code) : ''));
        $role_sql = $wide ? '1 = 1' : ($a . 'job_role_id IS NULL' . ($p['job_role_id'] ? ' OR ' . $a . 'job_role_id = ' . (int) $p['job_role_id'] : ''));
        return '((' . $org_sql . ') AND (' . implode(' OR ', $prop_parts) . ') AND (' . $dept_sql . ') AND (' . $role_sql . '))';
    }

    /** Can the user open this item? Published for readers; drafts only for authors and reviewers in scope. */
    public function can_view($sop_id, $user_id) {
        $sql = $this->visibility_sql($user_id, 'd');
        $row = $this->CI->db->select('d.id, d.status, d.created_by, d.owner_user_id, d.organization_id, d.property_id')->from('ha_sop_document d')
            ->where('d.id', (int) $sop_id)->where($sql, null, false)->get()->row_array();
        if (!$row) {
            return false;
        }
        if ($row['status'] === 'published') {
            return true;
        }
        return $this->can_edit($row, $user_id) || $this->CI->ha_auth->has(array('knowledge.review', 'knowledge.approve', 'knowledge.publish'));
    }

    public function can_edit(array $doc, $user_id) {
        if (!$this->CI->ha_auth->has('knowledge.update')) {
            return false;
        }
        // Global Altus content is edited by system-scoped (Altus) roles only; a property cannot change the master.
        if (empty($doc['organization_id']) && !$this->CI->ha_auth->is_system_scoped()) {
            return false;
        }
        return true;
    }

    // ------------------------------------------------------------------ reads

    public function get($sop_id, $version_id = null) {
        $doc = $this->CI->db->select('d.*, c.name_en AS category_en, c.name_ar AS category_ar, dm.name_en AS domain_en, dm.name_ar AS domain_ar, o.first_name AS owner_first, o.last_name AS owner_last')
            ->from('ha_sop_document d')->join('ha_sop_category c', 'c.id = d.category_id', 'left')
            ->join('ha_domain dm', 'dm.id = d.domain_id', 'left')->join('users o', 'o.id = d.owner_user_id', 'left')
            ->where('d.id', (int) $sop_id)->get()->row_array();
        if (!$doc) {
            return null;
        }
        $doc['versions'] = $this->CI->db->select('v.*, a.first_name AS author_first, a.last_name AS author_last, ap.first_name AS approver_first, ap.last_name AS approver_last')
            ->from('ha_sop_version v')->join('users a', 'a.id = v.author_user_id', 'left')->join('users ap', 'ap.id = v.approver_user_id', 'left')
            ->where('v.sop_id', (int) $sop_id)->order_by('v.version_major', 'DESC')->order_by('v.version_minor', 'DESC')->get()->result_array();
        $vid = $version_id ?: ($doc['current_version_id'] ?: ($doc['versions'] ? $doc['versions'][0]['id'] : null));
        $doc['version'] = null;
        foreach ($doc['versions'] as $v) {
            if ((int) $v['id'] === (int) $vid) {
                $doc['version'] = $v;
            }
        }
        $doc['working'] = $doc['versions'] ? $doc['versions'][0] : null;   // newest version, possibly a draft
        $doc['text'] = array();
        if ($doc['version']) {
            foreach ($this->CI->db->get_where('ha_sop_version_translation', array('version_id' => $doc['version']['id']))->result_array() as $t) {
                $doc['text'][$t['locale']] = $t;
            }
        }
        $doc['reviews'] = $this->CI->db->select('r.*, u.first_name, u.last_name')->from('ha_knowledge_review r')
            ->join('users u', 'u.id = r.actor_user_id', 'left')->where('r.sop_id', (int) $sop_id)->order_by('r.id', 'DESC')->limit(50)->get()->result_array();
        return $doc;
    }

    /** Published items the user may read, with the current version's title in their language. */
    public function visible_items($user_id, array $f = array()) {
        $loc = hkp_locale();
        // Resolve the visibility rule first: it runs its own queries, and CodeIgniter's
        // builder would merge them into this one if they ran mid-build.
        $vis = $this->visibility_sql($user_id, 'd');
        $db = $this->CI->db->select("d.id, d.code, d.item_type, d.domain_id, d.property_id, d.organization_id, d.is_mandatory, d.updated_at, v.version_label, v.effective_date, v.review_date,
                COALESCE(NULLIF(t.title, ''), te.title) AS title, COALESCE(NULLIF(t.purpose, ''), te.purpose) AS summary", false)
            ->from('ha_sop_document d')->join('ha_sop_version v', 'v.id = d.current_version_id')
            ->join('ha_sop_version_translation t', 't.version_id = v.id AND t.locale = ' . $this->CI->db->escape($loc), 'left')
            ->join('ha_sop_version_translation te', "te.version_id = v.id AND te.locale = 'en'", 'left')
            ->where('d.status', 'published')->where($vis, null, false);
        if (!empty($f['type'])) {
            $db->where('d.item_type', $f['type']);
        }
        if (!empty($f['domain_ids'])) {
            $db->where_in('d.domain_id', array_map('intval', (array) $f['domain_ids']));
        }
        if (!empty($f['domain_id'])) {
            $db->where('d.domain_id', (int) $f['domain_id']);
        }
        if (!empty($f['q'])) {
            $q = '%' . $this->CI->db->escape_like_str($f['q']) . '%';
            $db->where('(t.title LIKE ' . $this->CI->db->escape($q) . ' OR te.title LIKE ' . $this->CI->db->escape($q) . ' OR d.code LIKE ' . $this->CI->db->escape($q) . ')', null, false);
        }
        return $db->order_by('title')->limit(isset($f['limit']) ? (int) $f['limit'] : 200)->get()->result_array();
    }

    /** Items in the review pipeline within the user's scope (quality control dashboard). */
    public function pipeline($user_id) {
        $sql = $this->visibility_sql($user_id, 'd');
        $rows = $this->CI->db->select("d.id, d.code, d.item_type, d.status AS doc_status, d.owner_user_id, d.organization_id, d.property_id, d.review_interval_months,
                v.id AS version_id, v.version_label, v.status, v.review_date, v.updated_at, v.author_user_id,
                te.title AS title_en, ta.title AS title_ar", false)
            ->from('ha_sop_version v')->join('ha_sop_document d', 'd.id = v.sop_id')
            ->join('ha_sop_version_translation te', "te.version_id = v.id AND te.locale = 'en'", 'left')
            ->join('ha_sop_version_translation ta', "ta.version_id = v.id AND ta.locale = 'ar'", 'left')
            ->where_in('v.status', array('draft', 'internal_review', 'quality_review', 'review', 'approved', 'rejected'))
            ->where($sql, null, false)->order_by('v.updated_at', 'DESC')->limit(300)->get()->result_array();
        return $rows;
    }

    // ------------------------------------------------------------------ writes

    /**
     * Creates an item with its first draft version (1.0).
     * $data: code, item_type, category_id, domain_id, organization_id, property_id, department_code,
     *        job_role_id, visibility, is_mandatory, requires_acknowledgement, review_interval_months,
     *        reviewer_user_id, approver_user_id, tags, title_en, title_ar, sections[en|ar][field]
     */
    public function create(array $data, $actor_id) {
        if (!$this->CI->ha_auth->has('knowledge.create')) {
            throw new RuntimeException('You do not have permission to create knowledge.');
        }
        $title_en = trim((string) (isset($data['title_en']) ? $data['title_en'] : ''));
        if ($title_en === '') {
            throw new InvalidArgumentException('An English title is required.');
        }
        $type = isset($data['item_type']) && in_array($data['item_type'], self::$types, true) ? $data['item_type'] : 'sop';
        $org = isset($data['organization_id']) && $data['organization_id'] !== '' ? (int) $data['organization_id'] : null;
        $prop = isset($data['property_id']) && $data['property_id'] !== '' ? (int) $data['property_id'] : null;
        $auth = $this->CI->ha_auth;
        if ($org === null && !$auth->is_system_scoped()) {
            $org = $auth->default_organization_id();   // client staff author client content, never global content
        }
        if ($prop === null && $org !== null && !$auth->can_organization($org)) {
            $mine = $auth->profile();
            if ($mine && $mine['property_id'] && $auth->can_property($mine['property_id'])) {
                $prop = (int) $mine['property_id'];   // a property-scoped author writes for their own property
            }
        }
        if ($org !== null && !$auth->can_organization($org) && !($prop && $auth->can_property($prop))) {
            throw new RuntimeException('You cannot create content for that organisation.');
        }
        if ($prop !== null && !$auth->can_property($prop)) {
            throw new RuntimeException('You cannot create content for that property.');
        }
        $code = trim((string) (isset($data['code']) ? $data['code'] : ''));
        if ($code === '') {
            $code = $type . '-' . substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($title_en)), 0, 50) . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
        }
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($code)), '-');
        if ($this->CI->db->where('code', $code)->count_all_results('ha_sop_document')) {
            throw new InvalidArgumentException('Another item already uses the code ' . $code . '.');
        }
        $now = date('Y-m-d H:i:s');
        $this->CI->db->trans_start();
        $this->CI->db->insert('ha_sop_document', array(
            'code' => $code, 'item_type' => $type, 'slug_en' => $slug, 'slug_ar' => $slug . '-ar',
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'domain_id' => !empty($data['domain_id']) ? (int) $data['domain_id'] : null,
            'organization_id' => $org, 'property_id' => $prop,
            'department_code' => !empty($data['department_code']) ? mb_substr($data['department_code'], 0, 60) : null,
            'job_role_id' => !empty($data['job_role_id']) ? (int) $data['job_role_id'] : null,
            'owner_user_id' => !empty($data['owner_user_id']) ? (int) $data['owner_user_id'] : (int) $actor_id,
            'reviewer_user_id' => !empty($data['reviewer_user_id']) ? (int) $data['reviewer_user_id'] : null,
            'approver_user_id' => !empty($data['approver_user_id']) ? (int) $data['approver_user_id'] : null,
            'visibility' => isset($data['visibility']) && in_array($data['visibility'], array('private', 'organization', 'public'), true) ? $data['visibility'] : 'organization',
            'is_mandatory' => !empty($data['is_mandatory']) ? 1 : 0, 'requires_acknowledgement' => !empty($data['requires_acknowledgement']) ? 1 : 0,
            'review_interval_months' => isset($data['review_interval_months']) ? max(1, (int) $data['review_interval_months']) : 12,
            'ai_enabled' => isset($data['ai_enabled']) ? (int) (bool) $data['ai_enabled'] : 1,
            'tags' => isset($data['tags']) ? mb_substr((string) $data['tags'], 0, 255) : null,
            'status' => 'draft', 'created_by' => (int) $actor_id, 'created_at' => $now, 'updated_at' => $now,
        ));
        $sop_id = (int) $this->CI->db->insert_id();
        $vid = $this->insert_version($sop_id, 1, 0, $actor_id, 'Initial version', $data);
        $this->review_log($sop_id, $vid, $actor_id, 'create', null, 'draft', null);
        $this->CI->db->trans_complete();
        $this->CI->ha_audit->log('create', 'knowledge', $sop_id, array('description' => 'Knowledge item ' . $code . ' created',
            'organization_id' => $org, 'property_id' => $prop));
        return $sop_id;
    }

    protected function insert_version($sop_id, $major, $minor, $actor_id, $summary, array $data) {
        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_sop_version', array(
            'sop_id' => (int) $sop_id, 'version_label' => $major . '.' . $minor, 'version_major' => $major, 'version_minor' => $minor,
            'change_summary' => $summary ? mb_substr($summary, 0, 500) : null, 'author_user_id' => (int) $actor_id,
            'status' => 'draft', 'effective_date' => !empty($data['effective_date']) ? date('Y-m-d', strtotime($data['effective_date'])) : null,
            'review_date' => !empty($data['review_date']) ? date('Y-m-d', strtotime($data['review_date'])) : date('Y-m-d', strtotime('+12 months')),
            'created_at' => $now, 'updated_at' => $now));
        $vid = (int) $this->CI->db->insert_id();
        foreach (array('en', 'ar') as $loc) {
            $this->CI->db->insert('ha_sop_version_translation', $this->translation_row($vid, $loc, $data));
        }
        return $vid;
    }

    protected function translation_row($vid, $loc, array $data) {
        $row = array('version_id' => (int) $vid, 'locale' => $loc,
            'title' => mb_substr(trim((string) (isset($data['title_' . $loc]) ? $data['title_' . $loc] : '')), 0, 190));
        foreach (self::$sections as $s) {
            $row[$s] = isset($data['sections'][$loc][$s]) ? (string) $data['sections'][$loc][$s] : null;
        }
        return $row;
    }

    /** Edits the working draft. A published version is never edited: open a new version first. */
    public function update_draft($sop_id, array $data, $actor_id) {
        $doc = $this->get($sop_id);
        if (!$doc || !$this->can_edit($doc, $actor_id)) {
            throw new RuntimeException('You cannot edit this item.');
        }
        $w = $doc['working'];
        if (!$w || !in_array($w['status'], array('draft', 'rejected'), true)) {
            throw new RuntimeException('Only a draft can be edited. Create a new version of the published item to change it.');
        }
        $before = $this->CI->db->get_where('ha_sop_version_translation', array('version_id' => $w['id']))->result_array();
        foreach (array('en', 'ar') as $loc) {
            $row = $this->translation_row($w['id'], $loc, $data);
            unset($row['version_id'], $row['locale']);
            $this->CI->db->where(array('version_id' => $w['id'], 'locale' => $loc))->update('ha_sop_version_translation', $row);
        }
        $vupd = array('status' => 'draft', 'updated_at' => date('Y-m-d H:i:s'));
        if (isset($data['change_summary'])) {
            $vupd['change_summary'] = mb_substr((string) $data['change_summary'], 0, 500);
        }
        foreach (array('effective_date', 'review_date') as $k) {
            if (!empty($data[$k])) {
                $vupd[$k] = date('Y-m-d', strtotime($data[$k]));
            }
        }
        $this->CI->db->where('id', $w['id'])->update('ha_sop_version', $vupd);
        $meta = array();
        foreach (array('item_type', 'domain_id', 'department_code', 'job_role_id', 'reviewer_user_id', 'approver_user_id', 'is_mandatory', 'requires_acknowledgement', 'tags', 'ai_enabled', 'review_interval_months') as $k) {
            if (array_key_exists($k, $data)) {
                $meta[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }
        if (isset($meta['item_type']) && !in_array($meta['item_type'], self::$types, true)) {
            unset($meta['item_type']);
        }
        if ($meta) {
            $meta['updated_at'] = date('Y-m-d H:i:s');
            $this->CI->db->where('id', (int) $sop_id)->update('ha_sop_document', $meta);
        }
        $this->CI->ha_audit->log('update', 'knowledge_version', (int) $w['id'], array('description' => 'Draft ' . $w['version_label'] . ' of ' . $doc['code'] . ' edited',
            'before' => array('text' => $before), 'organization_id' => $doc['organization_id'], 'property_id' => $doc['property_id']));
    }

    /** Opens a new draft from the current published version (minor bump, or major when asked). */
    public function new_version($sop_id, $actor_id, $major = false, $summary = null) {
        $doc = $this->get($sop_id);
        if (!$doc || !$this->can_edit($doc, $actor_id)) {
            throw new RuntimeException('You cannot revise this item.');
        }
        $w = $doc['working'];
        if ($w && in_array($w['status'], array('draft', 'internal_review', 'quality_review', 'review', 'approved', 'rejected'), true)) {
            return (int) $w['id'];   // a revision is already open
        }
        $base = $doc['version'] ?: $w;
        $data = array('sections' => array());
        foreach ($this->CI->db->get_where('ha_sop_version_translation', array('version_id' => $base['id']))->result_array() as $t) {
            $data['title_' . $t['locale']] = $t['title'];
            foreach (self::$sections as $s) {
                $data['sections'][$t['locale']][$s] = $t[$s];
            }
        }
        $ma = (int) $base['version_major'];
        $mi = (int) $base['version_minor'];
        $vid = $major ? $this->insert_version($sop_id, $ma + 1, 0, $actor_id, $summary, $data) : $this->insert_version($sop_id, $ma, $mi + 1, $actor_id, $summary, $data);
        $this->review_log($sop_id, $vid, $actor_id, 'new_version', $base['status'], 'draft', $summary);
        return $vid;
    }

    /**
     * Moves the working version through the workflow.
     * action: submit | approve_internal | approve_quality | publish | reject | archive
     */
    public function act($sop_id, $action, $actor_id, $comment = null) {
        $doc = $this->get($sop_id);
        if (!$doc) {
            throw new InvalidArgumentException('Item not found.');
        }
        if (!$this->can_view($sop_id, $actor_id)) {
            throw new RuntimeException('That item is outside your scope.');
        }
        $w = $doc['working'];
        $auth = $this->CI->ha_auth;
        $flow = array(
            'submit'           => array(array('draft', 'rejected'), 'internal_review', 'knowledge.update'),
            'approve_internal' => array(array('internal_review', 'review'), 'quality_review', 'knowledge.review'),
            'approve_quality'  => array(array('quality_review'), 'approved', 'knowledge.approve'),
            'publish'          => array(array('approved'), 'published', 'knowledge.publish'),
            'reject'           => array(array('internal_review', 'quality_review', 'review', 'approved'), 'rejected', 'knowledge.review'),
            'archive'          => array(array('published', 'superseded', 'draft', 'rejected'), 'archived', 'knowledge.archive'),
        );
        if (!isset($flow[$action])) {
            throw new InvalidArgumentException('Unknown action.');
        }
        list($from, $to, $perm) = $flow[$action];
        if ($action === 'archive') {
            $w = $doc['version'] ?: $w;
        }
        if (!$w || !in_array($w['status'], $from, true)) {
            throw new RuntimeException('The current version is ' . str_replace('_', ' ', $w ? $w['status'] : 'missing') . '; it cannot be ' . str_replace('_', ' ', $action) . 'd now.');
        }
        if (!$auth->has($perm)) {
            throw new RuntimeException('You need the ' . $perm . ' permission for that step.');
        }
        if (in_array($action, array('approve_internal', 'approve_quality', 'publish'), true) && (int) $w['author_user_id'] === (int) $actor_id && !$auth->is_super_admin()) {
            throw new RuntimeException('The author of a version cannot review, approve or publish it. Ask a colleague.');
        }
        if ($action === 'reject' && trim((string) $comment) === '') {
            throw new InvalidArgumentException('Say what needs to change when sending a version back.');
        }
        if ($action === 'submit' && trim((string) (isset($doc['text']['en']['title']) ? $doc['text']['en']['title'] : $this->title_of($w['id'], 'en'))) === '') {
            throw new InvalidArgumentException('Add an English title before submitting.');
        }
        $now = date('Y-m-d H:i:s');
        $this->CI->db->trans_start();
        $vupd = array('status' => $to, 'updated_at' => $now);
        if ($action === 'approve_quality') {
            $vupd['approver_user_id'] = (int) $actor_id;
            $vupd['approved_at'] = $now;
        }
        if ($action === 'publish') {
            $vupd['published_at'] = $now;
            if (!$w['effective_date']) {
                $vupd['effective_date'] = date('Y-m-d');
            }
            if ($doc['current_version_id'] && (int) $doc['current_version_id'] !== (int) $w['id']) {
                $this->CI->db->where('id', (int) $doc['current_version_id'])->update('ha_sop_version', array('status' => 'superseded', 'superseded_at' => $now, 'updated_at' => $now));
                $this->CI->db->where(array('sop_id' => (int) $sop_id, 'status' => 'required'))->update('ha_sop_acknowledgement', array('status' => 'superseded', 'updated_at' => $now));
            }
        }
        $this->CI->db->where('id', (int) $w['id'])->update('ha_sop_version', $vupd);
        $dupd = array('updated_at' => $now);
        if ($action === 'publish') {
            $dupd['status'] = 'published';
            $dupd['current_version_id'] = (int) $w['id'];
        } elseif ($action === 'archive') {
            $dupd['status'] = 'archived';
        } elseif ($doc['status'] !== 'published') {
            $dupd['status'] = $to;
        }
        $this->CI->db->where('id', (int) $sop_id)->update('ha_sop_document', $dupd);
        $this->review_log($sop_id, $w['id'], $actor_id, $action === 'reject' ? 'reject' : $action, $w['status'], $to, $comment);
        $this->CI->db->trans_complete();

        $this->CI->ha_audit->log($action === 'publish' ? 'publish' : ($action === 'reject' ? 'reject' : ($action === 'archive' ? 'update' : 'approve')), 'knowledge', (int) $sop_id,
            array('description' => $doc['code'] . ' v' . $w['version_label'] . ': ' . $w['status'] . ' -> ' . $to, 'before' => array('status' => $w['status']),
                'after' => array('status' => $to), 'organization_id' => $doc['organization_id'], 'property_id' => $doc['property_id']));

        // Governance side effects: the index only ever holds the current published version.
        $this->CI->load->library('ha_governed_ai');
        if ($action === 'publish') {
            $this->CI->ha_governed_ai->index_knowledge($sop_id);
            if ((int) $doc['is_mandatory'] && (int) $doc['requires_acknowledgement']) {
                $this->require_acknowledgement($sop_id, (int) $w['id'], $w['version_label']);
            }
        } elseif ($action === 'archive') {
            $this->CI->ha_governed_ai->deindex('knowledge', $sop_id);
        }
        $title = $this->title_of($w['id'], 'en');
        if ($action === 'submit' && $doc['reviewer_user_id']) {
            $this->CI->ha_notify->send($doc['reviewer_user_id'], 'knowledge.submitted', array('title' => $title, 'version' => $w['version_label'],
                'author' => $auth->display_name(), 'url' => hkp_url('knowledge/item/' . (int) $sop_id), '_no_manager' => 1));
        }
        if ($action === 'reject' && $w['author_user_id']) {
            $this->CI->ha_notify->send($w['author_user_id'], 'knowledge.rejected', array('title' => $title, 'comment' => (string) $comment,
                'url' => hkp_url('knowledge/item/' . (int) $sop_id), '_no_manager' => 1));
        }
        return $this->get($sop_id);
    }

    protected function title_of($version_id, $loc) {
        $t = $this->CI->db->select('title')->get_where('ha_sop_version_translation', array('version_id' => (int) $version_id, 'locale' => $loc))->row_array();
        return $t ? $t['title'] : '';
    }

    public function comment($sop_id, $actor_id, $comment) {
        if (trim((string) $comment) === '') {
            throw new InvalidArgumentException('Write a comment.');
        }
        if (!$this->can_view($sop_id, $actor_id)) {
            throw new RuntimeException('That item is outside your scope.');
        }
        $doc = $this->get($sop_id);
        $this->review_log($sop_id, $doc['working'] ? $doc['working']['id'] : null, $actor_id, 'comment', null, null, $comment);
    }

    protected function review_log($sop_id, $version_id, $actor_id, $action, $from, $to, $comment) {
        $this->CI->db->insert('ha_knowledge_review', array('sop_id' => (int) $sop_id, 'version_id' => $version_id ? (int) $version_id : null,
            'actor_user_id' => $actor_id ? (int) $actor_id : null, 'action' => $action, 'from_status' => $from, 'to_status' => $to,
            'comment' => $comment, 'created_at' => date('Y-m-d H:i:s')));
    }

    /** Everyone the item applies to must acknowledge the new version. */
    protected function require_acknowledgement($sop_id, $version_id, $label) {
        $doc = $this->CI->db->get_where('ha_sop_document', array('id' => (int) $sop_id))->row_array();
        $db = $this->CI->db->select('p.user_id')->from('ha_profile p')->where('p.status', 'active');
        if ($doc['organization_id']) {
            $db->where('p.organization_id', (int) $doc['organization_id']);
        }
        if ($doc['property_id']) {
            $db->where('p.property_id', (int) $doc['property_id']);
        }
        if ($doc['job_role_id']) {
            $db->where('p.job_role_id', (int) $doc['job_role_id']);
        }
        if ($doc['department_code']) {
            $db->join('ha_department dd', 'dd.id = p.department_id')->where('dd.code', $doc['department_code']);
        }
        $users = array_column($db->get()->result_array(), 'user_id');
        $now = date('Y-m-d H:i:s');
        $title = $this->title_of($version_id, 'en');
        foreach ($users as $uid) {
            $exists = $this->CI->db->get_where('ha_sop_acknowledgement', array('version_id' => (int) $version_id, 'user_id' => (int) $uid))->row_array();
            if ($exists) {
                continue;
            }
            $this->CI->db->insert('ha_sop_acknowledgement', array('sop_id' => (int) $sop_id, 'version_id' => (int) $version_id, 'user_id' => (int) $uid,
                'status' => 'required', 'due_at' => date('Y-m-d H:i:s', strtotime('+7 days')), 'created_at' => $now, 'updated_at' => $now));
            $this->CI->ha_notify->send($uid, 'knowledge.mandatory', array('title' => $title, 'version' => $label,
                'url' => hkp_url('knowledge/item/' . (int) $sop_id), 'related_type' => 'knowledge', 'related_id' => (int) $sop_id, '_no_manager' => 1));
        }
        return count($users);
    }

    public function acknowledge($sop_id, $user_id) {
        $doc = $this->CI->db->get_where('ha_sop_document', array('id' => (int) $sop_id, 'status' => 'published'))->row_array();
        if (!$doc || !$this->can_view($sop_id, $user_id)) {
            throw new RuntimeException('That item is not available to you.');
        }
        $vid = (int) $doc['current_version_id'];
        $now = date('Y-m-d H:i:s');
        $row = $this->CI->db->get_where('ha_sop_acknowledgement', array('version_id' => $vid, 'user_id' => (int) $user_id))->row_array();
        $data = array('status' => 'acknowledged', 'acknowledged_at' => $now, 'updated_at' => $now,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 64) : 'cli',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null);
        if ($row) {
            $this->CI->db->where('id', $row['id'])->update('ha_sop_acknowledgement', $data);
        } else {
            $this->CI->db->insert('ha_sop_acknowledgement', $data + array('sop_id' => (int) $sop_id, 'version_id' => $vid, 'user_id' => (int) $user_id, 'created_at' => $now));
        }
        $this->CI->ha_audit->log('sop_acknowledge', 'knowledge', (int) $sop_id, array('description' => 'Acknowledged version ' . $vid));
    }

    public function acknowledgement($sop_id, $user_id) {
        $doc = $this->CI->db->select('current_version_id')->get_where('ha_sop_document', array('id' => (int) $sop_id))->row_array();
        if (!$doc || !$doc['current_version_id']) {
            return null;
        }
        return $this->CI->db->get_where('ha_sop_acknowledgement', array('version_id' => (int) $doc['current_version_id'], 'user_id' => (int) $user_id))->row_array();
    }

    public function record_view($entity, $id, $user_id) {
        $this->CI->db->insert('ha_content_view', array('user_id' => $user_id ? (int) $user_id : null, 'entity_type' => $entity,
            'entity_id' => (int) $id, 'created_at' => date('Y-m-d H:i:s')));
    }

    // ------------------------------------------------------------------- diff

    /** Field-by-field, line-by-line comparison of two versions (LCS diff). */
    public function compare($version_a, $version_b) {
        $ta = array();
        $tb = array();
        foreach ($this->CI->db->get_where('ha_sop_version_translation', array('version_id' => (int) $version_a))->result_array() as $t) {
            $ta[$t['locale']] = $t;
        }
        foreach ($this->CI->db->get_where('ha_sop_version_translation', array('version_id' => (int) $version_b))->result_array() as $t) {
            $tb[$t['locale']] = $t;
        }
        $out = array();
        foreach (array('en', 'ar') as $loc) {
            foreach (array_merge(array('title'), self::$sections) as $f) {
                $a = isset($ta[$loc][$f]) ? (string) $ta[$loc][$f] : '';
                $b = isset($tb[$loc][$f]) ? (string) $tb[$loc][$f] : '';
                if ($a === $b) {
                    continue;
                }
                $out[] = array('locale' => $loc, 'field' => $f, 'lines' => $this->diff_lines(preg_split('/\r?\n/', $a), preg_split('/\r?\n/', $b)));
            }
        }
        return $out;
    }

    public function diff_lines(array $a, array $b) {
        $n = count($a);
        $m = count($b);
        if ($n * $m > 250000) {   // guard very long texts: show as replaced
            $out = array();
            foreach ($a as $l) {
                $out[] = array('-', $l);
            }
            foreach ($b as $l) {
                $out[] = array('+', $l);
            }
            return $out;
        }
        $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $L[$i][$j] = $a[$i] === $b[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
            }
        }
        $out = array();
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out[] = array('=', $a[$i]);
                $i++;
                $j++;
            } elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) {
                $out[] = array('-', $a[$i++]);
            } else {
                $out[] = array('+', $b[$j++]);
            }
        }
        while ($i < $n) {
            $out[] = array('-', $a[$i++]);
        }
        while ($j < $m) {
            $out[] = array('+', $b[$j++]);
        }
        return $out;
    }

    // ------------------------------------------------------------------ search

    /**
     * Permission-aware search over the approved index (published knowledge
     * versions and published lessons), plus visible assessments by title.
     * Uses exactly the same visibility rule as direct access.
     */
    public function search($user_id, $term, array $f = array()) {
        $term = trim((string) $term);
        if (mb_strlen($term) < 2) {
            return array();
        }
        $this->CI->load->library('ha_governed_ai');
        $hits = $this->CI->ha_governed_ai->retrieve($user_id, $term, array('limit' => isset($f['limit']) ? (int) $f['limit'] : 30,
            'min_relevance' => 0.01, 'locale' => isset($f['locale']) ? $f['locale'] : null, 'type' => isset($f['type']) ? $f['type'] : null,
            'domain_id' => isset($f['domain_id']) ? $f['domain_id'] : null));
        $results = array();
        foreach ($hits as $h) {
            $key = $h['source_type'] . ':' . $h['source_id'];
            if (isset($results[$key])) {
                continue;
            }
            $results[$key] = array('type' => $h['source_type'], 'item_type' => isset($h['item_type']) ? $h['item_type'] : 'lesson', 'id' => (int) $h['source_id'],
                'title' => $h['title'], 'excerpt' => $this->excerpt($h['body'], $term), 'version' => $h['version_label'], 'locale' => $h['locale'],
                'scope' => $h['property_id'] ? 'property' : ($h['organization_id'] ? 'organization' : 'global'), 'updated' => substr((string) $h['indexed_at'], 0, 10),
                'url' => $h['source_type'] === 'knowledge' ? hkp_url('knowledge/item/' . (int) $h['source_id']) : hkp_url('learn/lesson/' . (int) $h['source_id']),
                'score' => $h['score']);
        }
        if (empty($f['type']) || $f['type'] === 'assessment') {
            $q = '%' . $this->CI->db->escape_like_str($term) . '%';
            $p = $this->CI->ha_auth->profile();
            $rows = $this->CI->db->select('a.id, a.title_en, a.title_ar, a.updated_at')->from('ha_assessment a')->where('a.status', 'published')
                ->group_start()->like('a.title_en', $term)->or_like('a.title_ar', $term)->group_end()
                ->group_start()->where('a.organization_id IS NULL', null, false)->or_where('a.organization_id', $p ? (int) $p['organization_id'] : 0)->group_end()
                ->limit(5)->get()->result_array();
            foreach ($rows as $r) {
                $results['assessment:' . $r['id']] = array('type' => 'assessment', 'item_type' => 'assessment', 'id' => (int) $r['id'], 'title' => hkp_pick($r, 'title'),
                    'excerpt' => '', 'version' => null, 'locale' => hkp_locale(), 'scope' => 'global', 'updated' => substr($r['updated_at'], 0, 10),
                    'url' => hkp_url('assess/theory/' . (int) $r['id']), 'score' => 0.5);
            }
        }
        $this->CI->db->insert('ha_search_log', array('user_id' => (int) $user_id, 'organization_id' => $this->CI->ha_auth->default_organization_id(),
            'term' => mb_substr($term, 0, 190), 'locale' => hkp_locale(), 'results_count' => count($results), 'created_at' => date('Y-m-d H:i:s')));
        return array_values($results);
    }

    public function excerpt($body, $term, $len = 220) {
        $body = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $body)));
        $pos = mb_stripos($body, $term);
        if ($pos === false) {
            $words = preg_split('/\s+/u', $term);
            foreach ($words as $w) {
                if (mb_strlen($w) > 2 && ($p = mb_stripos($body, $w)) !== false) {
                    $pos = $p;
                    break;
                }
            }
        }
        $start = $pos === false ? 0 : max(0, $pos - 60);
        return ($start > 0 ? '…' : '') . mb_substr($body, $start, $len) . (mb_strlen($body) > $start + $len ? '…' : '');
    }

    // ------------------------------------------------------------------ health

    /** Content health (sections 60, 166): what needs an editor's attention. */
    public function health($user_id) {
        $sql = $this->visibility_sql($user_id, 'd');
        $db = $this->CI->db;
        $base = function () use ($db, $sql) {
            return $db->select("d.id, d.code, d.item_type, d.status, d.owner_user_id, v.version_label, v.review_date, te.title AS title_en, ta.title AS title_ar", false)
                ->from('ha_sop_document d')->join('ha_sop_version v', 'v.id = d.current_version_id', 'left')
                ->join('ha_sop_version_translation te', "te.version_id = v.id AND te.locale = 'en'", 'left')
                ->join('ha_sop_version_translation ta', "ta.version_id = v.id AND ta.locale = 'ar'", 'left')
                ->where($sql, null, false)->where('d.status !=', 'archived');
        };
        $this->CI->load->library('ha_tenant');
        $warn = (int) $this->CI->ha_tenant->get('knowledge.review_warning_days');
        $unused_days = (int) $this->CI->ha_tenant->get('knowledge.unused_days');
        $out = array();
        $out['no_owner'] = $base()->where('d.owner_user_id IS NULL', null, false)->limit(100)->get()->result_array();
        $out['review_due'] = $base()->where('d.status', 'published')->where('v.review_date <=', date('Y-m-d', strtotime('+' . $warn . ' days')))->limit(100)->get()->result_array();
        $out['expired'] = $base()->where('d.expires_at IS NOT NULL', null, false)->where('d.expires_at <', date('Y-m-d'))->limit(100)->get()->result_array();
        $out['untranslated'] = $base()->where('d.status', 'published')->group_start()->where('ta.title IS NULL', null, false)->or_where('ta.title', '')
            ->or_where("(ta.`procedure` IS NULL OR ta.`procedure` = '') AND (te.`procedure` IS NOT NULL AND te.`procedure` != '')", null, false)->group_end()->limit(200)->get()->result_array();
        $out['duplicates'] = $db->query("SELECT te.title AS title_en, COUNT(*) n, GROUP_CONCAT(d.id) ids FROM ha_sop_document d
            JOIN ha_sop_version_translation te ON te.version_id = d.current_version_id AND te.locale = 'en'
            WHERE d.status = 'published' AND " . $sql . " GROUP BY te.title HAVING n > 1 LIMIT 50")->result_array();
        $out['unused'] = $base()->where('d.status', 'published')
            ->where('d.id NOT IN (SELECT entity_id FROM ha_content_view WHERE entity_type = \'knowledge\' AND created_at >= ' . $db->escape(date('Y-m-d H:i:s', strtotime('-' . $unused_days . ' days'))) . ')', null, false)
            ->limit(100)->get()->result_array();
        $out['top_searches'] = $db->select('term, COUNT(*) n, SUM(results_count = 0) zero', false)->where('created_at >=', date('Y-m-d H:i:s', strtotime('-90 days')))
            ->group_by('term')->order_by('n', 'DESC')->limit(15)->get('ha_search_log')->result_array();
        $out['ai_sources'] = $this->ai_source_usage();
        $out['pipeline'] = $this->pipeline($user_id);
        return $out;
    }

    public function ai_source_usage() {
        $rows = $this->CI->db->select('sources_json')->where('coverage', 'answered')->where('created_at >=', date('Y-m-d H:i:s', strtotime('-90 days')))
            ->order_by('id', 'DESC')->limit(2000)->get('ha_ai_query')->result_array();
        $count = array();
        foreach ($rows as $r) {
            foreach ((array) json_decode((string) $r['sources_json'], true) as $s) {
                if (isset($s['source_type'], $s['source_id'])) {
                    $k = $s['source_type'] . ':' . $s['source_id'];
                    if (!isset($count[$k])) {
                        $count[$k] = array('title' => isset($s['title']) ? $s['title'] : $k, 'n' => 0, 'type' => $s['source_type'], 'id' => (int) $s['source_id']);
                    }
                    $count[$k]['n']++;
                }
            }
        }
        usort($count, function ($a, $b) { return $b['n'] - $a['n']; });
        return array_slice($count, 0, 20);
    }
}
