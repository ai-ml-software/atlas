<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Ha_ai_gateway.php';

/**
 * A gateway whose network is a script: each request is matched against a
 * URL pattern and answered with a canned provider response, and every
 * request is recorded so tests can assert what would have been sent.
 */
class Ha_ai_gateway_fake extends Ha_ai_gateway {
    public $responses = array();
    public $calls = array();

    public function request($method, $url, array $headers = array(), $body = null, $timeout = null, $follow = false) {
        $this->calls[] = array('method' => $method, 'url' => $url, 'headers' => $headers,
            'body' => is_array($body) ? $body : json_decode((string) $body, true));
        foreach ($this->responses as $pattern => $reply) {
            if (preg_match($pattern, $url)) {
                $reply = is_callable($reply) ? $reply($url, $body) : $reply;
                $this->last_status = isset($reply['status']) ? $reply['status'] : 200;
                return array('status' => $this->last_status,
                    'body' => is_string($reply['body']) ? $reply['body'] : json_encode($reply['body'], JSON_UNESCAPED_UNICODE),
                    'headers' => array('content-type' => isset($reply['type']) ? $reply['type'] : 'application/json'), 'error' => '');
            }
        }
        throw new RuntimeException('Unexpected request in test: ' . $method . ' ' . $url);
    }
}

/**
 * AI Studio: the provider registry, encrypted credentials, endpoint
 * resolution, every chat wire format, task routing rules, JSON extraction,
 * output validation, the job state machine, review and publishing into the
 * curriculum, and the Arabic slide renderer.
 */
class Test_ai_studio extends Ha_testcase {

    /** @var Ha_ai_gateway_fake */
    private $gw;
    /** @var Ha_ai_studio */
    private $studio;
    private $admin_id;

    public function setUp() {
        $this->db->empty_table('ha_ai_provider');
        $this->db->empty_table('ha_ai_route');
        $this->db->empty_table('ha_ai_model');
        $this->db->empty_table('ha_ai_job');
        $this->db->empty_table('ha_ai_usage');
        unset($this->CI->ha_ai_gateway, $this->CI->ha_ai_studio, $this->CI->ha_ai_media);
        $this->gw = new Ha_ai_gateway_fake();
        $this->CI->ha_ai_gateway = $this->gw;
        require_once APPPATH . 'libraries/Ha_ai_studio.php';
        $this->studio = new Ha_ai_studio();
        $this->studio->sync_lms_enabled = false;
        $admin = $this->db->get_where('users', array('email' => 'admin@hospitalityacademy.sa'))->row_array();
        $this->admin_id = (int) $admin['id'];
    }

    private function enable($slug, $key = 'test-key-1234567890', array $extra = array()) {
        return $this->gw->save_provider($slug, $extra + array('enabled' => 1, 'api_key' => $key), $this->admin_id);
    }

    // -------------------------------------------------------------- registry

    public function test_registry_covers_providers_worldwide_with_usable_entries() {
        $providers = $this->gw->providers();
        $this->assertGreaterThan(40, count($providers), 'registry loaded');
        foreach (array('anthropic', 'openai', 'gemini', 'azure_openai', 'bedrock', 'mistral', 'deepseek', 'dashscope',
                       'moonshot', 'zhipu', 'yandex', 'gigachat', 'naver_clova', 'sarvam', 'core42', 'ollama', 'elevenlabs', 'heygen') as $slug) {
            $this->assertNotNull(isset($providers[$slug]) ? $providers[$slug] : null, 'provider present: ' . $slug);
        }
        $countries = array();
        foreach ($providers as $slug => $p) {
            $this->assertTrue(is_array($p['capabilities']), $slug . ' capabilities is a list');
            $this->assertContains($p['api_style'], array('openai', 'anthropic', 'gemini', 'cohere', 'bedrock', 'azure_openai', 'custom', 'local', null), $slug . ' api style');
            $countries[$p['hq_country']] = true;
        }
        $this->assertGreaterThan(8, count($countries), 'providers from many countries');
    }

    public function test_api_key_is_encrypted_kept_on_blank_and_removable() {
        $this->enable('anthropic', 'sk-ant-api03-SECRETSECRET');
        $row = $this->db->get_where('ha_ai_provider', array('slug' => 'anthropic'))->row_array();
        $this->assertNotContains('SECRETSECRET', $row['api_key_cipher'], 'no plaintext at rest');
        $this->assertSame('••••CRET', $row['api_key_hint']);

        $this->gw->save_provider('anthropic', array('enabled' => 1, 'api_key' => ''), $this->admin_id);
        $p = $this->gw->provider('anthropic');
        $this->assertTrue($p['has_key'], 'blank keeps the key');
        $this->assertSame('sk-ant-api03-SECRETSECRET', $this->gw->key_for($p));

        $this->gw->save_provider('anthropic', array('enabled' => 1, 'api_key' => '__clear__'), $this->admin_id);
        $this->assertFalse($this->gw->provider('anthropic')['has_key'], '__clear__ removes it');
    }

    public function test_base_url_override_must_be_https_except_localhost() {
        $gw = $this->gw;
        $admin = $this->admin_id;
        $this->assertThrows(function () use ($gw, $admin) { $gw->save_provider('openai', array('base_url' => 'http://evil.example/v1'), $admin); }, 'plain http refused');
        $this->assertThrows(function () use ($gw, $admin) { $gw->save_provider('nope_not_real', array(), $admin); }, 'unknown provider refused');
        $p = $gw->save_provider('ollama', array('enabled' => 1, 'base_url' => 'http://localhost:11434/v1'), $admin);
        $this->assertSame('http://localhost:11434/v1', $gw->endpoint($p));
        $this->assertFalse($p['needs_key'], 'local server needs no key');
    }

    public function test_endpoint_resolves_region_and_placeholders() {
        $p = $this->enable('azure_openai', 'k', array('settings' => array('resource' => 'altus-sa')));
        $this->assertSame('https://altus-sa.openai.azure.com/openai/v1', $this->gw->endpoint($p));
        $this->assertContains('resource', $this->gw->provider('azure_openai')['placeholders']);

        $d = $this->gw->provider('dashscope');
        $regions = array_keys($d['regional_base_urls']);
        $china = null;
        foreach ($regions as $r) {
            if (stripos($r, 'China mainland') !== false) { $china = $r; }
        }
        $this->assertNotNull($china, 'dashscope lists a China mainland region');
        $d = $this->enable('dashscope', 'k', array('region' => $china));
        $this->assertContains('dashscope.aliyuncs.com', $this->gw->endpoint($d), 'region picks the mainland host');
        $this->assertNotContains('intl', $this->gw->endpoint($d));
    }

    // -------------------------------------------------------------- adapters

    public function test_each_chat_wire_format_is_sent_and_parsed_correctly() {
        $msgs = array(array('role' => 'system', 'content' => 'Be brief.'), array('role' => 'user', 'content' => 'Hi'));
        $this->gw->responses = array(
            '#api\.anthropic\.com/v1/messages#' => array('body' => array('content' => array(array('type' => 'text', 'text' => 'Hello from Claude')), 'stop_reason' => 'end_turn', 'usage' => array('input_tokens' => 11, 'output_tokens' => 4))),
            '#api\.openai\.com/v1/chat/completions#' => array('body' => array('choices' => array(array('message' => array('content' => 'Hello from GPT'))), 'usage' => array('prompt_tokens' => 9, 'completion_tokens' => 3))),
            '#generativelanguage\.googleapis\.com/v1beta/models/gemini-x:generateContent#' => array('body' => array('candidates' => array(array('content' => array('parts' => array(array('text' => 'Hello from Gemini'))), 'finishReason' => 'STOP')), 'usageMetadata' => array('promptTokenCount' => 7, 'candidatesTokenCount' => 2))),
            '#api\.cohere\.com/v2/chat#' => array('body' => array('message' => array('content' => array(array('type' => 'text', 'text' => 'Hello from Cohere'))), 'usage' => array('tokens' => array('input_tokens' => 5, 'output_tokens' => 2)))),
            '#api\.deepseek\.com/chat/completions#' => array('body' => array('choices' => array(array('message' => array('content' => 'Hello from DeepSeek'))), 'usage' => array('prompt_tokens' => 1, 'completion_tokens' => 1))),
        );
        $cases = array('anthropic' => 'claude-x', 'openai' => 'gpt-x', 'gemini' => 'gemini-x', 'cohere' => 'command-x', 'deepseek' => 'deepseek-chat');
        foreach ($cases as $slug => $model) {
            $p = $this->enable($slug, 'KEY-' . $slug . '-000000');
            $r = $this->gw->chat_with($p, $model, $msgs, array('max_tokens' => 50));
            $this->assertContains('Hello from', $r['text'], $slug . ' text parsed');
            $this->assertGreaterThan(0, $r['input_tokens'], $slug . ' usage parsed');
            $call = end($this->gw->calls);
            $this->assertContains('KEY-' . $slug . '-000000', implode("\n", $call['headers']), $slug . ' key sent in a header');
            $this->assertNotContains('KEY-' . $slug, $call['url'], $slug . ' key never in the URL');
        }
        $anth = $this->gw->calls[0];
        $this->assertSame('Be brief.', $anth['body']['system'], 'Anthropic system prompt is top-level');
        $this->assertContains('anthropic-version: 2023-06-01', implode("\n", $anth['headers']));
        $gem = $this->gw->calls[2];
        $this->assertSame('Be brief.', $gem['body']['systemInstruction']['parts'][0]['text'], 'Gemini systemInstruction');
        $this->assertDatabaseCount('ha_ai_usage', 5, array('ok' => 1));
    }

    public function test_provider_errors_become_readable_messages() {
        $p = $this->enable('openai');
        $this->gw->responses = array('#chat/completions#' => array('status' => 401, 'body' => array('error' => array('message' => 'Incorrect API key provided'))));
        $gw = $this->gw;
        try {
            $gw->chat_with($p, 'gpt-x', array(array('role' => 'user', 'content' => 'x')));
            $this->fail('expected an exception');
        } catch (RuntimeException $e) {
            $this->assertContains('HTTP 401: Incorrect API key provided', $e->getMessage());
            $this->assertContains('Check the API key', $e->getMessage());
        }
        $this->assertDatabaseHas('ha_ai_usage', array('provider_slug' => 'openai', 'ok' => 0, 'http_status' => 401));
    }

    public function test_live_model_sync_replaces_the_cached_catalogue() {
        $this->enable('mistral');
        $this->gw->responses = array('#api\.mistral\.ai/v1/models#' => array('body' => array('data' => array(array('id' => 'mistral-large-latest'), array('id' => 'ministral-8b-latest')))));
        $this->assertCount(2, $this->gw->sync_models('mistral'));
        $this->gw->responses = array('#api\.mistral\.ai/v1/models#' => array('body' => array('data' => array(array('id' => 'mistral-large-latest')))));
        $this->gw->sync_models('mistral');
        $this->assertCount(1, $this->gw->models('mistral'), 'a model the provider dropped is no longer offered');
        $this->assertDatabaseHas('ha_ai_model', array('provider_slug' => 'mistral', 'model_id' => 'ministral-8b-latest', 'is_available' => 0));
    }

    public function test_routing_refuses_a_provider_without_the_capability() {
        $this->enable('deepseek');
        $gw = $this->gw;
        $this->assertThrows(function () use ($gw) { $gw->set_route('narration', 'deepseek', 'x'); }, 'deepseek has no TTS');
        $this->assertThrows(function () use ($gw) { $gw->set_route('lesson_script', 'deepseek', ''); }, 'model required');
        $this->assertThrows(function () use ($gw) { $gw->set_route('no_such_task', 'deepseek', 'x'); }, 'unknown task');
        $this->assertNotNull($gw->set_route('lesson_script', 'deepseek', 'deepseek-chat', array('temperature' => '9')));
        $this->assertEquals(2.0, (float) $gw->route('lesson_script')['temperature'], 'temperature clamped');
        $gw->save_provider('deepseek', array('enabled' => 0), $this->admin_id);
        $this->assertThrows(function () use ($gw) { $gw->resolve('lesson_script'); }, 'disabled provider cannot run a task');
    }

    public function test_sigv4_signature_is_deterministic_and_scoped() {
        $h1 = Ha_ai_gateway::sigv4('POST', 'bedrock-runtime.us-east-1.amazonaws.com', '/model/x/converse', '', '{}', 'us-east-1', 'bedrock', 'AKIDEXAMPLE', 'secret', null, 1700000000);
        $h2 = Ha_ai_gateway::sigv4('POST', 'bedrock-runtime.us-east-1.amazonaws.com', '/model/x/converse', '', '{}', 'us-east-1', 'bedrock', 'AKIDEXAMPLE', 'secret', null, 1700000000);
        $h3 = Ha_ai_gateway::sigv4('POST', 'bedrock-runtime.us-east-1.amazonaws.com', '/model/x/converse', '', '{"a":1}', 'us-east-1', 'bedrock', 'AKIDEXAMPLE', 'secret', null, 1700000000);
        $this->assertSame($h1, $h2, 'same input, same signature');
        $this->assertNotEquals($h1[2], $h3[2], 'payload is signed');
        $this->assertContains('Credential=AKIDEXAMPLE/20231114/us-east-1/bedrock/aws4_request', $h1[2]);
        $this->assertContains('SignedHeaders=content-type;host;x-amz-date', $h1[2]);
    }

    public function test_json_is_extracted_from_fenced_or_chatty_output() {
        $this->assertSame(array('a' => 1), Ha_ai_gateway::extract_json("```json\n{\"a\":1}\n```"));
        $this->assertSame(array('a' => 1), Ha_ai_gateway::extract_json("Sure! Here it is:\n{\"a\":1}\nHope it helps."));
        $this->assertNull(Ha_ai_gateway::extract_json('no json here'));
    }

    // -------------------------------------------------------------- workflow

    private function outline() {
        $lesson = function ($en, $ar, $type = 'video') {
            return array('type' => $type, 'minutes' => 6, 'en' => array('title' => $en, 'objective' => 'Do ' . $en), 'ar' => array('title' => $ar, 'objective' => 'تنفيذ ' . $ar));
        };
        return array(
            'code' => 'fo-hajj-arrivals', 'category_code' => 'front-office', 'level' => 'intermediate', 'duration_minutes' => 40,
            'en' => array('title' => 'Hajj Season Arrivals', 'short_description' => 'Welcome large pilgrim groups smoothly.',
                'description' => 'A practical course.', 'requirements' => 'None', 'outcomes' => array('Plan group check-in', 'Brief the team'),
                'faqs' => array(array('q' => 'Who is it for?', 'a' => 'Front office teams.'))),
            'ar' => array('title' => 'استقبال ضيوف موسم الحج', 'short_description' => 'استقبال مجموعات الحجاج بسلاسة.',
                'description' => 'دورة عملية.', 'requirements' => 'لا يوجد', 'outcomes' => array('تخطيط تسجيل المجموعات', 'إحاطة الفريق'),
                'faqs' => array(array('q' => 'لمن هذه الدورة؟', 'a' => 'لفرق الاستقبال.'))),
            'sections' => array(
                array('en' => 'Before arrival', 'ar' => 'قبل الوصول', 'lessons' => array($lesson('Rooming lists', 'قوائم الغرف'), $lesson('Team briefing', 'إحاطة الفريق', 'text'))),
                array('en' => 'On arrival', 'ar' => 'عند الوصول', 'lessons' => array($lesson('Group check-in', 'تسجيل المجموعات'))),
            ),
        );
    }

    private function script() {
        $v = function ($loc) {
            $ar = $loc === 'ar';
            return array(
                'title' => $ar ? 'قوائم الغرف' : 'Rooming lists', 'objective' => $ar ? 'إعداد قوائم الغرف' : 'Prepare rooming lists',
                'slides' => array(
                    array('title' => $ar ? 'لماذا القوائم؟' : 'Why lists matter', 'bullets' => array($ar ? 'السرعة' : 'Speed', $ar ? 'الدقة' : 'Accuracy'), 'narration' => $ar ? 'تساعد القوائم على سرعة التسجيل.' : 'Lists make check-in fast.'),
                    array('title' => $ar ? 'الخطوات' : 'Steps', 'bullets' => array($ar ? 'استلم القائمة' : 'Receive the list'), 'narration' => $ar ? 'استلم القائمة من المجموعة قبل يومين.' : 'Get the list two days ahead.'),
                ),
                'summary_html' => '<h3 onclick="x()">' . ($ar ? 'الملخص' : 'Summary') . '</h3><p>' . ($ar ? 'نص' : 'Text') . '</p><script>alert(1)</script>',
                'quiz' => array(array('question' => $ar ? 'متى تستلم القائمة؟' : 'When do you get the list?', 'options' => array('1', '2', '3', '4'), 'answer' => 1, 'explanation' => '')),
            );
        };
        return array('en' => $v('en'), 'ar' => $v('ar'));
    }

    private function route_text_tasks() {
        $this->enable('anthropic');
        $this->gw->set_route('course_outline', 'anthropic', 'claude-x');
        $this->gw->set_route('lesson_script', 'anthropic', 'claude-x');
    }

    private function reply_with(array $data) {
        $this->gw->responses = array('#/v1/messages#' => array('body' => array(
            'content' => array(array('type' => 'text', 'text' => "Here you go:\n```json\n" . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n```")),
            'stop_reason' => 'end_turn', 'usage' => array('input_tokens' => 100, 'output_tokens' => 900))));
    }

    public function test_course_draft_runs_reviews_and_publishes_a_bilingual_course() {
        $this->route_text_tasks();
        $this->reply_with($this->outline());
        $id = $this->studio->queue_course(array('topic' => 'Handling Hajj season arrivals', 'lessons' => 3, 'level' => 'intermediate'), $this->admin_id);
        $this->assertDatabaseHas('ha_ai_job', array('id' => $id, 'status' => 'queued'));

        $this->assertEquals($id, $this->studio->work_one(), 'worker claims the job');
        $job = $this->studio->job($id);
        $this->assertSame('draft', $job['status'], 'output waits for review: ' . $job['error']);
        $this->assertNull($this->studio->work_one(), 'queue empty');

        $studio = $this->studio;
        $admin = $this->admin_id;
        $this->assertThrows(function () use ($studio, $id, $admin) { $studio->publish($id, $admin); }, 'a draft cannot be published');
        $this->assertThrows(function () use ($studio, $id, $admin) { $studio->reject($id, $admin, ''); }, 'rejecting needs a reason');

        $this->studio->approve($id, $this->admin_id, 'Checked');
        $result = $this->studio->publish($id, $this->admin_id);
        $this->assertContains('Hajj Season Arrivals', $result['message']);
        $this->assertSame('published', $this->studio->job($id)['status']);

        $course = $this->db->get_where('ha_course', array('code' => 'fo-hajj-arrivals'))->row_array();
        $this->assertNotNull($course, 'course created');
        $this->assertSame('published', $course['status']);
        $this->assertEquals($this->admin_id, (int) $course['approved_by'], 'approver recorded');
        $this->assertDatabaseHas('ha_course_translation', array('course_id' => $course['id'], 'locale' => 'ar', 'title' => 'استقبال ضيوف موسم الحج'));
        $this->assertDatabaseCount('ha_course_section', 2, array('course_id' => $course['id']));
        $this->assertDatabaseCount('ha_lesson', 3, array('course_id' => $course['id']));
        $this->assertDatabaseCount('ha_course_outcome', 4, array('course_id' => $course['id']));
        $this->assertDatabaseHas('ha_audit_log', array('entity_type' => 'ha_ai_job', 'entity_id' => $id, 'action' => 'publish'));
        $this->assertContains('قبل-الوصول', 'قبل-الوصول');   // Arabic slug helper sanity
        $this->assertSame('استقبال-ضيوف-موسم-الحج', Ha_ai_studio::slug_ar('استقبال ضيوف موسم الحج'));
    }

    public function test_invalid_model_output_fails_the_job_and_publishes_nothing() {
        $this->route_text_tasks();
        $bad = $this->outline();
        $bad['ar']['title'] = 'Not Arabic at all';
        unset($bad['sections'][1]['ar']);
        $this->reply_with($bad);
        $courses_before = $this->db->count_all('ha_course');
        $id = $this->studio->queue_course(array('topic' => 'A topic long enough'), $this->admin_id);
        $this->studio->work_one();
        $job = $this->studio->job($id);
        $this->assertSame('failed', $job['status']);
        $this->assertContains('Arabic title is not in Arabic', $job['error']);
        $this->assertEquals($courses_before, $this->db->count_all('ha_course'), 'no course created from rejected output');
    }

    public function test_lesson_script_publishes_clean_bilingual_text_and_quiz() {
        $this->route_text_tasks();
        $this->reply_with($this->script());
        $lesson = $this->db->select('l.id')->from('ha_lesson l')->where('l.lesson_type', 'video')->limit(1)->get()->row_array();
        $id = $this->studio->queue_script($lesson['id'], array('minutes' => 3), $this->admin_id);
        $studio = $this->studio;
        $admin = $this->admin_id;
        $this->assertThrows(function () use ($studio, $lesson, $admin) { $studio->queue_script($lesson['id'], array(), $admin); }, 'no duplicate while one is queued');

        $this->studio->work_one();
        $this->assertSame('draft', $this->studio->job($id)['status']);
        $prompt = $this->gw->calls[0]['body'];
        $this->assertContains('Never invent statistics', $prompt['system'], 'house rules sent as the system prompt');

        $this->assertThrows(function () use ($studio, $id, $admin) { $studio->queue_render($id, 'video_render', 'en', $admin); }, 'no render before approval');
        $this->studio->approve($id, $this->admin_id);
        $this->studio->publish($id, $this->admin_id);

        $en = $this->db->get_where('ha_lesson_translation', array('lesson_id' => $lesson['id'], 'locale' => 'en'))->row_array();
        $ar = $this->db->get_where('ha_lesson_translation', array('lesson_id' => $lesson['id'], 'locale' => 'ar'))->row_array();
        $this->assertContains('Check your understanding', $en['body']);
        $this->assertContains('اختبر فهمك', $ar['body']);
        $this->assertNotContains('<script', $en['body'], 'model HTML is sanitised');
        $this->assertNotContains('onclick', $en['body'], 'attributes stripped');
        $this->assertContains('Lists make check-in fast.', $en['transcript'], 'narration becomes the transcript');
    }

    public function test_editing_an_approved_draft_voids_the_approval() {
        $this->route_text_tasks();
        $this->reply_with($this->outline());
        $id = $this->studio->queue_course(array('topic' => 'Another good topic'), $this->admin_id);
        $this->studio->work_one();
        $this->studio->approve($id, $this->admin_id);
        $out = json_decode($this->studio->job($id)['output_json'], true);
        $out['outline']['en']['title'] = 'Hajj Arrivals (edited)';
        $this->studio->save_edit($id, json_encode($out), $this->admin_id);
        $job = $this->studio->job($id);
        $this->assertSame('draft', $job['status'], 'back to draft');
        $this->assertNull($job['reviewed_by']);
        $studio = $this->studio;
        $this->assertThrows(function () use ($studio, $id) { $studio->save_edit($id, '{"outline": {}}', 1); }, 'invalid edits refused');
    }

    public function test_transient_failures_retry_and_permanent_ones_do_not() {
        $this->route_text_tasks();
        $this->gw->responses = array('#/v1/messages#' => array('status' => 529, 'body' => array('error' => array('message' => 'Overloaded'))));
        $id = $this->studio->queue_course(array('topic' => 'Retry behaviour check'), $this->admin_id);
        $this->studio->work_one();
        $this->assertSame('queued', $this->studio->job($id)['status'], '5xx is retried');

        $this->gw->responses = array('#/v1/messages#' => array('status' => 401, 'body' => array('error' => array('message' => 'invalid x-api-key'))));
        $this->db->where('id', $id)->update('ha_ai_job', array('locked_at' => null));
        $this->studio->work_one();
        $this->assertSame('failed', $this->studio->job($id)['status'], '401 is not retried');
    }

    public function test_stale_running_jobs_are_recovered() {
        $id = $this->studio->queue_course(array('topic' => 'Crash recovery check'), $this->admin_id);
        $this->db->where('id', $id)->update('ha_ai_job', array('status' => 'running', 'locked_at' => date('Y-m-d H:i:s', time() - 7200)));
        $this->route_text_tasks();
        $this->reply_with($this->outline());
        $this->studio->work_one();
        $this->assertSame('draft', $this->studio->job($id)['status'], 'a job orphaned by a dead worker runs again');
    }

    // --------------------------------------------------------------- render

    public function test_arabic_is_shaped_with_joining_forms_and_lam_alef() {
        require_once APPPATH . 'libraries/Ha_arabic.php';
        $shaped = Ha_arabic::shape('سلام');
        $cps = array_map(function ($c) { return mb_ord($c); }, preg_split('//u', $shaped, -1, PREG_SPLIT_NO_EMPTY));
        $this->assertSame(array(0xFEB3, 0xFEFC, 0xFEE1), $cps, 'initial seen, final lam-alef ligature, isolated meem');
        $this->assertSame(mb_chr(0xFE8D), Ha_arabic::shape('ا'), 'isolated alef');
        $line = Ha_arabic::visual(Ha_arabic::shape('الغرفة 305'));
        $this->assertContains('305', $line, 'numbers keep their reading order');
        $this->assertFalse(Ha_arabic::has_arabic('Hotel'));
    }

    public function test_slides_render_as_images_in_both_languages() {
        $this->CI->load->library('ha_video_renderer');
        $r = $this->CI->ha_video_renderer;
        if (!function_exists('imagettftext') || !$r->font(true)) {
            $this->assertTrue(true, 'GD/fonts unavailable on this host');
            return;
        }
        $script = $this->script();
        foreach (array('en', 'ar') as $loc) {
            $file = sys_get_temp_dir() . '/ha_slide_' . $loc . '.png';
            $r->draw_slide($script[$loc]['slides'][0], 1, 2, $loc, array('course' => 'Front Office', 'lesson' => 'Lists'), $file);
            $size = getimagesize($file);
            $this->assertEquals(1280, $size[0], $loc . ' width');
            $this->assertEquals(720, $size[1], $loc . ' height');
            $this->assertGreaterThan(10000, filesize($file), $loc . ' slide has content');
            @unlink($file);
        }
    }

    public function test_media_adapters_are_matched_to_real_vendors_only() {
        require_once APPPATH . 'libraries/Ha_ai_media.php';
        $p = $this->gw->providers();
        $this->assertTrue(Ha_ai_media::supports($p['openai'], 'tts'), 'OpenAI speech');
        $this->assertTrue(Ha_ai_media::supports($p['elevenlabs'], 'tts'));
        $this->assertTrue(Ha_ai_media::supports($p['heygen'], 'avatar'));
        $this->assertTrue(Ha_ai_media::supports($p['d_id'], 'avatar'));
        $this->assertTrue(Ha_ai_media::supports($p['google_veo'], 'clip'));
        $this->assertFalse(Ha_ai_media::supports($p['deepseek'], 'tts'), 'no speech adapter claimed for a chat-only vendor');
        $this->assertFalse(isset($p['openai_sora']) && Ha_ai_media::supports($p['openai_sora'], 'clip'), 'discontinued Sora is not offered');
        $wav = Ha_ai_media::pcm_to_wav(str_repeat("\0\0", 2400), 24000, 1, 16);
        $this->assertSame('RIFF', substr($wav, 0, 4));
        $this->assertEquals(44 + 4800, strlen($wav), 'WAV header is 44 bytes');
    }
}
