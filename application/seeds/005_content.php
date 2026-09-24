<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';

/**
 * Public website content, SEO centre records and the topic hub.
 * Plan sections 25, 26, 27, 28, 29, 30, 33.
 *
 * Written to be answerable, not keyword stuffed:
 *  - every page carries a question-shaped FAQ set so an answer engine has a
 *    direct, quotable answer (AEO),
 *  - every page has EN and AR metadata, canonical, hreflang inputs and
 *    JSON-LD (SEO),
 *  - city pages carry genuinely local content about the hotel market in that
 *    city rather than a swapped city name (GEO), which is what plan section 29
 *    asks for when it forbids thin doorway pages.
 *
 * No statistic, accreditation, endorsement or customer claim is invented
 * anywhere in this file. Testimonials are deliberately left unseeded because
 * a real quote requires a real, consenting person.
 */
class Seed_content extends Ha_seeder {

    const BRAND_EN = 'Hospitality Academy';
    const BRAND_AR = 'أكاديمية الضيافة';

    // ---------------------------------------------------------------- authors

    public static function authors() {
        return array(
            array('hospitality-academy-editorial', 'Hospitality Academy Editorial', 'هيئة تحرير أكاديمية الضيافة',
                'Editorial Team', 'فريق التحرير',
                'The editorial team writes and reviews every course outline, standard operating procedure and article published by the academy, working with the department heads whose work the content describes.',
                'يكتب فريق التحرير ويراجع كل مخطط دورة وإجراء تشغيل قياسي ومقال تنشره الأكاديمية، بالعمل مع رؤساء الأقسام الذين يصف المحتوى أعمالهم.'),
            array('academy-operations-desk', 'Academy Operations Desk', 'مكتب عمليات الأكاديمية',
                'Operations', 'العمليات',
                'The operations desk documents how hotel departments actually run a shift, and turns that into the procedures and checklists used in training.',
                'يوثق مكتب العمليات كيف تدير أقسام الفنادق الوردية فعلياً، ويحوّل ذلك إلى إجراءات وقوائم تحقق تُستخدم في التدريب.'),
        );
    }

    // ------------------------------------------------------------------ pages

    /**
     * code, slug_en, slug_ar, template,
     * EN [title, subtitle, body, cta_label, cta_url],
     * AR [...]
     */
    public static function pages() {
        return array(

            array('home', '', '', 'home',
                array(
                    'title' => 'Hospitality Academy — Learn. Certify. Standardize. Perform.',
                    'subtitle' => 'Role-based hotel training, standard operating procedures and workforce certification for hospitality teams in Saudi Arabia, in Arabic and English.',
                    'body' => '<h2>Training built around the shift, not around a syllabus</h2>'
                        . '<p>Hospitality Academy teaches the work a hotel actually does. Every course follows a procedure as it is carried out on shift: the sequence, the written standard, the mistakes that appear most often, and a checklist the learner works through in their own department. Courses run in Arabic and English, and the Arabic version is laid out right to left rather than mirrored from an English page.</p>'
                        . '<h2>What the academy covers</h2>'
                        . '<p>The catalogue is organised by hotel department, so a room attendant, a front desk agent, a commis chef and a maintenance technician each have a path that belongs to them. Front office, housekeeping, food and beverage, kitchen, sales and marketing, revenue and reservations, guest experience, quality and audit, security and safety, engineering and hotel management are all covered, along with the safety training every employee needs regardless of department.</p>'
                        . '<h2>Standard operating procedures as a first-class module</h2>'
                        . '<p>Procedures are not attachments in a shared drive. Each standard operating procedure is a versioned document with a purpose, scope, responsibilities, the procedure itself, a checklist, safety notes, a quality standard and an escalation route. When a procedure changes, the version is recorded and the people who need to read it are asked to acknowledge it again.</p>'
                        . '<h2>Certification a third party can check</h2>'
                        . '<p>A learner who completes a course and passes its assessment receives a certificate carrying a certificate number and a verification code. Anyone holding that code can check it on the public verification page and see whether it is valid, expired or revoked.</p>'
                        . '<h2>For hotels and groups</h2>'
                        . '<p>A hotel or group can assign training by property, department, job role or individual, set a due date, and see completion and outstanding items by department. Procedure acknowledgement is tracked the same way, so a manager can see who has read the current version and who has not.</p>',
                    'cta_label' => 'Browse the course catalogue',
                    'cta_url' => 'courses',
                ),
                array(
                    'title' => 'أكاديمية الضيافة — تعلّم، اعتمد، وحّد المعايير، وطوّر الأداء',
                    'subtitle' => 'تدريب فندقي قائم على الدور الوظيفي، وإجراءات تشغيل قياسية، واعتماد للكوادر في السعودية، بالعربية والإنجليزية.',
                    'body' => '<h2>تدريب مبني على الوردية، لا على منهج نظري</h2>'
                        . '<p>تُعلّم أكاديمية الضيافة العمل الذي يؤديه الفندق فعلياً. كل دورة تتبع إجراءً كما يُنفذ أثناء الوردية: التسلسل، والمعيار المكتوب، والأخطاء الأكثر تكراراً، وقائمة تحقق ينفذها المتدرب في قسمه. الدورات متاحة بالعربية والإنجليزية، والنسخة العربية مصممة من اليمين إلى اليسار لا منسوخة عن صفحة إنجليزية.</p>'
                        . '<h2>ما الذي تغطيه الأكاديمية</h2>'
                        . '<p>الكتالوج مُنظَّم حسب أقسام الفندق، بحيث يكون لعامل الغرف وموظف الاستقبال ومساعد الطاهي وفني الصيانة مسار يخص كلاً منهم. تغطي الأكاديمية مكتب الاستقبال والتدبير الفندقي والأغذية والمشروبات والمطبخ والمبيعات والتسويق والإيرادات والحجوزات وتجربة الضيف والجودة والتدقيق والأمن والسلامة والهندسة وإدارة الفنادق، إضافة إلى تدريب السلامة الذي يحتاجه كل موظف مهما كان قسمه.</p>'
                        . '<h2>إجراءات التشغيل القياسية كوحدة مستقلة</h2>'
                        . '<p>الإجراءات ليست ملفات مرفقة في مجلد مشترك. كل إجراء تشغيل قياسي وثيقة لها إصدار، تتضمن الغرض والنطاق والمسؤوليات والإجراء نفسه وقائمة تحقق وملاحظات السلامة ومعيار الجودة ومسار التصعيد. وعند تغيّر الإجراء يُسجَّل الإصدار ويُطلب من المعنيين الإقرار به من جديد.</p>'
                        . '<h2>اعتماد يمكن لطرف ثالث التحقق منه</h2>'
                        . '<p>المتدرب الذي يكمل الدورة ويجتاز تقييمها يحصل على شهادة تحمل رقم شهادة ورمز تحقق. ويستطيع أي شخص يملك الرمز التحقق منه عبر صفحة التحقق العامة ومعرفة ما إذا كانت الشهادة سارية أو منتهية أو ملغاة.</p>'
                        . '<h2>للفنادق والمجموعات</h2>'
                        . '<p>يستطيع الفندق أو المجموعة إسناد التدريب حسب الفندق أو القسم أو المسمى الوظيفي أو الموظف، وتحديد تاريخ استحقاق، ومتابعة نسب الإكمال والبنود المتبقية لكل قسم. ويُتابَع الإقرار بالإجراءات بالطريقة نفسها، فيرى المدير من قرأ الإصدار الحالي ومن لم يقرأه.</p>',
                    'cta_label' => 'تصفح كتالوج الدورات',
                    'cta_url' => 'courses',
                )),

            array('about', 'about', 'عن-الأكاديمية', 'standard',
                array(
                    'title' => 'About Hospitality Academy',
                    'subtitle' => 'Who writes the training, how it is reviewed, and what the academy will and will not claim.',
                    'body' => '<h2>What the academy is</h2>'
                        . '<p>Hospitality Academy is a training and procedure platform for hotel teams. It holds the courses a hotel employee needs for their role, the standard operating procedures their department works to, the assessments that check they can do the work, and the certificates that record it.</p>'
                        . '<h2>How the content is written</h2>'
                        . '<p>Content starts from the procedure as it is performed, not from a textbook. A draft is written with the department it describes, reviewed internally, and only then published. Each procedure carries an author, an approver, an effective date and a review date. When it changes, the previous version stays readable so an auditor can see what was in force at any point in time.</p>'
                        . '<h2>Arabic and English as equals</h2>'
                        . '<p>Every course, lesson, procedure and public page exists in Arabic and in English as separate, edited content. The Arabic interface is a right-to-left layout, not an English layout flipped. A learner can change language at any point without losing their progress.</p>'
                        . '<h2>What we do not claim</h2>'
                        . '<p>The academy does not publish success statistics, pass rates, employer endorsements or accreditation claims on this site. Where a certificate is issued, what it records is precisely stated: who completed which course, on what date, with what score, and whether it remains valid. Anything beyond that would be a claim we cannot evidence, so we do not make it.</p>',
                    'cta_label' => 'See how certification works',
                    'cta_url' => 'certificates',
                ),
                array(
                    'title' => 'عن أكاديمية الضيافة',
                    'subtitle' => 'من يكتب التدريب، وكيف يُراجع، وما الذي تدّعيه الأكاديمية وما الذي لا تدّعيه.',
                    'body' => '<h2>ما هي الأكاديمية</h2>'
                        . '<p>أكاديمية الضيافة منصة تدريب وإجراءات لفرق الفنادق. تضم الدورات التي يحتاجها موظف الفندق لدوره، وإجراءات التشغيل القياسية التي يعمل بها قسمه، والتقييمات التي تتحقق من قدرته على أداء العمل، والشهادات التي توثق ذلك.</p>'
                        . '<h2>كيف يُكتب المحتوى</h2>'
                        . '<p>يبدأ المحتوى من الإجراء كما يُنفَّذ، لا من كتاب دراسي. تُكتب المسودة مع القسم الذي تصفه، وتُراجَع داخلياً، ثم تُنشر بعد ذلك. ولكل إجراء مؤلف ومعتمِد وتاريخ سريان وتاريخ مراجعة. وعند التغيير يبقى الإصدار السابق قابلاً للقراءة ليتمكن المدقق من معرفة ما كان سارياً في أي وقت.</p>'
                        . '<h2>العربية والإنجليزية بالتساوي</h2>'
                        . '<p>كل دورة ودرس وإجراء وصفحة عامة موجودة بالعربية والإنجليزية كمحتوى منفصل ومحرَّر. والواجهة العربية مصممة من اليمين إلى اليسار، لا واجهة إنجليزية معكوسة. ويستطيع المتدرب تغيير اللغة في أي لحظة دون فقدان تقدمه.</p>'
                        . '<h2>ما الذي لا ندّعيه</h2>'
                        . '<p>لا تنشر الأكاديمية على هذا الموقع إحصاءات نجاح أو نسب اجتياز أو توصيات من جهات توظيف أو ادعاءات اعتماد. وحين تُصدر شهادة، يُذكر بدقة ما توثقه: من أكمل أي دورة، وفي أي تاريخ، وبأي درجة، وهل ما زالت سارية. وأي شيء يتجاوز ذلك ادعاء لا نملك إثباته، فلا نقوله.</p>',
                    'cta_label' => 'اطّلع على آلية الاعتماد',
                    'cta_url' => 'certificates',
                )),

            array('for-hotels', 'hotels', 'للفنادق', 'for_hotels',
                array(
                    'title' => 'Hotel Workforce Training for Saudi Properties',
                    'subtitle' => 'Assign training by property, department, job role or individual. Track completion, procedure acknowledgement and certification in one place.',
                    'body' => '<h2>The problem this solves</h2>'
                        . '<p>Most hotels already know what their teams should be doing. The difficulty is proving that each person has been trained on the current standard, and finding out who has not before an inspection or an incident does it for you.</p>'
                        . '<h2>How assignment works</h2>'
                        . '<p>A training assignment names what has to be completed, who it applies to and by when. The target can be an individual, a department, a whole property, a job role or the entire organization, so a new fire safety procedure can reach every employee in one action. Reminders go out before the due date, and overdue items escalate to the line manager.</p>'
                        . '<h2>Procedure acknowledgement</h2>'
                        . '<p>When a procedure is published or revised, the people it applies to are asked to read and acknowledge it. The acknowledgement records the person, the exact version, the timestamp and the originating address. A manager sees the count that matters: how many are required, how many have acknowledged, and exactly who is missing.</p>'
                        . '<h2>What a manager sees</h2>'
                        . '<p>Completion by department and by property, overdue training by person, certificates approaching expiry, assessment results, and the skills each employee has evidence for. A department manager sees their own team, a property manager sees their property, and a group administrator sees the organization. Nobody sees another organization.</p>'
                        . '<h2>Getting started</h2>'
                        . '<p>Tell us the properties, the departments and roughly how many employees per department. We will come back with the structure set up and the mandatory training already assigned, so the first thing your team sees is their own list rather than an empty catalogue.</p>',
                    'cta_label' => 'Talk to the academy',
                    'cta_url' => 'contact',
                ),
                array(
                    'title' => 'تدريب كوادر الفنادق للمنشآت في السعودية',
                    'subtitle' => 'أسند التدريب حسب الفندق أو القسم أو المسمى الوظيفي أو الموظف. وتابع الإكمال والإقرار بالإجراءات والاعتماد في مكان واحد.',
                    'body' => '<h2>المشكلة التي يعالجها هذا</h2>'
                        . '<p>معظم الفنادق تعرف أصلاً ما ينبغي أن تفعله فرقها. الصعوبة في إثبات أن كل شخص دُرِّب على المعيار الحالي، ومعرفة من لم يُدرَّب قبل أن يكتشف ذلك تفتيشٌ أو حادث.</p>'
                        . '<h2>كيف يعمل الإسناد</h2>'
                        . '<p>التكليف التدريبي يحدد ما يجب إكماله، ولمن ينطبق، وبأي موعد. ويمكن أن يكون المستهدف موظفاً أو قسماً أو فندقاً كاملاً أو مسمى وظيفياً أو المنشأة بأكملها، بحيث يصل إجراء سلامة جديد إلى كل موظف بإجراء واحد. وتُرسل التذكيرات قبل تاريخ الاستحقاق، وتُصعَّد البنود المتأخرة إلى المدير المباشر.</p>'
                        . '<h2>الإقرار بالإجراءات</h2>'
                        . '<p>عند نشر إجراء أو تعديله، يُطلب من المعنيين قراءته والإقرار به. ويسجل الإقرار الشخص والإصدار الدقيق والتوقيت وعنوان الاتصال. ويرى المدير الرقم المهم: كم عدد المطلوب منهم، وكم أقرّوا، ومن المتبقي بالاسم.</p>'
                        . '<h2>ما الذي يراه المدير</h2>'
                        . '<p>نسب الإكمال حسب القسم والفندق، والتدريب المتأخر لكل شخص، والشهادات المقتربة من الانتهاء، ونتائج التقييمات، والمهارات التي لدى كل موظف دليل عليها. مدير القسم يرى فريقه، ومدير الفندق يرى فندقه، ومدير المجموعة يرى المنشأة. ولا أحد يرى منشأة أخرى.</p>'
                        . '<h2>كيف تبدأ</h2>'
                        . '<p>أخبرنا بالفنادق والأقسام وعدد الموظفين تقريباً في كل قسم. وسنعود إليك والهيكل جاهز والتدريب الإلزامي مُسنَد بالفعل، بحيث يكون أول ما يراه فريقك قائمته الخاصة لا كتالوجاً فارغاً.</p>',
                    'cta_label' => 'تواصل مع الأكاديمية',
                    'cta_url' => 'contact',
                )),

            array('hotels-training', 'hotels/training', 'للفنادق/التدريب', 'landing',
                array(
                    'title' => 'Hotel Staff Training Programmes by Department',
                    'subtitle' => 'What each hotel department is trained on, and the order it is delivered in.',
                    'body' => '<h2>Front office</h2>'
                        . '<p>Arrival and departure procedure, guest registration and privacy, room assignment, complaint handling, upselling, and the night audit. A new front desk agent completes fundamentals, check-in, check-out and the property management system before working an unsupervised shift.</p>'
                        . '<h2>Housekeeping</h2>'
                        . '<p>Room cleaning sequence, bed and bathroom standards, chemical safety, lost and found, laundry, public areas, inspection and productivity. Chemical safety is completed before a room attendant handles any cleaning product unsupervised.</p>'
                        . '<h2>Food and beverage</h2>'
                        . '<p>Service sequence, greeting and seating, table service, beverage service, banquet operations, point of sale procedure and upselling, with food safety and personal hygiene completed first.</p>'
                        . '<h2>Kitchen</h2>'
                        . '<p>Culinary fundamentals, food safety, temperature control, storage, receiving, allergen handling, knife safety, waste control and the cleaning schedule. Temperature control and allergen handling are mandatory before working a service.</p>'
                        . '<h2>Engineering and maintenance</h2>'
                        . '<p>Building systems, preventive maintenance, HVAC, electrical safety, plumbing, work orders, asset inspection, fire safety and emergency response.</p>'
                        . '<h2>Management and commercial</h2>'
                        . '<p>Leadership, team management, quality management, hospitality finance, cost control, revenue management, distribution, analytics and hotel key performance indicators.</p>'
                        . '<h2>Mandatory for everyone</h2>'
                        . '<p>Fire safety, emergency response and the property induction are assigned to every employee regardless of department, with a seven day due date from their start date.</p>',
                    'cta_label' => 'See the full catalogue',
                    'cta_url' => 'courses',
                ),
                array(
                    'title' => 'برامج تدريب موظفي الفنادق حسب القسم',
                    'subtitle' => 'ما الذي يُدرَّب عليه كل قسم فندقي، وبأي ترتيب يُقدَّم.',
                    'body' => '<h2>مكتب الاستقبال</h2>'
                        . '<p>إجراءات الوصول والمغادرة، وتسجيل بيانات الضيف وخصوصيته، وتوزيع الغرف، ومعالجة الشكاوى، والبيع الإضافي، والتدقيق الليلي. ويكمل موظف الاستقبال الجديد الأساسيات وتسجيل الوصول والمغادرة ونظام إدارة الفندق قبل العمل في وردية دون إشراف.</p>'
                        . '<h2>التدبير الفندقي</h2>'
                        . '<p>تسلسل تنظيف الغرفة، ومعايير السرير ودورة المياه، وسلامة المواد الكيميائية، والمفقودات، والمغسلة، والمناطق العامة، والتفتيش، والإنتاجية. وتُكمَل سلامة المواد الكيميائية قبل أن يتعامل عامل الغرف مع أي منتج تنظيف دون إشراف.</p>'
                        . '<h2>الأغذية والمشروبات</h2>'
                        . '<p>تسلسل الخدمة، والاستقبال والإجلاس، وخدمة الطاولة، وخدمة المشروبات، وعمليات الولائم، وإجراءات نقاط البيع، والبيع الإضافي، مع إكمال سلامة الغذاء والنظافة الشخصية أولاً.</p>'
                        . '<h2>المطبخ</h2>'
                        . '<p>أساسيات الطهي، وسلامة الغذاء، وضبط الحرارة، والتخزين، والاستلام، والتعامل مع مسببات الحساسية، وسلامة السكاكين، وضبط الهدر، وجدول التنظيف. وضبط الحرارة والتعامل مع مسببات الحساسية إلزاميان قبل العمل في أي خدمة.</p>'
                        . '<h2>الهندسة والصيانة</h2>'
                        . '<p>أنظمة المبنى، والصيانة الوقائية، والتكييف، والسلامة الكهربائية، والسباكة، وأوامر العمل، وتفتيش الأصول، والسلامة من الحريق، والاستجابة للطوارئ.</p>'
                        . '<h2>الإدارة والجانب التجاري</h2>'
                        . '<p>القيادة، وإدارة الفريق، وإدارة الجودة، والمالية الفندقية، وضبط التكاليف، وإدارة الإيرادات، والتوزيع، والتحليلات، ومؤشرات الأداء الفندقية.</p>'
                        . '<h2>إلزامي للجميع</h2>'
                        . '<p>تُسنَد السلامة من الحريق والاستجابة للطوارئ وتعريف الفندق لكل موظف مهما كان قسمه، بتاريخ استحقاق سبعة أيام من تاريخ المباشرة.</p>',
                    'cta_label' => 'اطّلع على الكتالوج الكامل',
                    'cta_url' => 'courses',
                )),

            array('certificates-info', 'certificates', 'الشهادات', 'standard',
                array(
                    'title' => 'Certificates and Verification',
                    'subtitle' => 'What a Hospitality Academy certificate records, and how anyone can check whether one is genuine.',
                    'body' => '<h2>When a certificate is issued</h2>'
                        . '<p>A certificate is issued when a learner completes every mandatory lesson in a course and passes its assessment at or above the pass mark set for that course. A programme certificate is issued when every course in the programme is complete.</p>'
                        . '<h2>What it records</h2>'
                        . '<p>The learner name, the course or programme, the completion date, the final score, a unique certificate number, a verification code, and an expiry date where the subject requires periodic refreshing. Safety and food safety subjects carry an expiry; general operational subjects usually do not.</p>'
                        . '<h2>How to verify one</h2>'
                        . '<p>Open the verification page and enter the verification code printed on the certificate. The page returns the status directly: valid, expired, revoked, or not found. A valid result shows the learner name, the subject and the issue date, and nothing else about the person.</p>'
                        . '<h2>Why a certificate may show as revoked</h2>'
                        . '<p>A certificate is revoked if it was issued in error, if the assessment result behind it is found to be invalid, or at the request of the issuing organization. A revoked certificate stays verifiable and continues to return a revoked status rather than disappearing, so an earlier copy cannot be passed off as current.</p>',
                    'cta_label' => 'Verify a certificate',
                    'cta_url' => 'verify',
                ),
                array(
                    'title' => 'الشهادات والتحقق منها',
                    'subtitle' => 'ما الذي توثقه شهادة أكاديمية الضيافة، وكيف يتحقق أي شخص من صحتها.',
                    'body' => '<h2>متى تُصدر الشهادة</h2>'
                        . '<p>تُصدر الشهادة عندما يكمل المتدرب كل درس إلزامي في الدورة ويجتاز تقييمها بدرجة النجاح المحددة لتلك الدورة أو أعلى. وتُصدر شهادة البرنامج عند إكمال كل دورة فيه.</p>'
                        . '<h2>ما الذي توثقه</h2>'
                        . '<p>اسم المتدرب، والدورة أو البرنامج، وتاريخ الإكمال، والدرجة النهائية، ورقم شهادة فريد، ورمز تحقق، وتاريخ انتهاء حين يتطلب الموضوع تجديداً دورياً. وتحمل مواضيع السلامة وسلامة الغذاء تاريخ انتهاء، بينما المواضيع التشغيلية العامة غالباً لا تحمله.</p>'
                        . '<h2>كيف يتم التحقق</h2>'
                        . '<p>افتح صفحة التحقق وأدخل رمز التحقق المطبوع على الشهادة. تعيد الصفحة الحالة مباشرة: سارية أو منتهية أو ملغاة أو غير موجودة. وتعرض النتيجة السارية اسم المتدرب والموضوع وتاريخ الإصدار، ولا شيء آخر عن الشخص.</p>'
                        . '<h2>لماذا قد تظهر الشهادة ملغاة</h2>'
                        . '<p>تُلغى الشهادة إذا صدرت بالخطأ، أو إذا تبيّن أن نتيجة التقييم خلفها غير صحيحة، أو بطلب من الجهة المُصدِرة. وتبقى الشهادة الملغاة قابلة للتحقق وتعيد حالة «ملغاة» بدل أن تختفي، حتى لا تُقدَّم نسخة قديمة على أنها سارية.</p>',
                    'cta_label' => 'تحقق من شهادة',
                    'cta_url' => 'verify',
                )),

            array('contact', 'contact', 'تواصل', 'contact',
                array(
                    'title' => 'Contact the Academy',
                    'subtitle' => 'Tell us about your property and your team, and we will come back with a structure rather than a brochure.',
                    'body' => '<h2>What to include</h2>'
                        . '<p>The properties and cities involved, the departments you want to start with, roughly how many employees in each, and whether you need the interface primarily in Arabic, English or both. If there is a deadline such as a pre-opening or an inspection, say so.</p>'
                        . '<h2>What happens next</h2>'
                        . '<p>A member of the academy team replies to arrange a call. There is no automated sequence and your details are not passed to a third party.</p>',
                    'cta_label' => 'Send your enquiry',
                    'cta_url' => 'contact',
                ),
                array(
                    'title' => 'تواصل مع الأكاديمية',
                    'subtitle' => 'أخبرنا عن فندقك وفريقك، وسنعود إليك بهيكل عمل لا بكتيّب تعريفي.',
                    'body' => '<h2>ما الذي تذكره</h2>'
                        . '<p>الفنادق والمدن المعنية، والأقسام التي تريد البدء بها، وعدد الموظفين تقريباً في كل قسم، وهل تحتاج الواجهة بالعربية أساساً أم بالإنجليزية أم بكليهما. وإن كان هناك موعد نهائي مثل افتتاح أو تفتيش، فاذكره.</p>'
                        . '<h2>ماذا يحدث بعد ذلك</h2>'
                        . '<p>يرد عليك أحد أعضاء فريق الأكاديمية لترتيب مكالمة. لا توجد رسائل آلية متسلسلة، ولا تُمرَّر بياناتك لأي طرف ثالث.</p>',
                    'cta_label' => 'أرسل طلبك',
                    'cta_url' => 'contact',
                )),

            array('privacy', 'privacy', 'الخصوصية', 'legal',
                array(
                    'title' => 'Privacy Policy',
                    'subtitle' => 'What the academy stores, why, and how long it is kept.',
                    'body' => '<h2>What is collected</h2>'
                        . '<p>For a learner: name, work email, mobile number where provided, employer, property, department, job role, and the learning record itself, which covers enrolments, lesson progress, assessment attempts and scores, certificates and procedure acknowledgements.</p>'
                        . '<h2>Why it is collected</h2>'
                        . '<p>To deliver training, to record whether it was completed, to issue and verify certificates, and to let an employer see the compliance position for their own workforce. Nothing is sold, and nothing is used for advertising.</p>'
                        . '<h2>Procedure acknowledgements</h2>'
                        . '<p>An acknowledgement records the user, the exact procedure version, the timestamp and the originating network address. This is deliberate: an acknowledgement that cannot be tied to a person, a version and a moment is not evidence of anything.</p>'
                        . '<h2>Who can see a learning record</h2>'
                        . '<p>The learner, their line manager, their department, property and organization administrators within the same organization, and an academy administrator. No organization can see another organization data.</p>'
                        . '<h2>Retention</h2>'
                        . '<p>Learning records and certificates are retained while the account is active and afterwards for as long as the issuing organization requires them for audit. A certificate record is retained indefinitely so that verification keeps working.</p>'
                        . '<h2>Your requests</h2>'
                        . '<p>A learner may request a copy of their record or a correction to it through their employer administrator or by contacting the academy directly.</p>',
                    'cta_label' => 'Contact the academy',
                    'cta_url' => 'contact',
                ),
                array(
                    'title' => 'سياسة الخصوصية',
                    'subtitle' => 'ما الذي تحفظه الأكاديمية، ولماذا، وإلى متى.',
                    'body' => '<h2>ما الذي يُجمع</h2>'
                        . '<p>للمتدرب: الاسم، والبريد الإلكتروني الوظيفي، ورقم الجوال إن قُدِّم، وجهة العمل، والفندق، والقسم، والمسمى الوظيفي، والسجل التدريبي نفسه الذي يشمل التسجيلات وتقدم الدروس ومحاولات التقييم والدرجات والشهادات والإقرارات بالإجراءات.</p>'
                        . '<h2>لماذا يُجمع</h2>'
                        . '<p>لتقديم التدريب، وتوثيق إكماله، وإصدار الشهادات والتحقق منها، وتمكين جهة العمل من رؤية وضع الالتزام لكوادرها. لا يُباع شيء، ولا يُستخدم شيء لأغراض إعلانية.</p>'
                        . '<h2>الإقرار بالإجراءات</h2>'
                        . '<p>يسجل الإقرار المستخدم وإصدار الإجراء بدقة والتوقيت وعنوان الشبكة. وهذا مقصود: الإقرار الذي لا يمكن ربطه بشخص وإصدار ولحظة ليس دليلاً على شيء.</p>'
                        . '<h2>من يستطيع الاطلاع على السجل التدريبي</h2>'
                        . '<p>المتدرب، ومديره المباشر، ومديرو القسم والفندق والمنشأة داخل المنشأة نفسها، ومدير الأكاديمية. ولا تستطيع أي منشأة الاطلاع على بيانات منشأة أخرى.</p>'
                        . '<h2>مدة الحفظ</h2>'
                        . '<p>تُحفظ السجلات التدريبية والشهادات ما دام الحساب فعالاً، وبعده للمدة التي تطلبها الجهة المُصدِرة لأغراض التدقيق. ويُحفظ سجل الشهادة دون حد زمني حتى يستمر التحقق في العمل.</p>'
                        . '<h2>طلباتك</h2>'
                        . '<p>يستطيع المتدرب طلب نسخة من سجله أو تصحيحه عبر مدير جهة عمله أو بالتواصل مع الأكاديمية مباشرة.</p>',
                    'cta_label' => 'تواصل مع الأكاديمية',
                    'cta_url' => 'contact',
                )),

            array('terms', 'terms', 'الشروط', 'legal',
                array(
                    'title' => 'Terms of Use',
                    'subtitle' => 'The terms that apply to using the academy and to the certificates it issues.',
                    'body' => '<h2>Accounts</h2>'
                        . '<p>An account belongs to one named person. Sharing an account, or completing training on behalf of another person, invalidates any certificate resulting from it and is grounds for the account to be suspended.</p>'
                        . '<h2>Assessments</h2>'
                        . '<p>Assessment attempts are recorded with a timestamp and attempt number. Attempt limits, time limits and question randomisation are applied as configured for each assessment. A result obtained by circumventing those controls is void.</p>'
                        . '<h2>Certificates</h2>'
                        . '<p>A certificate records completion of a specific course or programme by a specific person on a specific date. It is not a licence, a regulatory approval, or a statement that the holder is qualified for any particular role. Any such use is outside these terms.</p>'
                        . '<h2>Content</h2>'
                        . '<p>Course material and procedures published by the academy remain the property of the academy or of the organization that authored them. Procedures uploaded by an organization remain that organization property and are not shared with any other organization.</p>'
                        . '<h2>Availability</h2>'
                        . '<p>The academy aims to keep the service available continuously but does not guarantee uninterrupted access. Planned maintenance is announced in advance where it is expected to interrupt access.</p>',
                    'cta_label' => 'Read the privacy policy',
                    'cta_url' => 'privacy',
                ),
                array(
                    'title' => 'شروط الاستخدام',
                    'subtitle' => 'الشروط المطبقة على استخدام الأكاديمية وعلى الشهادات التي تصدرها.',
                    'body' => '<h2>الحسابات</h2>'
                        . '<p>الحساب يخص شخصاً واحداً بالاسم. ومشاركة الحساب أو إكمال التدريب نيابة عن شخص آخر يُبطل أي شهادة تنتج عنه ويُعد سبباً لتعليق الحساب.</p>'
                        . '<h2>التقييمات</h2>'
                        . '<p>تُسجَّل محاولات التقييم بتوقيت ورقم محاولة. وتُطبَّق حدود المحاولات والوقت وترتيب الأسئلة العشوائي وفق إعداد كل تقييم. وأي نتيجة تُحصَّل بالالتفاف على هذه الضوابط تُعد لاغية.</p>'
                        . '<h2>الشهادات</h2>'
                        . '<p>توثق الشهادة إكمال شخص محدد لدورة أو برنامج محدد في تاريخ محدد. وهي ليست رخصة ولا موافقة تنظيمية ولا إفادة بأن حاملها مؤهل لدور معين. وأي استخدام من هذا النوع خارج هذه الشروط.</p>'
                        . '<h2>المحتوى</h2>'
                        . '<p>تبقى المواد التدريبية والإجراءات التي تنشرها الأكاديمية ملكاً لها أو للجهة التي ألّفتها. وتبقى الإجراءات التي ترفعها منشأة ملكاً لتلك المنشأة ولا تُشارَك مع أي منشأة أخرى.</p>'
                        . '<h2>الإتاحة</h2>'
                        . '<p>تسعى الأكاديمية لإبقاء الخدمة متاحة باستمرار لكنها لا تضمن وصولاً دون انقطاع. ويُعلَن عن الصيانة المخططة مسبقاً حين يُتوقع أن تعطّل الوصول.</p>',
                    'cta_label' => 'اقرأ سياسة الخصوصية',
                    'cta_url' => 'privacy',
                )),
        );
    }

    // ----------------------------------------------------------------- topics

    /**
     * Pillar topics and city topics. Plan section 29 explicitly forbids thin
     * doorway pages, so each city page carries content about that city hotel
     * market rather than a swapped city name.
     *
     * code, type, slug_en, slug_ar, city, EN [title, intro], AR [title, intro], course codes
     */
    public static function topics() {
        return array(

            array('front-office-training', 'pillar', 'front-office-training', 'تدريب-مكتب-الاستقبال', null,
                array('Front Office Training',
                    '<p>Front office training covers everything that happens between a guest arriving at the desk and their folio being closed. It is the department where a service failure is most visible and where a procedural mistake is most expensive, because a wrong room assignment, an unverified identity or a mis-posted charge all surface later as a complaint or an audit finding.</p>'
                    . '<h2>What front office training should cover</h2>'
                    . '<p>The arrival and departure sequences in order, guest registration and the records it creates, room assignment when the hotel is full, complaint handling with a method rather than an instinct, telephone standards, upselling that does not pressure the guest, and the night audit that closes the hotel day.</p>'
                    . '<h2>The order that works</h2>'
                    . '<p>A new agent should complete fundamentals and telephone etiquette first, then check-in, check-out and registration, then the property management system, before working an unsupervised shift. Complaint handling and the night audit come later, once the basic sequence is automatic.</p>'
                    . '<h2>How progress is measured</h2>'
                    . '<p>Each course ends in an assessment, and the operational courses are paired with a checklist the learner runs in their own department. Completion is recorded against the person, not the shift, so a manager can see who is ready to work alone.</p>'),
                array('تدريب مكتب الاستقبال',
                    '<p>يغطي تدريب مكتب الاستقبال كل ما يحدث بين وصول الضيف إلى المكتب وإغلاق فاتورته. وهو القسم الذي يكون فيه إخفاق الخدمة أكثر وضوحاً والخطأ الإجرائي أكثر كلفة، لأن توزيع غرفة خاطئ أو هوية غير موثقة أو رسماً مرحّلاً بالخطأ يظهر لاحقاً كشكوى أو ملاحظة تدقيق.</p>'
                    . '<h2>ما ينبغي أن يغطيه تدريب مكتب الاستقبال</h2>'
                    . '<p>تسلسل الوصول والمغادرة بالترتيب، وتسجيل بيانات الضيف والسجلات الناتجة عنه، وتوزيع الغرف عند اكتمال الفندق، ومعالجة الشكاوى بمنهج لا بارتجال، ومعايير الهاتف، والبيع الإضافي دون ضغط على الضيف، والتدقيق الليلي الذي يغلق يوم الفندق.</p>'
                    . '<h2>الترتيب الذي ينجح</h2>'
                    . '<p>ينبغي أن يكمل الموظف الجديد الأساسيات وآداب المكالمات أولاً، ثم تسجيل الوصول والمغادرة والتسجيل، ثم نظام إدارة الفندق، قبل العمل في وردية دون إشراف. وتأتي معالجة الشكاوى والتدقيق الليلي لاحقاً، بعد أن يصبح التسلسل الأساسي تلقائياً.</p>'
                    . '<h2>كيف يُقاس التقدم</h2>'
                    . '<p>تنتهي كل دورة بتقييم، وتقترن الدورات التشغيلية بقائمة تحقق ينفذها المتدرب في قسمه. ويُسجَّل الإكمال على الشخص لا على الوردية، بحيث يرى المدير من أصبح جاهزاً للعمل منفرداً.</p>'),
                array('fo-fundamentals', 'fo-check-in', 'fo-check-out', 'fo-complaints', 'fo-night-audit', 'dig-pms')),

            array('housekeeping-training', 'pillar', 'housekeeping-training', 'تدريب-التدبير-الفندقي', null,
                array('Housekeeping Training',
                    '<p>Housekeeping training exists to make a room predictable. Two attendants cleaning the same room should produce the same result, and an inspector should be able to score either one against the same written standard.</p>'
                    . '<h2>Why sequence matters more than effort</h2>'
                    . '<p>Most housekeeping failures are sequence failures rather than effort failures. Cleaning the bathroom before applying the chemical, vacuuming towards the far corner instead of towards the door, or making the bed before dusting all produce a room that took longer and looks worse. Training the sequence is what makes the time per room predictable.</p>'
                    . '<h2>Safety is part of the standard</h2>'
                    . '<p>Chemical safety is not an add-on module. Dilution, dwell time, glove use, never mixing products, and the colour coded cloth discipline that stops a bathroom cloth reaching a glass are all part of cleaning correctly, so they are taught alongside the cleaning itself.</p>'
                    . '<h2>Inspection closes the loop</h2>'
                    . '<p>An attendant who is inspected against a standard they were trained on can be given specific feedback. Training and inspection use the same checklist here, which is what makes a failed inspection a coaching conversation rather than an argument.</p>'),
                array('تدريب التدبير الفندقي',
                    '<p>وُجد تدريب التدبير الفندقي ليجعل الغرفة قابلة للتوقع. فعاملان ينظفان الغرفة نفسها ينبغي أن ينتجا النتيجة نفسها، وينبغي أن يستطيع المفتش تقييم أي منهما وفق المعيار المكتوب نفسه.</p>'
                    . '<h2>لماذا التسلسل أهم من الجهد</h2>'
                    . '<p>معظم إخفاقات التدبير الفندقي إخفاقات تسلسل لا إخفاقات جهد. فتنظيف دورة المياه قبل وضع المادة الكيميائية، أو الكنس باتجاه الزاوية البعيدة بدل الباب، أو ترتيب السرير قبل إزالة الغبار، كلها تنتج غرفة استغرقت وقتاً أطول وتبدو أسوأ. وتدريب التسلسل هو ما يجعل زمن الغرفة قابلاً للتوقع.</p>'
                    . '<h2>السلامة جزء من المعيار</h2>'
                    . '<p>سلامة المواد الكيميائية ليست وحدة إضافية. فالتخفيف ومدة التفاعل واستخدام القفازات وعدم خلط المنتجات وانضباط الترميز اللوني الذي يمنع وصول منشفة دورة المياه إلى كوب، كلها جزء من التنظيف الصحيح، ولذلك تُدرَّس مع التنظيف نفسه.</p>'
                    . '<h2>التفتيش يغلق الحلقة</h2>'
                    . '<p>العامل الذي يُفتَّش وفق معيار دُرِّب عليه يمكن إعطاؤه ملاحظات محددة. والتدريب والتفتيش هنا يستخدمان قائمة التحقق نفسها، وهو ما يجعل التفتيش غير المطابق حواراً تدريبياً لا جدالاً.</p>'),
                array('hk-fundamentals', 'hk-room-cleaning', 'hk-bathroom', 'hk-chemical-safety', 'hk-inspection')),

            array('food-safety-training', 'pillar', 'food-safety-training', 'تدريب-سلامة-الغذاء', null,
                array('Food Safety Training for Hotels',
                    '<p>Food safety training is the training a hotel is most likely to be asked to evidence, and the one where a gap has consequences beyond a guest complaint.</p>'
                    . '<h2>The control points that matter</h2>'
                    . '<p>Receiving temperature, storage order and dates, cooking temperature, hot holding, cooling times, reheating once, and allergen separation. Each is a point where a failure can be detected and corrected, and each produces a record.</p>'
                    . '<h2>The record is the training outcome</h2>'
                    . '<p>A team that has been trained produces a complete temperature log with no blank cells and a recorded corrective action against every out of range reading. An incomplete log is a training gap, not a paperwork problem, so the academy treats log completion as part of the competence rather than as an administrative task.</p>'
                    . '<h2>Who needs it</h2>'
                    . '<p>Kitchen teams need the full set. Service teams need temperature awareness on the pass, allergen questions and personal hygiene. Receiving clerks need the delivery checks. Housekeeping needs it where minibar or in-room provisioning is involved.</p>'),
                array('تدريب سلامة الغذاء للفنادق',
                    '<p>تدريب سلامة الغذاء هو التدريب الأكثر احتمالاً أن يُطلب من الفندق إثباته، وهو الذي تتجاوز فيه الفجوة حدود شكوى الضيف.</p>'
                    . '<h2>نقاط التحكم المهمة</h2>'
                    . '<p>حرارة الاستلام، وترتيب التخزين والتواريخ، وحرارة الطهي، والحفظ الساخن، وأزمنة التبريد، وإعادة التسخين مرة واحدة، وفصل مسببات الحساسية. وكل نقطة منها موضع يمكن اكتشاف الإخفاق فيه وتصحيحه، وكل منها ينتج سجلاً.</p>'
                    . '<h2>السجل هو ناتج التدريب</h2>'
                    . '<p>الفريق المدرَّب ينتج سجل حرارة مكتملاً دون خانات فارغة، وإجراءً تصحيحياً مسجلاً أمام كل قراءة خارج النطاق. والسجل الناقص فجوة تدريب لا مشكلة أوراق، ولذلك تتعامل الأكاديمية مع اكتمال السجل كجزء من الكفاءة لا كمهمة إدارية.</p>'
                    . '<h2>من يحتاجه</h2>'
                    . '<p>فرق المطبخ تحتاج المجموعة كاملة. وفرق الخدمة تحتاج الوعي الحراري عند التمرير وأسئلة مسببات الحساسية والنظافة الشخصية. وموظفو الاستلام يحتاجون فحوصات التوريد. ويحتاجه التدبير الفندقي حيث يوجد ميني بار أو تجهيز داخل الغرفة.</p>'),
                array('kit-food-safety', 'kit-temperature', 'kit-storage', 'kit-allergens', 'fb-haccp', 'fb-hygiene')),

            array('hotel-sop-training', 'pillar', 'hotel-sop-training', 'تدريب-إجراءات-التشغيل-الفندقية', null,
                array('Hotel SOP Training',
                    '<p>A standard operating procedure that nobody has read is a document, not a control. SOP training is what turns the second into the first.</p>'
                    . '<h2>What a usable SOP contains</h2>'
                    . '<p>A purpose, a scope, named responsibilities, the tools required, the procedure as numbered steps in the order they are performed, a checklist that can be run, safety notes, a quality standard that can be measured, and an escalation route. A procedure missing the escalation route is the one that fails at three in the morning.</p>'
                    . '<h2>Versions and acknowledgement</h2>'
                    . '<p>When a procedure changes, the previous version is kept and the new one is published with a change summary, an author and an approver. Everyone the procedure applies to is asked to acknowledge the new version, and the acknowledgement records the version, the person and the moment.</p>'
                    . '<h2>From procedure to checklist</h2>'
                    . '<p>Every procedure in the academy carries its checklist as a runnable item, so a supervisor can execute the check on a shift and sign it rather than remembering the steps.</p>'),
                array('تدريب إجراءات التشغيل الفندقية',
                    '<p>إجراء التشغيل القياسي الذي لم يقرأه أحد وثيقة لا ضابط. وتدريب الإجراءات هو ما يحوّل الثانية إلى الأولى.</p>'
                    . '<h2>ما يحتويه الإجراء القابل للاستخدام</h2>'
                    . '<p>غرض، ونطاق، ومسؤوليات محددة بالاسم، والأدوات المطلوبة، والإجراء كخطوات مرقّمة بترتيب تنفيذها، وقائمة تحقق قابلة للتنفيذ، وملاحظات سلامة، ومعيار جودة قابل للقياس، ومسار تصعيد. والإجراء الذي ينقصه مسار التصعيد هو الذي يُخفق في الثالثة فجراً.</p>'
                    . '<h2>الإصدارات والإقرار</h2>'
                    . '<p>عند تغيّر الإجراء يُحفظ الإصدار السابق ويُنشر الجديد مع ملخص التغيير ومؤلف ومعتمِد. ويُطلب من كل من ينطبق عليه الإجراء الإقرار بالإصدار الجديد، ويسجل الإقرار الإصدار والشخص واللحظة.</p>'
                    . '<h2>من الإجراء إلى قائمة التحقق</h2>'
                    . '<p>كل إجراء في الأكاديمية يحمل قائمة تحققه كبند قابل للتنفيذ، بحيث ينفذ المشرف الفحص في الوردية ويوقّع عليه بدل أن يتذكر الخطوات.</p>'),
                array('hk-room-cleaning', 'fo-check-in', 'kit-temperature', 'eng-fire-safety')),

            array('hospitality-certification', 'pillar', 'hospitality-certification', 'اعتماد-الضيافة', null,
                array('Hospitality Certification',
                    '<p>Certification is useful when it records something specific and can be checked by someone who was not there. That is the standard the academy holds itself to.</p>'
                    . '<h2>What a certificate here means</h2>'
                    . '<p>A named person completed every mandatory lesson of a named course and passed its assessment at or above the pass mark, on a recorded date. Nothing more is implied, and nothing more is printed.</p>'
                    . '<h2>Verification</h2>'
                    . '<p>Each certificate carries a certificate number and a verification code. A prospective employer, an auditor or an inspector can enter the code on the public verification page and see the status immediately, without an account.</p>'
                    . '<h2>Expiry and refreshing</h2>'
                    . '<p>Safety and food safety certificates carry an expiry date because the underlying standard and the person recall both change. The academy warns the learner and their manager before expiry rather than after it.</p>'),
                array('اعتماد الضيافة',
                    '<p>الاعتماد مفيد حين يوثق شيئاً محدداً ويمكن لشخص لم يكن حاضراً التحقق منه. وهذا هو المعيار الذي تلزم الأكاديمية نفسها به.</p>'
                    . '<h2>ماذا تعني الشهادة هنا</h2>'
                    . '<p>أن شخصاً محدداً بالاسم أكمل كل درس إلزامي في دورة محددة واجتاز تقييمها بدرجة النجاح أو أعلى، في تاريخ مسجل. لا يُفهم منها أكثر من ذلك، ولا يُطبع عليها أكثر من ذلك.</p>'
                    . '<h2>التحقق</h2>'
                    . '<p>كل شهادة تحمل رقم شهادة ورمز تحقق. ويستطيع صاحب عمل محتمل أو مدقق أو مفتش إدخال الرمز في صفحة التحقق العامة ورؤية الحالة فوراً دون حساب.</p>'
                    . '<h2>الانتهاء والتجديد</h2>'
                    . '<p>تحمل شهادات السلامة وسلامة الغذاء تاريخ انتهاء لأن المعيار وذاكرة الشخص كليهما يتغيران. وتنبّه الأكاديمية المتدرب ومديره قبل الانتهاء لا بعده.</p>'),
                array('eng-fire-safety', 'kit-food-safety', 'fo-fundamentals', 'hk-fundamentals')),

            array('hotel-compliance-training', 'compliance', 'hotel-compliance-training', 'تدريب-الالتزام-الفندقي', null,
                array('Hotel Compliance Training',
                    '<p>Compliance training is the set of subjects a hotel must be able to evidence for every employee, on demand, with a date and a name attached.</p>'
                    . '<h2>The mandatory set</h2>'
                    . '<p>Fire safety and evacuation, emergency response, chemical safety for anyone handling cleaning products, food safety for anyone touching food, knife safety in the kitchen, and guest data privacy for anyone with access to guest records.</p>'
                    . '<h2>Evidence, not attendance</h2>'
                    . '<p>An attendance sheet shows who was in the room. A completion record with an assessment result shows who understood it. The academy records both, and reports on the second.</p>'
                    . '<h2>Keeping it current</h2>'
                    . '<p>Compliance subjects are set to expire, and the expiry warning goes to the learner and the manager together. Outstanding and overdue items are visible by department and by property so the gap is found before an inspection finds it.</p>'),
                array('تدريب الالتزام الفندقي',
                    '<p>تدريب الالتزام هو مجموعة المواضيع التي يجب أن يستطيع الفندق إثباتها لكل موظف عند الطلب، بتاريخ واسم.</p>'
                    . '<h2>المجموعة الإلزامية</h2>'
                    . '<p>السلامة من الحريق والإخلاء، والاستجابة للطوارئ، وسلامة المواد الكيميائية لكل من يتعامل مع منتجات التنظيف، وسلامة الغذاء لكل من يلامس الطعام، وسلامة السكاكين في المطبخ، وخصوصية بيانات الضيف لكل من لديه وصول لسجلات الضيوف.</p>'
                    . '<h2>إثبات لا حضور</h2>'
                    . '<p>كشف الحضور يبيّن من كان في القاعة. وسجل الإكمال مع نتيجة التقييم يبيّن من فهم. وتسجل الأكاديمية الاثنين، وتقدم تقاريرها عن الثاني.</p>'
                    . '<h2>إبقاؤه محدّثاً</h2>'
                    . '<p>تُضبط مواضيع الالتزام لتنتهي صلاحيتها، ويصل تنبيه الانتهاء إلى المتدرب والمدير معاً. والبنود المتبقية والمتأخرة ظاهرة حسب القسم والفندق ليُكتشف النقص قبل أن يكتشفه التفتيش.</p>'),
                array('eng-fire-safety', 'eng-emergency', 'hk-chemical-safety', 'kit-food-safety', 'fo-guest-privacy')),
        );
    }

    /**
     * City pages. Plan section 29 allows city pages "only where there is useful
     * localized content", so each carries a genuine description of that city
     * hotel market and why the training emphasis differs there.
     *
     * slug_en, slug_ar, city, EN [title, intro], AR [title, intro]
     */
    public static function cities() {
        return array(
            array('hotel-training-riyadh', 'تدريب-فندقي-الرياض', 'Riyadh',
                array('Hotel Staff Training in Riyadh',
                    '<p>Riyadh hotels run a largely corporate and government pattern: weekday-heavy occupancy, a high share of single business travellers, short stays, and arrival peaks tied to meeting schedules rather than leisure seasons.</p>'
                    . '<h2>What that changes about training</h2>'
                    . '<p>Front office speed matters more than in a resort setting, because a business arrival at nine in the evening wants the room, not the tour. Check-in and check-out timing standards, express departure, and accurate folio handling for company-billed stays carry more weight than concierge itinerary building.</p>'
                    . '<h2>Meetings and events</h2>'
                    . '<p>Riyadh properties carry a heavy meetings and events load, so banquet operations, function sheet reading and the coordination between events, kitchen and engineering are trained as a set rather than individually.</p>'
                    . '<h2>Where teams usually need the most support</h2>'
                    . '<p>Handling a full house on a weekday, room assignment under pressure, complaint handling when a guaranteed room is not ready, and the night audit for a property with a high volume of company accounts.</p>'),
                array('تدريب موظفي الفنادق في الرياض',
                    '<p>تعمل فنادق الرياض على نمط مؤسسي وحكومي في معظمه: إشغال مرتفع في أيام العمل، ونسبة عالية من مسافري الأعمال الأفراد، وإقامات قصيرة، وذروة وصول مرتبطة بجداول الاجتماعات لا بمواسم الترفيه.</p>'
                    . '<h2>ما الذي يغيّره ذلك في التدريب</h2>'
                    . '<p>سرعة مكتب الاستقبال أهم منها في المنتجعات، لأن مسافر الأعمال الواصل في التاسعة مساءً يريد الغرفة لا الجولة السياحية. ولذلك تحمل معايير توقيت الوصول والمغادرة والمغادرة السريعة ودقة التعامل مع الفواتير المحمّلة على الشركات وزناً أكبر من بناء برامج الكونسيرج.</p>'
                    . '<h2>الاجتماعات والفعاليات</h2>'
                    . '<p>تحمل فنادق الرياض عبئاً كبيراً من الاجتماعات والفعاليات، ولذلك تُدرَّب عمليات الولائم وقراءة ورقة الفعالية والتنسيق بين الفعاليات والمطبخ والهندسة كمجموعة واحدة لا كوحدات منفصلة.</p>'
                    . '<h2>أين تحتاج الفرق الدعم غالباً</h2>'
                    . '<p>التعامل مع فندق ممتلئ في يوم عمل، وتوزيع الغرف تحت الضغط، ومعالجة الشكاوى حين لا تكون الغرفة المضمونة جاهزة، والتدقيق الليلي لفندق بحجم كبير من حسابات الشركات.</p>')),

            array('hotel-training-jeddah', 'تدريب-فندقي-جدة', 'Jeddah',
                array('Hotel Staff Training in Jeddah',
                    '<p>Jeddah mixes leisure, corporate and transit demand, with a strong food and beverage culture and a corniche hotel pattern where the restaurant is often busier than the rooms.</p>'
                    . '<h2>What that changes about training</h2>'
                    . '<p>Food and beverage depth matters here. Service sequence, banquet operations, beverage service and point of sale discipline are trained to a higher level, and kitchen teams carry a heavier allergen and temperature control load because of outlet volume.</p>'
                    . '<h2>Family and group service</h2>'
                    . '<p>Family seating, larger party service and majlis-style arrangements are common, so table service training covers those layouts explicitly rather than assuming a two-cover restaurant setting.</p>'
                    . '<h2>Where teams usually need the most support</h2>'
                    . '<p>Turning tables without rushing guests, coordinating a restaurant and a banquet running at the same time, and keeping food safety records complete when the outlet is at capacity.</p>'),
                array('تدريب موظفي الفنادق في جدة',
                    '<p>تمزج جدة بين الطلب الترفيهي والمؤسسي والعابر، مع ثقافة قوية للأغذية والمشروبات ونمط فنادق الكورنيش حيث يكون المطعم غالباً أكثر انشغالاً من الغرف.</p>'
                    . '<h2>ما الذي يغيّره ذلك في التدريب</h2>'
                    . '<p>عمق الأغذية والمشروبات مهم هنا. فتُدرَّب تسلسل الخدمة وعمليات الولائم وخدمة المشروبات وانضباط نقاط البيع إلى مستوى أعلى، وتحمل فرق المطبخ عبئاً أكبر في التعامل مع مسببات الحساسية وضبط الحرارة بسبب حجم المنافذ.</p>'
                    . '<h2>خدمة العائلات والمجموعات</h2>'
                    . '<p>جلسات العائلات وخدمة المجموعات الكبيرة وترتيبات المجلس شائعة، ولذلك يغطي تدريب خدمة الطاولة هذه الترتيبات صراحةً بدل افتراض مطعم بطاولات ثنائية.</p>'
                    . '<h2>أين تحتاج الفرق الدعم غالباً</h2>'
                    . '<p>تدوير الطاولات دون استعجال الضيوف، والتنسيق بين مطعم ووليمة يعملان في الوقت نفسه، وإبقاء سجلات سلامة الغذاء مكتملة عندما يكون المنفذ ممتلئاً.</p>')),

            array('hotel-training-makkah', 'تدريب-فندقي-مكة', 'Makkah',
                array('Hotel Staff Training in Makkah',
                    '<p>Makkah hotels operate at a scale and turnover rate that most properties never see: very large room counts, group arrivals and departures measured in coaches rather than cars, and seasonal peaks that change the entire operating rhythm.</p>'
                    . '<h2>What that changes about training</h2>'
                    . '<p>Volume handling is the core skill. Group check-in and check-out procedure, room blocking, key control across hundreds of rooms, and housekeeping turnaround at scale carry more weight than individual guest personalisation.</p>'
                    . '<h2>Housekeeping at scale</h2>'
                    . '<p>Room turnaround, deep cleaning cycles, linen throughput and productivity planning are trained as a system, because a departure wave that is handled badly does not recover within the same day.</p>'
                    . '<h2>Where teams usually need the most support</h2>'
                    . '<p>Coordinating a group departure and a group arrival on the same morning, maintaining the room standard under turnaround pressure, and clear multilingual communication with guests who may share no common language with the team.</p>'),
                array('تدريب موظفي الفنادق في مكة المكرمة',
                    '<p>تعمل فنادق مكة بحجم ومعدل دوران لا تشهده معظم الفنادق: أعداد غرف كبيرة جداً، ووصول ومغادرة مجموعات تُقاس بالحافلات لا بالسيارات، وذروات موسمية تغيّر إيقاع التشغيل بالكامل.</p>'
                    . '<h2>ما الذي يغيّره ذلك في التدريب</h2>'
                    . '<p>إدارة الحجم هي المهارة الأساسية. فإجراءات وصول ومغادرة المجموعات، وحجز الغرف، والتحكم بالمفاتيح عبر مئات الغرف، وتجهيز الغرف على نطاق واسع تحمل وزناً أكبر من تخصيص تجربة الضيف الفرد.</p>'
                    . '<h2>التدبير الفندقي على نطاق واسع</h2>'
                    . '<p>تُدرَّب تجهيز الغرف ودورات التنظيف العميق وطاقة المغسلة وتخطيط الإنتاجية كمنظومة واحدة، لأن موجة مغادرة تُدار بشكل سيئ لا يمكن تعويضها في اليوم نفسه.</p>'
                    . '<h2>أين تحتاج الفرق الدعم غالباً</h2>'
                    . '<p>التنسيق بين مغادرة مجموعة ووصول أخرى في الصباح نفسه، والحفاظ على معيار الغرفة تحت ضغط التجهيز، والتواصل الواضح متعدد اللغات مع ضيوف قد لا يشتركون مع الفريق في أي لغة.</p>')),

            array('hotel-training-madinah', 'تدريب-فندقي-المدينة', 'Madinah',
                array('Hotel Staff Training in Madinah',
                    '<p>Madinah shares the group-driven, high-turnover pattern of Makkah, with longer average stays in many properties and a strong emphasis on calm, unhurried guest handling.</p>'
                    . '<h2>What that changes about training</h2>'
                    . '<p>Group operations still dominate, but stayover service and long-stay housekeeping routines matter more, as does the ability to handle an elderly or less mobile guest with patience and without improvising.</p>'
                    . '<h2>Accessibility and assistance</h2>'
                    . '<p>Room assignment training covers accessibility needs explicitly, and front office and housekeeping teams are trained on assisting a guest with limited mobility, including during an evacuation.</p>'
                    . '<h2>Where teams usually need the most support</h2>'
                    . '<p>Stayover service that keeps a long-stay room to standard, complaint handling across a language barrier, and coordinating assistance requests between front office, housekeeping and security.</p>'),
                array('تدريب موظفي الفنادق في المدينة المنورة',
                    '<p>تشترك المدينة المنورة مع مكة في نمط المجموعات والدوران العالي، مع متوسط إقامة أطول في كثير من الفنادق وتركيز واضح على التعامل الهادئ غير المستعجل مع الضيوف.</p>'
                    . '<h2>ما الذي يغيّره ذلك في التدريب</h2>'
                    . '<p>ما تزال عمليات المجموعات هي الغالبة، لكن خدمة الإقامة المستمرة وروتين التدبير الفندقي للإقامات الطويلة أكثر أهمية، وكذلك القدرة على التعامل مع ضيف كبير في السن أو محدود الحركة بصبر ودون ارتجال.</p>'
                    . '<h2>إمكانية الوصول والمساعدة</h2>'
                    . '<p>يغطي تدريب توزيع الغرف احتياجات الوصول صراحةً، وتُدرَّب فرق الاستقبال والتدبير على مساعدة الضيف محدود الحركة، بما في ذلك أثناء الإخلاء.</p>'
                    . '<h2>أين تحتاج الفرق الدعم غالباً</h2>'
                    . '<p>خدمة الإقامة المستمرة التي تبقي غرفة الإقامة الطويلة على المعيار، ومعالجة الشكاوى عبر حاجز اللغة، وتنسيق طلبات المساعدة بين الاستقبال والتدبير والأمن.</p>')),

            array('hotel-training-al-khobar', 'تدريب-فندقي-الخبر', 'Al Khobar',
                array('Hotel Staff Training in Al Khobar',
                    '<p>Al Khobar and the surrounding Eastern Province corridor serve a steady industrial and corporate market, with long-stay contractor accommodation alongside conventional business travel.</p>'
                    . '<h2>What that changes about training</h2>'
                    . '<p>Long-stay operations matter: stayover service routines, linen change frequency for extended stays, and consistent handling of company-contracted rates and billing.</p>'
                    . '<h2>Engineering load</h2>'
                    . '<p>Higher occupancy over long periods increases wear, so preventive maintenance scheduling, HVAC routines and work order discipline are trained more heavily than in a low-occupancy leisure property.</p>'
                    . '<h2>Where teams usually need the most support</h2>'
                    . '<p>Keeping a long-stay room to arrival standard, managing corporate billing accurately across weeks, and running preventive maintenance without taking rooms out of a consistently full inventory.</p>'),
                array('تدريب موظفي الفنادق في الخبر',
                    '<p>تخدم الخبر والمنطقة الشرقية المحيطة بها سوقاً صناعياً ومؤسسياً مستقراً، مع سكن مقاولين للإقامات الطويلة إلى جانب سفر الأعمال التقليدي.</p>'
                    . '<h2>ما الذي يغيّره ذلك في التدريب</h2>'
                    . '<p>عمليات الإقامة الطويلة مهمة: روتين خدمة الإقامة المستمرة، وتكرار تغيير المفروشات للإقامات الممتدة، والتعامل المتسق مع الأسعار التعاقدية للشركات والفوترة.</p>'
                    . '<h2>عبء الهندسة</h2>'
                    . '<p>الإشغال المرتفع لفترات طويلة يزيد الاستهلاك، ولذلك تُدرَّب جدولة الصيانة الوقائية وروتين التكييف وانضباط أوامر العمل بدرجة أكبر منها في فندق ترفيهي منخفض الإشغال.</p>'
                    . '<h2>أين تحتاج الفرق الدعم غالباً</h2>'
                    . '<p>إبقاء غرفة الإقامة الطويلة على معيار الوصول، وإدارة فوترة الشركات بدقة عبر أسابيع، وتنفيذ الصيانة الوقائية دون إخراج غرف من مخزون ممتلئ باستمرار.</p>')),

            array('hotel-training-dammam', 'تدريب-فندقي-الدمام', 'Dammam',
                array('Hotel Staff Training in Dammam',
                    '<p>Dammam properties, particularly those near the airport, handle a high share of short and transit stays, with arrivals and departures spread across the full twenty four hours.</p>'
                    . '<h2>What that changes about training</h2>'
                    . '<p>Night shift competence is not optional. Night audit, overnight check-in, late arrival handling and a night team that can run the property without a daytime manager present are all core rather than advanced.</p>'
                    . '<h2>Fast turnaround</h2>'
                    . '<p>Short stays mean more departures per room per month, so housekeeping turnaround speed and front office departure processing carry more weight than long-stay routines.</p>'
                    . '<h2>Where teams usually need the most support</h2>'
                    . '<p>Running a competent night shift with a small team, handling an early morning arrival before the standard check-in time, and maintaining the room standard at a high turnaround rate.</p>'),
                array('تدريب موظفي الفنادق في الدمام',
                    '<p>تتعامل فنادق الدمام، خاصة القريبة من المطار، مع نسبة عالية من الإقامات القصيرة والعابرة، مع وصول ومغادرة موزعين على مدار أربع وعشرين ساعة.</p>'
                    . '<h2>ما الذي يغيّره ذلك في التدريب</h2>'
                    . '<p>كفاءة الوردية الليلية ليست اختيارية. فالتدقيق الليلي والتسجيل الليلي والتعامل مع الوصول المتأخر وفريق ليلي قادر على تشغيل الفندق دون حضور مدير نهاري، كلها أساسيات لا مستوى متقدماً.</p>'
                    . '<h2>التجهيز السريع</h2>'
                    . '<p>الإقامات القصيرة تعني مغادرات أكثر لكل غرفة شهرياً، ولذلك تحمل سرعة تجهيز التدبير الفندقي ومعالجة المغادرة في الاستقبال وزناً أكبر من روتين الإقامات الطويلة.</p>'
                    . '<h2>أين تحتاج الفرق الدعم غالباً</h2>'
                    . '<p>تشغيل وردية ليلية كفؤة بفريق صغير، والتعامل مع وصول في الصباح الباكر قبل موعد التسجيل القياسي، والحفاظ على معيار الغرفة مع معدل تجهيز مرتفع.</p>')),

            array('hotel-training-alula', 'تدريب-فندقي-العلا', 'AlUla',
                array('Hotel Staff Training in AlUla',
                    '<p>AlUla properties are resort and experience-led, often with smaller room counts, longer leisure stays and a guest who has travelled specifically for the destination.</p>'
                    . '<h2>What that changes about training</h2>'
                    . '<p>Concierge and local knowledge carry real weight here, because the guest expects the team to know the destination. Guest experience, personalisation and complaint recovery matter more than throughput speed.</p>'
                    . '<h2>Smaller teams, wider roles</h2>'
                    . '<p>A smaller property means one person covers more ground, so cross-department training is the norm rather than the exception: a front office agent may also need food and beverage service standards and basic emergency response leadership.</p>'
                    . '<h2>Where teams usually need the most support</h2>'
                    . '<p>Building a local knowledge base the whole team can use, handling a high-expectation leisure guest, and running an emergency response with a small overnight team.</p>'),
                array('تدريب موظفي الفنادق في العلا',
                    '<p>فنادق العلا منتجعية قائمة على التجربة، غالباً بأعداد غرف أصغر وإقامات ترفيهية أطول وضيف سافر خصيصاً من أجل الوجهة.</p>'
                    . '<h2>ما الذي يغيّره ذلك في التدريب</h2>'
                    . '<p>يحمل الكونسيرج والمعرفة المحلية وزناً حقيقياً هنا، لأن الضيف يتوقع أن يعرف الفريق الوجهة. وتجربة الضيف والتخصيص واستعادة رضا الضيف أهم من سرعة الإنجاز.</p>'
                    . '<h2>فرق أصغر وأدوار أوسع</h2>'
                    . '<p>الفندق الأصغر يعني أن الشخص الواحد يغطي مجالاً أوسع، ولذلك يكون التدريب متعدد الأقسام هو القاعدة لا الاستثناء: فقد يحتاج موظف الاستقبال أيضاً معايير خدمة الأغذية والمشروبات وقيادة أساسية للاستجابة للطوارئ.</p>'
                    . '<h2>أين تحتاج الفرق الدعم غالباً</h2>'
                    . '<p>بناء قاعدة معرفة محلية يستخدمها الفريق كله، والتعامل مع ضيف ترفيهي عالي التوقعات، وتنفيذ استجابة للطوارئ بفريق ليلي صغير.</p>')),

            array('hotel-training-abha', 'تدريب-فندقي-أبها', 'Abha',
                array('Hotel Staff Training in Abha',
                    '<p>Abha and the wider Aseer highlands run a strongly seasonal leisure pattern, with summer peaks, a high proportion of family and group bookings, and properties that may open capacity seasonally.</p>'
                    . '<h2>What that changes about training</h2>'
                    . '<p>Seasonal recruitment means training has to work for people who join at short notice. Mandatory safety training, the core departmental sequence and a clear induction matter more than long development programmes.</p>'
                    . '<h2>Family service</h2>'
                    . '<p>Family bookings change room assignment, housekeeping and restaurant service, so training covers connecting rooms, larger party service and family seating explicitly.</p>'
                    . '<h2>Where teams usually need the most support</h2>'
                    . '<p>Bringing seasonal joiners to a safe standard quickly, holding the service standard through a summer peak, and pre-opening or seasonal reopening room readiness.</p>'),
                array('تدريب موظفي الفنادق في أبها',
                    '<p>تعمل أبها ومرتفعات عسير على نمط ترفيهي موسمي بقوة، بذروة صيفية ونسبة عالية من حجوزات العائلات والمجموعات وفنادق قد تفتح طاقة استيعابية موسمياً.</p>'
                    . '<h2>ما الذي يغيّره ذلك في التدريب</h2>'
                    . '<p>التوظيف الموسمي يعني أن التدريب يجب أن ينجح مع أشخاص ينضمون بمهلة قصيرة. ولذلك يكون تدريب السلامة الإلزامي والتسلسل الأساسي للقسم والتعريف الواضح أهم من برامج التطوير الطويلة.</p>'
                    . '<h2>خدمة العائلات</h2>'
                    . '<p>حجوزات العائلات تغيّر توزيع الغرف والتدبير الفندقي وخدمة المطعم، ولذلك يغطي التدريب الغرف المتصلة وخدمة المجموعات الكبيرة وجلسات العائلات صراحةً.</p>'
                    . '<h2>أين تحتاج الفرق الدعم غالباً</h2>'
                    . '<p>الوصول بالموظفين الموسميين إلى مستوى آمن بسرعة، والحفاظ على معيار الخدمة خلال الذروة الصيفية، وجاهزية الغرف قبل الافتتاح أو إعادة الفتح الموسمي.</p>')),
        );
    }

    // --------------------------------------------------------------- articles

    /**
     * slug_en, slug_ar, category code, topic code, EN [title, excerpt, body], AR [...], tags
     */
    public static function articles() {
        return array(

            array('how-to-train-a-new-front-desk-agent', 'كيف-تدرب-موظف-استقبال-جديد',
                'front-office', 'front-office-training',
                array('How to Train a New Front Desk Agent in Their First Two Weeks',
                    'A two week sequence that gets a new front office agent to an unsupervised shift without skipping the safety and privacy training that protects the hotel.',
                    '<p>A new front desk agent is usually put on shift too early or too late. Too early and they learn the hotel bad habits before they learn the standard. Too late and they spend a fortnight shadowing without responsibility, which teaches very little. A fixed two week sequence avoids both.</p>'
                    . '<h2>Days one and two: the property, not the desk</h2>'
                    . '<p>Emergency exits, assembly point, who their manager is, where their locker is, and the reporting lines. Fire safety and emergency response are assigned on day one with a day seven due date, because an agent who cannot direct a guest to a stairwell is a risk regardless of how well they check people in.</p>'
                    . '<h2>Days three to five: the sequence</h2>'
                    . '<p>Front office fundamentals, telephone etiquette, then check-in and check-out as separate courses. The agent shadows a full arrival wave and a full departure wave, and runs the checklist alongside an experienced agent rather than just watching.</p>'
                    . '<h2>Days six to eight: the system and the records</h2>'
                    . '<p>Property management system training, guest registration, and guest privacy. Privacy is not an afterthought: room number discipline, what may be disclosed to a caller, and how identification documents are handled are all learned before the agent has unsupervised system access.</p>'
                    . '<h2>Days nine to eleven: under supervision</h2>'
                    . '<p>The agent works the desk with a supervisor present but not intervening unless necessary. Every exception, the first difficult guest and the first system error are worth more than another course.</p>'
                    . '<h2>Days twelve to fourteen: assessment and release</h2>'
                    . '<p>Complete the assessments, review the checklist results with the supervisor, and confirm the mandatory training is recorded as complete. Release to an unsupervised shift is a decision with a name attached to it, recorded against the employee, not an assumption that enough time has passed.</p>'
                    . '<h2>What comes after</h2>'
                    . '<p>Room assignment, complaint handling and upselling in the second month, and the night audit only once the day sequence is automatic.</p>'),
                array('كيف تدرب موظف استقبال جديد في أسبوعيه الأولين',
                    'تسلسل من أسبوعين يصل بموظف الاستقبال الجديد إلى وردية دون إشراف، دون تخطي تدريب السلامة والخصوصية الذي يحمي الفندق.',
                    '<p>غالباً ما يوضع موظف الاستقبال الجديد في الوردية مبكراً جداً أو متأخراً جداً. مبكراً جداً فيتعلم عادات الفندق السيئة قبل أن يتعلم المعيار. ومتأخراً جداً فيقضي أسبوعين في المراقبة دون مسؤولية، وهو ما يعلّم القليل. والتسلسل الثابت لأسبوعين يتجنب الأمرين.</p>'
                    . '<h2>اليومان الأول والثاني: الفندق لا المكتب</h2>'
                    . '<p>مخارج الطوارئ ونقطة التجمع ومن هو مديره وأين خزانته وخطوط الإبلاغ. وتُسنَد السلامة من الحريق والاستجابة للطوارئ في اليوم الأول بتاريخ استحقاق في اليوم السابع، لأن الموظف الذي لا يستطيع توجيه ضيف إلى درج الطوارئ يمثل خطراً مهما أجاد تسجيل الوصول.</p>'
                    . '<h2>الأيام من الثالث إلى الخامس: التسلسل</h2>'
                    . '<p>أساسيات مكتب الاستقبال، وآداب المكالمات، ثم تسجيل الوصول والمغادرة كدورتين منفصلتين. ويرافق الموظف موجة وصول كاملة وموجة مغادرة كاملة، وينفذ قائمة التحقق مع موظف خبير بدل الاكتفاء بالمشاهدة.</p>'
                    . '<h2>الأيام من السادس إلى الثامن: النظام والسجلات</h2>'
                    . '<p>تدريب نظام إدارة الفندق، وتسجيل بيانات الضيف، وخصوصية الضيف. والخصوصية ليست أمراً لاحقاً: فانضباط رقم الغرفة، وما يجوز الإفصاح عنه للمتصل، وطريقة التعامل مع وثائق الهوية، كلها تُتعلَّم قبل حصول الموظف على وصول غير مراقب للنظام.</p>'
                    . '<h2>الأيام من التاسع إلى الحادي عشر: تحت الإشراف</h2>'
                    . '<p>يعمل الموظف في المكتب بحضور مشرف دون تدخل إلا عند الضرورة. وكل حالة استثنائية، وأول ضيف صعب، وأول خطأ في النظام، تساوي أكثر من دورة إضافية.</p>'
                    . '<h2>الأيام من الثاني عشر إلى الرابع عشر: التقييم والتحرير</h2>'
                    . '<p>إكمال التقييمات، ومراجعة نتائج قائمة التحقق مع المشرف، وتأكيد تسجيل التدريب الإلزامي كمكتمل. والتحرير للعمل دون إشراف قرار له اسم مسؤول، يُسجَّل على الموظف، لا افتراض بأن وقتاً كافياً قد مضى.</p>'
                    . '<h2>ما يأتي بعد ذلك</h2>'
                    . '<p>توزيع الغرف ومعالجة الشكاوى والبيع الإضافي في الشهر الثاني، والتدقيق الليلي فقط بعد أن يصبح تسلسل النهار تلقائياً.</p>'),
                array('front-office', 'onboarding', 'hotel-training')),

            array('writing-a-hotel-sop-people-actually-follow', 'كتابة-إجراء-فندقي-يُتّبع-فعلاً',
                'management', 'hotel-sop-training',
                array('Writing a Hotel SOP People Actually Follow',
                    'Most hotel procedures fail for the same four reasons. Each one is fixable before the document is published.',
                    '<p>Most hotels have procedures. Far fewer have procedures that change what happens on a shift. The gap is almost always in the writing, not in the team.</p>'
                    . '<h2>It describes the outcome instead of the steps</h2>'
                    . '<p>"Ensure the room meets the required standard" is not a procedure. "Clean the bathroom in order: mirror, basin, bath or shower, toilet last, then floor" is. If a sentence cannot be performed, it belongs in the quality standard section, not in the procedure.</p>'
                    . '<h2>It has no escalation route</h2>'
                    . '<p>Every procedure meets a case it did not anticipate, usually at the worst possible hour. A procedure without a named escalation route forces the employee to invent one, and the invented route is the one that produces the incident report. State who is called, for what, and how urgently.</p>'
                    . '<h2>Nobody can tell which version they read</h2>'
                    . '<p>An undated procedure in a shared folder is unverifiable. Give every procedure a version number, an author, an approver, an effective date and a review date, keep the previous versions readable, and ask people to acknowledge each new version. Then a manager can answer the only question that matters during an audit: who had read the version that was in force.</p>'
                    . '<h2>It was written by someone who does not do the work</h2>'
                    . '<p>A procedure written in an office and handed to a department is usually missing a step the department cannot skip. Write the draft with the people who perform the task, walk it with them once, and correct it before publishing rather than after the first failure.</p>'
                    . '<h2>A test before publishing</h2>'
                    . '<p>Hand the draft to someone who has never done the task and ask them to follow it exactly. Every question they ask is a missing step.</p>'),
                array('كتابة إجراء تشغيل فندقي يُتّبع فعلاً',
                    'تفشل معظم الإجراءات الفندقية للأسباب الأربعة نفسها. وكل سبب منها قابل للإصلاح قبل نشر الوثيقة.',
                    '<p>معظم الفنادق لديها إجراءات. وعدد أقل بكثير لديه إجراءات تغيّر ما يحدث فعلاً في الوردية. والفجوة تكون دائماً تقريباً في الكتابة لا في الفريق.</p>'
                    . '<h2>يصف النتيجة بدل الخطوات</h2>'
                    . '<p>«تأكد من أن الغرفة تحقق المعيار المطلوب» ليست إجراءً. أما «نظّف دورة المياه بالترتيب: المرآة، المغسلة، البانيو أو الدش، المرحاض أخيراً، ثم الأرضية» فهي إجراء. وإذا كانت الجملة غير قابلة للتنفيذ فمكانها قسم معيار الجودة لا قسم الإجراء.</p>'
                    . '<h2>لا يحتوي مسار تصعيد</h2>'
                    . '<p>كل إجراء يواجه حالة لم يتوقعها، غالباً في أسوأ ساعة ممكنة. والإجراء بلا مسار تصعيد محدد يجبر الموظف على ابتكار مسار، والمسار المبتكر هو الذي ينتج تقرير الحادث. حدّد من يُتصل به، ولأي سبب، وبأي درجة استعجال.</p>'
                    . '<h2>لا أحد يعرف أي إصدار قرأ</h2>'
                    . '<p>الإجراء غير المؤرخ في مجلد مشترك غير قابل للتحقق. امنح كل إجراء رقم إصدار ومؤلفاً ومعتمِداً وتاريخ سريان وتاريخ مراجعة، وأبقِ الإصدارات السابقة قابلة للقراءة، واطلب الإقرار بكل إصدار جديد. عندها يستطيع المدير الإجابة عن السؤال الوحيد المهم أثناء التدقيق: من قرأ الإصدار الذي كان سارياً.</p>'
                    . '<h2>كتبه شخص لا يؤدي العمل</h2>'
                    . '<p>الإجراء المكتوب في مكتب والمُسلَّم إلى قسم ينقصه عادةً خطوة لا يستطيع القسم تخطيها. اكتب المسودة مع من ينفذون المهمة، ونفّذها معهم مرة واحدة، وصحّحها قبل النشر لا بعد أول إخفاق.</p>'
                    . '<h2>اختبار قبل النشر</h2>'
                    . '<p>سلّم المسودة لشخص لم يؤدِّ المهمة قط واطلب منه اتباعها حرفياً. كل سؤال يسأله خطوة ناقصة.</p>'),
                array('sop', 'hotel-operations', 'quality')),

            array('hotel-food-safety-records-that-survive-an-inspection', 'سجلات-سلامة-الغذاء-التي-تجتاز-التفتيش',
                'kitchen', 'food-safety-training',
                array('Hotel Food Safety Records That Survive an Inspection',
                    'What a complete temperature log looks like, why blank cells are the most common finding, and how to fix the cause rather than the paperwork.',
                    '<p>A kitchen can be genuinely safe and still fail on records. An inspector cannot taste safety, so the log is the evidence, and an incomplete log is treated as an absence of control rather than an absence of paperwork.</p>'
                    . '<h2>What a complete log contains</h2>'
                    . '<p>A calibration entry at the start of each shift. Chilled and frozen storage recorded twice per shift. Cooking temperatures confirmed before service. Hot holding recorded every two hours. Cooling times recorded against the two stage target. Reheating recorded once. And against every out of range reading, a recorded corrective action with a name.</p>'
                    . '<h2>Why cells end up blank</h2>'
                    . '<p>Almost never because the team does not care. Usually because the log lives somewhere the chef is not standing when they take the reading, because the probe is shared and unavailable at the moment of need, or because the shift was busy and the intention was to fill it in later. Recording from memory at the end of a shift is not a record, and it is usually visible as one.</p>'
                    . '<h2>Fixing the cause</h2>'
                    . '<p>Put the log where the reading is taken. Give each section its own probe. Make the two-hourly hot holding check a named person responsibility on the shift board rather than a shared one. Then check the log daily rather than weekly, because a gap found the next morning can still be explained.</p>'
                    . '<h2>The training point</h2>'
                    . '<p>Treat log completion as part of the competence, not as administration. A chef who has been trained that the record is part of the control fills it in at the moment of the reading, which is the only time it is true.</p>'),
                array('سجلات سلامة الغذاء الفندقية التي تجتاز التفتيش',
                    'كيف يبدو سجل الحرارة المكتمل، ولماذا الخانات الفارغة أكثر الملاحظات شيوعاً، وكيف تعالج السبب لا الأوراق.',
                    '<p>يمكن أن يكون المطبخ آمناً فعلاً ويُخفق في السجلات. فالمفتش لا يستطيع تذوق السلامة، ولذلك يكون السجل هو الدليل، ويُعامل السجل الناقص كغياب للتحكم لا كغياب لأوراق.</p>'
                    . '<h2>ما يحتويه السجل المكتمل</h2>'
                    . '<p>قيد معايرة في بداية كل وردية. وتسجيل التخزين المبرد والمجمد مرتين في الوردية. وتأكيد حرارة الطهي قبل التقديم. وتسجيل الحفظ الساخن كل ساعتين. وتسجيل أزمنة التبريد مقابل هدف المرحلتين. وتسجيل إعادة التسخين مرة واحدة. وأمام كل قراءة خارج النطاق إجراء تصحيحي مسجل باسم.</p>'
                    . '<h2>لماذا تبقى الخانات فارغة</h2>'
                    . '<p>نادراً جداً بسبب عدم الاهتمام. غالباً لأن السجل موجود في مكان لا يقف فيه الطاهي وقت أخذ القراءة، أو لأن المجس مشترك وغير متاح لحظة الحاجة، أو لأن الوردية كانت مزدحمة والنية أن يُملأ لاحقاً. والتسجيل من الذاكرة في نهاية الوردية ليس سجلاً، وغالباً ما يظهر ذلك بوضوح.</p>'
                    . '<h2>معالجة السبب</h2>'
                    . '<p>ضع السجل حيث تُؤخذ القراءة. وامنح كل قسم مجسه الخاص. واجعل فحص الحفظ الساخن كل ساعتين مسؤولية شخص محدد بالاسم على لوحة الوردية لا مسؤولية مشتركة. ثم افحص السجل يومياً لا أسبوعياً، لأن الفجوة المكتشفة في صباح اليوم التالي ما زال يمكن تفسيرها.</p>'
                    . '<h2>النقطة التدريبية</h2>'
                    . '<p>تعامل مع اكتمال السجل كجزء من الكفاءة لا كعمل إداري. فالطاهي الذي دُرِّب على أن السجل جزء من التحكم يملؤه لحظة القراءة، وهي اللحظة الوحيدة التي يكون فيها صحيحاً.</p>'),
                array('food-safety', 'compliance', 'kitchen')),

            array('measuring-hotel-training-completion-honestly', 'قياس-إكمال-التدريب-الفندقي-بصدق',
                'management', 'hotel-compliance-training',
                array('Measuring Hotel Training Completion Honestly',
                    'Why attendance is not completion, why a completion percentage can hide the only gap that matters, and what to report instead.',
                    '<p>Training reports are easy to make look good and hard to make useful. Three habits cause most of the difference.</p>'
                    . '<h2>Attendance is not completion</h2>'
                    . '<p>A signed attendance sheet records that someone was in the room. It does not record that they can do the task. A completion record tied to an assessment result does. Report the second, and keep the first only where a classroom session genuinely forms part of the requirement.</p>'
                    . '<h2>An average hides the gap</h2>'
                    . '<p>A property at ninety per cent completion sounds healthy until you notice the missing ten per cent is the entire night shift, or every new joiner from the last month. Report by department, by property and by person, and put the overdue list above the percentage, because the list is what someone can act on.</p>'
                    . '<h2>Completion without currency is not compliance</h2>'
                    . '<p>Safety and food safety training expires. A report that counts an expired certificate as complete is worse than no report, because it creates confidence that is not warranted. Show certificates approaching expiry as a standing item, not as an exception report.</p>'
                    . '<h2>What to put in front of a manager</h2>'
                    . '<p>Four things: who is overdue right now, who becomes overdue this week, which certificates expire this month, and which procedures have unacknowledged current versions. Everything else is context.</p>'),
                array('قياس إكمال التدريب الفندقي بصدق',
                    'لماذا الحضور ليس إكمالاً، ولماذا قد تخفي نسبة الإكمال الفجوة الوحيدة المهمة، وما الذي يُعرض بدلاً من ذلك.',
                    '<p>من السهل جعل تقارير التدريب تبدو جيدة، ومن الصعب جعلها مفيدة. وثلاث عادات تصنع معظم الفرق.</p>'
                    . '<h2>الحضور ليس إكمالاً</h2>'
                    . '<p>كشف الحضور الموقّع يوثق أن شخصاً كان في القاعة. ولا يوثق أنه يستطيع أداء المهمة. أما سجل الإكمال المرتبط بنتيجة تقييم فيوثق ذلك. اعرض الثاني، واحتفظ بالأول فقط حيث تكون الجلسة الحضورية جزءاً فعلياً من المتطلب.</p>'
                    . '<h2>المتوسط يخفي الفجوة</h2>'
                    . '<p>فندق عند تسعين بالمئة من الإكمال يبدو بحال جيدة حتى تلاحظ أن العشرة بالمئة الناقصة هي الوردية الليلية بأكملها، أو كل من انضم في الشهر الماضي. اعرض حسب القسم والفندق والشخص، وضع قائمة المتأخرين فوق النسبة، لأن القائمة هي ما يمكن التصرف بناءً عليه.</p>'
                    . '<h2>الإكمال دون سريان ليس التزاماً</h2>'
                    . '<p>تدريب السلامة وسلامة الغذاء تنتهي صلاحيته. والتقرير الذي يحسب شهادة منتهية كمكتملة أسوأ من عدم وجود تقرير، لأنه يخلق ثقة غير مبررة. اعرض الشهادات المقتربة من الانتهاء كبند دائم لا كتقرير استثنائي.</p>'
                    . '<h2>ما الذي يُوضع أمام المدير</h2>'
                    . '<p>أربعة أشياء: من هو متأخر الآن، ومن سيصبح متأخراً هذا الأسبوع، وأي شهادات تنتهي هذا الشهر، وأي إجراءات لها إصدارات حالية لم يُقَر بها. وكل ما عدا ذلك سياق.</p>'),
                array('reporting', 'compliance', 'hotel-training')),

            array('training-hotel-teams-in-arabic-and-english', 'تدريب-فرق-الفنادق-بالعربية-والإنجليزية',
                'management', 'front-office-training',
                array('Training Hotel Teams in Arabic and English Without Losing Either',
                    'Why a translated course is not a bilingual course, and what a genuinely bilingual hotel training programme looks like in practice.',
                    '<p>Most hotel teams in Saudi Arabia are genuinely bilingual, but not uniformly. A housekeeping floor may work primarily in Arabic, a front desk primarily in English, and a kitchen in a mix that changes by shift. A training programme that picks one language quietly excludes part of the team.</p>'
                    . '<h2>Translation is not the same as authoring</h2>'
                    . '<p>A machine translated course reads as translated, and a procedural instruction that reads awkwardly gets misread under pressure. Author the Arabic version as Arabic content, with the terms the department actually uses on shift, and the English version as English content. Keep them aligned in meaning, not word for word.</p>'
                    . '<h2>Right to left is a layout, not a text direction</h2>'
                    . '<p>An Arabic page is not an English page with the text flipped. Navigation, progress indicators, checklists, tables and form fields all need to read naturally from the right. A numbered procedure that runs left to right in an Arabic interface is a procedure people will misread step order in.</p>'
                    . '<h2>Let the learner choose per session</h2>'
                    . '<p>Language should be a per-session choice, not an account setting decided once at signup. A learner might take a safety course in Arabic and a property management system course in English because the system itself is in English. Progress must carry across the switch.</p>'
                    . '<h2>Terms that should not be translated</h2>'
                    . '<p>System field names, code words used on the radio, and any term printed on equipment should stay in the language they appear in on the job, with the explanation in the learner language. Translating a button label that the learner will see in English on screen creates a gap rather than closing one.</p>'),
                array('تدريب فرق الفنادق بالعربية والإنجليزية دون خسارة أي منهما',
                    'لماذا الدورة المترجمة ليست دورة ثنائية اللغة، وكيف يبدو برنامج التدريب الفندقي ثنائي اللغة فعلياً.',
                    '<p>معظم فرق الفنادق في السعودية ثنائية اللغة فعلاً، لكن ليس بالتساوي. فقد يعمل طابق التدبير الفندقي بالعربية أساساً، ومكتب الاستقبال بالإنجليزية أساساً، والمطبخ بمزيج يتغير حسب الوردية. وبرنامج التدريب الذي يختار لغة واحدة يستبعد جزءاً من الفريق بصمت.</p>'
                    . '<h2>الترجمة ليست كالتأليف</h2>'
                    . '<p>الدورة المترجمة آلياً تُقرأ كترجمة، والتعليمة الإجرائية التي تُقرأ بصعوبة يُساء فهمها تحت الضغط. ألّف النسخة العربية كمحتوى عربي بالمصطلحات التي يستخدمها القسم فعلاً في الوردية، والنسخة الإنجليزية كمحتوى إنجليزي. وأبقهما متطابقتين في المعنى لا في الكلمات.</p>'
                    . '<h2>من اليمين إلى اليسار تخطيط لا اتجاه نص</h2>'
                    . '<p>الصفحة العربية ليست صفحة إنجليزية معكوسة النص. فالتنقل ومؤشرات التقدم وقوائم التحقق والجداول وحقول النماذج كلها يجب أن تُقرأ طبيعياً من اليمين. والإجراء المرقّم الذي يسير من اليسار لليمين في واجهة عربية إجراء سيُساء فهم ترتيب خطواته.</p>'
                    . '<h2>دع المتدرب يختار في كل جلسة</h2>'
                    . '<p>ينبغي أن تكون اللغة اختياراً لكل جلسة لا إعداد حساب يُحدَّد مرة عند التسجيل. فقد يأخذ المتدرب دورة سلامة بالعربية ودورة نظام إدارة الفندق بالإنجليزية لأن النظام نفسه بالإنجليزية. ويجب أن ينتقل التقدم عبر التبديل.</p>'
                    . '<h2>مصطلحات لا ينبغي ترجمتها</h2>'
                    . '<p>أسماء حقول الأنظمة، والكلمات المستخدمة على اللاسلكي، وأي مصطلح مطبوع على المعدات، ينبغي أن تبقى باللغة التي تظهر بها في العمل، مع الشرح بلغة المتدرب. فترجمة اسم زر سيراه المتدرب بالإنجليزية على الشاشة تصنع فجوة بدل أن تغلقها.</p>'),
                array('bilingual', 'arabic', 'hotel-training')),

            array('pre-opening-hotel-training-checklist', 'قائمة-تدريب-الفندق-قبل-الافتتاح',
                'management', 'hotel-compliance-training',
                array('Pre-opening Hotel Training: What Has to Be Done Before the First Guest',
                    'The training and procedure work a pre-opening team cannot defer, ordered by when it has to happen.',
                    '<p>Pre-opening compresses a year of operational build into a few months, and training is usually the part that gets pushed back because it feels less urgent than the building. It is not, because the opening date does not move but the readiness of the team does.</p>'
                    . '<h2>Ninety days out: the procedures</h2>'
                    . '<p>Write the procedures before you hire the people who will follow them. A department that arrives to no written standard invents one, and unwinding an invented standard later costs more than writing it now. Each procedure needs an owner, an approver and a review date from the start.</p>'
                    . '<h2>Sixty days out: the mandatory set</h2>'
                    . '<p>Fire safety, emergency response and the property induction assigned to every employee with a due date before the soft opening, not before the grand opening. Chemical safety before housekeeping touches a product. Food safety before the kitchen runs a test service.</p>'
                    . '<h2>Thirty days out: the departmental sequence</h2>'
                    . '<p>Each department completes its core sequence and runs its checklists on the real floor, not in a training room. A housekeeping team should have cleaned real rooms in the actual building before the first guest, including the rooms that are not finished yet.</p>'
                    . '<h2>Fourteen days out: the dry run</h2>'
                    . '<p>Run a full simulated day with staff acting as guests: arrivals, a restaurant service, a complaint, a maintenance call, and an evacuation drill. Every gap it exposes is a gap a guest would have found instead.</p>'
                    . '<h2>Before release</h2>'
                    . '<p>No room is released for sale with an open fire safety, electrical or water safety item, regardless of the opening date. That decision has to be made once, in writing, before there is pressure on it.</p>'),
                array('التدريب الفندقي قبل الافتتاح: ما يجب إنجازه قبل أول ضيف',
                    'أعمال التدريب والإجراءات التي لا يستطيع فريق ما قبل الافتتاح تأجيلها، مرتبة حسب موعد تنفيذها.',
                    '<p>تضغط مرحلة ما قبل الافتتاح بناءً تشغيلياً يستغرق عاماً في بضعة أشهر، والتدريب عادةً هو الجزء الذي يُؤجَّل لأنه يبدو أقل إلحاحاً من المبنى. وهو ليس كذلك، لأن موعد الافتتاح لا يتحرك بينما جاهزية الفريق تتحرك.</p>'
                    . '<h2>قبل تسعين يوماً: الإجراءات</h2>'
                    . '<p>اكتب الإجراءات قبل توظيف من سيتبعها. فالقسم الذي يصل ولا يجد معياراً مكتوباً يبتكر معياراً، وتفكيك معيار مبتكر لاحقاً أكلف من كتابته الآن. ولكل إجراء مالك ومعتمِد وتاريخ مراجعة منذ البداية.</p>'
                    . '<h2>قبل ستين يوماً: المجموعة الإلزامية</h2>'
                    . '<p>السلامة من الحريق والاستجابة للطوارئ وتعريف الفندق، مُسنَدة لكل موظف بتاريخ استحقاق قبل الافتتاح التجريبي لا قبل الافتتاح الرسمي. وسلامة المواد الكيميائية قبل أن يلمس التدبير الفندقي أي منتج. وسلامة الغذاء قبل أن يشغّل المطبخ خدمة تجريبية.</p>'
                    . '<h2>قبل ثلاثين يوماً: تسلسل الأقسام</h2>'
                    . '<p>يكمل كل قسم تسلسله الأساسي وينفذ قوائم تحققه في الموقع الفعلي لا في قاعة تدريب. وينبغي أن يكون فريق التدبير الفندقي قد نظّف غرفاً حقيقية في المبنى نفسه قبل أول ضيف، بما في ذلك الغرف غير المكتملة بعد.</p>'
                    . '<h2>قبل أربعة عشر يوماً: التشغيل التجريبي</h2>'
                    . '<p>نفّذ يوماً محاكياً كاملاً بموظفين يؤدون دور الضيوف: وصول، وخدمة مطعم، وشكوى، وطلب صيانة، وتمرين إخلاء. وكل فجوة يكشفها هي فجوة كان الضيف سيكتشفها بدلاً منه.</p>'
                    . '<h2>قبل التحرير</h2>'
                    . '<p>لا تُحرَّر أي غرفة للبيع مع بند مفتوح يخص سلامة الحريق أو الكهرباء أو المياه، مهما كان موعد الافتتاح. وهذا قرار يجب اتخاذه مرة واحدة كتابةً قبل أن يقع عليه ضغط.</p>'),
                array('pre-opening', 'hotel-operations', 'compliance')),
        );
    }

    // ------------------------------------------------------------------- FAQs

    /**
     * Answer-engine oriented: each entry is a question a person would actually
     * type or ask aloud, answered directly in the first sentence.
     * scope_type, scope_key, EN q, EN a, AR q, AR a
     */
    public static function faqs() {
        return array(
            array('global', null,
                'What is Hospitality Academy?',
                'Hospitality Academy is a hotel training and standard operating procedure platform. It provides role-based courses for hotel departments, a versioned SOP library, assessments, and certificates that can be verified publicly. Courses are available in Arabic and English.',
                'ما هي أكاديمية الضيافة؟',
                'أكاديمية الضيافة منصة تدريب فندقي وإجراءات تشغيل قياسية. توفر دورات قائمة على الدور الوظيفي لأقسام الفنادق، ومكتبة إجراءات لها إصدارات، وتقييمات، وشهادات يمكن التحقق منها علناً. والدورات متاحة بالعربية والإنجليزية.'),
            array('global', null,
                'Are the courses available in Arabic?',
                'Yes. Every course, lesson, procedure and public page exists in Arabic and English as separately written content, and the Arabic interface uses a genuine right-to-left layout rather than a mirrored English one.',
                'هل الدورات متاحة بالعربية؟',
                'نعم. كل دورة ودرس وإجراء وصفحة عامة موجودة بالعربية والإنجليزية كمحتوى مكتوب بشكل منفصل، والواجهة العربية تستخدم تخطيطاً حقيقياً من اليمين إلى اليسار لا واجهة إنجليزية معكوسة.'),
            array('global', null,
                'How do I verify a Hospitality Academy certificate?',
                'Enter the verification code printed on the certificate on the public verification page. The page returns one of four results directly: valid, expired, revoked, or not found. No account is needed to check a certificate.',
                'كيف أتحقق من شهادة أكاديمية الضيافة؟',
                'أدخل رمز التحقق المطبوع على الشهادة في صفحة التحقق العامة. تعيد الصفحة إحدى أربع نتائج مباشرة: سارية أو منتهية أو ملغاة أو غير موجودة. ولا يلزم حساب للتحقق من شهادة.'),
            array('global', null,
                'How long does a hotel training course take?',
                'Most courses take between forty five minutes and three hours of study, and can be split across shifts. Each course page states its own duration, and progress is saved so a learner can stop and resume.',
                'كم تستغرق الدورة التدريبية الفندقية؟',
                'تستغرق معظم الدورات بين خمس وأربعين دقيقة وثلاث ساعات من الدراسة، ويمكن تقسيمها على عدة ورديات. وتذكر كل صفحة دورة مدتها، ويُحفظ التقدم بحيث يستطيع المتدرب التوقف والاستئناف.'),
            array('global', null,
                'Can a hotel assign training to a whole department at once?',
                'Yes. A training assignment can target an individual, a department, a property, a job role or the whole organization, with a start date, a due date, reminders before the deadline and escalation to the line manager when an item becomes overdue.',
                'هل يستطيع الفندق إسناد التدريب لقسم كامل دفعة واحدة؟',
                'نعم. يمكن أن يستهدف التكليف التدريبي موظفاً أو قسماً أو فندقاً أو مسمى وظيفياً أو المنشأة بأكملها، بتاريخ بدء وتاريخ استحقاق وتذكيرات قبل الموعد وتصعيد إلى المدير المباشر عند تأخر البند.'),
            array('global', null,
                'What is a hotel SOP?',
                'A hotel SOP, or standard operating procedure, is a written document that sets out exactly how a task is performed: its purpose, scope, responsibilities, required tools, numbered steps, checklist, safety notes, quality standard and escalation route. It carries a version number so a hotel can prove which version was in force at any date.',
                'ما هو إجراء التشغيل القياسي الفندقي؟',
                'إجراء التشغيل القياسي الفندقي وثيقة مكتوبة تحدد بدقة كيفية أداء المهمة: غرضها ونطاقها والمسؤوليات والأدوات المطلوبة والخطوات المرقمة وقائمة التحقق وملاحظات السلامة ومعيار الجودة ومسار التصعيد. ويحمل رقم إصدار ليتمكن الفندق من إثبات الإصدار الساري في أي تاريخ.'),
            array('global', null,
                'Does the academy publish pass rates or success statistics?',
                'No. The academy does not publish pass rates, employer endorsements or accreditation claims, because those would be claims it cannot evidence to a visitor. A certificate states only what it records: who completed what, when, with what score, and whether it is still valid.',
                'هل تنشر الأكاديمية نسب النجاح أو إحصاءات التحصيل؟',
                'لا. لا تنشر الأكاديمية نسب نجاح أو توصيات من جهات توظيف أو ادعاءات اعتماد، لأنها ادعاءات لا تستطيع إثباتها للزائر. والشهادة تذكر ما توثقه فقط: من أكمل ماذا، ومتى، وبأي درجة، وهل ما زالت سارية.'),
            array('global', null,
                'Which training is mandatory for every hotel employee?',
                'Fire safety, emergency response and the property induction are assigned to every employee regardless of department, usually with a seven day due date from their start date. Chemical safety, food safety and knife safety are mandatory for the roles that handle those hazards.',
                'ما التدريب الإلزامي لكل موظف فندقي؟',
                'تُسنَد السلامة من الحريق والاستجابة للطوارئ وتعريف الفندق لكل موظف مهما كان قسمه، عادةً بتاريخ استحقاق سبعة أيام من تاريخ المباشرة. وسلامة المواد الكيميائية وسلامة الغذاء وسلامة السكاكين إلزامية للأدوار التي تتعامل مع تلك المخاطر.'),
            array('topic', 'front-office-training',
                'What should a new front desk agent learn first?',
                'Fire safety and the property induction come first, then front office fundamentals and telephone etiquette, then check-in, check-out and guest registration, then the property management system. Complaint handling and the night audit come later, once the basic sequence is automatic.',
                'ما الذي ينبغي أن يتعلمه موظف الاستقبال الجديد أولاً؟',
                'تأتي السلامة من الحريق وتعريف الفندق أولاً، ثم أساسيات مكتب الاستقبال وآداب المكالمات، ثم تسجيل الوصول والمغادرة وتسجيل بيانات الضيف، ثم نظام إدارة الفندق. وتأتي معالجة الشكاوى والتدقيق الليلي لاحقاً بعد أن يصبح التسلسل الأساسي تلقائياً.'),
            array('topic', 'housekeeping-training',
                'How long should cleaning a hotel room take?',
                'A standard departure clean is commonly targeted at around forty minutes, but the number depends on room size, amenity level and the property standard. What makes the time predictable is the sequence: cleaning in a fixed order is what stops a room taking longer and looking worse.',
                'كم ينبغي أن يستغرق تنظيف غرفة الفندق؟',
                'يُستهدف عادةً نحو أربعين دقيقة لتنظيف غرفة مغادرة قياسية، لكن الرقم يعتمد على حجم الغرفة ومستوى المستلزمات ومعيار الفندق. وما يجعل الوقت قابلاً للتوقع هو التسلسل: فالتنظيف بترتيب ثابت هو ما يمنع أن تستغرق الغرفة وقتاً أطول وتبدو أسوأ.'),
            array('topic', 'food-safety-training',
                'What temperature should hot food be held at in a hotel?',
                'Hot food should be held at 63 degrees Celsius or above, with the holding temperature recorded every two hours. Cooked food should reach 75 degrees Celsius or above before service, and chilled storage should be at or below 5 degrees Celsius.',
                'على أي درجة حرارة يُحفظ الطعام الساخن في الفندق؟',
                'يُحفظ الطعام الساخن عند ٦٣ درجة مئوية أو أعلى، مع تسجيل حرارة الحفظ كل ساعتين. وينبغي أن يبلغ الطعام المطهو ٧٥ درجة مئوية أو أعلى قبل التقديم، وأن يكون التخزين المبرد عند ٥ درجات مئوية أو أقل.'),
            array('topic', 'hotel-sop-training',
                'How often should a hotel SOP be reviewed?',
                'A review interval of twelve months is a common default, and each procedure carries its own review date. A procedure should also be reviewed immediately after any incident it failed to prevent, and whenever the equipment, system or regulation it depends on changes.',
                'كل كم يُراجع إجراء التشغيل الفندقي؟',
                'فترة مراجعة اثني عشر شهراً هي الافتراض الشائع، ولكل إجراء تاريخ مراجعته الخاص. كما ينبغي مراجعة الإجراء فوراً بعد أي حادث لم يمنعه، وكلما تغيّرت المعدات أو النظام أو اللائحة التي يعتمد عليها.'),
        );
    }

    // ------------------------------------------------------------- competitors

    /**
     * Plan sections 31 and 32. Only publicly observable facts, each with the
     * evidence URL and the date it was recorded, exactly as the plan requires.
     * Nothing is asserted that the cited page does not itself state.
     */
    public static function competitors() {
        return array(
            array('typsy', 'Typsy', 'https://www.typsy.com/', 'Australia',
                'Hotels, restaurants and hospitality operators',
                'English',
                'Short video lessons, certificates, mobile learning, custom SOP content, performance reporting',
                'Subscription', 'https://www.typsy.com/',
                'Positions itself as a hospitality-specific learning platform combining video lessons, certificates, mobile access, custom SOP content and performance-oriented reporting.',
                'Publicly presented material is English-first; no Arabic or right-to-left interface is evidenced on the cited page.',
                'https://www.typsy.com/',
                array('lms' => 'yes', 'video_courses' => 'yes', 'certificates' => 'yes', 'mobile_learning' => 'yes',
                      'sop_support' => 'yes', 'analytics' => 'yes', 'arabic_content' => 'unknown', 'saudi_focus' => 'unknown')),

            array('saudi-tourism-authority-learning', 'Saudi Tourism Authority learning service',
                'https://my.gov.sa/en/services/2706562', 'Saudi Arabia',
                'Tourism sector businesses and their employees',
                'Arabic, English',
                'Electronic learning courses for the tourism sector, with attendance certificates on qualifying courses',
                'Government service', 'https://my.gov.sa/en/services/2706562',
                'A government-provided electronic learning service for the tourism sector, listed on the national services portal, with certificates of attendance available for qualifying courses.',
                'Scope is tourism-sector learning as a public service rather than a hotel workforce compliance and SOP platform.',
                'https://my.gov.sa/en/services/2706562',
                array('lms' => 'yes', 'video_courses' => 'unknown', 'certificates' => 'yes', 'mobile_learning' => 'unknown',
                      'sop_support' => 'no', 'analytics' => 'unknown', 'arabic_content' => 'yes', 'saudi_focus' => 'yes')),

            array('fsi', 'Future Skills Institute (FSI)', 'https://fsi.edu.sa/en/free-courses', 'Saudi Arabia',
                'Hospitality, hotel, restaurant and cafe sector workers',
                'Arabic, English',
                'Free hospitality workforce training tracks covering hotel, restaurant and cafe sectors',
                'Free on listed programmes', 'https://fsi.edu.sa/en/free-courses',
                'Advertises free hospitality workforce training tracks across hotel, restaurant and cafe sectors, and states that accredited certificates with verification codes are available on qualifying programmes.',
                'Delivery is programme-based training rather than an ongoing workforce compliance and SOP management platform for an operator.',
                'https://fsi.edu.sa/en/free-courses',
                array('lms' => 'unknown', 'video_courses' => 'unknown', 'certificates' => 'yes', 'mobile_learning' => 'unknown',
                      'sop_support' => 'unknown', 'analytics' => 'unknown', 'arabic_content' => 'yes', 'saudi_focus' => 'yes')),
        );
    }

    /** Keyword research from plan section 29, tracked with intent and cluster. */
    public static function keywords() {
        return array(
            // keyword, language, intent, cluster, our page
            array('hotel training courses in saudi arabia', 'en', 'commercial', 'hotel-training', 'hotels/training'),
            array('hospitality training in saudi arabia', 'en', 'commercial', 'hotel-training', 'hotels/training'),
            array('hotel staff training', 'en', 'commercial', 'hotel-training', 'hotels'),
            array('front office training', 'en', 'informational', 'front-office', 'hospitality-topics/front-office-training'),
            array('housekeeping training', 'en', 'informational', 'housekeeping', 'hospitality-topics/housekeeping-training'),
            array('hotel management courses', 'en', 'commercial', 'management', 'courses'),
            array('hospitality certification', 'en', 'commercial', 'certification', 'hospitality-topics/hospitality-certification'),
            array('hotel sop training', 'en', 'informational', 'sop', 'hospitality-topics/hotel-sop-training'),
            array('hotel employee training', 'en', 'commercial', 'hotel-training', 'hotels/training'),
            array('hospitality lms saudi arabia', 'en', 'transactional', 'platform', 'hotels'),
            array('hotel training management system', 'en', 'transactional', 'platform', 'hotels'),
            array('hotel workforce training', 'en', 'commercial', 'hotel-training', 'hotels'),
            array('hotel compliance training', 'en', 'commercial', 'compliance', 'hospitality-topics/hotel-compliance-training'),
            array('hospitality online courses saudi arabia', 'en', 'commercial', 'hotel-training', 'courses'),
            array('دورات تدريبية للفنادق في السعودية', 'ar', 'commercial', 'hotel-training', 'hotels/training'),
            array('تدريب موظفي الفنادق', 'ar', 'commercial', 'hotel-training', 'hotels'),
            array('دورات الضيافة', 'ar', 'commercial', 'hotel-training', 'courses'),
            array('دورات إدارة الفنادق', 'ar', 'commercial', 'management', 'courses'),
            array('تدريب الاستقبال الفندقي', 'ar', 'informational', 'front-office', 'hospitality-topics/front-office-training'),
            array('تدريب التدبير الفندقي', 'ar', 'informational', 'housekeeping', 'hospitality-topics/housekeeping-training'),
            array('أنظمة التشغيل الفندقي', 'ar', 'informational', 'platform', 'hotels'),
            array('إجراءات التشغيل القياسية للفنادق', 'ar', 'informational', 'sop', 'hospitality-topics/hotel-sop-training'),
            array('تدريب موظفي الضيافة', 'ar', 'commercial', 'hotel-training', 'hotels/training'),
            array('شهادات الضيافة', 'ar', 'commercial', 'certification', 'hospitality-topics/hospitality-certification'),
            array('التدريب الفندقي في السعودية', 'ar', 'commercial', 'hotel-training', 'hotels/training'),
        );
    }

    // -------------------------------------------------------------------- run

    public function run($db) {
        $this->boot($db);
        $written = 0;

        $editor = $this->db->get_where('users', array('email' => 'academy.admin@hospitalityacademy.sa'))->row_array();
        $editor_id = $editor ? (int) $editor['id'] : null;

        // Authors
        $author_ids = array();
        foreach (self::authors() as $a) {
            list($slug, $name_en, $name_ar, $title_en, $title_ar, $bio_en, $bio_ar) = $a;
            $author_ids[$slug] = $this->upsert('ha_author', array('slug' => $slug), array(
                'user_id'  => $slug === 'hospitality-academy-editorial' ? $editor_id : null,
                'name_en'  => $name_en, 'name_ar' => $name_ar,
                'title_en' => $title_en, 'title_ar' => $title_ar,
                'bio_en'   => $bio_en, 'bio_ar' => $bio_ar,
                'status'   => 'active',
            ));
            $written++;
        }

        // Pages
        foreach (self::pages() as $p) {
            list($code, $slug_en, $slug_ar, $template, $en, $ar) = $p;
            $page_id = $this->upsert('ha_page', array('code' => $code), array(
                'slug_en'      => $slug_en,
                'slug_ar'      => $slug_ar,
                'template'     => $template,
                'is_system'    => 1,
                'status'       => 'published',
                'published_at' => $this->now,
                'created_by'   => $editor_id,
            ));
            $written++;
            foreach (array('en' => $en, 'ar' => $ar) as $locale => $content) {
                $this->upsert('ha_page_translation', array('page_id' => $page_id, 'locale' => $locale), array(
                    'title'     => $content['title'],
                    'subtitle'  => $content['subtitle'],
                    'body'      => $content['body'],
                    'cta_label' => $content['cta_label'],
                    'cta_url'   => $content['cta_url'],
                ));
                $written++;
                $this->seo('page', $page_id, null, $locale, $content['title'], $content['subtitle'],
                    $locale === 'en' ? $slug_en : $slug_ar, $this->page_schema($code, $locale, $content));
                $written++;
            }
        }

        // Courses needed for topic links
        $course_ids = array();
        foreach ($this->db->select('id, code')->get('ha_course')->result_array() as $c) {
            $course_ids[$c['code']] = (int) $c['id'];
        }

        // Pillar and compliance topics
        $topic_ids = array();
        foreach (self::topics() as $i => $t) {
            list($code, $type, $slug_en, $slug_ar, $city, $en, $ar, $courses) = $t;
            $topic_id = $this->upsert('ha_topic', array('code' => $code), array(
                'slug_en'      => $slug_en,
                'slug_ar'      => $slug_ar,
                'title_en'     => $en[0],
                'title_ar'     => $ar[0],
                'intro_en'     => $en[1],
                'intro_ar'     => $ar[1],
                'city'         => $city,
                'topic_type'   => $type,
                'sort_order'   => $i,
                'status'       => 'published',
                'published_at' => $this->now,
            ));
            $topic_ids[$code] = $topic_id;
            $written++;

            $this->db->where('topic_id', $topic_id)->delete('ha_topic_course');
            foreach ($courses as $j => $c) {
                if (isset($course_ids[$c])) {
                    $this->db->insert('ha_topic_course', array(
                        'topic_id' => $topic_id, 'course_id' => $course_ids[$c], 'sort_order' => $j));
                    $written++;
                }
            }

            $this->seo('topic', $topic_id, null, 'en', $en[0],
                $this->summarise($en[1]), 'hospitality-topics/' . $slug_en,
                $this->topic_schema($en[0], $this->summarise($en[1]), 'en', $slug_en));
            $this->seo('topic', $topic_id, null, 'ar', $ar[0],
                $this->summarise($ar[1]), 'hospitality-topics/' . $slug_ar,
                $this->topic_schema($ar[0], $this->summarise($ar[1]), 'ar', $slug_ar));
            $written += 2;
        }

        // City topics (GEO)
        foreach (self::cities() as $i => $c) {
            list($slug_en, $slug_ar, $city, $en, $ar) = $c;
            $topic_id = $this->upsert('ha_topic', array('code' => $slug_en), array(
                'slug_en'      => $slug_en,
                'slug_ar'      => $slug_ar,
                'title_en'     => $en[0],
                'title_ar'     => $ar[0],
                'intro_en'     => $en[1],
                'intro_ar'     => $ar[1],
                'city'         => $city,
                'topic_type'   => 'city',
                'sort_order'   => 100 + $i,
                'status'       => 'published',
                'published_at' => $this->now,
            ));
            $written++;

            // City pages link to the courses that city emphasis actually needs.
            $emphasis = array(
                'Riyadh'    => array('fo-check-in', 'fo-room-assignment', 'fb-banquet', 'fo-night-audit'),
                'Jeddah'    => array('fb-restaurant-service', 'fb-banquet', 'kit-allergens', 'fb-table-service'),
                'Makkah'    => array('hk-turnaround', 'fo-check-in', 'sop-none', 'hk-productivity'),
                'Madinah'   => array('hk-fundamentals', 'fo-room-assignment', 'eng-emergency', 'fo-complaints'),
                'Al Khobar' => array('eng-preventive', 'eng-hvac', 'fo-check-out', 'hk-turnaround'),
                'Dammam'    => array('fo-night-audit', 'hk-turnaround', 'fo-check-in', 'fo-telephone'),
                'AlUla'     => array('fo-concierge', 'mgt-guest-experience', 'eng-emergency', 'fb-restaurant-service'),
                'Abha'      => array('eng-fire-safety', 'hk-fundamentals', 'fb-guest-greeting', 'hk-room-cleaning'),
            );
            $this->db->where('topic_id', $topic_id)->delete('ha_topic_course');
            $list = isset($emphasis[$city]) ? $emphasis[$city] : array();
            foreach ($list as $j => $code) {
                if (isset($course_ids[$code])) {
                    $this->db->insert('ha_topic_course', array(
                        'topic_id' => $topic_id, 'course_id' => $course_ids[$code], 'sort_order' => $j));
                    $written++;
                }
            }

            $this->seo('topic', $topic_id, null, 'en', $en[0] . ' | ' . self::BRAND_EN,
                $this->summarise($en[1]), 'hospitality-topics/' . $slug_en,
                $this->city_schema($en[0], $this->summarise($en[1]), $city, 'en', $slug_en));
            $this->seo('topic', $topic_id, null, 'ar', $ar[0] . ' | ' . self::BRAND_AR,
                $this->summarise($ar[1]), 'hospitality-topics/' . $slug_ar,
                $this->city_schema($ar[0], $this->summarise($ar[1]), $city, 'ar', $slug_ar));
            $written += 2;
        }

        // Categories used by articles
        $category_ids = array();
        foreach ($this->db->select('id, code')->get('ha_category')->result_array() as $c) {
            $category_ids[$c['code']] = (int) $c['id'];
        }

        // Tags
        $tag_ids = array();
        $tag_labels = array(
            'front-office'     => array('Front Office', 'مكتب الاستقبال'),
            'onboarding'       => array('Onboarding', 'الإدماج الوظيفي'),
            'hotel-training'   => array('Hotel Training', 'التدريب الفندقي'),
            'sop'              => array('SOP', 'إجراءات التشغيل'),
            'hotel-operations' => array('Hotel Operations', 'العمليات الفندقية'),
            'quality'          => array('Quality', 'الجودة'),
            'food-safety'      => array('Food Safety', 'سلامة الغذاء'),
            'compliance'       => array('Compliance', 'الالتزام'),
            'kitchen'          => array('Kitchen', 'المطبخ'),
            'reporting'        => array('Reporting', 'التقارير'),
            'bilingual'        => array('Bilingual', 'ثنائي اللغة'),
            'arabic'           => array('Arabic', 'العربية'),
            'pre-opening'      => array('Pre-opening', 'ما قبل الافتتاح'),
        );
        foreach ($tag_labels as $slug => $labels) {
            $tag_ids[$slug] = $this->upsert('ha_tag', array('slug' => $slug),
                array('name_en' => $labels[0], 'name_ar' => $labels[1]));
            $written++;
        }

        // Articles
        foreach (self::articles() as $i => $a) {
            list($slug_en, $slug_ar, $category, $topic, $en, $ar, $tags) = $a;
            $article_id = $this->upsert('ha_article', array('slug_en' => $slug_en), array(
                'slug_ar'          => $slug_ar,
                'category_id'      => isset($category_ids[$category]) ? $category_ids[$category] : null,
                'topic_id'         => isset($topic_ids[$topic]) ? $topic_ids[$topic] : null,
                'author_id'        => $author_ids['hospitality-academy-editorial'],
                'reading_minutes'  => max(3, (int) round(str_word_count(strip_tags($en[2])) / 200)),
                'cta_label_en'     => 'Browse the course catalogue',
                'cta_label_ar'     => 'تصفح كتالوج الدورات',
                'cta_url'          => 'courses',
                'status'           => 'published',
                'published_at'     => date('Y-m-d H:i:s', strtotime('-' . ($i * 6) . ' days')),
                'created_by'       => $editor_id,
            ));
            $written++;

            $this->upsert('ha_article_translation', array('article_id' => $article_id, 'locale' => 'en'),
                array('title' => $en[0], 'excerpt' => $en[1], 'body' => $en[2]));
            $this->upsert('ha_article_translation', array('article_id' => $article_id, 'locale' => 'ar'),
                array('title' => $ar[0], 'excerpt' => $ar[1], 'body' => $ar[2]));
            $written += 2;

            $this->db->where('article_id', $article_id)->delete('ha_article_tag');
            foreach ($tags as $t) {
                if (isset($tag_ids[$t])) {
                    $this->db->insert('ha_article_tag', array('article_id' => $article_id, 'tag_id' => $tag_ids[$t]));
                    $written++;
                }
            }

            $this->seo('article', $article_id, null, 'en', $en[0], $en[1], 'articles/' . $slug_en,
                $this->article_schema($en[0], $en[1], 'en', $slug_en));
            $this->seo('article', $article_id, null, 'ar', $ar[0], $ar[1], 'articles/' . $slug_ar,
                $this->article_schema($ar[0], $ar[1], 'ar', $slug_ar));
            $written += 2;
        }

        // FAQs
        $this->db->where('scope_type', 'global')->delete('ha_faq');
        foreach (self::faqs() as $i => $f) {
            list($scope, $key, $q_en, $a_en, $q_ar, $a_ar) = $f;
            $scope_id = null;
            if ($scope === 'topic' && isset($topic_ids[$key])) {
                $scope_id = $topic_ids[$key];
            }
            $match = array('scope_type' => $scope, 'scope_id' => $scope_id, 'question_en' => $q_en);
            $this->upsert('ha_faq', $match, array(
                'question_ar'       => $q_ar,
                'answer_en'         => $a_en,
                'answer_ar'         => $a_ar,
                'include_in_schema' => 1,
                'sort_order'        => $i,
                'status'            => 'published',
            ));
            $written++;
        }

        // Public menus
        $menu_id = $this->upsert('ha_menu', array('code' => 'public_header'),
            array('name_en' => 'Public header', 'name_ar' => 'قائمة الرأس'));
        $written++;
        $this->db->where('menu_id', $menu_id)->delete('ha_menu_item');
        $items = array(
            array('Home', 'الرئيسية', '', ''),
            array('Courses', 'الدورات', 'courses', 'courses'),
            array('Programs', 'البرامج', 'programs', 'programs'),
            array('Learning Paths', 'المسارات المهنية', 'learning-paths', 'learning-paths'),
            array('Hospitality Topics', 'مواضيع الضيافة', 'hospitality-topics', 'hospitality-topics'),
            array('SOP Resources', 'موارد الإجراءات', 'sop', 'sop'),
            array('Articles', 'المقالات', 'articles', 'articles'),
            array('Certifications', 'الشهادات', 'certificates', 'certificates'),
            array('About Academy', 'عن الأكاديمية', 'about', 'about'),
            array('For Hotels', 'للفنادق', 'hotels', 'hotels'),
            array('Contact', 'تواصل', 'contact', 'contact'),
        );
        foreach ($items as $i => $item) {
            $this->db->insert('ha_menu_item', array(
                'menu_id'    => $menu_id,
                'label_en'   => $item[0], 'label_ar' => $item[1],
                'url_en'     => $item[2], 'url_ar' => $item[3],
                'sort_order' => $i,
                'status'     => 'active',
            ));
            $written++;
        }

        // Competitor intelligence
        foreach (self::competitors() as $c) {
            list($slug, $name, $website, $country, $audience, $languages, $content_type,
                $pricing, $pricing_evidence, $strengths, $gaps, $evidence, $capabilities) = $c;
            $competitor_id = $this->upsert('ha_competitor', array('slug' => $slug), array(
                'name'                 => $name,
                'website'              => $website,
                'country'              => $country,
                'target_audience'      => $audience,
                'languages'            => $languages,
                'content_type'         => $content_type,
                'pricing_model'        => $pricing,
                'pricing_evidence_url' => $pricing_evidence,
                'strengths'            => $strengths,
                'gaps'                 => $gaps,
                'evidence_url'         => $evidence,
                'last_checked_at'      => date('Y-m-d'),
                'checked_by'           => $editor_id,
                'notes'                => 'Recorded from the cited public page only. Anything not stated there is marked unknown.',
                'status'               => 'active',
            ));
            $written++;
            foreach ($capabilities as $capability => $present) {
                $this->upsert('ha_competitor_capability',
                    array('competitor_id' => $competitor_id, 'capability' => $capability),
                    array('is_present' => $present, 'evidence_url' => $evidence, 'observed_at' => date('Y-m-d')));
                $written++;
            }
            $this->upsert('ha_competitor_page',
                array('competitor_id' => $competitor_id, 'url' => $website),
                array('observed_title' => $name, 'observed_topic' => 'Hospitality training',
                      'content_type' => 'landing', 'language' => 'en', 'last_checked_at' => date('Y-m-d')));
            $written++;
        }

        // Keyword tracking
        foreach (self::keywords() as $k) {
            list($keyword, $language, $intent, $cluster, $our_page) = $k;
            $this->upsert('ha_competitor_keyword',
                array('keyword' => $keyword, 'language' => $language, 'country' => 'Saudi Arabia'),
                array(
                    'search_intent' => $intent,
                    'cluster'       => $cluster,
                    'our_page'      => $our_page,
                    'status'        => 'covered',
                ));
            $written++;
        }

        // Route-level SEO for the listing pages that have no single entity.
        $routes = array(
            array('courses', 'Hotel Training Courses', 'دورات التدريب الفندقي',
                'Browse hotel training courses by professional domain: front office, housekeeping, food and beverage, kitchen, revenue, quality, safety, engineering and management. Available in Arabic and English.',
                'تصفح دورات التدريب الفندقي حسب القسم: مكتب الاستقبال والتدبير الفندقي والأغذية والمشروبات والمطبخ والهندسة والإدارة. متاحة بالعربية والإنجليزية.'),
            array('programs', 'Hospitality Programs', 'برامج الضيافة',
                'Structured hospitality programs that group courses into a qualification, from front office professional to food safety certified.',
                'برامج ضيافة منظمة تجمع الدورات في مؤهل واحد، من محترف مكتب الاستقبال إلى معتمد في سلامة الغذاء.'),
            array('learning-paths', 'Hospitality Career Paths', 'المسارات المهنية في الضيافة',
                'Career paths for hotel roles, from room attendant to executive housekeeper and from front office associate to front office manager.',
                'مسارات مهنية لأدوار الفنادق، من عامل غرف إلى مدير التدبير الفندقي، ومن موظف استقبال إلى مدير مكتب الاستقبال.'),
            array('hospitality-topics', 'Hospitality Topics', 'مواضيع الضيافة',
                'Guides to hotel training by subject and by Saudi city, covering front office, housekeeping, food safety, SOPs, certification and compliance.',
                'أدلة التدريب الفندقي حسب الموضوع وحسب المدينة السعودية، تغطي مكتب الاستقبال والتدبير الفندقي وسلامة الغذاء والإجراءات والاعتماد والالتزام.'),
            array('sop', 'Hotel SOP Resources', 'موارد الإجراءات الفندقية',
                'Publicly readable standard operating procedure resources for hotel departments, with structure, version control and acknowledgement explained.',
                'موارد إجراءات التشغيل القياسية المتاحة للاطلاع العام لأقسام الفنادق، مع شرح البنية والتحكم بالإصدارات والإقرار.'),
            array('articles', 'Hospitality Articles', 'مقالات الضيافة',
                'Practical articles on hotel training, standard operating procedures, food safety records, compliance reporting and bilingual delivery.',
                'مقالات عملية عن التدريب الفندقي وإجراءات التشغيل وسجلات سلامة الغذاء وتقارير الالتزام والتقديم ثنائي اللغة.'),
            array('verify', 'Verify a Certificate', 'التحقق من شهادة',
                'Enter a verification code to check whether a Hospitality Academy certificate is valid, expired or revoked. No account required.',
                'أدخل رمز التحقق لمعرفة ما إذا كانت شهادة أكاديمية الضيافة سارية أو منتهية أو ملغاة. لا يلزم حساب.'),
        );
        foreach ($routes as $r) {
            list($route, $title_en, $title_ar, $desc_en, $desc_ar) = $r;
            $this->seo(null, null, $route, 'en', $title_en . ' | ' . self::BRAND_EN, $desc_en, $route,
                $this->collection_schema($title_en, $desc_en, 'en', $route));
            $this->seo(null, null, $route, 'ar', $title_ar . ' | ' . self::BRAND_AR, $desc_ar, $route,
                $this->collection_schema($title_ar, $desc_ar, 'ar', $route));
            $written += 2;
        }

        return $written;
    }

    // ------------------------------------------------------------- SEO helpers

    private function base_url() {
        $url = getenv('APP_URL');
        return $url ? rtrim($url, '/') : 'http://localhost/atlas-lms/Academy-LMS';
    }

    private function canonical($locale, $path) {
        $prefix = $locale === 'ar' ? '/ar' : '/en';
        return $this->base_url() . $prefix . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    private function summarise($html, $limit = 300) {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $last = mb_strrpos($cut, ' ');
        return rtrim(mb_substr($cut, 0, $last ?: $limit), ' ,.;:') . '.';
    }

    private function seo($entity_type, $entity_id, $route_key, $locale, $title, $description, $path, $schema) {
        $match = array(
            'entity_type' => $entity_type === null ? 'route' : $entity_type,
            'entity_id'   => $entity_id,
            'route_key'   => $route_key,
            'locale'      => $locale,
        );
        // canonical_url is deliberately left empty: Ha_seo derives it from the
        // live base URL so a seeded value cannot pin the site to the wrong
        // host. An editor setting one explicitly in the SEO centre wins.
        return $this->upsert('ha_seo_metadata', $match, array(
            'meta_title'       => mb_substr($title, 0, 185),
            'meta_description' => mb_substr($description, 0, 315),
            'canonical_url'    => null,
            'robots'           => 'index,follow',
            'og_title'         => mb_substr($title, 0, 185),
            'og_description'   => mb_substr($description, 0, 315),
            'twitter_card'     => 'summary_large_image',
            'schema_json'      => $schema,
        ));
    }

    private function organization_schema($locale) {
        return array(
            '@type' => 'EducationalOrganization',
            'name'  => $locale === 'ar' ? self::BRAND_AR : self::BRAND_EN,
            'url'   => $this->base_url(),
            'areaServed' => array('@type' => 'Country', 'name' => 'Saudi Arabia'),
            'availableLanguage' => array('ar', 'en'),
        );
    }

    private function faq_schema($locale) {
        $entities = array();
        foreach (self::faqs() as $f) {
            if ($f[0] !== 'global') {
                continue;
            }
            $entities[] = array(
                '@type' => 'Question',
                'name'  => $locale === 'ar' ? $f[4] : $f[2],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $locale === 'ar' ? $f[5] : $f[3],
                ),
            );
        }
        return array('@type' => 'FAQPage', 'mainEntity' => $entities);
    }

    private function page_schema($code, $locale, $content) {
        $graph = array($this->organization_schema($locale));
        if ($code === 'home') {
            $graph[] = array(
                '@type' => 'WebSite',
                'name'  => $locale === 'ar' ? self::BRAND_AR : self::BRAND_EN,
                'url'   => $this->base_url(),
                'inLanguage' => $locale,
            );
            $graph[] = $this->faq_schema($locale);
        }
        $graph[] = array(
            '@type'      => 'WebPage',
            'name'       => $content['title'],
            'description'=> $content['subtitle'],
            'inLanguage' => $locale,
        );
        return json_encode(array('@context' => 'https://schema.org', '@graph' => $graph), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function topic_schema($title, $description, $locale, $slug) {
        return json_encode(array(
            '@context' => 'https://schema.org',
            '@graph'   => array(
                array(
                    '@type'       => 'WebPage',
                    'name'        => $title,
                    'description' => $description,
                    'inLanguage'  => $locale,
                    'url'         => $this->canonical($locale, 'hospitality-topics/' . $slug),
                ),
                $this->organization_schema($locale),
            ),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function city_schema($title, $description, $city, $locale, $slug) {
        return json_encode(array(
            '@context' => 'https://schema.org',
            '@graph'   => array(
                array(
                    '@type'       => 'WebPage',
                    'name'        => $title,
                    'description' => $description,
                    'inLanguage'  => $locale,
                    'url'         => $this->canonical($locale, 'hospitality-topics/' . $slug),
                    'about'       => array('@type' => 'City', 'name' => $city,
                        'containedInPlace' => array('@type' => 'Country', 'name' => 'Saudi Arabia')),
                ),
                array(
                    '@type'      => 'Service',
                    'serviceType'=> 'Hotel staff training',
                    'areaServed' => array('@type' => 'City', 'name' => $city),
                    'provider'   => $this->organization_schema($locale),
                ),
            ),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function article_schema($title, $excerpt, $locale, $slug) {
        return json_encode(array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => $title,
            'description'   => $excerpt,
            'inLanguage'    => $locale,
            'url'           => $this->canonical($locale, 'articles/' . $slug),
            'author'        => array('@type' => 'Organization',
                'name' => $locale === 'ar' ? self::BRAND_AR : self::BRAND_EN),
            'publisher'     => $this->organization_schema($locale),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function collection_schema($title, $description, $locale, $route) {
        return json_encode(array(
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => $title,
            'description' => $description,
            'inLanguage'  => $locale,
            'url'         => $this->canonical($locale, $route),
            'isPartOf'    => $this->organization_schema($locale),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
