# ALTUS Admin, Live CMS and AI Publisher

Release: October 2026. Native CodeIgniter services remain authoritative for login, permissions, tenancy, learner progress, certificates and SOP approvals.

| Area | Working features |
|---|---|
| Admin | Cream, charcoal and copper theme; real database figures; responsive tables/forms; permission-aware sidebar, search, active groups and keyboard command palette. Website Pages, Courses, AI Publisher and Menus stay visible. |
| Website | Home plus all twelve requested landing routes in EN/AR. Contact, catalogues and certificate verification retain their functional components. |
| Frontend editing | Authorized admins click supported text, images, cards and sections on the actual page preview. Inline text synchronizes with the controls; keyboard activation opens image selection. Published entity pages have native course/program/path/article/topic editors. |
| Page sections | Thirteen native types; insertion, duplication, ordering, visibility, media upload/library, undo/redo and desktop/tablet/mobile preview. Stable section identifiers preserve native rows. |
| Corporate homepage | Hero and corporate text blocks bind to native corporate records. Private changes appear only to authorized previews; explicit publication updates those records. |
| Drafts | Pages, courses, articles, topics, programs, paths, menus and global theme/site settings. Autosave or explicit save, published-content hashes, optimistic versions, revision history and restoration into private drafts. |
| Theme | Approved self-hosted/system fonts, brand colours, spacing, header/footer/navigation styles, logo, site name, SEO defaults and contact details. Preview before explicit publication. |
| Catalogue CRUD | Native bilingual create/read/update, publication and archive workflows. Programs/topics link ordered courses; paths use a visual sequence editor with stable steps and enrolled-history protections. |
| Courses | Existing native modules, lessons, media, assessments and question-bank CRUD. Generated course packages create real unpublished modules, lessons and quiz questions atomically. Governed PDF-library releases keep their existing review workflow. |
| Documents | PDF, DOCX, PPTX, TXT, MD and JSON; private retained uploads; queued extraction, progress, per-page provenance, cancellation, bounded retries/backoff, worker heartbeat and retention cleanup. |
| Scanned PDFs | Embedded text first, local Tesseract English/Arabic OCR only where needed, including mixed documents. Source page references and review flags remain visible. |
| AI outputs | Courses with lessons/quizzes, website pages, articles, hospitality topics and SOPs. Select configured provider/model, correct source, edit/order output, regenerate selected items, translate to a separate language draft and select featured media. |
| SOPs | Generated text maps to native SOP fields and enters internal review with reviewer/approver selection. AI and MCP cannot bypass SOP approval. |
| Native API | `/api/publisher/v1`: discovery, draft creation/editing, media/documents, generation/translation, package validation/import, jobs, approval requests/status, approved publication/archive and revision/archive restoration. Structured errors, request/audit references, idempotency and conflicts. |
| MCP | Separate Node 24/TypeScript gateway; official SDK; Streamable HTTP; OAuth PKCE S256, existing ALTUS login/2FA, consent, protected-resource metadata, audience checks, refresh rotation/revocation and persistent grants. |
| Integrations | Connections/revocation, ten-minute version-bound approvals, gateway/discovery health probes, searchable audit history and archive restoration. Requests recheck live account, permissions and tenant scope. |

## Records and feature switches

Additive migrations 25, 26, 27, 29 and 30 provide private drafts, homepage binding, section keys, extraction jobs, OAuth/approval/idempotency storage, media selection, retry scheduling and connection history. Run the migration runner so all applicable migrations are applied in order; migration 31 belongs to the separate mobile settings work. Existing learning relationships and history remain in their native tables.

`ALTUS_LIVE_EDITING=0` disables frontend live-edit controls. MCP is disabled unless `ALTUS_MCP_ENABLED=1` and its shared credentials are configured. Draft preview links are signed, expire and still require an authorized editor login; they are not public sharing links.

## Limits and workflows

Documents: 15 MB, 150 PDF pages, 120,000 extracted characters; generation sends up to 60,000 source characters. Images: JPEG/PNG/WebP, 5 MB and 40 megapixels. Retained sources default to 30 days. Extraction uses fixed configured executables and treats source text as untrusted data. No arbitrary SQL, shell tool or permanent-deletion MCP tool is exposed.

Publication requires review. MCP approval is bound to the client, user, operation, object and current content version, expires after ten minutes and is consumed once. Another administrator reviews by default. Altering content invalidates approval; an idempotent retry of the same request returns the original result.

Editing covers explicit native fields and section types. Transactional forms and dynamic learning widgets keep their native services; arbitrary template/code editing is outside the editor. Path UI preserves non-course relationships, while the existing public path template presents course items. Some secondary labels/messages retain English fallback. Live paid AI, production HTTPS hosting and external vendor clients require environment-specific verification; see the acceptance report.

## Guides

- [Installation and worker setup](ADMIN_INSTALL.md)
- [Admin daily workflows and screenshots](ADMIN_GUIDE.md)
- [MCP connection and OAuth setup](MCP_GATEWAY_RUNBOOK.md)
- [Integrations, approvals and audit workflows](MCP_ADMIN_WORKFLOWS.md)
- [Native API contract](PUBLISHER_API.md)
- [Executed verification](../ALTUS_AI_PUBLISHER_ACCEPTANCE_REPORT.md)
