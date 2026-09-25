import { test as setup, expect } from '@playwright/test';
import { USERS, Role, stateFile } from '../support/users';

// Sign in once per role through the real login form and keep the session.
for (const role of Object.keys(USERS) as Role[]) {
  setup(`sign in as ${role}`, async ({ page }) => {
    const u = USERS[role];
    await page.goto('login');
    await page.locator('#login-form #email').fill(u.email);
    await page.locator('#login-form #password').fill(u.password);
    await page.locator('#login-form button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    await page.goto('hkp');
    // /hkp sends each role to its own home (learner dashboard, /hkp/team, /hkp/admin, /hkp/exec)
    await expect(page, `${u.email} should be signed in`).toHaveURL(/\/hkp(\/(team|admin|exec))?\/?$/);
    await expect(page.locator('.hkp-nav')).toBeVisible();
    await page.context().storageState({ path: stateFile(role) });
  });
}
