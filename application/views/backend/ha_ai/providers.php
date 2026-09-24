<?php $ha_tab = 'providers'; include __DIR__ . '/_head.php'; ?>
<?php
$countries = array();
$caps = array();
foreach ($providers as $p) {
    $iso = strtoupper((string) $p['hq_country']);
    $countries[$iso] = ha_ai_country($iso);
    foreach ($p['capabilities'] as $c) {
        $caps[$c] = true;
    }
}
asort($countries);
uasort($providers, function ($a, $b) {
    if ($a['enabled'] !== $b['enabled']) {
        return $a['enabled'] ? -1 : 1;
    }
    return strcasecmp($a['name'], $b['name']);
});
?>
<div class="card"><div class="card-body">
    <div class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
            <label for="ha-q" class="form-label small mb-1">Search</label>
            <input type="search" id="ha-q" class="form-control" placeholder="Name, company, model, region…">
        </div>
        <div class="col-md-3">
            <label for="ha-country" class="form-label small mb-1">Headquarters</label>
            <select id="ha-country" class="form-select">
                <option value="">All countries (<?php echo count($providers); ?> providers)</option>
                <?php foreach ($countries as $iso => $name): ?>
                    <option value="<?php echo html_escape($iso); ?>"><?php echo ha_ai_flag($iso) . ' ' . html_escape($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="ha-state" class="form-label small mb-1">Status</label>
            <select id="ha-state" class="form-select">
                <option value="">Any</option><option value="on">Enabled</option><option value="key">Key saved</option><option value="off">Not configured</option>
            </select>
        </div>
        <div class="col-md-3 text-md-end">
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#ha-custom" aria-expanded="false">
                <i class="mdi mdi-plus"></i> Custom OpenAI-compatible endpoint
            </button>
        </div>
    </div>
    <div class="mb-3" role="group" aria-label="Filter by capability">
        <?php foreach (array_keys($caps) as $c): ?>
            <span class="badge bg-light text-dark border ha-chip me-1" data-cap="<?php echo html_escape($c); ?>" tabindex="0" role="button" aria-pressed="false"><?php echo html_escape($c); ?></span>
        <?php endforeach; ?>
    </div>

    <div class="collapse mb-3" id="ha-custom"><div class="card card-body bg-light">
        <form method="post" action="<?php echo site_url('ha_ai/provider_add'); ?>" class="row g-2" autocomplete="off">
            <?php echo ha_csrf_field(); ?>
            <div class="col-md-2"><label class="form-label small">Id</label><input name="slug" class="form-control" required pattern="[a-z0-9_]{2,40}" placeholder="my_vllm"></div>
            <div class="col-md-3"><label class="form-label small">Display name</label><input name="name" class="form-control" placeholder="In-house vLLM"></div>
            <div class="col-md-4"><label class="form-label small">Base URL (…/v1)</label><input name="base_url" class="form-control" required type="url" placeholder="https://llm.example.com/v1"></div>
            <div class="col-md-2"><label class="form-label small">API key</label><input name="api_key" class="form-control" type="password" autocomplete="new-password"></div>
            <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">Add</button></div>
            <div class="col-12 small text-muted">Any server that speaks the OpenAI chat format: vLLM, TGI, LiteLLM, Azure AI Foundry serverless, a regional reseller, a sovereign cloud.</div>
        </form>
    </div></div>

    <p class="small text-muted" id="ha-count" role="status"></p>
    <div class="row g-3" id="ha-grid">
        <?php foreach ($providers as $slug => $p):
            $iso = strtoupper((string) $p['hq_country']);
            $search = strtolower($p['name'] . ' ' . $p['company'] . ' ' . $slug . ' ' . implode(' ', (array) $p['default_models']) . ' '
                . implode(' ', array_keys($p['regional_base_urls'])) . ' ' . (is_string($p['available_regions']) ? $p['available_regions'] : json_encode($p['available_regions'])) . ' ' . ha_ai_country($iso));
            $state = $p['enabled'] ? 'on' : ($p['has_key'] ? 'key' : 'off');
        ?>
        <div class="col-sm-6 col-xl-4 ha-item" data-country="<?php echo html_escape($iso); ?>" data-caps="<?php echo html_escape(implode(' ', $p['capabilities'])); ?>"
             data-state="<?php echo $state; ?>" data-search="<?php echo html_escape($search); ?>">
            <div class="card ha-provider-card mb-0 <?php echo $p['enabled'] ? 'is-on' : ''; ?>"><div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <span class="ha-flag" title="<?php echo html_escape(ha_ai_country($iso)); ?>"><?php echo ha_ai_flag($iso); ?></span>
                    <div class="flex-grow-1">
                        <h5 class="mb-0 fs-6"><?php echo html_escape($p['name']); ?></h5>
                        <div class="small text-muted"><?php echo html_escape($p['company'] ?: ''); ?> · <?php echo html_escape(ha_ai_country($iso)); ?></div>
                    </div>
                    <?php if ($p['deprecated']): ?><span class="badge bg-dark">discontinued</span>
                    <?php elseif ($p['enabled']): ?><span class="badge bg-success">on</span>
                    <?php elseif ($p['has_key']): ?><span class="badge bg-info">key saved</span><?php endif; ?>
                </div>
                <div class="mb-2"><?php echo ha_ai_caps($p['capabilities']); ?></div>
                <?php if ($p['regional_base_urls']): ?>
                    <div class="small mb-1"><i class="mdi mdi-map-marker-radius-outline"></i> Regions: <?php echo html_escape(implode(', ', array_slice(array_keys($p['regional_base_urls']), 0, 4))); ?><?php echo count($p['regional_base_urls']) > 4 ? ' +' . (count($p['regional_base_urls']) - 4) : ''; ?></div>
                <?php endif; ?>
                <?php if (!empty($p['restrictions']) || !empty($p['available_regions'])): ?>
                    <div class="small text-muted mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                        <?php $ar = !empty($p['restrictions']) ? $p['restrictions'] : $p['available_regions']; echo html_escape(is_string($ar) ? $ar : implode('; ', array_map(function ($v) { return is_string($v) ? $v : json_encode($v); }, (array) $ar))); ?>
                    </div>
                <?php endif; ?>
                <?php if ($p['last_tested_at']): ?>
                    <div class="small mb-2"><?php echo $p['last_test_ok'] ? '<i class="mdi mdi-check-circle text-success"></i> Tested ' : '<i class="mdi mdi-close-circle text-danger"></i> Failed '; ?><?php echo html_escape($p['last_tested_at']); ?></div>
                <?php endif; ?>
                <div class="mt-auto d-flex gap-2">
                    <a class="btn btn-sm btn-primary" href="<?php echo site_url('ha_ai/provider/' . $slug); ?>">Configure</a>
                    <?php if ($p['docs_url']): ?><a class="btn btn-sm btn-link" href="<?php echo html_escape($p['docs_url']); ?>" target="_blank" rel="noopener">Docs</a><?php endif; ?>
                </div>
            </div></div>
        </div>
        <?php endforeach; ?>
    </div>
</div></div>
</div>
<script>
(function () {
    var items = Array.prototype.slice.call(document.querySelectorAll('.ha-item'));
    var q = document.getElementById('ha-q'), country = document.getElementById('ha-country'), state = document.getElementById('ha-state');
    var chips = Array.prototype.slice.call(document.querySelectorAll('.ha-chip'));
    function apply() {
        var term = q.value.trim().toLowerCase(), c = country.value, s = state.value;
        var caps = chips.filter(function (x) { return x.classList.contains('active'); }).map(function (x) { return x.dataset.cap; });
        var shown = 0;
        items.forEach(function (el) {
            var ok = (!term || el.dataset.search.indexOf(term) !== -1)
                && (!c || el.dataset.country === c)
                && (!s || el.dataset.state === s)
                && caps.every(function (cap) { return (' ' + el.dataset.caps + ' ').indexOf(' ' + cap + ' ') !== -1; });
            el.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        document.getElementById('ha-count').textContent = shown + ' of ' + items.length + ' providers';
    }
    chips.forEach(function (ch) {
        function toggle() { ch.classList.toggle('active'); ch.setAttribute('aria-pressed', ch.classList.contains('active')); apply(); }
        ch.addEventListener('click', toggle);
        ch.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
    });
    [q, country, state].forEach(function (el) { el.addEventListener('input', apply); });
    apply();
})();
</script>
