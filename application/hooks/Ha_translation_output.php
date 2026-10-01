<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Translate registered static HTML/JS literals without changing JSON or binary responses. */
class Ha_translation_output {
    public function display() {
        $CI =& get_instance(); $html = $CI->output->get_output();
        if (is_cli()) { $CI->output->_display($html); return; }
        // CI3's get_header uses array_shift(explode(...)), which warns on PHP 8.
        $content_type = '';
        foreach ($CI->output->headers as $header) { if (stripos($header[0], 'Content-Type:') === 0) { $content_type = trim(substr($header[0], 13)); } }
        if (($content_type && stripos($content_type, 'text/html') === false) || !preg_match('~<(?:html|body|div|form)\b~i', $html)) { $CI->output->_display($html); return; }
        require_once APPPATH . 'helpers/ha_locale_helper.php';
        $locale = isset($CI->locale) && ha_locale_enabled($CI->locale) ? $CI->locale : ha_site_locale();
        if (strpos($CI->uri->uri_string(), 'hkp') === 0) { require_once APPPATH . 'helpers/hkp_helper.php'; $locale = hkp_locale(); }
        elseif ($locale === 'en' && isset($CI->session)) {
            $legacy = (string) $CI->session->userdata('language');
            $locale = ha_locale_known($legacy) ? $legacy : (array_search($legacy, ha_locale_config()['legacy'], true) ?: 'en');
        }
        if ($locale === 'en' || !isset($CI->db) || !$CI->db->table_exists('ha_translation_unit')) { $CI->output->_display($html); return; }
        $signed = $CI->db->get_where('ha_language_inventory', array('locale'=>$locale,'modality'=>'signed'))->num_rows() > 0;
        $map = array(); $media = array(); $client_map=array();
        if (!$signed) { $CI->db->where('u.scope','site'); }
        foreach ($CI->db->select('u.source_text,u.target_json,v.value,v.signed_media,v.signed_media_sha256')->from('ha_translation_unit u')->join('ha_translation_value v','v.unit_id=u.id')
            ->where(array('u.active' => 1,'v.locale' => $locale,'v.status' => 'ready'))->where('v.source_hash=u.source_hash', null, false)
            ->where('v.reviewer !=','')->get()->result_array() as $r) {
            if (!$signed && trim((string) $r['value']) !== '') { $map[$r['source_text']] = $r['value']; }
            $target=json_decode((string)$r['target_json'],true);
            if (!$signed && isset($target['type']) && $target['type']==='ui' && trim((string)$r['value'])!=='' && strpos($r['source_text'],'<')===false) { $client_map[$r['source_text']]=$r['value']; }
            if ($signed && $r['signed_media'] && is_file(FCPATH . $r['signed_media']) && hash_file('sha256',FCPATH . $r['signed_media']) === $r['signed_media_sha256']) {
                // Include only signing for content present in this rendered response.
                // Unrendered quiz explanations and answers are never exposed.
                $plain = htmlspecialchars($r['source_text'], ENT_QUOTES, 'UTF-8');
                if (strpos($html, $plain) !== false || strpos($html, $r['source_text']) !== false) { $media[$r['signed_media']] = trim(strip_tags($r['source_text'])); }
            }
        }
        if (!$map && !$media) { $CI->output->_display($html); return; }
        foreach ($map as $source => $value) {
            if (preg_match('~^<(?:p|div|section|ul|ol|h[1-6])\b~i',$source)) { $html = str_replace($source,$value,$html); }
        }
        $parts = preg_split('~(<script\b[^>]*>.*?</script\s*>|<(?:style|textarea|pre|code)\b[^>]*>.*?</(?:style|textarea|pre|code)\s*>|<!--.*?-->|<[^>]+>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as &$part) {
            if (preg_match('~^<script\b~i', $part)) {
                $part = preg_replace_callback('/([\'"])((?:\\\\.|(?!\1).)*)\1/s', function ($m) use ($map) {
                    $text = $m[1] === "'" ? str_replace(array("\\'","\\\\"),array("'","\\"),$m[2]) : stripcslashes($m[2]);
                    return isset($map[$text]) ? json_encode($map[$text], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) : $m[0];
                }, $part);
            } elseif (preg_match('~^<(?:style|textarea|pre|code|!--)~i', $part)) { continue; }
            elseif (isset($part[0]) && $part[0] === '<') {
                $part = preg_replace_callback('~\b(title|alt|placeholder|aria-label)\s*=\s*([\'"])(.*?)\2~is', function ($m) use ($map) {
                    $key = html_entity_decode($m[3], ENT_QUOTES, 'UTF-8');
                    return isset($map[$key]) ? $m[1] . '=' . $m[2] . htmlspecialchars($map[$key], ENT_QUOTES, 'UTF-8') . $m[2] : $m[0];
                }, $part);
            } else {
                $key = html_entity_decode(trim($part), ENT_QUOTES, 'UTF-8');
                if (isset($map[$key])) {
                    preg_match('/^(\s*).*?(\s*)$/s', $part, $m);
                    $part = $m[1] . htmlspecialchars($map[$key], ENT_QUOTES, 'UTF-8') . $m[2];
                }
            }
        }
        unset($part); $html = implode('', $parts);
        $widgets = '';
        foreach ($media as $path => $label) {
            $widgets .= '<details class="ha-signed-translation"><summary>' . htmlspecialchars(mb_substr($label,0,120),ENT_QUOTES,'UTF-8') . '</summary><video controls preload="none" playsinline style="max-width:100%;width:640px" aria-label="' . htmlspecialchars($label,ENT_QUOTES,'UTF-8') . '" src="' . htmlspecialchars(base_url($path),ENT_QUOTES,'UTF-8') . '"></video></details>';
        }
        if ($widgets) { $html = strpos($html,'</body>') !== false ? str_replace('</body>', $widgets . '</body>', $html) : $html . $widgets; }
        if ($client_map) {
            // JSON is inert data; executable code stays in a same-origin file for CSP.
            $client='<script type="application/json" id="ha-reviewed-client-text">'.json_encode($client_map,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script><script src="'.htmlspecialchars(base_url('assets/academy/reviewed-translations.js'),ENT_QUOTES,'UTF-8').'" defer></script>';
            $html=strpos($html,'</body>')!==false ? str_replace('</body>',$client.'</body>',$html) : $html.$client;
        }
        $CI->output->_display($html);
    }
}
