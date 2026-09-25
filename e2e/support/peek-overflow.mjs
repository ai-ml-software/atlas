// node support/peek-overflow.mjs <role> <path> : lists elements that stick out of a 390px viewport
import { chromium } from '@playwright/test';
const [role, path] = process.argv.slice(2);
const browser = await chromium.launch({ channel: 'msedge' });
const ctx = await browser.newContext({ storageState: `.auth/${role}.json`, baseURL: 'http://localhost/atlas/atlas/', viewport: { width: 390, height: 844 }, hasTouch: true });
const page = await ctx.newPage();
await page.goto(path);
const out = await page.evaluate(() => {
  const vw = document.documentElement.clientWidth;
  const res = [];
  for (const el of document.querySelectorAll('body *')) { if (el.closest('aside.hkp-side')) continue;
    const r = el.getBoundingClientRect();
    if (r.right > vw + 1 && r.width > 0) {
      const cs = getComputedStyle(el);
      res.push(`${el.tagName.toLowerCase()}.${[...el.classList].join('.')} right=${Math.round(r.right)} w=${Math.round(r.width)} pos=${cs.position} minw=${cs.minWidth} ws=${cs.whiteSpace}`);
    }
  }
  return { dir: document.documentElement.dir, vw, sw: document.documentElement.scrollWidth, res: res.slice(0, 25) };
});
console.log(JSON.stringify(out, null, 1));
await browser.close();
