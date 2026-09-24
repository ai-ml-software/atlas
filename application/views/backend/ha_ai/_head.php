<?php
/*
 * Shared chrome for every AI Studio page: sub-navigation, styles and small
 * view helpers. Included at the top of each page view.
 */
if (!function_exists('ha_ai_flag')) {
    /** ISO 3166 alpha-2 -> flag emoji. */
    function ha_ai_flag($iso) {
        $iso = strtoupper((string) $iso);
        if (!preg_match('/^[A-Z]{2}$/', $iso)) {
            return '🌐';
        }
        return mb_chr(0x1F1E6 + ord($iso[0]) - 65) . mb_chr(0x1F1E6 + ord($iso[1]) - 65);
    }

    function ha_ai_country($iso) {
        $names = array('US' => 'United States', 'CN' => 'China', 'FR' => 'France', 'DE' => 'Germany', 'GB' => 'United Kingdom',
            'CA' => 'Canada', 'JP' => 'Japan', 'KR' => 'South Korea', 'IN' => 'India', 'RU' => 'Russia', 'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia', 'IL' => 'Israel', 'SG' => 'Singapore', 'HK' => 'Hong Kong', 'NL' => 'Netherlands', 'CH' => 'Switzerland',
            'SE' => 'Sweden', 'AU' => 'Australia', 'IE' => 'Ireland', 'QA' => 'Qatar', 'EG' => 'Egypt', 'TW' => 'Taiwan');
        $iso = strtoupper((string) $iso);
        return isset($names[$iso]) ? $names[$iso] : ($iso ?: 'Global / self-hosted');
    }

    function ha_ai_status_badge($status) {
        $map = array('queued' => 'secondary', 'running' => 'info', 'draft' => 'warning', 'approved' => 'primary',
            'published' => 'success', 'rejected' => 'dark', 'failed' => 'danger');
        return '<span class="badge bg-' . (isset($map[$status]) ? $map[$status] : 'light') . ' ha-status" data-status="' . html_escape($status) . '">' . html_escape($status) . '</span>';
    }

    function ha_ai_caps($caps) {
        $icons = array('chat' => 'mdi-message-text-outline', 'vision' => 'mdi-eye-outline', 'tts' => 'mdi-account-voice',
            'stt' => 'mdi-microphone-outline', 'image' => 'mdi-image-outline', 'video' => 'mdi-movie-open-outline', 'embeddings' => 'mdi-vector-point');
        $out = '';
        foreach ((array) $caps as $c) {
            $out .= '<span class="ha-cap" title="' . html_escape($c) . '"><i class="mdi ' . (isset($icons[$c]) ? $icons[$c] : 'mdi-circle-small') . '"></i> ' . html_escape($c) . '</span>';
        }
        return $out;
    }
}
$ha_tab = isset($ha_tab) ? $ha_tab : '';
$ha_tabs = array(
    'dashboard' => array('ha_ai', 'Overview', 'mdi-view-dashboard-outline', 'view'),
    'studio'    => array('ha_ai/studio', 'Studio', 'mdi-movie-edit-outline', 'generate'),
    'providers' => array('ha_ai/providers', 'Providers', 'mdi-earth', 'configure'),
    'routes'    => array('ha_ai/routes', 'Task routing', 'mdi-routes', 'configure'),
    'usage'     => array('ha_ai/usage', 'Usage', 'mdi-chart-line', 'configure'),
    'assistant' => array('ha_ai/assistant', 'Assistant', 'mdi-robot-outline', 'generate'),
);
?>
<style>
    .ha-ai .ha-tabs { display:flex; flex-wrap:wrap; gap:.25rem; border-bottom:1px solid var(--bs-border-color,#e3e6ef); margin-bottom:1.25rem; }
    .ha-ai .ha-tabs a { padding:.6rem .9rem; border-bottom:2px solid transparent; color:#6c757d; font-weight:500; text-decoration:none; }
    .ha-ai .ha-tabs a.active { color:#6f42c1; border-bottom-color:#6f42c1; }
    .ha-ai .ha-tabs a:hover { color:#6f42c1; }
    .ha-ai .ha-kpi { font-size:1.75rem; font-weight:700; line-height:1.1; }
    .ha-ai .ha-kpi-label { color:#6c757d; font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
    .ha-ai .ha-cap { display:inline-flex; align-items:center; gap:.15rem; font-size:.72rem; padding:.1rem .45rem; margin:0 .2rem .2rem 0; border-radius:1rem; background:#f1edfb; color:#5b36a8; }
    .ha-ai .ha-provider-card { transition: box-shadow .15s ease, transform .15s ease; height:100%; }
    .ha-ai .ha-provider-card:hover { box-shadow:0 .5rem 1.5rem rgba(111,66,193,.12); transform:translateY(-2px); }
    .ha-ai .ha-provider-card.is-on { border-inline-start:3px solid #6f42c1; }
    .ha-ai .ha-flag { font-size:1.35rem; line-height:1; }
    .ha-ai .ha-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:.82rem; }
    .ha-ai .ha-slide { background:#1e1b4b; color:#fff; border-radius:.5rem; padding:1rem 1.25rem; min-height:170px; border-inline-start:6px solid #8b5cf6; }
    .ha-ai .ha-slide h6 { color:#fff; font-size:1rem; }
    .ha-ai .ha-slide ul { padding-inline-start:1.1rem; margin-bottom:.5rem; color:#e0e7ff; font-size:.88rem; }
    .ha-ai .ha-slide .ha-narration { color:#c7d2fe; font-size:.8rem; border-top:1px solid rgba(255,255,255,.12); padding-top:.5rem; }
    .ha-ai [dir="rtl"] { text-align:right; }
    .ha-ai .ha-progress { height:6px; }
    .ha-ai .ha-chip { cursor:pointer; user-select:none; }
    .ha-ai .ha-chip.active { background:#6f42c1 !important; color:#fff !important; }
    .ha-ai textarea.ha-json { font-family: ui-monospace, Consolas, monospace; font-size:.8rem; min-height:420px; }
    @media (prefers-reduced-motion: reduce) { .ha-ai .ha-provider-card { transition:none; } .ha-ai .ha-provider-card:hover { transform:none; } }
</style>
<div class="ha-ai">
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0">
                <h4 class="page-title mb-3"><i class="mdi mdi-robot-happy-outline title_icon"></i> <?php echo html_escape($page_title); ?></h4>
                <nav class="ha-tabs" aria-label="AI Studio sections">
                    <?php foreach ($ha_tabs as $key => $t): if (empty($ha_can[$t[3]])) continue; ?>
                        <a href="<?php echo site_url($t[0]); ?>" class="<?php echo $ha_tab === $key ? 'active' : ''; ?>" <?php echo $ha_tab === $key ? 'aria-current="page"' : ''; ?>>
                            <i class="mdi <?php echo $t[2]; ?>"></i> <?php echo $t[1]; ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?php echo site_url('account_security'); ?>" class="ms-auto"><i class="mdi mdi-shield-key-outline"></i> Security &amp; API keys</a>
                </nav>
            </div>
        </div>
    </div>
</div>
<script>
    window.HA_CSRF = <?php echo json_encode(ha_csrf_token()); ?>;
    window.haPost = function (url, data) {
        var body = new URLSearchParams(data || {});
        body.append('ha_csrf', window.HA_CSRF);
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); });
    };
</script>
