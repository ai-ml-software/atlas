import { test, expect, open, flashOk, stamp } from '../support/fixtures';
import { userId } from '../support/db';

test.describe('Property management (demo.gm@altusdemo.sa, ALTUS Demo Hotel Riyadh)', () => {
  test('team dashboard shows capability tiles and links only to allowed pages', async ({ as }) => {
    const p = await as('gm');
    await open(p, 'hkp/team');
    await expect(p.locator('.hkp-tile').first()).toBeVisible();
    for (const href of await p.locator('main a[href*="/hkp/"]').evaluateAll((as) => as.map((a) => (a as HTMLAnchorElement).href))) {
      const r = await p.request.get(href);
      expect(r.status(), href).toBe(200);           // no dead links (403/404/500) for this role
    }
  });

  test('people list → employee evidence record', async ({ as }) => {
    const p = await as('gm');
    await open(p, 'hkp/team/people');
    const person = p.locator('main a[href*="hkp/team/employee/"]').first();
    await expect(person).toBeVisible();
    await person.click();
    await expect(p.locator('h1')).toBeVisible();
    await expect(p.locator('main')).toContainText(/competenc|readiness|learning/i);
  });

  test('assign a module to an employee', async ({ as }) => {
    const p = await as('gm');
    await open(p, 'hkp/team/assign');
    const title = `E2E assignment ${stamp()}`;
    await p.locator('#at').fill(title);
    await p.locator('#aco').selectOption({ index: 0 });
    await p.locator('#aus').selectOption(String(userId('demo.learner@altusdemo.sa')));
    await p.getByRole('button', { name: 'Assign' }).click();
    await flashOk(p);
    await expect(p.locator('main')).toContainText(title);
  });

  test('assigning without choosing anything is refused with a clear message', async ({ as }) => {
    const p = await as('gm');
    await open(p, 'hkp/team/assign');
    await p.locator('#at').fill(`E2E empty ${stamp()}`);
    await p.getByRole('button', { name: 'Assign' }).click();
    await expect(p.locator('.hkp-flash--error')).toBeVisible();
  });

  for (const path of ['hkp/team/gaps', 'hkp/team/readiness', 'hkp/team/opening', 'hkp/team/cohorts', 'hkp/team/actions',
    'hkp/team/kpis', 'hkp/team/audits', 'hkp/team/certifications', 'hkp/team/reports', 'hkp/team/branding', 'hkp/assess/queue']) {
    test(`management page ${path} renders`, async ({ as }) => {
      const p = await as('gm');
      await open(p, path);
      await expect(p.locator('h1')).toBeVisible();
    });
  }

  test('export a report to Excel and CSV', async ({ as }) => {
    const p = await as('gm');
    await open(p, 'hkp/team/reports');
    await p.locator('main section.hkp-card').first().getByRole('button', { name: 'View' }).click();
    await expect(p).toHaveURL(/hkp\/team\/reports\/\w+/);
    await expect(p.locator('h1')).toBeVisible();
    for (const [label, sig] of [['Excel', 'PK'], ['CSV', '']] as const) {
      const [dl] = await Promise.all([p.waitForEvent('download'), p.locator('main a', { hasText: label }).first().click()]);
      const path = await dl.path();
      const buf = require('fs').readFileSync(path);
      expect(buf.length, label).toBeGreaterThan(20);
      if (sig) expect(buf.subarray(0, 2).toString(), 'xlsx is a real zip').toBe(sig);
      else expect(buf.toString('utf8')).not.toMatch(/^[=+\-@]/m);   // CSV injection neutralised
    }
  });

  test('the GM sees the certificate register but cannot revoke (Altus governance only)', async ({ as }) => {
    const p = await as('gm');
    await open(p, 'hkp/team/certifications');
    const row = p.locator('tr', { hasText: 'E2E-FOA-2026-000001' });
    await expect(row).toBeVisible();
    await expect(row.getByRole('button', { name: 'Revoke' })).toHaveCount(0);
  });

  test('an Altus admin revokes a certificate; public verification then shows it as revoked', async ({ as, page }) => {
    const p = await as('admin');
    await open(p, 'hkp/team/certifications');
    const row = p.locator('tr', { hasText: 'E2E-FOA-2026-000001' });
    await expect(row).toBeVisible();
    await row.locator('input[name=reason]').fill('E2E: issued in error');
    await row.getByRole('button', { name: 'Revoke' }).click();
    await flashOk(p);
    await open(page, 'verify/E2E-VERIFY-0001');
    await expect(page.locator('body')).toContainText(/revoked/i);
  });

  test('tenant isolation: the GM cannot open another client’s employee', async ({ as }) => {
    const p = await as('gm');
    const res = await p.goto(`hkp/team/employee/${userId('omar.learner@dyafagroup.sa')}`);
    expect([403, 404]).toContain(res!.status());
  });

  test('training manager can reach assignment and cohorts', async ({ as }) => {
    const p = await as('training');
    await open(p, 'hkp/team/assign');
    await open(p, 'hkp/team/cohorts');
  });
});
