<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Public academy layout.
 *
 * The Arabic version is a genuine right-to-left document: dir is set on the
 * html element, the logical CSS properties do the mirroring, and the font
 * stack changes with the language. Plan sections 40 and 43.
 */
$is_rtl = $rtl;
$alt = $seo->get('alternate_path');
// Same page in every published site language (languages without their own slug use the English path).
$lang_links = array();
foreach ($site_locales as $lc) {
    $p = is_array($alt) ? (isset($alt[$lc]) ? $alt[$lc] : (isset($alt['en']) ? $alt['en'] : '')) : $alt;
    $lang_links[$lc] = base_url($lc . ($p === '' ? '' : '/' . $p));
}

/*
 * These pages used to read nothing from the admin panel, which is why
 * uploading a logo there appeared to do nothing: the upload was saved
 * correctly and this layout drew a hardcoded SVG instead. Everything an
 * administrator can change about the brand is resolved here, once, with the
 * built-in mark as the fallback when nothing has been uploaded.
 */
$ha_brand_name = trim((string) get_settings('system_title'));
if ($ha_brand_name === '') {
    $ha_brand_name = $seo->brand();
}
$ha_logo_header = trim((string) get_frontend_settings('dark_logo'));
$ha_logo_footer = trim((string) get_frontend_settings('light_logo'));
$ha_logo_header = ($ha_logo_header !== '' && file_exists(FCPATH . 'uploads/system/' . $ha_logo_header))
    ? base_url('uploads/system/' . $ha_logo_header) : '';
$ha_logo_footer = ($ha_logo_footer !== '' && file_exists(FCPATH . 'uploads/system/' . $ha_logo_footer))
    ? base_url('uploads/system/' . $ha_logo_footer) : '';
$ha_logo_small = trim((string) get_frontend_settings('small_logo'));
$ha_logo_small = ($ha_logo_small !== '' && file_exists(FCPATH . 'uploads/system/' . $ha_logo_small))
    ? base_url('uploads/system/' . $ha_logo_small) : '';
$ha_favicon = trim((string) get_frontend_settings('favicon'));
$ha_favicon = ($ha_favicon !== '' && file_exists(FCPATH . 'uploads/system/' . $ha_favicon))
    ? base_url('uploads/system/' . $ha_favicon) : '';
$ha_touch_icon = file_exists(FCPATH . 'uploads/system/apple-touch-icon.png')
    ? base_url('uploads/system/apple-touch-icon.png') : $ha_favicon;
$ha_custom_css = trim((string) get_frontend_settings('custom_css'));
?><!DOCTYPE html>
<html lang="<?= $locale ?>" dir="<?= $dir ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $seo->render_head() ?>

    <?php if ($ha_favicon !== ''): ?>
    <?php /* Read from the administrator's setting, like the logos. */ ?>
    <link rel="icon" type="image/png" href="<?= html_escape($ha_favicon) ?>">
    <link rel="apple-touch-icon" href="<?= html_escape($ha_touch_icon) ?>">
    <?php endif; ?>
    <?php if (!empty($hero_preload)): ?>
    <?php /* The LCP element on every page that has a hero. See Academy::hero_preload. */ ?>
    <link rel="preload" as="image" fetchpriority="high" href="<?= html_escape($hero_preload) ?>">
    <?php endif; ?>
    <?php
    /*
     * Typefaces are self-hosted (see .lab/fetch_fonts.py): no render-blocking
     * request to a third-party host, and the files are versioned with the code
     * that depends on them.
     *
     * The fonts are deliberately NOT preloaded. They were, until the fallbacks
     * in altus-fonts.css were metric-matched to them: with the swap now
     * invisible (measured CLS 0.000, down from 0.191), an early font costs the
     * page more than it saves. On a slow connection two preloaded faces
     * compete for bandwidth with the hero photograph, which is the actual LCP
     * element -- the home page measured 4.1s that way against 2.1s for pages
     * carrying fewer faces. The image is preloaded above instead, and the text
     * paints immediately in a fallback that occupies the same space.
     */
    ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-fonts.css') ?>">
    <?php /* Brand tokens must load before the theme, which defines its roles in terms of them. */ ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/academy/academy.css') ?>">
    <?php /* Owns the header and footer; must load after the theme it overrides. */ ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-chrome.css') ?>">
    <?php /* Altus Gulf corporate pages (About, Services, Ascent, Market, Case Studies, Leadership). */ ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-corporate.css') ?>">
    <?php /* The approved reference theme (dark editorial, photographic), loaded last over everything above. */ ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-theme.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/academy/library.css') ?>">
    <?php if ($view === 'profile_book'): ?><link rel="stylesheet" href="<?= base_url('assets/academy/profile-book.css') ?>"><?php endif; ?>
    <?php if ($ha_custom_css !== ''): ?>
        <style><?= $ha_custom_css ?></style>
    <?php endif; ?>
</head>
<body class="ha ha--<?= $locale ?><?= $is_rtl ? ' ha--rtl' : '' ?><?= in_array($locale, array('en', 'tl'), true) ? '' : ' ha--intl' ?><?= in_array($view, array('home_altus', 'profile_book'), true) ? ' ha--overlay' : '' ?>">

<a class="ha-skip" href="#ha-main"><?= html_escape($t['skip_to_content']) ?></a>

<?php
/*
 * Site chrome. Everything a visitor reads here that is not a route label comes
 * from frontend_settings, so the rail note, the address, the contact details
 * and the social links are the administrator's to change without a deploy.
 * ha_chrome() falls back to the shipped wording when a key has never been set.
 */
$ha_social = array(
    'linkedin'  => ha_chrome('ha_social_linkedin', $locale),
    'facebook'  => ha_chrome('ha_social_facebook', $locale),
    'instagram' => ha_chrome('ha_social_instagram', $locale),
    'youtube'   => ha_chrome('ha_social_youtube', $locale),
    'x'         => ha_chrome('ha_social_x', $locale),
);
$ha_social = array_filter($ha_social, function ($v) { return trim((string) $v) !== ''; });
?>
<div class="ha-chrome" data-ha-chrome>
    <div class="ha-mast">
        <div class="ha-shell ha-mast__inner">

            <div class="ha-mast__row">
                <a class="ha-mast__brand" href="<?= base_url($locale) ?>">
                    <?php if (($ha_logo_footer ?: $ha_logo_header) !== ''): ?>
                        <?php /* The bar is dark in this theme: light-on-dark lockup. */ ?>
                        <img class="ha-mast__logo" src="<?= $ha_logo_footer ?: $ha_logo_header ?>" alt="<?= html_escape($ha_brand_name) ?>" width="186" height="40">
                    <?php else: ?>
                        <span class="ha-brand__name"><?= html_escape($ha_brand_name) ?></span>
                    <?php endif; ?>
                </a>

                            <nav class="ha-mast__nav" id="ha-nav" aria-label="<?= html_escape($ha_brand_name) ?>">
                    <?php /* Visible only while the panel is a panel; Escape and the scrim also close it. */ ?>
                    <button class="ha-iconbtn ha-mast__close" type="button" data-ha-nav-close
                            aria-label="<?= ha_pe('Close menu') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" aria-hidden="true" focusable="false">
                            <path d="M6 6l12 12M18 6L6 18"/>
                        </svg>
                    </button>
                    <ul>
                        <?php foreach ($menu as $item):
                            $item_path = $locale . ($item['url'] === '' ? '' : '/' . $item['url']);
                            if ($item['url'] === 'knowledge-performance'): ?>
                            <?php /* Mega menu: the platform, the academy (learn, resources, company), the profile book and the founders. */ ?>
                            <li class="ha-mega" data-ha-mega>
                                <span class="ha-mega__head">
                                    <a href="<?= base_url($item_path) ?>"><?= html_escape($item['label']) ?></a>
                                    <button class="ha-mega__toggle" type="button" aria-expanded="false" aria-controls="ha-mega-panel" data-ha-mega-toggle>
                                        <span class="ha-visually-hidden"><?= ha_pe('Show the academy menu') ?></span>
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                    </button>
                                </span>
                                <?php $this->load->view('academy/_mega', array('locale' => $locale, 't' => $t, 'rtl' => $is_rtl, 'founders' => isset($founders) ? $founders : array())); ?>
                            </li>
                            <?php else: ?>
                            <li><a href="<?= base_url($item_path) ?>"><?= html_escape($item['label']) ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php /* Filled by academy.js with whatever does not fit; hidden when everything does. */ ?>
                        <li class="ha-more" data-ha-more>
                            <button class="ha-more__btn" type="button" aria-expanded="false" aria-haspopup="true">
                                <?= ha_pe('More') ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="M5 9l7 7 7-7"/>
                                </svg>
                            </button>
                            <ul class="ha-more__panel"></ul>
                        </li>
                    </ul>
                </nav>

                <div class="ha-mast__actions">
                    <details class="ha-langmenu" data-ha-langmenu>
                        <summary class="ha-rail__lang" aria-label="<?= ha_pe('Language') ?>: <?= html_escape(ha_locale_name($locale)) ?>"><span lang="<?= $locale ?>" aria-hidden="true"><?= strtoupper($locale) ?></span></summary>
                        <ul class="ha-langmenu__list" role="list">
                            <?php foreach ($lang_links as $lc => $href): ?>
                            <li><a href="<?= html_escape($href) ?>" hreflang="<?= $lc ?>" lang="<?= $lc ?>" dir="<?= ha_locale_dir($lc) ?>" data-ha-lang-switch<?= $lc === $locale ? ' aria-current="true"' : '' ?>><?= html_escape(ha_locale_name($lc)) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>

                    <form class="ha-mast__search" data-ha-search action="<?= base_url($locale . '/search') ?>" method="get" role="search">
                        <label class="ha-visually-hidden" for="ha-q"><?= html_escape($t['search']) ?></label>
                        <input id="ha-q" type="search" name="q" placeholder="<?= html_escape($t['search_placeholder']) ?>"
                               value="<?= isset($term) ? html_escape($term) : '' ?>" tabindex="-1">
                        <button class="ha-iconbtn" type="button" data-ha-search-toggle
                                aria-expanded="false" aria-controls="ha-q"
                                aria-label="<?= html_escape($t['search']) ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                                 stroke-linecap="round" aria-hidden="true" focusable="false">
                                <circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>
                            </svg>
                        </button>
                    </form>

                    <?php
                    // Views get the Loader as $this, not the controller, and the
                    // public pages do not autoload the session library, so reach
                    // the instance directly and cope with it being absent.
                    $ha_ci    = get_instance();
                    $ha_sess  = isset($ha_ci->session) ? $ha_ci->session : null;
                    $ha_user  = $ha_sess ? $ha_sess->userdata('user_login')  : false;
                    $ha_admin = $ha_sess ? $ha_sess->userdata('admin_login') : false;
                    ?>
                    <?php if ($ha_user || $ha_admin): ?>
                        <?php /* Signed-in people work in Altus Knowledge and Performance; /hkp routes each role to its home. */ ?>
                        <a class="ha-mast__cta" href="<?= site_url('hkp') ?>">
                            <?= ha_pe('Altus Knowledge and Performance') ?>
                        </a>
                    <?php else: ?>
                        <a class="ha-mast__signin" href="<?= base_url('login') ?>"><?= ha_pe('Login') ?></a>
                        <a class="ha-mast__cta" href="<?= base_url($locale . '/contact') ?>"><?= ha_pe('Get in Touch') ?> <span aria-hidden="true">→</span></a>
                    <?php endif; ?>

                    <button class="ha-iconbtn ha-mast__burger" type="button" data-ha-nav-toggle
                            aria-expanded="false" aria-controls="ha-nav"
                            aria-label="<?= ha_pe('Menu') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" aria-hidden="true" focusable="false">
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </button>
                </div>
            </div>



        </div>
    </div>
</div>
<div class="ha-scrim" data-ha-scrim hidden></div>

<?php $crumbs = $seo->breadcrumbs(); if (count($crumbs) > 1): ?>
<nav class="ha-crumbs" aria-label="Breadcrumb">
    <div class="ha-shell">
        <ol>
            <?php foreach ($crumbs as $i => $crumb): $last = ($i === count($crumbs) - 1); ?>
                <li>
                    <?php if ($last): ?>
                        <span aria-current="page"><?= html_escape($crumb['label']) ?></span>
                    <?php else: ?>
                        <a href="<?= base_url($locale . ($crumb['path'] === '' ? '' : '/' . $crumb['path'])) ?>"><?= html_escape($crumb['label']) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</nav>
<?php endif; ?>

<main id="ha-main" class="ha-main">
    <?php $this->load->view('academy/' . $view, get_defined_vars()); ?>
</main>

<footer class="ha-foot">
    <?php if ($ha_logo_small !== ''): ?>
        <img class="ha-foot__watermark" src="<?= $ha_logo_small ?>" alt="" aria-hidden="true" loading="lazy">
    <?php endif; ?>
    <div class="ha-shell ha-foot__inner">

        <?php /* Reference footer row: logo, the corporate menu, the place. */ ?>
        <div class="ha-foot__top">
            <a href="<?= base_url($locale) ?>" aria-label="<?= html_escape($ha_brand_name) ?>">
                <?php if ($ha_logo_footer !== ''): ?>
                    <img class="ha-foot__logo" src="<?= $ha_logo_footer ?>" alt="<?= html_escape($ha_brand_name) ?>" width="186" height="40" loading="lazy">
                <?php else: ?>
                    <span class="ha-footer__name"><?= html_escape($ha_brand_name) ?></span>
                <?php endif; ?>
            </a>
            <nav class="ha-foot__nav" aria-label="<?= ha_pe('Footer') ?>">
                <?php foreach ($menu as $item): ?>
                    <a href="<?= base_url($locale . ($item['url'] === '' ? '' : '/' . $item['url'])) ?>"><?= html_escape($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
            <p class="ha-foot__place"><?= ha_pe('Riyadh') ?> | <?= ha_pe('Saudi Arabia') ?> | <?= ha_pe('GCC') ?></p>
        </div>
        <p class="ha-foot__statement"><?= html_escape(ha_chrome('ha_footer_statement', $locale)) ?></p>

        <?php
        /*
         * Grouped, not listed. The previous footer put fourteen links in one
         * undifferentiated row, which is an inventory rather than wayfinding.
         * Each group is a real heading, so a screen reader announces the
         * structure and a scanning eye finds the right third of it.
         */
        $ha_foot_groups = array(
            // The corporate pages are the footer's top row (the header menu); the groups below are the academy.
            array(
                'title' => ha_pt('Learn'),
                'links' => array(
                    array('courses', $t['courses']),
                    array('programs', $t['programs']),
                    array('learning-paths', $t['learning_paths']),
                    array('certificates', $t['certificates']),
                ),
            ),
            array(
                'title' => ha_pt('Resources'),
                'links' => array(
                    array('sop', $t['sop']),
                    array('hospitality-topics', $t['topics']),
                    array('articles', $t['articles']),
                    array('verify', $t['verify_title']),
                ),
            ),
            array(
                'title' => ha_pt('Company'),
                'links' => array(
                    array('about', $t['about']),
                    array('hotels', $t['for_hotels']),
                    array('contact', $t['contact']),
                    array('credits', ha_pt('Photo credits')),
                ),
            ),
        );
        $ha_email = ha_chrome('ha_contact_email', $locale);
        $ha_phone = ha_chrome('ha_contact_phone', $locale);
        ?>
        <div class="ha-foot__cols">
            <?php foreach ($ha_foot_groups as $group): ?>
                <div class="ha-foot__col">
                    <h2><?= html_escape($group['title']) ?></h2>
                    <ul>
                        <?php foreach ($group['links'] as $link): ?>
                            <li><a href="<?= base_url($locale . '/' . $link[0]) ?>"><?= html_escape($link[1]) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <div class="ha-foot__col">
                <h2><?= ha_pe('Contact') ?></h2>
                <div class="ha-foot__meta">
                    <address><?= nl2br(html_escape(ha_chrome('ha_contact_address', $locale))) ?></address>
                    <?php if ($ha_email !== ''): ?>
                        <a href="mailto:<?= html_escape($ha_email) ?>"><?= html_escape($ha_email) ?></a>
                    <?php endif; ?>
                    <?php if ($ha_phone !== ''): ?>
                        <a href="tel:<?= html_escape(preg_replace('/[^\d+]/', '', $ha_phone)) ?>" dir="ltr"><?= html_escape($ha_phone) ?></a>
                    <?php endif; ?>
                </div>
                <?= ha_social_icons($ha_social + array('email' => $ha_email), 'ha-foot__social', 'Altus Gulf') ?>
            </div>
        </div>

        <?php
        /*
         * The founders, from Admin → Leadership profiles: portrait, phone (call and
         * WhatsApp), e-mail and their own social links. The shipped settings are the
         * fallback for a database the profiles have not reached yet.
         */
        $ha_people = !empty($founders) ? $founders : array(
            array('name' => ha_chrome('ha_founder_1_name', $locale), 'role' => ha_chrome('ha_founder_1_role', $locale), 'phone' => ha_chrome('ha_founder_1_phone', $locale), 'email' => '', 'photo' => '', 'social' => array(), 'slug' => ''),
            array('name' => ha_chrome('ha_founder_2_name', $locale), 'role' => ha_chrome('ha_founder_2_role', $locale), 'phone' => ha_chrome('ha_founder_2_phone', $locale), 'email' => '', 'photo' => '', 'social' => array(), 'slug' => ''),
        );
        ?>
        <div class="ha-foot__founders" aria-label="<?= ha_pe('Speak to a founder') ?>">
            <?php foreach ($ha_people as $f): $digits = preg_replace('/\D/', '', (string) $f['phone']); ?>
                <div class="ha-foot__founder">
                    <?php if ($f['photo'] !== ''): ?><img class="ha-foot__face" src="<?= base_url(str_replace('.webp', '-sm.webp', $f['photo'])) ?>" alt="<?= html_escape($f['name']) ?>" width="56" height="56" loading="lazy"><?php endif; ?>
                    <div>
                        <strong><?= html_escape($f['name']) ?></strong>
                        <span><?= ha_pe('Co-Founder') ?></span>
                        <?php if ($digits !== ''): ?><a class="ha-foot__tel" href="tel:+<?= $digits ?>" dir="ltr"><?= html_escape($f['phone']) ?></a><?php endif; ?>
                        <?php if ($f['email'] !== ''): ?><a class="ha-foot__mail" href="mailto:<?= html_escape($f['email']) ?>"><?= html_escape($f['email']) ?></a><?php endif; ?>
                        <div class="ha-foot__actions">
                            <?php if ($digits !== ''): ?>
                                <a href="https://wa.me/<?= $digits ?>" rel="noopener noreferrer" target="_blank"><?= ha_pe('WhatsApp') ?></a>
                                <a href="tel:+<?= $digits ?>"><?= ha_pe('Call') ?></a>
                            <?php endif; ?>
                            <?= ha_social_icons($f['social'], 'ha-foot__person-social', $f['name']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ha-foot__base">
            <p>&copy; <?= date('Y') ?> <?= html_escape($ha_brand_name) ?> · <?= ha_pe('All rights reserved.') ?>
                <?= ha_pe('Elevating Hospitality & Business Performance') ?>
            </p>
            <nav aria-label="<?= html_escape($t['legal']) ?>">
                <a href="<?= base_url($locale . '/privacy') ?>"><?= html_escape($t['privacy']) ?></a>
                <a href="<?= base_url($locale . '/terms') ?>"><?= html_escape($t['terms']) ?></a>
                <a href="<?= base_url($locale . '/credits') ?>"><?= ha_pe('Photo credits') ?></a>
                <a href="<?= base_url('sign_up') ?>"><?= ha_pe('Join Now') ?></a>
                <a href="<?= base_url('sitemap.xml') ?>"><?= ha_pe('Sitemap') ?></a>
            </nav>
        </div>
    </div>
</footer>

<?php /* GSAP drives the mega menu reveal and the profile book. Self-hosted: the CSP allows 'self' only. */ ?>
<script src="<?= base_url('assets/vendor/gsap/gsap.min.js') ?>" defer></script>
<script src="<?= base_url('assets/academy/academy.js') ?>" defer></script>
<script src="<?= base_url('assets/academy/mega.js') ?>" defer></script>
</body>
</html>
