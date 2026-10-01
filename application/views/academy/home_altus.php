<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Altus Gulf home page, in the approved reference theme (altus-theme.css):
 * photographic hero under a transparent header, an overlapping stat strip,
 * about split, six service image cards, the advantage band, results, market,
 * insights, the founders' word, FAQ and the closing band.
 *
 * One H1 (keyword-led), an answer-first summary, and a descriptive internal
 * link out of every section. Copy: ha_corporate_block (seed 010), editable in
 * the workspace; numbers are the profile's own and the case results are
 * labelled illustrative. Structured data is added by Academy::home().
 */
$first = function ($s) use ($b) { return isset($b[$s][0]) ? $b[$s][0] : array('code' => '', 'title' => '', 'body' => ''); };
$lines = function ($t) { return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $t)), 'strlen')); };
$by = function ($s) use ($b) { $o = array(); foreach ($b[$s] as $x) { $o[$x['code']] = $x; } return $o; };
// Photography: uploads/academy/altus/<name>.webp (see CREDITS.json); the existing library is the fallback.
$img = function ($name, $fallback) {
    foreach (array('uploads/academy/altus/' . $name . '.webp', 'uploads/academy/' . $fallback . '.webp') as $p) {
        if (is_file(FCPATH . $p)) { return base_url($p); }
    }
    return '';
};
$bg = function ($url) { return $url !== '' ? ' style="background-image:url(\'' . html_escape($url) . '\')"' : ''; };
$hero = $first('home_hero');
$about = $by('about');
$ans = $first('home_answer');
list($q_open, $q_close) = $rtl ? array('«', '»') : array('“', '”');
$svc = array();
foreach (array_merge($services['hospitality'], $services['business_growth']) as $s) { $svc[$s['code']] = $s; }
$icons = array(
    'people'  => '<path d="M8.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M2 20c.6-3.4 3.3-5.5 6.5-5.5S14.4 16.6 15 20"/><path d="M16 4.5a3 3 0 0 1 0 6M17.5 14.2c2.3.5 3.9 2.4 4.5 5.8"/>',
    'person'  => '<path d="M12 11.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4.5 21c.7-4 3.6-6.5 7.5-6.5s6.8 2.5 7.5 6.5"/>',
    'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z"/>',
    'grid'    => '<rect x="3.5" y="3.5" width="7" height="7"/><rect x="13.5" y="3.5" width="7" height="7"/><rect x="3.5" y="13.5" width="7" height="7"/><rect x="13.5" y="13.5" width="7" height="7"/>',
);
?>

<?php /* ================================================================ HERO */ ?>
<section class="t-hero" aria-labelledby="t-h1">
    <div class="t-hero__photo" aria-hidden="true"<?= $bg($img('hero-riyadh-terrace', 'page-home')) ?>></div>
    <p class="t-hero__place"><?= ha_pe('Riyadh') ?><br><?= ha_pe('Saudi Arabia') ?><br>&amp; <?= ha_pe('GCC') ?></p>
    <div class="ha-shell t-hero__body">
        <p class="ha-eyebrow"><?= ha_pe('Hospitality intelligence & business strategy') ?></p>
        <h1 id="t-h1"><?= html_escape($hero['title']) ?></h1>
        <p class="t-hero__lede"><?= html_escape($hero['body']) ?></p>
        <div class="t-hero__actions">
            <a class="ha-btn t-btn-light" href="<?= base_url($locale . '/services') ?>"><?= ha_pe('Explore our expertise') ?> <span aria-hidden="true">→</span></a>
            <a class="ha-btn t-btn-line" href="<?= base_url($locale . '/contact') ?>"><?= ha_pe('Start a conversation') ?></a>
        </div>
    </div>
    <p class="t-hero__tag"><?= ha_pe('Better insights.') ?><br><?= ha_pe('Stronger performance.') ?></p>
</section>

<?php /* ============================================== STAT STRIP (real numbers) */ ?>
<section class="t-stats" aria-label="<?= ha_pe('Altus Gulf at a glance') ?>">
    <div class="ha-shell">
        <ul class="t-stats__list">
            <?php foreach (array(array('people', '60+', 'Years combined experience'), array('person', '2', 'Principal advisors'),
                                 array('globe', '12', 'Sectors served'), array('grid', '1', 'Integrated advisory platform')) as $s): ?>
                <li>
                    <svg class="t-stats__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icons[$s[0]] ?></svg>
                    <span class="t-stats__n"><?= $s[1] ?></span><span class="t-stats__l"><?= ha_pe($s[2]) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php /* ================================================== ABOUT (answer first) */ ?>
<section class="ha-section" id="about" aria-labelledby="t-about">
    <div class="ha-shell t-about">
        <div>
            <p class="ha-eyebrow"><?= ha_pe('About Altus Gulf') ?></p>
            <h2 id="t-about"><?= ha_pe('Strategic Advisory for a Stronger Hospitality Future') ?></h2>
            <p><?= html_escape($ans['body']) ?></p>
            <div class="t-links">
                <a class="t-link" href="<?= base_url($locale . '/about-altus') ?>"><?= ha_pe('Our story') ?> <span aria-hidden="true">→</span></a>
                <a class="t-link" href="<?= base_url($locale . '/about-altus#why') ?>"><?= ha_pe('Why Altus Gulf') ?> <span aria-hidden="true">→</span></a>
            </div>
        </div>
        <div class="t-about__media" role="img" aria-label="<?= ha_pe('Riyadh skyline at dusk') ?>"<?= $bg($img('about-riyadh-dusk', 'city-riyadh')) ?>>
            <div class="t-about__card">
                <h3><?= ha_pe('From Vision to Performance') ?></h3>
                <p><?= ha_pe('Turning hospitality potential into measurable results, from the first blueprint to sustained execution.') ?></p>
            </div>
        </div>
        <ol class="t-index" aria-label="<?= ha_pe('How we work') ?>">
            <?php foreach (array('Expertise', 'Insight', 'Implementation', 'Lasting value') as $i => $w): ?>
                <li><span><?= sprintf('%02d', $i + 1) ?></span><span><?= ha_pe($w) ?></span></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<?php /* ===================================================== SERVICE CARDS */ ?>
<section class="ha-section ha-section--tint" id="services" aria-labelledby="t-svcs">
    <div class="ha-shell">
        <div class="t-head">
            <div><p class="ha-eyebrow"><?= ha_pe('Our services') ?></p><h2 id="t-svcs"><?= ha_pe('Comprehensive Solutions for the Hospitality Industry') ?></h2></div>
            <p><?= ha_pe('End-to-end hotel consulting and advisory across the hospitality value chain, helping owners and operators lift performance, increase profitability and stay ahead in a competitive Saudi market.') ?></p>
            <a class="t-link" href="<?= base_url($locale . '/services') ?>"><?= ha_pe('View all services') ?> <span aria-hidden="true">→</span></a>
        </div>
        <ul class="t-svcs">
            <?php foreach (array(
                array('svc-commercial', 'commercial', 'Commercial Advisory', 'Revenue strategy, distribution and GOPPAR performance.', 'services#hospitality-solutions', 'digital-hospitality'),
                array('svc-operations', 'pre_opening', 'Operational Excellence', 'Diagnostics, process improvement, quality and guest experience.', 'services#hospitality-solutions', 'front-office'),
                array('svc-asset', 'due_diligence', 'Asset Performance', 'Investment appraisal, valuation and asset value enhancement.', 'services#business-growth-solutions', 'city-riyadh'),
                array('svc-development', 'owner_representation', 'Hotel Development', 'Owner representation, feasibility and HMA governance.', 'services#hospitality-solutions', 'page-hotels'),
                array('svc-preopening', 'pre_opening', 'Pre-opening & Readiness', 'Launch command, staffing waves and operational setup.', 'services#hospitality-solutions', 'housekeeping-2'),
                array('svc-training', 'leadership', 'Training & Knowledge', 'Bilingual hotel training, e-learning and leadership development.', 'knowledge-performance', 'management'),
            ) as $c): ?>
                <li>
                    <a class="t-svc" href="<?= base_url($locale . '/' . $c[4]) ?>">
                        <div class="t-svc__frame"><div class="t-svc__img" aria-hidden="true"<?= $bg($img($c[0], $c[5])) ?>></div></div>
                        <div class="t-svc__body">
                            <h3><?= ha_pe($c[2]) ?></h3>
                            <p><?= ha_pe($c[3]) ?></p>
                            <span class="t-svc__go" aria-hidden="true">→</span>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php /* ===================================================== THE ADVANTAGE */ ?>
<section class="t-dark" id="why" aria-labelledby="t-adv">
    <div class="t-adv">
        <div class="t-adv__media" role="img" aria-label="<?= ha_pe('Modern architecture at dusk') ?>"<?= $bg($img('why-building', 'page-contact')) ?>></div>
        <div class="t-adv__body">
            <div>
                <p class="ha-eyebrow"><?= ha_pe('Why Altus Gulf') ?></p>
                <h2 id="t-adv"><?= ha_pe('The Advantage You Can Rely On') ?></h2>
                <p><?= html_escape($first('why')['body']) ?></p>
                <a class="t-link" href="<?= base_url($locale . '/ascent') ?>"><?= ha_pe('Our method: the Altus Ascent™ Framework') ?> <span aria-hidden="true">→</span></a>
            </div>
            <ol>
                <?php foreach (array(
                    array('60+ years of combined experience', 'Leadership formed inside Marriott, IHG, Starwood and Accor systems.'),
                    array('Hospitality expertise', 'From hotel operations to commercial strategy.'),
                    array('Saudi market knowledge', 'Riyadh-rooted, bilingual, Vision 2030-aligned.'),
                    array('Measurable performance', 'Evidence-led, KPI-focused, Six Sigma discipline.'),
                    array('Implementation capability', 'From strategy to execution, we stay with you.'),
                ) as $i => $r): ?>
                    <li><span><?= sprintf('%02d', $i + 1) ?></span><div><strong><?= ha_pe($r[0]) ?></strong><small><?= ha_pe($r[1]) ?></small></div></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
</section>

<?php /* ============================== RESULTS (illustrative case figures only) */ ?>
<section class="t-dark ha-section" id="results" aria-labelledby="t-res">
    <div class="ha-shell t-results">
        <div>
            <p class="ha-eyebrow"><?= ha_pe('Performance & results') ?></p>
            <h2 id="t-res"><?= ha_pe('Data-Driven Insights. Real Business Impact.') ?></h2>
            <p><?= ha_pe('We combine operator-grade hospitality knowledge with empirical analytics, financial modelling and Six Sigma discipline, so every recommendation is measurable and auditable.') ?></p>
            <a class="ha-btn t-btn-line" href="<?= base_url($locale . '/case-studies') ?>"><?= ha_pe('Read the case studies') ?> <span aria-hidden="true">→</span></a>
        </div>
        <div class="t-panel">
            <ul class="t-panel__kpis">
                <li><span class="t-panel__label">RevPAR</span><span class="t-panel__v">+25%</span><span class="t-panel__case"><?= ha_pe('Resort turnaround') ?></span></li>
                <li><span class="t-panel__label"><?= ha_pe('Guest satisfaction') ?></span><span class="t-panel__v">+30%</span><span class="t-panel__case"><?= ha_pe('Resort turnaround') ?></span></li>
                <li><span class="t-panel__label"><?= ha_pe('F&B revenue') ?></span><span class="t-panel__v">+8%</span><span class="t-panel__case"><?= ha_pe('19-hotel portfolio, 18 months') ?></span></li>
            </ul>
            <div class="t-panel__stack">
                <div>
                    <h3><?= ha_pe('The GOPPAR Value Stack™') ?></h3>
                    <ol>
                        <?php foreach ($b['goppar'] ?? array() as $g): ?><li><span><?= html_escape($g['title']) ?></span><span aria-hidden="true">↑</span></li><?php endforeach; ?>
                    </ol>
                </div>
                <div class="t-ring">
                    <svg viewBox="0 0 120 120" role="img" aria-label="<?= ha_pe('90 to 95 per cent operational readiness at launch') ?>">
                        <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(244,239,230,.14)" stroke-width="8"/>
                        <circle cx="60" cy="60" r="50" fill="none" stroke="#C45B2F" stroke-width="8" stroke-linecap="round" stroke-dasharray="<?= round(2 * M_PI * 50 * .925, 1) ?> 400" transform="rotate(-90 60 60)"/>
                        <text x="60" y="68" text-anchor="middle" class="t-ring__v">90–95%</text>
                    </svg>
                    <small><?= ha_pe('Operational readiness at launch, dual pre-opening') ?></small>
                </div>
            </div>
            <p class="t-panel__case"><?= ha_pe('Illustrative case results from mandates our principals have led; individual results vary by asset, market and execution.') ?></p>
        </div>
    </div>
</section>

<?php /* ============================================================ MARKET */ ?>
<section class="ha-section" id="market" aria-labelledby="t-mkt">
    <div class="ha-shell t-market">
        <div class="t-market__media" role="img" aria-label="<?= ha_pe('Riyadh skyline at golden hour') ?>"<?= $bg($img('market-skyline', 'city-riyadh')) ?>></div>
        <div>
            <p class="ha-eyebrow"><?= ha_pe('Market opportunity') ?></p>
            <h2 id="t-mkt"><?= ha_pe('A Growing Market. A Bigger Tomorrow.') ?></h2>
            <p><?= html_escape($first('market')['body']) ?></p>
            <a class="t-link" href="<?= base_url($locale . '/market') ?>"><?= ha_pe('Explore market insights') ?> <span aria-hidden="true">→</span></a>
        </div>
        <div>
            <ul class="t-market__kpis">
                <?php foreach ($b['market_kpi'] as $k): ?>
                    <li><span class="t-market__v"><?= html_escape($k['title']) ?></span><span class="t-market__l"><?= html_escape($k['body']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="ag-sources"><?= html_escape($first('market_sources')['body']) ?></p>
        </div>
    </div>
</section>

<?php /* ================================== PLATFORM (knowledge & performance) */ ?>
<?php $pl = $by('platform'); ?>
<section class="ha-section ha-section--tint" id="platform" aria-labelledby="t-plat">
    <div class="ha-shell">
        <div class="t-head">
            <div><p class="ha-eyebrow"><?= ha_pe('Proprietary digital platform') ?></p><h2 id="t-plat"><?= html_escape($pl['positioning']['title'] ?? '') ?></h2></div>
            <p><?= html_escape($pl['positioning']['body'] ?? '') ?></p>
            <a class="t-link" href="<?= base_url($locale . '/knowledge-performance') ?>"><?= ha_pe('Discover the platform') ?> <span aria-hidden="true">→</span></a>
        </div>
        <div class="ha-grid ha-grid--4">
            <?php foreach ($b['platform_features'] as $x): ?><article class="ag-cap"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article><?php endforeach; ?>
        </div>
        <?php $dom = $first('platform_domains'); if ($dom['body'] !== ''): ?>
            <p class="ag-subhead"><?= html_escape($dom['title']) ?></p>
            <ul class="ha-chips"><?php foreach ($lines($dom['body']) as $d): ?><li><a class="ha-chip" href="<?= base_url($locale . '/courses') ?>"><?= html_escape($d) ?></a></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</section>

<?php /* ========================================================== INSIGHTS */ ?>
<?php if ($articles): ?>
<section class="ha-section" id="insights" aria-labelledby="t-ins">
    <div class="ha-shell t-insights">
        <div>
            <p class="ha-eyebrow"><?= ha_pe('Industry insights') ?></p>
            <h2 id="t-ins"><?= ha_pe('Latest Insights & Perspectives') ?></h2>
            <p><?= ha_pe('Articles and practical guidance on hotel operations, training, standards and performance in Saudi Arabia.') ?></p>
            <a class="t-link" href="<?= base_url($locale . '/articles') ?>"><?= ha_pe('View all insights') ?> <span aria-hidden="true">→</span></a>
        </div>
        <ul class="t-posts">
            <?php foreach ($articles as $i => $a): $cover = $a['cover_image'] ? (ha_image_variant($a['cover_image'], 'card') ?: $a['cover_image']) : ''; ?>
                <li>
                    <a class="t-post" href="<?= base_url($locale . '/articles/' . rawurlencode($a['slug'])) ?>">
                        <div class="t-post__img" aria-hidden="true"<?= $bg($img('insight-' . ($i + 1), '') ?: ($cover !== '' ? base_url($cover) : '')) ?>></div>
                        <div class="t-post__body">
                            <?php if (!empty($a['category_name'])): ?><span class="t-post__tag"><?= html_escape($a['category_name']) ?></span><?php endif; ?>
                            <h3><?= html_escape($a['title']) ?></h3>
                            <span class="t-link"><?= ha_pe('Read more') ?> <span aria-hidden="true">→</span></span>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php /* ================================================ THE FOUNDERS' WORD */ ?>
<?php if (!empty($about['founders_quote'])): ?>
<section class="t-voice t-dark" aria-labelledby="t-voice"<?= $bg($img('cta-palms', 'page-home')) ?>>
    <div class="ha-shell t-voice__inner">
        <blockquote>
            <p class="ha-eyebrow" id="t-voice"><?= ha_pe('From the founders') ?></p>
            <p><?= $q_open . html_escape($about['founders_quote']['body']) . $q_close ?></p>
        </blockquote>
        <div class="t-voice__who">
            <?php foreach ($leaders as $l): ?>
                <a class="t-voice__person" href="<?= base_url($locale . '/leadership#' . $l['slug']) ?>">
                    <?php if ($l['photo'] !== ''): ?><img class="t-voice__mono" src="<?= base_url(str_replace('.webp', '-sm.webp', $l['photo'])) ?>" alt="" width="52" height="52" loading="lazy"><?php else: ?><span class="t-voice__mono" aria-hidden="true"><?= html_escape(mb_substr($l['name'], 0, 1)) ?></span><?php endif; ?>
                    <span><strong><?= html_escape($l['name']) ?></strong><small><?= ha_pe('Co-Founder') ?></small></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php /* ========================================================= FAQ (AEO) */ ?>
<?php if ($faqs): ?>
<section class="ha-section ha-section--tint" id="faq" aria-labelledby="ag-faq">
    <div class="ha-shell ag-faq">
        <div class="ha-section__head"><p class="ha-eyebrow"><?= ha_pe('Questions and answers') ?></p><h2 id="ag-faq"><?= ha_pe('Frequently asked questions about Altus Gulf') ?></h2></div>
        <div class="ha-faq">
        <?php foreach ($faqs as $i => $f): ?>
            <details<?= $i === 0 ? ' open' : '' ?>>
                <summary><h3 class="ag-faq__q"><?= html_escape($f['question']) ?></h3></summary>
                <p class="ha-faq__body"><?= html_escape($f['answer']) ?></p>
            </details>
        <?php endforeach; ?>
        </div>
        <p class="t-links"><a class="t-link" href="<?= base_url($locale . '/leadership') ?>"><?= ha_pe('Meet the founders') ?> <span aria-hidden="true">→</span></a>
            <a class="t-link" href="<?= base_url($locale . '/courses') ?>"><?= ha_pe('Browse hotel training courses') ?> <span aria-hidden="true">→</span></a></p>
    </div>
</section>
<?php endif; ?>

<?php /* ============================================================ CLOSE */ ?>
<?php $cl = $first('closing'); ?>
<section class="t-close t-dark" aria-labelledby="t-close"<?= $bg($img('cta-palms', 'city-alula')) ?>>
    <div class="ha-shell t-close__inner">
        <p class="ha-eyebrow"><?= ha_pe('Let’s build a stronger hospitality future') ?></p>
        <h2 id="t-close"><?= html_escape($cl['title']) ?></h2>
        <p><?= html_escape($cl['body']) ?></p>
        <div class="t-hero__actions">
            <a class="ha-btn t-btn-light" href="<?= base_url($locale . '/contact') ?>"><?= ha_pe('Get in Touch') ?> <span aria-hidden="true">→</span></a>
            <a class="ha-btn t-btn-line" href="<?= base_url($locale . '/services') ?>"><?= ha_pe('Explore our services') ?></a>
        </div>
    </div>
</section>
