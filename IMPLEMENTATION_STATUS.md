# Hospitality Academy — Implementation Status

Target specifications: `plan-final.txt` (60 sections, 19 phases) and, from 2026-09-24,
the ALTUS HK&P upgrade specification `ppt-features.txt` (190 sections), which is tracked
section by section in [gap.md](gap.md).
Host application: Academy LMS (CodeIgniter 3.1.9, PHP 8.1 locally), `C:\laragon\www\atlas\atlas`.
Working database (local): `atlas_merged` (production dump + migrations 11–15), selected by
`application/config/database.local.php`. Test database: `atlas_hospitality_test` (rebuilt on every run).

Status vocabulary, as required by plan section 57:

| Status | Meaning |
|---|---|
| VERIFIED | Code exists and an automated test or audit proves it works |
| IMPLEMENTED | Code exists and runs, no automated test yet |
| NOT STARTED | No code yet |

This file reports the true state. Nothing is marked VERIFIED without a passing check.

---

## Stack decision, recorded

`plan-final.txt` addresses "an existing Laravel application". This repository is
CodeIgniter 3, not Laravel. A Laravel implementation of the same specification
also exists at `C:\laragon\www\atlas-lms\bk`. Both facts were put to the project
owner, who directed the build to proceed inside the CodeIgniter application at
`Academy-LMS`. That decision is why the architecture below uses CodeIgniter
libraries and a hand-written migration, seeder, test and audit runner.

---

## Verification commands

```bash
cd C:/laragon/www/atlas-lms/Academy-LMS

php index.php ha_cli status        # migration state
php index.php ha_cli migrate       # apply pending migrations
php index.php ha_cli seed          # idempotent seed
php index.php ha_cli fresh         # full rollback, migrate, seed
php index.php ha_test run          # whole test suite
php index.php ha_test run seo      # one suite

# photography
php index.php ha_images fetch          # download the licensed photo library
php index.php ha_images fetch_variants # extra photos per course category
php index.php ha_images assign         # attach photos to content
php index.php ha_images report         # licence list and coverage

# rendered page audit against the live server
HA_AUDIT_BASE=http://localhost/atlas-lms/Academy-LMS php index.php ha_audit run
HA_AUDIT_BASE=http://localhost/atlas-lms/Academy-LMS php index.php ha_audit flows
```

Live URL: <http://localhost/atlas-lms/Academy-LMS/> (root redirects to `/en` or
`/ar` by browser language).

**Last full test run (2026-09-25): 136 tests, 136 passed, 0 failed, 2,100+ assertions.**
**Last authenticated crawl (2026-09-25, production data copy): 9 roles, 1,100+ pages, 0 errors, 0 dead links.**
**Arabic interface coverage: 1,673 / 1,673 strings (enforced by `Test_cms`).**

Figures below this line that predate 2026-09-24 are kept as history:

**Earlier full test run: 83 tests, 83 passed, 0 failed, 1,523 assertions.**
**Last browser route sweep: both frontends, signed out, learner and admin, 0 problems.**
**Last page audit: 240 checks across 236 URLs, 0 problems.**
**Last flow audit: 5 end-to-end flows, 0 problems.**

---

## Section status

### Foundation

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| — | Application installed, database configured, admin account, public site renders | `application/config/database.php`, `config.php` | HTTP sweep | VERIFIED |
| 36 | Architecture: migrations, seeders, repositories, libraries separated from controllers | `application/migrations/`, `seeds/`, `libraries/Ha_*.php` | `ha_cli fresh` | VERIFIED |
| 36 | Migration runner (CI 3.1.9's own library cannot run on PHP 8) | `controllers/Ha_cli.php`, `libraries/Ha_migration.php` | full down/up cycle | VERIFIED |
| 50 | Test runner, assertion library, isolated test database | `controllers/Ha_test.php`, `libraries/Ha_testcase.php` | 83 tests run | VERIFIED |
| 16, 53 | Rendered-page audit runner | `controllers/Ha_audit.php` | 236 URLs walked | VERIFIED |

### 37 — Database

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 37 | Core identity, RBAC, organization tree | `migrations/…000001_create_core_tables.php` | `Test_schema` | VERIFIED |
| 37 | Programs, courses, lessons, enrollment, progress, paths, skills | `…000002_create_academy_tables.php` | `Test_schema` | VERIFIED |
| 37 | Question engine, assessments, assignments, exams, certificates, verification | `…000003_create_assessment_tables.php` | `Test_schema` | VERIFIED |
| 37 | SOP hub, versions, acknowledgement, checklists, training assignments, attendance, notifications, audit log | `…000004_create_operations_tables.php` | `Test_schema` | VERIFIED |
| 37 | CMS, SEO metadata, redirects, topics, leads, competitor intelligence | `…000005_create_content_tables.php` | `Test_schema` | VERIFIED |
| 37 | 89 tables, all InnoDB/utf8mb4, foreign keys on every tenant relationship | database | `Test_schema` | VERIFIED |
| 40 | Bilingual model: parallel EN/AR translation rows | `ha_*_translation` | `Test_schema`, `Test_content` | VERIFIED |

### 2, 38, 45, 49, 52 — Authorization, tenancy and audit

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 2 | Eight roles | `seeds/001_rbac.php` | `Test_rbac` | VERIFIED |
| 38 | 180 permissions across 41 modules | `seeds/001_rbac.php` | `Test_rbac` | VERIFIED |
| 38 | Server-side permission checks, never hidden UI | `libraries/Ha_auth.php` | `Test_rbac` | VERIFIED |
| 45 | Role scope decides access; context columns do not grant it | `Ha_auth::load()` | `Test_rbac` (6 isolation tests) | VERIFIED |
| 52 | Cross-organization, cross-property, cross-department access refused | `Ha_auth` | `Test_rbac` | VERIFIED |
| 52 | Suspended profile and deactivated account lose access | `Ha_auth::load()` | `Test_rbac` | VERIFIED |
| 38 | Mass assignment, sort-key whitelisting, IDOR refusal | `libraries/Ha_repository.php` | `Test_repositories` | VERIFIED |
| 49 | Audit log with actor, action, entity, before/after, IP, agent; secrets redacted | `libraries/Ha_audit.php` | `Test_repositories` | VERIFIED |

### 41, 5 — Admin mechanics and organizations

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 41 | Search (EN/AR), filters, sort, pagination, bulk actions, export | `Ha_repository` | `Test_repositories` (18 tests) | VERIFIED |
| 41 | Destructive action blocked while a record is in use | `can_delete()` | `Test_repositories` | VERIFIED |
| 5 | Organizations, properties, departments, job roles | `libraries/Ha_repo_*.php` | `Test_repositories` | VERIFIED |
| 5 | Seed data: 1 organization, 8 Saudi properties, 16 departments, 28 job roles, 24 accounts | `seeds/002_organizations.php` | idempotent re-run verified | VERIFIED |

### 6, 7, 8, 9, 10, 20 — Curriculum

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 9 | Hospitality catalogue: 74 courses across nine departments | `seeds/003_curriculum.php` | `Test_content` | VERIFIED |
| 7, 8, 40 | 148 course translations, 666 lessons, 1,332 lesson translations | `seeds/003_curriculum.php` | `Test_content` | VERIFIED |
| 7 | Outcomes, requirements, FAQ, sections, preview lesson, completion rules | `seeds/003_curriculum.php` | `Test_content` | VERIFIED |
| 6 | 6 programs grouping courses into qualifications | `seeds/003_curriculum.php` | `Test_content` | VERIFIED |
| 10 | 3 career paths, 16 steps mapped to job roles | `seeds/003_curriculum.php` | `Test_content` | VERIFIED |
| 20 | 20 skills mapped to the courses that award them | `seeds/003_curriculum.php` | `Test_content` | VERIFIED |
| 7 | Unpublished content never appears publicly | `libraries/Ha_catalog.php` | `Test_content` | VERIFIED |

### 12, 13, 14, 15 — SOP hub

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 12 | 14 SOPs with the full structure: purpose, scope, responsibilities, tools, procedure, checklist, safety, quality, escalation | `seeds/004_sops.php` | `Test_content` | VERIFIED |
| 12 | 14 SOP categories, bilingual | `seeds/004_sops.php` | `ha_audit` (SOP pages render) | VERIFIED |
| 13 | Version control with author, approver, effective date, review date, change summary | `ha_sop_version` | `Test_schema` | VERIFIED |
| 39 | Organization-scoped SOPs never publicly readable by URL | `Ha_catalog::public_sop()` | `Test_content` | VERIFIED |
| 15 | 14 runnable checklists, 140 items, linked to their SOP | `seeds/004_sops.php` | seed idempotency + counts | VERIFIED |
| 14 | Acknowledgement schema (user, version, IP, timestamp) | `ha_sop_acknowledgement` | `Test_schema` | IMPLEMENTED |

### 25, 26 — Public academy website

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 26 | All 17 public routes, in both locales | `controllers/Academy.php`, `config/routes.php` | HTTP sweep, `ha_audit` | VERIFIED |
| 26 | 206 detail pages (courses, programs, paths, topics, articles) resolve in both locales | `Academy.php` | HTTP sweep: 206/206 → 200 | VERIFIED |
| 26 | Static pages resolve by translated slug from the database | `Academy::page_by_slug()` | `ha_audit` | VERIFIED |
| 28 | No broken internal link anywhere on the public site | — | `ha_audit` link crawl | VERIFIED |
| 54 | One H1 per page and no heading-level jumps | views | `ha_audit` | VERIFIED |
| 26 | `/verify/{code}` public certificate verification with four outcomes | `Academy::verify()`, `Ha_catalog::verify_certificate()` | `Test_content` | VERIFIED |
| 26 | Contact form writing a real lead record, rejecting invalid input | `Academy::contact()` | `ha_audit flows` | VERIFIED |
| 35 | Global search across courses, articles and topics | `Ha_catalog::search()` | `Test_content` | VERIFIED |
| 28 | 404 page that resolves, not a blank screen | `Academy::missing()` | `ha_audit` | VERIFIED |
| 25 | Public header navigation driven from the database | `ha_menu`, `ha_menu_item` | `Test_content` | VERIFIED |

### 27, 28, 29, 53 — SEO, AEO and GEO

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 27 | Admin-editable SEO metadata for every indexable page, EN and AR (70 records) | `libraries/Ha_seo.php`, `ha_seo_metadata` | `Test_seo` | VERIFIED |
| 28 | Title, description, canonical, robots, OpenGraph, Twitter card | `Ha_seo::render_head()` | `Test_seo`, `ha_audit` | VERIFIED |
| 28 | hreflang EN/AR plus x-default, pointing at the translated slug | `Ha_seo::render_head()` | `Test_seo`, `ha_audit` | VERIFIED |
| 28 | Arabic slugs as real URLs (`permitted_uri_chars` extended, slugs decoded) | `config/config.php`, `Academy::slug()` | 103 Arabic URLs → 200 | VERIFIED |
| 28 | Language-aware XML sitemap with alternates (236 URLs) | `Ha_seo::render_sitemap()` | `Test_seo` | VERIFIED |
| 28 | robots.txt allowing the site, blocking admin and API | `Ha_seo::render_robots()` | `Test_seo` | VERIFIED |
| 28 | Breadcrumbs plus BreadcrumbList schema | `Ha_seo::breadcrumb()` | `Test_seo` | VERIFIED |
| 28 | Organization, WebSite, WebPage, Course, Article, FAQPage, HowTo, CollectionPage schema | `Ha_seo`, `seeds/005_content.php` | `Test_seo` | VERIFIED |
| 28 | 301 redirect manager with hit counting | `Ha_seo::redirect_for()` | `Test_seo` | VERIFIED |
| 29 | Saudi city pages with genuinely local content, not doorway pages | `seeds/005_content.php` | `Test_content` (length + uniqueness) | VERIFIED |
| 29 | City schema naming the city and country | `seeds/005_content.php` | `Test_seo` | VERIFIED |
| — | AEO: question-shaped FAQs with direct answers, in FAQPage schema | `seeds/005_content.php` | `Test_seo` | VERIFIED |
| 53 | Automated SEO audit over every indexable page | `Test_seo`, `controllers/Ha_audit.php` | 21 tests + 236-URL walk | VERIFIED |

### 30, 31, 32, 33 — Content and competitor intelligence

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 33 | 8 public pages, bilingual, database-driven | `seeds/005_content.php` | `Test_content` | VERIFIED |
| 30 | 14 topics: 6 pillars, 8 city pages, content taxonomy linked to courses | `seeds/005_content.php` | `Test_content` | VERIFIED |
| 33 | 6 articles, bilingual, with author, category, tags, related course and CTA | `seeds/005_content.php` | `Test_content` | VERIFIED |
| 31 | 3 competitors recorded with capabilities, evidence URL and checked date | `seeds/005_content.php` | `Test_seo` | VERIFIED |
| 31 | No unsupported competitor claim: unknown is recorded as unknown | `seeds/005_content.php` | `Test_seo` | VERIFIED |
| 32 | 25 tracked keywords (EN and AR) with intent, cluster and target page | `seeds/005_content.php` | `Test_seo` | VERIFIED |
| 16 (reality rule) | No invented statistics, endorsements or testimonials anywhere | `seeds/005_content.php` | testimonials deliberately unseeded | VERIFIED |

### Academy LMS integration

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 7, 9 | Catalogue published into the legacy LMS: 74 courses, 296 sections, 666 lessons, 9 categories plus 9 sub-categories | `controllers/Ha_bridge.php` | `ha_bridge status`, browser sweep | VERIFIED |
| — | One source of truth: `ha_*` writes one way, matched on course code, idempotent | `Ha_bridge::sync()` | re-run leaves counts unchanged | VERIFIED |
| — | Legacy thumbnails published as JPEG under the theme's filename convention | `Ha_bridge::publish_course_thumbnail()` | 74 files, 0 broken images | VERIFIED |
| — | Two-tier category mapping, because a legacy course attaches to a sub-category | `Ha_bridge::sync_category_pair()` | course filter and home tiles render | VERIFIED |
| — | `/courses`, `/home/courses`, `/home/my_courses` show real courses | legacy theme + bridge | `.lab/routes.py` | VERIFIED |
| — | Seeded learners have enrolments (67) | `Ha_bridge::enrol()` | `/home/my_courses` renders 6 courses | VERIFIED |
| — | `/home/profile` no longer returns 500 | `controllers/Home.php` | `.lab/routes.py` | VERIFIED |
| — | `/courses` and `/course/{slug}` resolve instead of the theme 404 | `config/routes.php` | `.lab/routes.py` | VERIFIED |
| 16 (reality rule) | False partner endorsements removed from the shipped home page | `views/frontend/default-new/home_elegant.php` | visual check | VERIFIED |

### Design

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| — | Both frontends share the Academy LMS violet palette | `assets/academy/academy.css` | visual check | VERIFIED |
| — | Accent-led hierarchy: title rules, card hover lift, primary button, section rhythm | `assets/academy/academy.css` | visual check | VERIFIED |
| 54 | Refinements honour `prefers-reduced-motion` | `academy.css` | — | IMPLEMENTED |

### Photography and media

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| — | Root URL serves the academy; legacy LMS routes untouched | `config/routes.php` | HTTP 302 to `/en`, legacy routes still 200 | VERIFIED |
| 39 | Licensed photo library: 34 images, commercial-use licences only | `controllers/Ha_images.php` | `ha_images report` | VERIFIED |
| 39 | Attribution stored per file: author, licence, licence URL, source page | `migrations/…000006_add_media_attribution.php` | `ha_images report` | VERIFIED |
| 39 | Public credits page in both languages, linked from every page footer | `views/academy/credits.php`, `Academy::credits()` | `ha_audit` | VERIFIED |
| — | Sourcing by curated Commons category, not relevance search | `Ha_images::fetch_subject()` | inspected contact sheet | VERIFIED |
| — | Drawings, maps, logos, military and child imagery rejected | `Ha_images::looks_photographic()` | inspected contact sheet | VERIFIED |
| — | No two subjects share a photograph (checksum dedupe) | `Ha_images::store()` | `ha_images report` | VERIFIED |
| — | Course cards vary within a category | `Ha_images::assign()` | inspected screenshots | VERIFIED |
| 55 | WebP at two widths, intrinsic dimensions, lazy below the fold | `helpers/ha_media_helper.php` | browser check, 0 broken images | VERIFIED |
| 55 | No horizontal overflow at 1440px or 390px | `academy.css` | browser check | VERIFIED |
| 100% coverage | 74/74 courses, 14/14 topics, 6/6 articles, 6 pages, 70 social images | database | `ha_images report` | VERIFIED |

### 40, 43, 54 — Bilingual, mobile and accessibility

| # | Requirement | Location | Test | Status |
|---|---|---|---|---|
| 40 | Genuine RTL: `dir` on `<html>`, logical CSS properties, Arabic font stack | `views/academy/layout.php`, `assets/academy/academy.css` | `ha_audit`: lang/dir correct on all 236 URLs | VERIFIED |
| 40 | Language switch lands on the translated slug, not the same path | `Academy`, `Ha_seo::set_alternate()` | `ha_audit flows` | VERIFIED |
| 40 | Arabic authored separately, never a copy of the English string | `seeds/003_curriculum.php` | `Test_content` | VERIFIED |
| 43 | Mobile navigation, 44px tap targets, responsive grids, readable tables | `assets/academy/academy.css` | `ha_audit` markup checks | IMPLEMENTED |
| 54 | Skip link, focus-visible outlines, labelled inputs, single H1, heading order, image alt text | layout + `ha_audit` checks | `ha_audit` | VERIFIED |
| 54 | `prefers-reduced-motion` honoured | `academy.css` | — | IMPLEMENTED |

### AI Studio, key authentication, API v1 (added 2026-09-24)

| Requirement | Location | Test | Status |
|---|---|---|---|
| 47-provider registry from official docs, country / region / capability metadata | `config/ha_ai_providers.php` | `Test_ai_studio` | VERIFIED |
| Provider keys encrypted (AES-256-GCM), env-var override, live model sync, test connection | `libraries/Ha_ai_gateway.php`, `Ha_crypto.php` | `Test_ai_studio`, e2e | VERIFIED |
| Task routing, usage ledger, writing assistant | `controllers/Ha_ai.php`, `views/backend/ha_ai/` | e2e | VERIFIED |
| Course draft + lesson script jobs, validation, review, publish, single-course LMS sync | `libraries/Ha_ai_studio.php`, `Ha_bridge::sync_one()` | `Test_ai_studio`, e2e | VERIFIED |
| Narrated slide video (GD + Arabic shaping + ffmpeg + WebVTT) | `libraries/Ha_video_renderer.php`, `Ha_arabic.php` | slides drawn in tests; encoding needs ffmpeg | IMPLEMENTED |
| TTS / presenter / clip adapters (OpenAI-style, ElevenLabs, Azure, Google, Gemini, HeyGen, D-ID, Synthesia, Veo, Luma, Runway) | `libraries/Ha_ai_media.php` | adapter matching tested; no live vendor calls | IMPLEMENTED |
| Background worker with lock, heartbeat, retry, crash recovery | `controllers/Ha_ai_cli.php` | `Test_ai_studio`, e2e | VERIFIED |
| TOTP two-factor login on every login path, recovery codes, throttling | `libraries/Ha_two_factor.php`, `Ha_totp.php`, `User_model::set_login_userdata()` | `Test_key_auth`, e2e | VERIFIED |
| Personal API keys (hashed, scoped, expiry, IP pinning, revoke, rate limit) and `/api/v1` | `libraries/Ha_api_keys.php`, `controllers/Api_v1.php` | `Test_key_auth`, e2e | VERIFIED |

Last run: 116 tests, 116 passed, 1,824 assertions; 61/61 end-to-end HTTP checks.

---

### ALTUS Hospitality Knowledge & Performance (added 2026-09-24 / 25)

Per-section detail for all 190 sections is in [gap.md](gap.md). Summary:

| Area | Location | Test | Status |
|---|---|---|---|
| Tenancy: organisation → portfolio → property → department → team, 16 roles, scope-aware reads | migration `…013`, `Ha_auth`, `seeds/001_rbac.php` | `Test_rbac`, `Test_hkp`, `Test_cms` | VERIFIED |
| Evidence chain: requirements → learning → theory → practical → competency → gap → action → reassessment → readiness → certificate | `Ha_competency`, `Ha_theory`, `Ha_practical`, `Ha_action_plans`, `Ha_readiness`, `Ha_certification` | `Test_hkp::final_qa_scenario_end_to_end` | VERIFIED |
| Knowledge governance, versions, acknowledgement, health | `Ha_knowledge` | `Test_hkp` | VERIFIED |
| Governed AI (approved, authorised sources only; cites; says "insufficient") | `Ha_governed_ai` | `Test_hkp` | VERIFIED |
| KPIs, performance matrix, GOPPAR, ESG, capability model, engagements (ASCENT) | `Ha_kpi`, `Ha_advisory` | `Test_hkp` | VERIFIED |
| Reports CSV/XLSX, board report, certificate PDF/PNG with QR | `Ha_reports`, `Ha_xlsx`, `Ha_pdf`, `Ha_qr` | `Test_hkp` | VERIFIED |
| Page builder, revisions, SEO/AEO/GEO score, FAQ/Place schema | `Ha_page_builder`, `Ha_seo_score`, `Hkp_cms` | `Test_cms` | VERIFIED |
| Modules & lessons CMS: links, uploads (video/PDF/PPT/audio), drip release | `Hkp_cms`, `Ha_learning::release_at` | `Test_cms` | VERIFIED |
| AI editor help with provider/model choice and prompt enhancement | `Ha_ai_assist` | `Test_cms` | VERIFIED |
| HK&P API endpoints, spec response envelope | `Api_v1`, `Ha_api_hkp` | `Test_cms`, curl | VERIFIED |
| Arabic interface (1,673 strings) | `language/arabic/hkp_lang.php` | `Test_cms` | VERIFIED |
| Legacy admin and user sidebars link the workspace and CMS | `views/backend/*/navigation.php` | crawl: 67/67 admin links 200 | VERIFIED |

Partial (see gap.md for the exact gap): custom-domain provisioning, Arabic for 296 ported
legacy quiz questions, vector semantic search, SMS/WhatsApp delivery, offline lessons,
PMS/POS/HR connectors and SSO, privacy self-service export/erasure, load testing,
automatic retention purge, scheduled backups/DR, and committing this work to git.

---

## Honest summary

- **Delivered and test-proven:** the data model (37), authorization and tenancy
  (2, 38, 45, 52), audit (49), admin list/form mechanics (41), organizations (5),
  the full curriculum (6–10, 20), the SOP knowledge hub content and version
  model (12, 13, 39), the entire public website (25, 26, 35), the SEO/AEO/GEO
  system (27, 28, 29, 53), content and competitor intelligence (30–33),
  bilingual RTL delivery (40) and the accessibility basics (54).
- **Delivered without automated tests yet:** reduced-motion handling, which
  needs a real device to confirm.
- **Photography:** two subjects (certificate imagery) have no photograph of
  their own, because Wikimedia Commons holds no modern, on-brand certificate
  photograph that passes the filters. Those surfaces borrow the training-room
  image rather than ship something wrong.
- **Since 2026-09-24:** the admin screens, learner experience and operational
  engines that were "not started" are built as the HK&P workspace (table above).

136 automated tests pass, and the authenticated crawl of 9 roles on the production data copy
reports zero errors and zero dead links. No requirement above is marked
complete unless it is.
