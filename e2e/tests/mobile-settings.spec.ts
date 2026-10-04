import { test, expect, open } from '../support/fixtures';
import { sql } from '../support/db';

test('mobile settings are visible, active, responsive and tenant restricted', async ({ as }) => {
  const admin = await as('admin');
  for (const lang of ['en', 'ar']) {
    for (const width of [1440, 390]) {
      await admin.setViewportSize({ width, height: 900 });
      await open(admin, `hkp/admin/mobile?lang=${lang}`);
      await expect(admin.locator('.hkp-nav a[aria-current=page]')).toHaveAttribute('href', /admin\/mobile$/);
      await expect(admin.locator('[data-mobile-settings]')).toBeVisible();
      expect(await admin.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    }
  }
  for (const role of ['learner', 'orgAdmin', 'instructor'] as const) {
    const p = await as(role);
    expect((await p.request.get('hkp/admin/mobile')).status()).toBe(403);
    expect((await p.request.post('hkp/admin/mobile/key_create', { form: { name: 'denied', platform: 'all' } })).status()).toBe(403);
  }
});

test('admin and actual Expo app test configuration, reject bad keys, save and reset', async ({ as, browser }) => {
  test.setTimeout(120000);
  const p = await as('admin');
  const name = `Mobile browser fixture ${Date.now()}`;
  let keyId = 0;
  try {
    await open(p, 'hkp/admin/mobile?lang=en');
    await p.locator('#k-name').fill(name);
    await p.getByRole('button', { name: 'Generate key', exact: true }).click();
    const key = (await p.locator('[data-mobile-new-key] code').innerText()).trim();
    keyId = Number(sql(`SELECT id FROM ha_mobile_app_key WHERE name='${name}'`)[0][0]);
    const preflight = await p.request.fetch('api/v1/mobile/config', { method: 'OPTIONS', headers: { Origin: 'http://127.0.0.1:8083' } });
    expect(preflight.status()).toBe(204);
    expect(preflight.headers()['access-control-allow-headers']).toContain('X-App-Key');
    const trusted = await p.request.get('api/v1/mobile/config', { headers: { 'X-App-Key': key, Origin: 'http://127.0.0.1:8083' } });
    expect(trusted.headers()['access-control-allow-origin']).toBe('http://127.0.0.1:8083');
    expect(trusted.headers()['access-control-expose-headers']).toContain('ETag');
    const untrusted = await p.request.get('api/v1/mobile/config', { headers: { 'X-App-Key': key, Origin: 'https://untrusted.example' } });
    expect(untrusted.headers()['access-control-allow-origin']).toBeUndefined();
    await p.locator('[data-mobile-test]').click();
    await expect(p.locator('[data-mobile-test-status]')).toContainText('Connection successful');
    await p.reload();
    await p.locator('[data-mobile-test-key]').fill(key);
    await p.locator('[data-mobile-test]').click();
    await expect(p.locator('[data-mobile-test-status]')).toContainText('Connection successful');
    await p.screenshot({ path: '../docs/screenshots/mobile-admin-settings.png', fullPage: true,
      mask: [p.locator('[data-mobile-test-key]')], maskColor: '#ded8cf' });
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const app = await context.newPage();
    await app.goto('http://127.0.0.1:8083/sign-in');
    await expect(app.getByRole('textbox', { name: 'Personal API key', exact: true })).toBeVisible();
    await expect(app.getByRole('button', { name: 'Get my personal API key', exact: true })).toBeVisible();
    await app.getByRole('button', { name: 'Server setup', exact: true }).click();
    await app.getByRole('textbox', { name: 'Platform URL', exact: true }).fill('http://127.0.0.1:8099');
    await app.getByRole('textbox', { name: 'App key', exact: true }).fill(key);
    await app.getByRole('button', { name: 'Test connection', exact: true }).click();
    await expect(app.getByText(/Connection successful/)).toBeVisible();
    await app.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(app.getByText('Server saved and configuration loaded.', { exact: true })).toBeVisible();
    await app.screenshot({ path: '../docs/screenshots/mobile-server-setup.png' });
    sql(`UPDATE ha_mobile_app_key SET revoked_at=NOW() WHERE id=${keyId}`);
    await app.getByRole('button', { name: 'Test connection', exact: true }).click();
    await expect(app.getByText(/The app key was rejected/)).toBeVisible();
    await app.getByRole('button', { name: 'Reset to built-in server', exact: true }).click();
    await expect(app.getByText('Server setup cleared.', { exact: true })).toBeVisible();
    await context.close();
  } finally {
    if (keyId) sql(`UPDATE ha_mobile_app_key SET revoked_at=COALESCE(revoked_at,NOW()) WHERE id=${keyId}`);
  }
});

test('mobile Settings exposes both connection and personal key options', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  await context.addInitScript(() => localStorage.setItem('altus.mobile.local.v1', JSON.stringify({ mode: 'demo', role: 'learner', locale: 'en' })));
  const app = await context.newPage();
  await app.goto('http://127.0.0.1:8083/settings');
  await expect(app.getByRole('button', { name: /^Server setup/ })).toBeVisible();
  await expect(app.getByRole('button', { name: /Personal API key/ })).toBeVisible();
  await app.getByRole('button', { name: /^Server setup/ }).click();
  await expect(app.getByRole('textbox', { name: 'App key', exact: true })).toBeVisible();
  await context.close();
});

test('learner creates an own mobile key with password confirmation and no integration permission', async ({ as, browser }) => {
  test.setTimeout(120000);
  const p = await as('learner');
  const before = sql("SELECT id FROM ha_api_key WHERE user_id=(SELECT id FROM users WHERE email='demo.learner@altusdemo.sa')").flat();
  let id = 0;
  try {
    await open(p, 'account_security');
    await expect(p.locator('[data-personal-mobile-key]')).toBeVisible();
    await p.locator('#mobile-key-password').fill('incorrect');
    await p.getByRole('button', { name: 'Create personal mobile key', exact: true }).click();
    await expect(p.locator('body')).toContainText('Your password was not correct.');
    await p.locator('#mobile-key-password').fill('Academy#2026');
    await p.getByRole('button', { name: 'Create personal mobile key', exact: true }).click();
    const key = await p.locator('#ha-newkey').inputValue();
    id = Number(sql("SELECT id FROM ha_api_key WHERE user_id=(SELECT id FROM users WHERE email='demo.learner@altusdemo.sa') ORDER BY id DESC LIMIT 1")[0][0]);
    expect(before.includes(String(id))).toBe(false);
    const scopes = sql(`SELECT scopes FROM ha_api_key WHERE id=${id}`)[0][0];
    expect(scopes).toContain('profile:read'); expect(scopes).toContain('mobile:write'); expect(scopes).not.toContain('ai:generate');
    expect((await p.request.get('mobile_api/me', { headers: { Authorization: `Bearer ${key}` } })).status()).toBe(200);
    expect((await p.request.get('hkp/admin/mobile')).status()).toBe(403);
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const app = await context.newPage(); await app.goto('http://127.0.0.1:8083/sign-in');
    await app.getByRole('textbox', { name: 'Platform URL', exact: true }).fill('http://127.0.0.1:8099');
    await app.getByRole('textbox', { name: 'Personal API key', exact: true }).fill(key);
    await app.getByRole('button', { name: 'Connect account', exact: true }).click();
    await expect(app).toHaveURL(/\/home$/);
    await expect(app.getByText('LIVE ACCOUNT', { exact: true }).first()).toBeVisible();
    await context.close();
    await open(p, 'account_security'); await expect(p.locator('#ha-newkey')).toHaveCount(0);
  } finally { if (id) sql(`UPDATE ha_api_key SET revoked_at=NOW() WHERE id=${id}`); }
});

for (const role of ['admin', 'instructor'] as const) {
  test(`${role} creates an own key and connects to the correct app workspace`, async ({ as, browser }) => {
    test.setTimeout(120000);
    const p = await as(role); let id = 0;
    try {
      await open(p, 'account_security');
      await p.locator('#mobile-key-password').fill('Academy#2026');
      await p.getByRole('button', { name: 'Create personal mobile key', exact: true }).click();
      const key = await p.locator('#ha-newkey').inputValue();
      const prefix = key.split('_')[1];
      id = Number(sql(`SELECT id FROM ha_api_key WHERE prefix='${prefix}'`)[0][0]);
      const me = await p.request.get('mobile_api/me', { headers: { Authorization: `Bearer ${key}` } });
      expect(me.status()).toBe(200); expect((await me.json()).data.roles).toContain(role === 'admin' ? 'super_admin' : 'instructor');
      const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
      const app = await context.newPage(); await app.goto('http://127.0.0.1:8083/sign-in');
      await app.getByRole('textbox', { name: 'Platform URL', exact: true }).fill('http://127.0.0.1:8099');
      await app.getByRole('textbox', { name: 'Personal API key', exact: true }).fill(key);
      await app.getByRole('button', { name: 'Connect account', exact: true }).click();
      await expect(app).toHaveURL(/\/home$/);
      await app.getByRole('button', { name: 'Profile', exact: true }).click();
      await expect(app.getByRole('button', { name: /^ALTUS administration/ })).toBeVisible();
      await context.close();
    } finally { if (id) sql(`UPDATE ha_api_key SET revoked_at=NOW() WHERE id=${id}`); }
  });
}
