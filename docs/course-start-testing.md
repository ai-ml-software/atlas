# Course sign-in and My Learning verification

The public course button previously linked directly to `/login`. It discarded the chosen course, and login ignored the workspace's `hkp_return` link. Signing in therefore opened the dashboard without adding the course to My Learning.

## Result

A free public course now has a CSRF-protected POST start action. For guests, a session-bound selection expires after 30 minutes and survives an incorrect password, device confirmation, and two-factor login. Only successful authentication completes enrollment. Course availability and learner permissions are checked again at that point. Course GET requests and ordinary login never enroll anyone.

Signed-in learners can start the selected course directly. Existing enrollment IDs, progress, attempts, completion status, and assigned deadlines are retained. Enrolled courses show **View course**. My Learning includes a **Courses** link.

Workspace return links retain their query and selected language. They are consumed once and restricted to the current installation; external links, authentication loops, traversal paths, and header controls are rejected. Logout clears pending course choices and authentication factors.

New public registrations receive a learner profile and a self-scoped learner grant, with no organization, property, or administration privileges. Inactive users and suspended profiles stay signed out rather than looping between login and the workspace. The login footer also skips missing category records instead of emitting PHP warnings and broken links.

## Local account verification

Verified using the real login form for `omar.learner@dyafagroup.sa` on 2 October 2026. Active Listening appeared exactly once in My Learning. All eight previous enrollment records, existing lesson progress, and quiz attempt records matched their before-test snapshots. No lesson was completed and no quiz was submitted on this account.

Evidence remains in the ignored local `backups` directory:

- `learning-reproduction.json` and `learning-before.png`: original failure.
- `learning-fix-verified.json` and `learning-after.png`: successful verification.
- `omar-learning-before.json`: private comparison snapshot; do not publish or deploy it.

## Automated tests

Final validation on 2 October 2026: **160 PHP tests passed, with 3,623 assertions**. All **224 Playwright test cases** were validated: 222 passed in the full run, and the two remaining checks passed on rerun after fixing isolated fixture differences (public credit-file routing and the configured footer brand). All twelve course-entry browser cases passed in the full run. Nine role logins also passed again during the rerun. A further twelve public desktop/mobile checks passed against the actual local site, covering English, Arabic, Hindi fallback, pending-language 404s, metadata, and page width. PHP syntax checks and `git diff --check` passed.

The dedicated Playwright configuration uses a loopback PHP server on port 8099 and **atlas_hospitality_test**, not the working database. It verifies the server's test-database header before mutating course-start fixtures. Saved role sessions and reports are separate from ordinary local E2E runs.

The course-entry browser tests cover:

1. Guest selection, incorrect password, successful login, and My Learning enrollment.
2. Signed-in course start, repeated navigation, and retained progress.
3. Course deep links and Arabic locale across login, without unintended enrollment.
4. Ordinary login and logout after an abandoned course choice.
5. Arabic layouts at desktop and phone widths.
6. Rejection of GET, missing CSRF, and forged course IDs.
7. Quiz failure, retry, passing in Arabic, progress, and sequential unlocking.
8. Two-factor authentication before enrollment.
9. Deactivated accounts and suspended profiles without redirect loops.
10. A newly registered learner's course access and refusal of administration access.

PHP coverage also checks unavailable, draft, paid, scoped, changed, and expired course choices; stable enrollment identities; saved return links; and restricted registration grants. The existing suite additionally covers the 75% threshold, ten quiz attempts, translated answer identities, source import stability, tenant isolation, CMS, RBAC, and discovery metadata.

The library bridge's repeat-import test now compares stable record identities in a defined order. Its `updated_at` timestamps are expected to change when synchronization runs, so they are excluded from that identity comparison.

Run from the repository root using the locally installed PHP binary:

```powershell
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' index.php ha_test run
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' index.php ha_test prepare_browser
Set-Location e2e
node node_modules/@playwright/test/cli.js test -c playwright.isolated.config.ts
```

`prepare_browser` rebuilds only `atlas_hospitality_test`, seeds a fresh dataset, and provides an available PDF course, its legacy player mirror, and a public registration fixture. It does not send registration email. Do not rebuild this database while its browser tests are running.

For the course-entry tests alone, after preparing fixtures:

```powershell
node node_modules/@playwright/test/cli.js test -c playwright.isolated.config.ts course-entry.spec.ts --project=desktop --no-deps
```

The ordinary `playwright.config.ts` setup resets device lists and other fixtures in its configured local database. Use the isolated configuration for regression testing against a site with real learner progress.

The full-run report is retained at `backups/learning-full-browser-report-20261002/index.html`. The successful rerun report is `e2e/report-isolated/index.html`. AI authoring tests use the local mock provider; they do not verify a production provider connection. Registration provisioning is exercised without testing external email delivery. This run does not establish global translation completion or live external video availability.

## Deployment and rollback

Deploy the course-entry library and changed application controller, model, helper-controller, and view files together. This fix requires no database migration or course reseed. Keep the existing course publication states and identities.

The database override is limited to PHP's development server in the testing environment with the exact router-defined test database. Apache and production requests cannot enable it. Test commands and the test router should run only on a workstation or isolated CI environment.

After deployment, select an available free course as a guest, sign in, verify that it appears in My Learning, and revisit it while signed in. Confirm that existing progress remains intact and that a plain login creates no enrollment.

To roll back, restore the changed application files from the preceding revision. Retain enrollments and new learner profiles created through the corrected flow; deleting them would discard legitimate learner data. Test tooling and reports do not need deployment.

## Learning improvements added

Dashboard and My Learning now include a **Continue learning** card. It selects an active, available course using saved learning activity or its enrollment creation time, with a stable ID tie-breaker. Updating an enrollment without learning does not promote it. Completed, expired, cancelled, exempted, scoped-out, and unavailable courses cannot become resume suggestions. Prerequisites, checkpoint sequence, and scheduled lesson releases are checked before offering a lesson link. When lessons are done but assessments remain, the card links to the course assessment overview.

The card shows the next unfinished lesson and its saved native video position. The native player restores positive positions, including positions under six seconds, and bounds them to the current media duration. Time tracking omits video position when media is unavailable, embedded, or has not loaded metadata, preserving the saved position while the learner uses the written guide. External iframe players do not supply playback positions through this integration.

Self-selected and assigned courses have distinct labels. Unavailable course rows retain progress and explain their availability without linking to a 404 or offering Start. Existing mixed timezone timestamps are bounded to enrollment creation time when sorting; this avoids an earlier local-time activity timestamp pushing a newly selected course below older enrollments. It does not rewrite historical timestamps or claim to normalize all legacy timezone differences.

System-scoped administrators can review **Learning progress health** at `/hkp/admin/system`. The read-only audit checks progress ownership/course associations, percentage bounds, stored progress totals against published lessons, quiz evidence for completed lessons, and mandatory lesson/assessment evidence for completed courses. Optional unfinished lessons do not invalidate completion. Reports list exact enrollment, progress, lesson, and assessment identities. Changes to curriculum can legitimately require review; the audit never repairs history automatically. Unavailable-course enrollments are reported separately rather than treated as inconsistent data.

```powershell
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' index.php hkp_cli learning_health 200
```

This command prints JSON and exits 1 if review findings exist, or 0 when the checks pass. Each check includes its full count and up to the requested number of records (1–5,000), with an explicit `truncated` flag. Administrator views show up to 25 records per check. The diagnostic scope is workspace learning; it does not infer equivalence or synchronize progress between the legacy player and workspace.

English and Arabic interface copy is included. Register shared strings for other languages without staging course imports:

```powershell
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' index.php ha_library collect_site
```

The new strings were registered locally. Existing incomplete languages remain pending in the translation workflow; this change does not establish global translation completion.

The additions need no schema migration or reseed. Deploy `Ha_learning`, `Ha_learning_health`, `Hkp`, `Hkp_admin`, `Hkp_cli`, `Ha_library`, the changed workspace views including `learning_continue.php`, the Arabic dictionary, and `assets/hkp/hkp.js` together. The versioned asset URL refreshes the browser's JavaScript. Rollback consists of restoring those files; retain all learner records.

Read-only local verification for Omar confirmed nine enrollments, one Active Listening entry, eight retained unavailable assignments, and Active Listening as the continuation suggestion. Enrollment, progress, and attempt rows remained byte-equivalent before and after the new reads. The main audit found zero inconsistencies among 117 enrollments and reported 115 unavailable-course enrollments separately. Evidence is in `backups/learning-enhancement-verified.json`.

Further enhancements: show checkpoint requirements beside individual module lessons, review legacy/workspace progress parity through stable mappings, and standardize timestamp storage during a separate reviewed migration.

Final validation on 2 October 2026: **171 PHP tests passed (3,658 assertions)**. The focused browser regression run passed **67 Playwright tests** across course entry, continuation, learner journeys, security, and mobile layouts. One existing practical-assessment privacy test was skipped because this focused fixture set has no practical assessment belonging to another learner; the previous full-suite run exercised that flow. All nine new continuation/diagnostic browser tests passed. PHP syntax checks, JavaScript syntax checks, and `git diff --check` passed. Final report: `e2e/report-isolated/index.html`; PHP log: `backups/learning-enhancement-final-php.txt`.

Reproduce the focused browser validation after `ha_test prepare_browser`:

```powershell
Set-Location e2e
node node_modules/@playwright/test/cli.js test -c playwright.isolated.config.ts learning-continuation.spec.ts course-entry.spec.ts learner.spec.ts mobile.spec.ts security.spec.ts
```
