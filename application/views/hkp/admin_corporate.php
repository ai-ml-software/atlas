<div class="hkp-head"><div><h1><?php echo hkp_e('Corporate CMS'); ?></h1><p><?php echo hkp_e('Everything from the corporate profile is editable content: nothing is hardcoded. Only items marked public and published appear on the public pages.'); ?></p></div>
<div class="hkp-actions"><a class="hkp-btn hkp-btn--ghost" href="<?php echo site_url('en/altus'); ?>" target="_blank" rel="noopener"><?php echo hkp_e('View public page'); ?></a><a class="hkp-btn hkp-btn--ghost" href="<?php echo site_url('ar/altus'); ?>" target="_blank" rel="noopener">العربية</a></div></div>
<div class="hkp-grid hkp-grid--3">
<?php foreach (array('corporate_blocks' => array('Corporate content blocks', 'Founders\' message, vision, mission, purpose, philosophy, values, Vision 2030, market, contact.'),
  'case_studies' => array('Case studies', 'Each keeps its illustrative / anonymised label and a visibility level.'), 'leadership' => array('Leadership profiles', 'Biography, track record, recognition.'),
  'partners' => array('Partnership directory', 'Internal by default. A partner is shown as official only when marked so.'), 'sectors' => array('Industry sectors', 'The configurable sector taxonomy.'),
  'services' => array('Services', 'Hospitality Solutions and Business Growth Solutions.')) as $k => $l): ?>
  <a class="hkp-card" href="<?php echo hkp_url('admin/crud/' . $k); ?>" style="text-decoration:none;color:inherit"><h2><?php echo hkp_e($l[0]); ?> <span class="hkp-small hkp-muted">(<?php echo (int) $counts[$k]; ?>)</span></h2><p class="hkp-small hkp-muted"><?php echo hkp_e($l[1]); ?></p></a>
<?php endforeach; ?>
  <a class="hkp-card" href="<?php echo hkp_url('cms'); ?>" style="text-decoration:none;color:inherit"><h2><?php echo hkp_e('Website pages & page builder'); ?></h2><p class="hkp-small hkp-muted"><?php echo hkp_e('Public academy pages, sections, SEO, AEO and GEO.'); ?></p></a>
</div>
