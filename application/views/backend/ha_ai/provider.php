<?php $ha_tab = 'providers'; include __DIR__ . '/_head.php'; ?>
<?php
$slug = $p['slug'];
$s = $p['settings'];
$extra = array();   // name => array(label, help, secret?)
foreach ($p['placeholders'] as $ph) {
    if ($ph === 'region' && ($p['regional_base_urls'] || $slug === 'bedrock')) {
        continue;
    }
    $extra[$ph] = array(ucwords(str_replace('_', ' ', $ph)), 'Fills {' . $ph . '} in the endpoint URL.', false);
}
$specific = array(
    'azure_openai' => array('api_version' => array('API version', '"v1" for the current OpenAI-compatible surface, or a dated version such as 2024-10-21 for older resources. Model = your deployment name.', false)),
    'bedrock'      => array('aws_region' => array('AWS region', 'e.g. us-east-1, eu-central-1, me-central-1.', false),
                            'aws_access_key_id' => array('IAM access key id', 'Only when not using a Bedrock API key.', false),
                            'aws_access_key_secret' => array('IAM secret access key', 'Stored encrypted. Leave blank to keep.', true),
                            'aws_session_token' => array('Session token (optional)', 'For temporary credentials.', false)),
    'openai'       => array('organization' => array('Organization id (optional)', 'Sent as OpenAI-Organization.', false)),
    'watsonx'      => array('project_id' => array('Project id', 'watsonx.ai project (or use space id).', false),
                            'space_id' => array('Space id (optional)', '', false),
                            'version' => array('API version date', 'Defaults to 2024-10-08.', false)),
    'gigachat'     => array('scope' => array('Scope', 'GIGACHAT_API_PERS, GIGACHAT_API_B2B or GIGACHAT_API_CORP.', false),
                            'ca_bundle' => array('CA bundle path', 'Absolute path to a PEM containing the Russian Trusted Root CA.', false)),
    'vertex_ai'    => array('project' => array('Google Cloud project id', 'Paste the service account JSON into the API key field.', false)),
    'google_tts'   => array('project_id' => array('Quota project id (optional)', 'Paste an API key or a service account JSON as the key.', false)),
    'azure_speech' => array('region' => array('Speech region', 'e.g. uaenorth, qatarcentral, westeurope, eastus.', false)),
);
if (isset($specific[$slug])) {
    $extra = array_merge($extra, $specific[$slug]);
}
$key_placeholder = $p['key_source'] === 'environment' ? 'Set by environment variable HA_AI_KEY_' . strtoupper($slug)
    : ($p['has_key'] ? 'Saved (' . $p['key_hint'] . '). Leave blank to keep.' : 'Paste API key');
if (in_array($slug, array('vertex_ai', 'google_tts'), true)) {
    $key_placeholder = $p['has_key'] ? 'Saved. Leave blank to keep.' : 'API key, or the full service account JSON';
}
?>
<div class="row g-3">
<div class="col-lg-7">
    <div class="card"><div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="ha-flag"><?php echo ha_ai_flag($p['hq_country']); ?></span>
            <div>
                <h5 class="mb-0"><?php echo html_escape($p['name']); ?></h5>
                <div class="small text-muted"><?php echo html_escape($p['company']); ?> · <?php echo html_escape(ha_ai_country($p['hq_country'])); ?> · API style <span class="ha-mono"><?php echo html_escape($p['api_style']); ?></span></div>
            </div>
        </div>
        <div class="mb-3"><?php echo ha_ai_caps($p['capabilities']); ?></div>
        <?php if ($p['deprecated']): ?>
            <div class="alert alert-dark">This service is marked discontinued by its vendor. <?php echo html_escape((string) $p['note']); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo site_url('ha_ai/provider/' . $slug); ?>" autocomplete="off">
            <?php echo ha_csrf_field(); ?>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="ha-enabled" name="enabled" value="1" <?php echo $p['enabled'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ha-enabled">Enabled: tasks may be routed to this provider</label>
            </div>

            <div class="mb-3">
                <label for="ha-key" class="form-label">API key <?php if ($p['key_url']): ?><a class="small ms-1" href="<?php echo html_escape($p['key_url']); ?>" target="_blank" rel="noopener">get a key</a><?php endif; ?></label>
                <?php if (in_array($slug, array('vertex_ai', 'google_tts'), true)): ?>
                    <textarea id="ha-key" name="api_key" class="form-control ha-mono" rows="3" placeholder="<?php echo html_escape($key_placeholder); ?>" <?php echo $p['key_source'] === 'environment' ? 'disabled' : ''; ?>></textarea>
                <?php else: ?>
                    <input id="ha-key" name="api_key" type="password" class="form-control" autocomplete="new-password" spellcheck="false"
                           placeholder="<?php echo html_escape($key_placeholder); ?>" <?php echo $p['key_source'] === 'environment' ? 'disabled' : ''; ?>>
                <?php endif; ?>
                <div class="form-text">Encrypted with AES-256-GCM before it is stored; never shown again. Type <span class="ha-mono">__clear__</span> to remove it.
                    Production can instead set the environment variable <span class="ha-mono">HA_AI_KEY_<?php echo strtoupper($slug); ?></span>.</div>
                <?php if (!empty($p['auth']['flow'])): ?><div class="form-text"><i class="mdi mdi-information-outline"></i> <?php echo html_escape($p['auth']['flow']); ?></div><?php endif; ?>
            </div>

            <?php if ($p['regional_base_urls']): ?>
            <div class="mb-3">
                <label for="ha-region" class="form-label">Region / data residency</label>
                <select id="ha-region" name="region" class="form-select">
                    <option value="">Default (<?php echo html_escape($p['base_url']); ?>)</option>
                    <?php foreach ($p['regional_base_urls'] as $label => $url): ?>
                        <option value="<?php echo html_escape($label); ?>" <?php echo $p['region'] === $label ? 'selected' : ''; ?>><?php echo html_escape($label . (is_string($url) ? ' — ' . $url : '')); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Pick the region your account was created in; keys are usually region-bound (e.g. China mainland vs international).</div>
            </div>
            <?php endif; ?>

            <?php foreach ($extra as $name => $f): ?>
                <div class="mb-3">
                    <label for="ha-s-<?php echo $name; ?>" class="form-label"><?php echo html_escape($f[0]); ?></label>
                    <input id="ha-s-<?php echo $name; ?>" name="settings[<?php echo html_escape($name); ?>]" class="form-control"
                        type="<?php echo $f[2] ? 'password' : 'text'; ?>" autocomplete="off"
                        value="<?php echo $f[2] ? '' : html_escape(isset($s[$name]) ? $s[$name] : ''); ?>"
                        placeholder="<?php echo $f[2] && !empty($s[$name]) ? 'Saved. Leave blank to keep.' : ''; ?>">
                    <?php if ($f[1]): ?><div class="form-text"><?php echo html_escape($f[1]); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>

            <details class="mb-3" <?php echo $p['base_url_override'] ? 'open' : ''; ?>>
                <summary>Advanced</summary>
                <div class="mt-2">
                    <label for="ha-base" class="form-label">Base URL override</label>
                    <input id="ha-base" name="base_url" type="url" class="form-control ha-mono" value="<?php echo html_escape((string) $p['base_url_override']); ?>" placeholder="<?php echo html_escape((string) $p['base_url']); ?>">
                    <div class="form-text">For a proxy, a private endpoint or a gateway. https only (http allowed for localhost).</div>
                    <?php if ($p['api_style'] === 'openai' || $p['api_style'] === 'custom'): ?>
                    <div class="form-check mt-2">
                        <input type="hidden" name="settings[json_mode]" value="">
                        <input class="form-check-input" type="checkbox" id="ha-json" name="settings[json_mode]" value="1" <?php echo !empty($s['json_mode']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ha-json">Send <span class="ha-mono">response_format: json_object</span> for JSON tasks (only if the provider supports it)</label>
                    </div>
                    <?php endif; ?>
                </div>
            </details>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary"><i class="mdi mdi-content-save"></i> Save</button>
                <button class="btn btn-outline-success" type="button" id="ha-test"><i class="mdi mdi-lan-connect"></i> Test connection</button>
                <?php if (in_array('chat', $p['capabilities'], true)): ?>
                    <button class="btn btn-outline-secondary" type="button" id="ha-sync"><i class="mdi mdi-refresh"></i> Sync models</button>
                <?php endif; ?>
            </div>
            <div class="mt-2 small" id="ha-result" role="status" aria-live="polite">
                <?php if ($p['last_tested_at']): ?>
                    Last test <?php echo html_escape($p['last_tested_at']); ?>: <?php echo $p['last_test_ok'] ? '<span class="text-success">OK</span>' : '<span class="text-danger">' . html_escape((string) $p['last_error']) . '</span>'; ?>
                <?php endif; ?>
            </div>
        </form>
    </div></div>
</div>

<div class="col-lg-5">
    <div class="card mb-3"><div class="card-body small">
        <h6>Endpoint in use</h6>
        <p class="ha-mono text-break mb-2"><?php echo html_escape($endpoint ?: '—'); ?></p>
        <?php if (!empty($p['available_regions']) || !empty($p['restrictions'])): ?>
            <h6 class="mt-3">Availability</h6>
            <p class="mb-2"><?php $ar = !empty($p['restrictions']) ? $p['restrictions'] : $p['available_regions']; echo html_escape(is_string($ar) ? $ar : json_encode($ar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></p>
        <?php endif; ?>
        <?php if ($p['note']): ?><h6 class="mt-3">Notes</h6><p class="mb-2"><?php echo html_escape($p['note']); ?></p><?php endif; ?>
        <?php if (!empty($p['unverified'])): ?>
            <p class="text-muted mb-0"><i class="mdi mdi-alert-outline"></i> Not confirmed in official docs on the research date: <?php echo html_escape(implode(', ', (array) $p['unverified'])); ?></p>
        <?php endif; ?>
        <?php if ($p['docs_url']): ?><p class="mt-2 mb-0"><a href="<?php echo html_escape($p['docs_url']); ?>" target="_blank" rel="noopener">Official documentation <i class="mdi mdi-open-in-new"></i></a></p><?php endif; ?>
    </div></div>

    <?php if (in_array('chat', $p['capabilities'], true)): ?>
    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Models <span class="badge bg-light text-dark"><?php echo count($models); ?></span></h6>
            <span class="small text-muted"><?php echo $p['models_synced_at'] ? 'synced ' . html_escape($p['models_synced_at']) : 'registry defaults (not synced)'; ?></span>
        </div>
        <input type="search" class="form-control form-control-sm mb-2" id="ha-model-filter" placeholder="Filter models" aria-label="Filter models">
        <div style="max-height:420px;overflow:auto">
            <table class="table table-sm mb-0"><tbody id="ha-models">
                <?php foreach ($models as $m): ?>
                    <tr class="<?php echo isset($m['is_available']) && !$m['is_available'] ? 'text-muted' : ''; ?>">
                        <td class="ha-mono"><?php echo html_escape($m['model_id']); ?><?php echo isset($m['is_available']) && !$m['is_available'] ? ' <span class="badge bg-light text-muted">gone</span>' : ''; ?></td>
                        <td class="small text-muted"><?php echo html_escape($m['label'] !== $m['model_id'] ? (string) $m['label'] : ''); ?><?php echo !empty($m['context_tokens']) ? ' · ' . number_format($m['context_tokens']) . ' ctx' : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table>
        </div>
    </div></div>
    <?php endif; ?>
</div>
</div>
</div>
<script>
(function () {
    var out = document.getElementById('ha-result');
    function call(url, btn, okText) {
        btn.disabled = true;
        out.textContent = 'Working…';
        haPost(url).then(function (r) {
            btn.disabled = false;
            if (r.ok) { out.innerHTML = '<span class="text-success">' + (okText(r)) + '</span>'; setTimeout(function () { location.reload(); }, 900); }
            else { out.innerHTML = '<span class="text-danger"></span>'; out.firstChild.textContent = r.error || 'Failed'; }
        }).catch(function () { btn.disabled = false; out.textContent = 'Request failed.'; });
    }
    var t = document.getElementById('ha-test');
    t && t.addEventListener('click', function () { call('<?php echo site_url('ha_ai/provider_test/' . $slug); ?>', t, function (r) { return 'OK — ' + (r.detail || 'connected'); }); });
    var s = document.getElementById('ha-sync');
    s && s.addEventListener('click', function () { call('<?php echo site_url('ha_ai/provider_sync/' . $slug); ?>', s, function (r) { return r.count + ' models synced'; }); });
    var f = document.getElementById('ha-model-filter');
    f && f.addEventListener('input', function () {
        var q = f.value.toLowerCase();
        document.querySelectorAll('#ha-models tr').forEach(function (tr) { tr.style.display = tr.textContent.toLowerCase().indexOf(q) === -1 ? 'none' : ''; });
    });
})();
</script>
