<?php
// php -S 127.0.0.1:8099 -t . e2e/support/router.php (from the repository root).
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', array('127.0.0.1', '::1'), true)) {
    http_response_code(403);
    exit;
}
$root = dirname(__DIR__, 2);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('~^/(application|system|backups|\.git|e2e|tools|uploads/private)(/|$)~i', $path)) {
    http_response_code(404);
    exit;
}
if (is_file($root . $path) && preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|woff2?|ttf|ico|pdf|mp4|webp)$/i', $path)) {
    return false;
}
if (is_file($root . $path) && preg_match('~^/uploads/academy/.*/CREDITS\.json$~', $path)) {
    return false;
}
define('HA_E2E_DATABASE', 'atlas_hospitality_test');
header('X-HA-Test-Database: atlas_hospitality_test');
$_SERVER['CI_ENV'] = 'testing';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir($root);
require $root . '/index.php';
