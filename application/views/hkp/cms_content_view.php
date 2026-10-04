<div class="hkp-head">
  <div><div class="hkp-eyebrow"><?php echo hkp_e('Content preview'); ?></div>
    <p><?php echo hkp_badge($status); ?> · <?php echo hkp_e('Private preview. Learner progress is unchanged.'); ?></p>
  </div>
  <div class="hkp-actions">
    <a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_h($edit_url); ?>"><?php echo hkp_e('Back to editor'); ?></a>
    <a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_h($list_url); ?>"><?php echo hkp_e('Back to list'); ?></a>
    <?php foreach (array('en' => 'English', 'ar' => 'Arabic') as $code => $label): ?>
      <a class="hkp-btn hkp-btn--sm hkp-btn--ghost" href="<?php echo hkp_h(hkp_content_view_url($type, $id, $code, $draft)); ?>"<?php echo $locale === $code ? ' aria-current="page"' : ''; ?>><?php echo hkp_e($label); ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div lang="<?php echo hkp_h($locale); ?>" dir="<?php echo $locale === 'ar' ? 'rtl' : 'ltr'; ?>">
  <?php if ($lesson): $this->load->view('hkp/learn_lesson', $lesson); else: ?>
    <article class="hkp-card hkp-lesson">
      <h1><?php echo hkp_h($copy['title'] ?? ''); ?></h1>
      <?php if (!empty($copy['subtitle'])): ?><p><?php echo hkp_h($copy['subtitle']); ?></p><?php endif; ?>
      <?php if (!empty($copy['hero_image'])): ?><img src="<?php echo hkp_h(preg_match('~^https?://~', $copy['hero_image']) ? $copy['hero_image'] : base_url(ltrim($copy['hero_image'], '/'))); ?>" alt="" style="max-width:100%;height:auto"><?php endif; ?>
      <?php echo hkp_safe_html($copy['body'] ?? ''); ?>
    </article>
    <?php if ($sections): $this->load->view('academy/_sections', array('sections' => $sections, 'locale' => $locale)); endif; ?>
    <?php if ($lessons): ?>
      <section class="hkp-card" style="margin-top:1rem"><h2><?php echo hkp_e('Lessons'); ?></h2><ul>
        <?php foreach ($lessons as $item): ?><li><a href="<?php echo hkp_h(hkp_content_view_url('lessons', $item['id'], $locale)); ?>"><?php echo hkp_h($item['title']); ?></a> <?php echo hkp_badge($item['status']); ?></li><?php endforeach; ?>
      </ul></section>
    <?php endif; ?>
    <?php if ($related): ?>
      <section class="hkp-card" style="margin-top:1rem"><h2><?php echo hkp_e('Related courses'); ?></h2><ul>
        <?php foreach ($related as $item): ?><li><a href="<?php echo hkp_h(hkp_content_view_url('courses', $item['id'], $locale)); ?>"><?php echo hkp_h($item['title'] ?: $item['code']); ?></a> <?php echo hkp_badge($item['status']); ?></li><?php endforeach; ?>
      </ul></section>
    <?php endif; ?>
    <?php if ($steps): ?>
      <section class="hkp-card" style="margin-top:1rem"><h2><?php echo hkp_e('Learning sequence'); ?></h2><ol>
        <?php foreach ($steps as $item): ?><li><h3><?php echo hkp_h($item['title_' . $locale] ?? $item['title_en'] ?? ''); ?></h3><?php echo hkp_safe_html($item['description_' . $locale] ?? $item['description_en'] ?? ''); ?></li><?php endforeach; ?>
      </ol></section>
    <?php endif; ?>
  <?php endif; ?>
</div>
