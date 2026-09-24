<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';
require_once APPPATH . 'seeds/002_organizations.php';

/**
 * The hospitality curriculum from plan section 9, plus the categories, skills,
 * programs and career paths of sections 6, 10 and 20.
 *
 * Course descriptions are written for the hotel operation they belong to. No
 * outcome, statistic or accreditation claim is invented: text describes what
 * the course teaches, nothing about results or endorsements.
 */
class Seed_curriculum extends Ha_seeder {

    /** Department pillars, used for categories and for tagging content. */
    public static function categories() {
        return array(
            // The first nine are the professional domains published in the
            // corporate profile, in its order. Two of its ten -- Hotel
            // Fundamentals -- has no course written for it yet and is not
            // invented here; see enhance.md 2.1. Engineering and Hotel
            // Management follow: both carry real courses and neither appears
            // in the published ten, which is a gap in the profile, not in the
            // catalogue.
            array('front-office',            'Front Office',           'مكتب الاستقبال',       'FO',   'Reception, reservations, guest arrival and departure.', 'الاستقبال والحجوزات ووصول الضيوف ومغادرتهم.'),
            array('housekeeping',            'Housekeeping',           'التدبير الفندقي',      'HK',   'Room cleaning, inspection, linen and public areas.', 'تنظيف الغرف والتفتيش والمفروشات والمناطق العامة.'),
            array('food-and-beverage',       'Food & Beverage',        'الأغذية والمشروبات',   'FB',   'Restaurant, bar, banquet and in-room dining service.', 'خدمة المطاعم والبار والولائم وخدمة الغرف.'),
            array('kitchen',                 'Kitchen',                'المطبخ',               'KIT',  'Culinary production, food safety and kitchen discipline.', 'الإنتاج الغذائي وسلامة الأغذية وانضباط المطبخ.'),
            array('sales-and-marketing',     'Sales & Marketing',      'المبيعات والتسويق',    null,   'Hotel marketing, direct booking, search visibility and campaigns.', 'التسويق الفندقي والحجز المباشر والظهور في محركات البحث والحملات.'),
            array('revenue-and-reservations','Revenue & Reservations', 'الإيرادات والحجوزات',  null,   'Property systems, distribution, channel mix and revenue analytics.', 'أنظمة إدارة الفنادق والتوزيع ومزيج القنوات وتحليلات الإيرادات.'),
            array('guest-experience',        'Guest Experience',       'تجربة الضيف',          'GR',   'Service recovery, personalisation and guest loyalty.', 'معالجة الشكاوى والتخصيص وولاء الضيوف.'),
            array('quality-and-audit',       'Quality & Audit',        'الجودة والتدقيق',      null,   'Quality standards, performance indicators and internal audit.', 'معايير الجودة ومؤشرات الأداء والتدقيق الداخلي.'),
            array('security-and-safety',     'Security & Safety',      'الأمن والسلامة',       'SEC',  'Fire, occupational safety, security and regulatory compliance.', 'الحريق والسلامة المهنية والأمن والالتزام التنظيمي.'),
            array('engineering',             'Engineering',            'الهندسة',              'ENG',  'Building systems, preventive maintenance and safety.', 'أنظمة المبنى والصيانة الوقائية والسلامة.'),
            array('management',              'Hotel Management',       'إدارة الفنادق',        null,   'Leadership, commercial performance and hotel finance.', 'القيادة والأداء التجاري والمالية الفندقية.'),
        );
    }

    /**
     * The catalogue. Each entry:
     * code, category, department, level, minutes, EN title, AR title,
     * EN summary, AR summary, skills[]
     */
    public static function courses() {
        return array(
            // ---------------------------------------------------------- Front Office
            array('fo-fundamentals', 'front-office', 'FO', 'foundation', 180,
                'Front Office Fundamentals', 'أساسيات مكتب الاستقبال',
                'How the front desk fits into a hotel day, the shift structure, the systems in use and the standards every agent works to.',
                'كيف يندمج مكتب الاستقبال في يوم الفندق، وهيكل الورديات، والأنظمة المستخدمة، والمعايير التي يعمل بها كل موظف.',
                array('guest-service', 'pms-operation')),
            array('fo-reception-operations', 'front-office', 'FO', 'foundation', 150,
                'Reception Operations', 'عمليات الاستقبال',
                'Running the desk hour by hour: queue management, shift handover, cash float, key control and the daily reports.',
                'إدارة المكتب ساعة بساعة: تنظيم الطابور وتسليم الوردية وصندوق النقد والتحكم بالمفاتيح والتقارير اليومية.',
                array('pms-operation')),
            array('fo-check-in', 'front-office', 'FO', 'foundation', 90,
                'Guest Check-in', 'إجراءات تسجيل الوصول',
                'The arrival sequence from greeting to room handover, including identity verification and Saudi registration requirements.',
                'تسلسل الوصول من الترحيب حتى تسليم الغرفة، بما في ذلك التحقق من الهوية ومتطلبات التسجيل في السعودية.',
                array('guest-service', 'pms-operation')),
            array('fo-check-out', 'front-office', 'FO', 'foundation', 90,
                'Guest Check-out', 'إجراءات تسجيل المغادرة',
                'Closing a stay cleanly: folio review, disputed charges, late checkout, express departure and the farewell.',
                'إنهاء الإقامة بشكل سليم: مراجعة الفاتورة والرسوم المعترض عليها والمغادرة المتأخرة والسريعة والتوديع.',
                array('guest-service')),
            array('fo-guest-registration', 'front-office', 'FO', 'foundation', 75,
                'Guest Registration', 'تسجيل بيانات الضيف',
                'Capturing guest data accurately and lawfully, what must be recorded, and how registration records are stored.',
                'تسجيل بيانات الضيف بدقة وبما يتوافق مع الأنظمة، وما يجب توثيقه، وكيفية حفظ السجلات.',
                array('guest-data-privacy')),
            array('fo-room-assignment', 'front-office', 'FO', 'intermediate', 90,
                'Room Assignment', 'توزيع الغرف',
                'Matching inventory to guests: room blocking, preferences, accessibility needs and handling an oversold night.',
                'مطابقة المخزون بالضيوف: حجز الغرف والتفضيلات واحتياجات الوصول ومعالجة ليلة الحجز الزائد.',
                array('pms-operation')),
            array('fo-concierge', 'front-office', 'FO', 'intermediate', 120,
                'Concierge Service', 'خدمة الكونسيرج',
                'Building a usable local knowledge base, handling requests end to end and following up before the guest has to ask.',
                'بناء قاعدة معرفة محلية قابلة للاستخدام، ومعالجة الطلبات من البداية للنهاية، والمتابعة قبل أن يسأل الضيف.',
                array('guest-service')),
            array('fo-complaints', 'front-office', 'FO', 'intermediate', 120,
                'Guest Complaint Handling', 'معالجة شكاوى الضيوف',
                'A repeatable method for listening, owning, resolving and recording a complaint, and when to escalate it.',
                'طريقة قابلة للتكرار للاستماع وتحمل المسؤولية والحل والتوثيق، ومتى يتم التصعيد.',
                array('complaint-handling', 'guest-service')),
            array('fo-vip', 'front-office', 'FO', 'intermediate', 90,
                'VIP and Repeat Guest Handling', 'التعامل مع كبار الضيوف والمتكررين',
                'Recognising, preparing for and hosting VIP arrivals, including amenity timing and departmental coordination.',
                'التعرف على كبار الضيوف والتحضير لوصولهم واستضافتهم، بما في ذلك توقيت الهدايا والتنسيق بين الأقسام.',
                array('guest-service')),
            array('fo-telephone', 'front-office', 'FO', 'foundation', 60,
                'Telephone Etiquette', 'آداب المكالمات الهاتفية',
                'Answering standards, transfers, taking a message, handling a difficult caller and the wake-up call procedure.',
                'معايير الرد والتحويل وتدوين الرسائل والتعامل مع المتصل الصعب وإجراء مكالمة الإيقاظ.',
                array('guest-service')),
            array('fo-upselling', 'front-office', 'FO', 'intermediate', 90,
                'Front Desk Upselling', 'البيع الإضافي في الاستقبال',
                'Reading the booking, offering the right upgrade at the right moment and recording the result honestly.',
                'قراءة الحجز وتقديم الترقية المناسبة في الوقت المناسب وتسجيل النتيجة بأمانة.',
                array('upselling')),
            array('fo-night-audit', 'front-office', 'FO', 'advanced', 150,
                'Night Audit', 'التدقيق الليلي',
                'Closing the hotel day: posting, balancing, the no-show routine, system rollover and the morning report pack.',
                'إغلاق يوم الفندق: الترحيل والموازنة وإجراء عدم الحضور وتدوير النظام وحزمة تقارير الصباح.',
                array('night-audit', 'pms-operation')),
            array('fo-guest-privacy', 'front-office', 'FO', 'foundation', 60,
                'Guest Privacy', 'خصوصية الضيف',
                'What may and may not be disclosed about a guest, room number discipline and handling third party enquiries.',
                'ما يجوز وما لا يجوز الإفصاح عنه عن الضيف، وانضباط رقم الغرفة، والتعامل مع استفسارات الغير.',
                array('guest-data-privacy')),

            // ---------------------------------------------------------- Housekeeping
            array('hk-fundamentals', 'housekeeping', 'HK', 'foundation', 150,
                'Housekeeping Fundamentals', 'أساسيات التدبير الفندقي',
                'The department structure, the daily board, room status codes and how housekeeping and front office stay in step.',
                'هيكل القسم واللوحة اليومية ورموز حالة الغرفة وكيفية تنسيق التدبير مع الاستقبال.',
                array('room-standards')),
            array('hk-room-cleaning', 'housekeeping', 'HK', 'foundation', 120,
                'Room Cleaning Procedure', 'إجراء تنظيف الغرفة',
                'The cleaning sequence in order, the trolley setup, and what a room must look like before it is released.',
                'تسلسل التنظيف بالترتيب وتجهيز العربة وكيف يجب أن تبدو الغرفة قبل تسليمها.',
                array('room-standards', 'chemical-safety')),
            array('hk-bed-making', 'housekeeping', 'HK', 'foundation', 60,
                'Bed Making Standard', 'معيار ترتيب السرير',
                'The standard bed setup, linen change frequency, pillow presentation and how to spot linen that must be withdrawn.',
                'الإعداد القياسي للسرير وتكرار تغيير المفروشات وترتيب الوسائد وكيفية اكتشاف المفروشات التي يجب سحبها.',
                array('room-standards')),
            array('hk-bathroom', 'housekeeping', 'HK', 'foundation', 75,
                'Bathroom Cleaning', 'تنظيف دورة المياه',
                'Bathroom cleaning order, the chemicals used for each surface, amenity placement and the final check.',
                'ترتيب تنظيف دورة المياه والمواد المستخدمة لكل سطح وترتيب المستلزمات والفحص النهائي.',
                array('room-standards', 'chemical-safety')),
            array('hk-inspection', 'housekeeping', 'HK', 'intermediate', 90,
                'Room Inspection', 'تفتيش الغرف',
                'Inspecting to a written standard, scoring, giving feedback to the attendant and closing a failed inspection.',
                'التفتيش وفق معيار مكتوب والتقييم وإعطاء الملاحظات للعامل وإغلاق التفتيش غير المطابق.',
                array('room-standards', 'team-supervision')),
            array('hk-lost-found', 'housekeeping', 'HK', 'foundation', 45,
                'Lost and Found', 'المفقودات والموجودات',
                'Logging, storing, returning and disposing of guest property, and the record that has to exist for each item.',
                'تسجيل وتخزين وإعادة والتخلص من ممتلكات الضيوف، والسجل الواجب توفره لكل قطعة.',
                array('room-standards')),
            array('hk-laundry', 'housekeeping', 'LND', 'foundation', 90,
                'Laundry Operations', 'عمليات المغسلة',
                'Sorting, washing, finishing and guest laundry handling, including damage claims and turnaround commitments.',
                'الفرز والغسيل والتشطيب ومعالجة غسيل الضيوف، بما في ذلك مطالبات التلف والتزامات وقت التسليم.',
                array('room-standards')),
            array('hk-public-areas', 'housekeeping', 'HK', 'foundation', 75,
                'Public Area Cleaning', 'تنظيف المناطق العامة',
                'Lobby, corridor, restroom and back of house cleaning rounds, and keeping a public area presentable while occupied.',
                'جولات تنظيف اللوبي والممرات ودورات المياه والمناطق الخلفية، والحفاظ على المنطقة العامة مرتبة أثناء الاستخدام.',
                array('room-standards')),
            array('hk-deep-cleaning', 'housekeeping', 'HK', 'intermediate', 90,
                'Deep Cleaning', 'التنظيف العميق',
                'Planning a deep clean cycle, the tasks it covers beyond a daily service, and recording what was done.',
                'تخطيط دورة التنظيف العميق والمهام التي تتجاوز الخدمة اليومية وتوثيق ما تم إنجازه.',
                array('room-standards')),
            array('hk-chemical-safety', 'housekeeping', 'HK', 'foundation', 60,
                'Cleaning Chemical Safety', 'سلامة مواد التنظيف',
                'Reading a product label, dilution, personal protection, storage and what to do after an exposure.',
                'قراءة ملصق المنتج والتخفيف والحماية الشخصية والتخزين وما يجب فعله بعد التعرض.',
                array('chemical-safety', 'occupational-safety')),
            array('hk-turnaround', 'housekeeping', 'HK', 'intermediate', 60,
                'Room Turnaround', 'تجهيز الغرفة بين الإقامات',
                'Turning a departure into a saleable room quickly without dropping the standard, and communicating readiness.',
                'تحويل غرفة المغادرة إلى غرفة قابلة للبيع بسرعة دون خفض المعيار، وإبلاغ الجاهزية.',
                array('room-standards')),
            array('hk-productivity', 'housekeeping', 'HK', 'advanced', 90,
                'Housekeeping Productivity', 'إنتاجية التدبير الفندقي',
                'Building the daily assignment, minutes per room, credit systems and where time is actually lost on a floor.',
                'بناء التوزيع اليومي ودقائق الغرفة وأنظمة النقاط وأين يضيع الوقت فعلياً في الطابق.',
                array('team-supervision')),

            // ------------------------------------------------------- Food & Beverage
            array('fb-restaurant-service', 'food-and-beverage', 'FB', 'foundation', 150,
                'Restaurant Service', 'خدمة المطاعم',
                'The service sequence from seating to settlement, station setup and working a section under pressure.',
                'تسلسل الخدمة من الجلوس حتى الدفع وتجهيز المحطة والعمل في القسم تحت الضغط.',
                array('food-service')),
            array('fb-guest-greeting', 'food-and-beverage', 'FB', 'foundation', 45,
                'Guest Greeting and Seating', 'استقبال الضيوف وإجلاسهم',
                'Managing the door, the waitlist, seating decisions and handing a table over to the server.',
                'إدارة المدخل وقائمة الانتظار وقرارات الإجلاس وتسليم الطاولة للنادل.',
                array('guest-service', 'food-service')),
            array('fb-table-service', 'food-and-beverage', 'FB', 'foundation', 90,
                'Table Service Standards', 'معايير خدمة الطاولة',
                'Carrying, serving and clearing to standard, order of service, and adjusting for family and majlis seating.',
                'الحمل والتقديم والرفع وفق المعيار وترتيب الخدمة والتكيف مع جلسات العائلة والمجلس.',
                array('food-service')),
            array('fb-food-safety', 'food-and-beverage', 'FB', 'foundation', 120,
                'Food Safety for Service Staff', 'سلامة الغذاء لموظفي الخدمة',
                'Temperature control on the pass, allergen questions, cross contamination in the service area and personal hygiene.',
                'ضبط درجات الحرارة عند التمرير وأسئلة مسببات الحساسية والتلوث المتبادل في منطقة الخدمة والنظافة الشخصية.',
                array('food-safety')),
            array('fb-haccp', 'food-and-beverage', 'FB', 'intermediate', 120,
                'HACCP Basics', 'أساسيات الهاسب',
                'What a hazard analysis is, the critical control points a hotel kitchen usually has, and the records that prove control.',
                'ما هو تحليل المخاطر ونقاط التحكم الحرجة المعتادة في مطبخ الفندق والسجلات التي تثبت التحكم.',
                array('food-safety')),
            array('fb-hygiene', 'food-and-beverage', 'FB', 'foundation', 60,
                'Personal and Service Hygiene', 'النظافة الشخصية ونظافة الخدمة',
                'Grooming standards, hand washing moments, illness reporting and uniform handling.',
                'معايير المظهر ولحظات غسل اليدين والإبلاغ عن المرض والتعامل مع الزي.',
                array('food-safety')),
            array('fb-beverage', 'food-and-beverage', 'FB', 'intermediate', 90,
                'Beverage Service', 'خدمة المشروبات',
                'Coffee, tea, juice and mocktail service to standard, including Saudi coffee service and beverage cost discipline.',
                'خدمة القهوة والشاي والعصائر والموكتيل وفق المعيار، بما في ذلك خدمة القهوة السعودية وانضباط تكلفة المشروبات.',
                array('food-service')),
            array('fb-banquet', 'food-and-beverage', 'EVT', 'intermediate', 120,
                'Banquet Operations', 'عمليات الولائم',
                'Reading a function sheet, room setup styles, timing a plated service and the post event breakdown.',
                'قراءة ورقة الفعالية وأنماط تجهيز القاعة وتوقيت الخدمة المطبقة وتفكيك الفعالية.',
                array('food-service', 'team-supervision')),
            array('fb-pos', 'food-and-beverage', 'FB', 'foundation', 60,
                'POS Procedures', 'إجراءات نقاط البيع',
                'Opening a check, modifiers, voids and comps, room charge posting and closing a shift on the POS.',
                'فتح الفاتورة والتعديلات والإلغاءات والمجاملات وترحيل الرسوم على الغرفة وإغلاق الوردية.',
                array('pms-operation')),
            array('fb-upselling', 'food-and-beverage', 'FB', 'intermediate', 60,
                'Food and Beverage Upselling', 'البيع الإضافي في الأغذية والمشروبات',
                'Describing a dish so it sells itself, pairing suggestions and offering without pressuring the guest.',
                'وصف الطبق بحيث يبيع نفسه واقتراحات المرافقة والعرض دون الضغط على الضيف.',
                array('upselling')),

            // ------------------------------------------------------------- Kitchen
            array('kit-fundamentals', 'kitchen', 'KIT', 'foundation', 180,
                'Culinary Fundamentals', 'أساسيات فنون الطهي',
                'Brigade structure, mise en place, basic cooking methods and how a hotel kitchen runs a service.',
                'هيكل الفريق والتحضير المسبق وطرق الطهي الأساسية وكيف يدير مطبخ الفندق الخدمة.',
                array('culinary-basics')),
            array('kit-food-safety', 'kitchen', 'KIT', 'foundation', 120,
                'Kitchen Food Safety', 'سلامة الغذاء في المطبخ',
                'The temperature danger zone, cooking and cooling targets, date labelling and the daily food safety records.',
                'منطقة الخطر الحرارية وأهداف الطهي والتبريد ووضع تواريخ الصلاحية وسجلات سلامة الغذاء اليومية.',
                array('food-safety')),
            array('kit-hygiene', 'kitchen', 'KIT', 'foundation', 60,
                'Kitchen Hygiene', 'نظافة المطبخ',
                'Cleaning as you go, the wash up cycle, equipment hygiene and the end of shift close down.',
                'التنظيف أثناء العمل ودورة الغسيل ونظافة المعدات وإغلاق نهاية الوردية.',
                array('food-safety')),
            array('kit-knife-safety', 'kitchen', 'KIT', 'foundation', 45,
                'Knife Safety', 'سلامة استخدام السكاكين',
                'Grip, board setup, carrying, honing, storage and what to do after a cut.',
                'الإمساك وتجهيز اللوح والحمل والسن والتخزين وما يجب فعله بعد الجرح.',
                array('occupational-safety', 'culinary-basics')),
            array('kit-storage', 'kitchen', 'KIT', 'foundation', 60,
                'Food Storage', 'تخزين الأغذية',
                'Storage order in the fridge, dry store discipline, stock rotation and the labelling that makes it verifiable.',
                'ترتيب التخزين في الثلاجة وانضباط المخزن الجاف ودوران المخزون والملصقات التي تجعله قابلاً للتحقق.',
                array('food-safety')),
            array('kit-receiving', 'kitchen', 'PROC', 'intermediate', 60,
                'Goods Receiving', 'استلام البضائع',
                'Checking a delivery against the order, temperature and quality checks at the door, and rejecting a delivery properly.',
                'مطابقة التوريد مع أمر الشراء وفحص الحرارة والجودة عند الباب ورفض التوريد بشكل صحيح.',
                array('food-safety', 'cost-control')),
            array('kit-temperature', 'kitchen', 'KIT', 'foundation', 45,
                'Temperature Control', 'ضبط درجات الحرارة',
                'Probing correctly, recording readings, acting on an out of range result and calibrating a thermometer.',
                'القياس الصحيح وتسجيل القراءات والتصرف عند الخروج عن النطاق ومعايرة مقياس الحرارة.',
                array('food-safety')),
            array('kit-allergens', 'kitchen', 'KIT', 'intermediate', 75,
                'Allergen Handling', 'التعامل مع مسببات الحساسية',
                'Identifying allergens on a recipe, preventing contact during preparation and answering a guest allergy question.',
                'تحديد مسببات الحساسية في الوصفة ومنع التلامس أثناء التحضير والرد على سؤال حساسية الضيف.',
                array('food-safety')),
            array('kit-waste', 'kitchen', 'KIT', 'intermediate', 60,
                'Waste Control', 'ضبط الهدر',
                'Measuring waste, separating it by cause, and the kitchen decisions that reduce it without cutting the standard.',
                'قياس الهدر وفصله حسب السبب وقرارات المطبخ التي تقلله دون خفض المعيار.',
                array('cost-control')),
            array('kit-cleaning', 'kitchen', 'KIT', 'foundation', 60,
                'Kitchen Cleaning Schedule', 'جدول تنظيف المطبخ',
                'The daily, weekly and periodic cleaning schedule, who signs for each task and how it is verified.',
                'جدول التنظيف اليومي والأسبوعي والدوري ومن يوقع على كل مهمة وكيف يتم التحقق.',
                array('food-safety')),

            // --------------------------------------------------------- Engineering
            array('eng-fundamentals', 'engineering', 'ENG', 'foundation', 150,
                'Hotel Engineering Fundamentals', 'أساسيات الهندسة الفندقية',
                'The building systems a hotel runs on, the engineering shift and how work reaches the department.',
                'أنظمة المبنى التي يعمل بها الفندق ووردية الهندسة وكيف يصل العمل إلى القسم.',
                array('maintenance-basics')),
            array('eng-preventive', 'engineering', 'ENG', 'intermediate', 120,
                'Preventive Maintenance', 'الصيانة الوقائية',
                'Building a PPM schedule, guest room preventive rounds, and the records that prove the work happened.',
                'بناء جدول الصيانة الوقائية وجولات غرف الضيوف والسجلات التي تثبت تنفيذ العمل.',
                array('maintenance-basics')),
            array('eng-hvac', 'engineering', 'ENG', 'intermediate', 120,
                'HVAC Basics', 'أساسيات التكييف والتهوية',
                'How hotel air conditioning is arranged, filter and coil routines, and diagnosing a common guest room complaint.',
                'كيف يُرتب تكييف الفندق وروتين الفلاتر والملفات وتشخيص شكوى غرفة شائعة.',
                array('maintenance-basics')),
            array('eng-electrical', 'engineering', 'ENG', 'intermediate', 90,
                'Electrical Safety', 'السلامة الكهربائية',
                'Isolation and lock out, working limits for a hotel technician, and recognising an unsafe installation.',
                'العزل والإقفال وحدود العمل لفني الفندق والتعرف على التركيب غير الآمن.',
                array('occupational-safety', 'maintenance-basics')),
            array('eng-plumbing', 'engineering', 'ENG', 'foundation', 90,
                'Plumbing and Water Systems', 'السباكة وأنظمة المياه',
                'Guest room plumbing faults, drainage, hot water delivery and the water safety checks a hotel keeps.',
                'أعطال سباكة غرف الضيوف والصرف وتوصيل الماء الساخن وفحوصات سلامة المياه في الفندق.',
                array('maintenance-basics')),
            array('eng-fire-safety', 'security-and-safety', 'ENG', 'foundation', 90,
                'Fire Safety', 'السلامة من الحريق',
                'Detection and suppression systems, evacuation roles, the fire panel and the weekly and monthly checks.',
                'أنظمة الكشف والإطفاء وأدوار الإخلاء ولوحة الحريق والفحوصات الأسبوعية والشهرية.',
                array('emergency-response', 'occupational-safety')),
            array('eng-work-orders', 'engineering', 'ENG', 'foundation', 60,
                'Maintenance Work Orders', 'أوامر العمل',
                'Raising, prioritising, completing and closing a work order, and what a good fault description contains.',
                'إصدار وترتيب وإنجاز وإغلاق أمر العمل وما يحتويه وصف العطل الجيد.',
                array('maintenance-basics')),
            array('eng-asset-inspection', 'engineering', 'ENG', 'intermediate', 75,
                'Asset Inspection', 'تفتيش الأصول',
                'Walking an asset register, condition rating, photographing a defect and feeding the capital plan.',
                'مراجعة سجل الأصول وتقييم الحالة وتصوير العيب وتغذية الخطة الرأسمالية.',
                array('maintenance-basics')),
            array('eng-emergency', 'security-and-safety', 'ENG', 'intermediate', 90,
                'Emergency Response', 'الاستجابة للطوارئ',
                'The hotel emergency plan, roles on the night shift, guest evacuation assistance and the post incident report.',
                'خطة الطوارئ في الفندق والأدوار في الوردية الليلية ومساعدة الضيوف على الإخلاء وتقرير ما بعد الحادث.',
                array('emergency-response')),

            // ---------------------------------------------------------- Management
            array('mgt-hotel-management', 'management', null, 'leadership', 180,
                'Hotel Management Essentials', 'أساسيات إدارة الفنادق',
                'How the departments connect commercially and operationally, and what a duty manager is accountable for.',
                'كيف ترتبط الأقسام تجارياً وتشغيلياً وما هو المسؤول عنه مدير المناوبة.',
                array('leadership')),
            array('mgt-leadership', 'management', null, 'leadership', 150,
                'Hospitality Leadership', 'القيادة في الضيافة',
                'Setting a standard, holding it without damaging the team, giving feedback and running a pre shift briefing.',
                'وضع المعيار والحفاظ عليه دون الإضرار بالفريق وإعطاء الملاحظات وإدارة اجتماع ما قبل الوردية.',
                array('leadership', 'team-supervision')),
            array('mgt-team-management', 'management', 'HR', 'intermediate', 120,
                'Team Management', 'إدارة الفريق',
                'Rostering, handover discipline, absence handling, and onboarding a new joiner into a live operation.',
                'الجدولة وانضباط التسليم ومعالجة الغياب وإدماج الموظف الجديد في تشغيل قائم.',
                array('team-supervision')),
            array('mgt-finance', 'management', 'FIN', 'advanced', 150,
                'Hospitality Finance', 'المالية الفندقية',
                'Reading a departmental P&L, the difference between revenue and profit, and where a manager actually has control.',
                'قراءة قائمة الأرباح والخسائر للقسم والفرق بين الإيراد والربح وأين يملك المدير تحكماً فعلياً.',
                array('commercial-acumen')),
            array('mgt-revenue', 'revenue-and-reservations', 'REV', 'advanced', 150,
                'Revenue Management', 'إدارة الإيرادات',
                'Demand, segmentation, rate structure, the core metrics and how a pricing decision is made and reviewed.',
                'الطلب والتقسيم وهيكل الأسعار والمؤشرات الأساسية وكيف يُتخذ قرار التسعير ويُراجع.',
                array('revenue-management', 'commercial-acumen')),
            array('mgt-cost-control', 'management', 'FIN', 'intermediate', 120,
                'Cost Control', 'ضبط التكاليف',
                'Purchase to plate control, par levels, stock counts, and separating a cost problem from a pricing problem.',
                'التحكم من الشراء حتى الطبق ومستويات المخزون والجرد وفصل مشكلة التكلفة عن مشكلة التسعير.',
                array('cost-control')),
            array('mgt-guest-experience', 'guest-experience', 'GR', 'intermediate', 120,
                'Guest Experience Management', 'إدارة تجربة الضيف',
                'Mapping the guest journey, choosing the moments that matter and turning feedback into an operational change.',
                'رسم رحلة الضيف واختيار اللحظات المهمة وتحويل الملاحظات إلى تغيير تشغيلي.',
                array('guest-service', 'leadership')),
            array('mgt-quality', 'quality-and-audit', null, 'advanced', 120,
                'Quality Management', 'إدارة الجودة',
                'Writing a standard people can follow, auditing against it, and closing a finding so it does not return.',
                'كتابة معيار يمكن اتباعه والتدقيق عليه وإغلاق الملاحظة بحيث لا تتكرر.',
                array('quality-audit')),
            array('mgt-crisis', 'security-and-safety', null, 'advanced', 120,
                'Crisis Management', 'إدارة الأزمات',
                'Preparing for the incidents a hotel actually faces, the first hour, communication lines and the debrief.',
                'الاستعداد للحوادث التي يواجهها الفندق فعلاً والساعة الأولى وخطوط الاتصال والمراجعة اللاحقة.',
                array('emergency-response', 'leadership')),
            array('mgt-kpis', 'quality-and-audit', null, 'intermediate', 90,
                'Hotel KPIs', 'مؤشرات الأداء الفندقية',
                'What each operating metric measures, how it is calculated and which department can actually move it.',
                'ماذا يقيس كل مؤشر تشغيلي وكيف يُحسب وأي قسم يمكنه تحريكه فعلاً.',
                array('commercial-acumen')),

            // -------------------------------------------------- Digital hospitality
            array('dig-pms', 'revenue-and-reservations', 'FO', 'foundation', 120,
                'Property Management Systems', 'أنظمة إدارة الفنادق',
                'What a PMS holds, the reservation and folio model, night audit dependencies and keeping the data clean.',
                'ماذا يحتوي نظام إدارة الفندق ونموذج الحجز والفاتورة وارتباطات التدقيق الليلي والحفاظ على نظافة البيانات.',
                array('pms-operation')),
            array('dig-channel', 'revenue-and-reservations', 'REV', 'intermediate', 90,
                'Channel Management', 'إدارة قنوات التوزيع',
                'How inventory and rates reach each channel, parity, mapping errors and the daily distribution check.',
                'كيف يصل المخزون والأسعار لكل قناة والتكافؤ وأخطاء الربط والفحص اليومي للتوزيع.',
                array('revenue-management')),
            array('dig-booking-engine', 'revenue-and-reservations', 'REV', 'intermediate', 75,
                'Booking Engine', 'محرك الحجز المباشر',
                'The direct booking path, where guests drop out, and the content and rate setup a booking engine needs.',
                'مسار الحجز المباشر وأين ينسحب الضيوف وإعداد المحتوى والأسعار الذي يحتاجه محرك الحجز.',
                array('revenue-management')),
            array('dig-direct-booking', 'sales-and-marketing', 'SLS', 'intermediate', 90,
                'Direct Booking Strategy', 'استراتيجية الحجز المباشر',
                'Building a reason to book direct, the offer, the landing page and measuring the shift honestly.',
                'بناء سبب للحجز المباشر والعرض وصفحة الهبوط وقياس التحول بصدق.',
                array('revenue-management', 'commercial-acumen')),
            array('dig-ota', 'revenue-and-reservations', 'REV', 'intermediate', 90,
                'OTA Management', 'إدارة وكالات السفر الإلكترونية',
                'Working with online travel agents: content, ranking factors, commission mechanics and dispute handling.',
                'العمل مع وكالات السفر الإلكترونية: المحتوى وعوامل الترتيب وآلية العمولة ومعالجة النزاعات.',
                array('revenue-management')),
            array('dig-analytics', 'revenue-and-reservations', 'REV', 'advanced', 120,
                'Revenue Analytics', 'تحليلات الإيرادات',
                'Building a pickup report, reading pace, segment mix analysis and presenting a forecast you can defend.',
                'بناء تقرير الالتقاط وقراءة الوتيرة وتحليل مزيج الشرائح وتقديم توقع يمكن الدفاع عنه.',
                array('revenue-management', 'commercial-acumen')),
            array('dig-marketing', 'sales-and-marketing', 'SLS', 'intermediate', 120,
                'Hotel Digital Marketing', 'التسويق الرقمي الفندقي',
                'Positioning a property online, the content a hotel needs, campaign basics and measuring what converts.',
                'تموضع الفندق رقمياً والمحتوى الذي يحتاجه وأساسيات الحملات وقياس ما يحقق التحويل.',
                array('commercial-acumen')),
            array('dig-seo', 'sales-and-marketing', 'SLS', 'advanced', 120,
                'Hotel SEO', 'تحسين محركات البحث للفنادق',
                'How a hotel site is found, the pages that matter, bilingual and local search, and technical hygiene.',
                'كيف يُعثر على موقع الفندق والصفحات المهمة والبحث ثنائي اللغة والمحلي والسلامة التقنية.',
                array('commercial-acumen')),
            array('dig-reputation', 'guest-experience', 'GR', 'intermediate', 90,
                'Reputation Management', 'إدارة السمعة',
                'Responding to reviews in both languages, spotting an operational pattern in feedback and closing the loop.',
                'الرد على التقييمات باللغتين واكتشاف النمط التشغيلي في الملاحظات وإغلاق الحلقة.',
                array('guest-service', 'complaint-handling')),
            array('dig-technology', 'management', null, 'foundation', 90,
                'Hospitality Technology Landscape', 'المشهد التقني في الضيافة',
                'The systems a hotel runs, how they connect, what integration actually means and who owns each system.',
                'الأنظمة التي يشغلها الفندق وكيف ترتبط وما معنى التكامل فعلياً ومن يملك كل نظام.',
                array('pms-operation')),
        );
    }

    /** Skills awarded by the catalogue (plan section 20). */
    public static function skills() {
        return array(
            array('guest-service',      'Guest Service',           'خدمة الضيوف',            'FO'),
            array('pms-operation',      'PMS Operation',           'تشغيل نظام إدارة الفندق', 'FO'),
            array('complaint-handling', 'Complaint Handling',      'معالجة الشكاوى',         'FO'),
            array('upselling',          'Upselling',               'البيع الإضافي',          'FO'),
            array('night-audit',        'Night Audit',             'التدقيق الليلي',         'FO'),
            array('guest-data-privacy', 'Guest Data Privacy',      'خصوصية بيانات الضيف',    'FO'),
            array('room-standards',     'Room Standards',          'معايير الغرف',           'HK'),
            array('chemical-safety',    'Chemical Safety',         'سلامة المواد الكيميائية', 'HK'),
            array('food-service',       'Food Service',            'خدمة الطعام',            'FB'),
            array('food-safety',        'Food Safety',             'سلامة الغذاء',           'KIT'),
            array('culinary-basics',    'Culinary Basics',         'أساسيات الطهي',          'KIT'),
            array('cost-control',       'Cost Control',            'ضبط التكاليف',           'FIN'),
            array('maintenance-basics', 'Maintenance Basics',      'أساسيات الصيانة',        'ENG'),
            array('occupational-safety','Occupational Safety',     'السلامة المهنية',        'SEC'),
            array('emergency-response', 'Emergency Response',      'الاستجابة للطوارئ',      'SEC'),
            array('team-supervision',   'Team Supervision',        'الإشراف على الفريق',     'HR'),
            array('leadership',         'Leadership',              'القيادة',                'HR'),
            array('commercial-acumen',  'Commercial Acumen',       'الفطنة التجارية',        'REV'),
            array('revenue-management', 'Revenue Management',      'إدارة الإيرادات',        'REV'),
            array('quality-audit',      'Quality Audit',           'تدقيق الجودة',           null),
        );
    }

    /** Programs group courses into a qualification (plan section 6). */
    public static function programs() {
        return array(
            array('front-office-professional', 'Hotel Front Office Professional', 'محترف مكتب الاستقبال الفندقي',
                'front-office', 'intermediate',
                'A complete front office qualification covering arrival, departure, guest handling, upselling and the night audit.',
                'مؤهل متكامل لمكتب الاستقبال يغطي الوصول والمغادرة والتعامل مع الضيوف والبيع الإضافي والتدقيق الليلي.',
                array('fo-fundamentals', 'fo-reception-operations', 'fo-check-in', 'fo-check-out',
                    'dig-pms', 'fo-complaints', 'fo-upselling', 'fo-night-audit')),
            array('housekeeping-professional', 'Housekeeping Professional', 'محترف التدبير الفندقي',
                'housekeeping', 'intermediate',
                'Room standards from the first clean to inspection, including chemical safety, laundry and productivity.',
                'معايير الغرف من أول تنظيف حتى التفتيش، بما في ذلك سلامة المواد والمغسلة والإنتاجية.',
                array('hk-fundamentals', 'hk-room-cleaning', 'hk-bed-making', 'hk-bathroom',
                    'hk-chemical-safety', 'hk-inspection', 'hk-productivity')),
            array('food-safety-certified', 'Food Safety Certified', 'معتمد في سلامة الغذاء',
                'kitchen', 'intermediate',
                'Food safety for kitchen and service teams, covering temperature control, storage, allergens and HACCP basics.',
                'سلامة الغذاء لفرق المطبخ والخدمة، وتغطي ضبط الحرارة والتخزين ومسببات الحساسية وأساسيات الهاسب.',
                array('kit-food-safety', 'kit-storage', 'kit-temperature', 'kit-allergens',
                    'fb-haccp', 'fb-hygiene', 'kit-cleaning')),
            array('hospitality-supervisor', 'Hospitality Supervisor', 'مشرف الضيافة',
                'management', 'advanced',
                'The step from doing the work to leading it: standards, briefings, rosters, feedback and quality auditing.',
                'الانتقال من التنفيذ إلى القيادة: المعايير والاجتماعات والجداول والملاحظات وتدقيق الجودة.',
                array('mgt-leadership', 'mgt-team-management', 'mgt-quality', 'mgt-guest-experience', 'mgt-kpis')),
            array('revenue-and-distribution', 'Revenue and Distribution', 'الإيرادات والتوزيع',
                'revenue-and-reservations', 'advanced',
                'Commercial control of a property: pricing, channels, direct booking and the analytics behind the decisions.',
                'التحكم التجاري في الفندق: التسعير والقنوات والحجز المباشر والتحليلات خلف القرارات.',
                array('mgt-revenue', 'dig-channel', 'dig-ota', 'dig-booking-engine',
                    'dig-direct-booking', 'dig-analytics')),
            array('hotel-safety-essentials', 'Hotel Safety Essentials', 'أساسيات السلامة الفندقية',
                'security-and-safety', 'foundation',
                'The safety training every hotel employee needs regardless of department.',
                'تدريب السلامة الذي يحتاجه كل موظف في الفندق بغض النظر عن قسمه.',
                array('eng-fire-safety', 'eng-emergency', 'hk-chemical-safety', 'kit-knife-safety', 'mgt-crisis')),
        );
    }

    /** Career ladders (plan section 10). */
    public static function paths() {
        return array(
            array('front-office-career', 'Front Office Career Path', 'المسار المهني لمكتب الاستقبال', 'FO',
                'From a first day on the desk to running the department.',
                'من أول يوم في المكتب حتى إدارة القسم.',
                array(
                    array('Introduction to Front Office', 'مقدمة في مكتب الاستقبال', 'fo-associate',
                        array('fo-fundamentals', 'fo-telephone')),
                    array('Front Office Associate', 'موظف استقبال', 'fo-associate',
                        array('fo-check-in', 'fo-check-out', 'fo-guest-registration', 'dig-pms')),
                    array('Senior Front Office Associate', 'موظف استقبال أول', 'fo-senior-associate',
                        array('fo-room-assignment', 'fo-upselling', 'fo-vip', 'fo-guest-privacy')),
                    array('Front Office Supervisor', 'مشرف مكتب الاستقبال', 'fo-supervisor',
                        array('fo-complaints', 'fo-night-audit', 'mgt-team-management')),
                    array('Assistant Front Office Manager', 'مساعد مدير مكتب الاستقبال', 'fo-assistant-manager',
                        array('mgt-leadership', 'mgt-kpis')),
                    array('Front Office Manager', 'مدير مكتب الاستقبال', 'fo-manager',
                        array('mgt-hotel-management', 'mgt-revenue', 'mgt-quality')),
                )),
            array('housekeeping-career', 'Housekeeping Career Path', 'المسار المهني للتدبير الفندقي', 'HK',
                'From room attendant to executive housekeeper.',
                'من عامل غرف إلى مدير التدبير الفندقي.',
                array(
                    array('Room Attendant', 'عامل غرف', 'hk-room-attendant',
                        array('hk-fundamentals', 'hk-room-cleaning', 'hk-bed-making', 'hk-bathroom', 'hk-chemical-safety')),
                    array('Senior Room Attendant', 'عامل غرف أول', 'hk-senior-attendant',
                        array('hk-turnaround', 'hk-deep-cleaning', 'hk-lost-found')),
                    array('Housekeeping Supervisor', 'مشرف التدبير الفندقي', 'hk-supervisor',
                        array('hk-inspection', 'hk-public-areas', 'mgt-team-management')),
                    array('Assistant Executive Housekeeper', 'مساعد مدير التدبير الفندقي', 'hk-assistant-exec',
                        array('hk-productivity', 'hk-laundry', 'mgt-leadership')),
                    array('Executive Housekeeper', 'مدير التدبير الفندقي', 'hk-executive',
                        array('mgt-quality', 'mgt-cost-control', 'mgt-kpis')),
                )),
            array('food-beverage-career', 'Food and Beverage Career Path', 'المسار المهني للأغذية والمشروبات', 'FB',
                'From the floor to running a restaurant.',
                'من الصالة إلى إدارة المطعم.',
                array(
                    array('F&B Associate', 'موظف أغذية ومشروبات', 'fb-associate',
                        array('fb-restaurant-service', 'fb-guest-greeting', 'fb-hygiene', 'fb-food-safety')),
                    array('Captain', 'كابتن صالة', 'fb-captain',
                        array('fb-table-service', 'fb-beverage', 'fb-pos', 'fb-upselling')),
                    array('F&B Supervisor', 'مشرف الأغذية والمشروبات', 'fb-supervisor',
                        array('fb-banquet', 'fb-haccp', 'mgt-team-management')),
                    array('Assistant Restaurant Manager', 'مساعد مدير المطعم', 'fb-assistant-manager',
                        array('mgt-cost-control', 'mgt-leadership')),
                    array('Restaurant Manager', 'مدير المطعم', 'fb-restaurant-manager',
                        array('mgt-finance', 'mgt-quality', 'mgt-guest-experience')),
                )),
        );
    }

    /** Lesson plan applied to every course, so no course ships empty. */
    private function lesson_plan($course) {
        list($code, $category, $dept, $level, $minutes, $title_en, $title_ar, $sum_en, $sum_ar) = $course;
        return array(
            array('Orientation', 'التهيئة', array(
                array('text', 'Why this matters', 'لماذا يهم هذا الدرس',
                    'Where this work sits in the hotel day and what depends on it being done correctly.',
                    'أين يقع هذا العمل في يوم الفندق وما الذي يعتمد على تنفيذه بشكل صحيح.', 8),
                array('text', 'What you will be able to do', 'ما ستكون قادراً على فعله',
                    'The specific tasks you should be able to carry out unaided after this course.',
                    'المهام المحددة التي ينبغي أن تؤديها دون مساعدة بعد هذه الدورة.', 5),
            )),
            array('The standard', 'المعيار', array(
                array('video', 'The procedure demonstrated', 'عرض الإجراء عملياً',
                    'A walkthrough of the procedure as it is carried out on shift, step by step.',
                    'شرح عملي للإجراء كما يُنفذ أثناء الوردية، خطوة بخطوة.', 14),
                array('text', 'The written standard', 'المعيار المكتوب',
                    'The standard in writing, with the points an inspection or audit will look at.',
                    'المعيار مكتوباً مع النقاط التي سينظر إليها التفتيش أو التدقيق.', 12),
                array('pdf', 'Reference sheet', 'ورقة مرجعية',
                    'A one page summary to keep at the workstation.',
                    'ملخص من صفحة واحدة للاحتفاظ به في موقع العمل.', 5),
            )),
            array('In practice', 'في الممارسة', array(
                array('text', 'Common mistakes', 'الأخطاء الشائعة',
                    'The errors that appear most often in this task and what causes each one.',
                    'الأخطاء الأكثر تكراراً في هذه المهمة وسبب كل منها.', 10),
                array('text', 'Working under pressure', 'العمل تحت الضغط',
                    'How to keep the standard when the operation is busy, short staffed or running late.',
                    'كيف تحافظ على المعيار عندما يكون التشغيل مزدحماً أو ناقص الطاقم أو متأخراً.', 10),
                array('checklist', 'Practice checklist', 'قائمة التحقق التطبيقية',
                    'Work through the task against the checklist and confirm each point.',
                    'نفّذ المهمة وفق قائمة التحقق وأكد كل بند.', 12),
            )),
            array('Assessment', 'التقييم', array(
                array('text', 'Review before the quiz', 'مراجعة قبل الاختبار',
                    'A short recap of the points the assessment covers.',
                    'مراجعة موجزة للنقاط التي يغطيها التقييم.', 8),
            )),
        );
    }

    public function run($db) {
        $this->boot($db);
        $written = 0;

        // Categories
        $category_ids = array();
        foreach (self::categories() as $c) {
            list($code, $en, $ar, $dept, $desc_en, $desc_ar) = $c;
            $id = $this->upsert('ha_category', array('code' => $code), array(
                'slug_en'    => $code,
                'slug_ar'    => $this->slugify($ar),
                'sort_order' => count($category_ids),
                'status'     => 'active',
            ));
            $category_ids[$code] = $id;
            $this->upsert('ha_category_translation', array('category_id' => $id, 'locale' => 'en'),
                array('name' => $en, 'description' => $desc_en));
            $this->upsert('ha_category_translation', array('category_id' => $id, 'locale' => 'ar'),
                array('name' => $ar, 'description' => $desc_ar));
            $written += 3;
        }

        // Skills
        $skill_ids = array();
        foreach (self::skills() as $s) {
            list($code, $en, $ar, $dept) = $s;
            $skill_ids[$code] = $this->upsert('ha_skill', array('code' => $code), array(
                'name_en' => $en, 'name_ar' => $ar, 'department_code' => $dept, 'status' => 'active',
            ));
            $written++;
        }

        // Instructors, matched to the department a course belongs to.
        $instructors = array();
        foreach (array('FO', 'HK', 'FB') as $dept) {
            $email = 'instructor.' . strtolower($dept) . '@hospitalityacademy.sa';
            $row = $this->db->get_where('users', array('email' => $email))->row_array();
            if ($row) {
                $instructors[$dept] = (int) $row['id'];
            }
        }
        $default_instructor = $instructors ? current($instructors) : null;

        // Courses
        $course_ids = array();
        foreach (self::courses() as $course) {
            list($code, $category, $dept, $level, $minutes, $title_en, $title_ar,
                $sum_en, $sum_ar, $skills) = $course;

            $instructor = ($dept && isset($instructors[$dept])) ? $instructors[$dept] : $default_instructor;

            $course_id = $this->upsert('ha_course', array('code' => $code), array(
                'slug_en'              => $code,
                'slug_ar'              => $this->slugify($title_ar),
                'category_id'          => $category_ids[$category],
                'department_code'      => $dept,
                'instructor_user_id'   => $instructor,
                'level'                => $level,
                'duration_minutes'     => $minutes,
                'is_free'              => 1,
                'price'                => 0,
                'certificate_eligible' => 1,
                'pass_percentage'      => 70,
                'status'               => 'published',
                'published_at'         => $this->now,
            ));
            $course_ids[$code] = $course_id;
            $written++;

            $this->upsert('ha_course_translation', array('course_id' => $course_id, 'locale' => 'en'), array(
                'title'             => $title_en,
                'short_description' => $sum_en,
                'description'       => $sum_en . ' The course is built around the procedure as it is actually carried out on shift, with a written standard, the common mistakes, and a checklist to work through.',
                'requirements'      => 'No prior study is required. Access to the relevant work area is useful for the practice checklist.',
            ));
            $this->upsert('ha_course_translation', array('course_id' => $course_id, 'locale' => 'ar'), array(
                'title'             => $title_ar,
                'short_description' => $sum_ar,
                'description'       => $sum_ar . ' بُنيت الدورة حول الإجراء كما يُنفذ فعلياً أثناء الوردية، مع معيار مكتوب والأخطاء الشائعة وقائمة تحقق للتطبيق.',
                'requirements'      => 'لا يلزم دراسة سابقة. الوصول إلى منطقة العمل المعنية مفيد لقائمة التحقق التطبيقية.',
            ));
            $written += 2;

            // Skills the course awards
            foreach ($skills as $skill_code) {
                if (isset($skill_ids[$skill_code])) {
                    $this->link('ha_course_skill', array(
                        'course_id' => $course_id, 'skill_id' => $skill_ids[$skill_code],
                    ), array('awards_level' => 'competent'));
                }
            }

            // Outcomes, in both languages
            $this->db->where('course_id', $course_id)->delete('ha_course_outcome');
            $outcomes = array(
                array('en' => 'Carry out the procedure to the written standard without supervision.',
                      'ar' => 'تنفيذ الإجراء وفق المعيار المكتوب دون إشراف.'),
                array('en' => 'Recognise the common failures in this task and correct them.',
                      'ar' => 'التعرف على الإخفاقات الشائعة في هذه المهمة وتصحيحها.'),
                array('en' => 'Complete the associated checklist and record the result.',
                      'ar' => 'إكمال قائمة التحقق المرتبطة وتسجيل النتيجة.'),
                array('en' => 'Know when the situation must be escalated to a supervisor.',
                      'ar' => 'معرفة متى يجب تصعيد الموقف إلى المشرف.'),
            );
            foreach ($outcomes as $i => $o) {
                $this->db->insert('ha_course_outcome', array(
                    'course_id' => $course_id, 'locale' => 'en', 'body' => $o['en'], 'sort_order' => $i));
                $this->db->insert('ha_course_outcome', array(
                    'course_id' => $course_id, 'locale' => 'ar', 'body' => $o['ar'], 'sort_order' => $i));
                $written += 2;
            }

            // FAQ, used by the course page and its FAQ schema
            $this->db->where('course_id', $course_id)->delete('ha_course_faq');
            $faqs = array(
                array('q_en' => 'How long does this course take?',
                      'a_en' => 'About ' . $minutes . ' minutes of study, which can be split across shifts.',
                      'q_ar' => 'كم تستغرق هذه الدورة؟',
                      'a_ar' => 'نحو ' . $minutes . ' دقيقة من الدراسة، ويمكن تقسيمها على عدة ورديات.'),
                array('q_en' => 'Is a certificate issued?',
                      'a_en' => 'Yes. A certificate with a verification code is issued once the course and its assessment are passed.',
                      'q_ar' => 'هل تُصدر شهادة؟',
                      'a_ar' => 'نعم. تُصدر شهادة تحمل رمز تحقق بعد إكمال الدورة واجتياز تقييمها.'),
                array('q_en' => 'Is the course available in Arabic?',
                      'a_en' => 'Yes. Every lesson exists in English and Arabic, and the Arabic version is laid out right to left.',
                      'q_ar' => 'هل الدورة متاحة بالعربية؟',
                      'a_ar' => 'نعم. كل درس متوفر بالإنجليزية والعربية، والنسخة العربية معروضة من اليمين إلى اليسار.'),
            );
            foreach ($faqs as $i => $f) {
                $this->db->insert('ha_course_faq', array('course_id' => $course_id, 'locale' => 'en',
                    'question' => $f['q_en'], 'answer' => $f['a_en'], 'sort_order' => $i));
                $this->db->insert('ha_course_faq', array('course_id' => $course_id, 'locale' => 'ar',
                    'question' => $f['q_ar'], 'answer' => $f['a_ar'], 'sort_order' => $i));
                $written += 2;
            }

            // Sections and lessons
            $this->db->where('course_id', $course_id)->delete('ha_lesson');
            $this->db->where('course_id', $course_id)->delete('ha_course_section');
            $order = 0;
            foreach ($this->lesson_plan($course) as $s_index => $section) {
                list($sec_en, $sec_ar, $lessons) = $section;
                $this->db->insert('ha_course_section', array(
                    'course_id' => $course_id, 'title_en' => $sec_en, 'title_ar' => $sec_ar,
                    'sort_order' => $s_index,
                ));
                $section_id = (int) $this->db->insert_id();
                $written++;

                foreach ($lessons as $l) {
                    list($type, $l_en, $l_ar, $body_en, $body_ar, $mins) = $l;
                    $rule = ($type === 'video') ? 'watch_percentage' : (($type === 'checklist') ? 'acknowledge' : 'open');
                    $this->db->insert('ha_lesson', array(
                        'course_id'                 => $course_id,
                        'section_id'                => $section_id,
                        'lesson_type'               => $type,
                        'duration_seconds'          => $mins * 60,
                        'is_mandatory'              => 1,
                        'is_preview'                => ($order === 0) ? 1 : 0,
                        'completion_rule'           => $rule,
                        'required_watch_percentage' => 90,
                        'sort_order'                => $order++,
                        'status'                    => 'published',
                        'created_at'                => $this->now,
                        'updated_at'                => $this->now,
                    ));
                    $lesson_id = (int) $this->db->insert_id();
                    $written++;

                    $this->db->insert('ha_lesson_translation', array(
                        'lesson_id' => $lesson_id, 'locale' => 'en',
                        'title' => $l_en . ': ' . $title_en,
                        'objective' => $body_en,
                        'body' => '<p>' . $body_en . '</p><p>' . $sum_en . '</p>',
                    ));
                    $this->db->insert('ha_lesson_translation', array(
                        'lesson_id' => $lesson_id, 'locale' => 'ar',
                        'title' => $l_ar . ': ' . $title_ar,
                        'objective' => $body_ar,
                        'body' => '<p>' . $body_ar . '</p><p>' . $sum_ar . '</p>',
                    ));
                    $written += 2;
                }
            }
        }

        // Programs
        foreach (self::programs() as $p) {
            list($code, $en, $ar, $category, $level, $desc_en, $desc_ar, $courses) = $p;
            $hours = 0;
            foreach ($courses as $c) {
                foreach (self::courses() as $definition) {
                    if ($definition[0] === $c) {
                        $hours += $definition[4] / 60;
                    }
                }
            }
            $program_id = $this->upsert('ha_program', array('code' => $code), array(
                'slug_en'        => $code,
                'slug_ar'        => $this->slugify($ar),
                'category_id'    => $category_ids[$category],
                'level'          => $level,
                'duration_hours' => round($hours, 2),
                'completion_rule'=> 'all_courses',
                'status'         => 'published',
                'published_at'   => $this->now,
            ));
            $written++;
            $this->upsert('ha_program_translation', array('program_id' => $program_id, 'locale' => 'en'),
                array('title' => $en, 'short_description' => $desc_en, 'description' => $desc_en));
            $this->upsert('ha_program_translation', array('program_id' => $program_id, 'locale' => 'ar'),
                array('title' => $ar, 'short_description' => $desc_ar, 'description' => $desc_ar));
            $written += 2;

            $this->db->where('program_id', $program_id)->delete('ha_program_course');
            foreach ($courses as $i => $c) {
                if (!isset($course_ids[$c])) {
                    continue;
                }
                $this->db->insert('ha_program_course', array(
                    'program_id' => $program_id, 'course_id' => $course_ids[$c],
                    'sort_order' => $i, 'is_mandatory' => 1,
                ));
                $written++;
            }
        }

        // Career paths
        $job_roles = array();
        foreach ($this->db->get('ha_job_role')->result_array() as $jr) {
            $job_roles[$jr['code']] = (int) $jr['id'];
        }

        foreach (self::paths() as $path) {
            list($code, $en, $ar, $dept, $sum_en, $sum_ar, $steps) = $path;
            $path_id = $this->upsert('ha_learning_path', array('code' => $code), array(
                'slug_en'         => $code,
                'slug_ar'         => $this->slugify($ar),
                'title_en'        => $en,
                'title_ar'        => $ar,
                'summary_en'      => $sum_en,
                'summary_ar'      => $sum_ar,
                'description_en'  => $sum_en . ' Each step lists the courses to complete before moving on.',
                'description_ar'  => $sum_ar . ' تسرد كل مرحلة الدورات الواجب إكمالها قبل الانتقال للتالية.',
                'department_code' => $dept,
                'status'          => 'published',
                'published_at'    => $this->now,
            ));
            $written++;

            $this->db->where('path_id', $path_id)->delete('ha_path_step');
            foreach ($steps as $i => $step) {
                list($step_en, $step_ar, $job_code, $step_courses) = $step;
                $this->db->insert('ha_path_step', array(
                    'path_id'     => $path_id,
                    'job_role_id' => isset($job_roles[$job_code]) ? $job_roles[$job_code] : null,
                    'title_en'    => $step_en,
                    'title_ar'    => $step_ar,
                    'sort_order'  => $i,
                ));
                $step_id = (int) $this->db->insert_id();
                $written++;
                foreach ($step_courses as $j => $c) {
                    if (!isset($course_ids[$c])) {
                        continue;
                    }
                    $this->db->insert('ha_path_step_item', array(
                        'step_id'      => $step_id,
                        'item_type'    => 'course',
                        'item_id'      => $course_ids[$c],
                        'is_mandatory' => 1,
                        'sort_order'   => $j,
                    ));
                    $written++;
                }
            }
        }

        // Retire categories the catalogue no longer declares.
        //
        // The seeder upserts on `code`, so renaming or splitting a category
        // creates the new row and leaves the old one behind -- present in the
        // course filter, empty, and indistinguishable from a real domain. This
        // runs last, after every course and programme has been reassigned, so
        // nothing still points at what it removes. ha_course.category_id and
        // ha_program.category_id are ON DELETE SET NULL, so a stale row that
        // somehow still held content would orphan it rather than delete it.
        $declared = array();
        foreach (self::categories() as $c) {
            $declared[] = $c[0];
        }
        $stale = $this->db->select('id, code')->from('ha_category')
            ->where_not_in('code', $declared)->get()->result_array();
        foreach ($stale as $row) {
            $held = $this->db->where('category_id', $row['id'])->count_all_results('ha_course');
            if ($held > 0) {
                // Refuse rather than silently strand courses: a category with
                // content is a reassignment the catalogue forgot to make.
                throw new RuntimeException(sprintf(
                    'Category "%s" is no longer declared but still holds %d course(s). '
                    . 'Reassign them in Seed_curriculum::courses() before removing it.',
                    $row['code'], $held));
            }
            $this->db->where('id', $row['id'])->delete('ha_category');
            $written++;
        }

        return $written;
    }
}
