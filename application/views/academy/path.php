<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $path['title'],
    'hero_lede'  => $path['summary'],
)); ?>

<section class="ha-section">
    <div class="ha-shell">
        <div class="ha-prose">
            <p><?= html_escape($path['description']) ?></p>
        </div>

        <div style="margin-top:2rem">
            <?php foreach ($path['steps'] as $i => $step): ?>
                <div class="ha-step">
                    <span class="ha-step__no"><?= $i + 1 ?></span>
                    <h2 style="margin-bottom:.2rem"><?= html_escape($step['title']) ?></h2>
                    <?php if (!empty($step['job_title'])): ?>
                        <p><span class="ha-pill ha-pill--accent"><?= html_escape($step['job_title']) ?></span></p>
                    <?php endif; ?>
                    <?php if (!empty($step['description'])): ?>
                        <p><?= html_escape($step['description']) ?></p>
                    <?php endif; ?>

                    <?php if ($step['courses']): ?>
                        <div class="ha-module">
                            <div class="ha-module__head">
                                <span><?= html_escape($t['in_this_path']) ?></span>
                                <small><?= count($step['courses']) ?> <?= html_escape($t['courses']) ?></small>
                            </div>
                            <ol>
                                <?php foreach ($step['courses'] as $c): ?>
                                    <li>
                                        <span><a href="<?= base_url($locale . '/courses/' . rawurlencode($c['slug'])) ?>"><?= html_escape($c['title']) ?></a></span>
                                        <span>
                                            <span class="ha-pill"><?= html_escape(call_user_func($level_label, $c['level'])) ?></span>
                                            <small><?= (int) $c['duration_minutes'] ?> <?= html_escape($t['minutes']) ?></small>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Follow the path end to end'),
    'close_text'  => ha_pt('Each step builds on the one before it, and the certificate at the end records what was actually assessed.'),
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
)); ?>
