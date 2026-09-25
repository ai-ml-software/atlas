<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Where am I weak?'); ?></div><h1><?php echo hkp_e('My action plans'); ?></h1>
<p><?php echo hkp_e('Corrective actions agreed with your manager. Add evidence, submit, and your supervisor will review and reassess.'); ?></p></div></div>
<section class="hkp-card">
<?php if (!$rows): ?><div class="hkp-empty"><?php echo hkp_e('No action plans.'); ?></div><?php else: ?>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Action'); ?></th><th><?php echo hkp_e('Competency'); ?></th><th><?php echo hkp_e('Type'); ?></th><th><?php echo hkp_e('Priority'); ?></th><th><?php echo hkp_e('Due'); ?></th><th><?php echo hkp_e('Status'); ?></th></tr></thead><tbody>
  <?php foreach ($rows as $a): ?><tr><td><a href="<?php echo hkp_url('actions/view/' . $a['id']); ?>"><strong><?php echo hkp_h($a['title']); ?></strong></a></td><td><?php echo hkp_h(hkp_pick($a, 'skill')); ?></td>
    <td><?php echo hkp_label($a['action_type']); ?></td><td><?php echo hkp_badge($a['priority']); ?></td><td class="hkp-small"><?php echo hkp_date($a['due_at']); ?></td><td><?php echo hkp_badge($a['status']); ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</section>
