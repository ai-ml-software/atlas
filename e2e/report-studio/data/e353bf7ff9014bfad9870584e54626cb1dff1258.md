# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: admin-studio.spec.ts >> admin sidebar, command palette and every permitted destination render
- Location: tests\admin-studio.spec.ts:14:5

# Error details

```
Error: expect(locator).toHaveCount(expected) failed

Locator:  locator('.hkp-nav__item:visible')
Expected: 1
Received: 2
Timeout:  10000ms

Call log:
  - Expect "toHaveCount" locator('.hkp-nav__item:visible') with timeout 10000ms
  - waiting for locator('.hkp-nav__item:visible')
    23 × locator resolved to 2 elements
       - unexpected value "2"

```

# Page snapshot

```yaml
- generic [ref=f1e1]:
  - link "Skip to content" [ref=f1e2] [cursor=pointer]:
    - /url: "#hkp-main"
  - generic [ref=f1e3]:
    - complementary "Main navigation" [ref=f1e4]:
      - generic [ref=f1e5]: Altus Knowledge and Performance
      - generic [ref=f1e8]:
        - generic [ref=f1e9]: Find a page
        - searchbox "Find a page" [active] [ref=f1e10]: Website pages
        - button "Collapse sidebar" [expanded] [ref=f1e11] [cursor=pointer]
      - navigation [ref=f1e14]:
        - group [ref=f1e15]:
          - generic "Website" [ref=f1e16] [cursor=pointer]:
            - text: Website
            - generic [aria-hidden] [ref=f1e17]: ⌄
          - link "Website pages" [ref=f1e18] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/cms
        - generic [ref=f1e22] [cursor=pointer]: Academy LMS
        - link "Classic admin panel" [ref=f1e23] [cursor=pointer]:
          - /url: http://127.0.0.1:8099/admin/dashboard
      - generic [ref=f1e27]:
        - generic [ref=f1e28]: AA
        - generic [ref=f1e29]:
          - strong [ref=f1e30]: Academy Admin
          - link "Account settings" [ref=f1e31] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/profile
        - link "Sign out" [ref=f1e32] [cursor=pointer]:
          - /url: http://127.0.0.1:8099/login/logout
    - generic [ref=f1e35]:
      - banner [ref=f1e36]:
        - search [ref=f1e37]:
          - generic [ref=f1e40]: Search approved knowledge
          - searchbox "Search approved knowledge" [ref=f1e41]
        - button "Find pages and quick actions" [ref=f1e42] [cursor=pointer]: ⌘ K
        - generic [ref=f1e43]:
          - generic [ref=f1e44]: Property
          - combobox "Property" [ref=f1e45]:
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
        - group [ref=f1e46]:
          - generic "Language" [ref=f1e47] [cursor=pointer]: English
        - link "Notifications" [ref=f1e51] [cursor=pointer]:
          - /url: http://127.0.0.1:8099/hkp/notifications
        - group [ref=f1e54]:
          - generic "Academy Admin" [ref=f1e55] [cursor=pointer]:
            - generic [aria-hidden] [ref=f1e56]: AA
      - main [ref=f1e58]:
        - navigation "Breadcrumb" [ref=f1e59]:
          - link "Workspace" [ref=f1e60] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp
          - generic [aria-hidden] [ref=f1e61]: /
          - generic [ref=f1e62]: Portfolio dashboard
        - generic [ref=f1e63]:
          - generic [ref=f1e64]:
            - generic [ref=f1e65]: What is happening across the portfolio?
            - heading "Portfolio dashboard" [level=1] [ref=f1e66]
            - paragraph [ref=f1e67]: The people, properties and evidence behind your operational performance.
          - generic [ref=f1e68]:
            - link "+ New client" [ref=f1e69] [cursor=pointer]:
              - /url: http://127.0.0.1:8099/hkp/admin/crud/organizations/new
            - link "+ New property" [ref=f1e70] [cursor=pointer]:
              - /url: http://127.0.0.1:8099/hkp/admin/crud/properties/new
        - generic [ref=f1e71]:
          - link "Organisations 2" [ref=f1e72] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/crud/organizations
            - generic [ref=f1e76]:
              - generic [ref=f1e77]: Organisations
              - strong [ref=f1e78]: "2"
          - link "Properties 9" [ref=f1e79] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/crud/properties
            - generic [ref=f1e83]:
              - generic [ref=f1e84]: Properties
              - strong [ref=f1e85]: "9"
          - link "Active users · 30 days 30" [ref=f1e86] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/users
            - generic [ref=f1e90]:
              - generic [ref=f1e91]: Active users · 30 days
              - strong [ref=f1e92]: "30"
          - link "Learners 14" [ref=f1e93] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/admin/users
            - generic [ref=f1e97]:
              - generic [ref=f1e98]: Learners
              - strong [ref=f1e99]: "14"
          - link "Learning completion 4%" [ref=f1e100] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/team/reports
            - generic [ref=f1e104]:
              - generic [ref=f1e105]: Learning completion
              - strong [ref=f1e106]: 4%
          - link "Readiness 7%" [ref=f1e107] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/team/readiness
            - generic [ref=f1e111]:
              - generic [ref=f1e112]: Readiness
              - strong [ref=f1e113]: 7%
          - link "Critical gaps 33" [ref=f1e114] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/team/gaps
            - generic [ref=f1e118]:
              - generic [ref=f1e119]: Critical gaps
              - strong [ref=f1e120]: "33"
          - link "Certificates 2" [ref=f1e121] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/hkp/team/certifications
            - generic [ref=f1e125]:
              - generic [ref=f1e126]: Certificates
              - strong [ref=f1e127]: "2"
        - paragraph [ref=f1e128]: No comparison period available · Figures reflect your permitted portfolio.
        - generic [ref=f1e129]:
          - generic [ref=f1e130]:
            - strong [ref=f1e131]: "0"
            - text: Content awaiting approval
          - generic [ref=f1e132]:
            - strong [ref=f1e133]: "0"
            - text: Certificates expiring in 30 days
          - generic [ref=f1e134]:
            - strong [ref=f1e135]: "0"
            - text: Open action plans
          - generic [ref=f1e136]:
            - strong [ref=f1e137]: 10%
            - text: Competency coverage
        - generic [ref=f1e138]:
          - generic [ref=f1e139]:
            - generic [ref=f1e140]:
              - heading "Cross-property comparison" [level=2] [ref=f1e141]
              - paragraph [ref=f1e142]: Focus your attention where learning and readiness need support.
            - link "View properties →" [ref=f1e143] [cursor=pointer]:
              - /url: http://127.0.0.1:8099/hkp/admin/crud/properties
          - table [ref=f1e145]:
            - rowgroup [ref=f1e146]:
              - row [ref=f1e147]:
                - columnheader "Property" [ref=f1e148]
                - columnheader "Status" [ref=f1e149]
                - columnheader "Staff" [ref=f1e150]
                - columnheader "Learning completion" [ref=f1e151]
                - columnheader "Ready" [ref=f1e152]
                - columnheader "Critical gaps" [ref=f1e153]
                - columnheader "Certificates" [ref=f1e154]
            - rowgroup [ref=f1e155]:
              - row [ref=f1e156]:
                - cell [ref=f1e157]:
                  - strong [ref=f1e158]: Dyafa Riyadh Business Tower
                - cell "Operational" [ref=f1e159]
                - cell "7" [ref=f1e161]
                - cell [ref=f1e162]:
                  - progressbar [ref=f1e163]
                  - text: 0%
                - cell "0%" [ref=f1e164]
                - cell "12" [ref=f1e165]
                - cell "0" [ref=f1e167]
              - row [ref=f1e168]:
                - cell [ref=f1e169]:
                  - strong [ref=f1e170]: Dyafa Jeddah Corniche
                - cell "Operational" [ref=f1e171]
                - cell "5" [ref=f1e173]
                - cell [ref=f1e174]:
                  - progressbar [ref=f1e175]
                  - text: 0%
                - cell "0%" [ref=f1e176]
                - cell "5" [ref=f1e177]
                - cell "0" [ref=f1e179]
              - row [ref=f1e180]:
                - cell [ref=f1e181]:
                  - strong [ref=f1e182]: ALTUS Demo Hotel Riyadh
                - cell "Pre opening" [ref=f1e183]
                - cell "4" [ref=f1e185]
                - cell [ref=f1e186]:
                  - progressbar [ref=f1e187]
                  - text: 44%
                - cell "50%" [ref=f1e189]
                - cell "4" [ref=f1e190]
                - cell "2" [ref=f1e192]
              - row [ref=f1e193]:
                - cell [ref=f1e194]:
                  - strong [ref=f1e195]: Dyafa Al Khobar Waterfront
                - cell "Operational" [ref=f1e196]
                - cell "1" [ref=f1e198]
                - cell [ref=f1e199]:
                  - progressbar [ref=f1e200]
                  - text: 0%
                - cell "0%" [ref=f1e201]
                - cell "4" [ref=f1e202]
                - cell "0" [ref=f1e204]
              - row [ref=f1e205]:
                - cell [ref=f1e206]:
                  - strong [ref=f1e207]: Dyafa AlUla Desert Resort
                - cell "Operational" [ref=f1e208]
                - cell "1" [ref=f1e210]
                - cell [ref=f1e211]:
                  - progressbar [ref=f1e212]
                  - text: 0%
                - cell "0%" [ref=f1e213]
                - cell "2" [ref=f1e214]
                - cell "0" [ref=f1e216]
              - row [ref=f1e217]:
                - cell [ref=f1e218]:
                  - strong [ref=f1e219]: Dyafa Dammam Airport
                - cell "Operational" [ref=f1e220]
                - cell "1" [ref=f1e222]
                - cell [ref=f1e223]:
                  - progressbar [ref=f1e224]
                  - text: 0%
                - cell "0%" [ref=f1e225]
                - cell "2" [ref=f1e226]
                - cell "0" [ref=f1e228]
              - row [ref=f1e229]:
                - cell [ref=f1e230]:
                  - strong [ref=f1e231]: Dyafa Madinah Central
                - cell "Operational" [ref=f1e232]
                - cell "1" [ref=f1e234]
                - cell [ref=f1e235]:
                  - progressbar [ref=f1e236]
                  - text: 0%
                - cell "0%" [ref=f1e237]
                - cell "2" [ref=f1e238]
                - cell "0" [ref=f1e240]
              - row [ref=f1e241]:
                - cell [ref=f1e242]:
                  - strong [ref=f1e243]: Dyafa Makkah Haram View
                - cell "Operational" [ref=f1e244]
                - cell "1" [ref=f1e246]
                - cell [ref=f1e247]:
                  - progressbar [ref=f1e248]
                  - text: 0%
                - cell "0%" [ref=f1e249]
                - cell "2" [ref=f1e250]
                - cell "0" [ref=f1e252]
              - row [ref=f1e253]:
                - cell [ref=f1e254]:
                  - strong [ref=f1e255]: Dyafa Abha Highlands
                - cell "Pre opening" [ref=f1e256]
                - cell "0" [ref=f1e258]
                - cell [ref=f1e259]:
                  - progressbar [ref=f1e260]
                  - text: —
                - cell "—" [ref=f1e261]
                - cell "0" [ref=f1e262]
                - cell "0" [ref=f1e263]
        - generic [ref=f1e264]:
          - generic [ref=f1e265]:
            - generic [ref=f1e266]:
              - heading "Recent activity" [level=2] [ref=f1e267]
              - link "View audit →" [ref=f1e268] [cursor=pointer]:
                - /url: http://127.0.0.1:8099/hkp/admin/audit
            - list [ref=f1e269]:
              - listitem [ref=f1e270]:
                - generic [ref=f1e271]:
                  - strong [ref=f1e272]: Academy Admin
                  - text: Website menu labels, visibility and order saved
                - generic [ref=f1e273]: 2 Oct 2026, 09:30
              - listitem [ref=f1e274]:
                - generic [ref=f1e275]:
                  - strong [ref=f1e276]: Academy Admin
                  - text: "Catalogue content saved: studio-article-mur03onp"
                - generic [ref=f1e277]: 2 Oct 2026, 09:30
              - listitem [ref=f1e278]:
                - generic [ref=f1e279]:
                  - strong [ref=f1e280]: Academy Admin
                  - text: Website draft explicitly published
                - generic [ref=f1e281]: 2 Oct 2026, 09:30
              - listitem [ref=f1e282]:
                - generic [ref=f1e283]:
                  - strong [ref=f1e284]: Academy Admin
                  - text: Website draft saved
                - generic [ref=f1e285]: 2 Oct 2026, 09:30
              - listitem [ref=f1e286]:
                - generic [ref=f1e287]:
                  - strong [ref=f1e288]: Academy Admin
                  - text: "Source analysed: safe-cleaning.pdf"
                - generic [ref=f1e289]: 2 Oct 2026, 09:30
              - listitem [ref=f1e290]:
                - generic [ref=f1e291]:
                  - strong [ref=f1e292]: Academy Admin
                  - text: "Source analysed: Private.txt"
                - generic [ref=f1e293]: 2 Oct 2026, 13:18
          - generic [ref=f1e294]:
            - generic [ref=f1e295]: Content Studio
            - heading "Make your standards teachable" [level=2] [ref=f1e298]
            - paragraph [ref=f1e299]: Convert a PDF into editable learning content, or improve your website beside a live preview.
            - generic [ref=f1e300]:
              - link "Upload a document" [ref=f1e301] [cursor=pointer]:
                - /url: http://127.0.0.1:8099/hkp/cms/publisher
              - link "Edit website" [ref=f1e302] [cursor=pointer]:
                - /url: http://127.0.0.1:8099/hkp/cms
            - separator [ref=f1e303]
            - generic [ref=f1e304]: 79 published modules · 680 lessons · 86 assessments
      - contentinfo [ref=f1e305]:
        - generic [ref=f1e306]:
          - img "Altus Gulf" [ref=f1e307]
          - generic [ref=f1e308]: The right knowledge, to the right person, at the right time.
        - navigation "Altus Gulf" [ref=f1e309]:
          - link "About" [ref=f1e310] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/about-altus
          - link "Services" [ref=f1e311] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/services
          - link "Altus Knowledge and Performance" [ref=f1e312] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/knowledge-performance
          - link "Ascent" [ref=f1e313] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/ascent
          - link "Market" [ref=f1e314] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/market
          - link "Case Studies" [ref=f1e315] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/case-studies
          - link "Leadership" [ref=f1e316] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/leadership
          - link "Courses" [ref=f1e317] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/courses
          - link "Contact" [ref=f1e318] [cursor=pointer]:
            - /url: http://127.0.0.1:8099/en/contact
        - generic [ref=f1e319]:
          - generic [ref=f1e320]: © 2026 Altus Gulf · All rights reserved. Elevating Hospitality & Business Performance
          - generic [ref=f1e321]: Altus Knowledge and Performance · Altus Gulf, Riyadh
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
> 22 |   await p.locator('#studio-nav-search').fill('Website pages'); await expect(p.locator('.hkp-nav__item:visible')).toHaveCount(1); await p.locator('#studio-nav-search').fill('');
     |                                                                                                                  ^ Error: expect(locator).toHaveCount(expected) failed
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
  34 |   await p.locator('#publisher-provider').selectOption('e2e_mock'); await p.locator('#publisher-model').selectOption('mock-writer');
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