<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Run this application's own CLI commands (php index.php <controller> <method> ...)
 * from a web request: synchronously for short work such as publishing one
 * course, or detached for the AI worker.
 *
 * Arguments are passed as an array and each one is shell-escaped; there is no
 * code path that builds a command from a raw string.
 */
class Ha_cli_runner {

    /** The php CLI binary. Under Apache/FPM PHP_BINARY is the server, not php. */
    public function php() {
        $env = getenv('HA_PHP_CLI');
        if ($env) {
            return $env;
        }
        if (PHP_SAPI === 'cli' && PHP_BINARY) {
            return PHP_BINARY;
        }
        $win = DIRECTORY_SEPARATOR === '\\';
        $exe = $win ? '\\php.exe' : '/php';
        // Under mod_php PHP_BINARY is httpd and PHP_BINDIR is a compile-time
        // default; the loaded php.ini normally sits beside the CLI binary.
        $ini_dir = php_ini_loaded_file() ? dirname(php_ini_loaded_file()) : null;
        foreach (array($ini_dir ? $ini_dir . $exe : null, PHP_BINDIR . $exe, dirname(PHP_BINARY) . $exe,
                       '/usr/local/bin/php', '/usr/bin/php', '/opt/cpanel/ea-php' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '/root/usr/bin/php') as $c) {
            if ($c && @is_file($c) && @is_executable($c)) {
                return $c;
            }
        }
        return 'php';
    }

    private function command(array $args) {
        $parts = array(escapeshellarg($this->php()), escapeshellarg(FCPATH . 'index.php'));
        foreach ($args as $a) {
            if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', (string) $a)) {
                throw new InvalidArgumentException('Refusing an unsafe CLI argument.');
            }
            $parts[] = escapeshellarg((string) $a);
        }
        return implode(' ', $parts);
    }

    /** @return array('ok' => bool, 'code' => int, 'output' => string) */
    public function run(array $args, $timeout = 120) {
        if (!function_exists('proc_open')) {
            return array('ok' => false, 'code' => -1, 'output' => 'proc_open is disabled on this server');
        }
        $proc = proc_open($this->command($args), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, FCPATH);
        if (!is_resource($proc)) {
            return array('ok' => false, 'code' => -1, 'output' => 'could not start php');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $deadline = time() + $timeout;
        while (true) {
            $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            if (time() > $deadline) {
                proc_terminate($proc);
                return array('ok' => false, 'code' => -1, 'output' => $out . "\n(timed out)");
            }
            usleep(100000);
        }
        $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $code = isset($status['exitcode']) ? (int) $status['exitcode'] : -1;
        return array('ok' => $code === 0, 'code' => $code, 'output' => $out);
    }

    /** Start a command that outlives this request. Returns false where the host forbids it. */
    public function spawn(array $args) {
        $cmd = $this->command($args);
        if (DIRECTORY_SEPARATOR === '\\') {
            if (!function_exists('popen')) {
                return false;
            }
            $h = @popen('start "" /B ' . $cmd . ' > NUL 2>&1', 'r');
            if ($h === false) {
                return false;
            }
            pclose($h);
            return true;
        }
        if (!function_exists('exec')) {
            return false;
        }
        @exec('nohup ' . $cmd . ' > /dev/null 2>&1 &', $o, $code);
        return $code === 0;
    }
}
