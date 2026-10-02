import { Course, Document, Entry, bi } from "./models";
export const assets = {
  logo: require("../../assets/altus-logo-horizontal.png"),
  logoLight: require("../../assets/altus-logo-horizontal-white.png"),
  mark: require("../../assets/altus-mark.png"),
  hero: require("../../assets/hero-riyadh-terrace.webp"),
  lobby: require("../../assets/platform-learning.webp"),
  training: require("../../assets/svc-training.webp"),
  operations: require("../../assets/svc-operations.webp"),
};
export const courses: Course[] = [
  {
    id: "guest-service",
    title: bi(
      "The art of exceptional guest service",
      "فن خدمة الضيف الاستثنائية",
    ),
    category: bi("Guest experience", "تجربة الضيف"),
    description: bi(
      "Turn everyday interactions into memorable experiences. Listen with intention, respond with confidence, and practice service recovery.",
      "حوّل التفاعلات اليومية إلى تجارب لا تُنسى. استمع باهتمام واستجب بثقة وتدرّب على استعادة رضا الضيف.",
    ),
    minutes: 24,
    lessons: 4,
    progress: 50,
    mandatory: true,
    image: "lobby",
  },
  {
    id: "safety",
    title: bi("A culture of safety", "ثقافة السلامة"),
    category: bi("Security & safety", "الأمن والسلامة"),
    description: bi(
      "Recognize risk, follow approved procedures and protect guests and colleagues.",
      "تعرّف على المخاطر واتبع الإجراءات المعتمدة لحماية الضيوف والزملاء.",
    ),
    minutes: 18,
    lessons: 3,
    progress: 0,
    mandatory: true,
    image: "operations",
  },
  {
    id: "leadership",
    title: bi("Lead with purpose", "قيادة هادفة"),
    category: bi("Leadership", "القيادة"),
    description: bi(
      "Build a confident team through clear expectations, coaching and practical feedback.",
      "ابنِ فريقاً واثقاً عبر التوقعات الواضحة والتوجيه والملاحظات العملية.",
    ),
    minutes: 32,
    lessons: 5,
    progress: 100,
    mandatory: false,
    image: "training",
  },
  {
    id: "front-office",
    title: bi("An arrival worth remembering", "وصول يستحق التذكّر"),
    category: bi("Front office", "المكاتب الأمامية"),
    description: bi(
      "Create a warm, organized arrival from the first greeting to the room introduction.",
      "قدّم وصولاً دافئاً ومنظماً من التحية الأولى إلى التعريف بالغرفة.",
    ),
    minutes: 20,
    lessons: 4,
    progress: 0,
    mandatory: false,
    image: "lobby",
  },
];
export const documents: Document[] = [
  {
    id: "complaint",
    title: bi("Guest complaint handling", "التعامل مع شكاوى الضيوف"),
    category: bi("Front office", "المكاتب الأمامية"),
    code: "FO-SOP-04",
    version: "2.1",
    date: "2026-09-15",
    type: "sop",
    purpose: bi(
      "Resolve concerns with empathy, ownership and consistent follow-through. This is sample guidance for the demonstration; your property-approved procedure is authoritative.",
      "عالج المشكلات بتعاطف ومسؤولية ومتابعة مستمرة. هذه إرشادات توضيحية؛ المرجع الملزم هو الإجراء المعتمد في منشأتك.",
    ),
    steps: [
      bi(
        "Listen without interruption. Acknowledge the guest’s concern.",
        "استمع دون مقاطعة واعترف بمشكلة الضيف.",
      ),
      bi(
        "Clarify the issue and confirm what a satisfactory outcome would look like.",
        "وضّح المشكلة وتأكد من النتيجة المُرضية للضيف.",
      ),
      bi(
        "Take ownership within your authorization. Escalate decisions beyond your authority.",
        "تحمّل المسؤولية ضمن صلاحياتك وصعّد القرارات التي تتجاوزها.",
      ),
      bi(
        "Record the concern and agreed action in the approved system.",
        "سجّل المشكلة والإجراء المتفق عليه في النظام المعتمد.",
      ),
      bi(
        "Follow up with the guest and share the learning with your supervisor.",
        "تابع مع الضيف وشارك ما تعلمته مع مشرفك.",
      ),
    ],
    safety: bi(
      "Never disclose guest details in public areas or promise unauthorized compensation.",
      "لا تفصح عن بيانات الضيف في الأماكن العامة ولا تعد بتعويض دون صلاحية.",
    ),
  },
  {
    id: "room-entry",
    title: bi("Entering a guest room", "دخول غرفة الضيف"),
    category: bi("Housekeeping", "التدبير الفندقي"),
    code: "HK-SOP-02",
    version: "1.3",
    date: "2026-08-20",
    type: "sop",
    purpose: bi(
      "Respect guest privacy and follow the approved room-access protocol. Demonstration content.",
      "احترم خصوصية الضيف واتبع بروتوكول الدخول المعتمد. محتوى توضيحي.",
    ),
    steps: [
      bi(
        "Check room status and any privacy instructions before approaching.",
        "تحقق من حالة الغرفة وتعليمات الخصوصية قبل الاقتراب.",
      ),
      bi(
        "Knock and announce your department according to the property procedure.",
        "اطرق الباب وعرّف بقسمك وفق إجراء المنشأة.",
      ),
      bi(
        "If access is not authorized, contact your supervisor.",
        "إذا لم يكن الدخول مسموحاً، تواصل مع مشرفك.",
      ),
    ],
    safety: bi(
      "Do not override a privacy request without authorized direction.",
      "لا تتجاوز طلب الخصوصية دون توجيه معتمد.",
    ),
  },
  {
    id: "lost-found",
    title: bi("Lost & found procedure", "إجراءات المفقودات"),
    category: bi("Hotel fundamentals", "أساسيات الفنادق"),
    code: "OPS-SOP-08",
    version: "3.0",
    date: "2026-09-28",
    type: "policy",
    purpose: bi(
      "Maintain a documented chain of custody for guest belongings. Demonstration content.",
      "حافظ على سجل موثق لحيازة متعلقات الضيوف. محتوى توضيحي.",
    ),
    steps: [
      bi(
        "Record the item, time and exact location found.",
        "سجّل الغرض والوقت والمكان الدقيق للعثور عليه.",
      ),
      bi(
        "Hand it to the authorized custodian and record the transfer.",
        "سلّمه إلى المسؤول المعتمد وسجّل عملية التسليم.",
      ),
      bi(
        "Verify ownership before release under the approved policy.",
        "تحقق من الملكية قبل التسليم وفق السياسة المعتمدة.",
      ),
    ],
    safety: bi(
      "Never photograph sensitive documents on a personal device.",
      "لا تصوّر المستندات الحساسة على جهاز شخصي.",
    ),
  },
  {
    id: "arrival-checklist",
    title: bi("Arrival readiness checklist", "قائمة جاهزية الوصول"),
    category: bi("Front office", "المكاتب الأمامية"),
    code: "FO-CHK-01",
    version: "1.0",
    date: "2026-09-01",
    type: "checklist",
    purpose: bi(
      "Prepare the arrival experience before the guest reaches the desk. Demonstration content.",
      "جهّز تجربة الوصول قبل وصول الضيف إلى المكتب. محتوى توضيحي.",
    ),
    steps: [
      bi(
        "Review authorized arrival information.",
        "راجع معلومات الوصول المصرح بها.",
      ),
      bi(
        "Confirm room readiness and approved requests.",
        "تأكد من جاهزية الغرفة والطلبات المعتمدة.",
      ),
      bi(
        "Prepare a warm and discreet greeting.",
        "جهّز تحية دافئة تحافظ على الخصوصية.",
      ),
    ],
    safety: bi(
      "Keep personal information visible only to authorized colleagues.",
      "اجعل المعلومات الشخصية متاحة فقط للزملاء المصرح لهم.",
    ),
  },
];
export const domains = [
  bi("Hotel fundamentals", "أساسيات الفنادق"),
  bi("Front office", "المكاتب الأمامية"),
  bi("Housekeeping", "التدبير الفندقي"),
  bi("Food & beverage", "الأغذية والمشروبات"),
  bi("Kitchen", "المطبخ"),
  bi("Sales & marketing", "المبيعات والتسويق"),
  bi("Revenue & reservations", "الإيرادات والحجوزات"),
  bi("Guest experience", "تجربة الضيف"),
  bi("Quality & audit", "الجودة والتدقيق"),
  bi("Security & safety", "الأمن والسلامة"),
  bi("Leadership", "القيادة"),
  bi("Sustainability", "الاستدامة"),
];
export const people: Entry[] = [
  {
    id: "employee-1",
    title: bi("Ahmed Al Mansouri", "أحمد المنصوري"),
    subtitle: bi(
      "Front office · Guest service",
      "المكاتب الأمامية · خدمة الضيوف",
    ),
    status: bi("In progress", "قيد التقدم"),
    destination: "learner-detail",
  },
  {
    id: "employee-2",
    title: bi("Sara Mohammed", "سارة محمد"),
    subtitle: bi(
      "Housekeeping · Floor supervisor",
      "التدبير الفندقي · مشرفة الطابق",
    ),
    status: bi("Ready", "جاهز"),
    destination: "learner-detail",
  },
  {
    id: "employee-3",
    title: bi("Omar Khalid", "عمر خالد"),
    subtitle: bi("Food & beverage · Service", "الأغذية والمشروبات · الخدمة"),
    status: bi("Needs attention", "يحتاج إلى متابعة"),
    destination: "learner-detail",
  },
];
export const notifications: Entry[] = [
  {
    id: "n1",
    title: bi("Your next step is ready", "خطوتك التالية جاهزة"),
    subtitle: bi(
      "Continue Guest Service · Assigned learning",
      "تابع خدمة الضيف · تعلّم معيّن",
    ),
    destination: "course",
  },
  {
    id: "n2",
    title: bi("A standard, updated", "تم تحديث معيار"),
    subtitle: bi("Lost & found · Version 3.0", "المفقودات · الإصدار ٣.٠"),
    destination: "sop",
  },
  {
    id: "n3",
    title: bi("Make time for growth", "خصص وقتاً للنمو"),
    subtitle: bi(
      "Service recovery workshop · 8 October",
      "ورشة استعادة رضا الضيف · ٨ أكتوبر",
    ),
    destination: "event",
  },
];
export const questions = [
  {
    title: bi(
      "A guest is upset about a delayed room. What should you do first?",
      "ضيف مستاء من تأخر تجهيز الغرفة. ماذا تفعل أولاً؟",
    ),
    options: [
      bi("Listen and acknowledge the concern", "استمع واعترف بالمشكلة"),
      bi("Explain that the hotel is busy", "اشرح أن الفندق مزدحم"),
      bi("Promise a free stay", "عِد بإقامة مجانية"),
    ],
    correct: 0,
    explanation: bi(
      "Start with empathy and listening. Compensation decisions must stay within authorized limits.",
      "ابدأ بالتعاطف والاستماع. يجب أن تبقى قرارات التعويض ضمن حدود الصلاحيات.",
    ),
  },
  {
    title: bi(
      "You can promise compensation outside your authority. True or false?",
      "يمكنك الوعد بتعويض خارج صلاحياتك. صح أم خطأ؟",
    ),
    options: [bi("True", "صح"), bi("False", "خطأ")],
    correct: 1,
    explanation: bi(
      "Escalate decisions beyond your authorization.",
      "صعّد القرارات التي تتجاوز صلاحياتك.",
    ),
  },
  {
    title: bi(
      "After resolving a concern, what completes the recovery?",
      "بعد معالجة المشكلة، ما الذي يُكمل استعادة الرضا؟",
    ),
    options: [
      bi("Close the conversation immediately", "أنه المحادثة فوراً"),
      bi(
        "Follow up and record the agreed action",
        "تابع وسجّل الإجراء المتفق عليه",
      ),
      bi("Share guest details publicly", "شارك بيانات الضيف علناً"),
    ],
    correct: 1,
    explanation: bi(
      "Follow-through and documented learning make improvement measurable.",
      "المتابعة والتعلّم الموثق يجعلان التحسين قابلاً للقياس.",
    ),
  },
];
