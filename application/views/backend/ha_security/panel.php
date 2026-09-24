<?php
/*
 * Account security panel: two-factor login and personal API keys.
 * Rendered inside the backend layout (admin, instructor) and, through
 * views/frontend/<theme>/account_security.php, inside the site theme.
 */
$api_base = site_url('api/v1');
?>
<div class="row g-3 ha-security">
<div class="col-lg-6">
    <div class="card h-100"><div class="card-body">
        <h4 class="page-title mb-3"><i class="mdi mdi-cellphone-key"></i> Two-factor login</h4>

        <?php if (!empty($new_codes)): ?>
            <div class="alert alert-warning" role="alert">
                <strong>Your recovery codes.</strong> Each works once if you lose your phone. Store them in a password manager or print them. They are not shown again.
                <pre class="bg-white border rounded p-2 mt-2 mb-2" id="ha-codes" style="font-size:1rem;letter-spacing:.05em"><?php echo html_escape(implode("\n", $new_codes)); ?></pre>
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('ha-codes').textContent)">Copy codes</button>
            </div>
        <?php endif; ?>

        <?php if ($twofa_on): ?>
            <p><span class="badge bg-success">On</span> Signing in asks for a code from your authenticator app. Recovery codes left: <strong><?php echo (int) $recovery_left; ?></strong>
                <?php if ($recovery_left < 3): ?><span class="text-danger small">— generate new ones</span><?php endif; ?></p>
            <form method="post" action="<?php echo site_url('account_security/twofa_codes'); ?>" class="row g-2 mb-3" autocomplete="off">
                <?php echo ha_csrf_field(); ?>
                <div class="col-sm-7"><label for="ha-c1" class="visually-hidden">Current code</label>
                    <input id="ha-c1" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" placeholder="Code from your app" required maxlength="6"></div>
                <div class="col-sm-5"><button class="btn btn-outline-primary w-100">New recovery codes</button></div>
            </form>
            <details>
                <summary class="text-danger">Turn off two-factor login</summary>
                <form method="post" action="<?php echo site_url('account_security/twofa_disable'); ?>" class="row g-2 mt-2" autocomplete="off">
                    <?php echo ha_csrf_field(); ?>
                    <div class="col-sm-6"><label for="ha-pw" class="form-label small">Password</label><input id="ha-pw" type="password" name="password" class="form-control" required autocomplete="current-password"></div>
                    <div class="col-sm-6"><label for="ha-c2" class="form-label small">Code (or recovery code)</label><input id="ha-c2" name="code" class="form-control" required autocomplete="one-time-code"></div>
                    <div class="col-12"><button class="btn btn-danger btn-sm">Turn off</button></div>
                </form>
            </details>
        <?php elseif ($twofa_pending): ?>
            <ol class="small">
                <li>Open Google Authenticator, Microsoft Authenticator, Authy or 1Password.</li>
                <li>Scan this code (or enter the key manually).</li>
                <li>Type the 6-digit code the app shows.</li>
            </ol>
            <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                <img src="<?php echo $twofa_pending['qr']; ?>" width="180" height="180" alt="QR code for your authenticator app" class="border rounded bg-white p-1">
                <div class="small">
                    <div class="text-muted">Setup key</div>
                    <code class="fs-6" style="word-break:break-all"><?php echo html_escape(trim(chunk_split($twofa_pending['secret'], 4, ' '))); ?></code>
                    <div class="text-muted mt-2">Account: <?php echo html_escape($account['email']); ?></div>
                </div>
            </div>
            <form method="post" action="<?php echo site_url('account_security/twofa_confirm'); ?>" class="row g-2" autocomplete="off">
                <?php echo ha_csrf_field(); ?>
                <div class="col-sm-7"><label for="ha-c3" class="visually-hidden">6-digit code</label>
                    <input id="ha-c3" name="code" class="form-control form-control-lg" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required maxlength="7" autofocus></div>
                <div class="col-sm-5"><button class="btn btn-primary btn-lg w-100">Confirm</button></div>
            </form>
            <form method="post" action="<?php echo site_url('account_security/twofa_cancel'); ?>" class="mt-2">
                <?php echo ha_csrf_field(); ?><button class="btn btn-link btn-sm p-0">Cancel set-up</button>
            </form>
        <?php else: ?>
            <p><span class="badge bg-secondary">Off</span> Add a second step to sign-in: even with your password, nobody gets in without your phone.</p>
            <form method="post" action="<?php echo site_url('account_security/twofa_begin'); ?>">
                <?php echo ha_csrf_field(); ?>
                <button class="btn btn-primary"><i class="mdi mdi-shield-check"></i> Set up authenticator app</button>
            </form>
        <?php endif; ?>
    </div></div>
</div>

<div class="col-lg-6">
    <div class="card h-100"><div class="card-body">
        <h4 class="page-title mb-3"><i class="mdi mdi-key-chain-variant"></i> API keys</h4>
        <?php if (!$can_keys): ?>
            <p class="text-muted mb-0">Your role does not include API access.</p>
        <?php else: ?>
            <?php if (!empty($new_key)): ?>
                <div class="alert alert-success" role="alert">
                    <strong>New key.</strong> Copy it now; only a hash is kept.
                    <div class="input-group mt-2">
                        <input class="form-control font-monospace" id="ha-newkey" value="<?php echo html_escape($new_key); ?>" readonly aria-label="New API key">
                        <button class="btn btn-outline-dark" type="button" onclick="var i=document.getElementById('ha-newkey');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value)">Copy</button>
                    </div>
                    <pre class="small bg-white border rounded p-2 mt-2 mb-0">curl -H "Authorization: Bearer <?php echo html_escape($new_key); ?>" <?php echo html_escape($api_base); ?>/me</pre>
                </div>
            <?php endif; ?>

            <?php if ($keys): ?>
            <div class="table-responsive mb-3"><table class="table table-sm align-middle">
                <thead><tr><th>Name</th><th>Key</th><th>Scopes</th><th>Last used</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($keys as $k): $revoked = $k['revoked_at'] !== null; $expired = $k['expires_at'] && strtotime($k['expires_at']) < time(); ?>
                    <tr class="<?php echo $revoked || $expired ? 'text-muted' : ''; ?>">
                        <td><?php echo html_escape($k['name']); ?><?php echo $revoked ? ' <span class="badge bg-secondary">revoked</span>' : ($expired ? ' <span class="badge bg-warning">expired</span>' : ''); ?>
                            <?php if ($k['expires_at'] && !$revoked): ?><div class="small">expires <?php echo html_escape(substr($k['expires_at'], 0, 10)); ?></div><?php endif; ?></td>
                        <td class="font-monospace small">ha_<?php echo html_escape($k['prefix']); ?>_…</td>
                        <td class="small"><?php echo html_escape(str_replace(' ', ', ', $k['scopes'])); ?></td>
                        <td class="small"><?php echo $k['last_used_at'] ? html_escape($k['last_used_at']) . '<br>' . html_escape((string) $k['last_used_ip']) . ' · ' . (int) $k['use_count'] . '×' : 'never'; ?></td>
                        <td><?php if (!$revoked): ?>
                            <form method="post" action="<?php echo site_url('account_security/key_revoke'); ?>" onsubmit="return confirm('Revoke this key? Anything using it stops working.')">
                                <?php echo ha_csrf_field(); ?><input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Revoke</button>
                            </form>
                        <?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>

            <form method="post" action="<?php echo site_url('account_security/key_create'); ?>" class="border rounded p-3" autocomplete="off">
                <?php echo ha_csrf_field(); ?>
                <h6>Create a key</h6>
                <div class="row g-2">
                    <div class="col-sm-7"><label for="ha-kn" class="form-label small">Name</label><input id="ha-kn" name="name" class="form-control" required maxlength="120" placeholder="HR system integration"></div>
                    <div class="col-sm-5"><label for="ha-ke" class="form-label small">Expires (optional)</label><input id="ha-ke" name="expires_at" type="date" class="form-control" min="<?php echo date('Y-m-d', time() + 86400); ?>"></div>
                    <div class="col-12">
                        <span class="form-label small d-block">Scopes</span>
                        <?php foreach ($scopes as $scope => $label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="scopes[]" value="<?php echo html_escape($scope); ?>" id="ha-s-<?php echo md5($scope); ?>" <?php echo in_array($scope, array('profile:read', 'courses:read'), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="ha-s-<?php echo md5($scope); ?>"><span class="font-monospace"><?php echo html_escape($scope); ?></span> — <?php echo html_escape($label); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-12"><label for="ha-ip" class="form-label small">Allowed IP addresses (optional, comma separated)</label><input id="ha-ip" name="allowed_ips" class="form-control font-monospace" placeholder="203.0.113.10, 2001:db8::1"></div>
                    <div class="col-12"><button class="btn btn-primary"><i class="mdi mdi-key-plus"></i> Create key</button></div>
                </div>
            </form>
            <p class="small text-muted mt-2 mb-0">Base URL <span class="font-monospace"><?php echo html_escape($api_base); ?></span> · send <span class="font-monospace">Authorization: Bearer &lt;key&gt;</span> · 120 requests/minute per key. A key can never do more than your account can.</p>
        <?php endif; ?>
    </div></div>
</div>
</div>
