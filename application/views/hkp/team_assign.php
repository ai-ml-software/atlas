<div class="hkp-head"><div><h1><?php echo hkp_e('Assign learning'); ?></h1><p><?php echo hkp_e('Assign tracks or modules to people, departments, roles, cohorts or the whole property. Only people inside your scope are reached.'); ?></p></div></div>
<form class="hkp-card hkp-form" method="post"><?php echo ha_csrf_field(); ?>
  <div class="hkp-row">
    <div class="hkp-field"><label for="at"><?php echo hkp_e('Title (English)'); ?></label><input id="at" class="hkp-input" name="title_en" required></div>
    <div class="hkp-field"><label for="ata"><?php echo hkp_e('Title (Arabic)'); ?></label><input id="ata" class="hkp-input" name="title_ar" dir="rtl"></div>
    <div class="hkp-field"><label for="ad"><?php echo hkp_e('Due date'); ?></label><input id="ad" class="hkp-input" type="date" name="due_at" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"></div>
    <div class="hkp-field"><label for="ap"><?php echo hkp_e('Priority'); ?></label><select id="ap" class="hkp-select" name="priority"><?php foreach (array('low', 'medium', 'high', 'critical') as $p): ?><option value="<?php echo $p; ?>"<?php echo $p === 'medium' ? ' selected' : ''; ?>><?php echo hkp_label($p); ?></option><?php endforeach; ?></select></div>
  </div>
  <label class="hkp-check"><input type="checkbox" name="is_mandatory" value="1" checked> <?php echo hkp_e('Mandatory (counts towards readiness)'); ?></label>
  <div class="hkp-grid hkp-grid--2">
    <div class="hkp-field"><label for="atr"><?php echo hkp_e('Tracks'); ?></label><select id="atr" class="hkp-select" name="tracks[]" multiple size="8"><?php foreach ($tracks as $t): ?><option value="<?php echo (int) $t['id']; ?>"><?php echo hkp_h(hkp_pick($t, 'title')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="aco"><?php echo hkp_e('Modules'); ?></label><select id="aco" class="hkp-select" name="courses[]" multiple size="8"><?php foreach ($courses as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo hkp_h($c['title']); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="aus"><?php echo hkp_e('People'); ?></label><select id="aus" class="hkp-select" name="users[]" multiple size="6"><?php foreach ($people as $p): ?><option value="<?php echo (int) $p['id']; ?>"><?php echo hkp_h($p['first_name'] . ' ' . $p['last_name']); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="ade"><?php echo hkp_e('Departments'); ?></label><select id="ade" class="hkp-select" name="departments[]" multiple size="6"><?php foreach ($depts as $d): ?><option value="<?php echo (int) $d['id']; ?>"><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="aro"><?php echo hkp_e('Job roles'); ?></label><select id="aro" class="hkp-select" name="roles[]" multiple size="6"><?php foreach ($roles as $r): ?><option value="<?php echo (int) $r['id']; ?>"><?php echo hkp_h(hkp_pick($r, 'title')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="aci"><?php echo hkp_e('Cohorts'); ?></label><select id="aci" class="hkp-select" name="cohorts[]" multiple size="6"><?php foreach ($cohorts as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo hkp_h(hkp_pick($c, 'name')); ?></option><?php endforeach; ?></select></div>
  </div>
  <label class="hkp-check"><input type="checkbox" name="whole_property" value="1"> <?php echo hkp_e('Everyone at this property'); ?></label>
  <div><button class="hkp-btn"><?php echo hkp_icon('send'); ?> <?php echo hkp_e('Assign'); ?></button></div>
</form>
<section class="hkp-card" style="margin-top:1rem"><h2><?php echo hkp_e('Recent assignments'); ?></h2><div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Title'); ?></th><th><?php echo hkp_e('Source'); ?></th><th><?php echo hkp_e('Due'); ?></th><th class="hkp-num"><?php echo hkp_e('Completed'); ?></th></tr></thead><tbody>
<?php foreach ($recent as $r): ?><tr><td><?php echo hkp_h(hkp_pick($r, 'title')); ?></td><td><?php echo hkp_badge('neutral', hkp_label($r['source'])); ?></td><td class="hkp-small"><?php echo hkp_date($r['due_at']); ?></td><td class="hkp-num"><?php echo (int) $r['recipients_completed']; ?> / <?php echo (int) $r['recipients_total']; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
