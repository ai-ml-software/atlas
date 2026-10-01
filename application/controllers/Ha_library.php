<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Explicit CLI operations; no network or publication side effects in audits. */
class Ha_library extends CI_Controller {
    public function __construct() {
        parent::__construct(); if (!is_cli()) { show_404(); }
        @set_time_limit(0); ini_set('memory_limit', '768M');
        $this->load->database(); $this->load->library(array('ha_library_review','ha_global_translation','ha_library_video'));
    }
    private function output($value) { fwrite(STDOUT, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL); }
    private function file_arg($file) {
        if (strpos($file,'b64:') === 0) { $decoded = base64_decode(strtr(substr($file,4),'-_','+/'),true); if ($decoded === false || strpos($decoded,"\0") !== false) { throw new InvalidArgumentException('Invalid encoded file path.'); } return $decoded; }
        return rawurldecode($file);
    }
    public function index() { $this->output(array('commands' => array('audit [code]','collect [code]','review_export file','review_import file','translation_export locale file [scope]',
        'translation_import file','translate locale [scope] [limit]','coverage locale [scope]','videos [code]','video_export file','video_import file','verify_videos [code]','readiness code locale','publish code locale','enable locale','disable locale','qa_export locale file [scope]','qa_import file','companion code locale file','report file [locale]','language_variant locale base direction name'))); }
    public function audit($code = null) { $this->output($this->ha_library_review->audit_sources($code)); }
    public function collect($code = null) { $this->output(array('collected' => $this->ha_global_translation->collect($code))); }
    public function review_export($file) { $file=$this->file_arg($file); Ha_library_review::write_json($file, $this->ha_library_review->export_reviews()); $this->output(array('exported' => $file)); }
    public function review_import($file) { $this->output(array('imported' => $this->ha_library_review->import_reviews(Ha_library_review::json_file($this->file_arg($file))))); }
    public function translation_export($locale, $file, $scope = null) { $file=$this->file_arg($file); Ha_library_review::write_json($file, $this->ha_global_translation->export($locale, $scope)); $this->output(array('exported' => $file)); }
    public function translation_import($file) { $this->output(array('imported' => $this->ha_global_translation->import(Ha_library_review::json_file($this->file_arg($file))))); }
    public function qa_export($locale,$file,$scope='site') { $file=$this->file_arg($file); Ha_library_review::write_json($file,$this->ha_global_translation->qa_package($locale,$scope)); $this->output(array('exported'=>$file)); }
    public function qa_import($file) { $this->output($this->ha_global_translation->qa_import(Ha_library_review::json_file($this->file_arg($file)))); }
    public function translate($locale, $scope = null, $limit = 20) { $this->output(array('generated_for_review' => $this->ha_global_translation->generate($locale, $scope, $limit))); }
    public function coverage($locale, $scope = null) { $this->output($this->ha_global_translation->coverage($locale, $scope)); }
    public function videos($code = null) { $this->output($this->ha_library_video->report($code)); }
    public function video_export($file) {
        $file=$this->file_arg($file); $videos=array(); $lessons=array();
        foreach ($this->ha_library_video->report() as $row) {
            $lessons[]=array('course_code'=>$row['course'],'lesson_key'=>$row['lesson_key'],'title'=>$row['title'],'selection_status'=>'pending');
            foreach ($row['sources'] as $source) {
                $videos[]=array('course_code'=>$row['course'],'lesson_key'=>$row['lesson_key'],'provider'=>$source['provider'],'video_id'=>$source['video_id'],
                    'locale'=>$source['locale'],'title'=>$source['title'],'author_name'=>$source['author_name'],'author_url'=>$source['author_url'],'duration_seconds'=>$source['duration_seconds'],
                    'relevance_reason'=>$source['relevance_reason'],'editorial_score'=>$source['editorial_score'],'reviewer'=>$source['reviewer'],
                    'sort_order'=>$source['sort_order'],'captions_path'=>$source['captions_path'],'captions_authorized'=>(bool)$source['captions_authorized']);
            }
        }
        Ha_library_review::write_json($file,array('version'=>1,'videos'=>$videos,'lesson_inventory'=>$lessons)); $this->output(array('exported'=>$file,'lessons'=>count($lessons),'candidates'=>count($videos)));
    }
    public function video_import($file) { $this->output(array('imported' => $this->ha_library_video->import(Ha_library_review::json_file($this->file_arg($file))))); }
    public function verify_videos($code = null) { $this->output($this->ha_library_video->verify_all($code)); }
    public function readiness($code, $locale) { $this->output($this->ha_library_review->ready($code, $locale)); }
    public function publish($code, $locale) {
        $r = $this->ha_library_review->ready($code, $locale); $this->output($r);
        if (!$r['ready']) { exit(1); }
        $this->output($this->ha_library_review->publish($code, $locale));
        // Mirror only this course, never the unrelated catalogue.
        $php = escapeshellarg(PHP_BINARY); $entry = escapeshellarg(FCPATH . 'index.php');
        passthru($php . ' ' . $entry . ' ha_bridge sync_one ' . escapeshellarg($code), $status);
        if ($status) { fwrite(STDERR, "Course released; LMS mirror needs retry using ha_bridge sync_one.\n"); exit($status); }
    }
    public function enable($locale) {
        $this->ha_global_translation->collect_site();
        $r = $this->ha_global_translation->coverage($locale, 'site'); $this->output($r);
        if (!$r['ready']) { exit(1); }
        if (!$this->ha_global_translation->qa_ready($locale,'site')) { fwrite(STDERR,"Language activation blocked: current desktop, mobile and dynamic-content QA review required.\n"); exit(1); }
        $this->ha_global_translation->materialize_site($locale);
        $path = Ha_library_review::data_dir() . 'enabled_languages.json';
        $enabled = is_file($path) ? Ha_library_review::json_file($path) : array('locales' => array());
        $enabled['locales'] = array_values(array_unique(array_merge($enabled['locales'], array($locale))));
        $enabled['disabled']=array_values(array_diff(isset($enabled['disabled']) ? $enabled['disabled'] : array(),array($locale)));
        $language=$this->ha_library_review->language($locale); $enabled['definitions'][$locale]=array('name'=>$language['name'],'direction'=>$language['direction']);
        Ha_library_review::write_json($path, $enabled);
        $this->db->where('locale', $locale)->update('ha_language_inventory', array('status' => 'ready', 'enabled' => 1, 'updated_at' => date('Y-m-d H:i:s')));
    }
    public function disable($locale) {
        require_once APPPATH.'helpers/ha_locale_helper.php';
        $this->ha_library_review->language($locale);
        if ($locale===ha_locale_default()) { throw new InvalidArgumentException('The default source language must remain available.'); }
        $path=Ha_library_review::data_dir().'enabled_languages.json';
        $data=is_file($path) ? Ha_library_review::json_file($path) : array('locales'=>array());
        $data['locales']=array_values(array_diff($data['locales'],array($locale)));
        $data['disabled']=array_values(array_unique(array_merge(isset($data['disabled']) ? $data['disabled'] : array(),array($locale))));
        Ha_library_review::write_json($path,$data);
        $this->db->where('locale',$locale)->update('ha_language_inventory',array('enabled'=>0,'updated_at'=>date('Y-m-d H:i:s')));
        $this->output(array('disabled'=>$locale,'history_retained'=>true));
    }
    public function companion($code, $locale, $file) {
        $file=$this->file_arg($file);
        if (file_put_contents($file, $this->ha_global_translation->companion($code, $locale)) === false) { throw new RuntimeException('Cannot write companion.'); }
        $this->output(array('exported' => $file));
    }
    public function language_variant($locale, $base, $direction, $name) {
        if (!preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})+$/D',$locale) || explode('-',$locale)[0] !== $base || !in_array($direction,array('ltr','rtl'),true) || trim($name)==='') { throw new InvalidArgumentException('A valid script/region variant of its base, direction and name are required.'); }
        $parent = $this->ha_library_review->language($base);
        if (!$this->db->get_where('ha_language_inventory',array('locale'=>$locale))->num_rows()) {
            $this->db->insert('ha_language_inventory',array('locale'=>$locale,'iso6393'=>$parent['iso6393'],'name'=>$name,'modality'=>$parent['modality'],'direction'=>$direction,'status'=>'pending','enabled'=>0,'source_version'=>$parent['source_version'],'updated_at'=>date('Y-m-d H:i:s')));
        }
        $this->output($this->ha_library_review->language($locale));
    }
    public function report($file, $locale = 'en') {
        $file=$this->file_arg($file);
        $this->ha_global_translation->collect();
        $readiness = array();
        foreach (array_keys($this->ha_library_review->course_files()) as $code) { $readiness[] = $this->ha_library_review->ready($code,$locale); }
        $result = array('generated_at'=>gmdate('c'),'locale'=>$locale,'source_audit'=>$this->ha_library_review->audit_sources(),
            'translations'=>$this->ha_global_translation->coverage($locale), 'videos'=>$this->ha_library_video->report(),
            'course_readiness'=>$readiness, 'language_inventory'=>$this->db->order_by('locale')->get('ha_language_inventory')->result_array(),
            'resource_dependencies'=>array('Human PDF and curriculum reviewers','Enabled production translation provider or human translation imports','Reviewed signed-language media','YouTube Data API credentials for embedding and country checks'));
        Ha_library_review::write_json($file,$result);
        $this->output(array('report'=>$file,'files'=>$result['source_audit']['files'],'pages'=>$result['source_audit']['pages'],'reviewed_pages'=>$result['source_audit']['reviewed_pages'],
            'ready_courses'=>count(array_filter($readiness,function($r){return $r['ready'];})),'lessons'=>count($result['videos']),'languages'=>count($result['language_inventory'])));
    }
}
