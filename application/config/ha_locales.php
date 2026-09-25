<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Language registry for the whole application (public site, workspace, CMS,
 * legacy LMS phrases).
 *
 *  - 'enabled'  languages offered to visitors (URL prefix /{code}/, switchers,
 *               hreflang, sitemap). Order = order in language menus.
 *               Adding a code here is all it takes to publish a new language:
 *               untranslated strings fall back to English, untranslated content
 *               falls back to English and is marked noindex (no thin duplicates).
 *  - 'default'  fallback language (content and interface).
 *  - 'rtl'      right-to-left scripts.
 *  - 'catalog'  every ISO 639-1 language (+ a few BCP 47 codes). Native names
 *               come from ICU (intl) when available, otherwise the English name.
 *  - 'legacy'   Academy LMS `language` table column for each code.
 */
$config['ha_locales'] = array(
    'default' => 'en',
    'enabled' => array('en', 'ar', 'hi', 'ur', 'tl', 'bn', 'ne', 'ml'),
    'rtl'     => array('ar', 'ur', 'fa', 'he', 'ps', 'sd', 'ug', 'yi', 'dv', 'ckb', 'ku'),
    // Region used for number/date formatting and hreflang when a language is shown (xx-YY).
    'region'  => array('en' => 'GB', 'ar' => 'SA', 'hi' => 'IN', 'ur' => 'PK', 'tl' => 'PH', 'bn' => 'BD', 'ne' => 'NP', 'ml' => 'IN'),
    // Numbering system override (Saudi business UIs use Western digits with Arabic text).
    'numbers' => array('ar' => 'latn'),
    'legacy'  => array('en' => 'english', 'ar' => 'arabic', 'hi' => 'hindi', 'ur' => 'urdu', 'tl' => 'tagalog', 'bn' => 'bengali', 'ne' => 'nepali', 'ml' => 'malayalam'),
    'catalog' => array(
        'aa' => 'Afar', 'ab' => 'Abkhazian', 'af' => 'Afrikaans', 'ak' => 'Akan', 'am' => 'Amharic', 'an' => 'Aragonese', 'ar' => 'Arabic',
        'as' => 'Assamese', 'av' => 'Avaric', 'ay' => 'Aymara', 'az' => 'Azerbaijani', 'ba' => 'Bashkir', 'be' => 'Belarusian', 'bg' => 'Bulgarian',
        'bi' => 'Bislama', 'bm' => 'Bambara', 'bn' => 'Bengali', 'bo' => 'Tibetan', 'br' => 'Breton', 'bs' => 'Bosnian', 'ca' => 'Catalan',
        'ce' => 'Chechen', 'ch' => 'Chamorro', 'ckb' => 'Central Kurdish', 'co' => 'Corsican', 'cs' => 'Czech', 'cv' => 'Chuvash', 'cy' => 'Welsh',
        'da' => 'Danish', 'de' => 'German', 'dv' => 'Divehi', 'dz' => 'Dzongkha', 'ee' => 'Ewe', 'el' => 'Greek', 'en' => 'English',
        'eo' => 'Esperanto', 'es' => 'Spanish', 'et' => 'Estonian', 'eu' => 'Basque', 'fa' => 'Persian', 'ff' => 'Fula', 'fi' => 'Finnish',
        'fil' => 'Filipino', 'fj' => 'Fijian', 'fo' => 'Faroese', 'fr' => 'French', 'fy' => 'Western Frisian', 'ga' => 'Irish', 'gd' => 'Scottish Gaelic',
        'gl' => 'Galician', 'gn' => 'Guarani', 'gu' => 'Gujarati', 'gv' => 'Manx', 'ha' => 'Hausa', 'he' => 'Hebrew', 'hi' => 'Hindi',
        'hr' => 'Croatian', 'ht' => 'Haitian Creole', 'hu' => 'Hungarian', 'hy' => 'Armenian', 'id' => 'Indonesian', 'ig' => 'Igbo', 'is' => 'Icelandic',
        'it' => 'Italian', 'iu' => 'Inuktitut', 'ja' => 'Japanese', 'jv' => 'Javanese', 'ka' => 'Georgian', 'kk' => 'Kazakh', 'kl' => 'Kalaallisut',
        'km' => 'Khmer', 'kn' => 'Kannada', 'ko' => 'Korean', 'ks' => 'Kashmiri', 'ku' => 'Kurdish', 'ky' => 'Kyrgyz', 'la' => 'Latin',
        'lb' => 'Luxembourgish', 'lg' => 'Ganda', 'ln' => 'Lingala', 'lo' => 'Lao', 'lt' => 'Lithuanian', 'lv' => 'Latvian', 'mg' => 'Malagasy',
        'mi' => 'Maori', 'mk' => 'Macedonian', 'ml' => 'Malayalam', 'mn' => 'Mongolian', 'mr' => 'Marathi', 'ms' => 'Malay', 'mt' => 'Maltese',
        'my' => 'Burmese', 'nb' => 'Norwegian Bokmål', 'ne' => 'Nepali', 'nl' => 'Dutch', 'nn' => 'Norwegian Nynorsk', 'ny' => 'Chichewa', 'om' => 'Oromo',
        'or' => 'Odia', 'pa' => 'Punjabi', 'pl' => 'Polish', 'ps' => 'Pashto', 'pt' => 'Portuguese', 'qu' => 'Quechua', 'rm' => 'Romansh',
        'rn' => 'Kirundi', 'ro' => 'Romanian', 'ru' => 'Russian', 'rw' => 'Kinyarwanda', 'sa' => 'Sanskrit', 'sd' => 'Sindhi', 'se' => 'Northern Sami',
        'sg' => 'Sango', 'si' => 'Sinhala', 'sk' => 'Slovak', 'sl' => 'Slovenian', 'sm' => 'Samoan', 'sn' => 'Shona', 'so' => 'Somali',
        'sq' => 'Albanian', 'sr' => 'Serbian', 'ss' => 'Swati', 'st' => 'Southern Sotho', 'su' => 'Sundanese', 'sv' => 'Swedish', 'sw' => 'Swahili',
        'ta' => 'Tamil', 'te' => 'Telugu', 'tg' => 'Tajik', 'th' => 'Thai', 'ti' => 'Tigrinya', 'tk' => 'Turkmen', 'tl' => 'Tagalog (Filipino)',
        'tn' => 'Tswana', 'to' => 'Tongan', 'tr' => 'Turkish', 'ts' => 'Tsonga', 'tt' => 'Tatar', 'tw' => 'Twi', 'ug' => 'Uyghur',
        'uk' => 'Ukrainian', 'ur' => 'Urdu', 'uz' => 'Uzbek', 've' => 'Venda', 'vi' => 'Vietnamese', 'wo' => 'Wolof', 'xh' => 'Xhosa',
        'yi' => 'Yiddish', 'yo' => 'Yoruba', 'za' => 'Zhuang', 'zh' => 'Chinese', 'zu' => 'Zulu',
    ),
);
