# ALTUS instructor guide / دليل المدرب

## Sign in and understand your scope

Use `/login` and complete two-factor authentication if enabled. Open `/hkp` and locate Courses & lessons in Content Studio. Your instructor grant applies to the permitted records and learners; it does not confer organisation administration, global mobile settings or another tenant’s data. The sidebar is generated from permissions. The recording includes every menu item visible to the isolated instructor fixture, and the generated reference lists the visible page controls.

## Build and revise a course

1. Open Courses & lessons. Search for the permitted course or choose the available creation control.
2. Edit its English/Arabic title, description, category and publication fields. Preserve the course identifier when revising a course with learner history.
3. Add/order course sections and lessons. Choose the supported lesson type and media/document; verify that a learner can read the intended source.
4. Add the assessment and approved questions/answers. Configure score, attempts and evidence requirements. Check that questions match the teaching material.
5. Save a draft. Preview its learner experience, order and language coverage. Do not advertise pending translations as complete.
6. Publish only when your current role permits the normal publication workflow and review requirements are satisfied. AI-generated SOPs always use the SOP review/approval process.

## Instructor tools and their options

| Tool | Workflow |
| --- | --- |
| Learning/dashboard | Continue your own learning and inspect the learner-facing experience. |
| Courses & lessons | Search/open courses; edit permitted records, sections, lesson content/media and assessments; preserve history. |
| Assessments | Maintain questions, grading criteria and attempts; inspect submissions/outcomes under your scope. |
| Competencies/practical assessment | Review rubric requirements and supporting evidence, score permitted criteria and submit the assessment. Critical criteria can cap an outcome. |
| Team/learners | Find visible learners and inspect learning/capability data. Access is rechecked by the server. |
| Assign learning | Select permitted learners, course/path and due date when you have assignment permission. |
| Cohorts | Organise authorised learners for delivery and track individual completion where enabled. |
| Gaps/actions | Identify missing evidence, plan targeted learning or practical work, and review permitted follow-up actions. |
| Readiness/opening readiness | Inspect the required learning and practical evidence within your scope. Arrange the appropriate assessment or follow-up for missing requirements. |
| Reports | Filter the supported report to your scope and export only when allowed. |
| Knowledge/content review | Read approved material and submit eligible changes for review. Review/approve only with the corresponding permission. |
| AI Publisher | If both AI-generation and course-creation permissions are granted, upload PDF sources, review extraction and generate drafts using the configured provider/model. |
| Library/language coverage | If visible, inspect source and translation readiness. Publication requires reviewed language assets. |
| Notifications/profile | Follow learning updates and maintain permitted personal preferences. |
| Account security | Use authenticator/recovery codes and create/revoke your own mobile key. |

Some entries above appear only when an administrator assigns the corresponding permission. The recording/reference show the actual seeded instructor menu; they do not imply every instructor can see every management tool.

## Deliver, assess and follow up

Prepare reviewed course materials before assignment. Choose the correct cohort/learner and due date through the permitted assignment controls. Track participation and progress in the scoped learner view. For assessment, review instructions and evidence, apply the configured rubric, and record feedback. Do not replace failed critical criteria with course-completion status. Review capability gaps and action plans, then assign the appropriate remedial learning/practical work. Certificate issuance is controlled by its configured rules and the authorised issuer.

## Use PDF/AI safely within the normal workflow

If AI Publisher is visible, choose the output type and upload an eligible PDF. Monitor extraction, cancel unwanted jobs, correct source text and review generated sections, lessons and quiz answers. Regenerate selected sections instead of discarding reviewed content. Choose media and translations, save a private draft and preview. Setup failures identify missing OCR dependencies or provider/model configuration; ask the administrator to resolve them. AI output is not evidence of SOP approval.

## Mobile setup and daily use

Use **Get my personal API key** on mobile Sign in. Account Security requires your password and the authenticator code when enabled; the resulting own `ha_…` key expires after 90 days. Enter it with your platform URL on Sign in. The app now recognises the server’s instructor role. Configure public app settings separately through Settings → Server setup using the administrator’s `altm_…` key. Test, then Save; use native QR setup if preferred.

Use mobile for its live supported learning, knowledge, assessments and scoped performance features. Native content administration and other unavailable live flows show an explicit state; use the website’s instructor workspace for those operations. Demonstration content is sample/local data and must not be used as a real submission or learner report.

## Editing conflicts and common issues

On a version conflict, reload the current record and reconcile your intended change. Use permitted revision restoration instead of replacing identifiers. If a learner cannot continue, inspect enrolment, prerequisite, availability and assessment rules. If a user/key loses access, current role and tenant checks apply immediately. A missing menu entry requires the corresponding grant; creating a personal mobile key does not add that grant. A missing provider prevents genuine generation until configured.

## العربية — خطوات المدرب

سجّل الدخول وافتح **الدورات والدروس**. عدّل الدورة المصرح بها، وأضف الأقسام والدروس والوسائط والاختبارات، ثم احفظ مسودة وعاين المحتوى قبل النشر وفق صلاحياتك. حافظ على معرفات الدورة وسجل تقدم المتعلمين. قيّم الأدلة باستخدام المعايير المعتمدة، وتابع الفجوات وخطط العمل والتقارير ضمن نطاقك. إجراءات التشغيل القياسية تتطلب المراجعة والموافقة المعتادة.

للتطبيق، أنشئ مفتاحك الشخصي `ha_` من **أمان الحساب** بعد تأكيد كلمة المرور والمصادقة الثنائية، ثم أدخله في تسجيل الدخول مع عنوان المنصة. مفتاح `altm_` مخصص لإعداد الخادم ولا يمنح صلاحيات إضافية. استخدم لوحة الويب لإدارة المحتوى والعمليات التي لا تتوفر بعد للحساب المتصل في التطبيق.
