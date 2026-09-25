import { test, expect, open } from '../support/fixtures';
import { sql, one } from '../support/db';
import type { Page } from '@playwright/test';

/**
 * Library course (dy-active-listening) in the lesson player, as a learner:
 * read lesson 1 → its quiz → fail keeps lesson 2 locked → retake and pass → lesson 2 opens.
 */
const COURSE = Number(one(`SELECT id FROM course WHERE meta_keywords='ha:dy-active-listening'`));
const STUDENT = Number(one(`SELECT id FROM users WHERE email='omar.learner@dyafagroup.sa'`));
const lessons = sql(`SELECT l.id, l.lesson_type FROM lesson l JOIN section s ON s.id = l.section_id
                     WHERE l.course_id = ${COURSE} ORDER BY s.order, l.order`).map(([id, type]) => ({ id: Number(id), type }));
const [LESSON1, QUIZ1, LESSON2] = lessons;
const url = (id: number) => `home/lesson/active-listening/${COURSE}/${id}`;

function reset() {
  sql(`DELETE FROM quiz_results WHERE user_id=${STUDENT} AND quiz_id IN (${lessons.map((l) => l.id).join(',')})`);
  sql(`DELETE FROM watch_histories WHERE student_id=${STUDENT} AND course_id=${COURSE}`);
  sql(`INSERT INTO enrol (user_id, course_id, date_added) SELECT ${STUDENT}, ${COURSE}, UNIX_TIMESTAMP()
       FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM enrol WHERE user_id=${STUDENT} AND course_id=${COURSE})`);
}

/** Answers every question: correctly, or with the first wrong option. */
async function answerQuiz(p: Page, correctly: boolean) {
  const qs = sql(`SELECT correct_answers, number_of_options FROM question WHERE quiz_id=${QUIZ1.id} ORDER BY \`order\`, id`);
  await expect(p.locator('.ap-q').first()).toBeVisible();
  for (const [i, [answers, count]] of qs.entries()) {
    const right = Number(JSON.parse(answers)[0]);
    const pick = correctly ? right : (right === 1 ? 2 : 1);
    const saved = p.waitForResponse((r) => r.url().includes('/submit_quiz_answer/'));
    await p.locator(`#option_${i + 1}_${pick}`).check();
    await saved;
    expect(Number(count)).toBeGreaterThanOrEqual(pick);
  }
  const done = p.waitForResponse((r) => r.url().includes('/finish_quize_submission/'));
  await p.locator('#quizSubmissionBtn').click();
  await done;
}

test.describe.serial('Library course: each lesson unlocks only after its quiz is passed', () => {
  test.beforeAll(reset);
  test.afterAll(reset);

  test('the course is built as lesson → quiz → lesson with drip on', () => {
    expect(COURSE).toBeGreaterThan(0);
    expect(one(`SELECT enable_drip_content FROM course WHERE id=${COURSE}`)).toBe('1');
    expect([LESSON1.type, QUIZ1.type]).toEqual([expect.stringMatching(/video|text/), 'quiz']);
    expect(LESSON2.type).not.toBe('quiz');
  });

  test('lesson 2 is locked before anything is done', async ({ as }) => {
    const p = await as('student');
    await open(p, url(LESSON2.id));
    await expect(p.locator('.ap-notice--lock')).toBeVisible();
    await expect(p.locator('.ap-prose')).toHaveCount(0);
  });

  test('reading lesson 1 and completing it leads to its quiz; Next stays locked', async ({ as }) => {
    const p = await as('student');
    await open(p, url(LESSON1.id));
    await expect(p.locator('.ap-quiz-hint')).toBeVisible();              // "A short quiz follows this lesson"
    await p.locator('[data-ap-complete]').click();
    await expect(p).toHaveURL(new RegExp(`/${QUIZ1.id}$`));
    await expect(p.locator('[data-ap-next-locked]')).toBeVisible();
  });

  test('a failed quiz keeps lesson 2 locked', async ({ as }) => {
    const p = await as('student');
    await open(p, url(QUIZ1.id));
    await answerQuiz(p, false);
    await open(p, url(LESSON2.id));
    await expect(p.locator('.ap-notice--lock')).toBeVisible();
  });

  test('retaking and passing the quiz unlocks lesson 2', async ({ as }) => {
    const p = await as('student');
    await open(p, url(QUIZ1.id));
    await p.getByRole('link', { name: /take the quiz again/i }).click();
    await open(p, url(QUIZ1.id));
    await answerQuiz(p, true);
    await open(p, url(QUIZ1.id));
    await expect(p.locator('[data-ap-next-locked]')).toHaveCount(0);
    await open(p, url(LESSON2.id));
    await expect(p.locator('.ap-notice--lock')).toHaveCount(0);
    await expect(p.locator('.ap-prose')).toBeVisible();
  });
});
