# Admin Studio installation

This release extends the existing CodeIgniter application. It is not a WordPress plugin. Read [the architecture baseline](ADMIN_BASELINE.md) and [acceptance report](../ALTUS_AI_PUBLISHER_ACCEPTANCE_REPORT.md) for the architecture and executed checks. MCP setup is in [MCP_GATEWAY_RUNBOOK](MCP_GATEWAY_RUNBOOK.md).

## Existing Laragon installation

The local working database is `atlas_merged`. Migration `20260101000025_admin_studio` adds private document/page draft tables and missing landing-page/menu records. Migration `20260101000026_studio_home_binding` adds the explicit homepage binding marker. Existing courses, users, progress and certificates remain in their existing tables. Run the complete migration runner before opening the editors. Migrations 27, 29 and 30 add retained document jobs/OCR support, shared entity/theme drafts, stable section keys, OAuth grants, approvals, retries, media selection and connection history.

1. Start Laragon's Apache and MySQL. Keep `application/config/database.local.php` pointing to **your local database**. The tracked database configuration has a production fallback, so verify the override before any CLI migration.
2. Back up the database and private files. The backup made for this installation is outside the web root at `C:/laragon/backups/atlas-admin-studio-20261002/atlas_merged-before-admin-studio.sql`. Keep backups off the public web server.
3. In PowerShell, from the project root:

```powershell
Set-Location C:/laragon/www/atlas/atlas
$altusPhp = 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe'
& $altusPhp index.php ha_cli status
& $altusPhp index.php ha_cli migrate
```

4. Sign in using an existing platform admin account at `/login`. Open [the admin dashboard](http://localhost/atlas/atlas/hkp/admin) and [Content Studio](http://localhost/atlas/atlas/hkp/cms/studio).

The existing application's full installation, account setup and deployment instructions remain in [README](../README.md) and [DEPLOYMENT](../DEPLOYMENT.md). An empty installation needs the original Academy database as well as native migrations; this additive release is not a replacement database dump. Do not run `ha_cli fresh` against working data.

## Document extraction

PDF extraction requires Python, `pypdf`, PHP `proc_open`, `fileinfo`, `mbstring` and a readable `tools/` directory. DOCX/PPTX extraction also requires PHP `zip` and `dom`. TXT/MD/JSON require UTF-8 text. The verified local environment uses PHP 8.1.10, Python 3.13 and pypdf 6.19.0.

```powershell
& C:/Python313/python.exe -m pip install -r tools/publisher-requirements.txt
```

Install both the OCR engine and language data. Follow the [official Tesseract installation guide](https://tesseract-ocr.github.io/tessdoc/Installation.html); its Windows section links the UB Mannheim installer. Select English and Arabic language data, or put `eng.traineddata` and `ara.traineddata` from [official tessdata_fast](https://github.com/tesseract-ocr/tessdata_fast) in `application/storage/private/ocr/tessdata`.

After installing Tesseract on Windows, these commands install the official language files in the private folder used by the example configuration:

```powershell
New-Item -ItemType Directory -Force application/storage/private/ocr/tessdata | Out-Null
Invoke-WebRequest https://raw.githubusercontent.com/tesseract-ocr/tessdata_fast/main/eng.traineddata -OutFile application/storage/private/ocr/tessdata/eng.traineddata
Invoke-WebRequest https://raw.githubusercontent.com/tesseract-ocr/tessdata_fast/main/ara.traineddata -OutFile application/storage/private/ocr/tessdata/ara.traineddata
```

On Debian/Ubuntu:

```bash
sudo apt install tesseract-ocr tesseract-ocr-eng tesseract-ocr-ara
python3 -m pip install -r tools/publisher-requirements.txt
```

Windows local override (edit the executable paths after copying):

```powershell
Copy-Item application/config/ha_publisher.local.example.php application/config/ha_publisher.local.php
```

Leave `mcp_enabled` false until the gateway is configured. Never overwrite an existing local override blindly. Run `publisher_cli health` to check the actual configuration used by PHP.

Executable paths live in `application/config/ha_publisher.php` (or `ha_publisher.local.php` on a workstation) and can be overridden by environment variables. Never accept an executable or command from an HTTP request.

| Setting | Environment variable | Default |
|---|---|---|
| `python` | `ALTUS_PUBLISHER_PYTHON` | `python` on Windows, `python3` elsewhere (PATH lookup); set an absolute path when the web server PATH differs |
| `tesseract` | `ALTUS_TESSERACT` | `C:/Program Files/Tesseract-OCR/tesseract.exe` if present, else `tesseract` on PATH |
| `tessdata` | `ALTUS_TESSDATA` | `application/storage/private/ocr/tessdata` (needs `eng.traineddata` and `ara.traineddata`) |
| `max_attempts` | `ALTUS_PUBLISHER_MAX_ATTEMPTS` | `3` automatic tries for transient failures (time limit, expired worker lease) |
| `attempt_limit` | `ALTUS_PUBLISHER_ATTEMPT_LIMIT` | `6` total tries including manual **Retry extraction** |
| `backoff_seconds` | `ALTUS_PUBLISHER_BACKOFF` | `60`, doubled per attempt |
| `source_retention_days` | `ALTUS_PUBLISHER_RETENTION_DAYS` | `30`; retained uploads of finished jobs are deleted afterwards |

Input and dependency errors (damaged or encrypted PDF, missing Tesseract or language data) fail immediately with guidance instead of retrying. **Content Studio → AI Publisher → Setup & worker health** checks Python, pypdf, PyMuPDF, the Tesseract binary, eng/ara language data, an enabled AI provider and the worker heartbeat, with the fix for each failing item. The same checks run on the command line with `php index.php publisher_cli health`.

### Extraction worker

Uploaded documents are queued and extracted by a command-line worker. Run one of:

```powershell
php index.php publisher_cli daemon            # long-running service (poll every 5 s)
php index.php publisher_cli work 25           # one batch, for a scheduler
php index.php publisher_cli cleanup           # delete retained sources past the retention window
```

Windows Task Scheduler (elevated PowerShell, runs a batch every minute):

```powershell
powershell -ExecutionPolicy Bypass -File tools\publisher-worker-task.ps1 -Php "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
```

Linux cron:

```cron
* * * * * cd /var/www/altus && php index.php publisher_cli work 25 >> application/logs/publisher-worker.log 2>&1
```

Each run writes a heartbeat; the publisher shows the last heartbeat and warns when none was recorded in the last five minutes. Batch runs also apply the source retention cleanup. Migration `20260101000029_publisher_completion` adds the draft featured image (`media_id`) and the job retry time (`next_attempt_at`).

For uploads set PHP `upload_max_filesize` to at least `15M` and `post_max_size` to at least `20M`, then restart Apache. These values cover document imports; larger lesson/video uploads use the existing LMS limits. Upload temporary storage and existing application log/media/private-storage directories must be writable by the web-server account.

PDFs are bounded to 150 pages and 120,000 extracted characters. Pages without a text layer are read with local Tesseract OCR (English + Arabic). Encrypted and damaged PDFs are rejected. Upload size is limited to 15 MB. Extraction stores the source text privately in the database, marked with `[Page N]`, and records per-page provenance (method text/OCR, characters, review required) shown beside the source. OCR pages and Arabic text layers stored in presentation forms are flagged for human review. The original upload is retained privately only for the retention window. Use the existing lesson-resource upload if learners need the source file.

Generated sections, lessons and questions carry `source_pages` references to those markers. Before creating content you can choose a featured image from the platform media library (applied to the course thumbnail, page hero, article cover or topic hero). SOP output maps onto the knowledge fields (purpose, scope, responsibilities, procedure, safety notes, escalation …) and can be submitted to **internal review** with a chosen reviewer and approver; it is never approved or published automatically.

Regenerate the extraction fixtures with `python tools/publisher_fixtures.py`; run the extractor tests with `python tools/test_publisher_extract.py`.

## Configure AI generation

Open the existing **AI Studio → AI providers & keys** screen at `/ha_ai/providers`. Enable an approved chat provider, set its credentials through the existing encrypted settings workflow, and synchronize/select its models. Keep the application's encryption key stable when moving environments. Provider credentials must not go into documentation, browser JavaScript or Git.

In **Content Studio → AI Publisher**, explicitly select a provider/model and click **Generate draft**. That action sends up to 60,000 extracted source characters plus your editorial brief to that provider. Upload/analysis alone performs local extraction. Generation needs the provider to return the required structured JSON; failures leave the existing draft intact. The provider/model and source/content hashes are recorded for review. The browser-test mock is confined to `e2e/`; it is disabled after tests and is not an application fallback.

Without an enabled provider, admins can still edit the website and catalogue, create courses manually, and load/review a valid structured package in the Publisher's advanced editor.

## Permissions

Website live editing, public catalogue editing and menus require system-scoped platform access. Page actions require `cms_pages.update`; publication additionally requires `cms_pages.publish`. Catalogue permissions use `programs`, `learning_paths`, `articles` or `cms_pages` according to the type. Document drafting requires `ai.generate` plus the target course/page/article/topic/SOP creation permission; course import additionally checks lesson, assessment and question-bank creation permissions when those objects are included.

Source drafts are visible to their author and system administrators. Tenant authors cannot inspect other authors' sources or import into another organisation. The existing role and scope system remains authoritative; hiding a sidebar link is not the permission check.

## Verification and rollback

```powershell
& $altusPhp index.php ha_test run
& $altusPhp index.php ha_test prepare_browser
Set-Location e2e
npm.cmd ci
npx.cmd playwright test -c playwright.studio.config.ts
Set-Location ../mcp-gateway
npm.cmd ci
npm.cmd run build
npm.cmd test
npm.cmd run test:integration
```

Both PHP commands rebuild only `atlas_hospitality_test`. The Studio Playwright configuration uses loopback port 8099 and the same isolated database; it refuses a mismatched server. Run database rebuilds, browser tests and the MCP integration test sequentially: the latter two share the seeded browser database. Microsoft Edge and Node are required. Existing Playwright configuration can modify the working local database, so use the isolated Studio configuration for this guide.

For rollback, restore a verified database backup and the corresponding source/assets together. Migration 25's `down()` removes the two new draft tables but deliberately retains authored landing pages/menu data; using it discards private drafts and is not a complete content rollback. Migration 26 retains the binding marker on rollback to avoid changing published content accidentally. Do not reset the repository: it contains unrelated pre-existing work. For page-level recovery use the existing SEO/revisions screen; re-open the live editor after restoring a revision.

Before deploying, perform the checks in [DEPLOYMENT](../DEPLOYMENT.md), configure a real approved AI provider, test extraction under the actual web-server account, and validate this release on staging. Production was not deployed during this task.
