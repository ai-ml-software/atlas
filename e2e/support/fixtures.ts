import { test as base, expect, Page, Browser } from '@playwright/test';
import { Role, stateFile } from './users';

/**
 * `as(role)` opens a page already signed in as that role. Every page:
 *  - accepts confirm() dialogs (delete/submit buttons use them),
 *  - fails the test on any uncaught JavaScript error,
 *  - exposes expectHealthy() which asserts the page rendered without a PHP error.
 */
type Fixtures = { as: (role: Role) => Promise<Page> };

export const PHP_ERROR = /A PHP Error was encountered|Fatal error|A Database Error Occurred|An uncaught Exception|Parse error/;

async function prepare(page: Page) {
  const errors: string[] = [];
  page.on('dialog', (d) => d.accept());
  page.on('pageerror', (e) => errors.push(e.message));
  (page as any).__jsErrors = errors;
  return page;
}

export const test = base.extend<Fixtures>({
  page: async ({ page }, use) => {
    await prepare(page);
    await use(page);
    expect((page as any).__jsErrors, 'no JavaScript errors').toEqual([]);
  },
  as: async ({ browser }, use) => {
    const opened: Page[] = [];
    await use(async (role: Role) => {
      const ctx = await (browser as Browser).newContext({ storageState: stateFile(role) });
      const p = await prepare(await ctx.newPage());
      opened.push(p);
      return p;
    });
    for (const p of opened) {
      expect((p as any).__jsErrors, 'no JavaScript errors').toEqual([]);
      await p.context().close();
    }
  },
});

export { expect };

/** Navigate and assert: HTTP 200, no PHP error text, still signed in. */
export async function open(page: Page, path: string) {
  const res = await page.goto(path);
  expect(res?.status(), `${path} status`).toBe(200);
  await expect(page.locator('body')).not.toContainText(PHP_ERROR);
  expect(page.url(), `${path} should not bounce to login`).not.toMatch(/\/login(\?|$)/);
  return res;
}

export async function flashOk(page: Page) {
  await expect(page.locator('.hkp-flash--ok')).toBeVisible();
  await expect(page.locator('.hkp-flash--error')).toHaveCount(0);
}

export const stamp = () => Date.now().toString(36);
