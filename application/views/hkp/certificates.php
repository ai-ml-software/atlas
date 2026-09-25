<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Evidence of progress'); ?></div><h1><?php echo hkp_e('My certificates'); ?></h1>
<p><?php echo hkp_e('Certificates are issued only when learning, assessment, practical competency and readiness are all evidenced. Each one can be verified publicly.'); ?></p></div></div>
<section class="hkp-card" style="margin-bottom:1rem">
  <?php if (!$rows): ?><div class="hkp-empty"><?php echo hkp_e('No certificates yet.'); ?></div><?php else: ?>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Certificate'); ?></th><th><?php echo hkp_e('Number'); ?></th><th><?php echo hkp_e('Issued'); ?></th><th><?php echo hkp_e('Expires'); ?></th><th><?php echo hkp_e('Status'); ?></th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $c): ?><tr><td><a href="<?php echo hkp_url('certificates/view/' . $c['id']); ?>"><strong><?php echo hkp_h(hkp_pick($c, 'subject_title')); ?></strong></a></td><td class="hkp-small"><?php echo hkp_h($c['certificate_no']); ?></td>
    <td class="hkp-small"><?php echo hkp_date($c['issued_at']); ?></td><td class="hkp-small"><?php echo hkp_date($c['expires_at']); ?></td><td><?php echo hkp_badge($c['status']); ?></td>
    <td><a class="hkp-btn hkp-btn--sm hkp-btn--ghost" href="<?php echo hkp_url('certificates/pdf/' . $c['id']); ?>"><?php echo hkp_icon('download'); ?> PDF</a></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<?php foreach ($programs as $p): $met = count(array_filter($p['checks'], function ($c) { return $c['passed']; })); ?>
<section class="hkp-card" style="margin-bottom:1rem">
  <h2><?php echo hkp_h(hkp_pick($p, 'title')); ?> <span class="hkp-small hkp-muted">· <?php echo hkp_e('{m} of {n} requirements met', array('m' => $met, 'n' => count($p['checks']))); ?></span></h2>
  <?php echo hkp_bar(count($p['checks']) ? 100 * $met / count($p['checks']) : 0, 'accent'); ?>
  <ul class="hkp-list" style="margin-top:.6rem"><?php foreach ($p['checks'] as $c): ?><li><span><?php echo hkp_label($c['rule']); ?>: <strong><?php echo hkp_h($c['label']); ?></strong> <span class="hkp-small hkp-muted"><?php echo hkp_h($c['detail']); ?></span></span><?php echo $c['passed'] ? hkp_badge('success', hkp_t('Met')) : hkp_badge('danger', hkp_t('Not met')); ?></li><?php endforeach; ?></ul>
</section>
<?php endforeach; ?>
