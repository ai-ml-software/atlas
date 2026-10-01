<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** One request cache; approved row-based translations override legacy dictionaries. */
if (!function_exists('ha_reviewed_text')) {
    function ha_resolve_language_code($language) {
        require_once APPPATH . 'helpers/ha_locale_helper.php';
        if (ha_locale_known($language)) { return $language; }
        $code=array_search(strtolower($language),ha_locale_config()['legacy'],true);
        if ($code) { return $code; }
        foreach (ha_locale_config()['catalog'] as $code=>$name) { if (strcasecmp($name,$language)===0) { return $code; } }
        throw new InvalidArgumentException('Select a language code from the global inventory.');
    }
    function ha_reviewed_text($domain, $key, $locale, $fallback) {
        static $maps = array();
        if (!isset($maps[$locale])) {
            $maps[$locale] = array(); $CI =& get_instance();
            if ($CI && isset($CI->db) && $CI->db->table_exists('ha_translation_unit')) {
                foreach ($CI->db->select('u.locator,v.value')->from('ha_translation_unit u')
                    ->join('ha_translation_value v', 'v.unit_id=u.id')->where(array('u.scope' => 'site', 'u.active' => 1, 'v.locale' => $locale, 'v.status' => 'ready'))
                    ->where('v.source_hash=u.source_hash', null, false)->where('v.reviewer IS NOT NULL', null, false)->like('u.locator', 'ui:', 'after')->get()->result_array() as $r) {
                    if (trim((string) $r['value']) !== '') { $maps[$locale][$r['locator']] = $r['value']; }
                }
            }
        }
        $locator = 'ui:' . $domain . ':' . $key;
        return isset($maps[$locale][$locator]) ? $maps[$locale][$locator] : $fallback;
    }
    function ha_legacy_phrase($phrase, $api = false) {
        $CI =& get_instance(); $CI->load->database();
        $key = strtolower(preg_replace('/\s+/', '_', trim((string) $phrase)));
        if ($key === '') { return ''; }
        $language = !$api ? (string) $CI->session->userdata('language') : '';
        if ($language === '') { $setting = $CI->db->get_where('settings', array('key' => 'language'))->row_array(); $language = $setting ? $setting['value'] : 'english'; }
        require_once APPPATH . 'helpers/ha_locale_helper.php';
        $locale = ha_locale_known($language) ? $language : array_search($language, ha_locale_config()['legacy'], true);
        $locale = $locale ?: 'en';
        $column = ha_locale_legacy_column($locale);
        $row = $CI->db->get_where('language', array('phrase' => $key))->row_array();
        $fallback = $row && $column && !empty($row[$column]) ? $row[$column] : ($row && !empty($row['english']) ? $row['english'] : ucfirst(str_replace('_', ' ', $key)));
        return ha_reviewed_text('legacy', $key, $locale, $fallback);
    }
}
