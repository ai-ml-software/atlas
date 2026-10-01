<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';

/**
 * Altus Gulf corporate content, from the 2026 Corporate Profile (English and the
 * Modern Standard Arabic edition): the source for the public About, Services,
 * Ascent, Market, Case Studies and Leadership pages.
 *
 * Seed 006 wrote an earlier draft of some of these records (as "Altus Advisory").
 * This seed brings them to the 2026 edition while keeping the promise that
 * administrator edits are never overwritten: a corporate block is only updated
 * while updated_by is still empty (no person has saved it). Case studies,
 * leadership profiles and services carry no editor stamp, so they follow the
 * profile.
 *
 * Deliberately NOT published: the profile's placeholder contact lines
 * ("[Office address]", "[placeholder]" web, e-mail and LinkedIn).
 *
 *   php index.php ha_cli seed altus_profile
 */
class Seed_altus_profile extends Ha_seeder {

    private $order = 0;

    public function run($db) {
        $this->boot($db);
        if (!$this->db->table_exists('ha_corporate_block')) {
            return 0;
        }
        return $this->blocks() + $this->services() + $this->cases() + $this->leaders() + $this->menu() + $this->platform_brand();
    }

    /**
     * The platform-level workspace brand moves to the Altus Gulf palette, but only
     * while it still carries seed 006's original colours: a platform an
     * administrator has re-branded is left alone, and client (white-label) brands
     * are never touched.
     */
    private function platform_brand() {
        if (!$this->db->table_exists('ha_branding')) {
            return 0;
        }
        $this->db->where(array('scope_type' => 'platform', 'scope_id' => 0, 'color_primary' => '#0F3D3E', 'color_secondary' => '#0D1B2A', 'color_accent' => '#C89D4F'))
            ->update('ha_branding', array('brand_name_en' => 'Altus Knowledge and Performance', 'brand_name_ar' => 'Altus للمعرفة والأداء',
                'color_primary' => '#A2471F', 'color_secondary' => '#1E2329', 'color_accent' => '#C45B2F', 'color_surface' => '#F7F5F1', 'updated_at' => $this->now));
        return $this->db->affected_rows();
    }

    /**
     * Public header menu = the Altus Gulf corporate menu, with the platform item
     * named "Altus Knowledge and Performance". The academy items it replaces are
     * hidden, not deleted (an administrator can show them again), and every one
     * of them stays linked from the footer.
     */
    private function menu() {
        $menu = $this->db->get_where('ha_menu', array('code' => 'public_header'))->row_array();
        if (!$menu || !$this->db->table_exists('ha_menu_item')) {
            return 0;
        }
        $items = array(
            array('about-altus', 'About', 'من نحن'),
            array('services', 'Services', 'الخدمات'),
            array('knowledge-performance', 'Altus Knowledge and Performance', 'Altus للمعرفة والأداء'),
            array('ascent', 'Ascent', 'مسار الارتقاء'),
            array('market', 'Market', 'السوق'),
            array('case-studies', 'Case Studies', 'دراسات الحالة'),
            array('leadership', 'Leadership', 'القيادة'),
            array('contact', 'Contact', 'تواصل معنا'),
        );
        $keep = array();
        foreach ($items as $i => $m) {
            $keep[] = $this->upsert('ha_menu_item', array('menu_id' => (int) $menu['id'], 'url_en' => $m[0]),
                array('url_ar' => $m[0], 'label_en' => $m[1], 'label_ar' => $m[2], 'sort_order' => $i, 'status' => 'active', 'open_in_new_tab' => 0, 'parent_id' => null));
        }
        $this->db->where('menu_id', (int) $menu['id'])->where_not_in('id', $keep)->update('ha_menu_item', array('status' => 'hidden'));
        return count($items);
    }

    /** Block upsert that respects editors: skips rows a person has saved. */
    private function block($code, $section, $title_en, $title_ar, $body_en, $body_ar) {
        $row = $this->db->get_where('ha_corporate_block', array('code' => $code))->row_array();
        $this->order++;
        if ($row && !empty($row['updated_by'])) {
            return 0;
        }
        $this->upsert('ha_corporate_block', array('code' => $code), array('section' => $section, 'title_en' => $title_en, 'title_ar' => $title_ar,
            'body_en' => $body_en, 'body_ar' => $body_ar, 'sort_order' => $this->order, 'visibility' => 'public', 'status' => 'published'));
        return 1;
    }

    private function blocks() {
        $n = 0;
        $B = function () use (&$n) { $n += call_user_func_array(array($this, 'block'), func_get_args()); };

        // ------------------------------------------------------------ hero & about
        $B('hero', 'hero', 'The future belongs to those who prepare for it.', 'المستقبل لمن يستعدّ له.',
            "We do not adapt to the market. We help shape it.\nA boutique strategy house operating at the intersection of hospitality operations and applied business intelligence, built for the Kingdom's next decade.",
            "نحن لا نكتفي بمواكبة السوق، بل نُسهم في صياغته.\nبيت خبرة استراتيجي متخصص يعمل عند نقطة التقاء عمليات الضيافة بذكاء الأعمال التطبيقي، ومصمَّم لعقد المملكة المقبل.");
        $B('hero_stat_years', 'hero_stats', '60+', '60+', 'Years combined leadership', 'سنوات خبرة قيادية مجتمعة');
        $B('hero_stat_divisions', 'hero_stats', '2', '2', 'Integrated divisions', 'قسمان متكاملان');
        $B('hero_stat_sectors', 'hero_stats', '12', '12', 'Sectors served', 'قطاعًا نخدمه');
        $B('hero_stat_platform', 'hero_stats', '1', '1', 'Proprietary platform', 'منصة خاصة بالشركة');

        $B('founders_message', 'about', 'A Message from the Founders', 'رسالة من المؤسسين',
            "Global commerce is evolving at an unprecedented pace, and legacy operating frameworks are no longer sufficient on their own to secure market leadership. As the future architecture of business takes shape, one truth stands out: genuine excellence comes from the close integration of human capital and technological innovation.\n\nWe founded Altus Gulf to close a structural gap in the market. Asset owners and senior leadership teams are too often asked to choose between conventional hospitality operators' consultants and standalone digital agencies. Altus Gulf occupies the precise, multidisciplinary intersection of the two.\n\nWe favour evidence over instinct. We work from advanced empirical analytics, help engineer measurable growth, and support the conversion of physical assets into high-performing, digital-first institutions. That conviction now extends into product: our proprietary Altus Gulf Hospitality Knowledge & Performance platform institutionalises the very expertise we advise with.\n\nOur approach is straightforward. We stand beside you, shoulder to shoulder, from the first blueprint through sustained execution, and beyond it, until your organisation reaches greater operational consistency, stability, and confidence. The engagement may end; the alliance does not.",
            "تتطور التجارة العالمية بوتيرة غير مسبوقة، ولم تعد أطر التشغيل التقليدية وحدها كافية لتحقيق الريادة في السوق. ومع تشكّل البنية المستقبلية للأعمال، تبرز حقيقة واحدة: أن التميّز الحقيقي ينبع من التكامل الوثيق بين رأس المال البشري والابتكار التقني.\n\nأسسنا Altus Gulf لسدّ فجوة هيكلية في السوق؛ فكثيرًا ما يُطلب من ملّاك الأصول وفرق القيادة العليا الاختيار بين مستشاري مشغّلي الضيافة التقليديين ووكالات رقمية قائمة بذاتها. وتقف Altus Gulf عند نقطة التقاء دقيقة ومتعددة التخصصات بين الطرفين.\n\nنُقدّم الدليل على الحدس. فنحن ننطلق من التحليلات التجريبية المتقدمة، ونُسهم في هندسة نمو قابل للقياس، وندعم تحويل الأصول المادية إلى مؤسسات عالية الأداء تعتمد الرقمنة أولًا. وقد امتدّ هذا الإيمان اليوم إلى المنتج؛ إذ تعمل منصتنا الخاصة Altus Gulf للمعرفة والأداء في قطاع الضيافة على مأسسة الخبرة ذاتها التي نقدّم بها استشاراتنا.\n\nنهجنا واضح وبسيط: نقف إلى جانبك كتفًا بكتف، من المخطط الأول مرورًا بالتنفيذ المستمر وما بعده، حتى تبلغ مؤسستك مستوى أعلى من الاتساق التشغيلي والاستقرار والثقة. قد ينتهي التعاقد، لكن الشراكة تبقى.");
        $B('founders_quote', 'about', 'Islam Mahrous • Hussam Smadi — Co-Founders, Altus Gulf', 'إسلام محروس • حسام الصمادي — الشريكان المؤسسان، Altus Gulf',
            'Commercial considerations are a reality of business, but for us they come second. What comes first is helping you build a genuine success story.',
            'الاعتبارات التجارية جزء من واقع الأعمال، لكنها عندنا تأتي في المرتبة الثانية. أما الأولوية فهي أن نساعدك على صنع قصة نجاح حقيقية.');
        $B('company_overview', 'about', 'Company Overview', 'نبذة عن الشركة',
            'Altus Gulf is a specialised strategy and advisory consultancy built on rigorous international standards. We work where hospitality operations meet applied business intelligence, serving asset owners, investors, and ambitious enterprises across Saudi Arabia, the GCC, and global markets.',
            'Altus Gulf شركة استشارات استراتيجية متخصصة، قائمة على معايير دولية صارمة. نعمل حيث تلتقي عمليات الضيافة بذكاء الأعمال التطبيقي، ونخدم ملّاك الأصول والمستثمرين والشركات الطموحة في المملكة العربية السعودية ودول الخليج والأسواق العالمية.');
        $B('vision', 'about', 'Our Vision', 'رؤيتنا',
            'To be a leading strategic catalyst for hospitality excellence and enterprise transformation: championing bold founders, scaling enterprises, and visionary owners as they define the future of business performance.',
            'أن نكون محفّزًا استراتيجيًا رائدًا للتميّز في الضيافة وللتحول المؤسسي، ندعم المؤسسين الجريئين والشركات المتنامية وأصحاب الرؤى وهم يرسمون مستقبل أداء الأعمال.');
        $B('mission', 'about', 'Our Mission', 'رسالتنا',
            'To empower asset owners, investors, and business leaders with executable strategy, applied technology infrastructure, and human-capital frameworks: supporting sustainable revenue growth, operational independence, and consistently strong experiences across every physical and digital touchpoint.',
            'تمكين ملّاك الأصول والمستثمرين وقادة الأعمال باستراتيجية قابلة للتنفيذ، وبنية تقنية تطبيقية، وأطر لرأس المال البشري، بما يدعم نمو الإيرادات المستدام والاستقلالية التشغيلية وتجارب متميزة باستمرار عبر كل نقطة تواصل مادية ورقمية.');
        $B('purpose', 'about', 'Our Purpose', 'غايتنا',
            'The beating heart of our mission is to champion start-ups, scaling enterprises, and every investor or entrepreneur with a bold ambition — acting as a trusted strategic partner that helps turn ambition into practical, high-value results across the GCC and beyond.',
            'قلب رسالتنا النابض هو دعم الشركات الناشئة والمؤسسات المتنامية وكل مستثمر أو رائد أعمال يحمل طموحًا جريئًا، بصفتنا شريكًا استراتيجيًا موثوقًا يساعد على تحويل الطموح إلى نتائج عملية عالية القيمة في دول الخليج وخارجها.');
        $B('two_practices', 'about', 'Two synergistic practices. One integrated house.', 'ممارستان متكاملتان في بيت واحد.',
            'Hospitality Solutions and Business Growth Solutions, cross-pollinating operational mastery with digital sophistication.',
            'حلول الضيافة وحلول نمو الأعمال، يتبادلان الخبرة التشغيلية والحس الرقمي المتقدّم.');

        $B('philosophy_fiduciary', 'philosophy', 'Owner-Side Discipline', 'انضباط في صف المالك',
            "We sit on the owner's side of the table: independent, closely engaged, and measured against your returns, not our billable hours.",
            'نقف في صف المالك: باستقلالية وانخراط وثيق، ويُقاس عملنا بعوائدك لا بساعات عملنا المفوترة.');
        $B('philosophy_evidence', 'philosophy', 'Evidence over Intuition', 'الدليل قبل الحدس',
            'Every recommendation is grounded in empirical analytics, financial modelling, and field-tested operating discipline.',
            'تستند كل توصية إلى تحليلات تجريبية ونمذجة مالية وانضباط تشغيلي مُجرَّب ميدانيًا.');
        $B('philosophy_partnership', 'philosophy', 'Partnership beyond the Mandate', 'شراكة تتجاوز نطاق التعاقد',
            "Engagements evolve into enduring alliances. We remain invested in our clients' trajectory long after delivery.",
            'تتحول الارتباطات إلى تحالفات ممتدة. ونظل معنيّين بمسار عملائنا طويلًا بعد التسليم.');

        // ------------------------------------------------------------ why Altus Gulf
        $B('why_intro', 'why', 'Why Altus Gulf', 'لماذا Altus Gulf',
            'The consulting landscape too often operates in silos: traditional operations on one side, digital transformation on the other. Altus Gulf was built to close that structural gap with a single, integrated mandate.',
            'كثيرًا ما يعمل قطاع الاستشارات في جزر منفصلة: التشغيل التقليدي في جهة، والتحول الرقمي في جهة أخرى. وقد أُسست Altus Gulf لسدّ هذه الفجوة الهيكلية بمهمة واحدة متكاملة.');
        foreach (array(
            array('why_owner_side', 'Owner-Side Advisory', 'استشارات في صف المالك',
                "Independent, client-side oversight across the full asset lifecycle: helping protect budgets, govern HMAs, and manage operator and contractor risk on the owner's behalf.",
                'إشراف مستقل إلى جانب العميل على امتداد دورة حياة الأصل، يساعد على حماية الميزانيات وإحكام إدارة اتفاقيات إدارة الفنادق (HMA) ومعالجة مخاطر المشغّل والمقاول نيابةً عن المالك.'),
            array('why_operator_depth', 'Operator-Grade Depth', 'عمق بمستوى المشغّلين',
                'Leadership formed inside Marriott, IHG, Starwood, and Accor systems. 60+ combined years of P&L experience, not theoretical frameworks.',
                'قيادات تشكّلت داخل منظومات ماريوت وIHG وستاروود وأكور، بخبرة تتجاوز 60 عامًا مجتمعة في إدارة الأرباح والخسائر، لا في الأطر النظرية.'),
            array('why_dual', 'A Dual-Discipline Model', 'نموذج مزدوج التخصص',
                'Hospitality operations fused with AI-enabled commercial intelligence, engineering growth across both domains at the same time.',
                'عمليات ضيافة تتكامل مع ذكاء تجاري مدعوم بالذكاء الاصطناعي، لهندسة النمو في المجالين في آنٍ واحد.'),
            array('why_regional', 'Regional Fluency, Global Standards', 'إلمام إقليمي ومعايير عالمية',
                'Deep-rooted Saudi and GCC market knowledge, bilingual delivery, and Tier-1 international benchmarks applied without dilution.',
                'معرفة راسخة بالسوقين السعودي والخليجي، وتقديم ثنائي اللغة، وتطبيق معايير مرجعية دولية من الفئة الأولى دون تخفيف.'),
            array('why_evidence', 'Evidence over Intuition', 'الدليل قبل الحدس',
                'Empirical analytics, financial modelling, and Six Sigma discipline replace opinion. Every recommendation is measurable and auditable.',
                'تحل التحليلات التجريبية والنمذجة المالية وانضباط سيجما ستة (Six Sigma) محل الرأي الشخصي. وكل توصية قابلة للقياس والتدقيق.'),
            array('why_partnership', 'An Enduring Partnership', 'شراكة ممتدة',
                'Hands-on guidance from first blueprint to sustained execution: a relationship designed to outlast the engagement itself.',
                'توجيه ميداني من المخطط الأول إلى التنفيذ المستمر: علاقة صُمّمت لتدوم بعد انتهاء التعاقد ذاته.'),
        ) as $r) {
            $B($r[0], 'why', $r[1], $r[2], $r[3], $r[4]);
        }
        $B('uvp', 'why_uvp', 'Unique Value Proposition', 'عرض القيمة الفريد',
            'Hospitality operational excellence, integrated with AI-driven revenue intelligence: supporting measurable impact through strategic clarity, operational rigour, and continuous digital innovation.',
            'تميّز تشغيلي في الضيافة متكامل مع ذكاء الإيرادات المدعوم بالذكاء الاصطناعي، بما يدعم أثرًا قابلًا للقياس عبر الوضوح الاستراتيجي والصرامة التشغيلية والابتكار الرقمي المتواصل.');

        // ------------------------------------------------------------ the platform
        $B('promise', 'platform', 'The product vision', 'رؤية المنتج',
            'The right knowledge, to the right person, at the right time, with clear evidence of learning and improvement.',
            'المعرفة الصحيحة، للشخص الصحيح، في الوقت الصحيح، مع دليل واضح على التعلّم والتحسّن.');
        $B('positioning', 'platform', 'Altus Knowledge and Performance', 'Altus للمعرفة والأداء',
            'A platform that converts three decades of hotel expertise and operating standards into a structured digital system for learning, knowledge management, and performance measurement. Built for independent hotels, serviced apartments, and SME establishments across Saudi Arabia and the wider region that need practical, standards-based training without the cost and complexity of global-chain systems.',
            'منصة تحوّل ثلاثة عقود من الخبرة الفندقية والمعايير التشغيلية إلى نظام رقمي منظَّم للتعلّم وإدارة المعرفة وقياس الأداء. صُمّمت للفنادق المستقلة والشقق الفندقية والمنشآت الصغيرة والمتوسطة في المملكة العربية السعودية والمنطقة عمومًا، ممن تحتاج إلى تدريب عملي قائم على المعايير دون تكلفة أنظمة السلاسل العالمية وتعقيدها.');
        foreach (array(
            array('Bilingual by design', 'ثنائية اللغة بالتصميم', 'Arabic and English, one experience', 'العربية والإنجليزية في تجربة واحدة'),
            array('White-label per property', 'علامة خاصة لكل منشأة', "Each establishment's own brand", 'بعلامة كل منشأة'),
            array('Governed AI assistant', 'مساعد ذكاء اصطناعي محكوم', 'Answers only from approved content', 'يجيب من المحتوى المعتمد فقط'),
            array('Evidence of progress', 'دليل على التقدّم', 'Dashboards, reports, certification', 'لوحات متابعة وتقارير وشهادات'),
        ) as $i => $r) {
            $B('platform_feature_' . ($i + 1), 'platform_features', $r[0], $r[1], $r[2], $r[3]);
        }
        foreach (array(
            array('The Learner', 'المتعلّم', 'Role-based plan, short applied lessons, assessment, live progress, and a completion certificate.', 'خطة حسب الدور، ودروس تطبيقية قصيرة، وتقييم، وتقدّم مباشر، وشهادة إتمام.'),
            array('Property Management', 'إدارة المنشأة', "Assign tracks, follow team completion, read competency reports, and apply the property's own brand.", 'إسناد المسارات، ومتابعة إنجاز الفريق، وقراءة تقارير الكفاءة، وتطبيق علامة المنشأة.'),
            array('The Altus Gulf Team', 'فريق Altus Gulf', 'Content management, quality control, versioning, client administration, and cross-property analytics.', 'إدارة المحتوى، وضبط الجودة، وإدارة الإصدارات، وإدارة العملاء، وتحليلات عبر المنشآت.'),
        ) as $i => $r) {
            $B('platform_experience_' . ($i + 1), 'platform_experiences', $r[0], $r[1], $r[2], $r[3]);
        }

        // ------------------------------------------------------------ Altus Ascent
        $B('ascent_intro', 'ascent_intro', 'The Altus Ascent™ Framework', 'إطار Altus Ascent™ (مسار الارتقاء)',
            "A six-stage operating discipline that carries every mandate from first diagnostic to institutionalised, self-sustaining performance, with defined gates, deliverables, and owner sign-off at each stage.\nPrecision in planning. Discipline in execution.",
            "منهج تشغيلي من ست مراحل ينقل كل مهمة من التشخيص الأول إلى أداء مؤسسي مستدام ذاتيًا، بمراحل عبور ومخرجات محددة واعتماد من المالك في كل مرحلة.\nدقة في التخطيط، وانضباط في التنفيذ.");
        foreach (array(
            array('Discover', 'الاستكشاف', 'Immersive diagnostic of the asset, market, and organisation. Data rooms, field audits, stakeholder interviews, and competitive benchmarking establish the factual baseline.', 'تشخيص معمّق للأصل والسوق والمنظمة. وتُرسي غرف البيانات والتدقيقات الميدانية ومقابلات أصحاب المصلحة والمقارنات المرجعية التنافسية خط الأساس الواقعي.'),
            array('Assess', 'التقييم', 'Gap analysis against global standards and investor objectives. Financial modelling, risk mapping, and opportunity sizing convert findings into a quantified case for change.', 'تحليل الفجوات قياسًا بالمعايير العالمية وأهداف المستثمرين. وتحوّل النمذجة المالية ورسم المخاطر وتقدير حجم الفرص النتائج إلى مبرّر كمّي للتغيير.'),
            array('Design', 'التصميم', 'The strategic blueprint: operating model, commercial architecture, organisational structure, and technology roadmap, each with owners, milestones, and measurable targets.', 'المخطط الاستراتيجي: النموذج التشغيلي، والبنية التجارية، والهيكل التنظيمي، وخارطة الطريق التقنية، لكلٍّ منها مسؤولون ومحطات إنجاز ومستهدفات قابلة للقياس.'),
            array('Transform', 'التحويل', 'Hands-on, shoulder-to-shoulder execution. We embed alongside client teams to implement, coach, and course-correct, not observe from a distance.', 'تنفيذ ميداني كتفًا بكتف. نعمل ضمن فرق العميل للتطبيق والتدريب وتصحيح المسار، لا للمراقبة من بعيد.'),
            array('Optimise', 'التحسين', 'Performance instrumentation, dashboarding, and iterative refinement. Pricing, cost, quality, and guest-experience levers tuned against live data.', 'أدوات قياس الأداء ولوحات المتابعة والتحسين المتكرر. وتُضبط روافع التسعير والتكلفة والجودة وتجربة النزيل وفق بيانات مباشرة.'),
            array('Scale', 'التوسّع', 'Institutionalisation and growth: playbooks, capability transfer, and governance handover so momentum can compound long after the engagement concludes.', 'المأسسة والنمو: أدلة عمل، ونقل القدرات، وتسليم الحوكمة، ليتراكم الزخم طويلًا بعد انتهاء التعاقد.'),
        ) as $i => $r) {
            $B('ascent_' . ($i + 1), 'ascent', $r[0], $r[1], $r[2], $r[3]);
        }
        $B('frameworks_intro', 'frameworks_intro', 'Strategic Frameworks', 'الأطر الاستراتيجية',
            'Two proprietary lenses through which Altus Gulf reads an asset: locating it on the performance map, then mapping the route toward compounding returns.',
            'عدستان خاصتان تقرأ بهما Altus Gulf الأصل: تحدّد موقعه على خريطة الأداء، ثم ترسم المسار نحو عوائد متراكمة.');
        // Performance Matrix quadrants, read: x = digital & commercial intelligence, y = operational rigour.
        foreach (array(
            array('matrix_legacy', 'Legacy Operator', 'مشغّل تقليدي', 'Sound operations, analogue commercial engine', 'عمليات سليمة، ومحرك تجاري تقليدي'),
            array('matrix_zone', 'The Altus Gulf Zone', 'منطقة Altus Gulf', 'Operational mastery × digital intelligence: compounding performance', 'إتقان تشغيلي × ذكاء رقمي: أداء متراكم'),
            array('matrix_undermanaged', 'Undermanaged Asset', 'أصل غير مُدار بكفاءة', 'Capital deployed, potential unrealised', 'رأس مال موظَّف، وإمكانات غير محقَّقة'),
            array('matrix_veneer', 'Digital Veneer', 'واجهة رقمية شكلية', 'Technology adopted, operations underpowered', 'تقنية معتمدة، وعمليات ضعيفة القدرة'),
        ) as $r) {
            $B($r[0], 'matrix', $r[1], $r[2], $r[3], $r[4]);
        }
        $B('matrix_mandate', 'matrix_note', 'The Altus Performance Matrix™', 'مصفوفة الأداء The Altus Performance Matrix™',
            'Our mandate: move every client asset up and to the right, into the Altus Gulf Zone.',
            'مهمتنا: نقل كل أصل لدى العميل نحو الأعلى وإلى اليمين، إلى منطقة Altus Gulf.');
        foreach (array(
            array('Top-line capture', 'اقتناص الإيرادات الإجمالية', 'Dynamic pricing • predictive yield • total revenue systems', 'التسعير الديناميكي • العائد التنبؤي • أنظمة الإيرادات الشاملة'),
            array('Distribution economics', 'اقتصاديات التوزيع', 'Channel-mix optimisation • OTA governance • direct-booking growth', 'تحسين مزيج القنوات • حوكمة وكالات السفر الإلكترونية (OTA) • نمو الحجز المباشر'),
            array('Cost containment', 'ضبط التكاليف', 'Disciplined cost frameworks • procurement discipline • productivity', 'أطر تكلفة منضبطة • انضباط المشتريات • الإنتاجية'),
            array('Asset productivity', 'إنتاجية الأصل', 'Space monetisation • capex governance • energy & lifecycle efficiency', 'تحقيق العائد من المساحات • حوكمة النفقات الرأسمالية • كفاءة الطاقة ودورة الحياة'),
        ) as $i => $r) {
            $B('goppar_' . ($i + 1), 'goppar', $r[0], $r[1], $r[2], $r[3]);
        }
        $B('goppar_note', 'goppar_note', 'The GOPPAR Value Stack™', 'سلّم قيمة GOPPAR (The GOPPAR Value Stack™)',
            'GOPPAR expansion. One diagnostic. One value model. A shared language for every mandate.',
            'نمو GOPPAR. تشخيص واحد ونموذج قيمة واحد ولغة مشتركة لكل مهمة.');

        // ------------------------------------------------------------ capabilities, sectors note
        $B('capabilities_intro', 'capabilities_intro', 'Institutional Capabilities', 'القدرات المؤسسية',
            "Eight integrated capability sets, deployed selectively per mandate, always in combination, never in isolation.\nEvery capability is delivered by principals, never delegated to a junior bench.",
            "ثماني مجموعات قدرات متكاملة، تُوظَّف انتقائيًا بحسب كل مهمة، دائمًا في تكامل ولا تعمل بمعزل عن بعضها.\nتُقدَّم كل قدرة على يد الشركاء الرئيسيين، ولا تُفوَّض إلى فريق مبتدئ.");
        foreach (array(
            array('Operational', 'التشغيلية', 'SOP architecture, quality systems, pre-opening readiness, service-delivery engineering across rooms, F&B, and support functions.', 'هيكلة إجراءات التشغيل القياسية (SOP)، ونظم الجودة، وجاهزية ما قبل الافتتاح، وهندسة تقديم الخدمة عبر الغرف والأغذية والمشروبات والوظائف المساندة.'),
            array('Commercial', 'التجارية', 'Revenue strategy, sales-force effectiveness, distribution and channel economics, brand and marketing performance.', 'استراتيجية الإيرادات، وفاعلية فرق المبيعات، واقتصاديات التوزيع والقنوات، وأداء العلامة والتسويق.'),
            array('Financial', 'المالية', 'P&L restructuring, cost-containment frameworks, budgeting and forecasting discipline, capex governance and ROI tracking.', 'إعادة هيكلة الأرباح والخسائر (P&L)، وأطر ضبط التكاليف، وانضباط الموازنات والتنبؤات، وحوكمة النفقات الرأسمالية وتتبع العائد على الاستثمار.'),
            array('Investment', 'الاستثمارية', 'Feasibility validation, underwriting support, due diligence, valuation and synergy modelling for acquisitions and development.', 'التحقق من الجدوى، ودعم التقييم الاستثماري، والعناية الواجبة، ونمذجة التقييم والتكامل لعمليات الاستحواذ والتطوير.'),
            array('Digital & AI', 'الرقمنة والذكاء الاصطناعي', 'AI-readiness assessment, dynamic pricing, predictive analytics, CRM and technology-stack modernisation, plus the Altus Gulf HK&P platform.', 'تقييم الجاهزية للذكاء الاصطناعي، والتسعير الديناميكي، والتحليلات التنبؤية، وتحديث CRM ومنظومة التقنية، إضافةً إلى منصة Altus Gulf HK&P.'),
            array('Organisational', 'التنظيمية', 'Structure redesign, role clarity, succession planning, leadership coaching, and capability-transfer programmes.', 'إعادة تصميم الهياكل، ووضوح الأدوار، وتخطيط التعاقب، وتدريب القيادات، وبرامج نقل القدرات.'),
            array('Governance', 'الحوكمة', 'HMA oversight, owner and operator alignment, performance-clause monitoring, reporting and board-level assurance.', 'الإشراف على اتفاقيات إدارة الفنادق (HMA)، ومواءمة المالك والمشغّل، ومتابعة بنود الأداء، والتقارير والتأكيد على مستوى مجلس الإدارة.'),
            array('Transformation', 'التحوّل', 'Turnaround management, change protocols, post-merger integration, and enterprise-wide performance programmes.', 'إدارة التحوّل والتعافي، وبروتوكولات التغيير، والدمج بعد الاندماج، وبرامج الأداء على مستوى المؤسسة.'),
        ) as $i => $r) {
            $B('capability_' . ($i + 1), 'capabilities', $r[0], $r[1], $r[2], $r[3]);
        }
        $B('sectors_intro', 'sectors_intro', 'Industries We Serve', 'القطاعات التي نخدمها',
            "One operating philosophy, applied across the sectors driving the Kingdom's transformation, wherever service, asset performance, and guest experience decide the outcome.\nCommitted at every scale: from a boutique independent property taking its first steps, to national portfolios and global enterprises.",
            "فلسفة تشغيلية واحدة تُطبَّق في القطاعات التي تقود تحوّل المملكة، أينما كانت الخدمة وأداء الأصول وتجربة النزيل هي التي تحسم النتيجة.\nنخدم كل الأحجام: من منشأة مستقلة صغيرة تخطو خطواتها الأولى، إلى محافظ وطنية ومؤسسات عالمية.");

        // ------------------------------------------------------------ Vision 2030 & market
        $B('v2030_intro', 'vision2030_intro', 'Engineered for Saudi Vision 2030', 'مصمَّمة لدعم رؤية السعودية 2030',
            "Vision 2030 is not a blueprint of the future; it is a live, national-scale transformation already underway. Altus Gulf has aligned its mission and service architecture to directly support the Kingdom's core pillars.",
            'رؤية 2030 ليست مخططًا لمستقبل بعيد، بل تحوّل وطني حيّ وواسع النطاق يجري بالفعل. وقد واءمت Altus Gulf رسالتها وبنية خدماتها لدعم ركائز المملكة الأساسية بصورة مباشرة.');
        $B('v2030_tourism', 'vision2030', 'Powering the Tourism & Hospitality Surge', 'دعم الطفرة في السياحة والضيافة',
            'With the Kingdom targeting 150 million annual visits by 2030, we act as a strategic partner to developers and investors: owner representation, feasibility validation, and operational optimisation that support the delivery of new hotel assets on time, on budget, and engineered for GOP from day one.',
            'مع استهداف المملكة 150 مليون زيارة سنويًا بحلول 2030، نعمل شريكًا استراتيجيًا للمطوّرين والمستثمرين: تمثيل المالك، والتحقق من الجدوى، وتحسين التشغيل، بما يدعم تسليم أصول فندقية جديدة في موعدها وضمن ميزانيتها، ومصمَّمة لتحقيق GOP منذ اليوم الأول.');
        $B('v2030_digital', 'vision2030', 'Advancing Digital & AI Leadership', 'تعزيز الريادة الرقمية والذكاء الاصطناعي',
            'Supporting a digitally enabled society and tech-forward economy: AI-based dynamic pricing, predictive revenue management, and advanced digital marketing. Our Altus Gulf Hospitality Knowledge & Performance platform replaces paper-based procedures with a governed digital system that supports transparency and a culture of continuous improvement.',
            'دعم مجتمع ممكَّن رقميًا واقتصاد متقدّم تقنيًا: تسعير ديناميكي قائم على الذكاء الاصطناعي، وإدارة إيرادات تنبؤية، وتسويق رقمي متقدم. وتستبدل منصة Altus Gulf للمعرفة والأداء في قطاع الضيافة الإجراءات الورقية بنظام رقمي محكوم يدعم الشفافية وثقافة التحسين المستمر.');
        $B('v2030_people', 'vision2030', 'Empowering Saudi Human Capital', 'تمكين رأس المال البشري السعودي',
            "In step with Saudization and youth-empowerment goals, our executive coaching, leadership-competency frameworks, and knowledge-transfer programmes help build a globally competitive class of Saudi executives, now delivered at scale through the platform's bilingual digital academy, learning paths, and certification.",
            'انسجامًا مع أهداف التوطين وتمكين الشباب، تُسهم برامجنا في التدريب التنفيذي وأطر الكفاءات القيادية ونقل المعرفة في بناء جيل من القيادات السعودية المنافسة عالميًا، وتُقدَّم اليوم على نطاق واسع عبر الأكاديمية الرقمية ثنائية اللغة ومسارات التعلّم والاعتماد في المنصة.');
        $B('v2030_sme', 'vision2030', 'Enabling SMEs, Start-ups & Bold Entrepreneurs', 'تمكين المنشآت الصغيرة والمتوسطة والشركات الناشئة ورواد الأعمال الجريئين',
            'Vision 2030 prioritises SME contribution to GDP. We bring Tier-1 consulting standards, typically reserved for larger groups, to Saudi start-ups and scale-ups, and we priced our platform deliberately for independent hotels and SME establishments, supporting resilience and growth from day one.',
            'تعطي رؤية 2030 أولوية لمساهمة المنشآت الصغيرة والمتوسطة في الناتج المحلي الإجمالي. ونحن نقدّم معايير استشارية من الفئة الأولى، التي عادةً ما تكون حكرًا على المجموعات الكبرى، إلى الشركات الناشئة والمتنامية في السعودية، وقد سعّرنا منصتنا عمدًا بما يناسب الفنادق المستقلة والمنشآت الصغيرة والمتوسطة، دعمًا للمرونة والنمو منذ اليوم الأول.');

        $B('market_opportunity', 'market', 'Market Opportunity', 'فرصة السوق',
            'The Kingdom is executing one of the most ambitious hospitality build-outs in the world. The numbers define both the scale of the opportunity and the premium on disciplined, owner-side execution.',
            'تنفّذ المملكة واحدًا من أكثر برامج بناء قطاع الضيافة طموحًا في العالم. وتوضح الأرقام حجم الفرصة كما تبرز القيمة المضافة للتنفيذ المنضبط في صف المالك.');
        foreach (array(
            array('122M', '122 مليون', 'Domestic & international visits in 2025 (+5% YoY)', 'زيارات محلية ودولية في 2025 (بنمو 5% سنويًا)'),
            array('SAR 300B', '300 مليار ريال', 'Total tourism spending in 2025 (≈ USD 81B)', 'إجمالي الإنفاق السياحي في 2025 (≈ 81 مليار دولار)'),
            array('362K', '362 ألف', 'Projected hotel keys by 2030 (from ~167.5K)', 'غرفة فندقية متوقعة بحلول 2030 (من نحو 167.5 ألف)'),
            array('78%', '78%', 'Of pipeline in luxury, upscale & upper-upscale', 'من المشاريع قيد التطوير في الفئات الفاخرة والراقية وفوق الراقية'),
        ) as $i => $r) {
            $B('market_kpi_' . ($i + 1), 'market_kpi', $r[0], $r[1], $r[2], $r[3]);
        }
        foreach (array(
            array('35.7M Umrah pilgrims (2024)', '35.7 مليون معتمر (2024)', 'Record religious demand', 'طلب ديني قياسي'),
            array('Global events', 'الفعاليات العالمية', 'Expo 2030 Riyadh • FIFA World Cup 2034 • Asian Winter Games 2029', 'إكسبو 2030 الرياض • كأس العالم لكرة القدم (FIFA) 2034 • دورة الألعاب الآسيوية الشتوية 2029'),
            array('Giga-projects', 'المشاريع العملاقة', 'NEOM, Red Sea, Diriyah, AlUla, Qiddiya', 'نيوم، والبحر الأحمر، والدرعية، والعلا، والقدية'),
        ) as $i => $r) {
            $B('market_catalyst_' . ($i + 1), 'market_catalyst', $r[0], $r[1], $r[2], $r[3]);
        }
        $B('market_sources', 'market_sources', 'Sources', 'المصادر',
            'Saudi Ministry of Tourism preliminary data (Jan 2026); Knight Frank, Saudi Arabia Hospitality Market Review 2025; Ministry of Hajj & Umrah, 2024.',
            'بيانات وزارة السياحة السعودية الأولية (يناير 2026)؛ Knight Frank، مراجعة سوق الضيافة في السعودية 2025؛ وزارة الحج والعمرة، 2024.');

        // ------------------------------------------------------------ partnerships, values, ESG, digital
        $B('partnerships_intro', 'partnerships_intro', 'Strategic Partnerships', 'الشراكات الاستراتيجية',
            'Altus Gulf operates as the hub of a deliberately curated ecosystem: aligning operators, capital, technology, and government so that every mandate draws on best-in-class execution partners.',
            'تعمل Altus Gulf بوصفها محور منظومة مُنتقاة بعناية: تجمع المشغّلين ورأس المال والتقنية والجهات الحكومية، بحيث تستعين كل مهمة بأفضل شركاء التنفيذ في فئتها.');
        foreach (array(
            array('Hotel Operators & Global Brands', 'مشغّلو الفنادق والعلامات العالمية', "Working relationships across international operating systems: supporting informed HMA negotiation, brand selection, and operator performance governance on the owner's behalf.", 'علاقات عمل عبر منظومات التشغيل الدولية: دعم التفاوض المدروس على اتفاقيات إدارة الفنادق (HMA)، واختيار العلامة، وحوكمة أداء المشغّل نيابةً عن المالك.'),
            array('Investors, Funds & Family Offices', 'المستثمرون والصناديق والمكاتب العائلية', 'Advisory alignment with institutional investors, developers, and family offices: feasibility validation, underwriting support, and asset-performance stewardship across the hold period.', 'مواءمة استشارية مع المستثمرين المؤسسيين والمطوّرين والمكاتب العائلية: التحقق من الجدوى، ودعم التقييم الاستثماري، والإشراف على أداء الأصل طوال فترة الاحتفاظ به.'),
            array('Technology & Data Partners', 'شركاء التقنية والبيانات', 'A vetted bench of PMS, revenue-management, BI, and AI solution providers: selected per mandate on merit, never on commission, preserving full advisory independence.', 'نخبة مدقَّقة من مزوّدي أنظمة إدارة الفنادق (PMS) وإدارة الإيرادات وذكاء الأعمال (BI) وحلول الذكاء الاصطناعي: تُختار لكل مهمة على أساس الكفاءة لا العمولة، حفاظًا على الاستقلالية الاستشارية الكاملة.'),
            array('Government & Development Authorities', 'الجهات الحكومية وهيئات التطوير', 'Constructive engagement with ministries, destination authorities, and giga-project entities: aligning private asset strategy with national tourism and Vision 2030 priorities.', 'تواصل بنّاء مع الوزارات وهيئات الوجهات السياحية وجهات المشاريع العملاقة: لمواءمة استراتيجية الأصول الخاصة مع أولويات السياحة الوطنية ورؤية 2030.'),
        ) as $i => $r) {
            $B('partnership_' . ($i + 1), 'partnerships', $r[0], $r[1], $r[2], $r[3]);
        }
        $B('independence', 'about', 'The Independence Principle', 'مبدأ الاستقلالية',
            "We hold no equity in operators, take no vendor commissions, and carry no brand allegiance. Our partnerships exist to serve one interest: the client's. That independence sits behind every recommendation we make.",
            'لا نملك حصصًا في المشغّلين، ولا نتقاضى عمولات من المزوّدين، ولا ننحاز إلى علامة بعينها. وقد قامت شراكاتنا لخدمة مصلحة واحدة هي مصلحة العميل. وهذه الاستقلالية هي الأساس الذي تقوم عليه كل توصية نقدمها.');

        $B('values_intro', 'values_intro', 'Our Values', 'قيمنا',
            'Seven commitments that guide how we advise, how we execute, and how we behave when no one is watching.',
            'سبعة التزامات توجّه كيف ننصح وكيف ننفّذ وكيف نتصرّف حين لا يراقبنا أحد.');
        foreach (array(
            array('value_integrity', 'Integrity', 'النزاهة', "We act as a genuine partner on the client's side: realistic diagnostics, transparent reporting, and advice that serves your interest even when it costs us the engagement.", 'نتصرف شريكًا حقيقيًا في صف العميل: تشخيص واقعي، وتقارير شفافة، ونصيحة تخدم مصلحتك حتى لو كلّفتنا التعاقد.'),
            array('value_excellence', 'Excellence', 'التميّز', 'Consistently high standards: a refusal of mediocrity through close attention to execution detail and strong global benchmarks.', 'معايير عالية ثابتة: رفض للمستوى المتوسط من خلال العناية الدقيقة بتفاصيل التنفيذ والاستناد إلى مرجعيات عالمية قوية.'),
            array('value_innovation', 'Innovation', 'الابتكار', 'An agile pursuit of digital progress: actively seeking, testing, and integrating new capability as a competitive advantage.', 'سعي رشيق نحو التقدم الرقمي: نبحث بنشاط عن قدرات جديدة ونختبرها وندمجها بوصفها ميزة تنافسية.'),
            array('value_hospitality', 'Hospitality', 'الضيافة', "Service is our native language. The guest's experience, and the owner's return on it, sit at the centre of every decision.", 'الخدمة لغتنا الأم. وتجربة النزيل، وعائد المالك منها، في صميم كل قرار.'),
            array('value_performance', 'Performance', 'الأداء', 'Data-driven precision: intuition deliberately replaced by empirical analytics, AI, and financial modelling. Impact that is measured, not asserted.', 'دقة قائمة على البيانات: نستبدل الحدس عمدًا بالتحليلات التجريبية والذكاء الاصطناعي والنمذجة المالية. أثر يُقاس ولا يُدَّعى.'),
            array('value_trust', 'Trust', 'الثقة', 'Earned through candour, confidentiality, and consistency: the currency of every boardroom relationship we hold.', 'تُكتسب بالصراحة والسرّية والثبات: وهي عملة كل علاقة نقيمها في قاعات مجالس الإدارة.'),
            array('value_collaboration', 'Collaboration', 'التعاون', 'Human-centred empowerment: we build alongside client teams and local talent, transferring capability rather than creating dependency.', 'تمكين محوره الإنسان: نبني جنبًا إلى جنب مع فرق العملاء والكفاءات المحلية، وننقل القدرات بدل أن نخلق الاعتمادية.'),
        ) as $r) {
            $B($r[0], 'values', $r[1], $r[2], $r[3], $r[4]);
        }

        $B('esg_intro', 'esg_intro', 'ESG & Sustainability', 'البيئة والمجتمع والحوكمة (ESG) والاستدامة',
            "Sustainable performance is not a compliance exercise; it is an asset-value strategy. We help embed environmental, social, and governance discipline into the operating model itself.\nAssets that respect their destination tend to outperform those that merely occupy it.",
            "الأداء المستدام ليس تمرينًا للامتثال، بل استراتيجية لقيمة الأصل. ونساعد على دمج الانضباط البيئي والاجتماعي وانضباط الحوكمة في النموذج التشغيلي نفسه.\nالأصول التي تحترم وجهتها تميل إلى التفوّق على تلك التي تكتفي بشغل موقعها فيها.");
        foreach (array(
            array('Governance', 'الحوكمة', 'Transparent owner reporting, HMA and procurement integrity, anti-leakage controls, and board-grade assurance frameworks: the governance architecture that supports capital discipline and reputation alike.', 'تقارير شفافة للمالك، ونزاهة اتفاقيات إدارة الفنادق (HMA) والمشتريات، وضوابط منع التسرّب المالي، وأطر تأكيد بمستوى مجلس الإدارة: بنية حوكمة تدعم الانضباط في إدارة رأس المال والسمعة معًا.'),
            array('Responsible Tourism', 'السياحة المسؤولة', "Destination stewardship aligned with Vision 2030's regenerative tourism agenda: visitor-impact management, heritage protection, and guest experiences designed to give back more than they take.", 'رعاية الوجهات بما يتوافق مع أجندة السياحة التجديدية في رؤية 2030: إدارة أثر الزوار، وحماية التراث، وتجارب نزلاء مصمَّمة لتعطي أكثر مما تأخذ.'),
            array('Community & People', 'المجتمع والكفاءات البشرية', 'Saudization-first talent strategies, local supplier development, fair and dignified workplaces, and structured knowledge transfer that aims to leave communities stronger than we found them.', 'استراتيجيات استقطاب المواهب التي تُقدّم التوطين أولًا، وتطوير الموردين المحليين، وبيئات عمل عادلة تحفظ الكرامة، ونقل منظّم للمعرفة يهدف إلى ترك المجتمعات أقوى مما وجدناها.'),
            array('Operational Sustainability', 'الاستدامة التشغيلية', 'Energy, water, and waste efficiency engineered into SOPs and capex plans: utilities intelligence and lifecycle costing that can reduce footprint while supporting GOP.', 'كفاءة الطاقة والمياه والنفايات مدمجة في إجراءات التشغيل القياسية وخطط النفقات الرأسمالية: ذكاء المرافق وتكلفة دورة الحياة بما قد يقلّل البصمة البيئية مع دعم GOP.'),
        ) as $i => $r) {
            $B('esg_' . ($i + 1), 'esg', $r[0], $r[1], $r[2], $r[3]);
        }

        $B('digital_intro', 'digital_intro', 'Digital & AI', 'الرقمنة والذكاء الاصطناعي',
            "Every Altus Gulf mandate ships with an intelligence layer: the instrumentation that turns operations into evidence, and evidence into advantage.\nFrom gut feel to ground truth: replacing intuition with empirical analytics, deliberately and everywhere.",
            "تأتي كل مهمة من Altus Gulf مزوَّدة بطبقة ذكاء: أدوات القياس التي تحوّل العمليات إلى أدلة، والأدلة إلى ميزة تنافسية.\nمن الحدس إلى الحقيقة الميدانية: نستبدل الحدس بالتحليلات التجريبية، عن قصد وفي كل مكان.");
        foreach (array(
            array('Business Intelligence', 'ذكاء الأعمال', 'A single source of truth across PMS, POS, channel, and financial data: unified, cleansed, and decision-ready.', 'مصدر واحد للحقيقة عبر بيانات PMS وPOS والقنوات والبيانات المالية: موحَّدة ومنقّاة وجاهزة لاتخاذ القرار.'),
            array('Executive Dashboards', 'لوحات المتابعة التنفيذية', 'Owner- and board-grade dashboards: RevPAR, GOPPAR, pickup, sentiment, and payroll productivity in one live view.', 'لوحات بمستوى المالك ومجلس الإدارة: RevPAR وGOPPAR والحجوزات المُضافة (pickup) وانطباعات النزلاء وإنتاجية الرواتب في عرض مباشر واحد.'),
            array('Applied AI', 'الذكاء الاصطناعي التطبيقي', 'Dynamic pricing matrices, predictive yield management, demand forecasting, and AI-readiness roadmaps deployed in phases.', 'مصفوفات التسعير الديناميكي، وإدارة العائد التنبؤية، والتنبؤ بالطلب، وخرائط طريق الجاهزية للذكاء الاصطناعي تُنفَّذ على مراحل.'),
            array('Advanced Analytics', 'التحليلات المتقدمة', 'Channel economics, guest-sentiment mining, cost-driver analysis, and scenario modelling for capital decisions.', 'اقتصاديات القنوات، وتحليل انطباعات النزلاء، وتحليل محرّكات التكلفة، ونمذجة السيناريوهات لقرارات رأس المال.'),
            array('Performance Monitoring', 'متابعة الأداء', 'Continuous KPI instrumentation with alerting: variances surfaced in days, not at month-end post-mortems.', 'قياس مستمر لمؤشرات الأداء الرئيسية (KPI) مع تنبيهات: تظهر الانحرافات خلال أيام، لا في مراجعات نهاية الشهر المتأخرة.'),
            array('Digital Transformation', 'التحول الرقمي', 'CRM architecture, cloud migration, and integrated technology blueprints that help cut long-run IT overheads.', 'بنية إدارة علاقات العملاء (CRM)، والانتقال إلى السحابة، ومخططات تقنية متكاملة تساعد على خفض تكاليف تقنية المعلومات على المدى البعيد.'),
        ) as $i => $r) {
            $B('digital_' . ($i + 1), 'digital', $r[0], $r[1], $r[2], $r[3]);
        }

        // ------------------------------------------------------------ comparison (body: "traditional | altus")
        $B('compare_intro', 'compare_intro', 'Why Clients Choose Altus Gulf', 'لماذا يختار العملاء Altus Gulf',
            "A candid comparison: how the Altus Gulf model differs from conventional consulting engagements, dimension by dimension.\nThe Altus Gulf difference in one sentence: we stay close to your business, measured by practical outcomes, and focused on the result, not simply the report.",
            "مقارنة صريحة: كيف يختلف نموذج Altus Gulf عن الارتباطات الاستشارية التقليدية، بُعدًا بعد بُعد.\nالفرق الذي تصنعه Altus Gulf في جملة واحدة: نبقى قريبين من أعمالك، ويُقاس عملنا بالنتائج العملية، ونركّز على الأثر الفعلي لا على التقرير وحده.");
        foreach (array(
            array('Perspective', 'المنظور', 'Operator- or vendor-influenced viewpoints | Owner-side, independent, and client-focused', 'وجهات نظر متأثرة بالمشغّل أو المزوّد | في صف المالك، مستقل، ومتمحور حول العميل'),
            array('Delivery team', 'فريق التنفيذ', 'Partner sells, junior bench delivers | Principals deliver every mandate personally', 'الشريك يبيع وفريق مبتدئ ينفّذ | يتولى الشركاء الرئيسيون تنفيذ كل مهمة بأنفسهم'),
            array('Grounding', 'الأساس المعرفي', 'Frameworks and benchmarks from the outside | 60+ years of hands-on P&L leadership inside global brands', 'أطر ومقارنات مرجعية مستمدة من الخارج | أكثر من 60 عامًا من القيادة الميدانية للأرباح والخسائر داخل علامات عالمية'),
            array('Scope logic', 'منطق النطاق', 'Operations or digital, sold separately | One integrated mandate across operations, commercial, and AI', 'التشغيل أو الرقمنة، يُباعان منفصلين | مهمة متكاملة واحدة تشمل التشغيل والجانب التجاري والذكاء الاصطناعي'),
            array('Evidence', 'الدليل', 'Opinion-led recommendations | Empirical analytics, Six Sigma discipline, measurable KPIs', 'توصيات قائمة على الرأي | تحليلات تجريبية، وانضباط سيجما ستة (Six Sigma)، ومؤشرات أداء قابلة للقياس'),
            array('Digital enablement', 'التمكين الرقمي', 'Advice ends at the report | Altus Gulf HK&P: a proprietary learning and performance platform that lives on inside the client', 'ينتهي الإرشاد عند التقرير | Altus Gulf HK&P: منصة خاصة للتعلّم والأداء تبقى حيّة داخل مؤسسة العميل'),
            array('Engagement end', 'نهاية التعاقد', 'Report handover closes the file | Playbooks, capability transfer, and an enduring alliance', 'تسليم التقرير يُغلق الملف | أدلة عمل، ونقل قدرات، وتحالف ممتد'),
            array('Regional depth', 'العمق الإقليمي', 'Fly-in, fly-out coverage | Riyadh-rooted, bilingual, Vision 2030-aligned by design', 'تغطية عبر زيارات عابرة | متجذّرة في الرياض، ثنائية اللغة، ومتوائمة مع رؤية 2030 بالتصميم'),
            array('Fee philosophy', 'فلسفة الأتعاب', 'Billable hours as the objective | Client success first: commercial terms follow outcomes', 'الساعات المفوترة كهدف | نجاح العميل أولًا: تتبع الشروط التجارية النتائج'),
        ) as $i => $r) {
            $B('compare_' . ($i + 1), 'compare', $r[0], $r[1], $r[2], $r[3]);
        }

        // ------------------------------------------------------------ closing
        $B('closing', 'closing', "Let's shape the next stage, together", 'لنرسم المرحلة القادمة معًا',
            'Wherever your establishment is today, the next stage starts with clarity. Altus Gulf can help define the priorities, identify the opportunities, and build a practical path forward.',
            'أينما كانت منشأتك اليوم، تبدأ المرحلة القادمة بالوضوح. يمكن أن تساعد Altus Gulf في تحديد الأولويات، واكتشاف الفرص، وبناء مسار عملي للمضي قدمًا.');
        $B('tagline', 'tagline', 'Elevating Hospitality & Business Performance', 'الارتقاء بالضيافة وأداء الأعمال',
            'Strategic Advisory & Business Consultancy', 'الاستشارات الاستراتيجية واستشارات الأعمال');
        return $n;
    }

    private function services() {
        $rows = array(
            array('owner_representation', 'hospitality', 'Hotel Development & Owner Representation', 'تطوير الفنادق وتمثيل المالك',
                'Independent client-side oversight across the project lifecycle. We act as the link between investors and contractors: helping protect budgets, reduce design deficiencies, govern HMAs, and manage contractor claims through handover.',
                'إشراف مستقل إلى جانب العميل على امتداد دورة حياة المشروع. نعمل حلقةَ وصل بين المستثمرين والمقاولين: نساعد على حماية الميزانيات والحدّ من أوجه القصور في التصميم وإحكام إدارة اتفاقيات إدارة الفنادق (HMA) ومعالجة مطالبات المقاولين حتى التسليم.'),
            array('pre_opening', 'hospitality', 'Operations Optimisation & Pre-Opening Support', 'تحسين العمليات ودعم ما قبل الافتتاح',
                'Deep diagnostic assessments and restructuring of day-to-day operations. Structured project-management frameworks support smooth commercial launches, help manage delays, and support revenue generation from day one.',
                'تقييمات تشخيصية معمّقة وإعادة هيكلة للعمليات اليومية. وتدعم أطر إدارة المشاريع المنظّمة انطلاقات تجارية سلسة، وتساعد على احتواء التأخيرات، وتدعم توليد الإيرادات منذ اليوم الأول.'),
            array('feasibility', 'hospitality', 'Feasibility Studies & Investment Validation', 'دراسات الجدوى والتحقق من الاستثمار',
                'Rigorous financial modelling, macro supply-and-demand analysis, and empirical ROI forecasting: giving institutional investors greater confidence that capital allocated to new hospitality assets is well supported by evidence.',
                'نمذجة مالية دقيقة، وتحليل كلّي للعرض والطلب، وتوقّعات تجريبية للعائد على الاستثمار (ROI)، بما يمنح المستثمرين المؤسسيين ثقة أكبر بأن رأس المال المخصّص لأصول الضيافة الجديدة مسنود بالأدلة.'),
            array('quality_audits', 'hospitality', 'Guest Experience Enhancement & Quality Audits', 'تعزيز تجربة النزيل وتدقيق الجودة',
                'Systematic elevation of physical and digital touchpoints to global standards: rigorous brand-compliance audits, safety-procedure remediation, and mystery-guest programmes.',
                'ارتقاء منهجي بنقاط التواصل المادية والرقمية إلى المعايير العالمية: تدقيق صارم على الالتزام بمعايير العلامة، ومعالجة إجراءات السلامة، وبرامج الزائر السري.'),
            array('commercial', 'hospitality', 'Commercial Performance Improvement', 'تحسين الأداء التجاري',
                'Holistic alignment of commercial sales, digital marketing, and internal operations. Disciplined cost-containment frameworks and distribution-channel analytics support stronger GOPPAR across the asset.',
                'مواءمة شاملة بين المبيعات التجارية والتسويق الرقمي والعمليات الداخلية. وتدعم أطر ضبط التكاليف المنضبطة وتحليلات قنوات التوزيع تحسين مؤشر GOPPAR (إجمالي الربح التشغيلي لكل غرفة متاحة) على مستوى الأصل.'),
            array('strategic_planning', 'business_growth', 'Strategic Planning & Organisational Restructuring', 'التخطيط الاستراتيجي وإعادة الهيكلة التنظيمية',
                'Three-to-five-year strategic roadmaps and redefined corporate hierarchies: reducing administrative drag, increasing agility, and embedding change-management protocols aligned with labour regulation.',
                'خرائط طريق استراتيجية لمدة ثلاث إلى خمس سنوات وهياكل مؤسسية مُعاد تعريفها: للحدّ من الأعباء الإدارية، وزيادة المرونة، وترسيخ بروتوكولات إدارة التغيير المتوافقة مع أنظمة العمل.'),
            array('revenue_ai', 'business_growth', 'Revenue Optimisation & Applied AI', 'تحسين الإيرادات والذكاء الاصطناعي التطبيقي',
                'Dynamic pricing matrices, predictive yield management, and total revenue-capture systems, alongside enterprise AI-readiness assessments and phased technology deployment roadmaps.',
                'مصفوفات التسعير الديناميكي، وإدارة العائد التنبؤية، وأنظمة الاستحواذ الشامل على الإيرادات، إلى جانب تقييمات جاهزية المؤسسة للذكاء الاصطناعي وخرائط طريق لنشر التقنية على مراحل.'),
            array('digital_transformation', 'business_growth', 'Digital Transformation', 'التحول الرقمي',
                'End-to-end modernisation of internal technology stacks, CRM architecture, cloud-migration strategy, and integrated software blueprints: designed to lower long-run IT overheads and strengthen data security.',
                'تحديث شامل لمنظومات التقنية الداخلية، وبنية إدارة علاقات العملاء (CRM)، واستراتيجية الانتقال إلى السحابة، ومخططات البرمجيات المتكاملة: بما يسهم في خفض تكاليف تقنية المعلومات على المدى البعيد وتعزيز أمن البيانات.'),
            array('leadership', 'business_growth', 'Leadership & Capability Development', 'تطوير القيادات والقدرات',
                'Bespoke one-to-one executive coaching, leadership-competency frameworks, and corporate succession planning: building resilient executive layers capable of navigating complex transformation.',
                'تدريب تنفيذي فردي مخصّص، وأطر كفاءات قيادية، وتخطيط للتعاقب الوظيفي المؤسسي: لبناء مستويات تنفيذية مرنة قادرة على إدارة التحولات المعقدة.'),
            array('due_diligence', 'business_growth', 'Investment Appraisal & M&A Due Diligence', 'تقييم الاستثمار والعناية الواجبة للاندماج والاستحواذ',
                'Rigorous commercial due diligence for asset acquisitions, pre-acquisition valuation reporting, operational-synergy modelling, and post-merger integration plans that support capital discipline throughout the transaction.',
                'عناية واجبة تجارية دقيقة لعمليات الاستحواذ على الأصول، وتقارير تقييم ما قبل الاستحواذ، ونمذجة التكامل التشغيلي، وخطط الدمج بعد الاندماج التي تدعم الانضباط في إدارة رأس المال طوال الصفقة.'),
        );
        foreach ($rows as $i => $s) {
            $this->upsert('ha_service', array('code' => $s[0]), array('division' => $s[1], 'title_en' => $s[2], 'title_ar' => $s[3],
                'summary_en' => $s[4], 'summary_ar' => $s[5], 'sort_order' => $i, 'status' => 'published'));
        }
        // Division introductions live with the blocks so the services page has its own lead-ins.
        $this->block('division_hospitality', 'division', 'Hospitality Solutions', 'حلول الضيافة',
            "Dedicated to the physical asset, the guest experience, and the operating engine of hospitality enterprises: structural, hands-on intervention that supports stronger returns.\nEngineering excellence. Supporting growth, from blueprint to full operational maturity.",
            "قسم مخصص للأصل المادي وتجربة النزيل والمحرك التشغيلي لمنشآت الضيافة: تدخّل هيكلي وميداني يدعم عوائد أقوى.\nهندسة التميّز ودعم النمو، من المخطط الأول حتى النضج التشغيلي الكامل.");
        $this->block('division_business_growth', 'division', 'Business Growth Solutions', 'حلول نمو الأعمال',
            "The client-focused rigour of luxury hospitality, applied to the wider corporate landscape, for enterprises scaling regionally and globally.\nWhere hospitality meets intelligence: data-driven strategy, human-centred execution.",
            "صرامة الضيافة الفاخرة المتمحورة حول العميل، مطبّقة على قطاع الأعمال الأوسع، لخدمة المؤسسات التي تتوسع إقليميًا وعالميًا.\nحيث تلتقي الضيافة بالذكاء: استراتيجية قائمة على البيانات وتنفيذ محوره الإنسان.");
        return count($rows) + 2;
    }

    private function cases() {
        $rows = array(
            array('flagship-resort-turnaround', 'Flagship Resort Turnaround & Sustained Market Leadership', 'إعادة إصلاح أداء منتجع رئيسي وريادة مستدامة في السوق',
                'Egypt', 'Turnaround', 'Flagship resort • Egypt • Turnaround', 'منتجع رئيسي • مصر • إعادة الإصلاح',
                'A 300+ key branded beachfront resort in a competitive leisure market, underperforming its comp set on rate and guest sentiment.',
                'منتجع بعلامة عالمية على الواجهة البحرية بأكثر من 300 غرفة، في سوق ترفيهي تنافسي، يقلّ أداؤه عن مجموعة منافسيه في السعر وانطباعات النزلاء.',
                'Stagnant RevPAR, slipping guest-satisfaction scores, and margin pressure despite strong location fundamentals.',
                'ركود في إيراد الغرفة المتاحة (RevPAR)، وتراجع مؤشرات رضا النزلاء، وضغط على الهوامش رغم قوة مقومات الموقع.',
                'Full commercial re-engineering: pricing architecture, channel mix, service-culture programme, and Six Sigma-led guest-journey redesign with disciplined cost governance.',
                'إعادة هندسة تجارية شاملة: هيكلة التسعير، ومزيج القنوات، وبرنامج ثقافة الخدمة، وإعادة تصميم رحلة النزيل بقيادة سيجما ستة (Six Sigma)، مع حوكمة منضبطة للتكاليف.',
                'The property rose to No. 1 in its competitive set and held the position for six consecutive years, alongside structural GOP margin expansion.',
                'ارتقى المنتجع إلى المرتبة الأولى ضمن مجموعته التنافسية وحافظ عليها ست سنوات متتالية، إلى جانب توسّع هيكلي في هامش GOP.',
                array(array('+25%', 'RevPAR growth', 'نمو RevPAR'), array('+30%', 'guest satisfaction', 'رضا النزلاء'), array('#1', 'comp set, 6 yrs', 'المجموعة التنافسية، 6 سنوات'), array('$340K', 'annual savings, 1 project', 'وفورات سنوية، مشروع واحد'))),
            array('portfolio-operational-excellence', 'Portfolio-Wide Operational Excellence Mandate', 'مهمة التميّز التشغيلي على مستوى المحفظة',
                'Egypt', 'Operational excellence', 'Multi-property • 19 hotels • Operational excellence', 'متعدد المنشآت • 19 فندقًا • التميّز التشغيلي',
                "A global operator's country portfolio of 19 hotels and 3,000+ rooms spanning city, resort, and airport assets.",
                'محفظة مشغّل عالمي في دولة واحدة تضم 19 فندقًا وأكثر من 3,000 غرفة، تشمل فنادق المدن والمنتجعات والمطارات.',
                'Inconsistent service delivery, fragmented F&B performance, and uneven brand-standard compliance across the estate.',
                'تفاوت في تقديم الخدمة، وتشتت في أداء الأغذية والمشروبات، وتباين في الالتزام بمعايير العلامة عبر المحفظة.',
                'Centralised excellence programme: standards harmonisation, targeted property-level diagnostics, capability coaching for GMs and HODs, and monthly performance instrumentation.',
                'برنامج تميّز مركزي: توحيد المعايير، وتشخيصات مستهدفة على مستوى كل منشأة، وتدريب المديرين العامين ورؤساء الأقسام على تنمية القدرات، وقياس شهري للأداء.',
                'Portfolio-wide uplift in guest satisfaction and F&B revenue within 18 months, with a repeatable playbook institutionalised across the estate.',
                'ارتفاع في رضا النزلاء وإيرادات الأغذية والمشروبات على مستوى المحفظة خلال 18 شهرًا، مع دليل عمل قابل للتكرار جرى ترسيخه في جميع المنشآت.',
                array(array('19', 'hotels, 1 mandate', 'فندقًا في مهمة واحدة'), array('3,000+', 'rooms in scope', 'غرفة ضمن النطاق'), array('+10%', 'guest satisfaction', 'رضا النزلاء'), array('+8%', 'F&B revenue, 18 mo', 'إيرادات الأغذية والمشروبات، 18 شهرًا'))),
            array('dual-hotel-pre-opening', 'Simultaneous Dual-Hotel Pre-Opening & Ramp-Up', 'الاستعداد المتزامن لافتتاح فندقين والوصول إلى التشغيل الكامل',
                'KSA', 'Pre-opening', 'Pre-opening • KSA • Dual launch', 'ما قبل الافتتاح • السعودية • افتتاح مزدوج',
                'A regional hospitality group commissioning two new-build properties in the Kingdom within a single opening season.',
                'مجموعة إقليمية تُدخل في الخدمة فندقين جديدين في المملكة خلال موسم افتتاح واحد.',
                'Parallel critical paths across licensing, recruitment, OS&E procurement, systems, and brand standards, with little room for launch slippage.',
                'مسارات حرجة متوازية تشمل التراخيص والتوظيف وشراء المستلزمات والمعدات التشغيلية (OS&E) والأنظمة ومعايير العلامة، مع هامش ضيق جدًا لتأخر الافتتاح.',
                'Integrated pre-opening command structure: unified countdown plan, staged recruitment and training waves, procurement governance, and revenue systems live before soft opening.',
                'هيكل قيادة متكامل لما قبل الافتتاح: خطة موحّدة للعد التنازلي، وموجات توظيف وتدريب مرحلية، وحوكمة المشتريات، وأنظمة إيرادات جاهزة قبل الافتتاح التجريبي.',
                'Both properties opened on schedule, transitioned to full operation, and reached market-leading profitability within the region.',
                'افتُتح الفندقان في موعدهما، وانتقلا إلى التشغيل الكامل، وحققا ربحية رائدة في السوق ضمن المنطقة.',
                array(array('2', 'hotels, simultaneous', 'فندقان بالتزامن'), array('90–95%', 'readiness at launch', 'الجاهزية عند الافتتاح'), array('#1', 'GOP, region post-ramp', 'GOP في المنطقة بعد التشغيل الكامل'), array('T-0', 'on-time opening', 'افتتاح في الموعد'))),
            array('riyadh-owner-representation', "Owner's Representative for a Branded Riyadh Portfolio", 'ممثّل المالك لمحفظة فنادق بعلامات عالمية في الرياض',
                'Riyadh', 'Owner representation', 'Owner representation • Riyadh • 4 branded assets', 'تمثيل المالك • الرياض • 4 أصول بعلامات عالمية',
                'An institutional owner developing four internationally branded hotels within a flagship Riyadh financial and technology district.',
                'مالك مؤسسي يطوّر أربعة فنادق بعلامات عالمية داخل حي مالي وتقني رئيسي في الرياض.',
                'Supporting owner interests across HMA governance, technical services, procurement and OS&E review, and operator handover, across four concurrent projects.',
                'دعم مصالح المالك في حوكمة اتفاقيات إدارة الفنادق (HMA) والخدمات الفنية والمشتريات ومراجعة OS&E وتسليم المشغّل، عبر أربعة مشاريع متزامنة.',
                'A dedicated owner-representation office: HMA compliance monitoring, design and procurement challenge, milestone-based contractor management, and structured building-handover protocols.',
                'مكتب مخصص لتمثيل المالك: متابعة الالتزام باتفاقيات إدارة الفنادق (HMA)، ومراجعة نقدية للتصميم والمشتريات، وإدارة المقاولين وفق المراحل، وبروتوكولات منظّمة لتسليم المبنى.',
                'Owner interests supported across all four assets: budgets defended, claims mitigated, and handovers executed to operator acceptance without material value erosion.',
                'دُعمت مصالح المالك في الأصول الأربعة كلها: حماية الميزانيات، والحدّ من المطالبات، وإتمام التسليم إلى المشغّل بقبوله دون تآكل جوهري في القيمة.',
                array(array('4', 'branded assets', 'أصول بعلامات عالمية'), array('HMA', 'full agreement governance', 'حوكمة كاملة للاتفاقيات'), array('OS&E', 'procurement review', 'مراجعة المشتريات'), array('100%', 'handovers accepted', 'عمليات تسليم مقبولة'))),
        );
        foreach ($rows as $i => $c) {
            $this->upsert('ha_case_study', array('slug' => $c[0]), array('title_en' => $c[1], 'title_ar' => $c[2], 'geography' => $c[3], 'case_type' => $c[4],
                'category' => $c[5], 'sector_code' => null,
                'client_profile_en' => $c[7], 'client_profile_ar' => $c[8], 'challenge_en' => $c[9], 'challenge_ar' => $c[10],
                'approach_en' => $c[11], 'approach_ar' => $c[12], 'results_en' => $c[13], 'results_ar' => $c[14],
                // metrics: value + label per language; the Arabic kicker rides along for the page header.
                'metrics_json' => json_encode(array('kicker_ar' => $c[6], 'items' => array_map(function ($m) { return array('value' => $m[0], 'en' => $m[1], 'ar' => $m[2]); }, $c[15])), JSON_UNESCAPED_UNICODE),
                'is_illustrative' => 1, 'visibility' => 'public', 'status' => 'published', 'sort_order' => $i));
        }
        $this->block('cases_intro', 'cases_intro', 'Illustrative Case Studies', 'دراسات حالة توضيحية',
            'Composite engagements, anonymised and clearly labelled as illustrative. Each reflects the type of mandate our principals have personally led inside global brands and independent portfolios; individual results vary by asset, market, and execution.',
            'مهام مركّبة مجهولة الهوية وموسومة بوضوح بأنها توضيحية. تعكس كلٌّ منها نوع المهام التي قادها شركاؤنا الرئيسيون بأنفسهم داخل علامات عالمية ومحافظ مستقلة؛ وتختلف النتائج الفردية بحسب الأصل والسوق والتنفيذ.');
        return count($rows) + 1;
    }

    private function leaders() {
        $rows = array(
            array('islam-mahrous', 'Islam Mahrous', 'إسلام محروس',
                'Co-Founder: Brand, Commercial Strategy & AI-Driven Digital Transformation', 'شريك مؤسس: العلامة التجارية والاستراتيجية التجارية والتحول الرقمي المدعوم بالذكاء الاصطناعي',
                "Islam is the firm's growth innovator and its forward-looking technological edge: the bridge between traditional business models and the demands of the modern era. Across three decades of multi-brand leadership with Marriott, IHG, Starwood, and Accor, and independent asset management across Saudi Arabia, the GCC, Egypt, and North Africa, he has shown entrepreneurs and investors alike that digital modernisation and brand clarity can be direct, measurable drivers of enterprise value. He is the product architect of the Altus Gulf Hospitality Knowledge & Performance platform.",
                'إسلام هو مبتكر النمو في الشركة وواجهتها التقنية الاستشرافية: الجسر بين نماذج الأعمال التقليدية ومتطلبات العصر الحديث. وعبر ثلاثة عقود من القيادة متعددة العلامات مع ماريوت وIHG وستاروود وأكور، وفي إدارة الأصول المستقلة في السعودية ودول الخليج ومصر وشمال أفريقيا، أثبت لرواد الأعمال والمستثمرين على السواء أن التحديث الرقمي ووضوح العلامة قد يكونان محرّكين مباشرين وقابلين للقياس لقيمة المؤسسة. وهو مصمّم منتج منصة Altus Gulf للمعرفة والأداء في قطاع الضيافة.',
                "30+ years of multi-brand hospitality leadership across the GCC, Egypt, and North Africa: Marriott, IHG, Starwood, and Accor systems.\nLed a flagship branded resort to No. 1 in Marriott's Egypt competitive set for six consecutive years: +25% RevPAR and +30% guest satisfaction with sustained GOP margin expansion.\nMandated by Marriott International to lead operational excellence across 19 hotels in Egypt (3,000+ rooms), lifting guest satisfaction 10% and F&B revenue 8% within 18 months.\nDirected five pre-opening projects across Saudi Arabia, Libya, Egypt, and West Africa: 1,700+ rooms delivered at 90–95% operational readiness.\nSix Sigma Black Belt: four certified DMAIC projects, including a guest-journey redesign saving USD 340K annually.\nOversaw a USD 10M+ capital renovation portfolio, delivering 7–20% cost savings with minimal disruption to the guest experience.",
                "أكثر من 30 عامًا من القيادة في قطاع الضيافة متعددة العلامات عبر دول الخليج ومصر وشمال أفريقيا: منظومات ماريوت وIHG وستاروود وأكور.\nقاد منتجعًا رئيسيًا بعلامة عالمية إلى المرتبة الأولى في المجموعة التنافسية لماريوت في مصر ست سنوات متتالية: نمو RevPAR بنسبة 25% وارتفاع رضا النزلاء بنسبة 30%، مع توسّع مستدام في هامش GOP.\nكُلِّف من ماريوت إنترناشونال بقيادة التميّز التشغيلي في 19 فندقًا في مصر (أكثر من 3,000 غرفة)، فرفع رضا النزلاء بنسبة 10% وإيرادات الأغذية والمشروبات بنسبة 8% خلال 18 شهرًا.\nأشرف على خمسة مشاريع ما قبل افتتاح في السعودية وليبيا ومصر وغرب أفريقيا: أكثر من 1,700 غرفة سُلّمت بجاهزية تشغيلية بلغت 90–95%.\nحاصل على الحزام الأسود في سيجما ستة (Six Sigma): أربعة مشاريع DMAIC معتمدة، منها إعادة تصميم رحلة النزيل بوفر سنوي بلغ 340 ألف دولار أمريكي.\nأشرف على محفظة تجديدات رأسمالية تتجاوز 10 ملايين دولار أمريكي، حققت وفورات في التكاليف بين 7% و20% مع أدنى قدر من التأثير على تجربة النزيل.",
                "Marriott GM Award for Customer Service Excellence: MEA, 2017 & 2022\nStarwood Best Operational Innovation Manager, 2007",
                "جائزة ماريوت لمديري الفنادق للتميّز في خدمة العملاء: الشرق الأوسط وأفريقيا، 2017 و2022\nأفضل مدير للابتكار التشغيلي من ستاروود، 2007"),
            array('hossam-smadi', 'Hussam Smadi', 'حسام الصمادي',
                'Co-Founder: Hospitality Operations & Asset Management', 'شريك مؤسس: عمليات الضيافة وإدارة الأصول',
                "Hussam is the firm's operational architect. With deep expertise in the operating complexities of international hospitality, his focus is converting physical assets into high-performing, operationally strong institutions. Across 30+ years of executive leadership in hotel operations and asset management throughout Saudi Arabia and Jordan, spanning global brands and independent groups, he helps bring high-level strategy to life carefully on the ground, with close adherence to quality standards.",
                'حسام هو مهندس العمليات في الشركة. وبخبرة عميقة في تعقيدات التشغيل في الضيافة الدولية، يتركّز اهتمامه على تحويل الأصول المادية إلى مؤسسات عالية الأداء وقوية تشغيليًا. وعبر أكثر من 30 عامًا من القيادة التنفيذية في تشغيل الفنادق وإدارة الأصول في السعودية والأردن، مع علامات عالمية ومجموعات مستقلة، يسهم في تجسيد الاستراتيجية رفيعة المستوى على أرض الواقع بعناية وبالتزام وثيق بمعايير الجودة.',
                "30+ years of executive leadership in hotel operations and asset management across Saudi Arabia and Jordan: global brands and independent groups.\nAs General Manager, led a property from pre-opening to full operation and to the title of Best Economy Hotel in Saudi Arabia.\nDelivered the highest GOP in the Jazan region while leading Swiss Blue Hotels properties, through commercial discipline and strict cost governance.\nSuccessfully directed two simultaneous hotel openings in 2018, carrying both to full operation and revenue generation.\nOwner's Representative for four internationally branded Riyadh assets: Crowne Plaza Digital City, InterContinental, Hotel Indigo, and Wyndham Grand at KAFD.\nDirectly governed Hotel Management Agreements (HMA), procurement and OS&E reviews, and operator building-handover processes.",
                "أكثر من 30 عامًا من القيادة التنفيذية في تشغيل الفنادق وإدارة الأصول في السعودية والأردن: علامات عالمية ومجموعات مستقلة.\nبصفته مديرًا عامًا، قاد فندقًا من مرحلة ما قبل الافتتاح إلى التشغيل الكامل، ثم إلى لقب أفضل فندق اقتصادي في السعودية.\nحقق أعلى GOP في منطقة جازان أثناء قيادته منشآت Swiss Blue Hotels، من خلال الانضباط التجاري والحوكمة الصارمة للتكاليف.\nأدار بنجاح افتتاح فندقين بالتزامن في عام 2018، ونقلهما إلى التشغيل الكامل وتوليد الإيرادات.\nممثّل المالك لأربعة أصول بعلامات عالمية في الرياض: Crowne Plaza Digital City، وInterContinental، وHotel Indigo، وWyndham Grand في KAFD.\nتولّى مباشرةً حوكمة اتفاقيات إدارة الفنادق (HMA) ومراجعات المشتريات وOS&E وعمليات تسليم المبنى للمشغّل.",
                "Best Economy Hotel in Saudi Arabia, as General Manager\nU.S. Embassy Riyadh recognition: Best Security Measures",
                "أفضل فندق اقتصادي في السعودية، بصفته مديرًا عامًا\nتكريم من سفارة الولايات المتحدة في الرياض: أفضل إجراءات أمنية"),
        );
        foreach ($rows as $i => $l) {
            $this->upsert('ha_leadership_profile', array('slug' => $l[0]), array('name_en' => $l[1], 'name_ar' => $l[2], 'role_en' => $l[3], 'role_ar' => $l[4],
                'biography_en' => $l[5], 'biography_ar' => $l[6], 'track_record_en' => $l[7], 'track_record_ar' => $l[8],
                'recognition_en' => $l[9], 'recognition_ar' => $l[10], 'sort_order' => $i, 'status' => 'published'));
        }
        return count($rows);
    }
}
