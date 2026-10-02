# ALTUS Android release APK

Built and verified on 2 October 2026.

| Item | Result |
|---|---|
| File | ALTUS-1.0.0-release.apk |
| Size | 61,477,976 bytes / 58.6 MiB |
| Application | ALTUS Knowledge & Performance |
| Package | com.altusgulf.knowledge |
| Version | 1.0.0 / version code 1 |
| Minimum Android | API 24 / Android 7.0 |
| Target Android | API 36 |
| Architecture | ARM64 (arm64-v8a) |
| Native build | Gradle assembleRelease passed; 569 tasks executed |
| Signature | Dedicated ALTUS RSA signing key; apksigner verification passed with APK Signature Scheme v2 |
| JavaScript | 2,861,568 bytes bundled inside APK; Metro is not required to open the installed app |
| Native libraries | 26 ARM64 libraries included |
| Source integrity | Every app source file matches the short-folder build source |
| Device validation | Not performed; user requested the APK file only |

SHA-256: `7d1ab03ab0107493a97516fc4ec08a28b9c95ee1cb4ade538996a2915af16bde`

Transfer the APK to an ARM64 Android phone and open it. Permit installation from the chosen file source if Android asks. Open **ALTUS Knowledge & Performance** and select **Explore the demonstration** to review without a platform credential. The app bundles its demonstration data, photography and fonts. Live workflows need the additive server API and an authorized personal key.

The signing key and credentials are stored privately under the Windows user's LocalAppData/ALTUS/Signing directory, outside the website, source and client pack. Future updates must use the same signing key. A reusable Windows build wrapper avoids long native paths by staging in D:\altus-build.

This is the signed release build of the current product preview. The production integrations and native acceptance checks listed in `../../FINAL-AUDIT.md` remain outstanding. No Google Play submission or iOS binary is included. Detailed checks accompany this report in `signature-verification.txt`, `package-metadata.txt` and `build-verification.json`.
