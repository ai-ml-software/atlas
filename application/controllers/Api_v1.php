<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Versioned JSON API, authenticated by personal API key (plan section 46).
 *
 *   Authorization: Bearer ha_<prefix>_<secret>     (or X-API-Key: …)
 *
 *   GET  /api/v1/me                      profile:read
 *   GET  /api/v1/courses?locale=ar       courses:read
 *   GET  /api/v1/courses/{code}          courses:read
 *   GET  /api/v1/enrollments             enrollments:read
 *   GET  /api/v1/ai/jobs                 ai:jobs
 *   GET  /api/v1/ai/jobs/{id}            ai:jobs
 *   POST /api/v1/ai/jobs                 ai:generate  {"type":"lesson_script","lesson_id":12,"minutes":5}
 *                                                     {"type":"course_draft","topic":"…","lessons":8}
 *
 * Two independent checks guard every call: the key must carry the scope, and
 * the key's owner must hold the matching permission right now. A scope never
 * grants more than the person has.
 *
 * Errors are {"error": {"status": 401, "message": "…"}}. The legacy /api/*
 * (mobile app, JWT) is untouched.
 */
class Api_v1 extends CI_Controller {

    const RATE_PER_MINUTE = 120;

    private $key;
    private $user;

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->helper('ha_security');
        $this->load->library('ha_api_keys');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    public function dispatch() {
        $segments = array_slice($this->uri->segment_array(), 2);   // drop "api", "v1"
        $method = strtoupper($this->input->method());
        $ip = ha_client_ip();

        $auth = $this->ha_api_keys->authenticate(Ha_api_keys::from_request(), $ip);
        if (!$auth['ok']) {
            return $this->error($auth['status'], $auth['error']);
        }
        $this->key = $auth['key'];
        $this->user = $auth['user'];

        $bucket = 'api_key:' . $this->key['id'];
        $recent = $this->db->where('bucket', $bucket)->where('created_at >=', date('Y-m-d H:i:s', time() - 60))
            ->count_all_results('ha_auth_attempt');
        if ($recent >= self::RATE_PER_MINUTE) {
            header('Retry-After: 60');
            return $this->error(429, 'Rate limit of ' . self::RATE_PER_MINUTE . ' requests per minute exceeded.');
        }
        $this->ha_api_keys->attempt($bucket, true, $ip);
        if (mt_rand(1, 200) === 1) {
            $this->db->where('created_at <', date('Y-m-d H:i:s', time() - 86400))->delete('ha_auth_attempt');
        }
        header('X-RateLimit-Limit: ' . self::RATE_PER_MINUTE);
        header('X-RateLimit-Remaining: ' . max(0, self::RATE_PER_MINUTE - $recent - 1));

        $this->load->library('ha_auth');
        $this->ha_auth->from_api_key($auth);

        $route = implode('/', array_map(function ($s) {
            return preg_match('/^\d+$/', $s) ? '{id}' : (preg_match('/^[a-z0-9\-]+$/', $s) && !in_array($s, array('me', 'courses', 'enrollments', 'ai', 'jobs'), true) ? '{code}' : $s);
        }, $segments));

        try {
            switch ($method . ' ' . $route) {
                case 'GET me':                 return $this->me();
                case 'GET courses':            return $this->courses();
                case 'GET courses/{code}':     return $this->course($segments[1]);
                case 'GET enrollments':        return $this->enrollments();
                case 'GET ai/jobs':            return $this->jobs();
                case 'GET ai/jobs/{id}':       return $this->job_show((int) $segments[2]);
                case 'POST ai/jobs':           return $this->job_create();
            }
        } catch (InvalidArgumentException $e) {
            return $this->error(422, $e->getMessage());
        } catch (Exception $e) {
            log_message('error', 'api/v1: ' . $e->getMessage());
            return $this->error(500, 'The request could not be completed.');
        }
        return $this->error(404, 'No such endpoint: ' . $method . ' /api/v1/' . implode('/', $segments));
    }

    // ------------------------------------------------------------ helpers

    private function need($scope, $permission = null) {
        if (!Ha_api_keys::has_scope($this->key, $scope)) {
            $this->error(403, 'This API key does not have the "' . $scope . '" scope.');
            return false;
        }
        if ($permission && (int) $this->user['role_id'] !== 1 && !$this->ha_auth->has($permission)) {
            $this->error(403, 'The key owner does not have the "' . $permission . '" permission.');
            return false;
        }
        return true;
    }

    private function json($data, $status = 200) {
        $this->output->set_status_header($status)
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function error($status, $message) {
        $this->json(array('error' => array('status' => (int) $status, 'message' => $message)), $status);
    }

    private function body() {
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : (array) $this->input->post();
    }

    private function locale() {
        return $this->input->get('locale') === 'ar' ? 'ar' : 'en';
    }

    // ---------------------------------------------------------- endpoints

    private function me() {
        if (!$this->need('profile:read')) {
            return;
        }
        $u = $this->user;
        $this->json(array('data' => array(
            'id' => (int) $u['id'], 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'email' => $u['email'],
            'roles' => $this->ha_auth->role_codes(), 'is_admin' => (int) $u['role_id'] === 1,
            'key' => array('name' => $this->key['name'], 'scopes' => explode(' ', $this->key['scopes']), 'expires_at' => $this->key['expires_at']),
        )));
    }

    private function courses() {
        if (!$this->need('courses:read')) {
            return;
        }
        $loc = $this->locale();
        $page = max(1, (int) $this->input->get('page'));
        $per = max(1, min(100, (int) ($this->input->get('per_page') ?: 25)));
        $total = $this->db->where('status', 'published')->count_all_results('ha_course');
        $rows = $this->db->select('c.code, c.level, c.duration_minutes, c.is_free, c.price, c.currency, c.certificate_eligible, c.published_at')
            ->select('c.slug_' . $loc . ' AS slug, t.title, t.short_description, cat.code AS category', false)
            ->from('ha_course c')
            ->join('ha_course_translation t', "t.course_id = c.id AND t.locale = '" . $loc . "'", 'left')
            ->join('ha_category cat', 'cat.id = c.category_id', 'left')
            ->where('c.status', 'published')->order_by('c.code')->limit($per, ($page - 1) * $per)->get()->result_array();
        foreach ($rows as &$r) {
            $r['url'] = site_url($loc . '/courses/' . $r['slug']);
        }
        $this->json(array('data' => $rows, 'meta' => array('page' => $page, 'per_page' => $per, 'total' => $total, 'locale' => $loc)));
    }

    private function course($code) {
        if (!$this->need('courses:read')) {
            return;
        }
        $loc = $this->locale();
        $c = $this->db->select('c.id, c.code, c.level, c.duration_minutes, c.slug_' . $loc . ' AS slug, t.title, t.short_description, t.description', false)
            ->from('ha_course c')->join('ha_course_translation t', "t.course_id = c.id AND t.locale = '" . $loc . "'", 'left')
            ->where('c.code', $code)->where('c.status', 'published')->get()->row_array();
        if (!$c) {
            return $this->error(404, 'Course not found.');
        }
        $sections = $this->db->select('id, title_' . $loc . ' AS title', false)->where('course_id', $c['id'])->order_by('sort_order')->get('ha_course_section')->result_array();
        foreach ($sections as &$s) {
            $s['lessons'] = $this->db->select('l.id, l.lesson_type, l.duration_seconds, lt.title, (v.id IS NOT NULL) AS has_video', false)
                ->from('ha_lesson l')->join('ha_lesson_translation lt', "lt.lesson_id = l.id AND lt.locale = '" . $loc . "'", 'left')
                ->join('ha_lesson_video_source v', "v.lesson_id = l.id AND v.status = 'live'", 'left')
                ->where('l.section_id', $s['id'])->where('l.status', 'published')->order_by('l.sort_order')->get()->result_array();
            foreach ($s['lessons'] as &$l) {
                $l['id'] = (int) $l['id'];
                $l['has_video'] = (bool) $l['has_video'];
            }
            unset($s['id']);
        }
        unset($c['id']);
        $c['sections'] = $sections;
        $this->json(array('data' => $c));
    }

    private function enrollments() {
        if (!$this->need('enrollments:read')) {
            return;
        }
        $rows = $this->db->select('e.course_id, e.date_added, c.title, c.meta_keywords')
            ->from('enrol e')->join('course c', 'c.id = e.course_id')
            ->where('e.user_id', (int) $this->user['id'])->order_by('e.id', 'DESC')->get()->result_array();
        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'course_code' => strpos((string) $r['meta_keywords'], 'ha:') === 0 ? substr($r['meta_keywords'], 3) : null,
                'title' => $r['title'],
                'enrolled_at' => $r['date_added'] ? date('c', (int) $r['date_added']) : null,
                'progress_percent' => function_exists('course_progress') ? (int) course_progress($r['course_id'], $this->user['id']) : null,
            );
        }
        $this->json(array('data' => $out));
    }

    private function jobs() {
        if (!$this->need('ai:jobs', 'ai.view')) {
            return;
        }
        $this->load->library('ha_ai_studio');
        $filter = array('status' => $this->input->get('status'));
        if ((int) $this->user['role_id'] !== 1 && !$this->ha_auth->has('ai.approve')) {
            $filter['created_by'] = (int) $this->user['id'];
        }
        $rows = $this->ha_ai_studio->jobs($filter, 100);
        $this->json(array('data' => array_map(array($this, 'job_public'), $rows)));
    }

    private function job_show($id) {
        if (!$this->need('ai:jobs', 'ai.view')) {
            return;
        }
        $this->load->library('ha_ai_studio');
        $job = $this->ha_ai_studio->job($id);
        $mine = $job && (int) $job['created_by'] === (int) $this->user['id'];
        if (!$job || (!$mine && (int) $this->user['role_id'] !== 1 && !$this->ha_auth->has('ai.approve'))) {
            return $this->error(404, 'Job not found.');
        }
        $data = $this->job_public($job);
        $data['output'] = json_decode((string) $job['output_json'], true);
        $this->json(array('data' => $data));
    }

    private function job_create() {
        if (!$this->need('ai:generate', 'ai.generate')) {
            return;
        }
        $this->load->library('ha_ai_studio');
        $b = $this->body();
        $type = isset($b['type']) ? $b['type'] : '';
        if ($type === 'lesson_script') {
            $id = $this->ha_ai_studio->queue_script((int) (isset($b['lesson_id']) ? $b['lesson_id'] : 0), $b, (int) $this->user['id']);
        } elseif ($type === 'course_draft') {
            $id = $this->ha_ai_studio->queue_course($b, (int) $this->user['id']);
        } else {
            throw new InvalidArgumentException('type must be "lesson_script" or "course_draft". Rendering is started from AI Studio after a person approves the script.');
        }
        $this->load->library('ha_cli_runner');
        $this->ha_cli_runner->spawn(array('ha_ai_cli', 'work'));
        $this->json(array('data' => $this->job_public($this->ha_ai_studio->job($id))), 202);
    }

    private function job_public(array $j) {
        return array(
            'id' => (int) $j['id'], 'type' => $j['type'], 'status' => $j['status'], 'title' => $j['title'],
            'progress' => (int) $j['progress'], 'progress_note' => $j['progress_note'], 'error' => $j['error'],
            'lesson_id' => $j['lesson_id'] ? (int) $j['lesson_id'] : null, 'course_id' => $j['course_id'] ? (int) $j['course_id'] : null,
            'created_at' => $j['created_at'], 'updated_at' => $j['updated_at'],
            'review_url' => site_url('ha_ai/job/' . $j['id']),
        );
    }
}
