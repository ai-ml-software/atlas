<?php $ha_tab = 'routes'; include __DIR__ . '/_head.php'; ?>
<?php
require_once APPPATH . 'libraries/Ha_ai_media.php';
$need = array('text' => 'chat', 'tts' => 'tts', 'avatar' => 'video', 'clip' => 'video');
$option_help = array(
    'tts' => "voice=alloy\nvoice_ar=         (optional: a different voice for Arabic)",
    'avatar' => "HeyGen: avatar_id=…  voice_id=…  voice_id_ar=…\nD-ID: source_url=https://…/presenter.jpg  voice_id=en-US-JennyNeural\nSynthesia: avatar=…  test=1",
    'clip' => '',
);
$model_lists = array();
foreach ($models as $slug => $list) {
    $model_lists[$slug] = array_map(function ($m) { return array($m['model_id'], $m['label'] ?: $m['model_id']); }, $list);
}
?>
<form method="post" action="<?php echo site_url('ha_ai/routes'); ?>">
<?php echo ha_csrf_field(); ?>
<div class="card"><div class="card-body">
    <p class="text-muted">Code asks for a task, never a model. Switching every lesson script to another vendor is a change here, not a deploy. Only <strong>enabled</strong> providers that offer the needed capability are listed.</p>
    <div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th style="min-width:200px">Task</th><th style="min-width:200px">Provider</th><th style="min-width:260px">Model</th><th>Temp.</th><th>Max tokens</th><th style="min-width:240px">Options</th></tr></thead>
        <tbody>
        <?php foreach ($tasks as $task => $cfg):
            $r = isset($routes[$task]) ? $routes[$task] : null;
            $cap = $need[$cfg['kind']];
            $opts = $r ? (json_decode((string) $r['options_json'], true) ?: array()) : array();
            $opt_text = '';
            foreach ($opts as $k => $v) { $opt_text .= $k . '=' . $v . "\n"; }
        ?>
        <tr>
            <td><strong class="ha-mono"><?php echo html_escape($task); ?></strong><div class="small text-muted"><?php echo html_escape($cfg['label']); ?></div></td>
            <td>
                <select name="route[<?php echo $task; ?>][provider]" class="form-select form-select-sm ha-prov" data-task="<?php echo $task; ?>" aria-label="Provider for <?php echo $task; ?>">
                    <option value="">— not routed —</option>
                    <?php foreach ($providers as $slug => $p):
                        if (!$p['enabled'] || !in_array($cap, $p['capabilities'], true)) continue;
                        $usable = $cfg['kind'] === 'text' || Ha_ai_media::supports($p, $cfg['kind']);
                    ?>
                        <option value="<?php echo html_escape($slug); ?>" <?php echo $r && $r['provider_slug'] === $slug ? 'selected' : ''; ?> <?php echo $usable ? '' : 'disabled'; ?>>
                            <?php echo html_escape($p['name'] . ($usable ? '' : ' (no adapter yet)')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select name="route[<?php echo $task; ?>][model]" class="form-select form-select-sm mb-1 ha-model" data-task="<?php echo $task; ?>" data-current="<?php echo html_escape($r ? $r['model_id'] : ''); ?>" aria-label="Model for <?php echo $task; ?>"></select>
                <input name="route[<?php echo $task; ?>][model_custom]" class="form-control form-control-sm ha-mono" placeholder="…or type a model / deployment id" aria-label="Custom model id for <?php echo $task; ?>">
            </td>
            <td><?php if ($cfg['kind'] === 'text'): ?><input name="route[<?php echo $task; ?>][temperature]" class="form-control form-control-sm" style="width:70px" inputmode="decimal" value="<?php echo $r && $r['temperature'] !== null ? html_escape($r['temperature']) : ''; ?>" placeholder="<?php echo isset($cfg['temperature']) ? $cfg['temperature'] : ''; ?>" aria-label="Temperature"><?php endif; ?></td>
            <td><?php if ($cfg['kind'] === 'text'): ?><input name="route[<?php echo $task; ?>][max_tokens]" class="form-control form-control-sm" style="width:90px" inputmode="numeric" value="<?php echo $r && $r['max_tokens'] ? (int) $r['max_tokens'] : ''; ?>" placeholder="<?php echo isset($cfg['max_tokens']) ? $cfg['max_tokens'] : ''; ?>" aria-label="Max tokens"><?php endif; ?></td>
            <td><?php if ($cfg['kind'] !== 'text'): ?><textarea name="route[<?php echo $task; ?>][options]" rows="2" class="form-control form-control-sm ha-mono" placeholder="<?php echo html_escape($option_help[$cfg['kind']]); ?>" aria-label="Options"><?php echo html_escape(trim($opt_text)); ?></textarea><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <button class="btn btn-primary"><i class="mdi mdi-content-save"></i> Save routing</button>
    <span class="small text-muted ms-2">Supported media adapters — speech: <?php echo html_escape(implode(', ', Ha_ai_media::supported_list('tts'))); ?>; presenter: <?php echo html_escape(implode(', ', Ha_ai_media::supported_list('avatar'))); ?>; clips: <?php echo html_escape(implode(', ', Ha_ai_media::supported_list('clip'))); ?>.</span>
</div></div>
</form>
</div>
<script>
(function () {
    var lists = <?php echo json_encode($model_lists, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    function fill(task) {
        var prov = document.querySelector('.ha-prov[data-task="' + task + '"]').value;
        var sel = document.querySelector('.ha-model[data-task="' + task + '"]');
        var current = sel.dataset.current;
        sel.innerHTML = '';
        var list = lists[prov] || [];
        var found = false;
        list.forEach(function (m) {
            var o = document.createElement('option');
            o.value = m[0]; o.textContent = m[1] === m[0] ? m[0] : m[0] + ' — ' + m[1];
            if (m[0] === current) { o.selected = true; found = true; }
            sel.appendChild(o);
        });
        if (current && !found && prov) {
            var o = document.createElement('option');
            o.value = current; o.textContent = current + ' (current)'; o.selected = true;
            sel.insertBefore(o, sel.firstChild);
        }
        if (!list.length && !current) {
            var e = document.createElement('option'); e.value = ''; e.textContent = prov ? 'No synced models — type an id below' : '—';
            sel.appendChild(e);
        }
    }
    document.querySelectorAll('.ha-prov').forEach(function (s) {
        s.addEventListener('change', function () { document.querySelector('.ha-model[data-task="' + s.dataset.task + '"]').dataset.current = ''; fill(s.dataset.task); });
        fill(s.dataset.task);
    });
})();
</script>
