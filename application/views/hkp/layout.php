<?php
$loc = hkp_locale();
$brand_name = hkp_pick($brand, 'brand_name');
$show_altus = $brand['show_altus'] !== 'client' && $brand['source'] !== 'platform';
$logo = $brand['logo_path'] ? base_url(ltrim($brand['logo_path'], '/')) : base_url('logo.png');
$me_name = $me ? trim($me['first_name'] . ' ' . $me['last_name']) : '';
$initials = $me ? mb_strtoupper(mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1)) : '';
$chosen_property = (int) $this->session->userdata('hkp_property');
?><!DOCTYPE html>
<html lang="<?php echo $loc; ?>" dir="<?php echo hkp_dir(); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?php echo hkp_h($brand['color_secondary']); ?>">
<title><?php echo hkp_h($page_title ? $page_title . ' · ' : ''); ?><?php echo hkp_h($brand_name); ?></title>
<link rel="manifest" href="<?php echo hkp_url('manifest'); ?>">
<?php if ($brand['favicon_path']): ?><link rel="icon" href="<?php echo base_url(ltrim($brand['favicon_path'], '/')); ?>"><?php endif; ?>
<link rel="stylesheet" href="<?php echo base_url('assets/hkp/hkp.css?v=3'); ?>">
<style><?php echo $this->ha_tenant->css_vars($brand); ?></style>
</head>
<body class="hkp <?php echo $loc === 'ar' ? 'is-ar' : 'is-en'; ?>">
<a class="hkp-skip" href="#hkp-main"><?php echo hkp_e('Skip to content'); ?></a>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-book" viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5zM4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
    <symbol id="i-route" viewBox="0 0 24 24"><circle cx="6" cy="19" r="2.2" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="18" cy="5" r="2.2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8 19h7a3.5 3.5 0 0 0 0-7H9a3.5 3.5 0 0 1 0-7h7" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
    <symbol id="i-library" viewBox="0 0 24 24"><path d="M4 4h4v16H4zM10 4h4v16h-4zM16.5 4.5l3.8 1-3.6 14.5-3.8-1z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="4" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="m8 12 3 3 5-6" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></symbol>
    <symbol id="i-target" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></symbol>
    <symbol id="i-flag" viewBox="0 0 24 24"><path d="M5 21V4m0 0h11l-2 4 2 4H5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-award" viewBox="0 0 24 24"><circle cx="12" cy="9" r="6" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
    <symbol id="i-clipboard" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a5.5 5.5 0 0 1 3.5 6" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24"><path d="M21 3 3 10.5l7 2.5 2.5 7z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-group" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="5" cy="15" r="2.5" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="19" cy="15" r="2.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8 21a4 4 0 0 1 8 0" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3 2 20h20zM12 10v4M12 17h.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></symbol>
    <symbol id="i-gauge" viewBox="0 0 24 24"><path d="M3.5 17a9 9 0 1 1 17 0M12 13l4-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
    <symbol id="i-building" viewBox="0 0 24 24"><path d="M4 21V3h11v18M15 9h5v12M8 7h3M8 11h3M8 15h3M2 21h20" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 4.5 3.3 8 8 9 4.7-1 8-4.5 8-9V6z" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
    <symbol id="i-pulse" viewBox="0 0 24 24"><path d="M2 12h4l3-7 4 14 3-7h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 2h8l5 5v15H6zM14 2v5h5M9 13h7M9 17h7" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
    <symbol id="i-palette" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 2-2s-1-2 0-3 3 0 4-1 3-3 3-5a8 8 0 0 0-9-7z" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="7.5" cy="11" r="1.2" fill="currentColor"/><circle cx="11" cy="7.5" r="1.2" fill="currentColor"/><circle cx="15.5" cy="8.5" r="1.2" fill="currentColor"/></symbol>
    <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18" fill="none" stroke="currentColor" stroke-width="1.5"/></symbol>
    <symbol id="i-layers" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5zM3 13l9 5 9-5M3 17.5l9 5 9-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></symbol>
    <symbol id="i-compass" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="m15.5 8.5-2 5-5 2 2-5z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24"><path d="M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM13 13h8v8h-8z" fill="none" stroke="currentColor" stroke-width="1.6"/></symbol>
    <symbol id="i-pen" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v4h16v-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-cog" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></symbol>
    <symbol id="i-crown" viewBox="0 0 24 24"><path d="m3 7 4.5 4L12 5l4.5 6L21 7l-2 12H5z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m20 20-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 17V11a6 6 0 0 1 12 0v6l2 2H4zM10 21h4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-5-5 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
    <symbol id="i-play" viewBox="0 0 24 24"><path d="M7 4v16l13-8z" fill="currentColor"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 4v12m0 0-4-4m4 4 4-4M4 20h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8 10V7a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
  </defs>
</svg>

<div class="hkp-shell">
  <aside class="hkp-side" id="hkp-side" aria-label="<?php echo hkp_e('Main navigation'); ?>">
    <div class="hkp-brand">
      <img src="<?php echo hkp_h($logo); ?>" alt="" class="hkp-brand__logo" width="36" height="36">
      <div>
        <div class="hkp-brand__name"><?php echo hkp_h($brand_name); ?></div>
        <?php if ($show_altus): ?><div class="hkp-brand__by"><?php echo hkp_e('Powered by altus Hospitality Knowledge & Performance'); ?></div><?php endif; ?>
      </div>
    </div>
    <nav class="hkp-nav">
      <?php foreach ($nav as $sec): ?>
        <div class="hkp-nav__section"><?php echo hkp_h($sec['label']); ?></div>
        <?php foreach ($sec['items'] as $it): ?>
          <a href="<?php echo hkp_h($it['url']); ?>" class="hkp-nav__item<?php echo $active === $it['key'] ? ' is-active' : ''; ?>"<?php echo $active === $it['key'] ? ' aria-current="page"' : ''; ?>>
            <?php echo hkp_icon($it['icon']); ?><span><?php echo hkp_h($it['label']); ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if ($is_admin_login): ?>
        <div class="hkp-nav__section"><?php echo hkp_e('Academy LMS'); ?></div>
        <a class="hkp-nav__item" href="<?php echo site_url('admin/dashboard'); ?>"><?php echo hkp_icon('cog'); ?><span><?php echo hkp_e('Classic admin panel'); ?></span></a>
      <?php endif; ?>
    </nav>
  </aside>

  <div class="hkp-main-wrap">
    <header class="hkp-top">
      <button class="hkp-iconbtn hkp-only-mobile" type="button" data-toggle-nav aria-controls="hkp-side" aria-expanded="false" aria-label="<?php echo hkp_e('Open menu'); ?>"><?php echo hkp_icon('menu'); ?></button>
      <form class="hkp-search" action="<?php echo hkp_url('search'); ?>" method="get" role="search">
        <?php echo hkp_icon('search'); ?>
        <label class="hkp-sr" for="hkp-q"><?php echo hkp_e('Search approved knowledge'); ?></label>
        <input id="hkp-q" name="q" type="search" autocomplete="off" placeholder="<?php echo hkp_e('Search SOPs, lessons, standards…'); ?>" data-suggest="<?php echo hkp_url('search_suggest'); ?>" value="<?php echo hkp_h($this->input->get('q')); ?>">
        <div class="hkp-suggest" role="listbox" hidden></div>
      </form>
      <?php if ($properties): ?>
      <form method="post" action="<?php echo hkp_url('context'); ?>" class="hkp-context">
        <?php echo ha_csrf_field(); ?>
        <label class="hkp-sr" for="hkp-prop"><?php echo hkp_e('Property'); ?></label>
        <select id="hkp-prop" name="property_id" onchange="this.form.submit()">
          <option value="0"><?php echo hkp_e('All properties'); ?></option>
          <?php foreach ($properties as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>"<?php echo $chosen_property === (int) $p['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($p, 'name')); ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>
      <a class="hkp-lang" href="?lang=<?php echo $loc === 'ar' ? 'en' : 'ar'; ?>" lang="<?php echo $loc === 'ar' ? 'en' : 'ar'; ?>" hreflang="<?php echo $loc === 'ar' ? 'en' : 'ar'; ?>"><?php echo $loc === 'ar' ? 'English' : 'العربية'; ?></a>
      <a class="hkp-iconbtn" href="<?php echo hkp_url('notifications'); ?>" aria-label="<?php echo hkp_e('Notifications'); ?>"><?php echo hkp_icon('bell'); ?><?php if ($unread): ?><span class="hkp-dot"><?php echo (int) $unread; ?></span><?php endif; ?></a>
      <details class="hkp-user">
        <summary><span class="hkp-avatar" aria-hidden="true"><?php echo hkp_h($initials); ?></span><span class="hkp-hide-mobile"><?php echo hkp_h($me_name); ?></span></summary>
        <div class="hkp-user__menu">
          <a href="<?php echo hkp_url('profile'); ?>"><?php echo hkp_e('Profile'); ?></a>
          <a href="<?php echo site_url('account_security'); ?>"><?php echo hkp_e('Security & two-factor'); ?></a>
          <a href="<?php echo site_url('login/logout'); ?>"><?php echo hkp_icon('logout'); ?> <?php echo hkp_e('Sign out'); ?></a>
        </div>
      </details>
    </header>

    <main id="hkp-main" class="hkp-main" tabindex="-1">
      <?php if ($ok): ?><div class="hkp-flash hkp-flash--ok" role="status"><?php echo hkp_h($ok); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="hkp-flash hkp-flash--error" role="alert"><?php echo hkp_h($error); ?></div><?php endif; ?>
      <?php $this->load->view($content_view); ?>
    </main>
    <footer class="hkp-foot">
      <span><?php echo hkp_h(hkp_pick($brand, 'email_footer')); ?></span>
      <span><?php echo hkp_e('The right knowledge, to the right person, at the right time.'); ?></span>
    </footer>
  </div>
</div>
<script>window.HKP = {csrf: <?php echo json_encode(ha_csrf_token()); ?>, base: <?php echo json_encode(hkp_url()); ?>, rtl: <?php echo hkp_is_rtl() ? 'true' : 'false'; ?>, sw: <?php echo json_encode(hkp_url('sw.js')); ?>};</script>
<script src="<?php echo base_url('assets/hkp/hkp.js?v=3'); ?>" defer></script>
</body>
</html>
