<?php
/** Integrations: approvals queue, OAuth connections, connection health, MCP audit history, archive. Arabic strings inline (RTL via layout). */
$ar = hkp_locale() === 'ar';
$L = function ($en, $arabic) use ($ar) { return $ar ? $arabic : hkp_t($en); };
$base = hkp_url('cms/integrations');
$tabs = array('approvals' => $L('Approval requests', 'طلبات الموافقة'), 'connections' => $L('Connections', 'الاتصالات'), 'health' => $L('Connection health', 'حالة الاتصال'), 'audit' => $L('MCP audit history', 'سجل تدقيق MCP'), 'archive' => $L('Archive', 'الأرشيف'));
if (!$can['audit']) unset($tabs['audit']);
$state = function ($a) { return strtotime($a['expires_at'] . ' UTC') < time() && in_array($a['status'], array('pending', 'approved'), true) ? 'expired' : $a['status']; };
$badge = array('pending' => 'warning', 'approved' => 'success', 'consumed' => 'muted', 'declined' => 'danger', 'expired' => 'danger');
$statusLabel = array('pending' => $L('Pending', 'قيد الانتظار'), 'approved' => $L('Approved', 'موافق عليه'), 'consumed' => $L('Used', 'مستخدم'), 'declined' => $L('Declined', 'مرفوض'), 'expired' => $L('Expired', 'منتهي'), 'all' => $L('All', 'الكل'));
?>
<style>
.mcp-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-block:16px 4px;padding:0;list-style:none}
.mcp-tabs a{display:inline-block;padding:8px 14px;border-radius:999px;border:1px solid #d9d3ca;text-decoration:none;color:inherit;transition:transform .18s cubic-bezier(.34,1.56,.64,1),opacity .18s}
.mcp-tabs a:hover{transform:translateY(-1px)}.mcp-tabs a:active{transform:translateY(0);opacity:.8}
.mcp-tabs a:focus-visible{outline:2px solid #a84d27;outline-offset:2px}
.mcp-tabs a[aria-current=page]{background:#292725;color:#fff;border-color:#292725}
.mcp-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.mcp-filters .hkp-field{min-inline-size:140px}
.mcp-health{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.mcp-health div{padding:12px;border:1px solid #e6e0d7;border-radius:10px}
.mcp-pager{display:flex;gap:8px;align-items:center;margin-block-start:12px}
.mcp-muted{color:#6b665f;font-size:13px}
.hkp-sr-only{position:absolute;inline-size:1px;block-size:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
#mcp-bulk input[type=checkbox]{inline-size:18px;block-size:18px;accent-color:#a84d27}
#mcp-bulk input[type=checkbox]:focus-visible{outline:2px solid #a84d27;outline-offset:2px}
</style>
<div class="hkp-head"><div><div class="hkp-eyebrow"><?= hkp_h($L('Connected publishing', 'النشر المتصل')) ?></div><h1><?= hkp_h($L('Integrations & approvals', 'التكاملات والموافقات')) ?></h1><p><?= hkp_h($L('Review exact drafts, approve publication, monitor MCP connections and revoke client access.', 'راجع المسودات بدقة، ووافق على النشر، وراقب اتصالات MCP، وألغِ وصول العملاء.')) ?></p></div></div>
<nav aria-label="<?= hkp_h($L('Integration sections', 'أقسام التكامل')) ?>"><ul class="mcp-tabs"><?php foreach ($tabs as $k => $label): ?><li><a href="<?= $base . '?tab=' . $k ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= hkp_h($label) ?></a></li><?php endforeach; ?></ul></nav>
<section class="hkp-card" style="margin-top:12px"><h2><?= hkp_h($L('Gateway', 'البوابة')) ?> · <span class="hkp-badge hkp-badge--<?= $enabled ? 'success' : 'danger' ?>"><?= hkp_h($enabled ? $L('Enabled', 'مفعّلة') : ($switched_on ? $L('Secret missing', 'السر غير مُعد') : $L('Switched off', 'متوقفة'))) ?></span></h2><p><?= hkp_h($L('Endpoint', 'نقطة الاتصال')) ?>: <code dir="ltr"><?= hkp_h($gateway . '/mcp') ?></code></p><p class="mcp-muted"><?= hkp_h($L('Separation of duties', 'فصل المهام')) ?>: <?= hkp_h($self_approval ? $L('self-approval allowed by configuration', 'الموافقة الذاتية مسموحة بالإعدادات') : $L('another administrator must approve each request', 'يجب أن يوافق مسؤول آخر على كل طلب')) ?></p></section>

<?php $php_mcp = $php ?? array('enabled' => false, 'endpoint' => '', 'installed' => false, 'grants' => array(), 'clients' => array(), 'check' => null); ?>
<section class="hkp-card" style="margin-top:12px"><h2><?= hkp_h($L('MCP server (PHP, built in)', 'MCP server (PHP, built in)')) ?> · <span class="hkp-badge hkp-badge--<?= $php_mcp['enabled'] && $php_mcp['installed'] ? 'success' : 'danger' ?>"><?= hkp_h(!$php_mcp['installed'] ? $L('Run migration 032', 'Run migration 032') : ($php_mcp['enabled'] ? $L('Enabled', 'Enabled') : $L('Switched off', 'Switched off'))) ?></span></h2>
<p><?= hkp_h($L('Endpoint', 'Endpoint')) ?>: <code dir="ltr"><?= hkp_h($php_mcp['endpoint']) ?></code> <button type="button" class="hkp-btn hkp-btn--ghost" data-copy-text="<?= hkp_h($php_mcp['endpoint']) ?>" data-copy-ok="<?= hkp_h($L('MCP URL copied.', 'MCP URL copied.')) ?>" data-copy-error="<?= hkp_h($L('Select the endpoint and copy it manually.', 'Select the endpoint and copy it manually.')) ?>"><?= hkp_h($L('Copy', 'Copy')) ?></button></p>
<p class="mcp-muted"><?= hkp_h($L('Runs inside ALTUS: no Node gateway is needed. OAuth 2.1 with PKCE and dynamic client registration; clients sign in with ALTUS (two-factor included) and see a consent screen. The legacy Node gateway below is optional and deprecated.', 'Runs inside ALTUS: no Node gateway is needed. The legacy Node gateway below is optional and deprecated.')) ?></p>
<details><summary><?= hkp_h($L('Connection setup', 'Connection setup')) ?></summary>
<p><strong>Claude (claude.ai / Desktop)</strong>: <?= hkp_h($L('Settings → Connectors → Add custom connector → paste the URL.', 'Settings → Connectors → Add custom connector → paste the URL.')) ?></p>
<p><strong>Claude Code</strong>:</p><pre class="studio-source" dir="ltr">claude mcp add --transport http altus <?= hkp_h($php_mcp['endpoint']) ?></pre>
<p><strong>Cursor</strong> (<code>~/.cursor/mcp.json</code>):</p><pre class="studio-source" dir="ltr"><?= hkp_h(json_encode(array('mcpServers' => array('altus' => array('url' => $php_mcp['endpoint']))), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
<p><strong><?= hkp_h($L('Any MCP client', 'Any MCP client')) ?></strong>: <?= hkp_h($L('Streamable HTTP transport, URL only. The client discovers OAuth from the 401 response; never paste tokens or secrets.', 'Streamable HTTP transport, URL only. Never paste tokens or secrets.')) ?></p></details>
<?php if ($tab === 'health' && $php_mcp['check']): ?><div class="mcp-health" style="margin-top:12px"><?php foreach ($php_mcp['check'] as $k => $c): ?><div><strong dir="ltr"><?= hkp_h($k) ?></strong><p><span class="hkp-badge hkp-badge--<?= $c['ok'] ? 'success' : 'danger' ?>"><?= hkp_h($c['ok'] ? $L('OK', 'OK') : $L('Failing', 'Failing')) ?></span> <span class="mcp-muted" dir="ltr">HTTP <?= (int) $c['status'] ?> · <?= (int) $c['ms'] ?> ms</span></p></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($tab === 'connections'): ?>
<h3><?= hkp_h($L('Active PHP MCP grants', 'Active PHP MCP grants')) ?></h3>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?= hkp_h($L('Client', 'Client')) ?></th><th><?= hkp_h($L('User', 'User')) ?></th><th><?= hkp_h($L('Scopes', 'Scopes')) ?></th><th><?= hkp_h($L('Granted (UTC)', 'Granted (UTC)')) ?></th><th><?= hkp_h($L('Last used (UTC)', 'Last used (UTC)')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($php_mcp['grants'] as $g): ?><tr><td dir="ltr"><?= hkp_h(($g['client_name'] ?: '') . ' · ' . $g['client_id']) ?></td><td dir="ltr"><?= hkp_h(($g['email'] ?? '') . ' #' . (int) $g['user_id']) ?></td><td dir="ltr"><?= hkp_h($g['scope']) ?></td><td dir="ltr"><?= hkp_h($g['created_at']) ?></td><td dir="ltr"><?= hkp_h($g['last_used_at'] ?: '—') ?></td><td><form method="post"><?= ha_csrf_field() ?><input type="hidden" name="action" value="revoke_native"><input type="hidden" name="grant" value="<?= hkp_h($g['id']) ?>"><button class="hkp-btn hkp-btn--ghost"><?= hkp_h($L('Revoke access', 'Revoke access')) ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$php_mcp['grants']): ?><p class="hkp-muted"><?= hkp_h($L('No active PHP MCP connections.', 'No active PHP MCP connections.')) ?></p><?php endif; ?>
<?php if ($php_mcp['clients']): ?><h3><?= hkp_h($L('Registered clients', 'Registered clients')) ?></h3>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?= hkp_h($L('Name', 'Name')) ?></th><th>client_id</th><th><?= hkp_h($L('Redirect URIs', 'Redirect URIs')) ?></th><th><?= hkp_h($L('Registered (UTC)', 'Registered (UTC)')) ?></th></tr></thead><tbody>
<?php foreach ($php_mcp['clients'] as $c): ?><tr><td><?= hkp_h($c['client_name']) ?></td><td dir="ltr"><?= hkp_h($c['client_id']) ?></td><td dir="ltr"><?= hkp_h(implode(', ', (array) json_decode($c['redirect_uris'], true))) ?></td><td dir="ltr"><?= hkp_h($c['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php endif; ?>
</section>
<div class="hkp-actions" style="margin-block:12px"><button type="button" class="hkp-btn hkp-btn--ghost" data-copy-text="<?= hkp_h($gateway . '/mcp') ?>" data-copy-ok="<?= hkp_h($L('MCP URL copied.', '?? ??? ???? MCP.')) ?>" data-copy-error="<?= hkp_h($L('Select the endpoint above and copy it manually.', '??? ???? ??????? ????? ?????? ??????.')) ?>"><?= hkp_h($L('Copy MCP URL', '??? ???? MCP')) ?></button><span id="mcp-copy-status" role="status" aria-live="polite"></span></div>
<details class="hkp-card" style="margin-block:12px"><summary><?= hkp_h($L('Connect a client', '????? ????')) ?></summary><ol><li><?= hkp_h($L('Check Connection health, then add this MCP URL in your client settings.', '???? ?? ???? ??????? ?? ??? ???? MCP ?? ??????? ??????.')) ?></li><li><?= hkp_h($L('Start authorization, sign in to ALTUS and review the requested access before consenting.', '???? ??????? ???? ?????? ??? ALTUS ????? ?????? ??????? ??? ????????.')) ?></li><li><?= hkp_h($L('Check Connections for the client and its last activity. Review publication requests in Approval requests.', '???? ?? ?????? ???? ???? ?? ?? ?????????. ???? ????? ????? ?? ????? ????????.')) ?></li></ol></details>

<?php if ($tab === 'approvals'): ?>
<?php if (!empty($detail)): $a = $detail['approval']; ?>
<section class="hkp-card" style="margin-top:20px"><h2><?= hkp_h($L('Review requested operation', 'مراجعة العملية المطلوبة')) ?></h2>
<p><?= hkp_h($a['operation'] . ' · ' . $a['object_type'] . ' #' . $a['object_id'] . (isset($a['object_version']) ? ' · v' . $a['object_version'] : '') . ' · ' . $L('client', 'العميل') . ' ' . $a['client_id'] . ' · ' . $L('requested by user', 'طلبه المستخدم') . ' #' . $a['requester_id']) ?></p>
<?php if (isset($detail['error'])): ?><p><?= hkp_h($detail['error']) ?></p><?php else: ?>
<a class="hkp-btn hkp-btn--ghost" href="<?= hkp_h($detail['content']['preview_url']) ?>" target="_blank" rel="noopener"><?= hkp_h($L('Preview content', 'معاينة المحتوى')) ?></a>
<div class="studio-review"><div><h3><?= hkp_h($L('Published content', 'المحتوى المنشور')) ?></h3><pre class="studio-source" dir="ltr"><?= hkp_h(json_encode($detail['content']['state']['published'] ?? $detail['content']['state']['page'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div><div><h3><?= hkp_h($L('Requested draft', 'المسودة المطلوبة')) ?></h3><pre class="studio-source" dir="ltr"><?= hkp_h(json_encode($detail['content']['state']['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div></div>
<?php if ($state($a) === 'pending' && $can['publish'] && ((int) $a['requester_id'] !== $viewer_id || $self_approval)): ?>
<form method="post"><?= ha_csrf_field() ?><input type="hidden" name="approval" value="<?= (int) $a['id'] ?>"><div class="hkp-actions"><button class="hkp-btn" name="decision" value="approve"><?= hkp_h($L('Approve & publish this version', 'الموافقة على هذا الإصدار ونشره')) ?></button><button class="hkp-btn hkp-btn--ghost" name="decision" value="decline"><?= hkp_h($L('Decline', 'رفض')) ?></button></div></form>
<?php elseif ($state($a) === 'pending' && (int) $a['requester_id'] === $viewer_id && !$self_approval): ?><p class="mcp-muted"><?= hkp_h($L('You requested this operation, so another administrator must review it.', 'أنت من طلب هذه العملية، لذا يجب أن يراجعها مسؤول آخر.')) ?></p><?php endif; ?>
<?php endif; ?></section>
<?php endif; ?>
<section class="hkp-card" style="margin-top:20px"><h2><?= hkp_h($L('Approval requests', 'طلبات الموافقة')) ?></h2>
<form method="get" class="mcp-filters"><input type="hidden" name="tab" value="approvals"><div class="hkp-field"><label for="mcp-status"><?= hkp_h($L('Status', 'الحالة')) ?></label><select id="mcp-status" class="hkp-select" name="status"><?php foreach ($statusLabel as $k => $label) if ($k !== 'expired'): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= hkp_h($label) ?></option><?php endif; ?></select></div><button class="hkp-btn hkp-btn--ghost"><?= hkp_h($L('Filter', 'تصفية')) ?></button></form>
<?php $canApprove = function ($a) use ($state, $can, $viewer_id, $self_approval) { return $state($a) === 'pending' && $can['publish'] && ((int) $a['requester_id'] !== $viewer_id || $self_approval); }; $anyApprovable = (bool) array_filter($approvals, $canApprove); ?>
<form method="post" id="mcp-bulk"><?= ha_csrf_field() ?><input type="hidden" name="action" value="bulk_approve">
<?php if ($approvals): ?><div class="hkp-actions" style="margin-block:12px;align-items:center"><button class="hkp-btn" id="mcp-bulk-approve" disabled><?= hkp_h($L('Approve & publish selected', 'الموافقة والنشر للمحدد')) ?> (<span id="mcp-bulk-count">0</span>)</button><span class="mcp-muted"><?= hkp_h($anyApprovable ? $L('Tick requests, or use the header box to select all.', 'حدد الطلبات، أو استخدم مربع العنوان لتحديد الكل.') : $L('No request here can be approved. Expired requests must be requested again by the MCP client.', 'لا يوجد طلب قابل للموافقة هنا. يجب أن يعيد عميل MCP طلب الطلبات المنتهية.')) ?></span></div><?php endif; ?>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?php if ($approvals): ?><input type="checkbox" id="mcp-select-all"<?= $anyApprovable ? '' : ' disabled' ?> aria-label="<?= hkp_h($L('Select all pending requests', 'تحديد كل الطلبات المعلقة')) ?>"><?php else: ?><span class="hkp-sr-only"><?= hkp_h($L('Select', 'تحديد')) ?></span><?php endif; ?></th><th><?= hkp_h($L('Content', 'المحتوى')) ?></th><th><?= hkp_h($L('Client', 'العميل')) ?></th><th><?= hkp_h($L('Operation', 'العملية')) ?></th><th><?= hkp_h($L('Requester', 'مقدم الطلب')) ?></th><th><?= hkp_h($L('Status', 'الحالة')) ?></th><th><?= hkp_h($L('Expires (UTC)', 'ينتهي (UTC)')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($approvals as $a): $s = $state($a); $ok = $canApprove($a); ?><tr><td><?php $why = $ok ? '' : ($s === 'expired' ? $L('Expired: ask the MCP client to request approval again.', 'منتهي: اطلب من عميل MCP طلب الموافقة مجدداً.') : ($s !== 'pending' ? $L('Already reviewed.', 'تمت مراجعته.') : (!$can['publish'] ? $L('Publication permission required.', 'يلزم إذن النشر.') : $L('Another administrator must approve your own request.', 'يجب أن يوافق مسؤول آخر على طلبك.')))); ?><input type="checkbox"<?= $ok ? ' name="approvals[]" value="' . (int) $a['id'] . '"' : ' disabled title="' . hkp_h($why) . '"' ?> aria-label="<?= hkp_h($L('Select request', 'تحديد الطلب') . ' #' . (int) $a['id'] . ($why ? ' – ' . $why : '')) ?>"></td><td><a href="<?= $base . '?tab=approvals&status=' . $status . '&approval=' . (int) $a['id'] ?>"><?= hkp_h($a['object_type'] . ' #' . $a['object_id']) ?></a></td><td dir="ltr"><?= hkp_h($a['client_id']) ?></td><td><?= hkp_h($a['operation']) ?></td><td>#<?= (int) $a['requester_id'] ?></td><td><span class="hkp-badge hkp-badge--<?= $badge[$s] ?? 'muted' ?>"><?= hkp_h($statusLabel[$s] ?? $s) ?></span></td><td dir="ltr"><?= hkp_h($a['expires_at']) ?></td><td><?php if ($ok): ?><button class="hkp-btn hkp-btn--ghost" name="single" value="<?= (int) $a['id'] ?>"><?= hkp_h($L('Approve & publish', 'موافقة ونشر')) ?></button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></form><?php if (!$approvals): ?><p class="hkp-muted"><?= hkp_h($L('No requests in this view.', 'لا توجد طلبات في هذا العرض.')) ?></p><?php endif; ?></section>
<?php if ($approvals): ?><script>
(function () {
  var form = document.getElementById('mcp-bulk'), all = document.getElementById('mcp-select-all'),
      btn = document.getElementById('mcp-bulk-approve'), count = document.getElementById('mcp-bulk-count');
  if (!form || !all || !btn) return;
  var boxes = function () { return Array.prototype.slice.call(form.querySelectorAll('input[name="approvals[]"]')); };
  var sync = function () {
    var b = boxes(), n = b.filter(function (x) { return x.checked; }).length;
    count.textContent = n; btn.disabled = n === 0;
    all.checked = n > 0 && n === b.length; all.indeterminate = n > 0 && n < b.length;
  };
  all.addEventListener('change', function () { boxes().forEach(function (x) { x.checked = all.checked; }); sync(); });
  form.addEventListener('change', function (e) { if (e.target.name === 'approvals[]') sync(); });
  // A row's own Approve button submits only that row, whatever is ticked.
  form.addEventListener('submit', function (e) { if (e.submitter === btn && !boxes().some(function (x) { return x.checked; })) e.preventDefault(); });
  sync();
})();
</script><?php endif; ?>

<?php elseif ($tab === 'connections'): ?>
<section class="hkp-card" style="margin-top:20px"><h2><?= hkp_h($L('OAuth connections', 'اتصالات OAuth')) ?></h2>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?= hkp_h($L('Client', 'العميل')) ?></th><th><?= hkp_h($L('User', 'المستخدم')) ?></th><th><?= hkp_h($L('Last seen (UTC)', 'آخر ظهور (UTC)')) ?></th><th><?= hkp_h($L('Requests', 'الطلبات')) ?></th><th><?= hkp_h($L('Last action', 'آخر إجراء')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($grants as $g): $p = json_decode($g['payload'], true); $s = $seen[$g['id']] ?? null; ?><tr><td dir="ltr"><?= hkp_h($p['clientId'] ?? '') ?></td><td>#<?= (int) ($p['accountId'] ?? 0) ?></td><td dir="ltr"><?= hkp_h($s['last_seen_at'] ?? $L('Never', 'أبداً')) ?></td><td><?= (int) ($s['requests'] ?? 0) ?></td><td><?= hkp_h($s['last_action'] ?? '—') ?></td><td><?php if ($can['publish']): ?><form method="post"><?= ha_csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="grant" value="<?= hkp_h($g['id']) ?>"><button class="hkp-btn hkp-btn--ghost"><?= hkp_h($L('Revoke access', 'إلغاء الوصول')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$grants): ?><p class="hkp-muted"><?= hkp_h($L('No active connections.', 'لا توجد اتصالات نشطة.')) ?></p><?php endif; ?></section>

<?php elseif ($tab === 'health'): ?>
<section class="hkp-card" style="margin-top:20px"><h2><?= hkp_h($L('Connection health', 'حالة الاتصال')) ?></h2>
<?php if (!$probe): ?><p><?= hkp_h($L('The gateway is not enabled, so it was not contacted. Set ALTUS_MCP_ENABLED=1 and ALTUS_MCP_SECRET.', 'البوابة غير مفعّلة ولم يتم الاتصال بها. اضبط ALTUS_MCP_ENABLED=1 و ALTUS_MCP_SECRET.')) ?></p>
<?php else: ?><div class="mcp-health"><?php foreach (array('health' => $L('Gateway /health', 'فحص البوابة /health'), 'protected_resource' => $L('Protected-resource metadata', 'بيانات المورد المحمي'), 'authorization_server' => $L('OAuth server metadata (PKCE S256)', 'بيانات خادم OAuth (PKCE S256)')) as $k => $label): $c = $probe[$k]; ?><div><strong><?= hkp_h($label) ?></strong><p><span class="hkp-badge hkp-badge--<?= $c['ok'] ? 'success' : 'danger' ?>"><?= hkp_h($c['ok'] ? $L('OK', 'سليم') : $L('Failing', 'فشل')) ?></span> <span class="mcp-muted" dir="ltr">HTTP <?= (int) $c['status'] ?> · <?= (int) $c['ms'] ?> ms</span></p></div><?php endforeach; ?></div><?php endif; ?>
<p class="mcp-muted"><?= hkp_h($L('Signing key ids accepted', 'معرّفات مفاتيح التوقيع المقبولة')) ?>: <code dir="ltr"><?= hkp_h(implode(', ', $keys)) ?></code></p>
<h3><?= hkp_h($L('Recently active grants', 'المنح النشطة مؤخراً')) ?></h3>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?= hkp_h($L('Client', 'العميل')) ?></th><th><?= hkp_h($L('User', 'المستخدم')) ?></th><th><?= hkp_h($L('Last seen (UTC)', 'آخر ظهور (UTC)')) ?></th><th><?= hkp_h($L('Requests', 'الطلبات')) ?></th></tr></thead><tbody><?php foreach ($recent as $r): ?><tr><td dir="ltr"><?= hkp_h($r['client_id']) ?></td><td>#<?= (int) $r['user_id'] ?></td><td dir="ltr"><?= hkp_h($r['last_seen_at']) ?></td><td><?= (int) $r['requests'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$recent): ?><p class="hkp-muted"><?= hkp_h($L('No MCP requests recorded yet.', 'لم تُسجل أي طلبات MCP بعد.')) ?></p><?php endif; ?></section>

<?php elseif ($tab === 'audit'): $q = function ($extra) use ($filters, $base) { return $base . '?' . http_build_query(array_merge(array('tab' => 'audit'), array_filter($filters), $extra)); }; ?>
<section class="hkp-card" style="margin-top:20px"><h2><?= hkp_h($L('MCP audit history', 'سجل تدقيق MCP')) ?> <span class="mcp-muted">(<?= (int) $total ?>)</span></h2><p class="mcp-muted"><?= hkp_h($L('Read-only. Shows MCP operations, OAuth consent and revocation, and approval reviews.', 'للقراءة فقط. يعرض عمليات MCP وموافقات OAuth وإلغاءها ومراجعات الموافقة.')) ?></p>
<form method="get" class="mcp-filters"><input type="hidden" name="tab" value="audit">
<div class="hkp-field"><label for="f-action"><?= hkp_h($L('Action', 'الإجراء')) ?></label><input id="f-action" class="hkp-input" name="action" dir="ltr" placeholder="mcp.publish" value="<?= hkp_h($filters['action']) ?>"></div>
<div class="hkp-field"><label for="f-user"><?= hkp_h($L('User id', 'رقم المستخدم')) ?></label><input id="f-user" class="hkp-input" name="user" inputmode="numeric" value="<?= $filters['user'] ?: '' ?>"></div>
<div class="hkp-field"><label for="f-from"><?= hkp_h($L('From', 'من')) ?></label><input id="f-from" type="date" class="hkp-input" name="from" value="<?= hkp_h($filters['from']) ?>"></div>
<div class="hkp-field"><label for="f-to"><?= hkp_h($L('To', 'إلى')) ?></label><input id="f-to" type="date" class="hkp-input" name="to" value="<?= hkp_h($filters['to']) ?>"></div>
<div class="hkp-field"><label for="f-q"><?= hkp_h($L('Client or text', 'العميل أو النص')) ?></label><input id="f-q" class="hkp-input" name="q" value="<?= hkp_h($filters['q']) ?>"></div>
<button class="hkp-btn hkp-btn--ghost"><?= hkp_h($L('Filter', 'تصفية')) ?></button></form>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th>#</th><th><?= hkp_h($L('When', 'الوقت')) ?></th><th><?= hkp_h($L('Actor', 'المنفذ')) ?></th><th><?= hkp_h($L('Action', 'الإجراء')) ?></th><th><?= hkp_h($L('Object', 'الكائن')) ?></th><th><?= hkp_h($L('Details', 'التفاصيل')) ?></th></tr></thead><tbody>
<?php foreach ($audit as $r): ?><tr><td><?= (int) $r['id'] ?></td><td dir="ltr"><?= hkp_h($r['created_at']) ?></td><td><?= hkp_h(($r['actor_name'] ?: '') . ' #' . (int) $r['user_id']) ?></td><td dir="ltr"><code><?= hkp_h($r['action']) ?></code></td><td dir="ltr"><?= hkp_h($r['entity_type'] . ($r['entity_id'] ? ' #' . $r['entity_id'] : '')) ?></td><td><?= hkp_h($r['description']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$audit): ?><p class="hkp-muted"><?= hkp_h($L('No audit entries match.', 'لا توجد سجلات مطابقة.')) ?></p><?php endif; ?>
<nav class="mcp-pager" aria-label="<?= hkp_h($L('Pages', 'الصفحات')) ?>"><?php if ($page > 1): ?><a class="hkp-btn hkp-btn--ghost" href="<?= hkp_h($q(array('page' => $page - 1))) ?>"><?= hkp_h($L('Previous', 'السابق')) ?></a><?php endif; ?><span><?= (int) $page ?> / <?= (int) $pages ?></span><?php if ($page < $pages): ?><a class="hkp-btn hkp-btn--ghost" href="<?= hkp_h($q(array('page' => $page + 1))) ?>"><?= hkp_h($L('Next', 'التالي')) ?></a><?php endif; ?></nav></section>

<?php elseif ($tab === 'archive'): ?>
<section class="hkp-card" style="margin-top:20px"><h2><?= hkp_h($L('Archived pages', 'الصفحات المؤرشفة')) ?></h2><p class="mcp-muted"><?= hkp_h($L('Archiving happens only through an approved request. Restoring returns the page to draft; content is never permanently deleted here.', 'تتم الأرشفة فقط عبر طلب موافق عليه. الاستعادة تعيد الصفحة إلى مسودة، ولا يُحذف المحتوى نهائياً هنا.')) ?></p>
<div class="hkp-table-wrap"><table class="hkp-table"><thead><tr><th><?= hkp_h($L('Page', 'الصفحة')) ?></th><th><?= hkp_h($L('Slug', 'المسار')) ?></th><th></th></tr></thead><tbody><?php foreach ($archived as $p): ?><tr><td><?= hkp_h($p['code']) ?> #<?= (int) $p['id'] ?></td><td dir="ltr"><?= hkp_h($p['slug_en']) ?></td><td><?php if ($can['publish']): ?><form method="post"><?= ha_csrf_field() ?><input type="hidden" name="action" value="unarchive"><input type="hidden" name="type" value="page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="hkp-btn hkp-btn--ghost"><?= hkp_h($L('Restore to draft', 'استعادة كمسودة')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$archived): ?><p class="hkp-muted"><?= hkp_h($L('Nothing is archived.', 'لا يوجد محتوى مؤرشف.')) ?></p><?php endif; ?></section>
<?php endif; ?>
