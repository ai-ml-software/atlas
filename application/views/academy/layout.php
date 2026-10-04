<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Public academy layout.
 *
 * The Arabic version is a genuine right-to-left document: dir is set on the
 * html element, the logical CSS properties do the mirroring, and the font
 * stack changes with the language. Plan sections 40 and 43.
 */
$is_rtl = $rtl;
$alt = $seo->get('alternate_path');
// Same page in every published site language (languages without their own slug use the English path).
$lang_links = array();
foreach ($site_locales as $lc) {
    $p = is_array($alt) ? (isset($alt[$lc]) ? $alt[$lc] : (isset($alt['en']) ? $alt['en'] : '')) : $alt;
    $lang_links[$lc] = base_url($lc . ($p === '' ? '' : '/' . $p));
}

/*
 * These pages used to read nothing from the admin panel, which is why
 * uploading a logo there appeared to do nothing: the upload was saved
 * correctly and this layout drew a hardcoded SVG instead. Everything an
 * administrator can change about the brand is resolved here, once, with the
 * built-in mark as the fallback when nothing has been uploaded.
 */
include APPPATH . 'views/academy/_brand.php';
?><!DOCTYPE html>
<html lang="<?= $locale ?>" dir="<?= $dir ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $seo->render_head() ?>

    <?php if ($ha_favicon !== ''): ?>
    <?php /* Read from the administrator's setting, like the logos. */ ?>
    <link rel="icon" type="image/png" href="<?= html_escape($ha_favicon) ?>">
    <link rel="apple-touch-icon" href="<?= html_escape($ha_touch_icon) ?>">
    <?php endif; ?>
    <?php if (!empty($hero_preload)): ?>
    <?php /* The LCP element on every page that has a hero. See Academy::hero_preload. */ ?>
    <link rel="preload" as="image" fetchpriority="high" href="<?= html_escape($hero_preload) ?>">
    <?php endif; ?>
    <?php
    /*
     * Typefaces are self-hosted (see .lab/fetch_fonts.py): no render-blocking
     * request to a third-party host, and the files are versioned with the code
     * that depends on them.
     *
     * The fonts are deliberately NOT preloaded. They were, until the fallbacks
     * in altus-fonts.css were metric-matched to them: with the swap now
     * invisible (measured CLS 0.000, down from 0.191), an early font costs the
     * page more than it saves. On a slow connection two preloaded faces
     * compete for bandwidth with the hero photograph, which is the actual LCP
     * element -- the home page measured 4.1s that way against 2.1s for pages
     * carrying fewer faces. The image is preloaded above instead, and the text
     * paints immediately in a fallback that occupies the same space.
     */
    ?>
<?php include APPPATH . 'views/academy/_styles.php'; ?>
    <?php if ($ha_custom_css !== ''): ?>
        <style><?= $ha_custom_css ?></style>
    <?php endif; ?>
<link rel="stylesheet" href="<?= site_url('publisher_theme/css') ?><?= (!empty($studio_is_preview) || $this->input->get('studio_theme_preview')==='1') ? '?preview=1' : '' ?>">
</head>
<body class="ha ha--<?= $locale ?><?= $is_rtl ? ' ha--rtl' : '' ?><?= in_array($locale, array('en', 'tl'), true) ? '' : ' ha--intl' ?><?= in_array($view, array('home_altus', 'profile_book'), true) ? ' ha--overlay' : '' ?>">

<a class="ha-skip" href="#ha-main"><?= html_escape($t['skip_to_content']) ?></a>

<?php include APPPATH . 'views/academy/_header.php'; ?>
<?php $crumbs = $seo->breadcrumbs(); if (count($crumbs) > 1): ?>
<nav class="ha-crumbs" aria-label="Breadcrumb">
    <div class="ha-shell">
        <ol>
            <?php foreach ($crumbs as $i => $crumb): $last = ($i === count($crumbs) - 1); ?>
                <li>
                    <?php if ($last): ?>
                        <span aria-current="page"><?= html_escape($crumb['label']) ?></span>
                    <?php else: ?>
                        <a href="<?= base_url($locale . ($crumb['path'] === '' ? '' : '/' . $crumb['path'])) ?>"><?= html_escape($crumb['label']) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</nav>
<?php endif; ?>

<main id="ha-main" class="ha-main">
    <?php
    // Keep native listings/forms intact while allowing administrators to customise the introduction and all added sections.
    // Editable elements carry explicit ha_studio() markers in their templates (see ha_studio_helper);
    // the markers are emitted only while an authorized private preview renders.
    $this->load->helper('ha_studio');
    ha_studio_mode(!empty($studio_is_preview));
    $this->load->view('academy/' . $view, get_defined_vars());
    if (!empty($studio_page_copy['body']) && !in_array($view, array('page', 'home'), true)): ?><section class="ha-section"><div<?= ha_studio('body', 'html') ?> class="ha-shell ha-prose"><?php echo hkp_safe_html($studio_page_copy['body']); ?></div></section><?php endif;
    if (!empty($studio_sections)) { $this->load->view('academy/_sections', array('sections' => $studio_sections, 'locale' => $locale, 'studio_is_preview' => !empty($studio_is_preview))); }
    ?>
</main>
<?php if (!empty($studio_edit_url) && empty($studio_is_preview)): ?><a class="ha-studio-edit" href="<?php echo html_escape($studio_edit_url); ?>" style="position:fixed;bottom:24px;inset-inline-end:24px;z-index:100;padding:12px 18px;background:#a84d27;color:white;border-radius:8px;text-decoration:none;box-shadow:0 5px 20px #0002;font:600 13px sans-serif"><?php echo ha_pe('Edit page'); ?> ↗</a><?php endif; ?>

<?php include APPPATH . 'views/academy/_footer.php'; ?>
  <?php if (empty($studio_is_preview)) { include APPPATH . 'views/academy/_cookie.php'; } ?>


<?php include APPPATH . 'views/academy/_scripts.php'; ?>
</body>
</html>
