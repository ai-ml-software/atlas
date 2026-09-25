<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
$total_courses = 0;
$total_hours = 0;
foreach ($programs as $p) {
    $total_courses += (int) $p['course_count'];
    $total_hours += (float) $p['duration_hours'];
}
$this->load->view('academy/_hero', array(
    'hero_title' => $heading['title'],
    'hero_lede'  => $heading['lede'],
    'hero_image' => ha_page_art(array('management', 'front-office')),
    'hero_alt'   => '',
    'hero_stat'  => array('n' => (string) count($programs), 'label' => $t['programs']),
    'hero_actions' => '<a class="ha-btn" href="' . base_url($locale . '/courses') . '">'
        . html_escape($t['courses']) . '</a>'
        . '<a class="ha-btn ha-btn--ghost" href="' . base_url($locale . '/hotels') . '">'
        . html_escape($t['for_hotels']) . '</a>',
));
?>

<section class="ha-section">
    <div class="ha-shell">
        <?php if (!$programs): ?>
            <div class="ha-empty"><p><?= html_escape($t['no_results']) ?></p></div>
        <?php else: ?>
            <div class="ha-section__head">
                <h2><?= html_escape($t['programs']) ?></h2>
                <p><?= ha_pe('Each programme groups one department\'s courses into a single qualification that ends in a verifiable certificate.') ?></p>
            </div>
            <div class="ha-grid ha-grid--3">
                <?php foreach ($programs as $p): $url = base_url($locale . '/programs/' . rawurlencode($p['slug'])); ?>
                    <article class="ha-card ha-card--media">
                        <?php $cover = ha_image_variant($p['thumbnail'], 'card'); if ($cover): ?>
                            <a class="ha-card__cover" href="<?= $url ?>" tabindex="-1" aria-hidden="true">
                                <img src="<?= base_url($cover) ?>" alt="" loading="lazy" decoding="async">
                            </a>
                        <?php endif; ?>
                        <h2 class="ha-card__title"><a href="<?= $url ?>"><?= html_escape($p['title']) ?></a></h2>
                        <p><?= html_escape($p['short_description']) ?></p>
                        <div class="ha-card__meta">
                            <span class="ha-pill ha-pill--accent"><?= html_escape(call_user_func($level_label, $p['level'])) ?></span>
                            <span class="ha-pill"><?= (int) $p['course_count'] ?> <?= html_escape($t['courses']) ?></span>
                            <span class="ha-pill"><?= (float) $p['duration_hours'] ?> <?= html_escape($t['hours']) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($programs): ?>
<section class="ha-section ha-section--tint">
    <div class="ha-shell">
        <div class="ha-section__head">
            <h2><?= ha_pe('How a programme works') ?></h2>
        </div>
        <ol class="ha-steps">
            <?php
            // Described rather than counted: these are the four stages every
            // programme runs through, and none of them is a claim about
            // results the academy has not measured.
            $stages = $locale === 'ar' ? array(
                array('اختر البرنامج', 'اختر البرنامج الذي يطابق دور الموظف في الفندق.'),
                array('ادرس الدورات', 'الدورات مرتّبة بالترتيب الذي يُبنى به العمل على الشِفت.'),
                array('اجتَز التقييم', 'ينتهي كل دورة باختبار قصير يجب اجتيازه.'),
                array('تحقّق من الشهادة', 'تصدر الشهادة برمز يمكن لأي جهة التحقق منه.'),
            ) : array(
                array('Choose the programme', 'Pick the one that matches the role the employee actually works in.'),
                array('Work through the courses', 'They run in the order the job is built up on shift, not alphabetically.'),
                array('Pass the assessment', 'Every course ends in a short assessment that has to be passed.'),
                array('Verify the certificate', 'The certificate carries a code anyone can check against the register.'),
            );
            foreach ($stages as $i => $stage): ?>
                <li class="ha-steps__item">
                    <span class="ha-steps__n"><?= $i + 1 ?></span>
                    <div>
                        <h3><?= html_escape($stage[0]) ?></h3>
                        <p><?= html_escape($stage[1]) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Put a team through a full programme'),
    'close_text'  => ha_pt('Hotels buy seats, assign a programme to a department and follow completion per employee.'),
    'close_primary'   => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
