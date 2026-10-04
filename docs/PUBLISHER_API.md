# Native publisher API — `/api/publisher/v1`

Internal API used only by the MCP gateway. It is separate from, and does not
change, the public `/api/v1` contract. Implemented in
`application/controllers/Publisher_api.php` over the shared native services in
`Ha_publishing_service` (the same services the admin screens use).

## Request

- `POST /api/publisher/v1/{action}` with a JSON object body (≤22 MB). Other methods → 405.
- `Authorization: Bearer <delegated JWT>` — HS256, header `kid`, `aud=altus-native-publisher`, `iss=$ALTUS_MCP_URL`, lifetime ≤60 s, claims `sub`, `client_id`, `scope`, `source_id` (the OAuth access-token id). Each call re-verifies the token row, its OAuth grant, the user's active status and current permissions.
- Writes require `Idempotency-Key: [A-Za-z0-9_-]{8,100}`. Same key + same body → stored response is replayed with header `Idempotent-Replayed: true`; same key + different body → 409 `idempotency_conflict`.
- Optional `X-Request-Id` (echoed; generated otherwise).
- Body must never carry `user_id`, `organization_id` or `property_id` (422): identity and tenant come from the grant.
- Feature switch: when `ALTUS_MCP_ENABLED` is not `1` every call (including `bridge`) returns 503 `mcp_disabled`.

## Response envelope

```json
{"success":true,"data":{...},"error":null,"request_id":"..."}
{"success":false,"data":null,"error":{"code":"conflict","message":"...","details":{...},"retryable":false},"request_id":"..."}
```

Writes add `data.audit_reference = {audit_id, actor_id, client_id, request_key, request_id}`; `audit_id` is the `ha_audit_log.id` row written in the same transaction.

| HTTP | `error.code` | When |
|---|---|---|
| 401 | `missing_credential`, `invalid_credential`, `unknown_key`, `token_revoked`, `grant_revoked`, `user_revoked`, `login_expired` | Bad/expired/over-long JWT, unknown `kid`, revoked token or grant, inactive user. `WWW-Authenticate` is set. |
| 403 | `insufficient_scope` (`details.required_scope`), `forbidden`, `approval_required`, `self_approval_forbidden` | Scope or permission missing, tenant boundary, unreviewed approval. |
| 404 | `not_found`, `unknown_operation` | Missing object/revision/approval, or one owned by another user/client/tenant (no existence leak). |
| 405 | `method_not_allowed` | Not POST. |
| 409 | `conflict` (`details.current_version`, `current_base_hash`, `current_hash`, `object_type`, `object_id`; for approvals also `approval_id`, `approved_version`), `idempotency_conflict`, `approval_expired`, `approval_consumed`, `approval_mismatch`, `approval_unavailable` | Stale `version`/`base_hash`, content changed after approval, reused approval. |
| 413 | `payload_too_large` | Body over 22 MB. |
| 422 | `validation_failed`, `idempotency_key_required`, `sop_governance_required` | Invalid input; SOPs cannot be published through MCP. |
| 500 | `internal_error` (`retryable: true`) | Unexpected failure; transaction rolled back. |
| 503 | `mcp_disabled`, `mcp_not_configured` | Feature switch off / secret missing. |

## Actions

Scope in brackets. Types: `page`, `site`, `navigation`, `courses`, `programs`, `paths`, `articles`, `topics`, `publisher` (document drafts).

| Action | Body | Result |
|---|---|---|
| `health` [read] | — | native readiness flags |
| `list` [read] | `type`, `query?`, `page?` | object list |
| `get` [read] | `type`, `id` | `object_id, object_type, status, version, hash, state{payload,version,base_hash,published}, edit_url, preview_url, warnings` |
| `create` [content/course write] | `type` (courses/articles/topics/programs/paths), `payload` | new private draft; native tenant scope; status forced to draft |
| `save` [content/course write] | `type`, `id`, `version`, `base_hash`, `payload` | as `get` (version + 1) |
| `media_list` [read] / `media` [media write] | `query?` / `name, base64, alt_en?, alt_ar?` | media items / stored image |
| `source`, `upload_document` [content/course write] | text or base64 file, `name`, `target`, `locale` | publisher draft |
| `generate`, `translate`, `source_correction`, `import`, `job_control` [write]; `job_status` [read] | `id`, `version`, … | publisher draft / job |
| `validate` [read] | `target`, `payload` | dry-run package validation |
| `request_publish` [publish] | `type`, `id`, `operation: publish|archive` | `approval_id, status:pending, object_type, object_id, operation, version, expires_at (UTC, ISO 8601 Z), review_url` |
| `approval_status` [read] | `id` | `status` (`pending|approved|declined|consumed|expired`), binding fields, `expires_at` |
| `publish` [publish] | `approval_id`, optional `type`,`id`,`operation`,`version` to assert the binding | published object (`version: 0`) |
| `restore` [write] | `type`, `id`, `revision`, `version`, `base_hash` | revision restored **as a draft** |
| `unarchive` [publish] | `type`, `id` | archived object returned to `draft` |
| `bridge` | `{code, binding}` with `aud=altus-login-exchange` JWT whose `body_hash` matches | gateway-only login exchange |

There is no delete, SQL or shell action.

## Approval rules

- An approval is bound to client, requesting user, operation, object and content version (`object_version` + content hash). It expires 10 minutes after the request (`expires_at` stored in UTC), is single-use, and becomes invalid if the draft or published content changes.
- A reviewer must hold the publication permission for the object and be in scope. The requester cannot approve their own request unless `ALTUS_MCP_ALLOW_SELF_APPROVAL=1`.
- SOP documents are never published through MCP (`sop_governance_required`); imported SOP packages stay drafts in the existing SOP approval workflow.
