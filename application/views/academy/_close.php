<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * The closing band.
 *
 * Most pages ended on their last grid of cards and then simply became the
 * footer, which leaves the visitor at the bottom of a page with nothing asked
 * of them. This is the one place each page states what to do next, and it is
 * the same dark band everywhere so the end of a page is recognisable as an
 * end rather than as the page having run out.
 *
 * Expected in scope:
 *   $close_title    string
 *   $close_text     string
 *   $close_primary  array('label' => ..., 'url' => ...)
 *   $close_secondary optional, same shape
 */
$close_secondary = isset($close_secondary) ? $close_secondary : null;
?>
<section class="ha-close">
    <div class="ha-shell">
        <div class="ha-close__inner">
            <h2><?= html_escape($close_title) ?></h2>
            <p><?= html_escape($close_text) ?></p>
            <div class="ha-close__actions">
                <a class="ha-btn ha-btn--invert" href="<?= $close_primary['url'] ?>">
                    <?= html_escape($close_primary['label']) ?>
                </a>
                <?php if ($close_secondary): ?>
                    <a class="ha-btn ha-btn--outline" href="<?= $close_secondary['url'] ?>">
                        <?= html_escape($close_secondary['label']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
