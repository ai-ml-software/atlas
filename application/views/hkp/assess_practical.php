<?php $r = $pa['rubric']; ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Practical assessment'); ?> · <?php echo hkp_e('Attempt {n}', array('n' => $pa['attempt_no'])); ?><?php echo $pa['reassessment_id'] ? ' · ' . hkp_e('Reassessment') : ''; ?></div>
<h1><?php echo hkp_h(hkp_pick($r, 'title')); ?></h1>
<p><?php echo hkp_h($pa['first_name'] . ' ' . $pa['last_name']); ?> · <?php echo hkp_e('Assessor: {a}', array('a' => $pa['assessor_first'] . ' ' . $pa['assessor_last'])); ?> · <?php echo hkp_badge($pa['status']); ?></p></div>
<?php if ($pa['status'] === 'submitted'): ?><span style="font-size:1.25rem"><?php echo hkp_badge($pa['outcome']); ?> <?php echo hkp_pct($pa['weighted_score'], 1); ?></span><?php endif; ?></div>

<form method="post" enctype="multipart/form-data" data-rubric data-fractions='<?php echo hkp_h(json_encode($fractions)); ?>'>
<?php echo ha_csrf_field(); ?>
<div class="hkp-grid hkp-grid--main">
  <section class="hkp-card">
    <h2><?php echo hkp_e('Criteria'); ?></h2>
    <div class="hkp-table-wrap"><table class="hkp-table hkp-rubric">
      <thead><tr><th><?php echo hkp_e('Criterion'); ?></th><th class="hkp-num"><?php echo hkp_e('Weight'); ?></th><th><?php echo hkp_e('Rating'); ?></th><th><?php echo hkp_e('Comment'); ?></th></tr></thead>
      <tbody>
      <?php foreach ($r['criteria'] as $c): $s = isset($pa['scores'][$c['id']]) ? $pa['scores'][$c['id']] : null; ?>
        <tr data-weight="<?php echo (float) $c['weight']; ?>">
          <td><strong><?php echo hkp_h(hkp_pick($c, 'label')); ?></strong> <?php if ((int) $c['is_critical']): ?><?php echo hkp_badge('danger', hkp_t('Critical')); ?><?php endif; ?><?php if (hkp_pick($c, 'guidance')): ?><div class="hkp-small hkp-muted"><?php echo hkp_h(hkp_pick($c, 'guidance')); ?></div><?php endif; ?></td>
          <td class="hkp-num"><?php echo (float) $c['weight']; ?></td>
          <td><div class="hkp-rate" role="radiogroup" aria-label="<?php echo hkp_h(hkp_pick($c, 'label')); ?>">
            <?php foreach (Ha_practical::$ratings as $rt): ?><label><input type="radio" name="rating[<?php echo (int) $c['id']; ?>]" value="<?php echo $rt; ?>"<?php echo $s && $s['rating'] === $rt ? ' checked' : ''; ?><?php echo $editable ? '' : ' disabled'; ?>><span><?php echo hkp_label($rt); ?></span></label><?php endforeach; ?></div></td>
          <td><label class="hkp-sr" for="c<?php echo $c['id']; ?>"><?php echo hkp_e('Comment'); ?></label><input id="c<?php echo $c['id']; ?>" class="hkp-input" name="comment[<?php echo (int) $c['id']; ?>]" value="<?php echo hkp_h($s ? $s['comment'] : ''); ?>"<?php echo $editable ? '' : ' disabled'; ?>></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="hkp-field" style="margin-top:1rem"><label for="pc"><?php echo hkp_e('Overall comments'); ?></label><textarea id="pc" class="hkp-input" name="comments"<?php echo $editable ? '' : ' disabled'; ?>><?php echo hkp_h($pa['comments']); ?></textarea></div>
    <?php if ($editable): ?>
      <div class="hkp-field" style="margin-top:.8rem"><label for="pe"><?php echo hkp_e('Attach evidence (photo, video, document)'); ?></label><input id="pe" type="file" name="evidence" class="hkp-input"></div>
      <div class="hkp-actions" style="margin-top:1rem">
        <button class="hkp-btn hkp-btn--ghost" name="do" value="save"><?php echo hkp_e('Save draft'); ?></button>
        <button class="hkp-btn" name="do" value="submit" onclick="return confirm('<?php echo hkp_e('Submit this assessment? It cannot be changed afterwards.'); ?>')"><?php echo hkp_e('Submit assessment'); ?></button>
      </div>
    <?php endif; ?>
  </section>
  <div class="hkp-grid">
    <section class="hkp-card"><h2><?php echo hkp_e('How the score is calculated'); ?></h2>
      <p class="hkp-tile__value" data-score><?php echo hkp_pct($x['percentage'], 1); ?></p>
      <p class="hkp-small hkp-muted"><?php echo hkp_e('Each rating earns a share of the criterion weight: Not demonstrated {a}, Developing {b}, Competent {c}, Exceeds {d}.', array('a' => $fractions['not_demonstrated'], 'b' => $fractions['developing'], 'c' => $fractions['competent'], 'd' => $fractions['exceeds'])); ?></p>
      <dl class="hkp-kv"><dt><?php echo hkp_e('Developing from'); ?></dt><dd><?php echo hkp_pct($x['thresholds']['developing']); ?></dd><dt><?php echo hkp_e('Competent from'); ?></dt><dd><?php echo hkp_pct($x['thresholds']['competent']); ?></dd><dt><?php echo hkp_e('Exceeds from'); ?></dt><dd><?php echo hkp_pct($x['thresholds']['exceeds']); ?></dd></dl>
      <p class="hkp-small"><?php echo hkp_e('A critical criterion rated below Competent caps the outcome at Developing.'); ?></p>
      <?php if ($x['capped']): ?><p><?php echo hkp_badge('danger', hkp_t('Capped by a critical criterion')); ?></p><?php endif; ?>
      <p class="hkp-small"><?php echo hkp_e('Outcome now: {o} → competency level {l}', array('o' => hkp_label($x['outcome']), 'l' => $x['level'])); ?></p>
    </section>
    <section class="hkp-card"><h2><?php echo hkp_e('Evidence'); ?></h2>
      <?php if (!$pa['evidence']): ?><p class="hkp-muted"><?php echo hkp_e('No evidence attached.'); ?></p><?php endif; ?>
      <ul class="hkp-list"><?php foreach ($pa['evidence'] as $e): ?><li><span class="hkp-small"><?php echo hkp_h($e['title']); ?> · <?php echo hkp_label($e['evidence_type']); ?></span><?php if ($e['token']): ?><a href="<?php echo hkp_url('file/' . $e['token']); ?>"><?php echo hkp_icon('download'); ?></a><?php endif; ?></li><?php endforeach; ?></ul></section>
    <?php if ($history): ?><section class="hkp-card"><h2><?php echo hkp_e('Previous attempts'); ?></h2><ul class="hkp-list"><?php foreach ($history as $h): ?><li><a href="<?php echo hkp_url('assess/practical/' . $h['id']); ?>"><?php echo hkp_e('Attempt {n}', array('n' => $h['attempt_no'])); ?></a><span><?php echo hkp_pct($h['weighted_score'], 1); ?> <?php echo hkp_badge($h['outcome']); ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  </div>
</div>
</form>
