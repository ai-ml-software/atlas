<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $heading['title'],
    'hero_lede'  => $heading['lede'],
    'hero_image' => ha_page_art(array('article-frontdesk', 'guest-experience')),
    'hero_alt'   => '',
    'hero_stat'  => array('n' => (string) count($articles), 'label' => $t['articles']),
)); ?>

<section class="ha-section">
    <div class="ha-shell">
        <form class="ha-filters" method="get" action="<?= base_url($locale . '/articles') ?>" data-ha-autosubmit>
            <div class="ha-field ha-field--wide">
                <label for="a-q"><?= html_escape($t['search']) ?></label>
                <input id="a-q" type="search" name="q" value="<?= html_escape((string) $filters['search']) ?>">
            </div>
            <div class="ha-field">
                <label for="a-cat"><?= html_escape($t['all_categories']) ?></label>
                <select id="a-cat" name="category">
                    <option value=""><?= html_escape($t['all_categories']) ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= html_escape($cat['code']) ?>"<?= $filters['category'] === $cat['code'] ? ' selected' : '' ?>>
                            <?= html_escape($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="ha-btn" type="submit"><?= html_escape($t['search']) ?></button>
        </form>

        <p class="ha-count">
            <?= html_escape($t['showing']) ?> <?= count($articles) ?>
            <?= html_escape($t['of']) ?> <?= (int) $total ?> <?= html_escape($t['results']) ?>
        </p>

        <?php if (!$articles): ?>
            <div class="ha-empty"><p><?= html_escape($t['no_results']) ?></p></div>
        <?php else: ?>
            <div class="ha-grid ha-grid--3">
                <?php foreach ($articles as $a): ?>
                    <article class="ha-card ha-card--media">
                        <?= ha_media_figure($a['cover_image'], $locale === 'ar' ? $a['cover_image_alt_ar'] : $a['cover_image_alt_en']) ?>
                        <h2 class="ha-card__title"><a href="<?= base_url($locale . '/articles/' . rawurlencode($a['slug'])) ?>"><?= html_escape($a['title']) ?></a></h2>
                        <p><?= html_escape($a['excerpt']) ?></p>
                        <div class="ha-card__meta">
                            <?php if (!empty($a['category_name'])): ?>
                                <span class="ha-pill"><?= html_escape($a['category_name']) ?></span>
                            <?php endif; ?>
                            <span class="ha-pill"><?= (int) $a['reading_minutes'] ?> <?= html_escape($t['read_time']) ?></span>
                            <time datetime="<?= date('Y-m-d', strtotime($a['published_at'])) ?>">
                                <?= date('Y-m-d', strtotime($a['published_at'])) ?>
                            </time>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <?php
                $qs = array_filter(array('q' => $filters['search'], 'category' => $filters['category']));
                $link = function ($n) use ($qs, $locale) {
                    $qs['page'] = $n;
                    return base_url($locale . '/articles?' . http_build_query($qs));
                };
                ?>
                <nav class="ha-pager" aria-label="Pagination">
                    <?php if ($page > 1): ?><a href="<?= $link($page - 1) ?>" rel="prev"><?= html_escape($t['previous']) ?></a><?php endif; ?>
                    <?php for ($n = 1; $n <= $pages; $n++): ?>
                        <?php if ($n === $page): ?><span aria-current="page"><?= $n ?></span>
                        <?php else: ?><a href="<?= $link($n) ?>"><?= $n ?></a><?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $pages): ?><a href="<?= $link($page + 1) ?>" rel="next"><?= html_escape($t['next']) ?></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => $locale === 'ar' ? 'بقلم من يضعون المعيار' : 'Written by the people who set the standard',
    'close_text'  => $locale === 'ar'
        ? 'تأتي المقالات من فريق التحرير نفسه الذي يكتب الدورات والإجراءات، وتذكر مصادرها.'
        : 'Articles come from the same editorial desk that writes the courses and the procedures, and they cite what they rely on.',
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
