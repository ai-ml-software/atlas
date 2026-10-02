# ALTUS Gulf mobile product architecture

Source of truth: the user's brief, supplied ALTUS Gulf logo, color guidelines, mobile screenshot and eight-page reference PDF, plus the existing CodeIgniter HK&P platform. The reference website https://altusgulf.com/en was attempted but unavailable to the browsing tool. CHATGPT.md is a supplied development reference; it does not replace the user's request. Existing unrelated website changes are preserved.

## Product structure

Native Expo SDK 57, TypeScript and Expo Router. Five learner destinations: Home, Learn, Knowledge, Assistant, Profile. Progress, notifications, calendar, downloads, favorites and support sit one step from those destinations. Supervisors, managers, instructors and ALTUS administrators receive additional workspaces according to server permissions. No embedded website or WebView implements the app.

Knowledge → Application → Performance → Measurable improvement.

## Architecture

- `altus-mobile/src/app`: root Expo Router layout, onboarding entry and registered screens.
- `src/components`: accessible themed text, buttons, fields, cards, course cards, badges, headers, progress and state components.
- `src/features`: native onboarding, learner, knowledge, management, administration and support views.
- `src/domain`: typed screen registry, product entities and explicitly isolated demonstration fixtures.
- `src/state`: persisted local preferences and demonstration transactions; secure native credential storage; no secrets bundled with the app.
- `src/services`: one authenticated, timed API client; server errors and permission failures remain visible, never replaced with demonstration data.
- `src/i18n`: English/Arabic catalogues, direction-aware layouts, all 184 ISO 639-1 languages plus two platform additions, and all ISO 3166-1 countries plus the platform's region; untranslated languages use explicitly disclosed English fallback.
- `application/controllers/Mobile_api.php`: additive mobile API using existing authentication, live permissions, tenant scope and domain libraries. Existing `/api/v1` remains unchanged.

## Branding

Use the supplied palm-and-wordmark, already prepared by the website in `uploads/system/altus-*.png`. Cream #F7F5F1; ivory #FAF7F2; copper #C45B2F; sand #D9C6A3; slate #5B6775; charcoal #2A2F35; emerald #2E7D5A. Photography comes from existing website assets with its credits retained. Editorial serif headings, restrained data typography, open space and copper calls to action. Dark mode uses the same semantic tokens. Tablet layouts cap reading width and permit wider grids.

## Identity and tenancy

Live identity comes from a personal API credential owned by the signed-in user; credentials are never supplied by demonstration role selection. Live roles and permissions come from `Ha_auth`, not a selected screen or local storage. All property, people, course, document and reporting access is checked on the server. Selecting a property changes display context only within authorized properties; it grants no permission. New native password/session flows must preserve web MFA, device and anti-abuse policy before release. The current web account-security flow can issue/revoke scoped credentials.

## Backend mapping

Existing services cover learning plans/enrolment, drip and prerequisite rules, lesson progress, theory/practical assessment, SOP visibility/versioning/acknowledgment, approved-source knowledge search, governed AI, competencies, readiness, action plans, certification, assignments, notifications, reporting and tenancy. Reuse these services rather than duplicating policy. Newly requested push delivery, offline media licenses/sync, ticketing, discussion moderation and QR event attendance need explicit server contracts and operational configuration; their release status must be recorded in the final audit.

## Journeys

1. Splash → welcome → product introduction → language → sign in/demo → first setup → organization → property → role confirmation → privacy → notification permission → setup success → home.
2. Home → learning plan → course → lesson → knowledge check → result → next lesson → progress → certificate. Passing rules belong to the server in live mode.
3. Knowledge → department/type filter → SOP → version metadata → procedure → acknowledgment → favorites/download manager.
4. Search → authorized source → assistant → cited source. Unsupported questions receive no invented policy.
5. Management → team → learner evidence → competency gap → assignment → report.
6. Administration → client/property → content → version → review decision → audit log.
7. Profile → support → new ticket → attachment → ticket detail; profile → language/theme/accessibility/security; sign-out requires confirmation.

## Verification and completion contract

Screen inventory records every route, role and practical benefit. Browser captures verify the actual Expo web build at multiple phone widths, in English and Arabic, including dark mode. Native iOS/Android QA, push/camera behavior and store submissions require devices/build credentials and cannot be claimed from browser testing. Client PDF/video use real app captures and identify demonstration data. Screen existence, functional demonstration behavior, live API integration and production readiness are distinct audit fields. No unverified feature or language is represented as production-complete.
