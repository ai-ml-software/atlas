<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $page_title,
    'hero_lede'  => $lede,
    'hero_image' => ha_page_art(array('page-about', 'management')),
    'hero_alt'   => '',
)); ?>

<section class="ha-section">
    <div class="ha-shell">

        <?php if (!$credits): ?>
            <div class="ha-empty">
                <p><?= ha_pe('No image currently in use carries a licence that requires the photographer to be named.') ?></p>
            </div>
        <?php else: ?>
            <div class="ha-table-wrap">
                <table class="ha-table">
                    <caption class="ha-visually-hidden">
                        <?= ha_pe('Photographs, their authors and licences') ?>
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col"><?= ha_pe('Photograph') ?></th>
                            <th scope="col"><?= ha_pe('Author') ?></th>
                            <th scope="col"><?= ha_pe('Licence') ?></th>
                            <th scope="col"><?= ha_pe('Source') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credits as $c): ?>
                            <tr>
                                <th scope="row">
                                    <span class="ha-credit">
                                        <?= ha_image($c['file_path'], '', array('class' => 'ha-credit__thumb')) ?>
                                        <span><?= html_escape($c['original_name']) ?></span>
                                    </span>
                                </th>
                                <td><?= html_escape($c['author']) ?></td>
                                <td>
                                    <?php if (!empty($c['license_url'])): ?>
                                        <a href="<?= html_escape($c['license_url']) ?>" rel="license noopener" target="_blank">
                                            <?= html_escape($c['license']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= html_escape($c['license']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= html_escape($c['source_page']) ?>" rel="noopener" target="_blank">
                                        <?= ha_pe('Wikimedia Commons') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($uncredited > 0): ?>
                <p style="margin-top:1.4rem;color:var(--ha-ink-soft);font-size:.92rem">
                    <?= $locale === 'ar'
                        ? 'تُستخدم إضافة إلى ذلك ' . (int) $uncredited . ' صورة في الملك العام أو برخصة CC0 لا تشترط ذكر المصوّر.'
                        : 'A further ' . (int) $uncredited . ' photographs are in the public domain or under CC0, which do not require the photographer to be named.' ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <p style="margin-top:1.4rem;color:var(--ha-ink-soft);font-size:.92rem">
            <?= ha_pe('Photographs are illustrative. They do not depict hotels or employees associated with the academy.') ?>
        </p>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Photography we have the right to use'),
    'close_text'  => ha_pt('Every photograph on this site comes from Wikimedia Commons under a licence that permits commercial use, and every one is credited above.'),
    'close_primary'   => array('label' => $t['about'], 'url' => base_url($locale . '/about')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
