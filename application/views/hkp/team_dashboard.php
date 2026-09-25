<?php $all = isset($readiness['all']) ? $readiness['all'] : array('ready' => 0, 'conditional' => 0, 'not_ready' => 0, 'not_calculated' => 0, 'total' => 0, 'ready_pct' => 0); ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Where are the operational capability gaps?'); ?></div>
<h1><?php echo $property ? hkp_h(hkp_pick($property, 'name')) : hkp_e('Team dashboard'); ?></h1>
<p><?php echo hkp_e('Learning, knowledge assessment, practical competency, readiness and performance are shown separately. Completion is never presented as competence.'); ?></p></div>
<div class="hkp-actions"><?php if ($this->ha_auth->has('training_assignments.assign')): ?><a class="hkp-btn" href="<?php echo hkp_url('team/assign'); ?>"><?php echo hkp_icon('send'); ?> <?php echo hkp_e('Assign learning'); ?></a><?php endif; ?><?php if ($this->ha_auth->has('reports.view')): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('team/reports'); ?>"><?php echo hkp_icon('file'); ?> <?php echo hkp_e('Reports'); ?></a><?php endif; ?></div></div>

<div class="hkp-grid hkp-grid--4" style="margin-bottom:1rem">
  <a class="hkp-card hkp-tile" href="<?php echo hkp_url('team/people'); ?>" style="text-decoration:none"><span class="hkp-tile__label"><?php echo hkp_e('Staff'); ?></span><span class="hkp-tile__value"><?php echo (int) $people; ?></span><span class="hkp-tile__foot"><?php echo hkp_e('{n} assignments · {o} overdue', array('n' => $assigned, 'o' => $overdue)); ?></span></a>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Learning completion'); ?></span><span class="hkp-tile__value"><?php echo hkp_pct($k['learning_completion']); ?></span><?php echo hkp_bar($k['learning_completion']); ?></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Assessment pass rate'); ?></span><span class="hkp-tile__value"><?php echo hkp_pct($k['assessment_pass_rate']); ?></span><span class="hkp-tile__foot"><?php echo hkp_e('knowledge assessments'); ?></span></div>
  <?php $t0 = $this->ha_auth->has('gaps.view'); ?><<?php echo $t0 ? 'a href="' . hkp_url('team/gaps') . '" style="text-decoration:none"' : 'div'; ?> class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Competency coverage'); ?></span><span class="hkp-tile__value"><?php echo hkp_pct($k['competency_coverage']); ?></span><span class="hkp-tile__foot"><?php echo hkp_e('{n} critical gaps', array('n' => $k['critical_gaps'])); ?></span></<?php echo $t0 ? 'a' : 'div'; ?>>
  <?php $t1 = $this->ha_auth->has('readiness.view'); ?><<?php echo $t1 ? 'a href="' . hkp_url('team/readiness') . '" style="text-decoration:none"' : 'div'; ?> class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Ready'); ?></span><span class="hkp-tile__value"><?php echo hkp_pct($all['ready_pct']); ?></span>
    <div class="hkp-stack"><?php foreach (array('ready', 'conditional', 'not_ready', 'not_calculated') as $s): if (!$all[$s]) continue; ?><span class="<?php echo $s; ?>" style="inline-size:<?php echo round(100 * $all[$s] / max(1, $all['total']), 1); ?>%" title="<?php echo hkp_label($s); ?>: <?php echo (int) $all[$s]; ?>"></span><?php endforeach; ?></div>
    <span class="hkp-tile__foot"><?php echo hkp_e('{r} ready · {c} conditional · {n} not ready', array('r' => $all['ready'], 'c' => $all['conditional'], 'n' => $all['not_ready'])); ?></span></<?php echo $t1 ? 'a' : 'div'; ?>>
  <?php $t2 = $this->ha_auth->has(array('action_plans.review', 'action_plans.view')); ?><<?php echo $t2 ? 'a href="' . hkp_url('team/actions') . '" style="text-decoration:none"' : 'div'; ?> class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Outstanding actions'); ?></span><span class="hkp-tile__value"><?php echo (int) $open_actions; ?></span><span class="hkp-tile__foot"><?php echo hkp_e('{n} overdue', array('n' => $k['overdue_actions'])); ?></span></<?php echo $t2 ? 'a' : 'div'; ?>>
  <?php $co = $this->ha_auth->has(array('certificates.export', 'certificates.issue', 'certificates.view')); ?><<?php echo $co ? 'a href="' . hkp_url('team/certifications') . '" style="text-decoration:none"' : 'div'; ?> class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Certification coverage'); ?></span><span class="hkp-tile__value"><?php echo hkp_pct($cert['rate']); ?></span><span class="hkp-tile__foot"><?php echo hkp_e('{n} expiring soon', array('n' => $cert['expiring'])); ?></span></<?php echo $co ? 'a' : 'div'; ?>>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Data freshness'); ?></span><span class="hkp-tile__value" style="font-size:1rem"><?php echo hkp_date($k['calculated_at'], true); ?></span><span class="hkp-tile__foot"><?php echo hkp_e('calculated live from the evidence chain'); ?></span></div>
</div>

<div class="hkp-grid hkp-grid--main">
  <section class="hkp-card"><h2><?php echo hkp_e('Department breakdown'); ?></h2>
    <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Department'); ?></th><th class="hkp-num"><?php echo hkp_e('Staff'); ?></th><th><?php echo hkp_e('Learning'); ?></th><th class="hkp-num"><?php echo hkp_e('Ready'); ?></th><th class="hkp-num"><?php echo hkp_e('Critical gaps'); ?></th></tr></thead><tbody>
    <?php foreach ($dept as $d): ?><tr><td><a href="<?php echo hkp_url('team/people?department=' . $d['id']); ?>"><?php echo hkp_h(hkp_pick($d, 'name')); ?></a></td><td class="hkp-num"><?php echo (int) $d['staff']; ?></td>
      <td style="min-width:120px"><?php echo hkp_bar($d['completion']); ?> <span class="hkp-small"><?php echo hkp_pct($d['completion']); ?></span></td><td class="hkp-num"><?php echo hkp_pct($d['ready_pct']); ?></td>
      <td class="hkp-num"><?php echo $d['critical'] ? hkp_badge('critical', $d['critical']) : '0'; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
  <div class="hkp-grid">
    <section class="hkp-card"><h2><?php echo hkp_e('Alerts'); ?></h2>
      <?php if (!$alerts): ?><p class="hkp-muted"><?php echo hkp_e('No open alerts.'); ?></p><?php endif; ?>
      <ul class="hkp-list"><?php foreach ($alerts as $a): ?><li><a href="<?php echo hkp_h($a['url'] ?: hkp_url('team')); ?>"><?php echo hkp_h(hkp_pick($a, 'title')); ?></a><?php echo hkp_badge($a['severity']); ?></li><?php endforeach; ?></ul></section>
    <section class="hkp-card"><h2><?php echo hkp_e('Top gaps'); ?></h2>
      <?php if (!$gaps): ?><p class="hkp-muted"><?php echo hkp_e('No open gaps.'); ?></p><?php endif; ?>
      <ul class="hkp-list"><?php foreach ($gaps as $g): ?><li><span><?php echo hkp_person_open($g['user_id']); ?><?php echo hkp_h($g['first_name'] . ' ' . $g['last_name']); ?><?php echo hkp_person_close(); ?> · <?php echo hkp_h(hkp_pick($g, 'name')); ?></span><?php echo hkp_badge($g['severity']); ?></li><?php endforeach; ?></ul></section>
  </div>
</div>
