<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Governed AI assistant'); ?></div><h1><?php echo hkp_e('Ask about our standards'); ?></h1>
<p><?php echo hkp_e('The assistant answers only from approved, current knowledge you are allowed to see, and shows its sources. If the approved knowledge does not cover your question, it says so.'); ?></p></div></div>
<section class="hkp-card" data-assistant data-thinking="<?php echo hkp_e('Searching approved knowledge…'); ?>" data-sources="<?php echo hkp_e('Sources:'); ?>">
  <div class="hkp-chat" aria-live="polite">
    <?php if (!$history): ?><div class="hkp-msg hkp-msg--ai"><?php echo hkp_e('Try: “What is our approved check-in procedure?”'); ?></div><?php endif; ?>
    <?php foreach ($history as $h): $src = json_decode((string) $h['sources_json'], true) ?: array(); ?>
      <div class="hkp-msg hkp-msg--me" dir="auto"><?php echo hkp_h($h['question']); ?></div>
      <div class="hkp-msg hkp-msg--ai<?php echo $h['coverage'] === 'answered' ? '' : ' hkp-msg--warn'; ?>" dir="auto"><?php echo hkp_h($h['answer']); ?>
        <?php if ($src): ?><div class="hkp-sources"><?php echo hkp_e('Sources:'); ?> <?php foreach ($src as $s): ?><a href="<?php echo hkp_h($s['url']); ?>">[<?php echo hkp_h($s['ref']); ?>] <?php echo hkp_h($s['title']); ?><?php echo $s['version'] ? ' v' . hkp_h($s['version']) : ''; ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <form method="post" action="<?php echo hkp_url('assistant'); ?>" class="hkp-form" style="margin-top:1rem">
    <label class="hkp-label" for="aq"><?php echo hkp_e('Your question'); ?></label>
    <textarea id="aq" class="hkp-input" name="question" maxlength="1000" rows="2" required dir="auto" placeholder="<?php echo hkp_e('Ask in English or Arabic…'); ?>"></textarea>
    <div class="hkp-actions"><button class="hkp-btn"><?php echo hkp_icon('send'); ?> <?php echo hkp_e('Ask'); ?></button><span class="hkp-small hkp-muted"><?php echo hkp_e('Every question and answer is logged for governance.'); ?></span></div>
  </form>
</section>
