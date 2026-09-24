<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';
require_once APPPATH . 'seeds/001_rbac.php';

/**
 * Organizations, Saudi properties, departments, job roles and staff accounts.
 * Plan sections 5, 10 (career ladders), 45.
 *
 * Demo accounts all use the password "Academy#2026" and are clearly marked as
 * seeded data. No business claims or statistics are invented here.
 */
class Seed_organizations extends Ha_seeder {

    const DEMO_PASSWORD = 'Academy#2026';
    const SUPER_ADMIN_EMAIL = 'admin@hospitalityacademy.sa';

    public static function departments() {
        return array(
            array('FO',      'Front Office',        'مكتب الاستقبال'),
            array('HK',      'Housekeeping',        'التدبير الفندقي'),
            array('FB',      'Food & Beverage',     'الأغذية والمشروبات'),
            array('KIT',     'Kitchen',             'المطبخ'),
            array('ENG',     'Engineering',         'الهندسة'),
            array('MNT',     'Maintenance',         'الصيانة'),
            array('SEC',     'Security',            'الأمن'),
            array('SLS',     'Sales',               'المبيعات'),
            array('REV',     'Revenue Management',  'إدارة الإيرادات'),
            array('FIN',     'Finance',             'المالية'),
            array('HR',      'Human Resources',     'الموارد البشرية'),
            array('PROC',    'Procurement',         'المشتريات'),
            array('GR',      'Guest Relations',     'علاقات الضيوف'),
            array('EVT',     'Events',              'الفعاليات'),
            array('SPA',     'Spa & Wellness',      'السبا والعافية'),
            array('LND',     'Laundry',             'المغسلة'),
        );
    }

    public static function properties() {
        return array(
            array('riyadh-business-tower', 'Dyafa Riyadh Business Tower', 'ضيافة برج الأعمال الرياض', 'Riyadh', 'Riyadh Region', 'hotel', 240, 'operational'),
            array('jeddah-corniche',       'Dyafa Jeddah Corniche',       'ضيافة كورنيش جدة',        'Jeddah', 'Makkah Region', 'hotel', 310, 'operational'),
            array('makkah-haram-view',     'Dyafa Makkah Haram View',     'ضيافة إطلالة الحرم مكة',   'Makkah', 'Makkah Region', 'hotel', 520, 'operational'),
            array('madinah-central',       'Dyafa Madinah Central',       'ضيافة المدينة المركزي',     'Madinah', 'Madinah Region', 'hotel', 380, 'operational'),
            array('al-khobar-waterfront',  'Dyafa Al Khobar Waterfront',  'ضيافة واجهة الخبر البحرية', 'Al Khobar', 'Eastern Province', 'hotel', 180, 'operational'),
            array('dammam-airport',        'Dyafa Dammam Airport',        'ضيافة مطار الدمام',        'Dammam', 'Eastern Province', 'hotel', 150, 'operational'),
            array('alula-desert-resort',   'Dyafa AlUla Desert Resort',   'ضيافة منتجع العلا الصحراوي', 'AlUla', 'Madinah Region', 'resort', 90, 'operational'),
            array('abha-highlands',        'Dyafa Abha Highlands',        'ضيافة مرتفعات أبها',       'Abha', 'Aseer Region', 'resort', 120, 'pre_opening'),
        );
    }

    /** Career ladders from plan section 10, expressed as job roles. */
    public static function job_roles() {
        return array(
            // code, EN, AR, department code, level
            array('fo-associate',        'Front Office Associate',          'موظف استقبال',                 'FO',  'associate'),
            array('fo-senior-associate', 'Senior Front Office Associate',   'موظف استقبال أول',             'FO',  'senior'),
            array('fo-supervisor',       'Front Office Supervisor',         'مشرف مكتب الاستقبال',          'FO',  'supervisor'),
            array('fo-assistant-manager','Assistant Front Office Manager',  'مساعد مدير مكتب الاستقبال',    'FO',  'assistant_manager'),
            array('fo-manager',          'Front Office Manager',            'مدير مكتب الاستقبال',          'FO',  'manager'),
            array('hk-room-attendant',   'Room Attendant',                  'عامل غرف',                     'HK',  'entry'),
            array('hk-senior-attendant', 'Senior Room Attendant',           'عامل غرف أول',                 'HK',  'senior'),
            array('hk-supervisor',       'Housekeeping Supervisor',         'مشرف التدبير الفندقي',         'HK',  'supervisor'),
            array('hk-assistant-exec',   'Assistant Executive Housekeeper', 'مساعد مدير التدبير الفندقي',   'HK',  'assistant_manager'),
            array('hk-executive',        'Executive Housekeeper',           'مدير التدبير الفندقي',         'HK',  'manager'),
            array('fb-associate',        'F&B Associate',                   'موظف أغذية ومشروبات',          'FB',  'associate'),
            array('fb-captain',          'F&B Captain',                     'كابتن صالة',                   'FB',  'senior'),
            array('fb-supervisor',       'F&B Supervisor',                  'مشرف الأغذية والمشروبات',      'FB',  'supervisor'),
            array('fb-assistant-manager','Assistant Restaurant Manager',    'مساعد مدير المطعم',            'FB',  'assistant_manager'),
            array('fb-restaurant-manager','Restaurant Manager',             'مدير المطعم',                  'FB',  'manager'),
            array('kit-commis',          'Commis Chef',                     'مساعد طاهٍ',                   'KIT', 'entry'),
            array('kit-chef-de-partie',  'Chef de Partie',                  'رئيس قسم الطهي',               'KIT', 'senior'),
            array('kit-sous-chef',       'Sous Chef',                       'مساعد رئيس الطهاة',            'KIT', 'assistant_manager'),
            array('kit-executive-chef',  'Executive Chef',                  'رئيس الطهاة التنفيذي',         'KIT', 'manager'),
            array('eng-technician',      'Maintenance Technician',          'فني صيانة',                    'ENG', 'entry'),
            array('eng-supervisor',      'Engineering Supervisor',          'مشرف الهندسة',                 'ENG', 'supervisor'),
            array('eng-chief',           'Chief Engineer',                  'كبير المهندسين',               'ENG', 'manager'),
            array('sec-officer',         'Security Officer',                'ضابط أمن',                     'SEC', 'entry'),
            array('gr-agent',            'Guest Relations Agent',           'موظف علاقات الضيوف',           'GR',  'associate'),
            array('rev-analyst',         'Revenue Analyst',                 'محلل إيرادات',                 'REV', 'associate'),
            array('rev-manager',         'Revenue Manager',                 'مدير الإيرادات',               'REV', 'manager'),
            array('hr-officer',          'HR Officer',                      'موظف موارد بشرية',             'HR',  'associate'),
            array('gm',                  'General Manager',                 'المدير العام',                 null,  'director'),
        );
    }

    /**
     * Seeded staff. key => [first, last, email, role code, dept code|null, property slug|null]
     */
    public static function staff() {
        return array(
            array('Nouf',    'Al Qahtani', 'academy.admin@hospitalityacademy.sa', 'academy_admin',      null,  null),
            array('Faisal',  'Al Harbi',   'org.admin@dyafagroup.sa',             'org_admin',          null,  null),
            array('Hala',    'Al Otaibi',  'instructor.fo@hospitalityacademy.sa', 'instructor',         'FO',  null),
            array('Tariq',   'Al Shehri',  'instructor.hk@hospitalityacademy.sa', 'instructor',         'HK',  null),
            array('Mona',    'Al Zahrani', 'instructor.fb@hospitalityacademy.sa', 'instructor',         'FB',  null),
            array('Abdullah','Al Ghamdi',  'gm.riyadh@dyafagroup.sa',             'property_manager',   null,  'riyadh-business-tower'),
            array('Reem',    'Al Dossari', 'gm.jeddah@dyafagroup.sa',             'property_manager',   null,  'jeddah-corniche'),
            array('Yousef',  'Al Mutairi', 'fom.riyadh@dyafagroup.sa',            'department_manager', 'FO',  'riyadh-business-tower'),
            array('Sara',    'Al Anazi',   'exechk.riyadh@dyafagroup.sa',         'department_manager', 'HK',  'riyadh-business-tower'),
            array('Khalid',  'Al Balawi',  'fbm.jeddah@dyafagroup.sa',            'department_manager', 'FB',  'jeddah-corniche'),
            array('Maha',    'Al Juhani',  'auditor@dyafagroup.sa',               'auditor',            null,  null),
        );
    }

    /** Learners spread across properties and departments. */
    public static function learners() {
        return array(
            array('Omar',    'Al Suwailem', 'omar.learner@dyafagroup.sa',    'FO',  'riyadh-business-tower', 'fo-associate'),
            array('Layan',   'Al Rashid',   'layan.learner@dyafagroup.sa',   'FO',  'riyadh-business-tower', 'fo-senior-associate'),
            array('Bandar',  'Al Faraj',    'bandar.learner@dyafagroup.sa',  'HK',  'riyadh-business-tower', 'hk-room-attendant'),
            array('Noura',   'Al Hamdan',   'noura.learner@dyafagroup.sa',   'HK',  'riyadh-business-tower', 'hk-senior-attendant'),
            array('Saud',    'Al Turki',    'saud.learner@dyafagroup.sa',    'FB',  'jeddah-corniche',       'fb-associate'),
            array('Jood',    'Al Amoudi',   'jood.learner@dyafagroup.sa',    'FB',  'jeddah-corniche',       'fb-captain'),
            array('Ibrahim', 'Al Sulaiman', 'ibrahim.learner@dyafagroup.sa', 'KIT', 'jeddah-corniche',       'kit-commis'),
            array('Dana',    'Al Najjar',   'dana.learner@dyafagroup.sa',    'GR',  'makkah-haram-view',     'gr-agent'),
            array('Meshal',  'Al Qurashi',  'meshal.learner@dyafagroup.sa',  'ENG', 'madinah-central',       'eng-technician'),
            array('Areej',   'Al Sahli',    'areej.learner@dyafagroup.sa',   'FO',  'al-khobar-waterfront',  'fo-associate'),
            array('Hassan',  'Al Zamil',    'hassan.learner@dyafagroup.sa',  'SEC', 'dammam-airport',        'sec-officer'),
            array('Wijdan',  'Al Khaldi',   'wijdan.learner@dyafagroup.sa',  'HK',  'alula-desert-resort',   'hk-room-attendant'),
        );
    }

    public function run($db) {
        $this->boot($db);
        $written = 0;

        $org_id = $this->upsert('ha_organization', array('slug' => 'dyafa-hospitality-group'), array(
            'name_en'       => 'Dyafa Hospitality Group',
            'name_ar'       => 'مجموعة ضيافة للفندقة',
            'legal_name'    => 'Dyafa Hospitality Group',
            'country'       => 'Saudi Arabia',
            'city'          => 'Riyadh',
            'contact_name'  => 'Faisal Al Harbi',
            'contact_email' => 'org.admin@dyafagroup.sa',
            'contact_phone' => '+966 11 000 0000',
            'locale'        => 'en',
            'timezone'      => 'Asia/Riyadh',
            'status'        => 'active',
        ));
        $written++;

        // Properties
        $property_ids = array();
        foreach (self::properties() as $p) {
            list($slug, $en, $ar, $city, $region, $type, $rooms, $opstatus) = $p;
            $property_ids[$slug] = $this->upsert('ha_property', array('slug' => $slug), array(
                'organization_id'    => $org_id,
                'name_en'            => $en,
                'name_ar'            => $ar,
                'brand'              => 'Dyafa',
                'property_type'      => $type,
                'city'               => $city,
                'region'             => $region,
                'country'            => 'Saudi Arabia',
                'room_count'         => $rooms,
                'operational_status' => $opstatus,
                'status'             => 'active',
            ));
            $written++;
        }

        // Departments are defined once at organization level and reused by every property.
        $department_ids = array();
        foreach (self::departments() as $d) {
            list($code, $en, $ar) = $d;
            $department_ids[$code] = $this->upsert('ha_department',
                array('organization_id' => $org_id, 'code' => $code, 'property_id' => null),
                array('name_en' => $en, 'name_ar' => $ar, 'status' => 'active'));
            $written++;
        }

        // Job roles
        $job_role_ids = array();
        foreach (self::job_roles() as $j) {
            list($code, $en, $ar, $dept, $level) = $j;
            $job_role_ids[$code] = $this->upsert('ha_job_role', array('code' => $code), array(
                'organization_id' => $org_id,
                'department_id'   => $dept ? $department_ids[$dept] : null,
                'title_en'        => $en,
                'title_ar'        => $ar,
                'level'           => $level,
                'status'          => 'active',
            ));
            $written++;
        }

        // Staff + learners
        $roles = array();
        foreach ($this->db->get('ha_role')->result_array() as $r) {
            $roles[$r['code']] = (int) $r['id'];
        }

        $seq = 1000;
        foreach (self::staff() as $s) {
            list($first, $last, $email, $role_code, $dept, $property) = $s;
            $user_id = $this->ensure_user($first, $last, $email, $role_code === 'instructor');
            $written++;
            $this->ensure_profile($user_id, array(
                'employee_no'     => 'DHG-' . (++$seq),
                'job_title_en'    => self::role_label($role_code),
                'organization_id' => $org_id,
                'property_id'     => $property ? $property_ids[$property] : null,
                'department_id'   => $dept ? $department_ids[$dept] : null,
                'job_role_id'     => null,
                'locale'          => 'en',
                'status'          => 'active',
            ));
            $this->ensure_role_grant($user_id, $roles[$role_code], $org_id,
                $property ? $property_ids[$property] : null,
                $dept ? $department_ids[$dept] : null);
        }

        foreach (self::learners() as $l) {
            list($first, $last, $email, $dept, $property, $job) = $l;
            $user_id = $this->ensure_user($first, $last, $email, false);
            $written++;
            $this->ensure_profile($user_id, array(
                'employee_no'     => 'DHG-' . (++$seq),
                'job_title_en'    => isset($job_role_ids[$job]) ? self::job_title($job) : null,
                'organization_id' => $org_id,
                'property_id'     => $property_ids[$property],
                'department_id'   => $department_ids[$dept],
                'job_role_id'     => isset($job_role_ids[$job]) ? $job_role_ids[$job] : null,
                'locale'          => 'en',
                'status'          => 'active',
            ));
            $this->ensure_role_grant($user_id, $roles['learner'], $org_id,
                $property_ids[$property], $department_ids[$dept]);
        }

        // There is always exactly one super admin account, with no tenant limits.
        $admin = $this->db->get_where('users', array('role_id' => 1))->row_array();
        if (!$admin) {
            $admin_id = $this->ensure_user('Academy', 'Admin', self::SUPER_ADMIN_EMAIL, true);
            $this->db->where('id', $admin_id)->update('users', array('role_id' => 1));
            $admin = $this->db->get_where('users', array('id' => $admin_id))->row_array();
            $written++;
        }
        if ($admin) {
            $this->ensure_profile((int) $admin['id'], array(
                'employee_no'     => 'HA-0001',
                'job_title_en'    => 'Super Admin',
                'job_title_ar'    => 'مدير النظام',
                'organization_id' => null,
                'locale'          => 'en',
                'status'          => 'active',
            ));
            $this->ensure_role_grant((int) $admin['id'], $roles['super_admin'], null, null, null);
        }

        // Resolve every manager id first: the query builder holds pending
        // conditions between calls, so a lookup inside an update() argument
        // would leak that update's WHERE clause into the lookup.
        $gm_riyadh = $this->user_id_by_email('gm.riyadh@dyafagroup.sa');
        $gm_jeddah = $this->user_id_by_email('gm.jeddah@dyafagroup.sa');
        $fom = $this->user_id_by_email('fom.riyadh@dyafagroup.sa');
        $hk  = $this->user_id_by_email('exechk.riyadh@dyafagroup.sa');
        $fbm = $this->user_id_by_email('fbm.jeddah@dyafagroup.sa');

        // Property managers own their property record.
        $this->db->where('slug', 'riyadh-business-tower')->update('ha_property', array('manager_user_id' => $gm_riyadh));
        $this->db->where('slug', 'jeddah-corniche')->update('ha_property', array('manager_user_id' => $gm_jeddah));

        // Department managers own their department record and manage their team.
        $this->db->where('id', $department_ids['FO'])->update('ha_department', array('head_user_id' => $fom));
        $this->db->where('id', $department_ids['HK'])->update('ha_department', array('head_user_id' => $hk));
        $this->db->where('id', $department_ids['FB'])->update('ha_department', array('head_user_id' => $fbm));

        $this->db->where('department_id', $department_ids['FO'])->where('manager_user_id IS NULL')
            ->update('ha_profile', array('manager_user_id' => $fom));
        $this->db->where('department_id', $department_ids['HK'])->where('manager_user_id IS NULL')
            ->update('ha_profile', array('manager_user_id' => $hk));
        $this->db->where('department_id', $department_ids['FB'])->where('manager_user_id IS NULL')
            ->update('ha_profile', array('manager_user_id' => $fbm));

        return $written;
    }

    private static function role_label($code) {
        $roles = Seed_rbac::roles();
        return isset($roles[$code]) ? $roles[$code]['en'] : $code;
    }

    private static function job_title($code) {
        foreach (self::job_roles() as $j) {
            if ($j[0] === $code) {
                return $j[1];
            }
        }
        return null;
    }

    private function user_id_by_email($email) {
        $row = $this->db->get_where('users', array('email' => $email))->row_array();
        return $row ? (int) $row['id'] : null;
    }

    private function ensure_user($first, $last, $email, $is_instructor) {
        $existing = $this->db->get_where('users', array('email' => $email))->row_array();
        $payload = array(
            'first_name'    => $first,
            'last_name'     => $last,
            'is_instructor' => $is_instructor ? 1 : 0,
            'status'        => 1,
            'role_id'       => 2,
            'last_modified' => time(),
        );
        if ($existing) {
            $this->db->where('id', $existing['id'])->update('users', $payload);
            return (int) $existing['id'];
        }
        $payload = array_merge($payload, array(
            'email'        => $email,
            'password'     => sha1(self::DEMO_PASSWORD),
            'skills'       => '[]',
            'payment_keys' => '[]',
            'sessions'     => '[]',
            'social_links' => json_encode(array('facebook' => '', 'twitter' => '', 'linkedin' => '')),
            'wishlist'     => '[]',
            'date_added'   => time(),
        ));
        $this->db->insert('users', $payload);
        return (int) $this->db->insert_id();
    }

    private function ensure_profile($user_id, array $values) {
        $values['user_id'] = $user_id;
        return $this->upsert('ha_profile', array('user_id' => $user_id), $values);
    }

    private function ensure_role_grant($user_id, $role_id, $org_id, $property_id, $department_id) {
        $match = array(
            'user_id'         => $user_id,
            'role_id'         => $role_id,
            'organization_id' => $org_id,
            'property_id'     => $property_id,
            'department_id'   => $department_id,
        );
        $this->link('ha_user_role', $match, array('created_at' => $this->now));
    }
}
