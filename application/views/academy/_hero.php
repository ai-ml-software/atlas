<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * The page hero.
 *
 * One partial rather than a copy in each of twenty views, because the hero is
 * the thing most likely to be changed again and the copies had already drifted
 * apart. Give it a picture and it lays out in two columns; leave the picture
 * out and it falls back to the single column it used to be, which is what the
 * pages that genuinely have nothing to show should do.
 *
 * Expected in scope (all optional except the title):
 *   $hero_title   string
 *   $hero_lede    string
 *   $hero_image   path relative to the web root, usually from ha_media
 *   $hero_alt     alt text; empty marks the picture decorative, which it is
 *                 when the heading already carries the meaning
 *   $hero_stat    array('n' => '6', 'label' => 'programmes')
 *   $hero_actions raw HTML for the buttons under the lede
 */
$hero_image   = isset($hero_image) ? $hero_image : '';
$hero_alt     = isset($hero_alt) ? $hero_alt : '';
$hero_lede    = isset($hero_lede) ? $hero_lede : '';
$hero_stat    = isset($hero_stat) ? $hero_stat : null;
$hero_actions = isset($hero_actions) ? $hero_actions : '';
$hero_art     = $hero_image ? ha_image_variant($hero_image, 'wide') : '';
?>
<section class="ha-hero<?= $hero_art ? ' ha-hero--split' : '' ?>">
    <div class="ha-shell ha-hero__body">
        <div>
            <h1><?= html_escape($hero_title) ?></h1>
            <?php if ($hero_lede !== ''): ?>
                <p class="ha-hero__lede"><?= html_escape($hero_lede) ?></p>
            <?php endif; ?>
            <?php if ($hero_actions !== ''): ?>
                <div class="ha-hero__actions"><?= $hero_actions ?></div>
            <?php endif; ?>
        </div>

        <?php if ($hero_art): ?>
            <div class="ha-hero__art">
                <img src="<?= base_url($hero_art) ?>" alt="<?= html_escape($hero_alt) ?>"
                     loading="eager" decoding="async">
                <?php if ($hero_stat && $hero_stat['n'] !== ''): ?>
                    <p class="ha-hero__stat">
                        <b><?= html_escape($hero_stat['n']) ?></b>
                        <span><?= html_escape($hero_stat['label']) ?></span>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
