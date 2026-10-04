<?php
$p = array_merge(Ha_content_studio::defaults(), (array) $state['payload']);
$opts = Ha_content_studio::options();
$font_labels = array('serif' => 'Brand display (Fraunces, default)', 'sans-serif' => 'Brand text (Inter, default)', 'georgia' => 'System serif (Georgia)', 'arial' => 'System sans-serif (Arial)', 'fraunces' => 'Fraunces', 'inter' => 'Inter', 'inter-tight' => 'Inter Tight', 'plex-arabic' => 'IBM Plex Sans Arabic');
$colour_labels = array('accent' => 'Brand accent', 'background' => 'Page background', 'ink' => 'Text', 'surface' => 'Cards & surfaces', 'muted' => 'Secondary text', 'line' => 'Borders', 'link' => 'Links', 'footer_background' => 'Footer background');
$text = function ($key, $label, $type = 'text', $help = '') use ($p) { ?>
    <label class="hkp-field" for="theme-<?= hkp_h($key) ?>"><?= hkp_e($label) ?>
        <input class="hkp-input" id="theme-<?= hkp_h($key) ?>" type="<?= hkp_h($type) ?>" name="theme[<?= hkp_h($key) ?>]" value="<?= hkp_h($p[$key]) ?>"<?= $type === 'tel' || $type === 'email' ? ' dir="ltr"' : '' ?>>
        <?php if ($help): ?><small class="hkp-muted"><?= hkp_e($help) ?></small><?php endif; ?>
    </label>
<?php };
$select = function ($key, $label, array $options, array $labels = array()) use ($p) { ?>
    <label class="hkp-field" for="theme-<?= hkp_h($key) ?>"><?= hkp_e($label) ?>
        <select class="hkp-select" id="theme-<?= hkp_h($key) ?>" name="theme[<?= hkp_h($key) ?>]"><?php foreach ($options as $o): ?><option value="<?= hkp_h($o) ?>"<?= $o === $p[$key] ? ' selected' : '' ?>><?= hkp_e($labels[$o] ?? ucfirst(str_replace('-', ' ', $o))) ?></option><?php endforeach; ?></select>
    </label>
<?php };
?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?= hkp_e('Website studio') ?></div><h1><?= hkp_e('Theme & site settings') ?></h1><p><?= hkp_e('Adjust the shared website identity. Save privately, preview and publish after review. Visitors only see the published version.') ?></p></div>
<div class="hkp-actions"><a class="hkp-btn hkp-btn--ghost" href="<?= hkp_url('studio/revisions?type=site') ?>"><?= hkp_e('Revision history') ?></a></div></div>
<div class="studio-review"><form method="post" class="hkp-card hkp-form studio-theme-form" id="studio-theme-form"><?php echo ha_csrf_field(); ?><input type="hidden" name="draft_version" value="<?= (int) $state['version'] ?>"><input type="hidden" name="base_hash" value="<?= hkp_h($state['base_hash']) ?>">
<p class="studio-save-status" role="status"><?= $state['version'] ? hkp_e('Private draft saved, not yet published.') : hkp_e('Showing the published settings.') ?></p>
<fieldset class="studio-fieldset"><legend><?= hkp_e('Brand') ?></legend>
    <?php $text('site_name', 'Site name', 'text', 'Shown in the header, footer and browser titles. Leave empty to keep the system title.'); ?>
    <label class="hkp-field" for="theme-logo"><?= hkp_e('Logo') ?>
        <input class="hkp-input" id="theme-logo" type="text" name="theme[logo]" value="<?= hkp_h($p['logo']) ?>" dir="ltr" placeholder="uploads/...">
        <small class="hkp-muted"><?= hkp_e('PNG, JPEG, WebP or SVG. Leave empty to keep the logos from Branding.') ?></small>
    </label>
    <div class="hkp-actions"><button type="button" class="hkp-btn hkp-btn--sm hkp-btn--ghost" data-theme-pick="theme-logo"><?= hkp_e('Choose from media') ?></button>
        <label class="hkp-btn hkp-btn--sm hkp-btn--ghost studio-file-btn"><?= hkp_e('Upload logo') ?><input type="file" accept=".png,.jpg,.jpeg,.webp" data-theme-upload="theme-logo" class="hkp-visually-hidden"></label></div>
    <?php if ($p['logo'] !== ''): ?><img class="studio-theme-logo" src="<?= hkp_h(preg_match('~^https?://~', $p['logo']) ? $p['logo'] : base_url(ltrim($p['logo'], '/'))) ?>" alt="<?= hkp_e('Current logo') ?>"><?php endif; ?>
</fieldset>
<fieldset class="studio-fieldset"><legend><?= hkp_e('Colours') ?></legend><div class="studio-theme-grid">
    <?php foreach (Ha_content_studio::colour_keys() as $k): ?><label class="hkp-field" for="theme-<?= $k ?>"><?= hkp_e($colour_labels[$k]) ?><input class="hkp-input" id="theme-<?= $k ?>" type="color" name="theme[<?= $k ?>]" value="<?= hkp_h($p[$k]) ?>"></label><?php endforeach; ?>
</div></fieldset>
<fieldset class="studio-fieldset"><legend><?= hkp_e('Typography') ?></legend><div class="studio-theme-grid">
    <?php $select('heading_font', 'Heading font', array_keys(Ha_content_studio::fonts()), $font_labels); $select('body_font', 'Body font', array_keys(Ha_content_studio::fonts()), $font_labels); ?>
</div><p class="hkp-small hkp-muted"><?= hkp_e('Only typefaces already hosted with the website are offered. Arabic pages keep IBM Plex Sans Arabic.') ?></p></fieldset>
<fieldset class="studio-fieldset"><legend><?= hkp_e('Layout & navigation') ?></legend><div class="studio-theme-grid">
    <?php $select('spacing', 'Section spacing', $opts['spacing']); $select('header_style', 'Header height', $opts['header_style']); $select('navigation_style', 'Navigation style', $opts['navigation_style']); $select('footer_style', 'Footer density', $opts['footer_style']); ?>
</div></fieldset>
<fieldset class="studio-fieldset"><legend><?= hkp_e('SEO defaults') ?></legend>
    <?php $text('seo_title_suffix', 'Title suffix', 'text', 'Appended to page titles that do not already include it.'); $text('seo_description', 'Default meta description', 'text', 'Used only where a page has no description of its own.'); ?>
</fieldset>
<fieldset class="studio-fieldset"><legend><?= hkp_e('Contact information') ?></legend><div class="studio-theme-grid">
    <?php $text('contact_email', 'Email', 'email'); $text('contact_phone', 'Phone', 'tel'); ?></div>
    <?php $text('contact_address', 'Address'); ?>
</fieldset>
<div class="hkp-actions"><button class="hkp-btn" type="submit"><?= hkp_e('Save private draft') ?></button><?php if ($state['version'] && $this->ha_auth->has('cms_pages.publish')): ?><button class="hkp-btn hkp-btn--ghost" name="action" value="publish"><?= hkp_e('Publish saved draft') ?></button><?php endif; ?>
<a class="hkp-btn hkp-btn--ghost" href="<?= base_url('en?studio_theme_preview=1') ?>" target="_blank" rel="noopener"><?= hkp_e('Preview website') ?></a><a class="hkp-btn hkp-btn--ghost" href="<?= base_url('ar?studio_theme_preview=1') ?>" target="_blank" rel="noopener" lang="ar"><?= hkp_e('Preview in Arabic') ?></a></div></form>
<section class="hkp-card"><h2><?= hkp_e('Your website, consistently') ?></h2><p><?= hkp_e('Colours, typography and spacing apply across the public website. Header, navigation and footer styles are shared. Edit labels, ordering and visibility in Menus.') ?></p><p class="hkp-muted"><?= hkp_e('Private preview is available only to authorized website editors.') ?></p><a class="hkp-btn hkp-btn--ghost" href="<?= hkp_url('cms/navigation') ?>"><?= hkp_e('Edit menus & footer') ?></a></section></div>
<script src="<?= hkp_asset('assets/hkp/studio-inline.js') ?>" defer></script>
