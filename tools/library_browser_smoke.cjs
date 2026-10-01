/* Read-only public browser checks. Does not run the site's E2E database setup. */
const { chromium } = require('../e2e/node_modules/@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

(async () => {
  const base = process.env.HKP_BASE_URL || 'http://localhost/atlas/atlas/';
  if (!/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\//.test(base)) throw new Error('Local site required');
  const browser = await chromium.launch({channel:'msedge',headless:true});
  const checks = [];
  try {
    for (const width of [1440,390]) {
      const context = await browser.newContext({viewport:{width,height:900}});
      const page = await context.newPage();
      for (const locale of ['en','ar','hi']) {
        const response = await page.goto(base + locale + '/courses',{waitUntil:'domcontentloaded'});
        if (response.status() !== 200) throw new Error(`${locale} listing: ${response.status()}`);
        const target = await page.locator('a[href*="/courses/"]').evaluateAll(nodes => {
          const entry = nodes.find(n=>n.href.includes('active-listening')) || nodes[0]; return entry ? entry.href : null;
        });
        if (!target) throw new Error(`${locale} listing has no course links`);
        await page.goto(target,{waitUntil:'domcontentloaded'});
        const result = await page.evaluate(() => ({title:document.title,language:document.documentElement.lang,direction:document.documentElement.dir,
          canonical:document.querySelector('link[rel="canonical"]')?.href,
          alternates:[...document.querySelectorAll('link[hreflang]')].map(n=>({language:n.hreflang,url:n.href})),
          schemas:[...document.querySelectorAll('script[type="application/ld+json"]')].map(n=>JSON.parse(n.textContent)),
          width:innerWidth,bodyWidth:document.documentElement.scrollWidth,
          robots:document.querySelector('meta[name="robots"]')?.content,
          text:document.body.innerText.slice(0,200)}));
        if (!result.canonical || !result.title || !result.schemas.length) throw new Error('Missing discovery metadata');
        if (locale==='ar' && result.direction!=='rtl') throw new Error('Arabic direction incorrect');
        if (result.alternates.some(a=>a.language==='hi')) throw new Error('Unreleased Hindi course advertised as complete');
        checks.push({width,locale,url:target,...result});
        await page.screenshot({path:path.resolve('backups/library-review/browser-'+locale+'-'+width+'.png'),fullPage:false});
      }
      for (const locale of ['ase','sr-Latn','fil']) {
        const response=await page.goto(base+locale+'/courses',{waitUntil:'domcontentloaded'});
        if (response.status()!==404) throw new Error('Pending language exposed: '+locale);
        checks.push({width,locale,status:404,pending:true});
      }
      await context.close();
    }
    fs.writeFileSync('backups/library-review/browser-smoke.json',JSON.stringify({generatedAt:new Date().toISOString(),checks},null,2));
    console.log('Passed '+checks.length+' public desktop/mobile checks');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e.message);process.exitCode=1;});
