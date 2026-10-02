import { test, expect, Page } from '@playwright/test';
import { sql, one, userId } from '../support/db';
import { USERS } from '../support/users';

test.describe('Learning continuation and progress diagnostics', () => {
  test.use({serviceWorkers: 'block'}); // Local media routing and browser clock need direct requests.
  test.skip(!process.env.HKP_TEST_DATABASE, 'Requires the isolated database');
  let course: number, uid: number, lesson: number, assignedCourse: number;
  let originalLesson: string[], originalSources: string[][];
  let assignedStatus: string;
  const quote = (s: string) => s === 'NULL' ? 'NULL' : `'${s.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;

  test.beforeAll(async ({request}) => {
    expect((await request.get('en/courses')).headers()['x-ha-test-database']).toBe('atlas_hospitality_test');
    uid = userId(USERS.student.email);
    course = Number(one("SELECT id FROM ha_course WHERE code='dy-active-listening'"));
    lesson = Number(one(`SELECT id FROM ha_lesson WHERE course_id=${course} AND status='published' ORDER BY sort_order LIMIT 1`));
    assignedCourse = Number(one(`SELECT e.course_id FROM ha_enrollment e WHERE e.user_id=${uid} AND e.source='assigned' AND e.course_id<>${course} ORDER BY e.id LIMIT 1`));
    expect(assignedCourse).toBeGreaterThan(0);
    assignedStatus = one(`SELECT status FROM ha_course WHERE id=${assignedCourse}`)!;
    originalLesson = sql(`SELECT lesson_type,video_url,available_from,drip_days FROM ha_lesson WHERE id=${lesson}`)[0];
    originalSources = sql(`SELECT id,status FROM ha_lesson_video_source WHERE lesson_id=${lesson}`);
  });

  test.beforeEach(({page}) => {
    page.on('dialog', d => d.accept());
    sql(`DELETE FROM ha_enrollment WHERE course_id=${course} AND user_id=${uid}`);
    sql(`DELETE FROM ha_assessment_attempt WHERE user_id=${uid} AND assessment_id IN (SELECT id FROM ha_assessment WHERE course_id=${course})`);
    sql(`UPDATE users SET sessions='[]' WHERE id=${uid}`);
    sql(`UPDATE ha_profile SET locale='en' WHERE user_id=${uid}`);
  });

  test.afterEach(() => {
    const keys = ['lesson_type','video_url','available_from','drip_days'];
    sql(`UPDATE ha_course SET status=${quote(assignedStatus)} WHERE id=${assignedCourse}`);
    sql(`UPDATE ha_lesson SET ${keys.map((k,i) => `${k}=${quote(originalLesson[i])}`).join(',')} WHERE id=${lesson}`);
    for (const [id,status] of originalSources) sql(`UPDATE ha_lesson_video_source SET status=${quote(status)} WHERE id=${Number(id)}`);
  });

  async function login(page: Page, user: {email: string; password: string} = USERS.student) {
    await page.goto('login');
    await page.locator('#login-form #email').fill(user.email);
    await page.locator('#login-form #password').fill(user.password);
    await page.locator('#login-form button[type=submit]').click();
    await page.waitForLoadState('domcontentloaded');
  }

  async function start(page: Page) {
    await login(page);
    await page.goto('en/courses/active-listening');
    await page.locator('[data-course-start]').click();
    await expect(page).toHaveURL(new RegExp(`/hkp/learn/module/${course}`));
  }

  test('dashboard and My Learning continue the selected available course and explain draft assignments', async ({page}) => {
    await start(page);
    sql(`UPDATE ha_course SET status='draft' WHERE id=${assignedCourse}`);
    const before = sql(`SELECT id,source,status,progress_percentage,time_spent_seconds,updated_at FROM ha_enrollment WHERE user_id=${uid}`);
    for (const path of ['hkp', 'hkp/learn']) {
      await page.goto(path + '?lang=en');
      const card = page.locator('[data-learning-continue]');
      await expect(card).toContainText('Self selected');
      await expect(card.locator('a')).toHaveAttribute('href', new RegExp(`/learn/lesson/${lesson}$`));
      await expect(page.locator(`a[href*="/learn/module/${assignedCourse}"]`)).toHaveCount(0);
      await expect(page.locator('main')).toContainText('This course is currently unavailable. Your saved progress is retained.');
    }
    expect(sql(`SELECT id,source,status,progress_percentage,time_spent_seconds,updated_at FROM ha_enrollment WHERE user_id=${uid}`)).toEqual(before);
  });

  test('saved position survives reading a guide when video is unavailable', async ({page}) => {
    await start(page);
    sql(`UPDATE ha_lesson_video_source SET status='unavailable' WHERE lesson_id=${lesson}`);
    sql(`UPDATE ha_lesson SET video_url=NULL WHERE id=${lesson}`);
    await page.goto(`hkp/learn/lesson/${lesson}`);
    sql(`UPDATE ha_lesson_progress SET last_position_seconds=83 WHERE user_id=${uid} AND lesson_id=${lesson}`);
    await page.clock.install();
    await page.reload();
    await expect(page.locator('[data-lesson-track]')).toHaveAttribute('data-resume','83');
    await expect(page.locator('article video')).toHaveCount(0);
    const tracked = page.waitForResponse(r => r.url().includes(`/track_time/${lesson}`) && r.request().method() === 'POST');
    await page.clock.fastForward(31_000);
    expect((await tracked).status()).toBe(200);
    expect(one(`SELECT last_position_seconds FROM ha_lesson_progress WHERE user_id=${uid} AND lesson_id=${lesson}`)).toBe('83');
    await page.goto('hkp/learn');
    await expect(page.locator('[data-learning-continue]')).toContainText('Saved video position: 1:23');
  });

  for (const savedPosition of [2,83]) {
    test(`native media restores position ${savedPosition}, bounds it to duration and persists a new position`, async ({page}) => {
      await start(page);
      sql(`UPDATE ha_lesson_video_source SET status='unavailable' WHERE lesson_id=${lesson}`);
      sql(`UPDATE ha_lesson SET lesson_type='video',video_url='http://127.0.0.1:8099/e2e-resume.wav' WHERE id=${lesson}`);
      // Small owned PCM fixture: browser decodes duration and seeks through actual media.
      const samples = 8000 * 5, wav = Buffer.alloc(44 + samples * 2);
      wav.write('RIFF',0); wav.writeUInt32LE(wav.length - 8,4); wav.write('WAVEfmt ',8);
      wav.writeUInt32LE(16,16); wav.writeUInt16LE(1,20); wav.writeUInt16LE(1,22);
      wav.writeUInt32LE(8000,24); wav.writeUInt32LE(16000,28); wav.writeUInt16LE(2,32); wav.writeUInt16LE(16,34);
      wav.write('data',36); wav.writeUInt32LE(samples * 2,40);
      await page.route('**/e2e-resume.wav', r => {
        const range = /^bytes=(\d+)-(\d*)$/.exec(r.request().headers()['range'] || '');
        const begin = range ? Number(range[1]) : 0;
        const end = range && range[2] ? Math.min(Number(range[2]), wav.length - 1) : wav.length - 1;
        return r.fulfill({status: range ? 206 : 200, contentType:'audio/wav', body:wav.subarray(begin,end+1),
          headers:{'Accept-Ranges':'bytes', ...(range ? {'Content-Range':`bytes ${begin}-${end}/${wav.length}`} : {})}});
      });
      await page.goto(`hkp/learn/lesson/${lesson}`);
      sql(`UPDATE ha_lesson_progress SET last_position_seconds=${savedPosition} WHERE user_id=${uid} AND lesson_id=${lesson}`);
      await page.clock.install();
      await page.reload();
      const video = page.locator('article video');
      await expect(page.locator('[data-lesson-track]')).toHaveAttribute('data-resume',String(savedPosition));
      await expect.poll(() => video.evaluate((v: HTMLVideoElement) => ({duration:v.duration,state:v.readyState,error:v.error?.code}))).toMatchObject({duration:5});
      await expect.poll(() => video.evaluate((v: HTMLVideoElement) => v.currentTime)).toBe(Math.min(savedPosition,4));
      await video.evaluate((v: HTMLVideoElement) => {v.currentTime = 3;});
      const tracked = page.waitForResponse(r => r.url().includes(`/track_time/${lesson}`) && r.request().method() === 'POST');
      await page.clock.fastForward(31_000);
      expect((await tracked).status()).toBe(200);
      expect(one(`SELECT last_position_seconds FROM ha_lesson_progress WHERE user_id=${uid} AND lesson_id=${lesson}`)).toBe('3');
    });
  }

  test('future lesson release suppresses a broken resume link and explains the date', async ({page}) => {
    await start(page);
    sql(`UPDATE ha_lesson SET available_from='2099-01-01 00:00:00' WHERE id=${lesson}`);
    await page.goto('hkp/learn');
    await expect(page.locator(`[data-learning-continue] a[href*="/learn/lesson/${lesson}"]`)).toHaveCount(0);
    await expect(page.locator(`a[href*="/learn/lesson/${lesson}"]`)).toHaveCount(0);
    await expect(page.locator('main')).toContainText('This lesson opens on');
  });

  for (const width of [1440, 390]) {
    test(`Arabic continuation and assignment labels fit ${width}px`, async ({page}) => {
      await page.setViewportSize({width,height:844});
      await start(page);
      for (const path of ['hkp','hkp/learn']) {
        await page.goto(path + '?lang=ar');
        await expect(page.locator('html')).toHaveAttribute('dir','rtl');
        await expect(page.locator('[data-learning-continue]')).toContainText('متابعة التعلم');
        await expect(page.locator('[data-learning-continue]')).toContainText('اخترتها بنفسك');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
      }
    });
  }

  test('admin diagnostics identify a stale enrollment without repairing it', async ({page}) => {
    await start(page);
    sql(`UPDATE ha_enrollment SET lessons_completed=1,progress_percentage=25 WHERE user_id=${uid} AND course_id=${course}`);
    const eid = one(`SELECT id FROM ha_enrollment WHERE user_id=${uid} AND course_id=${course}`);
    const before = sql(`SELECT * FROM ha_enrollment WHERE id=${eid}`);
    await page.goto('login/logout');
    await login(page, USERS.admin);
    await page.goto('hkp/admin/system');
    const health = page.locator('[data-learning-health]');
    await expect(health).toBeVisible();
    await expect(health).toContainText('Attention');
    await health.locator('summary').filter({hasText:'enrollment_totals'}).click();
    await expect(health).toContainText(`"enrollment_id": "${eid}"`);
    expect(sql(`SELECT * FROM ha_enrollment WHERE id=${eid}`)).toEqual(before);
  });

  test('learner cannot read system diagnostics or invoke CLI audit over HTTP', async ({page}) => {
    await start(page);
    expect((await page.goto('hkp/admin/system'))!.status()).toBe(403);
    await expect(page.locator('[data-learning-health]')).toHaveCount(0);
    expect((await page.goto('hkp_cli/learning_health'))!.status()).toBe(404);
  });
});
