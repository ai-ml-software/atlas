<?php $flow = array('assigned', 'in_progress', 'submitted', 'under_review', 'completed'); $at = array_search($ap['status'], $flow, true); ?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Action plan'); ?> · <?php echo hkp_h(trim($ap['first_name'] . ' ' . $ap['last_name'])); ?></div><h1><?php echo hkp_h($ap['title']); ?></h1>
<p><?php echo hkp_label($ap['action_type']); ?> · <?php echo hkp_e('Due {d}', array('d' => hkp_date($ap['due_at']))); ?> · <?php echo hkp_badge($ap['priority']); ?></p></div>
<span style="font-size:1.2rem"><?php echo hkp_badge($ap['status']); ?></span></div>

<ol class="hkp-steps" style="margin-bottom:1rem">
  <?php foreach ($flow as $i => $s): ?><li class="<?php echo $at !== false && $i < $at ? 'is-done' : ($at === $i ? 'is-now' : ''); ?>"><?php echo hkp_label($s); ?></li><?php endforeach; ?>
  <li class="<?php echo $ap['reassessment'] && $ap['reassessment']['status'] === 'completed' ? 'is-done' : ($ap['reassessment'] ? 'is-now' : ''); ?>"><?php echo hkp_e('Reassessment'); ?></li>
</ol>
<div class="hkp-chain" style="margin-bottom:1rem"><span><?php echo hkp_e('Gap'); ?></span>→<span class="is-on"><?php echo hkp_e('Action'); ?></span>→<span><?php echo hkp_e('Evidence'); ?></span>→<span><?php echo hkp_e('Review'); ?></span>→<span><?php echo hkp_e('Reassessment'); ?></span>→<span><?php echo hkp_e('Competency'); ?></span></div>

<div class="hkp-grid hkp-grid--main">
  <div class="hkp-grid">
    <section class="hkp-card">
      <h2><?php echo hkp_e('What to do'); ?></h2>
      <p style="white-space:pre-wrap"><?php echo hkp_h($ap['required_action']) ?: '—'; ?></p>
      <?php if ($ap['gap']): ?><p class="hkp-small"><?php echo hkp_e('Closes the gap on {c}: level {cur} of {req}.', array('c' => hkp_pick($ap, 'skill'), 'cur' => $ap['gap']['current_level'], 'req' => $ap['gap']['required_level'])); ?></p><?php endif; ?>
      <?php if ($ap['linked_type'] === 'course' && $ap['linked_id']): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('learn/module/' . $ap['linked_id']); ?>"><?php echo hkp_e('Open the linked module'); ?></a><?php endif; ?>
      <?php if ($ap['linked_type'] === 'sop' && $ap['linked_id']): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('knowledge/item/' . $ap['linked_id']); ?>"><?php echo hkp_e('Open the linked SOP'); ?></a><?php endif; ?>
      <?php if ($ap['rejection_reason'] && $ap['status'] === 'rejected'): ?><div class="hkp-flash hkp-flash--error" style="margin-top:.8rem"><?php echo hkp_e('Returned: {r}', array('r' => $ap['rejection_reason'])); ?></div><?php endif; ?>
    </section>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Evidence'); ?></h2>
      <?php if (!$ap['evidence']): ?><p class="hkp-muted"><?php echo hkp_e('No evidence yet.'); ?></p><?php endif; ?>
      <ul class="hkp-list"><?php foreach ($ap['evidence'] as $e): ?>
        <li><div><strong><?php echo hkp_h($e['title']); ?></strong> <span class="hkp-small hkp-muted">· <?php echo hkp_label($e['evidence_type']); ?> · <?php echo hkp_date($e['created_at'], true); ?></span>
          <?php if ($e['description']): ?><div class="hkp-small" style="white-space:pre-wrap"><?php echo hkp_h($e['description']); ?></div><?php endif; ?></div>
          <?php if ($e['token']): ?><a class="hkp-btn hkp-btn--sm hkp-btn--ghost" href="<?php echo hkp_url('file/' . $e['token']); ?>"><?php echo hkp_icon('download'); ?> <?php echo hkp_h($e['original_name']); ?></a><?php endif; ?></li>
      <?php endforeach; ?></ul>
      <?php if ($ap['status'] !== 'completed'): ?>
      <form class="hkp-form" method="post" enctype="multipart/form-data" action="<?php echo hkp_url('actions/evidence/' . $ap['id']); ?>" style="margin-top:1rem">
        <?php echo ha_csrf_field(); ?>
        <div class="hkp-row">
          <div class="hkp-field"><label for="et"><?php echo hkp_e('Title'); ?></label><input id="et" class="hkp-input" name="title" maxlength="190"></div>
          <div class="hkp-field"><label for="ety"><?php echo hkp_e('Type'); ?></label><select id="ety" class="hkp-select" name="evidence_type">
            <?php foreach (array('document', 'photo', 'video', 'supervisor_note', 'observation', 'external_certificate') as $t): ?><option value="<?php echo $t; ?>"><?php echo hkp_label($t); ?></option><?php endforeach; ?></select></div>
          <div class="hkp-field"><label for="ef"><?php echo hkp_e('File'); ?></label><input id="ef" class="hkp-input" type="file" name="file"><span class="hkp-help"><?php echo hkp_e('PDF, image, video or Office document.'); ?></span></div>
        </div>
        <div class="hkp-field"><label for="ed"><?php echo hkp_e('Description'); ?></label><textarea id="ed" class="hkp-input" name="description" rows="3"></textarea></div>
        <div><button class="hkp-btn hkp-btn--ghost"><?php echo hkp_icon('upload'); ?> <?php echo hkp_e('Add evidence'); ?></button></div>
      </form>
      <?php endif; ?>
    </section>
  </div>
  <div class="hkp-grid">
    <?php if ($allowed): ?>
    <section class="hkp-card">
      <h2><?php echo hkp_e('Next step'); ?></h2>
      <form class="hkp-form" method="post" action="<?php echo hkp_url('actions/move/' . $ap['id']); ?>"><?php echo ha_csrf_field(); ?>
        <div class="hkp-field"><label for="an"><?php echo hkp_e('Note'); ?></label><textarea id="an" class="hkp-input" name="note" rows="3"></textarea></div>
        <div class="hkp-actions"><?php foreach ($allowed as $to): ?><button class="hkp-btn<?php echo $to === 'rejected' ? ' hkp-btn--danger' : ''; ?>" name="to" value="<?php echo $to; ?>"><?php echo hkp_e('Move to {s}', array('s' => hkp_label($to))); ?></button><?php endforeach; ?></div>
      </form>
    </section>
    <?php endif; ?>
    <section class="hkp-card">
      <h2><?php echo hkp_e('History'); ?></h2>
      <ul class="hkp-list"><?php foreach ($ap['events'] as $ev): ?>
        <li><div class="hkp-small"><strong><?php echo hkp_label($ev['to_status']); ?></strong> · <?php echo hkp_h(trim($ev['first_name'] . ' ' . $ev['last_name'])) ?: hkp_e('System'); ?><?php if ($ev['note']): ?><div class="hkp-muted"><?php echo hkp_h($ev['note']); ?></div><?php endif; ?></div><span class="hkp-small hkp-muted"><?php echo hkp_date($ev['created_at'], true); ?></span></li>
      <?php endforeach; ?></ul>
    </section>
    <?php if ($ap['reassessment']): ?>
    <section class="hkp-card"><h2><?php echo hkp_e('Reassessment'); ?></h2><p><?php echo hkp_badge($ap['reassessment']['status']); ?><?php if ($ap['reassessment']['result_level'] !== null): ?> · <?php echo hkp_e('Result level {n}', array('n' => $ap['reassessment']['result_level'])); ?><?php endif; ?></p>
      <?php if (!$is_mine && $this->ha_auth->has('practicals.assess') && $ap['reassessment']['status'] === 'approved' && $ap['reassessment']['rubric_id']): ?>
        <form method="post" action="<?php echo hkp_url('assess/practical_start'); ?>"><?php echo ha_csrf_field(); ?><input type="hidden" name="rubric_id" value="<?php echo (int) $ap['reassessment']['rubric_id']; ?>"><input type="hidden" name="user_id" value="<?php echo (int) $ap['user_id']; ?>"><input type="hidden" name="reassessment_id" value="<?php echo (int) $ap['reassessment']['id']; ?>"><button class="hkp-btn"><?php echo hkp_e('Start reassessment'); ?></button></form>
      <?php endif; ?></section>
    <?php endif; ?>
  </div>
</div>
