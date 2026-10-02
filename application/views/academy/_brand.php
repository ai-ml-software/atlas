<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$ha_brand_name = trim((string) get_settings('system_title'));
if ($ha_brand_name === '') {
    $ha_brand_name = (isset($seo) && is_object($seo) ? $seo->brand() : 'Altus Gulf');
}
$ha_logo_header = trim((string) get_frontend_settings('dark_logo'));
$ha_logo_footer = trim((string) get_frontend_settings('light_logo'));
$ha_logo_header = ($ha_logo_header !== '' && file_exists(FCPATH . 'uploads/system/' . $ha_logo_header))
    ? base_url('uploads/system/' . $ha_logo_header) : '';
$ha_logo_footer = ($ha_logo_footer !== '' && file_exists(FCPATH . 'uploads/system/' . $ha_logo_footer))
    ? base_url('uploads/system/' . $ha_logo_footer) : '';
$ha_logo_small = trim((string) get_frontend_settings('small_logo'));
$ha_logo_small = ($ha_logo_small !== '' && file_exists(FCPATH . 'uploads/system/' . $ha_logo_small))
    ? base_url('uploads/system/' . $ha_logo_small) : '';
$ha_favicon = trim((string) get_frontend_settings('favicon'));
$ha_favicon = ($ha_favicon !== '' && file_exists(FCPATH . 'uploads/system/' . $ha_favicon))
    ? base_url('uploads/system/' . $ha_favicon) : '';
$ha_touch_icon = file_exists(FCPATH . 'uploads/system/apple-touch-icon.png')
    ? base_url('uploads/system/apple-touch-icon.png') : $ha_favicon;
$ha_custom_css = trim((string) get_frontend_settings('custom_css'));
