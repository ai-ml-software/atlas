<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $t['verify_title'],
    'hero_lede'  => $t['verify_help'],
    'hero_image' => ha_page_art(array('safety-compliance', 'management')),
    'hero_alt'   => '',
)); ?>

<section class="ha-section">
    <div class="ha-shell" style="max-width:720px">

        <?php if ($result !== null): ?>
            <?php $status = $result['status']; $cert = $result['certificate']; ?>
            <div class="ha-result ha-result--<?= html_escape($status) ?>" role="status">
                <h2><?= html_escape($t['verify_' . $status]) ?></h2>
                <?php if ($cert): ?>
                    <ul class="ha-facts">
                        <li><span class="k"><?= html_escape($t['certificate_no']) ?></span>
                            <span class="v"><?= html_escape($cert['certificate_no']) ?></span></li>
                        <li><span class="k"><?= html_escape($t['holder']) ?></span>
                            <span class="v"><?= html_escape($locale === 'ar' && $cert['recipient_name_ar']
                                ? $cert['recipient_name_ar'] : $cert['recipient_name_en']) ?></span></li>
                        <li><span class="k"><?= html_escape($t['subject']) ?></span>
                            <span class="v"><?= html_escape($locale === 'ar'
                                ? $cert['subject_title_ar'] : $cert['subject_title_en']) ?></span></li>
                        <li><span class="k"><?= html_escape($t['issued_on']) ?></span>
                            <span class="v"><?= html_escape(date('Y-m-d', strtotime($cert['issued_at']))) ?></span></li>
                        <?php if (!empty($cert['expires_at'])): ?>
                            <li><span class="k"><?= html_escape($t['expires_on']) ?></span>
                                <span class="v"><?= html_escape(date('Y-m-d', strtotime($cert['expires_at']))) ?></span></li>
                        <?php endif; ?>
                        <?php if ($cert['final_score'] !== null): ?>
                            <li><span class="k"><?= html_escape($t['score']) ?></span>
                                <span class="v"><?= (float) $cert['final_score'] ?>%</span></li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url($locale . '/verify') ?>" class="ha-filters" style="align-items:flex-end">
            <div class="ha-field ha-field--wide">
                <label for="v-code"><?= html_escape($t['verify_code']) ?></label>
                <input id="v-code" name="code" type="text" required
                       value="<?= html_escape((string) $submitted) ?>"
                       autocomplete="off" spellcheck="false">
            </div>
            <button class="ha-btn" type="submit"><?= html_escape($t['verify_button']) ?></button>
        </form>

        <p style="margin-top:1.5rem;color:var(--ha-ink-soft);font-size:.92rem">
            <?= ha_pe('Verification shows only the holder name, the course and the issue date. No other information about the person is disclosed.') ?>
        </p>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Every certificate can be checked'),
    'close_text'  => ha_pt('A certificate is only worth what it can prove. Each one carries a code that an employer or an auditor can verify here, without an account.'),
    'close_primary'   => array('label' => $t['for_hotels'], 'url' => base_url($locale . '/hotels')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
