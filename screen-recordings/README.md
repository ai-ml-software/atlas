# Altus Gulf: how to use the system

Each video in this folder is a real, captioned walkthrough of the system. The videos are `.webm` files and play in Chrome, Edge, Firefox or VLC. They are recorded automatically with Playwright, so they always match the current version of the site (see "Re-recording the videos" at the end).

| # | Video | What it shows |
|---|-------|---------------|
| 01 | `01-sign-in-and-workspace.webm` | Signing in, landing in **Altus Knowledge and Performance**, and the menus and footer |
| 02 | `02-website-tour-en-ar.webm` | The Altus Gulf website: About, Services, Ascent, Market, Case Studies, Leadership, in English and Arabic |
| 03 | `03-create-course-manually.webm` | Creating a course (a *module*) and its chapters (*sections*) by hand |
| 04 | `04-add-lessons-text-and-video.webm` | Adding a reading lesson and a YouTube video lesson |
| 05 | `05-create-quiz-manually.webm` | Creating a quiz, adding a question, and making the quiz a lesson checkpoint |
| 06 | `06-ai-lessons-translation-and-quiz.webm` | Using AI to write a lesson, translate it to Arabic, and draft quiz questions |
| 07 | `07-learner-lesson-quiz-unlock.webm` | The learner's view: read the lesson, pass the quiz, unlock the next lesson |
| 08 | `08-publish-and-preview.webm` | Publishing a module and previewing it as a learner |

---

## 1. Signing in

1. Open the website and click **Login**.
2. Enter your work e-mail and password.
3. Every role lands in **Altus Knowledge and Performance** (`/hkp`), and each role sees its own home:
   - learners see *My learning*;
   - supervisors and managers see the team view;
   - administrators see the admin view;
   - executives see the executive view.
4. If you are already signed in, opening the Login or Sign-up page takes you straight to your workspace.

You can always get back to the workspace from:
- the website header button **Altus Knowledge and Performance**;
- the classic site's profile menu;
- the **Altus Knowledge and Performance** button in the lesson player.

## 2. Creating a course by hand

In the workspace a course is called a **module**. You need an administrator or content-editor account.

1. Go to **Modules & lessons** (`/hkp/cms/modules`) and click **New module**.
2. Fill in the title, short description and description, in English and Arabic. Arabic is optional, but learners who use Arabic will see it.
3. Fill in the details: domain, category, level, duration, pass mark, owner and thumbnail.
4. Keep **Status: Draft** while you build. Learners only ever see **Published** modules.
5. Click **Save module**.
6. Under **Lessons**, add **sections**. A section is a chapter: give it an English and an Arabic title, then click **Add section**.

## 3. Adding lessons

1. On the module page, click **Add lesson**.
2. Choose the lesson **type**:

   | Type | What to provide |
   |------|-----------------|
   | Text | Write the body in the editor. Simple HTML works: `<h2>`, `<p>`, `<ul>`. |
   | Video | Paste an `https://` YouTube or Vimeo link, or upload an MP4. |
   | PDF / Presentation | Upload the file. Learners read it in the page and can download it. |
   | External | Paste an `https://` link. |

3. Choose the **section**, then write the title (and the Arabic title).
4. Optional settings:
   - an attachment learners can download (*Resources*);
   - *drip*, to release the lesson some days after enrolment or on a fixed date;
   - *preview*, so visitors can open the lesson before they enrol.
5. Set **Status: Published** and click **Save lesson**.
6. To reorder lessons, drag the ⠿ handle, or focus a lesson and press **Alt + ↑/↓**.

Every save keeps the previous version of the lesson, so an edit never destroys content.

## 4. Quizzes: by hand

1. On the module page, open the **Quizzes** panel.
2. Enter the quiz title (English and Arabic), the **pass mark** (75% is the library standard) and the **maximum attempts** (0 means unlimited).
3. Click **Create quiz**. The quiz page opens.
4. Under **Add a question**:
   - choose the type (multiple choice, true/false, matching, ordering, scenario, short answer or essay);
   - write the question in English and Arabic;
   - fill in the options and tick the correct one(s);
   - optionally add an explanation;
   - click **Add question**.
5. On the right, under **Settings**, set the time limit, shuffling, and whether to show the correct answers after submission.
6. Make the quiz a **checkpoint**:
   - open the lesson the quiz belongs to;
   - set **Completion** to **Pass a quiz**;
   - choose the quiz under **Checkpoint assessment**;
   - save.

   The next lesson now stays locked until the learner passes this quiz.

## 5. Using AI (lessons, translation, quiz questions)

Every editor screen has an **AI writing help** panel.

1. Choose a **provider** and **model**. An administrator enables these in **AI Studio → Providers**, with your own API key.
2. Use **Enhance prompt** to turn a rough request into a precise brief.
3. Choose a task:

   | Task | What it produces |
   |------|------------------|
   | Write a short applied lesson | A complete lesson body: objective, standard, mistakes, a scenario and a check |
   | Translate to Arabic / to English | A translation that keeps the HTML intact |
   | Improve the selected text | A clearer version of the same facts |
   | Suggest meta title and description | SEO text for pages |
   | Write FAQ questions and answers | Questions and answers for a page |

4. Click **Generate**, read the draft, then click **Insert into the field**. Nothing is saved until you insert it and click Save.
5. **AI quiz questions:** on any quiz page, use **Generate questions with AI**:
   - choose the model;
   - say what the questions should test;
   - optionally paste the lesson text;
   - click **Generate and add questions**.

   The questions are added to the quiz and flagged **No Arabic**. Review each one, correct anything that is wrong, and add the Arabic before learners take the quiz.

Every AI call is logged with the model, the prompt and the output (**AI Studio → Logs**).

> The recording uses a local test model so it runs without an API key. On the live site, choose your real provider.

## 6. The learner's experience

- Courses read like a short book:
  - a chapter heading;
  - a reading lesson with a scene, tips and a key takeaway;
  - a recommended video where one fits;
  - a four-question scenario quiz after every lesson.
- **The next lesson opens only after the quiz is passed** (3 correct answers out of 4). Retakes are allowed.
- **Course content** in the sidebar shows a padlock on every lesson that is still locked.
- Progress at the top of the player updates as each lesson and quiz is completed.
- Learners can switch between English and Arabic at any time. The layout mirrors to right-to-left for Arabic.

## 7. The Dyafa course library (bulk import)

The 40 service-standard courses were built from the Dyafa training decks in `pdf-20260925T132712Z-1-001/pdf`. Each course has one JSON file in `application/seeds/library/<slug>.json`, in English and Arabic.

To import new or edited files, run these on the server, from the project root:

```
php index.php ha_cli seed library      # create or update the courses (safe to re-run: progress is kept)
php index.php ha_bridge sync           # publish them to the lesson player, with quiz locks
```

## 8. Corporate website content

The About, Services, Ascent, Market, Case Studies and Leadership pages come from the 2026 Corporate Profile. To load or refresh that content, run:

```
php index.php ha_cli seed altus_profile
```

Content an administrator has already edited in the workspace is never overwritten. The founders' phone numbers and the footer wording are site settings, so they can be changed in the admin panel without a deploy.

---

## Re-recording the videos

The videos are recorded against the local Laragon site, never against production. From `e2e/`:

```
npm run guides                 # record all eight videos into this folder
npx playwright test -c playwright.guides.config.ts -g "05"   # record one video
```

The recordings create a draft module named "Guide demo …", which learners never see. You can delete it at any time from **Modules & lessons**.
