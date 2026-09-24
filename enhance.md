# Altus Advisory — Full Frontend + Backend Execution Plan

Below is a complete, ordered build spec that takes the repo from its current
Dyafa state to the Altus brand, adds every promised feature, and satisfies
Google + Yandex indexing. Every step names its acceptance test so "error free"
is measurable, not aspirational.

Defect IDs used throughout (C1, H2, B7, A1, …) are defined in
**[Appendix A](#appendix-a--source-document-defect-catalogue)**. The evidence
behind them — what was read from the three PDFs and from this repository — is in
**[Appendix B](#appendix-b--how-the-sources-were-read)**.

---

## PART 0 — Lock these before writing code (blocking)

| # | Decision | Recommended answer |
|---|---|---|
| D1 | Altus HK&P vs Dyafa's academy? | **Altus HK&P is the product; Dyafa is tenant #1** |
| D2 | Separate advisory site? | **Yes — two properties, cross-linked** |
| D3 | Case studies illustrative or real? | **Real, principal-led. Relabel "Principal-Led Engagements"** |
| D4 | Which values set? | **Brand guide's seven (governance wins)** |
| D5 | Master founder quote? | **Brand guide string; profile matches it** |
| D6 | Allow AI crawlers? | **Yes — allow GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot; also allow YandexBot** |

Nothing in Part 1 can ship cleanly until D1 and D2 are written down. Everything
else is sequenced so it does not block.

---

## PART 1 — FRONTEND

### 1.1 Brand token layer (unblocks every visual change)

> **Status: delivered.** `assets/academy/altus-tokens.css` exists and is loaded
> ahead of `academy.css` in both entry points
> ([`views/academy/layout.php`](application/views/academy/layout.php),
> [`views/frontend/default-new/home_hospitality.php`](application/views/frontend/default-new/home_hospitality.php)).
> Verified by `.lab/design_check.py` (42 checks, 0 problems), `ha_test run`
> (83/83), `ha_audit run` (240 checks / 236 URLs, 0 problems) and
> `ha_audit flows` (5/5).
>
> **One prediction in this section was wrong.** "The stylesheet body never
> changes" did not survive contact: `--ha-accent` was the *button fill* in
> ~15 rules and the *link colour* in 8 more, so aliasing it to gold would have
> produced gold fills behind white text — the exact violation §1.7 exists to
> catch, failing on the first run. The token layer therefore splits the role in
> two, and academy.css was edited to match:
>
> | Token | Colour | Job |
> |---|---|---|
> | `--ha-action` | Obsidian Navy | **Fills.** Buttons, badges, step markers, skip link, active pager |
> | `--ha-accent` | Executive Gold | **Marks.** Section rules, nav underlines, list bullets, hairline borders. Never a fill, never behind text |
> | `--ha-accent-on-light` | Deepened gold `#7A5F28` | Gold-family **text** on light surfaces, 5.6:1 |
>
> Gold now paints **0.25% of the heaviest page**, against a 5% ceiling and a
> 0.1% floor. Every contrast ratio asserted in the token file was measured, not
> estimated.

Create `assets/academy/altus-tokens.css`, loaded **before** `academy.css`. All
existing `--ha-*` variables are remapped onto it.

```css
:root {
  /* Phase II — Brand Implementation Guide */
  --altus-navy:     #0D1B2A;
  --altus-gold:     #C89D4F;
  --altus-charcoal: #333333;
  --altus-gray:     #E6E8EB;
  --altus-teal:     #0F3D3E;
  --altus-ivory:    #F7F6F2;

  /* Data-viz only */
  --altus-steel:  #1E2A38;
  --altus-slate:  #4A6572;
  --altus-stone:  #8A7961;
  --altus-taupe:  #BDB6AD;
  --altus-sage:   #556B5C;
  --altus-sand:   #EDE4D3;

  /* Rules */
  --altus-gold-max-share: 5%;   /* documentation only — enforced in §1.7 */
  --altus-radius: 6px;
  --altus-dur: .18s;
  --altus-ease: cubic-bezier(.2,.7,.3,1);
}
```

Then in `academy.css` (lines 15–37) replace the violet/white block with aliases:

```css
:root {
  --ha-ink:        var(--altus-navy);
  --ha-bg:         var(--altus-ivory);
  --ha-surface:    #ffffff;
  --ha-accent:     var(--altus-gold);   /* accent only, never fill */
  --ha-text:       var(--altus-charcoal);
  --ha-divider:    var(--altus-gray);
  --ha-digital:    var(--altus-teal);
}
```

> **Note on `--ha-accent`.** The current stylesheet uses `--ha-accent` as a
> **button fill** ([academy.css:22](assets/academy/academy.css#L22)). Aliasing it
> straight to gold therefore violates the "never as fill, max 5%" rule the same
> token layer is meant to enforce. Primary buttons should fill with
> `--altus-navy` and take gold only as a border or hover rule. Audit every
> `background` that resolves to `--ha-accent` during this step.

**Acceptance:** `grep -n "754FFE" assets/` returns nothing; every page renders in
navy/ivory/gold; no element has a gold `background-color`.

### 1.2 Typography

> **Status: delivered.** [`.lab/fetch_fonts.py`](.lab/fetch_fonts.py) downloads
> the three families and generates
> [`assets/academy/altus-fonts.css`](assets/academy/altus-fonts.css) — 19 faces,
> one `@font-face` per unicode subset, all SIL OFL 1.1 with the licence recorded
> in the generated header. No request now leaves the server for a typeface.
> DIN Next Arabic is deliberately absent: commercial face, no webfont licence on
> file, and Cairo is the guide's own Arabic body typeface.
>
> **The guide's hierarchy law is now measured, not asserted.** Its print sizes
> (52/36/24/21pt) are unusable as CSS, so the *ratio* carries over. Verified in
> the browser at 1440px, both scripts:
>
> | | body | H1 | H2 |
> |---|---|---|---|
> | English | 17px Montserrat | 42.50px Playfair — **2.500×** | 29.75px Playfair — **1.750×** |
> | Arabic | 17px Cairo | 42.50px Cairo — **2.500×** | 29.75px Cairo — **1.750×** |
>
> **Two things this section did not anticipate:**
>
> 1. **Raising the body to 17px broke six component headings.** They had been
>    hand-sized against a 16px body; at the new body size `.ha-card h3` became
>    1.02× body and `.ha-aside h3` and `.ha-sop-demo__side h3` became *smaller
>    than the paragraphs beneath them*. All eight are now expressed against a
>    named component scale (`--fs-lead` / `--fs-sub` / `--fs-sub-sm`), and
>    `.lab/design_check.py` fails any heading that does not out-rank body text.
> 2. **Preload has to be locale-aware.** The two scripts share no font files, so
>    a single preload list makes every Arabic page fetch a Playfair face it
>    never paints. `layout.php` now preloads Montserrat + Playfair on `/en` and
>    Cairo on `/ar`.
>
> Per-page cost: **~156 KB** on an English page (5 faces), **~93 KB** on an
> Arabic one. Cairo is also fetched on the English homepage — that is correct,
> not waste: the page carries a genuine bilingual comparison block.
>
> **Deployment note:** `assets/academy/fonts/` (19 files, 644 KB) must ship with
> the code. Re-runnable with `python .lab/fetch_fonts.py`, which skips files
> already present.
>
> **One unavoidable deviation, for the guide to rule on (see B4).** Phase III
> says no font outside the approved four may appear, "no exceptions". On the web
> that cannot be literally true: `font-display: swap` paints a fallback face for
> the few hundred milliseconds before the webfont arrives, and a blocked or
> failed font request falls back permanently. The stacks name Georgia then
> Times New Roman behind Playfair, and system-ui behind Montserrat and Cairo.
> The alternative is `font-display: block`, which trades that flash for invisible
> text and a worse LCP. Recommend keeping `swap` and writing the fallback stacks
> into the guide as approved, rather than leaving the rule stating something the
> medium cannot honour.

Self-host. Playfair Display + Montserrat (Latin) and Cairo (Arabic). Drop Inter
and IBM Plex. DIN Next Arabic is licensed — **verify web-use rights before
serving it**; default to Cairo.

```
assets/academy/fonts/
  playfair-display-{700}.woff2
  montserrat-{400,600,700,italic}.woff2
  cairo-{400,600,700}.woff2
```

Web scale derived from the guide's ratio law (H1 = 2.5× body, H2 = 1.75× body):

```css
:root {
  --fs-h1: clamp(2.25rem, 5vw, 3.25rem);
  --fs-h2: clamp(1.75rem, 3.5vw, 2.25rem);
  --fs-h3: 1.5rem;
  --fs-body: 1.0625rem;
  --fs-caption: .875rem;
  --fs-phase: .75rem;      /* + letter-spacing: .12em */
  --lh-latin: 1.6;
  --lh-arabic: 1.85;
  --font-display: "Playfair Display", serif;
  --font-body: "Montserrat", system-ui, sans-serif;
  --font-arabic: "Cairo", "Noto Naskh Arabic", serif;
}

html[lang="ar"] {
  font-family: var(--font-arabic);
  line-height: var(--lh-arabic);
}
```

Arabic has no italic form (B7). Rather than a universal `font-style: normal
!important` — which would also flatten `<em>`, `<cite>` and `<address>` and
leave no emphasis mechanism at all — scope the reset and give Arabic a real
substitute:

```css
html[lang="ar"] :is(em, i, cite, dfn, .caption) {
  font-style: normal;
  font-weight: 600;          /* weight carries emphasis instead of slant */
  color: var(--altus-slate);
}
```

Preload the two above-the-fold faces, `font-display: swap`, subset to Arabic +
Latin Ext.

**Acceptance:** Lighthouse LCP unchanged or better after the font swap; no FOIT
on 3G; no synthesised oblique anywhere in the Arabic locale.

### 1.3 RTL / Arabic correctness

- `<html lang="ar" dir="rtl">` already set in layout — verify.
- Icons/arrows mirrored via `[dir="rtl"] .icon--next { transform: scaleX(-1); }`.
- Charts: axis direction flips, data order does not.
- Mixed runs (RevPAR, GOPPAR, brand names) wrapped in `<span dir="ltr" class="ltr-inline">`.
- Numerals: keep Western Arabic numerals in Arabic for hotel-industry consistency (already the codebase's position — confirm with the client).

### 1.3a Logo rollout

> **Status: delivered.** The source `logo.png` is processed by
> [`.lab/build_logo.py`](.lab/build_logo.py) into the four files the settings
> table already named, plus a touch icon. Verified on 9 page types x 2 locales
> x 2 widths: header and footer logo load everywhere, none distorted, favicon
> present on both frontends.
>
> **The source needed work before it could be used.** It ships as a square PNG
> on a solid near-white plate with **zero transparent pixels** — on the ivory
> header that paints a grey box, on the navy footer a white one. The plate is
> keyed out on brightness with a soft ramp (the border ring measures 241-255
> per channel while the body sits near 240, so keying on one corner colour left
> a grey haze over everything).
>
> **`logo-light.png` did not exist at all.** The setting named it, the file was
> absent, so the footer had been falling back to a generic SVG house icon.
>
> **The reversed variant is derived and needs sign-off.** Phase VII says never
> recolour the logo, and Phase VII also says every brand decision needs the
> founding partners. Both cannot hold here: the wordmark is Obsidian Navy and
> the footer is Obsidian Navy, so as drawn it is invisible. The most
> conservative option available was taken — the navy wordmark knocked to
> Executive Ivory, Executive Gold untouched because it already measures 7:1 on
> navy, and the palm's green lifted only as far as AA requires (as drawn it is
> #004830, which measures **1.2:1** on navy and simply disappears). No colour
> outside the approved palette was introduced. **If a proper reversed lockup
> exists, drop it in as `logo-light.png` and the script is unnecessary.**
>
> **Sizing.** The mark is a stacked lockup, near square, with a micro-tagline.
> At the theme's 38px "ALTUS" rendered about three pixels tall. The header is
> now 86px with the logo at 66px, which is what the wordmark needs. The shipped
> LMS theme forced its footer logo into a hard `180x40` box as HTML attributes
> — a 4.5:1 frame for a 1.12:1 mark, which is the stretching Phase VII forbids
> outright.
>
> **The shipped LMS theme was still violet on every page.** §1.1 rebranded
> `academy.css`; the LMS theme has its own stylesheets, and `/home`, `/login`,
> `/courses`, `/sign_up`, `/blog` and `/home/my_courses` were all still serving
> the old accent. Fixed **without editing vendor code**:
> [`.lab/build_legacy_brand.py`](.lab/build_legacy_brand.py) reads the theme's
> own stylesheets, finds every rule painting the violet, and re-emits those
> exact selectors with the brand colour into `assets/academy/altus-legacy.css`,
> loaded after the theme. It cannot drift from what it overrides because it is
> generated from it, and deleting the file restores the shipped theme exactly.
> **Re-run it after any theme update** — `update/` exists to replace those
> files, which is why editing them directly would have silently reverted.
>
> 125 rules overridden. **Violet elements across the legacy pages: 0**, down
> from 7. Three stylesheets had to be read, not one: the accent is also
> delivered through custom properties (`--color-4`, `--color-12`, `--color-15`)
> in a **minified** bundle, which is why the first pass left the sign-up
> button, the pagination chip and the checkboxes still violet.
>
> **No favicon link was being emitted on the academy pages at all** — flagged in
> §3.7 as a Google/Yandex requirement and never actually checked. Now read from
> the administrator's `favicon` setting, like the logos.
>
> **Still open — the name does not match the mark.** `system_title` is
> "Hospitality Academy" and `Ha_seo::BRAND_EN` is the same, so the footer reads
> "© 2026 Hospitality Academy" under an ALTUS ADVISORY logo, and the logo's alt
> text says "Hospitality Academy". That is **D1**, and renaming touches all 211
> metadata titles, so it is not done unilaterally. To switch: change
> `system_title` in the admin panel (alt text, footer, header follow it) and the
> `BRAND_EN`/`BRAND_AR` constants in `Ha_seo.php` (page titles and schema).

### 1.3b Header and footer redesign

> **Status: delivered.** Direction: **"Hairline"** -- the brand's restraint made
> structural. An obsidian plate, an ivory field, and a single Executive Gold
> rule as the connective tissue. The guide permits gold as line and border only,
> so the hairline is not decoration applied to the chrome, it *is* the chrome's
> identity and the whole ornament budget. Owned by
> [`assets/academy/altus-chrome.css`](assets/academy/altus-chrome.css) so it is
> reviewable and separable from the page theme.
>
> **Header.** Utility rail (note, verify, contact, gold-outlined language pill)
> over a two-tier masthead: brand row, then navigation. One authored motion
> moment -- past 56px the rail and nav row retract, the logo steps 64->46px and
> a navy-tinted shadow lifts the bar. Everything else holds still; an advisory
> firm that animates its furniture looks like it is selling software.
>
> **The navigation needed a real solution, not a tuned one.** Eleven
> admin-managed items need 1273px in a 1160px row. Shrinking the type to fit
> today's menu breaks on tomorrow's, so the row measures itself after the fonts
> resolve and moves whatever overflows into a **More** disclosure. Without
> JavaScript the list simply wraps and every link stays reachable.
>
> **Footer.** The previous one put fourteen links in a single undifferentiated
> row -- an inventory, not wayfinding. Now: a lead band (mark, a Playfair
> statement, a gold-outlined CTA), four grouped columns with real headings, and
> a legal rail. The palm from the actual mark is bled off the trailing edge at
> 4% opacity as texture -- the brand asset, not a geometric stand-in.
>
> **Arabic ranks by size, because its Latin devices are unavailable.** The
> footer column heads are 13px uppercase tracked gold in English. Arabic has no
> uppercase, and letter-spacing breaks cursive joins, so in Arabic that label
> carries no hierarchy at all -- it is just smaller gold text. Arabic takes the
> scale's step above body instead. `.lab/design_check.py` caught this; it was
> not visible by eye.
>
> **Three defects the inspection round found, all fixed:**
>
> 1. **The mobile close button did nothing.** `.ha-chrome` is `position: sticky`
>    with a z-index, which makes it a **stacking context** -- so the panel's
>    `z-index: 200` is scoped inside it and the scrim at 150 covered the panel
>    and its close button. The scrim now sits below the chrome.
> 2. **The close button was bound to nothing**, because it reused the burger's
>    hook and the script read only the first match. It has its own hook now.
>    All four close paths verified: button, scrim, Escape, resize.
> 3. **75px of horizontal overflow on the search page at 390px** -- the open
>    field, the join CTA and the menu button do not fit one row. The CTA yields
>    to the field and returns when it closes.
>
> Also: the collapsed search field measured 2x44 to an auditor and a thumb. It
> is now `visibility: hidden` when closed -- out of hit-testing and out of the
> accessibility tree, not merely small.
>
> **Everything a visitor reads here is now the administrator's.** The rail note,
> footer statement, address, email, phone and four social links move to
> `frontend_settings` with a new **Header and Footer** tab on the existing
> settings screen. `ha_chrome()` supplies the shipped wording when a key is
> empty, so the site renders correctly on a database that has never seen the
> screen and a cleared field returns the default rather than a blank footer.
> Social links stay hidden until a URL is entered -- a placeholder contact on a
> live site is worse than none, which is defect C2 in this document.
>
> **One measured cost, reported not glossed.** Throttled-mobile LCP moved from
> ~2.1-2.7s to ~2.7-3.4s, reproducible across three runs; desktop is unaffected
> (440-640ms). The LCP element is unchanged (the hero image). The extra
> stylesheet is not the cause -- it completes 600ms before `academy.css`, which
> is the long pole. The lever is merging the three small stylesheets into one
> request, which trades the separation this file was built for. Not taken
> without that being a deliberate call.
>
> The desktop CLS of 0.026-0.037 on a cold cache is **not** from this work: it
> is the Arabic font swap, flagged when Cairo AR landed, and a warm run measures
> 0.0005. Under the 0.1 budget either way.

### 1.4 Per-tenant theming (F3)

New table `ha_property_theme`:

```sql
CREATE TABLE ha_property_theme (
  id INT PRIMARY KEY AUTO_INCREMENT,
  property_id INT NOT NULL,
  logo_media_id INT NULL,
  primary_hex CHAR(7) NOT NULL,
  accent_hex  CHAR(7) NOT NULL,
  font_latin  VARCHAR(64) DEFAULT 'Montserrat',
  font_arabic VARCHAR(64) DEFAULT 'Cairo',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (property_id)
);
```

Layout emits a scoped override block **after** `altus-tokens.css`:

```php
<style>:root{
  --altus-navy: <?= e($theme['primary_hex']) ?>;
  --altus-gold: <?= e($theme['accent_hex']) ?>;
}</style>
```

Validate `primary_hex` / `accent_hex` against `/^#[0-9A-Fa-f]{6}$/` **on write**,
not only on output — the values land inside a `<style>` block, where escaping
alone is not a sufficient boundary.

Altus is the default theme row; Dyafa is a tenant row. Adding a client property
= one INSERT, not a deploy.

### 1.5 Images

**Pipeline (build once, run on every upload):**

1. Accept source → strip EXIF → generate WebP + AVIF at 3 widths (640/1280/1920).
2. Persist attribution in `ha_media` (already exists): author, licence, licence URL, source page.
3. Emit `<picture>` with AVIF → WebP → JPEG fallback.
4. `width` + `height` always set (kills CLS).

```html
<picture>
  <source type="image/avif" srcset="/img/hero-640.avif 640w, /img/hero-1280.avif 1280w, /img/hero-1920.avif 1920w" sizes="(max-width:768px) 100vw, 60vw">
  <source type="image/webp" srcset="/img/hero-640.webp 640w, /img/hero-1280.webp 1280w, /img/hero-1920.webp 1920w" sizes="(max-width:768px) 100vw, 60vw">
  <img src="/img/hero-1280.jpg" width="1280" height="720" loading="lazy" decoding="async"
       alt="Front-desk team at a branded Riyadh property running the Altus HK&P learner dashboard">
</picture>
```

> The existing pipeline emits WebP at two widths and already sets intrinsic
> dimensions ([`ha_media_helper.php`](application/helpers/ha_media_helper.php)).
> AVIF and the third width are additive; do not rebuild what works.

**Alt-text rule:** descriptive, entity-named, ≤ 125 chars, no keyword stuffing.
Bad: `hotel training Riyadh`. Good: `Altus HK&P competency dashboard showing
front-office certification progress at a Riyadh property`.

**Image sitemap** — add `<image:image>` blocks with `<image:license>` pointing at
the stored licence URL. This is a genuine differentiator: 36 properly licensed,
credited images with machine-readable provenance. Google and Yandex both consume
it.

```xml
<url>
  <loc>https://altusadvisory.com/en/engagements/riyadh-portfolio</loc>
  <image:image>
    <image:loc>https://altusadvisory.com/img/riyadh-portfolio-1280.webp</image:loc>
    <image:title>Riyadh branded portfolio — owner's representative mandate</image:title>
    <image:caption>Four branded assets under owner's-representative mandate, KAFD district.</image:caption>
    <image:license>https://creativecommons.org/licenses/by/4.0/</image:license>
  </image:image>
</url>
```

**Acceptance:** every `<img>` has alt + dimensions + is inside a `<picture>`;
image sitemap validates in Google Search Console and Yandex Webmaster.

### 1.6 Accessibility

- Gold `#C89D4F` on Ivory `#F7F6F2` ≈ **2.3:1** — **never for text**. Enforce in CSS comment and in the design check.
- Contrast measured and recorded for every approved pairing in the brand guide (B5).
- `prefers-reduced-motion` already implemented — test on a real device.
- Tap targets ≥ 44 × 44 px — test on iOS Safari and Android Chrome.
- Focus rings visible on navy background (gold ring, 2px, 2px offset). Gold-on-navy ≈ **7:1**, which clears AA for body text and AAA for large text — gold is safe on navy and unsafe on ivory, and that asymmetry is the rule worth writing into the brand guide.

### 1.7 Gold-usage guard (F4)

> **Status: delivered**, and proven to fire. The guard enforces three rules, not
> one: gold is never a background behind text (a hard failure, since gold+white
> is 2.7:1); gold never exceeds 5% of a page; and gold must actually appear
> somewhere site-wide, because a ceiling-only rule is satisfied by deleting the
> accent. It measures twice — from the DOM, which names the offending element,
> and from a full-page screenshot, which also sees gradients, SVG and imagery a
> computed-style walk cannot.
>
> `.lab/design_check_selftest.py` injects each violation into a live page and
> asserts the guard reports it, then asserts the page returns clean. All three
> cases caught. A check only ever observed to pass is not evidence that it
> measures anything.

Add to `.lab/design_check.py`, which already sweeps 21 pages × 2 widths:

```python
# Fails when gold is used as a background fill or exceeds 5% pixel share
GOLD = (0xC8, 0x9D, 0x4F)
MAX_SHARE = 0.05

def check_gold_share(png):
    total = png.width * png.height
    hits = sum(1 for px in png.pixels() if close(px, GOLD, tol=12))
    share = hits / total
    assert share <= MAX_SHARE, f"gold share {share:.1%} exceeds 5%"
```

**Acceptance:** `python .lab/design_check.py` passes at both widths in both
locales.

---

## PART 2 — BACKEND

### 2.1 Ten-domain taxonomy (align code to print)

> **Status: delivered, with one domain held and two added.** The catalogue went
> from 9 categories to **11**, all 74 courses reassigned, 0 orphaned, both
> locales, seed idempotent, bridge idempotent.
>
> | # | Published domain | code | courses |
> |---|---|---|---|
> | 1 | Hotel Fundamentals | — | **held, see below** |
> | 2 | Front Office | `front-office` | 13 |
> | 3 | Housekeeping | `housekeeping` | 12 |
> | 4 | Food & Beverage | `food-and-beverage` | 10 |
> | 5 | Kitchen | `kitchen` | 10 |
> | 6 | Sales & Marketing | `sales-and-marketing` | 3 |
> | 7 | Revenue & Reservations | `revenue-and-reservations` | 6 |
> | 8 | Guest Experience | `guest-experience` | 2 |
> | 9 | Quality & Audit | `quality-and-audit` | 2 |
> | 10 | Security & Safety | `security-and-safety` | 3 |
> | + | Engineering | `engineering` | 7 |
> | + | Hotel Management | `management` | 6 |
>
> **The published ten cannot hold the built catalogue, in both directions.**
> This section assumed option (a) — restructure the catalogue to the print
> taxonomy — was simply a data job. It is not:
>
> - **Hotel Fundamentals has no course written for it.** Nothing in the 74 is a
>   cross-cutting induction; every "fundamentals" course is a *departmental*
>   one (`fo-fundamentals`, `hk-fundamentals`, `eng-fundamentals`). Creating the
>   domain would publish an empty filter, and populating it means authoring
>   courses in **both** languages — which this repository's own rule says must
>   be human-authored. **Not invented.** It is the one published domain the
>   product does not have, and it is also the most commercially valuable, since
>   it is what every new hire takes on day one.
> - **Engineering (7 courses) and Hotel Management (6) appear nowhere in the
>   published ten.** That is 13 courses with real content and no home in the
>   profile's taxonomy. They are kept and honestly labelled. **This is a gap in
>   the profile, not in the catalogue** — the printed ten should become twelve.
>
> So the choice in §4.2 was never (a) *or* (b). It is both: the catalogue moved
> to the published names where content existed, and the profile now needs to
> acknowledge two domains it omitted and one it claims without backing.
>
> **Three defects found and fixed while doing it**, each the same shape — a
> write path that was idempotent on insert but blind to deletion:
>
> 1. **The seeder stranded retired categories.** It upserts on `code`, so a
>    rename created the new row and left the old one live in the course filter,
>    empty. It now retires undeclared categories last, after every reassignment,
>    and *refuses* rather than deleting one that still holds courses.
> 2. **The bridge stranded them in the legacy LMS too** — 4 orphaned rows
>    (`ha:digital-hospitality`, `ha:safety-compliance` and their `sub-` pair)
>    still drawn as empty category tiles. `Ha_bridge::prune_categories()` now
>    removes mirrored categories whose academy counterpart is gone, touching
>    only rows carrying the `ha:` marker.
> 3. **Authored prose named a retired domain** in three places across both
>    locales — the About page body, the courses meta description and the
>    `/courses` hero lede. A public sweep of 12 URLs now reports zero references
>    to either retired name in either language.
>
> Photography needed no re-fetch: the library is keyed by *subject*, a separate
> namespace from category codes, so the new domains borrow the subject they
> were split from via `Ha_images::$fallbacks`. That map now carries image
> variants across too — without it a split domain had a rotation pool of one and
> every course in it showed the same photograph.
>
> Legacy `?category=` links still resolve: codes are query parameters, not
> paths, so `ha_redirect` cannot carry them and the sitemap never listed them.
> `Academy::$category_aliases` maps the two retired codes forward.

Restructure `ha_category` and `ha_category_translation` to the published ten:

| # | Domain slug | Action |
|---|---|---|
| 1 | `hotel-fundamentals` | **add** — cross-cutting induction |
| 2 | `front-office` | keep |
| 3 | `housekeeping` | keep |
| 4 | `food-and-beverage` | keep |
| 5 | `kitchen` | keep |
| 6 | `sales-and-marketing` | **split from** `digital-hospitality` |
| 7 | `revenue-and-reservations` | **split from** `digital-hospitality` |
| 8 | `guest-experience` | keep |
| 9 | `quality-and-audit` | **promote from** `management` |
| 10 | `security-and-safety` | rename from `safety-compliance` |

`engineering` is the tenth category in code and appears in none of the published
ten. Decide explicitly: fold it under `security-and-safety`, or keep it as an
eleventh domain and correct the profile. Do not let it survive unlabelled.

Then:
- Register a redirect in `ha_redirect` for every changed slug (the manager already counts hits).
- Re-run `ha_bridge sync` — must be idempotent.
- Update both `ha_category` and `ha_category_translation` in one transaction.
- Arabic slugs change too. Every AR URL is a real database-resolved slug, so each one needs its own redirect row.

### 2.2 Content-model naming

> **Status: held, deliberately — this needs a decision, and the plan understated
> its cost.** Renaming Category → Professional Domain is done (§2.1). Renaming
> **Course → Module** is the problem:
>
> **6 of the 25 tracked keywords — 24% — contain "course" or "دورات", and five
> of the six point at `/courses`:**
>
> | Keyword | Target |
> |---|---|
> | hotel management courses | `/courses` |
> | hospitality online courses saudi arabia | `/courses` |
> | hotel training courses in saudi arabia | `/hotels/training` |
> | دورات إدارة الفنادق | `/courses` |
> | دورات الضيافة | `/courses` |
> | دورات تدريبية للفنادق في السعودية | `/hotels/training` |
>
> "Course" is the noun buyers search for, in both languages. "Module" is not.
> The published model is better *internal* vocabulary — Track and Module read as
> professional development where Program and Course read as a MOOC — but the
> public catalogue is where the search demand lands.
>
> **Recommendation: split the difference.** Use the published vocabulary where
> it describes *structure* (a Track is made of Modules; a Professional Domain
> groups Tracks) and keep "Course" as the public noun for the thing a learner
> enrols in and Google indexes. That keeps the profile's model honest without
> spending a quarter of the keyword map on a rename. If the founders want the
> full rename regardless, it is a day's work plus redirects for 206 URLs — say
> so and it ships.

Published model is four levels; the built model is five. One level must merge,
and the merge is the whole decision:

| Built | Published | Action |
|---|---|---|
| Category | Professional Domain | rename in views + URLs |
| Program | Track | rename |
| Course | Module | rename |
| Section | — | **merge away** — surface as a grouping heading inside a Module, not as a level |
| Lesson | Lesson | keep |

Public vocabulary only — DB column names stay to avoid a migration cascade. Map
in a `Ha_content_model` presenter.

> Sections currently carry 296 rows and are what `ha_bridge sync` publishes into
> the legacy `section` table. Demoting them to headings is a **presentation**
> change; the bridge and the legacy LMS keep their five-level structure
> untouched.

### 2.3 Assessment, certificates, dashboards (Phase 3)

Schema exists: `ha_assessment`, `ha_assessment_attempt`, `ha_certificate`,
`ha_certificate_template`, `ha_certificate_verification`. Build:

1. **Assessment delivery** — read from `ha_assessment` / `ha_question`, not `Ha_quizbank.php`.
2. **Certificate PDF generation** — QR encoding the existing `/verify/{code}` URL (four outcomes already work).
3. **Role-based learner dashboard** — progress, next lesson, certificates.

**Real dependency:** the 296 machine-translated food-safety questions. **Budget
human Arabic authoring.** This is a content cost, not engineering. `ha_question`
requires `body_ar` on every row, so the migration out of `Ha_quizbank.php` is
blocked until that authoring is done — sequence the writer ahead of the sprint,
not alongside it.

**Acceptance:** learner completes a track → passes assessment → receives PDF →
third party verifies QR at `/verify/{code}`. Add as `ha_audit flows`
end-to-end, both locales.

### 2.4 Property management + white-label (Phase 4)

Track assignment, completion tracking, competency dashboards, per-property
theming (§1.4), reporting. **Charts use secondary palette only** — this is where
that rule first bites.

### 2.5 Governed AI assistant (Phase 5)

The profile is the spec: *"answers strictly from approved content"*. That is
retrieval-grounded with a hard refusal boundary. The governance **is** the
product.

1. **Corpus** = published `ha_lesson`, `ha_sop` (current approved version), `ha_course`. Already versioned, bilingual, tenant-scoped.
2. **Scope** = retrieval goes **through** `Ha_auth` tenancy. A Dyafa learner must never see another property's SOPs.
3. **Grounding** = every answer cites the lesson/SOP version + link. **No citation, no answer.**
4. **Refusal** = outside corpus → say so, offer nearest approved content.
5. **Bilingual** = answer in the asking language, from that language's authored content.
6. **Audit** = every Q&A writes to `Ha_audit` (actor, action, entity, before/after, IP, agent).

**Build order:** text search first (useful alone, and it *is* page 08's "smart
search") → embeddings → generation. Shipping search first de-risks the corpus
and is independently sellable.

**Acceptance:** out-of-corpus question refused with citation-backed alternative;
cross-tenant question returns nothing; every answer has a resolvable source
link; whole exchange in the audit log.

### 2.6 Operational engines (Phase 6, ongoing)

SOP acknowledgement + manager compliance view; checklist run; attendance;
notifications; `/api/v1/*`; queue jobs. `ha_video recheck` already designed to
run on schedule — **it needs the first cron job.** It exits non-zero when a
third-party video dies, which is the signal the cron should raise.

---

## PART 3 — SEO / GOOGLE / YANDEX

### 3.1 Structured data — the single biggest opportunity

> **Status: partly delivered, and two entries deliberately refused.**
>
> **Delivered:** the image sitemap (§3.3) carries `<image:license>` on 140 of
> 148 entries, drawn from the author / licence / licence-URL recorded at
> download. The remaining 8 are CC0, which has no licence URL to cite. Very few
> sites can emit this, because very few know where their pictures came from.
>
> **Refused — `Person` ×2.** The plan proposed marking up the founders. They do
> not appear on this site; it is the academy, not the advisory firm (that is
> D2). The instructors who *are* here are seeded demo accounts — `ha_profile`
> carries a job title and no name. Emitting `Person` with `jobTitle` and
> credentials for fabricated instructors is exactly the unevidenced claim §8
> and the repository's own reality rule forbid. **The founders' `Person` schema
> belongs on the advisory site, where the bios are real and already written.**
>
> **Refused — `VideoObject`.** `ha_video` has never been run: 0 lessons carry a
> video. There is nothing to describe, and describing it anyway would be
> marking up content that does not exist.
>
> **Still open and genuinely applicable here:** `EducationalOccupationalCredential`
> (blocked on Phase 3 certificates), `ItemList` on listings, and
> `ProfessionalService` / `Service` (advisory site, D2).

Currently emitted: Organization, WebSite, WebPage, Course, Article, FAQPage,
HowTo, CollectionPage, BreadcrumbList.

Add:

| Schema | Source | Why |
|---|---|---|
| `ProfessionalService` | Profile §02, §04, §05 | Firm not described as a service entity at all today |
| `Person` ×2 | Profile §16, §17 | Strongest E-E-A-T asset, already written in two languages |
| `Service` per offering | Profile §04, §05 | Ten named services, ready to mark up |
| `Organization` enrichment | H4 | `legalName`, `taxID`, `foundingDate`, `founder` ×2, `areaServed` (SA/GCC/MENA), `sameAs` → LinkedIn |
| `ImageObject` | `ha_media` (36 licensed images) | Attribution data exists, unused |
| `VideoObject` | `ha_video` | Video placed, credited, not marked up |
| `EducationalOccupationalCredential` | Certificates (Phase 3) | Makes certification machine-readable |
| `ItemList` | Catalogue listings | Better listing presentation |

**Yandex note:** Yandex reads Schema.org JSON-LD and microdata, but weights
visible page content more heavily. Ensure every structured claim also appears as
visible text. Yandex specifically rewards clear `<address>`, phone, email, and
legal-entity info on commercial sites — this dovetails with H4.

### 3.2 Metadata gaps in `render_head()`

> **Status: delivered — and `og:image` was a 404 on every page of the site.**
>
> The value is written by `ha_images`, which runs from the **CLI**. CLI has no
> `HTTP_HOST`, so `base_url()` resolved to `http://localhost/` and dropped the
> sub-directory entirely: every page advertised
> `http://localhost/uploads/academy/page-home.webp`, which does not exist. Every
> social share, and every AI assistant reading these pages, got a broken image.
> The same would break on any deployment where the seeder runs on a different
> host to the web server — which is every real deployment.
>
> Fixed at the root rather than patched: the path is stored **relative** and
> `Ha_seo::asset_url()` rebuilds the absolute URL from the request actually
> serving the page. An absolute URL pointing elsewhere is left alone, so an
> editor can still paste a CDN image into the admin field.
>
> Delivered alongside: `og:image:width` / `:height` / `:alt` and
> `twitter:image:alt`, all resolved from `ha_media` so none is guessed;
> per-page `og:type` (it was hardcoded `website`, telling every crawler an
> article was a home page); `article:published_time` / `modified_time` /
> `author`; `og:locale:alternate`; and `theme-color`.
>
> **`twitter:site` was deliberately not added** — there is no account to name,
> and inventing a handle is the kind of unevidenced claim §8 exists to prevent.
>
> `hreflang` now emits `en`, `en-SA`, `ar`, `ar-SA` and `x-default`. The
> regional variants are listed *alongside* the bare pair, not instead of it, so
> an Arabic reader in the UAE still matches `ar` rather than falling through to
> `x-default`.

### 3.2a The original note ([`Ha_seo.php:186–224`](application/libraries/Ha_seo.php#L186-L224))

Add:

- `og:image:width`, `og:image:height`, `og:image:alt`
- `article:published_time`, `article:modified_time`, `article:author`
- `twitter:site`, `twitter:creator`
- Fix `og:type` — hardcoded `website` on [line 202](application/libraries/Ha_seo.php#L202); must emit `article` for articles
- `<meta name="theme-color" content="#0D1B2A">`
- `hreflang="ar-SA"` and `en-SA` **alongside** the generic pair; keep `x-default`

**Yandex-specific head additions:**

```html
<meta name="yandex-verification" content="…">
<meta name="geo.region" content="SA">
<meta name="geo.placename" content="Riyadh">
```

Add Yandex.Metrica alongside GA4 (or replace if KSA-only — check with client).

### 3.3 Sitemaps

> **Status: delivered.** `/image-sitemap.xml` — 148 URLs across both locales,
> 140 carrying `<image:license>`. `/llms.txt` and `/llms-full.txt` are generated
> from the live catalogue, so they cannot drift from the site the way a
> hand-written summary would.
>
> **`lastmod` was already correct** — emitted per URL from real `updated_at`
> timestamps. That clears one of the VERIFY items in Part 7.
>
> One wording fix worth noting: `llms.txt` first reported "0 public standard
> operating procedures", which was *true* but misleading — the 14 SOPs are
> deliberately organisation-scoped and never publicly readable by URL. It now
> states the count and the scoping, because "14 procedures" and "14 readable
> procedures" are different claims and only one of them is true.

### 3.3a The original note

- **Image sitemap** (§1.5) — differentiate with `<image:license>`.
- **Video sitemap** for `ha_video`.
- **`lastmod`** — verify it emits real modification timestamps. This is the recrawl signal for both Google and Yandex.
- **PDF URLs** — add once published (§3.6).
- **Sitemap index** — not needed at 236 URLs; needed if programmatic pages arrive.

Submit the sitemap to **both** Google Search Console and Yandex Webmaster.

### 3.4 `robots.txt` + AI crawlers

> **Status: delivered — and the site had no working `robots.txt` at all.**
>
> `/robots.txt` resolved through the catch-all route and returned the **home
> page as HTML with a 200**. The academy's robots file existed only at
> `/academy-robots.txt`, which no crawler looks for. So for the life of the
> project the site was serving no crawl directives, and `/admin`, `/login` and
> `/api/` were never actually disallowed to anyone.
>
> **`Test_seo` passed throughout**, because it calls `Ha_seo::render_robots()`
> and asserts on the returned string — it never fetched the URL. The rendered-
> page audit checked `/academy-robots.txt` and only its status code, so an HTML
> page answering 200 would have satisfied it too. This is the exact shape of
> gap worth naming: *the function was correct and the site was broken.*
>
> Fixed at the route, and the class of bug closed at the check:
> `Ha_audit::audit_endpoint()` now asserts on the **body** — rejecting anything
> that begins `<!doctype html` and requiring expected content — across
> `/robots.txt`, `/llms.txt`, `/llms-full.txt`, `/sitemap.xml` and
> `/image-sitemap.xml`. Proven by pointing one check at an HTML page: it fails.
>
> **13 crawler groups, 52 disallow lines.** The plan's note about group
> inheritance was right and load-bearing: `robots.txt` groups do not inherit, so
> the four `Disallow` lines are repeated into every named group. Appending them
> once at the end would have left all twelve named agents — including every AI
> crawler — with the admin wide open. Generated from one agents array × one
> disallows array so the two cannot drift.
>
> **`/sitemap.xml` was serving the wrong site.** With `root_frontend` set to
> `academy` it still returned the legacy LMS sitemap — `/login`, `/sign_up`,
> `/blog` — as though that were the whole site. It is now a sitemap **index**
> over `academy-sitemap.xml`, `image-sitemap.xml` and `lms-sitemap.xml`, so one
> address can be submitted to both Search Console and Yandex Webmaster and the
> legacy sitemap keeps its own home.

### 3.4a The original note ([`Ha_seo.php`](application/libraries/Ha_seo.php))

Currently silent on every AI crawler — meaning no decision has been made.
Recommended:

```
User-agent: GPTBot
Allow: /
User-agent: OAI-SearchBot
Allow: /
User-agent: ClaudeBot
Allow: /
User-agent: PerplexityBot
Allow: /
User-agent: Google-Extended
Allow: /
User-agent: CCBot
Allow: /

# Yandex
User-agent: YandexBot
Allow: /
User-agent: YandexImages
Allow: /

# All
Disallow: /admin
Disallow: /academy-admin
Disallow: /login
Disallow: /api/
```

> `robots.txt` groups do not inherit. A named `User-agent` block ignores every
> rule outside it, so the four `Disallow` lines must be **repeated inside each
> named group**, not appended once at the end. Generate the file from a single
> array of agents × a single array of disallows so the two can never drift.

### 3.5 `llms.txt`

Add `/llms.txt` beside `render_robots()` in `Ha_seo`. Plain-text map of the firm,
two divisions, platform, frameworks, market data, founders, catalogue, SOP hub.
Add `/llms-full.txt` with principal content inlined.

### 3.6 Publishing the PDFs

1. **Fix the Arabic text layer first** (A1).
2. Rename to `altus-advisory-corporate-profile-2026-en.pdf` / `-ar.pdf` (M6).
3. Set XMP metadata: Title, Author, Subject, Keywords, Language (`en-SA` / `ar-SA`).
4. Landing page `/corporate-profile` in both locales, `hreflang` between them, PDF as download.
5. Add both to sitemap with `rel="alternate"` between languages.
6. Point the QR at a tracked URL (`?src=pdf-en`) — see C2.
7. Optional lead gate → writes to `ha_lead` (contact form is VERIFIED).

**A1 — Arabic text layer fix, in order:**

1. Confirm by attempting to select text in Acrobat.
2. If outlined, re-export with live text + embedded subset fonts.
3. Tag for reading order (Arabic RTL must be set explicitly).
4. Set document language to `ar-SA` in properties.
5. Re-run `pdftotext -layout` as acceptance test.
6. **Run the same tagging check on the English PDF** — it extracts, but reading order was not verified.

### 3.7 Yandex specifics recap

| Item | Action |
|---|---|
| Yandex Webmaster | Verify both domains; submit sitemaps |
| Yandex.Metrica | Install; goal on PDF download + contact submit |
| Region | Set to Riyadh / Saudi Arabia in Webmaster |
| Legal entity | Visible `<address>`, CR number, legal name (H4) — Yandex weights this for commercial sites |
| Clean HTML | Content must be in HTML, not JS-only — it already is, verify |
| Speed | Yandex penalizes slow sites heavily — Core Web Vitals pass is required, not optional |
| Turbo pages | Optional; consider for the insights section |
| Favicon | 32×32 + 180×180 apple-touch-icon; both engines require it |
| Open Graph | Already emitted; add image dimensions (§3.2) |

### 3.8 Keyword architecture

25 keywords tracked today — all training-side. Add the advisory clusters, EN + AR:

| Cluster | Examples |
|---|---|
| Owner representation | hotel owner's representative Saudi Arabia, HMA negotiation, operator selection, ممثل المالك الفندقي |
| Feasibility | hotel feasibility study KSA, hospitality ROI modelling, دراسة جدوى فندقية |
| Pre-opening | hotel pre-opening consultant, critical path, ramp-up |
| Commercial | GOPPAR improvement, RevPAR optimisation, OTA commission reduction |
| Vision 2030 | Vision 2030 hospitality, 150 million visitors |
| Giga-projects | NEOM hospitality, Red Sea, Diriyah, AlUla, Qiddiya |
| Platform | hotel LMS Arabic, نظام تدريب فندقي, hotel SOP software |

**Arabic is the under-competed advantage.** The site already has authored Arabic
and real Arabic URLs; most competitors have neither.

---

## PART 4 — CONTENT

### 4.1 Two properties

| Property | Audience | Content |
|---|---|---|
| **altusadvisory.com** | Owners, investors, developers, family offices, government | 22 profile sections as pages, insight articles, frameworks, founder pages |
| **The HK&P platform** | GMs, HR/L&D, independent owners | Catalogue, SOP hub, city pages, training articles |

Cross-link deliberately. **The current site serves only the second** — the entire
advisory demand space has no web presence.

### 4.2 The profile is 22 pages of ready content

| Section | Becomes | Why |
|---|---|---|
| 09 · Altus Ascent™ | `/methodology` | Named, linkable, citable |
| 10 · Frameworks | `/frameworks/performance-matrix`, `/frameworks/goppar-value-stack` | Two named IP assets; GOPPAR under-served |
| 13 · Market | `/insights/saudi-hospitality-market-2026` | **Highest traffic potential in the document** |
| 12 · Vision 2030 | `/vision-2030` | High intent, nationally salient |
| 16, 17 · Founders | `/leadership/islam-mahrous`, `/leadership/hossam-smadi` | E-E-A-T — 30+ yrs, named brands, awards, Six Sigma Black Belt |
| 14, 15 · Engagements | `/engagements/*` | Proof, once C1 resolved |
| 06, 07 · Platform | Product site | Bridge between properties |

### 4.3 Articles the profile makes you uniquely qualified to write

1. **"What an owner's representative actually does"** — page 06's HMA, procurement and handover detail, plus the four KAFD assets. Almost nobody writes this from real operator experience.
2. **"Reading the GOPPAR Value Stack"** — the page 12 framework, expanded.
3. **"Saudi hotel supply to 2030: the key math"** — page 15 with H1 resolved, updated annually.
4. **"Pre-opening critical path: what slips and why"** — page 17, plus five pre-opening projects and 1,700+ rooms from page 18.
5. **"Consultant reports vs institutionalised knowledge"** — page 09's comparison table, already written.
6. **"Six Sigma in hotel operations: one DMAIC worth USD 340K"** — page 18. Specific, credible, rare.

Every one in Arabic as authored content, to the standard the codebase already
holds itself to.

### 4.4 Claims governance (`CLAIMS.md`)

| Field | Example |
|---|---|
| Claim | "362K hotel keys by 2030" |
| Tier | Verified |
| Source | Knight Frank, KSA Hospitality Market Review 2025 |
| Date checked | 2026-01 |
| Owner | who re-checks |
| Review due | annually |

Three tiers everywhere: **Verified** (sourced, dated) · **Modelled** (labelled
projection, basis stated) · **Removed** (cannot be evidenced — e.g. page 09's
"100% turnover immunity").

Enter immediately: page 09 platform metrics (C3), page 15 supply gap (H1),
case-study attributions (C1).

This resolves a live conflict: `README.md` forbids publishing unevidenced
statistics; the profile publishes them. The README's rule is the right one — the
profile comes up to it, not the other way round.

---

## PART 5 — ACCEPTANCE / "ERROR FREE" DEFINITION

> **Status: the gate has now actually been run.** It was defined here and never
> executed. Two new runners: [`.lab/a11y_check.py`](.lab/a11y_check.py) and
> [`.lab/perf_check.py`](.lab/perf_check.py).
>
> **Accessibility — 118 real problems found, all fixed, now 0.** axe-core
> WCAG 2.1 AA across 19 templates x 2 widths, both locales.
>
> - **4x `color-contrast` (serious).** `--ha-ink-faint` on the sand callout
>   measures **4.32:1**, under the 4.5 AA floor. A consequence of my own palette
>   change in §1.1 -- sand is lighter than the surface it replaced. Moved to
>   `--ha-ink-soft`, 4.90:1. ink-faint is only safe on ivory and white.
> - **114 tap-target failures, and then a correction to my own check.** My first
>   rule was a flat 44px, which flagged every footer and breadcrumb link.
>   **WCAG 2.1's 44px rule is AAA**; WCAG 2.2 AA asks 24x24 *with a spacing
>   exception*, and inline links in prose are exempt in both. The check now
>   encodes that: controls are held to the 44px project bar (plan 43), other
>   links to the 2.2 AA rule they actually have to meet. Fixed the utility bar,
>   brand link, search field and button, and language pill -- with a floor on
>   **both axes**, because Arabic "تواصل" is 35px wide where English "Contact"
>   is 54px.
> - **`prefers-reduced-motion` passes.** Carried as IMPLEMENTED-but-untested
>   since the start; now emulated and asserted. Nothing animates.
>
> **Security headers are live** -- `X-Content-Type-Options`, `X-Frame-Options`,
> `Referrer-Policy` all served; HSTS correctly commented out until HTTPS.
> **No CSP** -- the remaining gap, needing a decision on inline styles (the hero
> sets `background-image` inline).
>
> **Core Web Vitals -- measured, with one fix, one win and one honest limit.**
>
> | | desktop LCP | phone LCP (slow 4G, 4x CPU) | CLS |
> |---|---|---|---|
> | home | 712ms | 3300ms | 0.000 |
> | courses | 432ms | 1984ms | 0.000 |
> | course | 520ms | 2188ms | 0.000 |
> | home (AR) | 536ms | 2728ms | 0.000 |
>
> - **CLS was 0.191 on a course page and is now 0.000.** `font-display: swap`
>   reflowed the page when each webfont replaced the system font: Montserrat
>   measures **12% wider** than Arial, Playfair **12% narrower** than Georgia.
>   Metric-matched fallbacks (`size-adjust`, measured in the browser rather than
>   copied from a table) make the swap invisible. **This is exactly the risk
>   §1.2 flagged when three font families were added, and it was real.**
> - **Removing the font preloads was worth ~850ms.** Once the swap costs nothing
>   visually, an early font is pure competition for the LCP image on a slow
>   link. The hero image is preloaded instead -- it is a CSS `background-image`,
>   the latest a browser can possibly discover an image, and the measured LCP
>   element on every page that has one.
> - **Open: both home pages remain over 2.5s on the harshest mobile profile**
>   (3300ms EN, 2728ms AR; every other page passes). The driver is identified:
>   the EN home pulls **227KB of fonts across 7 files**, because the bilingual
>   comparison block puts Cairo on an English page. The next lever is a Cairo
>   face scoped to the Arabic unicode range so Latin text in that block falls
>   back to Montserrat. Not done -- recorded rather than glossed.
> - **TBT is reported but not enforced, and the reason is in the runner.**
>   Blocking on it would be measuring the harness: TBT tracks the CPU throttle
>   almost linearly (1x 233ms, 2x 509ms, 4x 1531ms), every long task is
>   attributed to `unknown:window` rather than to any script, and blocking our
>   1.3KB of JavaScript entirely moved it by 344ms out of 1554ms on a page of
>   553 DOM nodes. A trustworthy TBT/INP figure needs Lighthouse on an idle
>   machine or CrUX field data.

Every phase already has a named test above. The full gate before release:

| Check | Tool | Pass condition |
|---|---|---|
| Design compliance | `.lab/design_check.py` | Pass at 2 widths × 2 locales; gold ≤ 5%; every heading out-ranks body text |
| The guards themselves | `.lab/design_check_selftest.py` | Each violation injected is caught; the endorsed phase-label pattern is not |
| Content tests | `ha_test run content` | All pass |
| Full suite | `ha_test run` | 83+ tests, 0 failures |
| End-to-end flows | `ha_audit flows` | Includes new certificate+QR flow, both locales |
| URL audit | `ha_audit run` | 0 problems across 236+ URLs |
| Bridge sync | `ha_bridge sync` | Idempotent (run twice, no diff) |
| Redirects | `ha_redirect` | Every changed slug has a row, EN and AR |
| Schema | Google Rich Results Test + Yandex Webmaster | All types validate; no errors |
| Sitemap | GSC + Yandex Webmaster | Submitted, no errors, `lastmod` real |
| Core Web Vitals | PageSpeed Insights + CrUX | LCP < 2.5s, CLS < 0.1, INP < 200ms |
| Accessibility | `.lab/a11y_check.py` (axe WCAG 2.1 AA + tap targets + reduced motion) | 0 critical/serious; 0 tap-target failures |
| Core Web Vitals | `.lab/perf_check.py` | LCP < 2.5s and CLS < 0.1 enforced; TBT advisory (see Part 5 note) |
| PDF extraction | `pdftotext -layout` | English and Arabic both extract cleanly |
| AI assistant | Manual test suite | Refuses out-of-corpus; cites sources; tenant-scoped; audit-logged |
| RTL | Manual | Icons mirrored, charts correct, no mixed-run breakage |

---

## PART 6 — EXECUTION ORDER

| Sprint | Focus | Output |
|---|---|---|
| **0** | D1–D6; fix C1/C2/C3; fix Arabic text layer; reconcile brand guide | Distributable profile, both languages |
| **1** | Brand tokens, typography, gold guard, accessibility re-verify | Brand-compliant site |
| **2** | Ten-domain taxonomy, content-model naming, redirects | Product matches print |
| **3** | `robots.txt` AI directives, `llms.txt`, Person/ProfessionalService/Service/ImageObject schema, `og:image` completion, image sitemap, Yandex Webmaster | AEO/GEO foundation |
| **4** | Advisory content property: 22 sections, founder pages, market page | Advisory demand space entered |
| **5–7** | Assessment delivery, certificate + QR, learner dashboard | Page 09 "proof of adoption" true |
| **8–10** | Property management, competency dashboards, white-label | Page 08 three experiences complete |
| **11–14** | Smart search → governed AI assistant | Differentiator real |
| **Ongoing** | Operational engines, insight articles, claims register | — |

**Ordering rationale:** sprints 0–4 make existing claims **true and visible**;
sprints 5–14 build what is **claimed but absent**. Nothing in 0–4 depends on
5–14, so marketing is not gated behind engineering.

---

## PART 7 — THINGS I CANNOT VERIFY (carry these forward honestly)

- **Arabic profile content** — text layer doesn't extract; everything said is about its technical state, not its content.
- **Visual layout defects** (H2, M4, B6) — inferred from extraction, not a reliable witness. Confirm in source.
- **DIN Next Arabic web licence** — assumed restrictive; confirm.
- **`lastmod` in sitemap** and **security headers in `.htaccess`** — flagged, not read in full.
- **Whether approved Altus logo artwork exists** — none found in this repo; brand guide implies it exists somewhere.

---

## APPENDIX A — Source-document defect catalogue

The IDs referenced throughout Parts 0–7. Severity is about distribution risk, not
effort.

### A.1 English corporate profile

**Critical — fix before any external distribution**

**C1 · The case-study framing contradicts the founder bios.**
Pages 16–17 open with *"Composite engagements, anonymised and clearly labelled as
illustrative."* Page 18 then states as fact: *"Mandated by Marriott International
to lead operational excellence across 19 hotels in Egypt (3,000+ rooms), lifting
guest satisfaction 10% and F&B revenue 8% within 18 months"* — same engagement,
same numbers, named client. Page 17's anonymised "Owner's Representative for a
Branded Riyadh Portfolio" is named outright on page 19: Crowne Plaza Digital
City, InterContinental, Hotel Indigo, Wyndham Grand at KAFD.
*Fix:* relabel pages 16–17 **"Principal-Led Engagements"**, keep client
anonymisation, add *"Delivered by our principals in prior executive roles, not as
Altus Advisory mandates."*

**C2 · Four unresolved placeholders on the back cover.**
`[Office address]`, `www.altusadvisory.com [placeholder]`,
`advisory@altusadvisory.com [placeholder]`,
`linkedin.com/company/altus-advisory [placeholder]`, plus a QR block with no
stated destination. The call to action cannot be acted on.

**C3 · Unsourced absolute claims on page 09.**
`>40%` faster onboarding, `100%` turnover immunity, `100s` of training hours
saved, `GOP ↑`. "100% turnover immunity" cannot be substantiated.
*Fix:* convert to mechanism claims or explicitly labelled models with the basis
stated.

**High — fix before the 2026 edition is final**

- **H1 · Page 15 supply chart does not reconcile.** 168K existing + 100K pipeline ≠ 362K by 2030. A ~94K gap with no label. Add the missing band or footnote the delta.
- **H2 · Text-frame overflow**, second case study on pages 16 and 17 — APPROACH paragraph runs into the RESULTS row. Same pattern on two pages. **VERIFY** in source.
- **H3 · No engagement model.** 22 sections and never a statement of how a client starts. *Fix:* add a 23rd section, **"How We Engage"** — engagement types, mandate arc mapped to Altus Ascent™, fee philosophy, one next step. Highest-value addition available.
- **H4 · No legal entity, CR number, or confidentiality statement.** Conspicuous for a fiduciary positioning; also blocks `Organization` schema with `legalName`/`taxID` (§3.1) and costs Yandex commercial-site signal (§3.7).
- **H5 · The platform is sold but never priced or packaged.** Page 14 says it was *"priced deliberately for independent hotels and SME establishments"* and never says how.

**Medium**

- **M1 ·** The page 09 comparison table is the best page in the document and is buried. Promote a condensed version forward.
- **M2 ·** No visual of the platform — two spreads selling software with no screenshot. Depends on §2.3 shipping.
- **M3 ·** Trademark marks inconsistent across `Altus Ascent™`, `The Altus Performance Matrix™`, `GOPPAR Value Stack™`. Establish status, apply one convention.
- **M4 ·** Page 21 (Our Values) labels and descriptions may be vertically offset. **VERIFY**.
- **M5 ·** No client reference. One named reference from a principal's prior engagement, with consent, outweighs the entire values page.
- **M6 ·** Filename contains "Preview" and a version integer; it becomes the URL when published.

### A.2 Brand Implementation Guide

- **B1 · Version drift.** Stamped `© 2024` / `Version 1.0`; file named `v2`; profile is `© 2026`.
- **B2 · Master quote differs from the profile.** See D5.
- **B3 · Two incompatible values sets.** Shared: Integrity, Excellence, Innovation. Guide adds Partnership, Accountability, Transformation, Human-Centric Leadership. Profile adds Hospitality, Performance, Trust, Collaboration.
- **B4 · No digital or web specification.** Phase VIII covers documents and decks only. Missing: web type scale (52/36/24/21pt are print sizes), breakpoints, dark mode, UI states, forms, on-screen tables, motion, favicon. **The most important addition to the guide** — Part 1 has to invent it otherwise.
- **B5 · Accessibility unaddressed.** Gold on Ivory ≈ 2.3:1, far below AA; gold on Navy ≈ 7:1, comfortably above it. *Fix:* approved/forbidden pairings with measured ratios, minimum body size, and an explicit statement that the palette is AA-compliant **only** in the listed pairings.
- **B6 · No logo files, clear-space diagram, or misuse examples.** Phase VII references clear space equal to the height of the "A" but shows no mark. **VERIFY**.
- **B7 · Arabic typography under-specified**, and one rule is wrong: Phase III specifies *"Arabic Captions — Cairo, italic variant where available"*. **Arabic has no italic form.** Also missing: heading weight scale, mixed Arabic/Latin run handling, Arabic line-height, RTL mirroring rules.
- **B8 · No naming convention for the platform.** Lowercase "altus" in the product name against "ALTUS ADVISORY" in caps. Needs a written rule or every CMS will auto-capitalise it.

### A.3 Arabic corporate profile

- **A1 · No usable text layer.** `pdftotext -layout` returns disordered, largely empty output for the Arabic document while the English extracts cleanly. Likely outlined text or a missing `ToUnicode` CMap. Consequences: not searchable, not copy-pasteable, unindexable, uncitable by AI, unreadable by screen readers. Fix procedure in §3.6.
- **A2 · Parity check required.** Its content could not be verified against the English section by section. Before release: 22 sections, same order, same numbers, same sources, same disclaimers, and the C1 decision applied identically in both languages.
- **A3 · Arabic-first, not translated.** `README.md` already takes this position for the site: *"English and Arabic are separate authored content, not a translation layer."* The profile should meet the same bar — cite Arabic-language sources (وزارة السياحة) directly and use official رؤية 2030 terminology rather than back-translated English.

### A.4 Code-side identifiers

- **F1 · Brand token layer** → §1.1
- **F2 · Typography layer** → §1.2
- **F3 · Per-tenant theming** → §1.4
- **F4 · Gold-usage guard** → §1.7

---

## APPENDIX B — How the sources were read

| Source | Scope | Method |
|---|---|---|
| `EN- Altus_Advisory_Corporate_Profile_2026_A4_Portrait_Preview_2.pdf` | 24 pages, 22 numbered sections | `pdftotext -layout`, read end to end |
| `AR- Altus_Advisory_Corporate_Profile_2026_A4_AR_1.pdf` | Arabic mirror of the same 22 sections | Extraction attempted; text layer does not extract (A1) |
| `ALTUS_ADVISORY_Brand_Implementation_Guide_v2.pdf` | 19 pages, Phases I–VIII | `pdftotext -layout`, read end to end |
| This repository | `application/`, `assets/academy/`, `README.md`, `IMPLEMENTATION_STATUS.md` | Controllers, SEO library, seeds, palette, status matrix |

### B.1 Why this plan exists

The corporate profile sells a software product — **altus Hospitality Knowledge &
Performance** (sections 06–07, pages 08–09) — as the firm's central
differentiator. It is on the cover ("1 Proprietary Platform"), it carries two
spreads, it closes the page-24 comparison, and it is named as the delivery
mechanism for Vision 2030 Pillar III.

**That product is this repository.** Three mismatches follow, and they are what
Parts 1–4 resolve:

1. **The product does not carry the brand.** `grep -ri "altus" application/ assets/` returns **zero matches**. The seeded organisation is `Dyafa Hospitality Group`. The palette is violet `#754FFE`; the guide mandates Obsidian Navy with Executive Gold. Fonts are Inter and IBM Plex Sans Arabic; the guide permits Playfair Display, Montserrat, DIN Next Arabic and Cairo **and no others**.
2. **The profile promises capabilities the code has not built.** Assessment delivery, certificate generation, reports and analytics, the learner dashboard and notifications are all **Not started** in `IMPLEMENTATION_STATUS.md`. There is no AI assistant of any kind.
3. **The profile publishes statistics the site's own rules forbid.** See §4.4.

### B.2 Promise-vs-built, page 08–09

| Promise | Built |
|---|---|
| Layer 1 · Product & Experience | ✅ 18 routes, 206 detail pages, both locales, VERIFIED |
| Layer 2 · Knowledge & Learning | ⚠️ Curriculum ✅ (74 courses, 666 lessons); learning engine, assessment delivery, certification ❌ |
| Layer 3 · Intelligence & Performance | ❌ Basic search only; **no AI assistant**; no live indicators |
| Layer 4 · Technical Operations | ⚠️ Schema ✅ 89 tables, RBAC ✅ 8 roles / 180 permissions VERIFIED; per-property customisation, admin, reporting ❌ |
| Layer 5 · Governance & Growth | ⚠️ SOP versioning ✅, audit log ✅ VERIFIED; mobile responsive, no app |
| Ten professional domains | ❌ Nine categories, and not the same nine — §2.1 |
| Domain → Track → Module → Lesson → Assessment | ⚠️ Five levels built against four published — §2.2 |
| Three experiences (Learner / Property Mgmt / Altus Team) | ❌ None started; legacy LMS learner area is the interim answer |
| "Assessment, certification, competency dashboards per employee" | ❌ **None of the three** |
| "Governed AI assistant answering strictly from approved content" | ❌ **Does not exist** |
