<?php $a = $paper['assessment']; $at = $paper['attempt']; $show = (int) $a['show_correct_answers']; ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Result'); ?> · <?php echo hkp_e('Attempt {n}', array('n' => $at['attempt_no'])); ?></div><h1><?php echo hkp_h(hkp_pick($a, 'title')); ?></h1>
<p><?php echo $at['status'] === 'graded' ? hkp_e('Score {s}% — pass mark {p}%', array('s' => $at['percentage'], 'p' => $a['pass_percentage'])) : hkp_e('Some answers are waiting for a person to mark them.'); ?></p></div>
<span style="font-size:1.3rem"><?php echo $at['status'] === 'graded' ? hkp_badge($at['passed'] ? 'passed' : 'failed') : hkp_badge('pending'); ?></span></div>
<div class="hkp-actions" style="margin-bottom:1rem"><?php if ($a['course_id']): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('learn/module/' . $a['course_id']); ?>"><?php echo hkp_e('Back to module'); ?></a><?php endif; ?>
<?php if ($at['status'] === 'graded' && !$at['passed']): ?><a class="hkp-btn" href="<?php echo hkp_url('assess/theory/' . $a['id']); ?>"><?php echo hkp_e('Try again'); ?></a><?php endif; ?></div>
<?php foreach ($paper['questions'] as $i => $q): $ans = $q['answer']; $picked = $ans ? array_filter(explode(',', (string) $ans['selected_option_ids'])) : array(); ?>
<section class="hkp-q">
  <p><strong><?php echo ($i + 1) . '. ' . hkp_h($q['body']); ?></strong> <span class="hkp-small"><?php echo $ans ? (float) $ans['awarded_marks'] . '/' . (float) $ans['max_marks'] : ''; ?></span>
    <?php echo $ans && $ans['is_correct'] !== null ? hkp_badge($ans['is_correct'] ? 'passed' : 'failed', $ans['is_correct'] ? hkp_t('Correct') : hkp_t('Incorrect')) : hkp_badge('pending'); ?></p>
  <?php foreach ($q['options'] as $o): $is_picked = in_array((string) $o['id'], $picked, true); $cls = $show && isset($o['is_correct']) ? ($o['is_correct'] ? ' is-correct' : ($is_picked ? ' is-wrong' : '')) : ''; ?>
    <div class="hkp-opt<?php echo $cls; ?>"><span><?php echo $is_picked ? '●' : '○'; ?></span><span><?php echo hkp_h($o['body']); ?></span></div>
  <?php endforeach; ?>
  <?php if ($ans && $ans['answer_text']): ?><div class="hkp-callout" dir="auto"><?php echo hkp_h($ans['answer_text']); ?></div><?php endif; ?>
  <?php if ($ans && $ans['grader_comment']): ?><p class="hkp-small"><?php echo hkp_e('Marker: {c}', array('c' => $ans['grader_comment'])); ?></p><?php endif; ?>
  <?php if ($q['explanation']): ?><p class="hkp-small hkp-muted"><?php echo hkp_e('Why: {e}', array('e' => $q['explanation'])); ?></p><?php endif; ?>
</section>
<?php endforeach; ?>
