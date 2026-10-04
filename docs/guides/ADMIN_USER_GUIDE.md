# ALTUS administrator guide / دليل المسؤول

Use the native workspace at `/hkp/admin`. The menu is built from your current permissions. System-wide mobile configuration needs a system-scoped account with `settings.view`; changes also need `settings.update`. Property administrators cannot change every property's app configuration.

## Sign in and find an option

1. Open `/login`, enter your account credentials and complete the authenticator step if enabled.
2. Open the administrator workspace. Website Pages, Courses, AI Publisher and Menus are the prominent shortcuts.
3. Use sidebar search to find a tool. Expand Content Studio, Website, People & performance, Portfolio or Platform. The current page expands its group automatically.
4. Set the correct property context before inspecting team data. Use the language selector for English or Arabic.

## Connect the mobile app

1. Open **Platform → Mobile app settings**, directly at `/hkp/admin/mobile`. The legacy administrator sidebar also has **Mobile app settings**.
2. Leave **API base URL** empty if each build/device should keep its own connection URL. Set it only when moving all signed-out apps to a reachable, deployed API. A phone cannot use a desktop's `localhost` without USB reverse forwarding.
3. Under App keys, choose a descriptive name and platform, then **Generate key**. Copy the full `altm_…` value immediately. It is shown once; the database stores only its HMAC.
4. Use **Test connection** on this page. On the app, open **Profile → Settings → Server setup**, enter the URL and app key, press **Test connection**, then **Save**. You can instead scan the locally generated setup QR on a native device.
5. For personal sign-in, open `/account_security`, enter your current password and authenticator code if required, then **Create personal mobile key**. Copy the `ha_…` value once. In mobile Sign in, enter your platform URL and personal key. Every native API request checks the user's current permissions and tenant scope.
6. Rotate an app key when replacing it. The old value stops working immediately. Update devices through Server setup or rebuild the configured app. Revoke unused app and personal keys. Personal mobile keys expire after 90 days.

| Mobile setting | Daily use |
| --- | --- |
| Connection | API URL to use after loading configuration; keep empty for device-specific local addresses. |
| Branding | English/Arabic app name, primary/accent/background colours, header logo and gate-screen image URLs. Store icon/native splash require a rebuild. |
| Modules | Enable learning, knowledge, assessments, assistant, QR, notifications, team, performance and demonstration. Disabling a module does not grant/revoke account permissions. |
| Language | First-launch default: English, Arabic or the device language. Existing user choices remain. |
| Support | Email, phone and URL displayed in app Settings. |
| Version rules | Per-platform minimum version blocks unsupported releases; latest version suggests an update; store URL opens the appropriate installer. |
| Maintenance | English/Arabic blocking message with Retry and Server setup still accessible. |
| App keys | Generate, inspect prefix/platform/last-used time, rotate and revoke. Full secrets cannot be recovered. |

Configuration refreshes at launch, on returning to the foreground and every five minutes while active and online. A failed refresh retains the matching server's public cache and exposes a connection error. A different server or rotated key does not reuse another server's cache.

## Website content and publication

Open Website Pages, select a language and page, then open its live editor. Click supported text, images or section controls on the real frontend. Insert one of the supported section types; duplicate, reorder or hide sections; choose media; check desktop and mobile previews. Save a private draft. Preview links require permission. Publish explicitly when reviewed. Home, the twelve requested public destinations and supported detail pages use the native records; catalogue, contact and verification components keep their functional behaviour.

Use Courses & lessons for course structure, sections, lesson bodies, media and quizzes. Use Programs, Learning paths, Articles and Hospitality topics for their native catalogues and relationships. Edit English and Arabic independently. Use Theme & site settings for approved colours, fonts, spacing and header/footer drafts. Use Navigation & footer for labels, destinations, menu order and footer groups. Revision history lets permitted users restore saved content. If another editor changed the record, reload and reconcile the conflict instead of overwriting it.

## PDF and AI Publisher

Open AI Publisher, upload a PDF and choose course, website page, article, SOP or hospitality topic. Embedded text is extracted first; scanned pages need Python, PyMuPDF and Tesseract language data. Follow job progress. Cancel unwanted work, inspect clear failures and retry after fixing the cause. Review source text beside generated drafts, correct extraction, reorder/edit content, regenerate selected sections, translate and select media. A course package includes sections, lessons and quizzes. Generated output remains a draft. SOPs must enter the existing review/approval workflow. An enabled compatible provider and model are required for genuine generation; setup checks identify missing configuration.

## People, learning and operations

Use Users, Organisations and Properties to maintain accounts and tenant grants. Use Curriculum, Assessments and Competencies for learning definitions, questions, scoring and evidence. Use Content review for SOP review and approvals. Use Readiness & certification for evidence rules and certificate lifecycle. Use Team, Cohorts and Assign learning to organise delivery. Use Assessor queue to grade practical criteria, Gaps and Actions for follow-up, Readiness and Opening readiness for required evidence, and Quality audits/KPIs/Reports for scoped operational review. Use Engagements and Frameworks for advisory work. Use Imports for supported bulk updates, Library and language coverage for reviewed release coverage, AI governance for providers/policies, Audit logs for traceability and System for health and scheduled work.

The accompanying recording and generated menu reference cover **every menu item visible to the seeded system administrator**, including the controls present on each page. Empty fixture lists are shown honestly. Your own role may have fewer items; additional legacy add-ons are documented by their existing vendor guides.

## Connect MCP

Open **Website → Integrations & approvals**. Connections lists OAuth clients/grants, Approvals lists publication requests, Health checks gateway/dependencies, Audit shows integration events, and Archive exposes permitted restoration. Install the separate Node 24 gateway and configure its issuer, native URL and delegated credential using the [installation runbook](../MCP_GATEWAY_RUNBOOK.md). Enable the MCP feature only after local connection checks pass. Use the displayed MCP URL in the client, sign in through ALTUS including 2FA, and consent to the requested scopes.

Each publication needs an administrator-reviewed approval bound to client, user, operation, object and version. It expires after ten minutes, is single-use and is invalidated by content changes. SOP approval is still required. Revoke access in Connections; current native permissions are checked again on every request. See [MCP daily workflows](../MCP_ADMIN_WORKFLOWS.md) for approval and troubleshooting steps.

## العربية — الخطوات الأساسية

سجّل الدخول وأكمل المصادقة الثنائية عند تفعيلها. افتح لوحة المسؤول ثم **المنصة → إعدادات تطبيق الجوال**. أنشئ مفتاح التطبيق `altm_` وانسخه مرة واحدة. في التطبيق افتح **الملف الشخصي → الإعدادات → إعداد الخادم**، وأدخل عنوان المنصة والمفتاح، ثم اختبر الاتصال واحفظ. من **أمان الحساب** أنشئ مفتاحك الشخصي `ha_` بعد التحقق من كلمة المرور ورمز المصادقة، واستخدمه في شاشة تسجيل الدخول بالتطبيق. لا تستخدم المفتاح الشخصي في ملف بيئة البناء.

عدّل الصفحات والدورات من السجلات الأصلية، واحفظ مسودة خاصة ثم عاين وانشر بعد المراجعة. راجع مخرجات PDF والذكاء الاصطناعي قبل اعتمادها؛ إجراءات التشغيل القياسية تتطلب الموافقات المعتادة. استخدم سجل المراجعات لاستعادة النسخ السابقة، وسجل التدقيق لتتبع التغييرات. اتصال MCP وموافقات النشر موجودة في **التكاملات والموافقات**، ولا تتجاوز صلاحيات المستخدم أو حدود المنشأة.

## Common problems

- Missing Mobile app settings: verify migration 31 is installed, the account is active/system-scoped and has the required settings permissions. Re-sign in after changing grants.
- Wrong app key: `altm_` is for Server setup; `ha_` is for Sign in. Rotation/revocation is immediate.
- HTTP works in browser but fails on phone: use the documented local reverse forwarding or HTTPS; the APK allows local HTTP only for localhost, 127.0.0.1 and 10.0.2.2.
- Web preview fails while native app works: configure the exact preview origin through `HA_MOBILE_WEB_ORIGINS`; arbitrary origins are refused.
- Personal key works but a screen is restricted: check current account permission and key scopes. A key does not elevate the account.
- Changed `.env` but old URL persists: restart Expo with its cache cleared or rebuild/install the new APK; reset any saved Server setup override.

For backup, migrations, worker/OCR configuration and rollback, use [installation](../ADMIN_INSTALL.md). The current acceptance report distinguishes verified local checks from production/provider/device-dependent checks.
