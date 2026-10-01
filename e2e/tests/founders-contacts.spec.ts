import { test, expect, open } from '../support/fixtures';
import { sql, one } from '../support/db';

/** Founders' contact lines and portraits, company social links, and the book's full-screen button. */
test.describe('Founders, company contacts and full screen', () => {
  test('footer: both founders with portrait, phone, e-mail, WhatsApp and Call; support e-mail', async ({ page }) => {
    await open(page, 'en/services');
    const foot = page.locator('footer.ha-foot');
    await expect(foot.locator('.ha-foot__face')).toHaveCount(2);
    await expect(foot.locator('a[href="mailto:islam.mahrous@altusgulf.com"]')).toBeAttached();
    await expect(foot.locator('a[href="mailto:hussam.smadi@altusgulf.com"]')).toBeAttached();
    await expect(foot.locator('a[href="mailto:info@altusgulf.com"]').first()).toBeAttached();
    await expect(foot.locator('a[href="tel:+966500511994"]').first()).toBeAttached();
    for (const f of ['islam-mahrous', 'hussam-smadi']) expect((await page.request.get(`uploads/academy/people/${f}-sm.webp`)).status()).toBe(200);
  });

  test('mega menu and leadership page show the portraits and e-mails', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page, 'en/leadership');
    await expect(page.locator('.ag-leader__photo')).toHaveCount(2);
    await expect(page.locator('main a[href="mailto:hussam.smadi@altusgulf.com"]')).toBeVisible();
    await page.hover('[data-ha-mega] .ha-mega__head > a');
    const panel = page.locator('[data-ha-mega-panel]');
    await expect(panel.locator('.ha-mega__face')).toHaveCount(2);
    await expect(panel.locator('a[href="mailto:islam.mahrous@altusgulf.com"]')).toBeVisible();
    await expect(panel).toContainText('info@altusgulf.com');
  });

  test('company social links set in admin appear as footer icons (Facebook included)', async ({ page }) => {
    sql(`DELETE FROM frontend_settings WHERE \`key\` IN ('ha_social_facebook','ha_social_linkedin')`);
    sql(`INSERT INTO frontend_settings (\`key\`, value) VALUES ('ha_social_facebook', 'https://www.facebook.com/altusgulf'), ('ha_social_linkedin', 'https://www.linkedin.com/company/altus-gulf')`);
    try {
      await open(page, 'en');
      const social = page.locator('footer .ha-foot__social');
      await expect(social.locator('a[href="https://www.facebook.com/altusgulf"]')).toHaveAttribute('aria-label', /Facebook/);
      await expect(social.locator('a[href="https://www.linkedin.com/company/altus-gulf"]')).toBeAttached();
    } finally {
      sql(`DELETE FROM frontend_settings WHERE \`key\` IN ('ha_social_facebook','ha_social_linkedin')`);
    }
  });

  test('admin: leadership profiles take e-mail, phone, social links and a photo upload', async ({ as }) => {
    const p = await as('admin');
    const id = one(`SELECT id FROM ha_leadership_profile WHERE slug='hossam-smadi'`);
    const before = one(`SELECT photo_path FROM ha_leadership_profile WHERE id=${id}`);
    await open(p, `hkp/admin/crud/leadership/edit/${id}`);
    for (const f of ['email', 'phone', 'linkedin_url', 'facebook_url', 'instagram_url', 'x_url']) await expect(p.locator(`#f_${f}`), f).toBeVisible();
    await expect(p.locator('#f_email')).toHaveValue('hussam.smadi@altusgulf.com');
    // 1x1 PNG
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
    try {
      await p.locator('#f_x_url').fill('https://x.com/altusgulf');
      await p.locator('#f_photo_path').setInputFiles({ name: 'face.png', mimeType: 'image/png', buffer: png });
      await p.getByRole('button', { name: 'Save', exact: true }).click();
      await expect(p.locator('.hkp-flash--ok')).toBeVisible();
      const row = sql(`SELECT photo_path, x_url FROM ha_leadership_profile WHERE id=${id}`)[0];
      expect(row[0]).toMatch(/^uploads\/academy\/people\/[0-9a-f]{16}\.png$/);
      expect(row[1]).toBe('https://x.com/altusgulf');
      // a file that is not an image is refused
      await open(p, `hkp/admin/crud/leadership/edit/${id}`);
      await p.locator('#f_photo_path').setInputFiles({ name: 'evil.png', mimeType: 'image/png', buffer: Buffer.from('<?php echo 1; ?>') });
      await p.getByRole('button', { name: 'Save', exact: true }).click();
      await expect(p.locator('.hkp-flash--error')).toContainText(/JPG, PNG or WebP/);
    } finally {
      sql(`UPDATE ha_leadership_profile SET photo_path='${before}', x_url=NULL WHERE id=${id}`);
    }
  });

  for (const [loc, on, off] of [['en', 'Show full screen', 'Exit full screen'], ['ar', 'عرض بملء الشاشة', 'الخروج من ملء الشاشة']] as const) {
    test(`book full screen button (${loc})`, async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 });
      await open(page, `${loc}/profile`);
      const btn = page.locator('[data-pb-full]');
      await expect(btn).toHaveText(new RegExp(on));
      await btn.click();
      await expect(btn).toHaveText(new RegExp(off));
      await expect(btn).toHaveAttribute('aria-pressed', 'true');
      await expect(page.locator('[data-pb]')).toHaveClass(/pb--fullscreen/);
      await page.click('[data-pb-next]');
      await expect(page.locator('[data-pb-now]')).toHaveText('3');             // turning still works in full screen
      await btn.click();
      await expect(btn).toHaveText(new RegExp(on));
      await expect(page.locator('[data-pb]')).not.toHaveClass(/pb--fullscreen/);
    });
  }
});
