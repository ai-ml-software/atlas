<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Secrets at rest: provider API keys and TOTP seeds.
 *
 * AES-256-GCM, a fresh 96-bit nonce per value, the tag stored alongside. The
 * master key comes from the HA_APP_KEY environment variable when the host
 * sets one, and otherwise from application/config/ha_app_key.php, which is
 * generated on first use. That file is the one thing that must be copied when
 * the database moves to another server: without it every stored key is
 * unreadable by design, and the admin re-enters them.
 *
 * CodeIgniter's Encryption library was not used because this install ships
 * with an empty encryption_key and the legacy code depends on that.
 */
class Ha_crypto {

    const CIPHER  = 'aes-256-gcm';
    const VERSION = 'v1';

    private $key;

    public function encrypt($plaintext) {
        $plaintext = (string) $plaintext;
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return self::VERSION . ':' . base64_encode($nonce . $tag . $cipher);
    }

    /** Returns null for a value that cannot be decrypted rather than throwing: a wrong key must not take a page down. */
    public function decrypt($payload) {
        $payload = (string) $payload;
        if (strpos($payload, self::VERSION . ':') !== 0) {
            return null;
        }
        $raw = base64_decode(substr($payload, strlen(self::VERSION) + 1), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $nonce  = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);
        return $plain === false ? null : $plain;
    }

    /** Keyed hash for values that are compared, never read back (API key secrets, recovery codes). */
    public function hmac($value) {
        return hash_hmac('sha256', (string) $value, $this->key());
    }

    /** Last four characters, for "sk-…a1b2" hints in the admin. Never more. */
    public static function hint($secret) {
        $secret = (string) $secret;
        if (strlen($secret) < 12) {
            return '••••';
        }
        return '••••' . substr($secret, -4);
    }

    private function key() {
        if ($this->key !== null) {
            return $this->key;
        }
        $env = getenv('HA_APP_KEY');
        if ($env) {
            $decoded = base64_decode($env, true);
            $this->key = ($decoded !== false && strlen($decoded) === 32) ? $decoded : hash('sha256', $env, true);
            return $this->key;
        }

        $file = APPPATH . 'config/ha_app_key.php';
        if (is_file($file)) {
            $stored = include $file;
            $decoded = is_string($stored) ? base64_decode($stored, true) : false;
            if ($decoded !== false && strlen($decoded) === 32) {
                $this->key = $decoded;
                return $this->key;
            }
            throw new RuntimeException('config/ha_app_key.php exists but does not hold a valid key.');
        }

        $new = random_bytes(32);
        $php = "<?php\ndefined('BASEPATH') OR exit('No direct script access allowed');\n\n"
             . "// Master key for secrets stored by AI Studio and two-factor login.\n"
             . "// Generated " . date('Y-m-d H:i:s') . ". Back it up with the database; never commit it.\n"
             . "return '" . base64_encode($new) . "';\n";
        if (@file_put_contents($file, $php, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write ' . $file . '. Set HA_APP_KEY or make application/config writable once.');
        }
        @chmod($file, 0600);
        $this->key = $new;
        return $this->key;
    }
}
