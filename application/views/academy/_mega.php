<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Mega menu under "Altus Knowledge and Performance" (header nav).
 * Feature panel (platform + the profile book), the academy in three columns
 * (Learn, Resources, Company) and the contact column with both founders.
 * Desktop: full-width panel under the bar, revealed by assets/academy/mega.js.
 * Phone drawer: the same markup as an accordion.
 */
$u = function ($p) use ($locale) { return base_url($locale . '/' . $p); };
$cols = array(
    array(ha_pt('Learn'), array(array('courses', $t['courses']), array('programs', $t['programs']), array('learning-paths', $t['learning_paths']), array('certificates', $t['certificates']))),
    array(ha_pt('Resources'), array(array('sop', $t['sop']), array('hospitality-topics', $t['topics']), array('articles', $t['articles']), array('verify', $t['verify_title']))),
    array(ha_pt('Company'), array(array('about', $t['about']), array('hotels', $t['for_hotels']), array('contact', $t['contact']), array('credits', ha_pt('Photo credits')))),
);
// Same editable menus as the footer (Website studio → Navigation & footer); an existing menu wins, even when empty.
if (!empty($studio_footer_menus)) {
    foreach (array('footer_learn', 'footer_resources', 'footer_company') as $i => $code) {
        if (isset($studio_footer_menus[$code]) && get_instance()->db->where('code', $code)->count_all_results('ha_menu')) {
            $cols[$i][1] = array_map(function ($item) { return array($item['url'], $item['label']); }, $studio_footer_menus[$code]);
        }
    }
}
$people = !empty($founders) ? $founders : array(
    array('name' => ha_chrome('ha_founder_1_name', $locale), 'phone' => ha_chrome('ha_founder_1_phone', $locale), 'email' => '', 'photo' => '', 'social' => array()),
    array('name' => ha_chrome('ha_founder_2_name', $locale), 'phone' => ha_chrome('ha_founder_2_phone', $locale), 'email' => '', 'photo' => '', 'social' => array()),
);
$support = ha_chrome('ha_contact_email', $locale);
$cover = is_file(FCPATH . 'uploads/academy/profile/' . ($locale === 'ar' ? 'ar' : 'en') . '/01-sm.webp')
    ? base_url('uploads/academy/profile/' . ($locale === 'ar' ? 'ar' : 'en') . '/01-sm.webp') : '';
?>
<div class="ha-mega__panel" id="ha-mega-panel" data-ha-mega-panel>
    <div class="ha-mega__inner">
        <div class="ha-mega__feature" data-mega-col>
            <p class="ha-mega__kicker"><?= ha_pe('Proprietary platform') ?></p>
            <p class="ha-mega__title"><?= ha_pe('Altus Knowledge and Performance') ?></p>
            <p class="ha-mega__text"><?= ha_pe('Hotel learning, knowledge and measurable performance, in Arabic and English, white-label for every property.') ?></p>
            <div class="ha-mega__actions">
                <a class="ha-mega__btn" href="<?= $u('knowledge-performance') ?>"><?= ha_pe('Discover the platform') ?> <span aria-hidden="true">→</span></a>
                <a class="ha-mega__btn ha-mega__btn--line" href="<?= site_url('hkp') ?>"><?= ha_pe('Sign in') ?></a>
            </div>
            <a class="ha-mega__book" href="<?= $u('profile') ?>">
                <?php if ($cover !== ''): ?><img src="<?= $cover ?>" alt="" width="62" height="88" loading="lazy"><?php endif; ?>
                <span><strong><?= ha_pe('Corporate Profile 2026') ?></strong><small><?= ha_pe('Read it as a book') ?> <span aria-hidden="true">→</span></small></span>
            </a>
            <p class="ha-mega__editions">
                <a href="<?= base_url('en/profile') ?>" hreflang="en" lang="en">English</a>
                <a href="<?= base_url('ar/profile') ?>" hreflang="ar" lang="ar" dir="rtl">العربية</a>
            </p>
        </div>
        <?php foreach ($cols as $c): ?>
            <div class="ha-mega__col" data-mega-col>
                <p class="ha-mega__h"><?= html_escape($c[0]) ?></p>
                <ul>
                    <?php foreach ($c[1] as $l): ?><li><a href="<?= $u($l[0]) ?>"><?= html_escape($l[1]) ?></a></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <div class="ha-mega__col ha-mega__contact" data-mega-col>
            <p class="ha-mega__h"><?= ha_pe('Contact') ?></p>
            <p class="ha-mega__place"><?= ha_pe('Riyadh') ?><br><?= ha_pe('Kingdom of Saudi Arabia') ?></p>
            <?php foreach ($people as $p): $digits = preg_replace('/\D/', '', (string) $p['phone']); if ($digits === '') continue; ?>
                <div class="ha-mega__person">
                    <?php if ($p['photo'] !== ''): ?><img class="ha-mega__face" src="<?= base_url(str_replace('.webp', '-sm.webp', $p['photo'])) ?>" alt="" width="44" height="44" loading="lazy"><?php endif; ?>
                    <div>
                        <strong><?= html_escape($p['name']) ?></strong>
                        <span><?= ha_pe('Co-Founder') ?></span>
                        <a class="ha-mega__phone" href="tel:+<?= $digits ?>" dir="ltr"><?= html_escape($p['phone']) ?></a>
                        <?php if ($p['email'] !== ''): ?><a class="ha-mega__mail" href="mailto:<?= html_escape($p['email']) ?>"><?= html_escape($p['email']) ?></a><?php endif; ?>
                        <span class="ha-mega__acts">
                            <a href="https://wa.me/<?= $digits ?>" target="_blank" rel="noopener noreferrer"><?= ha_pe('WhatsApp') ?></a>
                            <a href="tel:+<?= $digits ?>"><?= ha_pe('Call') ?></a>
                        </span>
                        <?= ha_social_icons($p['social'], 'ha-mega__social', $p['name']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($support !== ''): ?><p class="ha-mega__support"><?= ha_pe('Support') ?>: <a href="mailto:<?= html_escape($support) ?>"><?= html_escape($support) ?></a></p><?php endif; ?>
        </div>
    </div>
</div>
