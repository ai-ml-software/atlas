import { test, expect, open, flashOk, stamp } from '../support/fixtures';
import { one } from '../support/db';

/** Quiz authoring: create a quiz on a module, add a question by hand and with AI, make it a lesson checkpoint. */
const id = stamp();
const MODULE = `E2E Quiz module ${id}`;
let moduleUrl = '';
let quizUrl = '';

test.describe.serial('Quiz authoring (admin)', () => {
  test('create a module to hold the quiz', async ({ as }) => {
    const p = await as('admin');
    await open(p, 'hkp/cms/module');
    await p.locator('#mt_en').fill(MODULE);
    await p.getByRole('button', { name: 'Save module' }).click();
    await flashOk(p);
    moduleUrl = p.url().replace(/^.*\/atlas\/atlas\//, '').replace(/[?#].*$/, '');
  });

  test('an empty quiz title is refused', async ({ as }) => {
    const p = await as('admin');
    await open(p, moduleUrl);
    const form = p.locator('[data-module-quizzes] form');
    await form.locator('#nqe').evaluate((el: HTMLInputElement) => { el.required = false; });
    await form.getByRole('button', { name: 'Create quiz' }).click();
    await expect(p.locator('.hkp-flash--error')).toContainText('English title');
  });

  test('create a quiz from the module page', async ({ as }) => {
    const p = await as('admin');
    await open(p, moduleUrl);
    const form = p.locator('[data-module-quizzes] form');
    await form.locator('#nqe').fill('Check-in knowledge check');
    await form.locator('#nqa').fill('اختبار تسجيل الوصول');
    await form.locator('#nqp').fill('80');
    await form.getByRole('button', { name: 'Create quiz' }).click();
    await flashOk(p);
    await expect(p).toHaveURL(/admin\/assessments\/view\/\d+/);
    quizUrl = p.url().replace(/^.*\/atlas\/atlas\//, '');
    await expect(p.locator('h1')).toHaveText('Check-in knowledge check');
    await expect(p.locator('#sp')).toHaveValue('80');
  });

  test('add a multiple-choice question by hand', async ({ as }) => {
    const p = await as('admin');
    await open(p, quizUrl);
    await p.locator('#qe').fill('How soon should a guest be greeted?');
    await p.locator('#qa').fill('متى يجب الترحيب بالنزيل؟');
    await p.locator('input[name="opt[0][en]"]').fill('Within 10 seconds');
    await p.locator('input[name="opt[1][en]"]').fill('After finishing your task');
    await p.locator('input[name="correct[]"][value="0"]').check();
    await p.getByRole('button', { name: 'Add question', exact: true }).click();
    await flashOk(p);
    await expect(p.locator('.hkp-q')).toContainText('✓ Within 10 seconds');
  });

  test('generate questions with AI and review them on the page', async ({ as }) => {
    const p = await as('admin');
    await open(p, quizUrl);
    const form = p.locator('form[data-ai-quiz]');
    await form.locator('#aqm').selectOption({ label: 'mock-fast' });
    await form.locator('#aqp').fill('Greeting guests at check-in');
    await form.getByRole('button', { name: 'Generate and add questions' }).click();
    await flashOk(p);
    await expect(p.locator('.hkp-q')).toHaveCount(2);
    await expect(p.locator('.hkp-q').nth(1)).toContainText('When do you greet a guest?');
    await expect(p.locator('.hkp-q').nth(1)).toContainText('✓ Within 10 seconds');     // correct: 1 → first option
    await expect(p.locator('.hkp-q').nth(1)).toContainText('No Arabic');                 // flagged for the reviewer
  });

  test('the quiz is listed on the module and can be a lesson checkpoint', async ({ as }) => {
    const p = await as('admin');
    await open(p, moduleUrl);
    await expect(p.locator('[data-module-quizzes]')).toContainText(/Check-in knowledge check.*2 questions/);
    await p.getByRole('link', { name: 'Add lesson' }).click();
    await p.locator('#lti_en').fill('Check-in standard');
    await p.locator('#lcr').selectOption('quiz');
    await p.locator('#las').selectOption({ label: 'Check-in knowledge check' });
    await p.locator('#lst').selectOption('published');
    await p.getByRole('button', { name: 'Save lesson' }).click();
    await flashOk(p);
    const lid = p.url().match(/cms\/lesson\/(\d+)/)![1];
    expect(one(`SELECT CONCAT(completion_rule,'|',assessment_id IS NOT NULL) FROM ha_lesson WHERE id=${lid}`)).toBe('quiz|1');
  });
});
