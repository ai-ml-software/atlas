<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_h(hkp_pick($brand, 'brand_name')); ?></div><h1><?php echo hkp_h($data['title']); ?></h1>
<?php foreach ($lines as $l): ?><div class="hkp-small hkp-muted"><?php echo hkp_h($l); ?></div><?php endforeach; ?></div>
<div class="hkp-actions hkp-noprint"><?php $qs = $_GET; foreach (array('xlsx' => 'Excel', 'csv' => 'CSV') as $fmt => $lab): $qs['format'] = $fmt; ?><a class="hkp-btn hkp-btn--ghost hkp-btn--sm" href="?<?php echo hkp_h(http_build_query($qs)); ?>"><?php echo hkp_icon('download'); ?> <?php echo $lab; ?></a><?php endforeach; ?><button class="hkp-btn hkp-btn--sm" onclick="window.print()"><?php echo hkp_e('Print / save as PDF'); ?></button></div></div>
<section class="hkp-card"><p class="hkp-small hkp-muted"><?php echo hkp_e('{n} rows', array('n' => count($data['rows']))); ?></p>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><?php foreach ($data['header'] as $h): ?><th><?php echo hkp_h($h); ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($data['rows'] as $r): ?><tr><?php foreach ($r as $v): ?><td class="hkp-small"><?php echo hkp_h($v); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></section>
<?php if (!empty($print)): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
