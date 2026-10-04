<?php
/**
 * Native OAuth consent (PHP MCP). $p = pending request (client, scope, redirect_uri), $allowed = scopes the user may grant now,
 * $offer = array(default, max) from the MCP console (global maximum ∩ client maximum).
 * The person picks one of three access levels; the server re-applies the same caps on submit.
 */
$redirect = parse_url($p['redirect_uri']);
$dest = ($redirect['scheme'] ?? '') . '://' . ($redirect['host'] ?? '') . (isset($redirect['port']) ? ':' . $redirect['port'] : '');
$offer = $offer ?? array('default' => 'write', 'max' => 'full');
$grantable = array_values(array_intersect($p['scope'], $allowed));
$cards = array(
    'read' => array(hkp_t('Read access'), hkp_t('Look, never touch'), hkp_t('Read pages, courses, articles, SOPs, media and approval status you can already see. Nothing can change.'), 'search'),
    'write' => array(hkp_t('Write access'), hkp_t('Draft, never publish'), hkp_t('Everything in Read, plus create and edit private drafts of content, courses and media. Nothing goes live.'), 'pen'),
    'full' => array(hkp_t('Full access'), hkp_t('Publish with approval'), hkp_t('Everything in Read and Write, plus request publication and publish after another person approves.'), 'crown'),
);
$per = array(); $prev = array(); $choice = null;
foreach ($cards as $k => $c) {
    $s = Ha_mcp_oauth::cap($grantable, $k);
    $state = Ha_mcp_oauth::LEVEL_RANK[$k] > Ha_mcp_oauth::LEVEL_RANK[$offer['max']] ? 'capped' : (!$s || ($k !== 'read' && count($s) === count($prev)) ? 'unavailable' : 'ok');
    $per[$k] = array('scopes' => $s, 'state' => $state); $prev = $s;
    if ($state === 'ok' && Ha_mcp_oauth::LEVEL_RANK[$k] <= Ha_mcp_oauth::LEVEL_RANK[$offer['default']]) $choice = $k;
}
if ($choice === null) foreach ($per as $k => $x) if ($x['state'] === 'ok') { $choice = $k; break; }
?>
<link rel="stylesheet" href="<?= hkp_asset('assets/hkp/mcp-console.css') ?>">
<div class="mcpc mcpc-consent">
<header class="mcpc-head"><div><div class="hkp-eyebrow"><?= hkp_e('Connected publishing') ?></div><h1><?= hkp_e('Connect an MCP client') ?></h1><p><?= hkp_e('An application wants to work with ALTUS on your behalf. Choose how much it may do.') ?></p></div></header>
<form method="post" class="mcpc-card mcpc-consent__card"><?= ha_csrf_field() ?><input type="hidden" name="request" value="<?= hkp_h($p['id']) ?>">
  <div class="mcpc-consent__app"><span class="mcpc-kpi__icon"><svg class="hkp-icon" aria-hidden="true"><use href="#i-plug"></use></svg></span><div><h2><?= hkp_h($p['client']['client_name']) ?></h2><p class="mcpc-muted" dir="ltr"><?= hkp_h($p['client']['client_id']) ?> · <?= hkp_e('returns to') ?> <strong><?= hkp_h($dest) ?></strong></p></div></div>
  <?php if ($grantable): ?>
  <fieldset class="mcpc-choose"><legend><?= hkp_e('Access level') ?></legend>
    <?php foreach ($cards as $k => $c): $x = $per[$k]; $off = $x['state'] !== 'ok'; ?>
    <label class="mcpc-choice mcpc-choice--<?= $k ?><?= $off ? ' is-off' : '' ?>">
      <input type="radio" name="level" value="<?= $k ?>"<?= $choice === $k ? ' checked' : '' ?><?= $off ? ' disabled' : '' ?>>
      <span class="mcpc-choice__body">
        <span class="mcpc-choice__top"><span class="mcpc-levelcard__icon"><svg class="hkp-icon" aria-hidden="true"><use href="#i-<?= $c[3] ?>"></use></svg></span><span class="mcpc-levelcard__tag"><?= hkp_h($c[1]) ?></span></span>
        <b><?= hkp_h($c[0]) ?></b>
        <span class="mcpc-choice__text"><?= hkp_h($c[2]) ?></span>
        <?php if ($x['state'] === 'capped'): ?><span class="mcpc-status mcpc-status--denied"><?= hkp_e('Not offered by your administrator') ?></span>
        <?php elseif ($x['state'] === 'unavailable'): ?><span class="mcpc-status mcpc-status--denied"><?= hkp_e('Not included in your permissions') ?></span>
        <?php else: ?><span class="mcpc-scopes"><?php foreach ($x['scopes'] as $s): ?><code dir="ltr" title="<?= hkp_h(hkp_t($texts[$s] ?? $s)) ?>"><?= hkp_h($s) ?></code><?php endforeach; ?></span><?php endif; ?>
      </span>
    </label>
    <?php endforeach; ?>
  </fieldset>
  <?php endif; ?>
  <p class="mcpc-muted"><?= hkp_e('The client acts with your permissions and tenant boundaries, which are checked again on every request. Everything it writes stays a private draft; publishing needs another person\'s approval in ALTUS. Access tokens last 5 minutes; you can revoke the connection at any time.') ?></p>
  <?php if (!$grantable): ?><p class="mcpc-banner"><?= hkp_e('Your current permissions do not allow any of the requested access, so the connection cannot be approved.') ?></p><?php endif; ?>
  <div class="mcpc-consent__actions"><?php if ($grantable && $choice): ?><button class="mcpc-btn" name="decision" value="allow"><?= hkp_e('Allow connection') ?></button><?php endif; ?><button class="mcpc-btn mcpc-btn--ghost" name="decision" value="deny"><?= hkp_e('Decline') ?></button></div>
</form>
</div>
<style>
.mcpc-consent{max-inline-size:860px}
.mcpc-consent__app{display:flex;gap:14px;align-items:center}
.mcpc-choose{border:0;margin:0;padding:0;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.mcpc-choose legend{font-size:12px;font-weight:600;margin-block-end:8px;padding:0}
.mcpc-choice{position:relative;cursor:pointer}
.mcpc-choice input{position:absolute;opacity:0}
.mcpc-choice__body{display:grid;gap:8px;align-content:start;block-size:100%;padding:16px;border-radius:14px;border:1px solid #e6ddd3;background:#fff;box-shadow:0 1px 2px #3a24160a,0 6px 18px #3a24160d;transition:transform .3s cubic-bezier(.34,1.56,.64,1),opacity .2s}
.mcpc-choice__top{display:flex;justify-content:space-between;align-items:center}
.mcpc-choice b{font-family:var(--serif);font-size:19px;letter-spacing:-.02em}
.is-ar .mcpc-choice b{font-family:var(--font-arabic)}
.mcpc-choice__text{font-size:12.5px;color:#6f6861;line-height:1.6}
.mcpc-choice .mcpc-scopes{display:flex;flex-wrap:wrap;gap:4px}
.mcpc-choice .mcpc-scopes code{font-size:10.5px;padding:1px 7px;border-radius:99px;background:#f6f3ee}
.mcpc-choice:hover .mcpc-choice__body{transform:translateY(-2px)}
.mcpc-choice:active .mcpc-choice__body{transform:scale(.99)}
.mcpc-choice input:focus-visible+.mcpc-choice__body{outline:2px solid #ac4e26;outline-offset:2px}
.mcpc-choice input:checked+.mcpc-choice__body{border-color:#ac4e26;box-shadow:inset 0 0 0 1px #ac4e26,0 10px 30px -12px #ac4e2666;background:linear-gradient(180deg,#fff,#fdf3ec)}
.mcpc-choice.is-off{cursor:not-allowed}.mcpc-choice.is-off .mcpc-choice__body{opacity:.55;transform:none}
.mcpc-consent__actions{display:flex;gap:10px;flex-wrap:wrap}
@media (max-width:760px){.mcpc-choose{grid-template-columns:minmax(0,1fr)}}
</style>
