<div class="hkp-head"><div><h1><?php echo hkp_e('Profile'); ?></h1><p><?php echo hkp_h(trim($me['first_name'] . ' ' . $me['last_name'])); ?> · <?php echo hkp_h($me['email']); ?></p></div></div>
<form class="hkp-grid hkp-grid--main" method="post"><?php echo ha_csrf_field(); ?>
  <section class="hkp-card hkp-form">
    <h2><?php echo hkp_e('Preferences'); ?></h2>
    <div class="hkp-row">
      <div class="hkp-field"><label for="pl"><?php echo hkp_e('Language'); ?></label><select id="pl" class="hkp-select" name="locale"><option value="en"<?php echo $p && $p['locale'] === 'en' ? ' selected' : ''; ?>>English</option><option value="ar"<?php echo $p && $p['locale'] === 'ar' ? ' selected' : ''; ?>>العربية</option></select></div>
      <div class="hkp-field"><label for="ptz"><?php echo hkp_e('Timezone'); ?></label><select id="ptz" class="hkp-select" name="timezone"><?php foreach (array('Asia/Riyadh', 'Asia/Dubai', 'Asia/Qatar', 'Asia/Kuwait', 'Asia/Bahrain', 'Asia/Muscat', 'Africa/Cairo', 'Asia/Amman', 'Europe/London') as $tz): ?><option<?php echo $p && $p['timezone'] === $tz ? ' selected' : ''; ?>><?php echo $tz; ?></option><?php endforeach; ?></select></div>
    </div>
    <h2 style="margin-top:.5rem"><?php echo hkp_e('Notifications'); ?></h2>
    <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Event'); ?></th><th><?php echo hkp_e('In app'); ?></th><th><?php echo hkp_e('Email'); ?></th></tr></thead><tbody>
    <?php foreach ($events as $code => $e): $pr = isset($prefs[$code]) ? $prefs[$code] : null; ?>
      <tr><td class="hkp-small"><?php echo hkp_label(str_replace('.', '_', $code)); ?></td>
        <td><input type="checkbox" name="pref[<?php echo $code; ?>][in_app]" value="1" aria-label="<?php echo hkp_e('In app'); ?>"<?php echo !$pr || (int) $pr['in_app'] ? ' checked' : ''; ?>></td>
        <td><input type="checkbox" name="pref[<?php echo $code; ?>][email]" value="1" aria-label="<?php echo hkp_e('Email'); ?>"<?php echo !$pr || (int) $pr['email'] ? ' checked' : ''; ?>></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <div><button class="hkp-btn"><?php echo hkp_e('Save'); ?></button></div>
  </section>
  <section class="hkp-card">
    <h2><?php echo hkp_e('My role'); ?></h2>
    <dl class="hkp-kv">
      <dt><?php echo hkp_e('Job role'); ?></dt><dd><?php echo $role ? hkp_h(hkp_pick($role, 'title')) : '—'; ?></dd>
      <dt><?php echo hkp_e('Property'); ?></dt><dd><?php echo $property ? hkp_h(hkp_pick($property, 'name')) : '—'; ?></dd>
      <dt><?php echo hkp_e('Employee number'); ?></dt><dd><?php echo hkp_h($p ? $p['employee_no'] : '') ?: '—'; ?></dd>
      <dt><?php echo hkp_e('Access'); ?></dt><dd><?php foreach ($grants as $g): ?><?php echo hkp_badge('neutral', hkp_label($g)); ?> <?php endforeach; ?></dd>
    </dl>
    <p style="margin-top:1rem"><a href="<?php echo site_url('account_security'); ?>"><?php echo hkp_e('Two-factor sign-in and API keys'); ?> →</a></p>
  </section>
</form>
