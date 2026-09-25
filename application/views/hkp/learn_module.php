<div class="hkp-head">
  <div><div class="hkp-eyebrow"><?php echo hkp_e('Module'); ?> · <?php echo hkp_h($c['code']); ?></div><h1><?php echo hkp_h($c['title']); ?></h1>
  <p><?php echo hkp_h($c['short_description']); ?></p></div>
  <?php if (!$enrollment): ?>
    <form method="post" action="<?php echo hkp_url('learn/enroll/' . $c['id']); ?>"><?php echo ha_csrf_field(); ?><button class="hkp-btn"><?php echo hkp_e('Add to my learning'); ?></button></form>
  <?php endif; ?>
</div>
<?php if ($unmet): ?><div class="hkp-flash hkp-flash--error"><?php echo hkp_e('Complete these modules first: {list}', array('list' => implode(', ', $unmet))); ?></div><?php endif; ?>

<div class="hkp-grid hkp-grid--main">
  <section class="hkp-card">
    <h2><?php echo hkp_e('Lessons'); ?></h2>
    <ol class="hkp-list">
    <?php foreach ($c['lessons'] as $l): $is_done = in_array((int) $l['id'], $done, true); ?>
      <li><span><a href="<?php echo hkp_url('learn/lesson/' . $l['id']); ?>"><?php echo hkp_h($l['title'] ?: $l['title_en']); ?></a>
        <span class="hkp-small hkp-muted"> · <?php echo hkp_label($l['lesson_type']); ?><?php echo $l['duration_seconds'] ? ' · ' . ceil($l['duration_seconds'] / 60) . ' ' . hkp_e('min') : ''; ?></span></span>
        <?php echo $is_done ? hkp_badge('completed') : hkp_badge('not_started'); ?></li>
    <?php endforeach; ?>
    </ol>
  </section>
  <div class="hkp-grid">
    <section class="hkp-card">
      <h2><?php echo hkp_e('Progress'); ?></h2>
      <?php $p = $enrollment ? (float) $enrollment['progress_percentage'] : 0; ?>
      <?php echo hkp_bar($p); ?> <p class="hkp-small"><?php echo hkp_pct($p); ?> · <?php echo $enrollment ? hkp_badge($enrollment['status']) : hkp_badge('not_started'); ?></p>
      <?php if ($enrollment && $enrollment['time_spent_seconds']): ?><p class="hkp-small hkp-muted"><?php echo hkp_e('Time spent: {m} min', array('m' => round($enrollment['time_spent_seconds'] / 60))); ?></p><?php endif; ?>
    </section>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Knowledge assessment'); ?></h2>
      <?php if (!$c['assessments']): ?><p class="hkp-muted"><?php echo hkp_e('This module has no assessment.'); ?></p><?php endif; ?>
      <?php foreach ($c['assessments'] as $a): ?>
        <p><strong><?php echo hkp_h(hkp_pick($a, 'title')); ?></strong><br><span class="hkp-small hkp-muted"><?php echo hkp_e('Pass mark {p}% · {n} attempts', array('p' => (int) $a['pass_percentage'], 'n' => (int) $a['max_attempts'])); ?></span></p>
        <a class="hkp-btn" href="<?php echo hkp_url('assess/theory/' . $a['id']); ?>"><?php echo hkp_e('Take assessment'); ?></a>
      <?php endforeach; ?>
      <?php if ($attempts): ?>
        <ul class="hkp-list" style="margin-top:.8rem">
          <?php foreach ($attempts as $at): ?><li><a href="<?php echo hkp_url('assess/result/' . $at['id']); ?>"><?php echo hkp_e('Attempt {n}', array('n' => $at['attempt_no'])); ?></a><span><?php echo hkp_pct($at['percentage']); ?> <?php echo $at['status'] === 'graded' ? hkp_badge($at['passed'] ? 'passed' : 'failed') : hkp_badge($at['status']); ?></span></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php if ($skills): ?>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Develops these competencies'); ?></h2>
      <p class="hkp-small hkp-muted"><?php echo hkp_e('Completing the module is not the same as being competent: your supervisor confirms competency in practice.'); ?></p>
      <ul class="hkp-list"><?php foreach ($skills as $s): ?><li><?php echo hkp_h(hkp_pick($s, 'name')); ?></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>
  </div>
</div>
