<?php
/**
 * MCP & AI connections console. Tabs: overview, levels, connections, console, activity, setup.
 * Every string goes through hkp_t (Arabic in language/hkp/ar.php); direction comes from the layout.
 */
$base = hkp_url('mcp');
$tabs = array('overview' => array(hkp_t('Overview'), 'gauge'), 'levels' => array(hkp_t('Access levels'), 'lock'), 'connections' => array(hkp_t('Connections'), 'users'),
    'console' => array(hkp_t('Test console'), 'play'), 'activity' => array(hkp_t('Activity'), 'pulse'), 'setup' => array(hkp_t('Setup'), 'cog'));
$levels = array(
    'read' => array('label' => hkp_t('Read access'), 'icon' => 'search', 'tag' => hkp_t('Look, never touch'),
        'text' => hkp_t('Read pages, courses, articles, SOPs, media and approval status the person can already see. Nothing can change.')),
    'write' => array('label' => hkp_t('Write access'), 'icon' => 'pen', 'tag' => hkp_t('Draft, never publish'),
        'text' => hkp_t('Everything in Read, plus create and edit private drafts of content, courses and media. Nothing goes live.')),
    'full' => array('label' => hkp_t('Full access'), 'icon' => 'crown', 'tag' => hkp_t('Publish with approval'),
        'text' => hkp_t('Everything in Write, plus request publication and publish after another person approves. Audit history only for administrators who hold it.')),
);
$scope_label = array('altus.read' => hkp_t('Read'), 'altus.content.write' => hkp_t('Content drafts'), 'altus.course.write' => hkp_t('Course drafts'),
    'altus.media.write' => hkp_t('Media uploads'), 'altus.publish' => hkp_t('Publish with approval'), 'altus.admin' => hkp_t('Full audit history'));
$when = function ($dt) { return !empty($dt) ? "\u{2066}" . gmdate('d M Y, H:i', strtotime($dt . ' UTC')) . " UTC\u{2069}" : '—'; };
$name = function ($r) { $n = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); return $n !== '' ? $n : ($r['email'] ?? '—'); };
$chip = function ($level) use ($levels) { return '<span class="mcpc-level mcpc-level--' . hkp_h($level) . '">' . hkp_h($levels[$level]['label'] ?? hkp_t('Custom')) . '</span>'; };
$status_chip = function ($s) { $map = array('ok' => hkp_t('OK'), 'error' => hkp_t('Error'), 'denied' => hkp_t('Denied')); return '<span class="mcpc-status mcpc-status--' . hkp_h($s) . '">' . hkp_h($map[$s] ?? $s) . '</span>'; };
$copy = function ($text, $label) { return '<button type="button" class="mcpc-btn mcpc-btn--quiet mcpc-copy" data-copy="' . hkp_h($text) . '">' . hkp_icon('file') . '<span>' . hkp_h($label) . '</span></button>'; };
$i18n = array(
    'copied' => hkp_t('Copied to the clipboard.'), 'copy_failed' => hkp_t('Copy failed. Select the text and copy it manually.'),
    'checking' => hkp_t('Checking…'), 'healthy' => hkp_t('All checks passed'), 'unhealthy' => hkp_t('Some checks failed'), 'in_process' => hkp_t('In-process'), 'http' => hkp_t('Over HTTP'),
    'running' => hkp_t('Running…'), 'no_tool' => hkp_t('Choose a tool on the left to build a request.'), 'execute' => hkp_t('Execute'),
    'form' => hkp_t('Form'), 'raw' => hkp_t('Raw JSON'), 'invalid_json' => hkp_t('The arguments are not valid JSON.'), 'required' => hkp_t('required'),
    'locked' => hkp_t('Not available at this level'), 'available' => hkp_t('Available'), 'read_only' => hkp_t('Read-only'), 'writes' => hkp_t('Writes a draft'),
    'destructive' => hkp_t('Changes live content'), 'idempotent' => hkp_t('Idempotent'), 'dry_unsafe' => hkp_t('Not available in dry-run'),
    'result' => hkp_t('Result'), 'structured' => hkp_t('structuredContent'), 'request' => hkp_t('Request'), 'response' => hkp_t('Response'),
    'ok' => hkp_t('OK'), 'error' => hkp_t('Error'), 'denied' => hkp_t('Denied'), 'blocked' => hkp_t('Blocked by level'), 'allowed' => hkp_t('Allowed'), 'fail' => hkp_t('Failed'),
    'passed' => hkp_t('passed'), 'tools' => hkp_t('tools'), 'level_read' => $levels['read']['label'], 'level_write' => $levels['write']['label'], 'level_full' => $levels['full']['label'],
    'group_read' => hkp_t('Read tools'), 'group_write' => hkp_t('Draft tools'), 'group_full' => hkp_t('Publishing tools'), 'no_match' => hkp_t('No tool matches that search.'),
    'confirm_live' => hkp_t('Dry-run is off. This call will really create or change private drafts as you. Continue?'),
    'session_ok' => hkp_t('Session initialised'), 'listed' => hkp_t('listed'), 'expected' => hkp_t('Expected'), 'outcome' => hkp_t('Outcome'), 'tool' => hkp_t('Tool'),
    'network' => hkp_t('The request did not complete. Check your connection and sign-in.'), 'dry_badge' => hkp_t('Dry-run · rolled back'), 'live_badge' => hkp_t('Live write'),
    'effective' => hkp_t('Effective scopes'), 'none' => hkp_t('none'), 'details' => hkp_t('Details'), 'loading' => hkp_t('Loading…'),
    'f_time' => hkp_t('Time'), 'f_source' => hkp_t('Source'), 'f_client' => hkp_t('Client'), 'f_user' => hkp_t('User'), 'f_status' => hkp_t('Status'), 'f_code' => hkp_t('Error code'),
    'f_latency' => hkp_t('Latency'), 'f_request' => hkp_t('Request id'), 'f_summary' => hkp_t('Arguments'), 'f_audit' => hkp_t('Audit entries'), 'f_method' => hkp_t('Method'),
);
?>
<link rel="stylesheet" href="<?= hkp_asset('assets/hkp/mcp-console.css') ?>">
<div class="mcpc" id="mcpc" data-tab="<?= hkp_h($tab) ?>" data-csrf="<?= hkp_h($csrf) ?>" data-url-rpc="<?= hkp_h($base . '/rpc') ?>" data-url-smoke="<?= hkp_h($base . '/smoke') ?>" data-url-health="<?= hkp_h($base . '/health') ?>" data-url-call="<?= hkp_h($base . '/call/') ?>">
<svg width="0" height="0" class="mcpc-defs" aria-hidden="true"><filter id="mcpc-grain"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" stitchTiles="stitch"/><feColorMatrix values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 .5 0"/></filter></svg>

<header class="mcpc-head">
  <div>
    <div class="hkp-eyebrow"><?= hkp_e('Connected AI') ?></div>
    <h1><?= hkp_e('MCP & AI connections') ?></h1>
    <p><?= hkp_e('Decide what AI assistants may do in ALTUS, connect them, test every tool and watch each call.') ?></p>
  </div>
  <div class="mcpc-head__state">
    <span class="mcpc-pulse mcpc-pulse--<?= $enabled ? 'on' : 'off' ?>" role="status"><i aria-hidden="true"></i><?= hkp_h($enabled ? hkp_t('Server online') : hkp_t('Server switched off')) ?></span>
    <a class="mcpc-btn mcpc-btn--ghost" href="<?= hkp_url('cms/integrations') ?>?tab=approvals"><?= hkp_icon('check') ?><span><?= hkp_e('Approvals queue') ?></span></a>
  </div>
</header>

<?php if (!$installed || !$ready): ?>
<div class="mcpc-banner" role="alert"><?= hkp_icon('alert') ?><div><strong><?= hkp_e('Database update needed') ?></strong><p><?= hkp_e('Run the MCP migrations (032 and 033): php index.php ha_cli migrate') ?></p></div></div>
<?php endif; ?>

<nav class="mcpc-tabs" aria-label="<?= hkp_e('MCP console sections') ?>">
  <?php foreach ($tabs as $k => $t): ?><a href="<?= hkp_h($base . '?tab=' . $k) ?>" data-tab-link="<?= hkp_h($k) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= hkp_icon($t[1]) ?><span><?= hkp_h($t[0]) ?></span></a><?php endforeach; ?>
</nav>
<p class="hkp-sr" id="mcpc-live" aria-live="polite"></p>

<?php if ($tab === 'overview'): ?>
<section class="mcpc-hero" aria-labelledby="mcpc-endpoint-h">
  <div class="mcpc-hero__glow" aria-hidden="true"></div>
  <div class="mcpc-hero__main">
    <h2 id="mcpc-endpoint-h" class="mcpc-hero__label"><?= hkp_e('Your MCP endpoint') ?></h2>
    <div class="mcpc-endpoint"><code dir="ltr" id="mcpc-endpoint"><?= hkp_h($endpoint) ?></code><button type="button" class="mcpc-btn mcpc-btn--light mcpc-copy" data-copy="<?= hkp_h($endpoint) ?>" aria-label="<?= hkp_e('Copy MCP URL') ?>"><?= hkp_icon('file') ?><span><?= hkp_e('Copy') ?></span></button></div>
    <ul class="mcpc-facts">
      <li><?= hkp_e('Streamable HTTP') ?></li><li dir="ltr">MCP 2025-06-18</li><li><?= hkp_e('OAuth 2.1 with PKCE') ?></li><li><?= hkp_e('No Node process') ?></li>
    </ul>
  </div>
  <div class="mcpc-hero__switch">
    <span class="mcpc-switch" role="switch" aria-checked="<?= $enabled ? 'true' : 'false' ?>" aria-disabled="true" aria-label="<?= hkp_e('MCP server enabled') ?>"><i></i></span>
    <div><strong><?= hkp_h($enabled ? hkp_t('Enabled') : hkp_t('Switched off')) ?></strong><small><?= hkp_e('Set by ALTUS_MCP_ENABLED in the server environment.') ?></small></div>
  </div>
</section>

<div class="mcpc-kpis">
  <?php
  $max = max(1, max($o['spark']));
  $kpis = array(
      array('users', hkp_t('OAuth clients'), $o['clients'], hkp_t('registered apps'), $base . '?tab=connections'),
      array('shield', hkp_t('Active connections'), $o['grants'], hkp_t('approved by people'), $base . '?tab=connections'),
      array('lock', hkp_t('Personal connections'), $o['personal'], hkp_t('token-based, unexpired'), $base . '?tab=levels'),
      array('alert', hkp_t('Errors (24h)'), $o['errors'], hkp_t('{n} blocked by level', array('n' => $o['denied'])), $base . '?tab=activity&status=error'),
      array('clipboard', hkp_t('Pending approvals'), $o['pending'], hkp_t('waiting for a person'), hkp_url('cms/integrations') . '?tab=approvals'),
      array('grid', hkp_t('Tools'), $o['tools'], hkp_t('read, draft and publish'), $base . '?tab=console'),
  );
  ?>
  <a class="mcpc-kpi mcpc-kpi--wide" href="<?= hkp_h($base . '?tab=activity') ?>">
    <span class="mcpc-kpi__icon"><?= hkp_icon('pulse') ?></span>
    <span class="mcpc-kpi__body"><small><?= hkp_e('Calls (24h)') ?></small><strong><?= (int) $o['calls'] ?></strong><em><?= hkp_e('{ms} ms average', array('ms' => $o['avg_ms'])) ?></em></span>
    <svg class="mcpc-spark" viewBox="0 0 240 48" preserveAspectRatio="none" role="img" aria-label="<?= hkp_e('Calls per hour over the last 24 hours') ?>">
      <?php foreach ($o['spark'] as $i => $v): $h = $v ? max(3, round($v / $max * 44)) : 1; $e = $o['spark_err'][$i] ? max(2, round($o['spark_err'][$i] / $max * 44)) : 0; ?>
      <rect x="<?= $i * 10 + 1 ?>" y="<?= 48 - $h ?>" width="7" height="<?= $h ?>" rx="2" class="mcpc-spark__bar"><title><?= hkp_h(hkp_t('{n} calls', array('n' => $v))) ?></title></rect>
      <?php if ($e): ?><rect x="<?= $i * 10 + 1 ?>" y="<?= 48 - $e ?>" width="7" height="<?= $e ?>" rx="2" class="mcpc-spark__err"/><?php endif; ?>
      <?php endforeach; ?>
    </svg>
  </a>
  <?php foreach ($kpis as $k): ?>
  <a class="mcpc-kpi<?= $k[0] === 'alert' && $k[2] ? ' mcpc-kpi--alert' : '' ?>" href="<?= hkp_h($k[4]) ?>">
    <span class="mcpc-kpi__icon"><?= hkp_icon($k[0]) ?></span>
    <span class="mcpc-kpi__body"><small><?= hkp_h($k[1]) ?></small><strong><?= (int) $k[2] ?></strong><em><?= hkp_h($k[3]) ?></em></span>
  </a>
  <?php endforeach; ?>
</div>

<div class="mcpc-grid2">
  <section class="mcpc-card" aria-labelledby="mcpc-health-h">
    <div class="mcpc-card__head"><div><h2 id="mcpc-health-h"><?= hkp_e('Live health checks') ?></h2><p><?= hkp_e('Discovery metadata and the 401 challenge that MCP clients rely on.') ?></p></div>
      <button type="button" class="mcpc-btn mcpc-btn--ghost" data-health-run><?= hkp_icon('pulse') ?><span><?= hkp_e('Run again') ?></span></button></div>
    <ul class="mcpc-checks" id="mcpc-checks" aria-busy="true"><li class="mcpc-skeleton"></li><li class="mcpc-skeleton"></li><li class="mcpc-skeleton"></li></ul>
    <p class="mcpc-muted" id="mcpc-health-summary"></p>
  </section>
  <section class="mcpc-card" aria-labelledby="mcpc-recent-h">
    <div class="mcpc-card__head"><div><h2 id="mcpc-recent-h"><?= hkp_e('Recent calls') ?></h2><p><?= hkp_e('The latest requests from every connection.') ?></p></div><a class="mcpc-btn mcpc-btn--ghost" href="<?= hkp_h($base . '?tab=activity') ?>"><span><?= hkp_e('View all activity') ?></span><?= hkp_icon('arrow', 'mcpc-flip') ?></a></div>
    <?php if (!$o['recent']): ?><div class="mcpc-empty"><?= hkp_icon('spark') ?><p><?= hkp_e('No calls yet. Open the Test console and run the smoke test to see the first entries.') ?></p><a class="mcpc-btn" href="<?= hkp_h($base . '?tab=console') ?>"><?= hkp_icon('play') ?><span><?= hkp_e('Open Test console') ?></span></a></div>
    <?php else: ?><ul class="mcpc-feed"><?php foreach ($o['recent'] as $r): ?><li><?= $status_chip($r['status']) ?><code dir="ltr"><?= hkp_h($r['tool'] ?: $r['method']) ?></code><span class="mcpc-muted"><?= hkp_h($r['client_name'] ?: $r['client_id'] ?: '—') ?> · <?= hkp_h($name($r)) ?></span><time><?= hkp_h($when($r['created_at'])) ?></time></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($o['top_tools']): ?><h3 class="mcpc-sub"><?= hkp_e('Most used tools (24h)') ?></h3><ol class="mcpc-bars"><?php $tmax = max(1, (int) $o['top_tools'][0]['n']); foreach ($o['top_tools'] as $t): ?><li><code dir="ltr"><?= hkp_h($t['tool']) ?></code><span class="mcpc-bars__track"><span style="inline-size:<?= round($t['n'] / $tmax * 100) ?>%"></span></span><b><?= (int) $t['n'] ?></b></li><?php endforeach; ?></ol><?php endif; ?>
  </section>
</div>

<?php elseif ($tab === 'levels'): ?>
<?php if (!empty($new_token)): ?>
<section class="mcpc-secret" aria-labelledby="mcpc-secret-h" tabindex="-1" id="mcpc-secret">
  <div class="mcpc-secret__icon"><?= hkp_icon('lock') ?></div>
  <div class="mcpc-secret__body">
    <h2 id="mcpc-secret-h"><?= hkp_e('Personal connection created') ?></h2>
    <p><?= hkp_e('Copy this token now. It is shown only once; ALTUS keeps only a hash of it.') ?></p>
    <div class="mcpc-endpoint mcpc-endpoint--secret"><code dir="ltr" id="mcpc-new-token"><?= hkp_h($new_token['token']) ?></code><?= $copy($new_token['token'], hkp_t('Copy token')) ?></div>
    <p class="mcpc-muted"><?= hkp_h($new_token['name']) ?> · <?= hkp_h($new_token['email']) ?> · <?= $chip(Ha_mcp_oauth::valid_level($new_token['level']) ? $new_token['level'] : 'custom') ?> · <?= hkp_e('expires') ?> <?= hkp_h($when($new_token['expires_at'])) ?></p>
    <?php $cmd = 'claude mcp add --transport http altus ' . $endpoint . ' --header "Authorization: Bearer ' . $new_token['token'] . '"'; ?>
    <div class="mcpc-snippet"><pre dir="ltr"><code><?= hkp_h($cmd) ?></code></pre><?= $copy($cmd, hkp_t('Copy Claude Code command')) ?></div>
  </div>
</section>
<?php endif; ?>

<section aria-labelledby="mcpc-levels-h">
  <div class="mcpc-section-head"><div><h2 id="mcpc-levels-h"><?= hkp_e('Three access levels') ?></h2><p><?= hkp_e('A level is a ceiling. The person\'s own ALTUS permissions are checked again on every call, so a level never grants more than they already have.') ?></p></div></div>
  <div class="mcpc-levels">
    <?php $counts = array('read' => 0, 'write' => 0, 'full' => 0); foreach ($catalogue as $t) { $counts['full']++; if ($t['group'] !== 'full') $counts['write']++; if ($t['group'] === 'read') $counts['read']++; } ?>
    <?php foreach ($levels as $k => $l): ?>
    <article class="mcpc-levelcard mcpc-levelcard--<?= $k ?>">
      <div class="mcpc-levelcard__top"><span class="mcpc-levelcard__icon"><?= hkp_icon($l['icon']) ?></span><span class="mcpc-levelcard__tag"><?= hkp_h($l['tag']) ?></span></div>
      <h3><?= hkp_h($l['label']) ?></h3>
      <p><?= hkp_h($l['text']) ?></p>
      <ul class="mcpc-scopes"><?php foreach (Ha_mcp_oauth::LEVELS[$k] as $s): ?><li title="<?= hkp_h($s) ?>"><?= hkp_h($scope_label[$s]) ?></li><?php endforeach; ?></ul>
      <div class="mcpc-levelcard__meter" aria-label="<?= hkp_h(hkp_t('{n} of {total} tools', array('n' => $counts[$k], 'total' => count($catalogue)))) ?>"><span style="inline-size:<?= round($counts[$k] / max(1, count($catalogue)) * 100) ?>%"></span></div>
      <small><?= hkp_e('{n} of {total} tools', array('n' => $counts[$k], 'total' => count($catalogue))) ?></small>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<div class="mcpc-grid2 mcpc-grid2--wide">
<section class="mcpc-card" aria-labelledby="mcpc-consent-h">
  <div class="mcpc-card__head"><div><h2 id="mcpc-consent-h"><?= hkp_e('OAuth consent screen') ?></h2><p><?= hkp_e('What a person sees when Claude, Cursor or another app asks to connect.') ?></p></div></div>
  <form method="post" action="<?= hkp_h($base . '/action') ?>" class="mcpc-form"><?= ha_csrf_field() ?><input type="hidden" name="action" value="settings">
    <div class="mcpc-field"><label for="mcpc-default"><?= hkp_e('Pre-selected level') ?></label><select id="mcpc-default" name="default_level"><?php foreach ($levels as $k => $l): ?><option value="<?= $k ?>"<?= $offer['default'] === $k ? ' selected' : '' ?>><?= hkp_h($l['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mcpc-field"><label for="mcpc-max"><?= hkp_e('Highest level offered') ?></label><select id="mcpc-max" name="max_level"><?php foreach ($levels as $k => $l): ?><option value="<?= $k ?>"<?= $offer['max'] === $k ? ' selected' : '' ?>><?= hkp_h($l['label']) ?></option><?php endforeach; ?></select></div>
    <p class="mcpc-muted"><?= hkp_e('Levels above the highest level are shown as unavailable. A client can be capped lower under Connections; caps apply to every request, including existing connections of that client.') ?></p>
    <button class="mcpc-btn"><?= hkp_icon('check') ?><span><?= hkp_e('Save consent levels') ?></span></button>
  </form>
</section>

<section class="mcpc-card" aria-labelledby="mcpc-pt-h">
  <div class="mcpc-card__head"><div><h2 id="mcpc-pt-h"><?= hkp_e('New personal connection') ?></h2><p><?= hkp_e('A bearer token for tools that cannot sign in with OAuth. It acts as the chosen person, at the chosen level, until it expires or you revoke it.') ?></p></div></div>
  <form method="post" action="<?= hkp_h($base . '/action') ?>" class="mcpc-form" data-pt-form><?= ha_csrf_field() ?><input type="hidden" name="action" value="pt_create">
    <div class="mcpc-field"><label for="mcpc-pt-user"><?= hkp_e('Person') ?></label><select id="mcpc-pt-user" name="user_id" required><option value=""><?= hkp_e('Choose a person…') ?></option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === (int) $this->ha_auth->id() ? ' selected' : '' ?>><?= hkp_h($name($u) . ' · ' . $u['email']) ?></option><?php endforeach; ?></select></div>
    <div class="mcpc-field"><label for="mcpc-pt-name"><?= hkp_e('Connection name') ?></label><input id="mcpc-pt-name" name="name" maxlength="120" required placeholder="<?= hkp_e('e.g. Claude Code on the content team laptop') ?>"></div>
    <fieldset class="mcpc-radios"><legend><?= hkp_e('Access level') ?></legend>
      <?php foreach ($levels as $k => $l): ?><label class="mcpc-radio"><input type="radio" name="level" value="<?= $k ?>"<?= $k === 'read' ? ' checked' : '' ?>><span><?= hkp_icon($l['icon']) ?><b><?= hkp_h($l['label']) ?></b></span></label><?php endforeach; ?>
      <label class="mcpc-radio"><input type="radio" name="level" value="custom"><span><?= hkp_icon('cog') ?><b><?= hkp_e('Custom') ?></b></span></label>
    </fieldset>
    <fieldset class="mcpc-custom" data-pt-custom hidden><legend><?= hkp_e('Custom scopes') ?></legend>
      <?php foreach ($scope_label as $s => $lab): ?><label class="mcpc-check"><input type="checkbox" name="scopes[]" value="<?= hkp_h($s) ?>"<?= $s === 'altus.read' ? ' checked disabled' : '' ?>><span><?= hkp_h($lab) ?> <code dir="ltr"><?= hkp_h($s) ?></code></span></label><?php endforeach; ?>
      <input type="hidden" name="scopes[]" value="altus.read">
    </fieldset>
    <div class="mcpc-field"><label for="mcpc-pt-days"><?= hkp_e('Expires after') ?></label><select id="mcpc-pt-days" name="days"><?php foreach (array(7, 30, 90, 180, 365) as $d): ?><option value="<?= $d ?>"<?= $d === 30 ? ' selected' : '' ?>><?= hkp_e('{n} days', array('n' => $d)) ?></option><?php endforeach; ?></select></div>
    <button class="mcpc-btn"><?= hkp_icon('lock') ?><span><?= hkp_e('Create token') ?></span></button>
  </form>
</section>
</div>

<section class="mcpc-card" aria-labelledby="mcpc-ptlist-h">
  <div class="mcpc-card__head"><div><h2 id="mcpc-ptlist-h"><?= hkp_e('Personal connections') ?></h2><p><?= hkp_e('Tokens are stored as SHA-256 hashes. Revoking ends every open session at once.') ?></p></div></div>
  <?php if (!$personal): ?><div class="mcpc-empty"><?= hkp_icon('lock') ?><p><?= hkp_e('No personal connections yet.') ?></p></div><?php else: ?>
  <div class="mcpc-table-wrap"><table class="mcpc-table"><thead><tr><th><?= hkp_e('Name') ?></th><th><?= hkp_e('Person') ?></th><th><?= hkp_e('Level') ?></th><th><?= hkp_e('Token') ?></th><th><?= hkp_e('Last used') ?></th><th><?= hkp_e('Expires') ?></th><th><span class="hkp-sr"><?= hkp_e('Actions') ?></span></th></tr></thead><tbody>
  <?php foreach ($personal as $p): $expired = strtotime($p['expires_at'] . ' UTC') < time(); ?>
    <tr<?= $p['revoked_at'] || $expired ? ' class="is-muted"' : '' ?>><td><strong><?= hkp_h($p['name']) ?></strong></td><td><?= hkp_h($name($p)) ?></td><td><?= $chip(Ha_mcp_oauth::valid_level($p['level']) ? $p['level'] : 'custom') ?></td><td><code dir="ltr"><?= hkp_h($p['token_hint']) ?></code></td><td><?= hkp_h($when($p['last_used_at'])) ?></td><td><?= hkp_h($when($p['expires_at'])) ?></td>
      <td class="mcpc-actions"><?php if ($p['revoked_at']): ?><span class="mcpc-status mcpc-status--denied"><?= hkp_e('Revoked') ?></span><?php elseif ($expired): ?><span class="mcpc-status mcpc-status--error"><?= hkp_e('Expired') ?></span><?php else: ?>
      <form method="post" action="<?= hkp_h($base . '/action') ?>" data-confirm="<?= hkp_e('Revoke this personal connection? Clients using it stop working immediately.') ?>"><?= ha_csrf_field() ?><input type="hidden" name="action" value="pt_revoke"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="mcpc-btn mcpc-btn--danger"><?= hkp_e('Revoke') ?></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<?php elseif ($tab === 'connections'): ?>
<section class="mcpc-card" aria-labelledby="mcpc-grants-h">
  <div class="mcpc-card__head"><div><h2 id="mcpc-grants-h"><?= hkp_e('Active connections') ?></h2><p><?= hkp_e('Each row is one person\'s approval of one app. Lowering a level takes effect on the very next request.') ?></p></div></div>
  <?php if (!$c['grants']): ?><div class="mcpc-empty"><?= hkp_icon('users') ?><p><?= hkp_e('Nobody has connected an app yet. Share the Setup instructions with your content team.') ?></p><a class="mcpc-btn" href="<?= hkp_h($base . '?tab=setup') ?>"><?= hkp_icon('cog') ?><span><?= hkp_e('Open Setup') ?></span></a></div><?php else: ?>
  <div class="mcpc-table-wrap"><table class="mcpc-table"><thead><tr><th><?= hkp_e('Person') ?></th><th><?= hkp_e('App') ?></th><th><?= hkp_e('Level') ?></th><th><?= hkp_e('Scopes') ?></th><th><?= hkp_e('Last used') ?></th><th><?= hkp_e('Requests') ?></th><th><?= hkp_e('Change level') ?></th><th><span class="hkp-sr"><?= hkp_e('Actions') ?></span></th></tr></thead><tbody>
  <?php foreach ($c['grants'] as $g): $rank = Ha_mcp_oauth::LEVEL_RANK[$g['level']]; ?>
    <tr><td><?= hkp_h($g['email'] ?: '#' . $g['user_id']) ?></td><td><strong><?= hkp_h($g['client_name'] ?: $g['client_id']) ?></strong><br><code dir="ltr" class="mcpc-muted"><?= hkp_h($g['client_id']) ?></code></td><td><?= $chip($g['level']) ?></td>
      <td><ul class="mcpc-scopes mcpc-scopes--inline"><?php foreach ($g['scopes'] as $s): ?><li title="<?= hkp_h($s) ?>"><?= hkp_h($scope_label[$s] ?? $s) ?></li><?php endforeach; ?></ul></td>
      <td><?= hkp_h($when($g['last_used_at'])) ?></td><td><?= (int) $g['requests'] ?></td>
      <td><?php if ($rank > 1): ?><form method="post" action="<?= hkp_h($base . '/action') ?>" class="mcpc-inline"><?= ha_csrf_field() ?><input type="hidden" name="action" value="grant_level"><input type="hidden" name="grant_id" value="<?= hkp_h($g['id']) ?>"><label class="hkp-sr" for="gl-<?= hkp_h($g['id']) ?>"><?= hkp_e('New level') ?></label><select id="gl-<?= hkp_h($g['id']) ?>" name="level"><?php foreach ($levels as $k => $l): if (Ha_mcp_oauth::LEVEL_RANK[$k] >= $rank) continue; ?><option value="<?= $k ?>"><?= hkp_h($l['label']) ?></option><?php endforeach; ?></select><button class="mcpc-btn mcpc-btn--ghost"><?= hkp_e('Lower') ?></button></form><?php else: ?><span class="mcpc-muted"><?= hkp_e('Lowest level') ?></span><?php endif; ?></td>
      <td class="mcpc-actions"><form method="post" action="<?= hkp_h($base . '/action') ?>" data-confirm="<?= hkp_e('Revoke this connection? The app must ask the person again.') ?>"><?= ha_csrf_field() ?><input type="hidden" name="action" value="grant_revoke"><input type="hidden" name="grant_id" value="<?= hkp_h($g['id']) ?>"><button class="mcpc-btn mcpc-btn--danger"><?= hkp_e('Revoke') ?></button></form></td></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<section class="mcpc-card" aria-labelledby="mcpc-clients-h">
  <div class="mcpc-card__head"><div><h2 id="mcpc-clients-h"><?= hkp_e('Registered apps') ?></h2><p><?= hkp_e('Apps register themselves (OAuth dynamic registration). Cap an app\'s level to limit every person who uses it.') ?></p></div></div>
  <?php $live = array_values(array_filter($c['clients'], function ($x) { return !$x['revoked_at']; })); ?>
  <?php if (!$live): ?><div class="mcpc-empty"><?= hkp_icon('plug') ?><p><?= hkp_e('No apps have registered yet.') ?></p></div><?php else: ?>
  <div class="mcpc-table-wrap"><table class="mcpc-table"><thead><tr><th><?= hkp_e('App') ?></th><th><?= hkp_e('Returns to') ?></th><th><?= hkp_e('Connections') ?></th><th><?= hkp_e('Registered') ?></th><th><?= hkp_e('Maximum level') ?></th><th><span class="hkp-sr"><?= hkp_e('Actions') ?></span></th></tr></thead><tbody>
  <?php foreach ($live as $cl): $host = parse_url($cl['redirects'][0] ?? '', PHP_URL_HOST) ?: (parse_url($cl['redirects'][0] ?? '', PHP_URL_SCHEME) ?: '—'); ?>
    <tr><td><strong><?= hkp_h($cl['client_name']) ?></strong><br><code dir="ltr" class="mcpc-muted"><?= hkp_h($cl['client_id']) ?></code></td><td dir="ltr"><?= hkp_h($host) ?></td><td><?= (int) $cl['grants'] ?></td><td><?= hkp_h($when($cl['created_at'])) ?></td>
      <td><form method="post" action="<?= hkp_h($base . '/action') ?>" class="mcpc-inline"><?= ha_csrf_field() ?><input type="hidden" name="action" value="client_level"><input type="hidden" name="client_id" value="<?= hkp_h($cl['client_id']) ?>"><label class="hkp-sr" for="cl-<?= hkp_h($cl['client_id']) ?>"><?= hkp_e('Maximum level') ?></label><select id="cl-<?= hkp_h($cl['client_id']) ?>" name="max_level"><option value=""><?= hkp_e('No cap') ?></option><?php foreach ($levels as $k => $l): ?><option value="<?= $k ?>"<?= ($cl['max_level'] ?? '') === $k ? ' selected' : '' ?>><?= hkp_h($l['label']) ?></option><?php endforeach; ?></select><button class="mcpc-btn mcpc-btn--ghost"><?= hkp_e('Save') ?></button></form></td>
      <td class="mcpc-actions"><form method="post" action="<?= hkp_h($base . '/action') ?>" data-confirm="<?= hkp_e('Revoke this app for everyone? All of its connections end now.') ?>"><?= ha_csrf_field() ?><input type="hidden" name="action" value="client_revoke"><input type="hidden" name="client_id" value="<?= hkp_h($cl['client_id']) ?>"><button class="mcpc-btn mcpc-btn--danger"><?= hkp_e('Revoke app') ?></button></form></td></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<?php elseif ($tab === 'console'): ?>
<section class="mcpc-toolbar" aria-label="<?= hkp_e('Test settings') ?>">
  <fieldset class="mcpc-seg"><legend><?= hkp_e('Test as') ?></legend>
    <?php foreach ($levels as $k => $l): ?><label><input type="radio" name="mcpc-level" value="<?= $k ?>"<?= $k === 'read' ? ' checked' : '' ?>><span><?= hkp_icon($l['icon']) ?><?= hkp_h($l['label']) ?></span></label><?php endforeach; ?>
  </fieldset>
  <label class="mcpc-toggle"><input type="checkbox" id="mcpc-dry" checked><span class="mcpc-toggle__track" aria-hidden="true"><i></i></span><span><b><?= hkp_e('Dry-run') ?></b><small><?= hkp_e('Writes run, then roll back') ?></small></span></label>
  <div class="mcpc-toolbar__actions">
    <button type="button" class="mcpc-btn mcpc-btn--ghost" data-rpc-quick="initialize"><?= hkp_icon('plug') ?><span><?= hkp_e('Initialize') ?></span></button>
    <button type="button" class="mcpc-btn mcpc-btn--ghost" data-rpc-quick="tools/list"><?= hkp_icon('list') ?><span><?= hkp_e('List tools') ?></span></button>
    <button type="button" class="mcpc-btn" data-smoke><?= hkp_icon('check') ?><span><?= hkp_e('Run smoke test') ?></span></button>
  </div>
  <p class="mcpc-toolbar__note"><?= hkp_icon('shield') ?><span><?= hkp_e('Calls run as you, through the same server code as a real client, capped at the level you pick. The smoke test only writes in dry-run.') ?></span></p>
</section>

<section class="mcpc-card mcpc-smoke" id="mcpc-smoke" hidden aria-labelledby="mcpc-smoke-h" tabindex="-1">
  <div class="mcpc-card__head"><div><h2 id="mcpc-smoke-h"><?= hkp_e('Smoke test') ?></h2><p id="mcpc-smoke-sum"></p></div></div>
  <div class="mcpc-table-wrap"><table class="mcpc-table mcpc-matrix" id="mcpc-matrix"></table></div>
</section>

<div class="mcpc-console">
  <aside class="mcpc-card mcpc-tools" aria-labelledby="mcpc-tools-h">
    <div class="mcpc-card__head"><div><h2 id="mcpc-tools-h"><?= hkp_e('Tools') ?></h2><p id="mcpc-tools-count"><?= hkp_e('{n} tools', array('n' => count($catalogue))) ?></p></div></div>
    <label class="hkp-sr" for="mcpc-tool-q"><?= hkp_e('Search tools') ?></label>
    <input type="search" id="mcpc-tool-q" class="mcpc-search" placeholder="<?= hkp_e('Search tools…') ?>">
    <div id="mcpc-tool-list" class="mcpc-tool-list" role="list"></div>
  </aside>
  <section class="mcpc-card mcpc-runner" aria-labelledby="mcpc-runner-h">
    <div class="mcpc-card__head"><div><h2 id="mcpc-runner-h"><?= hkp_e('Request') ?></h2><p id="mcpc-runner-sub"><?= hkp_e('Choose a tool on the left to build a request.') ?></p></div>
      <div class="mcpc-modes" role="group" aria-label="<?= hkp_e('Editor mode') ?>"><button type="button" class="is-on" data-mode="form" aria-pressed="true"><?= hkp_e('Form') ?></button><button type="button" data-mode="raw" aria-pressed="false"><?= hkp_e('Raw JSON') ?></button></div></div>
    <div id="mcpc-tool-meta" class="mcpc-tool-meta"><div class="mcpc-empty"><?= hkp_icon('spark') ?><p><?= hkp_e('Pick a tool, fill in its arguments and execute it. Locked tools show what the chosen level blocks; try them anyway to see the server refuse.') ?></p></div></div>
    <form id="mcpc-args" class="mcpc-form mcpc-args" novalidate></form>
    <label class="hkp-sr" for="mcpc-raw"><?= hkp_e('Raw JSON arguments') ?></label>
    <textarea id="mcpc-raw" class="mcpc-raw" spellcheck="false" dir="ltr" hidden>{}</textarea>
    <div class="mcpc-runner__go"><button type="button" class="mcpc-btn" id="mcpc-exec" disabled><?= hkp_icon('play') ?><span><?= hkp_e('Execute') ?></span></button><span id="mcpc-write-flag"></span></div>
    <div class="mcpc-result" id="mcpc-result" hidden>
      <div class="mcpc-result__bar"><span id="mcpc-result-status"></span><span id="mcpc-result-ms" class="mcpc-muted"></span><span id="mcpc-result-scopes" class="mcpc-muted"></span></div>
      <div class="mcpc-rtabs" role="tablist" aria-label="<?= hkp_e('Response views') ?>">
        <?php foreach (array('result' => hkp_t('Result'), 'structured' => hkp_t('structuredContent'), 'request' => hkp_t('Request'), 'response' => hkp_t('Response')) as $k => $lab): ?><button type="button" role="tab" id="rt-<?= $k ?>" aria-controls="rp" aria-selected="<?= $k === 'result' ? 'true' : 'false' ?>" data-rtab="<?= $k ?>"<?= $k === 'result' ? '' : ' tabindex="-1"' ?>><?= hkp_h($lab) ?></button><?php endforeach; ?>
      </div>
      <pre class="mcpc-json" id="rp" role="tabpanel" dir="ltr" tabindex="0"></pre>
    </div>
  </section>
</div>
<script type="application/json" id="mcpc-catalogue"><?= json_encode($catalogue, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<?php elseif ($tab === 'activity'): ?>
<form class="mcpc-card mcpc-filters" method="get" action="<?= hkp_h($base) ?>" aria-label="<?= hkp_e('Filter activity') ?>"><input type="hidden" name="tab" value="activity">
  <div class="mcpc-field"><label for="af-tool"><?= hkp_e('Tool') ?></label><select id="af-tool" name="tool"><option value=""><?= hkp_e('All tools') ?></option><?php foreach ($facets['tools'] as $t): ?><option<?= $filters['tool'] === $t ? ' selected' : '' ?>><?= hkp_h($t) ?></option><?php endforeach; ?></select></div>
  <div class="mcpc-field"><label for="af-client"><?= hkp_e('Client') ?></label><select id="af-client" name="client"><option value=""><?= hkp_e('All clients') ?></option><?php foreach ($facets['clients'] as $cl): ?><option value="<?= hkp_h($cl['client_id']) ?>"<?= $filters['client'] === $cl['client_id'] ? ' selected' : '' ?>><?= hkp_h($cl['client_name'] ?: $cl['client_id']) ?></option><?php endforeach; ?></select></div>
  <div class="mcpc-field"><label for="af-user"><?= hkp_e('User') ?></label><select id="af-user" name="user"><option value=""><?= hkp_e('Everyone') ?></option><?php foreach ($facets['users'] as $u): ?><option value="<?= (int) $u['user_id'] ?>"<?= (string) $filters['user'] === (string) $u['user_id'] ? ' selected' : '' ?>><?= hkp_h($u['email']) ?></option><?php endforeach; ?></select></div>
  <div class="mcpc-field"><label for="af-status"><?= hkp_e('Status') ?></label><select id="af-status" name="status"><option value=""><?= hkp_e('Any status') ?></option><?php foreach (array('ok' => hkp_t('OK'), 'error' => hkp_t('Error'), 'denied' => hkp_t('Denied')) as $k => $lab): ?><option value="<?= $k ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= hkp_h($lab) ?></option><?php endforeach; ?></select></div>
  <div class="mcpc-field"><label for="af-source"><?= hkp_e('Source') ?></label><select id="af-source" name="source"><option value=""><?= hkp_e('Any source') ?></option><?php foreach (array('oauth' => hkp_t('OAuth app'), 'personal' => hkp_t('Personal token'), 'console' => hkp_t('Test console')) as $k => $lab): ?><option value="<?= $k ?>"<?= $filters['source'] === $k ? ' selected' : '' ?>><?= hkp_h($lab) ?></option><?php endforeach; ?></select></div>
  <div class="mcpc-field"><label for="af-from"><?= hkp_e('From') ?></label><input id="af-from" type="date" name="from" value="<?= hkp_h($filters['from']) ?>"></div>
  <div class="mcpc-field"><label for="af-to"><?= hkp_e('To') ?></label><input id="af-to" type="date" name="to" value="<?= hkp_h($filters['to']) ?>"></div>
  <div class="mcpc-filters__go"><button class="mcpc-btn"><?= hkp_icon('search') ?><span><?= hkp_e('Apply') ?></span></button><a class="mcpc-btn mcpc-btn--quiet" href="<?= hkp_h($base . '?tab=activity') ?>"><?= hkp_e('Reset') ?></a></div>
</form>
<section class="mcpc-card" aria-labelledby="mcpc-act-h">
  <div class="mcpc-card__head"><div><h2 id="mcpc-act-h"><?= hkp_e('MCP activity') ?></h2><p><?= hkp_e('{n} calls match. Times are UTC. Arguments are summarised, never stored in full.', array('n' => $log['total'])) ?></p></div></div>
  <?php if (!$log['rows']): ?><div class="mcpc-empty"><?= hkp_icon('pulse') ?><p><?= hkp_e('No calls match these filters.') ?></p></div><?php else: ?>
  <div class="mcpc-table-wrap"><table class="mcpc-table mcpc-activity"><thead><tr><th><?= hkp_e('Time') ?></th><th><?= hkp_e('Tool or method') ?></th><th><?= hkp_e('Client') ?></th><th><?= hkp_e('User') ?></th><th><?= hkp_e('Status') ?></th><th><?= hkp_e('Latency') ?></th><th><span class="hkp-sr"><?= hkp_e('Details') ?></span></th></tr></thead><tbody>
  <?php foreach ($log['rows'] as $r): ?>
    <tr><td><time><?= hkp_h($when($r['created_at'])) ?></time></td><td><code dir="ltr"><?= hkp_h($r['tool'] ?: $r['method']) ?></code><?= $r['dry_run'] ? ' <span class="mcpc-pill">' . hkp_e('dry-run') . '</span>' : '' ?></td>
      <td><?= hkp_h($r['client_name'] ?: ($r['source'] === 'console' ? hkp_t('Test console') : ($r['client_id'] ?: '—'))) ?></td><td><?= hkp_h($r['email'] ?: '—') ?></td><td><?= $status_chip($r['status']) ?><?= $r['error_code'] ? ' <code dir="ltr" class="mcpc-muted">' . hkp_h($r['error_code']) . '</code>' : '' ?></td><td><?= (int) $r['ms'] ?> ms</td>
      <td><button type="button" class="mcpc-btn mcpc-btn--quiet" data-call="<?= (int) $r['id'] ?>"><?= hkp_e('Details') ?></button></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php if ($log['pages'] > 1): $q = $filters; $q['tab'] = 'activity'; ?><nav class="mcpc-pager" aria-label="<?= hkp_e('Pages') ?>">
    <?php if ($log['page'] > 1): $q['page'] = $log['page'] - 1; ?><a class="mcpc-btn mcpc-btn--ghost" href="<?= hkp_h($base . '?' . http_build_query($q)) ?>"><?= hkp_e('Previous') ?></a><?php endif; ?>
    <span><?= hkp_e('Page {p} of {n}', array('p' => $log['page'], 'n' => $log['pages'])) ?></span>
    <?php if ($log['page'] < $log['pages']): $q['page'] = $log['page'] + 1; ?><a class="mcpc-btn mcpc-btn--ghost" href="<?= hkp_h($base . '?' . http_build_query($q)) ?>"><?= hkp_e('Next') ?></a><?php endif; ?>
  </nav><?php endif; ?>
  <?php endif; ?>
</section>
<dialog class="mcpc-drawer" id="mcpc-drawer" aria-labelledby="mcpc-drawer-h"><div class="mcpc-drawer__head"><h2 id="mcpc-drawer-h"><?= hkp_e('Call details') ?></h2><button type="button" class="mcpc-btn mcpc-btn--quiet" data-close aria-label="<?= hkp_e('Close') ?>">✕</button></div><div id="mcpc-drawer-body" class="mcpc-drawer__body"></div></dialog>

<?php elseif ($tab === 'setup'): ?>
<?php
$desktop = json_encode(array('mcpServers' => array('altus' => array('command' => 'npx', 'args' => array('-y', 'mcp-remote', $endpoint)))), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$cursor = json_encode(array('mcpServers' => array('altus' => array('url' => $endpoint))), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$code = 'claude mcp add --transport http altus ' . $endpoint;
$generic = "Transport: Streamable HTTP (POST)\nURL: " . $endpoint . "\nProtected resource metadata: " . $issuer . "/.well-known/oauth-protected-resource/mcp\nAuthorization server: " . $issuer . "/.well-known/oauth-authorization-server\nOAuth: authorization code + PKCE S256, dynamic client registration\nProtocol versions: 2025-06-18, 2025-03-26, 2024-11-05";
$code_pt = 'claude mcp add --transport http altus ' . $endpoint . ' --header "Authorization: Bearer $ALTUS_TOKEN"';
$cursor_pt = json_encode(array('mcpServers' => array('altus' => array('url' => $endpoint, 'headers' => array('Authorization' => 'Bearer ${env:ALTUS_TOKEN}')))), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$desktop_pt = json_encode(array('mcpServers' => array('altus' => array('command' => 'npx', 'args' => array('-y', 'mcp-remote', $endpoint, '--header', 'Authorization:${ALTUS_AUTH}'), 'env' => array('ALTUS_AUTH' => 'Bearer altus_pt_…')))), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$curl = "curl -s " . $endpoint . " \\\n  -H \"Authorization: Bearer \$ALTUS_TOKEN\" \\\n  -H \"Content-Type: application/json\" \\\n  -H \"MCP-Protocol-Version: 2025-06-18\" \\\n  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'";
$cards = array(
    array('claude', 'Claude.ai', hkp_t('Settings → Connectors → Add custom connector. Paste the URL, then sign in to ALTUS and choose a level.'), $endpoint, hkp_t('Copy URL')),
    array('desktop', 'Claude Desktop', hkp_t('Use Settings → Connectors like Claude.ai, or add this to claude_desktop_config.json (needs Node for mcp-remote).'), $desktop, hkp_t('Copy JSON')),
    array('code', 'Claude Code', hkp_t('Run in a terminal, then type /mcp in Claude Code and choose Authenticate.'), $code, hkp_t('Copy command')),
    array('cursor', 'Cursor', hkp_t('Add to ~/.cursor/mcp.json (or .cursor/mcp.json in a project). Cursor opens the ALTUS sign-in page.'), $cursor, hkp_t('Copy JSON')),
    array('generic', hkp_t('Any MCP client'), hkp_t('Everything a standards-based client needs to discover and connect.'), $generic, hkp_t('Copy details')),
);
?>
<section class="mcpc-hero mcpc-hero--compact" aria-labelledby="mcpc-setup-h">
  <div class="mcpc-hero__glow" aria-hidden="true"></div>
  <div class="mcpc-hero__main"><h2 id="mcpc-setup-h" class="mcpc-hero__label"><?= hkp_e('Connect in three steps') ?></h2>
    <ol class="mcpc-steps"><li><?= hkp_e('Add the endpoint to your AI app.') ?></li><li><?= hkp_e('Sign in to ALTUS and pick Read, Write or Full access.') ?></li><li><?= hkp_e('Ask the assistant to start with altus_site_info.') ?></li></ol>
    <div class="mcpc-endpoint"><code dir="ltr"><?= hkp_h($endpoint) ?></code><button type="button" class="mcpc-btn mcpc-btn--light mcpc-copy" data-copy="<?= hkp_h($endpoint) ?>" aria-label="<?= hkp_e('Copy MCP URL') ?>"><?= hkp_icon('file') ?><span><?= hkp_e('Copy') ?></span></button></div>
  </div>
</section>
<div class="mcpc-setup">
  <?php foreach ($cards as $card): ?>
  <article class="mcpc-card mcpc-setup__card" id="setup-<?= $card[0] ?>"><h3><?= hkp_h($card[1]) ?></h3><p><?= hkp_h($card[2]) ?></p><div class="mcpc-snippet"><pre dir="ltr"><code><?= hkp_h($card[3]) ?></code></pre><?= $copy($card[3], $card[4]) ?></div></article>
  <?php endforeach; ?>
</div>
<section class="mcpc-card" aria-labelledby="mcpc-pt-setup-h">
  <div class="mcpc-card__head"><div><h2 id="mcpc-pt-setup-h"><?= hkp_e('With a personal connection token') ?></h2><p><?= hkp_e('For clients that cannot complete OAuth. Create the token under Access levels and keep it in an environment variable, never in a shared file.') ?></p></div><a class="mcpc-btn mcpc-btn--ghost" href="<?= hkp_h($base . '?tab=levels') ?>"><?= hkp_icon('lock') ?><span><?= hkp_e('Create a token') ?></span></a></div>
  <div class="mcpc-setup">
    <?php foreach (array(array('Claude Code', $code_pt), array('Cursor', $cursor_pt), array('Claude Desktop', $desktop_pt), array('curl', $curl)) as $s): ?>
    <article class="mcpc-setup__card mcpc-setup__card--flat"><h3><?= hkp_h($s[0]) ?></h3><div class="mcpc-snippet"><pre dir="ltr"><code><?= hkp_h($s[1]) ?></code></pre><?= $copy($s[1], hkp_t('Copy')) ?></div></article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
</div>
<script type="application/json" id="mcpc-i18n"><?= json_encode($i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= hkp_asset('assets/hkp/mcp-console.js') ?>" defer></script>
