# ALTUS Gulf native mobile app

React Native / Expo SDK 57, TypeScript and Expo Router. Uses ALTUS brand assets and the existing hospitality platform's domain services. The website is not embedded in a WebView.

## Run

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
adb install -r '..\client-deliverables\android\ALTUS-1.0.0-release.apk'
adb shell am start -n com.altusgulf.knowledge/.MainActivity
```

Alternatively, transfer the APK to the phone, open it and permit installation from that source when Android asks. A signed release build is not a claim that every production integration or device validation is complete.

## Live platform

Deploy the additive `application/controllers/Mobile_api.php` with the `mobile:write` scope entry in `application/libraries/Ha_api_keys.php`. Existing web routes and services remain intact. Use a personal API key issued by the existing web Account Security flow, with only required scopes. The owner must also hold the corresponding live permissions. Revocation and account suspension take effect server-side.

Read scopes: `profile:read`, `courses:read`, `enrollments:read`, `performance:read`, `knowledge:read`, `team:read`, `kpis:read`. Writes additionally require `mobile:write`. Never bundle a key or pass one in an `EXPO_PUBLIC_*` variable. Native credentials use SecureStore; browser credentials stay in memory. Live account content is not restored into guest/demo sessions.

Public endpoint configuration is `EXPO_PUBLIC_PLATFORM_URL`, or enter the HTTPS platform URL on Sign In. Copy `.env.example` to `.env.local` only for public configuration. For a hosted browser client, explicitly configure `HA_MOBILE_WEB_ORIGINS` on the server. Native requests do not use browser CORS.

Native username/password, reset and OTP screens currently direct account management to the existing secured web flows. A first-class mobile session/MFA contract remains release work. Live access does not use demonstration role selection.

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
