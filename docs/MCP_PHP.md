# ALTUS MCP server (PHP, built in)

ALTUS serves the Model Context Protocol directly from the CodeIgniter application. There is no Node
process: the MCP endpoint, the OAuth 2.1 authorization server, consent, tokens, approvals and audit all
run in PHP and use the same services as the ALTUS editors (`Ha_publishing_service`, `Ha_content_studio`,
`Ha_website_studio`, `Ha_document_publisher`, `Ha_knowledge`, `Ha_mcp_content`).

The Node gateway in `mcp-gateway/` is **deprecated and optional**. It is kept for reference only.

## Install / enable

1. Back up the database, confirm `application/config/database.local.php` points at the database you mean.
2. Run the additive migration: `php index.php ha_cli migrate` (adds migration `20260101000032_mcp_php`).
   Rollback: `php index.php ha_cli rollback 20260101000031` (drops only the seven `ha_mcp_*` tables it created).
3. Switch it on: `ALTUS_MCP_ENABLED=1` (environment) or, on a workstation only, `application/config/ha_publisher.local.php`
   returning `array('mcp_enabled' => true)`. With the switch off every MCP/OAuth endpoint answers `503`.
4. Production: set `ALTUS_MCP_ISSUER=https://your-domain[/path]` so the issuer never depends on the request `Host`
   header, and serve over HTTPS.
5. Apache must pass the `Authorization` header to PHP. The shipped `.htaccess` does this
   (`RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`) and allows `/.well-known/` while still
   blocking other hidden paths (`.git`, `.env`).

Optional settings (`application/config/ha_publisher.php`): `ALTUS_MCP_RATE_LIMIT` (requests/minute per client+user, default 120),
`ALTUS_MCP_IP_RATE_LIMIT` (per IP, all endpoints, default 300), `ALTUS_MCP_ALLOWED_ORIGINS` (extra browser origins for `/mcp`),
`ALTUS_MCP_ALLOW_SELF_APPROVAL` (default off).

## Endpoints

| Endpoint | Purpose |
|---|---|
| `POST /mcp` | Streamable HTTP, JSON-RPC 2.0 (single or batch). JSON responses; `202` for notifications only. |
| `GET /mcp` | `405` (no server-initiated SSE stream). |
| `DELETE /mcp` | Ends the session named by `Mcp-Session-Id` (`204`). |
| `GET /.well-known/oauth-protected-resource` and `/.well-known/oauth-protected-resource/mcp` | RFC 9728 metadata (`resource = {issuer}/mcp`). |
| `GET /.well-known/oauth-authorization-server` | RFC 8414 metadata. |
| `GET /.well-known/openid-configuration` | Same metadata plus the fields OIDC-discovery parsers require (needed when ALTUS runs in a sub-directory, see Troubleshooting). OpenID Connect sign-in is not offered; `/.well-known/jwks.json` is an empty set. |
| `POST /oauth/register` | RFC 7591 dynamic registration, public clients only (`token_endpoint_auth_method: none`). |
| `GET /oauth/authorize` | Validates the request, then sends the browser to ALTUS sign-in (two-factor included) and the consent screen `hkp/cms/mcp_authorize`. |
| `POST /oauth/token` | `authorization_code` (PKCE S256 required) and `refresh_token` (rotation). Form or JSON body. |
| `POST /oauth/revoke` | RFC 7009. Revoking a refresh token revokes the whole grant. |

Protocol details: `MCP-Protocol-Version` accepted values `2025-06-18`, `2025-03-26`, `2024-11-05` (others → `400`; missing → treated
as `2025-03-26`). `initialize` negotiates the version and returns `Mcp-Session-Id`; later requests may send it (unknown or ended
sessions → `404`). `Origin`, when present, must be the issuer origin or an allowed origin (`403` otherwise). Bearer tokens are
accepted only in the `Authorization` header. Without a valid token the response is `401` with
`WWW-Authenticate: Bearer resource_metadata="{issuer}/.well-known/oauth-protected-resource/mcp", scope="…"`.
JSON-RPC errors: `-32700` parse, `-32600` invalid request, `-32601` unknown method, `-32602` unknown tool / bad params,
`-32002` unknown resource. Tool failures are results with `isError: true` and `structuredContent.error = {code, message, status, details, retryable}`.
Methods: `initialize`, `ping`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `resources/templates/list`, `prompts/list` (empty), `logging/setLevel`, `completion/complete`.

## OAuth

- Authorization code + PKCE **S256 only**; exact `redirect_uri` match (registered: https, http loopback, or private-use schemes such as `cursor://`); `state` returned; `iss` returned (RFC 9207).
- Resource indicator (RFC 8707): `resource` must be `{issuer}/mcp`; tokens carry that audience and `/mcp` rejects any other.
- Access tokens: opaque, 5 minutes. Refresh tokens: 30 days, **rotated on every use**; presenting a used refresh token
  (or a used authorization code) revokes the grant and all its tokens. Codes live 60 seconds and are single-use (a failed attempt also consumes them).
- Codes and tokens are stored only as SHA-256 hashes (`ha_mcp_auth_code`, `ha_mcp_token`); grants in `ha_mcp_grant`; clients in `ha_mcp_client`.
- Every MCP request re-checks: token not expired/revoked, grant and client not revoked, user active (users.status and ha_profile.status),
  and the user's **current** permissions (effective scopes = granted scopes ∩ scopes the permissions allow now). Tenant scope is enforced by the
  native services through `Ha_auth`.

### Scopes

| Scope | Granted only if the user holds one of | Allows |
|---|---|---|
| `altus.read` | (any signed-in user) | read/search/list/get, previews, approval status, own audit entries |
| `altus.content.write` | `cms_pages.update`, `articles.update`, `knowledge.create`, `knowledge.update`, `programs.update`, `learning_paths.update` | drafts of pages, sections, articles, topics, programs, paths, SOPs, navigation, theme; document publisher |
| `altus.course.write` | `courses.create`, `courses.update`, `lessons.create`, `lessons.update`, `assessments.update`, `question_banks.create` | drafts of courses, course sections, lessons, quizzes, questions |
| `altus.media.write` | `cms_pages.update`, `media.create` | media upload and attach |
| `altus.publish` | `cms_pages.publish`, `courses.publish`, `articles.publish`, `programs.publish`, `learning_paths.publish` | request approval, publish an approved change, restore from archive |
| `altus.admin` | platform-scoped + `system.configure` | full audit history (with `audit_logs.view`) |

Each tool also runs the existing permission checks of the service it calls (e.g. `courses.update`, `knowledge.update`).

## Tools

All writes are private drafts. There are **no** delete, SQL or shell tools. Writes accept `idempotency_key` (8–100 chars; same key +
same arguments replays the first result with `idempotent_replayed: true`; same key + different arguments → `idempotency_conflict`).
Updates require `expected_version` (from `altus_get`); a mismatch returns `conflict` (HTTP-equivalent 409) with `current_version`.
Every write returns `object_type`, `object_id`, `status`, `version`, `changed_fields`, `edit_url`, `preview_url`, `warnings`, `audit_id`, `request_id`.

| Tool | Scope | Notes |
|---|---|---|
| `altus_site_info` | read | user, granted/effective scopes, publisher health, types, rules |
| `altus_search` | read | titles across pages, articles, topics, programs, paths, courses, SOPs |
| `altus_list` | read | `type` ∈ page, page_sections, articles, topics, programs, paths, courses, course_sections, lessons, quizzes, questions, sops, media, navigation, site, publisher (`parent_id` for children) |
| `altus_get` | read | draft + published state, `version`, URLs |
| `altus_create` | content/course write | page, catalogue types, course_sections, lessons, quizzes, questions, sops — always draft |
| `altus_update` | content/course write | partial update of drafts (page, catalogue, site, navigation, publisher drafts, structure types, SOP drafts) |
| `altus_page_section` | content write | insert / update / duplicate / hide / show / move / reorder on the page **draft** |
| `altus_reorder` | course write | course sections, lessons, quiz questions |
| `altus_upload_media` / `altus_attach_media` | media write | base64 images into the library; attach to catalogue image, page hero or section image |
| `altus_document_upload` / `_source` / `_status` / `_control` / `_generate` / `_import` | content write (status: read) | AI Document Publisher jobs (cancel, retry, generate, import as draft) |
| `altus_translate` | content write | translate a publisher draft (en/ar) |
| `altus_validate_package` | read | dry-run validation |
| `altus_preview_link` | read | signed time-limited link for published objects, editor preview otherwise |
| `altus_request_publish` | publish | approval request (publish/archive) for page, catalogue types, site, navigation, lessons, quizzes; SOPs → `sop_governance_required` |
| `altus_approval_status` | read | pending / approved / declined / consumed / expired |
| `altus_publish_approved` | publish | carries out an approved change (destructiveHint) |
| `altus_restore_archived` | publish | archived → draft |
| `altus_list_revisions` / `altus_restore_revision` | read / write | pages, catalogue, site, navigation, lessons, SOP versions (list) |
| `altus_sop_workflow` | content write | `new_version`, `submit` (review/approval/publication stay with people) |
| `altus_audit_list` | read | own actions; all users with `altus.admin` + `audit_logs.view` |

Rules enforced by the services:

- Approvals: bound to user, client, operation, object and content version/hash; expire after 10 minutes; single-use; invalid after
  any content change; the requester cannot approve their own request (unless `ALTUS_MCP_ALLOW_SELF_APPROVAL=1`). Reviewers approve in
  **Integrations → Approval requests**.
- Lessons, quizzes, questions and course outlines have no draft layer, so MCP may edit them only while unpublished
  (`published_locked` otherwise). They are published or archived through the same approval flow (lessons, quizzes).
- Identity and tenant are never taken from arguments (`organization_id`, `user_id`, … are rejected).
- Each call is audited (`ha_audit_log.action = mcp.<tool>`) with a summary of type/id/operation — never tokens or payloads. OAuth events use `oauth.*`.
- Rate limits: per client+user and per IP (`429` with `Retry-After`).

## Connect a client

Give clients the URL only — never tokens or secrets. The client discovers OAuth from the `401`, registers itself,
opens your browser for ALTUS sign-in and the consent screen.

- **Claude (claude.ai / Desktop):** Settings → Connectors → *Add custom connector* → `https://your-domain/mcp`
  (claude.ai needs a public HTTPS URL).
- **Claude Code:** `claude mcp add --transport http altus https://your-domain/mcp`, then `/mcp` to authenticate.
- **Cursor:** `~/.cursor/mcp.json` → `{"mcpServers": {"altus": {"url": "https://your-domain/mcp"}}}`
- **Any MCP client:** Streamable HTTP transport, URL `https://your-domain/mcp`.

Admins see the endpoint, setup snippets, registered clients, active grants (with **Revoke access**) and a live self-check of the
metadata endpoints and the `401` challenge under **Integrations** (`hkp/cms/integrations`, *Connections* and *Connection health* tabs).

## Verification

- `php index.php ha_test run mcp_php` — 15 tests / 800+ assertions through the real `Ha_mcp_server::dispatch()` boundary:
  registration validation, full OAuth flow, wrong verifier, code replay, refresh rotation, reuse detection, revocation, expiry,
  audience mismatch, scope capping and restriction, revoked user, tenant isolation, protocol (sessions, batch, errors, Origin,
  versions), CRUD for every content type, conflicts, idempotency, approval expiry / single use / changed content / self-approval,
  switch off, rate limit, admin screen, migration down/up.
- `node tests/mcp_sdk_e2e.mjs http://localhost/atlas/atlas` — official MCP TypeScript SDK client (`@modelcontextprotocol/client`)
  over real HTTP, with a real browser sign-in (Playwright/Edge) and consent click. Uses the SDK installed in `mcp-gateway/node_modules`
  and Playwright from `e2e/node_modules`; credentials from `ALTUS_E2E_EMAIL` / `ALTUS_E2E_PASSWORD`.

## Troubleshooting

- **401 on every call with a fresh token** — the `Authorization` header is not reaching PHP. Keep the `.htaccess` rule above; on
  PHP-FPM/CGI hosts also enable `CGIPassAuth On` in the vhost (it is not in `.htaccess` because some hosts reject it with a 500).
- **403 from Apache on `/.well-known/...`** — a rule denies dot-paths. The shipped `.htaccess` exempts only `.well-known`.
- **Client cannot discover the authorization server** when ALTUS runs in a sub-directory (e.g. `/atlas/atlas`): RFC 8414 path-aware
  discovery looks at `https://host/.well-known/oauth-authorization-server/atlas/atlas`, outside the application. Clients that follow
  the `resource_metadata` link from the `401` (Claude, Claude Code, the SDK) work; for others either serve ALTUS at the domain root or
  add a web-server rewrite from `/.well-known/oauth-authorization-server/<path>` to `<path>/.well-known/oauth-authorization-server`.
  The `openid-configuration` alias covers clients that try `<path>/.well-known/openid-configuration`.
- **`503 mcp_disabled`** — set `ALTUS_MCP_ENABLED=1`.
- **`insufficient_scope`** — reconnect and approve the scope, or the user lost the permission (effective scopes shrink immediately).
- **`published_locked`** — the lesson/quiz/outline is live; request an archive approval or edit it in the module editor.
- **`conflict`** — re-read with `altus_get` and retry with the returned `version`.
- **Redirect URI rejected** — plain `http` is accepted only for `localhost`, `127.0.0.1` and `[::1]`.

## Admin console (/hkp/mcp, migration 033)

Platform administrators (system scope + `settings.update`) get **MCP & AI connections** in the sidebar (Create & manage, and Platform).

- **Access levels**: Read (`altus.read`), Write (read + content/course/media write), Full (all incl. `altus.publish`; `altus.admin` only if the user holds it). A level is a ceiling; the user's permissions are re-checked on every call.
- **Consent**: the OAuth consent screen shows the three levels as cards. The console sets the pre-selected and highest level offered (`ha_mcp_setting`); each OAuth client can be capped (`ha_mcp_client.max_level`, enforced on every request).
- **Connections**: lower a grant's level (downgrade only, effective on the next request, refresh keeps working) or revoke grants/apps.
- **Personal connections**: admin-issued `altus_pt_…` bearer tokens for a chosen user + level (shown once, SHA-256 hashed, 1–365 day expiry, revocable).
- **Test console**: JSON-RPC as the signed-in admin through the real `call()`/`run_tool()` path at a chosen level; writes default to dry-run (transaction rolled back; file/AI tools refused). Smoke test runs a read/write/full matrix (12 checks).
- **Activity**: `ha_mcp_call_log` (one row per call; status ok/error/denied, latency, request id; 90-day retention) with filters and a detail drawer.
- **Setup**: copyable configs for Claude.ai, Claude Desktop, Claude Code, Cursor, generic, and personal-token header variants.
