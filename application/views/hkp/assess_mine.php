<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Have I learned it?'); ?></div><h1><?php echo hkp_e('My assessments'); ?></h1>
<p><?php echo hkp_e('Knowledge assessments you have taken and practical assessments your supervisor has recorded. They are reported separately on purpose.'); ?></p></div></div>
<div class="hkp-grid hkp-grid--2">
  <section class="hkp-card"><h2><?php echo hkp_e('Knowledge (theory)'); ?></h2>
    <?php if (!$rows): ?><div class="hkp-empty"><?php echo hkp_e('No attempts yet. Assessments appear inside each module.'); ?></div><?php endif; ?>
    <ul class="hkp-list"><?php foreach ($rows as $r): ?>
      <li><div><a href="<?php echo $r['status'] === 'in_progress' ? hkp_url('assess/theory/' . $r['assessment_id']) : hkp_url('assess/result/' . $r['id']); ?>"><?php echo hkp_h(hkp_pick($r, 'title')); ?></a>
        <div class="hkp-small hkp-muted"><?php echo hkp_e('Attempt {n}', array('n' => $r['attempt_no'])); ?> · <?php echo hkp_date($r['submitted_at'] ?: $r['started_at'], true); ?></div></div>
        <span><?php echo $r['status'] === 'graded' ? hkp_pct($r['percentage']) . ' ' . hkp_badge($r['passed'] ? 'passed' : 'failed') : hkp_badge($r['status']); ?></span></li>
    <?php endforeach; ?></ul></section>
  <section class="hkp-card"><h2><?php echo hkp_e('Practical (observed)'); ?></h2>
    <?php if (!$practicals): ?><div class="hkp-empty"><?php echo hkp_e('No practical assessments recorded yet.'); ?></div><?php endif; ?>
    <ul class="hkp-list"><?php foreach ($practicals as $p): ?>
      <li><div><a href="<?php echo hkp_url('assess/practical/' . $p['id']); ?>"><?php echo hkp_h(hkp_pick($p, 'title')); ?></a><div class="hkp-small hkp-muted"><?php echo hkp_e('Attempt {n}', array('n' => $p['attempt_no'])); ?> · <?php echo hkp_date($p['submitted_at']); ?></div></div>
        <span><?php echo hkp_pct($p['weighted_score'], 1); ?> <?php echo hkp_badge($p['outcome']); ?></span></li>
    <?php endforeach; ?></ul></section>
</div>
