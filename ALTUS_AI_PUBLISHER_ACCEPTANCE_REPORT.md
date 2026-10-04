# ALTUS Admin, Live CMS, AI Publisher and MCP verification

Verified locally on 3 October 2026. Native CodeIgniter 3.1.9, PHP 8.1.10, MySQL 8.0.30, Python 3.13, Tesseract English/Arabic data, Node 24 and Playwright/Edge. Implementation remains in the shared working tree alongside existing user changes; production was not deployed.

## Executed checks

| Check | Result |
|---|---|
| Complete native regression suite | **239 passed, 0 failed; 4,403 assertions; 313.89 seconds** in `atlas_hospitality_test_final`. Includes existing authentication, tenancy, learners, certificates, SOP governance, AI/LMS/library release tests plus new publishing and live CMS tests. |
| Extractor tests | **11 passed**. Actual embedded/scanned/mixed PDFs, English/Arabic dependencies, damaged/encrypted input, provenance, CLI progress/health and limits. |
| PHP / editor JavaScript syntax | **69 PHP files and 6 JavaScript files passed**, with final edited controllers/view/script checked again. |
| Final API contract rerun | **14 passed, 115 assertions** in separate `atlas_hospitality_test_contract_final`. |
| Public route smoke | **26/26 EN/AR landing routes HTTP 200**, no PHP-error output or guest editing markers. |
| Working extraction setup | Python/pypdf/PyMuPDF, Tesseract and eng/ara data pass. Local daemon PID **12732** has a current heartbeat; logs in `application/logs/publisher-worker-local.log`. AI provider check correctly reports none enabled. |
| Test rebuild safeguard | Working database name explicitly refused; only loopback MySQL and `atlas_hospitality_test[_suffix]` accepted. |
| Node gateway | TypeScript build passed; security unit tests **3 passed**, live integration intentionally skipped by the unit command. |
| Complete browser regression | **271 passed, 1 cleanup timeout out of 272; 13.2 minutes**. The editing assertions passed; the test closed its browser context while draft discard reloaded the editor. The check now waits for the reload; the corrective editor/integration run passed below. |
| Corrective browser run | **27 passed, 0 failed; 2.0 minutes**, including all new editor workflows plus keyboard/mobile/Arabic integration tabs and endpoint copying. |
| Final sidebar/integrations run | **12 passed, 0 failed; 54.2 seconds**, including corrected active navigation, keyboard tabs, mobile/Arabic layouts, endpoint copying and learner access denial. |
| Actual SDK/OAuth/native integration | **11 passed, 0 failed; 32.26 seconds**. Real official SDK client, running gateway, native API and browser consent/reviewer. Includes disabled gateway, private create, approved publish/archive/restore, conflicts/idempotency, scope restrictions, tenant isolation, revocation, refresh rotation and an encrypted enrolled-account two-factor login before OAuth consent/MCP access. |
| Working database schema | `ha_cli status` confirms migrations through 31 are applied, including publisher upgrades 27, 29 and 30. |
| Database backup before publisher migration | `C:/laragon/backups/atlas-publisher-upgrade-20261002/atlas_merged.sql`, outside web root. Earlier Admin Studio backup is documented in the installation guide. |

The PHP suite rebuilt only its separate test database. Browser and gateway integration use `atlas_hospitality_test`; run them sequentially. Local tests use a configured mock AI provider to exercise actual request/validation/import behavior without paid calls. OCR runs real local tooling. No production database was rebuilt.

## Native PHP MCP server (3 October 2026, supersedes the Node gateway)

MCP and OAuth now run in PHP inside the application (`/mcp`, `/oauth/*`, `/.well-known/*`); the Node gateway is deprecated and was not started for these checks. Details: [docs/MCP_PHP.md](docs/MCP_PHP.md).

| Check | Result |
|---|---|
| Database backup before migration 032 | `C:/Users/Lenovo/atlas-backups/atlas_merged_before_032_20261003_185214.sql` (30.3 MB, outside the web root). |
| Migration 032 on local `atlas_merged` | `ha_cli migrate 20260101000032` → `migrated 20260101000032 mcp_php`. down()/up() verified in the test database. |
| New PHP tests `180_mcp_php.php` | **15 passed, 0 failed, 809 assertions** (OAuth flow, PKCE, code replay, refresh rotation + reuse detection, revocation, audience, scope capping/restriction, revoked user, tenant isolation, protocol/session/batch/errors, CRUD for every content type, conflicts, idempotency, approval expiry/single-use/changed content/self-approval, switch off, rate limit, admin screen). |
| Complete PHP regression suite | **256 passed, 0 failed, 5,226 assertions** in `atlas_hospitality_test_mcpphp`. |
| Official MCP SDK client end to end | `node tests/mcp_sdk_e2e.mjs` against `http://localhost/atlas/atlas/mcp` (Apache + PHP only): **23/23 checks passed** — 401 discovery, RFC 7591 registration, PKCE, resource indicator, real browser sign-in + consent, code exchange, initialize, 27 tools listed, create/replay/update/conflict, approval request + refusal before approval, refresh rotation, reuse detection revoking the grant. |
| Integrations browser spec | `e2e/tests/integrations.spec.ts` **11 passed** (desktop, includes role sign-in setup). |
| PHP syntax | `php -l` clean on all new/changed PHP files. |

Not verified: production HTTPS / reverse proxy, claude.ai / Cursor / Claude Code connecting to a public URL (needs a deployed HTTPS host), two-factor during the SDK run (the scripted login was not asked for a second factor; the consent route sits behind the same `Hkp_Controller` sign-in gate as every workspace page), RFC 8414 path-aware discovery from the web-server root when installed in a sub-directory (see Troubleshooting in MCP_PHP.md). The SDK run left one draft article ("SDK e2e article", id 20), one pending approval and test OAuth clients/grants in the local database.

## Implementation and covered behavior

- Admin/sidebar hierarchy, actual metrics, responsive keyboard navigation and bilingual/RTL screens.
- Home and twelve requested public destinations; functional catalogue/contact/certificate verification components preserved.
- Genuine preview click-to-edit, images/media, thirteen section types, corporate homepage block binding, private EN/AR drafts and explicit publication.
- Page/entity/theme/menu version conflicts, signed authorized preview links, revision restoration, stable sections/steps and learner-history protection.
- Private retained document jobs, per-page text/OCR provenance, cancellation, retries/backoff/caps, worker heartbeat and retention cleanup.
- Course/page/article/topic/SOP packages, source correction, selected regeneration, independent translation drafts, featured media and SOP internal review without bypassing approval.
- Native API structured errors and current-version conflict details, idempotency replay, real audit references, scoped private creation, approvals bound to content/client/user and single-use publication.
- Node MCP SDK gateway, PKCE, native login/consent bridge, audience validation, persisted grants, refresh rotation/revocation, delegated credentials and signing key rotation support.

## Configuration-dependent checks

Not deployed or verified against production HTTPS/reverse proxy, Docker hosting or a third-party vendor client. No live paid AI provider call. The OAuth browser test includes a temporary encrypted two-factor fixture and restores it afterwards; the native two-factor regression tests also pass. Signing-key rotation is verified by security tests, not a running two-server production rotation. Arabic OCR quality depends on source quality; review flagged pages and generated answers before publishing.

The working application has no enabled AI chat provider: configure credentials/models in AI Studio before live generation. MCP remains off in the working application until its matching gateway/native settings and client callbacks are configured; the real connection tests use isolated, temporary settings. A local extraction daemon is started for the current workstation session; scheduled restart setup is documented in the installation guide.

The editor exposes supported native fields/sections, not arbitrary template or application-code editing. MCP (PHP) provides draft CRUD for pages, page sections, catalogue entries, site/theme, navigation, course sections, lessons, quizzes, questions and SOP drafts, plus package workflows and approved publish/archive/restore; published lessons/quizzes/outlines are locked against MCP edits (no draft layer). No permanent-delete, arbitrary SQL or shell MCP tool is provided.

## Install and use

[Install and worker setup](docs/ADMIN_INSTALL.md), [admin guide with screenshots](docs/ADMIN_GUIDE.md), [features](docs/ADMIN_FEATURES.md), [MCP setup/connection tests](docs/MCP_GATEWAY_RUNBOOK.md), [integration approvals/audit](docs/MCP_ADMIN_WORKFLOWS.md), [API contract](docs/PUBLISHER_API.md).
