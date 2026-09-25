<?php
/* Reusable AI writing panel. Expects $models (Ha_ai_assist::catalogue()), $ai_entity, $ai_id, $ai_tasks (task => label). */
$ai_tasks = isset($ai_tasks) ? $ai_tasks : array('section' => 'Write a page section', 'improve' => 'Improve the selected text', 'faq' => 'Write FAQ questions and answers',
    'seo_meta' => 'Suggest meta title and description', 'translate_ar' => 'Translate to Arabic (Modern Standard Arabic)', 'translate_en' => 'Translate to English');
?>
<section class="hkp-card" data-ai-panel data-endpoint="<?php echo hkp_url('cms/ai'); ?>" data-entity="<?php echo hkp_h($ai_entity); ?>" data-entity-id="<?php echo (int) $ai_id; ?>">
  <h2><?php echo hkp_icon('spark'); ?> <?php echo hkp_e('AI writing help'); ?></h2>
  <?php if (!$models): ?>
    <p class="hkp-small"><?php echo hkp_e('No AI provider is connected yet.'); ?> <a href="<?php echo site_url('ha_ai/providers'); ?>"><?php echo hkp_e('Connect one in AI Studio'); ?> →</a></p>
  <?php else: ?>
  <div class="hkp-form">
    <div class="hkp-row">
      <div class="hkp-field"><label for="aip"><?php echo hkp_e('Provider'); ?></label><select id="aip" class="hkp-select" data-ai-provider><?php foreach ($models as $m): ?><option value="<?php echo hkp_h($m['slug']); ?>"><?php echo hkp_h($m['name']); ?></option><?php endforeach; ?></select></div>
      <div class="hkp-field"><label for="aim"><?php echo hkp_e('Model'); ?></label><select id="aim" class="hkp-select" data-ai-model></select></div>
    </div>
    <div class="hkp-row">
      <div class="hkp-field"><label for="ait"><?php echo hkp_e('Task'); ?></label><select id="ait" class="hkp-select" data-ai-task><?php foreach ($ai_tasks as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo hkp_e($l); ?></option><?php endforeach; ?></select></div>
      <div class="hkp-field"><label for="ail"><?php echo hkp_e('Output language'); ?></label><select id="ail" class="hkp-select" data-ai-locale><option value="en">English</option><option value="ar">العربية</option></select></div>
    </div>
    <div class="hkp-field"><label for="aiq"><?php echo hkp_e('Your request'); ?></label><textarea id="aiq" class="hkp-input" rows="3" data-ai-prompt dir="auto" placeholder="<?php echo hkp_e('e.g. A section explaining our pre-opening support for independent hotels in Riyadh'); ?>"></textarea></div>
    <div class="hkp-field"><label for="aic"><?php echo hkp_e('Text to work on (optional)'); ?></label><textarea id="aic" class="hkp-input" rows="3" data-ai-context dir="auto"></textarea></div>
    <div class="hkp-actions"><button type="button" class="hkp-btn hkp-btn--ghost" data-ai-enhance><?php echo hkp_e('Enhance prompt'); ?></button><button type="button" class="hkp-btn" data-ai-run><?php echo hkp_icon('spark'); ?> <?php echo hkp_e('Generate'); ?></button></div>
    <div class="hkp-field"><label for="aio"><?php echo hkp_e('Result (review before using)'); ?></label><textarea id="aio" class="hkp-input" rows="8" data-ai-output dir="auto"></textarea><span class="hkp-help" data-ai-status role="status"></span></div>
    <div class="hkp-actions"><button type="button" class="hkp-btn hkp-btn--sm hkp-btn--ghost" data-ai-copy><?php echo hkp_e('Copy'); ?></button><?php if (!empty($ai_insert)): ?><button type="button" class="hkp-btn hkp-btn--sm" data-ai-insert="<?php echo hkp_h($ai_insert); ?>"><?php echo hkp_e('Insert into the field'); ?></button><?php endif; ?></div>
  </div>
  <script type="application/json" data-ai-models><?php echo json_encode($models, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?></script>
  <?php endif; ?>
</section>
