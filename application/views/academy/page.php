<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<section class="ha-hero<?= !empty($page['hero_image']) ? ' ha-hero--image' : '' ?>">
    <?php $hero = ha_image_variant($page['hero_image'], 'wide'); if ($hero): ?>
        <div class="ha-hero__media" aria-hidden="true"
             style="background-image:url('<?= base_url($hero) ?>')"></div>
    <?php endif; ?>
    <div class="ha-shell ha-hero__body">
        <h1><?= html_escape($page['title']) ?></h1>
        <?php if (!empty($page['subtitle'])): ?>
            <p class="ha-hero__lede"><?= html_escape($page['subtitle']) ?></p>
        <?php endif; ?>
        <?php if (!empty($page['cta_label'])): ?>
            <div class="ha-hero__actions">
                <a class="ha-btn" href="<?= base_url($locale . '/' . ltrim((string) $page['cta_url'], '/')) ?>">
                    <?= html_escape($page['cta_label']) ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (trim(strip_tags((string) $page['body'])) !== ''): ?>
<section class="ha-section">
    <div class="ha-shell ha-prose">
        <?= $page['body'] ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($sections)) { $this->load->view('academy/_sections', array('sections' => $sections, 'locale' => $locale)); } ?>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Start training your team'),
    'close_text'  => ha_pt('Browse the catalogue by department, or talk to us about what your property needs.'),
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
