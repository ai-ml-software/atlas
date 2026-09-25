<?php
$other = $lang === 'ar' ? 'en' : 'ar';
$path = $page === 'cases' ? 'altus/case-studies' : 'altus';
$title = $page === 'cases' ? hkp_t('Illustrative case studies') : 'Altus Advisory';
$desc = '';
if (!empty($blocks['about'])) {
    foreach ($blocks['about'] as $b) { if ($b['code'] === 'company_overview') { $desc = mb_substr(strip_tags(hkp_pick($b, 'body')), 0, 158); } }
}
$b = function ($section) use ($blocks) { return isset($blocks[$section]) ? $blocks[$section] : array(); };
$schema = array('@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Altus Advisory', 'url' => site_url($lang . '/altus'),
    'areaServed' => array('SA', 'GCC', 'MENA'), 'address' => array('@type' => 'PostalAddress', 'addressLocality' => 'Riyadh', 'addressCountry' => 'SA'),
    'founder' => array_map(function ($l) { return array('@type' => 'Person', 'name' => $l['name_en'], 'jobTitle' => $l['role_en']); }, $leaders));
?><!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo hkp_h($title); ?> · <?php echo hkp_e('Elevating Hospitality & Business Performance'); ?></title>
<meta name="description" content="<?php echo hkp_h($desc); ?>">
<link rel="canonical" href="<?php echo site_url($lang . '/' . $path); ?>">
<link rel="alternate" hreflang="<?php echo $lang; ?>" href="<?php echo site_url($lang . '/' . $path); ?>">
<link rel="alternate" hreflang="<?php echo $other; ?>" href="<?php echo site_url($other . '/' . $path); ?>">
<link rel="alternate" hreflang="x-default" href="<?php echo site_url('en/' . $path); ?>">
<meta property="og:title" content="<?php echo hkp_h($title); ?>"><meta property="og:description" content="<?php echo hkp_h($desc); ?>"><meta property="og:type" content="website"><meta property="og:locale" content="<?php echo $lang === 'ar' ? 'ar_SA' : 'en_US'; ?>">
<script type="application/ld+json"><?php echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<link rel="stylesheet" href="<?php echo hkp_asset('assets/hkp/hkp.css'); ?>">
</head>
<body class="hkp <?php echo $lang === 'ar' ? 'is-ar' : 'is-en'; ?>">
<main class="hkp-main" style="max-width:1100px;margin:0 auto">
  <div class="hkp-actions" style="justify-content:space-between;margin-bottom:1rem"><strong>ALTUS ADVISORY</strong>
    <span class="hkp-actions"><a href="<?php echo site_url($lang . '/altus'); ?>"><?php echo hkp_e('About'); ?></a><a href="<?php echo site_url($lang . '/altus/case-studies'); ?>"><?php echo hkp_e('Case studies'); ?></a><a href="<?php echo site_url('verify'); ?>"><?php echo hkp_e('Verify a certificate'); ?></a>
    <a class="hkp-lang" href="<?php echo site_url($other . '/' . $path); ?>" hreflang="<?php echo $other; ?>"><?php echo $lang === 'ar' ? 'English' : 'العربية'; ?></a></span></div>

<?php if ($page === 'about'): ?>
  <section class="hkp-card hkp-card--hero" style="margin-bottom:1rem">
    <div class="hkp-eyebrow" style="color:var(--accent)"><?php echo hkp_e('Elevating Hospitality & Business Performance'); ?></div>
    <h1><?php echo hkp_e('We do not adapt to the market. We shape it.'); ?></h1>
    <?php foreach ($b('platform') as $x): ?><p><?php echo hkp_h(hkp_pick($x, 'body')); ?></p><?php endforeach; ?>
  </section>
  <div class="hkp-grid hkp-grid--2" style="margin-bottom:1rem">
    <?php foreach ($b('about') as $x): ?><section class="hkp-card"><h2><?php echo hkp_h(hkp_pick($x, 'title')); ?></h2><p style="white-space:pre-line"><?php echo hkp_h(hkp_pick($x, 'body')); ?></p></section><?php endforeach; ?>
  </div>
  <?php foreach (array('philosophy' => 'Core philosophy', 'values' => 'Our values', 'vision2030' => 'Engineered for Saudi Vision 2030', 'market' => 'Market opportunity') as $sec => $label): if (!$b($sec)) continue; ?>
    <h2 style="margin:1.5rem 0 .75rem"><?php echo hkp_e($label); ?></h2>
    <div class="hkp-grid hkp-grid--3"><?php foreach ($b($sec) as $x): ?><section class="hkp-card"><h3><?php echo hkp_h(hkp_pick($x, 'title')); ?></h3><p class="hkp-small" style="white-space:pre-line"><?php echo hkp_h(hkp_pick($x, 'body')); ?></p></section><?php endforeach; ?></div>
  <?php endforeach; ?>
  <?php if ($services): ?><h2 style="margin:1.5rem 0 .75rem"><?php echo hkp_e('Services'); ?></h2>
    <div class="hkp-grid hkp-grid--2"><?php foreach (array('hospitality' => 'Hospitality Solutions', 'business_growth' => 'Business Growth Solutions') as $div => $lab): ?><section class="hkp-card"><h3><?php echo hkp_e($lab); ?></h3><ul><?php foreach ($services as $s): if ($s['division'] !== $div) continue; ?><li><?php echo hkp_h(hkp_pick($s, 'title')); ?></li><?php endforeach; ?></ul></section><?php endforeach; ?></div><?php endif; ?>
  <?php if ($sectors): ?><h2 style="margin:1.5rem 0 .75rem"><?php echo hkp_e('Industries we serve'); ?></h2><p><?php foreach ($sectors as $s): ?><?php echo hkp_badge('neutral', hkp_pick($s, 'name')); ?> <?php endforeach; ?></p><?php endif; ?>
  <?php if ($leaders): ?><h2 style="margin:1.5rem 0 .75rem"><?php echo hkp_e('Executive leadership'); ?></h2>
    <div class="hkp-grid hkp-grid--2"><?php foreach ($leaders as $l): ?><section class="hkp-card"><h3><?php echo hkp_h(hkp_pick($l, 'name')); ?></h3><p class="hkp-small hkp-muted"><?php echo hkp_h(hkp_pick($l, 'role')); ?></p><p class="hkp-small"><?php echo hkp_h(hkp_pick($l, 'biography')); ?></p>
      <ul class="hkp-small"><?php foreach (array_filter(explode("\n", (string) hkp_pick($l, 'track_record'))) as $t): ?><li><?php echo hkp_h($t); ?></li><?php endforeach; ?></ul></section><?php endforeach; ?></div><?php endif; ?>
<?php endif; ?>

<?php if ($page === 'cases' || $cases): ?>
  <?php $ht = $page === 'cases' ? 'h1' : 'h2'; /* the case-studies page needs its own single H1 */ ?>
  <<?php echo $ht; ?> style="margin:1.5rem 0 .75rem"><?php echo hkp_e('Illustrative case studies'); ?></<?php echo $ht; ?>>
  <div class="hkp-grid hkp-grid--2"><?php foreach ($cases as $c): ?>
    <section class="hkp-card"><?php if ((int) $c['is_illustrative']): ?><div class="hkp-eyebrow"><?php echo hkp_e('ILLUSTRATIVE CASE STUDY'); ?></div><?php endif; ?>
      <h3><?php echo hkp_h(hkp_pick($c, 'title')); ?></h3><p class="hkp-small hkp-muted"><?php echo hkp_h($c['case_type'] . ' · ' . $c['geography']); ?></p>
      <dl class="hkp-kv"><dt><?php echo hkp_e('Client profile'); ?></dt><dd><?php echo hkp_h(hkp_pick($c, 'client_profile')); ?></dd><dt><?php echo hkp_e('Challenge'); ?></dt><dd><?php echo hkp_h(hkp_pick($c, 'challenge')); ?></dd>
        <dt><?php echo hkp_e('Approach'); ?></dt><dd><?php echo hkp_h(hkp_pick($c, 'approach')); ?></dd><dt><?php echo hkp_e('Results'); ?></dt><dd><?php echo hkp_h(hkp_pick($c, 'results')); ?></dd></dl>
      <p><?php foreach ((array) json_decode((string) $c['metrics_json'], true) as $m): ?><?php echo hkp_badge('success', $m); ?> <?php endforeach; ?></p></section>
  <?php endforeach; ?></div>
  <p class="hkp-small hkp-muted"><?php echo hkp_e('Composite engagements, anonymised and clearly labelled as illustrative.'); ?></p>
<?php endif; ?>
</main></body></html>
