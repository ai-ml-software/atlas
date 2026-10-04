<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Hkp_Controller.php';

/**
 * Website pages and learning content, editable by people and assisted by AI.
 *
 *   /hkp/cms                         all public pages with their SEO / AEO / GEO scores
 *   /hkp/cms/page/{id}               page builder: sections (drag to reorder), SEO panel, score, revisions
 *   /hkp/cms/modules                 modules (courses)
 *   /hkp/cms/module/{id}             module editor: details, sections, lessons, publish to the LMS
 *   /hkp/cms/lesson/{id}             lesson editor: text, YouTube / Vimeo / link, uploaded video, PDF,
 *                                    PowerPoint or audio, drip release, completion rule
 *   POST /hkp/cms/ai                 AI help: choose provider + model, enhance prompt, generate / improve
 */
class Hkp_cms extends Hkp_Controller {

    /** Read-only content views never enrol an editor or change lesson progress. */
    public function view($type = '', $id = 0) {
        $id = (int) $id;
        $loc = $this->input->get('locale') === 'ar' ? 'ar' : 'en';
        $draft = $this->input->get('draft') === '1';
        $lesson = null;
        $data = array('locale' => $loc, 'sections' => array(), 'lessons' => array(), 'lesson' => null, 'related' => array(), 'steps' => array());
        if ($type === 'pages') {
            $this->need('cms_pages.view');
            $this->load->library('ha_page_builder');
            $row = $this->ha_page_builder->page($id);
            if (!$row) { show_404(); return; }
            $slug = $row['code'] === 'home' ? '' : ($row['code'] === 'for-hotels' ? 'hotels' : $row['slug_' . $loc]);
            $public = base_url($loc . ($slug !== '' ? '/' . $slug : ''));
            if (!$draft && $row['status'] === 'published') { redirect($public); return; }
            $payload = array('tr' => $row['tr'], 'sections' => $row['sections']);
            if ($draft) {
                $this->load->library('ha_website_studio');
                try { $payload = $this->ha_website_studio->state($id)['payload']; }
                catch (RuntimeException $e) { show_error($e->getMessage(), 403); return; }
                if ($row['status'] === 'published') {
                    $this->load->library('ha_studio_preview');
                    redirect($this->ha_studio_preview->sign_url($public . '?studio_preview=' . $id, 'page', $id)); return;
                }
            }
            $data['copy'] = $payload['tr'][$loc] ?? $payload['tr']['en'] ?? array();
            $data['sections'] = array_values(array_filter($payload['sections'], function ($s) { return !empty($s['is_visible']); }));
            $data['edit_url'] = hkp_url('cms/page/' . $id);
            $data['list_url'] = hkp_url('cms');
            $active = 'cms_pages';
        } elseif ($type === 'modules' || $type === 'lessons') {
            $this->need(array('courses.create', 'courses.update', 'lessons.create', 'lessons.update'));
            $lesson = $type === 'lessons' ? $this->db->get_where('ha_lesson', array('id' => $id))->row_array() : null;
            if ($type === 'lessons' && !$lesson) { show_404(); return; }
            $row = $this->module_row($lesson ? $lesson['course_id'] : $id);
            if (!$row) { show_404(); return; }
            $this->load->library('ha_learning');
            $course = $this->ha_learning->course($row['id'], $loc);
            $data['copy'] = array('title' => $course['title'], 'subtitle' => $course['short_description'], 'body' => $course['description']);
            $data['edit_url'] = hkp_url('cms/' . ($lesson ? 'lesson/' . $id : 'module/' . $id));
            $data['list_url'] = hkp_url($lesson ? 'cms/module/' . $row['id'] : 'cms/modules');
            if ($lesson) {
                $this->load->library('ha_lesson_video');
                $l = $this->ha_learning->lesson($id, $loc);
                $data['lesson'] = array('l' => $l, 'c' => $course, 'video' => $this->ha_lesson_video->for_lesson($l, $this->uid, $loc),
                    'progress' => array('status' => 'in_progress', 'last_position_seconds' => 0), 'prev' => null, 'next' => null, 'preview' => true);
                $data['copy']['title'] = $l['title'];
            } else {
                $data['lessons'] = $this->db->select('l.id, l.status, COALESCE(t.title, te.title) AS title', false)->from('ha_lesson l')
                    ->join('ha_lesson_translation t', 't.lesson_id = l.id AND t.locale = ' . $this->db->escape($loc), 'left')
                    ->join('ha_lesson_translation te', "te.lesson_id = l.id AND te.locale = 'en'", 'left')
                    ->where('l.course_id', $id)->order_by('l.sort_order')->get()->result_array();
            }
            $active = 'cms_modules';
        } else {
            $this->load->library('ha_studio_catalogue');
            try {
                $def = $this->ha_studio_catalogue->authorize($type, 'view');
                $record = $this->ha_studio_catalogue->record($type, $id);
            } catch (InvalidArgumentException $e) { show_404(); return; }
            catch (RuntimeException $e) { show_error($e->getMessage(), 403); return; }
            $row = $record['row'];
            // Client courses have no public catalogue detail page.
            if (!$draft && $row['status'] === 'published' && !($type === 'courses' && ($row['organization_id'] || $row['property_id']))) {
                redirect(base_url($loc . '/' . $def['route'] . '/' . $row['slug_' . $loc])); return;
            }
            $tr = isset($def['translation']) ? ($record['tr'][$loc] ?? $record['tr']['en'] ?? array()) : array();
            $data['copy'] = array('title' => $tr['title'] ?? $row['title_' . $loc] ?? '',
                'subtitle' => $def['summary'] ? ($tr[$def['summary']] ?? $row[$def['summary'] . '_' . $loc] ?? '') : '',
                'body' => $tr[$def['body']] ?? $row[$def['body'] . '_' . $loc] ?? '', 'hero_image' => $row[$def['image']] ?? '');
            if ($draft) {
                $this->load->library('ha_content_studio');
                try { $payload = $this->ha_content_studio->state($type, $id)['payload']; }
                catch (RuntimeException $e) { show_error($e->getMessage(), 403); return; }
                $data['copy'] = array('title' => $payload['title_' . $loc], 'subtitle' => $payload['summary_' . $loc] ?? '',
                    'body' => $payload['body_' . $loc], 'hero_image' => $payload['image'] ?? '');
            }
            if ($type === 'courses') {
                $data['lessons'] = $this->db->select('l.id, l.status, COALESCE(t.title, te.title) AS title', false)->from('ha_lesson l')
                    ->join('ha_lesson_translation t', 't.lesson_id = l.id AND t.locale = ' . $this->db->escape($loc), 'left')
                    ->join('ha_lesson_translation te', "te.lesson_id = l.id AND te.locale = 'en'", 'left')
                    ->where('l.course_id', $id)->order_by('l.sort_order')->get()->result_array();
            } elseif (in_array($type, array('programs', 'topics'), true)) {
                $course_ids = $draft && isset($payload['courses']) ? $payload['courses'] : array_column($record['courses'], 'course_id');
                foreach ($course_ids as $course_id) {
                    $related = $this->db->select('c.*, t.title')->from('ha_course c')
                        ->join('ha_course_translation t', 't.course_id = c.id AND t.locale = ' . $this->db->escape($loc), 'left')
                        ->where('c.id', (int) $course_id)->get()->row_array();
                    if ($related) { $data['related'][] = $related; }
                }
            } elseif ($type === 'paths') {
                $data['steps'] = $draft && isset($payload['steps_json']) ? (json_decode($payload['steps_json'], true) ?: array()) : $record['steps'];
            }
            $data['edit_url'] = hkp_url('cms/catalogue/' . $type . '/' . $id);
            $data['list_url'] = hkp_url('cms/catalogue/' . $type);
            $active = 'catalogue_' . $type;
        }
        $data['type'] = $type; $data['id'] = $id; $data['draft'] = $draft;
        $data['status'] = $lesson['status'] ?? $row['status'];
        $this->render('cms_content_view', $data, $data['copy']['title'] ?? hkp_t('Preview'), $active);
    }

    private function saved_content_url($type, $id, $editor, $draft = false) {
        if ($this->input->post('after_save') !== 'view') { return $editor; }
        return hkp_content_view_url($type, $id, hkp_locale(), $draft);
    }

    private function entity_live_enabled() {
        $this->config->load('ha_publisher',true);
        if (!$this->config->item('live_editing','ha_publisher')) show_error('Live editing is disabled.',503);
    }

    private function studio_ready() {
        if (!$this->db->table_exists('ha_publisher_draft') || !$this->db->field_exists('studio_enabled', 'ha_page')) { show_error('Install the Admin Studio migrations: php index.php ha_cli migrate', 503); }
    }

    public function mcp_login() {
        $this->load->library('ha_gateway');
        try { $binding=(string)($this->input->post('binding') ?: $this->input->get('binding')); $claims=$this->ha_gateway->verify($binding,'altus-login');
            if ($this->input->method()==='post') { $this->post_guard(); if ($this->input->post('decision')!=='allow') { redirect($this->config->item('gateway_url','ha_publisher').'/interaction/'.rawurlencode($claims['uid']).'/decline'); return; }
                $code=bin2hex(random_bytes(32)); $this->db->insert('ha_oauth_bridge',array('code_hash'=>hash('sha256',$code),'user_id'=>$this->uid,'binding_hash'=>hash('sha256',$binding),'expires_at'=>gmdate('Y-m-d H:i:s',time()+60)));
                $this->ha_audit->log('oauth.consent','users',$this->uid,array('description'=>'Approved MCP client '.$claims['client_id']));
                redirect($this->config->item('gateway_url','ha_publisher').'/interaction/'.rawurlencode($claims['uid']).'/complete?code='.$code.'&binding='.rawurlencode($binding)); return;
            }
            $this->render('studio_mcp_consent',array('binding'=>$binding,'claims'=>$claims),'Connect MCP client','');
        } catch (Throwable $e) { show_error($e->getMessage(),403); }
    }
    /**
     * Consent for the native PHP OAuth server (/oauth/authorize). Reached only after the normal ALTUS
     * sign-in (including two-factor) because Hkp_Controller requires a session. Scopes are capped by
     * the signed-in user's current permissions; the code goes to the client's exact redirect_uri.
     */
    public function mcp_authorize() {
        $this->load->library('ha_mcp_oauth'); $O = $this->ha_mcp_oauth;
        $id = (string) ($this->input->post('request') ?: $this->input->get('request'));
        $pending = $O->pending($id);
        if (!$pending) { show_error('This connection request expired or was already used. Start the connection again from your MCP client.', 400); return; }
        $perms = $this->ha_auth->permissions();
        if ($this->ha_auth->is_super_admin()) $perms = array_merge($perms, array('cms_pages.update', 'courses.update', 'media.create', 'cms_pages.publish', 'system.configure', 'knowledge.create'));
        $allowed = Ha_mcp_oauth::allowed_scopes($perms, $this->ha_auth->is_system_scoped());
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $level = (string) $this->input->post('level');
            try { $url = $O->consent($id, $this->uid, $this->input->post('decision') === 'allow', $perms, $this->ha_auth->is_system_scoped(), Ha_mcp_oauth::valid_level($level) ? $level : 'read'); }
            catch (Throwable $e) { show_error($e->getMessage(), 400); return; }
            $this->output->set_status_header(302)->set_header('Location: ' . $url)->set_header('Cache-Control: no-store'); return;
        }
        $this->render('studio_mcp_authorize', array('p' => $pending, 'allowed' => $allowed, 'texts' => Ha_mcp_oauth::SCOPE_TEXT, 'offer' => $O->consent_offer($pending['client'])), 'Connect MCP client', '');
    }
    public function integrations() {
        $this->need('cms_pages.update'); if (!$this->ha_auth->is_system_scoped()) show_error('Platform integrations require platform access.',403);
        $this->load->library(array('ha_gateway','ha_publishing_service'));
        // Data and mutations live in Ha_publishing_service::admin_view()/admin_post() (permission-gated there).
        if ($this->input->method()==='post') { $this->post_guard(); $this->attempt(function(){ $this->ha_publishing_service->admin_post((array)$this->input->post()); },'Integration request updated.'); return; }
        try { $view=$this->ha_publishing_service->admin_view((array)$this->input->get()); } catch (Ha_api_error $e) { show_error($e->getMessage(),$e->status); return; }
        $this->render('studio_integrations',$view,'Integrations & approvals','cms_integrations');
    }
    public function theme() {
        $this->need('cms_pages.update'); $this->load->library('ha_content_studio');
        if ($this->input->method() === 'post') { $this->post_guard(); $this->attempt(function () {
            if ($this->input->post('action') === 'publish') { $this->ha_content_studio->publish('site',1,(int)$this->input->post('draft_version')); }
            else { $this->ha_content_studio->save('site',1,(array)$this->input->post('theme'),(int)$this->input->post('draft_version'),(string)$this->input->post('base_hash')); }
        }, 'Theme draft updated.'); return; }
        $this->render('studio_theme', array('state'=>$this->ha_content_studio->state('site',1)), hkp_t('Theme & site settings'), 'cms_theme');
    }

    public function catalogue_live($type='articles',$id=0) {
        $this->entity_live_enabled();
        $this->load->library(array('ha_content_studio','ha_studio_preview')); try { $def=$this->ha_content_studio->authorize($type); } catch (InvalidArgumentException $e) { show_404(); } catch (RuntimeException $e) { show_error($e->getMessage(), 403); } $state=$this->ha_content_studio->state($type,(int)$id);
        $preview=$state['published']['status']==='published' ? $this->ha_studio_preview->sign_url(base_url('en/'.$def['route'].'/'.$state['published']['slug_en']).'?studio_entity_preview='.$type.':'.(int)$id, $type, (int)$id) : hkp_url('cms/entity_preview/'.$type.'/'.(int)$id);
        $this->render('studio_entity_live',array('type'=>$type,'id'=>(int)$id,'def'=>$def,'state'=>$state,'preview'=>$preview),'Live content editor','catalogue_'.$type);
    }
    public function entity_preview($type='articles',$id=0) {
        $this->entity_live_enabled();
        $this->load->library('ha_content_studio'); $s=$this->ha_content_studio->state($type,(int)$id); $loc=$this->input->get('locale')==='ar'?'ar':'en'; $p=$s['payload'];
        $this->load->view('hkp/studio_preview',array('locale'=>$loc,'studio_is_preview'=>true,'page_copy'=>array('title'=>$p['title_'.$loc],'subtitle'=>$p['summary_'.$loc]??'','body'=>$p['body_'.$loc]),'sections'=>array()));
    }
    public function entity_save($type='articles',$id=0) {
        $this->entity_live_enabled();
        $this->post_guard(); $this->load->library('ha_content_studio');
        try { if ($this->input->post('action')==='publish') { $this->ha_content_studio->publish($type,(int)$id,(int)$this->input->post('version')); $s=$this->ha_content_studio->state($type,(int)$id); $this->json(array('ok'=>true,'base_hash'=>$s['base_hash'])); }
            else { $p=json_decode((string)$this->input->post('payload'),true); if (!is_array($p)) throw new InvalidArgumentException('Invalid content draft.'); $v=$this->ha_content_studio->save($type,(int)$id,$p,(int)$this->input->post('version'),(string)$this->input->post('base_hash')); $this->json(array('ok'=>true,'version'=>$v)); }
        } catch (DomainException $e) { $this->json(array('ok'=>false,'error'=>$e->getMessage()),409); } catch (Throwable $e) { $this->json(array('ok'=>false,'error'=>$e->getMessage()),403); }
    }
    public function catalogue_draft($type='articles',$id=0) {
        $this->post_guard(); $this->load->library('ha_content_studio');
        $this->attempt(function () use ($type,$id) {
            if ($this->input->post('action')==='publish') { $this->ha_content_studio->publish($type,(int)$id,(int)$this->input->post('draft_version')); }
            else { $this->ha_content_studio->save($type,(int)$id,$this->input->post(),(int)$this->input->post('draft_version'),(string)$this->input->post('base_hash')); }
        }, $this->input->post('action') === 'publish' ? hkp_t('Content published.') : hkp_t('Private draft updated.'), $this->saved_content_url($type, $id, hkp_url('cms/catalogue/'.$type.'/'.$id), $this->input->post('action') !== 'publish'));
    }

    public function catalogue($type = 'programs', $id = 0) {
        $this->load->library(array('ha_studio_catalogue', 'ha_website_studio', 'ha_content_studio'));
        try { $def = $this->ha_studio_catalogue->authorize($type, 'view'); }
        catch (InvalidArgumentException $e) { show_404(); }
        catch (RuntimeException $e) { show_error($e->getMessage(), 403); }
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $this->attempt(function () use ($type, $id) { return $this->ha_studio_catalogue->save($type, (int) $id, $this->input->post()); }, hkp_t('Content saved.'), function ($saved) use ($type) { return $this->saved_content_url($type, $saved, hkp_url('cms/catalogue/' . $type . '/' . $saved)); }); return;
        }
        $record = null;
        if ($id && $id !== 'new') { try { $record = $this->ha_studio_catalogue->record($type, $id); } catch (InvalidArgumentException $e) { show_404(); } }
        $q = mb_substr(trim((string) $this->input->get('q')), 0, 100); $page = max(1, (int) $this->input->get('page'));
        $list = $this->ha_studio_catalogue->listing($type, $q, $page);
        $courses = in_array($type, array('programs', 'topics'), true) ? $this->db->select('c.id, t.title')->from('ha_course c')->join('ha_course_translation t', "t.course_id = c.id AND t.locale = 'en'")->where('c.organization_id IS NULL', null, false)->where('c.property_id IS NULL', null, false)->order_by('t.title')->get()->result_array() : array();
        $draft_state = $record ? $this->ha_content_studio->state($type, (int)$id) : null;
        $this->render('studio_catalogue', array('draft_state'=>$draft_state, 'type' => $type, 'def' => $def, 'record' => $record, 'editing' => (bool) $id,
            'list' => $list, 'q' => $q, 'page' => $page, 'courses' => $courses,
            'path_items' => $type === 'paths' && $id ? $this->ha_studio_catalogue->path_items() : array()), hkp_t($def['title']), 'catalogue_' . $type);
    }

    public function studio() {
        $this->need(array('courses.create', 'cms_pages.view'));
        $this->studio_ready();
        $this->render('studio_dashboard', array(), hkp_t('Content Studio'), 'studio');
    }

    public function publisher($id = 0) {
        $this->need('ai.generate');
        $this->studio_ready();
        $this->load->library(array('ha_document_publisher', 'ha_ai_assist', 'ha_document_jobs'));
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $this->attempt(function () {
                $source = trim((string) $this->input->post('source'));
                $name = 'Pasted source';
                if (!empty($_FILES['source_file']['name'])) {
                    $f = $_FILES['source_file'];
                    if ((int) $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) { throw new InvalidArgumentException('The upload did not complete.'); }
                    return $this->ha_document_jobs->enqueue($f['tmp_name'],$f['name'],(string)$this->input->post('target'),(string)$this->input->post('locale'));
                }
                return $this->ha_document_publisher->source($source, $name, (string) $this->input->post('target'), (string) $this->input->post('locale'));
            }, hkp_t('Source analysed. Review the extracted text before generating.'), function ($new_id) { return hkp_url('cms/publisher/' . $new_id); });
            return;
        }
        $draft = null;
        if ($id) { try { $draft = $this->ha_document_publisher->find($id); } catch (InvalidArgumentException $e) { show_404(); } }
        $models = $this->ha_ai_assist->catalogue();
        // Resolve helper queries before starting the list query (the builder is shared).
        $P = $this->ha_document_publisher; $job = $draft ? $this->ha_document_jobs->status($draft['id']) : null;
        $view = array('draft' => $draft, 'models' => $models, 'job' => $job, 'health' => $this->ha_document_jobs->health($this->input->get('refresh_health') === '1'),
            'media' => $draft && !empty($draft['media_id']) ? $P->media($draft['media_id']) : null, 'can_media' => $P->can_choose_media(),
            'reviewers' => $draft && $draft['target'] === 'sop' ? $P->reviewers() : array());
        $db = $this->db->select('id, source_name, target, locale, status, created_at')->from('ha_publisher_draft');
        if (!$this->ha_auth->is_system_scoped()) { $db->where('created_by', $this->uid); }
        $this->render('studio_publisher', $view + array('drafts' => $db->order_by('id', 'DESC')->limit(20)->get()->result_array()), hkp_t('AI Publisher'), 'publisher');
    }

    public function document_status($id=0) {
        $this->need('ai.generate'); $this->load->library('ha_document_jobs');
        try { $this->json(array('ok'=>true,'job'=>$this->ha_document_jobs->status($id))); } catch (Exception $e) { $this->json(array('ok'=>false,'error'=>$e->getMessage()),403); }
    }
    public function publisher_action($id = 0) {
        $this->need('ai.generate'); $this->post_guard(); $this->studio_ready();
        $this->load->library('ha_document_publisher');
        try {
            $d = $this->ha_document_publisher->find($id);
            $action = (string) $this->input->post('action'); $version = (int) $this->input->post('version');
            if (in_array($action,array('cancel','retry'),true)) { $this->load->library('ha_document_jobs'); $this->ha_document_jobs->control($id,$action); $this->json(array('ok'=>true)); return;
            } elseif ($action === 'media') { $m=$this->ha_document_publisher->set_media($id,(int)$this->input->post('media_id')); $this->json(array('ok'=>true,'media'=>$m?array('id'=>(int)$m['id'],'url'=>base_url($m['file_path']),'name'=>$m['original_name']):null)); return;
            } elseif ($action === 'source') { $version=$this->ha_document_publisher->correct_source($id,(string)$this->input->post('source'),$version);
            } elseif ($action==='translate') { $new=$this->ha_document_publisher->translate($id,$version,(string)$this->input->post('provider'),(string)$this->input->post('model'),$d['locale']==='en'?'ar':'en');$this->json(array('ok'=>true,'edit_url'=>hkp_url('cms/publisher/'.$new)));return;
            } elseif ($action === 'generate') {
                $version = $this->ha_document_publisher->generate($id, $version, (string) $this->input->post('provider'), (string) $this->input->post('model'), (string) $this->input->post('brief'),$this->input->post('selection'));
            } elseif ($action === 'save') {
                $p = json_decode((string) $this->input->post('payload'), true);
                if (!is_array($p)) { throw new InvalidArgumentException('The structured draft is not valid JSON.'); }
                $version = $this->ha_document_publisher->save($id, $p, $version);
            } elseif ($action === 'import') {
                if ($this->input->post('confirmed') !== 'yes') { throw new InvalidArgumentException('Confirm that you reviewed this package.'); }
                $entity = $this->ha_document_publisher->materialize($id, $version, array('submit' => $this->input->post('submit_review') === '1',
                    'reviewer_user_id' => (int) $this->input->post('reviewer_user_id'), 'approver_user_id' => (int) $this->input->post('approver_user_id')));
                $this->json(array('ok' => true, 'edit_url' => $this->ha_document_publisher->edit_url($d,$entity))); return;
            } else { throw new InvalidArgumentException('Unknown publisher action.'); }
            $d = $this->ha_document_publisher->find($id);
            $this->json(array('ok' => true, 'version' => $version, 'payload' => json_decode($d['payload_json'], true)));
        } catch (DomainException $e) { $this->json(array('ok' => false, 'error' => $e->getMessage()), 409); }
        catch (Exception $e) { $this->json(array('ok' => false, 'error' => $e->getMessage()), $e instanceof InvalidArgumentException ? 422 : 403); }
    }

    public function live($id = 0) {
        $this->need('cms_pages.update'); $this->config->load('ha_publisher',true); if (!$this->config->item('live_editing','ha_publisher')) show_error('Live editing is disabled.',503); $this->studio_ready();
        $this->load->library('ha_website_studio');
        try { $state = $this->ha_website_studio->state($id); }
        catch (RuntimeException $e) { show_error($e->getMessage(), 403); }
        catch (InvalidArgumentException $e) { show_404(); }
        $this->render('studio_live', array('state' => $state, 'types' => Ha_page_builder::types()), hkp_t('Live page editor'), 'cms_pages');
    }

    public function preview($id = 0) {
        $this->need('cms_pages.update'); $this->load->library('ha_website_studio');
        try { $s = $this->ha_website_studio->state($id); }
        catch (Exception $e) { show_error('Preview not available.', 403); }
        $loc = $this->input->get('edit') === 'ar' ? 'ar' : 'en';
        $p = $this->ha_website_studio->validate($s['payload']);
        $this->load->view('hkp/studio_preview', array('locale' => $loc, 'page_copy' => $p['tr'][$loc],
            'sections' => array_values(array_filter($p['sections'], function ($s) { return !empty($s['is_visible']); }))));
    }

    public function publisher_preview($id = 0) {
        $this->need('ai.generate'); $this->load->library('ha_document_publisher');
        try { $d = $this->ha_document_publisher->find($id); $p = $this->ha_document_publisher->validate(json_decode($d['payload_json'], true) ?: array(), $d['target']); }
        catch (Exception $e) { show_error('Save a valid draft before previewing.', 422); }
        $this->load->view('hkp/studio_preview', array('locale' => $d['locale'], 'package' => $p, 'target' => $d['target']));
    }

    public function live_save($id = 0) {
        $this->need('cms_pages.update'); $this->post_guard(); $this->studio_ready();
        $this->load->library('ha_website_studio');
        try {
            $version = (int) $this->input->post('version');
            if ($this->input->post('action') === 'discard') {
                $this->ha_website_studio->discard($id, $version); $this->json(array('ok' => true));
            } elseif ($this->input->post('action') === 'publish') {
                $this->ha_website_studio->publish($id, $version);
                $s = $this->ha_website_studio->state($id);
                $this->json(array('ok' => true, 'version' => 0, 'base_hash' => $s['base_hash'], 'published' => true));
            } else {
                $p = json_decode((string) $this->input->post('payload'), true);
                if (!is_array($p)) { throw new InvalidArgumentException('Invalid page draft.'); }
                $version = $this->ha_website_studio->save($id, $p, $version, (string) $this->input->post('base_hash'));
                $this->json(array('ok' => true, 'version' => $version));
            }
        } catch (DomainException $e) { $this->json(array('ok' => false, 'error' => $e->getMessage()), 409); }
        catch (Exception $e) { $this->json(array('ok' => false, 'error' => $e->getMessage()), $e instanceof InvalidArgumentException ? 422 : 403); }
    }

    public function manage_navigation() {
        $this->need('cms_pages.update');
        if (!$this->ha_auth->is_system_scoped()) { show_error('Website navigation requires platform access.', 403); }
        $this->load->library(array('ha_website_navigation','ha_content_studio'));
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $this->attempt(function () {
                $id=(int)$this->input->post('menu_id');
                if ($this->input->post('action')==='publish') { $this->ha_content_studio->publish('navigation',$id,(int)$this->input->post('draft_version')); }
                else { $this->ha_content_studio->save('navigation',$id,array('items'=>(array)$this->input->post('items')),(int)$this->input->post('draft_version'),(string)$this->input->post('base_hash')); }
            }, 'Navigation draft updated.');
            return;
        }
        $menus = $this->ha_website_navigation->menus();
        $id = (int) $this->input->get('menu');
        $menu = null; foreach ($menus as $m) { if ((int) $m['id'] === $id) { $menu = $m; } }
        $menu = $menu ?: ($menus[0] ?? null);
        $nav_state = $menu ? $this->ha_content_studio->state('navigation',$menu['id']) : null;
        $nav_items = $nav_state ? $nav_state['payload']['items'] : array();
        foreach ($nav_items as &$item) { if (isset($item['visible'])) { $item['status'] = $item['visible'] ? 'active' : 'hidden'; } } unset($item);
        $this->render('studio_navigation', array('state'=>$nav_state, 'menus' => $menus, 'selected_menu' => $menu,
            'items' => $nav_items), hkp_t('Navigation & footer'), 'cms_navigation');
    }

    // ================================================================ pages

    public function index() {
        $this->need('cms_pages.view');
        $rows = $this->db->select('p.*, te.title AS title_en, ta.title AS title_ar, (SELECT COUNT(*) FROM ha_page_section s WHERE s.page_id = p.id) sections', false)
            ->from('ha_page p')->join('ha_page_translation te', "te.page_id = p.id AND te.locale = 'en'", 'left')
            ->join('ha_page_translation ta', "ta.page_id = p.id AND ta.locale = 'ar'", 'left')->order_by('p.code')->get()->result_array();
        $this->render('cms_pages', array('rows' => $rows), hkp_t('Website pages'), 'cms_pages');
    }

    public function page_create() {
        $this->need('cms_pages.create');
        $this->post_guard();
        $this->attempt(function () {
            $title = trim((string) $this->input->post('title_en'));
            $slug = trim(preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) ($this->input->post('slug_en') ?: $title))), '-');
            if ($title === '' || $slug === '') {
                throw new InvalidArgumentException(hkp_t('Give the page an English title.'));
            }
            if ($this->db->where('slug_en', $slug)->or_where('code', $slug)->count_all_results('ha_page')) {
                throw new InvalidArgumentException(hkp_t('That address is already used by another page.'));
            }
            $now = date('Y-m-d H:i:s');
            $title_ar = trim((string) $this->input->post('title_ar')) ?: $title;
            $slug_ar = trim(preg_replace('/[^\p{Arabic}a-z0-9\-]+/u', '-', mb_strtolower($title_ar)), '-') ?: $slug . '-ar';
            if ($this->db->where('slug_ar', $slug_ar)->count_all_results('ha_page')) {
                $slug_ar .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            }
            $this->db->insert('ha_page', array('code' => $slug, 'slug_en' => $slug, 'slug_ar' => $slug_ar, 'template' => 'standard', 'status' => 'draft',
                'created_by' => $this->uid, 'created_at' => $now, 'updated_at' => $now));
            $id = (int) $this->db->insert_id();
            foreach (array('en' => $title, 'ar' => $title_ar) as $loc => $t) {
                $this->db->insert('ha_page_translation', array('page_id' => $id, 'locale' => $loc, 'title' => $t));
            }
            $this->ha_audit->log('create', 'page', $id, array('description' => 'Page ' . $slug . ' created'));
            return $id;
        }, hkp_t('Page created. Add sections below.'), function ($id) { return hkp_url('cms/page/' . $id); });
    }

    public function page($id = 0) {
        $this->need('cms_pages.view');
        $this->load->library(array('ha_page_builder', 'ha_seo_score', 'ha_ai_assist'));
        $p = $this->ha_page_builder->page($id);
        if (!$p) {
            show_404();
        }
        $loc = $this->input->get('edit') === 'ar' ? 'ar' : 'en';
        $this->render('cms_page_edit', array('p' => $p, 'loc' => $loc, 'types' => Ha_page_builder::types(),
            'score' => array('en' => $this->ha_seo_score->score($id, 'en'), 'ar' => $this->ha_seo_score->score($id, 'ar')),
            'revisions' => $this->db->select('r.id, r.note, r.created_at, u.first_name, u.last_name')->from('ha_page_revision r')->join('users u', 'u.id = r.created_by', 'left')
                ->where('r.page_id', (int) $id)->order_by('r.id', 'DESC')->limit(25)->get()->result_array(),
            'models' => $this->ha_ai_assist->catalogue(), 'edit_section' => (int) $this->input->get('section')), hkp_pick($p['tr'][$loc] ?? array(), 'title') ?: $p['code'], 'cms_pages');
    }

    public function page_save($id = 0) {
        $this->need('cms_pages.update');
        $this->post_guard();
        $this->load->library('ha_page_builder');
        $this->attempt(function () use ($id) {
            $p = $this->db->get_where('ha_page', array('id' => (int) $id))->row_array();
            if (!$p) {
                throw new InvalidArgumentException('Page not found.');
            }
            $now = date('Y-m-d H:i:s');
            foreach (array('en', 'ar') as $loc) {
                $t = (array) $this->input->post($loc);
                if (!isset($t['title'])) {
                    continue;
                }
                $row = array('title' => mb_substr(trim($t['title']), 0, 190), 'subtitle' => mb_substr(trim((string) $t['subtitle']), 0, 500),
                    'body' => (string) $t['body'], 'hero_image' => trim((string) $t['hero_image']) ?: null,
                    'cta_label' => trim((string) $t['cta_label']) ?: null, 'cta_url' => trim((string) $t['cta_url']) ?: null);
                $ex = $this->db->get_where('ha_page_translation', array('page_id' => (int) $id, 'locale' => $loc))->row_array();
                $ex ? $this->db->where('id', $ex['id'])->update('ha_page_translation', $row) : $this->db->insert('ha_page_translation', $row + array('page_id' => (int) $id, 'locale' => $loc));
                $seo = (array) $this->input->post('seo_' . $loc);
                $srow = array('meta_title' => mb_substr(trim((string) $seo['meta_title']), 0, 190) ?: null, 'meta_description' => mb_substr(trim((string) $seo['meta_description']), 0, 320) ?: null,
                    'canonical_url' => trim((string) $seo['canonical_url']) ?: null, 'robots' => in_array($seo['robots'], array('index,follow', 'noindex,follow', 'noindex,nofollow'), true) ? $seo['robots'] : 'index,follow',
                    'og_image' => trim((string) $seo['og_image']) ?: null, 'updated_by' => $this->uid, 'updated_at' => $now);
                $sx = $this->db->get_where('ha_seo_metadata', array('entity_type' => 'page', 'entity_id' => (int) $id, 'locale' => $loc))->row_array();
                $sx ? $this->db->where('id', $sx['id'])->update('ha_seo_metadata', $srow)
                    : $this->db->insert('ha_seo_metadata', $srow + array('entity_type' => 'page', 'entity_id' => (int) $id, 'locale' => $loc, 'created_at' => $now));
            }
            $meta = array('focus_keyword_en' => mb_substr(trim((string) $this->input->post('focus_keyword_en')), 0, 120) ?: null,
                'focus_keyword_ar' => mb_substr(trim((string) $this->input->post('focus_keyword_ar')), 0, 120) ?: null,
                'schema_type' => in_array($this->input->post('schema_type'), array('WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'Service', 'LocalBusiness', 'Course', 'Article', 'CollectionPage'), true) ? $this->input->post('schema_type') : 'WebPage',
                'geo_region' => mb_substr(trim((string) $this->input->post('geo_region')), 0, 10) ?: null, 'geo_placename' => mb_substr(trim((string) $this->input->post('geo_placename')), 0, 120) ?: null,
                'geo_lat' => is_numeric($this->input->post('geo_lat')) ? $this->input->post('geo_lat') : null, 'geo_lng' => is_numeric($this->input->post('geo_lng')) ? $this->input->post('geo_lng') : null,
                'updated_at' => $now);
            foreach (array('slug_en', 'slug_ar') as $sk) {
                $sv = trim((string) $this->input->post($sk));
                if ($sv !== '' && !$p['is_system']) {
                    $sv = trim(preg_replace('/[^\p{Arabic}a-z0-9\-\/]+/u', '-', mb_strtolower($sv)), '-');
                    if ($this->db->where($sk, $sv)->where('id !=', (int) $id)->count_all_results('ha_page')) {
                        throw new InvalidArgumentException(hkp_t('That address is already used by another page.'));
                    }
                    $meta[$sk] = $sv;
                }
            }
            $st = $this->input->post('status');
            if (in_array($st, array('draft', 'review', 'published', 'archived'), true)) {
                if ($st === 'published' && !$this->can('cms_pages.publish')) {
                    throw new RuntimeException(hkp_t('You need the publish permission.'));
                }
                $meta['status'] = $st;
                if ($st === 'published' && $p['status'] !== 'published') {
                    $meta['published_at'] = $now;
                }
            }
            $this->db->where('id', (int) $id)->update('ha_page', $meta);
            $this->ha_page_builder->revision($id, $this->uid, 'Page details saved');
            $this->ha_audit->log('update', 'page', (int) $id, array('description' => 'Page saved', 'after' => $meta));
        }, hkp_t('Page saved.'), $this->saved_content_url('pages', $id, hkp_url('cms/page/' . (int) $id)));
    }

    public function section($page_id = 0, $section_id = 0) {
        $this->need('cms_pages.update');
        $this->post_guard();
        $this->load->library('ha_page_builder');
        $B = $this->ha_page_builder;
        $action = (string) $this->input->post('do');
        $this->attempt(function () use ($B, $page_id, $section_id, $action) {
            if (!$this->db->where('id', (int) $page_id)->count_all_results('ha_page')) {
                throw new InvalidArgumentException('Page not found.');
            }
            switch ($action) {
                case 'toggle':    $B->toggle($page_id, $section_id); return $section_id;
                case 'duplicate': return $B->duplicate($page_id, $section_id, $this->uid);
                case 'delete':    $B->delete($page_id, $section_id, $this->uid); return 0;
            }
            $type = (string) $this->input->post('section_type');
            $settings = (array) $this->input->post('settings');
            if (!empty($_FILES['image']['name'])) {
                $settings['image'] = $this->store_public('image', 'pages', array('jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'), 5);
            }
            return $B->save_section($page_id, $section_id, $type, $B->content_from_input($type, (array) $this->input->post('en')),
                $B->content_from_input($type, (array) $this->input->post('ar')), $settings, $this->uid);
        }, $action === 'delete' ? hkp_t('Section deleted.') : hkp_t('Section saved.'), function ($sid) use ($page_id) { return hkp_url('cms/page/' . (int) $page_id) . ($sid ? '?section=' . (int) $sid . '#s' . (int) $sid : ''); });
    }

    public function order($page_id = 0) {
        $this->need('cms_pages.update');
        $this->post_guard();
        $this->load->library('ha_page_builder');
        $this->ha_page_builder->reorder((int) $page_id, array_filter(array_map('intval', explode(',', (string) $this->input->post('order')))));
        $this->json(array('ok' => true));
    }

    public function restore($page_id = 0, $rev = 0) {
        $this->need('cms_pages.update');
        $this->post_guard();
        $this->load->library('ha_page_builder');
        $this->attempt(function () use ($page_id, $rev) { $this->ha_page_builder->restore((int) $page_id, (int) $rev, $this->uid); }, hkp_t('Revision restored.'));
    }

    public function media_picker() { $this->need('cms_pages.update');$this->load->library('ha_studio_media');try{$this->json(array('ok'=>true,'items'=>$this->ha_studio_media->listing((string)$this->input->get('q'))));}catch(Throwable $e){$this->json(array('ok'=>false,'error'=>$e->getMessage()),403);} }
    public function upload() {
        $this->need(array('cms_pages.update', 'media.create', 'lessons.update'));
        $this->post_guard();
        try {
            $url = $this->store_public('file', 'pages', array('jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'), 5);
            $this->json(array('ok' => true, 'path' => $url, 'url' => base_url($url)));
        } catch (Exception $e) {
            $this->json(array('ok' => false, 'error' => $e->getMessage()));
        }
    }

    /** Public media (page images, lesson files) under /uploads with unguessable names. */
    protected function store_public($field, $folder, array $ext_ok, $max_mb) {
        $f = isset($_FILES[$field]) ? $_FILES[$field] : null;
        if (!$f || (int) $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            throw new InvalidArgumentException(hkp_t('The upload did not complete.'));
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ext_ok, true)) {
            throw new InvalidArgumentException(hkp_t('Files of this type are not accepted here: {t}', array('t' => $ext)));
        }
        if ($f['size'] > $max_mb * 1048576) {
            throw new InvalidArgumentException(hkp_t('The file is larger than {n} MB.', array('n' => $max_mb)));
        }
        $mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';
        $ok_mime = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'pdf' => 'application/pdf');
        if (isset($ok_mime[$ext]) && $mime && $mime !== $ok_mime[$ext] && !($ext === 'svg' && strpos($mime, 'svg') !== false) && !($ext === 'm4a' && strpos($mime, 'audio') === 0)) {
            throw new InvalidArgumentException(hkp_t('The file content does not match its extension.'));
        }
        if ($ext === 'svg' && preg_match('/<script|on\w+\s*=/i', file_get_contents($f['tmp_name']))) {
            throw new InvalidArgumentException(hkp_t('SVG files with scripts are not accepted.'));
        }
        $dir = FCPATH . 'uploads/hkp/' . $folder . '/' . date('Y/m') . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new RuntimeException(hkp_t('The upload folder is not writable.'));
        }
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . $name)) {
            throw new RuntimeException(hkp_t('The file could not be saved.'));
        }
        return 'uploads/hkp/' . $folder . '/' . date('Y/m') . '/' . $name;
    }

    // ================================================================== AI

    public function ai() {
        $this->post_guard();
        $this->load->library('ha_ai_assist');
        try {
            $this->json($this->ha_ai_assist->run($this->input->post()));
        } catch (Exception $e) {
            $this->json(array('ok' => false, 'error' => $e->getMessage()));
        }
    }

    // ============================================================== modules

    public function modules() {
        $this->need(array('courses.create', 'courses.update', 'lessons.create', 'lessons.update'));   // editor screens: viewing a course is not enough
        $q = trim((string) $this->input->get('q'));
        $db = $this->db->select("c.*, te.title AS title_en, ta.title AS title_ar, d.name_en AS domain_en, d.name_ar AS domain_ar,
                (SELECT COUNT(*) FROM ha_lesson l WHERE l.course_id = c.id) lessons", false)
            ->from('ha_course c')->join('ha_course_translation te', "te.course_id = c.id AND te.locale = 'en'", 'left')
            ->join('ha_course_translation ta', "ta.course_id = c.id AND ta.locale = 'ar'", 'left')->join('ha_domain d', 'd.id = c.domain_id', 'left');
        if (!$this->ha_auth->is_system_scoped()) {
            $db->group_start()->where('c.organization_id IS NULL', null, false)->or_where('c.organization_id', (int) $this->ctx['organization_id'])->group_end();
        }
        if ($q !== '') {
            $db->group_start()->like('te.title', $q)->or_like('ta.title', $q)->or_like('c.code', $q)->group_end();
        }
        $this->render('cms_modules', array('rows' => $db->order_by('te.title')->get()->result_array(), 'q' => $q,
            'domains' => $this->db->order_by('sort_order')->get('ha_domain')->result_array()), hkp_t('Modules & lessons'), 'cms_modules');
    }

    protected function module_row($id) {
        $c = $this->db->get_where('ha_course', array('id' => (int) $id))->row_array();
        if (!$c) {
            return null;
        }
        if ($c['organization_id'] && !$this->ha_auth->can_organization($c['organization_id']) && !($c['property_id'] && $this->ha_auth->can_property($c['property_id']))) {
            return null;
        }
        return $c;
    }

    public function module($id = 0) {
        $this->need(array('courses.create', 'courses.update', 'lessons.create', 'lessons.update'));   // editor screens: viewing a course is not enough
        $this->load->library('ha_ai_assist');
        $c = $id ? $this->module_row($id) : null;
        if ($id && !$c) {
            show_404();
        }
        $tr = array();
        $sections = array();
        $lessons = array();
        if ($c) {
            foreach ($this->db->get_where('ha_course_translation', array('course_id' => (int) $id))->result_array() as $t) {
                $tr[$t['locale']] = $t;
            }
            $sections = $this->db->order_by('sort_order')->get_where('ha_course_section', array('course_id' => (int) $id))->result_array();
            $lessons = $this->db->select('l.*, te.title AS title_en, ta.title AS title_ar')->from('ha_lesson l')
                ->join('ha_lesson_translation te', "te.lesson_id = l.id AND te.locale = 'en'", 'left')->join('ha_lesson_translation ta', "ta.lesson_id = l.id AND ta.locale = 'ar'", 'left')
                ->where('l.course_id', (int) $id)->order_by('l.sort_order')->get()->result_array();
        }
        $quizzes = $c ? $this->db->select('a.id, a.code, a.title_en, a.title_ar, a.pass_percentage, a.status, (SELECT COUNT(*) FROM ha_assessment_question q WHERE q.assessment_id = a.id) questions', false)
            ->from('ha_assessment a')->where('a.course_id', (int) $id)->order_by('a.id')->get()->result_array() : array();
        $this->render('cms_module_edit', array('c' => $c, 'tr' => $tr, 'sections' => $sections, 'lessons' => $lessons, 'quizzes' => $quizzes,
            'domains' => $this->db->order_by('sort_order')->get('ha_domain')->result_array(),
            'categories' => $this->db->select('c.id, t.name')->from('ha_category c')->join('ha_category_translation t', "t.category_id = c.id AND t.locale = 'en'")->get()->result_array(),
            'orgs' => $this->db->get('ha_organization')->result_array(), 'models' => $this->ha_ai_assist->catalogue()), $c ? ($tr['en']['title'] ?? $c['code']) : hkp_t('New module'), 'cms_modules');
    }

    public function module_save($id = 0) {
        $this->need($id ? 'courses.update' : 'courses.create');
        $this->post_guard();
        $this->attempt(function () use ($id) {
            $d = $this->input->post();
            $title_en = trim((string) $d['title_en']);
            if ($title_en === '') {
                throw new InvalidArgumentException(hkp_t('Give the module an English title.'));
            }
            $now = date('Y-m-d H:i:s');
            $status = in_array($d['status'], array('draft', 'review', 'approved', 'published', 'archived'), true) ? $d['status'] : 'draft';
            if ($status === 'published' && !$this->can('courses.publish')) {
                throw new RuntimeException(hkp_t('You need the publish permission.'));
            }
            $org = !empty($d['organization_id']) ? (int) $d['organization_id'] : null;
            if ($org === null && !$this->ha_auth->is_system_scoped()) {
                $org = (int) $this->ctx['organization_id'];
            }
            if ($org && !$this->ha_auth->can_organization($org)) {
                throw new RuntimeException(hkp_t('You cannot create modules for that organisation.'));
            }
            $row = array('category_id' => $d['category_id'] ?: null, 'domain_id' => $d['domain_id'] ?: null, 'organization_id' => $org,
                'department_code' => mb_substr(trim((string) $d['department_code']), 0, 60) ?: null,
                'level' => in_array($d['level'], array('foundation', 'intermediate', 'advanced', 'leadership'), true) ? $d['level'] : 'foundation',
                'duration_minutes' => max(0, (int) $d['duration_minutes']), 'pass_percentage' => max(0, min(100, (int) $d['pass_percentage'] ?: 75)),
                'certificate_eligible' => !empty($d['certificate_eligible']) ? 1 : 0, 'status' => $status, 'updated_at' => $now);
            if (!empty($_FILES['thumbnail']['name'])) {
                $row['thumbnail'] = $this->store_public('thumbnail', 'modules', array('jpg', 'jpeg', 'png', 'webp'), 5);
            }
            if ($status === 'published') {
                $row['published_at'] = $now;
                $row['approved_by'] = $this->uid;
                $row['approved_at'] = $now;
            }
            if ($id) {
                if (!$this->module_row($id)) {
                    throw new RuntimeException('Module not found.');
                }
                $this->db->where('id', (int) $id)->update('ha_course', $row);
            } else {
                $code = trim(preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) (!empty($d['code']) ? $d['code'] : $title_en))), '-');
                if ($this->db->where('code', $code)->count_all_results('ha_course')) {
                    $code .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
                }
                $row += array('code' => $code, 'slug_en' => $code, 'slug_ar' => $code . '-ar', 'created_by' => $this->uid, 'created_at' => $now, 'is_free' => 1, 'currency' => 'SAR');
                $this->db->insert('ha_course', $row);
                $id = (int) $this->db->insert_id();
                $this->db->insert('ha_course_section', array('course_id' => $id, 'title_en' => 'Lessons', 'title_ar' => 'الدروس', 'sort_order' => 0));
            }
            foreach (array('en', 'ar') as $loc) {
                $t = array('title' => mb_substr(trim((string) $d['title_' . $loc]) ?: $title_en, 0, 190), 'short_description' => mb_substr(trim((string) $d['short_' . $loc]), 0, 500),
                    'description' => (string) $d['description_' . $loc]);
                $ex = $this->db->get_where('ha_course_translation', array('course_id' => $id, 'locale' => $loc))->row_array();
                $ex ? $this->db->where('id', $ex['id'])->update('ha_course_translation', $t) : $this->db->insert('ha_course_translation', $t + array('course_id' => $id, 'locale' => $loc));
            }
            $this->ha_audit->log($status === 'published' ? 'publish' : 'update', 'course', $id, array('description' => 'Module ' . $title_en . ' saved (' . $status . ')'));
            if ($status === 'published') {
                $this->publish_side_effects($id);
            }
            return $id;
        }, hkp_t('Module saved.'), function ($mid) { return $this->saved_content_url('modules', $mid, hkp_url('cms/module/' . $mid)); });
    }

    /** Published modules are mirrored into the LMS and indexed for search and the governed assistant. */
    protected function publish_side_effects($course_id) {
        $this->load->library(array('ha_governed_ai', 'ha_cli_runner'));
        $this->ha_governed_ai->index_course($course_id);
        $c = $this->db->get_where('ha_course', array('id' => (int) $course_id))->row_array();
        if ($c && !$c['organization_id']) {
            $this->ha_cli_runner->spawn(array('ha_bridge', 'sync_one', $c['code']));
        }
    }

    public function section_add($course_id = 0) {
        $this->need('courses.update');
        $this->post_guard();
        if (!$this->module_row($course_id)) {
            show_404();
        }
        $t = trim((string) $this->input->post('title_en'));
        if ($t !== '') {
            $max = (int) $this->db->select_max('sort_order')->get_where('ha_course_section', array('course_id' => (int) $course_id))->row()->sort_order;
            $this->db->insert('ha_course_section', array('course_id' => (int) $course_id, 'title_en' => mb_substr($t, 0, 190),
                'title_ar' => mb_substr(trim((string) $this->input->post('title_ar')) ?: $t, 0, 190), 'sort_order' => $max + 1));
        }
        $this->back(hkp_t('Section added.'));
    }

    public function lesson_order($course_id = 0) {
        $this->need('lessons.update');
        $this->post_guard();
        if (!$this->module_row($course_id)) {
            show_404();
        }
        foreach (array_filter(array_map('intval', explode(',', (string) $this->input->post('order')))) as $i => $lid) {
            $this->db->where(array('id' => $lid, 'course_id' => (int) $course_id))->update('ha_lesson', array('sort_order' => $i));
        }
        $this->json(array('ok' => true));
    }

    public function lesson($id = 0) {
        $this->need(array('courses.create', 'courses.update', 'lessons.create', 'lessons.update'));
        $this->load->library('ha_ai_assist');
        $course_id = (int) $this->input->get('module');
        $l = $id ? $this->db->get_where('ha_lesson', array('id' => (int) $id))->row_array() : null;
        if ($id && !$l) {
            show_404();
        }
        $course_id = $l ? (int) $l['course_id'] : $course_id;
        $c = $this->module_row($course_id);
        if (!$c) {
            show_404();
        }
        $tr = array();
        if ($l) {
            foreach ($this->db->get_where('ha_lesson_translation', array('lesson_id' => (int) $id))->result_array() as $t) {
                $tr[$t['locale']] = $t;
            }
        }
        $this->render('cms_lesson_edit', array('l' => $l, 'tr' => $tr, 'c' => $c, 'course_tr' => $this->db->get_where('ha_course_translation', array('course_id' => $course_id, 'locale' => 'en'))->row_array(),
            'sections' => $this->db->order_by('sort_order')->get_where('ha_course_section', array('course_id' => $course_id))->result_array(),
            'assessments' => $this->db->get_where('ha_assessment', array('course_id' => $course_id))->result_array(),
            'attachments' => $l ? $this->db->get_where('ha_lesson_attachment', array('lesson_id' => (int) $id))->result_array() : array(),
            'models' => $this->ha_ai_assist->catalogue()), $l ? ($tr['en']['title'] ?? hkp_t('Lesson')) : hkp_t('New lesson'), 'cms_modules');
    }

    public function lesson_save($id = 0) {
        $this->need($id ? 'lessons.update' : 'lessons.create');
        $this->post_guard();
        $this->attempt(function () use ($id) {
            $d = $this->input->post();
            $course_id = $id ? (int) $this->db->get_where('ha_lesson', array('id' => (int) $id))->row()->course_id : (int) $d['course_id'];
            if (!$this->module_row($course_id)) {
                throw new RuntimeException('Module not found.');
            }
            $title = trim((string) $d['title_en']);
            if ($title === '') {
                throw new InvalidArgumentException(hkp_t('Give the lesson an English title.'));
            }
            $types = array('text', 'video', 'audio', 'pdf', 'presentation', 'external', 'checklist', 'sop', 'interactive');
            $type = in_array($d['lesson_type'], $types, true) ? $d['lesson_type'] : 'text';
            $video = trim((string) $d['video_url']);
            if ($video !== '' && !preg_match('~^(https?://|/)~i', $video)) {
                throw new InvalidArgumentException(hkp_t('Video links must start with https://'));
            }
            $external = trim((string) $d['external_url']);
            if ($external !== '' && !preg_match('~^https?://~i', $external)) {
                throw new InvalidArgumentException(hkp_t('External links must start with https://'));
            }
            $drip_mode = isset($d['drip_mode']) ? $d['drip_mode'] : 'none';
            $row = array('lesson_type' => $type, 'section_id' => !empty($d['section_id']) ? (int) $d['section_id'] : null,
                'video_source' => $video === '' ? null : (preg_match('~youtu~i', $video) ? 'youtube' : (preg_match('~vimeo~i', $video) ? 'vimeo' : 'url')),
                'video_url' => $video ?: null, 'external_url' => $external ?: null, 'duration_seconds' => max(0, (int) $d['duration_minutes']) * 60,
                'is_mandatory' => !empty($d['is_mandatory']) ? 1 : 0, 'is_preview' => !empty($d['is_preview']) ? 1 : 0,
                'completion_rule' => in_array($d['completion_rule'], array('open', 'watch_percentage', 'quiz', 'acknowledge', 'assignment'), true) ? $d['completion_rule'] : 'open',
                'required_watch_percentage' => max(10, min(100, (int) $d['required_watch_percentage'] ?: 90)),
                'assessment_id' => !empty($d['assessment_id']) ? (int) $d['assessment_id'] : null,
                'drip_days' => $drip_mode === 'days' && (int) $d['drip_days'] > 0 ? (int) $d['drip_days'] : null,
                'available_from' => $drip_mode === 'date' && !empty($d['available_from']) ? date('Y-m-d H:i:s', strtotime($d['available_from'])) : null,
                'status' => in_array($d['status'], array('draft', 'published', 'archived'), true) ? $d['status'] : 'draft', 'updated_at' => date('Y-m-d H:i:s'));
            if (!empty($_FILES['media']['name'])) {
                $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
                $map = array('mp4' => 'video', 'webm' => 'video', 'mp3' => 'audio', 'm4a' => 'audio', 'pdf' => 'pdf', 'ppt' => 'presentation', 'pptx' => 'presentation',
                    'doc' => 'document', 'docx' => 'document', 'xlsx' => 'document');
                if (!isset($map[$ext])) {
                    throw new InvalidArgumentException(hkp_t('Upload MP4/WebM video, MP3 audio, PDF, PowerPoint or Word.'));
                }
                $row['media_path'] = $this->store_public('media', 'lessons', array_keys($map), $map[$ext] === 'video' ? 500 : 100);
                $row['media_type'] = $map[$ext];
                if ($map[$ext] === 'video') {
                    $row['video_source'] = 'upload';
                    $row['video_url'] = $row['media_path'];
                    $row['lesson_type'] = 'video';
                } elseif ($map[$ext] === 'pdf' && $type === 'text') {
                    $row['lesson_type'] = 'pdf';
                } elseif ($map[$ext] === 'presentation' && $type === 'text') {
                    $row['lesson_type'] = 'presentation';
                }
            }
            if ($id) {
                $before = $this->db->get_where('ha_lesson', array('id' => (int) $id))->row_array();
                $this->snapshot_lesson($id, 'Before edit');
                $this->db->where('id', (int) $id)->update('ha_lesson', $row);
                $this->ha_audit->log_change('lesson', (int) $id, $before, $row);
            } else {
                $max = (int) $this->db->select_max('sort_order')->get_where('ha_lesson', array('course_id' => $course_id))->row()->sort_order;
                $row += array('course_id' => $course_id, 'sort_order' => $max + 1, 'created_at' => date('Y-m-d H:i:s'));
                $this->db->insert('ha_lesson', $row);
                $id = (int) $this->db->insert_id();
                $this->ha_audit->log('create', 'lesson', $id, array('description' => 'Lesson ' . $title . ' created'));
            }
            foreach (array('en', 'ar') as $loc) {
                $t = array('title' => mb_substr(trim((string) $d['title_' . $loc]) ?: $title, 0, 190), 'objective' => mb_substr(trim((string) $d['objective_' . $loc]), 0, 500),
                    'body' => (string) $d['body_' . $loc], 'transcript' => (string) $d['transcript_' . $loc]);
                $ex = $this->db->get_where('ha_lesson_translation', array('lesson_id' => $id, 'locale' => $loc))->row_array();
                $ex ? $this->db->where('id', $ex['id'])->update('ha_lesson_translation', $t) : $this->db->insert('ha_lesson_translation', $t + array('lesson_id' => $id, 'locale' => $loc));
            }
            if (!empty($_FILES['attachment']['name'])) {
                $path = $this->store_public('attachment', 'lessons', array('pdf', 'ppt', 'pptx', 'doc', 'docx', 'xlsx', 'zip', 'jpg', 'png'), 100);
                $this->db->insert('ha_lesson_attachment', array('lesson_id' => $id, 'title_en' => $_FILES['attachment']['name'], 'title_ar' => $_FILES['attachment']['name'],
                    'file_path' => $path, 'mime_type' => (string) $_FILES['attachment']['type'], 'file_size' => (int) $_FILES['attachment']['size'], 'created_at' => date('Y-m-d H:i:s')));
            }
            $total = (int) $this->db->where(array('course_id' => $course_id, 'status' => 'published'))->count_all_results('ha_lesson');
            $this->db->where('course_id', $course_id)->update('ha_enrollment', array('lessons_total' => $total));
            $c = $this->db->get_where('ha_course', array('id' => $course_id))->row_array();
            if ($c['status'] === 'published') {
                $this->publish_side_effects($course_id);
            }
            return $id;
        }, hkp_t('Lesson saved.'), function ($lid) { return $this->saved_content_url('lessons', $lid, hkp_url('cms/lesson/' . $lid)); });
    }

    /** Keeps the previous lesson content so an edit never destroys it (ppt-features 11). */
    protected function snapshot_lesson($id, $note) {
        $l = $this->db->get_where('ha_lesson', array('id' => (int) $id))->row_array();
        $tr = $this->db->get_where('ha_lesson_translation', array('lesson_id' => (int) $id))->result_array();
        $v = 1 + (int) $this->db->select_max('version_no')->get_where('ha_lesson_version', array('lesson_id' => (int) $id))->row()->version_no;
        $this->db->insert('ha_lesson_version', array('lesson_id' => (int) $id, 'version_no' => $v, 'snapshot_json' => json_encode(array('lesson' => $l, 'translations' => $tr), JSON_UNESCAPED_UNICODE),
            'change_summary' => $note, 'changed_by' => $this->uid, 'created_at' => date('Y-m-d H:i:s')));
    }

    public function lesson_delete($id = 0) {
        $this->need('lessons.delete');
        $this->post_guard();
        $l = $this->db->get_where('ha_lesson', array('id' => (int) $id))->row_array();
        if (!$l || !$this->module_row($l['course_id'])) {
            show_404();
        }
        $done = $this->db->where('lesson_id', (int) $id)->count_all_results('ha_lesson_progress');
        $this->snapshot_lesson($id, 'Before archive');
        // A lesson learners have progress on is archived, not deleted: their records stay valid.
        if ($done) {
            $this->db->where('id', (int) $id)->update('ha_lesson', array('status' => 'archived', 'updated_at' => date('Y-m-d H:i:s')));
        } else {
            $this->db->where('id', (int) $id)->delete('ha_lesson');
        }
        $this->ha_audit->log($done ? 'update' : 'delete', 'lesson', (int) $id, array('description' => $done ? 'Lesson archived (has learner progress)' : 'Lesson deleted'));
        $this->back($done ? hkp_t('Lesson archived: learners already have progress on it.') : hkp_t('Lesson deleted.'), true, hkp_url('cms/module/' . $l['course_id']));
    }
}
