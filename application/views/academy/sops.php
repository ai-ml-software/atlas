<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $heading['title'],
    'hero_lede'  => $heading['lede'],
    'hero_image' => ha_page_art(array('topic-sop', 'safety-compliance')),
    'hero_alt'   => '',
    'hero_stat'  => array('n' => (string) count($sops), 'label' => $t['sop']),
)); ?>

<section class="ha-section">
    <div class="ha-shell">
        <?php if (!$sops): ?>
            <div class="ha-empty">
                <p><?= $locale === 'ar'
                    ? 'لا توجد إجراءات متاحة للاطلاع العام حالياً. إجراءات كل منشأة خاصة بها ولا تظهر هنا.'
                    : 'No procedures are published for public reading at the moment. Each organization\'s procedures are private to that organization and are not listed here.' ?></p>
                <p><a class="ha-btn ha-btn--ghost" href="<?= base_url($locale . '/hospitality-topics/' . rawurlencode($sop_topic_slug)) ?>">
                    <?= $locale === 'ar' ? 'اقرأ عن بنية الإجراءات' : 'Read how procedures are structured' ?>
                </a></p>
            </div>
        <?php else: ?>
            <div class="ha-grid ha-grid--3">
                <?php foreach ($sops as $s): ?>
                    <article class="ha-card">
                        <h2 class="ha-card__title"><a href="<?= base_url($locale . '/sop/' . rawurlencode($s['slug'])) ?>"><?= html_escape($s['title']) ?></a></h2>
                        <p><?= html_escape(character_limiter((string) $s['purpose'], 160)) ?></p>
                        <div class="ha-card__meta">
                            <?php if (!empty($s['category_name'])): ?>
                                <span class="ha-pill"><?= html_escape($s['category_name']) ?></span>
                            <?php endif; ?>
                            <span class="ha-pill ha-pill--accent"><?= html_escape($t['version']) ?> <?= html_escape($s['version_label']) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => $locale === 'ar' ? 'إجراءات تشغيل يلتزم بها فريقك' : 'Standard operating procedures your team will follow',
    'close_text'  => $locale === 'ar'
        ? 'كل إجراء مكتوب كما يُنفَّذ على الشِفت، بالعربية والإنجليزية، مع قائمة تحقق يعتمدها المشرف.'
        : 'Every procedure is written as it is carried out on shift, in Arabic and English, with a checklist the supervisor can sign off.',
    'close_primary'   => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
