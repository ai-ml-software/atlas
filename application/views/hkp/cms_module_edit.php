<?php
$v = function ($k) use ($c) { return $c && isset($c[$k]) ? $c[$k] : ''; };
$t = function ($loc, $k) use ($tr) { return isset($tr[$loc][$k]) ? $tr[$loc][$k] : ''; };
$ai_entity = 'course'; $ai_id = $c ? $c['id'] : 0; $ai_insert = '#md_en';
$ai_tasks = array('lesson' => 'Write a short applied lesson', 'improve' => 'Improve the selected text', 'quiz' => 'Write assessment questions', 'translate_ar' => 'Translate to Arabic (Modern Standard Arabic)', 'enhance_prompt' => 'Turn a rough request into a precise brief');
?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><a href="<?php echo hkp_url('cms/modules'); ?>"><?php echo hkp_e('Modules & lessons'); ?></a></div><h1><?php echo $c ? hkp_h($t('en', 'title') ?: $c['code']) : hkp_e('New module'); ?></h1>
<?php if ($c): ?><p><?php echo hkp_badge($c['status']); ?> · <a href="<?php echo hkp_url('learn/module/' . $c['id']); ?>"><?php echo hkp_e('Preview as learner'); ?></a></p><?php endif; ?></div></div>
<div class="hkp-grid hkp-grid--main">
<div class="hkp-grid">
<form class="hkp-card hkp-form" method="post" enctype="multipart/form-data" action="<?php echo hkp_url('cms/module_save/' . ($c ? $c['id'] : 0)); ?>"><?php echo ha_csrf_field(); ?><h2><?php echo hkp_e('Module details'); ?></h2>
  <div class="hkp-grid hkp-grid--2">
  <?php foreach (array('en' => 'English', 'ar' => 'العربية') as $loc => $ln): $dir = $loc === 'ar' ? ' dir="rtl"' : ''; ?><div class="hkp-form"><strong class="hkp-small"><?php echo $ln; ?></strong>
    <div class="hkp-field"><label for="mt_<?php echo $loc; ?>"><?php echo hkp_e('Title'); ?></label><input id="mt_<?php echo $loc; ?>" class="hkp-input" name="title_<?php echo $loc; ?>" value="<?php echo hkp_h($t($loc, 'title')); ?>"<?php echo $dir; ?><?php echo $loc === 'en' ? ' required' : ''; ?>></div>
    <div class="hkp-field"><label for="ms_<?php echo $loc; ?>"><?php echo hkp_e('Short description'); ?></label><input id="ms_<?php echo $loc; ?>" class="hkp-input" name="short_<?php echo $loc; ?>" value="<?php echo hkp_h($t($loc, 'short_description')); ?>"<?php echo $dir; ?>></div>
    <div class="hkp-field"><label for="md_<?php echo $loc; ?>"><?php echo hkp_e('Description'); ?></label><textarea id="md_<?php echo $loc; ?>" class="hkp-input" rows="5" name="description_<?php echo $loc; ?>"<?php echo $dir; ?>><?php echo hkp_h($t($loc, 'description')); ?></textarea></div></div><?php endforeach; ?>
  </div>
  <div class="hkp-row">
    <div class="hkp-field"><label for="mdo"><?php echo hkp_e('Domain'); ?></label><select id="mdo" class="hkp-select" name="domain_id"><option value=""></option><?php foreach ($domains as $d): ?><option value="<?php echo (int) $d['id']; ?>"<?php echo (int) $v('domain_id') === (int) $d['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="mca"><?php echo hkp_e('Category'); ?></label><select id="mca" class="hkp-select" name="category_id"><option value=""></option><?php foreach ($categories as $cat): ?><option value="<?php echo (int) $cat['id']; ?>"<?php echo (int) $v('category_id') === (int) $cat['id'] ? ' selected' : ''; ?>><?php echo hkp_h($cat['name']); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="mlv"><?php echo hkp_e('Level'); ?></label><select id="mlv" class="hkp-select" name="level"><?php foreach (array('foundation', 'intermediate', 'advanced', 'leadership') as $l): ?><option value="<?php echo $l; ?>"<?php echo $v('level') === $l ? ' selected' : ''; ?>><?php echo hkp_label($l); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="mdu"><?php echo hkp_e('Duration (minutes)'); ?></label><input id="mdu" class="hkp-input" type="number" name="duration_minutes" value="<?php echo hkp_h($v('duration_minutes') ?: 30); ?>"></div>
  </div><div class="hkp-row">
    <div class="hkp-field"><label for="mdp"><?php echo hkp_e('Department code'); ?></label><input id="mdp" class="hkp-input" name="department_code" value="<?php echo hkp_h($v('department_code')); ?>" placeholder="FO"></div>
    <div class="hkp-field"><label for="mps"><?php echo hkp_e('Pass mark (%)'); ?></label><input id="mps" class="hkp-input" type="number" name="pass_percentage" value="<?php echo hkp_h($v('pass_percentage') ?: 75); ?>"></div>
    <div class="hkp-field"><label for="mor"><?php echo hkp_e('Owner'); ?></label><select id="mor" class="hkp-select" name="organization_id"><?php if ($this->ha_auth->is_system_scoped()): ?><option value=""><?php echo hkp_e('Altus global (all clients)'); ?></option><?php endif; ?><?php foreach ($orgs as $o): if (!$this->ha_auth->can_organization($o['id'])) continue; ?><option value="<?php echo (int) $o['id']; ?>"<?php echo (int) $v('organization_id') === (int) $o['id'] ? ' selected' : ''; ?>><?php echo hkp_h($o['name_en']); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="mth"><?php echo hkp_e('Thumbnail'); ?></label><input id="mth" class="hkp-input" type="file" name="thumbnail" accept="image/*"></div>
  </div>
  <div class="hkp-actions"><label class="hkp-check"><input type="checkbox" name="certificate_eligible" value="1"<?php echo !$c || (int) $c['certificate_eligible'] ? ' checked' : ''; ?>> <?php echo hkp_e('Counts towards certification'); ?></label>
    <label for="mst"><?php echo hkp_e('Status'); ?></label><select id="mst" class="hkp-select" name="status" style="max-width:200px"><?php foreach (array('draft', 'review', 'approved', 'published', 'archived') as $s): ?><option value="<?php echo $s; ?>"<?php echo $v('status') === $s ? ' selected' : ''; ?>><?php echo hkp_label($s); ?></option><?php endforeach; ?></select></div>
  <div><button class="hkp-btn"><?php echo hkp_e('Save module'); ?></button></div></form>

<?php if ($c): ?>
<section class="hkp-card"><div class="hkp-actions" style="justify-content:space-between"><h2 style="margin:0"><?php echo hkp_e('Lessons'); ?> <span class="hkp-small hkp-muted">· <?php echo hkp_e('drag to reorder'); ?></span></h2><a class="hkp-btn hkp-btn--sm" href="<?php echo hkp_url('cms/lesson?module=' . $c['id']); ?>"><?php echo hkp_e('Add lesson'); ?></a></div>
  <ol class="hkp-sortable" data-sortable data-order-url="<?php echo hkp_url('cms/lesson_order/' . $c['id']); ?>" style="padding:0;list-style:none;margin-top:.8rem">
  <?php foreach ($lessons as $l): ?><li data-id="<?php echo (int) $l['id']; ?>"><span class="hkp-handle" aria-hidden="true">⠿</span><span style="flex:1"><a href="<?php echo hkp_url('cms/lesson/' . $l['id']); ?>"><?php echo hkp_h(hkp_pick($l, 'title')); ?></a>
    <span class="hkp-small hkp-muted"> · <?php echo hkp_label($l['lesson_type']); ?><?php echo $l['drip_days'] ? ' · ' . hkp_e('day {n}', array('n' => $l['drip_days'])) : ''; ?><?php echo $l['available_from'] ? ' · ' . hkp_e('from {d}', array('d' => hkp_date($l['available_from']))) : ''; ?></span></span><?php echo hkp_badge($l['status']); ?></li><?php endforeach; ?></ol>
  <?php if (!$lessons): ?><div class="hkp-empty"><?php echo hkp_e('No lessons yet.'); ?></div><?php endif; ?>
  <form class="hkp-actions" method="post" action="<?php echo hkp_url('cms/section_add/' . $c['id']); ?>" style="margin-top:1rem"><?php echo ha_csrf_field(); ?><label class="hkp-sr" for="nsec"><?php echo hkp_e('Section title'); ?></label><input id="nsec" class="hkp-input" name="title_en" placeholder="<?php echo hkp_e('New section (English)'); ?>" style="max-width:260px"><input class="hkp-input" name="title_ar" dir="rtl" placeholder="<?php echo hkp_e('New section (Arabic)'); ?>" aria-label="<?php echo hkp_e('Section title (Arabic)'); ?>" style="max-width:260px"><button class="hkp-btn hkp-btn--sm hkp-btn--ghost"><?php echo hkp_e('Add section'); ?></button></form>
  <p class="hkp-small hkp-muted"><?php echo hkp_e('Sections: {s}', array('s' => implode(' · ', array_map(function ($s) { return hkp_pick($s, 'title'); }, $sections)))); ?></p></section>
<?php endif; ?>
</div>
<div class="hkp-grid"><?php $this->load->view('hkp/_ai_panel', compact('models', 'ai_entity', 'ai_id', 'ai_insert', 'ai_tasks')); ?></div>
</div>
<script>
document.addEventListener('hkp:sorted', function (e) {
  var list = e.target; var url = list.getAttribute('data-order-url'); if (!url || !window.HKP.post) return;
  window.HKP.post(url, { order: Array.prototype.map.call(list.children, function (li) { return li.getAttribute('data-id'); }).join(',') });
});
</script>
