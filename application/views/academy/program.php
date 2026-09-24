<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $program['title'],
    'hero_lede'  => $program['short_description'],
)); ?>

<section class="ha-section">
    <div class="ha-shell ha-detail">
        <div>
            <div class="ha-prose">
                <h2><?= html_escape($t['overview']) ?></h2>
                <p><?= html_escape($program['description']) ?></p>
            </div>

            <h2 style="margin-top:2rem"><?= html_escape($t['courses']) ?></h2>
            <div class="ha-module">
                <div class="ha-module__head">
                    <span><?= html_escape($t['curriculum']) ?></span>
                    <small><?= count($program['courses']) ?> <?= html_escape($t['courses']) ?></small>
                </div>
                <ol>
                    <?php foreach ($program['courses'] as $c): ?>
                        <li>
                            <span>
                                <a href="<?= base_url($locale . '/courses/' . rawurlencode($c['slug'])) ?>"><?= html_escape($c['title']) ?></a>
                            </span>
                            <span>
                                <span class="ha-pill"><?= html_escape(call_user_func($level_label, $c['level'])) ?></span>
                                <small><?= (int) $c['duration_minutes'] ?> <?= html_escape($t['minutes']) ?></small>
                                <?php if ((int) $c['is_mandatory'] === 1): ?>
                                    <span class="ha-pill ha-pill--accent"><?= html_escape($t['mandatory']) ?></span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>

        <aside class="ha-aside">
            <ul class="ha-facts">
                <li><span class="k"><?= html_escape($t['level']) ?></span><span class="v"><?= html_escape(call_user_func($level_label, $program['level'])) ?></span></li>
                <li><span class="k"><?= html_escape($t['duration']) ?></span><span class="v"><?= (float) $program['duration_hours'] ?> <?= html_escape($t['hours']) ?></span></li>
                <li><span class="k"><?= html_escape($t['courses']) ?></span><span class="v"><?= count($program['courses']) ?></span></li>
            </ul>
            <a class="ha-btn" style="width:100%" href="<?= base_url('login') ?>"><?= html_escape($t['sign_in_to_start']) ?></a>
        </aside>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => $locale === 'ar' ? 'أدخل فريقك في هذا البرنامج' : 'Put a team through this programme',
    'close_text'  => $locale === 'ar'
        ? 'تشتري الفنادق مقاعد وتسند البرنامج إلى القسم، وتتابع الإكمال لكل موظف.'
        : 'Hotels buy seats, assign the programme to a department and follow completion per employee.',
    'close_primary'   => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
