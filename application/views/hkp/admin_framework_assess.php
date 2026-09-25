<?php $f = $a['framework']; $r = $a['result']; $max = (int) $f['scale_max']; ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_h(hkp_pick($f, 'name')); ?> · <?php echo hkp_h(hkp_pick($a, 'org')); ?><?php echo $a['prop_en'] ? ' · ' . hkp_h(hkp_pick($a, 'prop')) : ''; ?></div><h1><?php echo hkp_h($a['title']); ?></h1></div>
<?php echo $a['result_label'] ? '<span style="font-size:1.2rem">' . hkp_badge('success', hkp_label($a['result_label'])) . '</span>' : hkp_badge($a['status']); ?></div>
<?php if ($r): ?>
<div class="hkp-grid hkp-grid--2" style="margin-bottom:1rem">
  <?php if ($f['code'] === 'performance_matrix' && isset($r['axes']['operational'], $r['axes']['digital'])): $x = 100 * $r['axes']['digital'] / $max; $y = 100 * $r['axes']['operational'] / $max; ?>
  <section class="hkp-card"><h2><?php echo hkp_e('Position on the Performance Matrix'); ?></h2>
    <div class="hkp-quad" role="img" aria-label="<?php echo hkp_e('Operational rigour {o}, digital and commercial intelligence {d}', array('o' => $r['axes']['operational'], 'd' => $r['axes']['digital'])); ?>">
      <div class="<?php echo $r['label'] === 'legacy_operator' ? 'is-here' : ''; ?>"><?php echo hkp_e('Legacy Operator'); ?><br><span class="hkp-small hkp-muted"><?php echo hkp_e('Sound operations, analogue commercial engine'); ?></span></div>
      <div class="<?php echo $r['label'] === 'altus_zone' ? 'is-here' : ''; ?>"><?php echo hkp_e('The Altus Zone'); ?><br><span class="hkp-small hkp-muted"><?php echo hkp_e('Operational mastery × digital intelligence'); ?></span></div>
      <div class="<?php echo $r['label'] === 'undermanaged_asset' ? 'is-here' : ''; ?>"><?php echo hkp_e('Undermanaged Asset'); ?><br><span class="hkp-small hkp-muted"><?php echo hkp_e('Capital deployed, potential unrealised'); ?></span></div>
      <div class="<?php echo $r['label'] === 'digital_veneer' ? 'is-here' : ''; ?>"><?php echo hkp_e('Digital Veneer'); ?><br><span class="hkp-small hkp-muted"><?php echo hkp_e('Technology adopted, operations underpowered'); ?></span></div>
      <span class="hkp-pin" style="inset-inline-start:<?php echo round($x, 1); ?>%;bottom:<?php echo round($y, 1); ?>%"></span>
    </div>
    <p class="hkp-small hkp-muted"><?php echo hkp_e('Vertical: operational rigour {o}. Horizontal: digital & commercial intelligence {d}. Threshold {t} (configurable).', array('o' => $r['axes']['operational'], 'd' => $r['axes']['digital'], 't' => isset($r['config']['axis_threshold']) ? $r['config']['axis_threshold'] : '—')); ?></p></section>
  <?php endif; ?>
  <section class="hkp-card"><h2><?php echo hkp_e('Dimension scores'); ?></h2><table class="hkp-table"><tbody><?php foreach ($r['dimensions'] as $code => $d): ?><tr><td><?php echo hkp_h(hkp_pick($d, 'name')); ?><?php echo in_array($code, $r['weakest'], true) ? ' ' . hkp_badge('warning', hkp_t('Weakest')) : ''; ?></td><td style="min-width:140px"><?php echo hkp_bar($d['score'] === null ? 0 : 100 * $d['score'] / $max, 'accent'); ?></td><td class="hkp-num"><?php echo $d['score'] === null ? '—' : hkp_number($d['score'], 2); ?></td></tr><?php endforeach; ?></tbody></table>
    <?php if ($r['unanswered']): ?><p class="hkp-small hkp-muted"><?php echo hkp_e('{n} questions unanswered and excluded.', array('n' => $r['unanswered'])); ?></p><?php endif; ?></section>
</div>
<?php endif; ?>
<form class="hkp-card hkp-form" method="post"><?php echo ha_csrf_field(); ?>
<?php foreach ($f['dimensions'] as $d): ?><h2><?php echo hkp_h(hkp_pick($d, 'name')); ?><?php echo $d['axis'] ? ' <span class="hkp-small hkp-muted">· ' . hkp_label($d['axis']) . '</span>' : ''; ?></h2>
  <?php foreach ($d['questions'] as $q): $ans = isset($a['answers'][$q['id']]) ? $a['answers'][$q['id']] : null; ?><div class="hkp-q"><p><?php echo hkp_h(hkp_pick($q, 'prompt')); ?></p>
    <div class="hkp-rate" role="radiogroup" aria-label="<?php echo hkp_h(hkp_pick($q, 'prompt')); ?>"><?php for ($s = 1; $s <= $max; $s++): ?><label><input type="radio" name="a[<?php echo (int) $q['id']; ?>][score]" value="<?php echo $s; ?>"<?php echo $ans && (float) $ans['score'] === (float) $s ? ' checked' : ''; ?>><span><?php echo $s; ?></span></label><?php endfor; ?></div>
    <div class="hkp-grid hkp-grid--2" style="margin-top:.5rem"><textarea class="hkp-input" name="a[<?php echo (int) $q['id']; ?>][evidence]" rows="2" placeholder="<?php echo hkp_e('Evidence observed'); ?>" aria-label="<?php echo hkp_e('Evidence'); ?>"><?php echo hkp_h($ans ? $ans['evidence'] : ''); ?></textarea>
      <textarea class="hkp-input" name="a[<?php echo (int) $q['id']; ?>][recommendation]" rows="2" placeholder="<?php echo hkp_e('Recommendation'); ?>" aria-label="<?php echo hkp_e('Recommendation'); ?>"><?php echo hkp_h($ans ? $ans['recommendation'] : ''); ?></textarea></div></div><?php endforeach; ?>
<?php endforeach; ?>
<div class="hkp-field"><label for="fn"><?php echo hkp_e('Notes'); ?></label><textarea id="fn" class="hkp-input" name="notes" rows="3"><?php echo hkp_h($a['notes']); ?></textarea></div>
<div class="hkp-actions"><button class="hkp-btn hkp-btn--ghost" name="complete" value="0"><?php echo hkp_e('Save draft'); ?></button><button class="hkp-btn" name="complete" value="1"><?php echo hkp_e('Complete and score'); ?></button></div></form>
