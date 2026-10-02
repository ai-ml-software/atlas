<?php defined('BASEPATH') OR exit('No direct script access allowed'); require_once APPPATH.'helpers/hkp_helper.php'; ?>

<section class="ha-hero<?= strpos($course['code'],'dy-')===0 ? ' ha-course-hero' : '' ?><?= !empty($course['thumbnail']) ? ' ha-hero--image' : '' ?>">
    <?php $hero = ha_image_variant($course['thumbnail'], 'wide'); if ($hero): ?>
        <div class="ha-hero__media" aria-hidden="true"
             style="background-image:url('<?= base_url($hero) ?>')"></div>
    <?php endif; ?>
    <div class="ha-shell ha-hero__body">
        <?php if (!empty($course['category_name'])): ?>
            <p><span class="ha-pill ha-pill--accent"><?= html_escape($course['category_name']) ?></span></p>
        <?php endif; ?>
        <h1><?= html_escape($course['title']) ?></h1>
        <p class="ha-hero__lede"><?= html_escape($course['short_description']) ?></p>
    </div>
</section>

<section class="ha-section">
    <div class="ha-shell ha-detail">

        <div>
            <?php if (isset($course['translation_complete']) && !$course['translation_complete']): ?><p role="status"><?= ha_pe('This course is not yet available in {language}; the English version is shown.',array('language'=>ha_locale_name($locale))) ?></p><?php endif; ?>
            <div class="ha-prose">
                <h2><?= html_escape($t['overview']) ?></h2>
                <div><?= hkp_safe_html($course['description']) ?></div>

                <?php if (!empty($course['source_documents'])): ?>
                    <h2><?= ha_pe('Source training material') ?></h2>
                    <p><?= ha_pe('Dyafa Hotels & Resorts') ?></p>
                    <ul><?php foreach ($course['source_documents'] as $document): ?><li><a href="<?= html_escape(base_url($document['path'])) ?>"><?= html_escape($document['filename']) ?></a></li><?php endforeach; ?></ul>
                <?php endif; ?>

                <?php if ($course['outcomes']): ?>
                    <h2><?= html_escape($t['outcomes']) ?></h2>
                    <ul class="ha-check">
                        <?php foreach ($course['outcomes'] as $o): ?>
                            <li><?= html_escape($o) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($course['requirements'])): ?>
                    <h2><?= html_escape($t['requirements']) ?></h2>
                    <p><?= html_escape($course['requirements']) ?></p>
                <?php endif; ?>

                <?php if ($course['prerequisites']): ?>
                    <h2><?= html_escape($t['prerequisites']) ?></h2>
                    <ul>
                        <?php foreach ($course['prerequisites'] as $p): ?>
                            <li><a href="<?= base_url($locale . '/courses/' . rawurlencode($p['slug'])) ?>"><?= html_escape($p['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <h2 style="margin-top:2rem"><?= html_escape($t['curriculum']) ?></h2>
            <?php foreach ($course['curriculum'] as $section): ?>
                <div class="ha-module">
                    <div class="ha-module__head">
                        <span><?= html_escape($section['title']) ?></span>
                        <small><?= count($section['lessons']) ?> <?= html_escape($t['lessons']) ?>
                            · <?= (int) round($section['duration_seconds'] / 60) ?> <?= html_escape($t['minutes']) ?></small>
                    </div>
                    <ol>
                        <?php foreach ($section['lessons'] as $lesson): ?>
                            <li>
                                <span>
                                    <span class="ha-pill"><?= html_escape($lesson['lesson_type']) ?></span>
                                    <?= html_escape($lesson['title']) ?>
                                </span>
                                <span>
                                    <?php if ((int) $lesson['is_preview'] === 1): ?>
                                        <span class="ha-pill ha-pill--ok"><?= html_escape($t['preview']) ?></span>
                                    <?php endif; ?>
                                    <small><?= (int) round($lesson['duration_seconds'] / 60) ?> <?= html_escape($t['minutes']) ?></small>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endforeach; ?>

            <?php if ($course['faqs']): ?>
                <h2 style="margin-top:2rem"><?= html_escape($t['faq']) ?></h2>
                <div class="ha-faq">
                    <?php foreach ($course['faqs'] as $f): ?>
                        <details>
                            <summary><?= html_escape($f['question']) ?></summary>
                            <div class="ha-faq__body"><p><?= html_escape($f['answer']) ?></p></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="ha-aside">
            <ul class="ha-facts">
                <li><span class="k"><?= html_escape($t['level']) ?></span><span class="v"><?= html_escape(call_user_func($level_label, $course['level'])) ?></span></li>
                <li><span class="k"><?= html_escape($t['duration']) ?></span><span class="v"><?= (int) $course['duration_minutes'] ?> <?= html_escape($t['minutes']) ?></span></li>
                <?php if (!empty($course['instructor_name'])): ?>
                    <li><span class="k"><?= html_escape($t['instructor']) ?></span><span class="v"><?= html_escape($course['instructor_name']) ?></span></li>
                <?php endif; ?>
                <li><span class="k"><?= html_escape($t['certificate']) ?></span><span class="v"><?= (int) $course['certificate_eligible'] === 1
                        ? (ha_pe('Yes'))
                        : (ha_pe('No')) ?></span></li>
                <?php if ((int) $course['is_free'] === 1): ?>
                    <li><span class="k">&nbsp;</span><span class="v"><span class="ha-pill ha-pill--ok"><?= html_escape($t['free']) ?></span></span></li>
                <?php endif; ?>
            </ul>

            <?php if ($enrolled): ?>
                <a class="ha-btn" style="width:100%" data-course-start href="<?= base_url('hkp/learn/module/' . (int) $course['id']) . '?lang=' . rawurlencode($locale) ?>"><?= ha_pe('View course') ?></a>
            <?php elseif ((int) $course['is_free'] === 1): ?>
                <form method="post" action="<?= base_url('academy/start/' . (int) $course['id']) ?>">
                    <?= ha_csrf_field() ?>
                    <input type="hidden" name="locale" value="<?= html_escape($locale) ?>">
                    <button type="submit" class="ha-btn" style="width:100%" data-course-start><?= html_escape($signed_in ? $t['enrol'] : $t['sign_in_to_start']) ?></button>
                </form>
            <?php else: ?>
                <a class="ha-btn" style="width:100%" href="<?= base_url($signed_in ? 'hkp/learn' : 'login') ?>"><?= $signed_in ? ha_pe('My learning') : html_escape($t['sign_in_to_start']) ?></a>
            <?php endif; ?>

            <?php if ($course['skills']): ?>
                <h3 style="margin-top:1.4rem"><?= html_escape($t['skills_awarded']) ?></h3>
                <p>
                    <?php foreach ($course['skills'] as $s): ?>
                        <span class="ha-pill"><?= html_escape($s['name']) ?></span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>

            <?php if ($course['programs']): ?>
                <h3 style="margin-top:1.4rem"><?= html_escape($t['part_of_programs']) ?></h3>
                <ul style="padding-inline-start:1rem;font-size:.9rem">
                    <?php foreach ($course['programs'] as $p): ?>
                        <li><a href="<?= base_url($locale . '/programs/' . rawurlencode($p['slug'])) ?>"><?= html_escape($p['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php if ($course['related']): ?>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($t['related_courses']) ?></h2></div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($course['related'] as $r): ?>
                <article class="ha-card">
                    <h3><a href="<?= base_url($locale . '/courses/' . rawurlencode($r['slug'])) ?>"><?= html_escape($r['title']) ?></a></h3>
                    <p><?= html_escape($r['short_description']) ?></p>
                    <div class="ha-card__meta">
                        <span class="ha-pill"><?= (int) $r['duration_minutes'] ?> <?= html_escape($t['minutes']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('This course inside a full programme'),
    'close_text'  => ha_pt('Most teams take this as part of a department programme that ends in one verifiable certificate.'),
    'close_primary'   => array('label' => $t['programs'], 'url' => base_url($locale . '/programs')),
    'close_secondary' => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
)); ?>
