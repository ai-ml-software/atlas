<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $heading['title'],
    'hero_lede'  => $heading['lede'],
    'hero_image' => ha_page_art(array('management-3', 'city-riyadh')),
    'hero_alt'   => '',
    'hero_stat'  => array('n' => (string) count($paths), 'label' => $t['learning_paths']),
)); ?>

<section class="ha-section">
    <div class="ha-shell">
        <?php if (!$paths): ?>
            <div class="ha-empty"><p><?= html_escape($t['no_results']) ?></p></div>
        <?php else: ?>
            <div class="ha-grid ha-grid--3">
                <?php foreach ($paths as $p): $url = base_url($locale . '/learning-paths/' . rawurlencode($p['slug'])); ?>
                    <article class="ha-card ha-card--media">
                        <?php $cover = ha_image_variant($p['thumbnail'], 'card'); if ($cover): ?>
                            <a class="ha-card__cover" href="<?= $url ?>" tabindex="-1" aria-hidden="true">
                                <img src="<?= base_url($cover) ?>" alt="" loading="lazy" decoding="async">
                            </a>
                        <?php endif; ?>
                        <h2 class="ha-card__title"><a href="<?= $url ?>"><?= html_escape($p['title']) ?></a></h2>
                        <p><?= html_escape($p['summary']) ?></p>
                        <div class="ha-card__meta">
                            <span class="ha-pill"><?= (int) $p['step_count'] ?> <?= html_escape($t['steps']) ?></span>
                            <?php if (!empty($p['department_code'])): ?>
                                <span class="ha-pill ha-pill--accent"><?= html_escape($p['department_code']) ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Start where the job starts'),
    'close_text'  => ha_pt('A learning path runs a new starter from their first shift to a verified certificate, in the order the work is learned.'),
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
)); ?>
