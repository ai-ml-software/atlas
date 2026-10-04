# ALTUS student guide / دليل الطالب

## Access your account

Open `/login`, use your own account and complete the authenticator prompt if enabled. Your workspace at `/hkp` shows only your authorised learning and knowledge. English and Arabic can be selected without changing your account. A property switch is offered only when you have access to multiple properties.

For mobile, install the current APK. Open Sign in, use the platform URL your administrator supplied, and choose **Get my personal API key**. In Account Security, confirm your password and authenticator code when enabled, then choose **Create personal mobile key**. Copy the `ha_…` key once and enter it in mobile Sign in. It expires after 90 days. Server setup uses a separate `altm_…` app key provided by the administrator; it cannot sign you in.

## Every student workspace option

| Option | How to use it |
| --- | --- |
| Dashboard | Read your next learning step and current progress. Continue the active course from its suggested position. |
| My learning | Find assigned, in-progress and completed courses. Open one, read its requirements and continue the first available lesson. |
| Learning paths | Follow the path sequence. Check prerequisites and due dates before trying a locked course. |
| Knowledge | Search approved SOPs in the right language/property. Open a result, read the current version and acknowledge only after understanding it. |
| Assessments | Read instructions, answer the available assessment and submit once ready. The server enforces attempts, passing criteria and any prerequisites. |
| Competencies | Review the capabilities and required evidence. Ask an authorised assessor about missing practical evidence. |
| Action plans | Record your action, due date and supporting evidence. Submit it for review and respond to the reviewer’s comments. |
| Certificates | Open/download issued certificates, check expiry and follow the verification link. Completion does not create a certificate unless its rules are satisfied. |
| AI assistant | Ask about approved operational guidance and inspect its cited sources. Ask your supervisor when coverage is insufficient. |
| Readiness | Review the learning, practical criteria and evidence available under your own scope. Missing evidence must be completed through its approved workflow. |
| Opening readiness | When visible, inspect the assigned opening-readiness requirements and follow up on missing items; this screen does not grant access to other properties. |
| Notifications | Read account updates and follow the linked course, assessment or action. |
| Profile | Check account details, language and permitted context. Change only the editable fields. |
| Account security | Manage authenticator/recovery codes, create an own mobile key and revoke unused keys. |

The student recording walks through all menu entries visible to the isolated learner fixture. The generated menu reference lists the actual visible controls for each recorded page. A workplace may enable additional features or restrict options further.

## Complete a course

1. Open My learning and select the assigned course. Read its title, requirements and current progress.
2. Open the next unlocked lesson. Read its text/PDF or use the supported media player. Follow the required completion control; protected progress is recorded on the server.
3. Continue in order. Locked lessons explain the prerequisite or release date. Returning to the course resumes the appropriate learning position.
4. Take the assessment when available. Review all answers before submitting. Check score/feedback and follow retry rules if you did not pass.
5. Complete any practical or additional evidence with the assigned assessor. Review Certificates when the configured issuance rules are met.

## Read and acknowledge an SOP

Search by topic or phrase. Check title, current version, property context and language. Read the complete procedure and any attachments, then acknowledge understanding. An acknowledgement is evidence of reading; it is not an approval or practical competency result. Follow the latest approved version when your property issues a revision.

## Mobile options

Home shows the next step. Learning lists live catalogue/assigned courses and assessments. Knowledge provides approved SOP search and acknowledgement. Assistant uses governed source-backed answers. Profile opens progress, certificates, Settings, account security and other entries. Settings contains **Server setup**, **Personal API key**, language/country, appearance, accessibility preferences, support contacts and account/privacy links. Server setup offers URL/app-key entry, Show/Hide key, Test connection, Save, native setup-QR scanning and Reset to built-in server.

Some native mobile entries currently show a clear unavailable state for a live account. Support ticket creation, discussion posting, attendance, encrypted offline media/progress synchronisation, native content administration and push delivery are not verified live services in this release. Demonstration mode shows sample/local flows and is clearly labelled. Use the website for supported instructor/admin operations. The complete mobile screen inventory identifies native data sources and demonstration-only entries; do not interpret demonstration data as your actual learning record.

## Protect your account and resolve errors

Keep personal keys and recovery codes private. Never paste them into a build `.env`, a support recording or a shared document. Sign out on shared devices. Revoke a lost device’s key in Account Security and create a replacement. When a lesson stays locked, check requirements and ask the instructor; repeated clicking does not bypass prerequisites. On connection failure, check URL, network and key type, use Test connection, and ask your administrator whether the key was revoked or expired. Learners cannot edit global app settings or another person’s record.

## العربية — الاستخدام اليومي

سجّل الدخول بحسابك وأكمل المصادقة الثنائية عند طلبها. من **تعلمي** افتح الدورة المسندة وأكمل الدروس المتاحة بالترتيب ثم الاختبار. راجع الملاحظات وأكمل الأدلة العملية المطلوبة مع المقيم. ابحث عن إجراءات التشغيل المعتمدة في **المعرفة**، واقرأ الإصدار الحالي قبل تأكيد الفهم. تابع خطط العمل والكفاءات والشهادات والإشعارات من مساحتك.

للتطبيق، افتح **الحصول على مفتاحي الشخصي**، ثم أنشئ مفتاح `ha_` من أمان الحساب بعد تأكيد كلمة المرور ورمز المصادقة. أدخله في تسجيل الدخول. مفتاح `altm_` الذي يقدمه المسؤول مخصص لإعداد الخادم فقط. اختبر الاتصال من الإعدادات، ولا تشارك مفاتيحك أو رموز الاسترداد. وضع العرض التوضيحي لا يمثل سجلك الحقيقي.
