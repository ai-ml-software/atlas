# ALTUS mobile app — sign-in, admin settings and release

The current 1.2.1 app opens directly to email/password sign-in. Server roles and permissions determine navigation and screens; there is no end-user URL, API-key or server setup screen. See [installation and login](guides/MOBILE_INSTALLATION.md). The public backend must receive the matching controller, session library, API-key library and migration `20260101000034` before the new APK can sign in. The following app keys are optional developer configuration credentials, not user login credentials.

App source: `mobile app/altus-mobile` (Expo SDK 57, Expo Router, TypeScript).
Bundle id / package: `com.altusgulf.knowledge`.

## 1. Admin: Mobile app settings

URL: `/hkp/admin/mobile`, directly visible as **Platform → Mobile app settings** and in the legacy administrator sidebar.
Viewing needs a system-scoped active account and `settings.view`; saving, generating, rotating and revoking keys also need `settings.update`. Property administrators cannot edit global app configuration.
Arabic UI strings are in `application/language/hkp/ar.php`.

| Section | What it controls in the app |
|---|---|
| Connection → API base URL | Where signed-out users point after loading config. Empty = keep the built-in/setup address. HTTPS only (http allowed for localhost/10.0.2.2). |
| Branding | App name EN/AR (logo accessibility label), primary/accent/background colours (used unless the user picked their own accent), logo URL (header), splash image URL (maintenance / update screens). The store icon and native launch screen are fixed per binary. |
| Modules shown | learning, knowledge, assessments, assistant, qr, notifications, team, performance, demo. Off = hidden from the tab bar and the screen shows "turned off by your administrator". Home/profile/settings are never hidden. |
| Language and support | Default language (applied on first launch only; `device` = phone language). Support email/phone/URL appear in Settings. |
| Versions and store links | Per platform: below `min_version` = blocking "Update required" screen; below `latest_version` = dismissible banner; store link opens from both. |
| Maintenance | Full-screen message (EN/AR) instead of the app, with Retry and Server setup. |

Save is a single, draft-free save. Each save bumps `version` and writes an `ha_audit_log` row (`entity_type = mobile_app`) with before/after.
Storage: `ha_mobile_config` (one row, JSON) and `ha_mobile_app_key` — migration `20260101000031_mobile_app_settings.php` (additive; `down()` drops both tables).

## 2. App keys (altm_…)

- **Generate** — name + platform; the full key `altm_<12 hex>_<40 chars>` is shown **once** together with a setup QR code. Only an HMAC of the secret is stored (same `Ha_crypto::hmac` as personal keys).
- **Rotate** — issues a new key with the same name/platform and revokes the old one immediately.
- **Revoke** — the key stops working at once.
- An app key only reads public remote config. People sign in with email/password; the backend issues an account-bound token automatically, which the app keeps in secure storage. Native session tokens cannot access `/api/v1/*`; ordinary integration keys retain their existing API contract.

## 3. Remote config endpoint

```
GET /api/v1/mobile/config
X-App-Key: altm_xxxxxxxxxxxx_xxxxxxxx…   (or Authorization: Bearer altm_…)
If-None-Match: "<etag>"                  (optional)
```

- 200 `{success:true, data:{version, updated_at, api_base_url, branding, features, default_language, support, platforms, maintenance}, message}` with `ETag` and `Cache-Control: private, max-age=300`.
- 304 when the ETag matches (weak `W/` validators are accepted too).
- 401 for a missing, wrong, revoked or personal key. 429 after 30 failures from one IP in 15 minutes. 405 for anything other than GET.
- Route: `application/config/routes.php` → `Mobile_config::index` (declared before the `api/v1/(.+)` catch-all).

```bash
curl -i -H "X-App-Key: altm_..." https://altusgulf.com/api/v1/mobile/config
```

## 4. How the app uses it

1. **Built-in defaults:** `EXPO_PUBLIC_PLATFORM_URL` (EAS env) → `app.json` `expo.extra.platformUrl` → `https://altusgulf.com`. You can also bake in an app key with `EXPO_PUBLIC_ALTUS_APP_KEY`. It ends up readable inside the binary, which is fine because it only unlocks public config. Never bake in an `ha_` key.
2. **Built-in connection:** the server URL and any matching optional configuration key are compiled by the developer. Setup QR codes remain part of the older admin workflow; the current application has no server-setup route or logo shortcut. Native session tokens use expo-secure-store (`WHEN_UNLOCKED_THIS_DEVICE_ONLY`); browser credentials stay in memory.
3. **At launch:** preferences and identity restore before server defaults apply. Cached public configuration is bound to the same URL and key prefix. Fetch uses `If-None-Match`; failures retain the matching cache and show the error. Refresh also runs on foreground return and every five minutes while active and online.
4. Code: `src/services/remoteConfig.ts` (pure helpers + fetch), `src/state/AppProvider.tsx` (`remote`, `feature()`, `setupServer`), `src/features/RemoteGate.tsx` (maintenance/update), `src/features/ServerSetup.tsx`, `src/app/server-setup.tsx`.

## 5. Verification

```powershell
# PHP (isolated test DB, never production)
$env:HA_TEST_DB='atlas_hospitality_test_mobile'
& 'C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe' index.php ha_test run 170_mobile
# App
cd 'mobile app\altus-mobile'
npm.cmd run check            # tsc + expo lint + node unit tests (remote configuration and role selection)
npx.cmd expo-doctor
npx.cmd expo export --platform android --platform ios --output-dir dist-native
```

## 6. Release — Android

Versions: bump `expo.version` (user-visible), and `android.versionCode` (integer, must increase every upload). `eas.json` uses `appVersionSource: "local"`; the production profile has `autoIncrement`.

**Signing (no secrets in git):** the upload keystore lives outside the repo in `%LOCALAPPDATA%\ALTUS\Signing\com.altusgulf.knowledge`. `plugins/withReleaseSigning.cjs` reads `ALTUS_ANDROID_KEYSTORE`, `ALTUS_ANDROID_STORE_PASSWORD`, `ALTUS_ANDROID_KEY_ALIAS` and `ALTUS_ANDROID_KEY_PASSWORD` at build time. `*.jks`, `.credentials/`, `/android` and `/ios` are git-ignored. Back up the keystore. Use Play App Signing, so this key is only the upload key.

Local build (Windows, verified toolchain):
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-android-windows.ps1            # signed APK
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-android-windows.ps1 -Bundle    # AAB for Play
```
Output: `mobile app/client-deliverables/android/`.

Cloud (run manually; this uploads to Expo):
```bash
eas login
eas env:create --environment production --name EXPO_PUBLIC_PLATFORM_URL --value https://altusgulf.com
eas credentials -p android                         # upload the existing keystore
eas build -p android --profile production          # AAB
eas submit -p android --profile production         # needs a Play service-account JSON (not in repo)
```

## 7. Release — iOS (needs EAS or a Mac; it cannot build on Windows)

```bash
eas build -p ios --profile production   # EAS creates/uses distribution cert + provisioning profile (Apple Developer account)
eas submit -p ios --profile production  # App Store Connect; needs the app record for com.altusgulf.knowledge
```
Bump `ios.buildNumber` for each upload. `ITSAppUsesNonExemptEncryption=false` is set (HTTPS only). The camera usage text is in English only. An Arabic `locales` entry in app.json made Android `lintVitalRelease` fail with ExtraTranslation, so it was removed. If you need it, add it later as an iOS-only InfoPlist.strings.

## 8. Store checklist

- [ ] Version / versionCode / buildNumber bumped; `min_version`/`latest_version` + store links set in `/hkp/admin/mobile` after the store release is live
- [ ] Production app key generated, put in the EAS env (or distributed by QR); old keys revoked
- [ ] Privacy policy URL (`/privacy`) and support URL; data-safety form (Play) / App Privacy (Apple): account email, learning progress; no tracking, no ads
- [ ] Screenshots: phone 6.7" + 5.5" (iOS), phone + 7"/10" tablet (Play), EN and AR
- [ ] Demo reviewer account (personal key with read scopes) in review notes; you can also switch the demo module on
- [ ] Permissions explained: camera (QR scanning only), notifications (Android 13+ prompt)
- [ ] Content rating questionnaire, target audience (adults / employees)
- [ ] Test on a physical Android device (release APK) and on TestFlight
