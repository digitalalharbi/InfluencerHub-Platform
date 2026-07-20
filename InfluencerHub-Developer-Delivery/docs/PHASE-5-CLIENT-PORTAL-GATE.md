# Phase 5 — بوابة العميل (Client Portal) — بوابة القبول

الحالة: **FEATURE COMPLETE** (بوابة العميل). التالي داخل Phase 5: بوابة الوكالة الخارجية (External Agency Portal) ثم Playwright + بوابة Phase 5 الكاملة.

> تسمية صادقة: هذه المرحلة **مكتملة الميزات ومُتحقَّقة بالمتصفّح والاختبارات**، وليست "جاهزة للإنتاج" — لا تزال قنوات البريد/SMS بانتظار مزوّد، وبوابة الوكالة الخارجية واختبارات E2E الموسّعة قيد التنفيذ.

## النطاق المُنجَز

| الوحدة | المسار | التحقق |
|--------|--------|:------:|
| دخول العميل + مبدّل العملاء (النشِطون فقط) | `/client/login`, `/client/switch` | Browser + Tests |
| اللوحة (أعداد فعلية + نسبة إكمال) | `/client/dashboard` | Browser |
| ملف العميل (مباشر فوري + حقول قانونية → مراجعة) | `/client/profile` | Browser + Tests |
| الملف المالي (بلا float) | `/client/billing-profile` | Browser + Tests |
| العناوين (CRUD + افتراضي فريد + أرشفة) | `/client/addresses` | Browser + Tests |
| المستندات الخاصة (رفع/إصدارات/تنزيل مُدقّق/رؤية) | `/client/documents` | Browser + Tests |
| سير عمل العلامات (مسودة→إرسال→إصدارات) | `/client/brands` | Browser + Tests |
| مراجعة العلامات (الوكالة، بالأحداث) | `/app/brand-reviews` | Browser + Tests |
| مراجعات العملاء (قانوني + مستندات) | `/app/client-reviews` | Browser + Tests |
| إدارة الفريق (دعوة/دور/حالة + حماية آخر مدير) | `/client/team` | Browser + Tests |
| الإشعارات (مركز + شارة + وصل بالأحداث) | `/client/notifications` | Browser + Tests |
| الإعدادات (تفضيلات/كلمة مرور/جلسات/2FA) | `/client/settings` | Browser + Tests |

## خصائص الأمان المُتحقَّقة
- **عزل المستأجر fail-closed**: كل استعلامات البوابة عبر `TenantContext` + `TenantScope`؛ Route model binding يفشل مغلقًا (404) عبر المستأجرين.
- **IDOR-safe**: كل مورد (عنوان/مستند/علامة/عضو/إشعار) مُقيَّد بالعميل النشِط، لا يُثق بمعرّفات النموذج.
- **العضوية النشطة فقط**: `EnsureClientMember` يرفض المعلّق/المُزال، جلسة مربوطة بالعميل النشِط.
- **الصلاحيات الدقيقة**: `ClientPortalAbilities` (EDIT_PROFILE/EDIT_BILLING/MANAGE_TEAM/MANAGE_DOCS/MANAGE_BRANDS) + `ClientPolicy` للوكالة.
- **الحقول المحظورة/الحساسة**: tenant_id/status/account_manager محظورة؛ الحقول القانونية عبر طلب مراجعة لا تُطبَّق مباشرة.
- **حماية آخر مدير**: لا يمكن خفض/تعليق/إزالة آخر client_admin نشِط.
- **بلا أسرار ثابتة**: كلمات مرور المعاينة عشوائية/بيئية (ملف خاص 0600)، اختبار يمنع الثابت.
- **إشعارات صادقة**: in_app فعلي، email/sms = waiting_for_credentials (لا تسليم وهمي).
- **جلسات**: عرض + إنهاء الجلسات الأخرى + إبطالها عند تغيير كلمة المرور.

## الاختبارات (خلفية)
الإجمالي **249 اختبارًا** ناجحة. أبرز مجموعات Phase 5:
`ClientPortalTest`, `ClientProfileTest`, `ClientAddressTest`, `ClientPortalDocumentTest`,
`BrandWorkflowTest(6)`, `AgencyClientReviewTest(7)`, `ClientTeamTest(9)`, `NotificationTest(5)`, `ClientSettingsTest(7)`,
`NoHardcodedCredentialsTest(3)`, `TenantResolutionTest(6)`.

## المتبقّي في Phase 5
1. ~~**بوابة الوكالة الخارجية** (`/partner/*`)~~ ✅ مُنجزة ومُتحقَّقة (قبول بالأحداث + دعوة + قبول عام مُحصّن + روابط مُنطّقة + بوابة شريك fail-closed).
2. **Playwright** (~50 سيناريو) لبوابة العميل + الوكالة الخارجية.
3. **بوابة Phase 5 الكاملة** بعد اكتمال ما سبق.
