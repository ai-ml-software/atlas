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
        if (!empty($studio_footer_menus)) {
            foreach (array('footer_learn', 'footer_resources', 'footer_company') as $i => $code) {
                // An existing menu with all items hidden is intentionally empty.
                if ($this->db->where('code', $code)->count_all_results('ha_menu')) {
                    $ha_foot_groups[$i]['links'] = array_map(function ($item) { return array($item['url'], $item['label']); }, $studio_footer_menus[$code]);
                }
            }
        }
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
                <a href="<?= base_url('sign_up') . '?lang=' . rawurlencode($locale) ?>"><?= ha_pe('Join Now') ?></a>
                <a href="<?= base_url('sitemap.xml') ?>"><?= ha_pe('Sitemap') ?></a>
            </nav>
        </div>
    </div>
</footer>
