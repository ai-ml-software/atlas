<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';

/**
 * Roles and permissions for the Hospitality Academy (plan sections 2, 38, 45).
 *
 * Permissions are generated from a module -> actions map so every sidebar
 * module has a real, enforceable permission code rather than a hidden button.
 */
class Seed_rbac extends Ha_seeder {

    /** module => array(actions), with bilingual module labels. */
    public static function modules() {
        return array(
            'dashboard'            => array('en' => 'Dashboard', 'ar' => 'لوحة التحكم', 'actions' => array('view')),
            'users'                => array('en' => 'Users', 'ar' => 'المستخدمون', 'actions' => array('view', 'create', 'update', 'delete', 'import', 'export', 'impersonate')),
            'learners'             => array('en' => 'Learners', 'ar' => 'المتدربون', 'actions' => array('view', 'create', 'update', 'delete', 'export')),
            'instructors'          => array('en' => 'Instructors', 'ar' => 'المدربون', 'actions' => array('view', 'create', 'update', 'delete')),
            'managers'             => array('en' => 'Managers', 'ar' => 'المديرون', 'actions' => array('view', 'update')),
            'organizations'        => array('en' => 'Organizations', 'ar' => 'المنشآت', 'actions' => array('view', 'create', 'update', 'delete', 'export')),
            'properties'           => array('en' => 'Properties', 'ar' => 'الفنادق والمواقع', 'actions' => array('view', 'create', 'update', 'delete', 'export')),
            'departments'          => array('en' => 'Departments', 'ar' => 'الأقسام', 'actions' => array('view', 'create', 'update', 'delete')),
            'job_roles'            => array('en' => 'Job Roles', 'ar' => 'المسميات الوظيفية', 'actions' => array('view', 'create', 'update', 'delete')),
            'programs'             => array('en' => 'Programs', 'ar' => 'البرامج', 'actions' => array('view', 'create', 'update', 'delete', 'publish')),
            'courses'              => array('en' => 'Courses', 'ar' => 'الدورات', 'actions' => array('view', 'create', 'update', 'delete', 'publish', 'approve', 'export')),
            'lessons'              => array('en' => 'Lessons', 'ar' => 'الدروس', 'actions' => array('view', 'create', 'update', 'delete')),
            'learning_paths'       => array('en' => 'Learning Paths', 'ar' => 'المسارات المهنية', 'actions' => array('view', 'create', 'update', 'delete', 'publish')),
            'enrollments'          => array('en' => 'Enrollments', 'ar' => 'التسجيلات', 'actions' => array('view', 'create', 'update', 'delete', 'export')),
            'training_assignments' => array('en' => 'Training Assignments', 'ar' => 'تكليفات التدريب', 'actions' => array('view', 'create', 'update', 'delete', 'assign', 'export')),
            'assessments'          => array('en' => 'Assessments', 'ar' => 'الاختبارات', 'actions' => array('view', 'create', 'update', 'delete', 'grade', 'publish')),
            'question_banks'       => array('en' => 'Question Banks', 'ar' => 'بنوك الأسئلة', 'actions' => array('view', 'create', 'update', 'delete')),
            'exams'                => array('en' => 'Exams', 'ar' => 'الامتحانات', 'actions' => array('view', 'create', 'update', 'delete', 'grade')),
            'assignments'          => array('en' => 'Assignments', 'ar' => 'الواجبات', 'actions' => array('view', 'create', 'update', 'delete', 'grade')),
            'certificates'         => array('en' => 'Certificates', 'ar' => 'الشهادات', 'actions' => array('view', 'create', 'update', 'issue', 'revoke', 'export')),
            'skills'               => array('en' => 'Skills', 'ar' => 'المهارات', 'actions' => array('view', 'create', 'update', 'delete')),
            'sops'                 => array('en' => 'SOP Library', 'ar' => 'مكتبة الإجراءات', 'actions' => array('view', 'create', 'update', 'delete', 'publish', 'approve', 'assign', 'acknowledge', 'export')),
            'checklists'           => array('en' => 'Checklists', 'ar' => 'قوائم التحقق', 'actions' => array('view', 'create', 'update', 'delete', 'run')),
            'attendance'           => array('en' => 'Attendance', 'ar' => 'الحضور', 'actions' => array('view', 'create', 'update', 'delete', 'export')),
            'notifications'        => array('en' => 'Notifications', 'ar' => 'الإشعارات', 'actions' => array('view', 'create', 'update')),
            'reports'              => array('en' => 'Reports', 'ar' => 'التقارير', 'actions' => array('view', 'export')),
            'analytics'            => array('en' => 'Analytics', 'ar' => 'التحليلات', 'actions' => array('view', 'export')),
            'cms_pages'            => array('en' => 'Pages', 'ar' => 'الصفحات', 'actions' => array('view', 'create', 'update', 'delete', 'publish')),
            'articles'             => array('en' => 'Articles', 'ar' => 'المقالات', 'actions' => array('view', 'create', 'update', 'delete', 'publish', 'approve')),
            'categories'           => array('en' => 'Categories', 'ar' => 'التصنيفات', 'actions' => array('view', 'create', 'update', 'delete')),
            'media'                => array('en' => 'Media', 'ar' => 'الوسائط', 'actions' => array('view', 'create', 'delete')),
            'faqs'                 => array('en' => 'FAQs', 'ar' => 'الأسئلة الشائعة', 'actions' => array('view', 'create', 'update', 'delete')),
            'testimonials'         => array('en' => 'Testimonials', 'ar' => 'آراء العملاء', 'actions' => array('view', 'create', 'update', 'delete')),
            'menus'                => array('en' => 'Menus', 'ar' => 'القوائم', 'actions' => array('view', 'create', 'update', 'delete')),
            'seo'                  => array('en' => 'SEO', 'ar' => 'تحسين محركات البحث', 'actions' => array('view', 'update', 'export')),
            'redirects'            => array('en' => 'Redirects', 'ar' => 'إعادة التوجيه', 'actions' => array('view', 'create', 'update', 'delete')),
            'competitors'          => array('en' => 'Competitor Intelligence', 'ar' => 'تحليل المنافسين', 'actions' => array('view', 'create', 'update', 'delete', 'export')),
            'competitor_keywords'  => array('en' => 'Competitor Keywords', 'ar' => 'كلمات المنافسين', 'actions' => array('view', 'create', 'update', 'delete', 'export')),
            'settings'             => array('en' => 'Settings', 'ar' => 'الإعدادات', 'actions' => array('view', 'update')),
            'audit_logs'           => array('en' => 'Audit Logs', 'ar' => 'سجل التدقيق', 'actions' => array('view', 'export')),
            'leads'                => array('en' => 'Leads', 'ar' => 'طلبات التواصل', 'actions' => array('view', 'update', 'delete', 'export')),
        );
    }

    public static function action_labels() {
        return array(
            'view'        => array('en' => 'View', 'ar' => 'عرض'),
            'create'      => array('en' => 'Create', 'ar' => 'إضافة'),
            'update'      => array('en' => 'Update', 'ar' => 'تعديل'),
            'delete'      => array('en' => 'Delete', 'ar' => 'حذف'),
            'publish'     => array('en' => 'Publish', 'ar' => 'نشر'),
            'approve'     => array('en' => 'Approve', 'ar' => 'اعتماد'),
            'assign'      => array('en' => 'Assign', 'ar' => 'تكليف'),
            'acknowledge' => array('en' => 'Acknowledge', 'ar' => 'إقرار'),
            'grade'       => array('en' => 'Grade', 'ar' => 'تصحيح'),
            'issue'       => array('en' => 'Issue', 'ar' => 'إصدار'),
            'revoke'      => array('en' => 'Revoke', 'ar' => 'إلغاء'),
            'export'      => array('en' => 'Export', 'ar' => 'تصدير'),
            'import'      => array('en' => 'Import', 'ar' => 'استيراد'),
            'impersonate' => array('en' => 'Impersonate', 'ar' => 'الدخول كمستخدم'),
            'run'         => array('en' => 'Run', 'ar' => 'تنفيذ'),
        );
    }

    /** The eight roles required by plan section 2 / PHASE 2. */
    public static function roles() {
        return array(
            'super_admin' => array(
                'en' => 'Super Admin', 'ar' => 'مدير النظام',
                'scope' => 'system',
                'desc_en' => 'Unrestricted control of the entire platform.',
                'desc_ar' => 'تحكم كامل في جميع أجزاء المنصة.',
                'grants' => '*',
            ),
            'academy_admin' => array(
                'en' => 'Academy Admin', 'ar' => 'مدير الأكاديمية',
                'scope' => 'system',
                'desc_en' => 'Runs the academy: programs, courses, instructors, certification and content approvals.',
                'desc_ar' => 'يدير الأكاديمية: البرامج والدورات والمدربين والشهادات واعتماد المحتوى.',
                'grants' => array(
                    'dashboard' => array('view'),
                    'users' => array('view', 'create', 'update', 'export'),
                    'learners' => '*', 'instructors' => '*', 'managers' => array('view'),
                    'organizations' => array('view'), 'properties' => array('view'), 'departments' => array('view'), 'job_roles' => '*',
                    'programs' => '*', 'courses' => '*', 'lessons' => '*', 'learning_paths' => '*',
                    'enrollments' => '*', 'training_assignments' => '*', 'assessments' => '*',
                    'question_banks' => '*', 'exams' => '*', 'assignments' => '*', 'certificates' => '*',
                    'skills' => '*', 'sops' => '*', 'checklists' => '*', 'attendance' => '*',
                    'notifications' => '*', 'reports' => '*', 'analytics' => '*',
                    'cms_pages' => '*', 'articles' => '*', 'categories' => '*', 'media' => '*',
                    'faqs' => '*', 'testimonials' => '*', 'menus' => '*', 'seo' => '*', 'redirects' => '*',
                    'competitors' => '*', 'competitor_keywords' => '*', 'leads' => '*',
                    'audit_logs' => array('view'),
                ),
            ),
            'instructor' => array(
                'en' => 'Instructor', 'ar' => 'مدرب',
                'scope' => 'self',
                'desc_en' => 'Builds and delivers assigned courses, grades work and answers learners.',
                'desc_ar' => 'ينشئ الدورات المسندة إليه ويصححها ويجيب المتدربين.',
                'grants' => array(
                    'dashboard' => array('view'),
                    'courses' => array('view', 'create', 'update', 'publish'),
                    'lessons' => '*',
                    'assessments' => array('view', 'create', 'update', 'delete', 'grade'),
                    'question_banks' => '*',
                    'assignments' => array('view', 'create', 'update', 'delete', 'grade'),
                    'exams' => array('view', 'create', 'update', 'grade'),
                    'enrollments' => array('view'), 'learners' => array('view'),
                    'attendance' => array('view', 'create', 'update'),
                    'certificates' => array('view'),
                    'media' => array('view', 'create'),
                    'reports' => array('view'),
                ),
            ),
            'org_admin' => array(
                'en' => 'Organization Admin', 'ar' => 'مدير المنشأة',
                'scope' => 'organization',
                'desc_en' => 'Manages one organization: its properties, employees and training compliance.',
                'desc_ar' => 'يدير منشأة واحدة: فنادقها وموظفيها والتزامها التدريبي.',
                'grants' => array(
                    'dashboard' => array('view'),
                    'users' => array('view', 'create', 'update', 'import', 'export'),
                    'learners' => '*', 'managers' => '*',
                    'organizations' => array('view', 'update'),
                    'properties' => '*', 'departments' => '*', 'job_roles' => '*',
                    'enrollments' => array('view', 'create', 'export'),
                    'training_assignments' => '*',
                    'courses' => array('view'), 'programs' => array('view'), 'learning_paths' => array('view'),
                    'certificates' => array('view', 'export'),
                    'skills' => array('view'),
                    'sops' => array('view', 'create', 'update', 'publish', 'approve', 'assign', 'export'),
                    'checklists' => '*', 'attendance' => '*',
                    'reports' => '*', 'analytics' => '*', 'notifications' => array('view'),
                    'audit_logs' => array('view'),
                ),
            ),
            'property_manager' => array(
                'en' => 'Property Manager', 'ar' => 'مدير الفندق',
                'scope' => 'property',
                'desc_en' => 'Runs training for one property and its departments.',
                'desc_ar' => 'يدير التدريب لفندق واحد وأقسامه.',
                'grants' => array(
                    'dashboard' => array('view'),
                    'users' => array('view'), 'learners' => array('view', 'update', 'export'),
                    'properties' => array('view', 'update'), 'departments' => array('view', 'create', 'update'),
                    'job_roles' => array('view'),
                    'training_assignments' => array('view', 'create', 'update', 'assign', 'export'),
                    'enrollments' => array('view', 'create'),
                    'courses' => array('view'), 'programs' => array('view'), 'learning_paths' => array('view'),
                    'certificates' => array('view', 'export'), 'skills' => array('view'),
                    'sops' => array('view', 'assign', 'export'), 'checklists' => array('view', 'run', 'create', 'update'),
                    'attendance' => array('view', 'create', 'update', 'export'),
                    'reports' => '*', 'analytics' => array('view'),
                ),
            ),
            'department_manager' => array(
                'en' => 'Department Manager', 'ar' => 'مدير القسم',
                'scope' => 'department',
                'desc_en' => 'Sees their own team, assigns training and verifies SOP acknowledgement.',
                'desc_ar' => 'يتابع فريقه ويكلفه بالتدريب ويتحقق من إقرار الإجراءات.',
                'grants' => array(
                    'dashboard' => array('view'),
                    'learners' => array('view', 'export'),
                    'departments' => array('view'),
                    'training_assignments' => array('view', 'create', 'assign'),
                    'enrollments' => array('view'),
                    'courses' => array('view'), 'programs' => array('view'), 'learning_paths' => array('view'),
                    'certificates' => array('view'), 'skills' => array('view'),
                    'sops' => array('view', 'assign'), 'checklists' => array('view', 'run'),
                    'attendance' => array('view', 'create', 'update'),
                    'assessments' => array('view'),
                    'reports' => array('view', 'export'),
                ),
            ),
            'learner' => array(
                'en' => 'Learner', 'ar' => 'متدرب',
                'scope' => 'self',
                'desc_en' => 'Takes assigned training, sits assessments, collects certificates and acknowledges SOPs.',
                'desc_ar' => 'يكمل التدريب المسند إليه ويجتاز الاختبارات ويحصل على الشهادات ويقر بالإجراءات.',
                'grants' => array(
                    'courses' => array('view'), 'programs' => array('view'), 'lessons' => array('view'),
                    'learning_paths' => array('view'), 'enrollments' => array('view'),
                    'assessments' => array('view'), 'assignments' => array('view'),
                    'certificates' => array('view'), 'skills' => array('view'),
                    'sops' => array('view', 'acknowledge'), 'checklists' => array('view', 'run'),
                    'attendance' => array('view'), 'notifications' => array('view'),
                ),
            ),
            'auditor' => array(
                'en' => 'Auditor', 'ar' => 'مدقق',
                'scope' => 'organization',
                'desc_en' => 'Read-only access to compliance, certificates, audit trails and reports.',
                'desc_ar' => 'اطلاع فقط على الالتزام والشهادات وسجلات التدقيق والتقارير.',
                'grants' => array(
                    'dashboard' => array('view'),
                    'reports' => array('view', 'export'), 'analytics' => array('view'),
                    'certificates' => array('view', 'export'), 'audit_logs' => array('view', 'export'),
                    'sops' => array('view', 'export'), 'training_assignments' => array('view'),
                    'learners' => array('view'), 'organizations' => array('view'),
                    'properties' => array('view'), 'departments' => array('view'),
                    'attendance' => array('view'), 'skills' => array('view'), 'enrollments' => array('view'),
                ),
            ),
        );
    }

    public function run($db) {
        $this->boot($db);
        $written = 0;
        $labels = self::action_labels();

        // Permissions
        $permission_ids = array();
        foreach (self::modules() as $module => $meta) {
            foreach ($meta['actions'] as $action) {
                $code = $module . '.' . $action;
                $al = isset($labels[$action]) ? $labels[$action] : array('en' => ucfirst($action), 'ar' => $action);
                $id = $this->upsert('ha_permission', array('code' => $code), array(
                    'module'   => $module,
                    'label_en' => $al['en'] . ' ' . $meta['en'],
                    'label_ar' => $al['ar'] . ' ' . $meta['ar'],
                ));
                $permission_ids[$code] = $id;
                $written++;
            }
        }

        // Roles + grants
        foreach (self::roles() as $code => $role) {
            $role_id = $this->upsert('ha_role', array('code' => $code), array(
                'name_en'        => $role['en'],
                'name_ar'        => $role['ar'],
                'description_en' => $role['desc_en'],
                'description_ar' => $role['desc_ar'],
                'scope'          => $role['scope'],
                'is_system'      => 1,
            ));
            $written++;

            $wanted = array();
            if ($role['grants'] === '*') {
                $wanted = array_keys($permission_ids);
            } else {
                foreach ($role['grants'] as $module => $actions) {
                    $available = self::modules();
                    if (!isset($available[$module])) {
                        continue;
                    }
                    $list = ($actions === '*') ? $available[$module]['actions'] : $actions;
                    foreach ($list as $action) {
                        if (isset($permission_ids[$module . '.' . $action])) {
                            $wanted[] = $module . '.' . $action;
                        }
                    }
                }
            }

            // Replace the grant set so removing a grant in code removes it in the database.
            $this->db->where('role_id', $role_id)->delete('ha_role_permission');
            foreach (array_unique($wanted) as $pcode) {
                $this->db->insert('ha_role_permission', array(
                    'role_id'       => $role_id,
                    'permission_id' => $permission_ids[$pcode],
                ));
                $written++;
            }
        }

        return $written;
    }
}
