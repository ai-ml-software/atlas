<?php $ha_tab = 'usage'; include __DIR__ . '/_head.php'; ?>
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="card-title mb-0">By provider, model and task — last <?php echo (int) $days; ?> days</h5>
        <div class="btn-group btn-group-sm" role="group" aria-label="Period">
            <?php foreach (array(7, 30, 90) as $d): ?><a class="btn btn-outline-secondary <?php echo $days === $d ? 'active' : ''; ?>" href="<?php echo site_url('ha_ai/usage?days=' . $d); ?>"><?php echo $d; ?> days</a><?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>Provider</th><th>Model</th><th>Task</th><th class="text-end">Calls</th><th class="text-end">Success</th><th class="text-end">Input tok.</th><th class="text-end">Output tok.</th><th class="text-end">Chars (TTS)</th><th class="text-end">Avg latency</th></tr></thead>
        <tbody>
        <?php foreach ($by as $r): ?>
            <tr>
                <td><?php echo html_escape($r['provider_slug']); ?></td>
                <td class="ha-mono"><?php echo html_escape((string) $r['model_id']); ?></td>
                <td><?php echo html_escape((string) $r['task']); ?></td>
                <td class="text-end"><?php echo number_format($r['calls']); ?></td>
                <td class="text-end"><?php echo $r['calls'] ? round(100 * $r['ok_calls'] / $r['calls']) : 0; ?>%</td>
                <td class="text-end"><?php echo number_format($r['tin']); ?></td>
                <td class="text-end"><?php echo number_format($r['tout']); ?></td>
                <td class="text-end"><?php echo number_format($r['chars']); ?></td>
                <td class="text-end"><?php echo number_format($r['avg_ms'] / 1000, 1); ?> s</td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$by): ?><tr><td colspan="9" class="text-muted">No calls in this period.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <p class="small text-muted mb-0">Token counts are what each provider reported. Multiply by your contracted price per model for cost.</p>
</div></div>

<div class="card"><div class="card-body">
    <h5 class="card-title">Last 50 calls</h5>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>When</th><th>Provider</th><th>Model</th><th>Task</th><th>Job</th><th class="text-end">Tokens</th><th class="text-end">Latency</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td class="small text-muted"><?php echo html_escape($r['created_at']); ?></td>
                <td><?php echo html_escape($r['provider_slug']); ?></td>
                <td class="ha-mono small"><?php echo html_escape((string) $r['model_id']); ?></td>
                <td class="small"><?php echo html_escape((string) $r['task']); ?></td>
                <td class="small"><?php echo $r['job_id'] ? '<a href="' . site_url('ha_ai/job/' . $r['job_id']) . '">#' . (int) $r['job_id'] . '</a>' : ($r['api_key_id'] ? 'API' : '—'); ?></td>
                <td class="text-end small"><?php echo number_format($r['input_tokens'] + $r['output_tokens']); ?></td>
                <td class="text-end small"><?php echo number_format($r['latency_ms'] / 1000, 1); ?> s</td>
                <td class="small"><?php echo $r['ok'] ? '<span class="text-success">ok</span>' : '<span class="text-danger" title="' . html_escape((string) $r['error']) . '">' . html_escape(mb_substr((string) $r['error'], 0, 80)) . '</span>'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div></div>
</div>
