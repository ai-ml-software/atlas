import { test, expect, open, flashOk, stamp } from '../support/fixtures';
import { one } from '../support/db';
import { PNG } from '../support/files';
import type { Page } from '@playwright/test';

const id = stamp();
const SLUG = `e2e-page-${id}`;
const TITLE = `E2E Pre-opening support ${id}`;
let pageUrl = '';

async function score(p: Page) {
  return Number((await p.locator('.hkp-tile__value').first().innerText()).match(/\d+/)![0]);
}
async function addSection(p: Page, label: string) {
  const outer = p.locator('section.hkp-card > details').filter({ has: p.locator('summary', { hasText: 'Add section' }) });
  if (!(await outer.getAttribute('open').then((v) => v !== null))) await outer.locator('> summary').click();
  const card = outer.locator('details.hkp-card').filter({ has: p.locator('summary strong', { hasText: new RegExp(`^${label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }) });
  await card.locator('summary').click();
  return card;
}
const sectionItems = (p: Page) => p.locator('ol.hkp-sortable > li');

test.describe.serial('Website page builder (admin)', () => {
  test('create a page', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms');
    await p.locator('#nt').fill(TITLE);
    await p.locator('#nta').fill(`صفحة اختبار ${id}`);
    await p.locator('#ns').fill(SLUG);
    await p.getByRole('button', { name: 'Create page' }).click();
    await flashOk(p);
    await expect(p).toHaveURL(/hkp\/cms\/page\/\d+/);
    pageUrl = p.url().replace(/^.*\/atlas\/atlas\//, '').replace(/[?#].*$/, '');
    await expect(p.locator('h1')).toHaveText(TITLE);
  });

  test('add a hero with an uploaded image, a text section and an FAQ section', async ({ as }) => {
    const p = await as('admin');
    await open(p, pageUrl);

    let card = await addSection(p, 'Hero banner');
    await card.locator('input[name="en[heading]"]').fill('Open with confidence');
    await card.locator('input[name="ar[heading]"]').fill('افتتح بثقة');
    await card.locator('input[type=file][name=image]').setInputFiles(PNG);
    await card.getByRole('button', { name: 'Add this section' }).click();
    await flashOk(p);

    card = await addSection(p, 'Text');
    await card.locator('input[name="en[heading]"]').fill('Why pre-opening support matters');
    await card.locator('textarea[name="en[body]"]').fill('<p>Altus Advisory prepares teams in Riyadh. <script>alert(1)</script></p>');
    await card.getByRole('button', { name: 'Add this section' }).click();
    await flashOk(p);

    card = await addSection(p, 'FAQ (answer engines)');
    await card.locator('input[name="en[heading]"]').fill('Questions owners ask');
    await card.locator('textarea[name="en[items]"]').fill(
      'What is pre-opening support? | A structured programme that prepares the team, standards and systems before opening.\n' +
      'How long does it take? | Usually twelve to sixteen weeks depending on the property size.');
    await card.getByRole('button', { name: 'Add this section' }).click();
    await flashOk(p);

    await expect(sectionItems(p)).toHaveCount(3);
  });

  test('a required field is enforced', async ({ as }) => {
    const p = await as('admin');
    await open(p, pageUrl);
    const card = await addSection(p, 'Call to action');
    const heading = card.locator('input[name="en[heading]"]');
    await expect(heading).toHaveAttribute('required', '');
  });

  test('drag and drop reorders sections, and the order is saved', async ({ as }) => {
    const p = await as('admin');
    await open(p, pageUrl);
    const before = await sectionItems(p).evaluateAll((li) => li.map((l) => l.getAttribute('data-id')));
    const saved = p.waitForResponse((r) => r.url().includes('/cms/order/') && r.request().method() === 'POST');
    await sectionItems(p).last().dragTo(sectionItems(p).first(), { targetPosition: { x: 20, y: 2 } });
    expect((await saved).status()).toBe(200);
    await p.reload();
    const after = await sectionItems(p).evaluateAll((li) => li.map((l) => l.getAttribute('data-id')));
    expect(after[0]).toBe(before[before.length - 1]);          // FAQ moved to the top and stayed there
  });

  test('keyboard reorder (Alt+Arrow) for accessibility', async ({ as }) => {
    const p = await as('admin');
    await open(p, pageUrl);
    const first = await sectionItems(p).first().getAttribute('data-id');
    const saved = p.waitForResponse((r) => r.url().includes('/cms/order/'));
    await sectionItems(p).first().focus();
    await p.keyboard.press('Alt+ArrowDown');
    await saved;
    await p.reload();
    expect(await sectionItems(p).nth(1).getAttribute('data-id')).toBe(first);
  });

  test('SEO, AEO and GEO: filling the fields raises the score; publish', async ({ as }) => {
    const p = await as('admin');
    await open(p, pageUrl);
    const before = await score(p);
    await p.locator('#mten').fill('Pre-opening hotel support in Riyadh | Altus');
    await p.locator('#mden').fill('How Altus Advisory prepares independent hotels in Riyadh for opening day: people, standards, systems and evidence.');
    await p.locator('#fken').fill('pre-opening');
    await p.locator('#sch').selectOption('Service');
    await p.locator('#gr').fill('SA-01');
    await p.locator('#gp').fill('Riyadh');
    await p.locator('#gla').fill('24.7136');
    await p.locator('#glo').fill('46.6753');
    await p.locator('#ps').selectOption('published');
    await p.getByRole('button', { name: 'Save page' }).click();
    await flashOk(p);
    const after = await score(p);
    expect(after, `score ${before} → ${after}`).toBeGreaterThan(before);
    await expect(p.locator('main')).toContainText('Optimisation checklist');
    await expect(p.locator('main li', { hasText: '✗' }).first()).toBeVisible();   // remaining fixes are listed
  });

  test('the published page renders sections, sanitises HTML and emits FAQ + place schema', async ({ page }) => {
    await open(page, `en/${SLUG}`);
    await expect(page.locator('h1')).toContainText(TITLE);
    await expect(page.locator('.ha-block--hero img')).toHaveAttribute('src', /uploads\//);
    await expect(page.locator('.ha-block--rich_text')).toContainText('Altus Advisory prepares teams');
    expect(await page.locator('.ha-block--rich_text script').count()).toBe(0);         // <script> stripped
    await expect(page.locator('.ha-block-faq details')).toHaveCount(2);
    const ld = (await page.locator('script[type="application/ld+json"]').allTextContents()).join('\n');
    expect(ld).toContain('FAQPage');
    expect(ld).toContain('What is pre-opening support?');
    expect(ld).toMatch(/Riyadh/);
    await expect(page.locator('head title')).toHaveCount(1);
    await expect(page).toHaveTitle(/Pre-opening hotel support in Riyadh/);
    await expect(page.locator('meta[name=description]')).toHaveAttribute('content', /opening day/);
  });

  test('the Arabic page is RTL and falls back to English where Arabic is empty', async ({ page }) => {
    const slugAr = one(`SELECT slug_ar FROM ha_page WHERE code='${SLUG}'`)!;
    await open(page, `ar/${encodeURIComponent(slugAr)}`);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('.ha-block--hero')).toContainText('افتتح بثقة');
    await expect(page.locator('.ha-block-faq')).toContainText('What is pre-opening support?');
  });

  test('hide a section, then restore an earlier revision', async ({ as, page }) => {
    const p = await as('admin');
    await open(p, pageUrl);
    const textItem = sectionItems(p).filter({ hasText: 'Why pre-opening support matters' });
    await textItem.getByRole('button', { name: 'Hide' }).click();
    await flashOk(p);
    await open(page, `en/${SLUG}`);
    await expect(page.locator('.ha-block--rich_text')).toHaveCount(0);

    await open(p, pageUrl);
    const revisions = p.locator('section.hkp-card', { has: p.locator('h2', { hasText: 'Revisions' }) }).getByRole('button', { name: 'Restore' });
    expect(await revisions.count()).toBeGreaterThan(2);
    await revisions.nth(1).click();                          // the state before hiding
    await flashOk(p);
    await open(page, `en/${SLUG}`);
    await expect(page.locator('.ha-block--rich_text')).toHaveCount(1);
  });

  test('duplicate and delete a section', async ({ as }) => {
    const p = await as('admin');
    await open(p, pageUrl);
    const n = await sectionItems(p).count();
    await sectionItems(p).first().getByRole('button', { name: 'Duplicate' }).click();
    await flashOk(p);
    await expect(sectionItems(p)).toHaveCount(n + 1);
    await sectionItems(p).first().getByRole('button', { name: 'Delete' }).click();
    await flashOk(p);
    await expect(sectionItems(p)).toHaveCount(n);
  });

  test('the pages list shows the page with its scores', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms');
    const row = p.locator('tr', { hasText: TITLE });
    await expect(row).toBeVisible();
    await expect(row.locator('.hkp-badge').first()).toHaveText(/\d+/);
  });
});
