<?php
/**
 * Deployment diagnostic.
 *
 * A production CodeIgniter install sets display_errors to 0, so a fatal error
 * reaches the browser as a bare HTTP 500 with nothing to act on. This script
 * runs outside the framework and reports what the server can actually do, so
 * the cause is a fact rather than a guess.
 *
 * Usage:
 *   1. Upload beside index.php.
 *   2. Open https://your-site/atlas/deploy-check.php?key=THE_KEY below.
 *   3. Fix what it reports.
 *   4. DELETE THIS FILE. It describes your server to anyone who opens it.
 *
 * It never prints a password, only whether one is set and whether it works.
 */

// Change this before uploading, and delete the file when you are done.
const DEPLOY_CHECK_KEY = 'change-me-before-uploading';

if (!isset($_GET['key']) || !hash_equals(DEPLOY_CHECK_KEY, (string) $_GET['key'])) {
    http_response_code(404);
    exit('Not found');
}

// Report everything this script itself hits, whatever the app is configured to do.
ini_set('display_errors', 1);
error_reporting(E_ALL);

$checks = array();

function check($group, $label, $ok, $detail = '', $fix = '') {
    global $checks;
    $checks[] = compact('group', 'label', 'ok', 'detail', 'fix');
}

// ------------------------------------------------------------------- server

check('Server', 'PHP version', version_compare(PHP_VERSION, '7.4', '>='),
    PHP_VERSION,
    'The application targets PHP 7.4 or newer. Set the PHP version in your host control panel.');

$sapi = php_sapi_name();
check('Server', 'PHP interface', true, $sapi,
    'Under php-fpm or cgi, php_value directives in .htaccess cause an immediate 500.');

check('Server', 'Document root', true, $_SERVER['DOCUMENT_ROOT'] ?? 'unknown');
check('Server', 'Script path', true, $_SERVER['SCRIPT_NAME'] ?? 'unknown');

$base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
check('Server', 'Detected base URL', true, $base,
    'This is what CodeIgniter will build links from.');

$behind_proxy = isset($_SERVER['HTTP_X_FORWARDED_PROTO']);
$https_direct = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if ($behind_proxy && !$https_direct) {
    check('Server', 'HTTPS behind a proxy', true,
        'X-Forwarded-Proto: ' . $_SERVER['HTTP_X_FORWARDED_PROTO'],
        'TLS terminates at a proxy. config.php must read X-Forwarded-Proto or every generated link will say http on an https site.');
}

// --------------------------------------------------------------- extensions

foreach (array(
    'mysqli'   => 'Database driver. Without it nothing connects.',
    'mbstring' => 'Arabic content handling.',
    'gd'       => 'Image resizing for the photo pipeline.',
    'curl'     => 'Outbound HTTP for the image fetcher.',
    'json'     => 'Core.',
    'zip'      => 'Used by the updater and some addons.',
    'intl'     => 'Optional, used for locale aware formatting.',
) as $ext => $why) {
    $loaded = extension_loaded($ext);
    check('PHP extensions', $ext, $loaded, $loaded ? 'loaded' : 'missing',
        $loaded ? '' : $why . ' Enable it in your host control panel.');
}

// ------------------------------------------------------------------ rewrite

$rewrite = null;
if (function_exists('apache_get_modules')) {
    $rewrite = in_array('mod_rewrite', apache_get_modules(), true);
}
check('Apache', 'mod_rewrite', $rewrite !== false,
    $rewrite === null ? 'cannot detect from PHP, test a pretty URL instead' : ($rewrite ? 'enabled' : 'NOT enabled'),
    'Without it every route except the home page returns 404.');

$htaccess = __DIR__ . '/.htaccess';
check('Apache', '.htaccess present', file_exists($htaccess),
    file_exists($htaccess) ? 'found' : 'missing');

if (file_exists($htaccess)) {
    $body = file_get_contents($htaccess);
    $expected_base = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    preg_match('/^\s*RewriteBase\s+(\S+)/mi', $body, $m);
    $declared = $m[1] ?? null;
    check('Apache', 'RewriteBase matches the install path',
        $declared === null || rtrim($declared, '/') === rtrim($expected_base, '/'),
        'declared: ' . ($declared ?? 'none') . '   expected: ' . $expected_base,
        'Set RewriteBase in .htaccess to ' . $expected_base);

    // Scan directives only. The shipped file names these tokens in its own
    // comments to explain why they are avoided, and matching those would
    // report a problem that is not there.
    $directives = array();
    foreach (preg_split('/\R/', $body) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $directives[] = $line;
    }
    $directives = implode("
", $directives);

    $risky = array();
    foreach (array('php_value', 'php_flag', 'AddHandler', 'FollowSymLinks') as $token) {
        if (stripos($directives, $token) !== false) {
            $risky[] = $token;
        }
    }
    check('Apache', 'No directives that commonly 500', empty($risky),
        $risky ? implode(', ', $risky) : 'none found',
        'These are rejected by many shared hosts and cause an immediate 500.');
}

// ----------------------------------------------------------------- database

$config_path = __DIR__ . '/application/config/database.php';
if (!file_exists($config_path)) {
    check('Database', 'Config file', false, 'application/config/database.php is missing');
} else {
    // CodeIgniter config files open with
    //   defined('BASEPATH') OR exit('No direct script access allowed');
    // so the constant has to exist before the file can be read from outside
    // the framework. Nothing else here loads CodeIgniter.
    if (!defined('BASEPATH')) {
        define('BASEPATH', __DIR__ . '/system/');
    }
    // The config also reads ENVIRONMENT to decide db_debug.
    if (!defined('ENVIRONMENT')) {
        define('ENVIRONMENT', 'production');
    }

    $db = array();
    $active_group = 'default';
    $query_builder = true;
    require $config_path;

    $conf = $db[$active_group] ?? null;
    if (!$conf) {
        check('Database', 'Config readable', false, 'no default connection group found');
    } else {
        check('Database', 'Host', true, $conf['hostname']);
        check('Database', 'Database name', true, $conf['database']);
        check('Database', 'Username', true, $conf['username']);
        check('Database', 'Password set', $conf['password'] !== '',
            $conf['password'] === '' ? 'empty' : 'yes (not shown)');

        $looks_local = in_array($conf['hostname'], array('localhost', '127.0.0.1'), true)
            && $conf['username'] === 'root';
        check('Database', 'Not still the local development credentials', !$looks_local,
            $looks_local ? 'hostname and username look like a local XAMPP/Laragon setup' : 'looks host specific',
            'Replace the credentials in application/config/database.php with the ones your host issued. This is the single most common cause of a 500 after uploading.');

        if (extension_loaded('mysqli')) {
            $link = @mysqli_connect($conf['hostname'], $conf['username'], $conf['password'], $conf['database']);
            if ($link) {
                check('Database', 'Connection', true, 'connected, server ' . mysqli_get_server_info($link));

                $tables = array();
                if ($res = @mysqli_query($link, 'SHOW TABLES')) {
                    while ($row = mysqli_fetch_array($res)) {
                        $tables[] = $row[0];
                    }
                }
                check('Database', 'Tables present', count($tables) > 0, count($tables) . ' tables',
                    'Import the database dump if this is zero.');

                $needed = array('users', 'settings', 'course', 'ha_course', 'ha_migration');
                $missing = array_values(array_diff($needed, $tables));
                check('Database', 'Expected tables', empty($missing),
                    $missing ? 'missing: ' . implode(', ', $missing) : 'all present',
                    'Export from the working database and import here.');

                mysqli_close($link);
            } else {
                check('Database', 'Connection', false, mysqli_connect_error(),
                    'This alone will produce a blank 500, because production mode hides the error.');
            }
        }
    }
}

// --------------------------------------------------------------- filesystem

foreach (array(
    'application/logs'   => 'CodeIgniter writes its error log here. Without it you are debugging blind.',
    'application/cache'  => 'Cache directory.',
    'uploads'            => 'Uploaded media.',
    'uploads/academy'    => 'Academy photography.',
    'uploads/thumbnails' => 'Course and category thumbnails.',
) as $rel => $why) {
    $path = __DIR__ . '/' . $rel;
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    check('Filesystem', $rel, $exists && $writable,
        !$exists ? 'missing' : ($writable ? 'writable' : 'NOT writable'),
        $why . ' chmod 755 (or 775) and make sure it is owned by the web user.');
}

// ----------------------------------------------------------------- app files

foreach (array(
    'index.php',
    'application/config/config.php',
    'application/config/routes.php',
    'system/core/CodeIgniter.php',
) as $rel) {
    check('Application', $rel, file_exists(__DIR__ . '/' . $rel),
        file_exists(__DIR__ . '/' . $rel) ? 'present' : 'MISSING',
        'Upload the complete application. A partial upload is a common cause of a 500.');
}

// Reading the CodeIgniter log is usually the fastest route to the real cause.
$log_dir = __DIR__ . '/application/logs';
$latest = null;
if (is_dir($log_dir)) {
    $logs = glob($log_dir . '/log-*.php');
    if ($logs) {
        usort($logs, function ($a, $b) { return filemtime($b) - filemtime($a); });
        $latest = $logs[0];
    }
}

$failed = array_filter($checks, function ($c) { return !$c['ok']; });

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deployment check</title>
<style>
  :root { --ok:#1C7C54; --bad:#B3261E; --ink:#0D0C23; --soft:#505763; --line:#E2E0F0; --accent:#754FFE; }
  body { margin:0; font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif; color:var(--ink); background:#F8F7FF; }
  .wrap { max-width:940px; margin:0 auto; padding:2rem 1.25rem 4rem; }
  h1 { margin:0 0 .3rem; font-size:1.8rem; }
  .lede { color:var(--soft); margin:0 0 1.5rem; }
  .summary { padding:1rem 1.2rem; border-radius:12px; margin-bottom:1.5rem; border:1px solid var(--line); background:#fff; }
  .summary.bad { border-color:var(--bad); background:#FDECEA; }
  .summary.good { border-color:var(--ok); background:#E6F5EE; }
  h2 { font-size:1.05rem; margin:1.6rem 0 .5rem; padding-top:.6rem; border-top:1px solid var(--line); }
  table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; }
  td { padding:.6rem .8rem; border-bottom:1px solid var(--line); vertical-align:top; }
  tr:last-child td { border-bottom:0; }
  .s { width:34px; font-weight:700; }
  .ok .s { color:var(--ok); } .no .s { color:var(--bad); }
  .l { width:34%; font-weight:600; }
  .d { color:var(--soft); font-family:ui-monospace,Menlo,Consolas,monospace; font-size:13px; word-break:break-all; }
  .fix { display:block; margin-top:.35rem; color:var(--bad); font-family:system-ui,sans-serif; font-size:13.5px; }
  pre { background:#0D0C23; color:#E8E6F5; padding:1rem; border-radius:10px; overflow:auto; font-size:12.5px; }
  .warn { margin-top:2rem; padding:1rem 1.2rem; border:2px solid var(--bad); border-radius:12px; background:#FDECEA; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Deployment check</h1>
  <p class="lede">What this server can actually do. Run it, fix what is red, then delete this file.</p>

  <?php if ($failed): ?>
    <div class="summary bad">
      <strong><?= count($failed) ?> problem<?= count($failed) === 1 ? '' : 's' ?> found.</strong>
      Each one below has a suggested fix. The database credentials are the most
      common cause of a 500 straight after uploading.
    </div>
  <?php else: ?>
    <div class="summary good">
      <strong>Everything this script can test passed.</strong>
      If the site still returns 500, read the CodeIgniter log below, or set
      <code>CI_ENV=development</code> temporarily to see the real error.
    </div>
  <?php endif; ?>

  <?php
  $groups = array();
  foreach ($checks as $c) { $groups[$c['group']][] = $c; }
  foreach ($groups as $group => $items): ?>
    <h2><?= htmlspecialchars($group) ?></h2>
    <table>
      <?php foreach ($items as $c): ?>
        <tr class="<?= $c['ok'] ? 'ok' : 'no' ?>">
          <td class="s"><?= $c['ok'] ? '&#10003;' : '&#10007;' ?></td>
          <td class="l"><?= htmlspecialchars($c['label']) ?></td>
          <td class="d">
            <?= htmlspecialchars($c['detail']) ?>
            <?php if (!$c['ok'] && $c['fix']): ?>
              <span class="fix"><?= htmlspecialchars($c['fix']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endforeach; ?>

  <h2>Latest CodeIgniter log</h2>
  <?php if ($latest): ?>
    <p class="d"><?= htmlspecialchars(basename($latest)) ?>, last 60 lines</p>
    <pre><?= htmlspecialchars(implode('', array_slice(file($latest), -60))) ?></pre>
  <?php else: ?>
    <p class="d">No log file yet. Set <code>$config['log_threshold'] = 1;</code> in
    application/config/config.php, make application/logs writable, reload the failing
    page, then reload this check.</p>
  <?php endif; ?>

  <div class="warn">
    <strong>Delete this file when you are finished.</strong>
    It describes your server, your database name and your directory layout to
    anyone who opens it.
  </div>
</div>
</body>
</html>
