<div class="hkp-head">
  <div><div class="hkp-eyebrow"><?php echo hkp_e('Learning'); ?></div><h1><?php echo hkp_e('My learning'); ?></h1>
  <p><?php echo hkp_e('Courses you selected and modules assigned for your role. Competency is confirmed separately by assessment.'); ?></p></div>
  <div class="hkp-actions"><a class="hkp-btn hkp-btn--ghost" href="<?php echo base_url(hkp_locale() . '/courses'); ?>"><?php echo hkp_e('Courses'); ?></a>
  <a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('learn/paths'); ?>"><?php echo hkp_icon('route'); ?> <?php echo hkp_e('Learning paths'); ?></a></div>
</div>
<?php $this->load->view('hkp/learning_continue', array('next' => $next)); ?>
<section class="hkp-card">
<?php if (!$plan): ?>
  <div class="hkp-empty"><?php echo hkp_e('Nothing is assigned to you yet.'); ?> <a href="<?php echo base_url(hkp_locale() . '/courses'); ?>"><?php echo hkp_e('Courses'); ?></a></div>
<?php else: ?>
  <div class="hkp-table-wrap"><table class="hkp-table">
    <thead><tr><th><?php echo hkp_e('Module'); ?></th><th><?php echo hkp_e('Assignment'); ?></th><th><?php echo hkp_e('Progress'); ?></th><th><?php echo hkp_e('Started'); ?></th><th><?php echo hkp_e('Due'); ?></th><th><?php echo hkp_e('Status'); ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($plan as $r): ?>
      <tr>
        <td><?php if ($r['course_available']): ?><a href="<?php echo hkp_url('learn/module/' . $r['course_id']); ?>"><strong><?php echo hkp_h($r['title']); ?></strong></a><?php else: ?><strong><?php echo hkp_h($r['title']); ?></strong><?php endif; ?><div class="hkp-small hkp-muted"><?php echo (int) $r['duration_minutes']; ?> <?php echo hkp_e('min'); ?> · <?php echo hkp_label($r['level']); ?></div>
          <?php if ($r['availability_note']): ?><p class="hkp-small hkp-muted"><?php echo hkp_h($r['availability_note']); ?></p><?php endif; ?></td>
        <td class="hkp-small"><?php echo hkp_h($r['selection_label']); ?><?php if ($r['training_assignment_id']): ?><div><?php echo hkp_h(hkp_pick($r, 'assignment')); ?></div><?php endif; ?></td>
        <td style="min-width:140px"><?php echo hkp_bar($r['progress_percentage']); ?> <span class="hkp-small"><?php echo (int) $r['lessons_completed']; ?>/<?php echo (int) $r['lessons_total']; ?></span></td>
        <td class="hkp-small"><?php echo hkp_date($r['started_at']); ?></td>
        <td class="hkp-small"><?php echo hkp_date($r['due_at']); ?></td>
        <td><?php echo hkp_badge($r['state']); ?></td>
        <td><?php if ($r['can_resume']): ?><a class="hkp-btn hkp-btn--sm" href="<?php echo hkp_url('learn/lesson/' . $r['next_lesson_id']); ?>"><?php echo $r['started_at'] ? hkp_e('Resume') : hkp_e('Start'); ?></a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>
