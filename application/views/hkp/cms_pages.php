<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Website'); ?></div><h1><?php echo hkp_e('Website pages'); ?></h1>
<p><?php echo hkp_e('Build public pages from sections, in English and Arabic, and optimise each for search (SEO), answer engines (AEO) and generative / local search (GEO).'); ?></p></div></div>
<div class="hkp-grid hkp-grid--main">
<section class="hkp-card"><div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php echo hkp_e('Page'); ?></th><th><?php echo hkp_e('Address'); ?></th><th class="hkp-num"><?php echo hkp_e('Sections'); ?></th><th class="hkp-num">EN</th><th class="hkp-num">AR</th><th><?php echo hkp_e('Status'); ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $p): $tone = function ($s) { return $s === null ? 'muted' : ($s >= 80 ? 'success' : ($s >= 50 ? 'warning' : 'danger')); }; ?><tr>
  <td><a href="<?php echo hkp_url('cms/page/' . $p['id']); ?>"><strong><?php echo hkp_h(hkp_pick($p, 'title') ?: $p['code']); ?></strong></a><?php if ((int) $p['is_system']): ?> <span class="hkp-small hkp-muted">· <?php echo hkp_e('system'); ?></span><?php endif; ?></td>
  <td class="hkp-small">/en/<?php echo hkp_h($p['slug_en']); ?><br><span dir="rtl">/ar/<?php echo hkp_h($p['slug_ar']); ?></span></td><td class="hkp-num"><?php echo (int) $p['sections']; ?></td>
  <td class="hkp-num"><?php echo hkp_badge($tone($p['seo_score_en']), $p['seo_score_en'] === null ? '—' : $p['seo_score_en']); ?></td><td class="hkp-num"><?php echo hkp_badge($tone($p['seo_score_ar']), $p['seo_score_ar'] === null ? '—' : $p['seo_score_ar']); ?></td>
  <td><?php echo hkp_badge($p['status']); ?></td><td><a class="hkp-btn hkp-btn--sm" href="<?php echo hkp_url('cms/page/' . $p['id']); ?>"><?php echo hkp_e('Edit'); ?></a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php if ($this->ha_auth->has('cms_pages.create')): ?><form id="new-page" class="hkp-card hkp-form" method="post" action="<?php echo hkp_url('cms/page_create'); ?>"><?php echo ha_csrf_field(); ?><h2><?php echo hkp_e('New page'); ?></h2>
  <div class="hkp-field"><label for="nt"><?php echo hkp_e('Title (English)'); ?></label><input id="nt" class="hkp-input" name="title_en" required></div>
  <div class="hkp-field"><label for="nta"><?php echo hkp_e('Title (Arabic)'); ?></label><input id="nta" class="hkp-input" name="title_ar" dir="rtl"></div>
  <div class="hkp-field"><label for="ns"><?php echo hkp_e('Address (English)'); ?></label><input id="ns" class="hkp-input" name="slug_en" placeholder="pre-opening-support"></div>
  <div><button class="hkp-btn"><?php echo hkp_e('Create page'); ?></button></div></form><?php endif; ?>
</div>
