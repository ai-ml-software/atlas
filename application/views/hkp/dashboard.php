<?php
$first = $me ? $me['first_name'] : '';
$pct = $plan_total ? round(100 * $plan_done / $plan_total) : 0;
$status = $readiness ? $readiness['status'] : null;
$failed = array();
if ($readiness) {
    foreach ($readiness['checks'] as $c) {
        if (!$c['passed']) { $failed[] = $c; }
    }
}
$comp_met = count(array_filter($competencies, function ($c) { return $c['gap'] === 0; }));
?>
<section class="hkp-card hkp-card--hero" style="margin-bottom:1rem">
  <div class="hkp-eyebrow" style="color:var(--accent)"><?php echo hkp_e('What do I need to do?'); ?></div>
  <h1><?php echo hkp_e('Welcome, {name}', array('name' => $first)); ?></h1>
  <p class="hkp-muted" style="max-width:70ch"><?php echo hkp_h(hkp_pick($brand, 'welcome')); ?></p>
  <?php if ($next): ?>
    <p style="margin:.9rem 0 0"><a class="hkp-btn hkp-btn--accent" href="<?php echo hkp_url('learn/lesson/' . $next['next_lesson_id']); ?>"><?php echo hkp_icon('play'); ?> <?php echo hkp_e('Continue: {title}', array('title' => $next['title'])); ?></a></p>
  <?php elseif ($plan_total && $plan_done === $plan_total): ?>
    <p style="margin:.9rem 0 0"><?php echo hkp_badge('completed', hkp_t('All assigned learning complete')); ?></p>
  <?php endif; ?>
</section>

<div class="hkp-grid hkp-grid--4" style="margin-bottom:1rem">
  <div class="hkp-card hkp-tile">
    <span class="hkp-tile__label"><?php echo hkp_e('Learning completion'); ?></span>
    <span class="hkp-tile__value"><?php echo $pct; ?>%</span>
    <?php echo hkp_bar($pct); ?>
    <span class="hkp-tile__foot"><?php echo hkp_e('{done} of {total} modules', array('done' => $plan_done, 'total' => $plan_total)); ?></span>
  </div>
  <div class="hkp-card hkp-tile">
    <span class="hkp-tile__label"><?php echo hkp_e('Practical competency'); ?></span>
    <span class="hkp-tile__value"><?php echo $comp_met; ?>/<?php echo count($competencies); ?></span>
    <span class="hkp-tile__foot"><?php echo hkp_e('competencies at required level'); ?></span>
    <a href="<?php echo hkp_url('competencies'); ?>"><?php echo hkp_e('See my competencies'); ?> →</a>
  </div>
  <div class="hkp-card hkp-tile">
    <span class="hkp-tile__label"><?php echo hkp_e('Readiness'); ?></span>
    <span class="hkp-tile__value" style="font-size:1.3rem"><?php echo $status ? hkp_badge($status) : hkp_badge('none', hkp_t('Not calculated')); ?></span>
    <span class="hkp-tile__foot"><?php echo $readiness ? hkp_e('Calculated {t}', array('t' => hkp_date($readiness['calculated_at'], true))) : ''; ?></span>
    <?php if ($this->ha_auth->has('readiness.view')): ?><a href="<?php echo hkp_url('readiness/me'); ?>"><?php echo hkp_e('Why?'); ?> →</a><?php endif; ?>
  </div>
  <div class="hkp-card hkp-tile">
    <span class="hkp-tile__label"><?php echo hkp_e('Certificates'); ?></span>
    <span class="hkp-tile__value"><?php echo count(array_filter($certs, function ($c) { return $c['status'] === 'issued'; })); ?></span>
    <span class="hkp-tile__foot"><?php echo hkp_e('current certificates'); ?></span>
    <a href="<?php echo hkp_url('certificates'); ?>"><?php echo hkp_e('My certificates'); ?> →</a>
  </div>
</div>

<div class="hkp-grid hkp-grid--main">
  <div class="hkp-grid">
    <section class="hkp-card">
      <div class="hkp-head" style="margin:0 0 .5rem"><h2><?php echo hkp_e('My learning plan'); ?></h2><a href="<?php echo hkp_url('learn'); ?>"><?php echo hkp_e('View all'); ?> →</a></div>
      <?php if (!$plan): ?>
        <div class="hkp-empty"><?php echo hkp_e('Nothing is assigned to you yet.'); ?> <a href="<?php echo hkp_url('learn/paths'); ?>"><?php echo hkp_e('Browse learning paths'); ?></a></div>
      <?php else: ?>
      <div class="hkp-table-wrap"><table class="hkp-table">
        <thead><tr><th><?php echo hkp_e('Module'); ?></th><th><?php echo hkp_e('Progress'); ?></th><th><?php echo hkp_e('Due'); ?></th><th><?php echo hkp_e('Status'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($plan as $r): ?>
          <tr>
            <td><a href="<?php echo hkp_url('learn/module/' . $r['course_id']); ?>"><?php echo hkp_h($r['title']); ?></a><?php if ((int) $r['is_mandatory']): ?> <span class="hkp-small hkp-muted">· <?php echo hkp_e('mandatory'); ?></span><?php endif; ?></td>
            <td style="min-width:120px"><?php echo hkp_bar($r['progress_percentage']); ?> <span class="hkp-small"><?php echo hkp_pct($r['progress_percentage']); ?></span></td>
            <td class="hkp-small"><?php echo hkp_date($r['due_at']); ?></td>
            <td><?php echo hkp_badge($r['state']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>

    <?php if ($failed): ?>
    <section class="hkp-card">
      <h2><?php echo hkp_e('What stands between you and Ready'); ?></h2>
      <ul class="hkp-list">
        <?php foreach ($failed as $c): ?>
          <li><span><strong><?php echo hkp_e($c['label']); ?></strong><br><span class="hkp-small hkp-muted"><?php echo hkp_h($c['detail']); ?></span></span><?php echo hkp_badge($c['on_fail'] === 'not_ready' ? 'not_ready' : 'conditional', $c['on_fail'] === 'not_ready' ? hkp_t('Blocking') : hkp_t('Conditional')); ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php if ($recommend): ?>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Recommended for your gaps'); ?></h2>
      <ul class="hkp-list">
        <?php foreach ($recommend as $r): ?>
          <li><a href="<?php echo hkp_h($r['url']); ?>"><?php echo hkp_h($r['title']); ?></a><?php echo hkp_badge('neutral', hkp_label($r['type'])); ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>

  <div class="hkp-grid">
    <section class="hkp-card">
      <h2><?php echo hkp_e('Upcoming deadlines'); ?></h2>
      <?php if (!$deadlines && !$acks): ?><p class="hkp-muted"><?php echo hkp_e('Nothing due in the next two weeks.'); ?></p><?php endif; ?>
      <ul class="hkp-list">
        <?php foreach ($deadlines as $d): ?><li><span><?php echo hkp_h($d['title']); ?></span><span class="hkp-small"><?php echo hkp_date($d['due_at']); ?></span></li><?php endforeach; ?>
        <?php foreach ($acks as $a): ?><li><a href="<?php echo hkp_url('knowledge/item/' . $a['sop_id']); ?>"><?php echo hkp_e('Acknowledge: {t}', array('t' => $a['title'])); ?></a><span class="hkp-small"><?php echo hkp_date($a['due_at']); ?></span></li><?php endforeach; ?>
      </ul>
    </section>
    <section class="hkp-card">
      <h2><?php echo hkp_e('My action plans'); ?></h2>
      <?php if (!$actions): ?><p class="hkp-muted"><?php echo hkp_e('No open action plans.'); ?></p><?php endif; ?>
      <ul class="hkp-list">
        <?php foreach ($actions as $a): ?><li><a href="<?php echo hkp_url('actions/view/' . $a['id']); ?>"><?php echo hkp_h($a['title']); ?></a><?php echo hkp_badge($a['status']); ?></li><?php endforeach; ?>
      </ul>
    </section>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Ask the assistant'); ?></h2>
      <p class="hkp-small hkp-muted"><?php echo hkp_e('Answers come only from your organisation\'s approved knowledge, with the source shown.'); ?></p>
      <a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('assistant'); ?>"><?php echo hkp_icon('spark'); ?> <?php echo hkp_e('Open AI assistant'); ?></a>
    </section>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Latest notifications'); ?></h2>
      <ul class="hkp-list">
        <?php foreach ($notices as $n): ?><li><span class="hkp-small"><?php echo hkp_h(hkp_pick($n, 'title')); ?></span><span class="hkp-small hkp-muted"><?php echo hkp_date($n['created_at']); ?></span></li><?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
