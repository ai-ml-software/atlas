# ALTUS Gulf native mobile app

React Native / Expo SDK 57, TypeScript and Expo Router. Uses ALTUS brand assets and the existing hospitality platform's domain services. The website is not embedded in a WebView.

## Run

The app opens directly to email/password sign-in. It asks for email device confirmation at the existing device limit and an authenticator or recovery code when 2FA is enabled. People never configure URLs or keys. The production URL (`EXPO_PUBLIC_PLATFORM_URL`) is baked in. A matching configuration-only `altm_` app key is optional at build time; this release does not bundle the workstation's configuration key. After sign-in `/mobile_api/me` supplies current roles and permissions, which control tabs, home shortcuts and screens. The server independently enforces every permission and tenant boundary. Role guides: [mobile installation](../../docs/guides/MOBILE_INSTALLATION.md).

```powershell
cd 'C:\laragon\www\atlas\atlas\mobile app\altus-mobile'
npm.cmd ci
npm.cmd run web
```

Choose **Explore the demonstration** to review the product without credentials. Demonstration records are labelled and stored locally. Profile → workspace lets reviewers explore learner, supervisor, manager, instructor and administrator journeys. Profile → Settings switches English/Arabic and dark appearance.

For the exported browser preview, run `npm.cmd run export:web`, then `node scripts/serve.cjs`; open http://127.0.0.1:8083.

## Android APK

```powershell
# Open a new PowerShell window to pick up the installed Android tools on PATH.
adb devices
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-android-windows.ps1
```

The Windows wrapper builds in `D:\altus-build` to avoid the 260-character native object-path limit, while keeping the original project in place. Use `-BuildDirectory 'C:\altus-build'` if drive D is unavailable. It uses installed Android SDK tools and Android Studio's Java, generates Android through Expo config plugins, and creates a signed release APK with its JavaScript/assets bundled. Default architecture is ARM64, for modern Android phones. Use `-Architectures 'arm64-v8a,armeabi-v7a,x86,x86_64'` for all architectures. Use `-Bundle` for an AAB. Output: `../client-deliverables/android/`.

Signing credentials are private under `%LOCALAPPDATA%\ALTUS\Signing\com.altusgulf.knowledge`, outside the website/source/client directories and restricted to the Windows user. Back up this directory securely; future updates need the same key. Do not place it in the client pack. No store submission has been made.

On a connected authorized phone:

```powershell
adb install -r '..\client-deliverables\android\ALTUS-1.2.1-release.apk'
adb shell am start -n com.altusgulf.knowledge/.MainActivity
```

Alternatively, transfer the APK to the phone, open it and permit installation from that source when Android asks. A signed release build is not a claim that every production integration or device validation is complete.

## Live platform

Deploy the backend update package and run only migration `20260101000034` (`php index.php ha_cli migrate 20260101000034`). `POST /mobile_api/login` checks credentials, profile status and lockout (5 failures / 15 minutes per account, 20 per address). When required, `login_2fa` completes an email device challenge followed by the existing authenticator/recovery check. Challenges expire in five minutes and are single use. A session lasts seven days, is bound to password/email/MFA state, is limited to the native API and is revocable under Account security. The combined web/mobile device limit is enforced after confirmation; old sessions remain active until all required verification passes. Logout revokes the token. Password/email/MFA changes or suspension invalidate it on the next request. A configured browser captcha policy remains enforced and requires secure web verification until native captcha integration is added.

Read scopes: `profile:read`, `courses:read`, `enrollments:read`, `performance:read`, `knowledge:read`, `team:read`, `kpis:read`. Writes additionally require `mobile:write`. Scopes are transport capabilities; every native data route checks current user permissions and tenant scope. Never bundle a personal session/API key or pass it in an `EXPO_PUBLIC_*` variable. Native credentials use SecureStore; browser credentials stay in memory. Live account content is not restored into guest/demo sessions.

The platform URL comes only from `EXPO_PUBLIC_PLATFORM_URL` (HTTPS). For a hosted browser client, explicitly configure `HA_MOBILE_WEB_ORIGINS` on the server. Native requests do not use browser CORS.

Native sign-in and verification are implemented. Password recovery and account-security management use the existing secured web flows. Permissions refresh on foreground return and every minute while active; API checks apply immediately. Advanced administration opens the appropriate secure web console, with a separate web login. Other workflows without a live native contract still show an explicit unavailable state. Do not describe those as implemented production features.

## Verify and regenerate client materials

```powershell
npm.cmd run check
npx.cmd expo-doctor
npm.cmd run export:web
$env:ALTUS_PREVIEW_URL='http://127.0.0.1:8083'
node scripts/capture-and-test.cjs
python -X utf8 scripts/build-client-pack.py
```

Capture scripts use the existing root `e2e/node_modules/playwright`. Client generation requires Python PyMuPDF, Pillow and imageio-ffmpeg plus Windows speech. It produces the full PDF, actual screen captures, 1080p narrated MP4, subtitles and full language/country registries. No simulated screen artwork is used.

The isolated API check script requires the loopback test server and **atlas_hospitality_test**, never the working database:

```powershell
& 'C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe' -S 127.0.0.1:8099 -t 'C:\laragon\www\atlas\atlas' 'C:\laragon\www\atlas\atlas\e2e\support\router.php'
# In another terminal:
& 'C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe' scripts/api-security-check.php
```

See `../ARCHITECTURE.md`, `../SCREEN-INVENTORY.md` and `../FINAL-AUDIT.md` for the coverage and remaining release work.
