# PDF library and global language workflow

## Delivery status — 2 October 2026

The local application now has a manifest-bound, repeatable import and a review-gated release workflow. Migrations 20–24 are applied locally. Existing availability is preserved: 40 PDF courses remain published and the other 86 draft courses are unchanged. Enrollment, progress, assessment attempts and existing course/lesson identities passed the before/after preservation check.

This is an **implementation and review-workflow delivery, not a completed editorial or global translation release**. The supplied course candidates remain under review. No person has approved the page audit, all 644 questions or the full language surfaces through this workflow. No revised course-language version has passed publication.

| Work item | Accounted for | Remaining release work |
| --- | ---: | --- |
| Original PDFs | 41 files, 368 pages, 40 topics | Visual correction, instructional-point mapping and approval of every page |
| Duplicate source | 2 Interdepartmental Communication PDFs | One canonical review, both original source references retained |
| Curriculum | 120 modules, 161 lessons | Explicit lesson objectives, objective-to-question mapping and editorial review |
| Assessments | 644 questions | PDF-grounded review, explanations and page references; add questions for uncovered objectives |
| Video records | 48 records; 113 lessons without a record | Relevant selections for all lessons, editorial scoring, duration and playback verification |
| Language target | 7,020 living individual ISO 639-3 languages, plus existing route aliases | Production translation service or human imports, human review, interface QA and signed-media production |

Raw OCR covers 359 canonical pages; the duplicate adds nine accounted source pages. OCR text is unreviewed and contains Arabic recognition errors. It must not be used as corrected source text without visual comparison. The 160 signed-language indications use official reference names, with non-English names and International Sign cross-checked against the University of Hamburg's [signed-language catalogue](https://www.sign-lang.uni-hamburg.de/lrec/language/index.html). Modality and writing direction require human review before a language is enabled. The frozen language table records its retrieval date and checksum; it is not a claim that all those languages are supported by a provider.

The configured local AI provider is a disabled test mock. No production translation provider or YouTube Data API key is configured. All 48 existing YouTube sources returned attribution through oEmbed, but that does not establish embedding, duration or country availability. No platform popularity ratings were invented.

## What changed

- Migrations add global language inventory, immutable candidate revisions, stable identities, translation units and reviews, multiple localized video sources, retirement markers, signed-media checksums, surface QA and the active course revision.
- The library seed imports only manifest courses, stages candidates and preserves published content. Importing and publishing are separate. Unrelated draft courses are not hidden, changed or published.
- Existing translation tables and `ha_i18n_text` remain in use. A row adapter replaces automatic creation of legacy language columns. English fallback is readable but never counted as a completed translation.
- CLI commands export/import source, translation, video and QA packages; report exact page, lesson, question and translation-unit failures; and gate course publication and site-language activation.
- Publication requires reviewed source points, explicit lesson objectives with assessment references, current curriculum approval, complete course and site translations, current desktop/mobile QA, and a reviewed, recently verified video for every lesson. Active assessment attempts block content replacement.
- Original PDFs are attached with checksum-based filenames. Reviewed releases generate complete translated HTML source companions and add mapped PDF page references to lessons and quiz explanations. HTML companions are accessible documents; this implementation does not generate translated PDF layouts.
- Multiple native or alternative-language videos, owned files and authorized local captions are supported. Written lessons remain available when media fails. Country restrictions are independent of language selection.
- Only the current released course-language version receives discovery alternates and sitemap entries. Old versions remain stored; stale translations are not presented as the current completed release. Existing pre-workflow availability is grandfathered until a reviewed revision is published.
- Public course overview HTML and PDF-course hero readability were corrected. Public pages show source attribution and a translation warning when a course version is incomplete.
- Reviewed shared translations cover rendered text and attributes. A same-origin client script covers registered dynamic DOM text and dialogs, while preserving code, editable content and `translate="no"` elements. Dynamic strings absent from the registry still require implementation changes and QA; the static scan alone does not prove every interface word is translated.

## Local validation

The PHP suite passed **152 tests and 3,580 assertions**, including repeated staging, stable keys, grading, the 75% threshold, ten-attempt limit, sequential unlocking, translated option identities, retirement, atomic translation imports, stale source detection, QA expiry, media integrity, source references and release-version filtering.

The final signed-language classification checks also passed in the focused library suite: 16 tests and 97 assertions. Sequential coverage reports completed after collection was made atomic per scope.

The public browser check passed 12 desktop/mobile cases: English, Arabic and the existing Hindi fallback course routes, plus unavailable pending signed (`ase`), three-letter (`fil`) and script-specific (`sr-Latn`) routes. A separate DOM fixture checked reviewed static/dynamic text, attributes and dialogs and protected editable/code content. Pending-route checks do not constitute completed signed-language learner or assessment QA. Every newly released language still needs real public, learner, instructor and administrator QA.

Runbook: [deployment and rollback](library-deployment.md). Editorial instructions: [source, translation and video review](library-review.md). The deployment ZIP contains an explicit file inventory with SHA-256 checksums and review reports. It excludes database dumps and machine-specific credentials.

## Reference specifications

YouTube checks use `status.embeddable`, privacy, duration and `contentDetails.regionRestriction` from the [YouTube video resource](https://developers.google.com/youtube/v3/docs/videos). Search alternates follow [Google's localized-version guidance](https://developers.google.com/search/docs/specialty/international/localized-versions), including reciprocal URLs and supported language annotations. The frozen inventory comes from the [ISO 639-3 code table](https://iso639-3.sil.org/sites/iso639-3/files/downloads/iso-639-3.tab).
