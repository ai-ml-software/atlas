import { test, expect, open } from '../support/fixtures';
import { userId, one } from '../support/db';

test.describe('Access control and security', () => {
  test('a learner is refused every management and administration area', async ({ as }) => {
    const p = await as('learner');
    for (const path of ['hkp/admin', 'hkp/admin/users', 'hkp/admin/system', 'hkp/cms', 'hkp/cms/modules', 'hkp/team/assign', 'hkp/exec']) {
      const r = await p.goto(path);
      expect(r!.status(), path).toBe(403);
      await expect(p.locator('body'), path).not.toContainText(/PHP Error/);
    }
  });

  test('the workspace navigation never offers a page the role cannot open', async ({ as }) => {
    for (const role of ['learner', 'supervisor', 'exec', 'gm'] as const) {
      const p = await as(role);
      await open(p, 'hkp');
      const hrefs = await p.locator('.hkp-nav a').evaluateAll((as) => as.map((a) => (a as HTMLAnchorElement).href));
      for (const h of hrefs.filter((h) => h.includes('/hkp'))) {
        expect((await p.request.get(h)).status(), `${role} → ${h}`).toBe(200);
      }
    }
  });

  test('restricted knowledge of another client is not even confirmed to exist (404)', async ({ as }) => {
    const other = one(`SELECT id FROM ha_sop_document WHERE organization_id <> (SELECT organization_id FROM ha_profile WHERE user_id=${userId('demo.learner@altusdemo.sa')}) AND status='published' LIMIT 1`);
    test.skip(!other, 'no other-tenant knowledge');
    const p = await as('learner');
    const r = await p.goto(`hkp/knowledge/item/${other}`);
    expect(r!.status()).toBe(404);
  });

  test('a learner cannot read another person’s practical assessment', async ({ as }) => {
    const pa = one(`SELECT id FROM ha_practical_assessment WHERE user_id <> ${userId('omar.learner@dyafagroup.sa')} LIMIT 1`);
    test.skip(!pa, 'no practical assessments yet');
    const p = await as('student');
    const r = await p.goto(`hkp/assess/practical/${pa}`);
    expect([403, 404]).toContain(r!.status());
  });

  test('POST without the CSRF token is rejected', async ({ as }) => {
    const p = await as('admin');
    const r = await p.request.post('hkp/cms/page_create', { form: { title_en: 'E2E csrf', slug_en: 'e2e-csrf-should-not-exist' }, maxRedirects: 0 });
    expect(r.status(), 'refused or bounced, never processed').not.toBe(201);
    expect(one(`SELECT COUNT(*) FROM ha_page WHERE code='e2e-csrf-should-not-exist'`)).toBe('0');
  });

  test('reflected XSS: a script in the search query is never echoed as HTML', async ({ page, as }) => {
    // Stored XSS (script in page content) is covered in cms-pages.spec.
    for (const [ctx, path] of [[page, 'en/search?q='], [await as('learner'), 'hkp/search?q=']] as const) {
      const r = await ctx.goto(path + encodeURIComponent('<script>alert(1)</script>'));
      expect(r!.status()).toBe(200);
      const html = await r!.text();
      expect(html, path).not.toContain('<script>alert(1)</script>');
    }
  });

  test('security headers are sent on workspace pages', async ({ as }) => {
    const p = await as('learner');
    const r = await p.request.get('hkp');
    const h = r.headers();
    expect(h['x-frame-options'] || h['content-security-policy']).toBeTruthy();
    expect(h['x-content-type-options'], 'sent once').toBe('nosniff');
    expect(h['x-frame-options'], 'sent once').toBe('SAMEORIGIN');
    expect(h['referrer-policy']).toBeTruthy();
  });

  test('API: missing or bad keys get the standard 401 envelope', async ({ request }) => {
    for (const headers of [{}, { Authorization: 'Bearer ha_bogus_key' }]) {
      const r = await request.get('api/v1/readiness', { headers });
      expect(r.status()).toBe(401);
      const j = await r.json();
      expect(j.success).toBe(false);
      expect(j.message).toMatch(/^Unauthorised/);
      expect(JSON.stringify(j)).not.toMatch(/stack|trace|\.php/i);
    }
  });

  test('certificate verification discloses only minimal data', async ({ page }) => {
    await open(page, 'verify/E2E-VERIFY-0001');
    await expect(page.locator('body')).not.toContainText('@altusdemo.sa');
  });
});
