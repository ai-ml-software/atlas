// End-to-end check of the native PHP MCP server with the OFFICIAL MCP TypeScript SDK client.
// Node is used only as a test client here; the server under test is PHP (Apache + CodeIgniter).
//
//   node tests/mcp_sdk_e2e.mjs [baseUrl]
//
// Env: ALTUS_E2E_EMAIL / ALTUS_E2E_PASSWORD (default: the local admin used by e2e/support/users.ts),
//      ALTUS_E2E_HEADED=1 to watch the browser. Requires mcp-gateway/node_modules (SDK client)
//      and e2e/node_modules (Playwright, Microsoft Edge channel) to be installed.
//
// Flow: 401 discovery -> RFC 9728/8414 metadata -> RFC 7591 registration -> authorize with PKCE ->
// real ALTUS sign-in + consent screen in a browser -> code exchange -> initialize/tools/list/tools/call
// (CRUD, conflict, idempotency, approval request) -> refresh rotation -> reuse detection revokes the grant.
import http from 'node:http';
import { pathToFileURL, fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sdk = await import(pathToFileURL(path.join(root, 'mcp-gateway/node_modules/@modelcontextprotocol/client/dist/index.mjs')).href);
const { chromium } = await import(pathToFileURL(path.join(root, 'e2e/node_modules/playwright/index.mjs')).href);
const { Client, StreamableHTTPClientTransport, UnauthorizedError } = sdk;

const BASE = (process.argv[2] || 'http://localhost/atlas/atlas').replace(/\/$/, '');
const EMAIL = process.env.ALTUS_E2E_EMAIL || 'admin@hospitalityacademy.sa';
const PASSWORD = process.env.ALTUS_E2E_PASSWORD || 'admin123';
const PORT = 33419, REDIRECT = `http://127.0.0.1:${PORT}/callback`;
let failures = 0;
const check = (ok, label, extra = '') => { console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? '  ' + extra : ''}`); if (!ok) failures++; };

// ---- OAuth client provider (in memory) ----
const store = { client: undefined, tokens: undefined, verifier: undefined, authUrl: undefined };
const provider = {
  get redirectUrl() { return REDIRECT; },
  get clientMetadata() { return { client_name: 'ALTUS SDK e2e', redirect_uris: [REDIRECT], grant_types: ['authorization_code', 'refresh_token'], response_types: ['code'], token_endpoint_auth_method: 'none', scope: 'altus.read altus.content.write altus.course.write altus.media.write altus.publish' }; },
  clientInformation: () => store.client, saveClientInformation: (c) => { store.client = c; },
  tokens: () => store.tokens, saveTokens: (t) => { store.tokens = t; },
  redirectToAuthorization: (url) => { store.authUrl = url; },
  saveCodeVerifier: (v) => { store.verifier = v; }, codeVerifier: () => store.verifier,
  saveDiscoveryState: (s) => { store.discovery = s; }, discoveryState: () => store.discovery,
};

// ---- loopback redirect receiver ----
let resolveCallback; const callback = new Promise((r) => { resolveCallback = r; });
const server = http.createServer((req, res) => { const u = new URL(req.url, REDIRECT); if (u.pathname === '/callback') { res.end('ALTUS e2e: you can close this window.'); resolveCallback(u.searchParams); } else { res.statusCode = 404; res.end(); } });
await new Promise((r) => server.listen(PORT, '127.0.0.1', r));

const client = new Client({ name: 'altus-sdk-e2e', version: '1.0.0' });
try {
  // 1. Unauthenticated connect -> discovery, registration, authorization URL.
  let threw = null;
  try { await client.connect(new StreamableHTTPClientTransport(new URL(BASE + '/mcp'), { authProvider: provider })); } catch (e) { threw = e; }
  check(threw instanceof UnauthorizedError, '401 + WWW-Authenticate discovery leads to OAuth', threw ? threw.constructor.name + ' ' + String(threw.message).slice(0, 600) : 'no error');
  check(!!store.client?.client_id?.startsWith('mcp_'), 'dynamic client registration (RFC 7591)', store.client?.client_id);
  const auth = store.authUrl ? new URL(store.authUrl) : null;
  check(!!auth && auth.searchParams.get('code_challenge_method') === 'S256', 'authorization URL uses PKCE S256');
  check(auth?.searchParams.get('resource') === BASE + '/mcp', 'resource indicator (RFC 8707) = ' + auth?.searchParams.get('resource'));

  // 2. Real ALTUS sign-in and consent in a browser.
  const browser = await chromium.launch({ channel: 'msedge', headless: process.env.ALTUS_E2E_HEADED !== '1' });
  const page = await browser.newPage();
  await page.goto(auth.href);
  await page.waitForLoadState('networkidle'); await page.waitForTimeout(1500);   // the workspace sends signed-out users to /login with a Refresh header
  if (/\/login/.test(page.url()) || await page.locator('#login-form').count()) {
    await page.locator('#login-form #email').fill(EMAIL);
    await page.locator('#login-form #password').fill(PASSWORD);
    await page.locator('#login-form button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    if (!/mcp_authorize/.test(page.url())) { await page.goto(auth.href); await page.waitForLoadState('networkidle'); }   // fresh request if login did not return here
  }
  check(/mcp_authorize/.test(page.url()), 'signed in and reached the ALTUS consent screen', page.url().replace(/request=\w+/, 'request=…'));
  const scopes = await page.locator('.mcp-scope code').allTextContents();
  check(scopes.includes('altus.read') && scopes.includes('altus.content.write'), 'consent lists requested scopes', scopes.join(' ') || (await page.content()).replace(/\s+/g, ' ').match(/<main[\s\S]{0,1500}|<h1[\s\S]{0,600}/)?.[0]);
  await page.locator('button[name=decision][value=allow]').click();
  const params = await Promise.race([callback, new Promise((_, rej) => setTimeout(() => rej(new Error('no callback within 20s')), 20000))]);
  await browser.close();
  check(!!params.get('code') && params.get('iss') === BASE, 'redirected to the exact redirect_uri with code + iss (RFC 9207)');

  // 3. Code exchange by the SDK, then MCP over Streamable HTTP.
  const transport = new StreamableHTTPClientTransport(new URL(BASE + '/mcp'), { authProvider: provider });
  await transport.finishAuth(params);
  check(!!store.tokens?.access_token?.startsWith('altus_at_') && !!store.tokens?.refresh_token, 'token exchange (authorization_code + PKCE)');
  await client.connect(transport);
  const sv = client.getServerVersion();
  check(sv?.name === 'altus-publisher-php', 'initialize -> PHP server ' + sv?.name + ' ' + sv?.version);
  check(!!transport.sessionId, 'Mcp-Session-Id issued');
  const { tools } = await client.listTools();
  check(tools.length >= 25 && !tools.some((t) => /delete|sql|shell/i.test(t.name)), `tools/list: ${tools.length} tools, no delete/sql/shell`);
  const call = async (name, args = {}) => { const r = await client.callTool({ name, arguments: args }); return { error: !!r.isError, data: r.structuredContent }; };
  const info = await call('altus_site_info');
  check(!info.error && info.data.server.node_required === false, 'altus_site_info (scopes: ' + (info.data?.effective_scopes || []).join(' ') + ')');
  const slug = 'sdk-e2e-' + Date.now();
  const created = await call('altus_create', { type: 'articles', data: { title_en: 'SDK e2e article', title_ar: 'مقال اختبار', slug_en: slug, slug_ar: slug + '-ar', body_en: '<p>From the official SDK.</p>', body_ar: '<p>اختبار</p>' }, idempotency_key: 'sdk-e2e-' + slug });
  check(!created.error && created.data.object_id > 0 && created.data.audit_id > 0, 'create draft article', JSON.stringify({ id: created.data.object_id, status: created.data.status, audit: created.data.audit_id }));
  const replay = await call('altus_create', { type: 'articles', data: { title_en: 'SDK e2e article', title_ar: 'مقال اختبار', slug_en: slug, slug_ar: slug + '-ar', body_en: '<p>From the official SDK.</p>', body_ar: '<p>اختبار</p>' }, idempotency_key: 'sdk-e2e-' + slug });
  check(replay.data.idempotent_replayed === true && replay.data.object_id === created.data.object_id, 'idempotent replay returns the same object');
  const got = await call('altus_get', { type: 'articles', id: created.data.object_id });
  const upd = await call('altus_update', { type: 'articles', id: created.data.object_id, data: { title_en: 'SDK e2e article (edited)' }, expected_version: got.data.version });
  check(!upd.error && upd.data.version === got.data.version + 1, 'update with expected_version', 'v' + got.data.version + ' -> v' + upd.data.version);
  const stale = await call('altus_update', { type: 'articles', id: created.data.object_id, data: { title_en: 'stale' }, expected_version: got.data.version });
  check(stale.error && stale.data.error.code === 'conflict' && stale.data.error.details.current_version === upd.data.version, 'stale write -> conflict with current version');
  const page2 = await call('altus_list', { type: 'page' });
  check(!page2.error, 'list pages');
  const req = await call('altus_request_publish', { type: 'articles', id: created.data.object_id, operation: 'publish' });
  check(!req.error && req.data.status === 'pending' && /integrations\?approval=/.test(req.data.review_url), 'request_publish creates a pending human approval');
  const early = await call('altus_publish_approved', { approval_id: req.data.approval_id });
  check(early.error && early.data.error.code === 'approval_required', 'publish without approval is refused');

  // 4. Refresh rotation and reuse detection against the real token endpoint.
  const meta = await (await fetch(BASE + '/.well-known/oauth-authorization-server')).json();
  const form = (o) => ({ method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(o) });
  const old = store.tokens.refresh_token;
  const r1 = await (await fetch(meta.token_endpoint, form({ grant_type: 'refresh_token', refresh_token: old, client_id: store.client.client_id }))).json();
  check(!!r1.access_token && r1.refresh_token !== old, 'refresh_token rotates');
  store.tokens = { ...store.tokens, ...r1 };
  const afterRefresh = await call('altus_get', { type: 'articles', id: created.data.object_id });
  check(!afterRefresh.error, 'new access token works');
  const reuse = await fetch(meta.token_endpoint, form({ grant_type: 'refresh_token', refresh_token: old, client_id: store.client.client_id }));
  check(reuse.status === 400 && (await reuse.json()).error === 'invalid_grant', 'reused refresh token rejected');
  const probe = await fetch(BASE + '/mcp', { method: 'POST', headers: { Authorization: 'Bearer ' + r1.access_token, 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' }, body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'ping' }) });
  check(probe.status === 401, 'reuse detection revoked the whole grant (access token now 401)');
} catch (e) {
  failures++; console.error('FAIL  unexpected error:', e?.stack || e);
} finally {
  try { await client.close(); } catch {}
  server.close();
}
console.log(failures ? `\n${failures} check(s) failed` : '\nAll SDK end-to-end checks passed');
process.exit(failures ? 1 : 0);
