<?php $ha_tab = 'assistant'; include __DIR__ . '/_head.php'; ?>
<div class="card"><div class="card-body">
    <?php if (!$route): ?>
        <div class="alert alert-warning">The <strong>assistant</strong> task has no model yet. Route one under Task routing.</div>
    <?php else: ?>
        <p class="small text-muted">Using <span class="ha-mono"><?php echo html_escape($route['provider_slug'] . ' / ' . $route['model_id']); ?></span>. Output is a starting point: check it before you use it.</p>
    <?php endif; ?>
    <form id="ha-assist">
        <label for="ha-prompt" class="form-label">What do you need?</label>
        <textarea id="ha-prompt" class="form-control mb-2" rows="4" maxlength="8000" required
                  placeholder="Write a WhatsApp announcement for housekeeping staff about the new linen change schedule, in English and Arabic."></textarea>
        <button class="btn btn-primary" id="ha-send"><i class="mdi mdi-send"></i> Ask</button>
        <span class="small text-muted ms-2" id="ha-meta" role="status"></span>
    </form>
    <div class="mt-3 d-none" id="ha-answer-wrap">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="mb-0">Answer</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="ha-copy"><i class="mdi mdi-content-copy"></i> Copy</button>
        </div>
        <div class="border rounded p-3" id="ha-answer" dir="auto" style="white-space:pre-wrap"></div>
    </div>
</div></div>
</div>
<script>
(function () {
    var form = document.getElementById('ha-assist'), btn = document.getElementById('ha-send'), meta = document.getElementById('ha-meta');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        btn.disabled = true; meta.textContent = 'Thinking…';
        haPost('<?php echo site_url('ha_ai/assistant'); ?>', { prompt: document.getElementById('ha-prompt').value }).then(function (r) {
            btn.disabled = false;
            if (!r.ok) { meta.textContent = r.error; return; }
            document.getElementById('ha-answer-wrap').classList.remove('d-none');
            document.getElementById('ha-answer').textContent = r.text;
            meta.textContent = r.model + ' · ' + r.tokens + ' tokens';
        }).catch(function () { btn.disabled = false; meta.textContent = 'Request failed.'; });
    });
    document.getElementById('ha-copy').addEventListener('click', function () {
        navigator.clipboard && navigator.clipboard.writeText(document.getElementById('ha-answer').textContent);
    });
})();
</script>
