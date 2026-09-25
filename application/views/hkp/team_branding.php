<?php $v = function ($k) use ($row) { return isset($row[$k]) ? $row[$k] : ''; }; ?>
<div class="hkp-head"><div><h1><?php echo hkp_e('Branding & settings'); ?></h1><p><?php echo hkp_e('Each property can carry its own brand. Empty fields inherit from the organisation, then from the platform.'); ?></p></div>
<div class="hkp-tabs" style="margin:0;border:0"><a href="?scope=property" class="<?php echo $scope === 'property' ? 'is-active' : ''; ?>"><?php echo hkp_e('This property'); ?></a><a href="?scope=organization" class="<?php echo $scope === 'organization' ? 'is-active' : ''; ?>"><?php echo hkp_e('Whole organisation'); ?></a></div></div>
<div class="hkp-grid hkp-grid--main">
<form class="hkp-card hkp-form" method="post" enctype="multipart/form-data"><?php echo ha_csrf_field(); ?>
  <div class="hkp-row">
    <div class="hkp-field"><label for="bn"><?php echo hkp_e('Brand name (English)'); ?></label><input id="bn" class="hkp-input" name="brand_name_en" value="<?php echo hkp_h($v('brand_name_en')); ?>"></div>
    <div class="hkp-field"><label for="bna"><?php echo hkp_e('Brand name (Arabic)'); ?></label><input id="bna" class="hkp-input" dir="rtl" name="brand_name_ar" value="<?php echo hkp_h($v('brand_name_ar')); ?>"></div></div>
  <div class="hkp-row">
    <?php foreach (array('color_primary' => 'Primary colour', 'color_secondary' => 'Ink colour', 'color_accent' => 'Accent colour', 'color_surface' => 'Background colour') as $k => $l): ?>
    <div class="hkp-field"><label for="<?php echo $k; ?>"><?php echo hkp_e($l); ?></label><input id="<?php echo $k; ?>" class="hkp-input" type="color" name="<?php echo $k; ?>" value="<?php echo hkp_h($v($k) ?: $effective[$k]); ?>"></div><?php endforeach; ?></div>
  <div class="hkp-row">
    <div class="hkp-field"><label for="bl"><?php echo hkp_e('Logo'); ?></label><input id="bl" class="hkp-input" type="file" name="logo" accept="image/png,image/jpeg,image/webp"><?php if ($v('logo_path')): ?><img src="<?php echo base_url($v('logo_path')); ?>" alt="" height="40"><?php endif; ?></div>
    <div class="hkp-field"><label for="bf"><?php echo hkp_e('Favicon'); ?></label><input id="bf" class="hkp-input" type="file" name="favicon"></div>
    <div class="hkp-field"><label for="bs"><?php echo hkp_e('Altus branding'); ?></label><select id="bs" class="hkp-select" name="show_altus"><?php foreach (array('both' => 'Show client and Altus', 'client' => 'Client brand only', 'altus' => 'Altus brand only') as $k => $l): ?><option value="<?php echo $k; ?>"<?php echo $v('show_altus') === $k ? ' selected' : ''; ?>><?php echo hkp_e($l); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="bd"><?php echo hkp_e('Custom domain'); ?></label><input id="bd" class="hkp-input" name="custom_domain" value="<?php echo hkp_h($v('custom_domain')); ?>" placeholder="training.clienthotel.com"></div></div>
  <div class="hkp-row">
    <div class="hkp-field"><label for="bw"><?php echo hkp_e('Welcome message (English)'); ?></label><textarea id="bw" class="hkp-input" name="welcome_en" rows="2"><?php echo hkp_h($v('welcome_en')); ?></textarea></div>
    <div class="hkp-field"><label for="bwa"><?php echo hkp_e('Welcome message (Arabic)'); ?></label><textarea id="bwa" class="hkp-input" name="welcome_ar" rows="2" dir="rtl"><?php echo hkp_h($v('welcome_ar')); ?></textarea></div></div>
  <div class="hkp-row">
    <div class="hkp-field"><label for="bsg"><?php echo hkp_e('Certificate signatory'); ?></label><input id="bsg" class="hkp-input" name="signatory_name_en" value="<?php echo hkp_h($v('signatory_name_en')); ?>"></div>
    <div class="hkp-field"><label for="bst"><?php echo hkp_e('Signatory title'); ?></label><input id="bst" class="hkp-input" name="signatory_title_en" value="<?php echo hkp_h($v('signatory_title_en')); ?>"></div>
    <div class="hkp-field"><label for="bef"><?php echo hkp_e('Email and report footer'); ?></label><input id="bef" class="hkp-input" name="email_footer_en" value="<?php echo hkp_h($v('email_footer_en')); ?>"></div></div>
  <div><button class="hkp-btn"><?php echo hkp_e('Save branding'); ?></button></div>
</form>
<section class="hkp-card"><h2><?php echo hkp_e('Preview'); ?></h2>
  <div style="border-radius:12px;overflow:hidden;border:1px solid var(--line)"><div style="background:<?php echo hkp_h($effective['color_secondary']); ?>;color:#fff;padding:1rem;font-weight:700"><?php echo hkp_h(hkp_pick($effective, 'brand_name')); ?></div>
  <div style="background:<?php echo hkp_h($effective['color_surface']); ?>;padding:1rem"><span class="hkp-btn" style="background:<?php echo hkp_h($effective['color_primary']); ?>;border-color:<?php echo hkp_h($effective['color_primary']); ?>"><?php echo hkp_e('Primary action'); ?></span> <span class="hkp-badge" style="background:<?php echo hkp_h($effective['color_accent']); ?>;color:#1c1407"><?php echo hkp_e('Accent'); ?></span></div></div>
  <p class="hkp-small hkp-muted"><?php echo hkp_e('Source: {s}', array('s' => hkp_label($effective['source']))); ?></p></section>
</div>
<?php if ($can_settings && $pid): ?>
<form class="hkp-card hkp-form" method="post" action="<?php echo hkp_url('team/settings'); ?>" style="margin-top:1rem"><?php echo ha_csrf_field(); ?>
  <h2><?php echo hkp_e('Property rules'); ?></h2><p class="hkp-small hkp-muted"><?php echo hkp_e('Leave a field empty to inherit the organisation or platform value.'); ?></p>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Setting'); ?></th><th><?php echo hkp_e('Effective'); ?></th><th><?php echo hkp_e('Property override'); ?></th></tr></thead><tbody>
  <?php foreach ($settings as $k => $s): ?><tr><td><strong><?php echo hkp_e($s['meta'][3]); ?></strong><div class="hkp-small hkp-muted"><?php echo hkp_h($k); ?></div></td><td class="hkp-num"><?php echo hkp_h(is_bool($s['effective']) ? ($s['effective'] ? 'on' : 'off') : $s['effective']); ?></td>
    <td><input class="hkp-input" style="max-width:120px" name="s[<?php echo hkp_h($k); ?>]" value="<?php echo hkp_h($s['override']); ?>" aria-label="<?php echo hkp_h($k); ?>"></td></tr><?php endforeach; ?>
  </tbody></table></div><div><button class="hkp-btn"><?php echo hkp_e('Save rules'); ?></button></div></form>
<?php endif; ?>
