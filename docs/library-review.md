# Editorial, translation and media review

## Command conventions

Run from the application root. On this Windows machine, pass the installed PHP executable to the wrapper:

```powershell
python tools/library_cli.py --php 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' review_export '@backups/library-review/source-review-template.json'
```

The `@path` convention encodes file paths for CodeIgniter URI parsing, including spaces, Unicode and Windows separators. Examples below assume `php` is on PATH. Export review files outside a public directory; quiz exports contain answers.

## Every file, page and learning objective

1. Preserve the originals. `manifest.json` records each checksum, page count, visual hash, topic association and duplicate relationship. Inspect all 41 references; the two visually identical Interdepartmental Communication files share one canonical page review. If a source changes, rebuild the manifest, inspect the change and re-review it. Do not edit a checksum to bypass validation.
2. Export source review scaffolding. Compare every page with the full-size original or rendered PNG. Correct extracted/OCR Arabic against the image, including headings, diagrams, labels and speaker-like text. Contact sheets are navigation aids; small text may require full-size inspection. To regenerate aids:

```sh
python tools/build_library_manifest.py --render
python tools/ocr_library_sources.py --models /path/to/tessdata --tesseract /path/to/tesseract
python tools/library_cli.py review_export @reviews/source.json
```

3. For each canonical page fill `corrected_text`, `source_locale`, `reviewer` and `visually_checked`. Set `has_instructional_content` explicitly for covers and closing pages too. Give those pages an `accounting_note`. A closing page with substantive instructions needs points and assessment references. Never count raw OCR as a visual approval.
4. Split every instructional point into a stable `id` and accurate `text`, mapping `lesson_keys` and relevant `question_keys` from the export. Missing points require curriculum changes; they must not be omitted to make a report pass. Every existing question and lesson also needs a reviewed source-point reference.
5. Edit only the manifest course candidates in `application/seeds/library/*.json`. Retain correct grouped modules. Add lessons/modules when needed. Keep all existing `source_key` values stable across reorderings; assign distinct course-prefixed keys to new items. Change a question key when its meaning or correct answer changes. Superseded questions/options/lessons are retired rather than deleting history.
6. Each lesson must contain an `objectives` array with explicit, assessable objective text and complete teaching content: explanations, procedures, clearly labelled additional examples and a summary. Update the corresponding translated content through the unit workflow. The old chapter title is a legacy objective fallback and does not satisfy revised publication readiness.
7. Review every question against the PDF. Keep at least four per lesson; add questions when an objective is uncovered. Keep numeric `answer` indices and option ordering as source identities. Each question needs a valid answer and an explanation. In the curriculum review, map every objective's zero-based index to its question keys under `objective_questions`:

```json
{
  "objectives_reviewed": true,
  "explanations_procedures_examples_summary_reviewed": true,
  "quiz_reviewed": true,
  "teaching_examples_labelled": true,
  "objective_questions": {
    "0": ["dy-active-listening:lesson-001:q001"]
  }
}
```

8. The curriculum review requires the current candidate signature and an identified reviewer. Re-export after editing candidates so its signature is current. Import reviewed pages/curriculum, recollect translations and run reports:

```sh
python tools/library_cli.py review_import @reviews/source.json
python tools/library_cli.py collect
python tools/library_cli.py audit
python tools/library_cli.py report @reviews/coverage-en.json en
```

Partial packages are supported, but incomplete pages cannot pass. The release attaches original PDFs and generates a complete translated HTML companion for every reviewed source page, including covers and closing pages. It also generates per-lesson and per-question source page references from the reviewed coverage map.

## Written translations and unsupported languages

The versioned target includes ISO 639-3 living individual languages and preserves existing aliases/macrolanguage URLs. Its full target stays visible in administration, including pending and unsupported languages. Script/region variants can be registered with `language_variant locale base direction name`; review direction rather than assuming every individual language uses the Latin script. Signed language is a separate modality.

```sh
python tools/library_cli.py translation_export ar @reviews/ar.json site
python tools/library_cli.py translation_export ar @reviews/ar-course.json course:dy-active-listening
python tools/library_cli.py translate ar site 20
python tools/library_cli.py translation_import @reviews/ar-reviewed.json
python tools/library_cli.py coverage ar site
```

AI generation requires a configured production provider and supported target language. Work is bounded per call and enters review, never approval. Human imports support languages the provider cannot translate. Fill each unit's `value`, retain `key` and `source_hash`, set `status` to `ready` only after human review and supply the reviewer identity. Unknown, retired, duplicate or stale units reject the whole import. Arabic seed text is review input, not an automatic sign-off.

The explicit glossary is `application/config/ha_translation_glossary.php`. Preserve its names, identifiers, URLs, placeholders such as `{name}` and `{{name}}`, formatting tags/attributes, printf tokens and backtick code. Update the glossary through review when adding protected entities. Do not translate routing slugs, `source_key`, video IDs or numeric answer indices. Check typography, wrapping, plural meaning, dates/numbers and direction on the actual pages; automatic token checks do not establish translation quality.

The shared registry includes interface phrases, static HTML/attributes, explicit JavaScript feedback, dynamic CMS/CRUD labels, public content, metadata and shared notification templates. The actual QA review must exercise public, legacy frontend, learner, instructor and administrator pages, CMS/forms/validation/dialogs, notifications, emails, certificates, reports and accessibility labels. Register/fix strings missed by static extraction before signing QA. No claim of complete translation should rely on the scan count alone.

## Signed languages

For every unit of a signed release, import reviewed signing as a local MP4/WebM under `uploads/academy/library/signed/` using `signed_media`. Written text may supplement the video but cannot satisfy completion. Media hashes protect approvals from file changes. Every lesson needs a reviewed video in the selected signed language, and assessment questions/options require reviewed signed media. The rendered page exposes only signing for content present in that response; it does not show hidden correct answers or explanations.

Human sign-language reviewers must check question meaning, option identities, timing, navigation and the full signed assessment flow. Reusing an unrelated clip for many units is not a translation and must not be approved. The workflow can import/show signed media; it does not produce sign-language recordings.

## Video selection and quality score

Search YouTube, Dailymotion, Vimeo and authoritative creators against each lesson's objectives. Watch the whole candidate. Prefer accurate hospitality examples and native-language delivery or authorized captions. A popular talk can be an alternative when its scope matches; popularity is not evidence of instructional fit. Written guides must be complete when a video uses another language.

Export the existing records and the complete lesson inventory:

```sh
python tools/library_cli.py video_export @reviews/video-candidates.json
python tools/library_cli.py video_import @reviews/video-reviewed.json
python tools/library_cli.py verify_videos dy-active-listening
```

Fill `course_code`, stable `lesson_key`, `provider`, `video_id`, actual `locale`, creator name/URL, measured `duration_seconds`, a lesson-specific `relevance_reason`, reviewer, score and optional alternate `sort_order`. Caption imports require an existing local authorized VTT under `uploads/academy/library/captions/`. Owned media must be under `uploads/academy/library/videos/` or `signed/`.

Use this editorial rubric; the final `editorial_score` is its sum, **not a platform rating**:

| Criterion | Maximum | Evidence |
| --- | ---: | --- |
| Direct objective relevance | 35 | Identify the lesson objectives and matching segments |
| Accuracy and source consistency | 25 | Compare advice/procedures with the PDF and responsible authority |
| Clear instruction and useful examples | 20 | Assess explanation, structure and demonstrations |
| Accessibility and language suitability | 10 | Understandable delivery, readable visuals and authorized captions where needed |
| Production quality and creator attribution | 10 | Usable audio/video and identifiable creator |

Require at least 70/100. A materially inaccurate or irrelevant video must be rejected regardless of its total. Record component scores and relevant timestamps in the editorial reviewer's working record; include the evidence in `relevance_reason`. Publication also requires creator credit, positive duration, embedding evidence, verification data and a check within 30 days. API verification is separate from human scoring.

YouTube oEmbed is attribution only. Configure the Data API key to verify privacy, embedding, duration, age restrictions and country rules. Dailymotion uses provider fields; Vimeo currently remains unchecked after oEmbed. Owned files require a reviewer and measured duration and are hash-checked. Recheck periodically, provide alternatives for restricted countries and keep the written lesson usable if a video fails. A permissive result for an unknown country is not a claim of worldwide availability.

## Final surface QA and release

Only export QA after translations and course content are current:

```sh
python tools/library_cli.py qa_export ar @reviews/ar-site-qa.json site
python tools/library_cli.py qa_export ar @reviews/ar-course-qa.json course:dy-active-listening
python tools/library_cli.py qa_import @reviews/ar-site-qa.json
python tools/library_cli.py qa_import @reviews/ar-course-qa.json
python tools/library_cli.py readiness dy-active-listening ar
```

An identified human reviewer checks `language_definition` against the target ISO identity, modality and intended writing system, then sets each applicable checklist item true after testing desktop/mobile layouts, direction, fonts, plurals and dynamic content. Signed releases additionally require `signed_flow`. The fingerprint includes source units, translated values, signed-media hashes, language definition, review schema and interface assets or course candidate signature. Changes invalidate QA. Do not manufacture sign-offs to bypass a missing provider, review or recording.

Readiness lists exact outstanding pages, lessons, questions, objectives, translation locators and media requirements. Release only after `ready: true`; activate the shared site language separately. Retain the 75% pass threshold, ten attempts and sequential unlocking. Test failed attempts, retries, correct translated answer identities and publication/import idempotence on a staging database before production release.
