<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/Ha_gateway.php';   // Ha_api_error

/**
 * MCP tool catalogue for the native PHP server. Every handler calls the shared
 * native services (Ha_publishing_service, Ha_mcp_content and the studio
 * libraries) — no SQL or shell here, no permanent deletes.
 */
class Ha_mcp_tools {
    const CATALOGUE = array('articles', 'topics', 'programs', 'paths', 'courses');
    const COURSE_TYPES = array('courses', 'course_sections', 'lessons', 'quizzes', 'questions');
    const READ_TYPES = array('page', 'page_sections', 'articles', 'topics', 'programs', 'paths', 'courses', 'course_sections', 'lessons', 'quizzes', 'questions', 'sops', 'media', 'navigation', 'site', 'publisher');
    const CREATE_TYPES = array('page', 'articles', 'topics', 'programs', 'paths', 'courses', 'course_sections', 'lessons', 'quizzes', 'questions', 'sops');
    const UPDATE_TYPES = array('page', 'articles', 'topics', 'programs', 'paths', 'courses', 'site', 'navigation', 'course_sections', 'lessons', 'quizzes', 'questions', 'sops', 'publisher');
    const APPROVAL_TYPES = array('page', 'articles', 'topics', 'programs', 'paths', 'courses', 'site', 'navigation', 'lessons', 'quizzes');
    private $CI; private $tools = null;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_publishing_service', 'ha_mcp_content', 'ha_page_builder'));
    }
    private function P() { return $this->CI->ha_publishing_service; }
    private function C() { return $this->CI->ha_mcp_content; }

    // ------------------------------------------------------------------ schema helpers
    private static function idem() { return array('type' => 'string', 'minLength' => 8, 'maxLength' => 100, 'pattern' => '^[A-Za-z0-9_-]+$', 'description' => 'Optional but recommended. Re-sending the same key with the same arguments returns the first result instead of repeating the write.'); }
    private static function ver() { return array('type' => array('integer', 'string'), 'description' => 'The "version" you last read (altus_get). The write fails with a conflict error, including the current version, if the object changed.'); }
    private static function id($d = 'Object id') { return array('type' => 'integer', 'minimum' => 1, 'description' => $d); }
    private static function obj(array $props, array $required = array()) { return array('type' => 'object', 'properties' => (object) $props, 'required' => $required, 'additionalProperties' => false); }
    private static function ann($title, $read, $destructive = false, $idempotent = false) { return array('title' => $title, 'readOnlyHint' => $read, 'destructiveHint' => $destructive, 'idempotentHint' => $idempotent, 'openWorldHint' => false); }

    /** name => definition. scope: string or callable(args) => scope. */
    public function definitions() {
        if ($this->tools !== null) return $this->tools;
        $types = function (array $t) { return array('type' => 'string', 'enum' => $t); };
        $data = array('type' => 'object', 'description' => 'Fields to set. Updates are partial: only the fields you send change. See altus_get output for field names.');
        $by_type = function ($a) { return in_array($a['type'] ?? '', self::COURSE_TYPES, true) ? 'altus.course.write' : 'altus.content.write'; };
        $t = array();
        $t['altus_site_info'] = array('description' => 'Who you are connected as, your granted and effective scopes, native publishing health, content types and limits. Call this first.',
            'schema' => self::obj(array()), 'scope' => 'altus.read', 'annotations' => self::ann('Site info and health', true, false, true), 'run' => function ($a, $ctx) { return $this->site_info($ctx); });
        $t['altus_search'] = array('description' => 'Search titles across pages, articles, topics, programs, learning paths, courses and SOPs you can read.',
            'schema' => self::obj(array('query' => array('type' => 'string', 'minLength' => 2, 'maxLength' => 100), 'types' => array('type' => 'array', 'items' => $types(array('page', 'articles', 'topics', 'programs', 'paths', 'courses', 'sops')), 'maxItems' => 7)), array('query')),
            'scope' => 'altus.read', 'annotations' => self::ann('Search content', true, false, true), 'run' => function ($a) { return $this->search($a); });
        $t['altus_list'] = array('description' => 'List objects of one type. parent_id is required for page_sections (page id), course_sections/lessons/quizzes (course id) and questions (quiz id).',
            'schema' => self::obj(array('type' => $types(self::READ_TYPES), 'query' => array('type' => 'string', 'maxLength' => 100), 'page' => array('type' => 'integer', 'minimum' => 1), 'parent_id' => self::id('Parent id (see description)')), array('type')),
            'scope' => 'altus.read', 'annotations' => self::ann('List content', true, false, true), 'run' => function ($a, $ctx) { return $this->listing($a, $ctx); });
        $t['altus_get'] = array('description' => 'Read one object with its current draft, status, version (for expected_version), edit and preview URLs. For page_sections pass the page id and optionally key.',
            'schema' => self::obj(array('type' => $types(self::READ_TYPES), 'id' => self::id(), 'key' => array('type' => 'string', 'maxLength' => 64)), array('type', 'id')),
            'scope' => 'altus.read', 'annotations' => self::ann('Get content', true, false, true), 'run' => function ($a) { return $this->get($a); });
        $t['altus_create'] = array('description' => 'Create a new private draft. Catalogue types need title_en, title_ar, slug_en, slug_ar (and optional summary_*/body_*). page: title_en, title_ar?, slug_en?. course_sections/lessons/quizzes need parent_id = course id; questions need parent_id = quiz id. sops: title_en, title_ar, item_type, sections{en{purpose,procedure,...},ar{...}}. Nothing is published.',
            'schema' => self::obj(array('type' => $types(self::CREATE_TYPES), 'parent_id' => self::id('Course id or quiz id for structure types'), 'data' => $data, 'idempotency_key' => self::idem()), array('type', 'data')),
            'scope' => $by_type, 'write' => true, 'annotations' => self::ann('Create draft', false, false, false), 'run' => function ($a) { return $this->create($a); });
        $t['altus_update'] = array('description' => 'Change a private draft (partial update). Requires expected_version from altus_get. Published learning objects without a draft layer (lessons, quizzes, questions, course outlines) are locked; pages, catalogue entries, site and navigation are edited as drafts and go live only through approval.',
            'schema' => self::obj(array('type' => $types(self::UPDATE_TYPES), 'id' => self::id(), 'data' => $data, 'expected_version' => self::ver(), 'idempotency_key' => self::idem()), array('type', 'id', 'data', 'expected_version')),
            'scope' => $by_type, 'write' => true, 'annotations' => self::ann('Update draft', false, false, true), 'run' => function ($a) { return $this->update($a); });
        $fields = array(); foreach (Ha_page_builder::types() as $st => $def) $fields[] = $st . ' (' . implode(', ', array_map(function ($x) { return rtrim($x, '*'); }, $def[1])) . ($def[2] ? '; settings: ' . implode(', ', $def[2]) : '') . ')';
        $t['altus_page_section'] = array('description' => 'Section types and their en/ar fields: ' . implode('; ', $fields) . '. Items fields take arrays of objects. Edit the sections of a page draft: insert (section{section_type,en,ar,settings}, position?), update (key, section partial), duplicate (key), hide/show (key), move (key, position), reorder (order = every key). expected_version is the page draft version.',
            'schema' => self::obj(array('page_id' => self::id('Page id'), 'operation' => $types(array('insert', 'update', 'duplicate', 'hide', 'show', 'move', 'reorder')), 'key' => array('type' => 'string', 'maxLength' => 64), 'section' => array('type' => 'object'), 'position' => array('type' => 'integer', 'minimum' => 0), 'order' => array('type' => 'array', 'items' => array('type' => 'string'), 'maxItems' => 80), 'expected_version' => self::ver(), 'idempotency_key' => self::idem()), array('page_id', 'operation', 'expected_version')),
            'scope' => 'altus.content.write', 'write' => true, 'annotations' => self::ann('Edit page sections', false, false, false), 'run' => function ($a) { return $this->page_section($a); });
        $t['altus_reorder'] = array('description' => 'Reorder course sections or lessons (parent_id = unpublished course id) or quiz questions (parent_id = unpublished quiz id). ids must list every child exactly once.',
            'schema' => self::obj(array('type' => $types(array('course_sections', 'lessons', 'questions')), 'parent_id' => self::id('Course or quiz id'), 'ids' => array('type' => 'array', 'items' => array('type' => 'integer'), 'minItems' => 1, 'maxItems' => 500), 'idempotency_key' => self::idem()), array('type', 'parent_id', 'ids')),
            'scope' => 'altus.course.write', 'write' => true, 'annotations' => self::ann('Reorder', false, false, true), 'run' => function ($a) { $ids = $this->C()->reorder($a['type'], $a['parent_id'], $a['ids']); return array('ok' => true, 'object_type' => $a['type'], 'parent_id' => (int) $a['parent_id'], 'order' => $ids, 'changed_fields' => array('sort_order'), 'warnings' => array()); });
        $t['altus_upload_media'] = array('description' => 'Upload an image (base64, PNG/JPEG/WebP, up to the media library limits) to the media library. Returns the media id and URL to attach.',
            'schema' => self::obj(array('name' => array('type' => 'string', 'minLength' => 1, 'maxLength' => 255), 'base64' => array('type' => 'string', 'minLength' => 4, 'maxLength' => 10 * 1048576), 'alt_en' => array('type' => 'string', 'maxLength' => 255), 'alt_ar' => array('type' => 'string', 'maxLength' => 255), 'idempotency_key' => self::idem()), array('name', 'base64')),
            'scope' => 'altus.media.write', 'write' => true, 'annotations' => self::ann('Upload media', false, false, false), 'run' => function ($a, $ctx) { $m = $this->P()->execute('media', $a, $ctx['client_id']); return array('ok' => true, 'object_type' => 'media', 'object_id' => (int) ($m['id'] ?? 0), 'status' => 'available', 'media' => $m, 'changed_fields' => array('file'), 'warnings' => array()); });
        $t['altus_attach_media'] = array('description' => 'Attach a library image to a draft: target "image" (articles/topics/programs/paths/courses), "hero_image" (page) or "section:<key>" (page section image). Give media_id or an existing site URL.',
            'schema' => self::obj(array('type' => $types(array('page', 'articles', 'topics', 'programs', 'paths', 'courses')), 'id' => self::id(), 'media_id' => self::id('Media library id'), 'url' => array('type' => 'string', 'maxLength' => 500), 'target' => array('type' => 'string', 'maxLength' => 80), 'expected_version' => self::ver(), 'idempotency_key' => self::idem()), array('type', 'id', 'target', 'expected_version')),
            'scope' => function ($a) { return 'altus.media.write'; }, 'write' => true, 'annotations' => self::ann('Attach media', false, false, true), 'run' => function ($a) { return $this->attach($a); });
        $doc_target = $types(array('course', 'page', 'article', 'sop', 'topic'));
        $t['altus_document_upload'] = array('description' => 'Upload a source document (base64 PDF/DOCX/PPTX/TXT, max 15 MB) for extraction into a publisher draft. Poll altus_document_status.',
            'schema' => self::obj(array('name' => array('type' => 'string', 'minLength' => 1, 'maxLength' => 255), 'base64' => array('type' => 'string', 'minLength' => 4, 'maxLength' => 21 * 1048576), 'target' => $doc_target, 'locale' => $types(array('en', 'ar')), 'idempotency_key' => self::idem()), array('name', 'base64', 'target', 'locale')),
            'scope' => 'altus.content.write', 'write' => true, 'annotations' => self::ann('Upload document', false, false, false), 'run' => function ($a, $ctx) { return $this->wrap('publisher', $this->P()->execute('upload_document', $a, $ctx['client_id']), array('source')); });
        $t['altus_document_source'] = array('description' => 'Start a publisher draft from pasted source text (30-120000 characters).',
            'schema' => self::obj(array('source' => array('type' => 'string', 'minLength' => 30, 'maxLength' => 120000), 'name' => array('type' => 'string', 'maxLength' => 255), 'target' => $doc_target, 'locale' => $types(array('en', 'ar')), 'idempotency_key' => self::idem()), array('source', 'target', 'locale')),
            'scope' => 'altus.content.write', 'write' => true, 'annotations' => self::ann('Document from text', false, false, false), 'run' => function ($a, $ctx) { return $this->wrap('publisher', $this->P()->execute('source', $a, $ctx['client_id']), array('source')); });
        $t['altus_document_status'] = array('description' => 'Extraction job status of a publisher draft.',
            'schema' => self::obj(array('id' => self::id('Publisher draft id')), array('id')), 'scope' => 'altus.read', 'annotations' => self::ann('Document job status', true, false, true), 'run' => function ($a, $ctx) { return $this->P()->execute('job_status', $a + array('type' => 'publisher'), $ctx['client_id']); });
        $t['altus_document_control'] = array('description' => 'Cancel or retry a document extraction job.',
            'schema' => self::obj(array('id' => self::id('Publisher draft id'), 'operation' => $types(array('cancel', 'retry')), 'idempotency_key' => self::idem()), array('id', 'operation')),
            'scope' => 'altus.content.write', 'write' => true, 'annotations' => self::ann('Cancel or retry job', false, false, true), 'run' => function ($a, $ctx) { $s = $this->P()->execute('job_control', $a + array('type' => 'publisher'), $ctx['client_id']); return array('ok' => true, 'object_type' => 'publisher', 'object_id' => (int) $a['id'], 'job' => $s, 'changed_fields' => array('job'), 'warnings' => array()); });
        $t['altus_document_generate'] = array('description' => 'Generate (or regenerate a selection of) the structured draft from the extracted source with the governed AI. version = publisher draft version.',
            'schema' => self::obj(array('id' => self::id('Publisher draft id'), 'version' => array('type' => 'integer', 'minimum' => 1), 'provider' => array('type' => 'string', 'maxLength' => 40), 'model' => array('type' => 'string', 'maxLength' => 100), 'brief' => array('type' => 'string', 'maxLength' => 2000), 'selection' => array('type' => 'string', 'pattern' => '^(sections|modules|quiz|lesson):\\d+(?::\\d+)?$'), 'idempotency_key' => self::idem()), array('id', 'version')),
            'scope' => 'altus.content.write', 'write' => true, 'annotations' => self::ann('Generate draft', false, false, false), 'run' => function ($a, $ctx) { return $this->wrap('publisher', $this->P()->execute('generate', $a + array('type' => 'publisher'), $ctx['client_id']), array('payload')); });
        $t['altus_document_import'] = array('description' => 'Create the target ALTUS draft (course, page, article, SOP, topic) from a reviewed publisher draft. Imports are drafts.',
            'schema' => self::obj(array('id' => self::id('Publisher draft id'), 'version' => array('type' => 'integer', 'minimum' => 1), 'idempotency_key' => self::idem()), array('id', 'version')),
            'scope' => 'altus.content.write', 'write' => true, 'annotations' => self::ann('Import document draft', false, false, false), 'run' => function ($a, $ctx) { $r = $this->P()->execute('import', $a + array('type' => 'publisher'), $ctx['client_id']); return $r + array('ok' => true, 'version' => null, 'changed_fields' => array('created'), 'warnings' => array('Imported content is a draft until approved.')); });
        $t['altus_translate'] = array('description' => 'Translate a publisher draft into English or Arabic with the governed AI. Creates a new publisher draft in the target locale.',
            'schema' => self::obj(array('id' => self::id('Publisher draft id'), 'version' => array('type' => 'integer', 'minimum' => 1), 'locale' => $types(array('en', 'ar')), 'provider' => array('type' => 'string', 'maxLength' => 40), 'model' => array('type' => 'string', 'maxLength' => 100), 'idempotency_key' => self::idem()), array('id', 'version', 'locale')),
            'scope' => 'altus.content.write', 'write' => true, 'annotations' => self::ann('Translate draft', false, false, false), 'run' => function ($a, $ctx) { return $this->wrap('publisher', $this->P()->execute('translate', $a + array('type' => 'publisher'), $ctx['client_id']), array('translation')); });
        $t['altus_validate_package'] = array('description' => 'Dry-run validation of a publisher package for a target. Never writes.',
            'schema' => self::obj(array('target' => $doc_target, 'payload' => array('type' => 'object')), array('target', 'payload')),
            'scope' => 'altus.read', 'annotations' => self::ann('Validate package', true, false, true), 'run' => function ($a, $ctx) { return $this->P()->execute('validate', $a, $ctx['client_id']); });
        $t['altus_preview_link'] = array('description' => 'Preview URL of the current draft (signed and time-limited when the object has a public page; otherwise opens the ALTUS editor preview).',
            'schema' => self::obj(array('type' => $types(array_values(array_diff(self::READ_TYPES, array('media', 'page_sections')))), 'id' => self::id()), array('type', 'id')),
            'scope' => 'altus.read', 'annotations' => self::ann('Preview link', true, false, true), 'run' => function ($a) { return $this->C()->preview_link($a['type'], $a['id']); });
        $t['altus_request_publish'] = array('description' => 'Ask a human to approve publishing or archiving the current draft version. Returns an approval id and the review URL. Approvals expire after 10 minutes, are single-use, are bound to you, this client, the operation, object and version, and the requester cannot approve their own request. SOPs use the SOP governance workflow instead.',
            'schema' => self::obj(array('type' => $types(array_merge(self::APPROVAL_TYPES, array('sops'))), 'id' => self::id(), 'operation' => $types(array('publish', 'archive')), 'idempotency_key' => self::idem()), array('type', 'id', 'operation')),
            'scope' => 'altus.publish', 'write' => true, 'annotations' => self::ann('Request publication', false, false, false), 'run' => function ($a, $ctx) { $r = $this->P()->execute('request_publish', $a, $ctx['client_id']); return $r + array('ok' => true, 'changed_fields' => array(), 'warnings' => array('Nothing changes until another person approves this request in ALTUS.')); });
        $t['altus_approval_status'] = array('description' => 'Status of an approval you requested from this client: pending, approved, declined, consumed or expired.',
            'schema' => self::obj(array('approval_id' => self::id('Approval id')), array('approval_id')), 'scope' => 'altus.read', 'annotations' => self::ann('Approval status', true, false, true),
            'run' => function ($a, $ctx) { return $this->P()->execute('approval_status', array('id' => $a['approval_id']), $ctx['client_id']); });
        $t['altus_publish_approved'] = array('description' => 'Carry out an approved publish/archive. Fails if the approval expired, was used, belongs to another user/client, or the content changed after approval.',
            'schema' => self::obj(array('approval_id' => self::id('Approval id'), 'type' => $types(self::APPROVAL_TYPES), 'id' => self::id(), 'operation' => $types(array('publish', 'archive')), 'version' => array('type' => 'integer', 'minimum' => 0), 'idempotency_key' => self::idem()), array('approval_id')),
            'scope' => 'altus.publish', 'write' => true, 'annotations' => self::ann('Publish approved change', false, true, false), 'run' => function ($a, $ctx) { $r = $this->P()->execute('publish', $a, $ctx['client_id']); unset($r['state']); return $r + array('ok' => true, 'changed_fields' => array('status')); });
        $t['altus_restore_archived'] = array('description' => 'Return archived content to draft (pages, catalogue entries, lessons, quizzes). Never deletes.',
            'schema' => self::obj(array('type' => $types(array('page', 'articles', 'topics', 'programs', 'paths', 'courses', 'lessons', 'quizzes')), 'id' => self::id(), 'idempotency_key' => self::idem()), array('type', 'id')),
            'scope' => 'altus.publish', 'write' => true, 'annotations' => self::ann('Restore from archive', false, false, true), 'run' => function ($a, $ctx) { $r = $this->P()->execute('unarchive', $a, $ctx['client_id']); return $this->summary($a['type'], $a['id'], array('status')) + $r; });
        $t['altus_list_revisions'] = array('description' => 'Saved revisions of a page, catalogue entry, site, navigation menu, lesson or SOP.',
            'schema' => self::obj(array('type' => $types(array('page', 'articles', 'topics', 'programs', 'paths', 'courses', 'site', 'navigation', 'lessons', 'sops')), 'id' => self::id()), array('type', 'id')),
            'scope' => 'altus.read', 'annotations' => self::ann('List revisions', true, false, true), 'run' => function ($a) { return array('object_type' => $a['type'], 'object_id' => (int) $a['id'], 'revisions' => $this->C()->revisions($a['type'], $a['id'])); });
        $t['altus_restore_revision'] = array('description' => 'Load a saved revision into the draft (pages, catalogue entries, site, navigation) or into an unpublished lesson. Requires expected_version.',
            'schema' => self::obj(array('type' => $types(array('page', 'articles', 'topics', 'programs', 'paths', 'courses', 'site', 'navigation', 'lessons')), 'id' => self::id(), 'revision_id' => self::id('Revision id'), 'expected_version' => self::ver(), 'idempotency_key' => self::idem()), array('type', 'id', 'revision_id', 'expected_version')),
            'scope' => $by_type, 'write' => true, 'annotations' => self::ann('Restore revision', false, false, false), 'run' => function ($a, $ctx) { return $this->restore_revision($a, $ctx); });
        $t['altus_sop_workflow'] = array('description' => 'SOP governance steps available to MCP: new_version (open a draft revision of a published SOP) and submit (send the draft to review). Review, approval and publication stay with people in ALTUS.',
            'schema' => self::obj(array('id' => self::id('SOP id'), 'action' => $types(array('new_version', 'submit')), 'comment' => array('type' => 'string', 'maxLength' => 1000), 'major' => array('type' => 'boolean'), 'idempotency_key' => self::idem()), array('id', 'action')),
            'scope' => 'altus.content.write', 'write' => true, 'annotations' => self::ann('SOP workflow step', false, false, false), 'run' => function ($a) { $r = $this->C()->sop_workflow($a['id'], $a['action'], $a['comment'] ?? null, $a['major'] ?? false); return $this->summary('sops', $a['id'], array('workflow')) + $r; });
        $t['altus_audit_list'] = array('description' => 'Recent audit entries. Your own actions by default; with the altus.admin scope and audit permission, all users.',
            'schema' => self::obj(array('object_type' => array('type' => 'string', 'maxLength' => 80), 'object_id' => self::id(), 'action_prefix' => array('type' => 'string', 'maxLength' => 60), 'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 100))),
            'scope' => 'altus.read', 'annotations' => self::ann('Audit history', true, false, true), 'run' => function ($a, $ctx) { return $this->C()->audit_list($a, in_array('altus.admin', $ctx['scopes'], true) && $this->CI->ha_auth->has('audit_logs.view')); });
        return $this->tools = $t;
    }

    /** MCP tools/list entries for the given effective scopes. */
    public function listing_for(array $scopes) {
        $out = array();
        foreach ($this->definitions() as $name => $d) {
            $need = is_string($d['scope']) ? array($d['scope']) : array('altus.content.write', 'altus.course.write');
            if (!array_intersect($need, $scopes)) continue;
            $ann = $d['annotations']; $title = $ann['title'];
            $out[] = array('name' => $name, 'title' => $title, 'description' => $d['description'], 'inputSchema' => $d['schema'], 'annotations' => $ann);
        }
        return $out;
    }

    // ------------------------------------------------------------------ validation (JSON Schema subset)
    public static function validate($schema, $value, $path = 'arguments') {
        $errors = array(); $types = (array) ($schema['type'] ?? array());
        if ($types) {
            $ok = false;
            foreach ($types as $t) {
                if (($t === 'object' && is_array($value) && (empty($value) || array_keys($value) !== range(0, count($value) - 1))) || ($t === 'array' && is_array($value) && array_keys($value) === range(0, count($value) - 1)) || ($t === 'string' && is_string($value)) || ($t === 'integer' && is_int($value)) || ($t === 'number' && (is_int($value) || is_float($value))) || ($t === 'boolean' && is_bool($value)) || ($t === 'null' && $value === null)) { $ok = true; break; }
            }
            if (!$ok) return array($path . ' must be ' . implode(' or ', $types));
        }
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) $errors[] = $path . ' must be one of: ' . implode(', ', $schema['enum']);
        if (is_string($value)) {
            if (isset($schema['minLength']) && mb_strlen($value) < $schema['minLength']) $errors[] = $path . ' is shorter than ' . $schema['minLength'];
            if (isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength']) $errors[] = $path . ' is longer than ' . $schema['maxLength'];
            if (isset($schema['pattern']) && !preg_match('/' . str_replace('/', '\/', $schema['pattern']) . '/u', $value)) $errors[] = $path . ' has an invalid format';
        }
        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) $errors[] = $path . ' must be >= ' . $schema['minimum'];
            if (isset($schema['maximum']) && $value > $schema['maximum']) $errors[] = $path . ' must be <= ' . $schema['maximum'];
        }
        if (is_array($value) && in_array('array', $types, true) && array_keys($value) === range(0, count($value) - 1)) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) $errors[] = $path . ' needs at least ' . $schema['minItems'] . ' items';
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) $errors[] = $path . ' allows at most ' . $schema['maxItems'] . ' items';
            if (isset($schema['items'])) foreach ($value as $i => $v) $errors = array_merge($errors, self::validate($schema['items'], $v, $path . '[' . $i . ']'));
        } elseif (is_array($value) && in_array('object', $types, true)) {
            $props = (array) ($schema['properties'] ?? array());
            foreach ((array) ($schema['required'] ?? array()) as $r) if (!array_key_exists($r, $value)) $errors[] = $path . '.' . $r . ' is required';
            foreach ($value as $k => $v) {
                if (isset($props[$k])) $errors = array_merge($errors, self::validate($props[$k], $v, $path . '.' . $k));
                elseif (($schema['additionalProperties'] ?? true) === false) $errors[] = $path . '.' . $k . ' is not a known argument';
            }
        }
        return $errors;
    }

    // ------------------------------------------------------------------ handlers
    private function site_info($ctx) {
        $A = $this->CI->ha_auth; $health = null;
        try { $health = $this->P()->execute('health', array(), $ctx['client_id']); } catch (Throwable $e) { $health = array('native' => 'ready', 'publisher_health' => 'unavailable: ' . $e->getMessage()); }
        return array('server' => array('name' => 'altus-publisher-php', 'transport' => 'streamable-http', 'endpoint' => $ctx['resource'], 'node_required' => false),
            'user' => array('id' => (int) $A->id(), 'name' => $A->display_name(), 'platform_scope' => $A->is_system_scoped()),
            'client_id' => $ctx['client_id'], 'granted_scopes' => $ctx['granted'], 'effective_scopes' => $ctx['scopes'], 'health' => $health,
            'types' => array('read' => self::READ_TYPES, 'create' => self::CREATE_TYPES, 'update' => self::UPDATE_TYPES, 'approval' => self::APPROVAL_TYPES),
            'rules' => array('All writes are private drafts.', 'Publishing needs a human approval (10 minutes, single-use, no self-approval).', 'SOPs follow the SOP governance workflow.', 'No permanent deletes.', 'Use expected_version from altus_get on every update.'),
            'limits' => array('media_base64_max_bytes' => 10 * 1048576, 'document_max_bytes' => 15 * 1048576, 'access_token_seconds' => 300));
    }
    private static function rows($r) { return isset($r['rows']) ? $r['rows'] : (array) $r; }
    private function search($a) {
        $hits = array(); $skipped = array();
        foreach (($a['types'] ?? array('page', 'articles', 'topics', 'programs', 'paths', 'courses', 'sops')) as $type) {
            try {
                $rows = $type === 'sops' ? $this->C()->sop_list($a['query']) : self::rows($this->P()->execute('list', array('type' => $type, 'query' => $a['query']), ''));
                foreach (array_slice($rows, 0, 20) as $r) $hits[] = array('type' => $type, 'id' => (int) $r['id'], 'title' => $r['title_en'] ?? $r['code'] ?? $r['slug_en'] ?? '', 'status' => $r['status'] ?? null);
            } catch (Throwable $e) { $skipped[$type] = $e->getMessage(); }
        }
        return array('query' => $a['query'], 'results' => $hits, 'skipped' => (object) $skipped);
    }
    private function need_parent($a) { if (empty($a['parent_id'])) throw new Ha_api_error(422, 'validation_failed', 'parent_id is required for type ' . $a['type'] . '.'); return (int) $a['parent_id']; }
    private function listing($a, $ctx) {
        $type = $a['type'];
        if (in_array($type, array('course_sections', 'lessons', 'quizzes', 'questions'), true)) return $this->C()->listing($type, $this->need_parent($a), $a['query'] ?? '');
        if ($type === 'page_sections') { $s = $this->P()->state('page', $this->need_parent($a)); return array('page_id' => (int) $a['parent_id'], 'version' => (int) $s['version'], 'sections' => array_map(function ($x) { return array('key' => (string) ($x['studio_key'] ?? ('section-' . ($x['id'] ?? ''))), 'section_type' => $x['section_type'], 'is_visible' => (bool) ($x['is_visible'] ?? 1), 'title_en' => $x['en']['title'] ?? $x['en']['heading'] ?? ''); }, array_values($s['payload']['sections']))); }
        if ($type === 'sops') return $this->C()->sop_list($a['query'] ?? '');
        if ($type === 'media') return $this->P()->execute('media_list', array('query' => $a['query'] ?? ''), $ctx['client_id']);
        return $this->P()->execute('list', array('type' => $type, 'query' => $a['query'] ?? '', 'page' => $a['page'] ?? 1), $ctx['client_id']);
    }
    private function get($a) {
        $type = $a['type']; $id = (int) $a['id'];
        if (in_array($type, Ha_mcp_content::STRUCTURE, true)) return $this->C()->describe($type, $id);
        if ($type === 'sops') return $this->C()->sop_describe($id);
        if ($type === 'media') { $this->CI->load->library('ha_studio_media'); $this->CI->ha_studio_media->authorize(); return array('object_type' => 'media', 'object_id' => $id, 'url' => $this->C()->media_url($id)); }
        if ($type === 'page_sections') {
            $s = $this->P()->state('page', $id); $secs = array();
            foreach (array_values($s['payload']['sections']) as $x) { $x['key'] = (string) ($x['studio_key'] ?? ('section-' . ($x['id'] ?? ''))); if (!empty($a['key']) && $x['key'] !== $a['key']) continue; $secs[] = $x; }
            if (!empty($a['key']) && !$secs) throw new Ha_api_error(404, 'not_found', 'Section key not found on this page draft.');
            return array('object_type' => 'page_sections', 'page_id' => $id, 'version' => (int) $s['version'], 'sections' => $secs);
        }
        return $this->P()->describe($type, $id);
    }
    /** Compact write result: ids, type, status, version, changed fields, URLs, warnings. */
    public function summary($type, $id, array $changed, array $extra = array()) {
        try {
            if ($type === 'sops') $d = $this->C()->sop_describe($id);
            elseif ($type === 'media') $d = array('status' => 'available', 'version' => null, 'edit_url' => hkp_url('cms/media_picker'), 'preview_url' => null, 'warnings' => array());
            else $d = $this->P()->describe($type, $id);
        } catch (Throwable $e) { $d = array('status' => null, 'version' => null, 'edit_url' => null, 'preview_url' => null, 'warnings' => array('Saved, but the object could not be re-read: ' . $e->getMessage())); }
        return array('ok' => true, 'object_type' => $type, 'object_id' => (int) $id, 'status' => $d['status'] ?? null, 'version' => $d['version'] ?? null, 'changed_fields' => array_values($changed), 'edit_url' => $d['edit_url'] ?? null, 'preview_url' => $d['preview_url'] ?? null, 'warnings' => $d['warnings'] ?? array()) + $extra;
    }
    private function wrap($type, $describe, array $changed) {
        $id = (int) ($describe['object_id'] ?? 0);
        return array('ok' => true, 'object_type' => $type, 'object_id' => $id, 'status' => $describe['status'] ?? null, 'version' => $describe['version'] ?? null, 'changed_fields' => $changed, 'edit_url' => $describe['edit_url'] ?? null, 'preview_url' => $describe['preview_url'] ?? null, 'warnings' => $describe['warnings'] ?? array());
    }
    private function create($a) {
        $type = $a['type']; $data = (array) $a['data'];
        foreach (array('organization_id', 'property_id', 'user_id', 'created_by') as $k) if (isset($data[$k])) throw new Ha_api_error(422, 'validation_failed', 'Identity and tenant come from your ALTUS sign-in; remove ' . $k . '.');
        if (in_array($type, self::CATALOGUE, true)) { $d = $this->P()->execute('create', array('type' => $type, 'payload' => $data), ''); return $this->summary($type, $d['object_id'], array_keys($data)); }
        if ($type === 'page') { $id = $this->C()->page_create($data); return $this->summary('page', $id, array_keys($data)); }
        if ($type === 'sops') { $id = $this->C()->sop_create($data); return $this->summary('sops', $id, array_keys($data)); }
        $id = $this->C()->create($type, $this->need_parent($a), $data);
        return $this->summary($type, $id, array_keys($data));
    }
    private static function merge(array $base, array $over) { foreach ($over as $k => $v) $base[$k] = is_array($v) && isset($base[$k]) && is_array($base[$k]) && array_keys($v) !== range(0, count($v) - 1) ? self::merge($base[$k], $v) : $v; return $base; }
    private function update($a) {
        $type = $a['type']; $id = (int) $a['id']; $data = (array) $a['data']; $expected = $a['expected_version'];
        if (in_array($type, Ha_mcp_content::STRUCTURE, true)) { $changed = $this->C()->update($type, $id, $data, $expected); return $this->summary($type, $id, $changed); }
        if ($type === 'sops') { $changed = $this->C()->sop_update($id, $data, $expected); return $this->summary('sops', $id, $changed); }
        if ($type === 'site') $id = 1;
        $s = $this->P()->state($type, $id);
        if ((string) $expected !== (string) (int) $s['version']) throw new Ha_api_error(409, 'conflict', 'The draft changed since you read it. Re-read it with altus_get and retry.', $this->P()->current($type, $id) + array('expected_version' => $expected));
        $payload = $type === 'navigation' ? array_replace($s['payload'], $data) : self::merge((array) $s['payload'], $data);
        if ($type === 'page' && isset($data['sections'])) $payload['sections'] = $data['sections'];
        $in = array('type' => $type, 'id' => $id, 'payload' => $payload, 'version' => (int) $s['version'], 'base_hash' => (string) ($s['base_hash'] ?? ''));
        $this->P()->execute('save', $in, '');
        $changed = array(); foreach ($data as $k => $v) { if (is_array($v) && in_array($k, array('tr'), true)) { foreach ($v as $loc => $f) foreach ((array) $f as $fk => $_) $changed[] = $k . '.' . $loc . '.' . $fk; } else $changed[] = $k; }
        return $this->summary($type, $id, $changed);
    }
    private function page_section($a) {
        $key = $this->C()->page_section((int) $a['page_id'], $a['operation'], $a, $a['expected_version']);
        return $this->summary('page', $a['page_id'], array('sections'), array('section_key' => $key, 'operation' => $a['operation']));
    }
    private function attach($a) {
        if (empty($a['media_id']) && empty($a['url'])) throw new Ha_api_error(422, 'validation_failed', 'Give media_id or url.');
        $url = !empty($a['media_id']) ? $this->C()->media_url($a['media_id']) : (string) $a['url'];
        $this->CI->load->library('ha_website_studio'); Ha_website_studio::safe_url($url);
        $target = (string) $a['target'];
        if (strpos($target, 'section:') === 0) {
            if ($a['type'] !== 'page') throw new Ha_api_error(422, 'validation_failed', 'Section targets apply to pages.');
            $key = $this->C()->page_section((int) $a['id'], 'update', array('key' => substr($target, 8), 'section' => array('settings' => array('image' => $url))), $a['expected_version']);
            return $this->summary('page', $a['id'], array('sections.' . $key . '.settings.image'), array('url' => $url));
        }
        if ($a['type'] === 'page') { if ($target !== 'hero_image') throw new Ha_api_error(422, 'validation_failed', 'Page targets: hero_image or section:<key>.'); $data = array('tr' => array('en' => array('hero_image' => $url), 'ar' => array('hero_image' => $url))); }
        else { if ($target !== 'image') throw new Ha_api_error(422, 'validation_failed', 'Catalogue targets: image.'); $data = array('image' => $url); }
        $r = $this->update(array('type' => $a['type'], 'id' => $a['id'], 'data' => $data, 'expected_version' => $a['expected_version']));
        return $r + array('url' => $url);
    }
    private function restore_revision($a, $ctx) {
        $type = $a['type']; $id = (int) $a['id'];
        if ($type === 'lessons') { $changed = $this->C()->restore_lesson($id, $a['revision_id'], $a['expected_version']); return $this->summary('lessons', $id, $changed, array('restored_revision' => (int) $a['revision_id'])); }
        if ($type === 'site') $id = 1;
        $s = $this->P()->state($type, $id);
        if ((string) $a['expected_version'] !== (string) (int) $s['version']) throw new Ha_api_error(409, 'conflict', 'The draft changed since you read it.', $this->P()->current($type, $id) + array('expected_version' => $a['expected_version']));
        $this->P()->execute('restore', array('type' => $type, 'id' => $id, 'revision' => (int) $a['revision_id'], 'version' => (int) $s['version'], 'base_hash' => (string) ($s['base_hash'] ?? '')), $ctx['client_id']);
        return $this->summary($type, $id, array('payload'), array('restored_revision' => (int) $a['revision_id']));
    }
}
