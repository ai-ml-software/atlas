<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $heading['title'],
    'hero_lede'  => $heading['lede'],
    'hero_image' => ha_page_art(array('housekeeping-3', 'front-office')),
    'hero_alt'   => '',
)); ?>

<section class="ha-section">
    <div class="ha-shell">

        <form class="ha-filters" method="get" action="<?= base_url($locale . '/courses') ?>" data-ha-autosubmit>
            <div class="ha-field ha-field--wide">
                <label for="f-q"><?= html_escape($t['search']) ?></label>
                <input id="f-q" type="search" name="q" value="<?= html_escape((string) $filters['search']) ?>"
                       placeholder="<?= html_escape($t['search_placeholder']) ?>">
            </div>
            <div class="ha-field">
                <label for="f-cat"><?= html_escape($t['all_categories']) ?></label>
                <select id="f-cat" name="category">
                    <option value=""><?= html_escape($t['all_categories']) ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= html_escape($cat['code']) ?>"
                            <?= $filters['category'] === $cat['code'] ? ' selected' : '' ?>>
                            <?= html_escape($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ha-field">
                <label for="f-level"><?= html_escape($t['level']) ?></label>
                <select id="f-level" name="level">
                    <option value=""><?= html_escape($t['all_levels']) ?></option>
                    <?php foreach (array('foundation', 'intermediate', 'advanced', 'leadership') as $lv): ?>
                        <option value="<?= $lv ?>"<?= $filters['level'] === $lv ? ' selected' : '' ?>>
                            <?= html_escape(call_user_func($level_label, $lv)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="ha-btn" type="submit"><?= html_escape($t['search']) ?></button>
        </form>

        <p class="ha-count">
            <?= html_escape($t['showing']) ?>
            <?= $total ? (($page - 1) * $per_page + 1) . '–' . min($page * $per_page, $total) : 0 ?>
            <?= html_escape($t['of']) ?> <?= (int) $total ?> <?= html_escape($t['results']) ?>
        </p>

        <?php if (!$courses): ?>
            <div class="ha-empty"><p><?= html_escape($t['no_results']) ?></p></div>
        <?php else: ?>
            <div class="ha-grid ha-grid--3">
                <?php foreach ($courses as $c): ?>
                    <article class="ha-card ha-card--media">
                        <?= ha_media_figure($c['thumbnail'], ha_image_alt($c['thumbnail'], $locale)) ?>
                        <h2 class="ha-card__title"><a href="<?= base_url($locale . '/courses/' . rawurlencode($c['slug'])) ?>"><?= html_escape($c['title']) ?></a></h2>
                        <p><?= html_escape($c['short_description']) ?></p>
                        <div class="ha-card__meta">
                            <?php if (!empty($c['category_name'])): ?>
                                <span class="ha-pill"><?= html_escape($c['category_name']) ?></span>
                            <?php endif; ?>
                            <span class="ha-pill ha-pill--accent"><?= html_escape(call_user_func($level_label, $c['level'])) ?></span>
                            <span class="ha-pill"><?= (int) $c['duration_minutes'] ?> <?= html_escape($t['minutes']) ?></span>
                            <?php if ((int) $c['certificate_eligible'] === 1): ?>
                                <span class="ha-pill ha-pill--ok"><?= html_escape($t['certificate']) ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <?php
                $qs = array_filter(array(
                    'q' => $filters['search'], 'category' => $filters['category'], 'level' => $filters['level'],
                ));
                $link = function ($n) use ($qs, $locale) {
                    $qs['page'] = $n;
                    return base_url($locale . '/courses?' . http_build_query($qs));
                };
                ?>
                <nav class="ha-pager" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= $link($page - 1) ?>" rel="prev"><?= html_escape($t['previous']) ?></a>
                    <?php endif; ?>
                    <?php for ($n = 1; $n <= $pages; $n++): ?>
                        <?php if ($n === $page): ?>
                            <span aria-current="page"><?= $n ?></span>
                        <?php else: ?>
                            <a href="<?= $link($n) ?>"><?= $n ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $pages): ?>
                        <a href="<?= $link($page + 1) ?>" rel="next"><?= html_escape($t['next']) ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => $locale === 'ar' ? 'درّب على الدور، لا على المسمى' : 'Train the role, not the job title',
    'close_text'  => $locale === 'ar'
        ? 'الدورات مرتّبة حسب أقسام الفندق، بحيث يحصل كل موظف على ما يحتاجه شِفته تحديداً.'
        : 'Courses are grouped by hotel department so a housekeeper and a front desk agent each get what their own shift needs.',
    'close_primary'   => array('label' => $t['programs'], 'url' => base_url($locale . '/programs')),
    'close_secondary' => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
)); ?>
