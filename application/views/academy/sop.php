<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<section class="ha-hero">
    <div class="ha-shell">
        <?php if (!empty($sop['category_name'])): ?>
            <p><span class="ha-pill ha-pill--accent"><?= html_escape($sop['category_name']) ?></span></p>
        <?php endif; ?>
        <h1><?= html_escape($sop['title']) ?></h1>
        <p class="ha-hero__lede"><?= html_escape($sop['purpose']) ?></p>
    </div>
</section>

<section class="ha-section">
    <div class="ha-shell ha-detail">
        <div>
            <?php if (!empty($sop['scope'])): ?>
                <div class="ha-sop-block">
                    <h2><?= html_escape($t['scope']) ?></h2>
                    <p><?= html_escape($sop['scope']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($sop['responsibilities'])): ?>
                <div class="ha-sop-block">
                    <h2><?= html_escape($t['responsibilities']) ?></h2>
                    <p><?= html_escape($sop['responsibilities']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($sop['required_tools'])): ?>
                <div class="ha-sop-block">
                    <h2><?= html_escape($t['required_tools']) ?></h2>
                    <p><?= html_escape($sop['required_tools']) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($sop['procedure_steps']): ?>
                <div class="ha-sop-block">
                    <h2><?= html_escape($t['procedure']) ?></h2>
                    <ol class="ha-sop-steps">
                        <?php foreach ($sop['procedure_steps'] as $step): ?>
                            <li><?= html_escape($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <?php if ($sop['checklist_items']): ?>
                <div class="ha-sop-block">
                    <h2><?= html_escape($t['checklist']) ?></h2>
                    <ul class="ha-check">
                        <?php foreach ($sop['checklist_items'] as $item): ?>
                            <li><?= html_escape($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($sop['safety_notes'])): ?>
                <div class="ha-sop-block">
                    <h2><?= html_escape($t['safety_notes']) ?></h2>
                    <div class="ha-note ha-note--danger"><p><?= html_escape($sop['safety_notes']) ?></p></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($sop['quality_standard'])): ?>
                <div class="ha-sop-block">
                    <h2><?= html_escape($t['quality_standard']) ?></h2>
                    <div class="ha-note ha-note--ok"><p><?= html_escape($sop['quality_standard']) ?></p></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($sop['escalation'])): ?>
                <div class="ha-sop-block">
                    <h2><?= html_escape($t['escalation']) ?></h2>
                    <div class="ha-note"><p><?= html_escape($sop['escalation']) ?></p></div>
                </div>
            <?php endif; ?>
        </div>

        <aside class="ha-aside">
            <ul class="ha-facts">
                <li><span class="k"><?= html_escape($t['version']) ?></span><span class="v"><?= html_escape($sop['version_label']) ?></span></li>
                <?php if (!empty($sop['effective_date'])): ?>
                    <li><span class="k"><?= html_escape($t['effective_date']) ?></span><span class="v"><?= html_escape($sop['effective_date']) ?></span></li>
                <?php endif; ?>
                <?php if (!empty($sop['review_date'])): ?>
                    <li><span class="k"><?= html_escape($t['review_date']) ?></span><span class="v"><?= html_escape($sop['review_date']) ?></span></li>
                <?php endif; ?>
                <?php if (!empty($sop['department_code'])): ?>
                    <li><span class="k"><?= html_escape($t['department']) ?></span><span class="v"><?= html_escape($sop['department_code']) ?></span></li>
                <?php endif; ?>
            </ul>
        </aside>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Use this procedure with your team'),
    'close_text'  => ha_pt('Procedures are issued to a property, acknowledged by the staff it applies to, and re-issued when they change.'),
    'close_primary'   => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
