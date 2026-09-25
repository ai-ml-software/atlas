<div class="hkp-head"><div><h1><?php echo hkp_e('Add employee'); ?></h1><p><?php echo hkp_e('The role decides the learning plan, required competencies and readiness policy automatically.'); ?></p></div></div>
<form class="hkp-card hkp-form" method="post" action="<?php echo hkp_url('team/person'); ?>"><?php echo ha_csrf_field(); ?>
  <div class="hkp-row">
    <div class="hkp-field"><label for="nfn"><?php echo hkp_e('First name'); ?></label><input id="nfn" class="hkp-input" name="first_name" required></div>
    <div class="hkp-field"><label for="nln"><?php echo hkp_e('Last name'); ?></label><input id="nln" class="hkp-input" name="last_name"></div>
    <div class="hkp-field"><label for="nar"><?php echo hkp_e('Arabic name'); ?></label><input id="nar" class="hkp-input" name="name_ar" dir="rtl"></div>
    <div class="hkp-field"><label for="nem"><?php echo hkp_e('Email'); ?></label><input id="nem" class="hkp-input" type="email" name="email" required></div>
  </div><div class="hkp-row">
    <div class="hkp-field"><label for="npr"><?php echo hkp_e('Property'); ?></label><select id="npr" class="hkp-select" name="property_id" required><?php foreach ($props as $p): ?><option value="<?php echo (int) $p['id']; ?>"<?php echo (int) $ctx['property_id'] === (int) $p['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($p, 'name')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="ndp"><?php echo hkp_e('Department'); ?></label><select id="ndp" class="hkp-select" name="department_id"><option value=""></option><?php foreach ($depts as $d): ?><option value="<?php echo (int) $d['id']; ?>"><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="njr"><?php echo hkp_e('Job role'); ?></label><select id="njr" class="hkp-select" name="job_role_id"><option value=""></option><?php foreach ($roles as $r): ?><option value="<?php echo (int) $r['id']; ?>"><?php echo hkp_h(hkp_pick($r, 'title')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="nno"><?php echo hkp_e('Employee number'); ?></label><input id="nno" class="hkp-input" name="employee_no"></div>
  </div><div class="hkp-row">
    <div class="hkp-field"><label for="nhd"><?php echo hkp_e('Hire date'); ?></label><input id="nhd" class="hkp-input" type="date" name="hire_date" value="<?php echo date('Y-m-d'); ?>"></div>
    <div class="hkp-field"><label for="net"><?php echo hkp_e('Employment type'); ?></label><select id="net" class="hkp-select" name="employment_type"><?php foreach (array('full_time', 'part_time', 'contract', 'intern', 'seasonal') as $t): ?><option value="<?php echo $t; ?>"><?php echo hkp_label($t); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="nlc"><?php echo hkp_e('Language'); ?></label><select id="nlc" class="hkp-select" name="locale"><option value="en">English</option><option value="ar">العربية</option></select></div>
  </div>
  <div><button class="hkp-btn"><?php echo hkp_e('Create employee'); ?></button><?php if ($this->ha_auth->has('imports.run')): ?> <a href="<?php echo hkp_url('admin/imports'); ?>"><?php echo hkp_e('or import many from CSV'); ?></a><?php endif; ?></div>
</form>
