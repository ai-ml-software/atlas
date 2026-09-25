<?php $pipe = $h['pipeline']; $by = array(); foreach ($pipe as $p) { $by[$p['status']][] = $p; } ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Governance & Growth'); ?></div><h1><?php echo hkp_e('Content review & quality control'); ?></h1>
<p><?php echo hkp_e('Draft → Internal review → Quality review → Approved → Published. Nothing becomes authoritative, or reaches the AI assistant, without the required approvals.'); ?></p></div>
<?php if ($this->ha_auth->has('knowledge.create')): ?><a class="hkp-btn" href="<?php echo hkp_url('admin/content/new'); ?>"><?php echo hkp_e('New knowledge item'); ?></a><?php endif; ?></div>
<div class="hkp-grid hkp-grid--4" style="margin-bottom:1rem">
<?php foreach (array('draft' => 'Draft', 'internal_review' => 'Pending review', 'quality_review' => 'Pending quality approval', 'approved' => 'Approved, not published', 'rejected' => 'Returned') as $k => $l): ?>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e($l); ?></span><span class="hkp-tile__value"><?php echo isset($by[$k]) ? count($by[$k]) : 0; ?></span></div>
<?php endforeach; ?>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Review due'); ?></span><span class="hkp-tile__value"><?php echo count($h['review_due']); ?></span></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Without translation'); ?></span><span class="hkp-tile__value"><?php echo count($h['untranslated']); ?></span></div>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e('Without owner'); ?></span><span class="hkp-tile__value"><?php echo count($h['no_owner']); ?></span></div>
</div>
<section class="hkp-card" style="margin-bottom:1rem"><h2><?php echo hkp_e('Review pipeline'); ?></h2>
  <?php if (!$pipe): ?><div class="hkp-empty"><?php echo hkp_e('Nothing is waiting for review.'); ?></div><?php endif; ?>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Item'); ?></th><th><?php echo hkp_e('Type'); ?></th><th><?php echo hkp_e('Version'); ?></th><th><?php echo hkp_e('Stage'); ?></th><th><?php echo hkp_e('Updated'); ?></th></tr></thead><tbody>
  <?php foreach ($pipe as $p): ?><tr><td><a href="<?php echo hkp_url('admin/content/edit/' . $p['id']); ?>"><?php echo hkp_h(hkp_pick($p, 'title') ?: $p['code']); ?></a></td><td class="hkp-small"><?php echo hkp_label($p['item_type']); ?></td><td>v<?php echo hkp_h($p['version_label']); ?></td><td><?php echo hkp_badge($p['status']); ?></td><td class="hkp-small"><?php echo hkp_date($p['updated_at'], true); ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
<div class="hkp-grid hkp-grid--3">
<?php foreach (array('review_due' => 'Expiring or due for review', 'untranslated' => 'Content without Arabic translation', 'no_owner' => 'Content without an owner', 'unused' => 'Not opened recently', 'expired' => 'Expired content') as $k => $l): ?>
  <section class="hkp-card"><h2><?php echo hkp_e($l); ?> <span class="hkp-small hkp-muted">(<?php echo count($h[$k]); ?>)</span></h2>
    <ul class="hkp-list"><?php foreach (array_slice($h[$k], 0, 8) as $r): ?><li><a class="hkp-small" href="<?php echo hkp_url('admin/content/edit/' . $r['id']); ?>"><?php echo hkp_h($r['title_en'] ?: $r['code']); ?></a><?php if (!empty($r['review_date'])): ?><span class="hkp-small hkp-muted"><?php echo hkp_date($r['review_date']); ?></span><?php endif; ?></li><?php endforeach; ?></ul></section>
<?php endforeach; ?>
  <section class="hkp-card"><h2><?php echo hkp_e('Duplicate titles'); ?></h2><ul class="hkp-list"><?php foreach ($h['duplicates'] as $d): ?><li><span class="hkp-small"><?php echo hkp_h($d['title_en']); ?></span><strong><?php echo (int) $d['n']; ?></strong></li><?php endforeach; ?></ul><?php if (!$h['duplicates']): ?><p class="hkp-muted hkp-small"><?php echo hkp_e('None.'); ?></p><?php endif; ?></section>
  <section class="hkp-card"><h2><?php echo hkp_e('Most searched'); ?></h2><ul class="hkp-list"><?php foreach ($h['top_searches'] as $s): ?><li><span class="hkp-small"><?php echo hkp_h($s['term']); ?></span><span class="hkp-small"><?php echo (int) $s['n']; ?><?php echo (int) $s['zero'] ? ' · ' . hkp_e('{n} with no result', array('n' => (int) $s['zero'])) : ''; ?></span></li><?php endforeach; ?></ul></section>
  <section class="hkp-card"><h2><?php echo hkp_e('Most used AI sources'); ?></h2><ul class="hkp-list"><?php foreach ($h['ai_sources'] as $s): ?><li><span class="hkp-small"><?php echo hkp_h($s['title']); ?></span><strong><?php echo (int) $s['n']; ?></strong></li><?php endforeach; ?></ul><?php if (!$h['ai_sources']): ?><p class="hkp-muted hkp-small"><?php echo hkp_e('No answered questions yet.'); ?></p><?php endif; ?></section>
</div>
