<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Master curriculum'); ?></div><h1><?php echo hkp_e('Learning paths'); ?></h1>
<p><?php echo hkp_e('Professional Domain → Track → Module → Lesson → Assessment & Certification.'); ?></p></div></div>

<?php if ($role): ?>
<section class="hkp-card" style="margin-bottom:1rem">
  <h2><?php echo hkp_e('Required for your role: {role}', array('role' => hkp_pick($role, 'title'))); ?></h2>
  <?php if (!$required): ?><p class="hkp-muted"><?php echo hkp_e('Your role has no required tracks yet.'); ?></p><?php endif; ?>
  <?php foreach ($required as $r): ?>
    <h3 style="margin-top:1rem"><a href="<?php echo hkp_url('learn/track/' . $r['item_id']); ?>"><?php echo hkp_h(hkp_pick($r, 'title')); ?></a> <span class="hkp-small hkp-muted">· <?php echo hkp_h(hkp_pick($r, 'domain')); ?></span></h3>
    <ol class="hkp-small">
      <?php foreach ($r['modules'] as $m): ?><li><a href="<?php echo hkp_url('learn/module/' . $m['course_id']); ?>"><?php echo hkp_h($m['title']); ?></a> <?php echo $m['e_status'] ? hkp_badge($m['e_status'] === 'completed' ? 'completed' : 'in_progress') : hkp_badge('not_started'); ?></li><?php endforeach; ?>
    </ol>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<div class="hkp-grid hkp-grid--3">
<?php foreach ($domains as $d): if (empty($tracks[$d['id']])) continue; ?>
  <section class="hkp-card">
    <div class="hkp-eyebrow"><?php echo hkp_h(hkp_label($d['domain_group'])); ?></div>
    <h2><?php echo hkp_h(hkp_pick($d, 'name')); ?></h2>
    <ul class="hkp-list">
      <?php foreach ($tracks[$d['id']] as $t): ?><li><a href="<?php echo hkp_url('learn/track/' . $t['id']); ?>"><?php echo hkp_h(hkp_pick($t, 'title')); ?></a></li><?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
</div>
