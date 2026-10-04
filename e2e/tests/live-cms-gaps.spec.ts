import { test, expect, open, flashOk, stamp } from '../support/fixtures';
import { one, sql } from '../support/db';

const pageId = (code: string) => Number(one(`SELECT id FROM ha_page WHERE code='${code}'`)!);

test('inline click-to-edit types into the real page, syncs the panel and stays private', async ({ as, page }) => {
  const p = await as('admin'), id = pageId('credits'), title = 'Inline headline ' + stamp();
  sql(`DELETE FROM ha_website_draft WHERE page_id=${id}`);
  await open(p, `hkp/cms/live/${id}`);
  const frame = p.frameLocator('#live-preview'), h1 = frame.locator('h1[data-studio-field=title]');
  await expect(h1).toHaveAttribute('data-studio-kind', 'text');
  await h1.click(); await expect(h1).toHaveAttribute('contenteditable', 'true'); await expect(h1).toBeFocused();
  await p.keyboard.press('Control+A'); await p.keyboard.type(title); await p.keyboard.press('Escape');
  await expect(p.locator('#live-fields [data-field=title]')).toHaveValue(title);
  await expect(p.locator('#live-status')).toContainText('Draft saved', { timeout: 15_000 });
  await open(page, 'en/credits'); await expect(page.locator('h1')).not.toHaveText(title);
  await expect(page.locator('[data-studio-field]')).toHaveCount(0);
  // Typing in the panel updates the preview element without a reload.
  await p.locator('#live-fields [data-field=subtitle]').fill('Panel lede ' + title);
  await expect(frame.locator('[data-studio-field=subtitle]')).toHaveText('Panel lede ' + title);
  await Promise.all([p.waitForEvent('load'), p.getByRole('button', { name: 'Discard draft', exact: true }).click()]);
});

test('section cards and images are editable from the preview; images open the media picker by keyboard', async ({ as }) => {
  test.setTimeout(120_000);
  const p = await as('admin'), id = pageId('credits');
  sql(`DELETE FROM ha_website_draft WHERE page_id=${id}`);
  await open(p, `hkp/cms/live/${id}`);
  await p.locator('#live-add-type').selectOption('cards'); await p.getByRole('button', { name: 'Add section', exact: true }).click();
  const card = p.locator('#live-fields details[data-key]').filter({ has: p.locator('[data-field$=":items"]') }).last();
  await card.locator('[data-field$=":heading"]').fill('Studio cards');
  await card.locator('[data-field$=":items"]').fill('First card | Card text | courses');
  await p.locator('#live-add-type').selectOption('image'); await p.getByRole('button', { name: 'Add section', exact: true }).click();
  await p.locator('#live-fields details[data-key]').last().locator('[data-field$=":image"]').fill('uploads/academy/altus/hero-riyadh-terrace.webp');
  await expect(p.locator('#live-status')).toContainText('Draft saved', { timeout: 15_000 });
  const frame = p.frameLocator('#live-preview');
  const item = frame.locator('[data-studio-field$=":items:0:title"]');
  await expect(item).toHaveText('First card');
  await item.click(); await p.keyboard.press('End'); await p.keyboard.type(' updated');
  await expect(card.locator('[data-field$=":items"]')).toHaveValue(/First card updated \| Card text/);
  const img = frame.locator('[data-studio-kind=image]').last();
  await img.focus(); await p.keyboard.press('Enter');
  await expect(p.locator('dialog.studio-media-dialog')).toBeVisible();
  await p.keyboard.press('Escape'); await expect(p.locator('dialog.studio-media-dialog')).toHaveCount(0);
  await expect(p.locator('#live-status')).toContainText('Draft saved', { timeout: 15_000 });
  await Promise.all([p.waitForEvent('load'), p.getByRole('button', { name: 'Discard draft', exact: true }).click()]);
});

test('corporate homepage blocks are editable in the live draft and private until publication', async ({ as, page }) => {
  const p = await as('admin'), id = pageId('home'), text = 'Corporate draft ' + stamp();
  sql(`DELETE FROM ha_website_draft WHERE page_id=${id}`);
  await open(p, `hkp/cms/live/${id}`);
  const block = p.frameLocator('#live-preview').locator('[data-studio-field^="c:"][data-studio-field$=":body"]').first();
  const field = await block.getAttribute('data-studio-field');
  await block.click(); await p.keyboard.press('Control+A'); await p.keyboard.type(text); await p.keyboard.press('Escape');
  await expect(p.locator(`#live-fields [data-field="${field}"]`)).toHaveValue(text);
  await expect(p.locator('#live-status')).toContainText('Draft saved', { timeout: 15_000 });
  await open(page, 'en'); await expect(page.locator('body')).not.toContainText(text);
  await Promise.all([p.waitForEvent('load'), p.getByRole('button', { name: 'Discard draft', exact: true }).click()]);
});

test('draft previews need an editor session and a valid signed link', async ({ as, page }) => {
  const p = await as('admin'), id = pageId('about'), title = 'Signed preview ' + stamp();
  sql(`DELETE FROM ha_website_draft WHERE page_id=${id}`);
  await open(p, `hkp/cms/live/${id}`);
  await p.locator('#live-fields [data-field=title]').fill(title);
  await expect(p.locator('#live-status')).toContainText('Draft saved', { timeout: 15_000 });
  const signed = (await p.locator('#live-preview').getAttribute('src'))!;
  expect(signed).toContain('studio_sig=');
  await open(p, signed); await expect(p.locator('h1')).toHaveText(title);
  await open(p, signed.replace(/studio_sig=[^&]+/, 'studio_sig=1.abc')); await expect(p.locator('h1')).not.toHaveText(title);
  await open(p, signed.replace(/&?studio_sig=[^&]+/, '')); await expect(p.locator('h1')).not.toHaveText(title);
  await open(page, signed); await expect(page.locator('h1')).not.toHaveText(title); // visitors keep the published page
  await open(p, `hkp/cms/live/${id}`);
  await p.getByRole('button', { name: 'Share review link', exact: true }).click();
  await expect(p.locator('#live-share-url')).toHaveValue(/studio_sig=\d{10}\.[a-f0-9]{64}/);
  await Promise.all([p.waitForEvent('load'), p.getByRole('button', { name: 'Discard draft', exact: true }).click()]);
});

test('theme site settings preview privately; revision history restores with keyboard and RTL', async ({ as, page }) => {
  test.setTimeout(120_000);
  const p = await as('admin'), name = 'Studio Site ' + stamp();
  await open(p, 'hkp/cms/theme');
  await expect(p.getByLabel('Heading font')).toBeVisible();
  const fonts = await p.getByLabel('Heading font').locator('option').allTextContents();
  expect(fonts).toContain('Fraunces'); expect(fonts).not.toContain('Comic Sans');
  await p.getByLabel('Site name').fill(name); await p.getByLabel('Navigation style').selectOption('pill');
  await p.getByLabel('Email', { exact: true }).fill('studio@example.com');
  await p.getByRole('button', { name: 'Save private draft', exact: true }).click(); await flashOk(p);
  await open(page, 'en'); await expect(page.locator('.ha-foot')).not.toContainText('studio@example.com');
  await open(p, 'en?studio_theme_preview=1'); await expect(p.locator('.ha-foot')).toContainText('studio@example.com');
  await open(p, 'hkp/cms/theme'); await p.getByRole('button', { name: 'Publish saved draft', exact: true }).click(); await flashOk(p);
  await page.reload(); await expect(page.locator('.ha-foot')).toContainText('studio@example.com');
  await open(p, 'hkp/studio/revisions?type=site');
  await expect(p.locator('.studio-revision-table tbody tr').first()).toContainText('#');
  const restore = p.getByRole('button', { name: /Restore revision/ }).nth(1);
  await restore.focus(); await p.keyboard.press('Enter'); await flashOk(p);
  await open(p, 'hkp/cms/theme'); await expect(p.locator('.studio-save-status')).toContainText('Private draft saved');
  // Put the published identity back for the rest of the suite.
  await p.getByLabel('Site name').fill(''); await p.getByLabel('Email', { exact: true }).fill(''); await p.getByLabel('Navigation style').selectOption('standard');
  await p.getByRole('button', { name: 'Save private draft', exact: true }).click(); await flashOk(p);
  await p.getByRole('button', { name: 'Publish saved draft', exact: true }).click(); await flashOk(p);
  await open(p, 'hkp/studio/revisions?lang=ar'); await expect(p.locator('html')).toHaveAttribute('dir', 'rtl');
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await open(p, 'hkp/cms/theme?lang=ar'); expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await open(p, 'hkp/studio/revisions?lang=en');
});

test('dashboard follows the reference with real comparisons and no encoding glitches', async ({ as }) => {
  const p = await as('admin'); await open(p, 'hkp/admin');
  await expect(p.locator('.studio-dash-head h1')).toHaveText('Portfolio dashboard');
  await expect(p.locator('.studio-metric')).toHaveCount(20);
  await expect(p.locator('body')).not.toContainText('ALTUS ?');
  await expect(p.locator('.studio-delta').first()).toBeVisible();
  await expect(p.getByRole('heading', { name: 'Cross-property comparison' })).toBeVisible();
  await expect(p.getByRole('heading', { name: 'Recent activity' })).toBeVisible();
  await p.screenshot({ path: '../docs/screenshots/admin-dashboard.png', fullPage: true });
  await open(p, 'hkp/admin?lang=ar'); await expect(p.locator('html')).toHaveAttribute('dir', 'rtl');
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await open(p, 'hkp/admin?lang=en');
});
