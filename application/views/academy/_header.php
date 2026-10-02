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
                        <a class="ha-mast__cta" data-hkp-menu href="<?= site_url('hkp') ?>">
                            <?= ha_pe('Altus Knowledge and Performance') ?>
                        </a>
                    <?php else: ?>
                        <a class="ha-mast__signin" href="<?= base_url('login') . '?lang=' . rawurlencode($locale) ?>"><?= ha_pe('Login') ?></a>
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

