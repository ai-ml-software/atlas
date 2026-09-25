<?php $p = $o['property']; ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Pre-opening'); ?></div><h1><?php echo hkp_e('Property opening readiness'); ?></h1>
<p><?php echo hkp_h(hkp_pick($p, 'name')); ?><?php if ($o['countdown'] !== null): ?> · <?php echo hkp_e('Opening {d}', array('d' => $p['opening_date'])); ?> · <strong>T-<?php echo max(0, (int) $o['countdown']); ?></strong><?php endif; ?> · <?php echo hkp_e('{n} staff', array('n' => $o['staff'])); ?></p></div>
<span style="font-size:1.3rem"><?php echo hkp_badge($o['overall']); ?></span></div>
<div class="hkp-grid hkp-grid--4" style="margin-bottom:1rem">
  <?php foreach ($o['categories'] as $c => $cat): if (!$cat['items']) continue; ?>
    <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_label($c); ?></span><span class="hkp-tile__value"><?php echo hkp_pct($cat['pct']); ?></span><?php echo hkp_bar($cat['pct'], $cat['status'] === 'ready' ? 'ok' : ($cat['status'] === 'not_ready' ? 'bad' : 'accent')); ?><span class="hkp-tile__foot"><?php echo hkp_badge($cat['status']); ?></span></div>
  <?php endforeach; ?>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Critical gaps'); ?></span><span class="hkp-tile__value"><?php echo (int) $o['critical_gaps']; ?></span></div>
</div>
<p class="hkp-small hkp-muted"><?php echo hkp_e('Rule: every category at target → Ready; every category at or above {m}% of target with no critical item short → Conditional; otherwise Not ready. Training, competency, SOP and certification figures are calculated from the platform.', array('m' => $o['conditional_min'])); ?></p>
<form class="hkp-card" method="post"><?php echo ha_csrf_field(); ?>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Category'); ?></th><th><?php echo hkp_e('Item'); ?></th><th class="hkp-num"><?php echo hkp_e('Required'); ?></th><th class="hkp-num"><?php echo hkp_e('Current'); ?></th><th><?php echo hkp_e('Due'); ?></th><th><?php echo hkp_e('Notes'); ?></th></tr></thead><tbody>
  <?php foreach ($o['categories'] as $c => $cat): foreach ($cat['items'] as $it): ?>
    <tr><td><?php echo hkp_label($c); ?></td><td><?php echo hkp_h(hkp_pick($it, 'label')); ?> <?php echo (int) $it['is_critical'] ? hkp_badge('danger', hkp_t('Critical')) : ''; ?><?php if (!empty($it['calculated'])): ?> <span class="hkp-small hkp-muted">(<?php echo hkp_e('calculated'); ?>)</span><?php endif; ?></td>
      <td class="hkp-num"><input class="hkp-input" style="max-width:90px" type="number" step="0.1" name="item[<?php echo (int) $it['id']; ?>][required]" value="<?php echo (float) $it['required_value']; ?>" aria-label="<?php echo hkp_e('Required'); ?>"></td>
      <td class="hkp-num"><input class="hkp-input" style="max-width:90px" type="number" step="0.1" name="item[<?php echo (int) $it['id']; ?>][current]" value="<?php echo (float) $it['current_value']; ?>"<?php echo !empty($it['calculated']) ? ' readonly' : ''; ?> aria-label="<?php echo hkp_e('Current'); ?>"></td>
      <td><input class="hkp-input" type="date" name="item[<?php echo (int) $it['id']; ?>][due]" value="<?php echo hkp_h($it['due_date']); ?>" aria-label="<?php echo hkp_e('Due'); ?>"></td>
      <td><input class="hkp-input" name="item[<?php echo (int) $it['id']; ?>][notes]" value="<?php echo hkp_h($it['notes']); ?>" aria-label="<?php echo hkp_e('Notes'); ?>"></td></tr>
  <?php endforeach; endforeach; ?></tbody></table></div>
  <h3 style="margin-top:1rem"><?php echo hkp_e('Add item'); ?></h3>
  <div class="hkp-row" style="display:grid;gap:.6rem;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <select class="hkp-select" name="new_category" aria-label="<?php echo hkp_e('Category'); ?>"><?php foreach (Ha_readiness::opening_categories() as $c): ?><option value="<?php echo $c; ?>"><?php echo hkp_label($c); ?></option><?php endforeach; ?></select>
    <input class="hkp-input" name="new_label" placeholder="<?php echo hkp_e('Item (English)'); ?>" aria-label="<?php echo hkp_e('Item (English)'); ?>"><input class="hkp-input" name="new_label_ar" dir="rtl" placeholder="<?php echo hkp_e('Item (Arabic)'); ?>" aria-label="<?php echo hkp_e('Item (Arabic)'); ?>">
    <input class="hkp-input" type="number" name="new_required" value="100" aria-label="<?php echo hkp_e('Required'); ?>"><label class="hkp-check"><input type="checkbox" name="new_critical" value="1"> <?php echo hkp_e('Critical'); ?></label></div>
  <div style="margin-top:1rem"><button class="hkp-btn"><?php echo hkp_e('Save'); ?></button></div>
</form>
