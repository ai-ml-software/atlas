<?php $checks = $eval['checks']; ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Am I operationally ready?'); ?></div><h1><?php echo hkp_e('My readiness'); ?></h1>
<p><?php echo hkp_e('Policy: {p}', array('p' => hkp_pick($eval['policy'] ?: array(), 'name'))); ?> · <?php echo $r ? hkp_e('Last calculated {t}', array('t' => hkp_date($r['calculated_at'], true))) : hkp_e('Not calculated yet'); ?></p></div>
<span style="font-size:1.4rem"><?php echo hkp_badge($eval['status']); ?></span></div>
<section class="hkp-card">
  <h2><?php echo hkp_e('How this was decided'); ?></h2>
  <p class="hkp-small hkp-muted"><?php echo hkp_e('Every check passes → Ready. Only conditional checks fail → Conditional. Any blocking check fails → Not ready.'); ?></p>
  <div class="hkp-table-wrap"><table class="hkp-table">
    <thead><tr><th><?php echo hkp_e('Check'); ?></th><th><?php echo hkp_e('Result'); ?></th><th><?php echo hkp_e('Detail'); ?></th><th><?php echo hkp_e('If it fails'); ?></th></tr></thead>
    <tbody><?php foreach ($checks as $c): ?>
      <tr><td><strong><?php echo hkp_e($c['label']); ?></strong></td><td><?php echo $c['passed'] ? hkp_badge('success', hkp_t('Pass')) : hkp_badge('danger', hkp_t('Not met')); ?></td>
        <td class="hkp-small"><?php echo hkp_h($c['detail']); ?></td><td><?php echo hkp_badge($c['on_fail']); ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
