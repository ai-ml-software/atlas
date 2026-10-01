# Deployment and rollback

## Scope and prerequisites

This is an overlay for **this Atlas workspace baseline**, including its existing Altus frontend and migrations through version 19. It is not a fresh installer. Some bundled files also retain the user's pre-existing changes. Compare the package inventory with the target checkout and merge any independently changed files before deploying. Preserve existing routing, locale configuration, translation dictionaries, media, custom hooks and environment configuration.

Use PHP 8.1+, MySQL 8 with utf8mb4/InnoDB, curl, mbstring and the existing application dependencies. Python 3 is useful for the CLI wrapper and package builder; PyMuPDF and Pillow are needed for manifest rendering. OCR additionally requires Tesseract and Arabic/English trained models. Browser tooling is optional and remains under `e2e`; Node and Playwright with Edge were used locally.

Back up the target database and changed application files securely before deployment. Retain live enrollment/progress data during any rollback. The local `tools/library_local_validation.py` is deliberately restricted to this Laragon installation; do not point it at production or change its guards to bypass them. Use your production backup process there.

## Deploy the workflow without publishing revisions

1. Extract the ZIP into a staging directory and verify `PACKAGE-MANIFEST.json`. Apply the listed application, asset, seed, source and tool files to the corresponding paths. The ZIP excludes `database*.php`, `.env`, SQL backups and `config.php`.
   Keep `delivery-reports/` and the separate private reviewer ZIP outside the public web root. The reviewer bundle contains translations and assessment explanations; it is not an application overlay.
2. Merge the `Ha_translation_output` display hook into `application/config/hooks.php`, preserving other hooks. Set `$config['enable_hooks'] = TRUE;` in the target `application/config/config.php`. The package includes the hook declaration; never overwrite independently configured hooks without merging.
3. Confirm migrations 1–19 already exist and are applied. Apply the new versions in order using the target's PHP executable:

```sh
php index.php ha_cli migrate 20260101000020
php index.php ha_cli migrate 20260101000021
php index.php ha_cli migrate 20260101000022
php index.php ha_cli migrate 20260101000023
php index.php ha_cli migrate 20260101000024
php index.php ha_cli seed 009_library
php index.php ha_cli seed 013_library_workflow
python tools/library_cli.py collect
python tools/library_cli.py report @reports/library-coverage-en.json en
```

`ha_cli seed` matches a filename fragment. These explicit fragments select the PDF library and workflow seeds only. Do not run `fresh`, the whole seeder, `011_library_only` with the opt-in hiding flag, or a catalogue-wide bridge sync as part of this deployment. The migrations intentionally retain additive data on rollback.

Run registry collection, editorial/translation imports and publication serially. Collection commits each scope atomically so readers retain the previous registry until the updated scope is complete. Competing CLI writers can still encounter database locks; rerun a failed report after the other writer finishes.

4. Ensure PHP can read the original source directory and write `uploads/academy/library`, the review support directory and the normal runtime cache directories. The workflow seed copies the original PDFs to checksum-based attachment paths. Verify `audit` reports 41 files/368 pages and lists the expected pending reviews.
5. Re-run the two explicit seeds and confirm no duplicate records, lost progress or visibility changes. Test public and learner routes in staging. Use `node tools/library_browser_smoke.cjs` only on a local site; it performs read-only public checks without the E2E global setup.

No deployment, production publication, email delivery or translation-provider calls were performed by this task. Production publication remains a separate operation after editorial and language readiness passes.

## Configure services and review inputs

Configure a production text provider in the existing AI Studio with the `translation` task capability and only languages the selected provider supports. Provider output is imported as `reviewing`; it cannot mark a translation ready. Human imports work without an AI provider. Credentials belong in the target environment or encrypted provider configuration, not review JSON or this ZIP.

Set `HA_YOUTUBE_API_KEY` in the PHP CLI and web environment for YouTube Data API verification. Its absence leaves YouTube sources unchecked even after successful attribution. Vimeo currently supplies attribution only and remains unchecked; use a verified alternate or owned media until a Vimeo privacy/restriction adapter is implemented. Dailymotion verification requires all requested embedding, privacy and geoblocking fields. Provider/network failures leave a source unchecked.

The final `coverage` and `readiness` JSON fields are authoritative. Audits may exit successfully while reporting pending work; do not interpret shell exit zero as editorial approval. `publish` and `enable` exit nonzero when their readiness gates fail.

## Release a reviewed course-language version

Follow [the review procedure](library-review.md), recollect changed content, import current reviewed translations and current QA. Then:

```sh
python tools/library_cli.py readiness dy-active-listening en
python tools/library_cli.py publish dy-active-listening en
```

Publication imports the current reviewed candidate transactionally, generates the translated source companion, records the active revision and mirrors **only that course** to the legacy LMS. If the mirror fails after the release transaction, retry:

```sh
php index.php ha_bridge sync_one dy-active-listening
```

Publishing a new content signature makes other course-language releases stale until they are reviewed for that signature. Their records and URLs remain available with incomplete-version treatment; discovery alternates and localized assessments do not claim they are current. Use a new stable question key when its meaning or correct answer changes so historical attempts retain their original question and options. Never overwrite an assessment while it has active attempts.

Enable a site language only after all shared units and actual interface QA pass:

```sh
python tools/library_cli.py enable ar
```

Language activation and course publication are independent. Preserve `application/seeds/library_support/enabled_languages.json` after activation; it is runtime release configuration and is deliberately not overwritten by the package builder. Retain existing aliases. Country access is controlled independently of locale URLs. Do not make artificial country-specific duplicate pages.

## Availability monitoring

Schedule these commands with your existing scheduler, using its actual PHP path and application root:

```sh
php index.php ha_library verify_videos
python tools/library_cli.py report @reports/library-coverage-en.json en
```

Run video verification at least weekly and inspect its JSON results; publication requires evidence from the last 30 days. Vimeo attribution and missing YouTube credentials do not satisfy that requirement. Produce coverage reports for every released language after source, template or interface changes. Keep reports outside public web directories: exports can contain assessment answers and review identities.

## Rollback without losing learners

For a workflow-code rollback, restore the backed-up application/asset files and prior hook configuration. **Leave migrations 20–24 and all learner, identity, retirement, translation and release tables in place.** Their `down()` methods intentionally do not drop retained history; rolling the migration ledger back is unnecessary and would cause later reapplication.

For a language issue, disable its new release without deleting translation history:

```sh
python tools/library_cli.py disable fil
```

The default source language cannot be disabled. Keep the previous activation file when rolling back language selection. For a content issue, restore an earlier reviewed candidate as a new corrective revision and pass the same readiness gates. Do not restore an old database dump over learner activity accumulated after deployment. A rollback after publishing changed assessments may require a reviewed corrective import; this workflow does not supply an unsafe automatic assessment-history rewind.

## Rebuild the package

```sh
python tools/build_library_package.py --output backups/library-delivery/library-workflow-20261002.zip --reports backups/library-review
```

The builder includes only its explicit implementation list, the 40 manifest course files, frozen support data, all 41 source PDFs, review documentation and named reports. It excludes private configuration, database dumps, generated test uploads, active locale configuration and unrelated draft seeds. The package requires the matching existing application baseline.
