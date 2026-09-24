<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * AI Studio (admin and instructor panel).
 *
 *   /ha_ai                     overview: providers, queue, worker, spend
 *   /ha_ai/providers           every provider in the registry, filterable by country and capability
 *   /ha_ai/provider/{slug}     key, region, endpoint settings, test, live model sync
 *   /ha_ai/routes              which provider + model runs each task
 *   /ha_ai/studio              generate courses and lesson scripts, queue renders
 *   /ha_ai/job/{id}            review, edit, approve, publish
 *   /ha_ai/usage               calls, tokens and latency per provider and task
 *   /ha_ai/assistant           writing assistant on the "assistant" route
 *
 * Permissions (seeded in 001_rbac): ai.view, ai.configure, ai.generate,
 * ai.approve, ai.publish. The legacy Academy LMS admin (role_id 1) holds all
 * of them. Every POST carries a CSRF token and every check is server side;
 * hidden buttons are a convenience, not the control.
 */
class Ha_ai extends CI_Controller {

    private $is_admin;
    private $user_id;

    public function __construct() {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->load->helper('ha_security');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->output->set_header('X-Frame-Options: SAMEORIGIN');

        if (!$this->session->userdata('admin_login') && !$this->session->userdata('user_login')) {
            redirect(site_url('login'), 'refresh');
        }
        $this->is_admin = (bool) $this->session->userdata('admin_login');
        $this->user_id = (int) $this->session->userdata('user_id');
        $this->load->library('ha_auth');
        $this->load->library('ha_ai_gateway');
        $this->ha_ai_gateway->context['user_id'] = $this->user_id;
    }

    // ------------------------------------------------------------ guards

    private function can($permission) {
        return $this->is_admin || $this->ha_auth->has('ai.' . $permission);
    }

    private function need($permission) {
        if ($this->can($permission)) {
            return true;
        }
        if ($this->input->is_ajax_request() || $this->input->method() === 'post') {
            $this->json(array('ok' => false, 'error' => 'You do not have the ai.' . $permission . ' permission.'), 403);
            $this->output->_display();
            exit;
        }
        $this->session->set_flashdata('error_message', 'You do not have permission to open that AI Studio page.');
        redirect(site_url($this->is_admin ? 'admin/dashboard' : 'user'), 'refresh');
    }

    private function post_guard() {
        if ($this->input->method() !== 'post' || !ha_csrf_valid()) {
            // 403 rather than 419: CodeIgniter's set_status_header() rejects non-standard codes.
            $this->json(array('ok' => false, 'error' => 'Your session expired. Reload the page and try again.'), 403);
            $this->output->_display();
            exit;
        }
    }

    private function json($data, $status = 200) {
        $this->output->set_status_header($status)->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function page($name, $title, array $data = array()) {
        $data['page_name'] = '../ha_ai/' . $name;
        $data['page_title'] = $title;
        $data['ha_can'] = array();
        foreach (array('view', 'configure', 'generate', 'approve', 'publish') as $p) {
            $data['ha_can'][$p] = $this->can($p);
        }
        $this->load->view('backend/index', $data);
    }

    private function back($message, $ok = true, $to = null) {
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message', $message);
        redirect($to ?: ($this->input->server('HTTP_REFERER') ?: site_url('ha_ai')), 'refresh');
    }

    // ---------------------------------------------------------- overview

    public function index() {
        $this->need('view');
        $this->load->library('ha_ai_studio');
        $this->load->library('ha_video_renderer');
        require_once APPPATH . 'controllers/Ha_ai_cli.php';
        $providers = $this->ha_ai_gateway->providers();
        $since = date('Y-m-d H:i:s', time() - 30 * 86400);
        $usage = $this->db->select('COUNT(*) calls, SUM(ok) ok_calls, SUM(input_tokens) tin, SUM(output_tokens) tout, SUM(characters) chars', false)
            ->where('created_at >=', $since)->get('ha_ai_usage')->row_array();
        $this->page('dashboard', 'AI Studio', array(
            'providers' => $providers,
            'enabled' => array_filter($providers, function ($p) { return $p['enabled']; }),
            'routes' => $this->ha_ai_gateway->routes(),
            'tasks' => $this->ha_ai_gateway->tasks(),
            'counts' => $this->ha_ai_studio->counts(),
            'recent' => $this->ha_ai_studio->jobs($this->can('approve') ? array() : array('created_by' => $this->user_id), 8),
            'usage' => $usage,
            'heartbeat' => Ha_ai_cli::read_heartbeat(),
            'renderer' => $this->ha_video_renderer->status(),
            'gap' => count($this->ha_ai_studio->lessons_without_video()),
        ));
    }

    // --------------------------------------------------------- providers

    public function providers() {
        $this->need('configure');
        $this->page('providers', 'AI providers', array('providers' => $this->ha_ai_gateway->providers()));
    }

    public function provider($slug = '') {
        $this->need('configure');
        $p = $this->ha_ai_gateway->provider($slug);
        if (!$p) {
            show_404();
        }
        if ($this->input->method() === 'post') {
            $this->post_guard();
            try {
                $this->ha_ai_gateway->save_provider($slug, array(
                    'enabled' => $this->input->post('enabled'),
                    'api_key' => $this->input->post('api_key'),
                    'region' => $this->input->post('region'),
                    'base_url' => $this->input->post('base_url'),
                    'settings' => (array) $this->input->post('settings'),
                ), $this->user_id);
                $this->load->library('ha_audit');
                $this->ha_audit->log('update', 'ha_ai_provider', null, array('user_id' => $this->user_id,
                    'description' => 'AI provider ' . $slug . ' settings saved' . ($this->input->post('api_key') ? ' (key changed)' : '')));
                $this->back($p['name'] . ' saved.', true, site_url('ha_ai/provider/' . $slug));
            } catch (Exception $e) {
                $this->back($e->getMessage(), false, site_url('ha_ai/provider/' . $slug));
            }
            return;
        }
        $this->page('provider', $p['name'], array(
            'p' => $p,
            'models' => $this->ha_ai_gateway->models($slug, false),
            'endpoint' => $this->ha_ai_gateway->endpoint($p),
        ));
    }

    public function provider_add() {
        $this->need('configure');
        $this->post_guard();
        $slug = strtolower(preg_replace('/[^a-z0-9_]/i', '_', (string) $this->input->post('slug')));
        try {
            if (strlen($slug) < 2 || $this->ha_ai_gateway->provider($slug)) {
                throw new InvalidArgumentException('Choose a new, unique id (letters, numbers, underscore).');
            }
            if (!trim((string) $this->input->post('base_url'))) {
                throw new InvalidArgumentException('A custom provider needs its OpenAI-compatible base URL.');
            }
            $this->ha_ai_gateway->save_provider($slug, array(
                'custom' => true, 'enabled' => 1, 'api_key' => $this->input->post('api_key'),
                'base_url' => $this->input->post('base_url'),
                'settings' => array('name' => mb_substr((string) $this->input->post('name'), 0, 80) ?: $slug, 'api_style' => 'openai'),
            ), $this->user_id);
            $this->back('Custom provider added.', true, site_url('ha_ai/provider/' . $slug));
        } catch (Exception $e) {
            $this->back($e->getMessage(), false, site_url('ha_ai/providers'));
        }
    }

    public function provider_test($slug = '') {
        $this->need('configure');
        $this->post_guard();
        try {
            $this->json($this->ha_ai_gateway->test($slug));
        } catch (Exception $e) {
            $this->json(array('ok' => false, 'error' => $e->getMessage()));
        }
    }

    public function provider_sync($slug = '') {
        $this->need('configure');
        $this->post_guard();
        try {
            $models = $this->ha_ai_gateway->sync_models($slug);
            $this->json(array('ok' => true, 'count' => count($models)));
        } catch (Exception $e) {
            $this->json(array('ok' => false, 'error' => $e->getMessage()));
        }
    }

    public function models_json($slug = '') {
        $this->need('configure');
        $out = array();
        foreach ($this->ha_ai_gateway->models($slug) as $m) {
            $out[] = array('id' => $m['model_id'], 'label' => $m['label'] ?: $m['model_id']);
        }
        $this->json(array('ok' => true, 'models' => $out));
    }

    // ------------------------------------------------------------ routes

    public function routes() {
        $this->need('configure');
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $errors = array();
            $posted = (array) $this->input->post('route');
            foreach ($this->ha_ai_gateway->tasks() as $task => $cfg) {
                $r = isset($posted[$task]) && is_array($posted[$task]) ? $posted[$task] : array();
                if (!$r) {
                    continue;
                }
                $options = array();
                foreach (preg_split('/\r?\n/', (string) (isset($r['options']) ? $r['options'] : '')) as $line) {
                    if (strpos($line, '=') !== false) {
                        list($k, $v) = array_map('trim', explode('=', $line, 2));
                        if ($k !== '') {
                            $options[preg_replace('/[^a-z0-9_]/i', '', $k)] = $v;
                        }
                    }
                }
                try {
                    $model = trim((string) (isset($r['model_custom']) && $r['model_custom'] !== '' ? $r['model_custom'] : (isset($r['model']) ? $r['model'] : '')));
                    $this->ha_ai_gateway->set_route($task, isset($r['provider']) ? $r['provider'] : '', $model, array(
                        'temperature' => isset($r['temperature']) ? $r['temperature'] : null,
                        'max_tokens' => isset($r['max_tokens']) ? $r['max_tokens'] : null,
                        'options' => $options,
                    ), $this->user_id);
                } catch (Exception $e) {
                    $errors[] = $task . ': ' . $e->getMessage();
                }
            }
            $this->back($errors ? implode(' | ', $errors) : 'Task routing saved.', !$errors, site_url('ha_ai/routes'));
            return;
        }
        $providers = $this->ha_ai_gateway->providers();
        $models = array();
        foreach ($providers as $slug => $p) {
            if ($p['enabled']) {
                $models[$slug] = $this->ha_ai_gateway->models($slug);
            }
        }
        $this->page('routes', 'AI task routing', array(
            'providers' => $providers, 'models' => $models,
            'tasks' => $this->ha_ai_gateway->tasks(), 'routes' => $this->ha_ai_gateway->routes(),
        ));
    }

    // ------------------------------------------------------------ studio

    public function studio() {
        $this->need('generate');
        $this->load->library('ha_ai_studio');
        $course_id = (int) $this->input->get('course');
        $courses = $this->db->select('c.id, c.code, t.title')->from('ha_course c')
            ->join('ha_course_translation t', "t.course_id = c.id AND t.locale = 'en'", 'left')
            ->order_by('c.code')->get()->result_array();
        $categories = $this->db->select('c.code, t.name')->from('ha_category c')
            ->join('ha_category_translation t', "t.category_id = c.id AND t.locale = 'en'")
            ->where('c.status', 'active')->where('c.parent_id IS NULL', null, false)->order_by('c.sort_order')->get()->result_array();
        $filter = $this->can('approve') ? array() : array('created_by' => $this->user_id);
        if ($this->input->get('status')) {
            $filter['status'] = $this->input->get('status');
        }
        $this->page('studio', 'AI course studio', array(
            'courses' => $courses, 'categories' => $categories, 'course_id' => $course_id,
            'gap' => $this->ha_ai_studio->lessons_without_video($course_id ?: null, 300),
            'jobs' => $this->ha_ai_studio->jobs($filter, 60),
            'routes' => $this->ha_ai_gateway->routes(),
        ));
    }

    public function queue_course() {
        $this->need('generate');
        $this->post_guard();
        $this->load->library('ha_ai_studio');
        try {
            $id = $this->ha_ai_studio->queue_course($this->input->post(), $this->user_id);
            $this->kick();
            $this->back('Course draft queued (job #' . $id . ').', true, site_url('ha_ai/job/' . $id));
        } catch (Exception $e) {
            $this->back($e->getMessage(), false);
        }
    }

    public function queue_scripts() {
        $this->need('generate');
        $this->post_guard();
        $this->load->library('ha_ai_studio');
        $ids = array_slice(array_map('intval', (array) $this->input->post('lesson_ids')), 0, 100);
        $queued = 0;
        $errors = array();
        foreach ($ids as $lesson_id) {
            try {
                $this->ha_ai_studio->queue_script($lesson_id, array(
                    'minutes' => $this->input->post('minutes'), 'notes' => $this->input->post('notes'),
                    'then_render' => $this->input->post('then_render'),
                ), $this->user_id);
                $queued++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($queued) {
            $this->kick();
        }
        $this->back($queued . ' lesson script(s) queued.' . ($errors ? ' Skipped: ' . implode(' ', array_unique($errors)) : ''), $queued > 0);
    }

    /** Start a worker now so nobody waits for cron. Harmless if one is already running. */
    private function kick() {
        $this->load->library('ha_cli_runner');
        return $this->ha_cli_runner->spawn(array('ha_ai_cli', 'work'));
    }

    public function run_worker() {
        $this->need('generate');
        $this->post_guard();
        $ok = $this->kick();
        $this->json(array('ok' => $ok, 'message' => $ok ? 'Worker started.' : 'This host does not allow starting background processes. Add the cron job shown on the overview page.'));
    }

    // --------------------------------------------------------------- jobs

    private function visible_job($id) {
        $this->load->library('ha_ai_studio');
        $job = $this->ha_ai_studio->job($id);
        if (!$job || (!$this->can('approve') && (int) $job['created_by'] !== $this->user_id)) {
            show_404();
        }
        return $job;
    }

    public function job($id = 0) {
        $this->need('view');
        $job = $this->visible_job($id);
        $children = $this->ha_ai_studio->jobs(array('lesson_id' => $job['lesson_id']), 20);
        $children = array_values(array_filter($children, function ($c) use ($job) {
            return (int) $c['parent_job_id'] === (int) $job['id'];
        }));
        $people = array();
        foreach (array('created_by', 'reviewed_by') as $k) {
            if ($job[$k]) {
                $u = $this->db->select('first_name, last_name')->get_where('users', array('id' => $job[$k]))->row_array();
                $people[$k] = $u ? trim($u['first_name'] . ' ' . $u['last_name']) : '#' . $job[$k];
            }
        }
        $this->page('job', 'AI job #' . (int) $id, array(
            'job' => $job, 'input' => json_decode($job['input_json'], true), 'output' => json_decode((string) $job['output_json'], true),
            'lesson' => $job['lesson_id'] ? $this->ha_ai_studio->lesson_context($job['lesson_id']) : null,
            'children' => $children, 'people' => $people,
        ));
    }

    public function job_status($id = 0) {
        $this->need('view');
        $job = $this->visible_job($id);
        $this->json(array('ok' => true, 'status' => $job['status'], 'progress' => (int) $job['progress'],
            'note' => $job['progress_note'], 'error' => $job['error'], 'updated_at' => $job['updated_at']));
    }

    public function job_action($id = 0) {
        $this->post_guard();
        $job = $this->visible_job($id);
        $action = (string) $this->input->post('action');
        $need = array(
            'save' => 'approve', 'approve' => 'approve', 'reject' => 'approve', 'retry' => 'generate', 'delete' => 'generate',
            'publish' => 'publish', 'render_en' => 'approve', 'render_ar' => 'approve', 'avatar_en' => 'approve', 'avatar_ar' => 'approve',
        );
        if (!isset($need[$action])) {
            $this->back('Unknown action.', false);
            return;
        }
        $this->need($need[$action]);
        $s = $this->ha_ai_studio;
        try {
            switch ($action) {
                case 'save':
                    $s->save_edit($id, $this->input->post('output_json'), $this->user_id);
                    $msg = 'Edits saved. Approve again to continue.';
                    break;
                case 'approve':
                    $s->approve($id, $this->user_id, $this->input->post('note'));
                    $msg = 'Approved.';
                    $this->kick();
                    break;
                case 'reject':
                    $s->reject($id, $this->user_id, $this->input->post('note'));
                    $msg = 'Rejected.';
                    break;
                case 'retry':
                    $s->retry($id, $this->user_id);
                    $this->kick();
                    $msg = 'Queued again.';
                    break;
                case 'delete':
                    $s->delete($id);
                    $this->back('Job deleted.', true, site_url('ha_ai/studio'));
                    return;
                case 'publish':
                    $r = $s->publish($id, $this->user_id);
                    $msg = $r['message'];
                    break;
                default:
                    list($kind, $loc) = explode('_', $action);
                    $child = $s->queue_render($id, $kind === 'avatar' ? 'avatar_video' : 'video_render', $loc, $this->user_id);
                    $this->kick();
                    $this->back('Render queued (job #' . $child . ').', true, site_url('ha_ai/job/' . $child));
                    return;
            }
            $this->back($msg, true, site_url('ha_ai/job/' . $id));
        } catch (Exception $e) {
            $this->back($e->getMessage(), false, site_url('ha_ai/job/' . $id));
        }
    }

    // --------------------------------------------------------------- usage

    public function usage() {
        $this->need('configure');
        $days = max(1, min(365, (int) ($this->input->get('days') ?: 30)));
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $by = $this->db->select('provider_slug, model_id, task, COUNT(*) calls, SUM(ok) ok_calls, SUM(input_tokens) tin, SUM(output_tokens) tout, SUM(characters) chars, ROUND(AVG(latency_ms)) avg_ms', false)
            ->where('created_at >=', $since)->group_by(array('provider_slug', 'model_id', 'task'))->order_by('calls', 'DESC')
            ->get('ha_ai_usage')->result_array();
        $recent = $this->db->order_by('id', 'DESC')->limit(50)->get('ha_ai_usage')->result_array();
        $this->page('usage', 'AI usage', array('by' => $by, 'recent' => $recent, 'days' => $days));
    }

    // ----------------------------------------------------------- assistant

    public function assistant() {
        $this->need('generate');
        if ($this->input->method() === 'post') {
            $this->post_guard();
            $prompt = trim((string) $this->input->post('prompt'));
            if ($prompt === '' || mb_strlen($prompt) > 8000) {
                $this->json(array('ok' => false, 'error' => 'Write a request of up to 8,000 characters.'));
                return;
            }
            try {
                $res = $this->ha_ai_gateway->chat('assistant', array(
                    array('role' => 'system', 'content' => 'You help the staff of Hospitality Academy, a hotel training academy in Saudi Arabia, write course material, emails and announcements. Answer in the language of the request. Never invent statistics, laws or quotes.'),
                    array('role' => 'user', 'content' => $prompt),
                ));
                $this->json(array('ok' => true, 'text' => $res['text'], 'model' => $res['provider'] . ' / ' . $res['model'],
                    'tokens' => $res['input_tokens'] + $res['output_tokens']));
            } catch (Exception $e) {
                $this->json(array('ok' => false, 'error' => $e->getMessage()));
            }
            return;
        }
        $this->page('assistant', 'AI writing assistant', array('route' => $this->ha_ai_gateway->route('assistant')));
    }
}
