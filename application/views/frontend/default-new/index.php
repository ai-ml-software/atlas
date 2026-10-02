<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$ci = get_instance();
$ci->load->library('ha_site_layout');
$ci->load->helper('ha_security');
extract($ci->ha_site_layout->data($ci->ha_site_layout->legacy_locale()));
$language_dir = $dir;
$is_rtl = $rtl;
$view = 'legacy';
$is_auth_page = in_array($page_name, Ha_site_layout::account_pages(), true);
$account_titles = $is_auth_page ? Ha_site_layout::account_titles() : array();
$query = $ci->input->get(null, false) ?: array();
unset($query['lang']);
$lang_links = array();
foreach ($site_locales as $lc) {
    $lang_links[$lc] = site_url(uri_string()) . '?' . http_build_query(array('lang'=>$lc) + $query);
}
include APPPATH . 'views/academy/_brand.php';
if ($is_auth_page) {
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
}
?><!DOCTYPE html>
<html lang="<?= html_escape($locale) ?>" dir="<?= $dir ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($is_auth_page): ?>
        <title><?= html_escape($account_titles[$page_name] . ' | ' . $ha_brand_name) ?></title>
        <meta name="robots" content="noindex, nofollow">
        <script src="<?= base_url('assets/global/js/jquery-3.6.1.min.js') ?>"></script>
    <?php else: ?>
        <?php include 'seo.php'; include 'includes_top.php'; ?>
    <?php endif; ?>
    <?php if ($ha_favicon !== ''): ?>
        <link rel="icon" href="<?= html_escape($ha_favicon) ?>">
        <link rel="apple-touch-icon" href="<?= html_escape($ha_touch_icon) ?>">
    <?php endif; ?>
    <?php include APPPATH . 'views/academy/_styles.php'; ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/account.css') ?>?v=<?= filemtime(FCPATH.'assets/academy/account.css') ?>">
    <?php if ($ha_custom_css !== ''): ?><style><?= $ha_custom_css ?></style><?php endif; ?>
</head>
<body class="ha ha--<?= html_escape($locale) ?> ha--legacy<?= $rtl ? ' ha--rtl' : '' ?><?= in_array($locale,array('en','tl'),true) ? '' : ' ha--intl' ?><?= $is_auth_page ? ' ha--account' : '' ?>">
<a class="ha-skip" href="#ha-main"><?= html_escape($t['skip_to_content']) ?></a>
<?php
$my_wishlist_items = array();
if ($user_id = $ci->session->userdata('user_id')) {
    $wishlist = $ci->user_model->get_all_user($user_id)->row('wishlist');
    $my_wishlist_items = json_decode($wishlist ?: '[]', true) ?: array();
}
if ($ci->session->userdata('app_url')) { include 'go_back_to_mobile_app.php'; }
if (!isset($home)) { $home = $ci->db->where('status',1)->get('home_pages')->row_array(); }
include APPPATH . 'views/academy/_header.php';
?>
<main id="ha-main" class="ha-main<?= $is_auth_page ? '' : ' ha-legacy-content' ?>">
<?php
if ($is_auth_page) { include APPPATH . 'views/academy/account.php'; }
elseif ($page_name === null) { include $path; }
else { include $page_name . '.php'; }
?>
</main>
<?php
include APPPATH . 'views/academy/_footer.php';
include APPPATH . 'views/academy/_cookie.php';
include APPPATH . 'views/academy/_scripts.php';
if ($is_auth_page): ?>
<script defer src="<?= base_url('assets/academy/account.js') ?>?v=<?= filemtime(FCPATH.'assets/academy/account.js') ?>"></script>
<?php else:
include 'includes_bottom.php'; include 'modal.php'; include 'common_scripts.php'; include 'init.php'; ?>
<script>jQuery(function(){ if(typeof WOW==='function') new WOW().init(); });</script>
<?php endif; ?>
<?= get_frontend_settings('embed_code') ?>
</body>
</html>
