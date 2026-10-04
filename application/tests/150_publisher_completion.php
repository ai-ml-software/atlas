<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** AI Publisher: OCR worker, retry policy, retention, media, provenance, AI validation, translation and SOP governance. */
class Test_publisher_completion extends Ha_testcase {
    private $gateway; private $config;
    private function fx($name) { return APPPATH . 'tests/fixtures/publisher/' . $name; }
    public function setUp() {
        $this->CI->load->library(array('ha_document_jobs', 'ha_document_publisher'));
        $this->CI->ha_auth->assume((int) $this->db->get_where('users', array('email' => 'admin@hospitalityacademy.sa'))->row('id'));
        $this->gateway = $this->CI->ha_ai_gateway; $this->config = $this->CI->config->config['ha_publisher'];
    }
    public function tearDown() { $this->CI->ha_ai_gateway = $this->gateway; $this->CI->config->config['ha_publisher'] = $this->config; }
    private function set($key, $value) { $this->CI->config->config['ha_publisher'][$key] = $value; }
    /** Deterministic chat provider: returns queued responses (string or Throwable). */
    private function fake(array $responses) {
        $this->CI->ha_ai_gateway = new class($responses) {
            public $responses; public $calls = array();
            public function __construct($r) { $this->responses = $r; }
            public function provider($slug) { return $slug === 'fake' ? array('slug' => 'fake', 'name' => 'Fake', 'enabled' => true, 'capabilities' => array('chat')) : null; }
            public function chat_with($p, $model, $messages, $opts = array(), $task = null) { $this->calls[] = $messages; $r = array_shift($this->responses); if ($r instanceof Throwable) throw $r; return array('text' => is_string($r) ? $r : json_encode($r, JSON_UNESCAPED_UNICODE)); }
        };
        return $this->CI->ha_ai_gateway;
    }
    private function error(callable $fn) { try { $fn(); } catch (Throwable $e) { return $e; } return null; }
    private function upload($fixture, $name, $target = 'article', $locale = 'en') {
        $tmp = tempnam(sys_get_temp_dir(), 'altus'); copy(is_file($fixture) ? $fixture : $this->fx($fixture), $tmp);
        $id = $this->CI->ha_document_jobs->enqueue($tmp, $name, $target, $locale); unlink($tmp); return $id;
    }
    private function job($draft) { return $this->db->order_by('id', 'DESC')->get_where('ha_document_job', array('draft_id' => (int) $draft))->row_array(); }
    private function paged_draft($target = 'page') {
        $P = $this->CI->ha_document_publisher;
        return $P->source("[Page 1]\nAlways wear PPE before cleaning hotel bathrooms.\n\n[Page 2]\nReport every chemical spill to the duty manager.", 'paged.txt', $target, 'en');
    }
    private function sections($n = 2) { $s = array(); for ($i = 1; $i <= $n; $i++) { $s[] = array('heading' => 'Section ' . $i, 'body' => '<p>Body ' . $i . '</p>', 'source_pages' => array($i)); } return array('title' => 'Publisher completion ' . uniqid(), 'summary' => 'Source facts', 'sections' => $s); }

    public function test_scanned_pdf_uses_ocr_and_records_page_provenance() {
        $id = $this->upload('mixed.pdf', 'mixed.pdf'); $this->CI->ha_document_jobs->work();
        $j = $this->CI->ha_document_jobs->status($id);
        $this->assertEquals('completed', $j['status'], 'job completed: ' . $j['error']);
        $this->assertEquals(array('text', 'ocr'), array_column($j['pages'], 'method'));
        $this->assertEquals(array(1, 2), array_column($j['pages'], 'page'));
        $this->assertTrue($j['pages'][1]['review_required'] && !$j['pages'][0]['review_required'], 'OCR page needs review');
        $this->assertGreaterThan(30, $j['pages'][1]['characters']);
        $text = $this->CI->ha_document_publisher->find($id)['source_text'];
        $this->assertContains('[Page 2]', $text); $this->assertContains('PPE', $text);
        $this->assertEquals(array(1, 2), $this->CI->ha_document_publisher->source_pages($text));
    }
    public function test_missing_tesseract_fails_once_with_actionable_message() {
        $this->set('tesseract', 'C:/altus-missing/tesseract.exe');
        $id = $this->upload('scanned.pdf', 'scanned.pdf'); $this->CI->ha_document_jobs->work();
        $j = $this->job($id);
        $this->assertEquals('failed', $j['status']); $this->assertEquals(1, (int) $j['attempts'], 'dependency errors are not retried automatically');
        $this->assertContains('Tesseract OCR was not found', $j['error']);
        $this->assertEquals('extracting', $this->CI->ha_document_publisher->find($id)['status']);
    }
    public function test_malformed_and_disguised_uploads_are_refused() {
        $id = $this->upload('malformed.pdf', 'broken.pdf'); $this->CI->ha_document_jobs->work();
        $j = $this->job($id); $this->assertEquals('failed', $j['status']); $this->assertContains('damaged or not readable', $j['error']);
        $tmp = tempnam(sys_get_temp_dir(), 'altus'); file_put_contents($tmp, 'This is plain text pretending to be a PDF document.');
        $e = $this->error(function () use ($tmp) { $this->CI->ha_document_jobs->enqueue($tmp, 'fake.pdf', 'article', 'en'); }); unlink($tmp);
        $this->assertTrue($e instanceof InvalidArgumentException); $this->assertContains('not PDF', $e->getMessage());
        $e = $this->error(function () { $this->CI->ha_document_jobs->enqueue($this->fx('text.pdf'), 'script.exe', 'article', 'en'); });
        $this->assertTrue($e instanceof InvalidArgumentException);
    }
    public function test_cancellation_is_honoured_and_finished_jobs_cannot_be_cancelled() {
        $J = $this->CI->ha_document_jobs; $id = $this->upload('text.pdf', 'text.pdf');
        $J->control($id, 'cancel'); $J->work(); $this->assertEquals('cancelled', $J->status($id)['status']);
        $J->control($id, 'retry'); $J->work(); $this->assertEquals('completed', $J->status($id)['status']);
        $this->assertThrows(function () use ($J, $id) { $J->control($id, 'cancel'); }, 'completed job cannot be cancelled');
        $this->assertThrows(function () use ($J, $id) { $J->control($id, 'retry'); }, 'completed job cannot be retried');
    }
    public function test_transient_failures_back_off_and_stop_at_the_retry_cap() {
        $J = $this->CI->ha_document_jobs; $this->set('max_attempts', 3); $this->set('attempt_limit', 4); $this->set('backoff_seconds', 60);
        $this->assertEquals(60, $J->backoff(1)); $this->assertEquals(120, $J->backoff(2)); $this->assertEquals(240, $J->backoff(3));
        $id = $this->upload('text.pdf', 'text.pdf'); $j = $this->job($id);
        $old = gmdate('Y-m-d H:i:s', time() - 1000);
        $this->db->where('id', $j['id'])->update('ha_document_job', array('status' => 'processing', 'attempts' => 1, 'updated_at' => $old));
        $this->assertFalse($J->work(), 'requeued job waits for its backoff');
        $j = $this->job($id); $this->assertEquals('queued', $j['status']); $this->assertTrue(strtotime($j['next_attempt_at'] . ' UTC') > time() + 30, 'next attempt scheduled in the future');
        $this->db->where('id', $j['id'])->update('ha_document_job', array('status' => 'processing', 'attempts' => 3, 'updated_at' => $old));
        $J->work(); $j = $this->job($id);
        $this->assertEquals('failed', $j['status'], 'retry cap reached'); $this->assertContains('after 3 attempts', $j['error']);
        $J->control($id, 'retry'); $this->assertEquals('queued', $this->job($id)['status'], 'one manual retry within attempt_limit');
        $this->db->where('id', $j['id'])->update('ha_document_job', array('status' => 'failed', 'attempts' => 4));
        $e = $this->error(function () use ($J, $id) { $J->control($id, 'retry'); });
        $this->assertTrue($e !== null && strpos($e->getMessage(), 'Retry limit reached') !== false, 'manual retries are capped');
    }
    public function test_retention_cleanup_removes_finished_sources_only() {
        $J = $this->CI->ha_document_jobs; $done = $this->upload('text.pdf', 'text.pdf'); $J->work(); $queued = $this->upload('text.pdf', 'text.pdf');
        $a = $this->job($done); $b = $this->job($queued);
        $this->db->where_in('id', array($a['id'], $b['id']))->update('ha_document_job', array('updated_at' => gmdate('Y-m-d H:i:s', time() - 40 * 86400)));
        $this->set('source_retention_days', 30); $this->assertGreaterThanOrEqual(1, $J->cleanup());
        $this->assertFalse(is_file($a['source_path']), 'expired source deleted'); $this->assertEquals('', $this->job($done)['source_path']);
        $this->assertTrue(is_file($b['source_path']), 'queued source retained');
        $this->db->where('id', $a['id'])->update('ha_document_job', array('status' => 'failed'));
        $e = $this->error(function () use ($J, $done) { $J->control($done, 'retry'); });
        $this->assertTrue($e !== null && strpos($e->getMessage(), 'retention') !== false, 'retry explains the removed source');
        $this->db->where('id', $b['id'])->update('ha_document_job', array('status' => 'cancelled')); $J->cleanup(); @unlink($b['source_path']);
    }
    public function test_worker_heartbeat_and_setup_health_are_reported() {
        $J = $this->CI->ha_document_jobs; $J->heartbeat('batch', 2); $w = $J->worker_status();
        $this->assertTrue($w['alive']); $this->assertContains('publisher_cli daemon', $w['command']); $this->assertNotEmpty($w['last_heartbeat']);
        $h = $J->health(true); $keys = array_column($h['checks'], 'key');
        foreach (array('python', 'pypdf', 'pymupdf', 'tesseract', 'tessdata_eng', 'tessdata_ara', 'ai', 'worker') as $k) { $this->assertContains($k, $keys, $k . ' check present'); }
        $by = array_column($h['checks'], 'ok', 'key'); $this->assertTrue($by['python'] && $by['pypdf'] && $by['pymupdf'], 'configured Python and libraries detected');
        foreach ($h['checks'] as $c) { if (!$c['ok']) { $this->assertNotEmpty($c['fix'], $c['key'] . ' failing check has guidance'); } }
        $this->set('python', 'C:/altus-missing/python.exe'); $h = $J->health(true); $py = $h['checks'][0];
        $this->assertFalse($py['ok']); $this->assertContains('ALTUS_PUBLISHER_PYTHON', $py['fix']); $J->health(true);
    }
    public function test_ai_validation_failure_leaves_the_draft_unchanged() {
        $P = $this->CI->ha_document_publisher; $id = $this->paged_draft(); $v = $P->save($id, $this->sections(), 1); $before = $P->find($id);
        $this->fake(array(array('title' => 'Missing sections'), 'not json at all'));
        $e = $this->error(function () use ($P, $id, $v) { $P->generate($id, $v, 'fake', 'm', ''); });
        $this->assertTrue($e instanceof RuntimeException); $this->assertContains('did not match the required structure', $e->getMessage());
        $e = $this->error(function () use ($P, $id, $v) { $P->generate($id, $v, 'fake', 'm', ''); }); $this->assertContains('invalid JSON', $e->getMessage());
        $after = $P->find($id); $this->assertEquals($before['payload_json'], $after['payload_json']); $this->assertEquals($before['version'], $after['version']);
    }
    public function test_generation_carries_source_page_references() {
        $P = $this->CI->ha_document_publisher; $id = $this->paged_draft(); $gw = $this->fake(array(array('title' => 'Paged', 'summary' => 's', 'sections' => array(array('heading' => 'A', 'body' => '<p>a</p>', 'source_pages' => array(2, 1, 9, 'x'))))));
        $v = $P->generate($id, 1, 'fake', 'm', 'brief'); $p = json_decode($P->find($id)['payload_json'], true);
        $this->assertEquals(array(1, 2), $p['sections'][0]['source_pages'], 'unknown pages dropped, sorted');
        $this->assertContains('[Page N]', $gw->calls[0][0]['content']); $this->assertContains('[Page 1]', $gw->calls[0][1]['content']);
        $this->assertEquals(2, $v);
    }
    public function test_regenerate_replaces_only_the_selected_section() {
        $P = $this->CI->ha_document_publisher; $id = $this->paged_draft(); $v = $P->save($id, $this->sections(3), 1);
        $this->fake(array(array('title' => 'ignored', 'sections' => array(array('heading' => 'Rewritten', 'body' => '<p>New</p>', 'source_pages' => array(2))))));
        $P->generate($id, $v, 'fake', 'm', '', 'sections:1'); $p = json_decode($P->find($id)['payload_json'], true);
        $this->assertEquals(array('Section 1', 'Rewritten', 'Section 3'), array_column($p['sections'], 'heading'));
        $this->assertEquals(array(2), $p['sections'][1]['source_pages']);
        $this->assertThrows(function () use ($P, $id) { $P->generate($id, 1, 'fake', 'm', '', 'sections:1'); }, 'stale version refused');
        $this->assertThrows(function () use ($P, $id, $v) { $P->generate($id, $v + 1, 'fake', 'm', '', 'sections:9'); }, 'missing selection refused');
    }
    public function test_translation_creates_independent_draft_and_failures_leave_no_orphan() {
        $P = $this->CI->ha_document_publisher; $id = $this->paged_draft('article'); $v = $P->save($id, $this->sections(), 1);
        $media = $this->media(); $P->set_media($id, $media);
        $count = $this->db->count_all('ha_publisher_draft');
        $this->fake(array(new RuntimeException('Provider timeout')));
        $this->assertThrows(function () use ($P, $id, $v) { $P->translate($id, $v, 'fake', 'm', 'ar'); });
        $this->assertEquals($count, $this->db->count_all('ha_publisher_draft'), 'failed translation removed');
        $this->fake(array(array('title' => 'مقالة', 'summary' => 'ملخص', 'sections' => array(array('heading' => 'القسم', 'body' => '<p>نص</p>', 'source_pages' => array())))));
        $new = $P->translate($id, $v, 'fake', 'm', 'ar'); $d = $P->find($new);
        $this->assertEquals('ar', $d['locale']); $this->assertEquals('draft', $d['status']); $this->assertEquals($media, (int) $d['media_id']);
        $this->assertEquals('مقالة', json_decode($d['payload_json'], true)['title']);
        $this->assertThrows(function () use ($P, $id, $v) { $P->translate($id, $v, 'fake', 'm', 'en'); }, 'same language refused');
    }
    public function test_correct_source_is_versioned_and_bounded() {
        $P = $this->CI->ha_document_publisher; $id = $this->paged_draft();
        $v = $P->correct_source($id, "[Page 1]\nCorrected: always wear gloves and PPE before cleaning.", 1); $this->assertEquals(2, $v);
        $this->assertContains('Corrected', $P->find($id)['source_text']);
        $e = $this->error(function () use ($P, $id) { $P->correct_source($id, 'Another corrected text that is long enough to save.', 1); }); $this->assertTrue($e instanceof DomainException, 'stale version');
        $e = $this->error(function () use ($P, $id) { $P->correct_source($id, 'short', 2); }); $this->assertContains('30–120,000', $e->getMessage());
    }
    private function media() {
        $this->db->insert('ha_media', array('disk' => 'public', 'file_path' => 'uploads/academy/page-home.webp', 'original_name' => 'publisher-test.webp', 'mime_type' => 'image/webp', 'extension' => 'webp', 'file_size' => 10, 'width' => 10, 'height' => 10, 'alt_en' => 'Hotel lobby', 'alt_ar' => 'ردهة', 'checksum' => sha1(uniqid()), 'uploaded_by' => $this->CI->ha_auth->id(), 'created_at' => gmdate('Y-m-d H:i:s')));
        return (int) $this->db->insert_id();
    }
    public function test_selected_media_becomes_the_featured_image() {
        $P = $this->CI->ha_document_publisher; $media = $this->media();
        $this->assertThrows(function () use ($P) { $P->set_media($this->paged_draft(), 999999); }, 'unknown media refused');
        $tables = array('article' => array('ha_article', 'cover_image'), 'topic' => array('ha_topic', 'hero_image'), 'course' => array('ha_course', 'thumbnail'));
        foreach (array('article', 'topic', 'page', 'course') as $target) {
            $id = $this->paged_draft($target); $P->set_media($id, $media);
            $payload = $target === 'course' ? array('title' => 'Media course ' . uniqid(), 'summary' => 's', 'modules' => array(array('title' => 'M', 'lessons' => array(array('title' => 'L', 'body' => '<p>b</p>', 'source_pages' => array(1)))))) : $this->sections();
            $v = $P->save($id, $payload, 1); $entity = $P->materialize($id, $v);
            if ($target === 'page') { $this->assertEquals('uploads/academy/page-home.webp', $this->db->get_where('ha_page_translation', array('page_id' => $entity))->row('hero_image'), 'page hero image'); }
            else { $this->assertEquals('uploads/academy/page-home.webp', $this->db->get_where($tables[$target][0], array('id' => $entity))->row($tables[$target][1]), $target . ' image'); }
            $this->assertThrows(function () use ($P, $id, $media) { $P->set_media($id, $media); }, 'imported drafts cannot change media');
        }
        $this->assertEquals('Hotel lobby', $this->db->order_by('id', 'DESC')->get('ha_article')->row('cover_image_alt_en'));
    }
    public function test_sop_maps_fields_and_submits_to_internal_review_without_approval() {
        $P = $this->CI->ha_document_publisher; $reviewers = $P->reviewers(); $this->assertNotEmpty($reviewers, 'a knowledge reviewer exists');
        $reviewer = (int) $reviewers[0]['id']; $me = $this->CI->ha_auth->id();
        $id = $this->paged_draft('sop');
        $v = $P->save($id, array('title' => 'Bathroom cleaning SOP ' . uniqid(), 'summary' => 'Safe bathroom cleaning', 'sop' => array('purpose' => '<p>Keep staff safe.</p>', 'scope' => '<p>Housekeeping</p>', 'procedure' => '<ol><li>Wear PPE</li></ol>', 'safety_notes' => '<p>Never mix chemicals.</p>', 'escalation' => '<p>Call the duty manager.</p>'), 'sections' => array(array('heading' => 'Spills', 'body' => '<p>Report spills.</p>', 'source_pages' => array(2)))), 1);
        $this->assertThrows(function () use ($P, $id, $v) { $P->materialize($id, $v, array('submit' => true)); }, 'submit needs a reviewer');
        $this->assertThrows(function () use ($P, $id, $v, $me) { $P->materialize($id, $v, array('submit' => true, 'reviewer_user_id' => $me)); }, 'author cannot review own SOP');
        $sop = $P->materialize($id, $v, array('submit' => true, 'reviewer_user_id' => $reviewer, 'approver_user_id' => $reviewer));
        $doc = $this->db->get_where('ha_sop_document', array('id' => $sop))->row_array();
        $this->assertEquals('internal_review', $doc['status']); $this->assertEquals($reviewer, (int) $doc['reviewer_user_id']); $this->assertEquals($reviewer, (int) $doc['approver_user_id']);
        $ver = $this->db->get_where('ha_sop_version', array('sop_id' => $sop))->row_array();
        $this->assertEquals('internal_review', $ver['status']); $this->assertNull($ver['approved_at'], 'never auto-approved'); $this->assertNull($ver['published_at']);
        $t = $this->db->get_where('ha_sop_version_translation', array('version_id' => $ver['id'], 'locale' => 'en'))->row_array();
        $this->assertContains('Keep staff safe', $t['purpose']); $this->assertContains('Never mix chemicals', $t['safety_notes']); $this->assertContains('Wear PPE', $t['procedure']); $this->assertContains('Report spills', $t['procedure']); $this->assertContains('duty manager', $t['escalation']);
        $this->assertDatabaseHas('ha_knowledge_review', array('sop_id' => $sop, 'action' => 'submit'));
        $this->assertEquals($sop, $P->materialize($id, $v), 'repeat import is idempotent');
        // Without submission the SOP stays a draft.
        $id2 = $this->paged_draft('sop'); $v2 = $P->save($id2, array('title' => 'Draft SOP ' . uniqid(), 'summary' => 'x', 'sections' => array(array('heading' => 'P', 'body' => '<p>Wear PPE.</p>'))), 1);
        $sop2 = $P->materialize($id2, $v2); $this->assertEquals('draft', $this->db->get_where('ha_sop_version', array('sop_id' => $sop2))->row('status'));
    }
}
