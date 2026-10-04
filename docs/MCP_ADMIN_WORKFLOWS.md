# MCP integrations — administrator daily workflows

Screen: **Content Studio → Integrations** (`/hkp/cms/integrations`). Requires
platform (system) scope and `cms_pages.update`. Approving, declining, revoking
and restoring require `cms_pages.publish` (or the content type's publish
permission). The audit tab requires `audit_logs.view`. The screen follows the
interface language; Arabic renders right-to-left.

| Tab | URL | Use |
|---|---|---|
| Approval requests | `?tab=approvals&status=pending` | Queue of MCP publish/archive requests. Filter by status. |
| Connections | `?tab=connections` | OAuth grants with user, client, last seen (UTC), request count, last action. Revoke. |
| Connection health | `?tab=health` | Probes gateway `/health`, protected-resource metadata and OAuth server metadata (PKCE S256); accepted signing key ids; recently active grants. |
| MCP audit history | `?tab=audit` | Read-only, 25 per page. Filter by action (`mcp.save`, `mcp.publish`, `oauth.consent`, `oauth.revoke` …), user id, date range, client/text. |
| Archive | `?tab=archive` | Archived pages; *Restore to draft*. Nothing is permanently deleted. |

Use **Copy MCP URL** and **Connect a client** for the connection instructions. The sidebar highlights Integrations while this screen is open.

## Morning check (2 minutes)

1. *Connection health*: all three probes **OK**. If the gateway shows *Switched off* or *Secret missing*, see the runbook §2.
2. *Approval requests* (pending): work the queue (below). Requests expire 10 minutes after they were made; expired ones need a fresh request from the client.
3. *Connections*: revoke grants for people who left, unknown clients, or anything not seen for a long time.

## Reviewing a publication request

1. Open the request. Check client, requesting user, object, version and operation.
2. Use *Preview content*, then compare *Published content* with *Requested draft*.
3. *Approve this version* or *Decline*. You cannot approve a request you made yourself; another administrator must (separation of duties). The MCP client then calls `altus_publish_approved` once; the approval is consumed.
4. If anyone edits the draft after approval, the approval stops working (409 conflict) and the client must request again — re-review the new version.

## Investigating an action

*MCP audit history* → filter by action or user → the `audit_reference.audit_id`
returned to the MCP client is the `#` column. Rows cannot be edited here.

## Restoring archived content

*Archive* → *Restore to draft*. The page returns to draft, then goes through the
normal review/publication flow. Archiving itself only happens via an approved
request.

## Revoking access

*Connections* → *Revoke access*. Tokens and refresh tokens for that grant stop
working immediately (next call → 401). Deactivating a user in ALTUS also blocks
all their MCP access at the next call. To revoke everything, follow runbook §6.
