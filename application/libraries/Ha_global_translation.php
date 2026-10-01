<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Reviewed, source-versioned translations; never stores fallback as a translation. */
class Ha_global_translation {
    private $CI;
    private $db;
    private $unit_cache = array();
    public function __construct() {
        $this->CI =& get_instance(); $this->CI->load->database(); $this->db = $this->CI->db;
        $this->CI->load->library('ha_library_review');
    }
    public static function unit_key($scope, $locator) { return hash('sha256', $scope . '|' . $locator); }
    public function unit($scope, $locator, $text, array $target = array()) {
        $key = self::unit_key($scope, $locator);
        $row = isset($this->unit_cache[$key]) ? $this->unit_cache[$key] : $this->db->get_where('ha_translation_unit', array('unit_key' => $key))->row_array();
        $data = array('scope' => $scope, 'locator' => $locator, 'source_text' => $text, 'source_hash' => hash('sha256', $text),
            'target_json' => json_encode($target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'active' => 1, 'updated_at' => date('Y-m-d H:i:s'));
        if ($row) {
            if ($row['source_hash'] !== $data['source_hash'] || $row['target_json'] !== $data['target_json'] || !(int)$row['active']) { $this->db->where('id', $row['id'])->update('ha_translation_unit', $data); }
            $id = (int)$row['id'];
        } else { $this->db->insert('ha_translation_unit', array('unit_key' => $key) + $data); $id = (int)$this->db->insert_id(); }
        $this->unit_cache[$key] = $data + array('id'=>$id,'unit_key'=>$key); return $id;
    }
    private function reset_scope($scope) {
        $this->db->where('scope',$scope)->update('ha_translation_unit',array('active'=>0));
        foreach ($this->db->get_where('ha_translation_unit',array('scope'=>$scope))->result_array() as $row) { $this->unit_cache[$row['unit_key']] = $row; }
    }
    private function leaves($value, $path = array()) {
        $out = array();
        if (is_string($value) && trim($value) !== '') { $out[] = array('path' => $path, 'value' => $value); }
        elseif (is_array($value)) {
            foreach ($value as $key => $item) {
                if (in_array((string) $key, array('source_key','source_pages','source_points','video','videos','answer','minutes'), true)) { continue; }
                $out = array_merge($out, $this->leaves($item, array_merge($path, array($key))));
            }
        }
        return $out;
    }
    private function course_locator(array $base, array $path) {
        $parts = $path;
        if (isset($path[0],$path[1]) && $path[0] === 'chapters') {
            $ch = $base['chapters'][$path[1]]; $parts[1] = $ch['source_key'];
            if (isset($path[2],$path[3]) && $path[2] === 'lessons') {
                $lesson = $ch['lessons'][$path[3]]; $parts[3] = $lesson['source_key'];
                if (isset($path[4],$path[5],$path[6]) && $path[4] === 'quiz' && $path[5] === 'questions') { $parts[6] = $lesson['quiz']['questions'][$path[6]]['source_key']; }
            }
        }
        return implode('/', $parts);
    }
    public function collect_course(array $c) {
        $code = 'dy-' . $c['slug']; $scope = 'course:' . $code; $n = 0;
        $this->db->trans_start();
        $this->reset_scope($scope);
        foreach ($this->leaves($c['locales']['en']) as $leaf) {
            $pointer = $this->course_locator($c['locales']['en'], $leaf['path']);
            $id = $this->unit($scope, 'library/' . $pointer, $leaf['value'], array('type' => 'library', 'code' => $code, 'path' => $leaf['path']));
            // Existing Arabic is useful review input, never an automatic approval.
            $ar = isset($c['locales']['ar']) ? $c['locales']['ar'] : array();
            foreach ($leaf['path'] as $p) { $ar = is_array($ar) && array_key_exists($p, $ar) ? $ar[$p] : null; }
            if (is_string($ar) && !$this->db->get_where('ha_translation_value', array('unit_id' => $id, 'locale' => 'ar'))->num_rows()) {
                $this->db->insert('ha_translation_value', array('unit_id' => $id, 'locale' => 'ar', 'value' => $ar,
                    'source_hash' => hash('sha256', $leaf['value']), 'source' => 'import', 'status' => 'reviewing', 'updated_at' => date('Y-m-d H:i:s')));
            }
            $n++;
        }
        $reviews = $this->CI->ha_library_review->reviews();
        foreach ($this->CI->ha_library_review->manifest()['sources'] as $source) {
            if ($source['course_code'] !== $code || $source['duplicate_of'] || !isset($reviews['sources'][$source['sha256']]['pages'])) { continue; }
            foreach ($reviews['sources'][$source['sha256']]['pages'] as $page => $r) {
                if (empty($r['visually_checked']) || empty($r['corrected_text'])) { continue; }
                $this->unit($scope, 'source/' . $source['sha256'] . '/' . $page, $r['corrected_text'],
                    array('type' => 'source', 'filename' => $source['filename'], 'page' => (int) $page, 'source_locale' => isset($r['source_locale']) ? $r['source_locale'] : 'en'));
                $n++;
            }
        }
        $this->db->trans_complete();
        if (!$this->db->trans_status()) { throw new RuntimeException('Course translation collection failed; previous registry retained.'); }
        return $n;
    }
    private function dictionary_units($domain, array $dictionary) {
        $n = 0;
        foreach ($dictionary as $key => $text) {
            if (!is_string($key) || $key === '') { continue; }
            $this->unit('site', 'ui:' . $domain . ':' . $key, $key, array('type' => 'ui', 'domain' => $domain, 'key' => $key)); $n++;
        }
        return $n;
    }
    public function collect_site() {
        $n = 0;
        $this->db->trans_start();
        $this->reset_scope('site');
        $files = array_merge(glob(APPPATH . 'views/*/*.php'), glob(APPPATH . 'views/*/*/*.php'), glob(APPPATH . 'controllers/*.php'),
            glob(APPPATH . 'helpers/*.php'), glob(APPPATH . 'libraries/Ha_*.php'), glob(APPPATH . 'core/*.php'));
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APPPATH . 'views', FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $f) { if ($f->isFile() && strtolower($f->getExtension()) === 'php') { $files[] = $f->getPathname(); } }
        $files = array_values(array_unique($files));
        foreach (array('academy','hkp','playing-page','lessons','backend/js/pages') as $directory) {
            $root=FCPATH.'assets/'.$directory; if (!is_dir($root)) { continue; }
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) { if ($file->isFile() && strtolower($file->getExtension())==='js') { $files[]=$file->getPathname(); } }
        }
        foreach ($files as $file) {
            $source = file_get_contents($file);
            foreach (array('site' => 'ha_p[te]', 'hkp' => 'hkp_[te]', 'legacy' => '(?:get_phrase|site_phrase|api_phrase)') as $domain => $fn) {
                if (preg_match_all('/\b' . $fn . '\(\s*([\'\"])((?:\\\\.|(?!\1).)*)\1/s', $source, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $text = $match[1] === "'" ? str_replace(array("\\'", "\\\\"), array("'", "\\"), $match[2]) : stripcslashes($match[2]);
                        if ($text === '' || strpos($text, '$') !== false) { continue; }
                        $key = $domain === 'legacy' ? strtolower(preg_replace('/\s+/', '_', $text)) : $text;
                        $value = $domain === 'legacy' ? ucfirst(str_replace('_', ' ', $key)) : $text;
                        $this->unit('site', 'ui:' . $domain . ':' . $key, $value, array('type' => 'ui', 'domain' => $domain, 'key' => $key)); $n++;
                    }
                }
            }
            if (preg_match_all('/(?:alert|confirm|setCustomValidity|\.text)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', $source, $js, PREG_SET_ORDER)) {
                foreach ($js as $match) {
                    $text = stripcslashes($match[2]);
                    if ($text !== '' && strpos($text, '$') === false) { $this->unit('site', 'ui:literal:' . $text, $text, array('type' => 'ui','domain' => 'literal','key' => $text)); $n++; }
                }
            }
            if (strtolower(pathinfo($file,PATHINFO_EXTENSION))==='js') {
                // Explicit plugin labels and feedback, never selectors or code identifiers.
                preg_match_all('/\b(?:placeholder|emptyTable|zeroRecords|loadingRecords|processing|infoEmpty|message|defaultText)\s*:\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s',$source,$labels,PREG_SET_ORDER);
                foreach ($labels as $match) {
                    $text=stripcslashes($match[2]);
                    if (trim($text)!=='' && preg_match('/\p{L}/u',$text)) { $this->unit('site','ui:literal:'.$text,$text,array('type'=>'ui','domain'=>'literal','key'=>$text)); $n++; }
                }
                continue;
            }
            // Static HTML text and user-facing attributes outside PHP/JS/CSS.
            // A server output hook translates these exact strings after render.
            $html = preg_replace('~<\?(?:php|=).*?\?>|<(script|style)\b[^>]*>.*?</\1>~is', '', $source);
            preg_match_all('~>\s*([^<>]+?)\s*<|(?:title|alt|placeholder|aria-label)\s*=\s*[\'"]([^\'"]+)[\'"]~u', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $text = html_entity_decode(trim(!empty($m[1]) ? $m[1] : (isset($m[2]) ? $m[2] : '')), ENT_QUOTES, 'UTF-8');
                if ($text === '' || !preg_match('/\p{L}/u', $text) || strpos($text, '$') !== false || strpos($text, '<?') !== false || strlen($text) > 10000) { continue; }
                $this->unit('site', 'ui:literal:' . $text, $text, array('type' => 'ui', 'domain' => 'literal', 'key' => $text)); $n++;
            }
        }
        foreach (array('site','hkp','legacy') as $domain) {
            foreach (glob(APPPATH . 'language/' . $domain . '/*.php') as $file) {
                $dictionary = include $file;
                if (is_array($dictionary)) { $n += $this->dictionary_units($domain, $dictionary); }
            }
        }
        if ($this->db->table_exists('language')) {
            foreach ($this->db->get('language')->result_array() as $r) {
                if (!$r['phrase']) { continue; }
                $text = !empty($r['english']) ? $r['english'] : ucfirst(str_replace('_', ' ', $r['phrase']));
                $this->unit('site', 'ui:legacy:' . $r['phrase'], $text, array('type' => 'ui', 'domain' => 'legacy', 'key' => $r['phrase'])); $n++;
            }
        }
        require_once APPPATH . 'libraries/Ha_i18n_keys.php';
        foreach (Ha_i18n_keys::collect() as $text) { $this->unit('site', 'ui:hkp:' . $text, $text, array('type' => 'ui', 'domain' => 'hkp', 'key' => $text)); $n++; }
        $skip = array('ha_course_translation','ha_lesson_translation','ha_question_translation','ha_question_option_translation',
            'ha_translation_unit','ha_translation_value','ha_library_revision','ha_course_locale_release','ha_i18n_text');
        foreach ($this->db->list_tables() as $table) {
            if (strpos($table, 'ha_') !== 0 || in_array($table, $skip, true)) { continue; }
            // Learner records, profiles, notifications sent to individuals and
            // tenant-generated material are not shared website content.
            if (!preg_match('/^ha_(page|page_section|page_translation|menu_item|corporate_block|service|sector|case_study|leadership_profile|partner|faq|topic|topic_faq|category_translation|program_translation|article_translation|notification_template|domain|skill|competency|sop_category|learning_path|path_step|seo_metadata)$/D', $table)) { continue; }
            $fields = $this->db->list_fields($table);
            if (!in_array('id', $fields, true)) { continue; }
            if ($table === 'ha_notification_template') { $this->db->where('organization_id',0); }
            $rows = in_array('locale', $fields, true) ? $this->db->get_where($table, array('locale' => 'en'))->result_array() : $this->db->get($table)->result_array();
            foreach ($rows as $r) {
                foreach ($r as $field => $value) {
                    if (!is_string($value) || trim($value) === '') { continue; }
                    $paired = preg_match('/^(.+)_en$/D', $field, $m) && in_array($m[1] . '_ar', $fields, true);
                    $translated = in_array('locale', $fields, true) && in_array($field, array('title','name','subject','body','description','short_description','subtitle','requirements','summary','objective','transcript','meta_title','meta_description','focus_keyword','og_title','og_description','image_alt'), true);
                    if ($table === 'ha_seo_metadata' && $r['entity_type'] === 'course') { continue; }
                    if (!$paired && !$translated) { continue; }
                    $target = array('type' => $paired ? 'pair' : 'translation', 'table' => $table, 'id' => (int) $r['id'], 'field' => $field);
                    $decoded = in_array($field, array('content_en','body_en'), true) ? json_decode($value, true) : null;
                    if (is_array($decoded)) {
                        foreach ($this->leaves($decoded) as $leaf) {
                            $this->unit('site', $table . '/' . $r['id'] . '/' . $field . '/' . implode('/', $leaf['path']), $leaf['value'], $target + array('path' => $leaf['path'])); $n++;
                        }
                    } else { $this->unit('site', $table . '/' . $r['id'] . '/' . $field, $value, $target); $n++; }
                }
            }
        }
        $this->db->trans_complete();
        if (!$this->db->trans_status()) { throw new RuntimeException('Site translation collection failed; previous registry retained.'); }
        return $n;
    }
    public function collect($code = null) {
        $n = 0;
        if (!$code) { $n += $this->collect_site(); }
        foreach ($this->CI->ha_library_review->course_files() as $c => $file) {
            if ($code && $c !== $code) { continue; }
            $course = Ha_library_review::json_file($file); $this->CI->ha_library_review->stage($course); $n += $this->collect_course($course);
        }
        return $n;
    }
    public static function protected_tokens($text) {
        preg_match_all('~\{\{\s*[A-Za-z0-9_.]+\s*\}\}|\{[A-Za-z0-9_.]+\}|%(?:\d+\$)?[-+0-9.]*[sdf]|https?://[^\s<>"\']+|<[a-zA-Z/][^>]*>|`[^`]+`~u', $text, $m);
        $tokens = $m[0]; sort($tokens); return $tokens;
    }
    public static function validate_text($source, $value) {
        if (!is_string($value) || trim($value) === '') { throw new InvalidArgumentException('Translation is empty.'); }
        if (self::protected_tokens($source) !== self::protected_tokens($value)) { throw new InvalidArgumentException('Translation changed markup, URLs, code, or placeholders.'); }
        foreach ((array) include APPPATH . 'config/ha_translation_glossary.php' as $term) {
            if (substr_count($source, $term) !== substr_count($value, $term)) { throw new InvalidArgumentException('Protected glossary term changed: ' . $term); }
        }
    }
    public function export($locale, $scope = null) {
        $language = $this->CI->ha_library_review->language($locale);
        $query = $this->db->where('active', 1);
        if ($scope) { $query->where('scope', $scope); }
        $units = $query->order_by('scope, locator')->get('ha_translation_unit')->result_array(); $out = array();
        foreach ($units as $u) {
            $v = $this->db->get_where('ha_translation_value', array('unit_id' => $u['id'], 'locale' => $locale))->row_array();
            $target=json_decode($u['target_json'],true);
            $out[] = array('key' => $u['unit_key'], 'scope' => $u['scope'], 'locator' => $u['locator'], 'source' => $u['source_text'],
                'source_locale'=>isset($target['source_locale']) ? $target['source_locale'] : 'en',
                'source_hash' => $u['source_hash'], 'value' => $v ? $v['value'] : '', 'signed_media' => $v ? $v['signed_media'] : null,
                'status' => $v ? $v['status'] : 'reviewing', 'reviewer' => $v ? $v['reviewer'] : null);
        }
        return array('version' => 1, 'locale' => $locale, 'modality' => $language['modality'], 'units' => $out);
    }
    public function import(array $package, $source = 'import') {
        if (!isset($package['version']) || $package['version'] !== 1 || empty($package['locale']) || !isset($package['units']) || !is_array($package['units'])) { throw new InvalidArgumentException('Invalid translation package.'); }
        if (!in_array($source, array('import','human','ai'), true)) { throw new InvalidArgumentException('Invalid translation origin.'); }
        $locale = $package['locale']; $language = $this->CI->ha_library_review->language($locale); $validated = array(); $seen = array();
        foreach ($package['units'] as $item) {
            if (!isset($item['key']) || isset($seen[$item['key']])) { throw new InvalidArgumentException('Missing or duplicate translation key.'); }
            $seen[$item['key']] = true;
            $u = $this->db->get_where('ha_translation_unit', array('unit_key' => $item['key'], 'active' => 1))->row_array();
            if (!$u || !isset($item['source_hash']) || $item['source_hash'] !== $u['source_hash']) { throw new InvalidArgumentException('Unknown, retired, or stale unit: ' . $item['key']); }
            $status = isset($item['status']) ? $item['status'] : 'reviewing';
            if (!in_array($status, array('translating','reviewing','ready'), true)) { throw new InvalidArgumentException('Invalid review status.'); }
            if ($status === 'ready' && ($source === 'ai' || empty($item['reviewer']))) { throw new InvalidArgumentException('Human reviewer required for ready translations.'); }
            $signed = isset($item['signed_media']) ? $item['signed_media'] : null;
            if ($signed) {
                if (!preg_match('~^uploads/academy/library/signed/[A-Za-z0-9_./-]+\.(mp4|webm)$~D', $signed) || strpos($signed, '..') !== false || !is_file(FCPATH . $signed)) { throw new InvalidArgumentException('Signed media must be an existing local MP4/WebM under the signed library directory.'); }
            }
            if ($language['modality'] === 'signed' && $status === 'ready' && !$signed) { throw new InvalidArgumentException('Signed translation requires signed media for each unit.'); }
            $value = isset($item['value']) ? $item['value'] : '';
            if ($value !== '' || $language['modality'] !== 'signed') { self::validate_text($u['source_text'], $value); }
            $validated[] = array('unit_id' => $u['id'], 'locale' => $locale, 'value' => $value, 'signed_media' => $signed,
                'signed_media_sha256' => $signed ? hash_file('sha256', FCPATH . $signed) : null,
                'source_hash' => $u['source_hash'], 'status' => $status, 'source' => $source,
                'reviewer' => $status === 'ready' ? $item['reviewer'] : null, 'updated_at' => date('Y-m-d H:i:s'));
        }
        $this->db->trans_begin();
        try {
            foreach ($validated as $v) {
                $match = array('unit_id' => $v['unit_id'], 'locale' => $locale);
                $prior = $this->db->get_where('ha_translation_value', $match)->row_array();
                if ($source === 'ai' && $prior && $prior['source'] !== 'ai') { continue; }
                if ($prior) { $this->db->where($match)->update('ha_translation_value', $v); }
                else { $this->db->insert('ha_translation_value', $v); }
            }
            if (!$this->db->trans_status()) { throw new RuntimeException('Translation import failed.'); }
            $this->db->trans_commit();
        } catch (Throwable $e) { $this->db->trans_rollback(); throw $e; }
        $this->db->where('locale', $locale)->update('ha_language_inventory', array('status' => 'reviewing', 'updated_at' => date('Y-m-d H:i:s')));
        return count($validated);
    }
    public function coverage($locale, $scope = null) {
        $language = $this->CI->ha_library_review->language($locale);
        $query = $this->db->select('u.*,v.value,v.signed_media,v.signed_media_sha256,v.status AS review_status,v.source_hash AS reviewed_hash,v.reviewer')
            ->from('ha_translation_unit u')->join('ha_translation_value v', 'v.unit_id=u.id AND v.locale=' . $this->db->escape($locale), 'left')->where('u.active', 1);
        if ($scope) { $query->where('u.scope', $scope); }
        $rows = $query->get()->result_array(); $missing = array(); $ready = 0;
        foreach ($rows as $u) {
            $target = json_decode((string) $u['target_json'], true);
            $source_locale = isset($target['source_locale']) ? $target['source_locale'] : 'en';
            if ($locale === $source_locale && $language['modality'] === 'spoken') { $ready++; continue; }
            $problem = null;
            if ($u['reviewed_hash'] !== $u['source_hash']) { $problem = $u['reviewed_hash'] ? 'Source changed; translation is stale' : 'Translation missing'; }
            elseif ($u['review_status'] !== 'ready' || !$u['reviewer']) { $problem = 'Human review pending'; }
            elseif ($language['modality'] === 'signed' && (!$u['signed_media'] || !is_file(FCPATH . $u['signed_media']))) { $problem = 'Signed media missing'; }
            elseif ($language['modality'] === 'signed' && $u['signed_media_sha256'] !== hash_file('sha256', FCPATH . $u['signed_media'])) { $problem = 'Signed media changed; review required'; }
            elseif ($language['modality'] !== 'signed' && trim((string) $u['value']) === '') { $problem = 'Translation empty'; }
            else { try { if ($u['value'] !== '') { self::validate_text($u['source_text'], $u['value']); } } catch (Throwable $e) { $problem = $e->getMessage(); } }
            if ($problem) { $missing[] = array('key' => $u['unit_key'], 'scope' => $u['scope'], 'locator' => $u['locator'], 'problem' => $problem); }
            else { $ready++; }
        }
        return array('locale' => $locale, 'scope' => $scope, 'total' => count($rows), 'reviewed' => $ready, 'ready' => count($rows) > 0 && !$missing, 'missing' => $missing);
    }
    /** A review of the actual interface catches dynamic strings a static scan cannot prove. */
    public function qa_package($locale,$scope = 'site') {
        $language=$this->CI->ha_library_review->language($locale);
        $rows=$this->db->select('u.unit_key,u.source_hash,v.value,v.signed_media_sha256')->from('ha_translation_unit u')
            ->join('ha_translation_value v','v.unit_id=u.id AND v.locale='.$this->db->escape($locale),'left')
            ->where(array('u.active'=>1,'u.scope'=>$scope))->order_by('u.unit_key')->get()->result_array();
        $surfaces=array('review_schema'=>2,'language_definition'=>array_intersect_key($language,array_flip(array('locale','iso6393','modality','direction'))));
        if (strpos($scope,'course:')===0) { $surfaces['course_candidate']=Ha_library_review::signature($this->CI->ha_library_review->course(substr($scope,7))); }
        if ($scope==='site') {
            foreach (array('views','controllers','helpers','libraries','core') as $directory) {
                $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APPPATH.$directory,FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (!$file->isFile() || strtolower($file->getExtension())!=='php') { continue; }
                    $relative=str_replace('\\','/',substr($file->getPathname(),strlen(APPPATH)));
                    if ($directory==='libraries' && strpos($relative,'libraries/Ha_')!==0) { continue; }
                    $surfaces[$relative]=hash_file('sha256',$file->getPathname());
                }
            }
            foreach (array('academy','hkp','playing-page','lessons','backend') as $directory) {
                $root=FCPATH.'assets/'.$directory; if (!is_dir($root)) { continue; }
                $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) { if ($file->isFile() && in_array(strtolower($file->getExtension()),array('js','css'),true)) { $surfaces[str_replace('\\','/',substr($file->getPathname(),strlen(FCPATH)))]=hash_file('sha256',$file->getPathname()); } }
            }
        }
        ksort($surfaces);
        return array('version'=>1,'locale'=>$locale,'scope'=>$scope,'fingerprint'=>hash('sha256',json_encode(array($rows,$surfaces),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),
            'reviewer'=>'','checklist'=>array('language_definition'=>false,'desktop'=>false,'mobile'=>false,'direction'=>false,'fonts'=>false,'plurals'=>false,'dynamic_content'=>false,'signed_flow'=>false));
    }
    public function qa_import(array $input) {
        if (empty($input['locale']) || empty($input['scope'])) { throw new InvalidArgumentException('QA locale and scope required.'); }
        $expected=$this->qa_package($input['locale'],$input['scope']); $language=$this->CI->ha_library_review->language($input['locale']);
        if (empty($input['fingerprint']) || $input['fingerprint']!==$expected['fingerprint'] || empty($input['reviewer']) || !$this->coverage($input['locale'],$input['scope'])['ready']) { throw new InvalidArgumentException('QA needs current complete translations and a human reviewer.'); }
        foreach ($expected['checklist'] as $key=>$unused) {
            if ($key==='signed_flow' && $language['modality']!=='signed') { continue; }
            if (empty($input['checklist'][$key]) || $input['checklist'][$key] !== true) { throw new InvalidArgumentException('QA check pending: '.$key); }
        }
        $match=array('locale'=>$input['locale'],'scope'=>$input['scope']);
        $row=array('fingerprint'=>$expected['fingerprint'],'reviewer'=>$input['reviewer'],'checklist_json'=>json_encode($input['checklist']),'reviewed_at'=>date('Y-m-d H:i:s'));
        if ($this->db->get_where('ha_translation_qa',$match)->num_rows()) { $this->db->where($match)->update('ha_translation_qa',$row); }
        else { $this->db->insert('ha_translation_qa',$match+$row); }
        return $match;
    }
    public function qa_ready($locale,$scope='site') {
        $row=$this->db->get_where('ha_translation_qa',array('locale'=>$locale,'scope'=>$scope))->row_array();
        return $row && $row['reviewer'] && $row['fingerprint']===$this->qa_package($locale,$scope)['fingerprint'];
    }
    private function put_path(&$value, array $path, $text) {
        $current =& $value;
        foreach ($path as $key) { if (!is_array($current) || !array_key_exists($key, $current)) { throw new RuntimeException('Translation target path changed.'); } $current =& $current[$key]; }
        $current = $text;
    }
    public function course_translation($code, $locale, array $base) {
        $coverage = $this->coverage($locale, 'course:' . $code);
        if (!$coverage['ready']) { throw new RuntimeException('Course translation is incomplete.'); }
        foreach ($this->db->select('u.target_json,u.source_text,v.value')->from('ha_translation_unit u')
            ->join('ha_translation_value v', 'v.unit_id=u.id AND v.locale=' . $this->db->escape($locale), 'left')
            ->where(array('u.scope' => 'course:' . $code, 'u.active' => 1))->get()->result_array() as $row) {
            $target = json_decode($row['target_json'], true);
            if ($target['type'] === 'library') { $this->put_path($base, $target['path'], $row['value'] !== null && $row['value'] !== '' ? $row['value'] : $row['source_text']); }
        }
        return $base;
    }
    public function generate($locale, $scope = null, $limit = 20) {
        $language = $this->CI->ha_library_review->language($locale);
        if ($language['modality'] === 'signed') { throw new RuntimeException('Signed languages require human signed-media production.'); }
        $this->CI->load->library('ha_ai_gateway');
        $package = $this->export($locale, $scope); $selected = array();
        foreach ($package['units'] as $u) { if ($u['source_locale'] !== $locale && (!$u['value'] || !$u['reviewer']) && count($selected) < max(1, min(100, (int) $limit))) { $selected[] = $u; } }
        if (!$selected) { return 0; }
        $this->db->where('locale',$locale)->update('ha_language_inventory',array('status'=>'translating','updated_at'=>date('Y-m-d H:i:s')));
        $result = $this->CI->ha_ai_gateway->chat_json('translation',
            'Translate hospitality learning and software text accurately into ' . $language['name'] . ' (' . $locale . '). Return {"units":[{"key":"...","value":"..."}]}. Preserve all HTML tags, attributes, URLs, code, placeholders, and these names: ' . implode(', ', include APPPATH . 'config/ha_translation_glossary.php') . '. Do not add claims or change quiz meaning. Never claim human review.',
            json_encode(array('units' => array_map(function ($u) { return array('key' => $u['key'], 'source' => $u['source']); }, $selected)), JSON_UNESCAPED_UNICODE), array('max_tokens' => 12000, 'temperature' => 0.1));
        if (empty($result['data']['units'])) { throw new RuntimeException('Provider returned no translation units.'); }
        $by_key = array_column($selected, null, 'key'); $units = array();
        foreach ($result['data']['units'] as $item) {
            if (!isset($by_key[$item['key']])) { throw new RuntimeException('Provider returned an unexpected key.'); }
            $units[] = array_merge($by_key[$item['key']], array('value' => $item['value'], 'status' => 'reviewing', 'reviewer' => null));
        }
        return $this->import(array('version' => 1, 'locale' => $locale, 'units' => $units), 'ai');
    }
    public function materialize_site($locale) {
        $report = $this->coverage($locale, 'site');
        if (!$report['ready']) { throw new RuntimeException('Shared site translations are incomplete.'); }
        $rows = $this->db->select('u.target_json,u.source_text,v.value')->from('ha_translation_unit u')
            ->join('ha_translation_value v', 'v.unit_id=u.id AND v.locale=' . $this->db->escape($locale), 'left')
            ->where(array('u.scope' => 'site', 'u.active' => 1))->get()->result_array();
        $this->db->trans_begin();
        try {
            foreach ($rows as $r) {
                $t = json_decode($r['target_json'], true); $value = $r['value'] !== null && $r['value'] !== '' ? $r['value'] : $r['source_text'];
                if (!in_array($t['type'], array('pair','translation'), true)) { continue; }
                $base = $this->db->get_where($t['table'], array('id' => $t['id']))->row_array();
                if (!$base) { throw new RuntimeException('Content target was removed; recollect translations.'); }
                if ($t['type'] === 'translation') {
                    $match = array('locale' => $locale);
                    foreach ($base as $k => $v) { if ((substr($k, -3) === '_id' && $k !== 'id') || in_array($k,array('entity_type','route_key','event_code','channel'),true)) { $match[$k] = $v; } }
                    if (count($match) < 2) { throw new RuntimeException('Translation target has no entity reference.'); }
                    $existing = $this->db->get_where($t['table'], $match)->row_array();
                    if ($existing) { $this->db->where('id', $existing['id'])->update($t['table'], array($t['field'] => $value)); }
                    else { unset($base['id']); $base['locale'] = $locale; $base[$t['field']] = $value; $this->db->insert($t['table'], $base); }
                    continue;
                }
                $field = substr($t['field'], 0, -3); $dest_field = $field . '_' . $locale;
                $has_column = array_key_exists($dest_field, $base);
                if (isset($t['path'])) {
                    if ($locale === 'en' || $locale === 'ar') {
                        $current = json_decode((string) $base[$dest_field], true);
                        if (!is_array($current)) { $current = json_decode($base[$t['field']], true); }
                        $this->put_path($current, $t['path'], $value); $value = json_encode($current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    } else {
                        $i18n = !empty($base['content_i18n']) ? json_decode($base['content_i18n'], true) : array();
                        if (!is_array($i18n)) { $i18n = array(); }
                        $current = isset($i18n[$locale]) ? $i18n[$locale] : json_decode($base[$t['field']], true);
                        $this->put_path($current, $t['path'], $value); $i18n[$locale] = $current;
                        if (!array_key_exists('content_i18n', $base)) { throw new RuntimeException('JSON content target needs an i18n storage column.'); }
                        $this->db->where('id', $t['id'])->update($t['table'], array('content_i18n' => json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
                        continue;
                    }
                }
                if ($has_column) { $this->db->where('id', $t['id'])->update($t['table'], array($dest_field => $value)); }
                else {
                    $match = array('entity' => substr($t['table'], 3), 'entity_id' => $t['id'], 'field' => $field, 'locale' => $locale);
                    $values = array('value' => $value,'source' => 'human','updated_at' => date('Y-m-d H:i:s'));
                    if ($this->db->get_where('ha_i18n_text',$match)->num_rows()) { $this->db->where($match)->update('ha_i18n_text',$values); }
                    else { $this->db->insert('ha_i18n_text',$match+$values); }
                }
            }
            if (!$this->db->trans_status()) { throw new RuntimeException('Site translation materialization failed.'); }
            $this->db->trans_commit();
        } catch (Throwable $e) { $this->db->trans_rollback(); throw $e; }
    }
    public function companion($code, $locale) {
        $report = $this->coverage($locale, 'course:' . $code);
        if (!$report['ready']) { throw new RuntimeException('Cannot produce a complete companion from unfinished translations.'); }
        $c = $this->course_translation($code, $locale, $this->CI->ha_library_review->course($code)['locales']['en']);
        require_once APPPATH . 'helpers/ha_locale_helper.php';
        $out = '<!doctype html><html dir="' . ha_locale_dir($locale) . '" lang="' . htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') . '</title><style>body{font-family:system-ui,sans-serif;max-width:900px;margin:auto;padding:1rem;line-height:1.7}pre{white-space:pre-wrap;font-family:inherit}video{width:100%;max-width:640px}</style></head><body><h1>' . htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        foreach ($c['chapters'] as $ch) { $out .= '<h2>' . htmlspecialchars($ch['title'], ENT_QUOTES, 'UTF-8') . '</h2>'; foreach ($ch['lessons'] as $l) { $out .= '<h3>' . htmlspecialchars($l['title'], ENT_QUOTES, 'UTF-8') . '</h3>' . $l['body']; } }
        foreach ($this->db->select('u.target_json,u.source_text,v.value,v.signed_media')->from('ha_translation_unit u')->join('ha_translation_value v', 'v.unit_id=u.id AND v.locale=' . $this->db->escape($locale), 'left')
            ->where(array('u.scope' => 'course:' . $code, 'u.active' => 1))->get()->result_array() as $r) {
            $t = json_decode($r['target_json'], true);
            if ($t['type'] === 'source') {
                $out .= '<section><h2>' . htmlspecialchars($t['filename'], ENT_QUOTES, 'UTF-8') . ' · ' . $t['page'] . '</h2><pre>' . htmlspecialchars($r['value'] ?: $r['source_text'], ENT_QUOTES, 'UTF-8') . '</pre>';
                if ($r['signed_media']) { $out .= '<video controls preload="none" playsinline src="' . htmlspecialchars(base_url($r['signed_media']),ENT_QUOTES,'UTF-8') . '"></video>'; }
                $out .= '</section>';
            }
        }
        return $out . '</body></html>';
    }
}
