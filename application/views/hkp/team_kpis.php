<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('From gut feel to ground truth'); ?></div><h1><?php echo hkp_e('KPIs'); ?></h1>
<p><?php echo hkp_e('Operational KPIs come from entry, CSV import or integrations and always show their period and source. The platform never invents a value.'); ?></p></div>
<?php if ($this->ha_auth->has('imports.run')): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('admin/imports?type=kpi_values'); ?>"><?php echo hkp_icon('upload'); ?> <?php echo hkp_e('Import CSV'); ?></a><?php endif; ?></div>
<h2><?php echo hkp_e('Capability KPIs (calculated from the evidence chain)'); ?></h2>
<div class="hkp-grid hkp-grid--4" style="margin:.5rem 0 1.25rem">
  <?php foreach (array('learning_completion' => 'Learning completion', 'competency_coverage' => 'Competency coverage', 'readiness_rate' => 'Readiness rate', 'certification_rate' => 'Certification rate') as $k => $l): ?>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e($l); ?></span><span class="hkp-tile__value"><?php echo hkp_pct($platform[$k]); ?></span><span class="hkp-tile__foot"><?php echo hkp_e('Live · {t}', array('t' => hkp_date($platform['calculated_at'], true))); ?></span></div>
  <?php endforeach; ?>
</div>
<?php if (!$pid): ?><div class="hkp-empty"><?php echo hkp_e('Choose a property in the top bar to see its operational scorecard.'); ?></div><?php else: ?>
<section class="hkp-card" style="margin-bottom:1rem"><h2><?php echo hkp_e('Operational scorecard'); ?></h2>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('KPI'); ?></th><th><?php echo hkp_e('Category'); ?></th><th class="hkp-num"><?php echo hkp_e('Actual'); ?></th><th class="hkp-num"><?php echo hkp_e('Target'); ?></th><th><?php echo hkp_e('Status'); ?></th><th><?php echo hkp_e('Period'); ?></th><th><?php echo hkp_e('Source'); ?></th><th><?php echo hkp_e('Trend'); ?></th></tr></thead><tbody>
  <?php foreach ($card as $k): $v = $k['value']; ?><tr><td><strong><?php echo hkp_h(hkp_pick($k, 'name')); ?></strong><div class="hkp-small hkp-muted"><?php echo hkp_h(hkp_pick($k, 'definition')); ?></div></td><td class="hkp-small"><?php echo hkp_label($k['category']); ?></td>
    <td class="hkp-num"><?php echo $v ? hkp_number($v['actual'], 2) . ' ' . hkp_h($k['unit']) : '—'; ?></td><td class="hkp-num"><?php echo $v && $v['target'] !== null ? hkp_number($v['target'], 2) : ($k['target'] !== null ? hkp_number($k['target'], 2) : '—'); ?></td>
    <td><?php echo $k['status'] === 'no_data' ? hkp_badge('muted', hkp_t('No data')) : hkp_badge($k['status']); ?><?php if ($k['stale']): ?> <?php echo hkp_badge('warning', hkp_t('Stale')); ?><?php endif; ?></td>
    <td class="hkp-small"><?php echo $v ? hkp_h($v['period_start'] . ' → ' . $v['period_end']) : '—'; ?></td><td class="hkp-small"><?php echo $v ? hkp_label($v['source']) : hkp_label($k['source']); ?></td>
    <td class="hkp-small"><?php foreach ($k['trend'] as $t): ?><?php echo hkp_number($t['actual'], 1); ?> <?php endforeach; ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
<?php if ($this->ha_auth->has('kpis.update')): ?>
<form class="hkp-card hkp-form" method="post"><?php echo ha_csrf_field(); ?><h2><?php echo hkp_e('Record a value'); ?></h2><div class="hkp-row">
  <div class="hkp-field"><label for="kk"><?php echo hkp_e('KPI'); ?></label><select id="kk" class="hkp-select" name="kpi_id"><?php foreach ($defs as $d): ?><option value="<?php echo (int) $d['id']; ?>"><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
  <div class="hkp-field"><label for="ks"><?php echo hkp_e('Period start'); ?></label><input id="ks" class="hkp-input" type="date" name="period_start" value="<?php echo date('Y-m-01'); ?>" required></div>
  <div class="hkp-field"><label for="ke"><?php echo hkp_e('Period end'); ?></label><input id="ke" class="hkp-input" type="date" name="period_end" value="<?php echo date('Y-m-t'); ?>"></div>
  <div class="hkp-field"><label for="ka"><?php echo hkp_e('Actual'); ?></label><input id="ka" class="hkp-input" type="number" step="any" name="actual" required></div>
  <div class="hkp-field"><label for="kt"><?php echo hkp_e('Target'); ?></label><input id="kt" class="hkp-input" type="number" step="any" name="target"></div></div>
  <div><button class="hkp-btn"><?php echo hkp_e('Save value'); ?></button></div></form>
<?php endif; endif; ?>
