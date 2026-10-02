# Admin Studio baseline — 2 October 2026

The supplied repository is CodeIgniter 3 (bundled framework), PHP 8.1.10, MySQL on Laragon, with native PHP templates and dependency-free workspace JavaScript. Node v24.16.0 and npm 11.13.0 are installed for Playwright. The public local `/en` route returned HTTP 200.

There is no WordPress installation, theme, Gutenberg, third-party WordPress LMS, WPML/Polylang or WordPress SEO plugin here. The WordPress master specification is an integration roadmap; its Phase 0 instruction explicitly requires preserving custom learning platforms. This implementation extends the native application. No production server is contacted or changed.

## Existing adapters

| Responsibility | Existing implementation to preserve |
|---|---|
| Authentication and capabilities | Legacy login session; `Ha_auth`; permission-generated navigation |
| Organisation/property isolation | `Ha_auth::scope_query`, `can_organization`, `can_property`; `Ha_tenant` |
| Learning hierarchy | Programs → `ha_course` → `ha_course_section` → `ha_lesson`; `Ha_learning`; `Ha_bridge` LMS mirror |
| Assessments and certificates | `Ha_theory`, `Ha_assessment`, `Ha_quizbank`, `Ha_certification` |
| CMS and revisions | `Ha_page_builder`, `ha_page_translation`, `ha_page_section`, `ha_page_revision` |
| Website navigation | `ha_menu`, `ha_menu_item`, `Ha_catalog::menu` |
| Bilingual content and SEO | Independent translation rows, `Ha_seo`, `Ha_seo_score`, locale overlays |
| AI | Configurable `Ha_ai_gateway`; reviewed `Ha_ai_studio` jobs; no provider hardcoded |
| Jobs and schedule | `Ha_ai_cli`, `Hkp_cli`, database job queues |
| Private sources and audit | `application/storage/private`, `Ha_files`, `Ha_audit` |

## Baseline verification

`php index.php ha_test run`: **171 tests, 171 passed, 3,658 assertions**, 184.21 seconds. Tests rebuilt only `atlas_hospitality_test`. Existing E2E checks are run with `playwright.isolated.config.ts`, never against the working learner database.

## Restore point

Pre-existing tracked edits were preserved by non-mutating `git stash create` object `58f6e883c58e01287e0d878e24fe9efd4fa90ada`. Files to be edited, including already untracked source, were copied to `backups/admin-studio-20261002/`. No stash pop/reset/clean was performed. Keep database and private uploads backed up independently before applying migrations.

## Proposed compatibility map

New admin tools call existing native services and tables. A future WordPress plugin must call an explicitly authenticated, tenant-scoped adapter API, with stable external IDs; it must not create a competing course database or write directly to this MySQL schema. WordPress/MCP/OAuth integration is deferred until that contract and a staging WordPress installation exist, as required by the master prompt. Remote gateway work is not included in this native admin release.
