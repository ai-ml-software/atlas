import { test, expect, open } from '../support/fixtures';

/** Mega menu under "Altus Knowledge and Performance", and the corporate profile book (GSAP). */
test.describe('Mega menu', () => {
  test('desktop: hover opens the panel with every academy link and both founders; Escape closes it', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page, 'en/services');
    const panel = page.locator('[data-ha-mega-panel]');
    await expect(panel).toBeHidden();
    await page.hover('[data-ha-mega] .ha-mega__head > a');
    await expect(panel).toBeVisible();
    await expect(page.locator('[data-ha-mega-toggle]')).toHaveAttribute('aria-expanded', 'true');
    for (const p of ['courses', 'programs', 'learning-paths', 'certificates', 'sop', 'hospitality-topics', 'articles', 'verify', 'about', 'hotels', 'contact', 'credits', 'profile', 'knowledge-performance']) {
      await expect(panel.locator(`a[href$="/en/${p}"]`).first(), p).toBeVisible();
    }
    await expect(panel.locator('a[href="https://wa.me/201095556779"]')).toBeVisible();
    await expect(panel.locator('a[href="https://wa.me/966500511994"]')).toBeVisible();
    await expect(panel.locator('a[href="tel:+966500511994"]').first()).toBeVisible();
    await expect(panel).toContainText('Kingdom of Saudi Arabia');
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(page.locator('[data-ha-mega-toggle]')).toBeFocused();
  });

  test('keyboard: the toggle opens and closes the panel, and the item never moves into More', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await open(page, 'en');
    await expect(page.locator('.ha-more__panel [data-ha-mega]')).toHaveCount(0);
    await page.locator('[data-ha-mega-toggle]').focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-ha-mega-panel]')).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page.locator('[data-ha-mega-panel]')).toBeHidden();
  });

  test('Arabic: translated and mirrored', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page, 'ar/services');
    await page.hover('[data-ha-mega] .ha-mega__head > a');
    const panel = page.locator('[data-ha-mega-panel]');
    await expect(panel).toContainText('التعلّم');
    await expect(panel).toContainText('المملكة العربية السعودية');
    await expect(panel.locator('a[href$="/ar/courses"]')).toBeVisible();
  });

  test('phone drawer: the toggle expands the panel as an accordion', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await open(page, 'en');
    await page.click('[data-ha-nav-toggle]');
    await page.click('[data-ha-mega-toggle]');
    await expect(page.locator('[data-ha-mega-panel]')).toBeVisible();
    await expect(page.locator('[data-ha-mega-panel] a[href$="/en/courses"]')).toBeVisible();
    expect(page.url()).toMatch(/\/en$/);                     // the tap opened the menu, it did not follow the link
  });
});

test.describe('Corporate profile book', () => {
  test('English: pages turn with the buttons, thumbnails and keys; the text version and back cover are complete', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page, 'en/profile');
    await expect(page.locator('h1')).toHaveText('Altus Gulf Corporate Profile 2026');
    await expect(page.locator('.pb-leaf')).toHaveCount(13);
    await expect(page.locator('[data-pb-now]')).toHaveText('1');
    await expect(page.locator('[data-pb-prev]')).toBeDisabled();
    await page.click('[data-pb-next]');
    await expect(page.locator('[data-pb-now]')).toHaveText('3');
    await page.waitForTimeout(1200);
    expect(await page.locator('[data-pb-leaf="0"]').evaluate((e) => getComputedStyle(e).transform)).not.toBe('none');   // turned in 3D
    await page.click('[data-pb-go="10"]');
    await expect(page.locator('[data-pb-now]')).toHaveText('11');
    await page.waitForTimeout(1300);
    await page.locator('[data-pb-stage]').focus();
    await page.keyboard.press('End');
    await expect(page.locator('[data-pb-now]')).toHaveText('25');
    await expect(page.locator('[data-pb-next]')).toBeDisabled();
    await expect(page.locator('.pb-back')).toContainText('Hussam Smadi');
    await expect(page.locator('.pb-back a[href="https://wa.me/966500511994"]')).toBeAttached();
    await expect(page.locator('.pb-text__page')).toHaveCount(24);
    await expect(page.locator('#pb-text')).toContainText('The Altus Ascent');
    await expect(page.locator('body')).not.toContainText(/\[placeholder\]|\[Office address\]/);
    for (const n of ['01', '12', '24']) expect((await page.request.get(`uploads/academy/profile/en/${n}.webp`)).status()).toBe(200);
    const types = await page.locator('script[type="application/ld+json"]').evaluateAll((s) => s.map((x) => JSON.parse(x.textContent!)['@type']));
    expect(types).toContain('Book');
  });

  test('Arabic: the book binds on the right and turns right-to-left', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page, 'ar/profile');
    await expect(page.locator('[data-pb]')).toHaveAttribute('data-dir', 'rtl');
    await page.locator('[data-pb-stage]').focus();
    await page.keyboard.press('ArrowLeft');                  // in Arabic, left is forward
    await expect(page.locator('[data-pb-now]')).toHaveText('3');
    await page.waitForTimeout(1200);
    const r = await page.locator('[data-pb-leaf="0"]').evaluate((e) => new DOMMatrix(getComputedStyle(e).transform).m11);
    expect(r).toBeLessThan(0);                               // rotated about the spine
    await expect(page.locator('#pb-text')).toContainText('رسالة من المؤسسين');
  });

  test('phone: one page at a time', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await open(page, 'en/profile');
    await expect(page.locator('[data-pb]')).toHaveClass(/pb--single/);
    await page.click('[data-pb-next]');
    await page.click('[data-pb-next]');
    await expect(page.locator('[data-pb-now]')).toHaveText('3');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(391);
  });
});
