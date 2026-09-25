<div class="hkp-head"><div><h1><?php echo hkp_e($e['title']); ?></h1><p><?php echo hkp_e('{n} records', array('n' => $data['total'])); ?></p></div>
<?php if ($this->ha_auth->has($e['perm'] . '.create') || in_array($e['perm'], array('corporate', 'system'), true) && $this->ha_auth->has($e['perm'] . '.update')): ?><a class="hkp-btn" href="<?php echo hkp_url('admin/crud/' . $e['key'] . '/new'); ?>"><?php echo hkp_e('Add'); ?></a><?php endif; ?></div>
<form class="hkp-card hkp-actions" method="get" style="margin-bottom:1rem"><label class="hkp-sr" for="cq"><?php echo hkp_e('Search'); ?></label><input id="cq" class="hkp-input" style="flex:1" name="q" value="<?php echo hkp_h($q); ?>" placeholder="<?php echo hkp_e('Search'); ?>"><button class="hkp-btn"><?php echo hkp_e('Search'); ?></button></form>
<section class="hkp-card"><?php if (!$data['rows']): ?><div class="hkp-empty"><?php echo hkp_e('Nothing here yet.'); ?></div><?php else: ?>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><?php foreach ($e['list'] as $c): ?><th><?php echo hkp_label($c); ?></th><?php endforeach; ?><th></th></tr></thead><tbody>
<?php foreach ($data['rows'] as $r): ?><tr>
  <?php foreach ($e['list'] as $i => $c): $v = $r[$c]; ?><td class="<?php echo $i ? 'hkp-small' : ''; ?>"><?php
    if ($c === 'organization_id') { echo $v ? hkp_h(isset($orgs[$v]) ? $orgs[$v] : '#' . $v) : hkp_e('Altus global'); }
    elseif ($c === 'status' || $c === 'visibility') { echo hkp_badge($v); }
    elseif (in_array($c, array('is_core', 'is_illustrative', 'is_official', 'enabled'), true)) { echo (int) $v ? '✓' : '—'; }
    elseif ($i === 0) { echo '<a href="' . hkp_url('admin/crud/' . $e['key'] . '/edit/' . $r['id']) . '"><strong>' . hkp_h($v) . '</strong></a>'; }
    else { echo hkp_h(strpos((string) $c, '_id') !== false ? '#' . $v : hkp_label($v)); } ?></td><?php endforeach; ?>
  <td><a class="hkp-btn hkp-btn--sm hkp-btn--ghost" href="<?php echo hkp_url('admin/crud/' . $e['key'] . '/edit/' . $r['id']); ?>"><?php echo hkp_e('Edit'); ?></a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($data['pages'] > 1): ?><div class="hkp-actions" style="margin-top:1rem"><?php for ($p = 1; $p <= $data['pages']; $p++): ?><a class="hkp-btn hkp-btn--sm<?php echo $p === $data['page'] ? '' : ' hkp-btn--ghost'; ?>" href="?page=<?php echo $p; ?>&amp;q=<?php echo rawurlencode((string) $q); ?>"><?php echo $p; ?></a><?php endfor; ?></div><?php endif; ?>
<?php endif; ?></section>
