<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Smart search'); ?></div><h1><?php echo $q !== '' ? hkp_e('Results for “{q}”', array('q' => $q)) : hkp_e('Search'); ?></h1>
<p><?php echo hkp_e('Searches only the approved knowledge you are allowed to read, in English and Arabic.'); ?></p></div></div>
<form class="hkp-card hkp-form" method="get" style="margin-bottom:1rem">
  <div class="hkp-row">
    <div class="hkp-field"><label for="sq"><?php echo hkp_e('Search'); ?></label><input id="sq" class="hkp-input" name="q" value="<?php echo hkp_h($q); ?>" required></div>
    <div class="hkp-field"><label for="st"><?php echo hkp_e('Content type'); ?></label><select id="st" class="hkp-select" name="type"><option value=""><?php echo hkp_e('Everything'); ?></option><option value="lesson"<?php echo $f['type'] === 'lesson' ? ' selected' : ''; ?>><?php echo hkp_e('Lessons'); ?></option><option value="assessment"<?php echo $f['type'] === 'assessment' ? ' selected' : ''; ?>><?php echo hkp_e('Assessments'); ?></option>
      <?php foreach ($types as $t): ?><option value="<?php echo $t; ?>"<?php echo $f['type'] === $t ? ' selected' : ''; ?>><?php echo hkp_label($t); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="sd"><?php echo hkp_e('Domain'); ?></label><select id="sd" class="hkp-select" name="domain"><option value=""><?php echo hkp_e('All domains'); ?></option>
      <?php foreach ($domains as $d): ?><option value="<?php echo (int) $d['id']; ?>"<?php echo (int) $f['domain_id'] === (int) $d['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($d, 'name')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="sl"><?php echo hkp_e('Language'); ?></label><select id="sl" class="hkp-select" name="locale"><option value=""><?php echo hkp_e('Both'); ?></option><option value="en"<?php echo $f['locale'] === 'en' ? ' selected' : ''; ?>>English</option><option value="ar"<?php echo $f['locale'] === 'ar' ? ' selected' : ''; ?>>العربية</option></select></div>
    <div class="hkp-field" style="justify-content:flex-end"><button class="hkp-btn"><?php echo hkp_icon('search'); ?> <?php echo hkp_e('Search'); ?></button></div>
  </div>
</form>
<?php if ($q !== ''): ?>
<section class="hkp-card">
  <?php if (!$results): ?>
    <div class="hkp-empty"><?php echo hkp_e('No approved content matches. Try different words, or ask the assistant.'); ?></div>
  <?php else: ?>
  <ul class="hkp-list">
    <?php foreach ($results as $r): ?>
    <li><div>
      <a href="<?php echo hkp_h($r['url']); ?>"><strong><?php echo hkp_h($r['title']); ?></strong></a>
      <div class="hkp-small hkp-muted"><?php echo hkp_label($r['item_type']); ?><?php echo $r['version'] ? ' · v' . hkp_h($r['version']) : ''; ?> · <?php echo hkp_label($r['scope']); ?> · <?php echo strtoupper(hkp_h($r['locale'])); ?> · <?php echo hkp_e('Updated {d}', array('d' => hkp_h($r['updated']))); ?></div>
      <?php if ($r['excerpt']): ?><p class="hkp-small" style="margin:.3rem 0 0" dir="auto"><?php echo hkp_h($r['excerpt']); ?></p><?php endif; ?>
    </div><a class="hkp-btn hkp-btn--sm hkp-btn--ghost" href="<?php echo hkp_h($r['url']); ?>"><?php echo hkp_e('Open'); ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endif; ?>
