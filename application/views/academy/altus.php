<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Altus Gulf corporate pages (About, Services, Altus Knowledge and Performance,
 * Ascent, Market, Case Studies, Leadership). Content is the CMS records seeded
 * from the 2026 Corporate Profile; this view only arranges it.
 *
 * $b[section] = list of {code, title, body}; body keeps line breaks, which split
 * into a lead paragraph and a closing line where the profile has one.
 */
$first = function ($section) use ($b) { return isset($b[$section][0]) ? $b[$section][0] : array('title' => '', 'body' => ''); };
$lines = function ($text) { return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text)), 'strlen')); };
$paras = function ($text) use ($lines) {
    $out = '';
    foreach ($lines($text) as $l) { $out .= '<p>' . html_escape($l) . '</p>'; }
    return $out;
};
$closing = $first('closing');
?>

<?php /* ------------------------------------------------------------ hero */ ?>
<?php
// Reference theme: every corporate page opens on its own photograph (uploads/academy/altus, see CREDITS.json).
$ag_photo = array('about-altus' => 'about-riyadh-dusk', 'services' => 'svc-operations', 'knowledge-performance' => 'platform-learning',
    'ascent' => 'why-building', 'market' => 'market-skyline', 'case-studies' => 'svc-development', 'leadership' => 'svc-training');
$ag_file = isset($ag_photo[$slug]) ? 'uploads/academy/altus/' . $ag_photo[$slug] . '.webp' : '';
?>
<section class="ha-hero ha-hero--lead ag-hero"<?php if ($ag_file !== '' && is_file(FCPATH . $ag_file)): ?> data-photo style="background-image:url('<?= base_url($ag_file) ?>')"<?php endif; ?>>
    <div class="ha-shell ha-hero__body">
        <p class="ha-eyebrow"><?= html_escape($tagline['title'] ?: 'Altus Gulf') ?></p>
        <h1><?= html_escape($page_title) ?></h1>
        <?php if ($slug === 'about-altus'): $hero = $first('hero'); $hl = $lines($hero['body']); ?>
            <?php list($q_open, $q_close) = $rtl ? array('«', '»') : array('“', '”'); ?>
            <p class="ag-hero__quote"><?= $q_open . html_escape($hero['title']) ?> <?= html_escape(isset($hl[0]) ? $hl[0] : '') . $q_close ?></p>
            <p class="ha-hero__lede"><?= html_escape(isset($hl[1]) ? $hl[1] : '') ?></p>
        <?php else: ?>
            <p class="ha-hero__lede"><?= html_escape($seo->get('description')) ?></p>
        <?php endif; ?>
        <div class="ha-hero__actions">
            <a class="ha-btn" href="<?= base_url($locale . '/contact') ?>"><?= ha_pe('Start a conversation') ?></a>
            <?php if ($slug !== 'knowledge-performance'): ?>
                <a class="ha-btn ha-btn--ghost" href="<?= base_url($locale . '/knowledge-performance') ?>"><?= ha_pe('Altus Knowledge and Performance') ?></a>
            <?php else: ?>
                <a class="ha-btn ha-btn--ghost" href="<?= site_url('hkp') ?>"><?= ha_pe('Sign in to the platform') ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($slug === 'about-altus'): ?>
<?php /* ======================================================= ABOUT ========= */ ?>
<section class="ha-factband" aria-label="<?= ha_pe('Altus Gulf at a glance') ?>">
    <div class="ha-shell">
        <ul class="ha-factband__list">
            <?php foreach ($b['hero_stats'] as $s): ?>
                <li><span class="ha-factband__n ha-num"><?= html_escape($s['title']) ?></span><span class="ha-factband__l"><?= html_escape($s['body']) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <p class="ha-factband__note"><?= ha_pe('Riyadh • Kingdom of Saudi Arabia • GCC & MENA') ?></p>
    </div>
</section>

<?php $about = array(); foreach ($b['about'] as $x) { $about[$x['code']] = $x; } ?>
<section class="ha-section">
    <div class="ha-shell ag-two">
        <div class="ha-prose">
            <p class="ha-eyebrow"><?= ha_pe('Who we are') ?></p>
            <h2><?= html_escape($about['company_overview']['title'] ?? '') ?></h2>
            <?= $paras($about['company_overview']['body'] ?? '') ?>
            <?php if (!empty($about['two_practices'])): ?>
                <p class="ag-lead"><strong><?= html_escape($about['two_practices']['title']) ?></strong> <?= html_escape($about['two_practices']['body']) ?></p>
            <?php endif; ?>
        </div>
        <dl class="ag-vmp">
            <?php foreach (array('vision', 'mission', 'purpose') as $k): if (empty($about[$k])) continue; ?>
                <div><dt><?= html_escape($about[$k]['title']) ?></dt><dd><?= html_escape($about[$k]['body']) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </div>
</section>

<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= ha_pe('Core philosophy') ?></h2></div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($b['philosophy'] as $x): ?>
                <article class="ha-card ag-card"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="ha-section" id="why">
    <div class="ha-shell">
        <?php $why = $b['why']; $intro = array_shift($why); ?>
        <div class="ha-section__head"><h2><?= html_escape($intro['title']) ?></h2><p><?= html_escape($intro['body']) ?></p></div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($why as $x): ?>
                <article class="ha-card ag-card"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
        <?php if ($b['why_uvp']): ?>
            <aside class="ag-callout"><p class="ha-eyebrow"><?= html_escape($b['why_uvp'][0]['title']) ?></p><p><?= html_escape($b['why_uvp'][0]['body']) ?></p></aside>
        <?php endif; ?>
    </div>
</section>

<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($first('values_intro')['title']) ?></h2><p><?= html_escape($first('values_intro')['body']) ?></p></div>
        <ul class="ag-values">
            <?php foreach ($b['values'] as $i => $x): ?>
                <li><span class="ag-values__n ha-num"><?= sprintf('%02d', $i + 1) ?></span><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($first('partnerships_intro')['title']) ?></h2><p><?= html_escape($first('partnerships_intro')['body']) ?></p></div>
        <div class="ha-grid ha-grid--2">
            <?php foreach ($b['partnerships'] as $x): ?>
                <article class="ha-card ag-card"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($about['independence'])): ?>
            <aside class="ag-callout"><p class="ha-eyebrow"><?= html_escape($about['independence']['title']) ?></p><p><?= html_escape($about['independence']['body']) ?></p></aside>
        <?php endif; ?>
    </div>
</section>

<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <?php $esg = $lines($first('esg_intro')['body']); ?>
        <div class="ha-section__head"><h2><?= html_escape($first('esg_intro')['title']) ?></h2><p><?= html_escape($esg[0] ?? '') ?></p></div>
        <div class="ha-grid ha-grid--2">
            <?php foreach ($b['esg'] as $x): ?>
                <article class="ha-card ag-card"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($esg[1])): ?><p class="ag-signoff"><?= html_escape($esg[1]) ?></p><?php endif; ?>
    </div>
</section>

<section class="ha-section">
    <div class="ha-shell">
        <?php $cmp = $lines($first('compare_intro')['body']); ?>
        <div class="ha-section__head"><h2><?= html_escape($first('compare_intro')['title']) ?></h2><p><?= html_escape($cmp[0] ?? '') ?></p></div>
        <div class="ha-table-wrap">
            <table class="ha-table ag-compare">
                <thead><tr><th scope="col"><?= ha_pe('Dimension') ?></th><th scope="col"><?= ha_pe('Traditional consulting') ?></th><th scope="col">Altus Gulf</th></tr></thead>
                <tbody>
                <?php foreach ($b['compare'] as $x): $cells = array_map('trim', explode('|', $x['body'], 2)); ?>
                    <tr><th scope="row"><?= html_escape($x['title']) ?></th><td><?= html_escape($cells[0]) ?></td><td><strong><?= html_escape($cells[1] ?? '') ?></strong></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($cmp[1])): ?><p class="ag-signoff"><?= html_escape($cmp[1]) ?></p><?php endif; ?>
    </div>
</section>

<?php elseif ($slug === 'services'): ?>
<?php /* ======================================================= SERVICES ====== */ ?>
<?php $div = array(); foreach ($b['division'] as $x) { $div[$x['code']] = $x; } ?>
<?php foreach (array('hospitality' => 'division_hospitality', 'business_growth' => 'division_business_growth') as $key => $code): $d = $div[$code] ?? array('title' => '', 'body' => ''); $dl = $lines($d['body']); ?>
<section class="ha-section<?= $key === 'business_growth' ? ' ha-section--tint' : '' ?>" id="<?= $key === 'hospitality' ? 'hospitality-solutions' : 'business-growth-solutions' ?>">
    <div class="ha-shell">
        <div class="ha-section__head">
            <p class="ha-eyebrow"><?= $key === 'hospitality' ? ha_pe('Division I') : ha_pe('Division II') ?></p>
            <h2><?= html_escape($d['title']) ?></h2><p><?= html_escape($dl[0] ?? '') ?></p>
        </div>
        <ol class="ag-services">
            <?php foreach ($services[$key] as $i => $s): ?>
                <li class="ha-card ag-card"><span class="ag-services__n ha-num"><?= sprintf('%02d', $i + 1) ?></span><h3><?= html_escape($s['title']) ?></h3><p><?= html_escape($s['summary']) ?></p></li>
            <?php endforeach; ?>
        </ol>
        <?php if (!empty($dl[1])): ?><p class="ag-signoff"><?= html_escape($dl[1]) ?></p><?php endif; ?>
    </div>
</section>
<?php endforeach; ?>

<section class="ha-section">
    <div class="ha-shell">
        <?php $ci = $lines($first('capabilities_intro')['body']); ?>
        <div class="ha-section__head"><h2><?= html_escape($first('capabilities_intro')['title']) ?></h2><p><?= html_escape($ci[0] ?? '') ?></p></div>
        <div class="ha-grid ha-grid--4 ag-caps">
            <?php foreach ($b['capabilities'] as $x): ?>
                <article class="ag-cap"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($ci[1])): ?><p class="ag-signoff"><?= html_escape($ci[1]) ?></p><?php endif; ?>
    </div>
</section>

<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <?php $si = $lines($first('sectors_intro')['body']); ?>
        <div class="ha-section__head"><h2><?= html_escape($first('sectors_intro')['title']) ?></h2><p><?= html_escape($si[0] ?? '') ?></p></div>
        <ul class="ha-chips ag-sectors"><?php foreach ($sectors as $s): ?><li><span class="ha-chip"><?= html_escape($s['name']) ?></span></li><?php endforeach; ?></ul>
        <?php if (!empty($si[1])): ?><p class="ag-signoff"><?= html_escape($si[1]) ?></p><?php endif; ?>
    </div>
</section>

<section class="ha-section">
    <div class="ha-shell">
        <?php $di = $lines($first('digital_intro')['body']); ?>
        <div class="ha-section__head"><h2><?= html_escape($first('digital_intro')['title']) ?></h2><p><?= html_escape($di[0] ?? '') ?></p></div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($b['digital'] as $x): ?>
                <article class="ha-card ag-card"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($di[1])): ?><p class="ag-signoff"><?= html_escape($di[1]) ?></p><?php endif; ?>
    </div>
</section>

<?php elseif ($slug === 'knowledge-performance'): ?>
<?php /* ============================================ KNOWLEDGE & PERFORMANCE ===== */ ?>
<?php $pl = array(); foreach ($b['platform'] as $x) { $pl[$x['code']] = $x; } ?>
<section class="ha-section">
    <div class="ha-shell ag-two">
        <div class="ha-prose">
            <p class="ha-eyebrow"><?= ha_pe('Proprietary digital platform') ?></p>
            <h2><?= html_escape($pl['positioning']['title'] ?? '') ?></h2>
            <?= $paras($pl['positioning']['body'] ?? '') ?>
        </div>
        <?php if (!empty($pl['promise'])): ?>
            <blockquote class="ag-quote"><p><?= html_escape($pl['promise']['body']) ?></p><footer><?= html_escape($pl['promise']['title']) ?></footer></blockquote>
        <?php endif; ?>
    </div>
</section>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-grid ha-grid--4">
            <?php foreach ($b['platform_features'] as $x): ?>
                <article class="ag-cap"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= ha_pe('One platform, three experiences') ?></h2></div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($b['platform_experiences'] as $x): ?>
                <article class="ha-card ag-card"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
        <div class="ha-hero__actions ag-center">
            <a class="ha-btn" href="<?= site_url('hkp') ?>"><?= ha_pe('Sign in to the platform') ?></a>
            <a class="ha-btn ha-btn--ghost" href="<?= base_url($locale . '/courses') ?>"><?= html_escape($t['courses']) ?></a>
        </div>
    </div>
</section>

<?php elseif ($slug === 'ascent'): ?>
<?php /* ======================================================= ASCENT ======== */ ?>
<?php $ai = $lines($first('ascent_intro')['body']); ?>
<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= ha_pe('Six stages, one discipline') ?></h2><p><?= html_escape($ai[0] ?? '') ?></p></div>
        <ol class="ha-steps ag-ascent">
            <?php foreach ($b['ascent'] as $i => $x): ?>
                <li class="ha-steps__item"><span class="ha-steps__n ha-num" aria-hidden="true"><?= $i + 1 ?></span><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></li>
            <?php endforeach; ?>
        </ol>
        <?php if (!empty($ai[1])): ?><p class="ag-signoff"><?= html_escape($ai[1]) ?></p><?php endif; ?>
    </div>
</section>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($first('frameworks_intro')['title']) ?></h2><p><?= html_escape($first('frameworks_intro')['body']) ?></p></div>
        <div class="ag-frameworks">
            <figure class="ag-matrix">
                <figcaption><h3><?= html_escape($first('matrix_note')['title']) ?></h3></figcaption>
                <?php $q = array(); foreach ($b['matrix'] as $x) { $q[$x['code']] = $x; } ?>
                <div class="ag-matrix__plot">
                    <p class="ag-matrix__axis ag-matrix__axis--y"><?= ha_pe('Operational rigour') ?> →</p>
                    <div class="ag-matrix__grid" role="list">
                        <?php foreach (array('matrix_legacy', 'matrix_zone', 'matrix_undermanaged', 'matrix_veneer') as $code): if (empty($q[$code])) continue; ?>
                            <div role="listitem" class="ag-matrix__cell<?= $code === 'matrix_zone' ? ' is-zone' : '' ?>"><strong><?= html_escape($q[$code]['title']) ?></strong><span><?= html_escape($q[$code]['body']) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                    <span></span>
                    <p class="ag-matrix__axis ag-matrix__axis--x"><?= ha_pe('Digital & commercial intelligence') ?> →</p>
                </div>
                <p class="ag-matrix__note"><?= html_escape($first('matrix_note')['body']) ?></p>
            </figure>
            <figure class="ag-stack">
                <figcaption><h3><?= html_escape($first('goppar_note')['title']) ?></h3></figcaption>
                <ol>
                    <?php foreach ($b['goppar'] as $x): ?>
                        <li><strong><?= html_escape($x['title']) ?></strong><span><?= html_escape($x['body']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
                <p class="ag-stack__result"><?= html_escape($first('goppar_note')['body']) ?></p>
            </figure>
        </div>
    </div>
</section>

<?php elseif ($slug === 'market'): ?>
<?php /* ======================================================= MARKET ======== */ ?>
<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($first('market')['title']) ?></h2><p><?= html_escape($first('market')['body']) ?></p></div>
        <ul class="ag-kpis">
            <?php foreach ($b['market_kpi'] as $x): ?>
                <li><span class="ag-kpis__v ha-num"><?= html_escape($x['title']) ?></span><span class="ag-kpis__l"><?= html_escape($x['body']) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <h3 class="ag-subhead"><?= ha_pe('Demand catalysts') ?></h3>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($b['market_catalyst'] as $x): ?>
                <article class="ha-card ag-card"><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
        <p class="ag-sources"><strong><?= html_escape($first('market_sources')['title']) ?>:</strong> <?= html_escape($first('market_sources')['body']) ?></p>
    </div>
</section>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($first('vision2030_intro')['title']) ?></h2><p><?= html_escape($first('vision2030_intro')['body']) ?></p></div>
        <div class="ha-grid ha-grid--2">
            <?php foreach ($b['vision2030'] as $i => $x): ?>
                <article class="ha-card ag-card"><p class="ha-eyebrow"><?= ha_pe('Pillar {n}', array('n' => $i + 1)) ?></p><h3><?= html_escape($x['title']) ?></h3><p><?= html_escape($x['body']) ?></p></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php elseif ($slug === 'case-studies'): ?>
<?php /* ======================================================= CASES ========= */ ?>
<section class="ha-section">
    <div class="ha-shell">
        <p class="ha-note"><?= html_escape($first('cases_intro')['body']) ?></p>
        <?php foreach ($cases as $c): ?>
            <article class="ag-case" id="<?= html_escape($c['slug']) ?>">
                <header>
                    <p class="ha-eyebrow"><?php if ($c['illustrative']): ?><span class="ha-pill ha-pill--accent"><?= ha_pe('Illustrative case study') ?></span> <?php endif; ?><?= html_escape($c['kicker']) ?></p>
                    <h2><?= html_escape($c['title']) ?></h2>
                </header>
                <ul class="ag-case__metrics">
                    <?php foreach ($c['metrics'] as $m): ?><li><span class="ha-num"><?= html_escape($m['value']) ?></span> <?= html_escape($m['label']) ?></li><?php endforeach; ?>
                </ul>
                <dl class="ag-case__body">
                    <div><dt><?= ha_pe('Client') ?></dt><dd><?= html_escape($c['client']) ?></dd></div>
                    <div><dt><?= ha_pe('Challenge') ?></dt><dd><?= html_escape($c['challenge']) ?></dd></div>
                    <div><dt><?= ha_pe('Approach') ?></dt><dd><?= html_escape($c['approach']) ?></dd></div>
                    <div><dt><?= ha_pe('Outcome') ?></dt><dd><?= html_escape($c['results']) ?></dd></div>
                </dl>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php elseif ($slug === 'leadership'): ?>
<?php /* ======================================================= LEADERSHIP ==== */ ?>
<?php $about = array(); foreach ($b['about'] as $x) { $about[$x['code']] = $x; } ?>
<?php if (!empty($about['founders_message'])): ?>
<section class="ha-section">
    <div class="ha-shell ha-prose ag-letter">
        <p class="ha-eyebrow"><?= ha_pe('Leadership perspective') ?></p>
        <h2><?= html_escape($about['founders_message']['title']) ?></h2>
        <?php foreach (preg_split('/\R\R+/u', $about['founders_message']['body']) as $p): ?><p><?= html_escape(trim($p)) ?></p><?php endforeach; ?>
        <?php if (!empty($about['founders_quote'])): ?>
            <blockquote class="ag-quote"><p><?= html_escape($about['founders_quote']['body']) ?></p><footer><?= html_escape($about['founders_quote']['title']) ?></footer></blockquote>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
<?php foreach ($leaders as $i => $l): ?>
<section class="ha-section<?= $i % 2 === 0 ? ' ha-section--tint' : '' ?>" id="<?= html_escape($l['slug']) ?>">
    <div class="ha-shell ag-leader">
        <header class="ag-leader__head">
            <?php if ($l['photo'] !== ''): ?><img class="ag-leader__photo" src="<?= base_url($l['photo']) ?>" alt="<?= html_escape($l['name']) ?>" width="160" height="160"><?php else: ?><span class="ag-leader__mono" aria-hidden="true"><?= html_escape(mb_substr($l['name'], 0, 1)) ?></span><?php endif; ?>
            <div><p class="ha-eyebrow"><?= ha_pe('Executive leadership') ?></p><h2><?= html_escape($l['name']) ?></h2><p class="ag-leader__role"><?= html_escape($l['role']) ?></p>
                <p class="ag-leader__contact">
                    <?php if ($l['phone_digits'] !== ''): ?><a href="tel:+<?= $l['phone_digits'] ?>" dir="ltr"><?= html_escape($l['phone']) ?></a> · <a href="https://wa.me/<?= $l['phone_digits'] ?>" target="_blank" rel="noopener noreferrer"><?= ha_pe('WhatsApp') ?></a><?php endif; ?>
                    <?php if ($l['email'] !== ''): ?> · <a href="mailto:<?= html_escape($l['email']) ?>"><?= html_escape($l['email']) ?></a><?php endif; ?>
                </p>
                <?= ha_social_icons($l['social'], 'ag-leader__social', $l['name']) ?></div>
        </header>
        <div class="ag-two">
            <div class="ha-prose"><p><?= html_escape($l['bio']) ?></p></div>
            <div>
                <h3 class="ag-subhead"><?= ha_pe('Track record') ?></h3>
                <ul class="ag-ticks"><?php foreach ($l['track'] as $tr): ?><li><?= html_escape($tr) ?></li><?php endforeach; ?></ul>
                <?php if ($l['recognition']): ?>
                    <h3 class="ag-subhead"><?= ha_pe('Recognition') ?></h3>
                    <ul class="ag-ticks ag-ticks--award"><?php foreach ($l['recognition'] as $r): ?><li><?= html_escape($r) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>

<?php /* ------------------------------------------------------------ closing band */ ?>
<?php if ($closing['title'] !== ''): ?>
<section class="ha-close ag-close">
    <div class="ha-shell ha-close__inner">
        <div>
            <p class="ha-eyebrow"><?= ha_pe('Your next stage starts here') ?></p>
            <h2><?= html_escape($closing['title']) ?></h2>
            <p><?= html_escape($closing['body']) ?></p>
        </div>
        <div class="ha-close__actions">
            <a class="ha-btn ha-btn--invert" href="<?= base_url($locale . '/contact') ?>"><?= ha_pe('Start a conversation') ?></a>
        </div>
    </div>
</section>
<?php endif; ?>
