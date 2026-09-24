<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Image rendering for the public academy site.
 *
 * One place decides how a photograph reaches the page, so every image gets
 * the same treatment: the card-sized file on cards and the wide file on
 * heroes, real alt text, intrinsic dimensions so the layout does not jump
 * while it loads, and lazy loading below the fold.
 */

if (!function_exists('ha_image_variant')) {
    /**
     * Picks the stored file for a given display size. Files are written as
     * "<subject>.webp" (wide) and "<subject>-card.webp" (card).
     */
    function ha_image_variant($path, $size = 'card') {
        if (!$path) {
            return null;
        }
        if ($size === 'card') {
            $card = preg_replace('/\.webp$/', '-card.webp', $path);
            if (file_exists(FCPATH . $card)) {
                return $card;
            }
        }
        return file_exists(FCPATH . $path) ? $path : null;
    }
}

if (!function_exists('ha_image')) {
    /**
     * @param string $path    stored path, e.g. uploads/academy/kitchen.webp
     * @param string $alt     alternative text describing the photograph
     * @param array  $options size (card|wide), class, eager (bool), ratio
     */
    function ha_image($path, $alt = '', array $options = array()) {
        $size = isset($options['size']) ? $options['size'] : 'card';
        $file = ha_image_variant($path, $size);
        if (!$file) {
            return '';
        }

        $class = isset($options['class']) ? $options['class'] : 'ha-media__img';
        $eager = !empty($options['eager']);
        $dimensions = @getimagesize(FCPATH . $file);
        $w = $dimensions ? $dimensions[0] : null;
        $h = $dimensions ? $dimensions[1] : null;

        $attributes = array(
            'src="' . html_escape(base_url($file)) . '"',
            'alt="' . html_escape($alt) . '"',
            'class="' . html_escape($class) . '"',
        );
        if ($w && $h) {
            $attributes[] = 'width="' . $w . '"';
            $attributes[] = 'height="' . $h . '"';
        }
        // The hero is the largest contentful paint, so it loads eagerly and
        // gets priority. Everything below the fold waits its turn.
        $attributes[] = $eager ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"';
        $attributes[] = 'decoding="async"';

        return '<img ' . implode(' ', $attributes) . '>';
    }
}

if (!function_exists('ha_media_figure')) {
    /** A card thumbnail wrapped in a fixed-ratio frame, so rows stay aligned. */
    function ha_media_figure($path, $alt = '', array $options = array()) {
        $img = ha_image($path, $alt, $options);
        if ($img === '') {
            return '';
        }
        $modifier = isset($options['modifier']) ? ' ' . $options['modifier'] : '';
        return '<div class="ha-media' . html_escape($modifier) . '">' . $img . '</div>';
    }
}

if (!function_exists('ha_image_alt')) {
    /**
     * Alt text stored with the file. Falls back to an empty string rather than
     * repeating the heading, because a screen reader announcing the same words
     * twice is worse than a decorative image with no description.
     */
    function ha_image_alt($path, $locale = 'en') {
        static $cache = array();
        if ($path === null || $path === '') {
            return '';
        }
        if (!isset($cache[$path])) {
            $CI =& get_instance();
            $row = $CI->db->select('alt_en, alt_ar')
                ->get_where('ha_media', array('file_path' => $path))->row_array();
            $cache[$path] = $row ?: array('alt_en' => '', 'alt_ar' => '');
        }
        $key = ($locale === 'ar') ? 'alt_ar' : 'alt_en';
        return isset($cache[$path][$key]) ? (string) $cache[$path][$key] : '';
    }
}

if (!function_exists('ha_page_art')) {
    /**
     * The photograph that belongs to a page rather than to a record.
     *
     * Listing pages have no thumbnail of their own, and borrowing the first
     * record's picture put a kitchen on the programmes hero purely because
     * "Food Safety Certified" sorts first. This resolves a named subject from
     * the media library instead, so the choice is deliberate and changing it
     * is a one-line edit rather than a re-sort.
     *
     * Falls back through the list and then to nothing, which the hero partial
     * handles by rendering its single-column form.
     *
     * @param  string|array $subject one subject, or several in preference order
     * @return string                path relative to the web root, or ''
     */
    function ha_page_art($subject) {
        $CI = &get_instance();
        $CI->load->database();
        foreach ((array) $subject as $candidate) {
            $row = $CI->db->select('file_path')->where('subject', $candidate)
                ->get('ha_media')->row_array();
            if ($row) {
                return $row['file_path'];
            }
        }
        return '';
    }
}
