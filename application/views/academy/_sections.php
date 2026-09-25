<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Page-builder sections for the public site. All authored HTML passes the
 * allow-list sanitiser; no inline script or style is emitted, so the page CSP
 * stays strict. Arabic content falls back to English per field when missing.
 */
$this->load->helper('hkp');
foreach ($sections as $s):
    $c = $s[$locale];
    $en = $s['en'];
    $g = function ($k) use ($c, $en) { return isset($c[$k]) && $c[$k] !== '' && $c[$k] !== array() ? $c[$k] : (isset($en[$k]) ? $en[$k] : ''); };
    $img = isset($s['settings']['image']) && $s['settings']['image'] !== '' ? base_url(ltrim($s['settings']['image'], '/')) : null;
    $link = function ($u) use ($locale) { return preg_match('~^(https?://|#)~', (string) $u) ? $u : base_url(ltrim((string) $u, '/')); };
    $type = $s['section_type'];
?>
<section class="ha-section ha-block ha-block--<?= html_escape($type) ?>">
  <div class="ha-shell">
  <?php if ($type === 'hero'): ?>
    <div class="ha-block-hero<?= $img ? ' ha-block-hero--image' : '' ?>">
      <?php if ($img): ?><img class="ha-block-hero__img" src="<?= html_escape($img) ?>" alt="" loading="lazy"><?php endif; ?>
      <div class="ha-block-hero__body"><h2><?= html_escape($g('heading')) ?></h2><?php if ($g('lede')): ?><p class="ha-hero__lede"><?= html_escape($g('lede')) ?></p><?php endif; ?>
        <?php if ($g('button_label')): ?><a class="ha-btn" href="<?= html_escape($link($g('button_url'))) ?>"><?= html_escape($g('button_label')) ?></a><?php endif; ?></div>
    </div>
  <?php elseif ($type === 'rich_text' || $type === 'html'): ?>
    <div class="ha-prose"><?php if ($g('heading')): ?><h2><?= html_escape($g('heading')) ?></h2><?php endif; ?><?= hkp_safe_html($g('body')) ?></div>
  <?php elseif ($type === 'image_text'): ?>
    <div class="ha-block-split<?= isset($s['settings']['image_side']) && $s['settings']['image_side'] === 'end' ? ' ha-block-split--end' : '' ?>">
      <?php if ($img): ?><img src="<?= html_escape($img) ?>" alt="<?= html_escape($g('image_alt')) ?>" loading="lazy"><?php endif; ?>
      <div class="ha-prose"><?php if ($g('heading')): ?><h2><?= html_escape($g('heading')) ?></h2><?php endif; ?><?= hkp_safe_html($g('body')) ?></div>
    </div>
  <?php elseif ($type === 'image'): ?>
    <?php if ($img): ?><figure class="ha-block-figure"><img src="<?= html_escape($img) ?>" alt="<?= html_escape($g('image_alt')) ?>" loading="lazy"><?php if ($g('caption')): ?><figcaption><?= html_escape($g('caption')) ?></figcaption><?php endif; ?></figure><?php endif; ?>
  <?php elseif ($type === 'gallery'): ?>
    <?php if ($g('heading')): ?><h2><?= html_escape($g('heading')) ?></h2><?php endif; ?>
    <div class="ha-block-grid ha-block-grid--3"><?php foreach ((array) $g('items') as $it): if (empty($it['image'])) continue; ?><figure class="ha-block-figure"><img src="<?= html_escape(base_url(ltrim($it['image'], '/'))) ?>" alt="<?= html_escape($it['caption']) ?>" loading="lazy"><?php if (!empty($it['caption'])): ?><figcaption><?= html_escape($it['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
  <?php elseif (in_array($type, array('cards', 'stats', 'steps'), true)): $cols = isset($s['settings']['columns']) ? (int) $s['settings']['columns'] : 3; ?>
    <?php if ($g('heading')): ?><h2><?= html_escape($g('heading')) ?></h2><?php endif; ?>
    <?= $type === 'steps' ? '<ol class="ha-block-steps">' : '<div class="ha-block-grid ha-block-grid--' . max(2, min(4, $cols)) . '">' ?>
    <?php foreach ((array) $g('items') as $it): ?>
      <?= $type === 'steps' ? '<li>' : '<div class="ha-block-card' . ($type === 'stats' ? ' ha-block-card--stat' : '') . '">' ?>
        <strong class="ha-block-card__title"><?= html_escape($it['title']) ?></strong><?php if (!empty($it['text'])): ?><p><?= html_escape($it['text']) ?></p><?php endif; ?>
        <?php if (!empty($it['link'])): ?><a href="<?= html_escape($link($it['link'])) ?>"><?= $locale === 'ar' ? 'المزيد' : 'Learn more' ?> →</a><?php endif; ?>
      <?= $type === 'steps' ? '</li>' : '</div>' ?>
    <?php endforeach; ?>
    <?= $type === 'steps' ? '</ol>' : '</div>' ?>
  <?php elseif ($type === 'faq'): ?>
    <?php if ($g('heading')): ?><h2><?= html_escape($g('heading')) ?></h2><?php endif; ?>
    <div class="ha-block-faq"><?php foreach ((array) $g('items') as $it): if (empty($it['q'])) continue; ?><details><summary><h3><?= html_escape($it['q']) ?></h3></summary><p><?= html_escape($it['a']) ?></p></details><?php endforeach; ?></div>
  <?php elseif ($type === 'cta'): ?>
    <div class="ha-block-cta"><h2><?= html_escape($g('heading')) ?></h2><?php if ($g('body')): ?><div class="ha-prose"><?= hkp_safe_html($g('body')) ?></div><?php endif; ?>
      <?php if ($g('button_label')): ?><a class="ha-btn" href="<?= html_escape($link($g('button_url'))) ?>"><?= html_escape($g('button_label')) ?></a><?php endif; ?></div>
  <?php elseif ($type === 'video'): $v = Ha_page_builder::video_embed($g('video_url')); ?>
    <?php if ($g('heading')): ?><h2><?= html_escape($g('heading')) ?></h2><?php endif; ?>
    <?php if ($v): ?><div class="ha-block-video"><?php if ($v['type'] === 'iframe'): ?><iframe src="<?= html_escape($v['src']) ?>" title="<?= html_escape($g('heading') ?: 'Video') ?>" allow="encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe><?php else: ?><video src="<?= html_escape($v['src']) ?>" controls preload="metadata" playsinline></video><?php endif; ?></div><?php endif; ?>
    <?php if ($g('caption')): ?><p class="ha-block-caption"><?= html_escape($g('caption')) ?></p><?php endif; ?>
  <?php elseif ($type === 'quote'): ?>
    <blockquote class="ha-block-quote"><p><?= html_escape($g('quote')) ?></p><?php if ($g('author')): ?><cite><?= html_escape($g('author')) ?></cite><?php endif; ?></blockquote>
  <?php endif; ?>
  </div>
</section>
<?php endforeach; ?>
