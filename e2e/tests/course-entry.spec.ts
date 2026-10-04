import { test, expect, Page } from '@playwright/test';
import { sql, one, userId } from '../support/db';
import { USERS } from '../support/users';
import { execFileSync } from 'node:child_process';

test.describe('Public course start and My Learning', () => {
  test.skip(!process.env.HKP_TEST_DATABASE, 'Run with playwright.isolated.config.ts to preserve real learner progress');
  let course: number;
  let uid: number;
  let lessons: number[];
  let courseTitle: string;
  let slugs: { en: string; ar: string };

  test.beforeAll(async ({ request }) => {
    const response = await request.get('en/courses');
    expect(response.headers()['x-ha-test-database']).toBe('atlas_hospitality_test');
    course = Number(one("SELECT id FROM ha_course WHERE code='dy-active-listening'"));
    uid = userId(USERS.student.email);
    const slugRow = sql(`SELECT slug_en,slug_ar FROM ha_course WHERE id=${course}`)[0];
    slugs = { en: slugRow[0], ar: slugRow[1] };
    courseTitle = one(`SELECT title FROM ha_course_translation WHERE course_id=${course} AND locale='en'`)!;
    sql(`UPDATE ha_course SET status='published' WHERE id=${course}`);
    sql(`UPDATE ha_lesson SET status='published' WHERE course_id=${course}`);
    sql(`UPDATE ha_assessment SET status='published' WHERE course_id=${course}`);
    lessons = sql(`SELECT id FROM ha_lesson WHERE course_id=${course} AND status='published' ORDER BY sort_order`).map(r => Number(r[0]));
    expect(course).toBeGreaterThan(0);
    expect(lessons.length).toBeGreaterThan(1);
  });

  test.beforeEach(({page}) => {
    page.on('dialog', dialog => dialog.accept());
    sql(`DELETE FROM ha_assessment_attempt WHERE user_id=${uid} AND assessment_id IN (SELECT id FROM ha_assessment WHERE course_id=${course})`);
    sql(`DELETE FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`);
    sql(`UPDATE users SET sessions='[]' WHERE id=${uid}`);
    sql(`UPDATE ha_profile SET locale='en' WHERE user_id=${uid}`);
    sql(`DELETE FROM ha_user_2fa WHERE user_id=${uid}`);
  });
  test.afterEach(() => {
    sql(`DELETE FROM ha_user_2fa WHERE user_id=${uid}`);
    sql(`UPDATE users SET status=1 WHERE id=${uid}`);
    sql(`UPDATE ha_profile SET status='active' WHERE user_id=${uid}`);
  });

  async function login(page: Page, password = USERS.student.password) {
    await page.locator('#login-form #email').fill(USERS.student.email);
    await page.locator('#login-form #password').fill(password);
    await page.locator('#login-form button[type=submit]').click();
    await page.waitForLoadState('domcontentloaded');
  }

  async function selectCourse(page: Page, locale = 'en') {
    await page.goto(locale + '/courses/' + encodeURIComponent(slugs[locale as 'en' | 'ar']));
    await page.locator('[data-course-start]').click();
  }

  async function expectMyLearning(page: Page) {
    await page.goto('hkp/learn?lang=en');
    await expect(page.locator(`a[href*="hkp/learn/module/${course}"]`)).toContainText(courseTitle);
    expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`)).toBe('1');
  }

  test('guest selection survives an invalid password, then appears in My Learning', async ({ page }) => {
    await selectCourse(page);
    await expect(page).toHaveURL(/\/login$/);
    expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`)).toBe('0');
    await login(page, 'wrong-password');
    await expect(page).toHaveURL(/\/login$/);
    await login(page);
    await expect(page).toHaveURL(new RegExp(`/hkp/learn/module/${course}\\?lang=en$`));
    await expect(page.getByRole('button', { name: 'Add to my learning', exact: true })).toHaveCount(0);
    await expectMyLearning(page);
  });

  test('signed-in start adds once; revisiting shows View course and preserves progress', async ({ page }) => {
    await page.goto('login'); await login(page);
    await selectCourse(page);
    await expect(page).toHaveURL(new RegExp(`/hkp/learn/module/${course}`));
    const eid = Number(one(`SELECT id FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`));
    sql(`UPDATE ha_enrollment SET progress_percentage=25,lessons_completed=1,time_spent_seconds=123 WHERE id=${eid}`);
    await page.goto('en/courses/active-listening');
    await expect(page.locator('[data-course-start]')).toHaveText('View course');
    await page.locator('[data-course-start]').click();
    expect(one(`SELECT CONCAT(id,':',progress_percentage,':',lessons_completed,':',time_spent_seconds) FROM ha_enrollment WHERE id=${eid}`)).toBe(`${eid}:25.00:1:123`);
    await expectMyLearning(page);
    await page.goto('login');
    await expect(page).not.toHaveURL(/\/login$/);
  });

  test('a workspace deep link preserves its course and Arabic query across login', async ({ page }) => {
    await page.goto(`hkp/learn/module/${course}?lang=ar`);
    await expect(page).toHaveURL(/\/login$/);
    await login(page);
    await expect(page).toHaveURL(new RegExp(`/hkp/learn/module/${course}\\?lang=ar$`));
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`)).toBe('0');
  });

  test('ordinary sign-in has no enrollment side effect; logout clears an abandoned selection', async ({ page }) => {
    await selectCourse(page);
    await page.goto('login/logout');
    await page.goto('login'); await login(page);
    await expect(page).toHaveURL(/\/hkp\/?$/);
    expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`)).toBe('0');
    await page.goto('hkp/learn');
    await expect(page.getByRole('link', {name:'Courses',exact:true}).first()).toBeVisible();
  });

  for (const width of [1440, 390]) {
    test(`Arabic course start keeps RTL and fits a ${width}px viewport`, async ({ page }) => {
      await page.setViewportSize({ width, height: 844 });
      await selectCourse(page, 'ar');
      await login(page);
      await expect(page).toHaveURL(new RegExp(`/hkp/learn/module/${course}\\?lang=ar$`));
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
      await expectMyLearning(page);
    });
  }

  test('GET, absent CSRF, and forged course IDs cannot enroll', async ({ page }) => {
    expect((await page.request.get(`academy/start/${course}`)).status()).toBe(405);
    expect((await page.request.post(`academy/start/${course}`, { form: { locale: 'en' } })).status()).toBe(403);
    await page.goto('en/courses/active-listening');
    const csrf = await page.locator('input[name=ha_csrf]').inputValue();
    expect((await page.request.post('academy/start/2147483647', { form: { locale: 'en', ha_csrf: csrf } })).status()).toBe(403);
    expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`)).toBe('0');
  });

  test('failed quiz stays locked; retry passes, records progress and unlocks the next lesson', async ({ page }) => {
    await selectCourse(page); await login(page);
    const assessment = Number(one(`SELECT assessment_id FROM ha_lesson WHERE id=${lessons[0]}`));
    expect(assessment).toBeGreaterThan(0);
    await page.goto(`hkp/learn/lesson/${lessons[1]}`);
    await expect(page).toHaveURL(/\/hkp\/learn(?:\?lang=en)?$/);
    await expect(page.locator('.hkp-flash--error')).toBeVisible();
    async function submit(correct: boolean, locale = 'en') {
      await page.goto(`hkp/assess/theory/${assessment}?lang=${locale}`);
      await expect(page.locator('fieldset.hkp-q')).toHaveCount(4);
      for (const field of await page.locator('fieldset.hkp-q').all()) {
        const name = await field.locator('input[type=radio]').first().getAttribute('name');
        const qid = Number(/\[(\d+)\]/.exec(name!)[1]);
        const oid = one(`SELECT id FROM ha_question_option WHERE question_id=${qid} AND is_correct=${correct ? 1 : 0} AND ha_retired_at IS NULL LIMIT 1`);
        await field.locator(`input[value="${oid}"]`).check();
      }
      await page.locator('form[action*="assess/theory/"] button').click();
      await expect(page).toHaveURL(/assess\/result\//);
    }
    await submit(false);
    expect(one(`SELECT passed FROM ha_assessment_attempt WHERE user_id=${uid} AND assessment_id=${assessment} ORDER BY id DESC LIMIT 1`)).toBe('0');
    await page.goto(`hkp/learn/lesson/${lessons[1]}`);
    await expect(page.locator('.hkp-flash--error')).toBeVisible();
    await submit(true, 'ar');
    expect(one(`SELECT CONCAT(attempt_no,':',passed) FROM ha_assessment_attempt WHERE user_id=${uid} AND assessment_id=${assessment} ORDER BY id DESC LIMIT 1`)).toBe('2:1');
    await page.goto(`hkp/learn/lesson/${lessons[0]}`);
    await page.locator('form[action*="learn/complete/"] button').click();
    await expect(page).toHaveURL(new RegExp(`/hkp/learn/lesson/${lessons[1]}`));
    expect(one(`SELECT lessons_completed FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`)).toBe('1');
    await expectMyLearning(page);
  });

  test('a background service-worker request preserves the locked-lesson explanation', async ({ page }) => {
    await selectCourse(page); await login(page);
    await expect(page).toHaveURL(new RegExp(`/hkp/learn/module/${course}\\?lang=en$`));
    const locked = await page.request.get(`hkp/learn/lesson/${lessons[1]}`);
    expect(locked.headers().refresh).toContain('/hkp/learn');
    expect((await page.request.get('hkp/sw.js')).status()).toBe(200);
    await page.goto('hkp/learn');
    await expect(page.locator('.hkp-flash--error')).toBeVisible();
  });

  test('selection remains pending until the second factor succeeds', async ({ page }) => {
    const fixture = JSON.parse(execFileSync(process.env.HKP_PHP || 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe', ['-r',
      "define('BASEPATH',getcwd());define('APPPATH',getcwd().'/../application/');require APPPATH.'libraries/Ha_crypto.php';require APPPATH.'libraries/Ha_totp.php';$c=new Ha_crypto();$t=new Ha_totp();echo json_encode(array('cipher'=>$c->encrypt('JBSWY3DPEHPK3PXP'),'code'=>$t->code('JBSWY3DPEHPK3PXP')));"
    ], {encoding:'utf8'}));
    sql(`INSERT INTO ha_user_2fa(user_id,secret_cipher,confirmed_at,created_at,updated_at) VALUES(${uid},'${fixture.cipher}',NOW(),NOW(),NOW())`);
    await selectCourse(page); await login(page);
    await expect(page).toHaveURL(/login\/two_factor$/);
    expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`)).toBe('0');
    await page.locator('#ha-2fa-code').fill(fixture.code);
    await page.locator('form[action*="login/two_factor"] button').click();
    await expect(page).toHaveURL(new RegExp(`/hkp/learn/module/${course}\\?lang=en$`));
    await expectMyLearning(page);
  });

  for (const state of ['deactivated', 'suspended']) {
    test(`${state} account stays signed out without a redirect loop or enrollment`, async ({ page }) => {
      if (state === 'deactivated') sql(`UPDATE users SET status=0 WHERE id=${uid}`);
      else sql(`UPDATE ha_profile SET status='suspended' WHERE user_id=${uid}`);
      await selectCourse(page); await login(page);
      await expect(page).toHaveURL(/\/login$/);
      await expect(page.locator('#login-form')).toBeVisible();
      expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`)).toBe('0');
      await page.goto('hkp/learn');
      await expect(page).toHaveURL(/\/login$/);
    });
  }

  test('a newly registered public learner can start and see the course without admin access', async ({ page }) => {
    const newUid = userId('e2e.newlearner@example.invalid');
    expect(newUid).toBeGreaterThan(0);
    sql(`DELETE FROM ha_enrollment WHERE user_id=${newUid} AND course_id=${course}`);
    await selectCourse(page);
    await page.locator('#login-form #email').fill('e2e.newlearner@example.invalid');
    await page.locator('#login-form #password').fill(USERS.student.password);
    await page.locator('#login-form button[type=submit]').click();
    await expect(page).toHaveURL(new RegExp(`/hkp/learn/module/${course}\\?lang=en$`));
    await page.goto('hkp/learn');
    await expect(page.locator(`a[href*="hkp/learn/module/${course}"]`)).toBeVisible();
    expect((await page.goto('hkp/admin')).status()).toBe(403);
    expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE user_id=${newUid} AND course_id=${course}`)).toBe('1');
  });
});
