<?php $at = $paper['attempt']; ?>
<div class="hkp-head"><div><h1><?php echo hkp_e('Grade attempt'); ?></h1><p><?php echo hkp_h(hkp_pick($paper['assessment'], 'title')); ?> · <?php echo hkp_e('Attempt {n}', array('n' => $at['attempt_no'])); ?></p></div></div>
<form method="post" class="hkp-form"><?php echo ha_csrf_field(); ?>
<?php foreach ($paper['questions'] as $i => $q): $ans = $q['answer']; if (!$ans || $ans['is_correct'] !== null || trim((string) $ans['answer_text']) === '') continue; ?>
  <section class="hkp-q"><p><strong><?php echo ($i + 1) . '. ' . hkp_h($q['body']); ?></strong></p>
    <div class="hkp-callout" dir="auto"><?php echo hkp_h($ans['answer_text']); ?></div>
    <div class="hkp-row"><div class="hkp-field"><label for="mk<?php echo $q['id']; ?>"><?php echo hkp_e('Marks (max {m})', array('m' => (float) $ans['max_marks'])); ?></label><input id="mk<?php echo $q['id']; ?>" class="hkp-input" type="number" step="0.5" min="0" max="<?php echo (float) $ans['max_marks']; ?>" name="marks[<?php echo $q['id']; ?>]" required></div>
    <div class="hkp-field"><label for="cm<?php echo $q['id']; ?>"><?php echo hkp_e('Feedback'); ?></label><input id="cm<?php echo $q['id']; ?>" class="hkp-input" name="comment[<?php echo $q['id']; ?>]"></div></div></section>
<?php endforeach; ?>
<div><button class="hkp-btn"><?php echo hkp_e('Save grades'); ?></button></div></form>
