<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Per-form CSRF protection for the AI Studio, security and API key screens.
 *
 * The install runs with $config['csrf_protection'] = FALSE because the legacy
 * theme's forms do not send a token, so switching it on globally would break
 * login and checkout. These screens change credentials and spend money on
 * provider APIs, so they carry their own session-bound token instead.
 */

if (!function_exists('ha_csrf_token')) {
    function ha_csrf_token() {
        $CI =& get_instance();
        $CI->load->library('session');
        $token = $CI->session->userdata('ha_csrf');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $CI->session->set_userdata('ha_csrf', $token);
        }
        return $token;
    }
}

if (!function_exists('ha_csrf_field')) {
    function ha_csrf_field() {
        return '<input type="hidden" name="ha_csrf" value="' . ha_csrf_token() . '">';
    }
}

if (!function_exists('ha_csrf_valid')) {
    /** Accepts the token from the form field or the X-HA-CSRF header (AJAX). */
    function ha_csrf_valid() {
        $CI =& get_instance();
        $expected = $CI->session->userdata('ha_csrf');
        $given = $CI->input->post('ha_csrf');
        if (!$given && isset($_SERVER['HTTP_X_HA_CSRF'])) {
            $given = $_SERVER['HTTP_X_HA_CSRF'];
        }
        return is_string($expected) && is_string($given) && $expected !== '' && hash_equals($expected, $given);
    }
}

if (!function_exists('ha_client_ip')) {
    /** REMOTE_ADDR only: forwarded headers are client-controlled and would let an attacker pick their own throttle bucket. */
    function ha_client_ip() {
        return isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '0.0.0.0';
    }
}
