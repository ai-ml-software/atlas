<?php
$tr = function ($loc, $k) use ($p) { return isset($p['tr'][$loc][$k]) ? $p['tr'][$loc][$k] : ''; };
$seo = function ($loc, $k) use ($p) { return isset($p['seo'][$loc][$k]) ? $p['seo'][$loc][$k] : ''; };
$sc = $score[$loc]['scores'];
$tone = function ($s) { return $s >= 80 ? 'success' : ($s >= 50 ? 'warning' : 'danger'); };
$ai_entity = 'page';
$ai_id = $p['id'];
?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><a href="<?php echo hkp_url('cms'); ?>"><?php echo hkp_e('Website pages'); ?></a> · <?php echo hkp_h($p['code']); ?></div>
<h1><?php echo hkp_h($tr($loc, 'title') ?: $p['code']); ?></h1><p><?php echo hkp_badge($p['status']); ?> · <a href="<?php echo site_url('en/' . $p['slug_en']); ?>" target="_blank" rel="noopener"><?php echo hkp_e('View English'); ?></a> · <a href="<?php echo site_url('ar/' . $p['slug_ar']); ?>" target="_blank" rel="noopener"><?php echo hkp_e('View Arabic'); ?></a></p></div>
<div class="hkp-tabs" style="margin:0;border:0"><a href="?edit=en" class="<?php echo $loc === 'en' ? 'is-active' : ''; ?>">English</a><a href="?edit=ar" class="<?php echo $loc === 'ar' ? 'is-active' : ''; ?>">العربية</a></div></div>

<div class="hkp-grid hkp-grid--4" style="margin-bottom:1rem">
  <?php foreach (array('overall' => 'Overall score', 'seo' => 'SEO', 'aeo' => 'AEO (answer engines)', 'geo' => 'GEO (generative & local)') as $k => $l): ?>
  <div class="hkp-card hkp-tile"><span class="hkp-tile__label"><?php echo hkp_e($l); ?> · <?php echo strtoupper($loc); ?></span><span class="hkp-tile__value"><?php echo (int) $sc[$k]; ?><small class="hkp-small">/100</small></span><?php echo hkp_bar($sc[$k], $sc[$k] >= 80 ? 'ok' : ($sc[$k] >= 50 ? 'accent' : 'bad')); ?></div>
  <?php endforeach; ?>
</div>

<div class="hkp-grid hkp-grid--main">
<div class="hkp-grid">
  <section class="hkp-card"><h2><?php echo hkp_e('Sections'); ?> <span class="hkp-small hkp-muted">· <?php echo hkp_e('drag to reorder'); ?></span></h2>
    <?php if (!$p['sections']): ?><div class="hkp-empty"><?php echo hkp_e('No sections yet. The page shows its title and body text. Add sections below.'); ?></div><?php endif; ?>
    <ol class="hkp-sortable" data-sortable data-order-url="<?php echo hkp_url('cms/order/' . $p['id']); ?>" style="padding:0;list-style:none">
    <?php foreach ($p['sections'] as $s): $c = $s[$loc] ?: $s['en']; $open = $edit_section === (int) $s['id']; $def = $types[$s['section_type']]; ?>
      <li data-id="<?php echo (int) $s['id']; ?>" id="s<?php echo (int) $s['id']; ?>" style="display:block">
        <div class="hkp-actions" style="justify-content:space-between"><span><span class="hkp-handle" aria-hidden="true">⠿</span> <strong><?php echo hkp_e($def[0]); ?></strong> <span class="hkp-small hkp-muted"><?php echo hkp_h(mb_substr(strip_tags((string) (isset($c['heading']) ? $c['heading'] : (isset($c['quote']) ? $c['quote'] : ''))), 0, 70)); ?></span> <?php echo (int) $s['is_visible'] ? '' : hkp_badge('muted', hkp_t('Hidden')); ?></span>
          <span class="hkp-actions"><a class="hkp-btn hkp-btn--sm hkp-btn--ghost" href="?edit=<?php echo $loc; ?>&amp;section=<?php echo (int) $s['id']; ?>#s<?php echo (int) $s['id']; ?>"><?php echo hkp_e('Edit'); ?></a>
            <?php foreach (array('toggle' => (int) $s['is_visible'] ? 'Hide' : 'Show', 'duplicate' => 'Duplicate', 'delete' => 'Delete') as $act => $lab): ?><form method="post" action="<?php echo hkp_url('cms/section/' . $p['id'] . '/' . $s['id']); ?>"<?php echo $act === 'delete' ? ' data-confirm="' . hkp_e('Delete this section? A revision is kept.') . '"' : ''; ?>><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--sm hkp-btn--ghost" name="do" value="<?php echo $act; ?>"><?php echo hkp_e($lab); ?></button></form><?php endforeach; ?></span></div>
        <?php if ($open): $this->load->view('hkp/_section_form', array('s' => $s, 'type' => $s['section_type'], 'def' => $def, 'page_id' => $p['id'])); endif; ?>
      </li>
    <?php endforeach; ?></ol>
    <details style="margin-top:1rem"<?php echo !$p['sections'] ? ' open' : ''; ?>><summary class="hkp-btn"><?php echo hkp_e('Add section'); ?></summary>
      <div class="hkp-grid hkp-grid--3" style="margin-top:.8rem"><?php foreach ($types as $t => $def): ?><details class="hkp-card"><summary><strong><?php echo hkp_e($def[0]); ?></strong></summary><?php $this->load->view('hkp/_section_form', array('s' => null, 'type' => $t, 'def' => $def, 'page_id' => $p['id'])); ?></details><?php endforeach; ?></div></details>
  </section>

  <form class="hkp-card hkp-form" method="post" action="<?php echo hkp_url('cms/page_save/' . $p['id']); ?>"><?php echo ha_csrf_field(); ?>
    <h2><?php echo hkp_e('Page, SEO, AEO and GEO settings'); ?></h2>
    <?php foreach (array('en' => 'English', 'ar' => 'العربية') as $l => $lname): $dir = $l === 'ar' ? ' dir="rtl"' : ''; ?>
    <details<?php echo $l === $loc ? ' open' : ''; ?>><summary><strong><?php echo $lname; ?></strong></summary><div class="hkp-form" style="margin-top:.6rem">
      <div class="hkp-row"><div class="hkp-field"><label for="t<?php echo $l; ?>"><?php echo hkp_e('Page heading (H1)'); ?></label><input id="t<?php echo $l; ?>" class="hkp-input" name="<?php echo $l; ?>[title]" value="<?php echo hkp_h($tr($l, 'title')); ?>"<?php echo $dir; ?> required></div>
        <div class="hkp-field"><label for="st<?php echo $l; ?>"><?php echo hkp_e('Subtitle'); ?></label><input id="st<?php echo $l; ?>" class="hkp-input" name="<?php echo $l; ?>[subtitle]" value="<?php echo hkp_h($tr($l, 'subtitle')); ?>"<?php echo $dir; ?>></div></div>
      <div class="hkp-field"><label for="bd<?php echo $l; ?>"><?php echo hkp_e('Body (shown before the sections)'); ?></label><textarea id="bd<?php echo $l; ?>" class="hkp-input" rows="5" name="<?php echo $l; ?>[body]"<?php echo $dir; ?>><?php echo hkp_h($tr($l, 'body')); ?></textarea></div>
      <div class="hkp-row"><div class="hkp-field"><label for="hi<?php echo $l; ?>"><?php echo hkp_e('Hero image path'); ?></label><input id="hi<?php echo $l; ?>" class="hkp-input" name="<?php echo $l; ?>[hero_image]" value="<?php echo hkp_h($tr($l, 'hero_image')); ?>"></div>
        <div class="hkp-field"><label for="cl<?php echo $l; ?>"><?php echo hkp_e('Button label'); ?></label><input id="cl<?php echo $l; ?>" class="hkp-input" name="<?php echo $l; ?>[cta_label]" value="<?php echo hkp_h($tr($l, 'cta_label')); ?>"<?php echo $dir; ?>></div>
        <div class="hkp-field"><label for="cu<?php echo $l; ?>"><?php echo hkp_e('Button link'); ?></label><input id="cu<?php echo $l; ?>" class="hkp-input" name="<?php echo $l; ?>[cta_url]" value="<?php echo hkp_h($tr($l, 'cta_url')); ?>"></div></div>
      <div class="hkp-row"><div class="hkp-field"><label for="mt<?php echo $l; ?>"><?php echo hkp_e('Meta title'); ?> <span class="hkp-muted" data-count-for="mt<?php echo $l; ?>"></span></label><input id="mt<?php echo $l; ?>" class="hkp-input" maxlength="190" name="seo_<?php echo $l; ?>[meta_title]" value="<?php echo hkp_h($seo($l, 'meta_title')); ?>"<?php echo $dir; ?>></div>
        <div class="hkp-field"><label for="fk<?php echo $l; ?>"><?php echo hkp_e('Focus keyword'); ?></label><input id="fk<?php echo $l; ?>" class="hkp-input" name="focus_keyword_<?php echo $l; ?>" value="<?php echo hkp_h($p['focus_keyword_' . $l]); ?>"<?php echo $dir; ?>></div></div>
      <div class="hkp-field"><label for="md<?php echo $l; ?>"><?php echo hkp_e('Meta description'); ?></label><textarea id="md<?php echo $l; ?>" class="hkp-input" rows="2" maxlength="320" name="seo_<?php echo $l; ?>[meta_description]"<?php echo $dir; ?>><?php echo hkp_h($seo($l, 'meta_description')); ?></textarea></div>
      <div class="hkp-row"><div class="hkp-field"><label for="cn<?php echo $l; ?>"><?php echo hkp_e('Canonical URL (optional)'); ?></label><input id="cn<?php echo $l; ?>" class="hkp-input" name="seo_<?php echo $l; ?>[canonical_url]" value="<?php echo hkp_h($seo($l, 'canonical_url')); ?>"></div>
        <div class="hkp-field"><label for="og<?php echo $l; ?>"><?php echo hkp_e('Social image path'); ?></label><input id="og<?php echo $l; ?>" class="hkp-input" name="seo_<?php echo $l; ?>[og_image]" value="<?php echo hkp_h($seo($l, 'og_image')); ?>"></div>
        <div class="hkp-field"><label for="rb<?php echo $l; ?>"><?php echo hkp_e('Robots'); ?></label><select id="rb<?php echo $l; ?>" class="hkp-select" name="seo_<?php echo $l; ?>[robots]"><?php foreach (array('index,follow', 'noindex,follow', 'noindex,nofollow') as $r): ?><option<?php echo ($seo($l, 'robots') ?: 'index,follow') === $r ? ' selected' : ''; ?>><?php echo $r; ?></option><?php endforeach; ?></select></div></div>
    </div></details>
    <?php endforeach; ?>
    <h3><?php echo hkp_e('Structured data and location (GEO)'); ?></h3>
    <div class="hkp-row"><div class="hkp-field"><label for="sch"><?php echo hkp_e('Schema type'); ?></label><select id="sch" class="hkp-select" name="schema_type"><?php foreach (array('WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'Service', 'LocalBusiness', 'Course', 'Article', 'CollectionPage') as $s): ?><option<?php echo $p['schema_type'] === $s ? ' selected' : ''; ?>><?php echo $s; ?></option><?php endforeach; ?></select></div>
      <div class="hkp-field"><label for="gr"><?php echo hkp_e('Region code'); ?></label><input id="gr" class="hkp-input" name="geo_region" value="<?php echo hkp_h($p['geo_region']); ?>" placeholder="SA-01"></div>
      <div class="hkp-field"><label for="gp"><?php echo hkp_e('Place name'); ?></label><input id="gp" class="hkp-input" name="geo_placename" value="<?php echo hkp_h($p['geo_placename']); ?>" placeholder="Riyadh"></div>
      <div class="hkp-field"><label for="gla"><?php echo hkp_e('Latitude'); ?></label><input id="gla" class="hkp-input" name="geo_lat" value="<?php echo hkp_h($p['geo_lat']); ?>" placeholder="24.7136"></div>
      <div class="hkp-field"><label for="glo"><?php echo hkp_e('Longitude'); ?></label><input id="glo" class="hkp-input" name="geo_lng" value="<?php echo hkp_h($p['geo_lng']); ?>" placeholder="46.6753"></div></div>
    <?php if (!(int) $p['is_system']): ?><div class="hkp-row"><div class="hkp-field"><label for="se"><?php echo hkp_e('Address (English)'); ?></label><input id="se" class="hkp-input" name="slug_en" value="<?php echo hkp_h($p['slug_en']); ?>"></div><div class="hkp-field"><label for="sa"><?php echo hkp_e('Address (Arabic)'); ?></label><input id="sa" class="hkp-input" name="slug_ar" dir="rtl" value="<?php echo hkp_h($p['slug_ar']); ?>"></div></div><?php endif; ?>
    <div class="hkp-field" style="max-width:260px"><label for="ps"><?php echo hkp_e('Status'); ?></label><select id="ps" class="hkp-select" name="status"><?php foreach (array('draft', 'review', 'published', 'archived') as $s): ?><option value="<?php echo $s; ?>"<?php echo $p['status'] === $s ? ' selected' : ''; ?>><?php echo hkp_label($s); ?></option><?php endforeach; ?></select></div>
    <div><button class="hkp-btn"><?php echo hkp_e('Save page'); ?></button></div>
  </form>
</div>

<div class="hkp-grid">
  <section class="hkp-card"><h2><?php echo hkp_e('Optimisation checklist'); ?> · <?php echo strtoupper($loc); ?></h2>
    <p class="hkp-small hkp-muted"><?php echo hkp_e('{w} words · title {t} characters · description {d} characters', array('w' => $score[$loc]['words'], 't' => mb_strlen($score[$loc]['title']), 'd' => mb_strlen($score[$loc]['description']))); ?></p>
    <?php foreach (array('seo' => 'SEO', 'aeo' => 'AEO', 'geo' => 'GEO') as $g => $gl): ?><h3><?php echo $gl; ?> <?php echo hkp_badge($tone($sc[$g]), $sc[$g]); ?></h3>
      <ul class="hkp-list"><?php foreach ($score[$loc]['checks'] as $c): if ($c['group'] !== $g) continue; ?><li><span class="hkp-small"><?php echo $c['passed'] ? '✓' : '✗'; ?> <?php echo $c['passed'] ? hkp_label($c['code']) : hkp_h($c['fix']); ?></span><span class="hkp-small hkp-muted"><?php echo (int) $c['weight']; ?></span></li><?php endforeach; ?></ul>
    <?php endforeach; ?></section>
  <?php $ai_insert = '#md' . $loc; $this->load->view('hkp/_ai_panel', compact('models', 'ai_entity', 'ai_id', 'ai_insert')); ?>
  <section class="hkp-card"><h2><?php echo hkp_e('Revisions'); ?></h2><ul class="hkp-list"><?php foreach ($revisions as $r): ?><li><span class="hkp-small"><?php echo hkp_h($r['note']); ?><br><span class="hkp-muted"><?php echo hkp_h(trim($r['first_name'] . ' ' . $r['last_name'])); ?> · <?php echo hkp_date($r['created_at'], true); ?></span></span>
    <form method="post" action="<?php echo hkp_url('cms/restore/' . $p['id'] . '/' . $r['id']); ?>" data-confirm="<?php echo hkp_e('Restore this revision? The current state is kept as a revision first.'); ?>"><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--sm hkp-btn--ghost"><?php echo hkp_e('Restore'); ?></button></form></li><?php endforeach; ?></ul>
    <?php if (!$revisions): ?><p class="hkp-muted hkp-small"><?php echo hkp_e('No revisions yet.'); ?></p><?php endif; ?></section>
</div>
</div>
<script>
document.addEventListener('hkp:sorted', function (e) {
  var list = e.target; var url = list.getAttribute('data-order-url'); if (!url || !window.HKP.post) return;
  window.HKP.post(url, { order: Array.prototype.map.call(list.children, function (li) { return li.getAttribute('data-id'); }).join(',') });
});
document.querySelectorAll('[data-count-for]').forEach(function (s) {
  var i = document.getElementById(s.getAttribute('data-count-for')); var f = function () { s.textContent = '(' + i.value.length + ')'; }; i.addEventListener('input', f); f();
});
</script>
