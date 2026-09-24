<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<!-- Hero ------------------------------------------------------------------ -->
<section class="ha-hero ha-hero--image ha-hero--lead">
    <?php $hero = ha_image_variant($page['hero_image'], 'wide'); if ($hero): ?>
        <div class="ha-hero__media" aria-hidden="true"
             style="background-image:url('<?= base_url($hero) ?>')"></div>
    <?php endif; ?>
    <div class="ha-shell ha-hero__body">
        <p class="ha-eyebrow"><?= $locale === 'ar' ? 'السعودية · بالعربية والإنجليزية' : 'Saudi Arabia · Arabic and English' ?></p>
        <h1><?= html_escape($page['title']) ?></h1>
        <p class="ha-hero__lede"><?= html_escape($page['subtitle']) ?></p>
        <div class="ha-hero__actions">
            <a class="ha-btn" href="<?= base_url($locale . '/' . ($page['cta_url'] ?: 'courses')) ?>">
                <?= html_escape($page['cta_label']) ?>
            </a>
            <a class="ha-btn ha-btn--ghost" href="<?= base_url($locale . '/hotels') ?>">
                <?= html_escape($t['for_hotels']) ?>
            </a>
        </div>
    </div>
</section>

<!-- What the catalogue actually holds. Every number is a live count. ------- -->
<section class="ha-factband" aria-label="<?= $locale === 'ar' ? 'حجم المحتوى' : 'What the catalogue holds' ?>">
    <div class="ha-shell">
        <ul class="ha-factband__list">
            <?php
            $fact_labels = $locale === 'ar'
                ? array('courses' => 'دورة', 'lessons' => 'درساً', 'hours' => 'ساعة تدريب',
                        'procedures' => 'إجراء تشغيل', 'roles' => 'مسمى وظيفي', 'cities' => 'مدن سعودية')
                : array('courses' => 'courses', 'lessons' => 'lessons', 'hours' => 'hours of training',
                        'procedures' => 'procedures', 'roles' => 'job roles', 'cities' => 'Saudi cities');
            foreach ($fact_labels as $key => $label): ?>
                <li>
                    <span class="ha-factband__n"><?= (int) $facts[$key] ?></span>
                    <span class="ha-factband__l"><?= html_escape($label) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="ha-factband__note">
            <?= $locale === 'ar'
                ? 'أرقام محسوبة من الكتالوج المنشور الآن، لا تقديرات تسويقية.'
                : 'Counted from the published catalogue as this page loaded. Not marketing estimates.' ?>
        </p>
    </div>
</section>

<!-- Two audiences, kept apart ---------------------------------------------- -->
<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-split">
            <?php foreach ($audiences as $a): ?>
                <article class="ha-split__card">
                    <p class="ha-eyebrow"><?= html_escape($a['eyebrow']) ?></p>
                    <h2><?= html_escape($a['title']) ?></h2>
                    <p><?= html_escape($a['body']) ?></p>
                    <a class="ha-btn ha-btn--ghost" href="<?= base_url($locale . '/' . $a['url']) ?>">
                        <?= html_escape($a['cta']) ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How it works: the operating loop --------------------------------------- -->
<section class="ha-section ha-section--tint" id="how-it-works">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= $locale === 'ar' ? 'كيف يعمل' : 'How it works' ?></h2>
            <p><?= $locale === 'ar'
                ? 'من إسناد التدريب إلى شهادة يمكن لطرف ثالث التحقق منها، في أربع خطوات.'
                : 'From assigning training to a certificate a third party can check, in four steps.' ?></p>
        </div>
        <ol class="ha-steps">
            <?php foreach ($steps as $i => $step): ?>
                <li class="ha-steps__item">
                    <span class="ha-steps__n" aria-hidden="true"><?= $i + 1 ?></span>
                    <h3><?= html_escape($step[0]) ?></h3>
                    <p><?= html_escape($step[1]) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<!-- The written argument ---------------------------------------------------- -->
<section class="ha-section">
    <div class="ha-shell ha-prose">
        <?= $page['body'] ?>
    </div>
</section>

<!-- Pick your department ---------------------------------------------------- -->
<?php if ($departments): ?>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= $locale === 'ar' ? 'ابدأ من قسمك' : 'Start from your department' ?></h2>
            <p><?= $locale === 'ar'
                ? 'كل قسم له مجموعته الخاصة. اختر قسمك لترى ما يخصه.'
                : 'Each department has its own set. Pick yours to see what belongs to it.' ?></p>
        </div>
        <ul class="ha-chips">
            <?php foreach ($departments as $d): ?>
                <li>
                    <a class="ha-chip" href="<?= base_url($locale . '/courses?department=' . rawurlencode($d['code'])) ?>">
                        <?= html_escape($d['name']) ?>
                        <span class="ha-chip__n"><?= (int) $d['course_count'] ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- Newest courses ---------------------------------------------------------- -->
<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= html_escape($t['courses']) ?></h2>
            <a href="<?= base_url($locale . '/courses') ?>"><?= html_escape($t['browse_all']) ?></a>
        </div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($courses as $c): ?>
                <article class="ha-card ha-card--media">
                    <?= ha_media_figure($c['thumbnail'], ha_image_alt($c['thumbnail'], $locale)) ?>
                    <h3><a href="<?= base_url($locale . '/courses/' . rawurlencode($c['slug'])) ?>"><?= html_escape($c['title']) ?></a></h3>
                    <p><?= html_escape($c['short_description']) ?></p>
                    <div class="ha-card__meta">
                        <span class="ha-pill ha-pill--accent"><?= html_escape(call_user_func($level_label, $c['level'])) ?></span>
                        <span class="ha-pill"><?= (int) $c['duration_minutes'] ?> <?= html_escape($t['minutes']) ?></span>
                        <?php if ((int) $c['is_free'] === 1): ?>
                            <span class="ha-pill ha-pill--ok"><?= html_escape($t['free']) ?></span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Bilingual proof: the same lesson, both languages, side by side ---------- -->
<?php if ($bilingual): ?>
<section class="ha-section ha-bilingual" id="bilingual">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= $locale === 'ar' ? 'العربية ليست ترجمة لاحقة' : 'Arabic is not an afterthought' ?></h2>
            <p><?= $locale === 'ar'
                ? 'هذا درس واحد حقيقي من الكتالوج، معروضاً بلغتيه. كل نسخة مكتوبة على حدة، والعربية معروضة من اليمين إلى اليسار.'
                : 'One real lesson from the catalogue, shown in both languages. Each version is written separately, and the Arabic is laid out right to left.' ?></p>
        </div>

        <div class="ha-compare">
            <article class="ha-compare__side" lang="en" dir="ltr">
                <p class="ha-compare__tag">English</p>
                <h3><?= html_escape($bilingual['en']['title']) ?></h3>
                <p><?= html_escape($bilingual['en']['objective']) ?></p>
            </article>
            <article class="ha-compare__side ha-compare__side--ar" lang="ar" dir="rtl">
                <p class="ha-compare__tag">العربية</p>
                <h3><?= html_escape($bilingual['ar']['title']) ?></h3>
                <p><?= html_escape($bilingual['ar']['objective']) ?></p>
            </article>
        </div>

        <p class="ha-compare__foot">
            <a href="<?= base_url($locale . '/courses/' . rawurlencode($bilingual['course']['slug_' . $locale])) ?>">
                <?= $locale === 'ar' ? 'افتح الدورة كاملة' : 'Open the full course' ?>
            </a>
        </p>
    </div>
</section>
<?php endif; ?>

<!-- The SOP hub, shown rather than described -------------------------------- -->
<?php if ($procedure): ?>
<section class="ha-section ha-section--tint" id="procedures">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= $locale === 'ar' ? 'إجراءات لها إصدارات، لا ملفات في مجلد' : 'Procedures with versions, not files in a folder' ?></h2>
            <p><?= $locale === 'ar'
                ? 'كل إجراء وثيقة لها إصدار وتاريخ سريان وتاريخ مراجعة، ويُطلب من المعنيين الإقرار بكل إصدار جديد.'
                : 'Each procedure is a versioned document with an effective date and a review date, and everyone it applies to is asked to acknowledge each new version.' ?></p>
        </div>

        <div class="ha-sop-demo">
            <div class="ha-sop-demo__doc">
                <div class="ha-sop-demo__head">
                    <h3><?= html_escape($procedure['title']) ?></h3>
                    <div class="ha-sop-demo__meta">
                        <span class="ha-pill ha-pill--accent"><?= html_escape($t['version']) ?> <?= html_escape($procedure['version_label']) ?></span>
                        <?php if ($procedure['effective_date']): ?>
                            <span class="ha-pill"><?= html_escape($t['effective_date']) ?>: <?= html_escape($procedure['effective_date']) ?></span>
                        <?php endif; ?>
                        <?php if ($procedure['review_date']): ?>
                            <span class="ha-pill"><?= html_escape($t['review_date']) ?>: <?= html_escape($procedure['review_date']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($procedure['steps']): ?>
                    <ol class="ha-sop-steps">
                        <?php foreach (array_slice($procedure['steps'], 0, 4) as $step): ?>
                            <li><?= html_escape($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                    <p class="ha-sop-demo__more">
                        <?= $locale === 'ar'
                            ? 'و' . (count($procedure['steps']) - 4) . ' خطوات أخرى، مع قائمة تحقق وملاحظات سلامة ومعيار جودة ومسار تصعيد.'
                            : 'Plus ' . (count($procedure['steps']) - 4) . ' more steps, a checklist, safety notes, a quality standard and an escalation route.' ?>
                    </p>
                <?php endif; ?>
            </div>

            <aside class="ha-sop-demo__side">
                <h3><?= $locale === 'ar' ? 'ما يراه المدير' : 'What a manager sees' ?></h3>
                <ul class="ha-ack">
                    <li><span><?= $locale === 'ar' ? 'مطلوب منهم' : 'Required' ?></span></li>
                    <li><span><?= $locale === 'ar' ? 'أقرّوا بالإصدار الحالي' : 'Acknowledged the current version' ?></span></li>
                    <li><span class="ha-ack__miss"><?= $locale === 'ar' ? 'متبقّون، بالاسم' : 'Still missing, by name' ?></span></li>
                </ul>
                <p class="ha-ack__note">
                    <?= $locale === 'ar'
                        ? 'يعرض التقرير هذه الأرقام لكل قسم وفندق. ويسجل كل إقرار الشخص والإصدار الدقيق والتوقيت، وهذا ما يجعله دليلاً.'
                        : 'The report gives these three figures per department and per property. Each acknowledgement records the person, the exact version and the timestamp, which is what makes it evidence.' ?>
                </p>
                <a class="ha-btn ha-btn--ghost" href="<?= base_url($locale . '/sop') ?>">
                    <?= html_escape($t['sop']) ?>
                </a>
            </aside>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Programmes and paths ---------------------------------------------------- -->
<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= html_escape($t['programs']) ?></h2>
            <a href="<?= base_url($locale . '/programs') ?>"><?= html_escape($t['browse_all']) ?></a>
        </div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($programs as $p): ?>
                <article class="ha-card">
                    <h3><a href="<?= base_url($locale . '/programs/' . rawurlencode($p['slug'])) ?>"><?= html_escape($p['title']) ?></a></h3>
                    <p><?= html_escape($p['short_description']) ?></p>
                    <div class="ha-card__meta">
                        <span class="ha-pill ha-pill--accent"><?= html_escape(call_user_func($level_label, $p['level'])) ?></span>
                        <span class="ha-pill"><?= (int) $p['course_count'] ?> <?= html_escape($t['courses']) ?></span>
                        <span class="ha-pill"><?= (float) $p['duration_hours'] ?> <?= html_escape($t['hours']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="ha-section__head" style="margin-top:2.6rem">
            <h2><?= html_escape($t['learning_paths']) ?></h2>
            <a href="<?= base_url($locale . '/learning-paths') ?>"><?= html_escape($t['browse_all']) ?></a>
        </div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($paths as $p): ?>
                <article class="ha-card">
                    <h3><a href="<?= base_url($locale . '/learning-paths/' . rawurlencode($p['slug'])) ?>"><?= html_escape($p['title']) ?></a></h3>
                    <p><?= html_escape($p['summary']) ?></p>
                    <div class="ha-card__meta">
                        <span class="ha-pill"><?= (int) $p['step_count'] ?> <?= html_escape($t['steps']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Cities: the geographic surface ------------------------------------------ -->
<?php if ($cities): ?>
<section class="ha-section ha-section--tint" id="cities">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= $locale === 'ar' ? 'حسب المدينة' : 'By city' ?></h2>
            <p><?= $locale === 'ar'
                ? 'يختلف تركيز التدريب باختلاف السوق. لكل مدينة صفحتها بما يخصها فعلاً.'
                : 'Training emphasis changes with the market. Each city has its own page describing what actually differs there.' ?></p>
        </div>
        <ul class="ha-cities">
            <?php foreach ($cities as $city): ?>
                <li>
                    <a class="ha-city" href="<?= base_url($locale . '/hospitality-topics/' . rawurlencode($city['slug'])) ?>">
                        <?= ha_media_figure($city['hero_image'], ha_image_alt($city['hero_image'], $locale), array('modifier' => 'ha-media--city')) ?>
                        <span class="ha-city__name"><?= html_escape($city['city']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- Verify a certificate ---------------------------------------------------- -->
<section class="ha-section ha-verifyband" id="verify">
    <div class="ha-shell">
        <div class="ha-verifyband__inner">
            <div>
                <h2><?= $locale === 'ar' ? 'شهادة يمكن لأي طرف التحقق منها' : 'A certificate anyone can check' ?></h2>
                <p><?= $locale === 'ar'
                    ? 'كل شهادة تحمل رمز تحقق. أدخله وسترى فوراً إن كانت سارية أو منتهية أو ملغاة. لا يلزم حساب.'
                    : 'Every certificate carries a verification code. Enter it and the answer comes back immediately: valid, expired or revoked. No account needed.' ?></p>
            </div>
            <form class="ha-verifyband__form" method="post" action="<?= base_url($locale . '/verify') ?>">
                <label class="ha-visually-hidden" for="ha-home-verify"><?= html_escape($t['verify_code']) ?></label>
                <input id="ha-home-verify" name="code" type="text" required
                       placeholder="<?= html_escape($t['verify_code']) ?>"
                       autocomplete="off" spellcheck="false">
                <button class="ha-btn" type="submit"><?= html_escape($t['verify_button']) ?></button>
            </form>
        </div>
    </div>
</section>

<!-- Questions, answered directly -------------------------------------------- -->
<?php if ($faqs): ?>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head"><h2><?= html_escape($t['faq']) ?></h2></div>
        <div class="ha-faq">
            <?php foreach ($faqs as $f): ?>
                <details>
                    <summary><?= html_escape($f['question']) ?></summary>
                    <div class="ha-faq__body"><p><?= html_escape($f['answer']) ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Writing ----------------------------------------------------------------- -->
<?php if ($articles): ?>
<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= html_escape($t['articles']) ?></h2>
            <a href="<?= base_url($locale . '/articles') ?>"><?= html_escape($t['browse_all']) ?></a>
        </div>
        <div class="ha-grid ha-grid--3">
            <?php foreach ($articles as $a): ?>
                <article class="ha-card ha-card--media">
                    <?= ha_media_figure($a['cover_image'], ha_image_alt($a['cover_image'], $locale)) ?>
                    <h3><a href="<?= base_url($locale . '/articles/' . rawurlencode($a['slug'])) ?>"><?= html_escape($a['title']) ?></a></h3>
                    <p><?= html_escape($a['excerpt']) ?></p>
                    <div class="ha-card__meta">
                        <span class="ha-pill"><?= (int) $a['reading_minutes'] ?> <?= html_escape($t['read_time']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- The close: it resolves, it does not trail off --------------------------- -->
<section class="ha-close">
    <div class="ha-shell ha-close__inner">
        <h2><?= $locale === 'ar'
            ? 'أخبرنا بفندقك، ونعود إليك بهيكل جاهز'
            : 'Tell us about your property, and we will come back with a structure' ?></h2>
        <p><?= $locale === 'ar'
            ? 'الفنادق والأقسام وعدد الموظفين تقريباً. سنعود والهيكل مُعد والتدريب الإلزامي مُسنَد، فيكون أول ما يراه فريقك قائمته الخاصة لا كتالوجاً فارغاً.'
            : 'The properties, the departments and roughly how many people in each. We come back with the structure set up and the mandatory training already assigned, so the first thing your team sees is their own list rather than an empty catalogue.' ?></p>
        <div class="ha-close__actions">
            <a class="ha-btn ha-btn--invert" href="<?= base_url($locale . '/contact') ?>">
                <?= html_escape($t['contact']) ?>
            </a>
            <a class="ha-btn ha-btn--outline" href="<?= base_url($locale . '/courses') ?>">
                <?= html_escape($t['browse_all']) ?>
            </a>
        </div>
    </div>
</section>
