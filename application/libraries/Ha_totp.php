<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Time-based one-time passwords (RFC 6238 over RFC 4226), compatible with
 * Google Authenticator, Microsoft Authenticator, Authy, 1Password and every
 * other app that reads an otpauth:// URI.
 *
 * SHA-1, 6 digits, 30-second step: the defaults every authenticator app
 * supports. Stronger parameters exist in the RFC but several popular apps
 * silently ignore them and then show codes that never match.
 */
class Ha_totp {

    const DIGITS = 6;
    const PERIOD = 30;
    const WINDOW = 1;   // accept one step either side for clock drift

    private static $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** 160-bit secret, the size RFC 4226 recommends for SHA-1. */
    public function new_secret() {
        return self::base32_encode(random_bytes(20));
    }

    public function code($secret, $step = null) {
        $step = $step === null ? $this->step() : (int) $step;
        $key = self::base32_decode($secret);
        $counter = pack('N*', 0) . pack('N*', $step);  // 64-bit big endian
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
                | ((ord($hash[$offset + 1]) & 0xff) << 16)
                | ((ord($hash[$offset + 2]) & 0xff) << 8)
                |  (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($binary % pow(10, self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the matched step, or false. The caller stores the step and
     * passes it back as $last_step so the same code cannot be used twice.
     */
    public function verify($secret, $code, $last_step = null, $now = null) {
        $code = preg_replace('/\s+/', '', (string) $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return false;
        }
        $current = $this->step($now);
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $step = $current + $i;
            if ($last_step !== null && $step <= (int) $last_step) {
                continue;
            }
            if (hash_equals($this->code($secret, $step), $code)) {
                return $step;
            }
        }
        return false;
    }

    public function step($now = null) {
        return (int) floor(($now === null ? time() : $now) / self::PERIOD);
    }

    public function uri($secret, $account, $issuer) {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        return 'otpauth://totp/' . $label . '?' . http_build_query(array(
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ), '', '&', PHP_QUERY_RFC3986);
    }

    /** QR code as a data: URI, rendered locally: the secret never leaves the server. */
    public function qr_data_uri($uri) {
        require_once APPPATH . 'libraries/phpqrcode/qrlib.php';
        // Rendered to a temp file: with no filename phpqrcode sends its own
        // Content-Type header, which would turn the surrounding page into a PNG.
        $file = tempnam(sys_get_temp_dir(), 'haqr');
        QRcode::png($uri, $file, QR_ECLEVEL_M, 6, 2);
        $png = (string) @file_get_contents($file);
        @unlink($file);
        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** Ten single-use recovery codes, formatted xxxxx-xxxxx. */
    public function recovery_codes($count = 10) {
        $codes = array();
        for ($i = 0; $i < $count; $i++) {
            $raw = strtolower(self::base32_encode(random_bytes(7)));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }
        return $codes;
    }

    public static function normalize_recovery($code) {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $code));
    }

    public static function base32_encode($bytes) {
        if ($bytes === '') {
            return '';   // str_split('') yields one empty chunk on PHP 8.1
        }
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::$alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public static function base32_decode($text) {
        $text = strtoupper(preg_replace('/[\s=]/', '', (string) $text));
        $bits = '';
        foreach (str_split($text) as $c) {
            $v = strpos(self::$alphabet, $c);
            if ($v === false) {
                continue;
            }
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
