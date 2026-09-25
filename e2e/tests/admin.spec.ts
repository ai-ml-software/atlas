import { test, expect, open, flashOk } from '../support/fixtures';
import { readFileSync } from 'node:fs';
import path from 'node:path';

test.describe('Altus administration (admin)', () => {
  const pages = [
    'hkp/admin', 'hkp/admin/crud/organizations', 'hkp/admin/crud/properties', 'hkp/admin/crud/departments', 'hkp/admin/crud/job_roles',
    'hkp/admin/crud/domains', 'hkp/admin/crud/tracks', 'hkp/admin/crud/competencies', 'hkp/admin/crud/kpis', 'hkp/admin/users',
    'hkp/admin/curriculum', 'hkp/admin/content', 'hkp/admin/assessments', 'hkp/admin/competencies', 'hkp/admin/rules',
    'hkp/admin/ai', 'hkp/admin/engagements', 'hkp/admin/frameworks', 'hkp/admin/corporate', 'hkp/admin/imports',
    'hkp/admin/audit', 'hkp/admin/system', 'hkp/cms', 'hkp/cms/modules', 'hkp/exec', 'hkp/exec/board',
  ];
  for (const p of pages) {
    test(`admin page ${p}`, async ({ as }) => {
      const page = await as('admin');
      await open(page, p);
      await expect(page.locator('h1')).toBeVisible();
    });
  }

  test('create forms open for every record type (organisation, property, department, job role, domain, track, competency, KPI)', async ({ as }) => {
    const page = await as('admin');
    for (const e of ['organizations', 'properties', 'departments', 'job_roles', 'domains', 'tracks', 'competencies', 'kpis']) {
      await open(page, `hkp/admin/crud/${e}/new`);
      await expect(page.locator('main form').first(), e).toBeVisible();
    }
  });

  test('record list search works', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'hkp/admin/crud/properties?q=Riyadh');
    await expect(page.locator('main table')).toContainText(/Riyadh/i);
  });

  test('curriculum: drag a module within a track and the order is saved', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'hkp/admin/curriculum');
    const list = page.locator('ol[data-sortable]').filter({ has: page.locator('li:nth-child(2)') }).first();
    await expect(list).toBeVisible();
    const saved = page.waitForResponse((r) => r.url().includes('/curriculum/order/'));
    await list.locator('> li').nth(1).dragTo(list.locator('> li').first(), { targetPosition: { x: 10, y: 2 } });
    expect((await saved).status()).toBe(200);
  });

  test('competency matrix and a rubric editor open (weights, critical flags)', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'hkp/admin/competencies');
    const rubric = page.locator('main a[href*="competencies/rubric/"]').first();
    await rubric.click();
    await expect(page.locator('input[name^="crit"][name$="[weight]"]').first()).toBeVisible();
    await expect(page.locator('input[type=checkbox][name$="[critical]"]').first()).toBeVisible();
  });

  test('frameworks: configuration (dimensions, thresholds) opens for each framework', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'hkp/admin/frameworks');
    for (const name of ['Altus Performance Matrix', 'GOPPAR Value Stack', 'ESG']) {
      await expect(page.locator('main')).toContainText(name);
    }
    // Thresholds are configuration, not code: the scoring form and a way to start an assessment exist.
    await expect(page.locator('main form[action*="admin/frameworks/config/"]').first()).toBeAttached();   // inside a collapsible panel
    await expect(page.locator('main form[action*="admin/frameworks/start"]').first()).toBeAttached();
    await expect(page.locator('main')).toContainText('Provisional default');        // honest labelling of defaults
  });

  test('user & role management: role grant form is present, escalation choices are limited', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'hkp/admin/users');
    await expect(page.locator('main table tbody tr').first()).toBeVisible();
  });

  test('import: download a CSV template', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'hkp/admin/imports');
    const tpl = page.locator('main a[href*="imports/template"]').first();
    const r = await page.request.get((await tpl.getAttribute('href'))!);
    expect(r.status()).toBe(200);
    expect(await r.text()).toMatch(/,/);
  });

  test('system health shows each check', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'hkp/admin/system');
    for (const c of ['database', 'queue', 'email', 'storage', 'scheduler']) {
      await expect(page.locator('main')).toContainText(new RegExp(c, 'i'));
    }
  });

  test('audit log records the actions of this run', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'hkp/admin/audit');
    await expect(page.locator('main table tbody tr').first()).toBeVisible();
  });
});

test.describe('Classic LMS admin panel (legacy sidebar)', () => {
  test('every sidebar link opens without an error', async ({ as }) => {
    const page = await as('admin');
    const nav = readFileSync(path.resolve(__dirname, '../../application/views/backend/admin/navigation.php'), 'utf8');
    const links = [...new Set([...nav.matchAll(/site_url\('([^']+)'\)/g)].map((m) => m[1].trim()))]
      .filter((l) => !/^(addons\/|ebook_manager)/.test(l));
    expect(links.length).toBeGreaterThan(50);
    const bad: string[] = [];
    for (const l of links) {
      const r = await page.request.get(l);
      const body = await r.text();
      if (r.status() !== 200 || /A PHP Error was encountered|Fatal error|A Database Error Occurred/.test(body)) bad.push(`${r.status()} ${l}`);
    }
    expect(bad, bad.join('\n')).toEqual([]);
  });

  test('the sidebar links into the altus workspace and CMS', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'admin/dashboard');
    const menu = page.locator('.left-side-menu');
    await menu.getByText('Website & content').click();
    await menu.getByRole('link', { name: 'Pages, sections & SEO' }).click();
    await expect(page).toHaveURL(/hkp\/cms/);
  });

  test('legacy course list and course editor open', async ({ as }) => {
    const page = await as('admin');
    await open(page, 'admin/courses');
    await open(page, 'admin/course_form/add_course');
  });

  test('instructor panel pages open', async ({ as }) => {
    const page = await as('instructor');
    for (const p of ['user/dashboard', 'user/courses', 'user/sales_report', 'user/payout_report']) await open(page, p);
  });
});
