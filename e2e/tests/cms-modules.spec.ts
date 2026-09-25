import { test, expect, open, flashOk, stamp } from '../support/fixtures';
import { pdf } from '../support/files';
import type { Page } from '@playwright/test';

const id = stamp();
const MODULE = `E2E Guest arrival ${id}`;
let moduleUrl = '';
const lessons: Record<string, string> = {};

async function newLesson(p: Page, fill: (p: Page) => Promise<void>) {
  await open(p, moduleUrl);
  await p.getByRole('link', { name: 'Add lesson' }).click();
  await expect(p.locator('h1')).toHaveText('New lesson');
  await fill(p);
  await p.getByRole('button', { name: 'Save lesson' }).click();
  await flashOk(p);
  await expect(p).toHaveURL(/cms\/lesson\/\d+/);
  return p.url().match(/cms\/lesson\/(\d+)/)![1];
}

test.describe.serial('Modules & lessons CMS (admin)', () => {
  test('create a module in English and Arabic', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms/module');
    await p.locator('#mt_en').fill(MODULE);
    await p.locator('#mt_ar').fill(`استقبال الضيف ${id}`);
    await p.locator('#ms_en').fill('Greeting and check-in standards');
    await p.locator('#mdo').selectOption({ index: 1 });
    await p.locator('#mst').selectOption('draft');
    await p.getByRole('button', { name: 'Save module' }).click();
    await flashOk(p);
    await expect(p).toHaveURL(/cms\/module\/\d+/);
    moduleUrl = p.url().replace(/^.*\/atlas\/atlas\//, '').replace(/[?#].*$/, '');
  });

  test('add a section (chapter)', async ({ as }) => {
    const p = await as('admin');
    await open(p, moduleUrl);
    const form = p.locator('form[action*="cms/section_add"]');
    await form.locator('#nsec').fill('Arrival');
    await form.locator('input[name=title_ar]').fill('الوصول');
    await form.getByRole('button', { name: 'Add section' }).click();
    await flashOk(p);
    await expect(p.locator('main')).toContainText(/Sections: .*Arrival/);    // after the default "Lessons" section
  });

  test('lesson from a YouTube link', async ({ as }) => {
    const p = await as('admin');
    lessons.video = await newLesson(p, async (p) => {
      await p.locator('#lt').selectOption('video');
      await p.locator('#lti_en').fill('Greeting the guest (video)');
      await p.locator('#lv').fill('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
      await p.locator('#lb_en').fill('<p>Watch the greeting standard.</p>');
      await p.locator('#lst').selectOption('published');
    });
  });

  test('lesson with an uploaded PDF and a downloadable resource', async ({ as }) => {
    const p = await as('admin');
    lessons.pdf = await newLesson(p, async (p) => {
      await p.locator('#lt').selectOption('pdf');
      await p.locator('#lti_en').fill('Check-in checklist (PDF)');
      await p.locator('#lm').setInputFiles(pdf('Check-in checklist'));
      await p.locator('#la').setInputFiles({ ...pdf('Printable card'), name: 'e2e-card.pdf' });
      await p.locator('#lst').selectOption('published');
    });
    await expect(p.locator('.hkp-help', { hasText: 'Current:' })).toBeVisible();
  });

  test('an upload whose content does not match its extension is rejected', async ({ as }) => {
    const p = await as('admin');
    await open(p, `hkp/cms/lesson/${lessons.pdf}`);
    await p.locator('#lm').setInputFiles({ name: 'fake.pdf', mimeType: 'application/pdf', buffer: Buffer.from('<?php echo 1; ?>') });
    await p.getByRole('button', { name: 'Save lesson' }).click();
    await expect(p.locator('.hkp-flash--error')).toBeVisible();
  });

  test('an external link that is not https is rejected', async ({ as }) => {
    const p = await as('admin');
    await open(p, `hkp/cms/lesson/${lessons.pdf}`);
    await p.locator('#le').fill('javascript:alert(1)');
    await p.getByRole('button', { name: 'Save lesson' }).click();
    await expect(p.locator('.hkp-flash--error')).toBeVisible();
  });

  test('drip lesson released 7 days after enrolment', async ({ as }) => {
    const p = await as('admin');
    lessons.drip = await newLesson(p, async (p) => {
      await p.locator('#lt').selectOption('text');
      await p.locator('#lti_en').fill('Handling late arrivals (week 2)');
      await p.locator('#lb_en').fill('<p>Released one week after you start.</p>');
      await p.locator('#ldm').selectOption('days');
      await p.locator('#ldd').fill('7');
      await p.locator('#lst').selectOption('published');
    });
    await open(p, moduleUrl);
    await expect(p.locator('ol.hkp-sortable')).toContainText('day 7');
  });

  test('publish the module; it appears for learners in the workspace', async ({ as }) => {
    const p = await as('admin');
    await open(p, moduleUrl);
    await p.locator('#mst').selectOption('published');
    await p.getByRole('button', { name: 'Save module' }).click();
    await flashOk(p);
    await expect(p.locator('main')).toContainText(/Published/i);
  });

  test('preview as learner: YouTube embed, PDF viewer + download, drip lock', async ({ as }) => {
    const p = await as('admin');
    await open(p, `hkp/learn/lesson/${lessons.video}`);
    await expect(p.locator('.hkp-video iframe')).toHaveAttribute('src', /youtube-nocookie\.com\/embed\/dQw4w9WgXcQ/);

    await open(p, `hkp/learn/lesson/${lessons.pdf}`);
    await expect(p.locator('iframe[src*=".pdf"]')).toBeVisible();
    const href = await p.getByRole('link', { name: 'Download the PDF' }).getAttribute('href');
    const r = await p.request.get(href!);
    expect(r.status()).toBe(200);
    expect((await r.body()).subarray(0, 5).toString()).toBe('%PDF-');
    await expect(p.locator('main')).toContainText('Resources');

    await p.goto(`hkp/learn/lesson/${lessons.drip}`);
    await expect(p.locator('.hkp-flash--error')).toContainText(/opens on/);
  });

  test('reorder lessons with the keyboard and delete one', async ({ as }) => {
    const p = await as('admin');
    await open(p, moduleUrl);
    const items = p.locator('ol.hkp-sortable > li');
    const first = await items.first().getAttribute('data-id');
    const saved = p.waitForResponse((r) => r.url().includes('/cms/lesson_order/'));
    await items.first().focus();
    await p.keyboard.press('Alt+ArrowDown');
    await saved;
    await p.reload();
    expect(await items.nth(1).getAttribute('data-id')).toBe(first);

    await open(p, `hkp/cms/lesson/${lessons.drip}`);
    await p.getByRole('button', { name: 'Delete lesson' }).click();
    await flashOk(p);
    await open(p, moduleUrl);
    await expect(items).toHaveCount(2);
  });

  test('the module is listed and searchable in the modules list', async ({ as }) => {
    const p = await as('admin');
    await open(p, `hkp/cms/modules?q=${encodeURIComponent(MODULE)}`);
    await expect(p.locator('main')).toContainText(MODULE);
  });
});
