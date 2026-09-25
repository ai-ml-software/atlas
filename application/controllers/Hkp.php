<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/Hkp_Controller.php';

/**
 * altus HK&P — the Learner experience and shared workspace endpoints
 * (ppt-features 4A, 58, 77, 137, 138: "What do I need to do?").
 *
 *   /hkp                         dashboard (routes managers / Altus / executives to theirs)
 *   /hkp/learn[/paths|module/{id}|lesson/{id}]
 *   /hkp/knowledge[/item/{id}]   governed library, acknowledgement
 *   /hkp/search, /hkp/search_suggest
 *   /hkp/assistant               governed AI
 *   /hkp/competencies, /hkp/actions, /hkp/certificates, /hkp/readiness/me
 *   /hkp/notifications, /hkp/profile, /hkp/file/{token}, /hkp/manifest, /hkp/sw.js
 */
class Hkp extends Hkp_Controller {

    protected function public_action() {
        $m = $this->router->fetch_method();
        return in_array($m, array('manifest', 'sw'), true);
    }

    // ------------------------------------------------------------ dashboard

    public function index() {
        $p = $this->ha_auth->profile();
        $has_role = $p && $p['job_role_id'];
        if (!$has_role && !$this->input->get('me')) {
            if ($this->can('executive.view') && !$this->can('learners.view')) {
                redirect(hkp_url('exec'));
            }
            if ($this->ha_auth->is_system_scoped() && $this->can('organizations.view')) {
                redirect(hkp_url('admin'));
            }
            if ($this->can(array('learners.view', 'practicals.assess'))) {
                redirect(hkp_url('team'));
            }
        }
        $this->load->library(array('ha_learning', 'ha_readiness', 'ha_competency', 'ha_certification'));
        $plan = $this->ha_learning->plan($this->uid);
        $next = null;
        foreach ($plan as $row) {
            if ($row['state'] !== 'completed' && $row['state'] !== 'exempted' && $row['next_lesson_id']) {
                $next = $row;
                break;
            }
        }
        $done = count(array_filter($plan, function ($r) { return $r['state'] === 'completed'; }));
        $this->render('dashboard', array(
            'plan' => array_slice($plan, 0, 8), 'plan_total' => count($plan), 'plan_done' => $done, 'next' => $next,
            'readiness' => $this->ha_readiness->current($this->uid),
            'competencies' => $this->ha_competency->profile($this->uid),
            'recommend' => $this->ha_competency->recommendations($this->uid, 5),
            'actions' => $this->db->where('user_id', $this->uid)->where_not_in('status', array('completed'))->order_by('due_at')->limit(5)->get('ha_action_plan')->result_array(),
            'certs' => $this->db->where('user_id', $this->uid)->order_by('issued_at', 'DESC')->limit(3)->get('ha_certificate')->result_array(),
            'deadlines' => array_values(array_filter($plan, function ($r) { return $r['due_at'] && $r['state'] !== 'completed' && strtotime($r['due_at']) < strtotime('+14 days'); })),
            'notices' => $this->ha_notify->inbox($this->uid, 5),
            'acks' => $this->db->select('a.*, t.title')->from('ha_sop_acknowledgement a')->join('ha_sop_version_translation t', "t.version_id = a.version_id AND t.locale = 'en'", 'left')
                ->where(array('a.user_id' => $this->uid, 'a.status' => 'required'))->limit(5)->get()->result_array(),
        ), hkp_t('Dashboard'), 'home');
    }

    public function context() {
        $this->post_guard();
        $pid = (int) $this->input->post('property_id');
        if ($pid && !$this->ha_auth->can_property($pid)) {
            $this->back(hkp_t('That property is outside your scope.'), false);
            return;
        }
        $this->session->set_userdata('hkp_property', $pid ?: null);
        $this->back(hkp_t('Context updated.'));
    }

    // ------------------------------------------------------------- learning

    public function learn($a = '', $id = 0) {
        $this->need('courses.view');
        $this->load->library('ha_learning');
        $L = $this->ha_learning;
        if ($a === 'paths') {
            $p = $this->ha_auth->profile();
            $req = $p && $p['job_role_id'] ? $this->db->select('rr.*, t.title_en, t.title_ar, t.code, d.name_en AS domain_en, d.name_ar AS domain_ar')->from('ha_role_requirement rr')
                ->join('ha_track t', "t.id = rr.item_id AND rr.item_type = 'track'")->join('ha_domain d', 'd.id = t.domain_id')
                ->where('rr.job_role_id', $p['job_role_id'])->where_in('rr.property_key', array(0, (int) $p['property_id']))->order_by('rr.sort_order')->get()->result_array() : array();
            foreach ($req as &$r) {
                $r['modules'] = $this->track_modules($r['item_id']);
            }
            unset($r);
            $domains = $this->db->order_by('sort_order')->get_where('ha_domain', array('status' => 'active'))->result_array();
            $tracks = array();
            foreach ($this->db->where('status', 'published')->group_start()->where('organization_id IS NULL', null, false)
                ->or_where('organization_id', (int) ($p ? $p['organization_id'] : 0))->group_end()->order_by('sort_order')->get('ha_track')->result_array() as $t) {
                $tracks[$t['domain_id']][] = $t;
            }
            $this->render('learn_paths', array('required' => $req, 'domains' => $domains, 'tracks' => $tracks,
                'role' => $p && $p['job_role_id'] ? $this->db->get_where('ha_job_role', array('id' => $p['job_role_id']))->row_array() : null), hkp_t('Learning paths'), 'paths');
            return;
        }
        if ($a === 'track') {
            $t = $this->db->get_where('ha_track', array('id' => (int) $id, 'status' => 'published'))->row_array();
            if (!$t) {
                show_404();
            }
            $this->render('learn_track', array('track' => $t, 'modules' => $this->track_modules($t['id'])), hkp_pick($t, 'title'), 'paths');
            return;
        }
        if ($a === 'module') {
            if (!$L->course_visible($id, $this->uid)) {
                show_404();
            }
            $c = $L->course($id);
            $e = $this->db->get_where('ha_enrollment', array('user_id' => $this->uid, 'course_id' => (int) $id))->row_array();
            $done = $e ? array_map('intval', array_column($this->db->select('lesson_id')->get_where('ha_lesson_progress', array('enrollment_id' => $e['id'], 'status' => 'completed'))->result_array(), 'lesson_id')) : array();
            $this->load->library('ha_knowledge');
            $this->ha_knowledge->record_view('course', $id, $this->uid);
            $this->render('learn_module', array('c' => $c, 'enrollment' => $e, 'done' => $done, 'unmet' => $L->unmet_prerequisites($id, $this->uid),
                'attempts' => $c['assessments'] ? $this->db->where('user_id', $this->uid)->where_in('assessment_id', array_column($c['assessments'], 'id'))->order_by('id', 'DESC')->get('ha_assessment_attempt')->result_array() : array(),
                'skills' => $this->db->select('s.*')->from('ha_course_skill cs')->join('ha_skill s', 's.id = cs.skill_id')->where('cs.course_id', (int) $id)->get()->result_array()),
                $c['title'], 'learn');
            return;
        }
        if ($a === 'lesson') {
            try {
                $open = $L->open_lesson($this->uid, $id);
            } catch (RuntimeException $e) {
                $this->back($e->getMessage(), false, hkp_url('learn'));
                return;
            }
            $l = $L->lesson($id);
            $c = $L->course($l['course_id']);
            $ids = array_map('intval', array_column($c['lessons'], 'id'));
            $pos = array_search((int) $id, $ids, true);
            $this->render('learn_lesson', array('l' => $l, 'c' => $c, 'progress' => $open['progress'],
                'prev' => $pos > 0 ? $ids[$pos - 1] : null, 'next' => $pos !== false && $pos < count($ids) - 1 ? $ids[$pos + 1] : null,
                'video' => $this->video_for($l)), $l['title'], 'learn');
            return;
        }
        if ($a === 'track_time') {
            $this->post_guard();
            $L->track($this->uid, (int) $id, (int) $this->input->post('seconds'), $this->input->post('position'));
            $this->json(array('ok' => true));
            return;
        }
        if ($a === 'complete') {
            $this->post_guard();
            try {
                $e = $L->complete_lesson($this->uid, (int) $id);
                $l = $this->db->get_where('ha_lesson', array('id' => (int) $id))->row_array();
                $next = $L->next_lesson($e['id'], $l['course_id']);
                if ($e['status'] === 'completed') {
                    $this->back(hkp_t('Module complete. Well done.'), true, hkp_url('learn/module/' . $l['course_id']));
                } elseif ($next) {
                    $this->back(hkp_t('Lesson complete.'), true, hkp_url('learn/lesson/' . $next));
                } else {
                    $this->back(hkp_t('All lessons done. Pass the module assessment to complete it.'), true, hkp_url('learn/module/' . $l['course_id']));
                }
            } catch (RuntimeException $e) {
                $this->back($e->getMessage(), false);
            }
            return;
        }
        if ($a === 'enroll') {
            $this->post_guard();
            $this->attempt(function () use ($L, $id) { return $L->enroll($this->uid, (int) $id, 'self'); }, hkp_t('Added to your learning.'), hkp_url('learn/module/' . (int) $id));
            return;
        }
        $this->render('learn', array('plan' => $L->plan($this->uid)), hkp_t('My learning'), 'learn');
    }

    protected function track_modules($track_id) {
        $loc = hkp_locale();
        $rows = $this->db->select("tm.*, c.code, c.duration_minutes, COALESCE(NULLIF(t.title, ''), te.title) AS title, e.status AS e_status, e.progress_percentage", false)
            ->from('ha_track_module tm')->join('ha_course c', 'c.id = tm.course_id')
            ->join('ha_course_translation t', 't.course_id = c.id AND t.locale = ' . $this->db->escape($loc), 'left')
            ->join('ha_course_translation te', "te.course_id = c.id AND te.locale = 'en'", 'left')
            ->join('ha_enrollment e', 'e.course_id = c.id AND e.user_id = ' . (int) $this->uid, 'left')
            ->where('tm.track_id', (int) $track_id)->order_by('tm.sort_order')->get()->result_array();
        return $rows;
    }

    /** Best playable source for a video lesson: published academy video, YouTube/Vimeo embed or direct URL. */
    protected function video_for(array $l) {
        if ($l['lesson_type'] !== 'video') {
            return null;
        }
        $src = $this->db->table_exists('ha_lesson_video_source') ? $this->db->where(array('lesson_id' => $l['id'], 'status' => 'live'))
            ->order_by("FIELD(provider,'academy','upload','youtube','vimeo','url')", '', false)->limit(1)->get('ha_lesson_video_source')->row_array() : null;
        $url = $src ? ($src['provider'] === 'academy' || $src['provider'] === 'upload' ? $src['watch_url'] : ($src['embed_url'] ?: $src['watch_url'])) : $l['video_url'];
        if (!$url) {
            return null;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return array('type' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1]);
        }
        if (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
            return array('type' => 'embed', 'src' => 'https://player.vimeo.com/video/' . $m[1]);
        }
        return array('type' => 'file', 'src' => preg_match('~^https?://~', $url) ? $url : base_url(ltrim($url, '/')));
    }

    // ------------------------------------------------------------ knowledge

    public function knowledge($a = '', $id = 0) {
        $this->need('knowledge.view');
        $this->load->library('ha_knowledge');
        $K = $this->ha_knowledge;
        if ($a === 'item') {
            if (!$K->can_view($id, $this->uid)) {
                show_404();   // not "forbidden": a restricted item must not even be confirmed to exist
            }
            $doc = $K->get($id, $this->input->get('v') ? (int) $this->input->get('v') : null);
            if ($doc['version'] && $doc['version']['status'] !== 'published' && !$K->can_edit($doc, $this->uid) && !$this->can(array('knowledge.review', 'knowledge.approve'))) {
                show_404();
            }
            $K->record_view('knowledge', $id, $this->uid);
            $this->render('knowledge_item', array('doc' => $doc, 'ack' => $K->acknowledgement($id, $this->uid), 'can_edit' => $K->can_edit($doc, $this->uid)),
                hkp_pick($doc['text'] ? array('title_en' => isset($doc['text']['en']) ? $doc['text']['en']['title'] : '', 'title_ar' => isset($doc['text']['ar']) ? $doc['text']['ar']['title'] : '') : array(), 'title'), 'knowledge');
            return;
        }
        if ($a === 'ack') {
            $this->post_guard();
            $this->attempt(function () use ($K, $id) { $K->acknowledge((int) $id, $this->uid); }, hkp_t('Acknowledged. Thank you.'));
            return;
        }
        $f = array('type' => $this->input->get('type'), 'domain_id' => (int) $this->input->get('domain'), 'q' => trim((string) $this->input->get('q')));
        $this->render('knowledge', array('items' => $K->visible_items($this->uid, $f), 'f' => $f, 'types' => Ha_knowledge::$types,
            'domains' => $this->db->order_by('sort_order')->get_where('ha_domain', array('status' => 'active'))->result_array()), hkp_t('Knowledge'), 'knowledge');
    }

    // --------------------------------------------------------------- search

    public function search() {
        $this->load->library('ha_knowledge');
        $term = trim((string) $this->input->get('q'));
        $f = array('type' => $this->input->get('type') ?: null, 'domain_id' => (int) $this->input->get('domain') ?: null, 'locale' => $this->input->get('locale') ?: null, 'limit' => 40);
        $results = $term !== '' ? $this->ha_knowledge->search($this->uid, $term, $f) : array();
        $this->render('search', array('q' => $term, 'results' => $results, 'f' => $f, 'types' => Ha_knowledge::$types,
            'domains' => $this->db->order_by('sort_order')->get_where('ha_domain', array('status' => 'active'))->result_array()), hkp_t('Search'), '');
    }

    public function search_suggest() {
        $this->load->library('ha_knowledge');
        $term = trim((string) $this->input->get('q'));
        $out = array();
        if (mb_strlen($term) >= 2) {
            foreach (array_slice($this->ha_knowledge->search($this->uid, $term, array('limit' => 12)), 0, 7) as $r) {
                $out[] = array('title' => $r['title'], 'url' => $r['url'], 'kind' => hkp_label($r['item_type']) . ($r['version'] ? ' · v' . $r['version'] : ''));
            }
        }
        $this->json(array('results' => $out));
    }

    // ------------------------------------------------------------ assistant

    public function assistant() {
        $this->need('ai.use');
        $this->load->library('ha_governed_ai');
        if ($this->input->method() === 'post') {
            $this->post_guard();
            try {
                $r = $this->ha_governed_ai->ask((string) $this->input->post('question'), hkp_locale());
                $this->json(array('ok' => true) + $r);
            } catch (Exception $e) {
                $this->json(array('ok' => false, 'error' => $e->getMessage()));
            }
            return;
        }
        $history = $this->db->order_by('id', 'DESC')->limit(10)->get_where('ha_ai_query', array('user_id' => $this->uid))->result_array();
        $this->render('assistant', array('history' => array_reverse($history)), hkp_t('AI assistant'), 'assistant');
    }

    // --------------------------------------------------------- competencies

    public function competencies($a = '', $id = 0) {
        $this->need('competencies.view');
        $this->load->library(array('ha_competency', 'ha_readiness'));
        if ($a === 'rubric') {
            $this->load->library('ha_practical');
            $r = $this->ha_practical->rubric($id);
            if (!$r || $r['status'] !== 'published') {
                show_404();
            }
            $this->render('rubric_view', array('r' => $r), hkp_pick($r, 'title'), 'competencies');
            return;
        }
        if ($a === 'request') {
            $this->post_guard();
            $this->need('reassessments.request');
            $this->load->library('ha_action_plans');
            $gap = $this->db->get_where('ha_competency_gap', array('id' => (int) $id, 'user_id' => $this->uid))->row_array();
            if (!$gap) {
                show_404();
            }
            $this->attempt(function () use ($gap) {
                return $this->ha_action_plans->request_reassessment(array('gap_id' => $gap['id'], 'note' => $this->input->post('note')), $this->uid);
            }, hkp_t('Reassessment requested. Your supervisor will review it.'));
            return;
        }
        $uid = $this->uid;
        $this->render('competencies', array('profile' => $this->ha_competency->profile($uid), 'history' => $this->ha_competency->history($uid),
            'levels' => $this->ha_competency->levels($this->ctx['organization_id']),
            'gaps' => $this->ha_competency->open_gaps(array('user_ids' => array($uid))),
            'reassess' => $this->db->order_by('id', 'DESC')->get_where('ha_reassessment', array('user_id' => $uid))->result_array(),
            'recommend' => $this->ha_competency->recommendations($uid, 8)), hkp_t('My competencies'), 'competencies');
    }

    public function readiness($who = 'me') {
        $this->need('readiness.view');
        $this->load->library('ha_readiness');
        $this->render('readiness_me', array('r' => $this->ha_readiness->current($this->uid), 'eval' => $this->ha_readiness->evaluate($this->uid)),
            hkp_t('My readiness'), 'competencies');
    }

    // -------------------------------------------------------------- actions

    public function actions($a = '', $id = 0) {
        $this->need('action_plans.view');
        $this->load->library('ha_action_plans');
        $AP = $this->ha_action_plans;
        if ($a === 'view' || $a === 'evidence' || $a === 'move') {
            $ap = $AP->get($id);
            if (!$ap || ((int) $ap['user_id'] !== $this->uid && !$this->ha_auth->can_user($ap['user_id']))) {
                show_404();
            }
            if ($a === 'evidence') {
                $this->post_guard();
                $this->attempt(function () use ($AP, $id) {
                    return $AP->add_evidence((int) $id, $this->input->post(), $this->uid, isset($_FILES['file']) ? $_FILES['file'] : null);
                }, hkp_t('Evidence added.'));
                return;
            }
            if ($a === 'move') {
                $this->post_guard();
                $to = (string) $this->input->post('to');
                $this->attempt(function () use ($AP, $id, $to) { return $AP->transition((int) $id, $to, $this->uid, $this->input->post('note')); }, hkp_t('Action plan updated.'));
                return;
            }
            $allowed = array();
            foreach (Ha_action_plans::transitions()[$ap['status']] as $to => $who) {
                if ($who !== 'system' && $AP->why_not($ap, $to, $this->uid) === '') {
                    $allowed[] = $to;
                }
            }
            $this->render('action_view', array('ap' => $ap, 'allowed' => $allowed, 'is_mine' => (int) $ap['user_id'] === $this->uid), $ap['title'], (int) $ap['user_id'] === $this->uid ? 'actions' : 'team_actions');
            return;
        }
        $this->render('actions', array('rows' => $AP->for_users(array($this->uid))), hkp_t('My action plans'), 'actions');
    }

    // --------------------------------------------------------- certificates

    public function certificates($a = '', $id = 0) {
        $this->need('certificates.view');
        $this->load->library('ha_certification');
        if ($a !== '') {
            $c = $this->db->get_where('ha_certificate', array('id' => (int) $id))->row_array();
            if (!$c || ((int) $c['user_id'] !== $this->uid && !$this->ha_auth->can_user($c['user_id']))) {
                show_404();
            }
            if ($a === 'pdf' || $a === 'png') {
                $bin = $a === 'pdf' ? $this->ha_certification->pdf($c) : $this->ha_certification->png($c);
                $this->output->set_content_type($a === 'pdf' ? 'application/pdf' : 'image/png')
                    ->set_header('Content-Disposition: ' . ($a === 'pdf' ? 'attachment' : 'inline') . '; filename="' . $c['certificate_no'] . '.' . $a . '"')
                    ->set_output($bin);
                return;
            }
            $this->load->library('ha_qr');
            $this->render('certificate_view', array('c' => $c, 'qr' => $this->ha_qr->svg($this->ha_certification->verify_url($c), 5, 2),
                'verify_url' => $this->ha_certification->verify_url($c), 'evidence' => json_decode((string) $c['evidence_json'], true) ?: array(),
                'property' => $c['property_id'] ? $this->db->get_where('ha_property', array('id' => $c['property_id']))->row_array() : null), $c['certificate_no'], 'certificates');
            return;
        }
        $this->load->library('ha_competency');
        $p = $this->ha_auth->profile();
        $programs = $p && $p['job_role_id'] ? $this->db->get_where('ha_certification_program', array('job_role_id' => $p['job_role_id'], 'status' => 'published'))->result_array() : array();
        foreach ($programs as &$pr) {
            $pr['checks'] = $this->ha_certification->check($pr, $this->uid);
        }
        unset($pr);
        $this->render('certificates', array('rows' => $this->db->order_by('issued_at', 'DESC')->get_where('ha_certificate', array('user_id' => $this->uid))->result_array(),
            'programs' => $programs), hkp_t('My certificates'), 'certificates');
    }

    // --------------------------------------------------------- notifications

    public function notifications($a = '', $id = 0) {
        if ($a === 'read') {
            $this->post_guard();
            $this->ha_notify->mark_read($this->uid, $id ?: null);
            $this->back(hkp_t('Marked as read.'));
            return;
        }
        $this->render('notifications', array('rows' => $this->ha_notify->inbox($this->uid, 100)), hkp_t('Notifications'), '');
    }

    public function profile() {
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $loc = $this->input->post('locale') === 'ar' ? 'ar' : 'en';
            $this->db->where('user_id', $this->uid)->update('ha_profile', array('locale' => $loc, 'timezone' => mb_substr((string) $this->input->post('timezone') ?: 'Asia/Riyadh', 0, 64), 'updated_at' => date('Y-m-d H:i:s')));
            $this->session->set_userdata('hkp_locale', $loc);
            foreach ((array) $this->input->post('pref') as $event => $ch) {
                if (!isset(Ha_notify::events()[$event])) {
                    continue;
                }
                $row = array('in_app' => !empty($ch['in_app']) ? 1 : 0, 'email' => !empty($ch['email']) ? 1 : 0, 'sms' => 0, 'whatsapp' => 0);
                $ex = $this->db->get_where('ha_notification_preference', array('user_id' => $this->uid, 'event_code' => $event))->row_array();
                $ex ? $this->db->where('id', $ex['id'])->update('ha_notification_preference', $row)
                    : $this->db->insert('ha_notification_preference', $row + array('user_id' => $this->uid, 'event_code' => $event));
            }
            $this->back(hkp_t('Profile saved.'), true, hkp_url('profile?lang=' . $loc));
            return;
        }
        $p = $this->ha_auth->profile();
        $prefs = array();
        foreach ($this->db->get_where('ha_notification_preference', array('user_id' => $this->uid))->result_array() as $r) {
            $prefs[$r['event_code']] = $r;
        }
        $this->render('profile', array('p' => $p, 'prefs' => $prefs, 'events' => Ha_notify::events(),
            'role' => $p && $p['job_role_id'] ? $this->db->get_where('ha_job_role', array('id' => $p['job_role_id']))->row_array() : null,
            'property' => $p && $p['property_id'] ? $this->db->get_where('ha_property', array('id' => $p['property_id']))->row_array() : null,
            'grants' => $this->ha_auth->role_codes()), hkp_t('Profile'), '');
    }

    // ------------------------------------------------------------------ files

    /** Private files: authenticated, authorised against the owning record, never a public URL (section 154). */
    public function file($token = '') {
        $this->load->library('ha_files');
        $f = $this->ha_files->by_token($token);
        if (!$f) {
            show_404();
        }
        $ok = false;
        switch ($f['entity_type']) {
            case 'action_plan':
                $ap = $this->db->get_where('ha_action_plan', array('id' => $f['entity_id']))->row_array();
                $ok = $ap && ((int) $ap['user_id'] === $this->uid || $this->ha_auth->can_user($ap['user_id']));
                break;
            case 'evidence':
                $ev = $this->db->get_where('ha_evidence', array('id' => $f['entity_id']))->row_array();
                $ok = $ev && ((int) $ev['user_id'] === $this->uid || $this->ha_auth->can_user($ev['user_id']));
                break;
            case 'engagement':
                $ok = $this->can('engagements.view') && (!$f['organization_id'] || $this->ha_auth->can_organization($f['organization_id']));
                break;
            case 'knowledge':
                $this->load->library('ha_knowledge');
                $ok = $this->ha_knowledge->can_view($f['entity_id'], $this->uid);
                break;
            default:
                $ok = (int) $f['owner_user_id'] === $this->uid || $this->ha_auth->is_system_scoped();
        }
        if (!$ok) {
            show_404();
        }
        $path = $this->ha_files->absolute_path($f);
        if (!$path) {
            show_404();
        }
        $this->ha_audit->log('view_private', 'file', (int) $f['id'], array('description' => 'Downloaded ' . $f['original_name']));
        $inline = in_array($f['mime_type'], array('application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'video/mp4'), true) && $this->input->get('inline');
        header('Content-Type: ' . $f['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($f['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    // -------------------------------------------------------------------- PWA

    public function manifest() {
        $b = $this->ha_tenant->brand();
        $this->json(array('name' => $b['brand_name_en'], 'short_name' => 'altus HK&P', 'start_url' => hkp_url(), 'scope' => hkp_url() . '/',
            'display' => 'standalone', 'background_color' => $b['color_surface'], 'theme_color' => $b['color_secondary'], 'dir' => 'auto', 'lang' => 'en',
            'icons' => array(array('src' => base_url('logo.png'), 'sizes' => '192x192', 'type' => 'image/png'), array('src' => base_url('logo.png'), 'sizes' => '512x512', 'type' => 'image/png'))));
        $this->output->set_content_type('application/manifest+json', 'utf-8');
    }

    /** Service worker: caches the app shell for fast loads; learner data is always fetched live. */
    public function sw() {
        $css = base_url('assets/hkp/hkp.css?v=3');
        $js = base_url('assets/hkp/hkp.js?v=3');
        $js_src = "const C='hkp-shell-v3';self.addEventListener('install',e=>{e.waitUntil(caches.open(C).then(c=>c.addAll(" . json_encode(array($css, $js)) . ")));self.skipWaiting();});"
            . "self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==C).map(x=>caches.delete(x)))));});"
            . "self.addEventListener('fetch',e=>{const u=e.request.url;if(e.request.method==='GET'&&/\\/assets\\/(hkp|academy\\/fonts)\\//.test(u)){e.respondWith(caches.match(e.request).then(r=>r||fetch(e.request).then(res=>{const cp=res.clone();caches.open(C).then(c=>c.put(e.request,cp));return res;})));}});";
        $this->output->set_content_type('application/javascript', 'utf-8')->set_header('Service-Worker-Allowed: ' . parse_url(hkp_url(), PHP_URL_PATH) . '/')->set_output($js_src);
    }
}
