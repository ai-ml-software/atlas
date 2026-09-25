<?php
$loc = hkp_locale();
$t = isset($doc['text'][$loc]) && trim((string) $doc['text'][$loc]['title']) !== '' ? $doc['text'][$loc] : (isset($doc['text']['en']) ? $doc['text']['en'] : null);
$fallback = $t && $t['locale'] !== $loc;
$labels = array('purpose' => 'Purpose', 'scope' => 'Scope', 'responsibilities' => 'Responsibilities', 'required_tools' => 'Tools and materials',
    'procedure' => 'Procedure', 'checklist' => 'Checklist', 'safety_notes' => 'Safety', 'quality_standard' => 'Quality standard',
    'escalation' => 'Escalation', 'related_documents' => 'Related documents');
$v = $doc['version'];
?>
<div class="hkp-head">
  <div><div class="hkp-eyebrow"><?php echo hkp_label($doc['item_type']); ?> · <?php echo hkp_h($doc['code']); ?></div>
  <h1><?php echo hkp_h($t ? $t['title'] : $doc['code']); ?></h1>
  <p class="hkp-small"><?php echo hkp_e('Version {v}', array('v' => $v ? $v['version_label'] : '—')); ?> · <?php echo $v ? hkp_badge($v['status']) : ''; ?>
    <?php if ($v && $v['effective_date']): ?> · <?php echo hkp_e('Effective {d}', array('d' => hkp_date($v['effective_date']))); ?><?php endif; ?>
    <?php if ($v && $v['review_date']): ?> · <?php echo hkp_e('Review by {d}', array('d' => hkp_date($v['review_date']))); ?><?php endif; ?></p></div>
  <div class="hkp-actions">
    <?php if ($can_edit): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('admin/content/edit/' . $doc['id']); ?>"><?php echo hkp_icon('pen'); ?> <?php echo hkp_e('Edit / workflow'); ?></a><?php endif; ?>
    <button class="hkp-btn hkp-btn--ghost" onclick="window.print()"><?php echo hkp_e('Print'); ?></button>
  </div>
</div>
<?php if ($fallback): ?><div class="hkp-flash hkp-flash--error"><?php echo hkp_e('This item is not yet available in Arabic; the English version is shown.'); ?></div><?php endif; ?>

<div class="hkp-grid hkp-grid--main">
  <article class="hkp-card hkp-lesson" lang="<?php echo $t ? $t['locale'] : 'en'; ?>" dir="<?php echo $t && $t['locale'] === 'ar' ? 'rtl' : 'ltr'; ?>">
    <?php if ($t): foreach ($labels as $f => $lab): if (trim((string) $t[$f]) === '') continue; ?>
      <h2><?php echo hkp_e($lab); ?></h2>
      <?php echo hkp_safe_html($t[$f]); ?>
    <?php endforeach; endif; ?>
  </article>
  <div class="hkp-grid">
    <?php if ((int) $doc['requires_acknowledgement'] && $doc['status'] === 'published'): ?>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Acknowledgement'); ?></h2>
      <?php if ($ack && $ack['status'] === 'acknowledged'): ?>
        <p><?php echo hkp_badge('completed', hkp_t('Acknowledged')); ?> <span class="hkp-small hkp-muted"><?php echo hkp_date($ack['acknowledged_at'], true); ?></span></p>
      <?php else: ?>
        <form method="post" action="<?php echo hkp_url('knowledge/ack/' . $doc['id']); ?>"><?php echo ha_csrf_field(); ?>
          <label class="hkp-check"><input type="checkbox" required> <?php echo hkp_e('I have read this version and will follow it.'); ?></label>
          <button class="hkp-btn" style="margin-top:.6rem"><?php echo hkp_e('Acknowledge'); ?></button></form>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Governance'); ?></h2>
      <dl class="hkp-kv">
        <dt><?php echo hkp_e('Domain'); ?></dt><dd><?php echo hkp_h(hkp_pick($doc, 'domain')) ?: '—'; ?></dd>
        <dt><?php echo hkp_e('Owner'); ?></dt><dd><?php echo hkp_h(trim($doc['owner_first'] . ' ' . $doc['owner_last'])) ?: hkp_badge('danger', hkp_t('No owner')); ?></dd>
        <dt><?php echo hkp_e('Scope'); ?></dt><dd><?php echo $doc['property_id'] ? hkp_e('Property') : ($doc['organization_id'] ? hkp_e('Organisation') : hkp_e('Altus global')); ?></dd>
        <dt><?php echo hkp_e('AI source'); ?></dt><dd><?php echo (int) $doc['ai_enabled'] ? hkp_badge('success', hkp_t('Used by assistant')) : hkp_badge('muted', hkp_t('Excluded')); ?></dd>
        <?php if ($v && $v['approver_first']): ?><dt><?php echo hkp_e('Approved by'); ?></dt><dd><?php echo hkp_h($v['approver_first'] . ' ' . $v['approver_last']); ?> · <?php echo hkp_date($v['approved_at']); ?></dd><?php endif; ?>
      </dl>
    </section>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Version history'); ?></h2>
      <ul class="hkp-list">
        <?php foreach ($doc['versions'] as $ver): if (!in_array($ver['status'], array('published', 'superseded'), true) && !$can_edit) continue; ?>
          <li><span><a href="?v=<?php echo (int) $ver['id']; ?>">v<?php echo hkp_h($ver['version_label']); ?></a> <span class="hkp-small hkp-muted"><?php echo hkp_h($ver['change_summary']); ?></span></span><?php echo hkp_badge($ver['status']); ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
