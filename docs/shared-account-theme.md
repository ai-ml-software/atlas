# Shared public and account theme

Opening `/login` now keeps the Altus Gulf navigation, logo, fonts, palette, footer, language selection and mobile menu used by the public academy. Account forms have visible labels, keyboard focus, password visibility controls, inline notices and responsive layouts. Choosing a course before signing in still adds that course to My Learning after successful authentication.

## Shared components

`Ha_site_layout` supplies the public navigation, language and common interface copy. Both `academy/layout.php` and `frontend/default-new/index.php` include the same `_brand`, `_styles`, `_header`, `_footer` and `_scripts` partials. The active legacy frontend uses this shell around its page content, including information pages, catalog pages and signed-in learner pages. Home builder content remains inside the shared shell.

`academy/account.php`, `assets/academy/account.css` and `account.js` render all seven account states:

- Login.
- Registration, including conditional instructor application fields.
- Forgot password.
- Choose a new password.
- Authenticator or recovery code.
- Confirm a new device.
- Verify an email address.

The registration verification URL now has an explicit route and renders its verification form when a pending registration email exists. It previously redirected to registration. The login form retains the submitted email after incorrect credentials and clears the password. Required instructor fields are disabled when the application option is unchecked. Password confirmation uses native localized validation.

The native cookie notice is shared by public and frontend account pages, uses the existing consent key and an accessible button, and links to the existing cookie policy route. Component logo dimensions take precedence over the legacy filename-based branding rules, which otherwise enlarged the shared header on mobile.

Changed shared styles and new account/cookie assets use versioned URLs so browsers refresh the deployed files.

Role workspaces and classic backend panels retain their application navigation and use the existing shared brand tokens. The immersive lesson player, mobile app, payment provider screens and installation wizard remain distinct application surfaces. This patch does not claim that those layouts are interchangeable with a public marketing page.

## Languages and search visibility

English and Arabic account copy is included, including browser titles, labels, help, actions and asynchronous feedback. Email fields and verification codes stay LTR inside RTL documents. Account URLs retain the selected language, including links between login, registration and password recovery. The public language menu continues to offer the existing released site languages.

Account pages emit `noindex, nofollow` and a `no-referrer` policy. They do not publish language alternates for private password-reset URLs. Public academy SEO, breadcrumbs and language alternates remain in the public layout.

The new literal interface strings are registered by the existing global translation collector:

```powershell
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' index.php ha_library collect_site
```

This registers source units without staging courses or publishing languages. Pending translations and signed-media requirements remain in the existing review workflow. Global language completion still requires translation providers, human review and signed-media production; English fallback does not establish completion.

## Validation

The reproducible browser suite is `e2e/tests/account-theme.spec.ts`. It checks shared component styles, duplicate headers/footers, viewport overflow, English/Arabic document direction, browser titles, labels, required inputs, password controls, keyboard focus, text contrast, inline errors, instructor fields, reset completion, guarded verification screens, expired challenges, asynchronous feedback and cookie acceptance. Public page families and signed-in legacy learner pages are covered alongside the existing role, security and learning tests.

The test session fixtures operate only on the dedicated `atlas_hospitality_test` database. Email requests in the feedback tests are intercepted; this verifies the interface without claiming external email delivery. Device confirmation is exercised with a test session and verifies unchanged enrollment/progress fields. Password-reset fixtures restore their test password afterward.

```powershell
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' index.php ha_test prepare_browser
Set-Location e2e
node node_modules/@playwright/test/cli.js test -c playwright.isolated.config.ts
```

Use the isolated configuration for a site containing learner data. Preparing browser fixtures rebuilds only the dedicated test database; do not run it while browser tests are active. Test artifacts and private account snapshots are excluded from deployment.

Validation on 2 October 2026:

- **251 Playwright tests passed** in the full isolated desktop/mobile run, with no skips. The report is `e2e/report-isolated/index.html`; the log is `backups/theme-full-browser.txt`.
- **27 additional account-suite tests passed**, including the nine role setup logins, after extending the guarded-screen viewport checks. Log: `backups/theme-account-final-browser.txt`.
- **171 PHP tests passed, with 3,658 assertions**. Log: `backups/theme-php-tests.txt`.
- Changed/new PHP files passed syntax checks; both new JavaScript files passed syntax checks; `git diff --check` passed.
- A final guest check against the actual Laragon site opened five public/account pages after adding stylesheet versions, with no JavaScript errors, HTTP asset failures or mobile overflow.
- The local read-only learning health report found zero inconsistencies across 118 enrollments. The legacy course library still contains 40 active PDF courses and 86 drafts. This patch does not import, reseed or publish courses.

| Account state | Languages | Browser widths |
| --- | --- | --- |
| Login, registration, forgot password and legacy forgot-password alias | English, Arabic | 320, 390, 768, 1440 |
| Authenticator/recovery, device confirmation, email verification | English, Arabic | 390, 1440 |
| Choose a new password | English, Arabic | 390, 1440 |

The dedicated shell checks open 31 public/legacy page-family URLs, plus nine signed-in legacy learner pages on desktop and mobile. The full suite covers the existing learner, instructor, assessor, manager, organization administrator, executive, CMS and classic backend workflows. Screenshot review includes all seven account states. These checks do not claim verification of every country, external video, translation or third-party authentication provider.

## Deployment and rollback

Deploy the shared library, changed `Academy` and `Login` controllers, routes, both layout files, all new academy partials, Arabic site dictionary, new account/cookie assets and changed shared theme styles together. Deploy the existing course-entry and learning improvements with their dependencies if they are not already installed; see `course-start-testing.md`.

This change requires no database migration, reseed or learner-history repair. It assumes the existing academy schema and services are present. Run `ha_library collect_site` after deployment to refresh translation source coverage. Keep machine-local database configuration, test credentials, `.auth` state and backups off the server.

Refresh `/login?lang=en` and `/login?lang=ar` after deployment, exercise registration and password recovery links, open the mobile menu and search, and verify a selected course after login. Confirm existing progress and ordinary login behavior. Restore the prior application and asset files together to roll back; retain all enrollment, progress, assessment and account records.

Recommended follow-up: automate screenshot comparison for these shared components in CI, and validate configured CAPTCHA/social providers plus email delivery in staging with controlled test accounts.
