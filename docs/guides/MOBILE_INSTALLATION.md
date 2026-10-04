# ALTUS mobile installation and sign-in

The signed Android release is **1.2.1**, version code **5**. Transfer `mobile app/client-deliverables/android/ALTUS-1.2.1-release.apk` to the phone, open it and allow installation from that source when Android asks. Android 7 or later is required. The release includes ARM64, ARMv7 and x86_64 libraries and bundled app code/assets; Expo Go and a development server are unnecessary.

Open the application and sign in using your ALTUS email address and password. No server URL, API key or role selection is required. The server supplies your roles and permissions. Learners, supervisors, managers, instructors and administrators receive their permitted tabs and workspace shortcuts. Advanced administration opens the appropriate secure web console, where you sign in separately. Other unfinished native integrations remain explicitly identified; do not market every registered screen as a deployed live service.

If the allowed device count is reached, enter the email code to confirm the new device. If two-factor authentication is enabled, also enter an authenticator or recovery code. Each challenge expires after five minutes. Old devices stay signed in until required checks pass. Sessions last seven days and are invalidated by password, email or MFA changes and account suspension. Sign out revokes the current session. A configured browser captcha policy requires secure web verification until native captcha support is added.

Use **Forgot your password?** to open the platform's existing secure recovery flow. Profile → Settings switches English/Arabic without signing out. Demonstration mode is explicitly labelled and never grants access to a real account or tenant.

## Backend rollout

The APK connects to `https://altusgulf.com`. On 4 October 2026 the public `/mobile_api/login` endpoint still returned the older API-key-only response. Native password sign-in requires deploying the supplied **ALTUS-Mobile-Backend-1.2.1.zip** to that website and applying the additive migration:

```shell
php index.php ha_cli migrate 20260101000034
```

Back up the deployed source and database first. Review the package against any server-specific changes. The package contains no database credentials, test accounts, signing key or AI-provider secrets. It requires the existing ALTUS/CodeIgniter authentication, knowledge and learning libraries. The migration creates mobile session/challenge tables and retires earlier unbound mobile sessions, requiring those devices to sign in again. HTTPS and the existing device-verification email delivery must work on the server.

## Developer build and verification

```powershell
cd 'C:\laragon\www\atlas\atlas\mobile app\altus-mobile'
npm.cmd ci
npm.cmd run check
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-android-windows.ps1 -Architectures 'arm64-v8a,armeabi-v7a,x86_64'
```

The build uses the preset HTTPS URL. An optional `EXPO_PUBLIC_ALTUS_APP_KEY` may read the same backend's public remote configuration; people never enter it. A workstation-generated key cannot authenticate against a separate production database. Never bundle a personal token, password, AI-provider key or signing secret. The signing key lives outside the repo under `%LOCALAPPDATA%\ALTUS\Signing\com.altusgulf.knowledge`; retain it securely for upgrades.

The Windows wrapper stages native sources in `D:\altus-build` to avoid native path-length limits. TypeScript, lint and configuration/access tests run with `npm.cmd run check`. `scripts/check-login.cjs` checks actual HTTP login and role flows using only `atlas_hospitality_test`; `ha_test run mobile_login` checks MFA/device/session rules in a protected test database. These browser/service tests do not substitute for physical Android/iOS testing. iOS was not built on this Windows host.

## العربية

انقل ملف APK إلى الهاتف وافتحه لتثبيت التطبيق. يفتح التطبيق شاشة تسجيل الدخول مباشرة. استخدم بريدك الإلكتروني وكلمة المرور في ألتوس؛ لا تحتاج إلى إدخال رابط الخادم أو مفتاح API أو اختيار دورك. يحدد الخادم الصلاحيات والشاشات المتاحة لحسابك.

عند بلوغ حد الأجهزة، أدخل رمز التحقق المرسل إلى بريد الحساب. إذا كان التحقق بخطوتين مفعلاً، أدخل أيضاً رمز تطبيق المصادقة أو رمز الاسترداد. يمكنك تغيير اللغة من الملف الشخصي ← الإعدادات دون تسجيل الخروج. تستخدم أدوات الإدارة المتقدمة لوحة الويب الآمنة، وتتطلب تسجيل دخول منفصلاً.

يتطلب تسجيل الدخول بكلمة المرور نشر تحديث الخادم المطابق على الموقع العام. بيانات العرض التوضيحي ليست بيانات حسابات حقيقية، ولا تمنح صلاحيات للوصول إلى معلومات الفنادق.
