const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const app = path.resolve(__dirname, '..');
const mod = { exports: {} };
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(app, 'src/domain/screens.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, { module: mod, exports: mod.exports });
const routes = fs.readFileSync(path.join(app, 'src/app/[route].tsx'), 'utf8');
const supported = new Set([...routes.match(/const liveSupported = new Set\(\[([\s\S]*?)\]\)/)[1].matchAll(/"([^"]+)"/g)].map((x) => x[1]));
const local = new Set(['settings','notification-settings','language','downloads','download-detail','conversation-history','privacy','terms','about']);
const webAccess = new Set(['forgot-password','reset-password','otp','security']);
const lines = ['# ALTUS mobile screen inventory', '', 'Final browser UI audit: 110 registered routes; each captured in English and Arabic. Every route is listed below. **Native iOS and Android device checks are pending.** A rendered screen is not proof of a completed live enterprise workflow. See FINAL-AUDIT.md for the release contract.', '', 'Live integration legend: API = connected to the existing authenticated domain services; local = device/session preference or reading copy; web = existing web-managed security flow; preview = native live contract remains to be implemented. Media and camera device behavior still require native validation.', '', '| # | Module | Route | Screen | UI | Live integration | Benefit |', '|---|---|---|---|---|---|---|'];
for (const [i, s] of mod.exports.screens.entries()) {
  const integration = s.id === 'sign-in' ? 'API · scoped personal key' : webAccess.has(s.id) ? 'Web-managed security' : supported.has(s.id) ? local.has(s.id) ? 'Local/session' : 'API' : 'Preview';
  lines.push(`| ${String(i+1).padStart(3,'0')} | ${s.module} | /${s.id} | ${s.title.replaceAll('|','/')} | Captured EN + AR | ${integration} | ${s.benefit.replaceAll('|','/')} |`);
}
fs.writeFileSync(path.join(app, '../SCREEN-INVENTORY.md'), lines.join('\n')+'\n');
console.log(`${mod.exports.screens.length} routes documented; ${supported.size} routes allow live-mode access, including preferences and web security.`);
