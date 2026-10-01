import { test, expect, open } from '../support/fixtures';

/**
 * Altus Gulf corporate site: the Lovable menu (with "Altus Knowledge and
 * Performance"), the pages built from the 2026 Corporate Profile in English and
 * Arabic, the footer, and the brand assets.
 */
const MENU_EN = ['About', 'Services', 'Altus Knowledge and Performance', 'Ascent', 'Market', 'Case Studies', 'Leadership', 'Contact'];
const MENU_AR = ['من نحن', 'الخدمات', 'Altus للمعرفة والأداء', 'مسار الارتقاء', 'السوق', 'دراسات الحالة', 'القيادة', 'تواصل معنا'];
const PAGES: Record<string, { h1: RegExp; text: RegExp; ar: RegExp }> = {
  'about-altus': { h1: /About Altus Gulf/, text: /Owner-Side Discipline/, ar: /انضباط في صف المالك/ },
  'services': { h1: /Advisory Services/, text: /Hotel Development & Owner Representation/, ar: /تطوير الفنادق وتمثيل المالك/ },
  'knowledge-performance': { h1: /Altus Knowledge and Performance/, text: /Governed AI assistant/, ar: /مساعد ذكاء اصطناعي محكوم/ },
  'ascent': { h1: /Ascent/, text: /Discover[\s\S]*Assess[\s\S]*Design[\s\S]*Transform[\s\S]*Optimise[\s\S]*Scale/, ar: /الاستكشاف[\s\S]*التوسّع/ },
  'market': { h1: /Market Opportunity/, text: /362K/, ar: /362 ألف/ },
  'case-studies': { h1: /Illustrative Case Studies/, text: /Flagship Resort Turnaround/, ar: /إعادة إصلاح أداء منتجع رئيسي/ },
  'leadership': { h1: /Leadership/, text: /Islam Mahrous[\s\S]*Hussam Smadi/, ar: /إسلام محروس[\s\S]*حسام الصمادي/ },
};

test.describe('Altus Gulf corporate site', () => {
  test('the header menu is the Altus Gulf menu, in order, in both languages', async ({ page }) => {
    await open(page, 'en');
    await expect(page.locator('#ha-nav > ul > li:not(.ha-more) > a')).toHaveText(MENU_EN);
    await open(page, 'ar');
    await expect(page.locator('#ha-nav > ul > li:not(.ha-more) > a')).toHaveText(MENU_AR);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  });

  for (const [slug, c] of Object.entries(PAGES)) {
    test(`${slug}: English and Arabic pages carry the profile content`, async ({ page }) => {
      await open(page, `en/${slug}`);
      await expect(page.locator('h1')).toHaveText(c.h1);
      await expect(page.locator('main')).toContainText(c.text);
      await expect(page).toHaveTitle(/\| Altus Gulf$/);
      expect((await page.locator('meta[name=description]').getAttribute('content'))!.length).toBeGreaterThan(70);
      await expect(page.locator('link[rel=alternate][hreflang=ar]')).toHaveAttribute('href', new RegExp(`/ar/${slug}$`));
      await expect(page.locator('script[type="application/ld+json"]').first()).toBeAttached();
      await open(page, `ar/${slug}`);
      await expect(page.locator('main')).toContainText(c.ar);
      // Arabic was once cut mid-letter by a non-Unicode line split: no stray fragments of a word.
      await expect(page.locator('main')).not.toContainText(/^\s*ية\.\s*$/m);
    });
  }

  test('case studies are labelled illustrative and keep the disclaimer', async ({ page }) => {
    await open(page, 'en/case-studies');
    await expect(page.locator('.ag-case')).toHaveCount(4);
    await expect(page.locator('.ag-case').first()).toContainText('Illustrative case study');
    await expect(page.locator('main')).toContainText('individual results vary by asset, market, and execution');
  });

  test('profile placeholders are never published', async ({ page }) => {
    for (const p of ['en/about-altus', 'en/leadership', 'ar/about-altus']) {
      await open(page, p);
      await expect(page.locator('body')).not.toContainText(/\[placeholder\]|\[Office address\]|\[نص مؤقت\]|\[عنوان المكتب\]/);
    }
  });

  test('the footer keeps every academy link and adds the corporate group and founders', async ({ page }) => {
    await open(page, 'en/services');
    const foot = page.locator('footer.ha-foot');
    for (const path of ['courses', 'programs', 'learning-paths', 'certificates', 'sop', 'hospitality-topics', 'articles', 'verify', 'about', 'hotels', 'contact',
      'about-altus', 'services', 'knowledge-performance', 'ascent', 'market', 'case-studies', 'leadership']) {
      await expect(foot.locator(`a[href$="/en/${path}"]`).first(), path).toBeAttached();
    }
    await expect(foot.locator('.ha-foot__founder')).toHaveCount(2);
    await expect(foot.locator('a[href="https://wa.me/966500511994"]')).toBeAttached();
    await expect(foot.locator('a[href="tel:+201095556779"]')).toBeAttached();
    await expect(foot).toContainText(/Altus Gulf · All rights reserved\./);
  });

  test('brand assets: new logo, favicon, Inter and the Fraunces display face are served', async ({ page }) => {
    await open(page, 'en/about-altus');
    const logo = page.locator('.ha-mast__logo');
    await expect(logo).toBeVisible();
    expect(await logo.evaluate((i: HTMLImageElement) => i.naturalWidth / i.naturalHeight)).toBeGreaterThan(3);   // horizontal lockup
    for (const f of ['uploads/system/favicon.png', 'uploads/system/apple-touch-icon.png', 'assets/academy/fonts/inter-400-latin.woff2', 'assets/academy/fonts/fraunces-400-latin.woff2']) {
      expect((await page.request.get(f)).status(), f).toBe(200);
    }
    expect(await page.evaluate(() => getComputedStyle(document.body).fontFamily)).toMatch(/Inter/);
    expect(await page.locator('h1').evaluate((h) => getComputedStyle(h).fontFamily)).toMatch(/Canela|Fraunces/);
    // Signature copper is the primary action colour (deepened for AA contrast).
    expect(await page.locator('.ha-btn').first().evaluate((b) => getComputedStyle(b).backgroundColor)).toBe('rgb(162, 71, 31)');
  });

  test('signed-in visitors see Altus Knowledge and Performance in the header, and the workspace footer links back', async ({ as }) => {
    const p = await as('student');
    await open(p, 'en');
    await expect(p.locator('.ha-mast__cta')).toHaveAttribute('href', /\/hkp$/);
    await expect(p.locator('.ha-mast__cta')).toContainText('Altus Knowledge and Performance');
    await open(p, 'hkp');
    const foot = p.locator('[data-hkp-foot]');
    await expect(foot).toBeVisible();
    await expect(foot.locator('a[href$="/en/about-altus"]')).toBeVisible();
    await expect(foot).toContainText('Altus Gulf');
  });
});
