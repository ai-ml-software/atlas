<?php $temp = $this->session->flashdata('hkp_temp_password'); ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_h(hkp_pick($u, 'prop')); ?> · <?php echo hkp_h(hkp_pick($u, 'dept')); ?></div>
<h1><?php echo hkp_h($u['first_name'] . ' ' . $u['last_name']); ?></h1><p><?php echo hkp_h(hkp_pick($u, 'role')); ?> · <?php echo hkp_h($u['employee_no']); ?> · <?php echo hkp_badge($u['status']); ?></p></div>
<span style="font-size:1.25rem"><?php echo hkp_badge($readiness['status']); ?></span></div>
<?php if ($temp): ?><div class="hkp-flash hkp-flash--ok"><?php echo hkp_e('Temporary password (shown once): {p}', array('p' => $temp)); ?></div><?php endif; ?>
<div class="hkp-chain" style="margin-bottom:1rem"><?php foreach (array('Role', 'Requirements', 'Learning', 'Assessment', 'Competency', 'Evidence', 'Gap', 'Action', 'Reassessment', 'Readiness', 'Certification') as $s): ?><span><?php echo hkp_e($s); ?></span>→<?php endforeach; ?><span class="is-on"><?php echo hkp_e('Performance'); ?></span></div>

<div class="hkp-grid hkp-grid--2">
  <section class="hkp-card"><h2><?php echo hkp_e('Readiness'); ?> <span class="hkp-small hkp-muted">· <?php echo $current ? hkp_date($current['calculated_at'], true) : ''; ?></span></h2>
    <ul class="hkp-list"><?php foreach ($readiness['checks'] as $c): ?><li><span><strong><?php echo hkp_e($c['label']); ?></strong><br><span class="hkp-small hkp-muted"><?php echo hkp_h($c['detail']); ?></span></span><?php echo $c['passed'] ? hkp_badge('success', hkp_t('Pass')) : hkp_badge($c['on_fail']); ?></li><?php endforeach; ?></ul></section>
  <section class="hkp-card"><h2><?php echo hkp_e('Competency profile'); ?></h2>
    <div class="hkp-table-wrap"><table class="hkp-table hkp-heat"><thead><tr><th><?php echo hkp_e('Competency'); ?></th><th class="hkp-num"><?php echo hkp_e('Current'); ?></th><th class="hkp-num"><?php echo hkp_e('Required'); ?></th><th></th></tr></thead><tbody>
    <?php foreach ($comp as $c): ?><tr><td><?php echo hkp_h(hkp_pick($c, 'name')); ?><?php echo $c['critical'] ? ' ' . hkp_badge('danger', hkp_t('Critical')) : ''; ?></td><td class="c s-<?php echo $c['gap'] ? $c['severity'] : 'none'; ?>"><?php echo (int) $c['current']; ?></td><td class="hkp-num"><?php echo (int) $c['required']; ?></td><td><?php echo $c['gap'] ? hkp_badge($c['severity']) : hkp_badge('success', hkp_t('Met')); ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if ($this->ha_auth->has('practicals.assess') && $rubrics): ?>
    <form class="hkp-actions" method="post" action="<?php echo hkp_url('assess/practical_start'); ?>" style="margin-top:.8rem"><?php echo ha_csrf_field(); ?><input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
      <label class="hkp-sr" for="er"><?php echo hkp_e('Rubric'); ?></label><select id="er" class="hkp-select" name="rubric_id" style="max-width:280px"><?php foreach ($rubrics as $r): ?><option value="<?php echo (int) $r['id']; ?>"><?php echo hkp_h(hkp_pick($r, 'title')); ?></option><?php endforeach; ?></select>
      <button class="hkp-btn hkp-btn--sm"><?php echo hkp_e('Start practical assessment'); ?></button></form>
    <?php endif; ?></section>
</div>

<section class="hkp-card" style="margin-top:1rem"><h2><?php echo hkp_e('Gaps and corrective actions'); ?></h2>
  <?php if (!$gaps): ?><p class="hkp-muted"><?php echo hkp_e('No gaps recorded.'); ?></p><?php endif; ?>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Competency'); ?></th><th><?php echo hkp_e('Level'); ?></th><th><?php echo hkp_e('Severity'); ?></th><th><?php echo hkp_e('Reason'); ?></th><th><?php echo hkp_e('Status'); ?></th><th></th></tr></thead><tbody>
  <?php foreach ($gaps as $g): ?><tr><td><?php echo hkp_h(hkp_pick($g, 'name')); ?></td><td><?php echo (int) $g['current_level']; ?> / <?php echo (int) $g['required_level']; ?></td><td><?php echo hkp_badge($g['severity']); ?></td><td class="hkp-small"><?php echo hkp_label($g['reason']); ?></td><td><?php echo hkp_badge($g['status']); ?></td>
    <td><?php if ($g['status'] === 'open' && $this->ha_auth->has('action_plans.create')): ?>
      <details><summary class="hkp-btn hkp-btn--sm"><?php echo hkp_e('Assign action'); ?></summary>
        <form class="hkp-form" method="post" action="<?php echo hkp_url('team/action_create'); ?>" style="margin-top:.5rem;min-width:260px"><?php echo ha_csrf_field(); ?><input type="hidden" name="gap_id" value="<?php echo (int) $g['id']; ?>">
          <input class="hkp-input" name="title" required placeholder="<?php echo hkp_e('Action title'); ?>" aria-label="<?php echo hkp_e('Action title'); ?>" value="<?php echo hkp_h(hkp_pick($g, 'name')); ?> — <?php echo hkp_e('development plan'); ?>">
          <select class="hkp-select" name="action_type" aria-label="<?php echo hkp_e('Type'); ?>"><?php foreach (Ha_action_plans::$types as $t): ?><option value="<?php echo $t; ?>"><?php echo hkp_label($t); ?></option><?php endforeach; ?></select>
          <textarea class="hkp-input" name="required_action" rows="2" placeholder="<?php echo hkp_e('What must the employee do?'); ?>" aria-label="<?php echo hkp_e('Required action'); ?>"></textarea>
          <input class="hkp-input" type="date" name="due_at" aria-label="<?php echo hkp_e('Due'); ?>" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
          <button class="hkp-btn hkp-btn--sm"><?php echo hkp_e('Assign'); ?></button></form></details><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if ($actions): ?><ul class="hkp-list" style="margin-top:.8rem"><?php foreach ($actions as $a): ?><li><a href="<?php echo hkp_url('actions/view/' . $a['id']); ?>"><?php echo hkp_h($a['title']); ?></a><span><?php echo hkp_date($a['due_at']); ?> <?php echo hkp_badge($a['status']); ?></span></li><?php endforeach; ?></ul><?php endif; ?>
</section>

<div class="hkp-grid hkp-grid--2" style="margin-top:1rem">
  <section class="hkp-card"><h2><?php echo hkp_e('Learning'); ?></h2><ul class="hkp-list"><?php foreach ($plan as $p): ?><li><span><?php echo hkp_h($p['title']); ?></span><span style="min-width:140px"><?php echo hkp_bar($p['progress_percentage']); ?> <?php echo hkp_badge($p['state']); ?></span></li><?php endforeach; ?></ul>
    <?php if ($recipients): ?><h3 style="margin-top:1rem"><?php echo hkp_e('Assignments'); ?></h3><ul class="hkp-list"><?php foreach ($recipients as $r): ?><li><span class="hkp-small"><?php echo hkp_h(hkp_pick($r, 'title')); ?> · <?php echo hkp_date($r['due_at']); ?></span>
      <span><?php echo hkp_badge($r['status']); ?><?php if ($r['status'] !== 'waived' && $r['status'] !== 'completed' && $this->ha_auth->has('training_assignments.update')): ?>
        <form method="post" action="<?php echo hkp_url('team/exempt/' . $r['id']); ?>" class="hkp-actions" style="margin-top:.3rem"><?php echo ha_csrf_field(); ?><input class="hkp-input" name="reason" required placeholder="<?php echo hkp_e('Reason'); ?>" aria-label="<?php echo hkp_e('Exemption reason'); ?>" style="max-width:150px"><button class="hkp-btn hkp-btn--sm hkp-btn--ghost"><?php echo hkp_e('Exempt'); ?></button></form><?php endif; ?></span></li><?php endforeach; ?></ul><?php endif; ?></section>
  <section class="hkp-card"><h2><?php echo hkp_e('Assessments'); ?></h2>
    <h3><?php echo hkp_e('Knowledge'); ?></h3><ul class="hkp-list"><?php foreach ($attempts as $a): ?><li><span class="hkp-small"><?php echo hkp_h(hkp_pick($a, 'title')); ?> #<?php echo (int) $a['attempt_no']; ?></span><span><?php echo hkp_pct($a['percentage']); ?> <?php echo $a['status'] === 'graded' ? hkp_badge($a['passed'] ? 'passed' : 'failed') : hkp_badge($a['status']); ?></span></li><?php endforeach; ?></ul>
    <h3 style="margin-top:1rem"><?php echo hkp_e('Practical'); ?></h3><ul class="hkp-list"><?php foreach ($practicals as $p): ?><li><a class="hkp-small" href="<?php echo hkp_url('assess/practical/' . $p['id']); ?>"><?php echo hkp_h(hkp_pick($p, 'title')); ?> #<?php echo (int) $p['attempt_no']; ?></a><span><?php echo $p['status'] === 'submitted' ? hkp_pct($p['weighted_score'], 1) . ' ' . hkp_badge($p['outcome']) : hkp_badge($p['status']); ?></span></li><?php endforeach; ?></ul>
    <h3 style="margin-top:1rem"><?php echo hkp_e('Certificates'); ?></h3><ul class="hkp-list"><?php foreach ($certs as $c): ?><li><a class="hkp-small" href="<?php echo hkp_url('certificates/view/' . $c['id']); ?>"><?php echo hkp_h($c['certificate_no']); ?></a><?php echo hkp_badge($c['status']); ?></li><?php endforeach; ?></ul></section>
</div>

<section class="hkp-card" style="margin-top:1rem"><h2><?php echo hkp_e('Competency evidence history'); ?></h2><div class="hkp-table-wrap"><table class="hkp-table"><tbody>
  <?php foreach ($history as $h): ?><tr><td class="hkp-small"><?php echo hkp_date($h['assessed_at'], true); ?></td><td><?php echo hkp_h(hkp_pick($h, 'name')); ?></td><td><?php echo hkp_badge('neutral', hkp_label($h['source'])); ?></td><td class="hkp-num"><?php echo (int) $h['previous_level_no']; ?> → <strong><?php echo (int) $h['level_no']; ?></strong></td><td class="hkp-small"><?php echo hkp_h($h['notes']); ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<?php if ($this->ha_auth->has('learners.update')): ?>
<details class="hkp-card" style="margin-top:1rem"><summary><strong><?php echo hkp_e('Edit employee record'); ?></strong></summary>
  <form class="hkp-form" method="post" action="<?php echo hkp_url('team/person/' . $u['id']); ?>" style="margin-top:1rem"><?php echo ha_csrf_field(); ?>
    <div class="hkp-row">
      <div class="hkp-field"><label for="efn"><?php echo hkp_e('First name'); ?></label><input id="efn" class="hkp-input" name="first_name" value="<?php echo hkp_h($u['first_name']); ?>" required></div>
      <div class="hkp-field"><label for="eln"><?php echo hkp_e('Last name'); ?></label><input id="eln" class="hkp-input" name="last_name" value="<?php echo hkp_h($u['last_name']); ?>"></div>
      <div class="hkp-field"><label for="ear"><?php echo hkp_e('Arabic name'); ?></label><input id="ear" class="hkp-input" name="name_ar" dir="rtl" value="<?php echo hkp_h($u['full_name_ar']); ?>"></div>
      <div class="hkp-field"><label for="eno"><?php echo hkp_e('Employee number'); ?></label><input id="eno" class="hkp-input" name="employee_no" value="<?php echo hkp_h($u['employee_no']); ?>"></div>
    </div><div class="hkp-row">
      <div class="hkp-field"><label for="edp"><?php echo hkp_e('Department'); ?></label><select id="edp" class="hkp-select" name="department_id"><option value=""></option><?php foreach ($depts as $d): ?><option value="<?php echo (int) $d['id']; ?>"<?php echo (int) $u['department_id'] === (int) $d['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
      <div class="hkp-field"><label for="ejr"><?php echo hkp_e('Job role'); ?></label><select id="ejr" class="hkp-select" name="job_role_id"><option value=""></option><?php foreach ($roles as $r): ?><option value="<?php echo (int) $r['id']; ?>"<?php echo (int) $u['job_role_id'] === (int) $r['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($r, 'title')); ?></option><?php endforeach; ?></select></div>
      <div class="hkp-field"><label for="est"><?php echo hkp_e('Status'); ?></label><select id="est" class="hkp-select" name="status"><?php foreach (array('active', 'inactive', 'on_leave', 'terminated', 'archived') as $s): ?><option value="<?php echo $s; ?>"<?php echo $u['status'] === $s ? ' selected' : ''; ?>><?php echo hkp_label($s); ?></option><?php endforeach; ?></select><span class="hkp-help"><?php echo hkp_e('Terminating removes access but keeps every record: knowledge stays with the institution.'); ?></span></div>
      <div class="hkp-field"><label for="ehd"><?php echo hkp_e('Hire date'); ?></label><input id="ehd" class="hkp-input" type="date" name="hire_date" value="<?php echo hkp_h($u['hire_date']); ?>"></div>
    </div><input type="hidden" name="property_id" value="<?php echo (int) $u['property_id']; ?>">
    <div><button class="hkp-btn"><?php echo hkp_e('Save'); ?></button></div></form></details>
<?php endif; ?>
