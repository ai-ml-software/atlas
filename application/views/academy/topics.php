<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $heading['title'],
    'hero_lede'  => $heading['lede'],
    'hero_image' => ha_page_art(array('city-jeddah', 'city-riyadh')),
    'hero_alt'   => '',
    'hero_stat'  => array('n' => (string) count($pillars), 'label' => $t['topics']),
)); ?>

<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-grid ha-grid--3">
            <?php foreach ($pillars as $topic): ?>
                <article class="ha-card ha-card--media">
                    <?= ha_media_figure($topic['hero_image'], ha_image_alt($topic['hero_image'], $locale)) ?>
                    <h2 class="ha-card__title"><a href="<?= base_url($locale . '/hospitality-topics/' . rawurlencode($topic['slug'])) ?>"><?= html_escape($topic['title']) ?></a></h2>
                    <p><?= html_escape(character_limiter(trim(strip_tags($topic['intro'])), 170)) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($cities): ?>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= $locale === 'ar' ? 'حسب المدينة' : 'By city' ?></h2>
        </div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($cities as $topic): ?>
                <article class="ha-card ha-card--media">
                    <?= ha_media_figure($topic['hero_image'], ha_image_alt($topic['hero_image'], $locale)) ?>
                    <h2 class="ha-card__title"><a href="<?= base_url($locale . '/hospitality-topics/' . rawurlencode($topic['slug'])) ?>"><?= html_escape($topic['title']) ?></a></h2>
                    <p><?= html_escape(character_limiter(trim(strip_tags($topic['intro'])), 170)) ?></p>
                    <div class="ha-card__meta">
                        <span class="ha-pill ha-pill--accent"><?= html_escape($topic['city']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $this->load->view('academy/_close', array(
    'close_title' => $locale === 'ar' ? 'تدريب فندقي، مدينة بمدينة' : 'Hotel training, city by city',
    'close_text'  => $locale === 'ar'
        ? 'تغطي صفحات المواضيع كيف تعمل الضيافة فعلياً في كل مدينة سعودية، وأي الدورات تناسب ذلك السوق.'
        : 'Topic pages cover how hospitality actually operates in each Saudi city, and which courses match that market.',
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['about'], 'url' => base_url($locale . '/about')),
)); ?>
