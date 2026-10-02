const { withAppBuildGradle } = require('expo/config-plugins');
// Keep generated Android sources reproducible. Secrets are supplied at build time.
module.exports = function withReleaseSigning(config) {
  return withAppBuildGradle(config, result => {
    const marker = '// ALTUS local release signing';
    const source = result.modResults.contents.split(marker)[0].trimEnd();
    result.modResults.contents = source + '\n\n' + marker + `
android {
    signingConfigs {
        altusRelease {
            def keystorePath = System.getenv("ALTUS_ANDROID_KEYSTORE")
            if (keystorePath) {
                storeFile file(keystorePath)
                storePassword System.getenv("ALTUS_ANDROID_STORE_PASSWORD")
                keyAlias System.getenv("ALTUS_ANDROID_KEY_ALIAS")
                keyPassword System.getenv("ALTUS_ANDROID_KEY_PASSWORD")
            }
        }
    }
    buildTypes {
        release {
            signingConfig signingConfigs.altusRelease
        }
    }
}
`;
    return result;
  });
};
