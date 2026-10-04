<?php $s = $config['settings']; $dis = $can_edit ? '' : ' disabled'; ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('System'); ?></div><h1><?php echo hkp_e('Mobile app'); ?></h1>
<p><?php echo hkp_e('Everything the iOS and Android app reads at launch: server address, app keys, branding, visible modules, update rules and maintenance. Changes reach phones within five minutes.'); ?></p></div>
<div class="hkp-actions"><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('admin/system'); ?>"><?php echo hkp_e('System'); ?></a></div></div>
<?php if (!$ready): ?><div class="hkp-card" role="alert"><?php echo hkp_e('Run migration 031 (mobile app settings) to enable this screen.'); ?></div><?php else: ?>

<?php if ($new_key): ?>
<section class="hkp-card" data-mobile-new-key style="margin-bottom:1rem"><h2><?php echo hkp_e('New app key'); ?></h2>
  <p class="hkp-small"><?php echo hkp_e('Copy it now. It is stored hashed and will not be shown again.'); ?></p>
  <div class="hkp-row" style="align-items:center;gap:1rem"><code dir="ltr" style="word-break:break-all;user-select:all"><?php echo hkp_h($new_key); ?></code>
  <?php if ($setup_qr): ?><figure style="margin:0"><img src="<?php echo $setup_qr; ?>" width="180" height="180" alt="<?php echo hkp_e('Server setup QR code'); ?>"><figcaption class="hkp-small hkp-muted"><?php echo hkp_e('Scan from the app: Settings, Server setup, Scan QR.'); ?></figcaption></figure><?php endif; ?></div>
</section>
<?php endif; ?>

<section class="hkp-card" style="margin-bottom:1rem" data-mobile-connection data-endpoint="<?php echo hkp_h($endpoint); ?>">
  <h2><?php echo hkp_e('Connect and test the app'); ?></h2>
  <p><a class="hkp-btn hkp-btn--ghost" href="<?php echo base_url('docs/guides/index.html'); ?>"><?php echo hkp_e('User guides and videos'); ?></a></p>
  <p><?php echo hkp_e('Open Settings → Server setup in the app. Enter the platform URL and an altm_ app key, then Test connection and Save. Sign in separately using your personal ha_ key from Account security.'); ?></p>
  <p class="hkp-small"><?php echo hkp_e('For local Android emulator use http://10.0.2.2/atlas/atlas. For a phone connected by USB, run adb reverse tcp:80 tcp:80 and use http://localhost/atlas/atlas. Production requires HTTPS.'); ?></p>
  <label class="hkp-field"><?php echo hkp_e('App key'); ?><input class="hkp-input" type="password" autocomplete="off" data-mobile-test-key placeholder="altm_…"<?php echo $new_key ? ' value="'.hkp_h($new_key).'"' : ''; ?>></label>
  <button class="hkp-btn hkp-btn--ghost" type="button" data-mobile-test><?php echo hkp_e('Test connection'); ?></button>
  <p class="hkp-small" role="status" aria-live="polite" data-mobile-test-status></p>
  <?php if ($new_key): ?><details><summary><?php echo hkp_e('Build environment'); ?></summary><p class="hkp-small"><?php echo hkp_e('Save these values in the mobile project .env file before building. The URL must be reachable from the target device.'); ?></p><pre dir="ltr" style="white-space:pre-wrap;word-break:break-all">EXPO_PUBLIC_PLATFORM_URL=<?php echo hkp_h(rtrim(base_url(), '/')); ?>
EXPO_PUBLIC_ALTUS_APP_KEY=<?php echo hkp_h($new_key); ?></pre></details><?php endif; ?>
</section>

<div class="hkp-grid hkp-grid--4" style="margin-bottom:1rem">
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Config version'); ?></span><span class="hkp-tile__value"><?php echo (int) $config['version']; ?></span><span class="hkp-tile__foot"><?php echo $config['updated_at'] ? hkp_date($config['updated_at'], true) : hkp_e('Defaults (never saved)'); ?></span></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Active app keys'); ?></span><span class="hkp-tile__value"><?php echo count(array_filter($keys, function ($k) { return !$k['revoked_at']; })); ?></span></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Maintenance'); ?></span><span><?php echo $s['maintenance']['enabled'] ? hkp_badge('warning', hkp_t('On')) : hkp_badge('success', hkp_t('Off')); ?></span></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Config endpoint'); ?></span><code class="hkp-small" dir="ltr" style="word-break:break-all"><?php echo hkp_h($endpoint); ?></code></div>
</div>

<div class="hkp-grid hkp-grid--main">
<form class="hkp-card hkp-form" method="post" action="<?php echo hkp_url('admin/mobile/save'); ?>" data-mobile-settings><?php echo ha_csrf_field(); ?>
  <h2><?php echo hkp_e('Connection'); ?></h2>
  <div class="hkp-field"><label for="m-base"><?php echo hkp_e('API base URL'); ?></label><input id="m-base" class="hkp-input" dir="ltr" type="url" name="m[api_base_url]" placeholder="https://altusgulf.com" value="<?php echo hkp_h($s['api_base_url']); ?>"<?php echo $dis; ?>>
    <span class="hkp-help"><?php echo hkp_e('Where the app sends requests after it loads this config. Leave empty to keep the address the app was built or set up with.'); ?></span></div>

  <h2><?php echo hkp_e('Branding'); ?></h2>
  <div class="hkp-row">
    <div class="hkp-field"><label for="m-name"><?php echo hkp_e('App name (English)'); ?></label><input id="m-name" class="hkp-input" name="m[branding][app_name]" maxlength="60" value="<?php echo hkp_h($s['branding']['app_name']); ?>"<?php echo $dis; ?>></div>
    <div class="hkp-field"><label for="m-name-ar"><?php echo hkp_e('App name (Arabic)'); ?></label><input id="m-name-ar" class="hkp-input" dir="rtl" name="m[branding][app_name_ar]" maxlength="60" value="<?php echo hkp_h($s['branding']['app_name_ar']); ?>"<?php echo $dis; ?>></div>
  </div>
  <div class="hkp-row">
    <?php foreach (array('primary_color' => 'Primary colour', 'accent_color' => 'Accent colour', 'background_color' => 'Background colour') as $k => $label): ?>
    <div class="hkp-field"><label for="m-<?php echo $k; ?>"><?php echo hkp_e($label); ?></label><input id="m-<?php echo $k; ?>" class="hkp-input" type="color" name="m[branding][<?php echo $k; ?>]" value="<?php echo hkp_h($s['branding'][$k]); ?>"<?php echo $dis; ?>></div>
    <?php endforeach; ?>
  </div>
  <div class="hkp-row">
    <div class="hkp-field"><label for="m-logo"><?php echo hkp_e('Logo URL'); ?></label><input id="m-logo" class="hkp-input" dir="ltr" type="url" name="m[branding][logo_url]" value="<?php echo hkp_h($s['branding']['logo_url']); ?>"<?php echo $dis; ?>></div>
    <div class="hkp-field"><label for="m-splash"><?php echo hkp_e('Splash image URL'); ?></label><input id="m-splash" class="hkp-input" dir="ltr" type="url" name="m[branding][splash_url]" value="<?php echo hkp_h($s['branding']['splash_url']); ?>"<?php echo $dis; ?>></div>
  </div>
  <span class="hkp-help"><?php echo hkp_e('Images are shown inside the app after launch. The store icon and native launch screen are fixed per release.'); ?></span>

  <h2><?php echo hkp_e('Modules shown in the app'); ?></h2>
  <?php foreach ($features as $f => $label): ?><input type="hidden" name="m[features][<?php echo $f; ?>]" value="0"><label class="hkp-check"><input type="checkbox" name="m[features][<?php echo $f; ?>]" value="1"<?php echo !empty($s['features'][$f]) ? ' checked' : ''; ?><?php echo $dis; ?>> <?php echo hkp_e($label); ?></label><?php endforeach; ?>

  <h2><?php echo hkp_e('Language and support'); ?></h2>
  <div class="hkp-row">
    <div class="hkp-field"><label for="m-lang"><?php echo hkp_e('Default language'); ?></label><select id="m-lang" class="hkp-input" name="m[default_language]"<?php echo $dis; ?>>
      <?php foreach (array('en' => 'English', 'ar' => 'العربية', 'device' => 'Device language') as $v => $l): ?><option value="<?php echo $v; ?>"<?php echo $s['default_language'] === $v ? ' selected' : ''; ?>><?php echo hkp_e($l); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="m-se"><?php echo hkp_e('Support email'); ?></label><input id="m-se" class="hkp-input" dir="ltr" type="email" name="m[support][email]" value="<?php echo hkp_h($s['support']['email']); ?>"<?php echo $dis; ?>></div>
    <div class="hkp-field"><label for="m-sp"><?php echo hkp_e('Support phone'); ?></label><input id="m-sp" class="hkp-input" dir="ltr" type="tel" name="m[support][phone]" value="<?php echo hkp_h($s['support']['phone']); ?>"<?php echo $dis; ?>></div>
    <div class="hkp-field"><label for="m-su"><?php echo hkp_e('Support URL'); ?></label><input id="m-su" class="hkp-input" dir="ltr" type="url" name="m[support][url]" value="<?php echo hkp_h($s['support']['url']); ?>"<?php echo $dis; ?>></div>
  </div>

  <h2><?php echo hkp_e('Versions and store links'); ?></h2>
  <?php foreach (array('ios' => 'iOS', 'android' => 'Android') as $p => $pl): ?>
  <div class="hkp-row">
    <div class="hkp-field"><label for="m-<?php echo $p; ?>-min"><?php echo hkp_h($pl) . ' · ' . hkp_e('Minimum version (force update below)'); ?></label><input id="m-<?php echo $p; ?>-min" class="hkp-input" dir="ltr" name="m[platforms][<?php echo $p; ?>][min_version]" value="<?php echo hkp_h($s['platforms'][$p]['min_version']); ?>"<?php echo $dis; ?>></div>
    <div class="hkp-field"><label for="m-<?php echo $p; ?>-latest"><?php echo hkp_h($pl) . ' · ' . hkp_e('Latest version (suggest update below)'); ?></label><input id="m-<?php echo $p; ?>-latest" class="hkp-input" dir="ltr" name="m[platforms][<?php echo $p; ?>][latest_version]" value="<?php echo hkp_h($s['platforms'][$p]['latest_version']); ?>"<?php echo $dis; ?>></div>
    <div class="hkp-field"><label for="m-<?php echo $p; ?>-store"><?php echo hkp_h($pl) . ' · ' . hkp_e('Store link'); ?></label><input id="m-<?php echo $p; ?>-store" class="hkp-input" dir="ltr" type="url" name="m[platforms][<?php echo $p; ?>][store_url]" value="<?php echo hkp_h($s['platforms'][$p]['store_url']); ?>"<?php echo $dis; ?>></div>
  </div>
  <?php endforeach; ?>

  <h2><?php echo hkp_e('Maintenance'); ?></h2>
  <input type="hidden" name="m[maintenance][enabled]" value="0"><label class="hkp-check"><input type="checkbox" name="m[maintenance][enabled]" value="1"<?php echo $s['maintenance']['enabled'] ? ' checked' : ''; ?><?php echo $dis; ?>> <?php echo hkp_e('Show the maintenance screen instead of the app'); ?></label>
  <div class="hkp-row">
    <div class="hkp-field"><label for="m-mm-en"><?php echo hkp_e('Message (English)'); ?></label><textarea id="m-mm-en" class="hkp-input" rows="2" maxlength="500" name="m[maintenance][message_en]"<?php echo $dis; ?>><?php echo hkp_h($s['maintenance']['message_en']); ?></textarea></div>
    <div class="hkp-field"><label for="m-mm-ar"><?php echo hkp_e('Message (Arabic)'); ?></label><textarea id="m-mm-ar" class="hkp-input" dir="rtl" rows="2" maxlength="500" name="m[maintenance][message_ar]"<?php echo $dis; ?>><?php echo hkp_h($s['maintenance']['message_ar']); ?></textarea></div>
  </div>
  <?php if ($can_edit): ?><div><button class="hkp-btn"><?php echo hkp_e('Save'); ?></button></div><?php endif; ?>
</form>

<section class="hkp-card" data-mobile-keys><h2><?php echo hkp_e('App keys'); ?></h2>
  <p class="hkp-small hkp-muted"><?php echo hkp_e('An app key lets an app build read this configuration only. People still sign in with their own personal key.'); ?></p>
  <?php if ($can_edit): ?>
  <form class="hkp-form" method="post" action="<?php echo hkp_url('admin/mobile/key_create'); ?>"><?php echo ha_csrf_field(); ?>
    <div class="hkp-row"><div class="hkp-field"><label for="k-name"><?php echo hkp_e('Key name'); ?></label><input id="k-name" class="hkp-input" name="name" maxlength="120" required placeholder="Production 1.0"></div>
    <div class="hkp-field"><label for="k-plat"><?php echo hkp_e('Platform'); ?></label><select id="k-plat" class="hkp-input" name="platform"><option value="all"><?php echo hkp_e('All'); ?></option><option value="ios">iOS</option><option value="android">Android</option></select></div></div>
    <div><button class="hkp-btn"><?php echo hkp_e('Generate key'); ?></button></div></form>
  <?php endif; ?>
  <ul class="hkp-list"><?php foreach ($keys as $k): ?><li>
    <span><strong><?php echo hkp_h($k['name']); ?></strong> <code class="hkp-small" dir="ltr">altm_<?php echo hkp_h($k['prefix']); ?>_…</code> <span class="hkp-small hkp-muted"><?php echo hkp_h($k['platform']); ?> · <?php echo $k['last_used_at'] ? hkp_e('Last used') . ' ' . hkp_date($k['last_used_at'], true) : hkp_e('Never used'); ?></span></span>
    <?php if ($k['revoked_at']): ?><?php echo hkp_badge('archived', hkp_t('Revoked')); ?>
    <?php elseif ($can_edit): ?><span class="hkp-actions">
      <form method="post" action="<?php echo hkp_url('admin/mobile/key_rotate/' . (int) $k['id']); ?>"><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--sm hkp-btn--ghost"><?php echo hkp_e('Rotate'); ?></button></form>
      <form method="post" action="<?php echo hkp_url('admin/mobile/key_revoke/' . (int) $k['id']); ?>" onsubmit="return confirm(this.dataset.confirm)" data-confirm="<?php echo hkp_e('Revoke this key? Apps using it stop receiving configuration.'); ?>"><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--sm hkp-btn--danger"><?php echo hkp_e('Revoke'); ?></button></form></span>
    <?php endif; ?></li><?php endforeach; ?>
    <?php if (!$keys): ?><li class="hkp-muted"><?php echo hkp_e('No app keys yet.'); ?></li><?php endif; ?></ul>
</section>
</div>
<?php endif; ?>
<script src="<?php echo base_url('assets/hkp/mobile-settings.js'); ?>" defer></script>
