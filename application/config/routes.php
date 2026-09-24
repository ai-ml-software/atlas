<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
// The root of the site is the Hospitality Academy. It detects the visitor's
// language and lands them on /en or /ar. Every legacy Academy LMS route
// (login, admin, user area) is untouched and keeps working.
$route['default_controller'] = 'academy';
$route['404_override']       = 'home/page_not_found';
$route['certificate/(:any)'] = "addons/certificate/generate_certificate/$1";

//course bundles
$route['course_bundles/(:any)']               = "addons/course_bundles/index/$1";
$route['course_bundles']                      = "addons/course_bundles";
$route['course_bundles/search/(:any)']        = "addons/course_bundles/search/$1";
$route['course_bundles/search/(:any)/(:any)'] = "addons/course_bundles/search/$1/$1";
$route['bundle_details/(:any)/(:any)']        = "addons/course_bundles/bundle_details/$1";
$route['bundle_details/(:any)']               = "addons/course_bundles/bundle_details/$1/$1";
$route['course_bundles/buy/(:any)']           = "addons/course_bundles/buy/$1";
$route['home/my_bundles']                     = "addons/course_bundles/my_bundles";
$route['home/bundle_invoice/(:any)']          = "addons/course_bundles/invoice/$1";
//end course bundles

//ebook
$route['ebook/ebook_details/(:any)/(:any)'] = "addons/ebook/ebook_details/$1/$2";
$route['ebook']                             = "addons/ebook/ebooks";
$route['ebook_manager/all_ebooks']          = "addons/ebook_manager/all_ebooks";
$route['ebook_manager/add_ebook']           = "addons/ebook_manager/add_ebook";
$route['ebook_manager/payment_history']     = "addons/ebook_manager/payment_history";
$route['ebook_manager/category']            = "addons/ebook_manager/category";
$route['ebook/buy/(:any)']                  = "addons/ebook/buy/$1";
$route['home/my_ebooks']                    = "addons/ebook/my_ebooks";
//end ebook

//BLog
$route['blogs']        = "blog/blogs";
$route['blogs/(:any)'] = "blog/blogs/$1";
//End blog

//Custom page
$route['page/(:any)'] = "page/index/$1";
//End Custom page

//tutor booking ..... tutor_booking/tutors
$route['tutors']                    = "addons/tutor_booking/list_of_tuitions";
$route['tutors/(:any)']             = "addons/tutor_booking/list_of_tuitions/$1";
$route['tutor/filter']              = "addons/tutor_booking/list_of_tuitions_after_filter";
$route['schedules_bookings/(:any)'] = "addons/tutor_booking/tutor_details/$1";
$route['my_bookings']               = "addons/tutor_booking/booked_schedules_student";
//End tutor booking

// The shipped listing lives at home/courses. Bare /courses is the URL people
// type and link to, so it resolves there rather than at the theme's 404.
$route['courses']       = 'home/courses';
$route['course/(:any)'] = 'home/course/$1';

$route['sitemap.xml'] = 'sitemap';

// Versioned, API-key authenticated JSON API. The legacy /api/* (mobile app,
// JWT) is a separate controller and unaffected.
$route['api/v1']      = 'api_v1/dispatch';
$route['api/v1/(.+)'] = 'api_v1/dispatch';

// ---------------------------------------------------------------------------
// Hospitality Academy public website (plan sections 25, 26, 28, 40).
// The locale is always explicit in the URL so hreflang is honest and a link
// can be shared in the language it was read in.
// ---------------------------------------------------------------------------
$route['academy-sitemap.xml']                 = 'academy/sitemap';
$route['academy-robots.txt']                  = 'academy/robots';

$route['(en|ar)']                             = 'academy/home/$1';
$route['(en|ar)/courses']                     = 'academy/courses/$1';
$route['(en|ar)/courses/(:any)']              = 'academy/course/$1/$2';
$route['(en|ar)/programs']                    = 'academy/programs/$1';
$route['(en|ar)/programs/(:any)']             = 'academy/program/$1/$2';
$route['(en|ar)/learning-paths']              = 'academy/paths/$1';
$route['(en|ar)/learning-paths/(:any)']       = 'academy/path/$1/$2';
$route['(en|ar)/hospitality-topics']          = 'academy/topics/$1';
$route['(en|ar)/hospitality-topics/(:any)']   = 'academy/topic/$1/$2';
$route['(en|ar)/sop']                         = 'academy/sops/$1';
$route['(en|ar)/sop/(:any)']                  = 'academy/sop/$1/$2';
$route['(en|ar)/articles']                    = 'academy/articles/$1';
$route['(en|ar)/articles/(:any)']             = 'academy/article/$1/$2';
$route['(en|ar)/certificates']                = 'academy/certificates/$1';
$route['(en|ar)/verify']                      = 'academy/verify/$1';
$route['(en|ar)/verify/(:any)']               = 'academy/verify/$1/$2';
$route['(en|ar)/about']                       = 'academy/about/$1';
$route['(en|ar)/hotels']                      = 'academy/hotels/$1';
$route['(en|ar)/hotels/training']             = 'academy/hotels_training/$1';
$route['(en|ar)/contact']                     = 'academy/contact/$1';
$route['(en|ar)/privacy']                     = 'academy/privacy/$1';
$route['(en|ar)/terms']                       = 'academy/terms/$1';
$route['(en|ar)/search']                      = 'academy/search/$1';
$route['(en|ar)/credits']                     = 'academy/credits/$1';
// Static pages resolve their slug from the database, in either language, so
// an Arabic page keeps an Arabic URL. This must stay last: every specific
// route above wins first. (.+) rather than (:any) so a nested slug such as
// "للفنادق/التدريب" still resolves.
$route['(en|ar)/(.+)']                        = 'academy/page_by_slug/$1/$2';

$route['translate_uri_dashes'] = false;
