# ALTUS Android release 1.2.1

Built and verified on 4 October 2026. This report supersedes earlier build details.

- APK: ALTUS-1.2.1-release.apk; version code 5.
- Package: com.altusgulf.knowledge.
- Size: 106625498 bytes / 101.7 MiB.
- Android 7+ (minimum API 24), target API 36.
- Architectures: ARM64, ARMv7 and x86_64.
- Signature: APK Signature Scheme v2 verified; RSA 3072. The certificate matches release 1.2.0.
- JavaScript: 2887044 bytes bundled; 78 native libraries. No Metro or Expo Go is needed.
- All src files and app.json match the short-folder build source.
- No workstation configuration key, account password or personal token is bundled.
- TypeScript/lint and 11 access/config tests passed; Expo Doctor 21/21.
- Authentication service: 10 tests, 50 assertions. Existing key/2FA regression: 15 tests, 73 assertions.
- Actual login/role HTTP and browser checks: 33. API/assessment security regression: 20.
- UI: 220 English/Arabic captures across all 110 registered routes, zero browser runtime errors or horizontal overflow.
- Native physical-device testing has not been performed.

SHA-256: 115ac4c4d657480edf6a262f65a2d8c3821f33cd1e1fac5c8ef363b23a0798ac

Transfer the APK to the phone, open it and permit installation from that source. The app opens email/password login; server-assigned permissions control tabs, shortcuts and screens. Device/email confirmation and authenticator/recovery verification are enforced when required.

The public website still returned the old API-key-only login response on 4 October. Deploy ../ALTUS-Mobile-Backend-1.2.1.zip and migration 20260101000034 there before native password login can work. The local migration has been applied. No production deployment was performed.

Advanced administration opens the secure web console and requires a separate web login. Other unfinished native integrations are recorded in ../../FINAL-AUDIT.md; this build must not be described as a fully released enterprise platform.

The client PDF and 1080p narrated video were regenerated with the current 110 English/Arabic screen captures. The video covers every registered screen and is 12 minutes 49 seconds.

Signing credentials remain privately outside the workspace under the Windows user LocalAppData/ALTUS/Signing directory. Preserve that key for updates. No iOS binary or store submission is included.
