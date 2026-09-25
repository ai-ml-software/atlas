<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Practical standard'); ?></div><h1><?php echo hkp_h(hkp_pick($r, 'title')); ?></h1><p><?php echo hkp_h(hkp_pick($r, 'description')); ?></p></div></div>
<section class="hkp-card">
  <p class="hkp-small hkp-muted"><?php echo hkp_e('Your supervisor observes you against these criteria. Competent at {p}% weighted; critical criteria must be met.', array('p' => (float) $r['pass_threshold'])); ?></p>
  <table class="hkp-table"><thead><tr><th><?php echo hkp_e('Criterion'); ?></th><th class="hkp-num"><?php echo hkp_e('Weight'); ?></th></tr></thead><tbody>
  <?php foreach ($r['criteria'] as $c): ?><tr><td><?php echo hkp_h(hkp_pick($c, 'label')); ?> <?php if ((int) $c['is_critical']): ?><?php echo hkp_badge('danger', hkp_t('Critical')); ?><?php endif; ?></td><td class="hkp-num"><?php echo (float) $c['weight']; ?></td></tr><?php endforeach; ?>
  </tbody></table>
</section>
