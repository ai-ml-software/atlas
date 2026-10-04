# ALTUS MCP gateway runbook

> **Deprecated (optional).** ALTUS now serves MCP and OAuth natively in PHP at `/mcp` — no Node process
> is required. See [MCP_PHP.md](MCP_PHP.md). This Node gateway is kept only for reference; do not deploy it
> for new installations.

The gateway (`mcp-gateway/`) is a separate Node 24 / TypeScript service. It runs
the official MCP SDK over Streamable HTTP at `$ALTUS_MCP_URL/mcp`, an OAuth 2.1
authorization server (`oidc-provider`, PKCE S256 required) at
`$ALTUS_MCP_URL/oauth`, and calls the native ALTUS API `/api/publisher/v1` with
short-lived (≤60 s) delegated credentials. Login and two-factor authentication
are the existing ALTUS login: the OAuth consent step redirects to
`/hkp/cms/mcp_login`.

```
MCP client ──OAuth PKCE──▶ gateway /oauth ──redirect──▶ ALTUS login + 2FA + consent
MCP client ──Bearer──────▶ gateway /mcp ──HS256 JWT (kid, ≤60s)──▶ /api/publisher/v1
```

## 1. Install

```powershell
Set-Location C:/laragon/www/atlas/atlas/mcp-gateway
node --version     # require Node 24; .nvmrc records the major version
npm.cmd ci
npm.cmd run build
npm.cmd test           # unit tests; the live contract test is skipped by default
node tools/generate-key.mjs   # creates private-jwks.json (OAuth token signing key, keep private)
Copy-Item .env.example .env   # then edit .env
```

Native side (once per database, after a backup — see §7):

```powershell
$altusPhp = 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe'
& $altusPhp index.php ha_cli migrate   # includes publisher migrations 27, 29 and 30 (drafts/jobs, OAuth grants, approvals, retries and connections)
```

## 2. Environment (`.env` and the PHP server environment)

| Variable | Where | Meaning |
|---|---|---|
| `ALTUS_MCP_ENABLED` | gateway **and** PHP | Feature switch. Anything but `1` → gateway `/mcp` returns 503 `mcp_disabled`; native API and bridge return 503. |
| `ALTUS_MCP_URL` | gateway **and** PHP | Public gateway origin. PHP checks it as the JWT `iss`. HTTPS required when `NODE_ENV=production`. |
| `ALTUS_NATIVE_URL` | gateway | ALTUS base URL (`https://…/` in production). |
| `ALTUS_MCP_SECRET` | gateway **and** PHP | Current HS256 secret, ≥32 random characters. |
| `ALTUS_MCP_KEY_ID` | gateway **and** PHP | Key id put in the JWT `kid` header (e.g. `2026-10`). |
| `ALTUS_MCP_SECRET_PREVIOUS`, `ALTUS_MCP_KEY_ID_PREVIOUS` | gateway **and** PHP | Rotation window only (§4). |
| `ALTUS_MCP_ALLOW_SELF_APPROVAL` | PHP | `1` lets a requester approve their own MCP request. Default off (separation of duties). |
| `ALTUS_DB_*` | gateway | MySQL for the OAuth store (`ha_oauth_store`) and live user checks. Use the same database as ALTUS. |
| `ALTUS_OAUTH_JWKS_FILE` | gateway | Path to `private-jwks.json`. |
| `ALTUS_OAUTH_CLIENTS` | gateway | JSON array of registered OAuth clients (§3). |
| `ALTUS_COOKIE_KEYS` | gateway | Comma-separated cookie keys, newest first. |
| `ALTUS_TRUST_PROXY` | gateway | `1` behind a TLS-terminating reverse proxy. |

PHP reads these with `getenv()` in `application/config/ha_publisher.php`. Under
Apache set them with `SetEnv` in the vhost (never in a committed file), or in an
untracked `application/config/ha_publisher.local.php` returning an array of the
same config keys.

Generate a secret: `node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"`.

## 3. OAuth client registration

Clients are static (no dynamic registration). Add an entry to
`ALTUS_OAUTH_CLIENTS` and restart the gateway:

```json
[{"client_id":"altus-desktop","redirect_uris":["http://127.0.0.1:3200/callback"],
  "grant_types":["authorization_code","refresh_token"],"response_types":["code"],
  "token_endpoint_auth_method":"none"}]
```

For local native configuration, copy `application/config/ha_publisher.local.example.php` to the ignored `ha_publisher.local.php` if it does not already exist. Set `mcp_enabled=true`, `gateway_url`, `gateway_secret` and `gateway_key_id` to the **same** URL, secret and key id as `.env`. Keep `allow_self_approval=false`. On Apache, restart after environment changes.

Start from `mcp-gateway/`:

```powershell
npm.cmd start
Invoke-RestMethod http://127.0.0.1:3100/health
Invoke-RestMethod http://127.0.0.1:3100/.well-known/oauth-protected-resource/mcp
```

Open ALTUS **Integrations ? Connection health**. All three probes must pass. In a compatible remote MCP client, add `http://127.0.0.1:3100/mcp` for local testing or your public HTTPS `/mcp` URL for a deployed service. A remote cloud client cannot reach your workstation loopback URL; configure a reachable HTTPS gateway for that client. Enter the registered client id if requested. Its exact callback URL must match `redirect_uris`; the example `altus-local` callback is for a locally implemented client, not a universal vendor callback. Confirm each vendor's callback in its current connector settings before registration.

Complete OAuth in the browser: ALTUS login ? existing 2FA if enabled ? scope consent ? return to the client. Call `altus_health`, `altus_list_objects` and `altus_get_object`; confirm last-seen activity in **Connections**. Use the steps in [admin workflows](MCP_ADMIN_WORKFLOWS.md) to save, review and publish a draft. No secret or raw delegated JWT needs to be pasted into the client.

Clients must send PKCE S256 and `resource=$ALTUS_MCP_URL/mcp`. Discovery:
`/.well-known/oauth-protected-resource/mcp` and
`/oauth/.well-known/openid-configuration`. Scopes: `altus.read`,
`altus.content.write`, `altus.course.write`, `altus.media.write`,
`altus.publish`, `altus.admin`; at consent they are limited to what the user's
current ALTUS permissions allow, and every native call rechecks permissions and
tenant scope. Access tokens live 300 s, refresh tokens rotate on use (24 h).

## 4. Secrets and rotation

Delegated credentials carry `kid`. To rotate without downtime:

1. Generate a new secret. On **PHP first**: set `ALTUS_MCP_SECRET_PREVIOUS`/`ALTUS_MCP_KEY_ID_PREVIOUS` to the old pair and `ALTUS_MCP_SECRET`/`ALTUS_MCP_KEY_ID` to the new pair. PHP now accepts both (by `kid`).
2. Update the gateway the same way and restart it. It signs with the new key.
3. After at least 60 s (longest delegated lifetime) remove the previous pair on both sides.
4. Confirm *Integrations → Connection health* lists only the new key id, and `GET /health` shows `key_id`.

Rotate `private-jwks.json` by adding a new key first in the `keys` array (old
key kept for 24 h so existing refresh tokens still verify), then removing it.
Never commit `.env`, `private-jwks.json` or the local PHP override.

## 5. Deploy

- Docker: `docker build -t altus-mcp mcp-gateway && docker run --env-file mcp-gateway/.env -v /secure/private-jwks.json:/app/private-jwks.json:ro -p 127.0.0.1:3100:3100 altus-mcp`
- Or systemd: `node --env-file=.env dist/server.js` as an unprivileged user, behind nginx/Apache TLS with `ALTUS_TRUST_PROXY=1`.
- Set `NODE_ENV=production`; the gateway refuses non-HTTPS URLs then.
- Smoke: `curl $ALTUS_MCP_URL/health` → `{"status":"ready",...}`; unauthenticated `POST /mcp` → 401 with `WWW-Authenticate: Bearer resource_metadata=…`. Then open *Integrations → Connection health* in ALTUS; all three probes should be OK.

## 6. Rollback

1. Disable first: set `ALTUS_MCP_ENABLED=0` in PHP (immediately blocks all MCP calls with 503) and stop the gateway.
2. Redeploy the previous gateway build (`dist/` or image tag) if needed; the OAuth store schema is unchanged by this release.
3. Database: migration 030 is additive and removing it is **not** needed to disable MCP. If you must, `php index.php ha_cli rollback 20260101000029` rolls back every migration newer than that version (check `ha_cli status` first so no other newer migration is reverted); 030's `down()` drops only `ha_mcp_connection` and the three added `ha_publisher_approval` columns. Restore from the backup in §7 for anything else.
4. Revoke all grants if credentials may be compromised: `DELETE FROM ha_oauth_store;` (forces re-consent) and rotate `ALTUS_MCP_SECRET`.

## 7. Backup before migrating

```powershell
$stamp = Get-Date -Format yyyyMMdd-HHmm
New-Item -ItemType Directory -Force C:/backups | Out-Null
& C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe -uroot --single-transaction --routines --result-file="C:/backups/atlas_merged-$stamp.sql" atlas_merged
```

Keep backups outside the web root. Verify size > 0 and restore into a scratch
database before production use (`mysql -uroot scratch --execute="source C:/backups/backup.sql"`).

## 8. Tests

| Command | What it covers |
|---|---|
| `npm test` | Scope mapping, delegated JWT audience/issuer/expiry, ≤60 s cap, `kid` rotation. |
| `npm run test:integration` (or `ALTUS_INTEGRATION_TEST=1 npm test`) | Live: real gateway + PHP built-in server on `atlas_hospitality_test` + official MCP SDK `Client` over `StreamableHTTPClientTransport`: OAuth PKCE (wrong verifier rejected), scope restriction, learner cannot obtain write scopes, tenant isolation, revoked user, revoked grant, refresh rotation, self-approval blocked, second-admin approval, single-use, expiry, changed-content rejection, idempotent duplicate publication. Also covers private catalogue creation, approved archive/restore, the disabled gateway and native two-factor login before OAuth consent. Requires seeded `atlas_hospitality_test` (`php index.php ha_test prepare_browser`) and Playwright in `e2e/`. |
| `php index.php ha_test run publisher` | Native contract (`160_publisher_api_gaps.php`, `140_publisher_upgrade.php`). |
