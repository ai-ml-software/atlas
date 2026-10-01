<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * The 2026 Corporate Profile as a book (assets/academy/profile-book.js turns the
 * pages with GSAP). Pages are the deck exported to WebP; leaf k carries page
 * 2k+1 on its front and 2k+2 on its back. The Arabic edition binds on the right
 * and turns right-to-left. The last page is an HTML back cover with the real
 * contact details (the deck's own last slide holds placeholders).
 *
 * Without JavaScript the pages simply stack as a readable column, and every
 * page's text is in the document below the book.
 */
$base = 'uploads/academy/profile/' . $edition . '/';
$src = function ($n, $sm = false) use ($base) { return base_url($base . sprintf('%02d', $n) . ($sm ? '-sm' : '') . '.webp'); };
$total = count($pages) + 1;                       // + the HTML back cover
$leaves = (int) ceil($total / 2);
$other = $edition === 'ar' ? 'en' : 'ar';
$cover_page = function ($n) use ($pages, $src, $total, $closing, $leaders, $edition) {
    if ($n > $total) {
        return '<div class="pb-page pb-page--blank" aria-hidden="true"></div>';
    }
    if ($n === $total) {
        ob_start(); ?>
        <div class="pb-page pb-page--back">
            <div class="pb-back">
                <img src="<?= base_url('uploads/system/altus-logo-stacked-white.png') ?>" alt="Altus Gulf" width="180" height="130" loading="lazy">
                <p class="pb-back__kicker"><?= ha_pe('Your next stage starts here') ?></p>
                <h2><?= html_escape($closing['title']) ?></h2>
                <p><?= html_escape($closing['body']) ?></p>
                <ul class="pb-back__people">
                    <?php foreach ($leaders as $l): $ph = '+' . $l['phone_digits']; if ($l['phone_digits'] === '') continue; ?>
                        <li><?php if ($l['photo'] !== ''): ?><img src="<?= base_url(str_replace('.webp', '-sm.webp', $l['photo'])) ?>" alt="" width="56" height="56" loading="lazy"><?php endif; ?>
                            <strong><?= html_escape($l['name']) ?></strong><span><?= ha_pe('Co-Founder') ?></span>
                            <?php if ($l['email'] !== ''): ?><a class="pb-back__mail" href="mailto:<?= html_escape($l['email']) ?>"><?= html_escape($l['email']) ?></a><?php endif; ?>
                            <span class="pb-back__acts"><a href="https://wa.me/<?= ltrim($ph, '+') ?>" target="_blank" rel="noopener noreferrer"><?= ha_pe('WhatsApp') ?></a><a href="tel:<?= $ph ?>"><?= ha_pe('Call') ?></a></span></li>
                    <?php endforeach; ?>
                </ul>
                <p class="pb-back__place"><?= ha_pe('Riyadh • Kingdom of Saudi Arabia • GCC & MENA') ?></p>
            </div>
        </div>
        <?php return ob_get_clean();
    }
    $p = $pages[$n - 1];
    return '<div class="pb-page"><img src="' . $src($n) . '" srcset="' . $src($n, true) . ' 620w, ' . $src($n) . ' 1240w" sizes="(max-width: 760px) 92vw, 46vw" alt="'
        . html_escape(sprintf('%s %d: %s', ha_pt('Page'), $n, $p['title'])) . '" width="1240" height="1753"' . ($n > 4 ? ' loading="lazy"' : ' fetchpriority="' . ($n === 1 ? 'high' : 'auto') . '"') . ' decoding="async"></div>';
};
?>
<section class="pb" data-pb data-dir="<?= $edition === 'ar' ? 'rtl' : 'ltr' ?>" data-total="<?= $total ?>" aria-labelledby="pb-title">
    <div class="ha-shell pb__head">
        <div>
            <p class="ha-eyebrow"><?= ha_pe('Corporate profile · 2026 edition') ?></p>
            <h1 id="pb-title"><?= html_escape($page_title) ?></h1>
            <p class="pb__lede"><?= ha_pe('Turn the pages of our profile: who we are, the Altus Ascent™ Framework, the Saudi market, illustrative case studies and the founders.') ?></p>
        </div>
        <div class="pb__tools">
            <a class="t-link" href="<?= base_url($other . '/profile') ?>" hreflang="<?= $other ?>" lang="<?= $other ?>"><?= $other === 'ar' ? 'النسخة العربية' : 'English edition' ?> <span aria-hidden="true">→</span></a>
            <a class="t-link" href="#pb-text"><?= ha_pe('Read as text') ?> <span aria-hidden="true">↓</span></a>
        </div>
    </div>

    <div class="pb__stage" data-pb-stage>
        <div class="pb__book" data-pb-book style="--pb-leaves: <?= $leaves ?>">
            <?php for ($k = 0; $k < $leaves; $k++): ?>
                <div class="pb-leaf" data-pb-leaf="<?= $k ?>">
                    <div class="pb-face pb-face--front"><?= $cover_page(2 * $k + 1) ?><span class="pb-shade" aria-hidden="true"></span></div>
                    <div class="pb-face pb-face--back"><?= $cover_page(2 * $k + 2) ?><span class="pb-shade" aria-hidden="true"></span></div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="ha-shell pb__bar">
        <button class="pb-btn" type="button" data-pb-prev aria-label="<?= ha_pe('Previous page') ?>"><span aria-hidden="true">←</span></button>
        <p class="pb__count" aria-live="polite"><span data-pb-now>1</span> / <?= $total ?></p>
        <button class="pb-btn" type="button" data-pb-next aria-label="<?= ha_pe('Next page') ?>"><span aria-hidden="true">→</span></button>
        <button class="pb-full" type="button" data-pb-full aria-pressed="false" data-label-on="<?= ha_pe('Show full screen') ?>" data-label-off="<?= ha_pe('Exit full screen') ?>">
            <svg class="pb-full__in" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
            <svg class="pb-full__out" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>
            <span data-pb-full-label><?= ha_pe('Show full screen') ?></span>
        </button>
        <ol class="pb__thumbs" aria-label="<?= ha_pe('Pages') ?>">
            <?php for ($n = 1; $n <= $total; $n++): ?>
                <li><button type="button" data-pb-go="<?= $n ?>" aria-label="<?= html_escape(ha_pt('Page') . ' ' . $n . ($n <= count($pages) ? ': ' . $pages[$n - 1]['title'] : '')) ?>">
                    <?php if ($n <= count($pages)): ?><img src="<?= $src($n, true) ?>" alt="" width="62" height="88" loading="lazy"><?php else: ?><span class="pb__thumb-back" aria-hidden="true"></span><?php endif; ?>
                </button></li>
            <?php endfor; ?>
        </ol>
    </div>
</section>

<?php /* The book as text: every page, for search, answer engines and screen readers. */ ?>
<section class="ha-section ha-section--tint" id="pb-text" aria-labelledby="pb-text-h">
    <div class="ha-shell pb-text">
        <h2 id="pb-text-h"><?= ha_pe('The profile as text') ?></h2>
        <?php foreach ($pages as $p): ?>
            <details class="pb-text__page">
                <summary><span class="ha-num"><?= sprintf('%02d', $p['n']) ?></span> <?= html_escape($p['title']) ?></summary>
                <div class="pb-text__body"><?php foreach ($p['text'] as $line): ?><p><?= html_escape($line) ?></p><?php endforeach; ?></div>
            </details>
        <?php endforeach; ?>
    </div>
</section>

<script src="<?= base_url('assets/academy/profile-book.js') ?>" defer></script>
