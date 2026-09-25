<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<section class="ha-hero<?= !empty($topic['hero_image']) ? ' ha-hero--image' : '' ?>">
    <?php $hero = ha_image_variant($topic['hero_image'], 'wide'); if ($hero): ?>
        <div class="ha-hero__media" aria-hidden="true"
             style="background-image:url('<?= base_url($hero) ?>')"></div>
    <?php endif; ?>
    <div class="ha-shell ha-hero__body">
        <?php if (!empty($topic['city'])): ?>
            <p><span class="ha-pill ha-pill--accent"><?= html_escape($topic['city']) ?></span></p>
        <?php endif; ?>
        <h1><?= html_escape($topic['title']) ?></h1>
    </div>
</section>

<section class="ha-section">
    <div class="ha-shell ha-detail">
        <div class="ha-prose">
            <?= $topic['intro'] ?>
        </div>

        <aside class="ha-aside">
            <h3><?= html_escape($topic['topic_type'] === 'city' ? $t['in_city'] : $t['courses']) ?></h3>
            <?php if ($topic['courses']): ?>
                <ul style="padding-inline-start:1rem;font-size:.92rem">
                    <?php foreach ($topic['courses'] as $c): ?>
                        <li style="margin-bottom:.5rem">
                            <a href="<?= base_url($locale . '/courses/' . rawurlencode($c['slug'])) ?>"><?= html_escape($c['title']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a class="ha-btn ha-btn--ghost" style="width:100%" href="<?= base_url($locale . '/courses') ?>">
                <?= html_escape($t['browse_all']) ?>
            </a>
        </aside>
    </div>
</section>

<?php if ($topic['faqs']): ?>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($t['faq']) ?></h2></div>
        <div class="ha-faq">
            <?php foreach ($topic['faqs'] as $f): ?>
                <details>
                    <summary><?= html_escape($f['question']) ?></summary>
                    <div class="ha-faq__body"><p><?= html_escape($f['answer']) ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($topic['articles']): ?>
<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($t['articles']) ?></h2></div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($topic['articles'] as $a): ?>
                <article class="ha-card">
                    <h3><a href="<?= base_url($locale . '/articles/' . rawurlencode($a['slug'])) ?>"><?= html_escape($a['title']) ?></a></h3>
                    <p><?= html_escape($a['excerpt']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Training that fits this market'),
    'close_text'  => ha_pt('Browse the courses that match this topic, or talk to us about what your property needs.'),
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
