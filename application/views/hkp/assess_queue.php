<?php $dname = array(); foreach ($depts as $d) { $dname[$d['id']] = hkp_pick($d, 'name'); } ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Who needs my attention?'); ?></div><h1><?php echo hkp_e('Assessor queue'); ?></h1>
<p><?php echo hkp_e('Observe real work, record evidence and confirm competency. Drafts save automatically when you press Save and can be resumed later.'); ?></p></div></div>

<div class="hkp-grid hkp-grid--4" style="margin-bottom:1rem">
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Awaiting practical'); ?></span><span class="hkp-tile__value"><?php echo count($awaiting); ?></span></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Drafts to resume'); ?></span><span class="hkp-tile__value"><?php echo count($drafts); ?></span></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Reassessments'); ?></span><span class="hkp-tile__value"><?php echo count($reassessments); ?></span></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Evidence to review'); ?></span><span class="hkp-tile__value"><?php echo count($evidence); ?></span></div>
</div>

<div class="hkp-grid hkp-grid--main">
  <div class="hkp-grid">
    <section class="hkp-card"><h2><?php echo hkp_e('Start a practical assessment'); ?></h2>
      <form class="hkp-form" method="post" action="<?php echo hkp_url('assess/practical_start'); ?>"><?php echo ha_csrf_field(); ?>
        <div class="hkp-row">
          <div class="hkp-field"><label for="pu"><?php echo hkp_e('Employee'); ?></label><select id="pu" class="hkp-select" name="user_id" required><option value=""><?php echo hkp_e('Choose…'); ?></option><?php foreach ($people as $p): ?><option value="<?php echo (int) $p['id']; ?>"><?php echo hkp_h($p['first_name'] . ' ' . $p['last_name'] . ($p['title_en'] ? ' — ' . hkp_pick($p, 'title') : '')); ?></option><?php endforeach; ?></select></div>
          <div class="hkp-field"><label for="pr"><?php echo hkp_e('Rubric'); ?></label><select id="pr" class="hkp-select" name="rubric_id" required><?php foreach ($rubrics as $r): ?><option value="<?php echo (int) $r['id']; ?>"><?php echo hkp_h(hkp_pick($r, 'title')); ?></option><?php endforeach; ?></select></div>
          <div class="hkp-field" style="justify-content:flex-end"><button class="hkp-btn"><?php echo hkp_icon('clipboard'); ?> <?php echo hkp_e('Start'); ?></button></div>
        </div></form></section>

    <section class="hkp-card"><h2><?php echo hkp_e('Employees awaiting practical assessment'); ?></h2>
      <?php if (!$awaiting): ?><p class="hkp-muted"><?php echo hkp_e('Everyone required to be assessed in practice has been.'); ?></p><?php endif; ?>
      <div class="hkp-table-wrap"><table class="hkp-table"><tbody><?php foreach ($awaiting as $w): ?>
        <tr><td><?php echo hkp_h($w['first_name'] . ' ' . $w['last_name']); ?></td><td><?php echo hkp_h(hkp_pick($w, 'name')); ?></td><td class="hkp-small"><?php echo hkp_h(hkp_pick(array('title_en' => $w['rubric_en'], 'title_ar' => $w['rubric_ar']), 'title')); ?></td>
          <td><form method="post" action="<?php echo hkp_url('assess/practical_start'); ?>"><?php echo ha_csrf_field(); ?><input type="hidden" name="user_id" value="<?php echo (int) $w['user_id']; ?>"><input type="hidden" name="rubric_id" value="<?php echo (int) $w['rubric_id']; ?>"><button class="hkp-btn hkp-btn--sm"><?php echo hkp_e('Assess'); ?></button></form></td></tr>
      <?php endforeach; ?></tbody></table></div></section>

    <?php if ($drafts): ?><section class="hkp-card"><h2><?php echo hkp_e('Drafts'); ?></h2><ul class="hkp-list"><?php foreach ($drafts as $d): ?>
      <li><span><?php echo hkp_h($d['first_name'] . ' ' . $d['last_name']); ?> — <?php echo hkp_h(hkp_pick($d, 'title')); ?></span><a class="hkp-btn hkp-btn--sm" href="<?php echo hkp_url('assess/practical/' . $d['id']); ?>"><?php echo hkp_e('Resume'); ?></a></li><?php endforeach; ?></ul></section><?php endif; ?>

    <?php if ($failed): ?><section class="hkp-card"><h2><?php echo hkp_e('Failed competencies'); ?></h2><ul class="hkp-list"><?php foreach ($failed as $f): ?>
      <li><span><?php echo hkp_h($f['first_name'] . ' ' . $f['last_name']); ?> — <a href="<?php echo hkp_url('assess/practical/' . $f['id']); ?>"><?php echo hkp_h(hkp_pick($f, 'title')); ?></a></span><?php echo hkp_badge($f['outcome']); ?></li><?php endforeach; ?></ul></section><?php endif; ?>
  </div>
  <div class="hkp-grid">
    <section class="hkp-card"><h2><?php echo hkp_e('Reassessment requests'); ?></h2>
      <?php if (!$reassessments): ?><p class="hkp-muted"><?php echo hkp_e('None.'); ?></p><?php endif; ?>
      <ul class="hkp-list"><?php foreach ($reassessments as $r): ?>
        <li><div><?php echo hkp_h($r['first_name'] . ' ' . $r['last_name']); ?><div class="hkp-small hkp-muted"><?php echo hkp_h(hkp_pick($r, 'name')); ?></div></div>
          <div><?php echo hkp_badge($r['status']); ?>
            <?php if ($r['status'] === 'requested' && $this->ha_auth->has('reassessments.approve')): ?>
              <form method="post" action="<?php echo hkp_url('assess/reassessment/' . $r['id']); ?>" class="hkp-actions" style="margin-top:.3rem"><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--sm" name="decision" value="approve"><?php echo hkp_e('Approve'); ?></button><button class="hkp-btn hkp-btn--sm hkp-btn--ghost" name="decision" value="decline"><?php echo hkp_e('Decline'); ?></button></form>
            <?php elseif ($r['status'] === 'approved' && $r['rubric_id']): ?>
              <form method="post" action="<?php echo hkp_url('assess/practical_start'); ?>" style="margin-top:.3rem"><?php echo ha_csrf_field(); ?><input type="hidden" name="user_id" value="<?php echo (int) $r['user_id']; ?>"><input type="hidden" name="rubric_id" value="<?php echo (int) $r['rubric_id']; ?>"><input type="hidden" name="reassessment_id" value="<?php echo (int) $r['id']; ?>"><button class="hkp-btn hkp-btn--sm"><?php echo hkp_e('Reassess now'); ?></button></form>
            <?php endif; ?></div></li>
      <?php endforeach; ?></ul></section>
    <section class="hkp-card"><h2><?php echo hkp_e('Evidence submitted'); ?></h2>
      <?php if (!$evidence): ?><p class="hkp-muted"><?php echo hkp_e('None.'); ?></p><?php endif; ?>
      <ul class="hkp-list"><?php foreach ($evidence as $e): ?><li><a href="<?php echo hkp_url('actions/view/' . $e['id']); ?>"><?php echo hkp_h($e['title']); ?></a><span class="hkp-small"><?php echo hkp_h($e['first_name']); ?></span></li><?php endforeach; ?></ul></section>
    <section class="hkp-card"><h2><?php echo hkp_e('Department readiness'); ?></h2>
      <?php foreach ($readiness as $dept => $s): ?><p class="hkp-small" style="margin:.3rem 0"><strong><?php echo hkp_h(isset($dname[$dept]) ? $dname[$dept] : hkp_t('Unassigned')); ?></strong> · <?php echo hkp_pct($s['ready_pct']); ?> <?php echo hkp_e('ready'); ?></p>
        <div class="hkp-stack" title="<?php echo hkp_e('Ready'); ?>"><?php foreach (array('ready', 'conditional', 'not_ready', 'not_calculated') as $k): if (!$s[$k]) continue; ?><span class="<?php echo $k; ?>" style="inline-size:<?php echo round(100 * $s[$k] / $s['total'], 1); ?>%"></span><?php endforeach; ?></div><?php endforeach; ?></section>
    <?php if ($expiring): ?><section class="hkp-card"><h2><?php echo hkp_e('Expiring certifications'); ?></h2><ul class="hkp-list"><?php foreach ($expiring as $c): ?><li><span><?php echo hkp_h($c['first_name'] . ' ' . $c['last_name']); ?></span><span class="hkp-small"><?php echo hkp_date($c['expires_at']); ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  </div>
</div>
