<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Knowledge & Learning'); ?></div><h1><?php echo hkp_e('Curriculum'); ?></h1>
<p><?php echo hkp_e('Professional Domain → Track → Module → Lesson → Assessment & Certification. Drag modules to reorder a track.'); ?></p></div>
<div class="hkp-actions"><a class="hkp-btn" href="<?php echo hkp_url('admin/crud/tracks/new'); ?>"><?php echo hkp_e('New track'); ?></a><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('admin/crud/domains'); ?>"><?php echo hkp_e('Domains'); ?></a><a class="hkp-btn hkp-btn--ghost" href="<?php echo site_url('admin/courses'); ?>"><?php echo hkp_e('Module & lesson editor'); ?></a><a class="hkp-btn hkp-btn--ghost" href="<?php echo site_url('ha_ai/studio'); ?>"><?php echo hkp_icon('spark'); ?> <?php echo hkp_e('AI course studio'); ?></a></div></div>
<?php foreach ($domains as $d): $dt = array_filter($tracks, function ($t) use ($d) { return (int) $t['domain_id'] === (int) $d['id']; }); ?>
<section class="hkp-card" style="margin-bottom:1rem">
  <div class="hkp-actions" style="justify-content:space-between"><h2 style="margin:0"><?php echo hkp_h(hkp_pick($d, 'name')); ?> <span class="hkp-small hkp-muted">· <?php echo hkp_label($d['domain_group']); ?></span></h2><a class="hkp-small" href="<?php echo hkp_url('admin/crud/domains/edit/' . $d['id']); ?>"><?php echo hkp_e('Edit domain'); ?></a></div>
  <?php if (!$dt): ?><p class="hkp-muted hkp-small"><?php echo hkp_e('No tracks yet.'); ?></p><?php endif; ?>
  <?php foreach ($dt as $t): ?>
    <details style="margin-top:.8rem" open><summary><strong><?php echo hkp_h(hkp_pick($t, 'title')); ?></strong> <?php echo hkp_badge($t['status']); ?> <span class="hkp-small hkp-muted"><?php echo $t['organization_id'] ? hkp_e('Client track') : hkp_e('Altus master'); ?></span> · <a class="hkp-small" href="<?php echo hkp_url('admin/crud/tracks/edit/' . $t['id']); ?>"><?php echo hkp_e('Edit'); ?></a></summary>
      <ol class="hkp-sortable" data-sortable data-order-url="<?php echo hkp_url('admin/curriculum/order/' . $t['id']); ?>" style="padding:0;list-style:none;margin:.6rem 0">
      <?php foreach (isset($mods[$t['id']]) ? $mods[$t['id']] : array() as $m): ?>
        <li data-id="<?php echo (int) $m['course_id']; ?>"><span class="hkp-handle" aria-hidden="true">⠿</span><span style="flex:1"><a href="<?php echo hkp_url('learn/module/' . $m['course_id']); ?>"><?php echo hkp_h($m['title']); ?></a> <span class="hkp-small hkp-muted"><?php echo hkp_h($m['code']); ?> · <?php echo (int) $m['lessons']; ?> <?php echo hkp_e('lessons'); ?> · <?php echo (int) $m['assessments']; ?> <?php echo hkp_e('assessments'); ?></span></span>
          <?php echo hkp_badge($m['status']); ?>
          <form method="post" action="<?php echo hkp_url('admin/curriculum/detach/' . $t['id']); ?>"><?php echo ha_csrf_field(); ?><input type="hidden" name="course_id" value="<?php echo (int) $m['course_id']; ?>"><button class="hkp-btn hkp-btn--sm hkp-btn--ghost" aria-label="<?php echo hkp_e('Remove from track'); ?>">✕</button></form></li>
      <?php endforeach; ?></ol>
      <form class="hkp-actions" method="post" action="<?php echo hkp_url('admin/curriculum/attach/' . $t['id']); ?>"><?php echo ha_csrf_field(); ?><label class="hkp-sr" for="ac<?php echo $t['id']; ?>"><?php echo hkp_e('Module'); ?></label>
        <select id="ac<?php echo $t['id']; ?>" class="hkp-select" name="course_id" style="max-width:360px"><?php foreach ($courses as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo hkp_h($c['title'] ?: $c['code']); ?></option><?php endforeach; ?></select>
        <label class="hkp-check"><input type="checkbox" name="is_mandatory" value="1" checked> <?php echo hkp_e('Mandatory'); ?></label><button class="hkp-btn hkp-btn--sm"><?php echo hkp_e('Add module'); ?></button></form>
    </details>
  <?php endforeach; ?>
</section>
<?php endforeach; ?>
<script>
document.addEventListener('hkp:sorted', function (e) {
  var list = e.target; var url = list.getAttribute('data-order-url'); if (!url || !window.HKP.post) return;
  window.HKP.post(url, { order: Array.prototype.map.call(list.children, function (li) { return li.getAttribute('data-id'); }).join(',') });
});
</script>
