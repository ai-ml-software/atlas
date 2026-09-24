<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */


if ( ! function_exists('get_user_role'))
{
	function get_user_role($type = "", $user_id = '') {
		$CI	=&	get_instance();
		$CI->load->database();

        $role_id	=	$CI->db->get_where('users' , array('id' => $user_id))->row()->role_id;
        $user_role	=	$CI->db->get_where('role' , array('id' => $role_id))->row()->name;

        if ($type == "user_role") {
            return $user_role;
        }else {
            return $role_id;
        }
	}
}


if ( ! function_exists('is_purchased'))
{
	function is_purchased($course_id = "", $user_id = "") {
		$CI	=&	get_instance();
		$CI->load->library('session');
		$CI->load->database();

		if (!$CI->session->userdata('user_login'))
			return false;

		if($user_id == "")
			$user_id = $CI->session->userdata('user_id');

		$enrolled_history = $CI->db->get_where('enrol' , ['user_id' => $user_id, 'course_id' => $course_id]);
		if ($enrolled_history->num_rows() > 0) {
			$expiry_date = $enrolled_history->row('expiry_date');
			// expiry_date is a varchar and "no expiry" has been written into
			// it three different ways: NULL, an empty string and "0". Only
			// NULL was treated as lifetime here, so a row holding "0" read as
			// expired and locked the learner out of a course they were
			// enrolled on. Treat anything that is not a real timestamp as no
			// expiry, which is what every one of those values means.
			if($expiry_date === null || trim((string) $expiry_date) === '' || (int) $expiry_date <= 0 || (int) $expiry_date >= time()){
				return true;
			}else{
				return false;
			}
		}else {
			return false;
		}
	}
}
if ( ! function_exists('enroll_status'))
{
	function enroll_status($course_id = "", $user_id = "") {
		$CI	=&	get_instance();
		$CI->load->library('session');
		$CI->load->database();


		if($user_id == "")
			$user_id = $CI->session->userdata('user_id');


		$enrolled_history = $CI->db->get_where('enrol' , ['user_id' => $user_id, 'course_id' => $course_id]);
		if ($enrolled_history->num_rows() > 0) {
			$expiry_date = $enrolled_history->row('expiry_date');
			// Same rule as is_enrolled above: NULL, an empty string and "0"
			// have all been used to mean "no expiry", and treating only NULL
			// as lifetime reported an enrolled learner as expired.
			if($expiry_date === null || trim((string) $expiry_date) === '' || (int) $expiry_date <= 0 || (int) $expiry_date >= time()){
				return 'valid';
			}else{
				return 'expired';
			}
		}else {
			return false;
		}
	}
}

// ------------------------------------------------------------------------
/* End of file user_helper.php */
/* Location: ./system/helpers/user_helper.php */
