<div class="hkp-head hkp-noprint"><div><div class="hkp-eyebrow"><?php echo hkp_e('Certificate'); ?></div><h1><?php echo hkp_h($c['certificate_no']); ?></h1></div>
<div class="hkp-actions"><a class="hkp-btn" href="<?php echo hkp_url('certificates/pdf/' . $c['id']); ?>"><?php echo hkp_icon('download'); ?> <?php echo hkp_e('Download PDF'); ?></a><button class="hkp-btn hkp-btn--ghost" onclick="window.print()"><?php echo hkp_e('Print'); ?></button></div></div>
<div class="hkp-cert">
  <div class="hkp-eyebrow"><?php echo hkp_h(hkp_pick($brand, 'brand_name')); ?></div>
  <div class="hkp-small hkp-muted"><?php echo hkp_e('Certificate of competence'); ?> · شهادة كفاءة</div>
  <h1><?php echo hkp_h($c['recipient_name_en']); ?></h1>
  <?php if ($c['recipient_name_ar']): ?><div style="font-size:1.4rem;font-family:var(--font-arabic)" lang="ar" dir="rtl"><?php echo hkp_h($c['recipient_name_ar']); ?></div><?php endif; ?>
  <p class="hkp-muted"><?php echo hkp_e('has demonstrated the knowledge, practical competency and readiness required for'); ?></p>
  <h2 style="font-size:1.4rem"><?php echo hkp_h($c['subject_title_en']); ?></h2>
  <div lang="ar" dir="rtl" style="font-family:var(--font-arabic);font-size:1.15rem"><?php echo hkp_h($c['subject_title_ar']); ?></div>
  <p class="hkp-small"><?php echo hkp_h(implode(' · ', array_filter(array(hkp_pick($c, 'role_title'), hkp_pick($c, 'domain_title'), $property ? hkp_pick($property, 'name') : null)))); ?></p>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;margin-top:1rem;text-align:start">
    <dl class="hkp-kv"><dt><?php echo hkp_e('Issued'); ?></dt><dd><?php echo hkp_date($c['issued_at']); ?></dd><dt><?php echo hkp_e('Valid until'); ?></dt><dd><?php echo $c['expires_at'] ? hkp_date($c['expires_at']) : hkp_e('No expiry'); ?></dd>
      <dt><?php echo hkp_e('Issuing authority'); ?></dt><dd><?php echo hkp_h(hkp_pick($c, 'issuer') ?: 'Altus Advisory'); ?></dd><dt><?php echo hkp_e('Status'); ?></dt><dd><?php echo hkp_badge($c['status']); ?></dd></dl>
    <div style="text-align:center"><div style="width:130px"><?php echo $qr; ?></div><div class="hkp-small hkp-muted"><?php echo hkp_e('Scan to verify'); ?></div></div>
  </div>
</div>
<section class="hkp-card hkp-noprint" style="margin-top:1rem">
  <h2><?php echo hkp_e('Evidence this certificate was based on'); ?></h2>
  <p class="hkp-small hkp-muted"><?php echo hkp_e('Frozen at issue so the record stays auditable. Public link: {u}', array('u' => $verify_url)); ?></p>
  <ul class="hkp-list"><?php foreach ($evidence as $e): ?><li><span><?php echo hkp_label($e['rule']); ?>: <strong><?php echo hkp_h($e['label']); ?></strong> <span class="hkp-small hkp-muted"><?php echo hkp_h($e['detail']); ?></span></span><?php echo hkp_badge($e['passed'] ? 'success' : 'danger', $e['passed'] ? hkp_t('Met') : hkp_t('Not met')); ?></li><?php endforeach; ?></ul>
</section>
