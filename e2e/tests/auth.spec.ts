import { test, expect } from '../support/fixtures';
import { USERS } from '../support/users';
import { sql } from '../support/db';

async function login(page, email: string, password: string) {
  await page.goto('login');
  await page.locator('#login-form #email').fill(email);
  await page.locator('#login-form #password').fill(password);
  await page.locator('#login-form button[type=submit]').click();
  await page.waitForLoadState('networkidle');
}

test.describe('Sign-in (README credentials)', () => {
  test.afterEach(() => { sql(`UPDATE users SET sessions='[]' WHERE sessions IS NOT NULL AND sessions <> '[]'`); });

  test('admin signs in and reaches the classic admin panel and the workspace', async ({ page }) => {
    await login(page, USERS.admin.email, USERS.admin.password);
    await page.goto('admin/dashboard');
    await expect(page).toHaveURL(/admin\/dashboard/);
    await expect(page.locator('.left-side-menu')).toContainText('altus Workspace');
    await page.goto('hkp');
    await expect(page).toHaveURL(/\/hkp\/admin/);
  });

  test('instructor signs in and reaches the instructor panel', async ({ page }) => {
    await login(page, USERS.instructor.email, USERS.instructor.password);
    await page.goto('user/dashboard');
    await expect(page).toHaveURL(/user\/dashboard/);
    await expect(page.locator('.left-side-menu')).toContainText('altus Workspace');
  });

  test('student signs in and sees their courses and learner workspace', async ({ page }) => {
    await login(page, USERS.student.email, USERS.student.password);
    await page.goto('home/my_courses');
    await expect(page).toHaveURL(/my_courses/);
    await page.goto('hkp');
    await expect(page).toHaveURL(/\/hkp\/?$/);
    await expect(page.locator('.hkp-nav')).toContainText(/My learning/);
  });

  test('login lands in the HK&P workspace; login/sign-up pages bounce signed-in users there', async ({ page }) => {
    await login(page, USERS.student.email, USERS.student.password);
    await expect(page).toHaveURL(/\/hkp/);
    for (const p of ['login', 'sign_up']) {
      await page.goto(p);
      await expect(page, p).toHaveURL(/\/hkp/);
    }
  });

  test('the site header menu links to the HK&P workspace', async ({ page }) => {
    await login(page, USERS.student.email, USERS.student.password);
    await page.goto('home');
    const link = page.locator('a[data-hkp-menu]').first();
    await expect(link).toHaveAttribute('href', /\/hkp$/);
    await page.goto(await link.getAttribute('href'));
    await expect(page.locator('.hkp-nav')).toBeVisible();
  });

  test('a wrong password is refused', async ({ page }) => {
    await login(page, USERS.student.email, 'wrong-password');
    await page.goto('hkp');
    await expect(page).toHaveURL(/login/);
  });

  test('signed-out visitors are sent to the login page from protected areas', async ({ page }) => {
    for (const p of ['hkp', 'hkp/team', 'hkp/admin', 'hkp/cms', 'admin/dashboard']) {
      await page.goto(p);
      await expect(page, p).toHaveURL(/login/);
    }
  });

  test('sign out ends the session', async ({ page }) => {
    await login(page, USERS.learner.email, USERS.learner.password);
    await page.goto('hkp');
    await page.locator('details.hkp-user > summary').click();       // open the user menu
    await page.getByRole('link', { name: /Sign out/ }).click();
    await page.goto('hkp');
    await expect(page).toHaveURL(/login/);
  });
});
