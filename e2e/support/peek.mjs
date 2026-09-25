// Debug helper: node support/peek.mjs <role> <path>  → prints status, h1 and hkp links on the page.
import { chromium } from '@playwright/test';
const [role, path] = process.argv.slice(2);
const browser = await chromium.launch({ channel: 'msedge' });
const ctx = await browser.newContext({ storageState: `.auth/${role}.json`, baseURL: 'http://localhost/atlas/atlas/' });
const page = await ctx.newPage();
const res = await page.goto(path);
console.log('status', res.status(), page.url());
console.log('h1', await page.locator('h1').allInnerTexts());
console.log('flash', await page.locator('.hkp-flash').allInnerTexts());
const main = await page.locator('main').innerText().catch(() => '');
console.log('main:', main.slice(0, 700).replace(/\s+/g, ' '));
console.log('links', await page.locator('main a').evaluateAll(as => as.map(a => a.getAttribute('href')).slice(0,15)));
await browser.close();
