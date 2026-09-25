<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';
require_once APPPATH . 'seeds/002_organizations.php';

/**
 * altus Hospitality Knowledge & Performance foundation data.
 *
 * Safe on a production database: every row is matched by a natural key
 * (code, slug, email), existing curriculum and SOP text is never rewritten,
 * and only structure (domains, tracks, matrices, rules) and clearly labelled
 * demo records are added. No operational KPI value is invented: KPI
 * definitions are seeded, values are not.
 *
 * The demo tenant "Altus Demo Client / ALTUS Demo Hotel Riyadh" is the
 * ppt-features section 185 QA scenario and the second tenant the isolation
 * tests need. Demo passwords: Academy#2026.
 */
class Seed_hkp extends Ha_seeder {

    public static function domains() {
        return array(
            // code, EN, AR, group, icon
            array('hotel_fundamentals',   'Hotel Fundamentals',     'أساسيات الفندقة',          'core', 'building'),
            array('sales_marketing',      'Sales & Marketing',      'المبيعات والتسويق',        'core', 'megaphone'),
            array('front_office',         'Front Office',           'المكتب الأمامي',           'core', 'desk'),
            array('revenue_reservations', 'Revenue & Reservations', 'الإيرادات والحجوزات',      'core', 'chart'),
            array('housekeeping',         'Housekeeping',           'التدبير الفندقي',          'core', 'bed'),
            array('guest_experience',     'Guest Experience',       'تجربة الضيف',              'core', 'star'),
            array('food_beverage',        'Food & Beverage',        'الأغذية والمشروبات',       'core', 'cup'),
            array('quality_audit',        'Quality & Audit',        'الجودة والتدقيق',          'core', 'check'),
            array('kitchen',              'Kitchen',                'المطبخ',                   'core', 'chef'),
            array('security_safety',      'Security & Safety',      'الأمن والسلامة',           'core', 'shield'),
            array('esg_governance',       'Governance',             'الحوكمة',                  'esg', 'scale'),
            array('esg_community',        'Community & People',     'المجتمع والأفراد',         'esg', 'people'),
            array('esg_tourism',          'Responsible Tourism',    'السياحة المسؤولة',         'esg', 'leaf'),
            array('esg_sustainability',   'Operational Sustainability', 'الاستدامة التشغيلية',  'esg', 'energy'),
            array('leadership',           'Leadership & Capability', 'القيادة وبناء القدرات',   'leadership', 'people'),
            array('digital_analytics',    'Digital & Analytics',    'الرقمنة والتحليلات',       'leadership', 'chart'),
        );
    }

    /** Which domain a module belongs to: keyword rules on the code, then department. */
    public static function domain_for(array $course) {
        $code = $course['code'];
        $rules = array(
            'quality_audit' => '/quality|audit|mystery/', 'security_safety' => '/safety|fire|security|emergency|crisis|first-aid|key-control/',
            'guest_experience' => '/guest-experience|complaint|reputation|vip|recovery/', 'revenue_reservations' => '/revenue|reservation|pricing|kpi|direct-booking|distribution/',
            'sales_marketing' => '/sales|marketing|seo|upsell/', 'leadership' => '/leadership|team|management|supervis/', 'digital_analytics' => '/dig-technology|dig-pms|analytics/',
        );
        foreach ($rules as $d => $re) {
            if (preg_match($re, $code)) {
                return $d;
            }
        }
        $map = array('FO' => 'front_office', 'front-office' => 'front_office', 'GR' => 'guest_experience', 'HK' => 'housekeeping', 'LND' => 'housekeeping',
            'FB' => 'food_beverage', 'EVT' => 'food_beverage', 'KIT' => 'kitchen', 'PROC' => 'kitchen', 'SEC' => 'security_safety', 'ENG' => 'security_safety',
            'REV' => 'revenue_reservations', 'SLS' => 'sales_marketing', 'HR' => 'leadership', 'FIN' => 'hotel_fundamentals');
        return isset($map[$course['department_code']]) ? $map[$course['department_code']] : 'hotel_fundamentals';
    }

    public function run($db) {
        $this->boot($db);
        $n = 0;
        $n += $this->levels();
        $domains = $this->seed_domains();
        $n += count($domains);
        $n += $this->assign_modules($domains);
        $n += $this->port_quizbank($domains);
        $tracks = $this->tracks($domains);
        $n += count($tracks);
        $skills = $this->skills($domains);
        $rubrics = $this->rubrics($skills, $domains);
        $n += count($rubrics);
        $n += $this->knowledge_types($domains);
        $n += $this->kpis();
        $n += $this->frameworks();
        $n += $this->corporate();
        $n += $this->notifications();
        $n += $this->flags();
        $n += $this->branding();
        $demo = $this->demo_tenant($domains, $tracks, $skills, $rubrics);
        $n += $this->dyafa_matrices($tracks, $skills);
        $n += $demo;
        $this->finalise();
        return $n;
    }

    // ------------------------------------------------------------ structure

    private function levels() {
        $levels = array(
            array(1, 'awareness', 'Awareness', 'إلمام', 'Knows the standard exists and where to find it.', 'يعرف بوجود المعيار وأين يجده.'),
            array(2, 'developing', 'Developing', 'قيد التطوير', 'Performs with guidance; not yet consistent.', 'يؤدي بتوجيه ولم يصل إلى الثبات بعد.'),
            array(3, 'competent', 'Competent', 'كفء', 'Performs to standard independently.', 'يؤدي وفق المعيار باستقلالية.'),
            array(4, 'advanced', 'Advanced', 'متقدم', 'Exceeds the standard and handles exceptions.', 'يتجاوز المعيار ويتعامل مع الاستثناءات.'),
            array(5, 'expert', 'Expert', 'خبير', 'Coaches others and improves the standard.', 'يدرب الآخرين ويطور المعيار.'),
        );
        foreach ($levels as $l) {
            $this->upsert('ha_competency_level', array('organization_id' => 0, 'level_no' => $l[0]),
                array('code' => $l[1], 'name_en' => $l[2], 'name_ar' => $l[3], 'description_en' => $l[4], 'description_ar' => $l[5]));
        }
        return count($levels);
    }

    private function seed_domains() {
        $ids = array();
        foreach (self::domains() as $i => $d) {
            $ids[$d[0]] = $this->upsert('ha_domain', array('code' => $d[0]), array('name_en' => $d[1], 'name_ar' => $d[2],
                'domain_group' => $d[3], 'icon' => $d[4], 'is_core' => $d[3] === 'core' ? 1 : 0, 'sort_order' => $i + 1, 'status' => 'active'));
        }
        return $ids;
    }

    /** Sets domain_id on modules that have none. A domain chosen by an administrator is kept. */
    private function assign_modules(array $domains) {
        $n = 0;
        foreach ($this->db->select('id, code, department_code, domain_id')->get('ha_course')->result_array() as $c) {
            if ($c['domain_id']) {
                continue;
            }
            $this->db->where('id', $c['id'])->update('ha_course', array('domain_id' => $domains[self::domain_for($c)]));
            $n++;
        }
        return $n;
    }

    /**
     * Publishes the reviewed end-of-module question bank into the native
     * assessment engine. English is the authored language; Arabic is left
     * empty and flagged as untranslated rather than machine-translated.
     */
    private function port_quizbank(array $domains) {
        require_once APPPATH . 'libraries/Ha_quizbank.php';
        $bank = new Ha_quizbank();
        $n = 0;
        foreach ($bank->questions() as $course_code => $questions) {
            $course = $this->db->get_where('ha_course', array('code' => $course_code))->row_array();
            if (!$course) {
                continue;
            }
            $qb = $this->upsert('ha_question_bank', array('code' => 'qb-' . $course_code), array('name_en' => 'Question bank: ' . $course_code,
                'name_ar' => 'بنك أسئلة: ' . $course_code, 'department_code' => $course['department_code'], 'course_id' => $course['id'], 'status' => 'active'));
            $assessment = $this->db->get_where('ha_assessment', array('code' => 'as-' . $course_code))->row_array();
            $t = $this->db->get_where('ha_course_translation', array('course_id' => $course['id'], 'locale' => 'en'))->row_array();
            $ta = $this->db->get_where('ha_course_translation', array('course_id' => $course['id'], 'locale' => 'ar'))->row_array();
            $aid = $this->upsert('ha_assessment', array('code' => 'as-' . $course_code), array(
                'title_en' => 'Module assessment: ' . ($t ? $t['title'] : $course_code), 'title_ar' => 'تقييم الوحدة: ' . ($ta ? $ta['title'] : $course_code),
                'assessment_type' => 'quiz', 'course_id' => $course['id'], 'bank_id' => $qb, 'domain_id' => $course['domain_id'] ?: $domains['hotel_fundamentals'],
                'question_selection' => 'fixed', 'shuffle_options' => 1, 'max_attempts' => 3, 'pass_percentage' => 75,
                'show_correct_answers' => 1, 'status' => $assessment ? $assessment['status'] : 'published'));
            if ($this->db->where('assessment_id', $aid)->count_all_results('ha_assessment_question')) {
                continue;   // already ported; edits made in the platform are kept
            }
            foreach ($questions as $i => $q) {
                list($text, $options, $correct) = $q;
                $this->db->insert('ha_question', array('bank_id' => $qb, 'question_type' => count($options) === 2 ? 'true_false' : 'multiple_choice',
                    'body_en' => $text, 'body_ar' => '', 'marks' => 1, 'difficulty' => 'medium', 'status' => 'active',
                    'domain_id' => $course['domain_id'], 'created_at' => $this->now, 'updated_at' => $this->now));
                $qid = (int) $this->db->insert_id();
                foreach ($options as $j => $o) {
                    $this->db->insert('ha_question_option', array('question_id' => $qid, 'body_en' => $o, 'body_ar' => '', 'is_correct' => ($j + 1) === (int) $correct ? 1 : 0, 'sort_order' => $j));
                }
                $this->db->insert('ha_assessment_question', array('assessment_id' => $aid, 'question_id' => $qid, 'sort_order' => $i));
                $n++;
            }
        }
        return $n;
    }

    private function tracks(array $domains) {
        $ids = array();
        foreach (self::domains() as $i => $d) {
            if ($d[3] !== 'core' && $d[0] !== 'leadership') {
                continue;
            }
            $code = 'trk-' . str_replace('_', '-', $d[0]) . '-core';
            $ids[$d[0]] = $this->upsert('ha_track', array('code' => $code), array('domain_id' => $domains[$d[0]],
                'title_en' => $d[1] . ' — Core', 'title_ar' => $d[2] . ' — الأساسيات',
                'summary_en' => 'The Altus master track for ' . $d[1] . ': the standards every property applies first.',
                'summary_ar' => 'المسار الرئيسي من ألتوس في ' . $d[2] . ': المعايير التي يطبقها كل فندق أولاً.',
                'level' => 'foundation', 'sort_order' => $i, 'status' => 'published', 'published_at' => $this->now));
            if (!$this->db->where('track_id', $ids[$d[0]])->count_all_results('ha_track_module')) {
                $mods = $this->db->select('id')->where('domain_id', $domains[$d[0]])->where('organization_id IS NULL', null, false)
                    ->where('status', 'published')->order_by('code')->limit(6)->get('ha_course')->result_array();
                foreach ($mods as $k => $m) {
                    $this->db->insert('ha_track_module', array('track_id' => $ids[$d[0]], 'course_id' => $m['id'], 'sort_order' => $k, 'is_mandatory' => 1));
                }
            }
        }
        return $ids;
    }

    private function skills(array $domains) {
        $meta = array(
            // code => domain, criticality, method, required
            'guest-service' => array('front_office', 'medium', 'theory', 3), 'pms-operation' => array('front_office', 'medium', 'theory', 3),
            'complaint-handling' => array('guest_experience', 'high', 'theory_practical', 3), 'upselling' => array('sales_marketing', 'low', 'theory', 2),
            'night-audit' => array('front_office', 'medium', 'theory', 3), 'guest-data-privacy' => array('front_office', 'critical', 'theory', 3),
            'room-standards' => array('housekeeping', 'high', 'theory_practical', 3), 'chemical-safety' => array('housekeeping', 'critical', 'theory', 3),
            'food-service' => array('food_beverage', 'medium', 'theory_practical', 3), 'food-safety' => array('kitchen', 'critical', 'theory_practical', 3),
            'culinary-basics' => array('kitchen', 'medium', 'theory', 2), 'cost-control' => array('hotel_fundamentals', 'medium', 'theory', 2),
            'maintenance-basics' => array('security_safety', 'medium', 'theory', 2), 'occupational-safety' => array('security_safety', 'high', 'theory', 3),
            'emergency-response' => array('security_safety', 'critical', 'theory_practical', 3), 'team-supervision' => array('leadership', 'medium', 'theory', 3),
            'leadership' => array('leadership', 'medium', 'theory', 3), 'commercial-acumen' => array('revenue_reservations', 'medium', 'theory', 3),
            'revenue-management' => array('revenue_reservations', 'high', 'theory', 3), 'quality-audit' => array('quality_audit', 'medium', 'theory', 3),
        );
        $ids = array();
        foreach ($meta as $code => $m) {
            $row = $this->db->get_where('ha_skill', array('code' => $code))->row_array();
            if (!$row) {
                continue;
            }
            $upd = array();
            if (!$row['domain_id']) {
                $upd = array('domain_id' => $domains[$m[0]], 'criticality' => $m[1], 'assessment_method' => $m[2], 'default_required_level' => $m[3]);
                $this->db->where('id', $row['id'])->update('ha_skill', $upd);
            }
            $ids[$code] = (int) $row['id'];
        }
        $ids['guest-check-in'] = $this->upsert('ha_skill', array('code' => 'guest-check-in'), array('name_en' => 'Guest Check-in', 'name_ar' => 'تسجيل وصول الضيف',
            'description_en' => 'Welcomes, verifies, registers and orients an arriving guest to property standard.',
            'description_ar' => 'يرحب بالضيف القادم ويتحقق من هويته ويسجله ويعرّفه بالفندق وفق معيار الفندق.',
            'department_code' => 'FO', 'domain_id' => $domains['front_office'], 'criticality' => 'critical', 'assessment_method' => 'theory_practical',
            'evidence_type' => 'Practical observation at the desk', 'default_required_level' => 3, 'status' => 'active'));
        // Content -> competency links (section 170).
        $links = array('fo-check-in' => array('guest-check-in', 2), 'fo-guest-registration' => array('guest-check-in', 2), 'fo-complaints' => array('complaint-handling', 2),
            'fo-fundamentals' => array('guest-service', 3), 'fo-guest-privacy' => array('guest-data-privacy', 3), 'fo-upselling' => array('upselling', 2));
        foreach ($links as $course_code => $l) {
            $c = $this->db->get_where('ha_course', array('code' => $course_code))->row_array();
            $a = $this->db->get_where('ha_assessment', array('code' => 'as-' . $course_code))->row_array();
            if ($c && isset($ids[$l[0]])) {
                $this->link('ha_course_skill', array('course_id' => $c['id'], 'skill_id' => $ids[$l[0]]), array('awards_level' => 'basic'));
            }
            if ($a && isset($ids[$l[0]])) {
                $this->link('ha_assessment_competency', array('assessment_id' => $a['id'], 'skill_id' => $ids[$l[0]]), array('level_on_pass' => $l[1]));
            }
        }
        return $ids;
    }

    private function rubrics(array $skills, array $domains) {
        $defs = array(
            'rub-fo-check-in' => array('Guest Check-in Competency', 'كفاءة تسجيل وصول الضيف', 'guest-check-in', 'front_office', array(
                array('Greeting', 'الترحيب', 10, 0), array('Identity verification', 'التحقق من الهوية', 15, 1), array('Reservation confirmation', 'تأكيد الحجز', 15, 0),
                array('System transaction', 'الإدخال في النظام', 20, 0), array('Service explanation', 'شرح الخدمات', 15, 0), array('Payment process', 'إجراءات الدفع', 10, 1),
                array('Communication', 'التواصل', 10, 0), array('Closing interaction', 'ختام التعامل', 5, 0))),
            'rub-complaint-handling' => array('Complaint Handling & Service Recovery', 'التعامل مع الشكاوى واستعادة الخدمة', 'complaint-handling', 'guest_experience', array(
                array('Listens without interrupting', 'يستمع دون مقاطعة', 15, 0), array('Acknowledges and apologises sincerely', 'يُقر ويعتذر بصدق', 15, 0),
                array('Clarifies the facts', 'يستوضح الوقائع', 15, 0), array('Offers a solution within authority', 'يقدم حلاً ضمن صلاحياته', 20, 1),
                array('Escalates correctly when needed', 'يصعّد بشكل صحيح عند الحاجة', 10, 0), array('Records the case', 'يسجل الحالة', 10, 0),
                array('Follows up with the guest', 'يتابع مع الضيف', 15, 0))),
            'rub-hk-room-inspection' => array('Guest Room Preparation', 'تجهيز غرفة الضيف', 'room-standards', 'housekeeping', array(
                array('Bed made to standard', 'ترتيب السرير وفق المعيار', 20, 0), array('Bathroom sanitised', 'تعقيم الحمام', 25, 1), array('Amenities complete', 'اكتمال المستلزمات', 15, 0),
                array('Dust-free surfaces', 'أسطح خالية من الغبار', 15, 0), array('Room status updated', 'تحديث حالة الغرفة', 10, 0), array('Chemicals used safely', 'استخدام المواد الكيميائية بأمان', 15, 1))),
            'rub-kit-food-safety' => array('Food Safety Practice', 'ممارسات سلامة الغذاء', 'food-safety', 'kitchen', array(
                array('Hand washing and hygiene', 'غسل اليدين والنظافة', 20, 1), array('Temperature control and logs', 'ضبط درجات الحرارة وسجلاتها', 25, 1),
                array('Cross-contamination controls', 'منع التلوث الخلطي', 25, 1), array('Labelling and dating', 'الملصقات والتواريخ', 15, 0), array('Allergen awareness', 'الوعي بمسببات الحساسية', 15, 0))),
            'rub-sec-emergency' => array('Emergency Response Drill', 'تمرين الاستجابة للطوارئ', 'emergency-response', 'security_safety', array(
                array('Raises the alarm correctly', 'يطلق الإنذار بشكل صحيح', 20, 1), array('Follows evacuation route', 'يتبع مسار الإخلاء', 20, 1),
                array('Assists guests', 'يساعد الضيوف', 20, 0), array('Reports at assembly point', 'يبلغ عند نقطة التجمع', 20, 0), array('Communicates clearly', 'يتواصل بوضوح', 20, 0))),
        );
        $ids = array();
        foreach ($defs as $code => $d) {
            if (!isset($skills[$d[2]])) {
                continue;
            }
            $ids[$code] = $this->upsert('ha_rubric', array('code' => $code), array('title_en' => $d[0], 'title_ar' => $d[1], 'skill_id' => $skills[$d[2]],
                'domain_id' => $domains[$d[3]], 'developing_threshold' => 50, 'pass_threshold' => 75, 'exceeds_threshold' => 90, 'status' => 'published',
                'description_en' => 'Observed on shift by a supervisor. Weights and thresholds are configurable.',
                'description_ar' => 'يُلاحظ أثناء العمل من قبل المشرف. الأوزان والحدود قابلة للتعديل.'));
            if (!$this->db->where('rubric_id', $ids[$code])->count_all_results('ha_rubric_criterion')) {
                foreach ($d[4] as $i => $c) {
                    $this->db->insert('ha_rubric_criterion', array('rubric_id' => $ids[$code], 'label_en' => $c[0], 'label_ar' => $c[1], 'weight' => $c[2], 'is_critical' => $c[3], 'sort_order' => $i));
                }
            }
        }
        return $ids;
    }

    /** Existing SOPs become governed knowledge items with a domain. Their text is untouched. */
    private function knowledge_types(array $domains) {
        $map = array('fo' => 'front_office', 'hk' => 'housekeeping', 'kit' => 'kitchen', 'eng' => 'security_safety', 'sec' => 'security_safety',
            'fb' => 'food_beverage', 'hr' => 'leadership', 'fin' => 'hotel_fundamentals', 'pre' => 'quality_audit', 'proc' => 'kitchen');
        $n = 0;
        foreach ($this->db->select('id, code, domain_id')->get('ha_sop_document')->result_array() as $d) {
            if ($d['domain_id']) {
                continue;
            }
            $parts = explode('-', $d['code']);
            $key = isset($parts[1]) ? $parts[1] : '';
            $this->db->where('id', $d['id'])->update('ha_sop_document', array('domain_id' => isset($map[$key]) ? $domains[$map[$key]] : $domains['hotel_fundamentals']));
            $n++;
        }
        return $n;
    }

    private function kpis() {
        $defs = array(
            // code, EN, AR, category, unit, direction, freq, source, value_stack, esg, definition
            array('revpar', 'RevPAR', 'الإيراد لكل غرفة متاحة', 'revenue', 'SAR', 'higher_better', 'monthly', 'pms', 'top_line', null, 'Rooms revenue divided by available room nights.'),
            array('adr', 'Average Daily Rate', 'متوسط السعر اليومي', 'revenue', 'SAR', 'higher_better', 'monthly', 'pms', 'top_line', null, 'Rooms revenue divided by occupied room nights.'),
            array('occupancy', 'Occupancy', 'نسبة الإشغال', 'revenue', '%', 'higher_better', 'monthly', 'pms', 'top_line', null, 'Occupied room nights divided by available room nights.'),
            array('goppar', 'GOPPAR', 'إجمالي الربح التشغيلي لكل غرفة متاحة', 'revenue', 'SAR', 'higher_better', 'monthly', 'bi', 'asset', null, 'Gross operating profit divided by available room nights.'),
            array('pickup', 'Pickup', 'معدل الحجوزات الجديدة', 'revenue', 'rooms', 'higher_better', 'weekly', 'pms', 'top_line', null, 'Room nights booked for a future date during the reporting window.'),
            array('direct_share', 'Direct booking share', 'نسبة الحجز المباشر', 'revenue', '%', 'higher_better', 'monthly', 'pms', 'distribution', null, 'Share of room nights booked through direct channels.'),
            array('ota_cost', 'OTA commission cost', 'تكلفة عمولات وكالات السفر', 'cost', 'SAR', 'lower_better', 'monthly', 'bi', 'distribution', null, 'Commission paid to online travel agencies.'),
            array('guest_satisfaction', 'Guest satisfaction', 'رضا الضيوف', 'guest', 'score', 'higher_better', 'monthly', 'api', null, null, 'Survey-based satisfaction index for the period.'),
            array('guest_sentiment', 'Guest sentiment', 'انطباع الضيوف', 'guest', 'score', 'higher_better', 'monthly', 'api', null, null, 'Net sentiment across reviews for the period.'),
            array('fb_revenue', 'F&B revenue', 'إيرادات الأغذية والمشروبات', 'revenue', 'SAR', 'higher_better', 'monthly', 'pos', 'top_line', null, 'Food and beverage revenue.'),
            array('payroll_productivity', 'Payroll productivity', 'إنتاجية الرواتب', 'people', 'SAR/hr', 'higher_better', 'monthly', 'bi', 'cost', null, 'Total revenue per paid labour hour.'),
            array('cpor', 'Cost per occupied room', 'تكلفة الغرفة المشغولة', 'cost', 'SAR', 'lower_better', 'monthly', 'bi', 'cost', null, 'Rooms department cost divided by occupied room nights.'),
            array('energy_por', 'Energy per occupied room', 'الطاقة لكل غرفة مشغولة', 'sustainability', 'kWh', 'lower_better', 'monthly', 'manual', 'asset', 'sustainability', 'Electricity consumed divided by occupied room nights.'),
            array('water_pgn', 'Water per guest night', 'المياه لكل ليلة ضيف', 'sustainability', 'L', 'lower_better', 'monthly', 'manual', 'asset', 'sustainability', 'Water consumed divided by guest nights.'),
            array('waste_diverted', 'Waste diverted', 'النفايات المُعاد تدويرها', 'sustainability', '%', 'higher_better', 'monthly', 'manual', null, 'sustainability', 'Share of waste recycled or recovered.'),
            array('saudization', 'Saudization rate', 'نسبة التوطين', 'people', '%', 'higher_better', 'quarterly', 'manual', null, 'community', 'Saudi nationals as a share of total headcount.'),
            array('audit_score', 'Quality audit score', 'درجة تدقيق الجودة', 'quality', '%', 'higher_better', 'monthly', 'platform', null, 'governance', 'Share of audited requirements found compliant.'),
        );
        foreach ($defs as $d) {
            $this->upsert('ha_kpi', array('code' => $d[0]), array('name_en' => $d[1], 'name_ar' => $d[2], 'category' => $d[3], 'unit' => $d[4], 'direction' => $d[5],
                'frequency' => $d[6], 'source' => $d[7], 'value_stack' => $d[8], 'esg_category' => $d[9], 'definition_en' => $d[10], 'status' => 'active'));
        }
        return count($defs);
    }

    private function frameworks() {
        $provisional = 'Provisional default set by the platform, not Altus methodology. Altus to confirm.';
        $fw = array(
            'performance_matrix' => array('Altus Performance Matrix™', 'مصفوفة ألتوس للأداء™', array('axis_threshold' => 3, 'note' => $provisional), array(
                array('op_sop', 'SOP maturity', 'نضج الإجراءات', 'operational', 'Operating procedures are written, current and followed on shift.', 'الإجراءات مكتوبة ومحدثة ومتبعة أثناء العمل.'),
                array('op_service', 'Service standards', 'معايير الخدمة', 'operational', 'Service standards are defined and measured.', 'معايير الخدمة محددة ومقاسة.'),
                array('op_quality', 'Quality control', 'ضبط الجودة', 'operational', 'Quality is audited and findings are closed.', 'تُدقق الجودة وتُغلق الملاحظات.'),
                array('op_cost', 'Cost control', 'ضبط التكاليف', 'operational', 'Costs are budgeted, tracked and contained.', 'التكاليف موازنة ومتابعة ومضبوطة.'),
                array('op_discipline', 'Management discipline', 'الانضباط الإداري', 'operational', 'Managers run daily routines and hold teams to account.', 'يدير المديرون الروتين اليومي ويحاسبون الفرق.'),
                array('op_consistency', 'Operational consistency', 'ثبات التشغيل', 'operational', 'Guests get the same standard every shift.', 'يحصل الضيوف على المعيار ذاته في كل وردية.'),
                array('dg_data', 'Data', 'البيانات', 'digital', 'Operational data is captured and trusted.', 'تُجمع البيانات التشغيلية ويُعتمد عليها.'),
                array('dg_revenue', 'Revenue management', 'إدارة الإيرادات', 'digital', 'Pricing responds to demand using a system and forecast.', 'يستجيب التسعير للطلب عبر نظام وتوقعات.'),
                array('dg_distribution', 'Distribution', 'التوزيع', 'digital', 'Channel mix is managed for cost and reach.', 'يُدار مزيج القنوات لتحقيق التكلفة والانتشار.'),
                array('dg_crm', 'CRM', 'إدارة علاقات العملاء', 'digital', 'Guest data drives marketing and service.', 'توجه بيانات الضيوف التسويق والخدمة.'),
                array('dg_analytics', 'Analytics', 'التحليلات', 'digital', 'Dashboards inform weekly decisions.', 'توجه لوحات المعلومات القرارات الأسبوعية.'),
                array('dg_ai', 'AI', 'الذكاء الاصطناعي', 'digital', 'AI is used where it measurably helps.', 'يُستخدم الذكاء الاصطناعي حيث يفيد بشكل قابل للقياس.'),
                array('dg_infra', 'Digital infrastructure', 'البنية الرقمية', 'digital', 'Systems are integrated, secure and supported.', 'الأنظمة متكاملة وآمنة ومدعومة.'))),
            'goppar_value_stack' => array('GOPPAR Value Stack™', 'هرم قيمة إجمالي الربح التشغيلي™', array('bands' => array(array('min' => 0, 'label' => 'foundational'), array('min' => 2.5, 'label' => 'developing'), array('min' => 3.5, 'label' => 'established'), array('min' => 4.5, 'label' => 'leading')), 'note' => $provisional), array(
                array('top_line', 'Top-Line Capture', 'تعظيم الإيرادات', null, 'Dynamic pricing, predictive yield and total revenue systems are in place.', 'التسعير الديناميكي والعائد التنبؤي وأنظمة الإيرادات الشاملة مطبقة.'),
                array('distribution', 'Distribution Economics', 'اقتصاديات التوزيع', null, 'Channel mix, OTA governance and direct booking growth are managed.', 'يُدار مزيج القنوات وحوكمة وكالات السفر ونمو الحجز المباشر.'),
                array('cost', 'Cost Containment', 'احتواء التكاليف', null, 'Cost frameworks, procurement discipline and productivity are managed.', 'تُدار أطر التكلفة وانضباط المشتريات والإنتاجية.'),
                array('asset', 'Asset Productivity', 'إنتاجية الأصل', null, 'Space monetisation, capex governance, energy and lifecycle efficiency are managed.', 'يُدار استثمار المساحات وحوكمة النفقات الرأسمالية والطاقة وكفاءة دورة الحياة.'))),
            'esg' => array('ESG & Sustainability', 'الحوكمة البيئية والاجتماعية', array('bands' => array(array('min' => 0, 'label' => 'foundational'), array('min' => 2.5, 'label' => 'developing'), array('min' => 3.5, 'label' => 'established'), array('min' => 4.5, 'label' => 'leading')), 'note' => $provisional), array(
                array('governance', 'Governance', 'الحوكمة', null, 'Owner reporting, HMA and procurement integrity, anti-leakage controls and assurance.', 'تقارير الملاك ونزاهة اتفاقيات الإدارة والمشتريات وضوابط منع التسرب والتأكيد.'),
                array('community', 'Community & People', 'المجتمع والأفراد', null, 'Saudization-first talent, local suppliers, fair workplaces and knowledge transfer.', 'التوطين أولاً والموردون المحليون وبيئات العمل العادلة ونقل المعرفة.'),
                array('tourism', 'Responsible Tourism', 'السياحة المسؤولة', null, 'Destination stewardship, visitor-impact management and heritage protection.', 'رعاية الوجهة وإدارة أثر الزوار وحماية التراث.'),
                array('sustainability', 'Operational Sustainability', 'الاستدامة التشغيلية', null, 'Energy, water and waste efficiency, utility intelligence and lifecycle costing.', 'كفاءة الطاقة والمياه والنفايات وذكاء المرافق وتكلفة دورة الحياة.'))),
            'capability_model' => array('Institutional Capabilities', 'القدرات المؤسسية', array('bands' => array(array('min' => 0, 'label' => 'foundational'), array('min' => 2.5, 'label' => 'developing'), array('min' => 3.5, 'label' => 'established'), array('min' => 4.5, 'label' => 'leading')), 'note' => $provisional), array(
                array('operational', 'Operational', 'تشغيلية', null, 'SOP architecture, quality systems, pre-opening readiness.', 'بنية الإجراءات وأنظمة الجودة وجاهزية ما قبل الافتتاح.'),
                array('financial', 'Financial', 'مالية', null, 'P&L restructuring, cost containment, forecasting, capex governance.', 'إعادة هيكلة الأرباح والخسائر واحتواء التكاليف والتنبؤ وحوكمة النفقات الرأسمالية.'),
                array('digital_ai', 'Digital & AI', 'الرقمنة والذكاء الاصطناعي', null, 'AI readiness, dynamic pricing, analytics, CRM and technology stack.', 'الجاهزية للذكاء الاصطناعي والتسعير الديناميكي والتحليلات وإدارة العملاء والتقنية.'),
                array('governance', 'Governance', 'الحوكمة', null, 'HMA oversight, owner-operator alignment, board-level assurance.', 'الإشراف على اتفاقيات الإدارة ومواءمة المالك والمشغل والتأكيد على مستوى المجلس.'),
                array('commercial', 'Commercial', 'تجارية', null, 'Revenue strategy, sales effectiveness, channel economics.', 'استراتيجية الإيرادات وفعالية المبيعات واقتصاديات القنوات.'),
                array('investment', 'Investment', 'استثمارية', null, 'Feasibility, underwriting, due diligence and valuation.', 'الجدوى والاكتتاب والعناية الواجبة والتقييم.'),
                array('organisational', 'Organisational', 'تنظيمية', null, 'Structure, role clarity, succession, leadership coaching.', 'الهيكل ووضوح الأدوار والتعاقب وتدريب القادة.'),
                array('transformation', 'Transformation', 'التحول', null, 'Turnaround, change protocols, post-merger integration.', 'التعافي وبروتوكولات التغيير والدمج بعد الاستحواذ.'))),
        );
        $n = 0;
        foreach ($fw as $code => $f) {
            $fid = $this->upsert('ha_framework', array('code' => $code), array('name_en' => $f[0], 'name_ar' => $f[1], 'scale_max' => 5,
                'config_json' => json_encode($f[2], JSON_UNESCAPED_UNICODE), 'status' => 'active'));
            foreach ($f[3] as $i => $d) {
                $did = $this->db->get_where('ha_framework_dimension', array('framework_id' => $fid, 'code' => $d[0]))->row_array();
                if ($did) {
                    continue;
                }
                $this->db->insert('ha_framework_dimension', array('framework_id' => $fid, 'code' => $d[0], 'name_en' => $d[1], 'name_ar' => $d[2], 'axis' => $d[3], 'sort_order' => $i));
                $dim = (int) $this->db->insert_id();
                $this->db->insert('ha_framework_question', array('dimension_id' => $dim, 'prompt_en' => $d[4], 'prompt_ar' => $d[5], 'weight' => 1, 'sort_order' => 0, 'status' => 'active'));
                $n++;
            }
        }
        return $n;
    }

    /** Corporate content from the 2026 corporate profile, editable in the CMS (sections 44, 46). */
    private function corporate() {
        $b = array(
            array('promise', 'platform', 'The product promise', 'وعد المنتج',
                'The right knowledge, to the right person, at the right time, with clear evidence of learning and improvement.',
                'المعرفة الصحيحة، للشخص المناسب، في الوقت المناسب، مع دليل واضح على التعلم والتحسن.'),
            array('positioning', 'platform', 'From advice to capability', 'من المشورة إلى القدرة',
                'From advice that ends at the report, to capability that lives inside the client\'s own institution.',
                'من مشورة تنتهي عند التقرير، إلى قدرة تعيش داخل مؤسسة العميل نفسها.'),
            array('founders_message', 'about', 'A Message from the Founders', 'رسالة المؤسسين',
                "We founded Altus Advisory to close a structural gap in the market. Asset owners and senior leadership teams are too often forced to choose between conventional hospitality operators' consultants and standalone digital agencies. Altus occupies the precise, multidisciplinary intersection of the two.\n\nOur promise is simple. We stand beside you, shoulder to shoulder, from the first blueprint through sustained execution, and beyond it, until your organisation reaches full operational consistency, stability, and confidence. The engagement may end; the alliance does not.",
                "أسسنا ألتوس للاستشارات لسد فجوة هيكلية في السوق. كثيراً ما يُضطر ملاك الأصول وفرق القيادة إلى الاختيار بين مستشاري المشغلين التقليديين والوكالات الرقمية المستقلة. تقع ألتوس في نقطة التقاء الاثنين تماماً.\n\nوعدنا بسيط: نقف إلى جانبكم كتفاً بكتف من المخطط الأول حتى التنفيذ المستدام وما بعده، إلى أن تبلغ مؤسستكم الثبات التشغيلي والاستقرار والثقة الكاملة. قد ينتهي التكليف، لكن الشراكة لا تنتهي."),
            array('company_overview', 'about', 'Company Overview', 'نبذة عن الشركة',
                'Altus Advisory is a specialised strategy consultancy built on rigorous international standards. We operate where elite hospitality operations meet advanced business intelligence, serving asset owners, investors, and ambitious enterprises across Saudi Arabia, the GCC, and global markets.',
                'ألتوس للاستشارات شركة استشارات استراتيجية متخصصة قائمة على معايير دولية صارمة. نعمل حيث تلتقي العمليات الفندقية الرفيعة بذكاء الأعمال المتقدم، ونخدم ملاك الأصول والمستثمرين والمؤسسات الطموحة في المملكة العربية السعودية ودول الخليج والأسواق العالمية.'),
            array('vision', 'about', 'Our Vision', 'رؤيتنا',
                'To be the most trusted strategic catalyst for hospitality excellence and enterprise transformation.',
                'أن نكون المحفز الاستراتيجي الأكثر ثقة للتميز الفندقي وتحول المؤسسات.'),
            array('mission', 'about', 'Our Mission', 'رسالتنا',
                'To empower asset owners, investors, and business leaders with executable strategy, advanced technology infrastructure, and human-capital frameworks.',
                'تمكين ملاك الأصول والمستثمرين وقادة الأعمال باستراتيجية قابلة للتنفيذ وبنية تقنية متقدمة وأطر لرأس المال البشري.'),
            array('purpose', 'about', 'Our Purpose', 'غايتنا',
                'To champion start-ups, scaling enterprises, and every investor or entrepreneur with a bold ambition, acting as the trusted strategic fiduciary that converts aspiration into tangible, high-yield reality.',
                'مناصرة الشركات الناشئة والمؤسسات النامية وكل مستثمر أو رائد أعمال صاحب طموح جريء، بصفتنا الأمين الاستراتيجي الذي يحول الطموح إلى واقع ملموس عالي العائد.'),
            array('philosophy_fiduciary', 'philosophy', 'Fiduciary First', 'الأمانة أولاً',
                'We sit on the owner\'s side of the table: independent, accountable, and measured against your returns, not our billable hours.',
                'نجلس في جانب المالك من الطاولة: مستقلون ومسؤولون ونُقاس بعوائدكم لا بساعات عملنا.'),
            array('philosophy_evidence', 'philosophy', 'Evidence over Intuition', 'الدليل قبل الحدس',
                'Every recommendation is grounded in empirical analytics, financial modelling, and field-tested operating discipline.',
                'كل توصية مبنية على التحليلات التجريبية والنمذجة المالية والانضباط التشغيلي المجرب ميدانياً.'),
            array('philosophy_partnership', 'philosophy', 'Partnership beyond the Mandate', 'شراكة تتجاوز التكليف',
                'Engagements evolve into enduring alliances. We remain invested in our clients\' trajectory long after delivery.',
                'تتحول التكليفات إلى تحالفات دائمة، ونبقى مهتمين بمسار عملائنا بعد التسليم بوقت طويل.'),
            array('independence', 'about', 'The Independence Principle', 'مبدأ الاستقلالية',
                'We hold no equity in operators, take no vendor commissions, and carry no brand allegiance. Our partnerships exist to serve one interest only: the client\'s.',
                'لا نملك حصصاً في المشغلين، ولا نتقاضى عمولات من الموردين، ولا ولاء لنا لأي علامة. شراكاتنا قائمة لخدمة مصلحة واحدة: مصلحة العميل.'),
            array('value_integrity', 'values', 'Integrity', 'النزاهة', 'We act as true fiduciaries of client assets: realistic diagnostics, transparent ROI reporting, and advice that serves your interest.', 'نعمل أمناء حقيقيين على أصول العملاء: تشخيص واقعي وتقارير شفافة للعائد ومشورة تخدم مصلحتكم.'),
            array('value_excellence', 'values', 'Excellence', 'التميز', 'Uncompromising standards: a refusal of mediocrity through obsessive attention to execution detail.', 'معايير لا تهاون فيها: رفض للمستوى المتوسط عبر اهتمام دقيق بتفاصيل التنفيذ.'),
            array('value_innovation', 'values', 'Innovation', 'الابتكار', 'Agile pursuit of digital disruption: seeking, testing, and integrating new capability as competitive advantage.', 'سعي مرن نحو التحول الرقمي: البحث عن القدرات الجديدة واختبارها ودمجها ميزةً تنافسية.'),
            array('value_hospitality', 'values', 'Hospitality', 'الضيافة', 'Service is our native language. The guest\'s experience, and the owner\'s return on it, sit at the centre of every decision.', 'الخدمة لغتنا الأم. تجربة الضيف وعائد المالك منها في قلب كل قرار.'),
            array('value_performance', 'values', 'Performance', 'الأداء', 'Data-driven precision: intuition deliberately replaced by empirical analytics, AI, and financial modelling.', 'دقة قائمة على البيانات: استبدال متعمد للحدس بالتحليلات والذكاء الاصطناعي والنمذجة المالية.'),
            array('value_trust', 'values', 'Trust', 'الثقة', 'Earned through candour, confidentiality, and consistency.', 'تُكتسب بالصراحة والسرية والثبات.'),
            array('value_collaboration', 'values', 'Collaboration', 'التعاون', 'We build alongside client teams and local talent, transferring capability rather than creating dependency.', 'نبني مع فرق العملاء والكفاءات المحلية، وننقل القدرة بدلاً من خلق الاعتماد.'),
            array('v2030_tourism', 'vision2030', 'Powering the Tourism & Hospitality Surge', 'دعم الطفرة السياحية والفندقية', 'Owner representation, feasibility validation, and operational optimisation that deliver new hotel assets on time, on budget, and engineered for GOP from day one.', 'تمثيل الملاك والتحقق من الجدوى وتحسين العمليات لتسليم الأصول الفندقية في موعدها وضمن ميزانيتها ومصممة للربحية من اليوم الأول.'),
            array('v2030_digital', 'vision2030', 'Advancing Digital & AI Leadership', 'تعزيز الريادة الرقمية والذكاء الاصطناعي', 'The platform replaces paper-based procedures with a governed digital system that embeds transparency and continuous improvement.', 'تستبدل المنصة الإجراءات الورقية بنظام رقمي محوكم يرسخ الشفافية والتحسين المستمر.'),
            array('v2030_people', 'vision2030', 'Empowering Saudi Human Capital', 'تمكين رأس المال البشري السعودي', 'Coaching, leadership-competency frameworks and knowledge transfer, delivered at scale through the bilingual digital academy, learning paths and certification.', 'التدريب وأطر كفاءات القيادة ونقل المعرفة، تُقدم على نطاق واسع عبر الأكاديمية الرقمية ثنائية اللغة والمسارات التعليمية والشهادات.'),
            array('v2030_sme', 'vision2030', 'Enabling SMEs, Start-ups & Bold Entrepreneurs', 'تمكين المنشآت الصغيرة والمتوسطة ورواد الأعمال', 'Tier-1 consulting standards brought to Saudi start-ups and scale-ups, with the platform priced deliberately for independent hotels and SME establishments.', 'معايير استشارية من الفئة الأولى للشركات السعودية الناشئة والنامية، مع منصة مسعّرة خصيصاً للفنادق المستقلة والمنشآت الصغيرة والمتوسطة.'),
            array('market_opportunity', 'market', 'Market Opportunity', 'فرصة السوق', "122M domestic and international visits in 2025 (+5% YoY); SAR 300B total tourism spending in 2025; 362K projected hotel keys by 2030 (from ~167.5K); 78% of pipeline in luxury, upscale and upper-upscale.\nSources: Saudi Ministry of Tourism preliminary data (Jan 2026); Knight Frank, Saudi Arabia Hospitality Market Review 2025; Ministry of Hajj & Umrah, 2024.", "122 مليون زيارة محلية ودولية في 2025 (+5% سنوياً)؛ 300 مليار ريال إجمالي الإنفاق السياحي في 2025؛ 362 ألف غرفة فندقية متوقعة بحلول 2030 (من نحو 167.5 ألف)؛ 78% من المشاريع في الفئات الفاخرة والراقية.\nالمصادر: البيانات الأولية لوزارة السياحة (يناير 2026)؛ نايت فرانك، مراجعة سوق الضيافة السعودي 2025؛ وزارة الحج والعمرة 2024."),
            array('contact', 'contact', 'Contact', 'تواصل معنا', "Riyadh, Kingdom of Saudi Arabia — [Office address]\nwww.altusadvisory.com [placeholder]\nadvisory@altusadvisory.com [placeholder]", "الرياض، المملكة العربية السعودية — [عنوان المكتب]\nwww.altusadvisory.com [مؤقت]\nadvisory@altusadvisory.com [مؤقت]"),
        );
        foreach ($b as $i => $r) {
            if ($this->db->where('code', $r[0])->count_all_results('ha_corporate_block')) {
                continue;   // edited content is never overwritten
            }
            $this->db->insert('ha_corporate_block', array('code' => $r[0], 'section' => $r[1], 'title_en' => $r[2], 'title_ar' => $r[3], 'body_en' => $r[4], 'body_ar' => $r[5],
                'sort_order' => $i, 'visibility' => $r[0] === 'contact' ? 'internal' : 'public', 'status' => 'published', 'created_at' => $this->now, 'updated_at' => $this->now));
        }
        $sectors = array(array('hotels', 'Hotels', 'الفنادق'), array('real_estate', 'Real Estate', 'العقارات'), array('entertainment', 'Entertainment', 'الترفيه'),
            array('sports_events', 'Sports & Events', 'الرياضة والفعاليات'), array('luxury_resorts', 'Luxury Resorts', 'المنتجعات الفاخرة'), array('government', 'Government & PSAs', 'الجهات الحكومية'),
            array('healthcare_hospitality', 'Healthcare Hospitality', 'الضيافة الصحية'), array('investment_pe', 'Investment & PE', 'الاستثمار والملكية الخاصة'),
            array('tourism_destinations', 'Tourism & Destinations', 'السياحة والوجهات'), array('mixed_use', 'Mixed-Use Developments', 'المشاريع متعددة الاستخدامات'),
            array('retail_fb', 'Retail & F&B', 'التجزئة والأغذية والمشروبات'), array('family_offices', 'Family Offices', 'المكاتب العائلية'));
        foreach ($sectors as $i => $s) {
            $this->upsert('ha_sector', array('code' => $s[0]), array('name_en' => $s[1], 'name_ar' => $s[2], 'sort_order' => $i, 'status' => 'active'));
        }
        $services = array(
            array('owner_representation', 'hospitality', 'Hotel Development & Owner Representation', 'تطوير الفنادق وتمثيل الملاك'),
            array('pre_opening', 'hospitality', 'Operations Optimisation & Pre-Opening Support', 'تحسين العمليات ودعم ما قبل الافتتاح'),
            array('feasibility', 'hospitality', 'Feasibility Studies & Investment Validation', 'دراسات الجدوى والتحقق من الاستثمار'),
            array('quality_audits', 'hospitality', 'Guest Experience Enhancement & Quality Audits', 'تحسين تجربة الضيف وتدقيق الجودة'),
            array('commercial', 'hospitality', 'Commercial Performance Improvement', 'تحسين الأداء التجاري'),
            array('strategic_planning', 'business_growth', 'Strategic Planning & Organisational Restructuring', 'التخطيط الاستراتيجي وإعادة الهيكلة'),
            array('revenue_ai', 'business_growth', 'Revenue Optimisation & Applied AI', 'تحسين الإيرادات والذكاء الاصطناعي التطبيقي'),
            array('digital_transformation', 'business_growth', 'Digital Transformation', 'التحول الرقمي'),
            array('leadership', 'business_growth', 'Leadership & Capability Development', 'تطوير القيادة والقدرات'),
            array('due_diligence', 'business_growth', 'Investment Appraisal & M&A Due Diligence', 'تقييم الاستثمار والعناية الواجبة للاندماج والاستحواذ'));
        foreach ($services as $i => $s) {
            $this->upsert('ha_service', array('code' => $s[0]), array('division' => $s[1], 'title_en' => $s[2], 'title_ar' => $s[3], 'sort_order' => $i, 'status' => 'published'));
        }
        $cases = array(
            array('flagship-resort-turnaround', 'Flagship Resort Turnaround & Sustained Market Leadership', 'التعافي والريادة المستدامة لمنتجع رئيسي', 'Egypt', 'Turnaround',
                'A 300+ key branded beachfront resort in a fiercely competitive leisure market.', 'Stagnant RevPAR, slipping guest-satisfaction scores, and margin erosion.',
                'Commercial re-engineering: pricing architecture, channel mix, service-culture programme, Six Sigma-led guest-journey redesign.', 'No. 1 in its competitive set for six consecutive years, with structural GOP margin expansion.',
                array('+25% RevPAR', '+30% guest satisfaction', '#1 comp set, 6 years', '$340K annual savings, one DMAIC project')),
            array('portfolio-operational-excellence', 'Portfolio-Wide Operational Excellence Mandate', 'تفويض التميز التشغيلي على مستوى المحفظة', 'Egypt', 'Operational excellence',
                'A global operator\'s country portfolio of 19 hotels and 3,000+ rooms.', 'Inconsistent service delivery, fragmented F&B performance, uneven brand-standard compliance.',
                'Standards harmonisation, property diagnostics, GM and HOD coaching, monthly performance instrumentation.', 'Portfolio-wide uplift in guest satisfaction and F&B revenue within 18 months.',
                array('19 hotels', '3,000+ rooms', '+10% guest satisfaction', '+8% F&B revenue in 18 months')),
            array('dual-hotel-pre-opening', 'Simultaneous Dual-Hotel Pre-Opening & Ramp-Up', 'افتتاح فندقين في وقت واحد', 'KSA', 'Pre-opening',
                'A regional hospitality group commissioning two new-build properties with a single opening season.', 'Parallel critical paths across licensing, recruitment, OS&E, systems and brand standards.',
                'Integrated pre-opening command structure, staged recruitment and training waves, procurement governance.', 'Both opened on schedule and reached market-leading profitability in the region.',
                array('2 hotels opened simultaneously', '90–95% operational readiness', 'T-0 on-time opening')),
            array('riyadh-owner-representation', 'Owner\'s Representative for a Branded Riyadh Portfolio', 'ممثل المالك لمحفظة فنادق في الرياض', 'Riyadh', 'Owner representation',
                'An institutional owner developing four internationally branded hotels.', 'Safeguarding owner interests across HMA governance, technical services, procurement and handover.',
                'Owner-representation office: HMA compliance, design and procurement challenge, milestone-based contractor management.', 'Budgets defended, claims mitigated, handovers executed to operator acceptance.',
                array('4 branded assets', 'Full HMA governance', '100% handovers accepted')),
        );
        foreach ($cases as $i => $c) {
            if ($this->db->where('slug', $c[0])->count_all_results('ha_case_study')) {
                continue;
            }
            $this->db->insert('ha_case_study', array('slug' => $c[0], 'title_en' => $c[1], 'title_ar' => $c[2], 'geography' => $c[3], 'case_type' => $c[4],
                'client_profile_en' => $c[5], 'challenge_en' => $c[6], 'approach_en' => $c[7], 'results_en' => $c[8], 'metrics_json' => json_encode($c[9]),
                'is_illustrative' => 1, 'visibility' => 'public', 'status' => 'published', 'sort_order' => $i, 'created_at' => $this->now, 'updated_at' => $this->now));
        }
        $leaders = array(
            array('islam-mahrous', 'Islam Mahrous', 'إسلام محروس', 'Co-Founder: Brand, Commercial Strategy & AI-Driven Digital Transformation', 'الشريك المؤسس: العلامة التجارية والاستراتيجية التجارية والتحول الرقمي بالذكاء الاصطناعي',
                'Three decades of multi-brand leadership with Marriott, IHG, Starwood and Accor, and independent asset management across Saudi Arabia, the GCC, Egypt and North Africa. Product architect of the altus Hospitality Knowledge & Performance platform.',
                "30+ years of multi-brand hospitality leadership\nLed a flagship branded resort to No. 1 in its competitive set for six consecutive years\nOperational excellence across 19 hotels (3,000+ rooms)\nDirected five pre-opening projects: 1,700+ rooms at 90–95% readiness\nSix Sigma Black Belt\nUSD 10M+ renovation portfolio, 7–20% cost savings",
                "Marriott GM Award for Customer Service Excellence: MEA, 2017 & 2022\nStarwood Best Operational Innovation Manager, 2007"),
            array('hossam-smadi', 'Hossam Smadi', 'حسام السمادي', 'Co-Founder: Hospitality Operations & Asset Management', 'الشريك المؤسس: العمليات الفندقية وإدارة الأصول',
                'Over 30 years of executive leadership in hotel operations and asset management across Saudi Arabia and Jordan, spanning global brands and independent groups.',
                "Led a property from pre-opening to Best Economy Hotel in Saudi Arabia\nHighest GOP in the Jazan region\nTwo simultaneous hotel openings in 2018\nOwner's Representative for four internationally branded Riyadh assets\nGoverned HMAs, procurement, OS&E and building handovers",
                "Best Economy Hotel in Saudi Arabia, as General Manager\nU.S. Embassy Riyadh recognition: Best Security Measures"),
        );
        foreach ($leaders as $i => $l) {
            if ($this->db->where('slug', $l[0])->count_all_results('ha_leadership_profile')) {
                continue;
            }
            $this->db->insert('ha_leadership_profile', array('slug' => $l[0], 'name_en' => $l[1], 'name_ar' => $l[2], 'role_en' => $l[3], 'role_ar' => $l[4],
                'biography_en' => $l[5], 'track_record_en' => $l[6], 'recognition_en' => $l[7], 'sort_order' => $i, 'status' => 'published',
                'created_at' => $this->now, 'updated_at' => $this->now));
        }
        return count($b) + count($sectors) + count($services) + count($cases) + count($leaders);
    }

    private function notifications() {
        require_once APPPATH . 'libraries/Ha_notify.php';
        $n = 0;
        foreach (Ha_notify::events() as $code => $e) {
            $this->db->query('INSERT IGNORE INTO ha_notification_rule (event_code, organization_id, enabled, channels, notify_manager, updated_at) VALUES (?, 0, 1, ?, ?, ?)',
                array($code, $e[1], $e[2], $this->now));
        }
        foreach (Ha_notify::default_templates() as $code => $locs) {
            foreach ($locs as $loc => $t) {
                $this->db->query('INSERT IGNORE INTO ha_notification_template (event_code, channel, locale, organization_id, subject, body, updated_at) VALUES (?, \'in_app\', ?, 0, ?, ?, ?)',
                    array($code, $loc, $t[0], $t[1], $this->now));
                $n++;
            }
        }
        return $n;
    }

    private function flags() {
        $f = array(
            array('governed_ai', 'Governed AI assistant', 'المساعد الذكي المحوكم', 'intelligence', 'released', 1),
            array('white_label', 'White-label per property', 'هوية مستقلة لكل فندق', 'experience', 'released', 1),
            array('custom_domains', 'Custom domains per property', 'نطاقات مخصصة لكل فندق', 'experience', 'beta', 1),
            array('pwa', 'Installable mobile app (PWA)', 'تطبيق جوال قابل للتثبيت', 'experience', 'released', 1),
            array('offline_lessons', 'Offline lessons', 'الدروس دون اتصال', 'experience', 'planned', 0),
            array('semantic_search', 'Semantic (vector) search', 'البحث الدلالي', 'intelligence', 'planned', 0),
            array('sso', 'Single sign-on (Entra ID, Google, SAML)', 'تسجيل الدخول الموحد', 'governance', 'planned', 0),
            array('whatsapp', 'WhatsApp and SMS notifications', 'إشعارات واتساب والرسائل النصية', 'operations', 'planned', 0),
            array('pms_integration', 'PMS / POS data integration', 'تكامل أنظمة إدارة الفنادق ونقاط البيع', 'operations', 'in_development', 0),
        );
        foreach ($f as $r) {
            $this->upsert('ha_feature_flag', array('code' => $r[0]), array('name_en' => $r[1], 'name_ar' => $r[2], 'layer' => $r[3], 'status' => $r[4], 'enabled' => $r[5]));
        }
        return count($f);
    }

    private function branding() {
        $this->db->query("INSERT IGNORE INTO ha_branding (scope_type, scope_id, brand_name_en, brand_name_ar, color_primary, color_secondary, color_accent, color_surface, show_altus, created_at, updated_at)
            VALUES ('platform', 0, 'altus Hospitality Knowledge & Performance', 'ألتوس للمعرفة والأداء الفندقي', '#0F3D3E', '#0D1B2A', '#C89D4F', '#F7F6F2', 'both', ?, ?)", array($this->now, $this->now));
        $dyafa = $this->db->get_where('ha_organization', array('slug' => 'dyafa-hospitality-group'))->row_array();
        if ($dyafa) {
            $this->db->query("INSERT IGNORE INTO ha_branding (scope_type, scope_id, brand_name_en, brand_name_ar, color_primary, color_accent, show_altus, created_at, updated_at)
                VALUES ('organization', ?, 'Dyafa Academy', 'أكاديمية ضيافة', '#4B2E83', '#C89D4F', 'both', ?, ?)", array($dyafa['id'], $this->now, $this->now));
        }
        return 2;
    }

    // ---------------------------------------------------------- demo tenant

    private function demo_tenant(array $domains, array $tracks, array $skills, array $rubrics) {
        $org = $this->upsert('ha_organization', array('slug' => 'altus-demo-client'), array('name_en' => 'Altus Demo Client', 'name_ar' => 'عميل ألتوس التجريبي',
            'legal_name' => 'Demo tenant — not a real company', 'country' => 'Saudi Arabia', 'city' => 'Riyadh', 'industry' => 'hotels', 'locale' => 'en', 'status' => 'active'));
        $portfolio = $this->upsert('ha_portfolio', array('organization_id' => $org, 'code' => 'riyadh'), array('name_en' => 'Riyadh Portfolio', 'name_ar' => 'محفظة الرياض', 'status' => 'active'));
        $prop = $this->upsert('ha_property', array('slug' => 'altus-demo-hotel-riyadh'), array('organization_id' => $org, 'portfolio_id' => $portfolio,
            'code' => 'ADH-RUH', 'name_en' => 'ALTUS Demo Hotel Riyadh', 'name_ar' => 'فندق ألتوس التجريبي الرياض', 'brand' => 'Independent',
            'property_type' => 'hotel', 'star_rating' => 4, 'city' => 'Riyadh', 'region' => 'Riyadh Region', 'country' => 'Saudi Arabia', 'room_count' => 160,
            'operational_status' => 'pre_opening', 'opening_date' => date('Y-m-d', strtotime('+45 days')), 'timezone' => 'Asia/Riyadh', 'status' => 'active'));
        $this->db->query("INSERT IGNORE INTO ha_branding (scope_type, scope_id, brand_name_en, brand_name_ar, color_primary, color_secondary, color_accent, color_surface, welcome_en, welcome_ar, show_altus, created_at, updated_at)
            VALUES ('property', ?, 'ALTUS Demo Hotel Riyadh Academy', 'أكاديمية فندق ألتوس التجريبي', '#7A2E3A', '#1F1A17', '#C89D4F', '#FBF8F3',
            'Welcome to the team. Your plan below is built for your role.', 'مرحباً بك في الفريق. خطتك أدناه مصممة لدورك.', 'both', ?, ?)", array($prop, $this->now, $this->now));

        $depts = array();
        foreach (array(array('FO', 'Front Office', 'المكتب الأمامي'), array('HK', 'Housekeeping', 'التدبير الفندقي'), array('GR', 'Guest Relations', 'علاقات الضيوف'), array('SEC', 'Security', 'الأمن')) as $d) {
            $depts[$d[0]] = $this->upsert('ha_department', array('organization_id' => $org, 'code' => $d[0], 'property_id' => null), array('name_en' => $d[1], 'name_ar' => $d[2], 'status' => 'active'));
        }
        $role = $this->upsert('ha_job_role', array('code' => 'demo-front-desk-agent'), array('organization_id' => $org, 'department_id' => $depts['FO'],
            'title_en' => 'Front Desk Agent', 'title_ar' => 'موظف مكتب الاستقبال', 'level' => 'associate', 'status' => 'active'));

        // Role -> competencies (section 70). Check-in is critical; complaint handling is not.
        foreach (array(array('guest-check-in', 3, 1), array('complaint-handling', 3, 0), array('guest-service', 3, 0), array('guest-data-privacy', 3, 1)) as $rc) {
            if (isset($skills[$rc[0]])) {
                $this->db->query('INSERT IGNORE INTO ha_role_competency (job_role_id, skill_id, property_key, required_level, is_critical, created_at, updated_at) VALUES (?,?,0,?,?,?,?)',
                    array($role, $skills[$rc[0]], $rc[1], $rc[2], $this->now, $this->now));
            }
        }
        // Role -> required learning: three focused demo tracks.
        $demo_tracks = array(
            'trk-demo-hotel-fundamentals' => array('hotel_fundamentals', 'Hotel Fundamentals — Front Desk', 'أساسيات الفندقة — الاستقبال', array('fo-fundamentals')),
            'trk-demo-front-office' => array('front_office', 'Front Office — Check-in', 'المكتب الأمامي — تسجيل الوصول', array('fo-check-in', 'fo-guest-privacy')),
            'trk-demo-guest-experience' => array('guest_experience', 'Guest Experience — Service Recovery', 'تجربة الضيف — استعادة الخدمة', array('fo-complaints')),
        );
        $tids = array();
        foreach ($demo_tracks as $code => $t) {
            $tids[$code] = $this->upsert('ha_track', array('code' => $code), array('domain_id' => $domains[$t[0]], 'title_en' => $t[1], 'title_ar' => $t[2],
                'organization_id' => $org, 'level' => 'foundation', 'status' => 'published', 'published_at' => $this->now));
            foreach ($t[3] as $i => $cc) {
                $c = $this->db->get_where('ha_course', array('code' => $cc))->row_array();
                if ($c) {
                    $this->db->query('INSERT IGNORE INTO ha_track_module (track_id, course_id, sort_order, is_mandatory) VALUES (?,?,?,1)', array($tids[$code], $c['id'], $i));
                }
            }
            $this->db->query('INSERT IGNORE INTO ha_role_requirement (job_role_id, property_key, item_type, item_id, is_mandatory, due_days, sort_order, created_at) VALUES (?,0,\'track\',?,1,30,?,?)',
                array($role, $tids[$code], count($tids), $this->now));
        }
        $this->upsert('ha_readiness_policy', array('code' => 'default'), array('name_en' => 'Default readiness policy', 'name_ar' => 'سياسة الجاهزية الافتراضية',
            'rules_json' => json_encode(Ha_readiness_rules::defaults()), 'status' => 'active'));
        $this->upsert('ha_readiness_policy', array('code' => 'demo-front-desk-ready'), array('name_en' => 'Front Office Ready', 'name_ar' => 'جاهزية المكتب الأمامي',
            'job_role_id' => $role, 'organization_id' => $org, 'rules_json' => json_encode(Ha_readiness_rules::defaults()), 'status' => 'active'));
        $this->upsert('ha_certification_program', array('code' => 'cert-front-office-agent'), array('title_en' => 'Front Office Certification', 'title_ar' => 'شهادة المكتب الأمامي',
            'description_en' => 'Awarded when learning, theory, practical competency and readiness are all evidenced.',
            'description_ar' => 'تُمنح عند توفر أدلة التعلم والمعرفة النظرية والكفاءة العملية والجاهزية.',
            'domain_id' => $domains['front_office'], 'job_role_id' => $role, 'organization_id' => $org, 'number_prefix' => 'ALTUS-FOA',
            'rules_json' => json_encode(array('tracks' => array_values($tids), 'rubrics' => array_values(array_filter(array(isset($rubrics['rub-fo-check-in']) ? $rubrics['rub-fo-check-in'] : null, isset($rubrics['rub-complaint-handling']) ? $rubrics['rub-complaint-handling'] : null))),
                'competencies' => array(array('skill_id' => $skills['guest-check-in'], 'level' => 3), array('skill_id' => $skills['complaint-handling'], 'level' => 3)), 'require_readiness' => 'ready')),
            'validity_months' => 24, 'status' => 'published'));

        // People. Seeded demo accounts, clearly named.
        $roles = array();
        foreach ($this->db->get('ha_role')->result_array() as $r) {
            $roles[$r['code']] = (int) $r['id'];
        }
        $people = array(
            array('Ahmed', 'Al Qahtani', 'أحمد القحطاني', 'demo.learner@altusdemo.sa', 'learner', 'FO', $role),
            array('Sara', 'Al Mutairi', 'سارة المطيري', 'demo.learner2@altusdemo.sa', 'learner', 'FO', $role),
            array('Faris', 'Al Dosari', 'فارس الدوسري', 'demo.supervisor@altusdemo.sa', 'supervisor', 'FO', null),
            array('Lama', 'Al Shammari', 'لمى الشمري', 'demo.gm@altusdemo.sa', 'property_manager', null, null),
            array('Noura', 'Al Saud', 'نورة آل سعود', 'demo.exec@altusdemo.sa', 'executive', null, null),
            array('Khalid', 'Al Otaibi', 'خالد العتيبي', 'demo.training@altusdemo.sa', 'training_manager', null, null),
        );
        $ids = array();
        $sup = null;
        foreach ($people as $i => $p) {
            $u = $this->db->get_where('users', array('email' => $p[3]))->row_array();
            if (!$u) {
                $this->db->insert('users', array('first_name' => $p[0], 'last_name' => $p[1], 'email' => $p[3], 'password' => sha1(Seed_organizations::DEMO_PASSWORD),
                    'role_id' => 2, 'status' => 1, 'is_instructor' => 0, 'skills' => '[]', 'payment_keys' => '[]', 'sessions' => '[]',
                    'social_links' => '{"facebook":"","twitter":"","linkedin":""}', 'wishlist' => '[]', 'date_added' => time(), 'last_modified' => time()));
                $uid = (int) $this->db->insert_id();
            } else {
                $uid = (int) $u['id'];
            }
            $ids[$p[3]] = $uid;
            $this->upsert('ha_profile', array('user_id' => $uid), array('employee_no' => 'ADH-' . (101 + $i), 'full_name_ar' => $p[2],
                'job_title_en' => $p[6] ? 'Front Desk Agent' : ucfirst(str_replace('_', ' ', $p[4])), 'organization_id' => $org,
                'property_id' => $p[4] === 'executive' || $p[4] === 'training_manager' ? null : $prop, 'department_id' => $p[5] ? $depts[$p[5]] : null,
                'job_role_id' => $p[6], 'locale' => 'en', 'hire_date' => date('Y-m-d', strtotime('-20 days')), 'status' => 'active'));
            $this->link('ha_user_role', array('user_id' => $uid, 'role_id' => $roles[$p[4]], 'organization_id' => $org,
                'property_id' => in_array($p[4], array('executive', 'training_manager'), true) ? null : $prop, 'department_id' => $p[5] ? $depts[$p[5]] : null),
                array('created_at' => $this->now));
        }
        $this->db->where_in('user_id', array($ids['demo.learner@altusdemo.sa'], $ids['demo.learner2@altusdemo.sa']))
            ->update('ha_profile', array('manager_user_id' => $ids['demo.supervisor@altusdemo.sa']));
        $this->db->where('id', $prop)->update('ha_property', array('manager_user_id' => $ids['demo.gm@altusdemo.sa']));

        // The property's own approved check-in SOP (answers the section 185 AI question).
        if (!$this->db->where('code', 'adh-sop-fo-check-in')->count_all_results('ha_sop_document')) {
            $this->db->insert('ha_sop_document', array('code' => 'adh-sop-fo-check-in', 'item_type' => 'sop', 'slug_en' => 'adh-sop-fo-check-in', 'slug_ar' => 'adh-sop-fo-check-in-ar',
                'domain_id' => $domains['front_office'], 'organization_id' => $org, 'property_id' => $prop, 'department_code' => 'FO',
                'owner_user_id' => $ids['demo.gm@altusdemo.sa'], 'visibility' => 'organization', 'is_mandatory' => 1, 'requires_acknowledgement' => 1,
                'review_interval_months' => 12, 'status' => 'published', 'ai_enabled' => 1, 'created_by' => $ids['demo.gm@altusdemo.sa'], 'created_at' => $this->now, 'updated_at' => $this->now));
            $sop = (int) $this->db->insert_id();
            $this->db->insert('ha_sop_version', array('sop_id' => $sop, 'version_label' => '1.0', 'version_major' => 1, 'version_minor' => 0,
                'change_summary' => 'Approved for opening', 'author_user_id' => $ids['demo.gm@altusdemo.sa'], 'approver_user_id' => $ids['demo.gm@altusdemo.sa'],
                'status' => 'published', 'effective_date' => date('Y-m-d'), 'review_date' => date('Y-m-d', strtotime('+12 months')),
                'approved_at' => $this->now, 'published_at' => $this->now, 'created_at' => $this->now, 'updated_at' => $this->now));
            $v = (int) $this->db->insert_id();
            $this->db->insert('ha_sop_version_translation', array('version_id' => $v, 'locale' => 'en', 'title' => 'Guest check-in procedure — ALTUS Demo Hotel Riyadh',
                'purpose' => 'Every arriving guest is welcomed, verified and registered to the same standard within four minutes.',
                'procedure' => "1. Stand, make eye contact and greet the guest within 10 seconds of arrival.\n2. Confirm the reservation by name and dates.\n3. Verify identity: national ID or Iqama for residents, passport for visitors, and match it to the reservation.\n4. Complete registration in the PMS, including the Shomoos submission.\n5. Take the payment guarantee and explain any deposit.\n6. Explain breakfast times, Wi-Fi and the prayer room.\n7. Issue two keys, point to the lifts and wish the guest a pleasant stay.",
                'escalation' => 'Identity that does not match the reservation goes to the Duty Manager before any key is issued.'));
            $this->db->insert('ha_sop_version_translation', array('version_id' => $v, 'locale' => 'ar', 'title' => 'إجراء تسجيل وصول الضيف — فندق ألتوس التجريبي الرياض',
                'purpose' => 'يُستقبل كل ضيف قادم ويُتحقق من هويته ويُسجل وفق المعيار ذاته خلال أربع دقائق.',
                'procedure' => "1. قف وتواصل بالنظر ورحب بالضيف خلال 10 ثوانٍ من وصوله.\n2. أكد الحجز بالاسم والتواريخ.\n3. تحقق من الهوية: الهوية الوطنية أو الإقامة للمقيمين وجواز السفر للزوار، وطابقها مع الحجز.\n4. أكمل التسجيل في نظام إدارة الفندق بما في ذلك إرسال بيانات شموس.\n5. خذ ضمان الدفع ووضح أي تأمين.\n6. اشرح مواعيد الإفطار والإنترنت ومصلى الفندق.\n7. سلّم مفتاحين وأرشد الضيف إلى المصاعد وتمنَّ له إقامة طيبة.",
                'escalation' => 'الهوية التي لا تطابق الحجز تُحال إلى مدير المناوبة قبل تسليم أي مفتاح.'));
            $this->db->where('id', $sop)->update('ha_sop_document', array('current_version_id' => $v));
        }

        // Pre-opening cohort and opening readiness items (sections 172, 173).
        $cohort = $this->upsert('ha_cohort', array('organization_id' => $org, 'code' => 'pre-opening-2026'), array('property_id' => $prop,
            'name_en' => 'Pre-opening Team 2026', 'name_ar' => 'فريق ما قبل الافتتاح 2026', 'cohort_type' => 'pre_opening',
            'opening_date' => date('Y-m-d', strtotime('+45 days')), 'status' => 'active'));
        foreach (array('demo.learner@altusdemo.sa', 'demo.learner2@altusdemo.sa') as $e) {
            $this->db->query('INSERT IGNORE INTO ha_cohort_member (cohort_id, user_id, wave, joined_at) VALUES (?, ?, \'Wave 1\', ?)', array($cohort, $ids[$e], $this->now));
        }
        if (!$this->db->where('property_id', $prop)->count_all_results('ha_opening_readiness_item')) {
            $items = array(
                array('recruitment', 'Front office headcount hired', 'تعيين موظفي المكتب الأمامي', 'manual', 12, 10, 0),
                array('training', 'Mandatory learning completed', 'إكمال التعلم الإلزامي', 'training', 100, 0, 1),
                array('competency', 'Required competencies met', 'تحقيق الكفاءات المطلوبة', 'competency', 100, 0, 1),
                array('sop', 'SOPs acknowledged', 'الإقرار بالإجراءات', 'sop', 100, 0, 0),
                array('systems', 'PMS configured and tested', 'تهيئة نظام إدارة الفندق واختباره', 'manual', 100, 95, 0),
                array('safety', 'Fire and life safety sign-off', 'اعتماد السلامة من الحريق', 'manual', 100, 100, 1),
                array('quality', 'Mock-room inspection passed', 'اجتياز فحص الغرفة النموذجية', 'manual', 100, 90, 0),
                array('commercial', 'Rates loaded on all channels', 'تحميل الأسعار على جميع القنوات', 'manual', 100, 80, 0),
            );
            foreach ($items as $it) {
                $this->db->insert('ha_opening_readiness_item', array('property_id' => $prop, 'category' => $it[0], 'label_en' => $it[1], 'label_ar' => $it[2],
                    'auto_source' => $it[3], 'required_value' => $it[4], 'current_value' => $it[5], 'is_critical' => $it[6],
                    'owner_user_id' => $ids['demo.gm@altusdemo.sa'], 'due_date' => date('Y-m-d', strtotime('+30 days')), 'created_at' => $this->now, 'updated_at' => $this->now));
            }
        }
        if (!$this->db->where('code', 'ENG-DEMO-PREOPEN')->count_all_results('ha_engagement')) {
            $this->db->insert('ha_engagement', array('organization_id' => $org, 'property_id' => $prop, 'code' => 'ENG-DEMO-PREOPEN', 'name' => 'ALTUS Demo Hotel pre-opening',
                'engagement_type' => 'pre_opening', 'start_date' => date('Y-m-d', strtotime('-30 days')), 'end_date' => date('Y-m-d', strtotime('+120 days')),
                'status' => 'active', 'objectives' => 'Open on schedule with a certified front office team.', 'framework' => 'ascent',
                'created_at' => $this->now, 'updated_at' => $this->now));
            $eng = (int) $this->db->insert_id();
            foreach (array('discover', 'assess', 'design', 'transform', 'optimise', 'scale') as $i => $s) {
                $this->db->insert('ha_engagement_stage', array('engagement_id' => $eng, 'stage' => $s, 'status' => $i < 2 ? 'signed_off' : ($i === 2 ? 'in_progress' : 'not_started'),
                    'signed_off_by' => $i < 2 ? 'Owner representative (demo)' : null, 'signed_off_at' => $i < 2 ? $this->now : null, 'sort_order' => $i));
            }
        }
        return count($people) + 20;
    }

    /** Competency matrices for the existing Dyafa career-ladder roles, so their dashboards have evidence to show. */
    private function dyafa_matrices(array $tracks, array $skills) {
        $m = array(
            'fo-associate' => array(array('guest-service', 3, 0), array('complaint-handling', 2, 0), array('pms-operation', 3, 0), array('guest-data-privacy', 3, 1), array('guest-check-in', 3, 1)),
            'fo-senior-associate' => array(array('guest-service', 3, 0), array('complaint-handling', 3, 0), array('upselling', 3, 0), array('guest-check-in', 3, 1)),
            'hk-room-attendant' => array(array('room-standards', 3, 0), array('chemical-safety', 3, 1)),
            'hk-senior-attendant' => array(array('room-standards', 4, 0), array('chemical-safety', 3, 1)),
            'fb-associate' => array(array('food-service', 3, 0), array('food-safety', 3, 1)),
            'fb-captain' => array(array('food-service', 4, 0), array('food-safety', 3, 1), array('team-supervision', 2, 0)),
            'kit-commis' => array(array('food-safety', 3, 1), array('culinary-basics', 2, 0)),
            'sec-officer' => array(array('emergency-response', 3, 1), array('occupational-safety', 3, 0)),
            'gr-agent' => array(array('guest-service', 3, 0), array('complaint-handling', 3, 0)),
            'eng-technician' => array(array('maintenance-basics', 3, 0), array('occupational-safety', 3, 0)),
        );
        $track_for = array('fo' => 'front_office', 'hk' => 'housekeeping', 'fb' => 'food_beverage', 'kit' => 'kitchen', 'sec' => 'security_safety', 'gr' => 'guest_experience', 'eng' => 'security_safety');
        $n = 0;
        foreach ($m as $code => $list) {
            $role = $this->db->get_where('ha_job_role', array('code' => $code))->row_array();
            if (!$role) {
                continue;
            }
            foreach ($list as $rc) {
                if (!isset($skills[$rc[0]])) {
                    continue;
                }
                $this->db->query('INSERT IGNORE INTO ha_role_competency (job_role_id, skill_id, property_key, required_level, is_critical, created_at, updated_at) VALUES (?,?,0,?,?,?,?)',
                    array($role['id'], $skills[$rc[0]], $rc[1], $rc[2], $this->now, $this->now));
                $n++;
            }
            $prefix = strtok($code, '-');
            if (isset($track_for[$prefix], $tracks[$track_for[$prefix]])) {
                $this->db->query('INSERT IGNORE INTO ha_role_requirement (job_role_id, property_key, item_type, item_id, is_mandatory, due_days, sort_order, created_at) VALUES (?,0,\'track\',?,1,45,0,?)',
                    array($role['id'], $tracks[$track_for[$prefix]], $this->now));
                if (isset($tracks['hotel_fundamentals'])) {
                    $this->db->query('INSERT IGNORE INTO ha_role_requirement (job_role_id, property_key, item_type, item_id, is_mandatory, due_days, sort_order, created_at) VALUES (?,0,\'track\',?,1,45,1,?)',
                        array($role['id'], $tracks['hotel_fundamentals'], $this->now));
                }
            }
        }
        return $n;
    }

    /** Index approved content and calculate everyone's readiness so dashboards open with real state. */
    private function finalise() {
        $CI =& get_instance();
        $CI->load->helper(array('url', 'hkp'));
        $CI->load->library(array('ha_governed_ai', 'ha_readiness', 'ha_learning'));
        $CI->ha_governed_ai->reindex_all();
        foreach ($this->db->select('user_id')->where('job_role_id IS NOT NULL', null, false)->where('status', 'active')->get('ha_profile')->result_array() as $p) {
            $has = $this->db->select('tr.id')->from('ha_training_recipient tr')->join('ha_training_assignment ta', 'ta.id = tr.assignment_id')
                ->where(array('tr.user_id' => $p['user_id'], 'ta.source' => 'role_requirement'))->get()->row_array();
            if (!$has) {
                $CI->ha_learning->sync_role_plan($p['user_id']);
            }
            $CI->ha_readiness->calculate($p['user_id']);
        }
    }
}

/** Kept separate so the seed does not need the readiness library loaded to know its defaults. */
class Ha_readiness_rules {
    public static function defaults() {
        require_once APPPATH . 'libraries/Ha_readiness.php';
        return Ha_readiness::default_rules();
    }
}
