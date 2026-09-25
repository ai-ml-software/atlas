<!DOCTYPE html>
<html lang="<?php echo hkp_locale(); ?>" dir="<?php echo hkp_dir(); ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?php echo hkp_e('Certificate verification'); ?> · <?php echo hkp_h(hkp_pick($brand, 'brand_name')); ?></title>
<link rel="stylesheet" href="<?php echo hkp_asset('assets/hkp/hkp.css'); ?>"></head>
<body class="hkp <?php echo hkp_locale() === 'ar' ? 'is-ar' : 'is-en'; ?>">
<main class="hkp-main" style="max-width:760px;margin:2rem auto">
  <div class="hkp-actions" style="justify-content:space-between;margin-bottom:1rem">
    <strong><?php echo hkp_h(hkp_pick($brand, 'brand_name')); ?></strong>
    <a class="hkp-lang" href="?lang=<?php echo hkp_locale() === 'ar' ? 'en' : 'ar'; ?>"><?php echo hkp_locale() === 'ar' ? 'English' : 'العربية'; ?></a>
  </div>
  <section class="hkp-card">
    <h1><?php echo hkp_e('Certificate verification'); ?></h1>
    <form method="get" action="<?php echo site_url('verify'); ?>" class="hkp-actions" style="margin:1rem 0">
      <label class="hkp-sr" for="vc"><?php echo hkp_e('Certificate number'); ?></label>
      <input id="vc" class="hkp-input" style="flex:1" name="code" value="<?php echo hkp_h($code); ?>" placeholder="ALTUS-FOA-2026-000152" required>
      <button class="hkp-btn"><?php echo hkp_e('Verify'); ?></button>
    </form>
    <?php if ($r): ?>
      <?php if ($r['result'] === 'throttled'): ?>
        <div class="hkp-flash hkp-flash--error"><?php echo hkp_e('Too many lookups. Try again in a few minutes.'); ?></div>
      <?php elseif ($r['result'] === 'not_found'): ?>
        <div class="hkp-flash hkp-flash--error" role="alert"><?php echo hkp_e('No certificate matches this number. It may be mistyped, or it was not issued by this platform.'); ?></div>
      <?php else: ?>
        <div class="hkp-flash <?php echo $r['result'] === 'valid' ? 'hkp-flash--ok' : 'hkp-flash--error'; ?>" role="status">
          <?php echo $r['result'] === 'valid' ? hkp_e('Valid certificate') : ($r['result'] === 'expired' ? hkp_e('This certificate has expired') : hkp_e('This certificate has been revoked')); ?></div>
        <dl class="hkp-kv">
          <dt><?php echo hkp_e('Certificate'); ?></dt><dd><?php echo hkp_h(hkp_pick($r, 'title')); ?></dd>
          <dt><?php echo hkp_e('Holder'); ?></dt><dd><?php echo hkp_h(hkp_pick($r, 'holder')); ?></dd>
          <?php if ($r['property_en']): ?><dt><?php echo hkp_e('Property'); ?></dt><dd><?php echo hkp_h(hkp_pick($r, 'property')); ?></dd><?php endif; ?>
          <dt><?php echo hkp_e('Number'); ?></dt><dd><?php echo hkp_h($r['certificate_no']); ?></dd>
          <dt><?php echo hkp_e('Issued'); ?></dt><dd><?php echo hkp_h($r['issued_at']); ?></dd>
          <dt><?php echo hkp_e('Valid until'); ?></dt><dd><?php echo $r['expires_at'] ? hkp_h($r['expires_at']) : hkp_e('No expiry'); ?></dd>
          <dt><?php echo hkp_e('Issuing authority'); ?></dt><dd><?php echo hkp_h(hkp_pick($r, 'issuer')); ?></dd>
        </dl>
      <?php endif; ?>
    <?php endif; ?>
    <p class="hkp-small hkp-muted" style="margin-top:1rem"><?php echo hkp_e('Only the details needed to confirm a certificate are shown. No other personal data is disclosed.'); ?></p>
  </section>
</main>
</body></html>
