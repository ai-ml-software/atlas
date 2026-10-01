<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** PDF provenance, stable identities and publication readiness. No inferred approvals. */
class Ha_library_review {
    private $CI;
    private $db;
    private $site_collected = false;
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->db = $this->CI->db;
    }
    public static function data_dir() { return APPPATH . 'seeds/library_support/'; }
    public static function json_file($path) {
        if (!is_file($path)) { throw new RuntimeException('Missing file: ' . $path); }
        $v = json_decode(file_get_contents($path), true);
        if (!is_array($v)) { throw new RuntimeException('Invalid JSON: ' . basename($path)); }
        return $v;
    }
    public static function write_json($path, array $value) {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) { throw new RuntimeException('Cannot create output directory.'); }
        $temp = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temp, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false
            || !rename($temp, $path)) { @unlink($temp); throw new RuntimeException('Cannot save JSON file.'); }
    }
    public function manifest() { return self::json_file(self::data_dir() . 'manifest.json'); }
    public function course_files() {
        $files = array();
        foreach ($this->manifest()['sources'] as $s) {
            if (!preg_match('/^dy-([a-z0-9-]+)$/D', $s['course_code'], $m)) { throw new RuntimeException('Invalid manifest course code.'); }
            $files[$s['course_code']] = APPPATH . 'seeds/library/' . $m[1] . '.json';
        }
        ksort($files);
        return $files;
    }
    public function course($code) {
        $files = $this->course_files();
        if (!isset($files[$code])) { throw new InvalidArgumentException('Course is outside the PDF manifest: ' . $code); }
        return self::json_file($files[$code]);
    }
    public static function signature(array $course) {
        return hash('sha256', json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    public function stage(array $course) {
        $code = 'dy-' . $course['slug'];
        if (!isset($this->course_files()[$code])) { throw new InvalidArgumentException('Course is outside the PDF manifest.'); }
        $sig = self::signature($course);
        if (!$this->db->get_where('ha_library_revision', array('course_code' => $code, 'signature' => $sig))->num_rows()) {
            $this->db->insert('ha_library_revision', array('course_code' => $code, 'signature' => $sig,
                'candidate_json' => json_encode($course, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'created_at' => date('Y-m-d H:i:s')));
        }
        return $sig;
    }
    public function identity($entity, $key, $code, $fallback_id = 0) {
        $row = $this->db->get_where('ha_library_identity', array('entity' => $entity, 'source_key' => $key))->row_array();
        if ($row) { return (int) $row['entity_id']; }
        if ($fallback_id) {
            $this->db->insert('ha_library_identity', array('entity' => $entity, 'source_key' => $key,
                'course_code' => $code, 'entity_id' => (int) $fallback_id));
        }
        return (int) $fallback_id;
    }
    public function keys(array $c) {
        $code = 'dy-' . $c['slug']; $lessons = array(); $questions = array(); $sections = array(); $n = 0;
        foreach ($c['locales']['en']['chapters'] as $ci => $ch) {
            $section = isset($ch['source_key']) ? $ch['source_key'] : $code . ':section-' . sprintf('%03d', $ci+1);
            if (strpos($section, $code . ':') !== 0 || isset($sections[$section])) { throw new InvalidArgumentException('Invalid or duplicate section identity: ' . $section); }
            $sections[$section] = $ci;
            foreach ($ch['lessons'] as $li => $l) {
                $key = isset($l['source_key']) ? $l['source_key'] : $code . ':lesson-' . sprintf('%03d', ++$n);
                if (isset($l['source_key'])) { $n++; }
                if (strpos($key, $code . ':') !== 0 || isset($lessons[$key])) { throw new InvalidArgumentException('Invalid or duplicate lesson identity: ' . $key); }
                $lessons[$key] = array('chapter' => $ci, 'lesson' => $li, 'position' => $n-1, 'title' => $l['title']);
                foreach ($l['quiz']['questions'] as $qi => $q) {
                    $qkey = isset($q['source_key']) ? $q['source_key'] : $key . ':q' . sprintf('%03d', $qi+1);
                    if (strpos($qkey, $code . ':') !== 0 || isset($questions[$qkey])) { throw new InvalidArgumentException('Invalid or duplicate question identity: ' . $qkey); }
                    $questions[$qkey] = array('lesson_key' => $key, 'question' => $q['question']);
                }
            }
        }
        return array('lessons' => $lessons, 'questions' => $questions, 'sections' => $sections);
    }
    public function reviews() {
        $file = self::data_dir() . 'reviews.json';
        return is_file($file) ? self::json_file($file) : array('version' => 1, 'sources' => array());
    }
    public function audit_sources($code = null) {
        $issues = array(); $files = 0; $pages = 0; $reviewed = 0; $reviews = $this->reviews();
        $manifest = $this->manifest(); $by_sha = array_column($manifest['sources'], null, 'sha256');
        $keys = array();
        foreach ($this->course_files() as $c => $file) { if (!$code || $c === $code) { $keys[$c] = $this->keys(self::json_file($file)); } }
        if ($code && !isset($keys[$code])) { throw new InvalidArgumentException('Course is outside the PDF manifest.'); }
        foreach ($manifest['sources'] as $s) {
            if ($code && $s['course_code'] !== $code) { continue; }
            $files++; $pages += $s['page_count'];
            $path = FCPATH . $s['path'];
            if (!is_file($path) || hash_file('sha256', $path) !== $s['sha256']) { $issues[] = array('source' => $s['filename'], 'problem' => 'Source checksum mismatch or file missing'); continue; }
            $canonical = !empty($s['duplicate_of']) ? $s['duplicate_of'] : $s['sha256'];
            if (!isset($by_sha[$canonical]) || $by_sha[$canonical]['visual_sha256'] !== $s['visual_sha256']) {
                $issues[] = array('source' => $s['filename'], 'problem' => 'Duplicate reference is invalid'); continue;
            }
            $r = isset($reviews['sources'][$canonical]) ? $reviews['sources'][$canonical] : array();
            $lk = $keys[$s['course_code']];
            foreach ($s['pages'] as $p) {
                $rp = isset($r['pages'][(string) $p['page']]) ? $r['pages'][(string) $p['page']] : array();
                $errors = $this->page_errors($p, $rp, $lk);
                if ($errors) { foreach ($errors as $error) { $issues[] = array('source' => $s['filename'], 'page' => $p['page'], 'problem' => $error); } }
                else { $reviewed++; }
            }
        }
        return array('files' => $files, 'pages' => $pages, 'reviewed_pages' => $reviewed, 'issues' => $issues, 'ready' => $files > 0 && !$issues);
    }
    private function page_errors(array $page, array $review, array $keys) {
        $errors = array();
        if (empty($review['reviewer']) || empty($review['visually_checked']) || empty($review['corrected_text']) || empty($review['source_locale'])) { return array('Visual review, source language and corrected source text are required'); }
        if (!isset($review['has_instructional_content']) || !is_bool($review['has_instructional_content'])) { $errors[] = 'Explicit instructional-content decision required, including covers and closing pages'; }
        if ($page['kind'] !== 'instruction') {
            if (empty($review['accounting_note'])) { $errors[] = 'Cover or closing page needs an accounting note'; }
        }
        if (($page['kind'] === 'instruction' || !empty($review['has_instructional_content'])) && empty($review['points'])) { $errors[] = 'Instructional page has no reviewed points'; }
        foreach ((array) (isset($review['points']) ? $review['points'] : array()) as $i => $point) {
            if (empty($point['id'])) { $errors[] = 'Point ' . ($i+1) . ' needs a stable point identity'; }
            if (empty($point['text']) || empty($point['lesson_keys']) || empty($point['question_keys'])) { $errors[] = 'Point ' . ($i+1) . ' needs text, lesson and quiz references'; continue; }
            foreach ($point['lesson_keys'] as $k) { if (!isset($keys['lessons'][$k])) { $errors[] = 'Unknown lesson reference: ' . $k; } }
            foreach ($point['question_keys'] as $k) {
                if (!isset($keys['questions'][$k]) || !in_array($keys['questions'][$k]['lesson_key'], $point['lesson_keys'], true)) { $errors[] = 'Unknown or unrelated question reference: ' . $k; }
            }
        }
        return $errors;
    }
    public function export_reviews() {
        $existing = $this->reviews(); $out = array('version' => 1, 'sources' => array(), 'course_keys' => array(), 'curriculum' => isset($existing['curriculum']) ? $existing['curriculum'] : array());
        $ocr_file = self::data_dir() . 'ocr_drafts.json'; $ocr = is_file($ocr_file) ? self::json_file($ocr_file) : array('sources'=>array());
        foreach ($this->course_files() as $code => $path) {
            $course = self::json_file($path); $out['course_keys'][$code] = $this->keys($course);
            if (!isset($out['curriculum'][$code])) { $out['curriculum'][$code] = array('signature'=>self::signature($course), 'reviewer'=>'', 'lessons'=>array()); }
            foreach ($out['course_keys'][$code]['lessons'] as $key => $lesson) {
                if (!isset($out['curriculum'][$code]['lessons'][$key])) { $out['curriculum'][$code]['lessons'][$key] = array('objectives_reviewed'=>false,'explanations_procedures_examples_summary_reviewed'=>false,'quiz_reviewed'=>false,'teaching_examples_labelled'=>false); }
                if (!isset($out['curriculum'][$code]['lessons'][$key]['objective_questions'])) { $out['curriculum'][$code]['lessons'][$key]['objective_questions']=array(); }
            }
        }
        foreach ($this->manifest()['sources'] as $s) {
            if ($s['duplicate_of']) { continue; }
            $item = array('filename' => $s['filename'], 'sha256' => $s['sha256'], 'pages' => array());
            foreach ($s['pages'] as $p) {
                $item['pages'][(string) $p['page']] = array('extracted_text' => $p['extracted_text'], 'kind' => $p['kind'],
                    'ocr_draft' => isset($ocr['sources'][$s['sha256']]['pages'][(string)$p['page']]['ocr_text']) ? $ocr['sources'][$s['sha256']]['pages'][(string)$p['page']]['ocr_text'] : '',
                    'corrected_text' => '', 'source_locale' => '', 'reviewer' => '', 'visually_checked' => false, 'has_instructional_content' => null, 'accounting_note' => '', 'points' => array());
            }
            $out['sources'][$s['sha256']] = isset($existing['sources'][$s['sha256']]) ? $existing['sources'][$s['sha256']] : $item;
        }
        return $out;
    }
    public function import_reviews(array $input) {
        $manifest = $this->manifest(); $sources = array_column($manifest['sources'], null, 'sha256'); $out = $this->reviews();
        if (!isset($input['version']) || $input['version'] !== 1 || empty($input['sources'])) { throw new InvalidArgumentException('Invalid source review package.'); }
        foreach ($input['sources'] as $sha => $r) {
            if (!isset($sources[$sha]) || $sources[$sha]['duplicate_of']) { throw new InvalidArgumentException('Unknown source or duplicate must use canonical source: ' . $sha); }
            $s = $sources[$sha];
            if (!is_file(FCPATH . $s['path']) || hash_file('sha256', FCPATH . $s['path']) !== $sha) { throw new InvalidArgumentException('Source changed: ' . $s['filename']); }
            $keys = $this->keys($this->course($s['course_code']));
            foreach ($r['pages'] as $page => $rp) {
                if (!ctype_digit((string) $page) || !isset($s['pages'][(int) $page-1])) { throw new InvalidArgumentException('Unknown source page.'); }
                // Partial packages are allowed, but incomplete pages cannot pass.
                if (!empty($rp['visually_checked']) && $this->page_errors($s['pages'][(int) $page-1], $rp, $keys)) { throw new InvalidArgumentException('Invalid reviewed page: ' . $s['filename'] . ' page ' . $page); }
            }
            $previous = isset($out['sources'][$sha]) ? $out['sources'][$sha] : array('pages' => array());
            $r['pages'] = array_replace($previous['pages'], $r['pages']); $out['sources'][$sha] = $r;
        }
        foreach (isset($input['curriculum']) ? $input['curriculum'] : array() as $code => $r) {
            $c = $this->course($code);
            if (empty($r['signature']) || $r['signature'] !== self::signature($c)) { throw new InvalidArgumentException('Curriculum review is stale: ' . $code); }
            $keys = $this->keys($c);
            foreach ($r['lessons'] as $key => $unused) { if (!isset($keys['lessons'][$key])) { throw new InvalidArgumentException('Unknown reviewed lesson: ' . $key); } }
            $out['curriculum'][$code] = $r;
        }
        self::write_json(self::data_dir() . 'reviews.json', $out);
        return count($input['sources']);
    }
    public function language($locale) {
        if (!preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $locale)) { throw new InvalidArgumentException('Invalid language code.'); }
        $row = $this->db->get_where('ha_language_inventory', array('locale' => $locale))->row_array();
        if (!$row) { throw new InvalidArgumentException('Language is absent from the global inventory: ' . $locale); }
        return $row;
    }
    public function released_locales($course_id) {
        $active=$this->active_signature($course_id);
        $this->db->where('course_id',(int)$course_id);
        if ($active) { $this->db->where('signature',$active); }
        return array_column($this->db->select('locale')->get('ha_course_locale_release')->result_array(),'locale');
    }
    private function active_signature($course_id) {
        return $this->db->table_exists('ha_library_active_revision') ? $this->db->select('signature')->get_where('ha_library_active_revision',array('course_id'=>(int)$course_id))->row('signature') : null;
    }
    public function translation_current($course_id,$locale) {
        // Keep the existing installation available until its first reviewed revision.
        return !$this->active_signature($course_id) || in_array($locale,$this->released_locales($course_id),true);
    }
    public function ready($code, $locale) {
        $c = $this->course($code); $language = $this->language($locale); $issues = $this->audit_sources($code)['issues'];
        $keys = $this->keys($c); $review = $this->reviews(); $mapped_lessons = array(); $mapped_questions = array();
        foreach ($this->manifest()['sources'] as $s) {
            if ($s['course_code'] !== $code || $s['duplicate_of']) { continue; }
            if (isset($review['sources'][$s['sha256']]['pages'])) {
                foreach ($review['sources'][$s['sha256']]['pages'] as $page) { foreach ((array) $page['points'] as $p) {
                    foreach ((array) $p['lesson_keys'] as $k) { $mapped_lessons[$k] = true; }
                    foreach ((array) $p['question_keys'] as $k) { $mapped_questions[$k] = true; }
                } }
            }
        }
        foreach ($keys['lessons'] as $k => $l) { if (!isset($mapped_lessons[$k])) { $issues[] = array('lesson' => $k, 'problem' => 'No reviewed source point maps to this lesson'); } }
        $curriculum = isset($review['curriculum'][$code]) ? $review['curriculum'][$code] : array();
        foreach ($keys['lessons'] as $k => $l) {
            $cr = isset($curriculum['lessons'][$k]) ? $curriculum['lessons'][$k] : array();
            if (empty($curriculum['reviewer']) || empty($curriculum['signature']) || $curriculum['signature'] !== self::signature($c)
                || empty($cr['objectives_reviewed']) || empty($cr['explanations_procedures_examples_summary_reviewed']) || empty($cr['quiz_reviewed']) || empty($cr['teaching_examples_labelled'])) {
                $issues[] = array('lesson'=>$k,'problem'=>'Current lesson objectives, teaching content, examples and PDF-grounded quiz need editorial review');
            }
        }
        foreach ($keys['questions'] as $k => $q) { if (!isset($mapped_questions[$k])) { $issues[] = array('question' => $k, 'problem' => 'Quiz question has no reviewed source reference'); } }
        $this->CI->load->library('ha_global_translation');
        if (!$this->site_collected) { $this->CI->ha_global_translation->collect_site(); $this->site_collected = true; }
        $this->CI->ha_global_translation->collect_course($c);
        $tr = $this->CI->ha_global_translation->coverage($locale, 'course:' . $code);
        foreach ($tr['missing'] as $u) { $issues[] = array('unit' => $u['locator'], 'problem' => $u['problem']); }
        if (!$tr['total']) { $issues[] = array('problem' => 'Translation units have not been collected'); }
        $site = $this->CI->ha_global_translation->coverage($locale, 'site');
        if (!$site['ready']) { $issues[] = array('problem' => 'Shared interface and page translations are incomplete', 'missing_units' => count($site['missing'])); }
        if (!$this->CI->ha_global_translation->qa_ready($locale,'site')) { $issues[] = array('problem'=>'Current interface QA required: desktop, mobile, fonts, direction, plurals and dynamic text'); }
        if (!$this->CI->ha_global_translation->qa_ready($locale,'course:'.$code)) { $issues[] = array('problem'=>'Current course-language QA review required, including signed assessment flow when applicable'); }
        $course = $this->db->get_where('ha_course', array('code' => $code))->row_array();
        if ($course) {
            foreach ($this->db->select('at.id')->from('ha_assessment_attempt at')->join('ha_assessment a','a.id=at.assessment_id')
                ->where(array('a.course_id'=>$course['id'],'at.status'=>'in_progress'))->get()->result_array() as $attempt) {
                $issues[] = array('attempt'=>$attempt['id'],'problem'=>'Wait for the active assessment attempt to close before replacing its content');
            }
        }
        foreach ($keys['lessons'] as $k => $l) {
            $lesson_id = $this->identity('lesson', $k, $code);
            if (!$lesson_id && $course) {
                $lesson_id = (int) $this->db->select('id')->get_where('ha_lesson', array('course_id' => $course['id'], 'sort_order' => $l['position']))->row('id');
            }
            $videos = $lesson_id ? $this->db->get_where('ha_lesson_video_source', array('lesson_id' => $lesson_id, 'status' => 'live', 'embeddable' => 1))->result_array() : array();
            $usable = false;
            foreach ($videos as $v) {
                $evidence = json_decode((string) $v['verification_json'], true);
                $this->CI->load->library('ha_library_video');
                if (!Ha_library_video::media_unchanged($v)) { continue; }
                if ($v['reviewer'] && $v['relevance_reason'] && (int) $v['editorial_score'] >= 70 && (int) $v['duration_seconds'] > 0
                    && $v['author_name'] && $evidence && $v['last_checked_at'] && strtotime($v['last_checked_at']) >= time()-30*86400
                    && ($language['modality'] !== 'signed' || $v['locale'] === $locale)) { $usable = true; break; }
            }
            if (!$usable) { $issues[] = array('lesson' => $k, 'problem' => $language['modality'] === 'signed' ? 'Reviewed signed lesson video required' : 'Reviewed, recently verified lesson video required'); }
            $lesson = $c['locales']['en']['chapters'][$l['chapter']]['lessons'][$l['lesson']];
            if (empty($lesson['objectives']) || !is_array($lesson['objectives'])) { $issues[]=array('lesson'=>$k,'problem'=>'Explicit lesson learning objectives are missing from the candidate'); }
            foreach (isset($lesson['objectives']) && is_array($lesson['objectives']) ? $lesson['objectives'] : array() as $index=>$objective) {
                $mapped=isset($curriculum['lessons'][$k]['objective_questions'][$index]) ? $curriculum['lessons'][$k]['objective_questions'][$index] : array();
                if (!is_string($objective) || trim($objective)==='' || !$mapped) { $issues[]=array('lesson'=>$k,'objective'=>$index,'problem'=>'Objective needs text and reviewed assessment references'); }
                foreach ((array)$mapped as $question_key) { if (!isset($keys['questions'][$question_key]) || $keys['questions'][$question_key]['lesson_key']!==$k) { $issues[]=array('lesson'=>$k,'objective'=>$index,'question'=>$question_key,'problem'=>'Objective assessment reference is unknown or belongs to another lesson'); } }
            }
            if (count($lesson['quiz']['questions']) < 4) { $issues[] = array('lesson' => $k, 'problem' => 'At least four quiz questions required'); }
            foreach ($lesson['quiz']['questions'] as $q) {
                if (!isset($q['answer']) || !is_int($q['answer']) || !isset($q['options'][$q['answer']]) || empty($q['explanation']) || count($q['options']) < 2) { $issues[] = array('lesson' => $k, 'problem' => 'Invalid quiz answer, options, or explanation'); }
            }
        }
        return array('course' => $code, 'locale' => $locale, 'signature' => self::signature($c), 'ready' => !$issues, 'issues' => $issues);
    }
    public function attach_sources($code, $course_id) {
        $lessons = $this->db->select('id')->get_where('ha_lesson', array('course_id' => $course_id))->result_array();
        foreach ($this->manifest()['sources'] as $s) {
            if ($s['course_code'] !== $code) { continue; }
            $source = FCPATH . $s['path'];
            if (!is_file($source) || hash_file('sha256', $source) !== $s['sha256']) { throw new RuntimeException('Source checksum mismatch: ' . $s['filename']); }
            $relative = 'uploads/academy/library/sources/' . $s['sha256'] . '.pdf'; $dest = FCPATH . $relative;
            if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0775, true); }
            if (is_file($dest) && hash_file('sha256',$dest)!==$s['sha256']) { throw new RuntimeException('Attached source PDF checksum mismatch: '.$s['filename']); }
            if (!is_file($dest) && !copy($source, $dest)) { throw new RuntimeException('Cannot attach source PDF.'); }
            foreach ($lessons as $l) {
                $match = array('lesson_id' => $l['id'], 'file_path' => $relative);
                if (!$this->db->get_where('ha_lesson_attachment', $match)->num_rows()) {
                    $this->db->insert('ha_lesson_attachment', $match + array('title_en' => $s['filename'], 'title_ar' => $s['filename'],
                        'mime_type' => 'application/pdf', 'file_size' => filesize($source), 'created_at' => date('Y-m-d H:i:s')));
                }
            }
        }
    }
    public function publish($code, $locale) {
        $report = $this->ready($code, $locale);
        if (!$report['ready']) { throw new RuntimeException('Publication blocked: ' . json_encode($report, JSON_UNESCAPED_UNICODE)); }
        $c = $this->course($code);
        $this->CI->load->library('ha_global_translation');
        $c['locales'][$locale] = $this->CI->ha_global_translation->course_translation($code, $locale, $c['locales']['en']);
        $c=$this->annotate_sources($c,$locale);
        $this->db->trans_begin();
        try {
            require_once APPPATH . 'seeds/009_library.php';
            $seed = new Seed_library(); $seed->import_reviewed($this->db, $c, $locale);
            $course_id = (int) $this->db->select('id')->get_where('ha_course', array('code' => $code))->row('id');
            $this->db->replace('ha_library_active_revision',array('course_id'=>$course_id,'signature'=>$report['signature'],'released_at'=>date('Y-m-d H:i:s')));
            $match = array('course_id' => $course_id, 'locale' => $locale);
            $row = $this->db->get_where('ha_course_locale_release', $match)->row_array();
            $values = array('signature' => $report['signature'], 'status' => 'released', 'released_at' => date('Y-m-d H:i:s'));
            if ($row) { $this->db->where($match)->update('ha_course_locale_release', $values); }
            else { $this->db->insert('ha_course_locale_release', $match + $values); }
            $this->db->where(array('course_code' => $code, 'signature' => $report['signature']))->update('ha_library_revision', array('status' => 'released', 'released_at' => date('Y-m-d H:i:s')));
            $this->attach_sources($code, $course_id);
            $relative = 'uploads/academy/library/companions/' . $code . '/' . $locale . '-' . $report['signature'] . '.html';
            if (!is_dir(dirname(FCPATH . $relative))) { mkdir(dirname(FCPATH . $relative),0775,true); }
            $html = $this->CI->ha_global_translation->companion($code,$locale);
            if (file_put_contents(FCPATH . $relative,$html,LOCK_EX) === false) { throw new RuntimeException('Cannot save the translated source companion.'); }
            foreach ($this->db->select('id')->get_where('ha_lesson',array('course_id'=>$course_id,'status'=>'published'))->result_array() as $lesson) {
                $match = array('lesson_id'=>$lesson['id'],'file_path'=>$relative);
                if (!$this->db->get_where('ha_lesson_attachment',$match)->num_rows()) {
                    $this->db->insert('ha_lesson_attachment',$match + array('title_en'=>$c['locales'][$locale]['title'] . ' (' . $locale . ')','title_ar'=>$c['locales'][$locale]['title'] . ' (' . $locale . ')','mime_type'=>'text/html','file_size'=>strlen($html),'created_at'=>date('Y-m-d H:i:s')));
                }
            }
            if (!$this->db->trans_status()) { throw new RuntimeException('Publication transaction failed.'); }
            $this->db->trans_commit();
        } catch (Throwable $e) { $this->db->trans_rollback(); throw $e; }
        return $report;
    }
    private function annotate_sources(array $course,$locale) {
        $code='dy-'.$course['slug']; $keys=$this->keys($course); $reviews=$this->reviews(); $references=array();
        foreach ($this->manifest()['sources'] as $source) {
            if ($source['course_code']!==$code || $source['duplicate_of']) { continue; }
            foreach ($reviews['sources'][$source['sha256']]['pages'] as $page=>$review) {
                $ref=array('filename'=>$source['filename'],'page'=>(int)$page,'path'=>'uploads/academy/library/sources/'.$source['sha256'].'.pdf');
                foreach ($review['points'] as $point) { foreach (array_merge($point['lesson_keys'],$point['question_keys']) as $key) { $references[$key][$source['sha256'].':'.$page]=$ref; } }
            }
        }
        foreach (array_unique(array('en',$locale)) as $language) {
            foreach ($keys['lessons'] as $key=>$position) {
                $lesson =& $course['locales'][$language]['chapters'][$position['chapter']]['lessons'][$position['lesson']];
                $items=array();
                foreach ($references[$key] as $ref) { $label=$ref['filename'].' · '.$ref['page']; $items[]='<li><a href="'.htmlspecialchars(base_url($ref['path']).'#page='.$ref['page'],ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</a></li>'; }
                $lesson['body'].='<aside class="bk-source-reference" aria-label="PDF"><ul>'.implode('',$items).'</ul></aside>';
                foreach ($lesson['quiz']['questions'] as $i=>&$question) {
                    $qkey=$course['locales']['en']['chapters'][$position['chapter']]['lessons'][$position['lesson']]['quiz']['questions'][$i]['source_key'];
                    $labels=array(); foreach ($references[$qkey] as $ref) { $labels[]=$ref['filename'].' · '.$ref['page']; }
                    $question['explanation'].=' ['.implode('; ',$labels).']';
                }
                unset($question,$lesson);
            }
        }
        return $course;
    }
}
