<?php $ha_tab = 'dashboard'; include __DIR__ . '/_head.php'; ?>
<?php
$routed = count($routes);
$tasks_total = count($tasks);
$beat_age = $heartbeat ? time() - strtotime($heartbeat['at']) : null;
$worker_ok = $heartbeat && ($heartbeat['state'] === 'working' ? $beat_age < 900 : $beat_age < 3 * 86400);
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="ha-kpi-label">Providers enabled</div>
        <div class="ha-kpi"><?php echo count($enabled); ?> <small class="text-muted fs-6">/ <?php echo count($providers); ?></small></div>
        <a href="<?php echo site_url('ha_ai/providers'); ?>" class="small">Manage providers →</a>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="ha-kpi-label">Tasks routed</div>
        <div class="ha-kpi"><?php echo $routed; ?> <small class="text-muted fs-6">/ <?php echo $tasks_total; ?></small></div>
        <a href="<?php echo site_url('ha_ai/routes'); ?>" class="small">Task routing →</a>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="ha-kpi-label">Awaiting review</div>
        <div class="ha-kpi"><?php echo (int) $counts['draft']; ?></div>
        <a href="<?php echo site_url('ha_ai/studio?status=draft'); ?>" class="small">Review drafts →</a>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="ha-kpi-label">Video lessons with no video</div>
        <div class="ha-kpi"><?php echo (int) $gap; ?></div>
        <a href="<?php echo site_url('ha_ai/studio'); ?>" class="small">Generate scripts →</a>
    </div></div></div>
</div>

<?php if (!$enabled || $routed < 2): ?>
<div class="card border-primary mb-3"><div class="card-body">
    <h5 class="mb-2"><i class="mdi mdi-rocket-launch-outline text-primary"></i> Get started in three steps</h5>
    <ol class="mb-0">
        <li class="<?php echo $enabled ? 'text-decoration-line-through text-muted' : ''; ?>">Open <a href="<?php echo site_url('ha_ai/providers'); ?>">Providers</a>, pick one, paste its API key, press <strong>Test</strong>. Its live model list loads.</li>
        <li class="<?php echo $routed >= 2 ? 'text-decoration-line-through text-muted' : ''; ?>">In <a href="<?php echo site_url('ha_ai/routes'); ?>">Task routing</a>, choose a model for <em>course_outline</em> and <em>lesson_script</em> (and a voice for <em>narration</em>).</li>
        <li>In <a href="<?php echo site_url('ha_ai/studio'); ?>">Studio</a>, generate a course or scripts for lessons that have no video. Review, approve, publish.</li>
    </ol>
</div></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100"><div class="card-body">
            <h5 class="card-title">Recent jobs</h5>
            <?php if (!$recent): ?>
                <p class="text-muted mb-0">No AI jobs yet.</p>
            <?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>#</th><th>Job</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $j): ?>
                    <tr>
                        <td><?php echo (int) $j['id']; ?></td>
                        <td><a href="<?php echo site_url('ha_ai/job/' . $j['id']); ?>"><?php echo html_escape($j['title']); ?></a>
                            <div class="small text-muted"><?php echo html_escape(str_replace('_', ' ', $j['type'])); ?></div></td>
                        <td><?php echo ha_ai_status_badge($j['status']); ?></td>
                        <td class="small text-muted"><?php echo html_escape($j['updated_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-body">
            <h5 class="card-title">Worker</h5>
            <p class="mb-2">
                <?php if ($worker_ok): ?>
                    <span class="badge bg-success">healthy</span> last seen <?php echo html_escape($heartbeat['at']); ?> (<?php echo html_escape($heartbeat['state']); ?>)
                <?php elseif ($heartbeat): ?>
                    <span class="badge bg-warning">stale</span> last seen <?php echo html_escape($heartbeat['at']); ?>
                <?php else: ?>
                    <span class="badge bg-secondary">never run</span>
                <?php endif; ?>
                · queue: <?php echo (int) $counts['queued']; ?> queued, <?php echo (int) $counts['running']; ?> running
            </p>
            <?php if (!empty($ha_can['generate'])): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" id="ha-run-worker"><i class="mdi mdi-play"></i> Run worker now</button>
                <span class="small ms-2" id="ha-run-worker-msg" role="status"></span>
            <?php endif; ?>
            <details class="mt-3"><summary class="small">Schedule it (recommended)</summary>
                <p class="small mb-1 mt-2">cPanel → Cron Jobs, every minute:</p>
                <pre class="ha-mono bg-light p-2 mb-2">cd <?php echo html_escape(rtrim(FCPATH, '/\\')); ?> &amp;&amp; php index.php ha_ai_cli work &gt;/dev/null 2&gt;&amp;1</pre>
                <p class="small mb-0">A second worker exits immediately while one is running, so an every-minute schedule is safe.</p>
            </details>
        </div></div>
        <div class="card mb-3"><div class="card-body">
            <h5 class="card-title">Video renderer</h5>
            <ul class="list-unstyled small mb-0">
                <li><?php echo $renderer['ffmpeg'] ? '<i class="mdi mdi-check-circle text-success"></i> ffmpeg: <span class="ha-mono">' . html_escape($renderer['ffmpeg']) . '</span>'
                    : '<i class="mdi mdi-alert-circle text-danger"></i> ffmpeg not found. Install it (Windows: <span class="ha-mono">winget install Gyan.FFmpeg</span>, Linux: <span class="ha-mono">apt install ffmpeg</span>) or set <span class="ha-mono">HA_FFMPEG</span>. Scripts and publishing work without it; slide videos need it.'; ?></li>
                <li><?php echo $renderer['gd'] ? '<i class="mdi mdi-check-circle text-success"></i> GD + FreeType' : '<i class="mdi mdi-alert-circle text-danger"></i> GD with FreeType missing'; ?></li>
                <li><?php echo $renderer['font_arabic'] ? '<i class="mdi mdi-check-circle text-success"></i> Arabic font: <span class="ha-mono">' . html_escape(basename($renderer['font_arabic'])) . '</span>' : '<i class="mdi mdi-alert-circle text-danger"></i> No Arabic font'; ?></li>
                <li><?php echo $renderer['exec'] ? '<i class="mdi mdi-check-circle text-success"></i> Process control available' : '<i class="mdi mdi-alert-circle text-danger"></i> proc_open disabled by the host'; ?></li>
            </ul>
        </div></div>
        <div class="card"><div class="card-body">
            <h5 class="card-title">Last 30 days</h5>
            <div class="row text-center">
                <div class="col"><div class="ha-kpi fs-4"><?php echo number_format((int) $usage['calls']); ?></div><div class="ha-kpi-label">calls</div></div>
                <div class="col"><div class="ha-kpi fs-4"><?php echo $usage['calls'] ? round(100 * $usage['ok_calls'] / $usage['calls']) : 0; ?>%</div><div class="ha-kpi-label">succeeded</div></div>
                <div class="col"><div class="ha-kpi fs-4"><?php echo number_format((int) $usage['tin'] + (int) $usage['tout']); ?></div><div class="ha-kpi-label">tokens</div></div>
            </div>
        </div></div>
    </div>
</div>
</div>
<script>
(function () {
    var b = document.getElementById('ha-run-worker');
    if (!b) return;
    b.addEventListener('click', function () {
        b.disabled = true;
        haPost('<?php echo site_url('ha_ai/run_worker'); ?>').then(function (r) {
            document.getElementById('ha-run-worker-msg').textContent = r.message || r.error;
            b.disabled = false;
        });
    });
})();
</script>
