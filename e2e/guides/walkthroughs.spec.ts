import { test, expect, say, point, type, stamp } from './guide';
import { sql, one } from '../support/db';
import { USERS } from '../support/users';

/**
 * The walkthrough videos. Each test records one guide into screen-recordings/.
 * They run in order because 03 → 06 build one demo module step by step.
 *
 * The demo module is created as a DRAFT named "Guide demo …" so it never shows
 * to learners; delete it from Modules & lessons whenever you like.
 */
const id = stamp();
const MODULE = `Guide demo: Guest check-in ${id}`;
let moduleUrl = '';
let quizUrl = '';

const COURSE = Number(one(`SELECT id FROM course WHERE meta_keywords='ha:dy-active-listening'`));
const STUDENT = Number(one(`SELECT id FROM users WHERE email='${USERS.student.email}'`));
const lessons = sql(`SELECT l.id FROM lesson l JOIN section s ON s.id = l.section_id WHERE l.course_id = ${COURSE} ORDER BY s.order, l.order`).map(([i]) => Number(i));
const lessonUrl = (i: number) => `home/lesson/active-listening/${COURSE}/${i}`;

test.describe.serial('Altus Gulf walkthroughs', () => {
  test('01 sign in and find Altus Knowledge and Performance', async ({ studio }) => {
    const p = await studio(null, '01-sign-in-and-workspace.webm');
    sql(`UPDATE users SET sessions='[]' WHERE email='${USERS.learner.email}'`);
    await p.goto('en');
    await say(p, '1 · The public website', 'The header carries the Altus Gulf menu. "Altus Knowledge and Performance" is the learning platform.', 2600);
    await point(p.locator('#ha-nav a', { hasText: 'Altus Knowledge and Performance' }));
    await point(p.locator('.ha-mast__signin'));
    await p.locator('.ha-mast__signin').click();
    await say(p, '2 · Sign in', 'Use your work e-mail and password. Every role signs in on the same page.');
    await type(p.locator('#login-form #email'), USERS.learner.email);
    await type(p.locator('#login-form #password'), USERS.learner.password);
    await point(p.locator('#login-form button[type=submit]'));
    await p.locator('#login-form button[type=submit]').click();
    await p.waitForURL(/\/hkp/);
    await say(p, '3 · You land in the workspace', 'After sign-in everyone goes straight to Altus Knowledge and Performance; each role sees its own home.', 3000);
    await point(p.locator('.hkp-nav'));
    await p.locator('[data-hkp-foot]').scrollIntoViewIfNeeded();
    await say(p, '4 · Footer', 'The footer links back to the corporate pages and the course catalogue.', 2600);
    await p.goto('home');
    await say(p, '5 · From the classic site too', 'Signed-in users also reach the workspace from the profile menu: "Altus Knowledge and Performance".', 2400);
  });

  test('02 tour of the Altus Gulf website in English and Arabic', async ({ studio }) => {
    const p = await studio(null, '02-website-tour-en-ar.webm');
    for (const [path, title, text] of [
      ['en/about-altus', 'About', 'Company overview, vision, mission, philosophy, values, partnerships and ESG, all from the 2026 Corporate Profile.'],
      ['en/services', 'Services', 'Hospitality Solutions and Business Growth Solutions, capabilities, sectors and the Digital & AI layer.'],
      ['en/ascent', 'Ascent', 'The six-stage Altus Ascent™ Framework, the Performance Matrix™ and the GOPPAR Value Stack™.'],
      ['en/market', 'Market', 'The Saudi growth runway with sources, and the four Vision 2030 pillars.'],
      ['en/case-studies', 'Case Studies', 'Four mandates, clearly labelled as illustrative.'],
      ['en/leadership', 'Leadership', 'The founders\' message and both co-founders\' track records.'],
    ]) {
      await p.goto(path);
      await say(p, title, text, 1600);
      await p.mouse.wheel(0, 900); await p.waitForTimeout(900);
      await p.mouse.wheel(0, 900); await p.waitForTimeout(900);
    }
    await p.goto('en/about-altus');
    await say(p, 'Arabic', 'Every page has a native Arabic version, right-to-left. Use the language menu at the top.', 1800);
    await p.locator('details[data-ha-langmenu] > summary').click();
    await point(p.locator('a[data-ha-lang-switch][hreflang=ar]'));
    await p.locator('a[data-ha-lang-switch][hreflang=ar]').click();
    await p.waitForURL(/\/ar\//);
    await say(p, 'العربية', 'Same page, Modern Standard Arabic, mirrored layout.', 2200);
    await p.mouse.wheel(0, 1200); await p.waitForTimeout(1200);
    await p.locator('footer.ha-foot').scrollIntoViewIfNeeded();
    await say(p, 'Footer', 'Corporate links, the full academy catalogue, and direct WhatsApp / call buttons for both founders.', 2600);
  });

  test('03 create a course (module) by hand', async ({ studio }) => {
    const p = await studio('admin', '03-create-course-manually.webm');
    await p.goto('hkp/cms/modules');
    await say(p, 'Modules & lessons', 'A course is called a module in the workspace. Admins and content editors manage them here.', 2400);
    await p.goto('hkp/cms/module');
    await say(p, 'New module', 'Fill in English and Arabic. Arabic is optional, but learners who use Arabic will see it.');
    await type(p.locator('#mt_en'), MODULE);
    await type(p.locator('#mt_ar'), `عرض توضيحي: تسجيل وصول النزيل ${id}`);
    await type(p.locator('#ms_en'), 'Greeting, verifying the booking and handing over the key.');
    await point(p.locator('#mdo'));
    await p.locator('#mdo').selectOption({ index: 1 });
    await say(p, 'Status', 'Keep it as Draft while you build. Learners only see Published modules.');
    await p.locator('#mst').selectOption('draft');
    await point(p.getByRole('button', { name: 'Save module' }));
    await p.getByRole('button', { name: 'Save module' }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();
    moduleUrl = p.url().replace(/^.*\/atlas\/atlas\//, '').replace(/[?#].*$/, '');
    await say(p, 'Saved', 'The module now has a Lessons list, Sections and a Quizzes panel.', 2000);
    const sec = p.locator('form[action*="cms/section_add"]');
    await say(p, 'Sections (chapters)', 'Group lessons into chapters. Add one per part of the course.');
    await type(sec.locator('#nsec'), 'Arrival');
    await type(sec.locator('input[name=title_ar]'), 'الوصول');
    await sec.getByRole('button', { name: 'Add section' }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();
    await say(p, 'Done', 'Next: add lessons to this module.', 1800);
  });

  test('04 add lessons: reading lesson and YouTube video', async ({ studio }) => {
    const p = await studio('admin', '04-add-lessons-text-and-video.webm');
    await p.goto(moduleUrl);
    await say(p, 'Add a lesson', 'Open the module and press "Add lesson".');
    await point(p.getByRole('link', { name: 'Add lesson' }));
    await p.getByRole('link', { name: 'Add lesson' }).click();
    await say(p, 'Reading lesson', 'Choose the type, the section, then write the title and body in English and Arabic.');
    await p.locator('#lt').selectOption('text');
    await type(p.locator('#lti_en'), 'Greeting the guest');
    await type(p.locator('#lti_ar'), 'الترحيب بالنزيل');
    await type(p.locator('#lb_en'), '<h2>Greet within ten seconds</h2><p>Stand, smile, make eye contact and welcome the guest by name when you know it.</p>');
    await p.locator('#lst').selectOption('published');
    await point(p.getByRole('button', { name: 'Save lesson' }));
    await p.getByRole('button', { name: 'Save lesson' }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();
    await say(p, 'Saved', 'Each edit keeps the previous version, so nothing is lost.', 1600);

    await p.goto(moduleUrl);
    await p.getByRole('link', { name: 'Add lesson' }).click();
    await say(p, 'Video lesson', 'Paste a YouTube or Vimeo link (https), or upload an MP4. PDFs and slides work the same way.');
    await p.locator('#lt').selectOption('video');
    await type(p.locator('#lti_en'), 'The secret ingredients of great hospitality (TED)');
    await type(p.locator('#lv'), 'https://www.youtube.com/watch?v=bwcyXcOpWVs');
    await p.locator('#lst').selectOption('published');
    await p.getByRole('button', { name: 'Save lesson' }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();
    await p.goto(moduleUrl);
    await say(p, 'Lesson list', 'Drag the handle (or Alt + arrow keys) to reorder lessons.', 2400);
  });

  test('05 create a quiz by hand and make it a checkpoint', async ({ studio }) => {
    const p = await studio('admin', '05-create-quiz-manually.webm');
    await p.goto(moduleUrl);
    const panel = p.locator('[data-module-quizzes]');
    await panel.scrollIntoViewIfNeeded();
    await say(p, 'Quizzes panel', 'Create a quiz on the module, add questions, then attach it to a lesson.', 2400);
    await type(panel.locator('#nqe'), 'Check-in knowledge check');
    await type(panel.locator('#nqa'), 'اختبار تسجيل الوصول');
    await say(p, 'Pass mark', 'Learners need this percentage to pass; 75% is the library standard.');
    await panel.locator('#nqp').fill('75');
    await panel.getByRole('button', { name: 'Create quiz' }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();
    quizUrl = p.url().replace(/^.*\/atlas\/atlas\//, '');
    await say(p, 'Add a question', 'Write the question in English and Arabic, fill the options and tick the correct one.');
    await type(p.locator('#qe'), 'How soon should a guest be greeted at the desk?');
    await type(p.locator('#qa'), 'متى يجب الترحيب بالنزيل عند المكتب؟');
    await type(p.locator('input[name="opt[0][en]"]'), 'Within 10 seconds');
    await type(p.locator('input[name="opt[1][en]"]'), 'When you finish your current task');
    await type(p.locator('input[name="opt[2][en]"]'), 'Only if the guest speaks first');
    await point(p.locator('input[name="correct[]"][value="0"]'));
    await p.locator('input[name="correct[]"][value="0"]').check();
    await type(p.locator('#xe'), 'The standard is to acknowledge every guest within ten seconds.');
    await p.getByRole('button', { name: 'Add question', exact: true }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();
    await p.locator('.hkp-q').first().scrollIntoViewIfNeeded();
    await say(p, 'Question added', 'The ✓ marks the correct answer. Settings on the right: attempts, time limit, shuffling.', 2600);

    await p.goto(moduleUrl);
    await p.locator('ol.hkp-sortable a', { hasText: 'Greeting the guest' }).click();
    await say(p, 'Make it a checkpoint', 'On the lesson, set Completion to "Pass a quiz" and choose the quiz.');
    await point(p.locator('#lcr'));
    await p.locator('#lcr').selectOption('quiz');
    await point(p.locator('#las'));
    await p.locator('#las').selectOption({ label: 'Check-in knowledge check' });
    await p.getByRole('button', { name: 'Save lesson' }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();
    await say(p, 'Locked until passed', 'Learners must now pass this quiz before the next lesson opens.', 2600);
  });

  test('06 write a lesson, translate it and generate quiz questions with AI', async ({ studio }) => {
    const p = await studio('admin', '06-ai-lessons-translation-and-quiz.webm');
    await p.goto(moduleUrl);
    await p.getByRole('link', { name: 'Add lesson' }).click();
    const ai = p.locator('[data-ai-panel]');
    await ai.scrollIntoViewIfNeeded();
    await say(p, 'AI writing help', 'Every editor screen has this panel. Pick a provider and model (set up in AI Studio → Providers).', 2600);
    await ai.locator('[data-ai-provider]').selectOption('e2e_mock');
    await say(p, 'About this recording', 'It uses a local test model so it runs without an API key; in production choose your real provider.', 2600);
    await type(ai.locator('[data-ai-prompt]'), 'Welcoming guests at the front desk');
    await ai.locator('[data-ai-task]').selectOption('lesson');
    await point(ai.getByRole('button', { name: 'Generate' }));
    await ai.getByRole('button', { name: 'Generate' }).click();
    await expect(ai.locator('[data-ai-output]')).not.toHaveValue('');
    await say(p, 'Review, then insert', 'Nothing is saved until you accept it: read the draft, then "Insert into the field".', 2200);
    await ai.getByRole('button', { name: 'Insert into the field' }).click();
    await expect(p.locator('#lb_en')).not.toHaveValue('');
    await type(p.locator('#lti_en'), 'Welcoming guests (AI draft)');
    await say(p, 'Translate to Arabic', 'Choose "Translate to Arabic", give it the text, generate and paste into the Arabic body.', 2200);
    await ai.locator('[data-ai-context]').fill('Welcome to the hotel');
    await ai.locator('[data-ai-task]').selectOption('translate_ar');
    await ai.getByRole('button', { name: 'Generate' }).click();
    await expect(ai.locator('[data-ai-output]')).toHaveValue(/\S/);
    await p.getByRole('button', { name: 'Save lesson' }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();

    await p.goto(quizUrl);
    const gen = p.locator('form[data-ai-quiz]');
    await gen.scrollIntoViewIfNeeded();
    await say(p, 'AI quiz questions', 'On a quiz, "Generate questions with AI" drafts multiple-choice questions straight into the quiz.', 2600);
    await gen.locator('#aqm').selectOption({ label: 'mock-fast' });
    await type(gen.locator('#aqp'), 'Greeting guests at check-in and confirming the booking');
    await gen.getByRole('button', { name: 'Generate and add questions' }).click();
    await expect(p.locator('.hkp-flash--ok')).toBeVisible();
    await p.locator('.hkp-q').last().scrollIntoViewIfNeeded();
    await say(p, 'Always review', 'AI questions are flagged "No Arabic": check each one, fix anything wrong and add the Arabic before learners take it.', 3200);
  });

  test('07 learner: read, take the quiz, unlock the next lesson', async ({ studio }) => {
    sql(`DELETE FROM quiz_results WHERE user_id=${STUDENT} AND quiz_id IN (${lessons.join(',')})`);
    sql(`DELETE FROM watch_histories WHERE student_id=${STUDENT} AND course_id=${COURSE}`);
    sql(`INSERT INTO enrol (user_id, course_id, date_added) SELECT ${STUDENT}, ${COURSE}, UNIX_TIMESTAMP() FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM enrol WHERE user_id=${STUDENT} AND course_id=${COURSE})`);
    const p = await studio('student', '07-learner-lesson-quiz-unlock.webm');
    await p.goto(lessonUrl(lessons[0]));
    await say(p, 'A library course', 'Built from the Dyafa training deck: a short reading lesson, a video, then a quiz.', 2400);
    await point(p.locator('.ap-side'));
    await say(p, 'Locked lessons', 'The padlocks in Course content open one by one, as each quiz is passed.', 2400);
    await p.mouse.wheel(0, 700); await p.waitForTimeout(1200);
    await p.mouse.wheel(0, 700); await p.waitForTimeout(1200);
    await point(p.locator('[data-ap-complete]'));
    await p.locator('[data-ap-complete]').click();
    await p.waitForURL(new RegExp(`/${lessons[1]}$`));
    await say(p, 'The quiz', 'Four scenario questions. Three correct answers unlock the next lesson; retakes are allowed.', 2600);
    const qs = sql(`SELECT correct_answers FROM question WHERE quiz_id=${lessons[1]} ORDER BY \`order\`, id`);
    for (const [i, [answers]] of qs.entries()) {
      const opt = p.locator(`#option_${i + 1}_${Number(JSON.parse(answers)[0])}`);
      await point(opt, 350);
      const saved = p.waitForResponse((r) => r.url().includes('/submit_quiz_answer/'));
      await opt.check();
      await saved;
    }
    await point(p.locator('#quizSubmissionBtn'));
    const done = p.waitForResponse((r) => r.url().includes('/finish_quize_submission/'));
    await p.locator('#quizSubmissionBtn').click();
    await done;
    await p.goto(lessonUrl(lessons[1]));
    await say(p, 'Passed', 'The result and the right answers are shown, and the next lesson is now unlocked.', 2600);
    await p.goto(lessonUrl(lessons[2]));
    await say(p, 'Next lesson open', 'Progress at the top updates as each lesson and quiz is completed.', 2600);
    sql(`DELETE FROM quiz_results WHERE user_id=${STUDENT} AND quiz_id IN (${lessons.join(',')})`);
    sql(`DELETE FROM watch_histories WHERE student_id=${STUDENT} AND course_id=${COURSE}`);
  });

  test('08 publish the module and check it as a learner', async ({ studio }) => {
    const p = await studio('admin', '08-publish-and-preview.webm');
    await p.goto(moduleUrl);
    await say(p, 'Publish', 'When the lessons and quizzes are ready, set Status to Published and save.', 2200);
    await point(p.locator('#mst'));
    await say(p, 'In this recording', 'The demo stays a Draft so learners never see it, so we only open the preview.', 2400);
    await point(p.getByRole('link', { name: 'Preview as learner' }));
    await p.getByRole('link', { name: 'Preview as learner' }).click();
    await say(p, 'Preview as learner', 'See exactly what a learner will see, including locks and the quiz checkpoint.', 2800);
  });
});
