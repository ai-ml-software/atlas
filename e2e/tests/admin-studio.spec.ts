import { test, expect, open, flashOk, stamp } from '../support/fixtures';
import { one, sql, workerEnv, preserve, keepPage } from '../support/db';
import { mkdirSync } from 'node:fs';
import {execFileSync} from 'node:child_process';
import path from 'node:path';

function sourcePdf() {
  const text = 'BT /F1 12 Tf 50 750 Td (Always wear PPE before cleaning hotel bathrooms. Follow the approved procedure and report hazards.) Tj ET';
  const objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', `<< /Length ${text.length} >>\nstream\n${text}\nendstream`];
  let pdf = '%PDF-1.4\n', offsets = [0]; objects.forEach((o, i) => { offsets.push(pdf.length); pdf += `${i + 1} 0 obj\n${o}\nendobj\n`; });
  const xref = pdf.length; pdf += `xref\n0 6\n0000000000 65535 f \n${offsets.slice(1).map(o => `${String(o).padStart(10, '0')} 00000 n \n`).join('')}trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
  return { name: 'safe-cleaning.pdf', mimeType: 'application/pdf', buffer: Buffer.from(pdf) };
}

test('admin sidebar, command palette and every permitted destination render', async ({ as }) => {
  test.setTimeout(180_000);
  const p = await as('admin'); await open(p, 'hkp/admin');
  await expect(p.locator('.studio-metric')).toHaveCount(20);
  await expect(p.locator('.studio-comparison-note')).toContainText('30 days ago');
  await p.keyboard.press('Control+k'); await expect(p.locator('#studio-command')).toBeVisible();
  await p.locator('#studio-command-input').fill('AI Publisher'); await expect(p.locator('.studio-command-results a:visible')).toHaveCount(1); await p.keyboard.press('Escape');
  await p.locator('[data-collapse-sidebar]').click(); await expect(p.locator('body')).toHaveClass(/studio-collapsed/); await p.reload(); await expect(p.locator('body')).toHaveClass(/studio-collapsed/); await p.locator('[data-collapse-sidebar]').click();
  await p.locator('#studio-nav-search').fill('Website pages'); await expect(p.locator('.hkp-nav .hkp-nav__item:visible')).toHaveCount(1); await p.locator('#studio-nav-search').fill('');
  const urls = await p.locator('.hkp-nav__item').evaluateAll(links => [...new Set(links.map(a => (a as HTMLAnchorElement).href))]);
  for (const url of urls) await open(p, url);
  await open(p, 'hkp/admin'); mkdirSync(path.resolve('..', 'docs', 'screenshots'), { recursive: true });
  await p.screenshot({ path: '../docs/screenshots/admin-dashboard.png', fullPage: true });
  await open(p, 'hkp/cms/studio'); await p.screenshot({ path: '../docs/screenshots/content-studio.png', fullPage: true });
});

test('PDF analysis, configured AI drafting, saved preview and unpublished package import', async ({ as }) => {
  const p = await as('admin'); await open(p, 'hkp/cms/publisher');
  await p.locator('#publisher-file').setInputFiles(sourcePdf()); await p.getByRole('button', { name: 'Analyse document' }).click(); await flashOk(p);
  execFileSync('C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe',['index.php','publisher_cli','work','10'],{cwd:path.resolve('..'),env:workerEnv()});
  await p.reload(); await expect(p.locator('#publisher-source-correction')).toHaveValue(/Always wear PPE/);
  await p.locator('#publisher-provider').selectOption('e2e_mock'); await p.locator('#publisher-model').selectOption('mock-writer');
  await p.getByRole('button', { name: 'Generate draft', exact: true }).click();
  await expect(p.locator('#publisher-status')).toContainText('Draft saved', { timeout: 30_000 });
  const title = await p.locator('#publisher-visual input').first().inputValue();
  await expect(p.locator('#publisher-visual')).toContainText('Preparation');
  const preview = await p.context().newPage(); await open(preview, (await p.getByRole('link', { name: 'Preview saved draft' }).getAttribute('href'))!); await expect(preview.locator('h1')).toHaveText(title); await preview.close();
  await p.screenshot({ path: '../docs/screenshots/document-publisher.png', fullPage: true });
  await p.getByRole('button', { name: 'Create content as draft' }).click(); await expect(p).toHaveURL(/cms\/module\/\d+/);
  const course = Number(p.url().match(/module\/(\d+)/)![1]);
  expect(one(`SELECT status FROM ha_course WHERE id=${course}`)!).toBe('draft');
  expect(one(`SELECT COUNT(*) AS n FROM ha_lesson WHERE course_id=${course}`)!).toBe('1');
  expect(one(`SELECT status FROM ha_assessment WHERE course_id=${course}`)!).toBe('draft');
});

test('live editing keeps draft private, previews the real page and publishes explicitly', async ({ as, page }) => {
  const p = await as('admin'); const id = Number(one("SELECT id FROM ha_page WHERE code='about'")!), title = 'Private live title ' + stamp();
  const restore = keepPage(id);   // this spec publishes the shared About page; put it back afterwards
  try {
  await open(page, 'en'); const homeTitle = await page.locator('h1').innerText();
  const homeId = Number(one("SELECT id FROM ha_page WHERE code='home'")!);
  await open(p, `hkp/cms/live/${homeId}`);
  await expect(p.locator('#live-fields').getByLabel('Page title', { exact: true })).toHaveValue(homeTitle);
  await expect(p.frameLocator('#live-preview').locator('h1')).toHaveText(homeTitle);
  // A preceding PHP test deliberately leaves an outdated contact draft; use an unmodified published page here.
  await open(p, `hkp/cms/live/${id}`);
  await p.locator('#live-fields').getByLabel('Page title', { exact: true }).fill(title);
  await expect(p.locator('#live-status')).toContainText('Draft saved', { timeout: 15_000 });
  await expect(p.frameLocator('#live-preview').locator('h1')).toHaveText(title);
  await open(page, 'en/about'); await expect(page.locator('h1')).not.toHaveText(title); await expect(page.locator('.ha-studio-edit')).toHaveCount(0);
  await p.getByRole('button', { name: 'Mobile', exact: true }).click(); await expect(p.locator('#live-preview-wrap')).toHaveAttribute('data-device', 'mobile');
  await p.screenshot({ path: '../docs/screenshots/live-editor.png', fullPage: true });
  await p.getByRole('button', { name: 'Publish', exact: true }).click(); await expect(p.locator('#live-status')).toContainText('Published');
  await page.reload(); await expect(page.locator('h1')).toHaveText(title);
  await p.locator('#live-fields').getByLabel('Page title', { exact: true }).fill('Discard this private edit');
  await expect(p.locator('#live-status')).toContainText('Draft saved', { timeout: 15_000 });
  const download = p.waitForEvent('download'); await p.getByRole('button', { name: 'Download draft', exact: true }).click();
  expect((await download).suggestedFilename()).toBe(`altus-page-${id}-draft.json`);
  await p.getByRole('button', { name: 'Discard draft', exact: true }).click();
  await expect(p.locator('#live-fields').getByLabel('Page title', { exact: true })).toHaveValue(title);
  await page.reload(); await expect(page.locator('h1')).toHaveText(title);
  await open(p, 'en/about'); await expect(p.getByRole('link', { name: /Edit page/ })).toBeVisible();
  } finally { restore(); }
});

test('catalogue drafts save bilingual articles; navigation labels appear publicly', async ({ as, page }) => {
  const p = await as('admin'), id = stamp(); await open(p, 'hkp/cms/catalogue/articles/new');
  await p.locator('[name=title_en]').fill('Studio article ' + id); await p.locator('[name=title_ar]').fill('مقالة اختبار');
  await p.locator('[name=slug_en]').fill('studio-article-' + id); await p.locator('[name=slug_ar]').fill('studio-article-ar-' + id);
  await p.locator('[name=body_en]').fill('<p>Useful source content.</p>'); await p.locator('[name=body_ar]').fill('<p>محتوى مفيد.</p>');
  await p.getByRole('button', { name: 'Save content' }).click(); await flashOk(p); await expect(p).toHaveURL(/catalogue\/articles\/\d+/);
  await open(p, 'hkp/cms/navigation'); await p.getByRole('link', { name: 'Footer · Learn', exact: true }).click();
  // Publishing a menu label changes the shared site footer: put the menus back afterwards.
  const label = 'Studio professional courses ' + id, restore = preserve([['ha_menu_item', '1=1']]);
  try {
    await p.locator('[name="items[0][label_en]"]').fill(label); await p.getByRole('button', { name: 'Save private draft' }).click(); await flashOk(p);
    await open(page,'en');await expect(page.locator('.ha-foot')).not.toContainText(label); await p.getByRole('button',{name:'Publish saved draft'}).click();await flashOk(p);await page.reload();await expect(page.locator('.ha-foot')).toContainText(label);
  } finally { restore(); sql("DELETE FROM ha_studio_draft WHERE object_type='navigation'"); }
});

test('new editor actions require permission and a valid CSRF token', async ({ as }) => {
  const p = await as('learner'); for (const route of ['hkp/cms/live/1', 'hkp/cms/catalogue/articles', 'hkp/cms/navigation', 'hkp/cms/publisher']) { const response = await p.request.get(route); expect(response.status()).toBe(403); }
  const admin = await as('admin'); await open(admin, 'hkp/cms/publisher');
  const response = await admin.request.post('hkp/cms/live_save/1', { form: { action: 'publish', version: '1' }, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); expect(response.status()).toBe(403);
});

test('learning path editor persists bilingual steps and their real course relationships', async ({ as }) => {
  const p = await as('admin'), code = stamp(); await open(p, 'hkp/cms/catalogue/paths/new');
  await p.locator('[name=title_en]').fill('Studio path ' + code); await p.locator('[name=title_ar]').fill('مسار التدريب');
  await p.locator('[name=slug_en]').fill('studio-path-' + code); await p.locator('[name=slug_ar]').fill('studio-path-ar-' + code);
  await p.getByRole('button', {name: 'Add step', exact: true}).click();
  await p.getByLabel('English step title').fill('Preparation'); await p.getByLabel('عنوان الخطوة بالعربية').fill('الاستعداد');
  await p.getByRole('button', {name: 'Add learning item', exact: true}).click();
  const select = p.getByLabel('Learning item', {exact: true}); const course = await select.locator('option').nth(1).getAttribute('value');
  await select.selectOption(course!); await p.getByRole('button', {name: 'Save content', exact: true}).click(); await flashOk(p);
  await expect(p.getByLabel('English step title')).toHaveValue('Preparation');
  const pathId = Number(p.url().match(/paths\/(\d+)/)![1]);
  expect(one(`SELECT COUNT(*) FROM ha_path_step_item i JOIN ha_path_step s ON s.id=i.step_id WHERE s.path_id=${pathId} AND i.item_id=${Number(course)}`)).toBe('1');
  await p.screenshot({path: '../docs/screenshots/learning-path-editor.png', fullPage: true});
  await p.setViewportSize({width: 390, height: 844});
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await expect(p.getByLabel('Learning item', {exact: true})).toBeVisible();
});

test('editor pages fit mobile and Arabic layouts', async ({ as }) => {
  const p = await as('admin'); await p.setViewportSize({ width: 390, height: 844 });
  for (const route of ['hkp/admin', 'hkp/cms/studio', 'hkp/cms/publisher', 'hkp/cms/live/1', 'hkp/cms/catalogue/articles', 'hkp/cms/navigation']) { await open(p, route); expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true); }
  await open(p, 'hkp/cms/studio?lang=ar'); await expect(p.locator('html')).toHaveAttribute('dir', 'rtl'); expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await open(p, 'hkp/cms/studio?lang=en'); // The saved admin session is shared with subsequent suites.
});
