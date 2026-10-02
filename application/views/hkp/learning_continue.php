<?php if ($next): ?>
<section class="hkp-card" data-learning-continue style="margin-bottom:1rem">
  <div class="hkp-eyebrow"><?php echo hkp_e('Continue learning'); ?></div>
  <h2><?php echo hkp_h($next['title']); ?></h2>
  <p class="hkp-small hkp-muted"><?php echo hkp_h($next['selection_label']); ?><?php if ($next['lesson_title']): ?> · <?php echo hkp_h($next['lesson_title']); ?><?php endif; ?></p>
  <?php echo hkp_bar($next['progress_percentage']); ?>
  <?php if ($next['saved_position'] > 0): ?>
    <p class="hkp-small"><?php echo hkp_e('Saved video position: {time}', array('time' => sprintf('%d:%02d', floor($next['saved_position'] / 60), $next['saved_position'] % 60))); ?></p>
  <?php endif; ?>
  <?php if (!$next['next_lesson_id']): ?><p class="hkp-small"><?php echo hkp_e('Review your assessments to finish this course.'); ?></p><?php endif; ?>
  <a class="hkp-btn hkp-btn--accent" href="<?php echo hkp_h($next['continue_url']); ?>"><?php echo $next['can_resume'] ? hkp_e('Continue: {title}', array('title' => $next['title'])) : hkp_e('View course'); ?></a>
</section>
<?php endif; ?>
