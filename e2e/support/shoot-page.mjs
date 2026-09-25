// node support/shoot-page.mjs <role> <path> <out.png> [width] [height] [fullPage]
// Screenshots a page as a signed-in role and prints JS errors, PHP errors and horizontal overflow.
import { chromium } from '@playwright/test';
const [role, path, out, w = '1440', h = '900', full = '1'] = process.argv.slice(2);
const browser = await chromium.launch({ channel: 'msedge' });
const ctx = await browser.newContext({ storageState: role === 'guest' ? undefined : `.auth/${role}.json`, baseURL: 'http://localhost/atlas/atlas/',
  viewport: { width: +w, height: +h }, deviceScaleFactor: 1 });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push('js: ' + e.message));
const res = await page.goto(path, { waitUntil: 'networkidle' });
await page.waitForTimeout(600);
const body = await page.locator('body').innerText();
if (/A PHP Error was encountered|Fatal error|Database Error/.test(body)) errors.push('php error on page');
const [sw, cw] = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth]);
const offenders = sw > cw ? await page.evaluate(() => { const vw = document.documentElement.clientWidth; return [...document.querySelectorAll('body *')].filter((e) => { const r = e.getBoundingClientRect(); return r.right > vw + 1 && r.width > 0 && getComputedStyle(e).position !== 'fixed'; }).slice(0, 12).map((e) => e.tagName.toLowerCase() + '.' + [...e.classList].join('.') + '#' + e.id + ' w=' + Math.round(e.getBoundingClientRect().width)); }) : [];
await page.screenshot({ path: out, fullPage: full === '1' });
console.log(JSON.stringify({ status: res.status(), url: page.url(), title: await page.title(), overflow: sw - cw, offenders, errors }));
await browser.close();
