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
                <p><?= $locale === 'ar'
                    ? 'لا توجد صور تشترط رخصتها ذكر المصوّر حالياً.'
                    : 'No image currently in use carries a licence that requires the photographer to be named.' ?></p>
            </div>
        <?php else: ?>
            <div class="ha-table-wrap">
                <table class="ha-table">
                    <caption class="ha-visually-hidden">
                        <?= $locale === 'ar' ? 'قائمة الصور ومصادرها ورخصها' : 'Photographs, their authors and licences' ?>
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col"><?= $locale === 'ar' ? 'الصورة' : 'Photograph' ?></th>
                            <th scope="col"><?= $locale === 'ar' ? 'المصوّر' : 'Author' ?></th>
                            <th scope="col"><?= $locale === 'ar' ? 'الرخصة' : 'Licence' ?></th>
                            <th scope="col"><?= $locale === 'ar' ? 'المصدر' : 'Source' ?></th>
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
                                        <?= $locale === 'ar' ? 'ويكيميديا كومنز' : 'Wikimedia Commons' ?>
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
            <?= $locale === 'ar'
                ? 'الصور توضيحية ولا تمثل فنادق أو موظفين مرتبطين بالأكاديمية.'
                : 'Photographs are illustrative. They do not depict hotels or employees associated with the academy.' ?>
        </p>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => $locale === 'ar' ? 'صور نملك حق استخدامها' : 'Photography we have the right to use',
    'close_text'  => $locale === 'ar'
        ? 'كل صورة في هذا الموقع من ويكيميديا كومنز بترخيص يسمح بالاستخدام التجاري، وكلها موثّقة أعلاه.'
        : 'Every photograph on this site comes from Wikimedia Commons under a licence that permits commercial use, and every one is credited above.',
    'close_primary'   => array('label' => $t['about'], 'url' => base_url($locale . '/about')),
    'close_secondary' => array('label' => $t['contact'], 'url' => base_url($locale . '/contact')),
)); ?>
