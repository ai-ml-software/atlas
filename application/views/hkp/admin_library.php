<div class="hkp-head"><div><h1><?php echo hkp_e('Library and language coverage'); ?></h1><p><?php echo hkp_e('Published courses remain available while revised content is reviewed.'); ?></p></div></div>
<div class="hkp-grid">
  <article class="hkp-card"><h2><?php echo hkp_e('PDF source coverage'); ?></h2><p><?php echo hkp_h($source_report['files']); ?> / 41 · <?php echo hkp_h($source_report['pages']); ?> / 368</p><p><?php echo hkp_e('Reviewed pages: {count}',array('count' => $source_report['reviewed_pages'])); ?></p></article>
  <article class="hkp-card"><h2><?php echo hkp_e('Translation units'); ?></h2><p><?php echo (int) $unit_count; ?></p><p><?php echo hkp_e('Missing translations and English fallback do not count as completed coverage.'); ?></p></article>
  <article class="hkp-card"><h2><?php echo hkp_e('Revision review'); ?></h2><?php foreach ($revision_counts as $r): ?><p><?php echo hkp_h($r['status']); ?>: <?php echo (int) $r['total']; ?></p><?php endforeach; ?></article>
  <article class="hkp-card"><h2><?php echo hkp_e('Video verification'); ?></h2><?php foreach ($video_counts as $r): ?><p><?php echo hkp_h($r['status']); ?>: <?php echo (int) $r['total']; ?></p><?php endforeach; ?></article>
</div>
<article class="hkp-card" style="margin-top:1rem"><h2><?php echo hkp_e('Source review gaps'); ?></h2><ul><?php foreach (array_slice($source_report['issues'],0,50) as $r): ?><li><?php echo hkp_h($r['source']); ?><?php echo isset($r['page']) ? ' · ' . (int) $r['page'] : ''; ?>: <?php echo hkp_h($r['problem']); ?></li><?php endforeach; ?></ul><?php if (count($source_report['issues'])>50): ?><p><?php echo hkp_e('The complete report is available through the library audit command.'); ?></p><?php endif; ?></article>
<article class="hkp-card" style="margin-top:1rem"><h2><?php echo hkp_e('Global language inventory'); ?></h2>
  <p><?php foreach ($language_counts as $r): ?><?php echo hkp_h($r['status']); ?>: <?php echo (int) $r['total']; ?> · <?php endforeach; ?></p>
  <form method="get"><label><?php echo hkp_e('Find a language'); ?> <input name="q" value="<?php echo hkp_h($q); ?>"></label><button class="hkp-btn"><?php echo hkp_e('Search'); ?></button></form>
  <div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Language'); ?></th><th><?php echo hkp_e('Code'); ?></th><th><?php echo hkp_e('Modality'); ?></th><th><?php echo hkp_e('Review status'); ?></th><th><?php echo hkp_e('Availability'); ?></th></tr></thead><tbody>
  <?php foreach ($languages as $r): ?><tr><td><?php echo hkp_h($r['name']); ?></td><td><?php echo hkp_h($r['locale']); ?></td><td><?php echo hkp_h($r['modality']); ?></td><td><?php echo hkp_h($r['status']); ?></td><td><?php echo $r['enabled'] ? hkp_e('Released') : (ha_locale_enabled($r['locale']) ? hkp_e('Existing language; review pending') : hkp_e('Pending')); ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php if ($offset): ?><a href="<?php echo hkp_h(hkp_url('admin/library') . '?q=' . rawurlencode($q) . '&offset=' . max(0,$offset-100)); ?>"><?php echo hkp_e('Previous'); ?></a><?php endif; ?>
  <?php if (count($languages)===100): ?><a href="<?php echo hkp_h(hkp_url('admin/library') . '?q=' . rawurlencode($q) . '&offset=' . ($offset+100)); ?>"><?php echo hkp_e('Next'); ?></a><?php endif; ?>
</article>
