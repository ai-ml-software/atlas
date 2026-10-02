<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** A CSRF-confirmed course choice survives password/device/2FA authentication. */
class Ha_course_entry {
    private $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->library(array('session', 'ha_auth', 'ha_learning'));
        $this->CI->load->helper(array('url', 'ha_locale'));
    }

    public function remember($course_id, $locale) {
        // Only free, public courses can be selected before authentication.
        $course = $this->CI->db->get_where('ha_course', array('id' => (int) $course_id))->row_array();
        if (!$course || $course['status'] !== 'published' || !$course['is_free']
            || $course['organization_id'] || $course['property_id']) {
            throw new RuntimeException('This course is not available for public enrollment.');
        }
        $this->CI->session->set_userdata('ha_course_start', array(
            'course_id' => (int) $course_id,
            'locale' => ha_locale_enabled($locale) ? $locale : ha_locale_default(),
            'expires' => time() + 1800,
        ));
    }

    /** Called only after all authentication factors succeed. Consumed exactly once. */
    public function finish($user_id) {
        $intent = $this->CI->session->userdata('ha_course_start');
        $this->CI->session->unset_userdata('ha_course_start');
        if (!is_array($intent)) { return null; }
        if (empty($intent['expires']) || (int) $intent['expires'] < time()) {
            throw new RuntimeException('Your course selection expired. Choose the course again.');
        }
        $this->CI->ha_auth->refresh();
        if ($this->CI->ha_auth->id() !== (int) $user_id || !$this->CI->ha_auth->check()
            || !$this->CI->ha_auth->has('courses.view')) {
            throw new RuntimeException('Your account does not have access to learning.');
        }
        $id = (int) $intent['course_id'];
        // Revalidate: status, price and scope may change while a login is pending.
        $course = $this->CI->db->get_where('ha_course', array('id' => $id))->row_array();
        if (!$course || !$course['is_free'] || $course['organization_id'] || $course['property_id']) {
            throw new RuntimeException('This course is no longer available for public enrollment.');
        }
        $this->CI->ha_learning->enroll($user_id, $id, 'self');
        $locale = ha_locale_enabled($intent['locale']) ? $intent['locale'] : ha_locale_default();
        $this->CI->session->unset_userdata(array('hkp_return', 'url_history'));
        return site_url('hkp/learn/module/' . $id) . '?lang=' . rawurlencode($locale);
    }

    /** Consume return links, allowing only pages inside this installation. */
    public function return_url() {
        $values = array($this->CI->session->userdata('hkp_return'), $this->CI->session->userdata('url_history'));
        $this->CI->session->unset_userdata(array('hkp_return', 'url_history'));
        foreach ($values as $url) {
            if ($this->safe_return($url)) { return $url; }
        }
        return site_url('hkp');
    }

    public function safe_return($url) {
        if (!is_string($url) || $url === '' || preg_match('/[\\\\\x00-\x20\x7f]/', $url)) { return false; }
        $base = parse_url(base_url());
        $target = parse_url($url);
        if (!$target || isset($target['user']) || isset($target['pass'])
            || !isset($target['scheme'], $target['host'])
            || strtolower($target['scheme']) !== strtolower($base['scheme'])
            || strtolower($target['host']) !== strtolower($base['host'])
            || ($target['port'] ?? null) !== ($base['port'] ?? null)) { return false; }
        $path = rawurldecode($target['path'] ?? '/');
        $root = rtrim($base['path'] ?? '/', '/') . '/';
        if (strpos($path, $root) !== 0 || preg_match('~(?:^|/)\.\.?(/|$)|[\\\\\x00-\x20\x7f]~', $path)) { return false; }
        $relative = substr($path, strlen($root));
        return !preg_match('~^(?:index\.php/)?(?:login|logout|sign_up)(/|$)~i', $relative);
    }
}
