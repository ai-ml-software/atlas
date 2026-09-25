<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Institutional knowledge'); ?></div><h1><?php echo hkp_e('Knowledge library'); ?></h1>
<p><?php echo hkp_e('Only approved, current versions appear here. Knowledge belongs to the institution and stays when people move on.'); ?></p></div>
<?php if ($this->ha_auth->has('knowledge.create')): ?><a class="hkp-btn" href="<?php echo hkp_url('admin/content/new'); ?>"><?php echo hkp_e('New knowledge item'); ?></a><?php endif; ?></div>

<form class="hkp-card hkp-form" method="get" style="margin-bottom:1rem">
  <div class="hkp-row">
    <div class="hkp-field"><label for="kq"><?php echo hkp_e('Title contains'); ?></label><input id="kq" class="hkp-input" name="q" value="<?php echo hkp_h($f['q']); ?>"></div>
    <div class="hkp-field"><label for="kt"><?php echo hkp_e('Type'); ?></label><select id="kt" class="hkp-select" name="type"><option value=""><?php echo hkp_e('All types'); ?></option>
      <?php foreach ($types as $t): ?><option value="<?php echo $t; ?>"<?php echo $f['type'] === $t ? ' selected' : ''; ?>><?php echo hkp_label($t); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="kd"><?php echo hkp_e('Domain'); ?></label><select id="kd" class="hkp-select" name="domain"><option value=""><?php echo hkp_e('All domains'); ?></option>
      <?php foreach ($domains as $d): ?><option value="<?php echo (int) $d['id']; ?>"<?php echo (int) $f['domain_id'] === (int) $d['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field" style="justify-content:flex-end"><button class="hkp-btn"><?php echo hkp_e('Filter'); ?></button></div>
  </div>
</form>

<section class="hkp-card">
<?php if (!$items): ?><div class="hkp-empty"><?php echo hkp_e('No approved knowledge matches.'); ?></div><?php else: ?>
  <div class="hkp-table-wrap"><table class="hkp-table">
    <thead><tr><th><?php echo hkp_e('Title'); ?></th><th><?php echo hkp_e('Type'); ?></th><th><?php echo hkp_e('Scope'); ?></th><th><?php echo hkp_e('Version'); ?></th><th><?php echo hkp_e('Effective'); ?></th><th><?php echo hkp_e('Review by'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($items as $k): ?>
      <tr>
        <td><a href="<?php echo hkp_url('knowledge/item/' . $k['id']); ?>"><strong><?php echo hkp_h($k['title']); ?></strong></a><?php if ((int) $k['is_mandatory']): ?> <?php echo hkp_badge('warning', hkp_t('Mandatory')); ?><?php endif; ?>
          <div class="hkp-small hkp-muted"><?php echo hkp_h(mb_substr(strip_tags((string) $k['summary']), 0, 140)); ?></div></td>
        <td><?php echo hkp_label($k['item_type']); ?></td>
        <td class="hkp-small"><?php echo $k['property_id'] ? hkp_e('Property') : ($k['organization_id'] ? hkp_e('Organisation') : hkp_e('Altus global')); ?></td>
        <td>v<?php echo hkp_h($k['version_label']); ?></td>
        <td class="hkp-small"><?php echo hkp_date($k['effective_date']); ?></td>
        <td class="hkp-small"><?php echo hkp_date($k['review_date']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>
