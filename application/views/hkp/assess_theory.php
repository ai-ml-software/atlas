<?php $a = $paper['assessment']; $at = $paper['attempt']; ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Knowledge assessment'); ?> · <?php echo hkp_e('Attempt {n}', array('n' => $at['attempt_no'])); ?></div><h1><?php echo hkp_h(hkp_pick($a, 'title')); ?></h1>
<p><?php echo hkp_e('Pass mark {p}%. {n} questions.', array('p' => (int) $a['pass_percentage'], 'n' => count($paper['questions']))); ?><?php if ($at['expires_at']): ?> <strong><?php echo hkp_e('Submit by {t}.', array('t' => hkp_date($at['expires_at'], true))); ?></strong><?php endif; ?></p></div></div>
<?php if (hkp_pick($a, 'instructions')): ?><div class="hkp-callout"><?php echo hkp_safe_html(hkp_pick($a, 'instructions')); ?></div><?php endif; ?>
<form method="post" action="<?php echo hkp_url('assess/theory/' . $a['id']); ?>" data-confirm="<?php echo hkp_e('Submit your answers? You cannot change them afterwards.'); ?>">
  <?php echo ha_csrf_field(); ?><input type="hidden" name="attempt_id" value="<?php echo (int) $at['id']; ?>">
  <?php foreach ($paper['questions'] as $i => $q): $name = 'q[' . $q['id'] . ']'; ?>
  <fieldset class="hkp-q">
    <legend><?php echo ($i + 1) . '. ' . hkp_h($q['body']); ?> <span class="hkp-small hkp-muted">(<?php echo (float) $q['marks']; ?>)</span></legend>
    <?php if (!$q['translated']): ?><p class="hkp-small hkp-muted"><?php echo hkp_e('Not yet translated; shown in English.'); ?></p><?php endif; ?>
    <?php if (in_array($q['type'], array('multiple_choice', 'true_false', 'scenario'), true)): foreach ($q['options'] as $o): ?>
      <label class="hkp-opt"><input type="radio" name="<?php echo $name; ?>" value="<?php echo (int) $o['id']; ?>" required><span><?php echo hkp_h($o['body']); ?></span></label>
    <?php endforeach; elseif ($q['type'] === 'multiple_response'): ?>
      <p class="hkp-small hkp-muted"><?php echo hkp_e('Select every correct answer.'); ?></p>
      <?php foreach ($q['options'] as $o): ?><label class="hkp-opt"><input type="checkbox" name="<?php echo $name; ?>[]" value="<?php echo (int) $o['id']; ?>"><span><?php echo hkp_h($o['body']); ?></span></label><?php endforeach; ?>
    <?php elseif ($q['type'] === 'ordering'): ?>
      <p class="hkp-small hkp-muted"><?php echo hkp_e('Drag into the correct order (or focus an item and press Alt + arrow keys).'); ?></p>
      <input type="hidden" id="ord<?php echo $q['id']; ?>" name="order[<?php echo $q['id']; ?>]">
      <ol class="hkp-sortable" data-sortable="ord<?php echo $q['id']; ?>" style="padding:0;list-style:none"><?php foreach ($q['options'] as $o): ?><li data-id="<?php echo (int) $o['id']; ?>"><span class="hkp-handle" aria-hidden="true">⠿</span><?php echo hkp_h($o['body']); ?></li><?php endforeach; ?></ol>
    <?php elseif ($q['type'] === 'matching'): $keys = array_values(array_unique(array_filter(array_column($q['options'], 'match')))); sort($keys); ?>
      <?php foreach ($q['options'] as $o): ?><div class="hkp-opt"><span style="flex:1"><?php echo hkp_h($o['body']); ?></span>
        <label class="hkp-sr" for="m<?php echo $o['id']; ?>"><?php echo hkp_e('Match for {x}', array('x' => $o['body'])); ?></label>
        <select id="m<?php echo $o['id']; ?>" class="hkp-select" style="max-width:260px" name="<?php echo $name; ?>[<?php echo (int) $o['id']; ?>]"><option value=""><?php echo hkp_e('Choose…'); ?></option><?php foreach ($keys as $k): ?><option><?php echo hkp_h($k); ?></option><?php endforeach; ?></select></div><?php endforeach; ?>
    <?php else: ?>
      <label class="hkp-sr" for="t<?php echo $q['id']; ?>"><?php echo hkp_e('Your answer'); ?></label>
      <textarea id="t<?php echo $q['id']; ?>" class="hkp-input" name="<?php echo $name; ?>" rows="<?php echo $q['type'] === 'essay' ? 6 : 2; ?>" dir="auto"></textarea>
    <?php endif; ?>
  </fieldset>
  <?php endforeach; ?>
  <button class="hkp-btn"><?php echo hkp_e('Submit answers'); ?></button>
</form>
