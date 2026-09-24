<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * AI Studio jobs: from a prompt to reviewed, published course content.
 *
 *   course_draft   topic -> full bilingual course outline (sections, lessons,
 *                  outcomes, FAQ). Publishing creates the ha_course tree.
 *   lesson_script  lesson -> bilingual video script: slides with narration,
 *                  a written summary and a self-check quiz. Publishing writes
 *                  the lesson text and transcript.
 *   video_render   approved script -> narrated slide MP4 + captions, drawn
 *                  and encoded on this server.
 *   avatar_video   approved script -> presenter video from HeyGen, D-ID or
 *                  Synthesia (render happens at the vendor; this polls).
 *
 * State machine, enforced here and nowhere else:
 *
 *   queued -> running -> draft -> approved -> published
 *                     \-> failed (retry -> queued)      \-> rejected
 *
 * The rule the whole module exists to keep: AI output is a draft. Nothing a
 * model wrote reaches a learner until a named person approved it, and the
 * approval is recorded on the job and in the audit log.
 */
class Ha_ai_studio {

    private $CI;
    /** @var Ha_ai_gateway */
    private $gw;
    private $limits;

    public static $types = array('course_draft', 'lesson_script', 'video_render', 'avatar_video');

    /**
     * The LMS mirror runs as a separate CLI process against the configured
     * database. The test runner switches the connection in memory only, so
     * it turns this off rather than let a test publish into working data.
     */
    public $sync_lms_enabled = true;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->library('ha_ai_gateway');
        $this->gw = $this->CI->ha_ai_gateway;
        $this->limits = (array) $this->CI->config->item('ha_ai_limits', 'ha_ai');
    }

    // ================================================================ create

    public function queue_course($input, $user_id) {
        $topic = trim((string) (isset($input['topic']) ? $input['topic'] : ''));
        if (mb_strlen($topic) < 8) {
            throw new InvalidArgumentException('Describe the course topic in at least a few words.');
        }
        $lessons = max(3, min((int) $this->limits['max_lessons_per_course'], (int) (isset($input['lessons']) ? $input['lessons'] : 8)));
        $payload = array(
            'topic' => mb_substr($topic, 0, 2000),
            'audience' => mb_substr(trim((string) (isset($input['audience']) ? $input['audience'] : '')), 0, 500),
            'category_code' => isset($input['category_code']) ? (string) $input['category_code'] : '',
            'level' => in_array(isset($input['level']) ? $input['level'] : '', array('foundation', 'intermediate', 'advanced', 'leadership'), true) ? $input['level'] : 'foundation',
            'lessons' => $lessons,
            'notes' => mb_substr(trim((string) (isset($input['notes']) ? $input['notes'] : '')), 0, 2000),
            'auto_scripts' => !empty($input['auto_scripts']),
        );
        return $this->insert_job('course_draft', 'Course: ' . mb_substr($topic, 0, 180), $payload, $user_id);
    }

    public function queue_script($lesson_id, $input, $user_id) {
        $lesson = $this->lesson_context((int) $lesson_id);
        if (!$lesson) {
            throw new InvalidArgumentException('Lesson not found.');
        }
        $open = $this->CI->db->where(array('type' => 'lesson_script', 'lesson_id' => (int) $lesson_id))
            ->where_in('status', array('queued', 'running'))->count_all_results('ha_ai_job');
        if ($open) {
            throw new InvalidArgumentException('A script for "' . $lesson['title_en'] . '" is already being generated.');
        }
        $payload = array(
            'minutes' => max(2, min(20, (int) (isset($input['minutes']) ? $input['minutes'] : 5))),
            'notes' => mb_substr(trim((string) (isset($input['notes']) ? $input['notes'] : '')), 0, 2000),
            'then_render' => !empty($input['then_render']),
        );
        return $this->insert_job('lesson_script', 'Script: ' . $lesson['title_en'], $payload, $user_id,
            (int) $lesson['course_id'], (int) $lesson_id);
    }

    public function queue_render($script_job_id, $type, $locale, $user_id) {
        $script = $this->job($script_job_id);
        if (!$script || $script['type'] !== 'lesson_script') {
            throw new InvalidArgumentException('Pick a lesson script to render.');
        }
        if (!in_array($script['status'], array('approved', 'published'), true)) {
            throw new InvalidArgumentException('Approve the script before rendering video: rendering costs money and a rejected script wastes it.');
        }
        if (!in_array($type, array('video_render', 'avatar_video'), true) || !in_array($locale, array('en', 'ar'), true)) {
            throw new InvalidArgumentException('Unknown render request.');
        }
        return $this->insert_job($type, ($type === 'avatar_video' ? 'Presenter video' : 'Slide video') . ' (' . strtoupper($locale) . '): '
            . preg_replace('/^Script: /', '', $script['title']),
            array('script_job_id' => (int) $script_job_id, 'locale' => $locale), $user_id,
            $script['course_id'], $script['lesson_id'], (int) $script_job_id, $locale);
    }

    private function insert_job($type, $title, array $payload, $user_id, $course_id = null, $lesson_id = null, $parent = null, $locales = 'en,ar') {
        $today = $this->CI->db->where('created_by', (int) $user_id)->where('created_at >=', date('Y-m-d 00:00:00'))
            ->count_all_results('ha_ai_job');
        if ($user_id && $today >= (int) $this->limits['max_jobs_per_user_per_day']) {
            throw new InvalidArgumentException('Daily limit of ' . $this->limits['max_jobs_per_user_per_day'] . ' AI jobs reached.');
        }
        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_ai_job', array(
            'type' => $type, 'status' => 'queued', 'title' => mb_substr($title, 0, 255),
            'course_id' => $course_id, 'lesson_id' => $lesson_id, 'parent_job_id' => $parent, 'locales' => $locales,
            'input_json' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'progress' => 0, 'progress_note' => 'Waiting for the worker',
            'created_by' => $user_id ? (int) $user_id : null, 'created_at' => $now, 'updated_at' => $now,
        ));
        return (int) $this->CI->db->insert_id();
    }

    // ================================================================= read

    public function job($id) {
        return $this->CI->db->get_where('ha_ai_job', array('id' => (int) $id))->row_array() ?: null;
    }

    public function jobs(array $filter = array(), $limit = 50, $offset = 0) {
        $db = $this->CI->db->select('j.*, u.first_name, u.last_name')->from('ha_ai_job j')
            ->join('users u', 'u.id = j.created_by', 'left');
        if (!empty($filter['status'])) {
            $db->where('j.status', $filter['status']);
        }
        if (!empty($filter['type'])) {
            $db->where('j.type', $filter['type']);
        }
        if (!empty($filter['created_by'])) {
            $db->where('j.created_by', (int) $filter['created_by']);
        }
        if (!empty($filter['lesson_id'])) {
            $db->where('j.lesson_id', (int) $filter['lesson_id']);
        }
        return $db->order_by('j.id', 'DESC')->limit($limit, $offset)->get()->result_array();
    }

    public function counts() {
        $out = array_fill_keys(array('queued', 'running', 'draft', 'approved', 'rejected', 'published', 'failed'), 0);
        foreach ($this->CI->db->select('status, COUNT(*) n', false)->group_by('status')->get('ha_ai_job')->result_array() as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    public function lesson_context($lesson_id) {
        return $this->CI->db
            ->select('l.id, l.course_id, l.section_id, l.lesson_type, l.duration_seconds, c.code AS course_code, c.level')
            ->select('le.title AS title_en, le.objective AS objective_en, le.body AS body_en', false)
            ->select('la.title AS title_ar, la.objective AS objective_ar', false)
            ->select('ce.title AS course_en, ca.title AS course_ar, s.title_en AS section_en', false)
            ->from('ha_lesson l')
            ->join('ha_course c', 'c.id = l.course_id')
            ->join('ha_lesson_translation le', "le.lesson_id = l.id AND le.locale = 'en'", 'left')
            ->join('ha_lesson_translation la', "la.lesson_id = l.id AND la.locale = 'ar'", 'left')
            ->join('ha_course_translation ce', "ce.course_id = c.id AND ce.locale = 'en'", 'left')
            ->join('ha_course_translation ca', "ca.course_id = c.id AND ca.locale = 'ar'", 'left')
            ->join('ha_course_section s', 's.id = l.section_id', 'left')
            ->where('l.id', (int) $lesson_id)->get()->row_array() ?: null;
    }

    /** Video lessons that still carry no video: the gap this studio exists to close. */
    public function lessons_without_video($course_id = null, $limit = 500) {
        $db = $this->CI->db
            ->select('l.id, l.course_id, c.code, le.title, ce.title AS course_title, l.sort_order', false)
            ->select('(SELECT MAX(j.id) FROM ha_ai_job j WHERE j.lesson_id = l.id AND j.type = \'lesson_script\') AS script_job_id', false)
            ->from('ha_lesson l')
            ->join('ha_course c', 'c.id = l.course_id')
            ->join('ha_lesson_translation le', "le.lesson_id = l.id AND le.locale = 'en'", 'left')
            ->join('ha_course_translation ce', "ce.course_id = c.id AND ce.locale = 'en'", 'left')
            ->join('ha_lesson_video_source v', 'v.lesson_id = l.id', 'left')
            ->where('l.lesson_type', 'video')->where('v.id IS NULL', null, false);
        if ($course_id) {
            $db->where('l.course_id', (int) $course_id);
        }
        return $db->order_by('c.code', 'ASC')->order_by('l.sort_order', 'ASC')->limit($limit)->get()->result_array();
    }

    // ================================================================ worker

    /**
     * Claim and process the next job. Returns the job id, or null when idle.
     * Safe to run from several workers: the claim is an atomic UPDATE.
     */
    public function work_one() {
        $lock_minutes = (int) $this->limits['job_lock_minutes'];
        $stale = date('Y-m-d H:i:s', time() - $lock_minutes * 60);
        // A job locked longer than the limit belonged to a worker that died.
        $this->CI->db->where('status', 'running')->where('locked_at <', $stale)
            ->update('ha_ai_job', array('status' => 'queued', 'locked_at' => null, 'progress_note' => 'Recovered after a worker stopped'));

        $candidate = $this->CI->db->select('id')->where('status', 'queued')
            ->group_start()->where('locked_at IS NULL', null, false)->or_where('locked_at <', date('Y-m-d H:i:s', time() - 20))->group_end()
            ->order_by('id', 'ASC')->limit(1)->get('ha_ai_job')->row_array();
        if (!$candidate) {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $this->CI->db->where('id', $candidate['id'])->where('status', 'queued')->update('ha_ai_job', array(
            'status' => 'running', 'locked_at' => $now, 'started_at' => $now, 'updated_at' => $now,
        ));
        if ($this->CI->db->affected_rows() !== 1) {
            return $this->work_one();   // another worker won the race
        }
        $this->CI->db->set('attempts', 'attempts + 1', false)->where('id', $candidate['id'])->update('ha_ai_job');
        $job = $this->job($candidate['id']);
        $this->run($job);
        return (int) $job['id'];
    }

    public function run(array $job) {
        $this->gw->context = array('user_id' => $job['created_by'], 'job_id' => (int) $job['id'], 'api_key_id' => null);
        try {
            switch ($job['type']) {
                case 'course_draft':  $this->run_course($job); break;
                case 'lesson_script': $this->run_script($job); break;
                case 'video_render':  $this->run_render($job); break;
                case 'avatar_video':  $this->run_avatar($job); break;
            }
        } catch (Exception $e) {
            $job = $this->job($job['id']);
            $retryable = $this->retryable($e) && (int) $job['attempts'] < (int) $this->limits['job_max_attempts'];
            $this->update($job['id'], array(
                'status' => $retryable ? 'queued' : 'failed',
                'error' => mb_substr($e->getMessage(), 0, 4000),
                'progress_note' => $retryable ? 'Retrying after: ' . mb_substr($e->getMessage(), 0, 200) : 'Failed',
                'locked_at' => $retryable ? date('Y-m-d H:i:s') : null,   // brief back-off before the retry
                'finished_at' => $retryable ? null : date('Y-m-d H:i:s'),
            ));
        }
    }

    /** Network and rate-limit failures are worth retrying; configuration errors are not. */
    private function retryable(Exception $e) {
        return (bool) preg_match('/HTTP (429|5\d\d)|Could not reach|timed out|valid JSON/i', $e->getMessage());
    }

    private function update($id, array $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->CI->db->where('id', (int) $id)->update('ha_ai_job', $data);
    }

    private function progress($id, $pct, $note) {
        $this->update($id, array('progress' => max(0, min(100, (int) $pct)), 'progress_note' => mb_substr($note, 0, 255), 'locked_at' => date('Y-m-d H:i:s')));
    }

    private function finish($id, array $output, $artifact = null) {
        $this->update($id, array(
            'status' => 'draft', 'progress' => 100, 'progress_note' => 'Ready for review',
            'output_json' => json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'artifact_path' => $artifact, 'error' => null, 'locked_at' => null, 'finished_at' => date('Y-m-d H:i:s'),
        ));
    }

    // ------------------------------------------------------------ prompts

    private function house_rules() {
        return <<<TXT
You write training content for Hospitality Academy, which trains hotel staff in Saudi Arabia.
Rules you must follow:
- Teach real, practical procedure a hotel team member can apply on shift. Be specific: steps, phrases to say, checks to make.
- Never invent statistics, studies, laws, regulations, certifications, brand standards or quotes. If a rule depends on local law or the hotel's own policy, say "follow your property's policy" instead of stating a rule.
- Respect Saudi cultural context: modest, respectful service; no alcohol service content; prayer times and Ramadan awareness where relevant.
- English: plain, friendly, second person ("you"), short sentences, suitable for staff whose first language may not be English.
- Arabic: write it natively in clear Modern Standard Arabic as a native Saudi trainer would. Do not translate word for word, do not transliterate English, keep industry terms learners actually use (e.g. "الاستقبال", "التدبير الفندقي").
- Output only what the JSON schema asks for.
TXT;
    }

    private function run_course(array $job) {
        $in = json_decode($job['input_json'], true);
        $categories = array();
        foreach ($this->CI->db->select('c.code, t.name')->from('ha_category c')
                     ->join('ha_category_translation t', "t.category_id = c.id AND t.locale = 'en'")
                     ->where('c.status', 'active')->where('c.parent_id IS NULL', null, false)->get()->result_array() as $c) {
            $categories[] = $c['code'] . ' (' . $c['name'] . ')';
        }
        $existing = array_column($this->CI->db->select('code')->get('ha_course')->result_array(), 'code');

        $this->progress($job['id'], 10, 'Drafting the course outline');
        $user = "Design a complete course.\n"
            . 'Topic: ' . $in['topic'] . "\n"
            . ($in['audience'] ? 'Audience: ' . $in['audience'] . "\n" : '')
            . 'Level: ' . $in['level'] . "\n"
            . 'Number of lessons: about ' . $in['lessons'] . ", grouped into 2 to 5 sections.\n"
            . 'Category: ' . ($in['category_code'] ?: 'choose the best one') . '. Allowed category codes: ' . implode(', ', $categories) . "\n"
            . ($in['notes'] ? 'Author notes: ' . $in['notes'] . "\n" : '')
            . 'Existing course codes you must not reuse: ' . implode(', ', array_slice($existing, 0, 200)) . "\n\n"
            . "JSON schema:\n"
            . '{"code":"kebab-case, department prefix like fo-, hk-, fb-, kit-, eng-, mgt-","category_code":"one allowed code","level":"foundation|intermediate|advanced|leadership","duration_minutes":integer,'
            . '"en":{"title":"","short_description":"<=200 chars","description":"2-3 paragraphs","requirements":"","outcomes":["4-6 measurable outcomes"],"faqs":[{"q":"","a":""}]},'
            . '"ar":{same keys as en, in Arabic},'
            . '"sections":[{"en":"section title","ar":"عنوان القسم","lessons":[{"type":"video|text","minutes":integer,"en":{"title":"","objective":"one sentence"},"ar":{"title":"","objective":""}}]}]}';

        $res = $this->gw->chat_json('course_outline', $this->house_rules(), $user);
        $outline = $res['data'];
        $problems = self::validate_outline($outline);
        if ($problems) {
            throw new RuntimeException('The outline did not pass validation: ' . implode('; ', $problems) . '. Retry, or choose a stronger model for course_outline.');
        }
        $outline['code'] = $this->unique_code(self::slug($outline['code']));
        $this->finish($job['id'], array('outline' => $outline, 'model' => $res['provider'] . ' / ' . $res['model'],
            'tokens' => array('in' => $res['input_tokens'], 'out' => $res['output_tokens'])));
    }

    private function run_script(array $job) {
        $in = json_decode($job['input_json'], true);
        $l = $this->lesson_context($job['lesson_id']);
        if (!$l) {
            throw new RuntimeException('The lesson no longer exists.');
        }
        $slides = max(4, min(14, (int) round($in['minutes'] * 1.4)));
        $words = (int) round($in['minutes'] * 130);

        $siblings = array_column($this->CI->db->select('lt.title')->from('ha_lesson l')
            ->join('ha_lesson_translation lt', "lt.lesson_id = l.id AND lt.locale = 'en'")
            ->where('l.course_id', $l['course_id'])->order_by('l.sort_order')->get()->result_array(), 'title');

        $this->progress($job['id'], 10, 'Writing the lesson script');
        $user = "Write a video lesson.\n"
            . 'Course: ' . $l['course_en'] . ' (level ' . $l['level'] . ")\n"
            . 'Section: ' . $l['section_en'] . "\n"
            . 'Lesson: ' . $l['title_en'] . ($l['title_ar'] ? ' / ' . $l['title_ar'] : '') . "\n"
            . ($l['objective_en'] ? 'Objective: ' . $l['objective_en'] . "\n" : '')
            . 'Other lessons in this course (do not repeat their content): ' . implode(' | ', array_diff($siblings, array($l['title_en']))) . "\n"
            . ($l['body_en'] ? "Existing written lesson text to build on:\n" . mb_substr(strip_tags($l['body_en']), 0, 3000) . "\n" : '')
            . ($in['notes'] ? 'Author notes: ' . $in['notes'] . "\n" : '')
            . "Length: about {$in['minutes']} minutes of narration (~{$words} words per language), {$slides} slides.\n\n"
            . "JSON schema, both languages required:\n"
            . '{"en":{"title":"","objective":"","slides":[{"title":"<=60 chars","bullets":["2-4 bullets, <=80 chars each"],"narration":"what the narrator says on this slide","visual":"b-roll idea for this slide"}],'
            . '"summary_html":"the written lesson for learners: <h3>, <p>, <ul>, <ol> only","quiz":[{"question":"","options":["4 options"],"answer":0,"explanation":""}]},'
            . '"ar":{same structure, written natively in Arabic}}' . "\nWrite 3 to 5 quiz questions per language.";

        $res = $this->gw->chat_json('lesson_script', $this->house_rules(), $user);
        $script = $res['data'];
        $problems = self::validate_script($script);
        if ($problems && isset($script['en']) && !isset($script['ar']) && $this->gw->route('translate_ar')) {
            $this->progress($job['id'], 60, 'Writing the Arabic version');
            $ar = $this->gw->chat_json('translate_ar', $this->house_rules(),
                "Adapt this English lesson into native Modern Standard Arabic. Keep the same JSON structure and the same number of slides and quiz questions.\n"
                . json_encode($script['en'], JSON_UNESCAPED_UNICODE));
            $script['ar'] = isset($ar['data']['ar']) ? $ar['data']['ar'] : $ar['data'];
            $problems = self::validate_script($script);
        }
        if ($problems) {
            throw new RuntimeException('The script did not pass validation: ' . implode('; ', $problems) . '.');
        }
        $this->finish($job['id'], array('script' => $script, 'model' => $res['provider'] . ' / ' . $res['model'],
            'tokens' => array('in' => $res['input_tokens'], 'out' => $res['output_tokens'])));
    }

    private function run_render(array $job) {
        $in = json_decode($job['input_json'], true);
        $parent = $this->job($in['script_job_id']);
        $out = json_decode((string) $parent['output_json'], true);
        $script = isset($out['script'][$in['locale']]) ? $out['script'][$in['locale']] : null;
        if (!$script) {
            throw new RuntimeException('The approved script has no ' . strtoupper($in['locale']) . ' version.');
        }
        $l = $this->lesson_context($job['lesson_id']);
        $this->CI->load->library('ha_video_renderer');
        $id = $job['id'];
        $self = $this;
        $this->CI->ha_video_renderer->on_progress(function ($pct, $note) use ($self, $id) {
            $self->progress_public($id, $pct, $note);
        });
        $result = $this->CI->ha_video_renderer->render($script, $in['locale'], 'lesson-' . $job['lesson_id'] . '-' . $in['locale'] . '-j' . $id, array(
            'course' => $in['locale'] === 'ar' ? $l['course_ar'] : $l['course_en'],
            'lesson' => isset($script['title']) ? $script['title'] : '',
        ));
        $this->finish($id, array('render' => $result, 'locale' => $in['locale']), $result['video']);
    }

    /** Vendor renders take minutes: start once, then the job returns to the queue and is polled. */
    private function run_avatar(array $job) {
        $in = json_decode($job['input_json'], true);
        $state = json_decode((string) $job['output_json'], true) ?: array();
        $this->CI->load->library('ha_ai_media');

        if (empty($state['ref'])) {
            $parent = $this->job($in['script_job_id']);
            $out = json_decode((string) $parent['output_json'], true);
            $script = $out['script'][$in['locale']];
            $text = implode("\n\n", array_filter(array_map(function ($s) {
                return isset($s['narration']) ? trim($s['narration']) : '';
            }, $script['slides'])));
            $start = $this->CI->ha_ai_media->avatar_start($text, $in['locale'], isset($script['title']) ? $script['title'] : $job['title']);
            $this->update($job['id'], array('status' => 'queued', 'output_json' => json_encode($start), 'progress' => 30,
                'progress_note' => 'Rendering at ' . $start['provider'], 'locked_at' => date('Y-m-d H:i:s')));
            return;
        }

        $poll = $this->CI->ha_ai_media->avatar_poll($state['provider'], $state['ref']);
        if (!$poll['done']) {
            // Re-queue with a lock stamp: work_one() skips it for 20 seconds.
            $this->CI->db->set('attempts', 'GREATEST(attempts - 1, 0)', false)->where('id', $job['id'])->update('ha_ai_job');
            $this->update($job['id'], array('status' => 'queued', 'progress' => min(90, (int) $job['progress'] + 5),
                'progress_note' => 'Still rendering at ' . $state['provider'], 'locked_at' => date('Y-m-d H:i:s')));
            return;
        }
        if ($poll['error']) {
            throw new RuntimeException($poll['error']);
        }
        $path = $this->CI->ha_ai_media->store($poll['bytes'] ?: $poll['url'], 'uploads/ai_videos', 'lesson-' . $job['lesson_id'] . '-' . $in['locale'] . '-avatar-j' . $job['id']);
        $this->finish($job['id'], array('render' => array('video' => $path, 'poster' => null, 'captions' => null, 'narrated' => true),
            'locale' => $in['locale'], 'provider' => $state['provider'], 'ref' => $state['ref']), $path);
    }

    /** For the renderer's progress callback (closures cannot reach private methods in PHP 5.x style code). */
    public function progress_public($id, $pct, $note) {
        $this->progress($id, $pct, $note);
    }

    // ------------------------------------------------------------ validation

    public static function validate_outline($o) {
        $p = array();
        if (!is_array($o)) {
            return array('not an object');
        }
        foreach (array('code', 'level', 'en', 'ar', 'sections') as $k) {
            if (empty($o[$k])) {
                $p[] = 'missing ' . $k;
            }
        }
        if ($p) {
            return $p;
        }
        if (!in_array($o['level'], array('foundation', 'intermediate', 'advanced', 'leadership'), true)) {
            $p[] = 'invalid level';
        }
        foreach (array('en', 'ar') as $loc) {
            if (empty($o[$loc]['title'])) {
                $p[] = $loc . ' title missing';
            }
        }
        if (!empty($o['ar']['title']) && !preg_match('/\p{Arabic}/u', $o['ar']['title'])) {
            $p[] = 'Arabic title is not in Arabic';
        }
        $n = 0;
        foreach ((array) $o['sections'] as $i => $s) {
            if (empty($s['en']) || empty($s['ar']) || empty($s['lessons']) || !is_array($s['lessons'])) {
                $p[] = 'section ' . ($i + 1) . ' incomplete';
                continue;
            }
            foreach ($s['lessons'] as $j => $l) {
                $n++;
                if (empty($l['en']['title']) || empty($l['ar']['title'])) {
                    $p[] = 'lesson ' . ($i + 1) . '.' . ($j + 1) . ' needs both titles';
                }
            }
        }
        if ($n < 2) {
            $p[] = 'fewer than 2 lessons';
        }
        return $p;
    }

    public static function validate_script($s) {
        $p = array();
        if (!is_array($s)) {
            return array('not an object');
        }
        foreach (array('en', 'ar') as $loc) {
            if (empty($s[$loc]) || !is_array($s[$loc])) {
                $p[] = 'missing ' . $loc . ' version';
                continue;
            }
            $v = $s[$loc];
            if (empty($v['slides']) || !is_array($v['slides']) || count($v['slides']) < 2) {
                $p[] = $loc . ': needs at least 2 slides';
            } else {
                foreach ($v['slides'] as $i => $slide) {
                    if (empty($slide['title']) || empty($slide['narration'])) {
                        $p[] = $loc . ' slide ' . ($i + 1) . ' needs a title and narration';
                    }
                }
            }
            if (empty($v['summary_html'])) {
                $p[] = $loc . ': summary missing';
            }
            foreach ((array) (isset($v['quiz']) ? $v['quiz'] : array()) as $i => $q) {
                if (empty($q['question']) || !is_array(isset($q['options']) ? $q['options'] : null) || count($q['options']) < 2
                    || !isset($q['answer']) || !isset($q['options'][(int) $q['answer']])) {
                    $p[] = $loc . ' quiz ' . ($i + 1) . ' is malformed';
                }
            }
            if ($loc === 'ar' && !empty($v['slides'][0]['narration']) && !preg_match('/\p{Arabic}/u', $v['slides'][0]['narration'])) {
                $p[] = 'Arabic narration is not in Arabic';
            }
        }
        return $p;
    }

    // ================================================================ review

    /** Save reviewer edits to a draft. The edited payload is re-validated. */
    public function save_edit($id, $json, $user_id) {
        $job = $this->job($id);
        if (!$job || !in_array($job['status'], array('draft', 'approved'), true)) {
            throw new InvalidArgumentException('Only drafts can be edited.');
        }
        $data = json_decode((string) $json, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('The edited content is not valid JSON: ' . json_last_error_msg());
        }
        if ($job['type'] === 'course_draft') {
            $problems = self::validate_outline(isset($data['outline']) ? $data['outline'] : null);
        } elseif ($job['type'] === 'lesson_script') {
            $problems = self::validate_script(isset($data['script']) ? $data['script'] : null);
        } else {
            $problems = array();
        }
        if ($problems) {
            throw new InvalidArgumentException('Edits did not pass validation: ' . implode('; ', $problems));
        }
        $data['edited_by'] = (int) $user_id;
        $data['edited_at'] = date('Y-m-d H:i:s');
        // An edit after approval voids the approval: what was approved is no longer what would ship.
        $this->update($id, array('output_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'draft', 'reviewed_by' => null, 'reviewed_at' => null));
        return $this->job($id);
    }

    public function approve($id, $user_id, $note = null) {
        return $this->review($id, 'approved', $user_id, $note);
    }

    public function reject($id, $user_id, $note) {
        if (trim((string) $note) === '') {
            throw new InvalidArgumentException('Say why it is rejected, so the next draft can be better.');
        }
        return $this->review($id, 'rejected', $user_id, $note);
    }

    private function review($id, $status, $user_id, $note) {
        $job = $this->job($id);
        if (!$job || $job['status'] !== 'draft') {
            throw new InvalidArgumentException('Only a draft can be ' . $status . '.');
        }
        $this->update($id, array('status' => $status, 'reviewed_by' => (int) $user_id, 'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note !== null ? mb_substr(trim($note), 0, 500) : null));
        $this->audit($status === 'approved' ? 'approve' : 'reject', $id, $user_id, $job['title'] . ($note ? ' — ' . $note : ''));
        if ($status === 'approved' && $job['type'] === 'lesson_script') {
            $in = json_decode($job['input_json'], true);
            if (!empty($in['then_render'])) {
                foreach (array('en', 'ar') as $loc) {
                    $this->queue_render($id, 'video_render', $loc, $user_id);
                }
            }
        }
        return $this->job($id);
    }

    public function retry($id, $user_id) {
        $job = $this->job($id);
        if (!$job || !in_array($job['status'], array('failed', 'rejected'), true)) {
            throw new InvalidArgumentException('Only failed or rejected jobs can be retried.');
        }
        $this->update($id, array('status' => 'queued', 'attempts' => 0, 'error' => null, 'progress' => 0,
            'progress_note' => 'Retry requested', 'output_json' => $job['type'] === 'avatar_video' ? null : $job['output_json'],
            'locked_at' => null, 'reviewed_by' => null, 'reviewed_at' => null));
        return $this->job($id);
    }

    public function delete($id) {
        $job = $this->job($id);
        if (!$job) {
            return false;
        }
        if ($job['status'] === 'published') {
            throw new InvalidArgumentException('A published job is part of the record and cannot be deleted.');
        }
        if ($job['artifact_path'] && strpos($job['artifact_path'], 'uploads/ai_videos/') === 0 && is_file(FCPATH . $job['artifact_path'])) {
            @unlink(FCPATH . $job['artifact_path']);
        }
        $this->CI->db->where('id', (int) $id)->delete('ha_ai_job');
        return true;
    }

    // =============================================================== publish

    /** @return array('message' => string, 'lms' => bool) */
    public function publish($id, $user_id) {
        $job = $this->job($id);
        if (!$job || $job['status'] !== 'approved') {
            throw new InvalidArgumentException('Only approved content can be published.');
        }
        $out = json_decode((string) $job['output_json'], true);
        $this->CI->db->trans_start();
        switch ($job['type']) {
            case 'course_draft':
                $course_id = $this->publish_course($out['outline'], $user_id);
                $this->update($id, array('course_id' => $course_id));
                $code = $out['outline']['code'];
                $message = 'Course "' . $out['outline']['en']['title'] . '" created with its lessons.';
                break;
            case 'lesson_script':
                $this->publish_script($job['lesson_id'], $out['script']);
                $code = $this->course_code($job['course_id']);
                $message = 'Lesson text, transcript and self-check quiz published (EN + AR).';
                break;
            default:
                $this->publish_video($job, $out, $user_id);
                $code = $this->course_code($job['course_id']);
                $message = 'Video attached to the lesson.';
        }
        $this->update($id, array('status' => 'published'));
        $this->CI->db->trans_complete();
        if (!$this->CI->db->trans_status()) {
            throw new RuntimeException('Publishing failed and was rolled back.');
        }
        $this->audit('publish', $id, $user_id, $job['title']);

        $lms = $this->sync_lms($code);
        if ($job['type'] === 'course_draft' && !empty(json_decode($job['input_json'], true)['auto_scripts'])) {
            $queued = 0;
            foreach ($this->lessons_without_video($this->job($id)['course_id']) as $l) {
                try {
                    $this->queue_script($l['id'], array('minutes' => 5), $user_id);
                    $queued++;
                } catch (Exception $e) {
                    break;   // daily limit reached: stop quietly, the rest can be queued later
                }
            }
            $message .= ' ' . $queued . ' lesson scripts queued.';
        }
        return array('message' => $message . ($lms ? ' Live in the LMS.' : ' LMS sync pending: run "php index.php ha_bridge sync".'), 'lms' => $lms);
    }

    private function publish_course(array $o, $user_id) {
        $now = date('Y-m-d H:i:s');
        $category = $this->CI->db->get_where('ha_category', array('code' => isset($o['category_code']) ? $o['category_code'] : ''))->row_array();
        $code = $this->unique_code($o['code']);
        $lesson_minutes = 0;
        foreach ($o['sections'] as $s) {
            foreach ($s['lessons'] as $l) {
                $lesson_minutes += max(1, (int) (isset($l['minutes']) ? $l['minutes'] : 5));
            }
        }
        $this->CI->db->insert('ha_course', array(
            'code' => $code, 'slug_en' => $this->unique_slug('slug_en', self::slug($o['en']['title'])),
            'slug_ar' => $this->unique_slug('slug_ar', self::slug_ar($o['ar']['title'])),
            'category_id' => $category ? $category['id'] : null, 'department_code' => $category ? $category['code'] : null,
            'level' => $o['level'], 'duration_minutes' => (int) (isset($o['duration_minutes']) ? $o['duration_minutes'] : $lesson_minutes) ?: $lesson_minutes,
            'is_free' => 1, 'price' => 0, 'currency' => 'SAR', 'certificate_eligible' => 1, 'pass_percentage' => 70,
            'status' => 'published', 'published_at' => $now, 'approved_by' => (int) $user_id, 'approved_at' => $now,
            'created_by' => (int) $user_id, 'rating_avg' => 0, 'rating_count' => 0, 'enrollment_count' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ));
        $course_id = (int) $this->CI->db->insert_id();

        foreach (array('en', 'ar') as $loc) {
            $t = $o[$loc];
            $this->CI->db->insert('ha_course_translation', array(
                'course_id' => $course_id, 'locale' => $loc, 'title' => mb_substr($t['title'], 0, 190),
                'short_description' => isset($t['short_description']) ? mb_substr($t['short_description'], 0, 500) : null,
                'description' => isset($t['description']) ? $t['description'] : null,
                'requirements' => isset($t['requirements']) ? (is_array($t['requirements']) ? implode("\n", $t['requirements']) : $t['requirements']) : null,
            ));
            foreach (array_values((array) (isset($t['outcomes']) ? $t['outcomes'] : array())) as $i => $body) {
                $this->CI->db->insert('ha_course_outcome', array('course_id' => $course_id, 'locale' => $loc, 'body' => mb_substr((string) $body, 0, 500), 'sort_order' => $i));
            }
            foreach (array_values((array) (isset($t['faqs']) ? $t['faqs'] : array())) as $i => $f) {
                if (!empty($f['q']) && !empty($f['a'])) {
                    $this->CI->db->insert('ha_course_faq', array('course_id' => $course_id, 'locale' => $loc,
                        'question' => mb_substr($f['q'], 0, 500), 'answer' => $f['a'], 'sort_order' => $i));
                }
            }
        }

        $order = 0;
        foreach (array_values($o['sections']) as $si => $s) {
            $this->CI->db->insert('ha_course_section', array('course_id' => $course_id,
                'title_en' => mb_substr($s['en'], 0, 190), 'title_ar' => mb_substr($s['ar'], 0, 190), 'sort_order' => $si));
            $section_id = (int) $this->CI->db->insert_id();
            foreach ($s['lessons'] as $l) {
                $type = (isset($l['type']) && $l['type'] === 'text') ? 'text' : 'video';
                $this->CI->db->insert('ha_lesson', array(
                    'course_id' => $course_id, 'section_id' => $section_id, 'lesson_type' => $type,
                    'duration_seconds' => max(60, (int) (isset($l['minutes']) ? $l['minutes'] : 5) * 60),
                    'is_mandatory' => 1, 'is_preview' => $order === 0 ? 1 : 0,
                    'completion_rule' => $type === 'video' ? 'watch_percentage' : 'open', 'required_watch_percentage' => 80,
                    'sort_order' => $order++, 'status' => 'published', 'created_at' => $now, 'updated_at' => $now,
                ));
                $lesson_id = (int) $this->CI->db->insert_id();
                foreach (array('en', 'ar') as $loc) {
                    $objective = isset($l[$loc]['objective']) ? $l[$loc]['objective'] : '';
                    $this->CI->db->insert('ha_lesson_translation', array(
                        'lesson_id' => $lesson_id, 'locale' => $loc, 'title' => mb_substr($l[$loc]['title'], 0, 190),
                        'objective' => mb_substr($objective, 0, 500),
                        'body' => '<p>' . htmlspecialchars($objective, ENT_QUOTES, 'UTF-8') . '</p>',
                    ));
                }
            }
        }
        return $course_id;
    }

    private function publish_script($lesson_id, array $script) {
        foreach (array('en', 'ar') as $loc) {
            $v = $script[$loc];
            $narration = implode("\n\n", array_filter(array_map(function ($s) {
                return isset($s['narration']) ? trim($s['narration']) : '';
            }, $v['slides'])));
            $body = self::clean_html($v['summary_html']) . self::quiz_html(isset($v['quiz']) ? $v['quiz'] : array(), $loc);
            $data = array('body' => $body, 'transcript' => $narration);
            if (!empty($v['objective'])) {
                $data['objective'] = mb_substr($v['objective'], 0, 500);
            }
            $exists = $this->CI->db->where(array('lesson_id' => (int) $lesson_id, 'locale' => $loc))->count_all_results('ha_lesson_translation');
            if ($exists) {
                $this->CI->db->where(array('lesson_id' => (int) $lesson_id, 'locale' => $loc))->update('ha_lesson_translation', $data);
            } else {
                $data += array('lesson_id' => (int) $lesson_id, 'locale' => $loc, 'title' => mb_substr(isset($v['title']) ? $v['title'] : '', 0, 190));
                $this->CI->db->insert('ha_lesson_translation', $data);
            }
        }
        $this->CI->db->where('id', (int) $lesson_id)->update('ha_lesson', array('updated_at' => date('Y-m-d H:i:s')));
    }

    private function publish_video(array $job, array $out, $user_id) {
        $render = $out['render'];
        $locale = $out['locale'];
        $now = date('Y-m-d H:i:s');
        if (!is_file(FCPATH . $render['video'])) {
            throw new RuntimeException('The rendered file ' . $render['video'] . ' is missing.');
        }
        $reviewer = $this->CI->db->get_where('users', array('id' => (int) $job['reviewed_by']))->row_array();
        $credit = 'Hospitality Academy' . ($reviewer ? ' · reviewed by ' . trim($reviewer['first_name'] . ' ' . $reviewer['last_name']) : '');

        if ($locale === 'en') {
            // The LMS player shows the English video; one source per lesson.
            $row = array(
                'provider' => 'academy', 'video_id' => 'job-' . $job['id'], 'watch_url' => $render['video'], 'embed_url' => $render['video'],
                'title' => mb_substr($job['title'], 0, 255), 'author_name' => $credit, 'author_url' => null,
                'thumbnail_url' => $render['poster'], 'duration_seconds' => (int) (isset($render['seconds']) ? $render['seconds'] : 0),
                'is_owned' => 1, 'status' => 'live', 'last_checked_at' => $now, 'last_error' => null, 'updated_at' => $now,
            );
            if ($this->CI->db->where('lesson_id', $job['lesson_id'])->count_all_results('ha_lesson_video_source')) {
                $this->CI->db->where('lesson_id', $job['lesson_id'])->update('ha_lesson_video_source', $row);
            } else {
                $this->CI->db->insert('ha_lesson_video_source', $row + array('lesson_id' => $job['lesson_id'], 'created_at' => $now));
            }
            $this->CI->db->where('id', $job['lesson_id'])->update('ha_lesson', array(
                'lesson_type' => 'video', 'video_source' => 'upload', 'video_url' => $render['video'],
                'duration_seconds' => max(60, (int) (isset($render['seconds']) ? $render['seconds'] : 60)), 'updated_at' => $now,
            ));
        }
        // Captions and, for Arabic, the video itself travel on the translation.
        $t = $this->CI->db->get_where('ha_lesson_translation', array('lesson_id' => $job['lesson_id'], 'locale' => $locale))->row_array();
        if ($t) {
            $data = array();
            if (!empty($render['captions'])) {
                $data['captions_url'] = $render['captions'];
            }
            if ($locale !== 'en') {
                $player = '<figure class="ha-lesson-video" data-job="' . (int) $job['id'] . '"><video controls preload="metadata" playsinline src="'
                    . htmlspecialchars(base_url($render['video']), ENT_QUOTES) . '"'
                    . (!empty($render['poster']) ? ' poster="' . htmlspecialchars(base_url($render['poster']), ENT_QUOTES) . '"' : '') . '>'
                    . (!empty($render['captions']) ? '<track kind="captions" srclang="' . $locale . '" src="' . htmlspecialchars(base_url($render['captions']), ENT_QUOTES) . '" default>' : '')
                    . '</video></figure>';
                $body = preg_replace('#<figure class="ha-lesson-video".*?</figure>#s', '', (string) $t['body']);
                $data['body'] = $player . $body;
            }
            if ($data) {
                $this->CI->db->where('id', $t['id'])->update('ha_lesson_translation', $data);
            }
        }
    }

    private function course_code($course_id) {
        $row = $this->CI->db->select('code')->get_where('ha_course', array('id' => (int) $course_id))->row_array();
        return $row ? $row['code'] : null;
    }

    /** Mirror one course into the legacy LMS tables through the bridge CLI. */
    public function sync_lms($code) {
        if (!$code || !$this->sync_lms_enabled) {
            return false;
        }
        $this->CI->load->library('ha_cli_runner');
        $r = $this->CI->ha_cli_runner->run(array('ha_bridge', 'sync_one', $code), 300);
        return $r['ok'];
    }

    private function audit($action, $job_id, $user_id, $description) {
        $this->CI->load->library('ha_audit');
        $this->CI->ha_audit->log($action, 'ha_ai_job', $job_id, array('user_id' => $user_id, 'description' => mb_substr($description, 0, 500)));
    }

    // ---------------------------------------------------------------- utils

    public static function slug($text) {
        $text = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $text)), '-'));
        return mb_substr($text !== '' ? $text : 'course', 0, 150);
    }

    public static function slug_ar($text) {
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', (string) $text);
        $text = trim(preg_replace('/[^\p{Arabic}a-z0-9]+/u', '-', mb_strtolower($text)), '-');
        return mb_substr($text !== '' ? $text : 'دورة', 0, 150);
    }

    private function unique_code($code) {
        $code = self::slug($code);
        $base = $code;
        $i = 2;
        while ($this->CI->db->where('code', $code)->count_all_results('ha_course')) {
            $code = $base . '-' . $i++;
        }
        return $code;
    }

    private function unique_slug($column, $slug) {
        $base = $slug;
        $i = 2;
        while ($this->CI->db->where($column, $slug)->count_all_results('ha_course')) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /** Model HTML is untrusted: keep structural tags, drop everything else and every attribute. */
    public static function clean_html($html) {
        $html = strip_tags((string) $html, '<h3><h4><p><ul><ol><li><strong><em><b><i><br><blockquote>');
        return preg_replace('/<(\w+)\s[^>]*>/', '<$1>', $html);
    }

    public static function quiz_html(array $quiz, $locale) {
        if (!$quiz) {
            return '';
        }
        $h = $locale === 'ar' ? 'اختبر فهمك' : 'Check your understanding';
        $show = $locale === 'ar' ? 'عرض الإجابة' : 'Show answer';
        $out = '<section class="ha-self-check"><h3>' . $h . '</h3><ol>';
        foreach ($quiz as $q) {
            $out .= '<li><p>' . htmlspecialchars($q['question'], ENT_QUOTES, 'UTF-8') . '</p><ul>';
            foreach ($q['options'] as $o) {
                $out .= '<li>' . htmlspecialchars((string) $o, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $answer = isset($q['options'][(int) $q['answer']]) ? $q['options'][(int) $q['answer']] : '';
            $out .= '</ul><details><summary>' . $show . '</summary><p><strong>' . htmlspecialchars((string) $answer, ENT_QUOTES, 'UTF-8') . '</strong>'
                . (!empty($q['explanation']) ? ' — ' . htmlspecialchars($q['explanation'], ENT_QUOTES, 'UTF-8') : '') . '</p></details></li>';
        }
        return $out . '</ol></section>';
    }
}
