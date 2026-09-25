<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Can I actually do it?'); ?></div><h1><?php echo hkp_e('My competencies'); ?></h1>
<p><?php echo hkp_e('Each level is backed by assessment evidence. Completing a course does not change a level; a knowledge test or a supervisor\'s practical assessment does.'); ?></p></div>
<?php if ($this->ha_auth->has('readiness.view')): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('readiness/me'); ?>"><?php echo hkp_icon('gauge'); ?> <?php echo hkp_e('My readiness'); ?></a><?php endif; ?></div>

<div class="hkp-grid hkp-grid--main">
  <section class="hkp-card">
    <h2><?php echo hkp_e('Required for my role'); ?></h2>
    <?php if (!$profile): ?><div class="hkp-empty"><?php echo hkp_e('Your role has no required competencies yet.'); ?></div><?php else: ?>
    <div class="hkp-table-wrap"><table class="hkp-table hkp-heat">
      <thead><tr><th><?php echo hkp_e('Competency'); ?></th><th class="hkp-num"><?php echo hkp_e('Current'); ?></th><th class="hkp-num"><?php echo hkp_e('Required'); ?></th><th><?php echo hkp_e('Gap'); ?></th><th><?php echo hkp_e('Assessed by'); ?></th><th class="hkp-num"><?php echo hkp_e('Evidence'); ?></th></tr></thead>
      <tbody>
      <?php foreach ($profile as $c): ?>
        <tr>
          <td><strong><?php echo hkp_h(hkp_pick($c, 'name')); ?></strong><?php if ($c['critical']): ?> <?php echo hkp_badge('danger', hkp_t('Critical')); ?><?php endif; ?></td>
          <td class="c s-<?php echo $c['gap'] ? $c['severity'] : 'none'; ?>"><?php echo (int) $c['current']; ?> <span class="hkp-small">· <?php echo hkp_h($this->ha_competency->level_name($c['current'])); ?></span></td>
          <td class="hkp-num"><?php echo (int) $c['required']; ?></td>
          <td><?php echo $c['gap'] ? hkp_badge($c['severity'], hkp_t('{n} level(s)', array('n' => $c['gap']))) : hkp_badge('success', hkp_t('Met')); ?></td>
          <td class="hkp-small"><?php echo hkp_label($c['method']); ?></td>
          <td class="hkp-num"><?php echo (int) $c['evidence']; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="hkp-legend"><?php foreach ($levels as $n => $lv): ?><span><strong><?php echo (int) $n; ?></strong> <?php echo hkp_h(hkp_pick($lv, 'name')); ?></span><?php endforeach; ?></div>
    <?php endif; ?>
  </section>
  <div class="hkp-grid">
    <section class="hkp-card">
      <h2><?php echo hkp_e('Open gaps'); ?></h2>
      <?php if (!$gaps): ?><p class="hkp-muted"><?php echo hkp_e('No open gaps.'); ?></p><?php endif; ?>
      <ul class="hkp-list">
      <?php foreach ($gaps as $g): ?>
        <li><div><strong><?php echo hkp_h(hkp_pick($g, 'name')); ?></strong><div class="hkp-small hkp-muted"><?php echo hkp_label($g['reason']); ?> · <?php echo hkp_e('Level {c} of {r}', array('c' => $g['current_level'], 'r' => $g['required_level'])); ?></div></div>
          <div><?php echo hkp_badge($g['severity']); ?>
          <?php if ($this->ha_auth->has('reassessments.request') && $g['status'] === 'open'): ?>
            <form method="post" action="<?php echo hkp_url('competencies/request/' . $g['id']); ?>" style="margin-top:.4rem"><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--sm hkp-btn--ghost"><?php echo hkp_e('Request reassessment'); ?></button></form>
          <?php endif; ?></div></li>
      <?php endforeach; ?>
      </ul>
    </section>
    <?php if ($recommend): ?>
    <section class="hkp-card"><h2><?php echo hkp_e('Recommended practice'); ?></h2>
      <ul class="hkp-list"><?php foreach ($recommend as $r): ?><li><a href="<?php echo hkp_h($r['url']); ?>"><?php echo hkp_h($r['title']); ?></a><?php echo hkp_badge('neutral', hkp_label($r['type'])); ?></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
    <?php if ($reassess): ?>
    <section class="hkp-card"><h2><?php echo hkp_e('Reassessments'); ?></h2>
      <ul class="hkp-list"><?php foreach ($reassess as $r): ?><li><span class="hkp-small"><?php echo hkp_date($r['created_at']); ?></span><?php echo hkp_badge($r['status']); ?></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
  </div>
</div>

<section class="hkp-card" style="margin-top:1rem">
  <h2><?php echo hkp_e('Assessment history'); ?></h2>
  <p class="hkp-small hkp-muted"><?php echo hkp_e('Every result is kept. A reassessment adds a record; it never overwrites the original.'); ?></p>
  <div class="hkp-table-wrap"><table class="hkp-table">
    <thead><tr><th><?php echo hkp_e('Date'); ?></th><th><?php echo hkp_e('Competency'); ?></th><th><?php echo hkp_e('Source'); ?></th><th class="hkp-num"><?php echo hkp_e('Level'); ?></th><th><?php echo hkp_e('Assessor'); ?></th><th><?php echo hkp_e('Notes'); ?></th></tr></thead>
    <tbody><?php foreach ($history as $h): ?>
      <tr><td class="hkp-small"><?php echo hkp_date($h['assessed_at'], true); ?></td><td><?php echo hkp_h(hkp_pick($h, 'name')); ?></td><td><?php echo hkp_badge('neutral', hkp_label($h['source'])); ?></td>
        <td class="hkp-num"><?php echo (int) $h['previous_level_no']; ?> → <strong><?php echo (int) $h['level_no']; ?></strong></td>
        <td class="hkp-small"><?php echo hkp_h(trim($h['assessor_first'] . ' ' . $h['assessor_last'])) ?: '—'; ?></td><td class="hkp-small"><?php echo hkp_h($h['notes']); ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
