<div class="hkp-head"><div><h1><?php echo hkp_e('Cohorts'); ?></h1><p><?php echo hkp_e('Groups such as new joiners or a pre-opening team receive one assignment instead of many.'); ?></p></div></div>
<div class="hkp-grid hkp-grid--main">
  <section class="hkp-card"><h2><?php echo $cohort ? hkp_h(hkp_pick($cohort, 'name')) : hkp_e('All cohorts'); ?></h2>
  <?php if ($cohort): ?>
    <p class="hkp-small"><?php echo hkp_label($cohort['cohort_type']); ?><?php if ($cohort['opening_date']): $t = (int) floor((strtotime($cohort['opening_date']) - time()) / 86400); ?> · <?php echo hkp_e('Opening {d} (T-{n})', array('d' => $cohort['opening_date'], 'n' => max(0, $t))); ?><?php endif; ?></p>
    <table class="hkp-table"><thead><tr><th><?php echo hkp_e('Member'); ?></th><th><?php echo hkp_e('Wave'); ?></th><th><?php echo hkp_e('Readiness'); ?></th></tr></thead><tbody>
    <?php foreach ($members as $m): ?><tr><td><?php echo hkp_person_open($m['user_id']); ?><?php echo hkp_h($m['first_name'] . ' ' . $m['last_name']); ?><?php echo hkp_person_close(); ?></td><td><?php echo hkp_h($m['wave']); ?></td><td><?php echo $m['readiness'] ? hkp_badge($m['readiness']) : '—'; ?></td></tr><?php endforeach; ?></tbody></table>
    <?php if ($this->ha_auth->has('cohorts.update')): ?><form class="hkp-form" method="post" style="margin-top:1rem"><?php echo ha_csrf_field(); ?><div class="hkp-row">
      <div class="hkp-field"><label for="cm"><?php echo hkp_e('Add members'); ?></label><select id="cm" class="hkp-select" name="members[]" multiple size="6"><?php foreach ($people as $p): ?><option value="<?php echo (int) $p['id']; ?>"><?php echo hkp_h($p['first_name'] . ' ' . $p['last_name']); ?></option><?php endforeach; ?></select></div>
      <div class="hkp-field"><label for="cw"><?php echo hkp_e('Wave'); ?></label><input id="cw" class="hkp-input" name="wave" placeholder="Wave 2"></div></div><div><button class="hkp-btn"><?php echo hkp_e('Add'); ?></button></div></form><?php endif; ?>
  <?php else: ?>
    <ul class="hkp-list"><?php foreach ($cohorts as $c): ?><li><a href="<?php echo hkp_url('team/cohorts/' . $c['id']); ?>"><?php echo hkp_h(hkp_pick($c, 'name')); ?></a><span class="hkp-small"><?php echo hkp_label($c['cohort_type']); ?> · <?php echo (int) $c['members']; ?></span></li><?php endforeach; ?></ul>
  <?php endif; ?></section>
  <?php if ($this->ha_auth->has('cohorts.create')): ?>
  <section class="hkp-card"><h2><?php echo hkp_e('New cohort'); ?></h2><form class="hkp-form" method="post" action="<?php echo hkp_url('team/cohorts'); ?>"><?php echo ha_csrf_field(); ?>
    <div class="hkp-field"><label for="cn"><?php echo hkp_e('Name (English)'); ?></label><input id="cn" class="hkp-input" name="name_en" required></div>
    <div class="hkp-field"><label for="cna"><?php echo hkp_e('Name (Arabic)'); ?></label><input id="cna" class="hkp-input" name="name_ar" dir="rtl"></div>
    <div class="hkp-field"><label for="ct"><?php echo hkp_e('Type'); ?></label><select id="ct" class="hkp-select" name="cohort_type"><?php foreach (array('general', 'new_joiners', 'pre_opening', 'management', 'department', 'role') as $t): ?><option value="<?php echo $t; ?>"><?php echo hkp_label($t); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="co"><?php echo hkp_e('Opening date (pre-opening only)'); ?></label><input id="co" class="hkp-input" type="date" name="opening_date"></div>
    <div><button class="hkp-btn"><?php echo hkp_e('Create'); ?></button></div></form></section>
  <?php endif; ?>
</div>
