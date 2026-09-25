<div class="hkp-head"><div><h1><?php echo hkp_e('Team'); ?></h1><p><?php echo hkp_e('{n} people', array('n' => count($rows))); ?></p></div>
<?php if ($this->ha_auth->has('users.create')): ?><a class="hkp-btn" href="<?php echo hkp_url('team/new_person'); ?>"><?php echo hkp_e('Add employee'); ?></a><?php endif; ?></div>
<form class="hkp-card hkp-form" method="get" style="margin-bottom:1rem"><div class="hkp-row">
  <div class="hkp-field"><label for="tq"><?php echo hkp_e('Search'); ?></label><input id="tq" class="hkp-input" name="q" value="<?php echo hkp_h($f['q']); ?>"></div>
  <div class="hkp-field"><label for="td"><?php echo hkp_e('Department'); ?></label><select id="td" class="hkp-select" name="department"><option value=""><?php echo hkp_e('All'); ?></option><?php foreach ($depts as $d): ?><option value="<?php echo (int) $d['id']; ?>"<?php echo (int) $f['department_id'] === (int) $d['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
  <div class="hkp-field"><label for="ts"><?php echo hkp_e('Status'); ?></label><select id="ts" class="hkp-select" name="status"><option value=""><?php echo hkp_e('All'); ?></option><?php foreach (array('active', 'inactive', 'on_leave', 'terminated', 'archived') as $s): ?><option value="<?php echo $s; ?>"<?php echo $f['status'] === $s ? ' selected' : ''; ?>><?php echo hkp_label($s); ?></option><?php endforeach; ?></select></div>
  <div class="hkp-field" style="justify-content:flex-end"><button class="hkp-btn"><?php echo hkp_e('Filter'); ?></button></div></div></form>
<section class="hkp-card"><div class="hkp-table-wrap"><table class="hkp-table">
  <thead><tr><th><?php echo hkp_e('Employee'); ?></th><th><?php echo hkp_e('Role'); ?></th><th><?php echo hkp_e('Department'); ?></th><th><?php echo hkp_e('Learning'); ?></th><th class="hkp-num"><?php echo hkp_e('Gaps'); ?></th><th><?php echo hkp_e('Readiness'); ?></th><th><?php echo hkp_e('Status'); ?></th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr><td><?php echo hkp_person_open($r['id']); ?><strong><?php echo hkp_h($r['first_name'] . ' ' . $r['last_name']); ?></strong><?php echo hkp_person_close(); ?><div class="hkp-small hkp-muted"><?php echo hkp_h($r['employee_no']); ?> · <?php echo hkp_h(hkp_pick($r, 'prop')); ?></div></td>
      <td class="hkp-small"><?php echo hkp_h(hkp_pick($r, 'role')); ?></td><td class="hkp-small"><?php echo hkp_h(hkp_pick($r, 'dept')); ?></td>
      <td style="min-width:110px"><?php echo hkp_bar($r['progress']); ?> <span class="hkp-small"><?php echo hkp_pct($r['progress']); ?></span></td>
      <td class="hkp-num"><?php echo (int) $r['gaps']; ?></td><td><?php echo $r['readiness'] ? hkp_badge($r['readiness']) : '—'; ?></td><td><?php echo hkp_badge($r['status']); ?></td></tr>
  <?php endforeach; ?></tbody></table></div></section>
