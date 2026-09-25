<div class="hkp-head"><div><h1><?php echo hkp_e('Readiness'); ?></h1><p><?php echo hkp_e('Ready, conditional and not ready by department and role. Open any person to see the exact reasons.'); ?></p></div>
<?php if ($this->ha_auth->has('readiness.calculate')): ?><form method="post"><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--ghost"><?php echo hkp_e('Recalculate now'); ?></button></form><?php endif; ?></div>
<section class="hkp-card" style="margin-bottom:1rem"><h2><?php echo hkp_e('Readiness heatmap'); ?></h2>
  <div class="hkp-table-wrap"><table class="hkp-table hkp-heat"><thead><tr><th><?php echo hkp_e('Department'); ?></th><th><?php echo hkp_e('Role'); ?></th><th><?php echo hkp_e('Ready'); ?></th><th><?php echo hkp_e('Conditional'); ?></th><th><?php echo hkp_e('Not ready'); ?></th><th><?php echo hkp_e('Distribution'); ?></th></tr></thead><tbody>
  <?php foreach ($grid as $g): $t = max(1, (int) $g['total']); ?><tr><td><?php echo hkp_h(hkp_pick($g, 'dept')) ?: '—'; ?></td><td><?php echo hkp_h(hkp_pick($g, 'role')); ?></td>
    <td class="c r-ready"><?php echo (int) $g['ready']; ?></td><td class="c r-conditional"><?php echo (int) $g['conditional']; ?></td><td class="c r-not_ready"><?php echo (int) $g['not_ready']; ?></td>
    <td style="min-width:140px"><div class="hkp-stack"><span class="ready" style="inline-size:<?php echo round(100 * $g['ready'] / $t); ?>%"></span><span class="conditional" style="inline-size:<?php echo round(100 * $g['conditional'] / $t); ?>%"></span><span class="not_ready" style="inline-size:<?php echo round(100 * $g['not_ready'] / $t); ?>%"></span></div></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
<section class="hkp-card"><h2><?php echo hkp_e('People'); ?></h2>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Employee'); ?></th><th><?php echo hkp_e('Role'); ?></th><th><?php echo hkp_e('Readiness'); ?></th><th><?php echo hkp_e('Why'); ?></th><th><?php echo hkp_e('Calculated'); ?></th></tr></thead><tbody>
  <?php foreach ($people as $p): $why = array(); foreach ((array) json_decode((string) $p['reasons_json'], true) as $c) { if (empty($c['passed'])) { $why[] = $c['detail']; } } ?>
    <tr><td><?php echo hkp_person_open($p['id']); ?><?php echo hkp_h($p['first_name'] . ' ' . $p['last_name']); ?><?php echo hkp_person_close(); ?></td><td class="hkp-small"><?php echo hkp_h(hkp_pick($p, 'title')); ?></td><td><?php echo $p['status'] ? hkp_badge($p['status']) : hkp_badge('none', hkp_t('Not calculated')); ?></td>
      <td class="hkp-small"><?php echo hkp_h(implode(' · ', array_slice($why, 0, 3))); ?></td><td class="hkp-small"><?php echo hkp_date($p['calculated_at'], true); ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
