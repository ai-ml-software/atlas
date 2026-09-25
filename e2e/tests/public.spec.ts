import { test, expect, open } from '../support/fixtures';


test.describe('Public website (signed out)', () => {
  test('root redirects to a language and the home page renders in English and Arabic', async ({ page }) => {
    await page.goto('');
    await expect(page).toHaveURL(/\/(en|ar)\/?$/);
    await open(page, 'en');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('h1')).toHaveCount(1);
    await open(page, 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  });

  const pages = ['about', 'courses', 'programs', 'learning-paths', 'hospitality-topics', 'sop', 'articles', 'certificates',
    'hotels', 'contact', 'privacy', 'terms', 'credits', 'altus', 'altus/case-studies'];
  for (const p of pages) {
    test(`public page /${p} renders in both languages with SEO tags`, async ({ page }) => {
      for (const loc of ['en', 'ar']) {
        await open(page, `${loc}/${p}`);
        await expect(page.locator('h1').first()).toBeVisible();
        await expect(page).toHaveTitle(/\S/);
        await expect(page.locator('link[rel=canonical]')).toHaveAttribute('href', /^https?:\/\//);
      }
    });
  }

  test('SEO head: hreflang pairs, meta description, JSON-LD on the home page', async ({ page }) => {
    await open(page, 'en');
    await expect(page.locator('link[rel=alternate][hreflang=ar]')).toHaveCount(1);
    await expect(page.locator('link[rel=alternate][hreflang=en]')).toHaveCount(1);
    await expect(page.locator('meta[name=description]')).toHaveAttribute('content', /.{20,}/);
    const ld = await page.locator('script[type="application/ld+json"]').allTextContents();
    expect(ld.length).toBeGreaterThan(0);
    for (const block of ld) expect(() => JSON.parse(block)).not.toThrow();
  });

  test('crawler files: robots.txt, sitemap index, academy/image/LMS sitemaps, llms.txt', async ({ request }) => {
    const rb = await request.get('robots.txt');
    expect(rb.headers()['content-type']).toContain('text/plain');
    const robots = await rb.text();
    expect(robots).toMatch(/^User-agent:/m);
    expect(robots).toMatch(/Disallow: \/admin/);
    expect(robots).toMatch(/Sitemap: .*sitemap\.xml/);

    const idx = await request.get('sitemap.xml');
    expect(idx.headers()['content-type']).toContain('xml');
    const index = await idx.text();
    expect(index).toContain('<sitemapindex');
    for (const m of ['academy-sitemap.xml', 'image-sitemap.xml', 'lms-sitemap.xml']) {
      expect(index).toContain(m);
      const r = await request.get(m);
      expect(r.status(), m).toBe(200);
      expect(await r.text(), m).toContain('<urlset');
    }
    const academy = await (await request.get('academy-sitemap.xml')).text();
    expect(academy).toContain('hreflang="ar"');               // bilingual alternates

    for (const f of ['llms.txt', 'llms-full.txt']) {
      const r = await request.get(f);
      expect(r.headers()['content-type'], f).toContain('text/plain');
      expect(await r.text(), f).toMatch(/^# /);
    }
  });

  test('unknown addresses return a real 404, not a soft 404', async ({ request }) => {
    expect((await request.get('this-page-does-not-exist')).status()).toBe(404);
    expect((await request.get('en/this-page-does-not-exist')).status()).toBe(404);
  });

  test('browse the course catalogue and open a course detail page', async ({ page }) => {
    await open(page, 'en/courses');
    const first = page.locator('a[href*="/en/courses/"]').first();
    await expect(first).toBeVisible();
    const href = await first.getAttribute('href');
    await open(page, href!.replace(/^.*\/atlas\/atlas\//, ''));
    await expect(page.locator('h1')).toBeVisible();
  });

  test('language switch lands on the translated page', async ({ page }) => {
    await open(page, 'en/about');
    await page.locator('details[data-ha-langmenu] > summary').first().click();   // the switcher is a dropdown
    await page.locator('a[data-ha-lang-switch][hreflang=ar]').first().click();
    await expect(page).toHaveURL(/\/ar\//);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  });

  test('public search returns results', async ({ page }) => {
    await open(page, 'en/search?q=check');
    await expect(page.locator('main')).toContainText(/check/i);
  });

  test('certificate verification: an issued certificate verifies, a fake code does not', async ({ page }) => {
    await open(page, 'verify/NOT-A-REAL-CODE-000');
    await expect(page.locator('body')).toContainText(/not found|not valid|could not|no certificate|invalid/i);
    await open(page, 'verify/E2E-VERIFY-0001');
    await expect(page.locator('body')).toContainText('E2E-FOA-2026-000001');
    await expect(page.locator('body')).toContainText(/valid|verified/i);
    await expect(page.locator('body')).not.toContainText('@');        // minimal data: no email disclosed
  });

  test('contact form rejects an empty submission', async ({ page }) => {
    await open(page, 'en/contact');
    const form = page.locator('form').filter({ has: page.locator('textarea') }).first();
    await expect(form).toBeVisible();
    await form.locator('button[type=submit], button:not([type])').first().click();
    // Either the browser blocks it (required fields) or the server returns an error; it must not succeed.
    await expect(page.locator('body')).not.toContainText(/thank you|received your message/i);
  });
});
