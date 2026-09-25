<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Public, unauthenticated pages (ppt-features 113, 137, 161-163):
 *   /verify/{number}  and /verify/certificate/{code}
 *   /{en|ar}/altus and /{en|ar}/altus/case-studies — CMS-managed corporate content
 *
 * Only content marked public and published is ever shown here. Internal,
 * client-only and confidential corporate records, and every employee datum
 * beyond the minimum needed to confirm a certificate, stay private.
 */
class Hkp_public extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper(array('url', 'hkp'));
        $this->load->library(array('ha_tenant'));
        $this->output->set_header('X-Frame-Options: SAMEORIGIN');
        $this->output->set_header('X-Content-Type-Options: nosniff');
    }

    public function verify($code = '') {
        $lang = $this->input->get('lang');
        hkp_locale($lang === 'ar' ? 'ar' : 'en');
        $code = trim(rawurldecode((string) ($code ?: $this->input->get('code'))));
        $result = null;
        if ($code !== '') {
            // Throttle enumeration: at most 30 lookups per IP per 10 minutes.
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $recent = $this->db->where('ip_address', $ip)->where('verified_at >=', date('Y-m-d H:i:s', time() - 600))->count_all_results('ha_certificate_verification');
            if ($recent >= 30) {
                $this->output->set_status_header(429);
                $result = array('result' => 'throttled');
            } else {
                $this->load->library('ha_certification');
                $result = $this->ha_certification->verify($code);
            }
        }
        $this->output->set_header('X-Robots-Tag: noindex');
        $this->load->view('hkp/public_verify', array('code' => $code, 'r' => $result, 'brand' => $this->ha_tenant->brand()));
    }

    protected function blocks($section = null) {
        $db = $this->db->where(array('status' => 'published', 'visibility' => 'public'));
        if ($section) {
            $db->where('section', $section);
        }
        $out = array();
        foreach ($db->order_by('sort_order')->get('ha_corporate_block')->result_array() as $b) {
            $out[$b['section']][] = $b;
        }
        return $out;
    }

    public function corporate($lang = 'en') {
        hkp_locale($lang === 'ar' ? 'ar' : 'en');
        $this->load->view('hkp/public_corporate', array('lang' => hkp_locale(), 'blocks' => $this->blocks(), 'brand' => $this->ha_tenant->brand(),
            'services' => $this->db->order_by('sort_order')->get_where('ha_service', array('status' => 'published'))->result_array(),
            'sectors' => $this->db->order_by('sort_order')->get_where('ha_sector', array('status' => 'active'))->result_array(),
            'leaders' => $this->db->order_by('sort_order')->get_where('ha_leadership_profile', array('status' => 'published'))->result_array(),
            'cases' => $this->db->order_by('sort_order')->get_where('ha_case_study', array('status' => 'published', 'visibility' => 'public'))->result_array(),
            'partners' => $this->db->get_where('ha_partner', array('visibility' => 'public', 'is_official' => 1))->result_array(),
            'page' => 'about'));
    }

    public function case_studies($lang = 'en') {
        hkp_locale($lang === 'ar' ? 'ar' : 'en');
        $this->load->view('hkp/public_corporate', array('lang' => hkp_locale(), 'blocks' => array(), 'brand' => $this->ha_tenant->brand(),
            'services' => array(), 'sectors' => array(), 'leaders' => array(), 'partners' => array(),
            'cases' => $this->db->order_by('sort_order')->get_where('ha_case_study', array('status' => 'published', 'visibility' => 'public'))->result_array(),
            'page' => 'cases'));
    }
}
