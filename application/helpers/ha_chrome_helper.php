<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Editable chrome copy: the wording in the utility rail and the footer.
 *
 * All of it used to be hardcoded in the layout, which meant changing the
 * address or the strapline was a deploy. These read from frontend_settings
 * instead, keyed per locale, and fall back to the shipped wording when a key
 * has never been set -- so the site renders correctly on a database that has
 * never seen the admin screen, and an administrator who clears a field gets
 * the default back rather than a blank footer.
 *
 * Keys are <name>_en / <name>_ar. Values that are the same in both languages
 * (an email address, a social URL) are stored once under the bare name.
 */

if (!function_exists('ha_chrome_defaults')) {
    function ha_chrome_defaults() {
        return array(
            'ha_rail_note_en' => 'Hotel training in Arabic and English · Verifiable certificates',
            'ha_rail_note_ar' => 'تدريب فندقي بالعربية والإنجليزية · شهادات قابلة للتحقق',

            'ha_footer_statement_en' => 'Standards that hold when no one is watching.',
            'ha_footer_statement_ar' => 'معايير تصمد حين لا يراقب أحد.',

            'ha_contact_address_en' => "Riyadh\nKingdom of Saudi Arabia",
            'ha_contact_address_ar' => "الرياض\nالمملكة العربية السعودية",

            // Deliberately empty. A placeholder address on a live site is worse
            // than none: it collects mail nobody reads. The footer omits each
            // of these until it is filled in.
            'ha_contact_email'    => '',
            'ha_contact_phone'    => '',
            'ha_social_linkedin'  => '',
            'ha_social_instagram' => '',
            'ha_social_youtube'   => '',
            'ha_social_x'         => '',
        );
    }
}

if (!function_exists('ha_chrome')) {
    /**
     * @param string $key    e.g. 'ha_rail_note' or 'ha_contact_email'
     * @param string $locale 'en' or 'ar'
     * @return string
     */
    function ha_chrome($key, $locale = 'en') {
        static $cache = array();

        // Only English and Arabic have admin-editable columns. Any other language uses the
        // English value, translated through the public-site dictionary when it has an entry.
        $other = ($locale !== 'ar' && $locale !== 'en') ? $locale : null;
        if ($other !== null) {
            $en = ha_chrome($key, 'en');
            return function_exists('ha_pt') ? ha_pt($en) : $en;
        }
        $defaults = ha_chrome_defaults();

        // Locale-specific key first, then the shared one.
        $lookup = isset($defaults[$key . '_' . $locale]) ? $key . '_' . $locale : $key;
        if (!isset($defaults[$lookup])) {
            return '';
        }

        if (!array_key_exists($lookup, $cache)) {
            $value = '';
            if (function_exists('get_frontend_settings')) {
                $value = trim((string) get_frontend_settings($lookup));
            }
            $cache[$lookup] = ($value !== '') ? $value : $defaults[$lookup];
        }
        return $cache[$lookup];
    }
}
