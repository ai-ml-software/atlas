<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<section class="ha-hero">
    <div class="ha-shell">
        <?php if (!empty($article['category_name'])): ?>
            <p><span class="ha-pill ha-pill--accent"><?= html_escape($article['category_name']) ?></span></p>
        <?php endif; ?>
        <h1><?= html_escape($article['title']) ?></h1>
        <p class="ha-hero__lede"><?= html_escape($article['excerpt']) ?></p>
        <p style="color:var(--ha-ink-faint);font-size:.9rem">
            <?php if (!empty($article['author_name'])): ?><?= html_escape($article['author_name']) ?> · <?php endif; ?>
            <time datetime="<?= date('Y-m-d', strtotime($article['published_at'])) ?>">
                <?= date('Y-m-d', strtotime($article['published_at'])) ?>
            </time>
            · <?= (int) $article['reading_minutes'] ?> <?= html_escape($t['read_time']) ?>
        </p>
    </div>
</section>

<?php $cover = ha_image_variant($article['cover_image'], 'wide'); if ($cover): ?>
<figure class="ha-cover">
    <div class="ha-shell">
        <?= ha_image($article['cover_image'], $locale === 'ar' ? $article['cover_image_alt_ar'] : $article['cover_image_alt_en'],
            array('size' => 'wide', 'class' => 'ha-cover__img', 'eager' => true)) ?>
    </div>
</figure>
<?php endif; ?>

<section class="ha-section">
    <div class="ha-shell ha-detail">
        <article class="ha-prose">
            <?= $article['body'] ?>

            <?php if ($article['tags']): ?>
                <p style="margin-top:2rem">
                    <?php foreach ($article['tags'] as $tag): ?>
                        <span class="ha-pill"><?= html_escape($tag['name']) ?></span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </article>

        <aside class="ha-aside">
            <?php if (!empty($article['author_name'])): ?>
                <h3><?= html_escape($article['author_name']) ?></h3>
                <?php if (!empty($article['author_bio'])): ?>
                    <p style="font-size:.9rem;color:var(--ha-ink-soft)"><?= html_escape($article['author_bio']) ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($article['related_course'])): ?>
                <h3><?= html_escape($t['related_courses']) ?></h3>
                <p><a href="<?= base_url($locale . '/courses/' . rawurlencode($article['related_course']['slug'])) ?>">
                    <?= html_escape($article['related_course']['title']) ?></a></p>
            <?php endif; ?>

            <a class="ha-btn" style="width:100%"
               href="<?= base_url($locale . '/' . ($article['cta_url'] ?: 'courses')) ?>">
                <?= html_escape($locale === 'ar' ? $article['cta_label_ar'] : $article['cta_label_en']) ?>
            </a>
        </aside>
    </div>
</section>

<?php if ($article['related']): ?>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($t['articles']) ?></h2></div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($article['related'] as $r): ?>
                <article class="ha-card">
                    <h3><a href="<?= base_url($locale . '/articles/' . rawurlencode($r['slug'])) ?>"><?= html_escape($r['title']) ?></a></h3>
                    <p><?= html_escape($r['excerpt']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Turn the reading into training'),
    'close_text'  => ha_pt('The practice behind this article is taught as a course, with a written standard and an assessment at the end.'),
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['articles'], 'url' => base_url($locale . '/articles')),
)); ?>
