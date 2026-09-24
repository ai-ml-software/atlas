<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Key-based authentication: TOTP two-factor login, recovery codes, personal
 * API keys, throttling, and the encryption that protects stored secrets.
 * TOTP is checked against the published RFC 6238 test vectors, so a
 * passing run means real authenticator apps will agree with the server.
 */
class Test_key_auth extends Ha_testcase {

    private $user_id;

    public function setUp() {
        require_once APPPATH . 'libraries/Ha_totp.php';
        require_once APPPATH . 'libraries/Ha_crypto.php';
        $this->CI->load->library('ha_two_factor');
        $this->CI->load->library('ha_api_keys');
        $row = $this->db->get_where('users', array('email' => 'omar.learner@dyafagroup.sa'))->row_array();
        $this->user_id = (int) $row['id'];
        $this->db->where('user_id', $this->user_id)->delete('ha_user_2fa');
        $this->db->where('user_id', $this->user_id)->delete('ha_api_key');
        $this->db->empty_table('ha_auth_attempt');
    }

    // ------------------------------------------------------------------ TOTP

    public function test_base32_round_trips_arbitrary_bytes() {
        foreach (array('', 'f', 'fo', 'foo', 'foob', 'fooba', 'foobar', random_bytes(20)) as $raw) {
            $this->assertSame($raw, Ha_totp::base32_decode(Ha_totp::base32_encode($raw)), 'base32 round trip');
        }
        $this->assertSame('MZXW6YTBOI', Ha_totp::base32_encode('foobar'), 'RFC 4648 vector');
    }

    public function test_totp_matches_rfc6238_sha1_vectors() {
        $totp = new Ha_totp();
        $secret = Ha_totp::base32_encode('12345678901234567890');
        // RFC 6238 Appendix B, SHA1 column, last six digits of the 8-digit values.
        $vectors = array(59 => '287082', 1111111109 => '081804', 1111111111 => '050471',
                         1234567890 => '005924', 2000000000 => '279037', 20000000000 => '353130');
        foreach ($vectors as $time => $expected) {
            $this->assertSame($expected, $totp->code($secret, $totp->step($time)), 'TOTP at T=' . $time);
        }
    }

    public function test_totp_accepts_one_step_of_drift_and_blocks_replay() {
        $totp = new Ha_totp();
        $secret = $totp->new_secret();
        $now = 1700000000;
        $step = $totp->step($now);
        $this->assertSame($step, $totp->verify($secret, $totp->code($secret, $step), null, $now), 'current code');
        $this->assertSame($step - 1, $totp->verify($secret, $totp->code($secret, $step - 1), null, $now), 'previous step allowed');
        $this->assertFalse($totp->verify($secret, $totp->code($secret, $step - 2), null, $now), 'two steps old refused');
        $this->assertFalse($totp->verify($secret, $totp->code($secret, $step), $step, $now), 'same step cannot be replayed');
        $this->assertFalse($totp->verify($secret, 'abcdef', null, $now), 'non-numeric refused');
        $this->assertEquals(32, strlen($secret), '160-bit secret is 32 base32 characters');
    }

    public function test_otpauth_uri_and_qr_render_locally() {
        $totp = new Ha_totp();
        $uri = $totp->uri('JBSWY3DPEHPK3PXP', 'a@b.sa', 'Hospitality Academy');
        $this->assertContains('otpauth://totp/Hospitality%20Academy:a%40b.sa?', $uri);
        $this->assertContains('secret=JBSWY3DPEHPK3PXP', $uri);
        $qr = $totp->qr_data_uri($uri);
        $this->assertMatches('#^data:image/png;base64,#', $qr);
        $png = base64_decode(substr($qr, strlen('data:image/png;base64,')));
        $this->assertSame("\x89PNG", substr($png, 0, 4), 'a real PNG');
    }

    // ------------------------------------------------------------------- 2FA

    public function test_enrolment_is_off_until_a_code_confirms_it() {
        $tf = $this->CI->ha_two_factor;
        $start = $tf->begin($this->user_id, 'omar@example.test');
        $this->assertFalse($tf->is_enabled($this->user_id), 'not enabled on QR alone');
        $this->assertFalse($tf->confirm($this->user_id, '000000') !== false && $tf->is_enabled($this->user_id), 'wrong code does not enable');

        $row = $this->db->get_where('ha_user_2fa', array('user_id' => $this->user_id))->row_array();
        $this->assertNotContains($start['secret'], $row['secret_cipher'], 'secret is encrypted at rest');

        $codes = $tf->confirm($this->user_id, $tf->totp()->code($start['secret']));
        $this->assertCount(10, $codes, 'ten recovery codes');
        $this->assertTrue($tf->is_enabled($this->user_id));
        $row = $this->db->get_where('ha_user_2fa', array('user_id' => $this->user_id))->row_array();
        $this->assertNotContains($codes[0], $row['recovery_hashes'], 'recovery codes are stored hashed');
    }

    public function test_recovery_code_works_exactly_once() {
        $tf = $this->CI->ha_two_factor;
        $start = $tf->begin($this->user_id, 'x');
        $codes = $tf->confirm($this->user_id, $tf->totp()->code($start['secret']));
        $first = $tf->check($this->user_id, strtoupper($codes[3]), '127.0.0.1');
        $this->assertTrue($first['ok'], 'recovery code accepted (case-insensitive)');
        $this->assertTrue($first['used_recovery']);
        $this->assertEquals(9, $first['remaining']);
        $again = $tf->check($this->user_id, $codes[3], '127.0.0.1');
        $this->assertFalse($again['ok'], 'second use refused');
    }

    public function test_login_code_is_refused_after_five_failures() {
        $tf = $this->CI->ha_two_factor;
        $start = $tf->begin($this->user_id, 'x');
        $tf->confirm($this->user_id, $tf->totp()->code($start['secret'], $tf->totp()->step() - 1));
        for ($i = 0; $i < 5; $i++) {
            $tf->check($this->user_id, '000000', '127.0.0.1');
        }
        $locked = $tf->check($this->user_id, $tf->totp()->code($start['secret']), '127.0.0.1');
        $this->assertFalse($locked['ok'], 'even a right code is refused while throttled');
        $this->assertContains('Too many', $locked['error']);
    }

    public function test_disable_removes_the_factor() {
        $tf = $this->CI->ha_two_factor;
        $start = $tf->begin($this->user_id, 'x');
        $tf->confirm($this->user_id, $tf->totp()->code($start['secret']));
        $this->assertTrue($tf->disable($this->user_id));
        $this->assertFalse($tf->is_enabled($this->user_id));
        $this->assertDatabaseMissing('ha_user_2fa', array('user_id' => $this->user_id));
    }

    // -------------------------------------------------------------- API keys

    public function test_api_key_is_shown_once_and_stored_as_a_hash() {
        $k = $this->CI->ha_api_keys->create($this->user_id, 'Integration', array('courses:read', 'not:a-scope'));
        $this->assertMatches('/^ha_[a-f0-9]{12}_[A-Za-z0-9]{40}$/', $k['key']);
        $row = $this->db->get_where('ha_api_key', array('id' => $k['id']))->row_array();
        $this->assertNotContains(substr($k['key'], 16), json_encode($row), 'secret never stored');
        $this->assertSame('courses:read', $row['scopes'], 'unknown scopes dropped');
    }

    public function test_api_key_authenticates_its_owner_and_records_use() {
        $k = $this->CI->ha_api_keys->create($this->user_id, 'CI', array('profile:read'));
        $r = $this->CI->ha_api_keys->authenticate($k['key'], '10.0.0.5');
        $this->assertTrue($r['ok'], 'valid key accepted');
        $this->assertEquals($this->user_id, (int) $r['user']['id']);
        $this->assertTrue(Ha_api_keys::has_scope($r['key'], 'profile:read'));
        $this->assertFalse(Ha_api_keys::has_scope($r['key'], 'ai:generate'));
        $this->assertDatabaseHas('ha_api_key', array('id' => $k['id'], 'use_count' => 1, 'last_used_ip' => '10.0.0.5'));

        $tampered = substr($k['key'], 0, -1) . (substr($k['key'], -1) === 'A' ? 'B' : 'A');
        $this->assertEquals(401, $this->CI->ha_api_keys->authenticate($tampered, '10.0.0.5')['status'], 'one changed character fails');
        $this->assertEquals(401, $this->CI->ha_api_keys->authenticate('Bearer nonsense', '10.0.0.5')['status']);
    }

    public function test_revoked_expired_and_wrong_ip_keys_are_refused() {
        $api = $this->CI->ha_api_keys;
        $a = $api->create($this->user_id, 'revoke me', array('profile:read'));
        $this->assertTrue($api->revoke($a['id'], $this->user_id));
        $this->assertContains('revoked', $api->authenticate($a['key'], '1.1.1.1')['error']);

        $b = $api->create($this->user_id, 'expiring', array('profile:read'), date('Y-m-d', time() + 86400));
        $this->db->where('id', $b['id'])->update('ha_api_key', array('expires_at' => date('Y-m-d H:i:s', time() - 60)));
        $this->assertContains('expired', $api->authenticate($b['key'], '1.1.1.1')['error']);

        $c = $api->create($this->user_id, 'pinned', array('profile:read'), null, '203.0.113.7');
        $this->assertEquals(403, $api->authenticate($c['key'], '198.51.100.1')['status'], 'other IP refused');
        $this->assertTrue($api->authenticate($c['key'], '203.0.113.7')['ok'], 'pinned IP accepted');

        $other = $this->db->get_where('users', array('email' => 'admin@hospitalityacademy.sa'))->row_array();
        $d = $api->create($this->user_id, 'not yours', array('profile:read'));
        $this->assertFalse($api->revoke($d['id'], (int) $other['id'], false), 'a user cannot revoke someone else\'s key');
    }

    public function test_key_creation_validates_input() {
        $api = $this->CI->ha_api_keys;
        $this->assertThrows(function () use ($api) { $api->create(1, '', array('profile:read')); }, 'empty name');
        $this->assertThrows(function () use ($api) { $api->create(1, 'x', array('bogus')); }, 'no valid scope');
        $this->assertThrows(function () use ($api) { $api->create(1, 'x', array('profile:read'), '2001-01-01'); }, 'past expiry');
        $this->assertThrows(function () use ($api) { $api->create(1, 'x', array('profile:read'), null, 'not-an-ip'); }, 'bad IP');
    }

    public function test_failed_key_attempts_are_throttled_per_ip() {
        $api = $this->CI->ha_api_keys;
        for ($i = 0; $i < Ha_api_keys::MAX_FAILURES_PER_IP; $i++) {
            $api->authenticate('ha_000000000000_' . str_repeat('A', 40), '192.0.2.9');
        }
        $k = $api->create($this->user_id, 'ok', array('profile:read'));
        $this->assertEquals(429, $api->authenticate($k['key'], '192.0.2.9')['status'], 'address locked out');
        $this->assertTrue($api->authenticate($k['key'], '192.0.2.10')['ok'], 'other addresses unaffected');
    }

    // ---------------------------------------------------------------- crypto

    public function test_encryption_round_trips_and_detects_tampering() {
        $c = new Ha_crypto();
        $cipher = $c->encrypt('sk-ant-very-secret');
        $this->assertNotEquals($cipher, $c->encrypt('sk-ant-very-secret'), 'fresh nonce every time');
        $this->assertSame('sk-ant-very-secret', $c->decrypt($cipher));
        $raw = base64_decode(substr($cipher, 3));
        $raw[30] = chr(ord($raw[30]) ^ 1);
        $this->assertNull($c->decrypt('v1:' . base64_encode($raw)), 'GCM tag rejects a flipped bit');
        $this->assertNull($c->decrypt('garbage'));
        $this->assertSame('••••cret', Ha_crypto::hint('sk-ant-very-secret'));
    }
}
