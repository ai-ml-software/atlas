<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Explicit click-to-edit markers for the live website editor.
 *
 * Templates call ha_studio('title') on the element that shows a field. The
 * attribute is emitted only while an authorized private preview is rendering
 * (the layout switches the mode on), so visitors never receive editor markup.
 *
 * Field names:
 *   title | subtitle | body | hero_image | cta_label    page or entity copy
 *   s:<studio_key>:<field>                              page-builder section field
 *   s:<studio_key>:items:<index>:<key>                  one item of a section list
 *   c:<block id>:title | c:<block id>:body              corporate homepage block
 * Kinds: text (plain, inline editable), html (rich, inline editable), image (opens media picker).
 */
if (!function_exists('ha_studio_mode')) {
    function ha_studio_mode($on = null) {
        static $mode = false;
        if ($on !== null) { $mode = (bool) $on; }
        return $mode;
    }
}
if (!function_exists('ha_studio')) {
    function ha_studio($field, $kind = 'text') {
        if (!ha_studio_mode()) { return ''; }
        $kind = in_array($kind, array('text', 'html', 'image'), true) ? $kind : 'text';
        return ' data-studio-field="' . htmlspecialchars((string) $field, ENT_QUOTES, 'UTF-8') . '" data-studio-kind="' . $kind . '"';
    }
}
