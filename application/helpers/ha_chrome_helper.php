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
            'ha_rail_note_en' => 'Strategic Advisory & Business Consultancy · Riyadh · GCC & MENA',
            'ha_rail_note_ar' => 'الاستشارات الاستراتيجية واستشارات الأعمال · الرياض · الخليج والشرق الأوسط وشمال أفريقيا',

            'ha_footer_statement_en' => 'Elevating Hospitality & Business Performance',
            'ha_footer_statement_ar' => 'الارتقاء بالضيافة وأداء الأعمال',

            // Founders' direct lines, as published on the Altus Gulf corporate site.
            'ha_founder_1_name_en'  => 'Islam Mahrous',
            'ha_founder_1_name_ar'  => 'إسلام محروس',
            'ha_founder_1_role_en'  => 'Co-Founder',
            'ha_founder_1_role_ar'  => 'شريك مؤسس',
            'ha_founder_1_phone'    => '+20 10 9555 6779',
            'ha_founder_2_name_en'  => 'Hussam Smadi',
            'ha_founder_2_name_ar'  => 'حسام الصمادي',
            'ha_founder_2_role_en'  => 'Co-Founder',
            'ha_founder_2_role_ar'  => 'شريك مؤسس',
            'ha_founder_2_phone'    => '+966 50 051 1994',

            'ha_contact_address_en' => "Riyadh\nKingdom of Saudi Arabia",
            'ha_contact_address_ar' => "الرياض\nالمملكة العربية السعودية",

            // The support inbox is real; phone and social links stay empty until an
            // administrator fills them in (Admin → Frontend settings), and the footer
            // omits each one until then: a placeholder link is worse than none.
            'ha_contact_email'    => 'info@altusgulf.com',   // support inbox (Admin → Frontend settings)
            'ha_contact_phone'    => '',
            'ha_social_linkedin'  => '',
            'ha_social_facebook'  => '',
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

if (!function_exists('ha_social_icons')) {
    /**
     * Thin-line icon links for social networks and e-mail (one stroke weight, the brand's
     * icon rule). $links: network => url, plus 'email' => address. Empty values are skipped.
     *
     * @param array  $links
     * @param string $class  wrapper class
     * @param string $who    accessible name prefix, e.g. a person's name
     */
    function ha_social_icons(array $links, $class = 'ha-social', $who = '') {
        $paths = array(
            'linkedin'  => '<path d="M5.5 9v10M5.5 5.2v.1M10 19V9M10 13c0-2.3 1.5-4 3.6-4S17 10.6 17 13v6"/>',
            'facebook'  => '<path d="M14.5 8H17V4.5h-2.5A4 4 0 0 0 10.5 8.5V11H8v3.5h2.5V21H14v-6.5h2.6L17 11h-3V8.9c0-.5.4-.9.9-.9Z"/>',
            'instagram' => '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.7"/><path d="M16.9 7.1v.1"/>',
            'x'         => '<path d="M4.5 4.5l15 15M19.5 4.5l-15 15"/>',
            'youtube'   => '<rect x="3" y="6" width="18" height="12" rx="3.6"/><path d="M10.4 9.6l4.6 2.4-4.6 2.4z"/>',
            'email'     => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="M4 7l8 6 8-6"/>',
        );
        $names = array('linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'email' => 'Email');
        $out = '';
        foreach ($paths as $k => $svg) {
            $v = isset($links[$k]) ? trim((string) $links[$k]) : '';
            if ($v === '' || ($k !== 'email' && !preg_match('~^https?://~i', $v)) || ($k === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL))) {
                continue;
            }
            $href = $k === 'email' ? 'mailto:' . $v : $v;
            $label = trim($who . ' ' . $names[$k]);
            $out .= '<a href="' . html_escape($href) . '"' . ($k === 'email' ? '' : ' target="_blank" rel="noopener noreferrer"')
                . ' aria-label="' . html_escape($label) . '" title="' . html_escape($k === 'email' ? $v : $names[$k]) . '">'
                . '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
                . $svg . '</svg></a>';
        }
        return $out === '' ? '' : '<span class="' . html_escape($class) . '">' . $out . '</span>';
    }
}

