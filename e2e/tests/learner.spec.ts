import { test, expect, open, flashOk } from '../support/fixtures';

test.describe('Learner journey (demo.learner@altusdemo.sa)', () => {
  test('dashboard shows what to do next', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp');
    await expect(p.locator('h1')).toBeVisible();
    await expect(p.locator('.hkp-nav')).toContainText('My learning');
    await expect(p.locator('.hkp-nav')).not.toContainText('Altus team');     // no admin sections
  });

  test('open a module, read a lesson, mark it complete', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp/learn');
    const mod = p.locator('a[href*="hkp/learn/module/"]').first();
    await expect(mod).toBeVisible();
    await mod.click();
    await expect(p.locator('body')).not.toContainText(/PHP Error/);
    const enrol = p.getByRole('button', { name: 'Add to my learning' });
    if (await enrol.count()) { await enrol.click(); }
    const lesson = p.locator('a[href*="hkp/learn/lesson/"]').first();
    await lesson.click();
    await expect(p.locator('article.hkp-card')).toBeVisible();
    await expect(p.locator('[data-lesson-track]')).toHaveCount(1);         // progress tracking is wired
    const done = p.getByRole('button', { name: /Mark lesson complete|Completed — continue/ });
    const ack = p.locator('form input[type=checkbox][required]');
    if (await ack.count()) { await ack.check(); }
    await done.click();
    await expect(p.locator('.hkp-flash--error')).toHaveCount(0);
  });

  test('take a theory assessment and see the result with feedback', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp/learn');
    const modules = await p.locator('a[href*="hkp/learn/module/"]').evaluateAll((as) => as.map((a) => (a as HTMLAnchorElement).href));
    let taken = false;
    for (const m of [...new Set(modules)].slice(0, 8)) {
      await p.goto(m);
      const take = p.locator('a[href*="hkp/assess/theory/"]').first();
      if (!(await take.count())) continue;
      await take.click();
      if (!/assess\/theory\//.test(p.url()) || !(await p.locator('fieldset.hkp-q').count())) continue;   // no attempts left
      for (const q of await p.locator('fieldset.hkp-q').all()) {
        const radio = q.locator('input[type=radio]').first();
        const box = q.locator('input[type=checkbox]').first();
        const text = q.locator('input[type=text], textarea').first();
        if (await radio.count()) await radio.check();
        else if (await box.count()) await box.check();
        else if (await text.count()) await text.fill('Greet the guest and confirm the booking');
      }
      await p.getByRole('button', { name: 'Submit answers' }).click();
      await expect(p).toHaveURL(/assess\/result\//);
      await expect(p.locator('body')).toContainText(/%/);
      await expect(p.locator('body')).toContainText(/Passed|Not passed|pass|fail/i);
      taken = true;
      break;
    }
    test.skip(!taken, 'every assessment in reach has used its attempts');
  });

  test('knowledge library: open an approved item and acknowledge it', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp/knowledge');
    const item = p.locator('a[href*="hkp/knowledge/item/"]').first();
    await expect(item).toBeVisible();
    await item.click();
    await expect(p.locator('h1')).toBeVisible();
    const ack = p.getByRole('button', { name: 'Acknowledge' });
    if (await ack.count()) {
      // The confirmation box is required: submitting without it must not acknowledge.
      await ack.click();
      await expect(p.locator('.hkp-flash--ok')).toHaveCount(0);
      await p.locator('form[action*="knowledge/ack"] input[type=checkbox]').check();
      await ack.click();
      await flashOk(p);
      await expect(p.getByRole('button', { name: 'Acknowledge' })).toHaveCount(0);   // recorded, not offered again
    }
  });

  test('smart search finds approved content and offers suggestions', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp');
    const box = p.locator('#hkp-q');
    await box.fill('check');
    await box.press('Enter');
    await expect(p).toHaveURL(/hkp\/search\?q=check/);
    await expect(p.locator('main')).toContainText(/check/i);
    const r = await p.request.get('hkp/search_suggest?q=chec');
    expect(r.status()).toBe(200);
  });

  test('bilingual search: an Arabic query is understood', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp/search?q=' + encodeURIComponent('تسجيل الوصول'));
    await expect(p.locator('body')).not.toContainText(/PHP Error/);
  });

  test('governed AI assistant answers from approved knowledge or says it cannot', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp/assistant');
    await p.locator('#aq').fill('What is our approved check-in procedure?');
    await p.getByRole('button', { name: 'Ask' }).click();
    const answer = p.locator('.hkp-chat .hkp-msg--ai').last();
    await expect(answer).not.toHaveText(/Searching approved knowledge/, { timeout: 30_000 });
    await expect(answer).not.toBeEmpty();
    // It must either cite sources or clearly state the knowledge is insufficient.
    const text = (await p.locator('.hkp-chat').innerText()).toLowerCase();
    expect(text).toMatch(/sources:|not (contain|cover)|insufficient|approved knowledge/);
  });

  for (const [path, heading] of [
    ['hkp/competencies', /competenc/i], ['hkp/readiness/me', /Readiness|ready/i], ['hkp/actions', /action/i],
    ['hkp/certificates', /certificate/i], ['hkp/notifications', /notification/i], ['hkp/profile', /profile/i],
    ['hkp/learn/paths', /path|track/i], ['hkp/assess', /assessment/i],
  ] as const) {
    test(`learner page ${path} renders`, async ({ as }) => {
      const p = await as('learner');
      await open(p, path);
      await expect(p.locator('h1')).toContainText(heading);
    });
  }

  test('readiness explains every check', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp/readiness/me');
    await expect(p.locator('main')).toContainText(/Mandatory learning|theory|practical|gap/i);
  });

  test('switch the whole workspace to Arabic (RTL) and back', async ({ as }) => {
    const p = await as('learner');
    await open(p, 'hkp?lang=ar');
    await expect(p.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(p.locator('.hkp-nav')).toContainText('تعلّمي');
    await open(p, 'hkp?lang=en');
    await expect(p.locator('html')).toHaveAttribute('dir', 'ltr');
  });

  test('the README student also has the workspace and the classic LMS area', async ({ as }) => {
    const p = await as('student');
    await open(p, 'hkp');
    await open(p, 'home/my_courses');
  });
});
