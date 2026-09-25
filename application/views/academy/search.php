<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<section class="ha-hero">
    <div class="ha-shell">
        <h1><?= html_escape($t['search']) ?></h1>
        <?php if ($term !== ''): ?>
            <p class="ha-hero__lede">
                <?= html_escape($t['search_results_for']) ?> &ldquo;<?= html_escape($term) ?>&rdquo;<?php
                // Say how much was found. A results page that only repeats the
                // query tells the visitor nothing they did not already know.
                $found = 0;
                foreach (array('courses', 'articles', 'topics', 'programs') as $bucket) {
                    if (isset($$bucket) && is_array($$bucket)) { $found += count($$bucket); }
                }
                ?> &middot; <?= (int) $found ?> <?= html_escape($t['results']) ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="ha-section">
    <div class="ha-shell">
        <?php if ($term === ''): ?>
            <div class="ha-empty"><p><?= html_escape($t['search_placeholder']) ?></p></div>
        <?php elseif (!$results): ?>
            <div class="ha-empty"><p><?= html_escape($t['no_results']) ?></p></div>
        <?php else: ?>
            <div class="ha-grid ha-grid--2">
                <?php foreach ($results as $r):
                    $paths = array('course' => 'courses', 'article' => 'articles', 'topic' => 'hospitality-topics');
                    $base = isset($paths[$r['type']]) ? $paths[$r['type']] : 'courses';
                ?>
                    <article class="ha-card">
                        <div class="ha-card__meta" style="margin:0 0 .5rem">
                            <span class="ha-pill ha-pill--accent"><?= html_escape($r['type']) ?></span>
                        </div>
                        <h3><a href="<?= base_url($locale . '/' . $base . '/' . rawurlencode($r['slug'])) ?>"><?= html_escape($r['title']) ?></a></h3>
                        <?php if (!empty($r['excerpt'])): ?>
                            <p><?= html_escape(character_limiter($r['excerpt'], 150)) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Not finding it?'),
    'close_text'  => ha_pt('Browse the catalogue by department, or tell us what your team needs to be trained on.'),
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
