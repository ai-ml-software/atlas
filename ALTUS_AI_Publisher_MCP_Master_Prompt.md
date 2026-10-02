# ALTUS WordPress AI Publisher + MCP Bridge
## Master Build Prompt — Production-Ready Frontend + Backend + WordPress Plugin + Remote MCP

You are the lead architect and senior full-stack WordPress/MCP engineer for ALTUS Gulf.

Your job is to DESIGN, BUILD, TEST, PACKAGE, and DOCUMENT an original production-ready system called:

**ALTUS AI Publisher**
Plugin slug: `altus-ai-publisher`
MCP namespace: `altus`
Suggested remote MCP hostname: `https://mcp.altusgulf.com`
Primary WordPress site: `https://altusgulf.com`

The product should provide a user experience functionally similar to modern WordPress AI/MCP management tools such as WPVibe, but it MUST be an original implementation. Do not copy proprietary code, proprietary prompts, proprietary UI, or private/internal implementation details from any third-party product.

The result must allow an authorized ALTUS administrator to connect compatible AI clients such as Claude, Cursor, Claude Code, ChatGPT custom MCP apps where supported, and other MCP-compatible clients to the ALTUS WordPress website and safely:

- create and edit WordPress pages;
- create and edit posts/articles;
- upload and manage media;
- create, update, translate, organize, and publish courses;
- create modules, lessons, quizzes, questions, programs, learning paths, and certificates;
- update course metadata and featured images;
- create content in English and Arabic with proper RTL support;
- import a complete course from structured Markdown, JSON, ZIP, or pasted AI-generated content;
- read site content and configuration;
- search existing pages/courses before creating duplicates;
- manage drafts safely;
- preview content before publishing;
- publish only with explicit authorization;
- maintain a detailed immutable-style audit trail;
- expose well-defined WordPress Abilities and MCP tools;
- remain usable from WordPress Admin even when no AI client is connected.

The system must be straightforward enough that a hotel training administrator can say:

> Create a Housekeeping course called "Safe Bathroom Cleaning", add 5 lessons, create a 10-question quiz with an 80% passing score, add the Arabic translation, upload the supplied PDF, keep everything as draft, and give me preview links.

The system must execute the request reliably and return structured results.

---

# 1. BUSINESS CONTEXT

ALTUS Gulf is a hospitality and business advisory company in Saudi Arabia.

ALTUS Knowledge and Performance is its learning, knowledge-management, and performance platform.

The existing public site includes:
- company/advisory pages;
- articles and resources;
- courses;
- programs;
- learning paths;
- certifications;
- SOP resources;
- hospitality topics;
- bilingual English and Arabic content;
- RTL support for Arabic;
- hotel-oriented professional domains.

The plugin must NOT replace the entire ALTUS website.

It must extend WordPress so ALTUS can manage the website and the learning platform efficiently from:
1. WordPress Admin;
2. REST API;
3. WordPress Abilities API;
4. MCP clients.

Preserve compatibility with the existing WordPress theme and content.

Never overwrite the existing production site during development.

Development flow:
`local -> automated tests -> staging -> approval -> production`.

---

# 2. PRIMARY PRODUCT GOALS

Build one coherent product with four layers:

## Layer A — WordPress Plugin
A normal installable WordPress plugin ZIP.

## Layer B — WordPress Admin Application
A polished admin UI for content, courses, imports, integrations, connection status, audit logs, and approvals.

## Layer C — WordPress Abilities + REST API
A structured programmable API for everything the plugin supports.

## Layer D — MCP Gateway
A secure MCP interface that allows compatible AI clients to call those capabilities.

The WordPress plugin must remain fully functional without the gateway.

---

# 3. SUPPORTED PLATFORM

Target:

- WordPress 7.0+ preferred.
- WordPress 6.9+ supported where feasible.
- PHP 8.1+.
- MySQL 8+ / MariaDB supported versions.
- HTTPS required for production.
- Composer.
- Node 20+ for build tooling and gateway.
- TypeScript for MCP gateway.
- React using WordPress packages for admin screens.
- WordPress Coding Standards.
- REST API.
- WordPress Abilities API.
- Official WordPress MCP Adapter integration where practical.

When WordPress 6.9/7.0 behavior differs from later WordPress releases, explicitly handle compatibility rather than assuming one behavior.

Do not bundle or fork the official WordPress MCP Adapter unless licensing and release packaging require it. Prefer declaring/detecting it as a dependency or using documented integration hooks.

---

# 4. CORE ARCHITECTURE

Create a monorepo similar to:

```text
altus-ai-publisher/
├── README.md
├── SECURITY.md
├── CHANGELOG.md
├── LICENSE
├── composer.json
├── package.json
├── phpunit.xml.dist
├── .editorconfig
├── .gitignore
├── plugin/
│   ├── altus-ai-publisher.php
│   ├── uninstall.php
│   ├── readme.txt
│   ├── includes/
│   │   ├── Bootstrap.php
│   │   ├── Capabilities.php
│   │   ├── PostTypes.php
│   │   ├── Taxonomies.php
│   │   ├── Rest/
│   │   ├── Abilities/
│   │   ├── Courses/
│   │   ├── Import/
│   │   ├── Media/
│   │   ├── Audit/
│   │   ├── Approval/
│   │   ├── Preview/
│   │   ├── Security/
│   │   ├── Compatibility/
│   │   └── Integrations/
│   ├── admin/
│   │   ├── src/
│   │   └── build/
│   ├── templates/
│   ├── assets/
│   └── languages/
├── gateway/
│   ├── src/
│   │   ├── server.ts
│   │   ├── mcp/
│   │   ├── oauth/
│   │   ├── wordpress/
│   │   ├── audit/
│   │   └── security/
│   ├── tests/
│   ├── package.json
│   ├── tsconfig.json
│   └── Dockerfile
├── tests/
│   ├── php/
│   ├── integration/
│   ├── e2e/
│   └── fixtures/
├── docs/
│   ├── INSTALL.md
│   ├── MCP-SETUP.md
│   ├── CHATGPT.md
│   ├── CLAUDE.md
│   ├── CURSOR.md
│   ├── COURSE-IMPORT.md
│   ├── API.md
│   ├── SECURITY-MODEL.md
│   └── TROUBLESHOOTING.md
└── dist/
    └── altus-ai-publisher.zip
```

Actual internal organization may differ if justified, but maintain strong separation of responsibilities.

---

# 5. LMS STRATEGY

The plugin must work in two modes.

## Mode 1 — Native ALTUS Learning Model

If no supported LMS plugin is installed, provide a lightweight native ALTUS learning model.

Custom post types:

- `altus_course`
- `altus_module`
- `altus_lesson`
- `altus_quiz`
- `altus_question`
- `altus_program`
- `altus_learning_path`
- `altus_certificate`

Recommended taxonomies:

- `altus_domain`
- `altus_department`
- `altus_level`
- `altus_language`
- `altus_topic`
- `altus_property_type`

Use stable IDs and relationships.

Do NOT store complex application data in one huge serialized meta field.

Use normal WordPress data structures where practical, and dedicated custom tables only when there is a clear scalability/querying reason.

## Mode 2 — LMS Adapter Mode

Detect supported LMS plugins and route operations through dedicated adapters.

Design adapter interfaces for:

- Tutor LMS
- LearnDash
- LifterLMS

At minimum:
- detect installed/active LMS;
- expose which adapter is active;
- map course/module/lesson/quiz operations;
- never corrupt third-party LMS internal data;
- use official APIs/hooks/models where available.

Do not fake support.

If an adapter is incomplete, return a clear `not_supported` result.

---

# 6. COURSE DATA MODEL

A course must support:

```json
{
  "title": "Safe Bathroom Cleaning",
  "slug": "safe-bathroom-cleaning",
  "status": "draft",
  "language": "en",
  "translation_group": "uuid",
  "summary": "...",
  "description": "...",
  "domain": "housekeeping",
  "department": "housekeeping",
  "level": "foundation",
  "estimated_minutes": 35,
  "passing_score": 80,
  "featured_media_id": 0,
  "certificate_enabled": true,
  "prerequisites": [],
  "modules": []
}
```

Each module supports ordered lessons.

Each lesson supports:
- title;
- content;
- short summary;
- video URL;
- uploaded video/media attachment;
- PDF/document attachments;
- downloadable resources;
- duration;
- scenario;
- key takeaways;
- language;
- completion requirement;
- optional knowledge check.

Each quiz supports:
- passing score;
- attempt limit;
- question randomization;
- answer randomization;
- immediate or delayed feedback;
- question bank references.

Question types:
- single choice;
- multiple choice;
- true/false;
- short answer;
- scenario multiple choice.

Every object must expose:
- ID;
- status;
- created/updated timestamps;
- author;
- language;
- translation relationship;
- canonical preview URL where applicable.

---

# 7. BILINGUAL AND RTL

English and Arabic are first-class languages.

Requirements:

- correct `lang` and `dir`;
- Arabic editor and frontend RTL;
- independent English/Arabic content;
- translation linkage between corresponding content;
- support creating an Arabic translation from English without overwriting the English version;
- slugs can remain Latin even when title is Arabic;
- preserve Arabic punctuation and Unicode;
- sanitize without destroying Arabic characters;
- backend tables and search must correctly handle Arabic.

If WPML or Polylang is installed:
- detect it;
- provide a compatibility adapter;
- preserve its translation relationships.

If neither is installed:
- use native ALTUS translation groups.

---

# 8. FRONTEND REQUIREMENTS

Do not redesign the entire ALTUS public website.

Provide frontend templates/components only for plugin-owned learning content where the active theme does not already provide them.

Pages:
- Course archive.
- Course detail.
- Lesson view.
- Program view.
- Learning path view.
- Certificate verification.
- Learner dashboard, if Native ALTUS Learning Model is active.

Visual requirements:
- clean premium hospitality/business look;
- responsive;
- accessible;
- compatible with existing ALTUS theme;
- no forced global CSS reset;
- CSS scoped under ALTUS plugin classes;
- English LTR + Arabic RTL;
- mobile-friendly;
- fast loading.

Use WordPress template hierarchy/filtering so the site owner can override plugin templates inside the theme.

Do not create a proprietary page builder.

---

# 9. WORDPRESS ADMIN UX

Add a top-level admin menu:

**ALTUS AI Publisher**

Submenus:

1. Dashboard
2. Content
3. Courses
4. Course Import
5. Media
6. MCP Connections
7. Approvals
8. Audit Log
9. Settings
10. System Health

## Dashboard

Show:
- plugin version;
- WordPress version;
- PHP version;
- MCP Adapter status;
- gateway connection status;
- active LMS mode;
- number of pages/posts/courses/lessons;
- drafts awaiting approval;
- failed imports;
- recent AI actions;
- recent audit events.

## Course Import

Create a highly usable wizard.

Input options:
- paste Markdown;
- paste JSON;
- upload `.md`;
- upload `.json`;
- upload `.zip`;
- upload DOCX/PDF only if a reliable parser exists; otherwise reject with a clear message rather than silently damaging content.

Wizard flow:
1. Upload/paste.
2. Parse.
3. Show detected course structure.
4. Let user map title, language, modules, lessons, quiz, images, attachments.
5. Validate.
6. Dry-run.
7. Import as draft.
8. Show created objects and preview links.

Never directly publish imported AI content by default.

---

# 10. STRUCTURED MARKDOWN COURSE IMPORT FORMAT

Support a predictable Markdown format such as:

```markdown
---
type: course
title: Safe Bathroom Cleaning
language: en
domain: housekeeping
level: foundation
passing_score: 80
certificate_enabled: true
status: draft
---

# Course Summary

...

## Module: Preparation

### Lesson: PPE and Chemical Safety

Duration: 7

Content...

#### Key Takeaways

- ...
- ...

#### Scenario

...

## Module: Cleaning Sequence

### Lesson: Toilet and Basin

...

## Quiz

Passing Score: 80

### Question 1
Type: single_choice
Question: Which step comes first?
- [x] Wear PPE
- [ ] Spray everything immediately
- [ ] Mix chemicals

Explanation: ...
```

Parser must provide detailed validation errors with line/context where possible.

---

# 11. REST API

Create a namespaced REST API:

`/wp-json/altus/v1/...`

Minimum endpoints:

```text
GET    /site
GET    /health
GET    /content/search
GET    /pages
POST   /pages
GET    /pages/{id}
PATCH  /pages/{id}

GET    /posts
POST   /posts
GET    /posts/{id}
PATCH  /posts/{id}

GET    /media
POST   /media/import-url
POST   /media/attach

GET    /courses
POST   /courses
GET    /courses/{id}
PATCH  /courses/{id}
POST   /courses/{id}/duplicate
POST   /courses/{id}/translate

POST   /courses/{id}/modules
PATCH  /modules/{id}
POST   /modules/{id}/lessons
PATCH  /lessons/{id}
POST   /courses/{id}/quizzes
POST   /quizzes/{id}/questions

POST   /imports/course/validate
POST   /imports/course/dry-run
POST   /imports/course/execute

POST   /preview
POST   /publish/request
POST   /publish/execute

GET    /audit
GET    /approvals
POST   /approvals/{id}/approve
POST   /approvals/{id}/decline
```

Use appropriate WordPress REST primitives and HTTP status codes.

All endpoints must have permission callbacks.

Never rely only on frontend hiding.

---

# 12. WORDPRESS ABILITIES API

Register a category:

`altus-ai-publisher`

Expose carefully selected abilities.

Abilities are PRIVATE by default.

Only MCP-safe abilities receive explicit MCP/public exposure.

Every ability requires:
- label;
- description;
- category;
- JSON input schema;
- JSON output schema;
- permission callback;
- execute callback;
- MCP annotations where supported.

Recommended abilities:

## Read-only
- `altus/site-info`
- `altus/system-health`
- `altus/search-content`
- `altus/list-pages`
- `altus/get-page`
- `altus/list-posts`
- `altus/get-post`
- `altus/list-courses`
- `altus/get-course`
- `altus/list-media`
- `altus/get-audit-events`
- `altus/get-import-status`

## Write but non-destructive
- `altus/create-page-draft`
- `altus/update-page`
- `altus/create-post-draft`
- `altus/update-post`
- `altus/import-media-url`
- `altus/create-course-draft`
- `altus/update-course`
- `altus/create-module`
- `altus/update-module`
- `altus/create-lesson`
- `altus/update-lesson`
- `altus/create-quiz`
- `altus/create-question`
- `altus/reorder-course`
- `altus/translate-course`
- `altus/import-course-dry-run`
- `altus/import-course-draft`

## Sensitive
- `altus/request-publish`
- `altus/publish-content`
- `altus/trash-content`
- `altus/restore-content`

Do not expose permanent delete in v1.

Use WordPress capabilities such as:
- `edit_posts`
- `edit_pages`
- `upload_files`
- `publish_posts`
- `publish_pages`
- `manage_options`

Add custom capabilities for learning objects where useful:
- `altus_edit_courses`
- `altus_publish_courses`
- `altus_manage_learning`
- `altus_manage_mcp`
- `altus_view_audit`

Administrators receive them on activation.
Create an optional `ALTUS Learning Manager` role.

---

# 13. MCP DESIGN

Support the official WordPress MCP Adapter.

The plugin should register abilities in a way that the adapter can discover and execute them.

Also implement an optional ALTUS Remote MCP Gateway.

## Direct WordPress MCP

Where a compatible client can authenticate directly to WordPress:
- use HTTPS;
- use WordPress Application Passwords or another documented secure WordPress auth flow;
- never use the user's normal WordPress password;
- document revocation.

## Remote Gateway

The remote gateway exists for easier AI-client onboarding and OAuth.

Suggested endpoint:

`https://mcp.altusgulf.com/mcp`

Transport:
- Streamable HTTP.
- No unnecessary SSE dependency.
- Standards-compliant JSON-RPC/MCP.

The gateway must:
1. authenticate the AI client/user;
2. determine the selected WordPress site;
3. authorize scopes;
4. call the site's protected ALTUS REST/Abilities endpoints;
5. return structured results;
6. never expose WordPress credentials to the AI model;
7. log security events;
8. rate limit abuse;
9. support credential revocation.

The first release only needs one primary ALTUS site but architecture must support multiple sites later.

---

# 14. OAUTH REQUIREMENTS FOR REMOTE MCP

Implement production-grade OAuth 2.1 style Authorization Code + PKCE.

Do NOT invent weak token auth.

Requirements:
- HTTPS only;
- Authorization Code;
- PKCE S256;
- state validation;
- short-lived access tokens;
- refresh tokens;
- refresh token rotation;
- token revocation;
- scopes;
- hashed/encrypted storage as appropriate;
- CSRF protection;
- exact redirect URI validation;
- protected resource metadata;
- OAuth authorization server metadata;
- `resource`/audience validation;
- clear consent screen;
- `offline_access` where supported/required by clients.

Suggested scopes:

```text
altus.read
altus.content.write
altus.course.write
altus.media.write
altus.publish
altus.admin
```

Never automatically grant `altus.admin`.

The gateway must be compatible with current MCP OAuth clients as closely as standards permit.

Document any client-specific configuration separately rather than baking client names into core auth logic.

---

# 15. MCP TOOL UX

AI tools must be easy for models to use.

Do not expose hundreds of confusing low-level tools.

Prefer 20–35 clear tools with excellent schemas and descriptions.

Potential MCP tools:

```text
site_info
system_health
search_content

list_pages
get_page
create_page_draft
update_page

list_posts
get_post
create_post_draft
update_post

list_courses
get_course
create_course_draft
update_course
create_module
create_lesson
create_quiz
create_question
translate_course
reorder_course
import_course_dry_run
import_course_draft

list_media
import_media_url
attach_media

preview_content
request_publish
publish_content

list_audit_events
```

Every write response must include:
- success;
- object ID;
- object type;
- status;
- changed fields;
- edit URL;
- preview URL where available;
- warnings;
- audit event ID.

Example response:

```json
{
  "success": true,
  "id": 418,
  "type": "altus_course",
  "status": "draft",
  "title": "Safe Bathroom Cleaning",
  "changed_fields": ["title", "summary", "modules"],
  "preview_url": "https://altusgulf.com/?p=418&preview=true",
  "edit_url": "https://altusgulf.com/wp-admin/post.php?post=418&action=edit",
  "warnings": [],
  "audit_event_id": "aud_01H..."
}
```

---

# 16. AI-FRIENDLY COMPOSITE OPERATIONS

In addition to atomic abilities, create several high-value composite operations.

## `create_course_package`

Input:
- course metadata;
- modules;
- lessons;
- quiz;
- questions;
- attachments;
- translation data.

Behavior:
- validate entire payload first;
- execute transaction-like workflow;
- if one required operation fails, do not leave an undocumented partial result;
- return created IDs and rollback information;
- default all created content to draft.

## `translate_course`

Input:
- source course ID;
- destination language;
- translated payload.

Behavior:
- never use uncontrolled machine translation inside the plugin unless a provider is explicitly configured;
- allow the AI client to provide translation;
- preserve source course;
- link translations;
- validate module/lesson correspondence.

## `import_course_dry_run`

Must report:
- objects that would be created;
- objects that would be updated;
- slug collisions;
- missing media;
- invalid questions;
- unsupported content;
- permission issues;
- likely duplicates.

---

# 17. MEDIA

Support:

1. WordPress Media Library browsing.
2. Upload from browser.
3. Import from a remote URL.
4. Attach existing media to pages/courses/lessons.
5. Set featured image.
6. Add alt text.
7. Support PDF resources.

Security:
- use WordPress allowed MIME types;
- validate actual MIME;
- block executable uploads;
- sanitize filenames;
- limit size;
- prevent SSRF on remote imports;
- block loopback/private-network URLs;
- timeouts;
- redirect limits;
- maximum file size;
- HTTPS preferred.

Do not allow arbitrary filesystem writes from MCP.

---

# 18. PAGE CONTENT

Pages/posts must support:
- Gutenberg/block content;
- plain HTML only when sanitized;
- title;
- slug;
- excerpt;
- status;
- featured image;
- taxonomies;
- parent page;
- menu order.

Before updating a page:
- fetch current revision/version;
- use optimistic locking or an equivalent conflict check;
- reject stale updates with `409 Conflict`.

Do not blindly replace large page-builder payloads.

Detect common builders.
If a safe adapter is unavailable, return:
`builder_write_not_supported`
rather than corrupting the page.

---

# 19. DRAFT-FIRST SAFETY MODEL

Mandatory.

Default for AI-created content:
`draft`.

AI may edit drafts within permissions.

Publishing is separate.

Publishing flow:
1. AI calls `request_publish`.
2. Server creates an approval item.
3. WordPress admin receives an approval screen.
4. User reviews title, content type, changes, preview, and requester.
5. User approves/declines.
6. Approved request receives one-time approval token or server-side approval state.
7. AI/client can call `publish_content`.
8. Server re-checks current content hash/revision before publishing.
9. If content changed after approval, invalidate approval and require review again.

No permanent delete in v1.

Trash operation requires elevated permission and confirmation.

---

# 20. APPROVAL UI

Admin page:
**ALTUS AI Publisher -> Approvals**

Each approval card shows:
- operation;
- requested by;
- AI client;
- content ID;
- content type;
- title;
- change summary;
- current status;
- before/after fields;
- preview link;
- requested timestamp;
- approve;
- decline.

Approval tokens:
- random cryptographic values;
- stored hashed;
- single-use;
- short expiry;
- bound to user, operation, object ID, content hash.

---

# 21. AUDIT LOG

Maintain a high-integrity audit log.

Recommended custom table:
`{$wpdb->prefix}altus_ai_audit`

Fields:
- `id`
- `event_uuid`
- `created_at_gmt`
- `wp_user_id`
- `client_id`
- `client_name`
- `source`
- `operation`
- `object_type`
- `object_id`
- `ability_name`
- `request_id`
- `ip_hash`
- `status`
- `risk_level`
- `before_hash`
- `after_hash`
- `summary_json`
- `error_code`

Do not store secrets, passwords, access tokens, refresh tokens, or raw Authorization headers.

Provide filtered audit view.

Audit data should not be editable through normal UI.

If deletion is needed for privacy/retention rules, provide a separate administrator-only retention process and document it.

---

# 22. SECURITY REQUIREMENTS

Security is a release blocker.

Follow:
- WordPress nonces for browser admin actions;
- capability checks;
- REST permission callbacks;
- input validation;
- output escaping;
- prepared SQL;
- CSRF prevention;
- XSS prevention;
- SSRF prevention;
- path traversal prevention;
- rate limiting for gateway;
- secrets never in logs;
- least privilege;
- secure headers where appropriate;
- no eval;
- no arbitrary PHP execution;
- no arbitrary shell execution;
- no arbitrary SQL execution;
- no arbitrary WP-CLI execution from MCP in v1.

Do not create an MCP tool that accepts arbitrary:
- PHP;
- shell command;
- SQL;
- filesystem path;
- plugin/theme source code execution.

This system is a publishing and learning-management bridge, not a remote code execution interface.

---

# 23. CONNECTION EXPERIENCE

Create an admin setup wizard:

### Step 1 — System Check
- WordPress version;
- PHP;
- permalink;
- HTTPS;
- REST API;
- Application Password availability;
- MCP Adapter status.

### Step 2 — Choose Connection Method
- ALTUS Remote MCP Gateway;
- Direct WordPress MCP;
- REST API only.

### Step 3 — Authorize
Use one-click WordPress authorization where possible.

Never ask the user to paste their normal WP password into an AI chat.

### Step 4 — Show Client Setup

Tabs:
- Claude
- Cursor
- Claude Code
- ChatGPT
- Generic MCP

Generate copyable configuration examples using the current site/gateway URL.

Do not hardcode secrets in displayed JSON.

For clients supporting OAuth:
show only the remote MCP URL.

For direct/local clients using Application Passwords:
instruct users to store secrets in environment variables or the client's secure secret store.

---

# 24. CHATGPT SUPPORT

Provide a `docs/CHATGPT.md`.

Design the gateway so it can be registered as a custom remote MCP app when the user's ChatGPT plan/workspace supports authenticated MCP write actions.

The documentation must:
- explain plan/workspace limitations may apply;
- show how to add the remote MCP endpoint;
- show OAuth authorization flow;
- explain requested scopes;
- test read first;
- create a draft second;
- test publish approval last.

Never claim every ChatGPT plan supports custom write-enabled MCP.

---

# 25. CLAUDE SUPPORT

Provide `docs/CLAUDE.md`.

Support remote HTTP MCP with OAuth where the current Claude client permits it.

Also document a generic local proxy/direct method if needed.

Test:
- list courses;
- get course;
- create draft page;
- create draft course;
- import course;
- request publish.

---

# 26. CURSOR SUPPORT

Provide `docs/CURSOR.md`.

Support remote Streamable HTTP MCP.

Example config shape:

```json
{
  "mcpServers": {
    "altus": {
      "url": "https://mcp.altusgulf.com/mcp"
    }
  }
}
```

If OAuth client configuration is required, document it without hardcoding secrets.

Also document direct WordPress MCP / local proxy option for developers.

---

# 27. SYSTEM HEALTH

Admin System Health must test:

- REST API reachable;
- plugin database schema;
- WordPress cron;
- write permissions;
- media uploads;
- Abilities API available;
- registered ALTUS ability count;
- MCP Adapter installed/active;
- MCP endpoint response;
- gateway response;
- OAuth metadata;
- audit table;
- approval table;
- active LMS adapter;
- PHP extensions needed;
- HTTPS.

Add "Copy diagnostics" button that excludes secrets.

---

# 28. DATABASE TABLES

Avoid unnecessary tables.

Custom tables are acceptable for:
- audit;
- approvals;
- OAuth/gateway state if hosted in WordPress;
- import jobs;
- learner progress only if Native ALTUS model requires it and postmeta would be inefficient.

Use dbDelta-compatible migrations.

Maintain plugin schema version.

Never drop tables automatically on plugin deactivation.

On uninstall:
- default to preserve data;
- allow explicit "Delete plugin data on uninstall" setting with strong warning.

---

# 29. BACKGROUND JOBS

Large course imports may exceed web request limits.

Implement resumable background jobs.

Preferred:
- Action Scheduler if available/dependency justified;
- otherwise WordPress cron/custom queue with safe locks.

Job must expose:
- job ID;
- progress percent;
- current step;
- created IDs;
- warnings;
- error;
- retry state.

Repeated requests must be idempotent where possible.

Use idempotency keys for imports and composite create operations.

---

# 30. DUPLICATE PROTECTION

Before creating pages/courses:
- compare slug;
- normalized title;
- translation group;
- optional external/import ID.

If likely duplicate exists:
return candidates and do not silently create a duplicate.

Support explicit:
- `create_anyway`;
- `update_existing_id`.

---

# 31. VERSIONING AND CONFLICT CONTROL

For mutable content expose:
- `modified_gmt`;
- revision ID/hash where possible;
- content hash.

Update input should accept:
`expected_modified_gmt` or `expected_hash`.

If mismatch:
return `409 conflict` and the current version metadata.

This is essential for AI agents.

---

# 32. SEO COMPATIBILITY

Do not make the plugin depend on one SEO plugin.

Detect:
- Yoast SEO;
- Rank Math;
- All in One SEO.

Create adapter interface.

Support:
- SEO title;
- meta description;
- canonical URL;
- social image where safe.

If unsupported:
store ALTUS-neutral SEO meta only if the site owner enables it.

---

# 33. COURSE CERTIFICATES

Native model certificates:
- course title;
- learner name;
- completion date;
- certificate ID;
- verification URL;
- optional expiry;
- QR code optional;
- ALTUS/property branding.

Verification endpoint must disclose only necessary information.

Do not expose learner email publicly.

---

# 34. WHITE-LABEL READINESS

The learning platform may later serve multiple hotel properties.

Prepare architecture for:
- organization/property ID;
- property logo;
- property name;
- brand color variables;
- localized certificate;
- property-specific course visibility.

Do not build full multi-tenancy in v1 unless needed.

Instead:
- add extension points;
- avoid hardcoding a single hotel name in data structures.

ALTUS remains the platform owner.

---

# 35. PERMISSIONS MATRIX

Implement and document.

Example:

| Operation | Contributor | Editor | Learning Manager | Administrator |
|---|---:|---:|---:|---:|
| Read public content | Yes | Yes | Yes | Yes |
| Create page draft | No | Yes | Optional | Yes |
| Edit course draft | No | Optional | Yes | Yes |
| Upload media | No | Yes | Yes | Yes |
| Publish page | No | WordPress rules | No by default | Yes |
| Publish course | No | No | Optional | Yes |
| Manage MCP | No | No | No | Yes |
| View audit | No | No | Optional | Yes |
| Change security settings | No | No | No | Yes |

Actual implementation must use capabilities, not role-name checks.

---

# 36. MCP ANNOTATIONS

For MCP tools/abilities, set accurate behavior hints.

Examples:

Read tools:
- `readOnlyHint: true`
- `destructiveHint: false`
- `idempotentHint: true`

Draft create:
- `readOnlyHint: false`
- `destructiveHint: false`
- `idempotentHint: false`

Update:
- `readOnlyHint: false`
- `destructiveHint: false`

Trash:
- `destructiveHint: true`

Publish:
- not destructive, but sensitive and approval-gated.

Do not misuse annotations as a substitute for server-side authorization.

---

# 37. ERROR CONTRACT

Every API/MCP error should be structured.

Example:

```json
{
  "success": false,
  "error": {
    "code": "altus_permission_denied",
    "message": "You do not have permission to publish this course.",
    "details": {
      "required_capability": "altus_publish_courses"
    },
    "retryable": false
  },
  "request_id": "req_..."
}
```

Standard codes:
- `altus_auth_required`
- `altus_permission_denied`
- `altus_validation_error`
- `altus_not_found`
- `altus_conflict`
- `altus_duplicate_detected`
- `altus_builder_write_not_supported`
- `altus_lms_write_not_supported`
- `altus_approval_required`
- `altus_approval_expired`
- `altus_rate_limited`
- `altus_import_failed`
- `altus_media_fetch_blocked`
- `altus_internal_error`

Do not return PHP stack traces to remote clients in production.

---

# 38. TESTING

No "complete" claim until tests pass.

## PHP Unit Tests
Test:
- registration;
- capabilities;
- sanitization;
- REST permissions;
- course CRUD;
- relationships;
- translation linkage;
- audit writing;
- approval token validation;
- import parser;
- duplicate protection;
- conflict handling;
- SSRF guard.

## Integration Tests
Create a real WordPress test environment.

Test:
- activate plugin;
- database migrations;
- create course;
- add modules/lessons;
- quiz;
- Arabic course;
- upload PDF;
- preview;
- request approval;
- publish;
- trash/restore.

## MCP Contract Tests
Test:
- tool discovery;
- schemas;
- authentication;
- unauthorized call;
- read call;
- draft creation;
- approval-required publishing;
- malformed input;
- idempotency.

## E2E Browser Tests
Use Playwright.

Test admin:
- dashboard;
- course import;
- dry-run;
- create draft;
- approval UI;
- audit filtering;
- settings;
- system health.

Test frontend:
- English LTR;
- Arabic RTL;
- mobile course page;
- lesson navigation;
- certificate verification.

---

# 39. PERFORMANCE TARGETS

Admin/plugin should not noticeably slow unrelated frontend pages.

Rules:
- load admin JS only on ALTUS plugin screens;
- lazy-load large admin modules;
- cache read-only counts/health checks;
- paginate audit logs;
- index custom tables;
- do not autoload large WordPress options;
- do not fetch remote gateway on every frontend request.

API target:
- normal read operations should be fast enough for interactive AI use;
- long imports use background jobs.

---

# 40. ACCESSIBILITY

Admin and frontend:
- semantic HTML;
- keyboard navigation;
- visible focus;
- ARIA only when necessary;
- sufficient contrast;
- accessible form labels;
- screen-reader status for background jobs;
- Arabic RTL tested.

Target WCAG 2.2 AA where reasonable.

---

# 41. PRIVACY

Do not send WordPress/site content to an AI provider from the plugin unless the site administrator explicitly configures such a provider and initiates that feature.

The plugin is primarily a bridge:
AI client -> authorized MCP/REST call -> WordPress.

Do not silently train on or replicate customer content.

Add privacy documentation.

Do not log full course/page content by default in audit.
Log hashes and concise summaries.

---

# 42. NO VENDOR LOCK-IN

The plugin must not depend on Claude, OpenAI, or Cursor APIs for core CRUD functionality.

Any standards-compatible MCP client should be able to use the gateway.

AI-provider-specific configuration belongs in documentation/adapters only.

---

# 43. ADMIN SETTINGS

Settings sections:

## General
- default content status = draft;
- default language;
- native learning mode on/off;
- default passing score;
- default certificate behavior.

## MCP
- enable/disable MCP integration;
- gateway URL;
- direct MCP status;
- allowed scopes;
- per-client access;
- revoke connection.

## Safety
- always require publish approval;
- allow trash actions;
- max batch size;
- max upload size;
- remote media import allowed;
- allowed remote media hosts optional;
- approval expiry.

## Audit
- retention period;
- export;
- privacy mode.

## Integrations
- active LMS;
- translation plugin;
- SEO plugin;
- MCP Adapter.

---

# 44. CLIENT REGISTRY

Maintain known authorized clients.

Fields:
- client ID;
- display name;
- created date;
- last used;
- scopes;
- WordPress user;
- status;
- revoke.

Examples:
- "Hussam Cursor"
- "ALTUS Claude"
- "ALTUS ChatGPT"

Never show raw refresh tokens.

---

# 45. IMPORT/EXPORT

Provide:

## Export Course
- JSON;
- Markdown package;
- ZIP containing manifest + referenced local resources where licensing allows.

## Import Course
- validate before write;
- map IDs;
- preserve source external ID;
- avoid duplicates;
- dry-run required for bulk import.

This allows courses generated by Claude/ChatGPT/Cursor to be moved into ALTUS safely.

---

# 46. OPTIONAL WORDPRESS AI CLIENT INTEGRATION

This is OPTIONAL and must not block core delivery.

If the installed WordPress version supports the official WordPress AI Client / Connectors architecture, add a separate optional integration for admin-side assisted drafting.

Examples:
- summarize lesson;
- suggest quiz questions;
- generate draft description.

Requirements:
- provider configured by administrator;
- no provider hardcoded;
- explicit action;
- results inserted as draft;
- clear AI-generated status;
- no automatic publication.

Do not confuse this with MCP.
MCP lets external AI use WordPress.
WordPress AI Client lets WordPress call AI.

Keep the architecture separate.

---

# 47. BUILD AND RELEASE

Commands should include equivalents of:

```bash
composer install
composer test
composer lint

npm install
npm run build
npm test
npm run lint

npm run test:e2e
npm run build:plugin
```

`npm run build:plugin` must output:

`dist/altus-ai-publisher.zip`

The ZIP must:
- install directly via WordPress Plugins -> Add Plugin -> Upload Plugin;
- exclude dev files;
- include production assets;
- include vendor dependencies if required;
- activate without fatal errors.

---

# 48. INSTALLATION UX

After activation:
- do not redirect unexpectedly on AJAX/CLI;
- show admin notice with "Start Setup";
- setup wizard performs environment checks;
- create custom tables;
- register capabilities;
- optionally recommend/install the official MCP Adapter but do not silently install external plugins without approval.

Deactivation:
- no data deletion.

Uninstall:
- preserve data unless explicit configured removal.

---

# 49. DOCUMENTATION DELIVERABLES

Create:

### `README.md`
Architecture and developer overview.

### `docs/INSTALL.md`
Install plugin and gateway.

### `docs/MCP-SETUP.md`
MCP concepts, endpoints, auth, testing.

### `docs/CHATGPT.md`
Current supported integration pattern and limitations.

### `docs/CLAUDE.md`
Remote MCP setup.

### `docs/CURSOR.md`
Remote and direct setup.

### `docs/COURSE-IMPORT.md`
Markdown/JSON schemas and examples.

### `docs/API.md`
REST endpoints and WordPress abilities.

### `docs/SECURITY-MODEL.md`
Threat model, capabilities, approvals, credential storage.

### `docs/TROUBLESHOOTING.md`
REST blocked, Authorization header stripped, firewall, permalink issues, Application Password disabled, MCP Adapter missing, OAuth callback mismatch.

---

# 50. REQUIRED EXAMPLE DATA

Ship development-only sample fixtures, not automatically installed in production:

Course:
**Housekeeping Essentials**

Modules:
1. Professional Appearance
2. Entering the Guest Room
3. Room Cleaning Sequence
4. Bathroom Cleaning
5. Linen and Bed Making
6. Lost & Found
7. Pest Signs and Reporting

Languages:
- English
- Arabic

Include quiz fixtures.

Do not use customer-sensitive data.

---

# 51. ACCEPTANCE SCENARIOS

The build is NOT accepted unless the following scenarios work.

## Scenario A — Page Creation
From an MCP client:

"Create an English page titled Hotel Asset Performance, add supplied content, set it as draft."

Expected:
- one draft;
- no duplicate;
- preview URL returned;
- audit event.

## Scenario B — Arabic Page
"Create the Arabic translation and link it."

Expected:
- independent Arabic page;
- RTL;
- translation relationship;
- English unchanged.

## Scenario C — Full Course
"Create a Housekeeping course with 3 modules, 9 lessons, a 10-question quiz and 80% pass mark."

Expected:
- complete hierarchy;
- draft;
- ordered correctly;
- no missing relationships;
- preview URLs.

## Scenario D — Markdown Import
Upload a `.md` course.

Expected:
- validation;
- dry-run;
- explicit confirmation;
- draft import;
- job progress;
- report.

## Scenario E — Update Conflict
Two clients edit same lesson.

Expected:
- stale update gets 409 conflict;
- no silent overwrite.

## Scenario F — Publish Safety
AI asks to publish.

Expected:
- approval required;
- admin reviews;
- only approved revision can publish.

## Scenario G — Unauthorized User
Subscriber tries write operation.

Expected:
- 403;
- no data change;
- audit security event where appropriate.

## Scenario H — Malicious Media URL
Request import from `http://127.0.0.1/...`.

Expected:
- blocked as SSRF.

## Scenario I — ChatGPT/Claude/Cursor Gateway
OAuth-authorized client lists courses and creates a draft.

Expected:
- bearer token accepted;
- scopes enforced;
- WordPress user's permissions enforced;
- tokens never exposed to model output.

---

# 52. UI QUALITY BAR

Do not produce a crude developer settings page.

The admin application should look native to modern WordPress:
- WordPress components;
- clear cards/tables;
- useful empty states;
- progress indicators;
- confirmations;
- inline validation;
- responsive;
- no unnecessary animation.

Primary users may not be developers.

Use simple language:
- "Create Draft"
- "Preview"
- "Request Approval"
- "Publish"
- "Import Course"
- "Connection Status"

Avoid exposing protocol jargon unless in the MCP settings screen.

---

# 53. SOURCE-CODE QUALITY

Requirements:
- typed PHP where compatible;
- namespaces;
- small services;
- dependency injection where useful;
- no god classes;
- no business logic inside REST controllers;
- no duplicate validation logic;
- TypeScript strict mode;
- ESLint/Prettier;
- PHP_CodeSniffer;
- PHPStan if feasible;
- meaningful exceptions/error objects;
- tests for critical paths.

Do not leave TODO placeholders in release code.

Do not return mocked success responses.

---

# 54. IMPLEMENTATION PHASES

Implement sequentially.

## Phase 0 — Discovery
- inspect current ALTUS WordPress environment on staging;
- identify theme;
- identify page builder;
- identify multilingual plugin;
- identify LMS;
- identify SEO plugin;
- document findings.

Do not modify production.

## Phase 1 — Plugin Foundation
- bootstrap;
- settings;
- permissions;
- database migrations;
- admin shell;
- health check.

## Phase 2 — Content CRUD
- pages;
- posts;
- media;
- search;
- version/conflict handling;
- audit.

## Phase 3 — Learning CRUD
- native LMS;
- course builder;
- modules;
- lessons;
- quizzes;
- questions;
- translations.

## Phase 4 — Import/Export
- Markdown;
- JSON;
- ZIP manifest;
- dry-run;
- jobs.

## Phase 5 — Abilities + MCP
- abilities;
- MCP Adapter compatibility;
- direct connection tests.

## Phase 6 — Remote Gateway + OAuth
- MCP endpoint;
- OAuth;
- scopes;
- site connector;
- client setup.

## Phase 7 — Safety
- approvals;
- revision hash;
- publish gate;
- trash;
- audit review.

## Phase 8 — Frontend Templates
- course;
- lesson;
- program;
- certificate;
- RTL.

## Phase 9 — QA
- tests;
- security review;
- performance;
- accessibility;
- staging.

## Phase 10 — Release
- ZIP;
- gateway deployment guide;
- migration guide;
- rollback;
- release notes.

At the end of every phase:
1. run tests;
2. show files changed;
3. show commands run;
4. show test result;
5. show risks/open items;
6. create a checkpoint/commit.

Do not proceed over failing baseline tests.

---

# 55. REQUIRED FINAL DELIVERY

Deliver all of the following:

1. Complete source repository.
2. Installable:
   `dist/altus-ai-publisher.zip`
3. Gateway source and Dockerfile.
4. `.env.example` with NO real secrets.
5. Database migration code.
6. Admin frontend.
7. Frontend templates.
8. WordPress Abilities.
9. REST API.
10. MCP gateway.
11. OAuth.
12. Tests.
13. Documentation.
14. Course import examples.
15. Client configuration examples.
16. Security review checklist.
17. Staging deployment instructions.
18. Production deployment instructions.
19. Rollback instructions.
20. Final acceptance report.

---

# 56. FINAL ACCEPTANCE REPORT FORMAT

Create:

`ALTUS_AI_PUBLISHER_ACCEPTANCE_REPORT.md`

Include:

```markdown
# ALTUS AI Publisher — Acceptance Report

## Version
## Build Date
## Git Commit

## Environment
- WordPress:
- PHP:
- Database:
- Theme:
- LMS:
- Multilingual plugin:
- SEO plugin:

## Build Status
- PHP lint:
- PHP unit:
- Integration:
- TypeScript:
- Gateway unit:
- MCP contract:
- Playwright E2E:
- WordPress Coding Standards:

## Feature Matrix
| Feature | Status | Evidence |
|---|---|---|

## MCP Tools
| Tool | Read/Write | Approval | Tested |
|---|---|---|---|

## Security Controls
| Control | Status | Evidence |
|---|---|---|

## Known Limitations

## Staging Validation

## Production Readiness
```

Do not say "production ready" if any blocker remains.

---

# 57. IMPORTANT NON-NEGOTIABLE RULES

1. Never use the user's normal WordPress password.
2. Never hardcode secrets.
3. Never automatically publish AI-generated content.
4. Never permanently delete content in v1.
5. Never expose arbitrary SQL/shell/PHP execution to MCP.
6. Never bypass WordPress capability checks.
7. Never trust MCP annotations as authorization.
8. Never overwrite page-builder data without a safe adapter.
9. Never modify production during development.
10. Never claim a test passed unless it was executed.
11. Never leave fake/mock implementations in the release build.
12. Never copy proprietary WPVibe code or prompts.
13. Use public standards and official WordPress APIs.
14. Prefer drafts, previews, approvals, auditability, and rollback.
15. Keep ALTUS data portable and vendor-neutral.

---

# 58. STARTING INSTRUCTION TO THE CODING AGENT

Begin with **Phase 0 and Phase 1 only**.

First:
1. inspect the repository/environment provided;
2. create a baseline report;
3. confirm WordPress/PHP versions and existing plugins;
4. identify whether the current ALTUS site already has custom post types for courses;
5. identify multilingual/LMS/page-builder/SEO systems;
6. propose the final adapter map;
7. scaffold the plugin;
8. implement activation, capabilities, health checks, settings, audit schema, admin shell;
9. run tests;
10. produce a checkpoint report.

Do NOT start the remote gateway until the WordPress plugin foundation and data model are stable.

If the current ALTUS site uses a custom learning platform rather than standard WordPress post types, do not destroy or replace it. Build a compatibility adapter or migration plan and show it before writing data.

The goal is a production-quality ALTUS-owned AI publishing and learning-management bridge that makes course and page creation from AI tools simple, safe, auditable, and maintainable.
