const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
if (!process.env.LOCALAPPDATA) throw new Error('Windows LOCALAPPDATA is required for private signing storage.');
const dir = path.join(process.env.LOCALAPPDATA, 'ALTUS', 'Signing', 'com.altusgulf.knowledge');
const info = path.join(dir, 'android-signing.json');
const key = path.join(dir, 'altus-release.p12');
if (!fs.existsSync(info) && fs.existsSync(path.join(root, '.credentials/altus-release.p12'))) {
  throw new Error('A legacy signing key exists in this project. Move its keystore and android-signing.json to the private ALTUS signing directory before building; never generate a replacement.');
}
fs.mkdirSync(dir, { recursive: true });
if (fs.existsSync(info)) {
  if (!fs.existsSync(key)) throw new Error('Existing signing metadata has no keystore. Restore the original keystore; do not replace it.');
  process.stdout.write('Using the existing ALTUS release signing key.\n');
} else {
  if (fs.existsSync(key)) throw new Error('Existing keystore has no signing metadata. Restore its original credentials.');
  const password = crypto.randomBytes(36).toString('base64url');
  const javaHome = process.env.JAVA_HOME;
  if (!javaHome) throw new Error('JAVA_HOME is required.');
  execFileSync(path.join(javaHome, 'bin', 'keytool.exe'), [
    '-genkeypair', '-noprompt', '-storetype', 'PKCS12', '-keystore', key,
    '-alias', 'altus-release', '-keyalg', 'RSA', '-keysize', '3072',
    '-validity', '10000', '-dname', 'CN=ALTUS Gulf, OU=Knowledge and Performance, O=ALTUS Gulf',
    '-storepass:env', 'ALTUS_SIGNING_TEMP_PASSWORD', '-keypass:env', 'ALTUS_SIGNING_TEMP_PASSWORD'
  ], { env: { ...process.env, ALTUS_SIGNING_TEMP_PASSWORD: password }, stdio: 'pipe' });
  fs.writeFileSync(info, JSON.stringify({ keystore: 'altus-release.p12', alias: 'altus-release', storePassword: password, keyPassword: password }, null, 2), { mode: 0o600, flag: 'wx' });
  process.stdout.write('Created a private ALTUS release signing key under LOCALAPPDATA/ALTUS/Signing. Back up this directory securely.\n');
}
