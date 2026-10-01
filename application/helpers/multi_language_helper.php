<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package     CodeIgniter
 * @author      ExpressionEngine Dev Team
 * @copyright   Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license     http://codeigniter.com/user_guide/license.html
 * @link        http://codeigniter.com
 * @since       Version 1.0
 * @filesource
 */

//  function test(){
//     $this->fileAndFolderDistributor('application/controllers');
//     $this->fileAndFolderDistributor('application/helpers');
//     $this->fileAndFolderDistributor('application/models');
//     $this->fileAndFolderDistributor('application/views');

// }
// public function fileAndFolderDistributor($uploaded_dir_path = "")
// {
//     $uploaded_dir_paths = glob($uploaded_dir_path . '/*');

//     foreach ($uploaded_dir_paths as $uploaded_sub_dir_path) {
//         if (is_dir($uploaded_sub_dir_path)) {
//             $all_available_sub_paths = count(glob($uploaded_sub_dir_path . '/*'));
//             if ($all_available_sub_paths > 0) {
//                 $this->fileAndFolderDistributor($uploaded_sub_dir_path);
//             }
//         } else {
//             $this->get_phrase_preg_matches($uploaded_sub_dir_path);
//         }
//     }
// }

// function get_phrase_preg_matches($file_path){
//     $all_languages = $this->crud_model->get_all_languages();
//     $file_content = file_get_contents($file_path);


//     $pattern = "/get_phrase\(['\"](.*?)['\"]\)/";
//     preg_match_all($pattern, $file_content, $matches);

//     $functionParameters = $matches[1];

//     // $functionParameters now contains an array of all parameters passed to the get_phrase function
//     //Insert phrases in database
//     foreach($functionParameters as $val){
//         if($this->db->where('phrase', $val)->get('language')->num_rows() == 0){
//             $data['phrase'] = $val;

//             $val = str_replace('_', ' ', $val);

//             foreach($all_languages as $language){
//                 $data[$language] = $val;
//             }

//             $this->db->insert('language', $data);
//         }
//     }
//     // print_r($functionParameters);
//     // echo $file_path;
//     // echo '<hr>';
// }

//All common helper functions
if (!function_exists('get_phrase')) {
    function get_phrase_($phrase = "", $replaces = array())
    {
        if(!is_array($replaces)){
            $replaces = array($replaces);
        }
        foreach ($replaces as $replace) {
            $phrase = preg_replace('/____/', $replace, $phrase, 1); // Replace one placeholder at a time
        }

        return $phrase;
    }
}

// Reviewed row-based phrases with existing dictionary compatibility; no inferred writes.
if (!function_exists('get_phrase')) {
    function get_phrase($phrase = '')
    {
        require_once APPPATH . 'helpers/ha_reviewed_translation_helper.php';
        return ha_legacy_phrase($phrase, false);
    }
}

if ( ! function_exists('api_phrase'))
{
    function api_phrase($phrase = '') {
        require_once APPPATH . 'helpers/ha_reviewed_translation_helper.php';
        return ha_legacy_phrase($phrase, true);
    }
}

// This function helps us to get the translated phrase from the file. If it does not exist this function will save the phrase and by default it will have the same form as given
if (!function_exists('site_phrase')) {
    function site_phrase($phrase = '')
    {
        require_once APPPATH . 'helpers/ha_reviewed_translation_helper.php';
        return ha_legacy_phrase($phrase, false);
    }
}

// This function helps us to decode the language json and return that array to us
if (!function_exists('openJSONFile')) {
    function openJSONFile($code)
    {
        $CI = get_instance();
        $CI->load->database();
        require_once APPPATH.'helpers/ha_reviewed_translation_helper.php';
        $locale=ha_resolve_language_code($code);
        $column=ha_locale_legacy_column($locale);
        $review=array();
        if ($CI->db->table_exists('ha_translation_unit')) {
            foreach ($CI->db->select('u.target_json,v.value')->from('ha_translation_unit u')->join('ha_translation_value v','v.unit_id=u.id')
                ->where(array('u.scope'=>'site','u.active'=>1,'v.locale'=>$locale))->like('u.locator','ui:legacy:','after')->get()->result_array() as $item) {
                $target=json_decode($item['target_json'],true); $review[$target['key']]=$item['value'];
            }
        }
        $key_value_pairs = [];
        $language_query = $CI->db->get_where('language')->result_array();
        foreach ($language_query as $row) {
            $key = $row['phrase'];
            $value = isset($review[$key]) ? $review[$key] : ($column && isset($row[$column]) ? $row[$column] : '');
            $key_value_pairs[$key] = $value;
        }
        return $key_value_pairs;
    }
}

// This function helps us to create a new json file for new language
if (!function_exists('saveDefaultJSONFile')) {
    function saveDefaultJSONFile($language_code)
    {
        require_once APPPATH.'helpers/ha_reviewed_translation_helper.php';
        $CI=get_instance(); $CI->load->library('ha_library_review');
        return $CI->ha_library_review->language(ha_resolve_language_code($language_code));
    }
}

// This function helps us to update a phrase inside the language file.
if (!function_exists('saveJSONFile')) {
    function saveJSONFile($language_code, $updating_key, $updating_value)
    {
        $CI = get_instance();
        $CI->load->library('ha_global_translation');
        require_once APPPATH.'helpers/ha_reviewed_translation_helper.php';
        $locale=ha_resolve_language_code($language_code);
        $key=strtolower(preg_replace('/\s+/','_',trim($updating_key)));
        $row=$CI->db->get_where('language',array('phrase'=>$key))->row_array();
        $source=$row && !empty($row['english']) ? $row['english'] : ucfirst(str_replace('_',' ',$key));
        $id=$CI->ha_global_translation->unit('site','ui:legacy:'.$key,$source,array('type'=>'ui','domain'=>'legacy','key'=>$key));
        $unit=$CI->db->get_where('ha_translation_unit',array('id'=>$id))->row_array();
        $CI->ha_global_translation->import(array('version'=>1,'locale'=>$locale,'units'=>array(array('key'=>$unit['unit_key'],'source_hash'=>$unit['source_hash'],'value'=>$updating_value,'status'=>'reviewing'))),'human');
    }
}


// This function helps us to update a phrase inside the language file.
if (!function_exists('escapeJsonString')) {
    function escapeJsonString($value)
    {
        $value = str_replace('"', "'", $value);
        $escapers =     array("\\",     "/",   "\"",  "\n",  "\r",  "\t", "\x08", "\x0c");
        $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t",  "\\f",  "\\b");
        $result = str_replace($escapers, $replacements, $value);
        return $result;
    }
}

// This function helps us to update a phrase inside the language file.
if (!function_exists('getIsoCode')) {
    function getIsoCode($language = "")
    {
        $all_lan_ISO_CODES = '
        {
            "aa": "Afar",
            "ab": "Abkhazian",
            "ae": "Avestan",
            "af": "Afrikaans",
            "ak": "Akan",
            "am": "Amharic",
            "an": "Aragonese",
            "ar": "Arabic",
            "as": "Assamese",
            "av": "Avaric",
            "ay": "Aymara",
            "az": "Azerbaijani",
            "ba": "Bashkir",
            "be": "Belarusian",
            "bg": "Bulgarian",
            "bh": "Bihari languages",
            "bi": "Bislama",
            "bm": "Bambara",
            "bn": "Bengali",
            "bo": "Tibetan",
            "br": "Breton",
            "bs": "Bosnian",
            "ca": "Catalan",
            "ce": "Chechen",
            "ch": "Chamorro",
            "co": "Corsican",
            "cr": "Cree",
            "cs": "Czech",
            "cu": "Church Slavic",
            "cv": "Chuvash",
            "cy": "Welsh",
            "da": "Danish",
            "de": "German",
            "dv": "Maldivian",
            "dz": "Dzongkha",
            "ee": "Ewe",
            "el": "Greek",
            "en": "English",
            "eo": "Esperanto",
            "es": "Spanish",
            "et": "Estonian",
            "eu": "Basque",
            "fa": "Persian",
            "ff": "Fulah",
            "fi": "Finnish",
            "fj": "Fijian",
            "fo": "Faroese",
            "fr": "French",
            "fy": "Western Frisian",
            "ga": "Irish",
            "gd": "Gaelic",
            "gl": "Galician",
            "gn": "Guarani",
            "gu": "Gujarati",
            "gv": "Manx",
            "ha": "Hausa",
            "he": "Hebrew",
            "hi": "Hindi",
            "ho": "Hiri Motu",
            "hr": "Croatian",
            "ht": "Haitian",
            "hu": "Hungarian",
            "hy": "Armenian",
            "hz": "Herero",
            "ia": "Interlingua",
            "id": "Indonesian",
            "ie": "Interlingue",
            "ig": "Igbo",
            "ii": "Sichuan Yi",
            "ik": "Inupiaq",
            "io": "Ido",
            "is": "Icelandic",
            "it": "Italian",
            "iu": "Inuktitut",
            "ja": "Japanese",
            "jv": "Javanese",
            "ka": "Georgian",
            "kg": "Kongo",
            "ki": "Kikuyu",
            "kj": "Kuanyama",
            "kk": "Kazakh",
            "kl": "Kalaallisut",
            "km": "Central Khmer",
            "kn": "Kannada",
            "ko": "Korean",
            "kr": "Kanuri",
            "ks": "Kashmiri",
            "ku": "Kurdish",
            "kv": "Komi",
            "kw": "Cornish",
            "ky": "Kirghiz",
            "la": "Latin",
            "lb": "Luxembourgish",
            "lg": "Ganda",
            "li": "Limburgan",
            "ln": "Lingala",
            "lo": "Lao",
            "lt": "Lithuanian",
            "lu": "Luba-Katanga",
            "lv": "Latvian",
            "mg": "Malagasy",
            "mh": "Marshallese",
            "mi": "Maori",
            "mk": "Macedonian",
            "ml": "Malayalam",
            "mn": "Mongolian",
            "mr": "Marathi",
            "ms": "Malay",
            "mt": "Maltese",
            "my": "Burmese",
            "na": "Nauru",
            "nb": "Norwegian",
            "nd": "North Ndebele",
            "ne": "Nepali",
            "ng": "Ndonga",
            "nl": "Dutch",
            "nn": "Norwegian",
            "no": "Norwegian",
            "nr": "South Ndebele",
            "nv": "Navajo",
            "ny": "Chichewa",
            "oc": "Occitan",
            "oj": "Ojibwa",
            "om": "Oromo",
            "or": "Oriya",
            "os": "Ossetic",
            "pa": "Panjabi",
            "pi": "Pali",
            "pl": "Polish",
            "ps": "Pushto",
            "pt": "Portuguese",
            "qu": "Quechua",
            "rm": "Romansh",
            "rn": "Rundi",
            "ro": "Romanian",
            "ru": "Russian",
            "rw": "Kinyarwanda",
            "sa": "Sanskrit",
            "sc": "Sardinian",
            "sd": "Sindhi",
            "se": "Northern Sami",
            "sg": "Sango",
            "si": "Sinhala",
            "sk": "Slovak",
            "sl": "Slovenian",
            "sm": "Samoan",
            "sn": "Shona",
            "so": "Somali",
            "sq": "Albanian",
            "sr": "Serbian",
            "ss": "Swati",
            "st": "Sotho, Southern",
            "su": "Sundanese",
            "sv": "Swedish",
            "sw": "Swahili",
            "ta": "Tamil",
            "te": "Telugu",
            "tg": "Tajik",
            "th": "Thai",
            "ti": "Tigrinya",
            "tk": "Turkmen",
            "tl": "Tagalog",
            "tn": "Tswana",
            "to": "Tonga",
            "tr": "Turkish",
            "ts": "Tsonga",
            "tt": "Tatar",
            "tw": "Twi",
            "ty": "Tahitian",
            "ug": "Uighur",
            "uk": "Ukrainian",
            "ur": "Urdu",
            "uz": "Uzbek",
            "ve": "Venda",
            "vi": "Vietnamese",
            "vo": "Volapük",
            "wa": "Walloon",
            "wo": "Wolof",
            "xh": "Xhosa",
            "yi": "Yiddish",
            "yo": "Yoruba",
            "za": "Zhuang",
            "zh": "Chinese",
            "zu": "Zulu"
        }';

        $languages_arr = json_decode($all_lan_ISO_CODES, true);
        $all_lan_ISO_CODES = array_map('strtolower', $languages_arr);
        $index = array_search(strtolower($language), $all_lan_ISO_CODES);

        if(is_array($languages_arr) && array_key_exists($index, $languages_arr)){
            //return $languages_arr[$index];
            return $index;
        }
    }

    
}





// ------------------------------------------------------------------------
/* End of file language_helper.php */
/* Location: ./system/helpers/language_helper.php */
