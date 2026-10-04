<div class="hkp-head"><div><div class="hkp-eyebrow"><?= hkp_e('Website studio') ?></div><h1><?= hkp_e('Revision history') ?></h1><p><?= hkp_e('Every publication of content, theme and menus is kept. Restoring a revision creates a private draft; nothing changes for visitors until you publish it.') ?></p></div></div>
<form method="get" class="hkp-card studio-revision-filter" action="<?= hkp_url('studio/revisions') ?>" role="search">
    <label class="hkp-field" for="rev-type"><?= hkp_e('Content type') ?>
        <select class="hkp-select" id="rev-type" name="type"><option value=""><?= hkp_e('All types') ?></option><?php foreach ($types as $k => $label): ?><option value="<?= hkp_h($k) ?>"<?= $k === $type ? ' selected' : '' ?>><?= hkp_h($label) ?></option><?php endforeach; ?></select>
    </label>
    <label class="hkp-field" for="rev-id"><?= hkp_e('Record ID') ?><input class="hkp-input" id="rev-id" name="id" type="number" min="0" inputmode="numeric" value="<?= $object_id ?: '' ?>" dir="ltr"></label>
    <div class="hkp-actions"><button class="hkp-btn" type="submit"><?= hkp_e('Filter') ?></button><?php if ($type !== '' || $object_id): ?><a class="hkp-btn hkp-btn--ghost" href="<?= hkp_url('studio/revisions') ?>"><?= hkp_e('Clear') ?></a><?php endif; ?></div>
</form>
<section class="hkp-card"><div class="hkp-table-wrap"><table class="hkp-table studio-revision-table">
<caption class="hkp-visually-hidden"><?= hkp_e('Published revisions, newest first') ?></caption>
<thead><tr><th scope="col"><?= hkp_e('Revision') ?></th><th scope="col"><?= hkp_e('Content') ?></th><th scope="col"><?= hkp_e('Type') ?></th><th scope="col"><?= hkp_e('Published by') ?></th><th scope="col"><?= hkp_e('Date') ?></th><th scope="col"><span class="hkp-visually-hidden"><?= hkp_e('Actions') ?></span></th></tr></thead><tbody>
<?php foreach ($rows as $r): $state = $r['state']; ?>
<tr>
    <td class="hkp-num">#<?= (int) $r['id'] ?></td>
    <td><strong><?= hkp_h($r['label']) ?></strong> <span class="hkp-small hkp-muted" dir="ltr">(<?= hkp_h($r['object_type']) ?> #<?= (int) $r['object_id'] ?>)</span><?php if ($r['current']): ?> <?= hkp_badge('active', hkp_t('Live')) ?><?php endif; ?><?php if ($state && $state['version']): ?> <?= hkp_badge('warning', hkp_t('Draft open')) ?><?php endif; ?></td>
    <td><?= hkp_h($types[$r['object_type']] ?? $r['object_type']) ?></td>
    <td><?= hkp_h(trim($r['first_name'] . ' ' . $r['last_name']) ?: hkp_t('System')) ?></td>
    <td><?= hkp_date($r['created_at'], true) ?></td>
    <td><?php if ($state): ?><form method="post" action="<?= hkp_url('studio/revision_restore/' . (int) $r['id']) ?>" class="studio-inline-form" data-confirm="<?= hkp_e('Replace the current private draft with this revision?') ?>"><?= ha_csrf_field() ?><input type="hidden" name="draft_version" value="<?= (int) $state['version'] ?>"><input type="hidden" name="base_hash" value="<?= hkp_h($state['base_hash']) ?>">
        <button class="hkp-btn hkp-btn--sm hkp-btn--ghost" type="submit" aria-label="<?= hkp_e('Restore revision') ?> #<?= (int) $r['id'] ?>"><?= hkp_e('Restore to draft') ?></button></form><?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6"><p class="hkp-muted"><?= hkp_e('No published revisions yet. Revisions appear after the first studio publication.') ?></p></td></tr><?php endif; ?>
</tbody></table></div>
<div class="hkp-actions" style="justify-content:space-between;margin-top:12px"><?php if ($offset): ?><a class="hkp-btn hkp-btn--ghost hkp-btn--sm" href="<?= hkp_url('studio/revisions?' . http_build_query(array('type' => $type, 'id' => $object_id ?: null, 'offset' => max(0, $offset - 50)))) ?>"><?= hkp_e('Newer') ?></a><?php else: ?><span></span><?php endif; ?><?php if ($more): ?><a class="hkp-btn hkp-btn--ghost hkp-btn--sm" href="<?= hkp_url('studio/revisions?' . http_build_query(array('type' => $type, 'id' => $object_id ?: null, 'offset' => $offset + 50))) ?>"><?= hkp_e('Older') ?></a><?php endif; ?></div>
</section>
