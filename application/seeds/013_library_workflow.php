<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';

/** Inventory, staged revisions and identities only. Does not change course visibility. */
class Seed_library_workflow extends Ha_seeder {
    public function run($db) {
        $this->boot($db);
        if (!$db->table_exists('ha_language_inventory')) { return 0; }
        $CI =& get_instance(); $CI->load->helper('ha_locale'); $CI->load->library('ha_library_review'); $R = $CI->ha_library_review;
        $source = Ha_library_review::json_file(Ha_library_review::data_dir() . 'languages.json');
        $existing = $db->select('locale,modality')->get('ha_language_inventory')->result_array(); $known = array_fill_keys(array_column($existing,'locale'), true); $modalities=array_column($existing,'modality','locale'); $batch = array();
        foreach ($source['languages'] as $l) {
            if (isset($known[$l['locale']])) {
                if ($l['modality']==='signed' && $modalities[$l['locale']]!=='signed') { $db->where('locale',$l['locale'])->update('ha_language_inventory',array('modality'=>'signed','updated_at'=>$this->now)); }
                continue;
            }
            $batch[] = array('locale' => $l['locale'], 'iso6393' => $l['iso6393'], 'name' => $l['name'], 'modality' => $l['modality'],
                'direction' => ha_locale_dir($l['locale']), 'source_version' => $source['source_sha256'], 'updated_at' => $this->now);
            $known[$l['locale']]=true;
        }
        foreach (array_chunk($batch, 250) as $rows) { $db->insert_batch('ha_language_inventory', $rows); }
        // Existing macrolanguage routes and script/region aliases remain stable.
        foreach (ha_locale_config()['catalog'] as $code => $name) {
            if (!isset($known[$code])) {
                $db->insert('ha_language_inventory', array('locale' => $code, 'name' => $name, 'modality' => 'spoken', 'direction' => ha_locale_dir($code), 'source_version' => 'existing-route', 'updated_at' => $this->now));
                $known[$code]=true;
            }
        }
        $n = count($batch);
        foreach ($R->course_files() as $code => $path) {
            $c = Ha_library_review::json_file($path); $R->stage($c);
            $row = $db->get_where('ha_course', array('code' => $code))->row_array();
            if (!$row) { continue; }
            $keys = $R->keys($c);
            $adopt = array();
            foreach (array('section','lesson','assessment','question') as $entity) { $adopt[$entity] = !$db->where(array('entity'=>$entity,'course_code'=>$code))->count_all_results('ha_library_identity'); }
            foreach ($keys['sections'] as $key => $position) {
                $id = (int) $db->select('id')->get_where('ha_course_section', array('course_id' => $row['id'], 'sort_order' => $position))->row('id');
                $R->identity('section', $key, $code, $adopt['section'] ? $id : 0);
            }
            foreach ($keys['lessons'] as $key => $lesson) {
                $l = $db->get_where('ha_lesson', array('course_id' => $row['id'], 'sort_order' => $lesson['position']))->row_array();
                if (!$l) { continue; }
                $R->identity('lesson', $key, $code, $adopt['lesson'] ? $l['id'] : 0);
                if ($l['assessment_id']) { $R->identity('assessment', $key, $code, $adopt['assessment'] ? $l['assessment_id'] : 0); }
                $qs = $db->order_by('sort_order')->get_where('ha_assessment_question', array('assessment_id' => $l['assessment_id']))->result_array();
                $index = 0;
                foreach ($keys['questions'] as $qkey => $q) {
                    if ($q['lesson_key'] === $key) { if (isset($qs[$index])) { $R->identity('question', $qkey, $code, $adopt['question'] ? $qs[$index]['question_id'] : 0); } $index++; }
                }
            }
            $R->attach_sources($code, $row['id']); $n++;
        }
        return $n;
    }
}
