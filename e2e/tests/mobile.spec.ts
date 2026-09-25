import { test, expect, open } from '../support/fixtures';

// Runs in the "mobile" project (390 × 844, touch).
const noHorizontalScroll = async (p) => {
  const [sw, cw] = await p.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.clientWidth]);
  expect(sw, 'no horizontal scroll').toBeLessThanOrEqual(cw + 1);
};

test.describe('Mobile-first learner experience', () => {
  for (const path of ['hkp', 'hkp/learn', 'hkp/knowledge', 'hkp/competencies', 'hkp/readiness/me', 'hkp/assistant', 'hkp/certificates']) {
    test(`learner ${path} fits a phone screen`, async ({ as }) => {
      const p = await as('learner');
      await open(p, path);
      await noHorizontalScroll(p);
    });
  }

  test('Arabic (RTL) dashboard fits a phone screen', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp?lang=ar');
    await expect(p.locator('html')).toHaveAttribute('dir', 'rtl');
    await noHorizontalScroll(p);
    await open(p, 'hkp?lang=en');
  });

  test('manager team dashboard fits a phone screen', async ({ as }) => {
    const p = await as('gm');
    await open(p, 'hkp/team');
    await noHorizontalScroll(p);
  });

  test('public home page fits a phone screen in both languages', async ({ page }) => {
    for (const l of ['en', 'ar']) {
      await open(page, l);
      await noHorizontalScroll(page);
    }
  });

  test('tap targets in the workspace navigation are at least 44px tall', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp');
    const toggle = p.locator('[data-toggle-nav]');
    if (await toggle.count()) await toggle.click();
    const heights = await p.locator('.hkp-nav__item').evaluateAll((els) => els.filter((e) => (e as HTMLElement).offsetParent).map((e) => e.getBoundingClientRect().height));
    for (const h of heights) expect(h).toBeGreaterThanOrEqual(44);
  });
});
