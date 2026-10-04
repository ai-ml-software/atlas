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
/*
 * Global site settings from the Website Studio (published version only; an
 * authorized editor previewing the theme sees the private draft). Empty
 * settings leave the values above untouched.
 */
$ha_site_settings = null;
$ha_site_contact = array();
if (get_instance()->db->table_exists('ha_site_studio')) {
    get_instance()->load->library('ha_content_studio');
    $ha_site_settings = get_instance()->ha_content_studio->site_settings(get_instance()->input->get('studio_theme_preview') === '1');
}
if ($ha_site_settings) {
    if ($ha_site_settings['site_name'] !== '') { $ha_brand_name = $ha_site_settings['site_name']; }
    if ($ha_site_settings['logo'] !== '') {
        $ha_studio_logo = preg_match('~^https?://~', $ha_site_settings['logo']) ? $ha_site_settings['logo'] : base_url(ltrim($ha_site_settings['logo'], '/'));
        $ha_logo_header = $ha_logo_footer = $ha_studio_logo;
    }
    foreach (array('contact_email', 'contact_phone', 'contact_address') as $ha_k) {
        if ($ha_site_settings[$ha_k] !== '') { $ha_site_contact[$ha_k] = $ha_site_settings[$ha_k]; }
    }
    if (isset($seo) && is_object($seo)) {
        if ($ha_site_settings['seo_description'] !== '' && trim((string) $seo->get('description')) === '') { $seo->set('description', $ha_site_settings['seo_description']); }
        if ($ha_site_settings['seo_title_suffix'] !== '') {
            $ha_t = (string) $seo->get('title');
            if ($ha_t !== '' && strpos($ha_t, $ha_site_settings['seo_title_suffix']) === false) { $seo->set('title', $ha_t . ' ' . $ha_site_settings['seo_title_suffix']); }
        }
    }
}
