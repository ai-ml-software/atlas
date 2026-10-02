<?php if (get_frontend_settings('cookie_status') === 'active'): ?>
<aside class="ha-cookie" data-site-cookie aria-label="<?= html_escape(ha_pt('Cookie policy')) ?>" hidden>
    <p><?= html_escape(ha_pt(trim(strip_tags((string)get_frontend_settings('cookie_note'))))) ?> <a href="<?= site_url('home/cookie_policy').'?lang='.rawurlencode($locale) ?>"><?= html_escape(ha_pt('Cookie policy')) ?></a></p>
    <button class="ha-btn ha-btn--primary" type="button" data-cookie-accept><?= html_escape(ha_pt('Accept')) ?></button>
</aside>
<?php endif; ?>
