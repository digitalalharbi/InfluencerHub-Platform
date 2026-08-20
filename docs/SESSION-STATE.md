# حالة الجلسة — سجلّ دائم للاستئناف

> الغرض: التقاط حالة النظام وما أُنجز في المحادثة في ملفّ دائم داخل المستودع، فلا يعتمد الاستئناف على ذاكرة محادثة مُلخَّصة (ضاغطة/فاقدة). ابدأ محادثة جديدة واقرأ هذا الملفّ + PROJECT-OVERVIEW.md لتستأنف بلا فقد.

آخر تحديث: ٢٠٢٦-٠٨-١٠

---

## 1. أين المشروع الآن

- **المسار الثابت:** `~/Developer/InfluencerHub-Platform` (نُقل من سطح المكتب المُزامَن لتفادي تحرّك الملفّات).
- **الأصل:** GitHub `digitalalharbi/InfluencerHub-Platform` — هو المرجع. النشر منه إلى `influencerhub.io` عبر GitHub Actions → VPS.
- **قاعدة العمل:** الدفع إلى `main` = نشر مباشر على الحيّ. التطوير على فرع → معاينة محلّية → PR → دمج.
- **المعاينة المحلّية:** `php artisan serve --port=8010` → `http://127.0.0.1:8010`.
- **حساب مدير النظام (محلّي):** `system_admin@demo.test` / `admin-local-1234`.

---

## 2. الفرع النشط: `feature/admin-creator-pool`

قاعدة مؤثري مدير النظام + محرّك الترشيح. **٥ إيداعات، غير مدفوعة إلى main، لم تُنشَر على الحيّ.**

| المكوّن | الملفّ | الحالة |
|---|---|---|
| جدول القاعدة | `admin_creator_pool` (هجرة 2026_08_10_100001) | ✅ |
| نموذج القاعدة | `app/Domain/AdminPool/Models/PoolCreator.php` | ✅ |
| أمر الاستيراد | `app/Console/Commands/ImportCreatorPool.php` (`pool:import`) | ✅ |
| أمر الحذف | `PurgeCreatorPool.php` (`pool:purge`) + زرّ في الصفحة | ✅ |
| محرّك المطابقة | `app/Domain/AdminPool/Services/PoolMatchService.php` | ✅ |
| المساعد (قواعد + OpenAI معطّل) | `app/Domain/AdminPool/Assistant/*` | ✅ |
| متحكّم القاعدة | `Inertia/Admin/CreatorPoolController.php` | ✅ |
| متحكّم الترشيح | `Inertia/Admin/ShortlistingController.php` | ✅ |
| توصيات العميل | جدول `admin_pool_recommendations` + `PoolRecommendation.php` | ✅ |
| صفحتان React | `Admin/CreatorPool.tsx` · `Admin/Shortlisting.tsx` | ✅ |
| الاختبارات | CreatorPoolTest · PoolMatchTest · ShortlistAssistantTest (٢٣) | ✅ |

**المسارات (كلها تحت `system_admin`):**
`/beta/admin/creator-pool` · `/creator-pool/transfer` · `/creator-pool/purge` · `/beta/admin/shortlisting`.

---

## 3. القيود المطبَّقة (مهمّة — لا تُخالَف)

- **بيانات المصدر حسّاسة (ملكية فكرية):** استُبعِد نهائيًّا ولا يُذكَر: اسم المصدر/العلامة، رقم الترخيص، البنك/IBAN، اسم الموظّف، الشحنات. حارس `ALLOWED` في الاستيراد + اختبار يثبت عدم التسرّب.
- **لا PII في git إطلاقًا:** الجوّالات (~٣٠٠٠) في `storage/app/private/pool/pool.json` (مُتجاهَل)، والـExcel لا يُنسَخ للمستودع. الاستيراد يقرأ ملفًّا منظَّفًا خارج git.
- **لمدير النظام وحده:** كل المسارات محميّة بوسيط `system_admin`. لا تظهر لوكالة/مبدع/عميل.
- **التمييز المقصود:** مدير النظام يرى كل بيانات الحجز (تواصل، تكلفة، بيع). التحويل إلى العميل يأخذ **نسخة بلا جوّال** (snapshot لا مرجع) تصمد لحذف القاعدة.
- **زرّ الحذف الكامل:** يمسح القاعدة وملفّ الاستيراد بتأكيد نصّي، قبل أي استعراض للنظام.
- **لا ذكاء مُلفَّق:** `OpenAiAssistant` معطّل بصدق بلا مفتاح، يرتدّ إلى القواعد.

---

## 4. كيف تُعيد البيانات على أي بيئة

البيانات ليست في git. لإعادتها:
1. ضع الملفّ المنظَّف في `storage/app/private/pool/pool.json`.
2. `php artisan pool:import --fresh`.
- سكربت التنظيف (Python) يقرأ ملفّي Excel ويُخرِج JSON منظَّفًا (يستبعد المحظورات، يلتقط تكلفة + بيع، يُطبِّع المتابعين، يُزيل التكرار). آخر تشغيل: ٢٩٥٧ مؤثرًا (سناب ١٢٠٥ · تيك ١٧٢٧ · UGC ٣٠٧ · X ٢١ · لينكدإن ٤).

---

## 5. ما لم يُنفَّذ بعد (قرار المالك)

1. **منطق OpenAI الفعلي** في `OpenAiAssistant::interpret()` — البنية جاهزة، يُفعَّل بـ`OPENAI_API_KEY` + `POOL_ASSISTANT_DRIVER=openai`.
2. **واجهة قبول/رفض التوصية في بوابة العميل** — الجدول والتحويل جاهزان، تنقص الشاشة.
3. **الدفع إلى `main` والنشر** — لم يحدث. البيانات لن تصل الحيّ عبر Claude (لا PII في git)؛ تُشغَّل `pool:import` على الخادم بملفّ يضعه المالك.
4. **رحلة الوكالة الأساسية** موقوفة عند قرار العميل على ترشيح الحملة (سياق أقدم — انظر أقسام CONTINUATION-STATE السابقة).

---

## 6. مستندات مرجعية أخرى

- `PROJECT-OVERVIEW.md` — النظام كاملًا (منظومة، بوّابات، رحلة، مبادئ، حالة صادقة).
- `SHORTLISTING-AND-SMART-MATCHING.md` — تصميم محرّك الترشيح الذكي وخطته.
- `ARCHITECTURE-MAP.md` — المخطّط مقابل الواقع + تدقيق إمكانية الوصول بين الحالات.
- `CONTINUATION-STATE.md` — نقاط استئناف تاريخية أقدم.
