<?php
$sections = array('purpose' => 'Purpose', 'scope' => 'Scope', 'responsibilities' => 'Responsibilities', 'required_tools' => 'Tools and materials', 'procedure' => 'Procedure',
    'checklist' => 'Checklist', 'safety_notes' => 'Safety', 'quality_standard' => 'Quality standard', 'escalation' => 'Escalation', 'related_documents' => 'Related documents');
$wv = $w ? $w['working'] : null;
$txt = $w ? $w['text'] : array();
$editable = !$doc || ($can_edit && $wv && in_array($wv['status'], array('draft', 'rejected'), true));
$tx = function ($loc, $f) use ($txt) { return isset($txt[$loc][$f]) ? $txt[$loc][$f] : ''; };
$flow = array('draft', 'internal_review', 'quality_review', 'approved', 'published');
$now = $wv ? array_search($wv['status'], $flow, true) : 0;
?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><a href="<?php echo hkp_url('admin/content'); ?>"><?php echo hkp_e('Content review'); ?></a><?php echo $doc ? ' · ' . hkp_h($doc['code']) : ''; ?></div>
<h1><?php echo $doc ? hkp_h($tx('en', 'title') ?: $doc['code']) : hkp_e('New knowledge item'); ?></h1>
<?php if ($wv): ?><p><?php echo hkp_e('Working version {v}', array('v' => $wv['version_label'])); ?> · <?php echo hkp_badge($wv['status']); ?><?php if ($doc['version'] && $doc['current_version_id']): ?> · <?php echo hkp_e('Published version: v{v}', array('v' => $doc['version']['version_label'])); ?><?php endif; ?></p><?php endif; ?></div>
<?php if ($doc): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('knowledge/item/' . $doc['id']); ?>"><?php echo hkp_e('View as reader'); ?></a><?php endif; ?></div>
<?php if ($wv): ?><ol class="hkp-steps" style="margin-bottom:1rem"><?php foreach ($flow as $i => $s): ?><li class="<?php echo $now !== false && $i < $now ? 'is-done' : ($now === $i ? 'is-now' : ''); ?>"><?php echo hkp_label($s); ?></li><?php endforeach; ?></ol><?php endif; ?>

<div class="hkp-grid hkp-grid--main">
<form class="hkp-card hkp-form" method="post" action="<?php echo $doc ? hkp_url('admin/content/save/' . $doc['id']) : hkp_url('admin/content/create'); ?>"><?php echo ha_csrf_field(); ?>
  <?php if (!$editable): ?><div class="hkp-flash hkp-flash--error"><?php echo hkp_e('This version is {s} and read-only. Open a new version to change it.', array('s' => hkp_label($wv['status']))); ?></div><?php endif; ?>
  <fieldset <?php echo $editable ? '' : 'disabled'; ?> style="border:0;padding:0;margin:0;display:grid;gap:.9rem">
  <div class="hkp-row">
    <div class="hkp-field"><label for="kte"><?php echo hkp_e('Title (English)'); ?> *</label><input id="kte" class="hkp-input" name="title_en" required value="<?php echo hkp_h($tx('en', 'title')); ?>"></div>
    <div class="hkp-field"><label for="kta"><?php echo hkp_e('Title (Arabic)'); ?></label><input id="kta" class="hkp-input" name="title_ar" dir="rtl" value="<?php echo hkp_h($tx('ar', 'title')); ?>"></div></div>
  <div class="hkp-row">
    <div class="hkp-field"><label for="kty"><?php echo hkp_e('Type'); ?></label><select id="kty" class="hkp-select" name="item_type"><?php foreach ($types as $t): ?><option value="<?php echo $t; ?>"<?php echo $doc && $doc['item_type'] === $t ? ' selected' : ''; ?>><?php echo hkp_label($t); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="kdm"><?php echo hkp_e('Domain'); ?></label><select id="kdm" class="hkp-select" name="domain_id"><option value=""></option><?php foreach ($domains as $d): ?><option value="<?php echo (int) $d['id']; ?>"<?php echo $doc && (int) $doc['domain_id'] === (int) $d['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
    <?php if (!$doc): ?>
    <div class="hkp-field"><label for="kor"><?php echo hkp_e('Owner organisation (scope)'); ?></label><select id="kor" class="hkp-select" name="organization_id"><?php if ($this->ha_auth->is_system_scoped()): ?><option value=""><?php echo hkp_e('Altus global (all clients)'); ?></option><?php endif; ?><?php foreach ($orgs as $o): if (!$this->ha_auth->can_organization($o['id'])) continue; ?><option value="<?php echo (int) $o['id']; ?>"><?php echo hkp_h($o['name_en']); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="kpr"><?php echo hkp_e('Property (optional)'); ?></label><select id="kpr" class="hkp-select" name="property_id"><option value=""><?php echo hkp_e('All properties'); ?></option><?php foreach ($props as $p): ?><option value="<?php echo (int) $p['id']; ?>"><?php echo hkp_h(hkp_pick($p, 'name')); ?></option><?php endforeach; ?></select></div>
    <?php endif; ?></div>
  <div class="hkp-row">
    <div class="hkp-field"><label for="kdc"><?php echo hkp_e('Department code (optional)'); ?></label><input id="kdc" class="hkp-input" name="department_code" value="<?php echo hkp_h($doc ? $doc['department_code'] : ''); ?>" placeholder="FO"></div>
    <div class="hkp-field"><label for="kjr"><?php echo hkp_e('Job role (optional)'); ?></label><select id="kjr" class="hkp-select" name="job_role_id"><option value=""></option><?php foreach ($roles as $r): ?><option value="<?php echo (int) $r['id']; ?>"<?php echo $doc && (int) $doc['job_role_id'] === (int) $r['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($r, 'title')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="krv"><?php echo hkp_e('Reviewer'); ?></label><select id="krv" class="hkp-select" name="reviewer_user_id"><option value=""></option><?php foreach ($reviewers as $r): ?><option value="<?php echo (int) $r['id']; ?>"<?php echo $doc && (int) $doc['reviewer_user_id'] === (int) $r['id'] ? ' selected' : ''; ?>><?php echo hkp_h($r['first_name'] . ' ' . $r['last_name']); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="krd"><?php echo hkp_e('Review date'); ?></label><input id="krd" class="hkp-input" type="date" name="review_date" value="<?php echo hkp_h($wv ? $wv['review_date'] : date('Y-m-d', strtotime('+12 months'))); ?>"></div></div>
  <input type="hidden" name="is_mandatory" value="0"><input type="hidden" name="requires_acknowledgement" value="0"><input type="hidden" name="ai_enabled" value="0">
  <div class="hkp-actions"><label class="hkp-check"><input type="checkbox" name="is_mandatory" value="1"<?php echo $doc && (int) $doc['is_mandatory'] ? ' checked' : ''; ?>> <?php echo hkp_e('Mandatory'); ?></label>
    <label class="hkp-check"><input type="checkbox" name="requires_acknowledgement" value="1"<?php echo !$doc || (int) $doc['requires_acknowledgement'] ? ' checked' : ''; ?>> <?php echo hkp_e('Requires acknowledgement'); ?></label>
    <label class="hkp-check"><input type="checkbox" name="ai_enabled" value="1"<?php echo !$doc || (int) $doc['ai_enabled'] ? ' checked' : ''; ?>> <?php echo hkp_e('Approved source for the AI assistant'); ?></label>
    <input type="hidden" name="tags" value="<?php echo hkp_h($doc ? $doc['tags'] : ''); ?>"></div>
  <div class="hkp-field"><label for="kcs"><?php echo hkp_e('What changed in this version'); ?></label><input id="kcs" class="hkp-input" name="change_summary" value="<?php echo hkp_h($wv ? $wv['change_summary'] : ''); ?>"></div>
  <?php foreach ($sections as $f => $l): ?>
    <div class="hkp-grid hkp-grid--2"><div class="hkp-field"><label for="en_<?php echo $f; ?>"><?php echo hkp_e($l); ?> (EN)</label><textarea id="en_<?php echo $f; ?>" class="hkp-input" name="en[<?php echo $f; ?>]" rows="<?php echo $f === 'procedure' ? 8 : 3; ?>"><?php echo hkp_h($tx('en', $f)); ?></textarea></div>
      <div class="hkp-field"><label for="ar_<?php echo $f; ?>"><?php echo hkp_e($l); ?> (AR)</label><textarea id="ar_<?php echo $f; ?>" class="hkp-input" name="ar[<?php echo $f; ?>]" dir="rtl" rows="<?php echo $f === 'procedure' ? 8 : 3; ?>"><?php echo hkp_h($tx('ar', $f)); ?></textarea></div></div>
  <?php endforeach; ?>
  <div><button class="hkp-btn"><?php echo $doc ? hkp_e('Save draft') : hkp_e('Create draft'); ?></button></div>
  </fieldset>
</form>

<?php if ($doc): ?><div class="hkp-grid">
  <section class="hkp-card"><h2><?php echo hkp_e('Workflow'); ?></h2>
    <form class="hkp-form" method="post" action="<?php echo hkp_url('admin/content/act/' . $doc['id']); ?>"><?php echo ha_csrf_field(); ?>
      <div class="hkp-field"><label for="wc"><?php echo hkp_e('Comment / review note'); ?></label><textarea id="wc" class="hkp-input" name="comment" rows="3"></textarea></div>
      <div class="hkp-actions">
        <?php $s = $wv['status']; ?>
        <?php if (in_array($s, array('draft', 'rejected'), true) && $can_edit): ?><button class="hkp-btn" name="action" value="submit"><?php echo hkp_e('Submit for review'); ?></button><?php endif; ?>
        <?php if (in_array($s, array('internal_review', 'review'), true) && $this->ha_auth->has('knowledge.review')): ?><button class="hkp-btn" name="action" value="approve_internal"><?php echo hkp_e('Pass internal review'); ?></button><?php endif; ?>
        <?php if ($s === 'quality_review' && $this->ha_auth->has('knowledge.approve')): ?><button class="hkp-btn" name="action" value="approve_quality"><?php echo hkp_e('Quality-approve'); ?></button><?php endif; ?>
        <?php if ($s === 'approved' && $this->ha_auth->has('knowledge.publish')): ?><button class="hkp-btn hkp-btn--accent" name="action" value="publish"><?php echo hkp_e('Publish'); ?></button><?php endif; ?>
        <?php if (in_array($s, array('internal_review', 'quality_review', 'review', 'approved'), true) && $this->ha_auth->has('knowledge.review')): ?><button class="hkp-btn hkp-btn--danger" name="action" value="reject"><?php echo hkp_e('Request changes'); ?></button><?php endif; ?>
        <?php if (in_array($s, array('published', 'superseded'), true) && $can_edit): ?><button class="hkp-btn" name="action" value="new_version"><?php echo hkp_e('Open new version'); ?></button><?php endif; ?>
        <button class="hkp-btn hkp-btn--ghost" name="action" value="comment"><?php echo hkp_e('Comment'); ?></button>
        <?php if ($this->ha_auth->has('knowledge.archive') && $doc['status'] !== 'archived'): ?><button class="hkp-btn hkp-btn--ghost" name="action" value="archive" onclick="return confirm('<?php echo hkp_e('Archive this item? It leaves search and the AI index; history is kept.'); ?>')"><?php echo hkp_e('Archive'); ?></button><?php endif; ?>
      </div></form>
    <p class="hkp-small hkp-muted" style="margin-top:.6rem"><?php echo hkp_e('The author of a version cannot review, approve or publish it.'); ?></p></section>
  <section class="hkp-card"><h2><?php echo hkp_e('Versions'); ?></h2><ul class="hkp-list"><?php foreach ($doc['versions'] as $v): ?><li><span>v<?php echo hkp_h($v['version_label']); ?> <span class="hkp-small hkp-muted"><?php echo hkp_h($v['change_summary']); ?></span></span><span><?php echo hkp_badge($v['status']); ?>
    <?php if ((int) $v['id'] !== (int) $wv['id']): ?><a class="hkp-small" href="?compare=<?php echo (int) $v['id']; ?>&amp;with=<?php echo (int) $wv['id']; ?>"><?php echo hkp_e('Compare'); ?></a><?php endif; ?></span></li><?php endforeach; ?></ul></section>
  <?php if ($diff !== null): ?><section class="hkp-card"><h2><?php echo hkp_e('Changes'); ?></h2><?php if (!$diff): ?><p class="hkp-muted"><?php echo hkp_e('No differences.'); ?></p><?php endif; ?>
    <?php foreach ($diff as $d): ?><h3><?php echo hkp_label($d['field']); ?> (<?php echo strtoupper($d['locale']); ?>)</h3><pre class="hkp-small" style="white-space:pre-wrap;background:#fafafa;padding:.6rem;border-radius:8px" dir="auto"><?php foreach ($d['lines'] as $l): ?><span style="<?php echo $l[0] === '+' ? 'background:#e6f4ec' : ($l[0] === '-' ? 'background:#fbe9e7;text-decoration:line-through' : ''); ?>"><?php echo $l[0] === '=' ? '  ' : $l[0] . ' '; ?><?php echo hkp_h($l[1]); ?></span>
<?php endforeach; ?></pre><?php endforeach; ?></section><?php endif; ?>
  <section class="hkp-card"><h2><?php echo hkp_e('Review history'); ?></h2><ul class="hkp-list"><?php foreach ($doc['reviews'] as $r): ?><li><div class="hkp-small"><strong><?php echo hkp_label($r['action']); ?></strong> · <?php echo hkp_h(trim($r['first_name'] . ' ' . $r['last_name'])); ?><?php if ($r['comment']): ?><div class="hkp-muted"><?php echo hkp_h($r['comment']); ?></div><?php endif; ?></div><span class="hkp-small hkp-muted"><?php echo hkp_date($r['created_at'], true); ?></span></li><?php endforeach; ?></ul></section>
</div><?php endif; ?>
</div>
