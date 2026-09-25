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
            'ai'                   => array('en' => 'AI Studio', 'ar' => 'استوديو الذكاء الاصطناعي', 'actions' => array('view', 'configure', 'generate', 'approve', 'publish', 'use', 'govern')),
            'api_keys'             => array('en' => 'API Keys', 'ar' => 'مفاتيح الواجهة البرمجية', 'actions' => array('view', 'create', 'revoke')),
            // altus Hospitality Knowledge & Performance (ppt-features sections 35, 63).
            'knowledge'            => array('en' => 'Knowledge Library', 'ar' => 'مكتبة المعرفة', 'actions' => array('view', 'create', 'update', 'review', 'approve', 'publish', 'archive')),
            'curriculum'           => array('en' => 'Curriculum', 'ar' => 'المنهج', 'actions' => array('view', 'create', 'update', 'publish')),
            'competencies'         => array('en' => 'Competencies', 'ar' => 'الكفاءات', 'actions' => array('view', 'create', 'update', 'delete', 'assess')),
            'rubrics'              => array('en' => 'Practical Rubrics', 'ar' => 'نماذج التقييم العملي', 'actions' => array('view', 'create', 'update', 'publish')),
            'practicals'           => array('en' => 'Practical Assessments', 'ar' => 'التقييمات العملية', 'actions' => array('view', 'assess', 'void')),
            'gaps'                 => array('en' => 'Competency Gaps', 'ar' => 'فجوات الكفاءة', 'actions' => array('view', 'waive')),
            'action_plans'         => array('en' => 'Action Plans', 'ar' => 'خطط التحسين', 'actions' => array('view', 'create', 'update', 'assign', 'review')),
            'reassessments'        => array('en' => 'Reassessments', 'ar' => 'إعادة التقييم', 'actions' => array('view', 'request', 'approve')),
            'readiness'            => array('en' => 'Readiness', 'ar' => 'الجاهزية', 'actions' => array('view', 'calculate', 'configure')),
            'cohorts'              => array('en' => 'Cohorts', 'ar' => 'الدفعات', 'actions' => array('view', 'create', 'update', 'delete')),
            'kpis'                 => array('en' => 'KPIs', 'ar' => 'مؤشرات الأداء', 'actions' => array('view', 'create', 'update', 'import')),
            'engagements'          => array('en' => 'Engagements', 'ar' => 'المهام الاستشارية', 'actions' => array('view', 'create', 'update')),
            'frameworks'           => array('en' => 'Advisory Frameworks', 'ar' => 'الأطر الاستشارية', 'actions' => array('view', 'assess', 'configure')),
            'quality_audits'       => array('en' => 'Quality Audits', 'ar' => 'تدقيق الجودة', 'actions' => array('view', 'create', 'update')),
            'corporate'            => array('en' => 'Corporate Content', 'ar' => 'المحتوى المؤسسي', 'actions' => array('view', 'update', 'publish')),
            'branding'             => array('en' => 'Branding', 'ar' => 'الهوية البصرية', 'actions' => array('view', 'update')),
            'executive'            => array('en' => 'Executive View', 'ar' => 'العرض التنفيذي', 'actions' => array('view')),
            'imports'              => array('en' => 'Imports', 'ar' => 'الاستيراد', 'actions' => array('run')),
            'system'               => array('en' => 'System', 'ar' => 'النظام', 'actions' => array('health', 'configure')),
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
            'configure'   => array('en' => 'Configure', 'ar' => 'إعداد'),
            'generate'    => array('en' => 'Generate with', 'ar' => 'التوليد عبر'),
            'use'         => array('en' => 'Use', 'ar' => 'استخدام'),
            'govern'      => array('en' => 'Govern', 'ar' => 'حوكمة'),
            'review'      => array('en' => 'Review', 'ar' => 'مراجعة'),
            'archive'     => array('en' => 'Archive', 'ar' => 'أرشفة'),
            'assess'      => array('en' => 'Assess', 'ar' => 'تقييم'),
            'void'        => array('en' => 'Void', 'ar' => 'إبطال'),
            'waive'       => array('en' => 'Waive', 'ar' => 'إعفاء'),
            'request'     => array('en' => 'Request', 'ar' => 'طلب'),
            'calculate'   => array('en' => 'Calculate', 'ar' => 'احتساب'),
            'health'      => array('en' => 'Monitor health of', 'ar' => 'مراقبة صحة'),
        );
    }

    /** Grants shared by every role that manages people's capability, not content. */
    private static function capability_manager_grants() {
        return array(
            'competencies' => array('view', 'assess'), 'practicals' => array('view', 'assess'),
            'gaps' => '*', 'action_plans' => '*', 'reassessments' => '*',
            'readiness' => array('view', 'calculate'), 'cohorts' => '*',
            'knowledge' => array('view'), 'curriculum' => array('view'), 'rubrics' => array('view'),
            'ai' => array('use'),
        );
    }

    /**
     * The eight roles required by plan section 2 / PHASE 2, extended with the
     * platform, organisation and property roles of ppt-features section 35.
     */
    public static function roles() {
        $roles = array(
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
                    'ai' => '*', 'api_keys' => '*',
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
                    // Instructors draft with AI; an academy admin approves.
                    'ai' => array('view', 'generate'),
                    'api_keys' => '*',
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
                    'api_keys' => '*',
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

        // --- HK&P grants on the original roles -------------------------------
        $cap = self::capability_manager_grants();
        $roles['academy_admin']['en'] = 'Altus Administrator';
        $roles['academy_admin']['ar'] = 'مدير ألتوس';
        $roles['academy_admin']['desc_en'] = 'Runs the platform for every client: curriculum, knowledge approval, certification, analytics and AI governance.';
        $roles['academy_admin']['desc_ar'] = 'يدير المنصة لجميع العملاء: المنهج واعتماد المعرفة والشهادات والتحليلات وحوكمة الذكاء الاصطناعي.';
        $roles['academy_admin']['grants'] += array(
            'knowledge' => '*', 'curriculum' => '*', 'competencies' => '*', 'rubrics' => '*', 'practicals' => '*',
            'gaps' => '*', 'action_plans' => '*', 'reassessments' => '*', 'readiness' => '*', 'cohorts' => '*',
            'kpis' => '*', 'engagements' => '*', 'frameworks' => '*', 'quality_audits' => '*', 'corporate' => '*',
            'branding' => '*', 'executive' => '*', 'imports' => '*', 'system' => array('health'),
        );
        $roles['academy_admin']['grants']['organizations'] = '*';
        $roles['academy_admin']['grants']['properties'] = '*';
        $roles['academy_admin']['grants']['departments'] = '*';
        $roles['academy_admin']['grants']['users'] = '*';

        $roles['instructor']['grants'] += array(
            'knowledge' => array('view', 'create', 'update'), 'curriculum' => array('view'),
            'competencies' => array('view'), 'rubrics' => array('view', 'create', 'update'),
        );
        $roles['instructor']['grants']['ai'][] = 'use';

        $roles['org_admin']['grants'] += $cap + array(
            'kpis' => '*', 'branding' => '*', 'quality_audits' => '*', 'executive' => array('view'),
            'imports' => array('run'), 'engagements' => array('view'), 'frameworks' => array('view'),
        );
        $roles['org_admin']['grants']['knowledge'] = array('view', 'create', 'update', 'review', 'approve', 'publish', 'archive');
        $roles['org_admin']['grants']['competencies'] = array('view', 'create', 'update', 'assess');
        $roles['org_admin']['grants']['rubrics'] = array('view', 'create', 'update', 'publish');
        $roles['org_admin']['grants']['readiness'] = '*';

        $roles['property_manager']['en'] = 'General Manager';
        $roles['property_manager']['ar'] = 'المدير العام';
        $roles['property_manager']['grants'] += $cap + array(
            'kpis' => array('view', 'create', 'update', 'import'), 'branding' => array('view'),
            'quality_audits' => '*', 'executive' => array('view'),
        );
        // A property authors its own local SOPs and standards; review and publish stay separate.
        $roles['property_manager']['grants']['knowledge'] = array('view', 'create', 'update', 'archive');
        // A General Manager creates their own staff and applies the property's brand (ppt-features 4B, 190).
        $roles['property_manager']['grants']['users'] = array('view', 'create');
        $roles['property_manager']['grants']['branding'] = '*';

        $roles['department_manager']['en'] = 'Department Head';
        $roles['department_manager']['ar'] = 'رئيس القسم';
        $roles['department_manager']['grants'] += $cap;

        $roles['learner']['en'] = 'Employee / Learner';
        $roles['learner']['ar'] = 'موظف / متدرب';
        $roles['learner']['grants'] += array(
            'knowledge' => array('view'), 'competencies' => array('view'), 'action_plans' => array('view', 'update'),
            'reassessments' => array('view', 'request'), 'readiness' => array('view'), 'ai' => array('use'),
        );

        $roles['auditor']['grants'] += array(
            'knowledge' => array('view'), 'competencies' => array('view'), 'gaps' => array('view'),
            'action_plans' => array('view'), 'readiness' => array('view'), 'kpis' => array('view'),
            'quality_audits' => array('view'), 'practicals' => array('view'),
        );

        // --- New roles (ppt-features section 35) -----------------------------
        $roles['altus_admin'] = array(
            'en' => 'Altus Platform Administrator', 'ar' => 'مسؤول منصة ألتوس',
            'scope' => 'system',
            'desc_en' => 'Administers clients, properties, users, configuration and system health across the platform.',
            'desc_ar' => 'يدير العملاء والفنادق والمستخدمين والإعدادات وصحة النظام على مستوى المنصة.',
            'grants' => array(
                'dashboard' => array('view'), 'users' => '*', 'learners' => '*', 'managers' => '*',
                'organizations' => '*', 'properties' => '*', 'departments' => '*', 'job_roles' => '*',
                'reports' => '*', 'analytics' => '*', 'audit_logs' => '*', 'settings' => '*', 'notifications' => '*',
                'certificates' => '*', 'branding' => '*', 'executive' => '*', 'imports' => '*', 'system' => '*',
                'kpis' => '*', 'readiness' => '*', 'cohorts' => '*', 'gaps' => array('view'),
                'knowledge' => array('view'), 'curriculum' => array('view'), 'competencies' => array('view'),
                'ai' => array('view', 'use', 'govern'), 'api_keys' => '*',
            ),
        );
        $roles['content_manager'] = array(
            'en' => 'Altus Content Manager', 'ar' => 'مدير محتوى ألتوس',
            'scope' => 'system',
            'desc_en' => 'Authors the master curriculum, knowledge items, question banks and rubrics. Cannot approve or publish knowledge.',
            'desc_ar' => 'يؤلف المنهج الرئيسي وعناصر المعرفة وبنوك الأسئلة ونماذج التقييم. لا يعتمد المعرفة ولا ينشرها.',
            'grants' => array(
                'dashboard' => array('view'), 'knowledge' => array('view', 'create', 'update', 'archive'),
                'curriculum' => array('view', 'create', 'update'), 'courses' => array('view', 'create', 'update'),
                'lessons' => '*', 'question_banks' => '*', 'assessments' => array('view', 'create', 'update'),
                'competencies' => array('view', 'create', 'update'), 'rubrics' => array('view', 'create', 'update'),
                'corporate' => array('view', 'update'), 'media' => '*', 'ai' => array('view', 'generate', 'use'),
                'imports' => array('run'),
            ),
        );
        $roles['quality_reviewer'] = array(
            'en' => 'Altus Quality Reviewer', 'ar' => 'مراجع جودة ألتوس',
            'scope' => 'system',
            'desc_en' => 'Reviews and quality-approves knowledge and curriculum. Publishing stays with an administrator.',
            'desc_ar' => 'يراجع المعرفة والمنهج ويعتمد جودتها. يبقى النشر بيد المسؤول.',
            'grants' => array(
                'dashboard' => array('view'), 'knowledge' => array('view', 'review', 'approve'),
                'curriculum' => array('view'), 'courses' => array('view', 'approve'), 'assessments' => array('view'),
                'question_banks' => array('view'), 'competencies' => array('view'), 'rubrics' => array('view'),
                'corporate' => array('view'), 'ai' => array('view', 'use'), 'reports' => array('view'),
            ),
        );
        $roles['consultant'] = array(
            'en' => 'Altus Consultant', 'ar' => 'مستشار ألتوس',
            'scope' => 'system',
            'desc_en' => 'Runs client engagements, advisory frameworks and cross-property analytics.',
            'desc_ar' => 'يدير المهام الاستشارية للعملاء والأطر الاستشارية والتحليلات عبر الفنادق.',
            'grants' => array(
                'dashboard' => array('view'), 'engagements' => '*', 'frameworks' => array('view', 'assess'),
                'analytics' => '*', 'reports' => '*', 'executive' => array('view'), 'kpis' => array('view', 'create', 'update', 'import'),
                'readiness' => array('view'), 'gaps' => array('view'), 'competencies' => array('view'),
                'quality_audits' => '*', 'organizations' => array('view'), 'properties' => array('view'),
                'knowledge' => array('view'), 'ai' => array('use'),
            ),
        );
        $roles['executive'] = array(
            'en' => 'Executive / Owner', 'ar' => 'تنفيذي / مالك',
            'scope' => 'organization',
            'desc_en' => 'Board-grade view of capability, readiness, risk and KPIs. No employee-level detail.',
            'desc_ar' => 'عرض على مستوى مجلس الإدارة للقدرات والجاهزية والمخاطر ومؤشرات الأداء دون تفاصيل الموظفين.',
            'grants' => array(
                'dashboard' => array('view'), 'executive' => array('view'), 'reports' => array('view', 'export'),
                'analytics' => array('view'), 'kpis' => array('view'), 'readiness' => array('view'),
                'engagements' => array('view'), 'frameworks' => array('view'),
            ),
        );
        $roles['training_manager'] = array(
            'en' => 'HR / Training Manager', 'ar' => 'مدير الموارد البشرية / التدريب',
            'scope' => 'organization',
            'desc_en' => 'Assigns learning, runs cohorts and imports, follows gaps and issues certification across the organisation.',
            'desc_ar' => 'يسند التعلم ويدير الدفعات والاستيراد ويتابع الفجوات ويصدر الشهادات على مستوى المنشأة.',
            'grants' => $cap + array(
                'dashboard' => array('view'), 'users' => array('view', 'create', 'update', 'import', 'export'),
                'learners' => '*', 'training_assignments' => '*', 'enrollments' => '*', 'certificates' => array('view', 'issue', 'export'),
                'reports' => '*', 'analytics' => array('view'), 'imports' => array('run'), 'job_roles' => array('view'),
                'departments' => array('view'), 'courses' => array('view'), 'learning_paths' => array('view'),
            ),
        );
        $roles['property_admin'] = array(
            'en' => 'Property Admin', 'ar' => 'مسؤول الفندق',
            'scope' => 'property',
            'desc_en' => 'Configures one property: staff, departments, branding and local standards.',
            'desc_ar' => 'يهيئ فندقاً واحداً: الموظفين والأقسام والهوية البصرية والمعايير المحلية.',
            'grants' => array(
                'dashboard' => array('view'), 'users' => array('view', 'create', 'update'), 'learners' => array('view', 'update'),
                'properties' => array('view', 'update'), 'departments' => '*', 'job_roles' => array('view'),
                'branding' => '*', 'knowledge' => array('view', 'create', 'update'), 'cohorts' => '*',
                'training_assignments' => array('view', 'create', 'assign'), 'readiness' => array('view', 'calculate'),
                'reports' => array('view', 'export'), 'kpis' => array('view', 'create', 'update', 'import'), 'ai' => array('use'),
            ),
        );
        $roles['supervisor'] = array(
            'en' => 'Supervisor / Assessor', 'ar' => 'مشرف / مقيّم',
            'scope' => 'department',
            'desc_en' => 'Conducts practical assessments, records evidence, reviews action plans and runs reassessments.',
            'desc_ar' => 'يجري التقييمات العملية ويسجل الأدلة ويراجع خطط التحسين وينفذ إعادة التقييم.',
            'grants' => array(
                'dashboard' => array('view'), 'learners' => array('view'),
                'competencies' => array('view', 'assess'), 'practicals' => array('view', 'assess'),
                'gaps' => array('view'), 'action_plans' => array('view', 'update', 'review'),
                'reassessments' => '*', 'readiness' => array('view'), 'rubrics' => array('view'),
                'knowledge' => array('view'), 'ai' => array('use'),
            ),
        );
        return $roles;
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
