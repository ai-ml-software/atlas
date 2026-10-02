# ALTUS mobile implementation audit — 2 October 2026

The delivered code is a native mobile product preview with working demonstration journeys and an additive live API. It is not yet a fully deployed production platform. Screen coverage, functional demonstration behavior, live integration and native validation are separate facts.

## Delivered

- 110 registered routes covering access, learning, knowledge, performance, management, administration, property context and support.
- Branded mobile components, hospitality photography with retained credits, English/Arabic UI, direction-aware navigation, dark mode and accessibility preferences.
- Working local onboarding, lesson/assessment/result, SOP acknowledgement, favorites, saved reading, support requests, discussions, drafts and role previews. Local records are explicitly demonstration data.
- Live identity, bounded courses/plans, course/lesson access, authorized media links and native video/audio controls, progress tracking, server-graded assessments, approved documents/acknowledgements, source-led search and governed AI, people, competencies, readiness, gaps, actions, certificates, KPIs, notifications and authorized assignments.
- Live detail views for learners, certificates, actions and notifications. Server permissions and tenant boundaries remain authoritative.
- 69-page client PDF explaining every screen in English and Arabic; narrated 1080p MP4 covering all 110 screen chapters; subtitles; complete machine-readable locale/country catalogs.

## Evidence

- TypeScript and Expo lint: passed.
- Expo Doctor: 21/21 checks passed again after adding Android release signing and system UI configuration.
- Browser audit: 220 captures, all 110 routes in English and Arabic; no captured runtime errors or horizontal overflow. Twelve journey/layout checks include onboarding, course/quiz/result persistence, SOP confirmation/favorite/saving, local support ticket creation, language/theme switching, role restriction, 320/360/430/768 widths and dark appearance.
- Isolated HTTP checks: 20 passed against `atlas_hospitality_test`, including missing/revoked credentials, scope checks, cross-tenant access/assignment denial, attempt ownership, hidden answer keys, server grading and duplicate submission rejection. Test credentials and created assessment attempts are removed. Derived test notifications/readiness remain within the isolated database.
- The existing working database and unrelated website changes were not edited by these checks.
- Device testing: no iOS or Android physical-device validation has been completed. Browser checks do not verify native camera, notifications, filesystem or playback behavior.
- Android artifact: signed ARM64 release APK built successfully; package/version/minimum SDK, embedded JavaScript/assets, source integrity and APK v2 signature verified. Bundled code and assets remove the Metro requirement. Physical runtime validation remains pending; the user requested the APK file only.
- Dependency audit: 17 findings (13 moderate, 4 high) remain in the Expo/router/build dependency tree after conservative updates. Forced audit suggestions downgrade the Expo major version and were not applied. Review vendor fixes and reproduce native/browser checks before public release.

## Language and country coverage

The language registry includes 186 entries: all 184 ISO 639-1 languages plus Filipino and Central Kurdish. The country registry has 250 entries, including all 249 ISO 3166-1 country/territory codes and the platform's additional region. English and Arabic interface translations are implemented. Other selections disclose English fallback. This does not claim translation into every living language, dialect or script variant, nor availability of translated learning content.

## Required production work

| Area | Remaining work |
|---|---|
| Authentication | Native password/session, reset and MFA integration preserving existing device, consent and anti-abuse policies; current live access uses scoped personal keys. |
| Learning delivery | Device validation of video/audio playback, resume, every assessment type, interruption/retry and timers; protected/private media delivery and PDF/interactive lesson rendering. |
| Offline | Encrypted account-bound content, licenses/expiry/revocation, media downloads, version reconciliation and idempotent progress synchronization. Current live reading copies are session-local; demonstration copies persist locally. |
| Push | Register device tokens securely, apply server preferences, deliver via configured providers, handle deep links and failed/revoked tokens. Local preference switches alone do not deliver push. |
| Support/collaboration | Server ticket/discussion contracts, attachment upload/storage, response/moderation workflows and property feature settings. Current records are local demonstration data. |
| QR | Event/attendance check-in with server-issued expiring tokens and duplicate protection; current camera/manual-code flow opens the official certificate verifier. |
| Administration | Native essential content review/approval, client/user management, version/archive actions, audit browsing, property-brand publishing and broader analytics. Current administration is a demonstration workspace. |
| White label | Retrieve and publish authorized property logo/colors/welcome content; current demonstration branding previews property name and accent locally. |
| Analytics | Replace generic live data rendering with approved localized reporting schemas and validate complete compliance/department/time-period calculations. Do not infer performance improvement solely from learning completion. |
| Stores | Native acceptance testing, signing/key custody, production endpoints/providers, privacy/store disclosures, iOS build and submission credentials, release review. No store release or production deployment was made. |

Live workflows that lack a native contract display an explicit unavailable state. No fabricated live success is returned. Some demonstration flows deliberately show local transactions; they must not be represented commercially as already deployed enterprise services.

## Handover

App source: `altus-mobile/`. Additive backend: `application/controllers/Mobile_api.php`; the only existing backend library change for this work is the `mobile:write` scope catalogue entry. Client artifacts: `client-deliverables/`. Architecture and the final per-route inventory accompany this audit. Android APK build/verification results are recorded separately in `client-deliverables/android/BUILD-REPORT.md` once produced.
