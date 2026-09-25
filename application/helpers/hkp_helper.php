<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * altus Hospitality Knowledge & Performance - view helpers.
 *
 * Translation is gettext style: the English sentence is the key and
 * application/language/arabic/hkp_lang.php maps it to Arabic. That keeps views
 * readable and lets Test_hkp_i18n scan every hkp_t('...') literal in the
 * workspace views and fail the build when one has no Arabic, so "no hardcoded
 * language strings" is enforced rather than hoped for (ppt-features 7, 79).
 */

if (!function_exists('hkp_locale')) {
    /** The active workspace locale: explicit choice, then profile, then English. */
    function hkp_locale($set = null) {
        static $locale = null;
        if ($set === 'en' || $set === 'ar') {
            $locale = $set;
        }
        if ($locale === null) {
            $CI =& get_instance();
            $chosen = (!is_cli() && isset($CI->session)) ? $CI->session->userdata('hkp_locale') : null;
            if ($chosen === 'en' || $chosen === 'ar') {
                $locale = $chosen;
            } elseif (isset($CI->ha_auth) && $CI->ha_auth->check()) {
                $locale = $CI->ha_auth->locale();
            } else {
                $locale = 'en';
            }
        }
        return $locale;
    }

    function hkp_is_rtl() {
        return hkp_locale() === 'ar';
    }

    function hkp_dir() {
        return hkp_is_rtl() ? 'rtl' : 'ltr';
    }

    /** @return array English => Arabic */
    function hkp_dictionary() {
        static $dict = null;
        if ($dict === null) {
            $file = APPPATH . 'language/arabic/hkp_lang.php';
            $dict = is_file($file) ? (array) include $file : array();
        }
        return $dict;
    }

    /**
     * Translates an interface string. {name} placeholders are replaced after
     * translation so Arabic word order is preserved. Output is NOT escaped:
     * wrap in hkp_e() or html_escape() when printing.
     */
    function hkp_t($text, array $vars = array()) {
        $out = $text;
        if (hkp_locale() === 'ar') {
            $dict = hkp_dictionary();
            if (isset($dict[$text]) && $dict[$text] !== '') {
                $out = $dict[$text];
            }
        }
        foreach ($vars as $k => $v) {
            $out = str_replace('{' . $k . '}', (string) $v, $out);
        }
        return $out;
    }

    /** Translate and escape in one step, for text nodes and attributes. */
    function hkp_e($text, array $vars = array()) {
        return htmlspecialchars(hkp_t($text, $vars), ENT_QUOTES, 'UTF-8');
    }

    /** Escape a data value. */
    function hkp_h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Picks the locale column from a bilingual row: hkp_pick($row, 'title')
     * reads title_ar in Arabic and falls back to title_en when the Arabic
     * is empty, so a missing translation shows English rather than nothing.
     */
    function hkp_pick($row, $field) {
        if (!is_array($row)) {
            return '';
        }
        $loc = hkp_locale();
        $primary = isset($row[$field . '_' . $loc]) ? trim((string) $row[$field . '_' . $loc]) : '';
        if ($primary !== '') {
            return $row[$field . '_' . $loc];
        }
        $other = $loc === 'ar' ? 'en' : 'ar';
        return isset($row[$field . '_' . $other]) ? (string) $row[$field . '_' . $other] : (isset($row[$field]) ? (string) $row[$field] : '');
    }

    /** Human label for an enum value such as not_ready -> "Not ready". */
    function hkp_label($value) {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        return hkp_t(ucfirst(str_replace('_', ' ', $value)));
    }

    function hkp_date($value, $with_time = false) {
        if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '—';
        }
        $ts = is_numeric($value) ? (int) $value : strtotime($value);
        if (!$ts) {
            return '—';
        }
        $out = date($with_time ? 'Y-m-d H:i' : 'Y-m-d', $ts);
        return $out;
    }

    function hkp_number($value, $decimals = 0) {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, $decimals);
    }

    function hkp_pct($value, $decimals = 0) {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, $decimals) . '%';
    }

    /** A status pill. Tone is derived from the value so every screen colours statuses the same way. */
    function hkp_badge($status, $label = null) {
        $tones = array(
            'success' => array('ready', 'completed', 'competent', 'exceeds', 'passed', 'published', 'issued', 'active', 'closed', 'on_target', 'signed_off', 'done', 'answered', 'valid', 'compliant', 'released', 'approved'),
            'warning' => array('conditional', 'in_progress', 'developing', 'submitted', 'under_review', 'review', 'internal_review', 'quality_review', 'assigned', 'warning', 'minor', 'moderate', 'pending', 'requested', 'draft', 'in_action', 'awaiting_signoff', 'partial', 'beta', 'no_model', 'at_risk', 'medium', 'high'),
            'danger'  => array('not_ready', 'failed', 'overdue', 'critical', 'rejected', 'not_demonstrated', 'expired', 'revoked', 'open', 'blocked', 'insufficient', 'error', 'non_compliant', 'declined'),
            'muted'   => array('archived', 'superseded', 'waived', 'exempted', 'none', 'not_started', 'cancelled', 'planned', 'retired', 'inactive', 'terminated', 'disabled', 'low', 'info', 'not_applicable'),
        );
        $tone = 'neutral';
        foreach ($tones as $t => $list) {
            if (in_array($status, $list, true)) {
                $tone = $t;
                break;
            }
        }
        return '<span class="hkp-badge hkp-badge--' . $tone . '">' . hkp_h($label !== null ? $label : hkp_label($status)) . '</span>';
    }

    /** Inline SVG icon from the workspace sprite. */
    function hkp_icon($name, $class = '') {
        return '<svg class="hkp-icon ' . hkp_h($class) . '" aria-hidden="true" focusable="false"><use href="#i-' . hkp_h($name) . '"></use></svg>';
    }

    /** A horizontal progress bar with an accessible value. */
    function hkp_bar($pct, $tone = '') {
        $pct = max(0, min(100, (float) $pct));
        return '<span class="hkp-bar ' . ($tone ? 'hkp-bar--' . hkp_h($tone) : '') . '" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . round($pct) . '"><span style="inline-size:' . round($pct, 1) . '%"></span></span>';
    }

    function hkp_url($path = '') {
        return site_url('hkp' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    /**
     * Opening tag for a person's name: a link to the employee record when the
     * viewer may open it (learners.view, same check as the page), plain text
     * otherwise, so executives and assessors never meet a dead link.
     */
    function hkp_person_open($user_id, $class = '') {
        static $can = null;
        if ($can === null) {
            $CI =& get_instance();
            $can = isset($CI->ha_auth) && $CI->ha_auth->has('learners.view');
        }
        $GLOBALS['hkp_person_tag'] = $can ? 'a' : 'span';
        $cls = $class !== '' ? ' class="' . hkp_h($class) . '"' : '';
        return $can ? '<a' . $cls . ' href="' . hkp_url('team/employee/' . (int) $user_id) . '">' : '<span' . $cls . '>';
    }

    function hkp_person_close() {
        return '</' . (isset($GLOBALS['hkp_person_tag']) ? $GLOBALS['hkp_person_tag'] : 'span') . '>';
    }

    /**
     * Allow-list HTML sanitiser for authored content (lessons, knowledge).
     * Authored text is stored as written and rendered through this, so a
     * pasted <script> or on* handler never reaches another user's browser.
     */
    function hkp_safe_html($html) {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }
        if (strpos($html, '<') === false) {
            return nl2br(htmlspecialchars($html, ENT_QUOTES, 'UTF-8'));
        }
        $allowed = array('p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5', 'blockquote',
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'a', 'img', 'figure', 'figcaption', 'span', 'div', 'hr', 'code', 'pre', 'small', 'mark');
        $attrs = array('href', 'src', 'alt', 'title', 'colspan', 'rowspan', 'class', 'dir', 'lang');
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="hkp-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('hkp-root');
        if (!$root) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }
        $walk = function (DOMNode $node) use (&$walk, $allowed, $attrs) {
            for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
                $child = $node->childNodes->item($i);
                if ($child instanceof DOMElement) {
                    $tag = strtolower($child->tagName);
                    if (in_array($tag, array('script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'), true)) {
                        $node->removeChild($child);
                        continue;
                    }
                    if (!in_array($tag, $allowed, true)) {
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }
                    for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                        $attr = $child->attributes->item($a);
                        $name = strtolower($attr->name);
                        if (!in_array($name, $attrs, true)) {
                            $child->removeAttribute($attr->name);
                            continue;
                        }
                        if (($name === 'href' || $name === 'src') && !preg_match('#^(https?:|/|\#|mailto:)#i', trim($attr->value))) {
                            $child->removeAttribute($attr->name);
                        }
                    }
                    if ($tag === 'a') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                    $walk($child);
                }
            }
        };
        $walk($root);
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }
        return $out;
    }
}
