<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Two-factor login with an authenticator app.
 *
 * Enrolment is two steps on purpose: begin() stores an unconfirmed secret,
 * and 2FA is only switched on once confirm() sees a valid code from the app.
 * Enabling on the strength of a QR code alone locks out every user whose
 * scan silently failed.
 */
class Ha_two_factor {

    const ISSUER = 'Hospitality Academy';
    const MAX_FAILURES = 5;         // per user
    const WINDOW_SECONDS = 900;     // 15 minutes

    private $CI;
    private $crypto;
    private $totp;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        require_once APPPATH . 'libraries/Ha_crypto.php';
        require_once APPPATH . 'libraries/Ha_totp.php';
        $this->crypto = new Ha_crypto();
        $this->totp = new Ha_totp();
    }

    public function totp() {
        return $this->totp;
    }

    public function is_enabled($user_id) {
        $row = $this->row($user_id);
        return $row && $row['confirmed_at'] !== null;
    }

    /** Start (or restart) enrolment. Returns the secret, otpauth URI and QR. */
    public function begin($user_id, $account_label) {
        $row = $this->row($user_id);
        if ($row && $row['confirmed_at'] !== null) {
            throw new RuntimeException('Two-factor login is already on. Turn it off first to re-enrol.');
        }
        $secret = $this->totp->new_secret();
        $now = date('Y-m-d H:i:s');
        $data = array(
            'secret_cipher'   => $this->crypto->encrypt($secret),
            'confirmed_at'    => null,
            'last_step'       => null,
            'recovery_hashes' => null,
            'updated_at'      => $now,
        );
        if ($row) {
            $this->CI->db->where('user_id', (int) $user_id)->update('ha_user_2fa', $data);
        } else {
            $data['user_id'] = (int) $user_id;
            $data['created_at'] = $now;
            $this->CI->db->insert('ha_user_2fa', $data);
        }
        $uri = $this->totp->uri($secret, $account_label, self::ISSUER);
        return array('secret' => $secret, 'uri' => $uri, 'qr' => $this->totp->qr_data_uri($uri));
    }

    /** Pending enrolment details, so a page reload does not rotate the secret. */
    public function pending($user_id, $account_label) {
        $row = $this->row($user_id);
        if (!$row || $row['confirmed_at'] !== null) {
            return null;
        }
        $secret = $this->crypto->decrypt($row['secret_cipher']);
        if ($secret === null) {
            return null;
        }
        $uri = $this->totp->uri($secret, $account_label, self::ISSUER);
        return array('secret' => $secret, 'uri' => $uri, 'qr' => $this->totp->qr_data_uri($uri));
    }

    /** @return array|false recovery codes (plaintext, shown once) or false */
    public function confirm($user_id, $code) {
        $row = $this->row($user_id);
        if (!$row || $row['confirmed_at'] !== null) {
            return false;
        }
        $secret = $this->crypto->decrypt($row['secret_cipher']);
        $step = $secret ? $this->totp->verify($secret, $code) : false;
        if ($step === false) {
            return false;
        }
        $codes = $this->totp->recovery_codes();
        $this->CI->db->where('user_id', (int) $user_id)->update('ha_user_2fa', array(
            'confirmed_at'    => date('Y-m-d H:i:s'),
            'last_step'       => $step,
            'recovery_hashes' => json_encode($this->hash_codes($codes)),
            'updated_at'      => date('Y-m-d H:i:s'),
        ));
        return $codes;
    }

    /**
     * Check a login code: a six-digit TOTP or a recovery code.
     * @return array('ok' => bool, 'error' => string|null, 'used_recovery' => bool, 'remaining' => int)
     */
    public function check($user_id, $code, $ip = null) {
        $bucket = '2fa_user:' . (int) $user_id;
        if ($this->throttled($bucket)) {
            return array('ok' => false, 'error' => 'Too many wrong codes. Wait 15 minutes and try again.', 'used_recovery' => false, 'remaining' => 0);
        }
        $row = $this->row($user_id);
        if (!$row || $row['confirmed_at'] === null) {
            return array('ok' => false, 'error' => 'Two-factor login is not set up for this account.', 'used_recovery' => false, 'remaining' => 0);
        }

        $code = trim((string) $code);
        if (preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $code))) {
            $secret = $this->crypto->decrypt($row['secret_cipher']);
            $step = $secret ? $this->totp->verify($secret, $code, $row['last_step']) : false;
            if ($step !== false) {
                $this->CI->db->where('user_id', (int) $user_id)->update('ha_user_2fa',
                    array('last_step' => $step, 'updated_at' => date('Y-m-d H:i:s')));
                $this->attempt($bucket, true, $ip);
                return array('ok' => true, 'error' => null, 'used_recovery' => false, 'remaining' => $this->remaining_codes($row));
            }
        } else {
            $hashes = json_decode((string) $row['recovery_hashes'], true) ?: array();
            $wanted = $this->crypto->hmac(Ha_totp::normalize_recovery($code));
            foreach ($hashes as $i => $h) {
                if (hash_equals($h, $wanted)) {
                    unset($hashes[$i]);
                    $this->CI->db->where('user_id', (int) $user_id)->update('ha_user_2fa', array(
                        'recovery_hashes' => json_encode(array_values($hashes)),
                        'updated_at'      => date('Y-m-d H:i:s'),
                    ));
                    $this->attempt($bucket, true, $ip);
                    return array('ok' => true, 'error' => null, 'used_recovery' => true, 'remaining' => count($hashes));
                }
            }
        }
        $this->attempt($bucket, false, $ip);
        return array('ok' => false, 'error' => 'That code is not valid. Check the time on your phone and try again.', 'used_recovery' => false, 'remaining' => 0);
    }

    public function regenerate_recovery($user_id) {
        if (!$this->is_enabled($user_id)) {
            return false;
        }
        $codes = $this->totp->recovery_codes();
        $this->CI->db->where('user_id', (int) $user_id)->update('ha_user_2fa', array(
            'recovery_hashes' => json_encode($this->hash_codes($codes)),
            'updated_at'      => date('Y-m-d H:i:s'),
        ));
        return $codes;
    }

    public function disable($user_id) {
        $this->CI->db->where('user_id', (int) $user_id)->delete('ha_user_2fa');
        return $this->CI->db->affected_rows() > 0;
    }

    public function remaining_codes($row_or_user) {
        $row = is_array($row_or_user) ? $row_or_user : $this->row($row_or_user);
        if (!$row) {
            return 0;
        }
        return count(json_decode((string) $row['recovery_hashes'], true) ?: array());
    }

    private function row($user_id) {
        return $this->CI->db->get_where('ha_user_2fa', array('user_id' => (int) $user_id))->row_array();
    }

    private function hash_codes(array $codes) {
        $out = array();
        foreach ($codes as $c) {
            $out[] = $this->crypto->hmac(Ha_totp::normalize_recovery($c));
        }
        return $out;
    }

    private function attempt($bucket, $ok, $ip) {
        $this->CI->db->insert('ha_auth_attempt', array(
            'bucket' => $bucket, 'ok' => $ok ? 1 : 0, 'ip' => $ip, 'created_at' => date('Y-m-d H:i:s'),
        ));
        if ($ok) {
            // A success clears the slate, so an honest typo streak does not linger.
            $this->CI->db->where('bucket', $bucket)->where('ok', 0)->delete('ha_auth_attempt');
        }
    }

    private function throttled($bucket) {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        return $this->CI->db->where('bucket', $bucket)->where('ok', 0)
            ->where('created_at >=', $since)->count_all_results('ha_auth_attempt') >= self::MAX_FAILURES;
    }
}
