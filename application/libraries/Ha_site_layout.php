<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** One public shell for the academy, account screens and legacy frontend. */
class Ha_site_layout {
    private $CI;
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->helper(array('ha_locale','ha_chrome','ha_media'));
        $this->CI->load->library(array('session','ha_catalog','ha_corporate'));
    }
    public function data($locale) {
        $locale = ha_locale_enabled($locale) ? $locale : ha_locale_default();
        ha_site_locale($locale);
        return array('locale'=>$locale, 'rtl'=>ha_locale_dir($locale)==='rtl', 'dir'=>ha_locale_dir($locale),
            'site_locales'=>ha_site_locales(), 'menu'=>$this->CI->ha_catalog->menu('public_header',$locale),
            'founders'=>$this->CI->db->table_exists('ha_leadership_profile') ? $this->CI->ha_corporate->locale($locale)->leaders() : array(),
            'studio_footer_menus'=>array('footer_learn'=>$this->CI->ha_catalog->menu('footer_learn',$locale),
                'footer_resources'=>$this->CI->ha_catalog->menu('footer_resources',$locale), 'footer_company'=>$this->CI->ha_catalog->menu('footer_company',$locale)),
            't'=>$this->phrases());
    }
    public function legacy_locale() {
        $explicit = $this->CI->input->get('lang');
        $legacy = $this->CI->session->userdata('language');
        $legacy = ha_locale_enabled($legacy) ? $legacy : array_search($legacy,ha_locale_config()['legacy'],true);
        $locale = ha_locale_enabled($explicit) ? $explicit : $this->CI->session->userdata('ha_public_locale');
        if (!ha_locale_enabled($locale)) { $locale = ha_locale_enabled($legacy) ? $legacy : ha_locale_default(); }
        $this->CI->session->set_userdata(array('ha_public_locale'=>$locale, 'hkp_locale'=>$locale,
            'language'=>ha_locale_legacy_column($locale) ?: $locale));
        return $locale;
    }
    public static function account_pages() {
        return array('login','sign_up','forgot_password','change_password_from_forgot_password',
            'two_factor','new_login_confirmation','verification_code');
    }
    public static function account_titles() {
        return array('login'=>ha_pt('Log in'), 'sign_up'=>ha_pt('Create your account'),
            'forgot_password'=>ha_pt('Reset your password'),
            'change_password_from_forgot_password'=>ha_pt('Choose a new password'),
            'two_factor'=>ha_pt('Verify your sign-in'),
            'new_login_confirmation'=>ha_pt('Confirm this device'), 'verification_code'=>ha_pt('Verify your email'));
    }
    public function phrases() {
        $en = array(
            'skip_to_content' => 'Skip to content',
            'search' => 'Search',
            'search_placeholder' => 'Search courses, topics and articles',
            'search_results_for' => 'Search results for',
            'no_results' => 'Nothing matched that search.',
            'courses' => 'Courses', 'programs' => 'Programs', 'learning_paths' => 'Learning Paths',
            'topics' => 'Hospitality Topics', 'sop' => 'SOP Resources', 'articles' => 'Articles',
            'certificates' => 'Certifications', 'about' => 'About Platform', 'for_hotels' => 'For Hotels',
            'contact' => 'Contact', 'home' => 'Home',
            'all_categories' => 'All categories', 'all_levels' => 'All levels',
            'level' => 'Level', 'duration' => 'Duration', 'minutes' => 'min', 'hours' => 'hours',
            'lessons' => 'lessons', 'free' => 'Free', 'certificate' => 'Certificate',
            'overview' => 'Overview', 'curriculum' => 'Curriculum', 'instructor' => 'Instructor',
            'outcomes' => 'What you will be able to do', 'requirements' => 'Requirements',
            'prerequisites' => 'Prerequisites', 'faq' => 'Frequently asked questions',
            'related_courses' => 'Related courses', 'part_of_programs' => 'Part of these programs',
            'skills_awarded' => 'Skills recorded on completion',
            'preview' => 'Preview', 'mandatory' => 'Mandatory', 'optional' => 'Optional',
            'enrol' => 'Start this course', 'sign_in_to_start' => 'Sign in to start',
            'read_more' => 'Read more', 'published' => 'Published', 'read_time' => 'min read',
            'verify_title' => 'Verify a certificate',
            'verify_help' => 'Enter the verification code printed on the certificate.',
            'verify_code' => 'Verification code', 'verify_button' => 'Check certificate',
            'verify_valid' => 'This certificate is valid.',
            'verify_expired' => 'This certificate has expired.',
            'verify_revoked' => 'This certificate has been revoked.',
            'verify_not_found' => 'No certificate matches that code.',
            'certificate_no' => 'Certificate number', 'issued_on' => 'Issued on',
            'expires_on' => 'Expires on', 'holder' => 'Holder', 'subject' => 'Course or program',
            'score' => 'Final score', 'status' => 'Status',
            'contact_name' => 'Your name', 'contact_email' => 'Work email', 'contact_phone' => 'Phone',
            'contact_org' => 'Hotel or organization', 'contact_city' => 'City',
            'contact_headcount' => 'Approximate number of employees',
            'contact_interest' => 'What you need', 'contact_message' => 'Message',
            'contact_submit' => 'Send enquiry',
            'contact_thanks' => 'Thank you. Your enquiry has been recorded and someone will reply by email.',
            'required_field' => 'This field is required.',
            'steps' => 'Steps', 'step' => 'Step', 'in_this_path' => 'Courses in this step',
            'version' => 'Version', 'effective_date' => 'Effective date', 'review_date' => 'Review date',
            'purpose' => 'Purpose', 'scope' => 'Scope', 'responsibilities' => 'Responsibilities',
            'required_tools' => 'Required tools', 'procedure' => 'Procedure', 'checklist' => 'Checklist',
            'safety_notes' => 'Safety notes', 'quality_standard' => 'Quality standard',
            'escalation' => 'Escalation', 'department' => 'Department',
            'showing' => 'Showing', 'of' => 'of', 'results' => 'results',
            'previous' => 'Previous', 'next' => 'Next',
            'not_found_title' => 'Page not found',
            'not_found_body' => 'The page you asked for does not exist. It may have been moved or the address may be mistyped.',
            'back_home' => 'Go to the home page',
            'language_switch' => 'العربية',
            'in_city' => 'Training emphasis in this city',
            'browse_all' => 'Browse all',
            'footer_note' => 'Hotel training, standard operating procedures and workforce certification.',
            'legal' => 'Legal', 'privacy' => 'Privacy', 'terms' => 'Terms',
        );
        // English is the source; application/language/site/{code}.php translates it.
        return array_map('ha_pt', $en);
    }

}
