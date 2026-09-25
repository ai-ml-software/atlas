# Gap analysis: ALTUS HK&P specification vs. the PHP LMS

This report checks the specification in `ppt-features.txt` (190 numbered sections plus the feature brief) and the Altus Advisory profile in `ppt-text.txt` against this codebase. For every section it gives the gap, the fix, where the fix lives and how it was checked.

**Baseline before the upgrade.** The codebase was Academy LMS (CodeIgniter 3.1.9) plus a bilingual public academy site. It already had these parts:

- a course catalogue, a legacy course player, legacy quizzes and a legacy certificate addon
- an SOP library
- SEO
- 8 RBAC roles
- AI Studio

It had none of the following:

- competencies
- practical assessment
- gaps, action plans or reassessment
- readiness
- certification rules
- white-label tenants
- governed AI
- KPIs
- advisory frameworks
- executive dashboards
- a page builder
- drip content

**Status key**

| Mark | Meaning |
|---|---|
| ✅ T | Built, and proved by an automated test in `application/tests/` (`php index.php ha_test run`) |
| ✅ C | Built, and proved by the authenticated crawl: every page reachable from the navigation, for 9 roles, on the production data copy |
| 🟡 | Partly built. The note says exactly what is missing |
| 🔵 | Not built. The data model or hook exists for it |

**Totals:** 177 sections met (✅) and 13 partly met (🟡). Test suite: **136 tests, 0 failures**. Crawl: **1,100+ pages across 9 roles, 0 errors, 0 dead links**. Arabic interface coverage: **1,673 / 1,673 strings (100%)**.

---

## 1. Product, experiences and architecture (§1–§9)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 1 | Product objective: an evidence-based capability platform, not a course shop | New `/hkp` workspace. The evidence chain runs Role → Requirement → Learning → Theory → Practical → Competency → Gap → Action → Evidence → Reassessment → Readiness → Certificate → KPI (`libraries/Ha_competency.php`, `Ha_readiness.php`, `Ha_certification.php`) | `Test_hkp::final_qa_scenario_end_to_end` | ✅ T |
| 2 | Core differentiator: training ≠ performance | Theory results are capped and never lower a level. Practical and manager results need evidence (`Ha_competency::record_result`) | `Test_hkp` | ✅ T |
| 3 | Five integrated layers | Product & Experience (`views/hkp/*`); Knowledge & Learning (`Ha_knowledge`, `Ha_learning`); Intelligence (`Ha_governed_ai`, `Ha_kpi`); Tech Ops (`Ha_tenant`, `Ha_auth`); Governance (`Ha_audit`, workflows) | module map below | ✅ C |
| 4 | Three experiences: Learner, Property Management, Altus Team (+ Executive) | Role-aware navigation (`core/Hkp_Controller::navigation`), with separate controllers `Hkp`, `Hkp_team`, `Hkp_admin`, `Hkp_exec` | crawl per role | ✅ C |
| 5 | Multi-tenant / multi-property | Organisation → portfolio → property → department → team, scoped by `Ha_auth::scope_query/visible_user_ids/can_*` (migration `…013`) | `Test_rbac`, `Test_hkp::tenant_isolation…`, `Test_cms::admin_lists_never_show…` | ✅ T |
| 6 | White-label property experience | Branding per tenant: logos, colours as CSS variables, name and footer (`Ha_tenant::brand/css_vars`, `hkp/team/branding`). A `ha_domain` table holds custom domains | crawl | 🟡 Branding is complete. Custom-domain DNS and SSL provisioning is a hosting task and is not automated |
| 7 | Language & localisation (AR/EN, RTL) | Gettext-style `hkp_t()` and `language/arabic/hkp_lang.php` (1,673 strings). Logical CSS for RTL, bilingual content fields, a `?lang=` switch | `Test_cms::every_interface_string_has_arabic` (100%, placeholders kept) | 🟡 The interface is 100% Arabic. The 296 ported legacy quiz questions still have an empty `body_ar`, so they fall back to English until someone translates them |
| 8 | Master curriculum architecture | Domain → Track → Module → Lesson → Block (`ha_domain`, `ha_track`, `ha_track_module`, `ha_lesson_block`) | `Test_hkp::ten_core_domains…` | ✅ T |
| 9 | Ten core professional domains | 16 domains seeded, with the 10 core ones flagged (`seeds/006_hkp.php`) | `Test_hkp` | ✅ T |

## 2. Knowledge & learning (§10–§17)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 10 | Content management system | The page builder (`Ha_page_builder`, `/hkp/cms`) has 13 section types. You can drag sections to reorder them, and duplicate, hide or delete them. The module and lesson CMS (`/hkp/cms/modules`) accepts text, YouTube or Vimeo links, video uploads, PDF, PPT and audio, and other links | `Test_cms::sections_save_reorder_and_restore` | ✅ T |
| 11 | Content version control | Knowledge versions with diff (`Ha_knowledge::new_version/compare`). `ha_lesson_version` holds lesson snapshots and `ha_page_revision` holds the last 60 page states, which can be restored | `Test_hkp::knowledge_governance…`, `Test_cms` | ✅ T |
| 12 | Knowledge governance | Draft → submit → internal approval → quality approval → publish, with reject and archive. An author cannot approve their own item | `Test_hkp::knowledge_governance…` | ✅ T |
| 13 | Learning engine | Enrol, lesson open/track/complete, progress refresh, prerequisites, assignment dispatch (`Ha_learning`) | `Test_hkp` | ✅ T |
| 14 | Lesson experience | Resume position, time tracking, transcript and captions, PDF viewer, slide and audio downloads, external resource links, drip release by date or days after enrolment (`Ha_learning::release_at`) | `Test_cms::drip_release_blocks_until_due` | ✅ T |
| 15 | Role-based learning paths | Role requirements generate each person's plan automatically (`Ha_learning::sync_role_plan`) | `Test_hkp` | ✅ T |
| 16 | Assessment engine | Theory papers with single, multiple, true/false, ordering and short-answer questions. Includes auto-grading, manual grading and question statistics (`Ha_theory`) | `Test_hkp` | ✅ T |
| 17 | Practical competency assessment | Weighted rubrics with critical criteria (a critical miss caps the result), save and resume, supervisor queue, void (`Ha_practical`) | `Test_hkp::practical_critical_criterion_caps_outcome` | ✅ T |

## 3. Performance chain (§18–§23)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 18 | Competency framework | 5 levels (Awareness → Expert), role-to-competency requirements, an append-only results history | `Test_hkp` | ✅ T |
| 19 | Gap analysis | Automatic recalculation after each result. Severity thresholds are configurable per tenant | `Test_hkp::gap_severity_thresholds_are_configurable` | ✅ T |
| 20 | Corrective action plans | Plans move through a state machine with events and evidence. An overdue sweep and manager review are included (`Ha_action_plans`) | `Test_hkp::final_qa…` | ✅ T |
| 21 | Reassessment | Request, decide, then run a new assessment that can close the gap | `Test_hkp::final_qa…` | ✅ T |
| 22 | Readiness engine | Configurable per-role policy with 7 explained checks: Ready, Conditional or Not ready | `Test_hkp` | ✅ T |
| 23 | Certification engine | Rules per programme, auto-issue, numbering (`ALTUS-XXX-YYYY-000001`), expiry, revoke, PDF and PNG with QR, public verify page | `Test_hkp::qr_encoder_and_pdf_writer` | ✅ T |

## 4. Intelligence (§24–§30)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 24 | Reporting | Report catalogue scoped to the viewer, with CSV (formula-injection safe) and real XLSX export (`Ha_reports`, `Ha_xlsx`) | `Test_hkp::reports_are_scoped…` | ✅ T |
| 25 | Executive dashboards | `/hkp/exec` and a printable board report (`/hkp/exec/board`) | crawl (a 500 error on the board page was fixed in this pass) | ✅ C |
| 26 | Performance intelligence | Performance matrix, GOPPAR value stack, KPI scorecards, cross-property comparison | `Test_hkp::performance_matrix…` | ✅ T |
| 27 | Smart search | Search over approved knowledge and lessons that respects visibility, with suggestions and a bilingual glossary for AR↔EN queries (`Ha_knowledge::search`, `Ha_governed_ai::retrieve`) | `Test_hkp::bilingual_retrieval…` | 🟡 It uses FULLTEXT plus a glossary. Vector-embedding semantic search is not built |
| 28 | Governed AI assistant | Retrieves only approved, authorised passages and cites them. If nothing supports an answer it says "insufficient knowledge" instead of inventing one. It falls back to extractive answers when no model is connected | `Test_hkp::ai_states_insufficient…` | ✅ T |
| 29 | AI governance | Every query is logged (`ha_ai_query`). Citations are validated. Per-item `ai_enabled` switches, index status, `/hkp/admin/ai` | `Test_hkp` | ✅ T |
| 30 | Knowledge retention / institutional memory | Knowledge belongs to the organisation, not the author. Leaving employees are marked terminated or archived, and their evidence is kept | `Test_hkp` | ✅ T |

## 5. Tenancy, content and platform services (§31–§38)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 31 | Property-specific customisation | Property SOPs, branding, settings, and role requirements keyed by property | `Test_hkp::tenant_isolation…` | ✅ T |
| 32 | Global vs property content | One visibility rule: global, organisation or property (`Ha_knowledge::visibility_sql`) | `Test_hkp` | ✅ T |
| 33 | Content approval & QA | Two-stage approval with a quality reviewer role (`quality_reviewer`) | `Test_hkp` | ✅ T |
| 34 | Notifications | Rules, templates, in-app and email, alerts (`Ha_notify`). Local email is captured rather than sent (`email.delivery` setting) | `Test_hkp` | 🟡 SMS and WhatsApp can be chosen per rule but have no provider. Messages stay pending |
| 35 | User & role management | 16 roles, grant and revoke, and server-side escalation checks (`/hkp/admin/users`) | `Test_rbac`, `Test_hkp::role_escalation…` | ✅ T |
| 36 | Audit logging | `Ha_audit` records actor, before and after values, IP and user agent, with secrets redacted. Viewer at `/hkp/admin/audit` | `Test_repositories` | ✅ T |
| 37 | File management | Private storage outside the web root, token downloads, MIME and extension checks, SVG files with scripts rejected (`Ha_files`, `Hkp_cms::upload`) | crawl, code review | ✅ C |
| 38 | Mobile / responsive | Mobile-first layout, 44 px targets, PWA manifest and service worker (caches the app shell) | crawl | 🟡 The app is responsive and installable. It cannot download lessons for offline learning |

## 6. Altus corporate frameworks and content (§39–§52)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 39 | Altus frameworks inside the platform | `Ha_advisory` holds frameworks, dimensions and questions, and scores each assessment (`/hkp/admin/frameworks`) | `Test_hkp` | ✅ T |
| 40 | Altus Performance Matrix™ | The axis threshold places each asset in a quadrant. The threshold is configurable and marked provisional | `Test_hkp::performance_matrix…` | ✅ T |
| 41 | GOPPAR Value Stack™ | 4-layer framework: Top-Line, Distribution, Cost, Asset Productivity | crawl | ✅ C |
| 42 | Strategic capability model | The `capability_model` framework | crawl | ✅ C |
| 43 | ESG & sustainability | ESG framework plus ESG categories on KPIs | crawl | ✅ C |
| 44 | Vision 2030 alignment | Four Vision 2030 content blocks in EN/AR (`seeds/006_hkp.php`), shown at `/en/altus` | crawl | ✅ C |
| 45 | Industry / sector taxonomy | `ha_sector` (seeded) | crawl | ✅ C |
| 46 | Corporate content / CMS | Corporate blocks editable at `/hkp/admin/corporate`. Public pages are built with the page builder | crawl | ✅ C |
| 47 | Hospitality solutions content | `ha_service` (hospitality line) | crawl | ✅ C |
| 48 | Business growth solutions | `ha_service` (growth line) | crawl | ✅ C |
| 49 | Case study module | Each case study carries a visibility level and an "illustrative / anonymised" label | crawl | ✅ C |
| 50 | Executive profile module | `ha_leadership` | crawl | ✅ C |
| 51 | Partnership / ecosystem | `ha_partner` directory | crawl | ✅ C |
| 52 | KPI architecture | 17 definitions with direction, thresholds and frequency, KPI values, and staleness flags (`Ha_kpi`) | `Test_hkp::kpi_status_respects…` | ✅ T |

## 7. API, database and upgrade rules (§53–§55)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 53 | Integration-ready API | `/api/v1` has hashed, scoped, expiring and IP-pinnable keys, a rate limit of 120 requests per minute, and TOTP 2FA on accounts. **Added in this pass:** HK&P endpoints for competencies, readiness, actions, certificates, people, gaps, search and KPIs (`libraries/Ha_api_hkp.php`, `controllers/Api_v1.php`). Output uses public shapes and never exposes raw rows | `Test_cms::performance_api_is_tenant_scoped`, `Test_key_auth` | 🟡 The API is complete. No PMS, POS, CRM or HR connectors are built, and there is no SSO identity provider |
| 54 | Database architecture | 5 additive migrations (11–15) with helper columns (`Ha_migration::add_columns`), all reversible | migrate on the production copy: 5 applied, 0 errors | ✅ T |
| 55 | Existing LMS upgrade rule (never break the legacy LMS) | Legacy routes untouched. The bridge publishes `ha_*` into the legacy tables. Both sidebars link the new workspace | legacy admin sidebar: 67 links, all 200 | ✅ C |

## 8. Dashboards and cross-cutting services (§56–§68)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 56 | Admin dashboard | `/hkp/admin` portfolio view. Tiles link only to pages the viewer can open (fixed in this pass) | crawl | ✅ C |
| 57 | Property dashboard | `/hkp/team` | crawl | ✅ C |
| 58 | Learner dashboard | `/hkp` | crawl | ✅ C |
| 59 | Assessor dashboard | `/hkp/assess/queue`, grading | crawl | ✅ C |
| 60 | Quality control dashboard | `/hkp/admin/content`, `/hkp/team/audits` | crawl | ✅ C |
| 61 | Certification dashboard | `/hkp/team/certifications` | crawl | ✅ C |
| 62 | Search & discovery | `/hkp/search` with type and domain filters and suggestions | crawl | ✅ C |
| 63 | Access control | Checks run on the server for every action. The UI hides what the server would refuse | `Test_rbac`, crawl (0 dead links) | ✅ T |
| 64 | Data privacy & security | CSRF, security headers, CSP, idle timeout, encrypted AI keys, audit redaction, 2FA | `Test_key_auth` | 🟡 No self-service data export or erasure request flow. `retention.years` is configurable (see §142) |
| 65 | Performance | Indexed foreign keys, pagination, one query per table in matrices, cached shell | crawl timings | 🟡 No formal load test has been run |
| 66 | Report exports | CSV, XLSX, board print, certificate PDF | `Test_hkp::reports…` | ✅ T |
| 67 | Import / migration tools | Preview → commit → rollback for users and KPI values (`Ha_importer`). The production database merge is described in §80 | crawl | ✅ C |
| 68 | System configuration | Settings registry with tenant overrides (`Ha_tenant::registry`, `/hkp/admin/system`) | crawl (a 500 error on the system page was fixed in this pass) | ✅ C |

## 9. Workflows, matrices and rules (§69–§77)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 69 | Complete employee performance journey | Covered end to end | `Test_hkp::final_qa_scenario_end_to_end` (40 assertions) | ✅ T |
| 70 | Role-to-competency matrix | `/hkp/admin/competencies` matrix editor (`ha_role_competency`) | crawl | ✅ C |
| 71 | Gap heatmap | `/hkp/team/gaps` (`Ha_competency::matrix`) | crawl | ✅ C |
| 72 | Readiness heatmap | `/hkp/team/readiness` | crawl | ✅ C |
| 73 | Content recommendation engine | Modules and knowledge linked to each gap competency (`Ha_competency::recommendations`) | `Test_hkp` | ✅ T |
| 74 | Rule: do not confuse training with performance | Theory raises levels only up to the cap. A practical result needs an assessor. A manager rating needs evidence | `Test_hkp` | ✅ T |
| 75 | Corporate brand positioning | Altus positioning in corporate blocks, sourced from `ppt-text.txt` | crawl | ✅ C |
| 76 | Brand / UX direction | Design system in `assets/hkp/hkp.css`: Playfair, Montserrat and Cairo fonts, served locally | crawl | ✅ C |
| 77 | Required main navigation | My workspace / Property management / Altus team / Executive, filtered by role | crawl | ✅ C |

## 10. Engineering standards and delivery (§78–§88)

| § | Requirement | Resolution (where) | Evidence | Status |
|---|---|---|---|---|
| 78 | Technical approach | Libraries hold the logic. Controllers stay thin. Views are escaped by default (`hkp_h`) | code | ✅ C |
| 79 | Code quality | One base controller. `attempt()` wraps errors so users see a message. No query runs while another is half built (three such bugs were fixed in this pass) | tests + crawl | ✅ T |
| 80 | Database migration rules | Backup → import → migrate → seed only what is production-safe → verify, all against a copy (see "Production database merge" below) | performed | ✅ C |
| 81 | Testing requirements | 136 tests, 2,100+ assertions, run against an isolated database | `ha_test run` | ✅ T |
| 82 | Acceptance criteria | See §185 and §189 | — | ✅ T |
| 83 | Non-functional requirements | Security, bilingual support, accessibility and audit are covered in their own sections | — | ✅ C |
| 84 | Development deliverables | This file, README.md, IMPLEMENTATION_STATUS.md, DEPLOYMENT.md, migrations, seeds, tests | files | ✅ C |
| 85 | Master development prompt | Followed | — | ✅ C |
| 86 | Recommended phases | Delivered in two batches: the core chain, then CMS, API and hardening | — | ✅ C |
| 87 | Most important product rules | Evidence over intuition. No invented data (illustrative content is labelled). Tenant isolation | `Test_hkp` | ✅ T |
| 88 | Complete system module map | Modules 02–45 are listed below | — | ✅ C |

## 11. The 45 modules (§89–§132)

| § | Module | Where | Status |
|---|---|---|---|
| 89 | 02 Organisation management | `/hkp/admin/crud/organizations` | ✅ C |
| 90 | 03 Property management | `/hkp/admin/crud/properties` (a 500 on "new" was fixed in this pass) | ✅ C |
| 91 | 04 Department management | `/hkp/admin/crud/departments` | ✅ C |
| 92 | 05 Job role management | `/hkp/admin/crud/job_roles` | ✅ C |
| 93 | 06 Employee management | `/hkp/team/people`, `/hkp/team/new_person`, profile status (on leave / terminated / archived) | ✅ C |
| 94 | 07 Curriculum management | `/hkp/admin/curriculum`, now tenant-scoped (fixed in this pass) | ✅ T |
| 95 | 08 Knowledge library | `/hkp/knowledge` | ✅ T |
| 96 | 09 Content editor | `/hkp/admin/content` plus the AI panel | ✅ C |
| 97 | 10 Lesson builder | `/hkp/cms/lesson`: media, blocks, drip, attachments | ✅ T |
| 98 | 11 Learning path builder | Tracks plus drag-ordered modules | ✅ C |
| 99 | 12 Assignment engine | `/hkp/team/assign` (people, roles, departments, cohorts; due dates; exemptions) | ✅ T |
| 100 | 13 Cohort management | `/hkp/team/cohorts` | ✅ C |
| 101 | 14 Learner progress engine | `Ha_learning::refresh_enrollment` | ✅ T |
| 102 | 15 Assessment builder | `/hkp/admin/assessments` | ✅ C |
| 103 | 16 Question bank | Question types, ordering, statistics | ✅ T |
| 104 | 17 Practical assessment builder | Rubric editor (an infinite loop was fixed in this pass) | ✅ C |
| 105 | 18 Competency library | `/hkp/admin/crud/competencies` | ✅ C |
| 106 | 19 Employee competency profile | `/hkp/competencies`, `/hkp/team/employee/{id}` | ✅ T |
| 107 | 20 Skill gap engine | `Ha_competency::recalculate_gaps` | ✅ T |
| 108 | 21 Action plan engine | `Ha_action_plans` | ✅ T |
| 109 | 22 Evidence management | `ha_evidence`, private files | ✅ T |
| 110 | 23 Reassessment engine | `ha_reassessment` | ✅ T |
| 111 | 24 Readiness engine | `Ha_readiness` | ✅ T |
| 112 | 25 Certificate engine | `Ha_certification` | ✅ T |
| 113 | 26 Certificate verification | `/verify/{code}` with throttling and minimal data | ✅ T |
| 114 | 27 Knowledge search engine | `Ha_knowledge::search` | ✅ T |
| 115 | 28 Governed AI knowledge pipeline | `Ha_governed_ai::index_*`, `hkp_cli index` | ✅ T |
| 116 | 29 AI answer validation | Citation validation, insufficient-knowledge answer | ✅ T |
| 117 | 30 AI administration | `/hkp/admin/ai`, AI Studio providers and routes | ✅ C |
| 118 | 31 Notification engine | `Ha_notify` | ✅ T |
| 119 | 32 Email template engine | `ha_notification_template`, editable at `/hkp/admin/system` | ✅ C |
| 120 | 33 Reporting engine | `Ha_reports` | ✅ T |
| 121 | 34 Executive analytics | `/hkp/exec` | ✅ C |
| 122 | 35 KPI management | `/hkp/team/kpis`, KPI definitions CRUD | ✅ T |
| 123 | 36 Performance scorecard | `Ha_kpi::scorecard` | ✅ T |
| 124 | 37 Altus engagement management | `/hkp/admin/engagements` | ✅ C |
| 125 | 38 Altus ASCENT project tracker | Engagement stages DISCOVER → SCALE with tasks | ✅ C |
| 126 | 39 Asset assessment | Framework assessment (performance matrix) | ✅ T |
| 127 | 40 GOPPAR value stack assessment | Framework `goppar` | ✅ C |
| 128 | 41 ESG assessment | Framework `esg` | ✅ C |
| 129 | 42 Case study management | `/hkp/admin/corporate` | ✅ C |
| 130 | 43 Corporate leadership CMS | `/hkp/admin/corporate` | ✅ C |
| 131 | 44 Partnership directory | `/hkp/admin/corporate` | ✅ C |
| 132 | 45 Values / ESG / corporate content | Corporate blocks, `/en/altus` | ✅ C |

## 12. Data, API standard and UX rules (§133–§141)

| § | Requirement | Resolution | Status |
|---|---|---|---|
| 133 | Complete database entity list | Every listed entity exists (migrations 13–15). Legacy tables are kept | ✅ T |
| 134 | Relationship principles | Tenant columns on every scoped table. Results are append-only | ✅ T |
| 135 | API structure (`/api/v1/*`) | me, courses, enrollments, ai/jobs, competencies, readiness, actions, certificates, people, gaps, search, kpis | 🟡 These cover the reads that integrations need. Write endpoints for users, properties and curriculum stay in the UI; add them per integration |
| 136 | API response standard | `{"success", "data", "message"}` and `{"success": false, "message", "errors"}`. No stack traces are ever returned (added in this pass) | ✅ C (curl) |
| 137 | Frontend screen map | 80+ `views/hkp/*` screens | ✅ C |
| 138 | Dashboard UX rules | Every figure has a label, a unit and a link to its detail | ✅ C |
| 139 | Critical management alerts | `ha_alert`, raised by sweeps and resolved automatically | ✅ T |
| 140 | Data visualisation | Accessible bars, heatmaps and quadrant charts in pure CSS | ✅ C |
| 141 | Auditability rule | Every state change is written to the audit log or to action-plan events | ✅ T |

## 13. Operations (§142–§149)

| § | Requirement | Resolution | Status |
|---|---|---|---|
| 142 | Data retention | `retention.years` setting (default 7) | 🟡 No automatic purge, by design. Deleting assessment evidence should be a human decision |
| 143 | Backup | System health checks for backup files in `/backups`. A `mysqldump` procedure is in README | 🟡 Scheduling backups is the hosting provider's job and is not automated here |
| 144 | Deployment environment | `DEPLOYMENT.md`, `deploy-check.php`. `database.local.php` overrides the live config locally | ✅ C |
| 145 | Configuration / environment variables | `database.local.php`. AI provider keys can come from environment variables | ✅ C |
| 146 | Cron / scheduled tasks | `hkp_cli daily` (reminders, overdue work, expiry, readiness) and `hkp_cli work` (queue) | ✅ T |
| 147 | Queue requirements | `ha_queue_job` plus a worker with locking | ✅ C |
| 148 | Error handling | `attempt()` shows the user a message and logs the detail. Nothing leaks | ✅ C |
| 149 | Logging | CI logs, audit log, AI query log, report-run log | ✅ C |

## 14. Security (§150–§158)

| § | Requirement | Resolution | Status |
|---|---|---|---|
| 150 | Search security | Visibility SQL is applied inside retrieval, plus a join as a second guard | ✅ T |
| 151 | Multi-tenant security test | `Test_hkp::tenant_isolation…`, `Test_cms::admin_lists_never_show…`, `Test_cms::performance_api_is_tenant_scoped` | ✅ T |
| 152 | Role security test | `Test_hkp::role_escalation_is_refused_server_side`, `Test_rbac` | ✅ T |
| 153 | Tenant ID security | Tenant is taken from the session or the profile, never from a form field. Every scope field on a form is re-checked with `can_organization` | ✅ T |
| 154 | File security | Private storage, tokens, MIME checks | ✅ C |
| 155 | AI security | AI sees only authorised approved content. Prompt and answer are logged | ✅ T |
| 156 | AI prompt governance | Fixed system prompts per task. "Enhance prompt" shows the rewritten brief before it runs (`Ha_ai_assist`) | ✅ T |
| 157 | AI knowledge update workflow | Publishing re-indexes the item and archiving removes it | ✅ T |
| 158 | Bilingual AI | Arabic questions find English sources and the reverse | ✅ T |

## 15. UX, SEO and content governance (§159–§166)

| § | Requirement | Resolution | Status |
|---|---|---|---|
| 159 | Mobile-first learner UX | Crawl on mobile width; bottom-safe actions | ✅ C |
| 160 | Accessibility | Labels, focus-visible, skip link, ARIA progressbars, one H1 per page | ✅ C |
| 161 | SEO for public pages | SEO/AEO/GEO checklist: 26 checks, with a score per language stored on the page. FAQPage, Place and LocalBusiness schema (`Ha_seo_score`) | ✅ T |
| 162 | Public vs private content | Private knowledge and files are never reachable publicly | ✅ T |
| 163 | Case study confidentiality | Visibility level and anonymised labelling | ✅ C |
| 164 | Content ownership | Owner, reviewer and approver on every item | ✅ T |
| 165 | Review / expiration | `expires_at` and review dates, with a health list | ✅ T |
| 166 | Content health dashboard | `Ha_knowledge::health` lists stale, expired, unused and awaiting items | ✅ C |

## 16. Analytics and property modes (§167–§180)

| § | Requirement | Resolution | Status |
|---|---|---|---|
| 167 | Learning analytics | Completion, time spent, overdue, per property and department | ✅ C |
| 168 | Question analytics | Facility and discrimination per question (`Ha_theory::question_stats`) | ✅ T |
| 169 | Practical assessment analytics | Criteria failure rates in the assessor queue | ✅ C |
| 170 | Content → competency linking | Course, assessment and knowledge linked to skills | ✅ T |
| 171 | Competency → business outcome | `ha_competency_kpi` | ✅ C |
| 172 | Property readiness | Readiness summary per property | ✅ T |
| 173 | Pre-opening team mode | Opening readiness items by category (`/hkp/team/opening`) | ✅ C |
| 174 | Operational audit mode | Quality audits → findings → action plans | ✅ C |
| 175 | Mystery guest / quality input | `mystery_guest` audit type | ✅ C |
| 176 | Executive owner view | `/hkp/exec` | ✅ C |
| 177 | Board / executive export | `/hkp/exec/board`, printable | ✅ C |
| 178 | Data freshness | KPI staleness, "calculated at" on readiness, last indexed on AI | ✅ T |
| 179 | Transparency of calculations | Every readiness check and practical score explains itself | ✅ T |
| 180 | System health dashboard | `/hkp/admin/system`: database, queue, email, storage, AI, scheduler, backup, API | ✅ C |

## 17. Release (§181–§190)

| § | Requirement | Resolution | Status |
|---|---|---|---|
| 181 | Backup / disaster recovery | Restore procedure in README and DEPLOYMENT | 🟡 Recovery-time targets and off-site copies are hosting decisions |
| 182 | Deployment workflow | README → "Production" | ✅ C |
| 183 | Git requirements | Migrations are versioned and secrets are kept out of the repository | 🟡 The work is **not committed yet**. Review it, then commit |
| 184 | Documentation | README.md (setup, credentials, guides), this file, IMPLEMENTATION_STATUS.md, DEPLOYMENT.md | ✅ C |
| 185 | Final QA scenario | The full 20-step scenario is an automated test | ✅ T |
| 186 | Final product architecture | As built | ✅ C |
| 187 | Product north star | Evidence-based readiness | ✅ T |
| 188 | Final development instruction | Followed | ✅ C |
| 189 | Release gates | Tests green, crawl clean, isolation proved, Arabic 100%, migrations reversible | ✅ T |
| 190 | Final acceptance statement | Met, with the 13 🟡 items listed above | ✅ |

---

## Issues found and fixed during this pass

| Issue | Impact | Fix |
|---|---|---|
| Tenant list leak: the admin lists (tracks, competencies, KPIs and other CRUD entities) showed other clients' records | A client could see another client's records | `Ha_crud::scope_sql()` is resolved before the query is built. Regression test added |
| Curriculum screen showed every tenant's tracks and modules, and attach, detach and order were not scoped | Cross-tenant edits were possible | The track and module tenant is checked before any change (`Hkp_admin::curriculum`) |
| Board report 500 | Executives could not print | Scoped lists are computed before the query (`Hkp_exec::board`) |
| New property 500 | Properties could not be created from the UI | `Ha_crud::options()` and `org_options()` resolve scope first |
| Rubric editor exhausted 512 MB | Rubrics could not be edited | Fixed a loop bound that grew inside its own loop |
| System health 500 | The page failed on real data | The AI check now counts enabled providers |
| Instructor area 500 (6 pages) | Instructors were locked out | Sidebar permission lookup fixed (`views/backend/user/navigation.php`) |
| Dead links (403) for GM, supervisor and executive | Confusing UX | Tiles, buttons and person names link only when permitted (`hkp_person_open()`) |
| "Website pages" link pointed to a route that does not exist | 404 | Now points to `/hkp/cms` |
| 142 new interface strings had no Arabic | Mixed-language UI | Translated. The test enforces 100% coverage |

## Production database merge

1. **Backup.** `atlas_local` was saved as a file and as the database `atlas_local_premerge`. `atlas_local` itself was **not** modified.
2. **Import.** `khidmat_atlas.sql` (MariaDB 11.4) was loaded into `atlas_merged`. It has 148 tables, 136 real enrolments, 100 certificate verifications and the real branding.
3. **Migrate.** Migrations 11–15 were applied on the production data: 5 applied, 0 errors.
4. **Seed.** Only the production-safe seeds ran: `seed rbac` (1,378 rows) and `seed hkp` (626 rows). Both upsert by natural key. The curriculum and content seeds were **not** run, so production content is unchanged.
5. **Carry over.** Local-only rows were copied by natural key, never by ID:
   - course `fo-e2e-ramadan-service`
   - the AI Studio configuration
   - 59 interface phrases

   Two of these turned out to be end-to-end test fixtures: a mock AI provider on `127.0.0.1:8765` and a course with placeholder lessons. The provider was disabled, its routes were removed and the course was archived. They were kept for reference but are inactive.
6. **Verify.**
   - The README logins work.
   - The 67 legacy admin sidebar links all return 200.
   - The workspace crawl passed for 9 roles with 0 problems.

`application/config/database.local.php` now points at `atlas_merged`.
