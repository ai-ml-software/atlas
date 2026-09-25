<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Track'); ?></div><h1><?php echo hkp_h(hkp_pick($track, 'title')); ?></h1>
<p><?php echo hkp_h(hkp_pick($track, 'summary')); ?></p></div></div>
<section class="hkp-card">
  <ol class="hkp-list">
  <?php foreach ($modules as $i => $m): ?>
    <li><span><strong><?php echo ($i + 1) . '. '; ?><a href="<?php echo hkp_url('learn/module/' . $m['course_id']); ?>"><?php echo hkp_h($m['title']); ?></a></strong>
      <span class="hkp-small hkp-muted"> · <?php echo (int) $m['duration_minutes']; ?> <?php echo hkp_e('min'); ?><?php echo (int) $m['is_mandatory'] ? ' · ' . hkp_e('mandatory') : ''; ?></span></span>
      <span><?php echo $m['e_status'] ? hkp_badge($m['e_status'] === 'completed' ? 'completed' : 'in_progress') : hkp_badge('not_started'); ?></span></li>
  <?php endforeach; ?>
  </ol>
</section>
