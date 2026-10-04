import { test, expect, open } from '../support/fixtures';

const TABS = ['overview', 'levels', 'connections', 'console', 'activity', 'setup'];

test('sidebar shows MCP & AI connections and every console tab renders', async ({ as }) => {
  test.setTimeout(120000);
  const p = await as('admin');
  await open(p, 'hkp/mcp');
  const pinned = p.locator('.studio-nav-primary [data-nav="mcp_console"]');
  await expect(pinned).toBeVisible();
  await expect(pinned).toHaveAttribute('aria-current', 'page');
  await expect(p.locator('details[data-nav-group="platform"] [data-nav="mcp_console"]')).toHaveCount(1);
  await expect(p.locator('#mcpc-checks .mcpc-dot').first()).toBeVisible();
  for (const tab of TABS) {
    await open(p, `hkp/mcp?tab=${tab}`);
    await expect(p.locator(`.mcpc-tabs [data-tab-link="${tab}"]`)).toHaveAttribute('aria-current', 'page');
  }
  await open(p, 'hkp/cms');
  await expect(p.locator('.studio-nav-primary [data-nav="mcp_console"]')).toBeVisible();
});

test('test console runs a tool and the read/write/full smoke test', async ({ as }) => {
  test.setTimeout(180000);
  const p = await as('admin');
  await open(p, 'hkp/mcp?tab=console');
  await expect(p.locator('#mcpc-tools-count')).toContainText('/');
  await p.locator('[data-tool="altus_site_info"]').click();
  await p.locator('#mcpc-exec').click();
  await expect(p.locator('#mcpc-result-status .mcpc-status--ok')).toBeVisible();
  // A write tool at Read level is refused by the server.
  await p.locator('[data-tool="altus_create"]').click();
  await p.locator('[data-mode="raw"]').click();
  await p.locator('#mcpc-raw').fill('{"type":"articles","data":{"title_en":"x"}}');
  await p.locator('#mcpc-exec').click();
  await expect(p.locator('#mcpc-result-status .mcpc-status--denied')).toBeVisible();
  await p.locator('[data-smoke]').click();
  await expect(p.locator('#mcpc-matrix tbody tr')).toHaveCount(4, { timeout: 60000 });
  await expect(p.locator('#mcpc-smoke-sum')).toContainText('12/12');
});

test('Arabic RTL and mobile layout without overflow', async ({ as }) => {
  test.setTimeout(120000);
  const p = await as('admin');
  await p.setViewportSize({ width: 390, height: 844 });
  for (const tab of TABS) {
    await open(p, `hkp/mcp?tab=${tab}&lang=ar`);
    await expect(p.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(p.locator('h1')).toContainText('MCP');
    expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), tab + ' overflow').toBe(true);
  }
  await open(p, 'hkp/mcp?tab=overview&lang=en');
});

test('non-administrators cannot see or call the console', async ({ as }) => {
  const p = await as('learner');
  expect((await p.request.get('hkp/mcp')).status()).toBe(403);
  expect((await p.request.post('hkp/mcp/smoke', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, data: {} })).status()).toBe(403);
  await open(p, 'hkp');
  await expect(p.locator('[data-nav="mcp_console"]')).toHaveCount(0);
  const a = await as('admin');
  expect((await a.request.post('hkp/mcp/smoke', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, data: {} })).status(), 'CSRF required').toBe(403);
});

