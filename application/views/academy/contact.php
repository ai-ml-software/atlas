<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php $this->load->view('academy/_hero', array(
    'hero_title' => $page ? $page['title'] : $t['contact'],
    'hero_lede'  => ($page && !empty($page['subtitle'])) ? $page['subtitle'] : '',
    'hero_image' => ha_page_art(array('page-contact', 'management')),
    'hero_alt'   => '',
)); ?>

<section class="ha-section">
    <div class="ha-shell ha-detail">
        <div>
            <?php if ($sent): ?>
                <div class="ha-result ha-result--valid" role="status">
                    <h2><?= html_escape($t['contact_thanks']) ?></h2>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= base_url($locale . '/' . ($page ? rawurlencode($page['slug']) : 'contact')) ?>" class="ha-filters" style="flex-direction:column;align-items:stretch">
                <div class="ha-field">
                    <label for="c-name"><?= html_escape($t['contact_name']) ?> *</label>
                    <input id="c-name" name="name" type="text" required
                           value="<?= html_escape(isset($old['name']) ? $old['name'] : '') ?>">
                    <?php if (isset($errors['name'])): ?>
                        <span class="ha-field__error"><?= html_escape($errors['name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="ha-field">
                    <label for="c-email"><?= html_escape($t['contact_email']) ?> *</label>
                    <input id="c-email" name="email" type="email" required
                           value="<?= html_escape(isset($old['email']) ? $old['email'] : '') ?>">
                    <?php if (isset($errors['email'])): ?>
                        <span class="ha-field__error"><?= html_escape($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="ha-field">
                    <label for="c-phone"><?= html_escape($t['contact_phone']) ?></label>
                    <input id="c-phone" name="phone" type="tel" inputmode="tel"
                           value="<?= html_escape(isset($old['phone']) ? $old['phone'] : '') ?>">
                </div>

                <div class="ha-field">
                    <label for="c-org"><?= html_escape($t['contact_org']) ?></label>
                    <input id="c-org" name="organization_name" type="text"
                           value="<?= html_escape(isset($old['organization_name']) ? $old['organization_name'] : '') ?>">
                </div>

                <div class="ha-field">
                    <label for="c-city"><?= html_escape($t['contact_city']) ?></label>
                    <input id="c-city" name="city" type="text" list="ha-cities"
                           value="<?= html_escape(isset($old['city']) ? $old['city'] : '') ?>">
                    <datalist id="ha-cities">
                        <?php foreach (array('Riyadh', 'Jeddah', 'Makkah', 'Madinah', 'Al Khobar', 'Dammam', 'AlUla', 'Abha') as $city): ?>
                            <option value="<?= html_escape($city) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="ha-field">
                    <label for="c-head"><?= html_escape($t['contact_headcount']) ?></label>
                    <input id="c-head" name="headcount" type="text" inputmode="numeric"
                           value="<?= html_escape(isset($old['headcount']) ? $old['headcount'] : '') ?>">
                </div>

                <div class="ha-field">
                    <label for="c-interest"><?= html_escape($t['contact_interest']) ?></label>
                    <select id="c-interest" name="interest">
                        <?php
                        $options = $locale === 'ar'
                            ? array('hotel_training' => 'تدريب كوادر فندقية', 'course' => 'دورة محددة',
                                    'program' => 'برنامج', 'sop' => 'إجراءات تشغيل', 'certification' => 'اعتماد', 'other' => 'أخرى')
                            : array('hotel_training' => 'Hotel workforce training', 'course' => 'A specific course',
                                    'program' => 'A program', 'sop' => 'Standard operating procedures',
                                    'certification' => 'Certification', 'other' => 'Something else');
                        foreach ($options as $value => $label): ?>
                            <option value="<?= $value ?>"<?= (isset($old['interest']) && $old['interest'] === $value) ? ' selected' : '' ?>>
                                <?= html_escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ha-field">
                    <label for="c-message"><?= html_escape($t['contact_message']) ?> *</label>
                    <textarea id="c-message" name="message" required><?= html_escape(isset($old['message']) ? $old['message'] : '') ?></textarea>
                    <?php if (isset($errors['message'])): ?>
                        <span class="ha-field__error"><?= html_escape($errors['message']) ?></span>
                    <?php endif; ?>
                </div>

                <div>
                    <button class="ha-btn" type="submit"><?= html_escape($t['contact_submit']) ?></button>
                </div>
            </form>
        </div>

        <aside class="ha-aside">
            <?php if ($page): ?>
                <div class="ha-prose" style="font-size:.95rem"><?= $page['body'] ?></div>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php $this->load->view('academy/_close', array(
    'close_title' => ha_pt('Or start from the catalogue'),
    'close_text'  => ha_pt('Browse the courses by hotel department, or check a certificate the academy has issued.'),
    'close_primary'   => array('label' => $t['courses'], 'url' => base_url($locale . '/courses')),
    'close_secondary' => array('label' => $t['verify_title'], 'url' => base_url($locale . '/verify')),
)); ?>
