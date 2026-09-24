# Hospitality Academy

Hotel training, standard operating procedures and workforce certification, in
Arabic and English, built inside the Academy LMS CodeIgniter application.

**Live locally:** <http://localhost/atlas-lms/Academy-LMS/>

The root URL detects the visitor's language and lands on `/en` or `/ar`. Every
original Academy LMS route (`/login`, `/admin`, the user area) is untouched and
continues to work.

---

## What this is

**One catalogue, two frontends.** The academy tables (`ha_*`) are the single
source of truth. A bridge command publishes that catalogue into the original
Academy LMS tables, so the shipped theme, the student area, enrolment, the
instructor panel and the admin course manager all work against the same
content. Both frontends use the same violet palette.

| Frontend | URL | What it does |
|---|---|---|
| Hospitality Academy | `/en`, `/ar` | Bilingual marketing and catalogue site, SEO, certificate verification |
| Academy LMS | `/`, `/courses`, `/home/my_courses` | The shipped LMS: enrolment, course player, student area, instructor and admin panels |

A public academy website plus the data model behind it:

| Area | What exists |
|---|---|
| Public site | 18 routes, every one in English and Arabic, 206 detail pages |
| Curriculum | 74 courses, 666 lessons, 6 programmes, 3 career paths, 20 skills, all bilingual |
| SOP hub | 14 versioned procedures with checklists, acknowledgement model, public resources page |
| Content | 8 pages, 14 topics (6 subject pillars, 8 Saudi city pages), 6 articles, 12 FAQs |
| SEO | Per-page metadata in both languages, hreflang, sitemap, robots, JSON-LD, redirect manager |
| Photography | 36 photographs from Wikimedia Commons, licensed, credited, served as WebP |
| Authorization | 8 roles, 180 permissions, tenant scoping, audit log |
| Verification | 83 automated tests, a rendered-page audit, a public flow audit |

---

## Deploying

Putting this on a server, or debugging an HTTP 500 after uploading, is covered
in [DEPLOYMENT.md](DEPLOYMENT.md). Upload `deploy-check.php`, open it with your
key, and it reports what the server can actually do rather than leaving you to
guess.

---

## Requirements

- PHP 8.3 with `gd` (WebP), `curl`, `mysqli`
- MySQL 8
- Apache with `mod_rewrite` (Laragon provides all of this)

---

## Setup

```bash
cd C:/laragon/www/atlas-lms/Academy-LMS

# 1. Database. Credentials live in application/config/database.php
mysql -uroot -proot -e "CREATE DATABASE atlas_hospitality CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -uroot -proot atlas_hospitality < uploads/install.sql

# 2. Schema and content
php index.php ha_cli migrate
php index.php ha_cli seed

# 3. Photography (downloads from Wikimedia Commons, needs network)
php index.php ha_images fetch
php index.php ha_images fetch_variants
php index.php ha_images assign

# 4. Publish the catalogue into the Academy LMS tables
php index.php ha_bridge sync
php index.php ha_bridge enrol     # demo enrolments for the seeded learners
```

Then open <http://localhost/atlas-lms/Academy-LMS/>.

---

## Command reference

### Schema and data

```bash
php index.php ha_cli status     # which migrations have run
php index.php ha_cli migrate    # apply pending migrations
php index.php ha_cli rollback   # roll back to a version (default 0)
php index.php ha_cli seed       # seed content, idempotent
php index.php ha_cli fresh      # rollback, migrate, seed
```

Seeders are idempotent: running `seed` twice updates rows in place rather than
duplicating them, so it is safe against a populated database.

CodeIgniter 3.1.9's own Migration library calls `is_callable([$class, 'up'])`,
which PHP 8 evaluates as `false` for a non-static method, so it cannot run on
this PHP build. `Ha_cli` is a replacement that keeps the same file format and
records state in `ha_migration`. The framework in `system/` is not patched.

### Photography

```bash
php index.php ha_images fetch            # download the subject library
php index.php ha_images fetch_variants   # extra photos per course category
php index.php ha_images assign           # attach photos to content
php index.php ha_images report           # licence list and coverage
php index.php ha_images purge <subject>  # drop one so it can be refetched
php index.php ha_images build            # fetch then assign
```

### Publishing to the Academy LMS

```bash
php index.php ha_bridge sync     # mirror ha_* into course/lesson/section/category
php index.php ha_bridge enrol    # give the seeded learners enrolments
php index.php ha_bridge status   # what is mirrored, and whether it has drifted
php index.php ha_bridge clear    # remove the mirrored rows
```

`sync` writes one way and is idempotent. A mirrored course is matched on the
academy code held in `course.meta_keywords`, so running it twice updates rather
than duplicates. It also writes the JPEG files the legacy theme resolves by
filename convention, and mirrors each academy category as a category plus a
sub-category, because a legacy course attaches to the sub-category.

Re-run `sync` after any change to the academy catalogue.

### Lesson video

```bash
php index.php ha_video discover   # read the source playlists, verify every video
php index.php ha_video assign     # place verified videos on the curated courses
php index.php ha_video recheck    # re-verify assigned videos, exit 1 if any died
php index.php ha_video report     # what is placed, and which channels are credited
php index.php ha_video clear      # remove every assignment
```

Course video is embedded from third-party YouTube channels. The academy does
not own it, so the pipeline is built around that fact rather than around it:

- **Nothing ships unverified.** `discover` fetches every candidate through
  YouTube's oEmbed endpoint (no API key, no quota) and keeps only videos that
  actually resolve. A video ID looks valid whether or not it points at
  anything, and a catalogue of plausible dead embeds fails invisibly until a
  learner hits one.
- **Placement is curated, not scored.** A title-matching scorer was tried
  first and is why the curated map in `Ha_video` exists: it put a
  guest-complaint video on a HACCP course and a bellboy video on a
  property-management course, both in the right department and about the
  wrong thing. Courses absent from the map keep their written lesson.
- **Every video is credited.** The channel name and a link to the original
  travel with the lesson, and the credit line says plainly that the video is
  not the academy's own production.
- **`recheck` is meant to run on a schedule.** Third-party videos get deleted,
  set private and age-gated without notice. It exits non-zero when one dies so
  a cron job can raise it.
- **A video lesson with no verified source publishes as text**, not as an
  empty player.

`discover` reads playlist RSS rather than the Data API, which needs no key but
returns only the most recent 15 entries per playlist. That is the current cap
on how many videos exist to place.

### Interface language

```bash
php index.php ha_lang translate   # write Arabic for every phrase
php index.php ha_lang export      # write application/language/arabic.json
php index.php ha_lang status      # coverage
php index.php ha_lang missing     # what is still English
```

The application has two translation systems and they are unrelated. The public
academy site keeps its Arabic in the `ha_*_translation` tables. The admin panel
and the Academy LMS front end read theirs through `get_phrase()` and
`site_phrase()` from the `language` table, which shipped with an `english`
column and nothing else, so neither could be translated at all.

`ha_lang translate` fills the `arabic` column from
`application/libraries/Ha_phrasebook.php`, which holds the phrase map as
reviewable source rather than as rows someone edited once in a database.
`export` then writes `application/language/arabic.json`: the application
discovers which languages to offer by scanning that directory for `.json`
files, so without the file Arabic is fully translated and still missing from
the language menu.

Not translated, and deliberately: catalogue text in the Academy LMS front end.
Course titles, section names and lesson titles there come from `Ha_bridge sync`,
which publishes the English side of the academy tables into a `course` table
that has one title column. Switching the interface to Arabic translates the
chrome around them, not the courses themselves.

### Page design

```bash
php index.php ha_artwork assign   # give programmes and paths a photograph
php index.php ha_artwork report   # what carries artwork
python .lab/design_check.py       # design sweep, both locales and widths
```

Three shared pieces do the work, so a change reaches every page instead of one:

- `application/views/academy/_hero.php` is the hero. Give it a picture and it
  lays out in two columns with one real count resting on the corner of the
  photograph; leave the picture out and it falls back to a single column. It
  replaced a hero copied into twenty views that had already drifted apart.
- `application/views/academy/_close.php` is the closing band. Every page now
  ends by asking for something rather than by becoming the footer.
- `.ha-card__cover` in `academy.css` is the card that leads with its picture.

Programmes and learning paths carried no artwork at all, which is why those
listings were walls of white rectangles. `ha_artwork assign` pairs each one
with a file already in `ha_media`, so the pictures are the same Wikimedia
Commons files under the same licences, already credited. The pairing is written
out rather than matched on words: "Hotel Safety Essentials" matched nothing
sensible, and a front office photograph on a fire safety programme is worse
than none.

`ha_page_art()` resolves a hero picture by media subject. Listing pages have no
thumbnail of their own, and borrowing the first record's put a kitchen on the
programmes hero purely because "Food Safety Certified" sorts first.

`.lab/design_check.py` is the guard: 21 pages at desktop and phone, failing on
a page too thin to be worth the visit, a hero with neither copy nor artwork, a
grid where every card shows the same picture, a page with no closing band,
broken images, horizontal overflow and JavaScript errors.

### The Academy LMS home layout

`application/views/frontend/default-new/home_hospitality.php`, registered in
**Home Page Builder** as "Hospitality Academy" and active.

It loads `assets/academy/academy.css` rather than restating the palette. That
is the point: the two front ends were drifting into two different violets and
two different card shapes, and one stylesheet is the only thing that keeps them
together. Every number on it is counted from the catalogue as the page renders.

Two things worth knowing if you build another layout:

- `Home::home()` picks the view from `home_pages.html_file_names[0]`, **not**
  from `frontend_settings.home_page`. A row without that column falls back to
  `home_1` and your layout never renders, which looks exactly like the layout
  being broken.
- The layout is visible at `/home`. The site root shows it only when
  **root_frontend** is set to `lms` (see above); it is currently `academy`, so
  the root still serves the bilingual SEO site.

### Which front end the site root serves

The application ships two public front ends and the root can only show one.
It is an administrator's choice, under **Home Page Builder**:

| Setting | The root shows |
|---|---|
| `academy` (default) | The bilingual SEO site, `/en` and `/ar` |
| `lms` | The Academy LMS theme at `/home`, which the Home Page Builder and the theme switcher drive |

This exists because with the academy site fixed at the root, building a home
page and activating a theme both appeared to do nothing: they were configuring
a front end nobody ever landed on.

The academy pages also read the administrator's brand: the uploaded dark logo
in the header, the light logo in the footer, `system_title` as the name, and
`custom_css` on every page. They previously read none of it and drew a
hardcoded mark instead, which is why uploading a logo appeared to do nothing.

### Assessments

```bash
php index.php ha_quiz build     # create or refresh the assessment on every course
php index.php ha_quiz report    # coverage and question count
php index.php ha_quiz clear     # remove every assessment
```

Every course ends with a four-question assessment: 74 courses, 296 questions,
70 per cent to pass, three attempts. Questions live in
`application/libraries/Ha_quizbank.php`, written per course rather than
generated, because a question that could belong to any course ("What is the
main goal of this procedure?") tests whether the learner can read rather than
whether they learned the work.

`ha_bridge sync` rebuilds them as its last step. It has to: republishing
rewrites every legacy lesson row, which used to take the assessments with it,
so they existed until the next routine sync and then quietly did not. The same
rebuild also clears questions orphaned by earlier syncs, which had accumulated
to three times the real count.

Questions are `single_choice`, which renders radio buttons. The
`multiple_choice` type renders checkboxes and belongs to questions with more
than one right answer. `correct_answers` holds the correct option's **1-based
position as a string**, because that is the value the radio posts and what the
grader compares with `in_array()`.

Not yet done: the questions belong in `ha_assessment` and `ha_question` so the
academy tables stay the source of truth. That move is blocked on Arabic, since
those tables require `body_ar` on every question and 296 machine-translated
exam questions about food safety and fire response is not something to ship.

### Content gap

```bash
php index.php ha_audit content   # per course: thin lessons, missing media, no assessment
```

This is the audit that matters before launch, and it currently fails. The
website is finished; the teaching content is not. Every course carries the
same nine lesson shapes with the course name interpolated, bodies average
under 200 characters, no lesson has a transcript, and no course has an
assessment, so a learner can be certified without being tested. `ha_audit run`
proving 240 green checks says the pages render, not that they teach.

### Verification

```bash
php index.php ha_test run              # whole suite
php index.php ha_test run rbac         # one suite
php index.php ha_test list_tests       # what exists

HA_AUDIT_BASE=http://localhost/atlas-lms/Academy-LMS php index.php ha_audit run
HA_AUDIT_BASE=http://localhost/atlas-lms/Academy-LMS php index.php ha_audit flows

python .lab/routes.py       # both frontends in a real browser, signed in and out
python .lab/shoot.py        # desktop and phone screenshots of the academy site
```

`ha_test` rebuilds `atlas_hospitality_test` from the migrations on every run, so
a test never touches working data. `ha_audit run` walks every URL in the sitemap
and checks what a crawler actually receives. `ha_audit flows` submits the contact
form, verifies certificates, searches and follows the language switch.

---

## Layout

```
application/
  controllers/
    Academy.php        public website, all routes, both languages
    Ha_cli.php         migration and seed runner
    Ha_images.php      photography pipeline
    Ha_test.php        test runner
    Ha_audit.php       rendered page and flow audits
    Ha_bridge.php      publishes the catalogue into the Academy LMS tables
  libraries/
    Ha_auth.php        roles, permissions, tenant scope
    Ha_audit.php       audit log writer
    Ha_catalog.php     public read model, published content only
    Ha_seo.php         metadata, hreflang, JSON-LD, sitemap, redirects
    Ha_repository.php  admin list and write mechanics
    Ha_repo_*.php      one per organisation entity
    Ha_migration.php   migration base class
    Ha_seeder.php      seeder base class
    Ha_testcase.php    assertions
  helpers/
    ha_media_helper.php  image rendering
  migrations/          six migrations, 89 tables
  seeds/               RBAC, organisations, curriculum, SOPs, content
  tests/               schema, RBAC, repositories, content, SEO
  views/academy/       the public theme
assets/academy/        stylesheet and script
uploads/academy/       downloaded photographs (WebP, two sizes)
```

---

## Photography and licensing

Photographs come from **Wikimedia Commons**, not from an image search. Google
Images indexes third-party copyrighted work; republishing it would expose the
site to takedowns and licence claims.

The pipeline only accepts licences that permit commercial reuse (CC0, public
domain, CC BY, CC BY-SA), stores the author, licence, licence URL and source
page beside each file, and flags anything whose licence requires the
photographer to be named. Those are listed on `/en/credits` and `/ar/credits`,
which the footer links from every page. That page is a licence obligation, not
decoration.

Candidates are drawn from curated Commons categories rather than free-text
search, because relevance ranking returns confident nonsense: a search for
"Riyadh" first returned a photograph of a fort in Bahrain. Files whose name or
description marks them as a drawing, map, diagram, logo or archival plate are
rejected, as are military subjects and any image featuring children, which do
not belong on a hotel training site. No two subjects share a photograph: the
downloader rejects a file whose checksum is already held.

Two subjects have no photograph of their own. Commons has no modern, on-brand
certificate photograph that survives those filters, so the certification
surfaces borrow the training-room image rather than ship a medieval manuscript
or a military parade.

Every image is converted to WebP at two widths: 1600px for heroes and 800px for
cards. Markup carries intrinsic `width` and `height` so a grid does not reflow
while images arrive, heroes load eagerly, everything else lazily.

---

## Design

Both frontends share the Academy LMS palette: violet `#754FFE` on slate
(`#0D0C23`, `#1E293B`, `#6E798A`) over a near-white `#F8F7FF`. The academy site
uses the accent to lead the eye rather than to decorate: a rule above each page
and section title, a lift and accent border on card hover, a solid primary
button with a real shadow, and tinted sections alternating with white to give
the page a rhythm.

Two things were removed from the shipped theme's home page, because they state
something untrue. The partner logo strip used demo assets showing Stanford, the
University of Texas and the University of Chicago, which claims an endorsement
this academy does not have. The generic stock hero art was replaced with the
academy's own licensed hospitality photography. Reinstate a partner strip only
with real partners who have agreed to be named.

## Bilingual delivery

English and Arabic are separate authored content, not a translation layer. Each
translatable record has one row per locale in a `*_translation` table, and slugs
are translated too, so an Arabic reader gets an Arabic URL:

```
/en/courses/fo-check-in
/ar/courses/إجراءات-تسجيل-الوصول
```

That needs two things most CodeIgniter sites do not have. `permitted_uri_chars`
in `application/config/config.php` is extended to the Arabic Unicode block, and
slugs are decoded once in the controller. Arabic page URLs resolve through a
database lookup rather than literal route keys, because CodeIgniter compiles a
route key into a regex without the unicode modifier and a literal Arabic key
never matches.

The Arabic interface is a real right-to-left document: `dir` on `<html>`,
logical CSS properties throughout, and an Arabic font stack. It is not an
English layout mirrored.

---

## Content rules

The site does not publish pass rates, success statistics, employer endorsements
or accreditation claims, because none of them can be evidenced to a visitor.
Testimonials are deliberately unseeded: a real quote needs a real, consenting
person.

Competitor records store only what the cited page states, each with an evidence
URL and the date it was checked. Anything the source does not say is recorded as
`unknown` rather than guessed.

---

## Status

Implementation status against the specification in `plan-final.txt`, including
what is finished and what is not, is tracked in
[IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md). Nothing there is marked
verified without a passing test or audit.

The public website, curriculum, SOP content, SEO system and photography are
complete. The Academy LMS admin panel and learner area work and are documented
under [Running the academy](#running-the-academy); what is unbuilt is the
academy's own `ha_*` administration, and the assessment, certificate,
notification and reporting engines, which have schema but no application code
yet.

---

## Demo accounts

Seeded by `002_organizations`, password `Academy#2026`:

| Account | Role |
|---|---|
| `academy.admin@hospitalityacademy.sa` | Academy admin |
| `org.admin@dyafagroup.sa` | Organisation admin |
| `gm.riyadh@dyafagroup.sa` | Property manager |
| `fom.riyadh@dyafagroup.sa` | Department manager |
| `omar.learner@dyafagroup.sa` | Learner |
| `auditor@dyafagroup.sa` | Auditor |

The super admin is `admin@hospitalityacademy.sa`. Change every one of these
before this runs anywhere but a local machine.

| Account | Password | Where it lands |
|---|---|---|
| `admin@hospitalityacademy.sa` | `admin123` | `/admin/dashboard` |
| `instructor.fo@hospitalityacademy.sa` | `Academy#2026` | instructor panel |
| `omar.learner@dyafagroup.sa` | `Academy#2026` | `/home/my_courses` |

---

## Running the academy

### Signing in as the administrator

There is one sign-in form for everybody, at `/login`. `/admin` is not a login
page: it checks the session and, when there is none, sends the browser to
`/login` with a `Refresh` header. A blank window at `/admin` therefore means
the browser has not followed that redirect, not that the panel is down. To
check a deployment from the command line:

```bash
curl -sI https://example.com/atlas/admin | grep -i refresh
# refresh: 0;url=https://example.com/atlas/login   <- working as designed
```

Sign in at `/login` with the super admin account above and you land on
`/admin/dashboard`. The sidebar is the whole panel. `Visit website` in the top
bar returns to the public site, and `Administration` in the public header comes
back.

### Where course content is edited

Everything about a course lives in one editor, reached from
**Courses > All courses** and then the edit action, or directly:

```
/admin/courses                         the list
/admin/course_form/add_course          create
/admin/course_form/course_edit/{id}    edit: info, curriculum, pricing, SEO
/admin/quizes/{id}                     quizzes for a course
/admin/quiz_questions/{quiz_id}        questions in a quiz
```

Sections and lessons are added inside the course editor's curriculum tab, not
on a page of their own. `/admin/lessons/{id}` redirects there.

### Lesson types

The **Add lesson** dialog offers these. Only the first three columns matter for
where the video actually lives:

| Type | Where the file sits | Notes |
|---|---|---|
| Video (upload) | your server | Simplest, and the fastest way to fill a shared host's disk quota |
| YouTube | YouTube | Unlisted videos work and cost nothing |
| Vimeo | Vimeo | |
| Google Drive | Drive | Needs the file shared to anyone with the link |
| HTML5 | any URL | A direct `.mp4` URL, played in a `<video>` tag. Needs byte-range support |
| Wasabi | Wasabi S3 | Configured under **Settings > Wasabi**. Cheap S3-compatible storage |
| Academy Cloud | Creativeitem's service | Paid addon |
| iframe embed | anywhere | Renders the URL inside an `<iframe>` |
| Audio, Document, Image, Text | your server | Handouts, SOP PDFs, checklists |

### Terabox will not work as a video source

Not a configuration problem, and not fixable from this side. Terabox sends
`X-Frame-Options: SAMEORIGIN` on its share pages:

```bash
curl -sI https://www.terabox.com/ | grep -i x-frame
# X-Frame-Options: SAMEORIGIN
```

A browser refuses to render that inside an iframe on another domain, so an
`iframe embed` lesson pointing at a Terabox share shows an empty box. The
`HTML5` type does not rescue it either: Terabox does not publish a stable
direct file URL, the one it generates is tied to a session and expires, and
using a consumer storage account to serve a commercial course also runs against
its terms of service.

For paid courses, use a host built for it. In rough order of least work:

1. **YouTube unlisted**, free, already supported, but the player carries
   YouTube branding and the video is reachable by anyone with the link.
2. **Bunny Stream** or **Cloudflare Stream**, a few dollars a month, real
   signed playback and adaptive bitrate. Use the `HTML5` or `iframe` type.
3. **Wasabi**, already built into this panel under **Settings > Wasabi**, if
   you would rather own the bucket.

### Quizzes and resources

Quizzes are per course, at **Courses > the course > Quiz**, or
`/admin/quizes/{course_id}`. A quiz holds questions added at
`/admin/quiz_questions/{quiz_id}`. Downloadable handouts, SOP PDFs and
checklists are lessons of type Document, attached to the section they belong to.

### Analytics

| Screen | What it answers |
|---|---|
| `/admin/dashboard` | Revenue by month, course/lesson/enrolment/student counts, course overview |
| `/admin/admin_revenue` | What the platform earned |
| `/admin/instructor_revenue` | What each instructor is owed |
| `/admin/purchase_history` | Every transaction |
| `/admin/enrol_history` | Every enrolment, paid or manual |
| `/admin/course_enrol_list` | Who is enrolled on one course |

The public site has a separate check of its own, `php index.php ha_audit run`,
which walks every published page. It is a correctness audit, not traffic
analytics; for traffic, add an analytics tag under **Settings > SEO**.

### Taking payment

The catalogue as seeded earns nothing, by construction. Three things are set
for a demo and all three have to change before a single riyal can arrive:

1. **Every course is free.** All 74 rows carry `is_free_course = 1` and
   `price = 0`. Set a price per course in the course editor, or in bulk:

   ```sql
   UPDATE course SET is_free_course = 0, price = 250 WHERE id IN (...);
   ```

2. **Every gateway is in test mode.** `paypal` is `"mode":"sandbox"`, `stripe`
   has `"testmode":"on"`, and `razorpay` holds an `rzp_test` key. Enter live
   keys and switch the mode under **Settings > Payment**.

3. **The currency is USD.** For Saudi pricing set `system_currency` to `SAR`
   under **Settings > System**, along with the matching gateway currency.

Instructor payouts and the platform's commission split are under
**Settings > Instructor** and `/admin/instructor_payout`.
