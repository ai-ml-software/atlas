# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: admin-studio.spec.ts >> PDF analysis, configured AI drafting, saved preview and unpublished package import
- Location: tests\admin-studio.spec.ts:30:5

# Error details

```
TimeoutError: locator.selectOption: Timeout 15000ms exceeded.
Call log:
  - waiting for locator('#publisher-model')
    - locator resolved to <select class="hkp-select" id="publisher-model"></select>
  - attempting select option action
    2 × waiting for element to be visible and enabled
      - did not find some options
    - retrying select option action
    - waiting 20ms
    2 × waiting for element to be visible and enabled
      - did not find some options
    - retrying select option action
      - waiting 100ms
    29 × waiting for element to be visible and enabled
       - did not find some options
     - retrying select option action
       - waiting 500ms

```

# Page snapshot

```yaml
- generic [active] [ref=f2e1]:
  - link "Skip to content" [ref=f2e2] [cursor=pointer]:
    - /url: "#hkp-main"
  - generic [ref=f2e3]:
    - complementary "Main navigation" [ref=f2e4]:
      - generic [ref=f2e5]: Altus Knowledge and Performance
      - generic [ref=f2e8]:
        - generic [ref=f2e9]: Find a page
        - searchbox "Find a page" [ref=f2e10]
        - button "Collapse sidebar" [expanded] [ref=f2e11] [cursor=pointer]
      - navigation [ref=f2e14]:
        - group [ref=f2e15]:
          - generic "Overview" [ref=f2e16] [cursor=pointer]:
            - text: Overview
            - generic [aria-hidden] [ref=f2e17]: ⌄
          - link "Portfolio dashboard" [ref=f2e18] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin
        - group [ref=f2e22]:
          - generic "Learn" [ref=f2e23] [cursor=pointer]:
            - text: Learn
            - generic [aria-hidden] [ref=f2e24]: ⌄
        - group [ref=f2e25]:
          - generic "People & performance" [ref=f2e26] [cursor=pointer]:
            - text: People & performance
            - generic [aria-hidden] [ref=f2e27]: ⌄
        - group [ref=f2e28]:
          - generic "Portfolio" [ref=f2e29] [cursor=pointer]:
            - text: Portfolio
            - generic [aria-hidden] [ref=f2e30]: ⌄
        - group [ref=f2e31]:
          - generic "Content Studio" [ref=f2e32] [cursor=pointer]:
            - text: Content Studio
            - generic [aria-hidden] [ref=f2e33]: ⌄
          - link "Content dashboard" [ref=f2e34] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms/studio
          - link "Modules & lessons" [ref=f2e38] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms/modules
          - link "Programs" [ref=f2e42] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms/catalogue/programs
          - link "Learning paths" [ref=f2e46] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms/catalogue/paths
          - link "Curriculum" [ref=f2e50] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/curriculum
          - link "Assessments" [ref=f2e54] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/assessments
          - link "Articles" [ref=f2e58] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms/catalogue/articles
          - link "Hospitality topics" [ref=f2e62] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms/catalogue/topics
          - link "Competencies" [ref=f2e66] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/competencies
          - link "AI Publisher" [ref=f2e70] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms/publisher
          - link "Imports" [ref=f2e74] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/imports
          - link "Content review" [ref=f2e78] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/content
          - link "Library and language coverage" [ref=f2e82] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/library
        - group [ref=f2e86]:
          - generic "Website" [ref=f2e87] [cursor=pointer]:
            - text: Website
            - generic [aria-hidden] [ref=f2e88]: ⌄
        - group [ref=f2e89]:
          - generic "Platform" [ref=f2e90] [cursor=pointer]:
            - text: Platform
            - generic [aria-hidden] [ref=f2e91]: ⌄
        - generic [ref=f2e92] [cursor=pointer]: Academy LMS
        - link "Classic admin panel" [ref=f2e93] [cursor=pointer]:
          - /url: http://127.0.0.1:8099/admin/dashboard
      - generic [ref=f2e97]:
        - generic [ref=f2e98]: AA
        - generic [ref=f2e99]:
          - strong [ref=f2e100]: Academy Admin
          - link "Account settings" [ref=f2e101] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/profile
        - link "Sign out" [ref=f2e102] [cursor=pointer]:
          - /url: http://127.0.0.1:8099/login/logout
    - generic [ref=f2e105]:
      - banner [ref=f2e106]:
        - search [ref=f2e107]:
          - generic [ref=f2e110]: Search approved knowledge
          - searchbox "Search approved knowledge" [ref=f2e111]
        - button "Find pages and quick actions" [ref=f2e112] [cursor=pointer]: ⌘ K
        - generic [ref=f2e113]:
          - generic [ref=f2e114]: Property
          - combobox "Property" [ref=f2e115]:
            - option "All properties" [selected]
            - option "Altus Demo Client / ALTUS Demo Hotel Riyadh"
            - option "Dyafa Hospitality Group / Dyafa Abha Highlands"
            - option "Dyafa Hospitality Group / Dyafa Al Khobar Waterfront"
            - option "Dyafa Hospitality Group / Dyafa AlUla Desert Resort"
            - option "Dyafa Hospitality Group / Dyafa Dammam Airport"
            - option "Dyafa Hospitality Group / Dyafa Jeddah Corniche"
            - option "Dyafa Hospitality Group / Dyafa Madinah Central"
            - option "Dyafa Hospitality Group / Dyafa Makkah Haram View"
            - option "Dyafa Hospitality Group / Dyafa Riyadh Business Tower"
        - group [ref=f2e116]:
          - generic "Language" [ref=f2e117] [cursor=pointer]: English
        - link "Notifications" [ref=f2e121] [cursor=pointer]:
          - /url: http://127.0.0.1:8099/hkp/notifications
        - group [ref=f2e124]:
          - generic "Academy Admin" [ref=f2e125] [cursor=pointer]:
            - generic [aria-hidden] [ref=f2e126]: AA
      - main [ref=f2e128]:
        - navigation "Breadcrumb" [ref=f2e129]:
          - link "Workspace" [ref=f2e130] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp
          - generic [aria-hidden] [ref=f2e131]: /
          - generic [ref=f2e132]: AI Publisher
        - status [ref=f2e133]: Source analysed. Review the extracted text before generating.
        - generic [ref=f2e134]:
          - generic [ref=f2e135]:
            - generic [ref=f2e136]: From source to learning
            - heading "AI Publisher" [level=1] [ref=f2e137]
            - paragraph [ref=f2e138]: Your operational knowledge. A structured draft. A human decision.
          - link "New document" [ref=f2e139] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms/publisher
        - generic [ref=f2e140]:
          - generic [ref=f2e141]:
            - strong [ref=f2e142]: "1"
            - text: Upload source
          - generic [ref=f2e143]:
            - strong [ref=f2e144]: "2"
            - text: Analyse & generate
          - generic [ref=f2e145]:
            - strong [ref=f2e146]: "3"
            - text: Review & edit
          - generic [ref=f2e147]:
            - strong [ref=f2e148]: "4"
            - text: Create draft
        - generic [ref=f2e150]:
          - generic [ref=f2e151]:
            - generic [ref=f2e152]:
              - heading "Source document" [level=2] [ref=f2e153]
              - generic [ref=f2e154]: EN
            - paragraph [ref=f2e155]:
              - strong [ref=f2e156]: safe-cleaning.pdf
              - text: · 107 characters
            - generic [ref=f2e157]: "[Page 1] Always wear PPE before cleaning hotel bathrooms. Follow the approved procedure and report hazards."
          - generic [ref=f2e158]:
            - generic [ref=f2e159]:
              - heading "Structured draft" [level=2] [ref=f2e160]
              - generic [ref=f2e161]: AI Draft
            - group [ref=f2e164]:
              - generic "Generation settings" [ref=f2e165]
              - generic [ref=f2e166]:
                - generic [ref=f2e167]:
                  - generic [ref=f2e168]:
                    - generic [ref=f2e169]: Provider
                    - combobox "Provider" [ref=f2e170]:
                      - option "Choose provider"
                      - option "Claude API"
                      - option "E2E Mock" [selected]
                  - generic [ref=f2e171]:
                    - generic [ref=f2e172]: Model
                    - combobox "Model" [ref=f2e173]
                - generic [ref=f2e174]:
                  - generic [ref=f2e175]: Editorial brief
                  - textbox "Editorial brief" [ref=f2e176]:
                    - /placeholder: Audience, difficulty, number of lessons, quiz questions…
                - paragraph [ref=f2e177]: Generate sends this extracted source to your chosen provider for drafting. Review all facts and answers before use.
                - button "Generate draft" [ref=f2e178] [cursor=pointer]
            - status
            - paragraph [ref=f2e182]: Generate a draft, or paste a structured package in the advanced editor.
            - group [ref=f2e183]:
              - 'generic "Advanced: structured JSON / import" [ref=f2e184]'
            - generic [ref=f2e185]:
              - button "Save draft" [ref=f2e186] [cursor=pointer]
              - link "Preview saved draft" [ref=f2e187] [cursor=pointer]:
                - /url: http://127.0.0.1:8099/hkp/cms/publisher_preview/5
              - button "Create content as draft" [ref=f2e188] [cursor=pointer]
        - generic [ref=f2e191]:
          - heading "Recent documents" [level=2] [ref=f2e192]
          - table [ref=f2e194]:
            - rowgroup [ref=f2e195]:
              - row [ref=f2e196]:
                - columnheader "Source" [ref=f2e197]
                - columnheader "Output" [ref=f2e198]
                - columnheader "Language" [ref=f2e199]
                - columnheader "Status" [ref=f2e200]
                - columnheader "Created" [ref=f2e201]
            - rowgroup [ref=f2e202]:
              - row [ref=f2e203]:
                - cell [ref=f2e204]:
                  - link "safe-cleaning.pdf" [ref=f2e205] [cursor=pointer]:
                    - /url: http://127.0.0.1:8099/hkp/cms/publisher/5
                - cell "Course" [ref=f2e206]
                - cell "EN" [ref=f2e207]
                - cell "Source" [ref=f2e208]
                - cell "2 Oct 2026, 09:32" [ref=f2e210]
              - row [ref=f2e211]:
                - cell [ref=f2e212]:
                  - link "safe-cleaning.pdf" [ref=f2e213] [cursor=pointer]:
                    - /url: http://127.0.0.1:8099/hkp/cms/publisher/4
                - cell "Course" [ref=f2e214]
                - cell "EN" [ref=f2e215]
                - cell "Source" [ref=f2e216]
                - cell "2 Oct 2026, 09:30" [ref=f2e218]
              - row [ref=f2e219]:
                - cell [ref=f2e220]:
                  - link "Private.txt" [ref=f2e221] [cursor=pointer]:
                    - /url: http://127.0.0.1:8099/hkp/cms/publisher/3
                - cell "Course" [ref=f2e222]
                - cell "EN" [ref=f2e223]
                - cell "Source" [ref=f2e224]
                - cell "2 Oct 2026, 13:18" [ref=f2e226]
              - row [ref=f2e227]:
                - cell [ref=f2e228]:
                  - link "Safe cleaning.txt" [ref=f2e229] [cursor=pointer]:
                    - /url: http://127.0.0.1:8099/hkp/cms/publisher/2
                - cell "Course" [ref=f2e230]
                - cell "EN" [ref=f2e231]
                - cell "Imported" [ref=f2e232]
                - cell "2 Oct 2026, 13:18" [ref=f2e234]
              - row [ref=f2e235]:
                - cell [ref=f2e236]:
                  - link "Source.txt" [ref=f2e237] [cursor=pointer]:
                    - /url: http://127.0.0.1:8099/hkp/cms/publisher/1
                - cell "Course" [ref=f2e238]
                - cell "EN" [ref=f2e239]
                - cell "Draft" [ref=f2e240]
                - cell "2 Oct 2026, 13:18" [ref=f2e242]
      - contentinfo [ref=f2e243]:
        - generic [ref=f2e244]:
          - img "Altus Gulf" [ref=f2e245]
          - generic [ref=f2e246]: The right knowledge, to the right person, at the right time.
        - navigation "Altus Gulf" [ref=f2e247]:
          - link "About" [ref=f2e248] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/about-altus
          - link "Services" [ref=f2e249] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/services
          - link "Altus Knowledge and Performance" [ref=f2e250] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/knowledge-performance
          - link "Ascent" [ref=f2e251] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/ascent
          - link "Market" [ref=f2e252] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/market
          - link "Case Studies" [ref=f2e253] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/case-studies
          - link "Leadership" [ref=f2e254] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/leadership
          - link "Courses" [ref=f2e255] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/courses
          - link "Contact" [ref=f2e256] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/contact
        - generic [ref=f2e257]:
          - generic [ref=f2e258]: © 2026 Altus Gulf · All rights reserved. Elevating Hospitality & Business Performance
          - generic [ref=f2e259]: Altus Knowledge and Performance · Altus Gulf, Riyadh
```

# Test source

```ts
  1  | import { test, expect, open, flashOk, stamp } from '../support/fixtures';
  2  | import { one, sql } from '../support/db';
  3  | import { mkdirSync } from 'node:fs';
  4  | import path from 'node:path';
  5  | 
  6  | function sourcePdf() {
  7  |   const text = 'BT /F1 12 Tf 50 750 Td (Always wear PPE before cleaning hotel bathrooms. Follow the approved procedure and report hazards.) Tj ET';
  8  |   const objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', `<< /Length ${text.length} >>\nstream\n${text}\nendstream`];
  9  |   let pdf = '%PDF-1.4\n', offsets = [0]; objects.forEach((o, i) => { offsets.push(pdf.length); pdf += `${i + 1} 0 obj\n${o}\nendobj\n`; });
  10 |   const xref = pdf.length; pdf += `xref\n0 6\n0000000000 65535 f \n${offsets.slice(1).map(o => `${String(o).padStart(10, '0')} 00000 n \n`).join('')}trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
  11 |   return { name: 'safe-cleaning.pdf', mimeType: 'application/pdf', buffer: Buffer.from(pdf) };
  12 | }
  13 | 
  14 | test('admin sidebar, command palette and every permitted destination render', async ({ as }) => {
  15 |   test.setTimeout(180_000);
  16 |   const p = await as('admin'); await open(p, 'hkp/admin');
  17 |   await expect(p.locator('.studio-metric')).toHaveCount(8);
  18 |   await expect(p.locator('body')).toContainText('No comparison period available');
  19 |   await p.keyboard.press('Control+k'); await expect(p.locator('#studio-command')).toBeVisible();
  20 |   await p.locator('#studio-command-input').fill('AI Publisher'); await expect(p.locator('.studio-command-results a:visible')).toHaveCount(1); await p.keyboard.press('Escape');
  21 |   await p.locator('[data-collapse-sidebar]').click(); await expect(p.locator('body')).toHaveClass(/studio-collapsed/); await p.reload(); await expect(p.locator('body')).toHaveClass(/studio-collapsed/); await p.locator('[data-collapse-sidebar]').click();
  22 |   await p.locator('#studio-nav-search').fill('Website pages'); await expect(p.locator('.hkp-nav__item:visible')).toHaveCount(1); await p.locator('#studio-nav-search').fill('');
  23 |   const urls = await p.locator('.hkp-nav__item').evaluateAll(links => [...new Set(links.map(a => (a as HTMLAnchorElement).href))]);
  24 |   for (const url of urls) await open(p, url);
  25 |   await open(p, 'hkp/admin'); mkdirSync(path.resolve('..', 'docs', 'screenshots'), { recursive: true });
  26 |   await p.screenshot({ path: '../docs/screenshots/admin-dashboard.png', fullPage: true });
  27 |   await open(p, 'hkp/cms/studio'); await p.screenshot({ path: '../docs/screenshots/content-studio.png', fullPage: true });
  28 | });
  29 | 
  30 | test('PDF analysis, configured AI drafting, saved preview and unpublished package import', async ({ as }) => {
  31 |   const p = await as('admin'); await open(p, 'hkp/cms/publisher');
  32 |   await p.locator('#publisher-file').setInputFiles(sourcePdf()); await p.getByRole('button', { name: 'Analyse document' }).click(); await flashOk(p);
  33 |   await expect(p.locator('.studio-source')).toContainText('Always wear PPE');
> 34 |   await p.locator('#publisher-provider').selectOption('e2e_mock'); await p.locator('#publisher-model').selectOption('mock-writer');
     |                                                                                                        ^ TimeoutError: locator.selectOption: Timeout 15000ms exceeded.
  35 |   await p.getByRole('button', { name: 'Generate draft', exact: true }).click();
  36 |   await expect(p.locator('#publisher-status')).toContainText('Draft saved', { timeout: 30_000 });
  37 |   const title = await p.locator('#publisher-visual input').first().inputValue();
  38 |   await expect(p.locator('#publisher-visual')).toContainText('Preparation');
  39 |   const preview = await p.context().newPage(); await open(preview, (await p.getByRole('link', { name: 'Preview saved draft' }).getAttribute('href'))!); await expect(preview.locator('h1')).toHaveText(title); await preview.close();
  40 |   await p.screenshot({ path: '../docs/screenshots/document-publisher.png', fullPage: true });
  41 |   await p.getByRole('button', { name: 'Create content as draft' }).click(); await expect(p).toHaveURL(/cms\/module\/\d+/);
  42 |   const course = Number(p.url().match(/module\/(\d+)/)![1]);
  43 |   expect(one(`SELECT status FROM ha_course WHERE id=${course}`)!).toBe('draft');
  44 |   expect(one(`SELECT COUNT(*) AS n FROM ha_lesson WHERE course_id=${course}`)!).toBe('1');
  45 |   expect(one(`SELECT status FROM ha_assessment WHERE course_id=${course}`)!).toBe('draft');
  46 | });
  47 | 
  48 | test('live editing keeps draft private, previews the real page and publishes explicitly', async ({ as, page }) => {
  49 |   const p = await as('admin'); const id = Number(one("SELECT id FROM ha_page WHERE code='about'")!), title = 'Private live title ' + stamp();
  50 |   // A preceding PHP test deliberately leaves an outdated contact draft; use an unmodified published page here.
  51 |   await open(p, `hkp/cms/live/${id}`);
  52 |   await p.locator('#live-fields').getByLabel('Page title', { exact: true }).fill(title);
  53 |   await expect(p.locator('#live-status')).toContainText('Draft saved', { timeout: 15_000 });
  54 |   await expect(p.frameLocator('#live-preview').locator('h1')).toHaveText(title);
  55 |   await open(page, 'en/about'); await expect(page.locator('h1')).not.toHaveText(title); await expect(page.locator('.ha-studio-edit')).toHaveCount(0);
  56 |   await p.getByRole('button', { name: 'Mobile', exact: true }).click(); await expect(p.locator('#live-preview-wrap')).toHaveAttribute('data-device', 'mobile');
  57 |   await p.screenshot({ path: '../docs/screenshots/live-editor.png', fullPage: true });
  58 |   await p.getByRole('button', { name: 'Publish', exact: true }).click(); await expect(p.locator('#live-status')).toContainText('Published');
  59 |   await page.reload(); await expect(page.locator('h1')).toHaveText(title);
  60 |   await open(p, 'en/about'); await expect(p.getByRole('link', { name: /Edit page/ })).toBeVisible();
  61 | });
  62 | 
  63 | test('catalogue drafts save bilingual articles; navigation labels appear publicly', async ({ as, page }) => {
  64 |   const p = await as('admin'), id = stamp(); await open(p, 'hkp/cms/catalogue/articles/new');
  65 |   await p.locator('[name=title_en]').fill('Studio article ' + id); await p.locator('[name=title_ar]').fill('مقالة اختبار');
  66 |   await p.locator('[name=slug_en]').fill('studio-article-' + id); await p.locator('[name=slug_ar]').fill('studio-article-ar-' + id);
  67 |   await p.locator('[name=body_en]').fill('<p>Useful source content.</p>'); await p.locator('[name=body_ar]').fill('<p>محتوى مفيد.</p>');
  68 |   await p.getByRole('button', { name: 'Save content' }).click(); await flashOk(p); await expect(p).toHaveURL(/catalogue\/articles\/\d+/);
  69 |   await open(p, 'hkp/cms/navigation'); await p.getByRole('link', { name: 'Footer · Learn', exact: true }).click();
  70 |   await p.locator('[name="items[0][label_en]"]').fill('Studio professional courses'); await p.getByRole('button', { name: 'Save navigation' }).click(); await flashOk(p);
  71 |   await open(page, 'en'); await expect(page.locator('.ha-foot')).toContainText('Studio professional courses');
  72 | });
  73 | 
  74 | test('new editor actions require permission and a valid CSRF token', async ({ as }) => {
  75 |   const p = await as('learner'); for (const route of ['hkp/cms/live/1', 'hkp/cms/catalogue/articles', 'hkp/cms/navigation', 'hkp/cms/publisher']) { const response = await p.request.get(route); expect(response.status()).toBe(403); }
  76 |   const admin = await as('admin'); await open(admin, 'hkp/cms/publisher');
  77 |   const response = await admin.request.post('hkp/cms/live_save/1', { form: { action: 'publish', version: '1' }, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); expect(response.status()).toBe(403);
  78 | });
  79 | 
  80 | test('editor pages fit mobile and Arabic layouts', async ({ as }) => {
  81 |   const p = await as('admin'); await p.setViewportSize({ width: 390, height: 844 });
  82 |   for (const route of ['hkp/admin', 'hkp/cms/studio', 'hkp/cms/publisher', 'hkp/cms/live/1', 'hkp/cms/catalogue/articles', 'hkp/cms/navigation']) { await open(p, route); expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true); }
  83 |   await open(p, 'hkp/cms/studio?lang=ar'); await expect(p.locator('html')).toHaveAttribute('dir', 'rtl'); expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  84 | });
  85 | 
```