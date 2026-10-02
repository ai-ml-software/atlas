    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-fonts.css') ?>">
    <?php /* Brand tokens must load before the theme, which defines its roles in terms of them. */ ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/academy/academy.css') ?>">
    <?php /* Owns the header and footer; must load after the theme it overrides. */ ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-chrome.css') ?>?v=<?= filemtime(FCPATH.'assets/academy/altus-chrome.css') ?>">
    <?php /* Altus Gulf corporate pages (About, Services, Ascent, Market, Case Studies, Leadership). */ ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-corporate.css') ?>">
    <?php /* The approved reference theme (dark editorial, photographic), loaded last over everything above. */ ?>
    <link rel="stylesheet" href="<?= base_url('assets/academy/altus-theme.css') ?>?v=<?= filemtime(FCPATH.'assets/academy/altus-theme.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/academy/library.css') ?>">
    <?php if ($view === 'profile_book'): ?><link rel="stylesheet" href="<?= base_url('assets/academy/profile-book.css') ?>"><?php endif; ?>
