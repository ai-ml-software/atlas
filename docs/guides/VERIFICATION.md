# Mobile settings, connection and training verification

Verified locally on 3 October 2026. Working database: `atlas_merged`; destructive test rebuilding is restricted to the `atlas_hospitality_test` namespace. The full PHP regression run used `atlas_hospitality_test_mobile_fix`, and browser/recording accounts used `atlas_hospitality_test`. No production deployment or remote cloud build was performed.

## Passing checks

- Full PHP regression suite: **241 tests passed, 4,413 assertions**, including authentication, tenant boundaries, learning history, SOP governance, publisher/MCP contracts and mobile configuration.
- Mobile TypeScript and Expo lint: passed. **8 unit tests** passed for role mapping, cache isolation, malformed payload rejection, version/feature rules, QR parsing and configuration responses.
- **6 actual browser integration tests** passed: admin menu visibility and active state, desktop/mobile Arabic layout, tenant/instructor/learner denial of global settings, native configuration CORS and ETag exposure, rejection of untrusted origins, app/admin connection tests, revoked keys, Save/Reset, visible Settings/key options, password-confirmed own learner key creation and actual Expo sign-in/profile workspaces for learner, administrator and instructor. Fixture keys were revoked afterwards.
- A real Expo setup recording successfully loaded and tested the working Laragon configuration endpoint using the configured local app-only key. No personal sign-in key was embedded in `.env` or the release build.
- Windows signed release build succeeded. APK package `com.altusgulf.knowledge`, version **1.0.1**, versionCode **2**, architecture **arm64-v8a**. `apksigner verify --verbose` passed with APK v2 signing and one signer.
- The actual packaged Android network XML was decoded using `aapt2`. Global plain HTTP is disabled; only localhost, 127.0.0.1 and 10.0.2.2 are allowed. Production hosts require TLS.
- English/Arabic written guides were exported as printable HTML and A4 PDFs. The source inventory accounts for **all 110 registered mobile screens** and distinguishes live/shared routes from demonstration-only entries.
- Narrated MP4 walkthroughs use actual browser recordings and isolated sample accounts, with English synthetic voice, on-screen chapter explanations and caption tracks. All recorded page visits are checked for HTTP 200 and PHP rendering failures.
- All four final MP4s were fully decoded with no errors and contain H264 video plus AAC narration. The player passed desktop/mobile layout, all **91** role-chapter links, actual seek/play and eight printable-guide download checks. Durations: admin **13:58**, student **3:07**, instructor **4:41**, mobile setup **1:32**.

## Recordings

The administrator recording covers **57** visible destinations. One slow library page initially exceeded the recorder timeout. The first 45 completed chapters were retained, their on-screen captions were used to recover timestamps at three-second intervals, and the remaining 12 chapters were recorded after raising the navigation timeout. This is a joined actual screen recording; recovered chapter boundaries can differ by a few seconds. Subsequent captures persist a checkpoint after every chapter.

The student recording covers **14** visible destinations. The instructor recording covers **20** visible destinations. The mobile setup recording shows **5** steps including the real configuration connection test. The video player provides chapter navigation; raw capture/narration work files are retained privately under `application/logs/guide-recordings`.

## Configuration-dependent checks and limits

No connected physical Android device was available. Native camera/QR permission handling, device-level SecureStore behaviour and the installed APK's complete device journey still require device validation. An iOS binary, TestFlight, app stores and production `altusgulf.com` endpoint rollout were not tested from this Windows environment. A local app key does not work against a separate production database.

The current mobile source intentionally keeps some modules in demonstration mode: native content administration, live support/discussions/attendance, account-bound encrypted offline media/progress synchronisation and push delivery are not complete live services in this release. Their routes show an explicit unavailable state for live accounts. The feature inventory and role guides state these limits; registered screens are not presented as completed live CRUD.

AI output requires a configured live provider/model; tests use controlled providers where stated in the main publisher acceptance report. MCP remains behind its separate feature switch until the gateway and OAuth deployment are configured. The existing [publisher acceptance report](../../ALTUS_AI_PUBLISHER_ACCEPTANCE_REPORT.md) and [MCP runbook](../MCP_GATEWAY_RUNBOOK.md) record the gateway/SDK checks and setup requirements.

The local `.env` and `ha_mobile.local.php` are ignored by Git. The app configuration key is public-only by design; personal user keys, two-factor recovery codes and signing secrets are excluded from public build variables and recordings. The private signing key stays in the existing protected signing directory.

Apache access checks found browser-test session files publicly retrievable by path. The source-protection rule now blocks `e2e` and `mobile app/altus-mobile` as well as the existing protected source directories, case-insensitively. Follow-up checks return 403 for session/mobile source files while the signed APK, guide HTML and PDFs remain available.
