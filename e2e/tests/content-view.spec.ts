import { test, expect, open, flashOk, stamp } from '../support/fixtures';
import { sql, one, localDatabase } from '../support/db';

test.beforeAll(() => {
  expect(localDatabase()).toBe('atlas_hospitality_test');
});

test('content lists offer bilingual views and protect private previews', async ({ as, page }) => {
  const p = await as('admin');
  for (const route of ['hkp/cms', 'hkp/cms/modules', ...['courses', 'programs', 'paths', 'articles', 'topics'].map(t => 'hkp/cms/catalogue/' + t)]) {
    await open(p, route);
    const row = p.locator('tbody tr').filter({ has: p.locator('[data-content-view]') }).first();
    await expect(row.locator('[data-content-view]')).toHaveCount(2);
    const url = await row.locator('[data-content-view]').first().getAttribute('href');
    expect(url).toMatch(/hkp\/cms\/view\/.+\/\d+\?locale=en/);
    await open(p, url!);
    await expect(p.locator('h1').first()).toBeVisible();
  }
  const id = one("SELECT id FROM ha_page WHERE code='about'")!;
  await p.setViewportSize({ width: 390, height: 844 });
  await open(p, `hkp/cms/page/${id}`);
  await expect(p.locator('[data-content-view]')).toHaveCount(4);
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await open(p, `hkp/cms/view/pages/${id}?draft=1`);
  expect(p.url()).toContain('studio_sig=');
  await expect(p.locator('h1')).toBeVisible();
  const unauthenticated = await page.goto(`hkp/cms/view/pages/${id}?draft=1`);
  expect(unauthenticated?.status()).toBe(200);
  await expect(page).toHaveURL(/\/login(?:\?|$)/);
  const student = await as('student');
  expect((await student.goto(`hkp/cms/view/pages/${id}?draft=1`))?.status()).toBe(403);
});

test('create, update, view and delete lessons without changing learner progress', async ({ as }) => {
  const p = await as('admin'), name = 'View module ' + stamp();
  let courseId = 0;
  try {
    await open(p, 'hkp/cms/module');
    await p.locator('[name=title_en]').fill(name);
    await p.locator('[name=title_ar]').fill(name + ' AR');
    await p.locator('[name=description_en]').fill('<p>Initial module content</p>');
    await p.getByRole('button', { name: 'Save and view', exact: true }).click();
    await expect(p).toHaveURL(/cms\/view\/modules\/\d+/);
    courseId = Number(/modules\/(\d+)/.exec(p.url())![1]);
    await expect(p.locator('h1')).toHaveText(name);
    await flashOk(p);
    await p.getByRole('link', { name: 'Back to editor', exact: true }).click();
    await p.locator('#mt_en').fill(name + ' updated');
    await p.getByRole('button', { name: 'Save and view', exact: true }).click();
    await expect(p.locator('h1')).toHaveText(name + ' updated');
    await open(p, `hkp/cms/lesson?module=${courseId}`);
    await p.locator('[name=title_en]').fill('Preview lesson');
    await p.locator('[name=body_en]').fill('<p>Saved lesson body</p>');
    await p.locator('[name=lesson_type]').selectOption('video');
    await p.locator('[name=video_url]').fill('http://127.0.0.1:8099/docs/guides/ALTUS-Mobile-Guide.mp4');
    await p.getByRole('button', { name: 'Save and view', exact: true }).click();
    await expect(p).toHaveURL(/cms\/view\/lessons\/\d+/);
    const lessonId = Number(/lessons\/(\d+)/.exec(p.url())![1]);
    await expect(p.locator('.hkp-lesson')).toContainText('Saved lesson body');
    await expect(p.locator('video')).toHaveAttribute('controlslist', 'nodownload');
    await expect(p.locator('video')).toHaveAttribute('src', /ALTUS-Mobile-Guide\.mp4/);
    await expect(p.locator('[data-lesson-track]')).toHaveCount(0);
    await expect(p.locator('form[action*="learn/complete"]')).toHaveCount(0);
    expect(one(`SELECT COUNT(*) FROM ha_lesson_progress WHERE lesson_id=${lessonId}`)).toBe('0');
    expect(one(`SELECT COUNT(*) FROM ha_enrollment WHERE course_id=${courseId}`)).toBe('0');
    await p.getByRole('link', { name: 'Back to editor', exact: true }).click();
    await p.getByRole('button', { name: 'Delete lesson', exact: true }).click();
    await expect(p).toHaveURL(new RegExp(`cms/module/${courseId}$`));
    await flashOk(p);
    await expect(p.locator('[data-content-view]')).toHaveCount(2);
    expect(one(`SELECT COUNT(*) FROM ha_lesson WHERE id=${lessonId}`)).toBe('0');
  } finally {
    if (courseId) sql(`DELETE FROM ha_course WHERE id=${courseId}`);
  }
});

test('page saves and section deletion retain working previews', async ({ as }) => {
  const p = await as('admin'), name = 'View page ' + stamp();
  let id = 0;
  try {
    await open(p, 'hkp/cms');
    await p.locator('#new-page [name=title_en]').fill(name);
    await p.locator('#new-page [name=title_ar]').fill(name + ' AR');
    await p.getByRole('button', { name: 'Create page', exact: true }).click();
    await expect(p).toHaveURL(/cms\/page\/\d+/);
    id = Number(/page\/(\d+)/.exec(p.url())![1]);
    await expect(p.locator('[data-content-view]')).toHaveCount(4);
    await p.locator('[name="en[title]"]').fill(name + ' updated');
    await p.locator('#bden').fill('<p>Page content after save</p>');
    await p.getByRole('button', { name: 'Save and view', exact: true }).click();
    await expect(p).toHaveURL(/cms\/view\/pages\/\d+/);
    await expect(p.locator('h1')).toHaveText(name + ' updated');
    await expect(p.locator('body')).toContainText('Page content after save');
    await p.getByRole('link', { name: 'Back to editor', exact: true }).click();
    const section = p.locator('form').filter({ has: p.locator('[name=section_type][value=rich_text]') });
    await section.locator('..').locator('summary').first().click();
    await section.locator('[name="en[heading]"]').fill('Section to delete');
    await section.locator('[name="en[body]"]').fill('<p>Temporary section</p>');
    await section.getByRole('button', { name: 'Add this section', exact: true }).click();
    const sectionId = one(`SELECT id FROM ha_page_section WHERE page_id=${id}`)!;
    await p.locator(`#s${sectionId}`).getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(p).toHaveURL(new RegExp(`cms/page/${id}$`));
    await expect(p.locator('.hkp-flash--ok')).toHaveText('Section deleted.');
    await open(p, `hkp/cms/view/pages/${id}`);
    await expect(p.locator('body')).not.toContainText('Temporary section');
    await expect(p.locator('h1')).toHaveText(name + ' updated');
  } finally {
    if (id) sql(`DELETE FROM ha_page WHERE id=${id}`);
  }
});

test('catalogue save and view shows a saved private draft and follows the current published address', async ({ as }) => {
  const p = await as('admin'), slug = 'view-article-' + stamp();
  let id = 0;
  try {
    await open(p, 'hkp/cms/catalogue/articles/new');
    for (const loc of ['en', 'ar']) {
      await p.locator(`[name=title_${loc}]`).fill('View article ' + loc);
      await p.locator(`[name=slug_${loc}]`).fill(slug + '-' + loc);
      await p.locator(`[name=body_${loc}]`).fill('<p>Article body ' + loc + '</p>');
    }
    await p.getByRole('button', { name: 'Save and view', exact: true }).click();
    await expect(p).toHaveURL(/cms\/view\/articles\/\d+/);
    id = Number(/articles\/(\d+)/.exec(p.url())![1]);
    await expect(p.locator('h1')).toHaveText('View article en');
    await p.getByRole('link', { name: 'Back to editor', exact: true }).click();
    await p.locator('[name=title_en]').fill('Saved private article draft');
    await p.locator('[name=slug_en]').fill(slug + '-updated');
    await p.getByRole('button', { name: 'Save and view', exact: true }).click();
    await expect(p).toHaveURL(/draft=1/);
    await expect(p.locator('h1')).toHaveText('Saved private article draft');
    expect(one(`SELECT title FROM ha_article_translation WHERE article_id=${id} AND locale='en'`)).toBe('View article en');
    await p.getByRole('link', { name: 'Back to editor', exact: true }).click();
    await p.getByRole('button', { name: 'Publish saved draft', exact: true }).click();
    await flashOk(p);
    await open(p, `hkp/cms/view/articles/${id}?locale=en`);
    expect(p.url()).toContain('/en/articles/' + slug + '-updated');
    await expect(p.locator('h1')).toHaveText('Saved private article draft');
  } finally {
    if (id) {
      sql(`DELETE FROM ha_studio_draft WHERE object_type='articles' AND object_id=${id}`);
      sql(`DELETE FROM ha_article WHERE id=${id}`);
    }
  }
});
