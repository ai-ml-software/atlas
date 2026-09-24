<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<section class="ha-section">
    <div class="ha-shell" style="max-width:640px;text-align:center;padding-block:3rem">
        <p style="font-size:3rem;margin:0;color:var(--ha-ink-faint)">404</p>
        <h1><?= html_escape($t['not_found_title']) ?></h1>
        <p style="color:var(--ha-ink-soft)"><?= html_escape($t['not_found_body']) ?></p>
        <?php if (!empty($message)): ?>
            <p style="color:var(--ha-ink-soft)"><?= html_escape($message) ?></p>
        <?php endif; ?>
        <div class="ha-hero__actions" style="justify-content:center">
            <a class="ha-btn" href="<?= base_url($locale) ?>"><?= html_escape($t['back_home']) ?></a>
            <a class="ha-btn ha-btn--ghost" href="<?= base_url($locale . '/courses') ?>"><?= html_escape($t['courses']) ?></a>
        </div>
    </div>
</section>
