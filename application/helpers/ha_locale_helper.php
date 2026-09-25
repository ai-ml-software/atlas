<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Language and country support shared by the public site, the workspace and
 * the legacy LMS. Data comes from config/ha_locales.php and config/ha_countries.php;
 * names and formats come from ICU (PHP intl) when the server has it, so every
 * language/country is supported without maintaining translated name lists.
 */

if (!function_exists('ha_locale_config')) {

    function ha_locale_config() {
        static $cfg = null;
        if ($cfg === null) {
            $config = array();
            include APPPATH . 'config/ha_locales.php';
            $cfg = $config['ha_locales'];
        }
        return $cfg;
    }

    /** Enabled language codes, in menu order. */
    function ha_locales() {
        return ha_locale_config()['enabled'];
    }

    function ha_locale_default() {
        return ha_locale_config()['default'];
    }

    function ha_locale_enabled($code) {
        return is_string($code) && in_array($code, ha_locales(), true);
    }

    /** Known to the registry (can be enabled), not necessarily enabled. */
    function ha_locale_known($code) {
        return is_string($code) && isset(ha_locale_config()['catalog'][$code]);
    }

    function ha_locale_dir($code) {
        return in_array($code, ha_locale_config()['rtl'], true) ? 'rtl' : 'ltr';
    }

    /** ICU locale id, e.g. ur_PK, for number/date/currency formatting. */
    function ha_locale_icu($code) {
        $cfg = ha_locale_config();
        $base = $code === 'tl' ? 'fil' : $code;   // ICU names Filipino "fil"
        $id = isset($cfg['region'][$code]) ? $base . '_' . $cfg['region'][$code] : $base;
        return isset($cfg['numbers'][$code]) ? $id . '@numbers=' . $cfg['numbers'][$code] : $id;
    }

    /** Language name in its own language ("اردو", "हिन्दी"), or in $in. */
    function ha_locale_name($code, $in = null) {
        $cat = ha_locale_config()['catalog'];
        if (extension_loaded('intl')) {
            $icu = $code === 'tl' ? 'fil' : $code;
            $n = \Locale::getDisplayLanguage($icu, $in ?: $icu);
            if ($n && $n !== $icu) {
                return mb_strtoupper(mb_substr($n, 0, 1)) . mb_substr($n, 1);
            }
        }
        return isset($cat[$code]) ? $cat[$code] : $code;
    }

    /** Legacy Academy LMS `language` table column for a code (null when not provisioned). */
    function ha_locale_legacy_column($code) {
        $m = ha_locale_config()['legacy'];
        return isset($m[$code]) ? $m[$code] : null;
    }

    /** Regex alternation of enabled codes for routes: "en|ar|hi|…". */
    function ha_locale_route_pattern() {
        return implode('|', array_map('preg_quote', ha_locales()));
    }

    // ------------------------------------------------ public website strings

    /**
     * Languages the public website is published in: English plus every enabled
     * language that has its interface dictionary (application/language/site/{code}.php).
     * Only these appear in hreflang, sitemaps and the language menu.
     */
    function ha_site_locales() {
        static $list = null;
        if ($list === null) {
            $list = array();
            foreach (ha_locales() as $l) {
                if ($l === 'en' || is_file(APPPATH . 'language/site/' . $l . '.php')) {
                    $list[] = $l;
                }
            }
        }
        return $list;
    }

    /** The language the public website is rendering in (set by Academy::boot). */
    function ha_site_locale($set = null) {
        static $locale = 'en';
        if ($set !== null && ha_locale_enabled($set)) {
            $locale = $set;
        }
        return $locale;
    }

    function ha_site_dictionary($locale) {
        static $dicts = array();
        if (!isset($dicts[$locale])) {
            $file = APPPATH . 'language/site/' . preg_replace('/[^a-z]/', '', $locale) . '.php';
            $dicts[$locale] = ($locale !== 'en' && is_file($file)) ? (array) include $file : array();
        }
        return $dicts[$locale];
    }

    /**
     * Public-site interface string, gettext style: the English text is the key,
     * application/language/site/{code}.php holds each language. Missing strings
     * fall back to English. Raw output (for data passed on to views that escape).
     */
    function ha_pt($text, array $vars = array()) {
        $locale = ha_site_locale();
        $out = $text;
        if ($locale !== 'en') {
            $d = ha_site_dictionary($locale);
            if (isset($d[$text]) && $d[$text] !== '') {
                $out = $d[$text];
            }
        }
        foreach ($vars as $k => $v) {
            $out = str_replace('{' . $k . '}', (string) $v, $out);
        }
        return $out;
    }

    /** ha_pt() escaped for direct output in HTML text or attributes. */
    function ha_pe($text, array $vars = array()) {
        return htmlspecialchars(ha_pt($text, $vars), ENT_QUOTES, 'UTF-8');
    }

    // ------------------------------------------------------------ formatting

    function ha_format_number($n, $code, $decimals = null) {
        if (extension_loaded('intl')) {
            $f = new \NumberFormatter(ha_locale_icu($code), \NumberFormatter::DECIMAL);
            if ($decimals !== null) {
                $f->setAttribute(\NumberFormatter::FRACTION_DIGITS, (int) $decimals);
            }
            $out = $f->format((float) $n);
            if ($out !== false) {
                return $out;
            }
        }
        return number_format((float) $n, $decimals === null ? (floor($n) == $n ? 0 : 1) : (int) $decimals);
    }

    function ha_format_money($amount, $currency, $code) {
        if (extension_loaded('intl')) {
            $f = new \NumberFormatter(ha_locale_icu($code), \NumberFormatter::CURRENCY);
            $out = $f->formatCurrency((float) $amount, strtoupper((string) $currency));
            if ($out !== false) {
                return $out;
            }
        }
        return strtoupper((string) $currency) . ' ' . number_format((float) $amount, 2);
    }

    /** Localised date ("25 Sept 2026" / "٢٥ سبتمبر ٢٠٢٦"). $tz: IANA zone, default app zone. */
    function ha_format_date($value, $code, $with_time = false, $tz = null) {
        if (!$value) {
            return '';
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (!$ts) {
            return (string) $value;
        }
        if (extension_loaded('intl')) {
            $f = new \IntlDateFormatter(ha_locale_icu($code), \IntlDateFormatter::MEDIUM,
                $with_time ? \IntlDateFormatter::SHORT : \IntlDateFormatter::NONE, $tz ?: date_default_timezone_get());
            $out = $f->format($ts);
            if ($out !== false) {
                return $out;
            }
        }
        return date($with_time ? 'Y-m-d H:i' : 'Y-m-d', $ts);
    }

    // ------------------------------------------------------------ countries

    function ha_country_data() {
        static $c = null;
        if ($c === null) {
            $config = array();
            include APPPATH . 'config/ha_countries.php';
            $c = $config['ha_countries'];
        }
        return $c;
    }

    /** Country name in the given language (ICU), falling back to the English name. */
    function ha_country_name($iso2, $code = 'en') {
        $iso2 = strtoupper((string) $iso2);
        if (extension_loaded('intl')) {
            $n = \Locale::getDisplayRegion('-' . $iso2, ha_locale_icu($code));
            if ($n && $n !== $iso2) {
                return $n;
            }
        }
        $d = ha_country_data();
        return isset($d[$iso2]) ? $d[$iso2][0] : $iso2;
    }

    /** All countries as iso2 => localised name, sorted in that language. */
    function ha_countries($code = 'en') {
        $out = array();
        foreach (array_keys(ha_country_data()) as $iso2) {
            $out[$iso2] = ha_country_name($iso2, $code);
        }
        if (class_exists('\Collator')) {
            $col = new \Collator(ha_locale_icu($code));
            uasort($out, function ($a, $b) use ($col) { return $col->compare($a, $b); });
        } else {
            asort($out);
        }
        return $out;
    }

    /** array(dial code, currency, default timezone) for an ISO country, or null. */
    function ha_country($iso2) {
        $d = ha_country_data();
        $iso2 = strtoupper((string) $iso2);
        return isset($d[$iso2]) ? array('iso2' => $iso2, 'name' => $d[$iso2][0], 'dial' => $d[$iso2][1], 'currency' => $d[$iso2][2], 'timezone' => $d[$iso2][3]) : null;
    }

    /**
     * Normalises a phone number to E.164 (+9665XXXXXXXX) using the country's dial
     * code. Returns null when it cannot be a valid international number.
     */
    function ha_phone_e164($raw, $iso2) {
        $digits = preg_replace('/[^\d+]/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }
        if (strpos($digits, '00') === 0) {
            $digits = '+' . substr($digits, 2);
        }
        if ($digits[0] !== '+') {
            $c = ha_country($iso2);
            if (!$c) {
                return null;
            }
            $digits = '+' . ltrim($c['dial'], '+') . ltrim($digits, '0');
        }
        return preg_match('/^\+[1-9]\d{6,14}$/', $digits) ? $digits : null;
    }
}
