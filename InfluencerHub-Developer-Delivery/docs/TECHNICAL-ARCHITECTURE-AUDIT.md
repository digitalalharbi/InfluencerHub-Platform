# تدقيق البنية التقنية — الواقع مقابل المُعلَن

> تدقيق قراءة فقط. كل بند أدناه مُتحقَّق من ملف في المستودع، والمرجع مذكور بالمسار والسطر.
> ما لم يُتحقَّق مكتوب صراحةً **غير متحقَّق**. لم يُعتمَد على `docs/CONTINUATION-STATE.md` كمصدر
> حقيقة؛ عُومل كادّعاء وقُوبل بالشيفرة.
>
> نطاق الفحص: `app/`, `routes/`, `config/`, `resources/`, `bootstrap/`, `composer.json`, `package.json`, `vite.config.js`.

---

## 1. البنية الحالية (مُتحقَّقة)

### 1.1 المنصّة

| العنصر | القيمة الفعلية | المرجع |
|---|---|---|
| إطار العمل | Laravel `^12.0` | `composer.json:11` |
| PHP المطلوب | `^8.2` | `composer.json:8` |
| PHP في بيئة التطوير | 8.4.23 | `php -v` |
| هيكل الإقلاع | Laravel 11+ (`bootstrap/app.php`، بلا `Http/Kernel.php`) | `bootstrap/app.php:7` |
| Inertia (خادم) | `inertiajs/inertia-laravel ^3.1` | `composer.json:9` |
| المصادقة الآلية | `laravel/sanctum ^4.0` (لـ `routes/api.php` فقط) | `composer.json:12`، `routes/api.php:11` |
| عميل Redis | `predis/predis ^3.5` مثبَّت | `composer.json:13` |
| اختبارات | PHPUnit `^11.5.50` + Playwright `^1.61.1` | `composer.json:22`، `package.json:9` |

### 1.2 قاعدة البيانات

| العنصر | القيمة | المرجع |
|---|---|---|
| الافتراضي في `config/` | `sqlite` | `config/database.php:20` |
| الافتراضي في `.env.example` | `sqlite` | `.env.example` سطر `DB_CONNECTION=sqlite` |
| الواقع في بيئة المطوّر | `pgsql` | `.env` (`DB_CONNECTION=pgsql`) |
| بيئة E2E | `pgsql` → `influencerhub_e2e` | `.env.e2e` |
| قواعد موجودة فعلًا | `influencerhub`, `influencerhub_e2e`, `influencerhub_proof`, `influencerhub_testing` | `psql -l` |

**فجوة:** `.env.example` لا يزال هيكل Laravel الافتراضي (`sqlite`). استنساخ نظيف للمستودع
لا يُنتج نظامًا على PostgreSQL — وهو ما تفترضه المهاجرات والاستعلامات. الملف لم يُخصَّص للمشروع.

### 1.3 الواجهة

| العنصر | القيمة | المرجع |
|---|---|---|
| البناء | Vite `^7.0.7` + `laravel-vite-plugin ^2.0.0` | `package.json:16,12` |
| الواجهة | React `^19.2.7` + `@inertiajs/react ^3.6.1` | `package.json:26,25` |
| TypeScript | `^7.0.2` | `package.json:15` |
| CSS | Tailwind `^4.0.0` عبر `@tailwindcss/vite` | `package.json:14,6` |
| الأيقونات | `lucide-react ^1.25.0` | `package.json:27` |
| Alpine.js | `^3.14.1` — ما زال ضمن `dependencies` | `package.json:26` |

### 1.4 نقاط الدخول (`vite.config.js:11`)

| المُدخَل | الغرض | الحالة |
|---|---|---|
| `resources/css/app.css` | أنماط مشتركة (Blade + React) | حيّ |
| `resources/js/inertia.tsx` | طبقة React/Inertia | حيّ — سطح المنتَج كله |
| `resources/js/app.js` | Alpine/Blade | مُعلَّق عليه في `vite.config.js:10` بأنه «legacy, kept in parallel» |

`resources/views/inertia.blade.php:9` يُحمّل `app.css` + `inertia.tsx` فقط. `app.js` يُحمَّل من
`layouts/*.blade.php` فقط، أي أنه لا يخدم اليوم سوى صفحات الدخول وتدفّق الانضمام وأدوات التطوير.

### 1.5 الوسائط (Middleware)

مُسجَّلة كأسماء مستعارة في `bootstrap/app.php:16-23`:

| الاسم | الصنف |
|---|---|
| `tenant` | `App\Domain\Tenancy\Support\SetTenantContext` |
| `creator` | `App\Http\Middleware\EnsureCreator` |
| `client_member` | `App\Http\Middleware\EnsureClientMember` |
| `agency_member` | `App\Http\Middleware\EnsureAgencyMember` |
| `partner_member` | `App\Http\Middleware\EnsurePartnerMember` |
| `system_admin` | `App\Http\Middleware\EnsureSystemAdmin` |
| `inertia` | `App\Http\Middleware\HandleInertiaRequests` |

ترتيب حرِج مضبوط في `bootstrap/app.php:27-30`: `SetTenantContext` يسبق `SubstituteBindings`،
وإلّا عمل route-model binding بلا سياق مستأجر فيُغلق `TenantScope` حتى على المالك. التعليق في
الملف يوثّق السبب — وهو صحيح.

`HandleInertiaRequests` يلتزم حدّه: يشارك مستخدمًا/مساحة عمل/فلاش/عدّادات فقط، والصلاحيات
تبقى في Policies (`app/Http/Middleware/HandleInertiaRequests.php:16-18`).

### 1.6 مجموعات المسارات (`routes/web.php`)

| البادئة | السطور | الوسائط | الطبقة |
|---|---|---|---|
| (جذر) عام/تسويقي | 9-57 | `inertia` | React |
| `/join` | 61-80 | — | **Blade** |
| `/login`, `/logout` | 83-87 | `guest` / `auth` | Blade |
| `/creator` | 96-163 | `auth`,`creator` | React (داخل `inertia`) |
| `/client` | 172-249 | `auth`,`client_member` | React (داخل `inertia`) |
| `/partner` | 261-278 | `auth`,`partner_member` | React (داخل `inertia`) |
| `/beta` | 282-375 | `auth`,`tenant`,`agency_member`,`inertia` | React |
| `/beta/creator` | 378-408 | `auth`,`creator`,`inertia` | React |
| `/beta/admin` | 411-422 | `auth`,`system_admin`,`inertia` | React |
| `/beta/partner` | 425-431 | `auth`,`partner_member`,`inertia` | React |
| `/beta/client` | 434-479 | `auth`,`client_member`,`inertia` | React |
| `/app` | 481-670 | `auth`,`tenant`,`agency_member` | React (داخل `inertia`) + مساران ميتان |
| `/api/v1` | `routes/api.php:6` | `auth:sanctum`,`tenant` | JSON |

الفحص عبر `php artisan route:list` **غير متحقَّق**: `vendor/` غير مثبَّت في شجرة العمل هذه،
فالأمر يفشل قبل الإقلاع. كل ما سبق مقروء من المصدر مباشرةً.

---

## 2. Redis — التوجيه يفترض استعماله، والواقع أنه غير مستعمَل

**الخلاصة: Redis غير مستعمَل في أي سائق. لا ذاكرة، ولا طابور، ولا جلسات. كل شيء على قاعدة البيانات.**

| السائق | افتراضي `config/` | `.env.example` | `.env` الفعلي | `.env.e2e` | الحقيقة |
|---|---|---|---|---|---|
| الذاكرة | `database` — `config/cache.php:18` | `CACHE_STORE=database` | `CACHE_STORE=database` | `CACHE_STORE=database` | **database** |
| الطابور | `database` — `config/queue.php:16` | `QUEUE_CONNECTION=database` | `QUEUE_CONNECTION=database` | `QUEUE_CONNECTION=sync` | **database** |
| الجلسات | `database` — `config/session.php:21` | `SESSION_DRIVER=database` | `SESSION_DRIVER=database` | `SESSION_DRIVER=database` | **database** |

لا يوجد في أي طبقة مسار يقود إلى Redis: `config/cache.php:77` و`config/queue.php:69` يعرّفان
اتصال `redis` لكن لا شيء يختاره.

`predis/predis ^3.5` (`composer.json:13`) تبعية مثبَّتة بلا مستهلك. والبحث في `app/` عن
`Redis::` يعطي نتيجة واحدة فقط: `app/Support/Health/HealthCheck.php:125` — وهي داخل فرع
لا يُنفَّذ أصلًا في التهيئة الحالية.

**ملاحظة لصالح الشيفرة:** `HealthCheck` صادق ولا يبالغ. `app/Support/Health/HealthCheck.php:105-122`
يحسب `usedBy` من السوائق الفعلية، وإن كانت فارغة يُرجع الحالة `not_in_use` مع النص:
«لا سائق يعتمد عليه حاليًّا — الجلسات والطابور والذاكرة على قاعدة البيانات».
أي أن **الشيفرة تعرف الحقيقة، والتوثيق/التوجيه هو ما تجاوزها**.

`REDIS_CLIENT=phpredis` في `.env.example` بينما المثبَّت هو `predis`. تناقض غير ضار اليوم
(لأن Redis معطّل)، ويصبح خطأ تشغيل لحظة تفعيله.

---

## 3. الطوابير والمجدوِل

### 3.1 المهام (Jobs)

لا يوجد `app/Jobs/`. المهام موزّعة على النطاقات:

| المهمة | الملف | مَن يُطلقها |
|---|---|---|
| `SendOtpJob` | `app/Domain/Creators/Jobs/SendOtpJob.php:10` | `app/Domain/Creators/Services/CreatorApplicationService.php:169` |
| `FinalizeCreatorFilesJob` | `app/Domain/Creators/Jobs/FinalizeCreatorFilesJob.php:16` | `app/Domain/Creators/Actions/ApproveCreatorApplication.php:121` |
| `CreateTenantNoteJob` | `app/Domain/Tenancy/Jobs/CreateTenantNoteJob.php:10` | **لا مُطلِق في `app/`** — يُستدعى `handle()` مباشرةً من `tests/Feature/TenantHttpIsolationTest.php:95` |

`CreateTenantNoteJob` موثّق في ملفه بأنه «مثال Job» — أي أنه شيفرة تعليمية/اختبارية تعيش في
`app/Domain`، لا مهمّة منتَج.

بند مؤجَّل مُعلَن صراحةً: `app/Domain/CRM/Actions/InviteClientMember.php:21`
`// TODO(Queue): SendClientMemberInvitationNotification::dispatch($inv)` — دعوة عضو العميل
تُرسَل بشكل متزامن (أو لا تُرسَل)، والإشعار عبر الطابور لم يُنفَّذ بعد.

### 3.2 المهام المجدولة

مهمة واحدة، في `routes/console.php`:

```
Schedule::command('sla:scan')->hourly()->withoutOverlapping();
```

مرتبطة بـ `app/Console/Commands/SlaScanCommand.php` و`app/Domain/Automation/Services/SlaEngineService.php`.

### 3.3 هل يعمل عامل فعلًا؟ — لا، ليس عبر أي شيء في المستودع

| ما يلزم | الحالة | الدليل |
|---|---|---|
| عامل طابور في التطوير | موجود | `composer.json` سكربت `dev` يشغّل `php artisan queue:listen` ضمن `concurrently` |
| عامل طابور في الإنتاج | **مفقود** | لا `Procfile`، لا `docker-compose*`، لا ملف `supervisor` في الجذر |
| مُشغِّل المجدوِل (`schedule:run` كل دقيقة) | **مفقود** | لا cron ولا مكافئ في المستودع |
| `.env.e2e` | `QUEUE_CONNECTION=sync` | المهام تُنفَّذ داخل الطلب في E2E، فلا يختبر أحدٌ المسار غير المتزامن |

**الأثر العملي:** خارج جلسة `composer dev` المحلية، `SendOtpJob` و`FinalizeCreatorFilesJob`
تُكتَب في جدول `jobs` ولا يستهلكها أحد. رمز التحقق لا يصل، وملفات المبدع لا تُرحَّل بعد
الموافقة. و`sla:scan` لا يعمل أبدًا، فمحرّك SLA بلا نبض.
هل يوجد إعداد نشر خارج المستودع؟ **غير متحقَّق** (خارج نطاق الشيفرة).

---

## 4. ازدواج Blade/React

### 4.1 الادّعاء

«لم يبقَ أي صفحة منتَج على Blade».

### 4.2 الحكم

**الادّعاء غير دقيق.** لا توجد صفحة منتَج مُكرَّرة بنسختين متطابقتين (Blade + React) — هذا
الجزء صحيح. لكن **تدفّق انضمام المبدع بأكمله ما زال على Blade ولا مقابل React له**، وهو
سطح منتَج لا تدفّق مصادقة.

### 4.3 جرد Blade الكامل — 35 ملفًا

| الملف | التصنيف | مرجع الاستعمال |
|---|---|---|
| `resources/views/inertia.blade.php` | بنية تحتية (جذر Inertia) | `HandleInertiaRequests.php:22` |
| `resources/views/auth/login.blade.php` | auth | `Auth/LoginController.php:9` |
| `resources/views/client/login.blade.php` | auth | `Client/ClientAuthController.php:11` |
| `resources/views/creator/login.blade.php` | auth | `Creator/CreatorAuthController.php:11` |
| `resources/views/partner/login.blade.php` | auth | `Partner/PartnerAuthController.php:11` |
| `resources/views/layouts/auth.blade.php` | auth (تخطيط) | تُوسَّع من صفحات الدخول الأربع |
| `resources/views/mail/otp.blade.php` | email | قالب بريد |
| `resources/views/mail/signup-approved.blade.php` | email | قالب بريد |
| `resources/views/mail/signup-rejected.blade.php` | email | قالب بريد |
| `resources/views/dev/design-system.blade.php` | dev-tool | `Web/PreviewCenterController.php:32` |
| `resources/views/dev/preview-center.blade.php` | dev-tool | `Web/PreviewCenterController.php:26` |
| **`resources/views/join/index.blade.php`** | **صفحة منتَج على Blade** | `Public/JoinController.php:14` |
| **`resources/views/join/creator.blade.php`** | **صفحة منتَج على Blade** | `Public/JoinController.php:18` |
| **`resources/views/join/status.blade.php`** | **صفحة منتَج على Blade** (168 سطرًا) | `Public/JoinController.php:80` |
| **`resources/views/join/recover.blade.php`** | **صفحة منتَج على Blade** | `Public/JoinController.php:253` |
| **`resources/views/partner/accept-invite.blade.php`** | **صفحة منتَج على Blade** | `Partner/PartnerInvitationController.php:13` |
| **`resources/views/partner/invite-invalid.blade.php`** | **صفحة منتَج على Blade** | `Partner/PartnerInvitationController.php:12` |
| `resources/views/layouts/public.blade.php` | تخطيط لصفحات `join` | تُوسَّع من `join/*` |
| `resources/views/client/not-available.blade.php` | كعب (stub) | `Client/ClientPortalController.php:10` |
| `resources/views/creator/not-available.blade.php` | كعب (stub) | `Creator/CreatorPortalController.php:14` |
| `resources/views/partner/not-available.blade.php` | كعب (stub) | `Partner/PartnerPortalController.php:11` |
| `resources/views/client/layout.blade.php` | تخطيط — لا يخدم إلا الكعب | `client/not-available.blade.php:1` |
| `resources/views/creator/layout.blade.php` | تخطيط — لا يخدم إلا الكعب | `creator/not-available.blade.php:1` |
| `resources/views/partner/layout.blade.php` | تخطيط — لا يخدم إلا الكعب | `partner/not-available.blade.php:1` |
| `resources/views/layouts/app.blade.php` | تخطيط — لا يخدم إلا `dev/*` | `dev/*.blade.php:1` |
| `resources/views/welcome.blade.php` | **يتيم** (278 سطرًا) | صفر مراجع — انظر §6 |
| `resources/views/components/app-nav.blade.php` | مكوّن — لتخطيطات البوابات الميتة | `layouts/app:36`, `client/layout:29`, `partner/layout:23` |
| `resources/views/components/app-bottom-nav.blade.php` | مكوّن — نفس المصير | `layouts/app:79`, `client/layout:47`, `partner/layout:40` |
| `resources/views/components/command-palette.blade.php` | مكوّن — نفس المصير | `layouts/app:80` |
| `resources/views/components/workspace-header.blade.php` | مكوّن — لا يُستعمل إلا في dev | `dev/design-system.blade.php:261` |
| `resources/views/components/summary-strip.blade.php` | مكوّن — لا يُستعمل إلا في dev | `dev/design-system.blade.php:273` |
| `resources/views/components/status-badge.blade.php` | مكوّن — dev + `workspace-header:21` | انظر §5-B5 |
| `resources/views/components/ih-benefit.blade.php` | مكوّن auth | `creator/login.blade.php:7-9` |
| `resources/views/components/ih-logo.blade.php` | مكوّن auth | `layouts/auth.blade.php:17,38` |
| `resources/views/components/icon.blade.php` | مكوّن مشترك | `layouts/app.blade.php:56` وغيره |

**المجموع: 6 صفحات منتَج ما زالت على Blade**، إضافةً إلى 3 أكعُب و7 مكوّنات/تخطيطات لا تخدم
إلا تلك الأكعُب أو أدوات التطوير.

### 4.4 هل توجد صفحة منتَج بنسختين (Blade + React)؟

لا توجد ازدواجية بالمعنى الحرفي. أقرب حالة تراكب هي بوّابتا تسجيل متوازيتان لا نسختان لصفحة واحدة:

| السطح | Blade | React | ملاحظة |
|---|---|---|---|
| مدخل التسجيل | `resources/views/join/index.blade.php` → `/join` | `resources/js/Pages/Public/AccountType.tsx` → `/register/account-type` (`routes/web.php:11-12`) | Blade يعرض خيارًا واحدًا (مبدع)، React يعرض ثلاثة. مدخلان مختلفان لنفس القرار، لا نسختان لنفس الصفحة. |
| تسجيل المبدع | `join/creator` + `join/status` + `join/recover` (Blade) | **لا مقابل** | التدفّق الوحيد. |
| تسجيل الوكالة | **لا مقابل** | `Pages/Public/SelfSignup/{Start,Verify,Setup,Stepper}.tsx` (`routes/web.php:38-44`) | التدفّق الوحيد. |

النتيجة: **لا انتهاك ازدواج، بل انتهاك تغطية** — نصف تدفّقات التسجيل على Blade والنصف
الآخر على React، والمستخدم يعبر بين مظهرين مختلفين داخل الرحلة نفسها.

### 4.5 الازدواج الحقيقي: نفس صفحات React تحت بادئتين

هذا هو الازدواج الفعلي في المشروع، وهو أوسع من أي بقايا Blade:

| السطح | البادئة أ | البادئة ب | نفس المتحكّمات؟ |
|---|---|---|---|
| الوكالة | `/app` (`routes/web.php:481`) | `/beta` (`routes/web.php:282`) | نعم — `Inertia\*Controller` نفسها |
| بوابة العميل | `/client` (`:172`) | `/beta/client` (`:434`) | نعم — `Inertia\Client\*` نفسها |
| بوابة المبدع | `/creator` (`:96`) | `/beta/creator` (`:378`) | نعم — `Inertia\Creator\*` نفسها |
| بوابة الشريك | `/partner` (`:261`) | `/beta/partner` (`:425`) | نعم — `Inertia\Partner\*` نفسها |
| لوحة النظام | **لا مقابل** | `/beta/admin` (`:411`) | تعيش تحت `beta` وحدها |

`app/Support/Http/MountPrefix.php:19-21` يُقنّن هذا الازدواج بدل إزالته: قائمة عشر بادئات
تُحسَب لكل طلب لتُبنى منها إعادة التوجيه، وتُشارَك مع الواجهة عبر
`HandleInertiaRequests.php:53` (`'base' => MountPrefix::for($request)`). التعليق في
`MountPrefix.php:10-11` يصف السبب بصراحة: «أثناء التحويل التدريجي من Blade تُقدَّم الصفحة
نفسها تحت أكثر من بادئة». لكن التحويل انتهى — والبادئات بقيت.

المجموعتان ليستا متطابقتين. `/beta` (282-375) أقصر من `/app` (481-670): مثلًا الفواتير
(`routes/web.php:610-617`) والناشرون (`:663-666`) و`/my-tasks` (`:658`) موجودة تحت `/app`
فقط. أي أن `/beta` **نسخة قديمة ناقصة** ما زالت مفتوحة للمستخدمين المصادَق عليهم.

التعليق في `routes/web.php:281` ما زال يقول «لا يحذف نسخة Blade في /app حتى تُثبت بوابة
القبول» — وهو وصف لواقع لم يعد قائمًا: لا توجد نسخة Blade في `/app`.

---

## 5. تكرار منطق العمل

### 5.1 منطق مُعاد في المتحكّمات رغم وجوده في الخدمات

| # | المفهوم | المصدر الشرعي | النسخة المكرّرة | الأثر |
|---|---|---|---|---|
| A1 | حالات المحتوى القابلة لتحرير المبدع | `app/Domain/Content/Models/ContentItem.php:16` (`CREATOR_EDITABLE`)، مُستهلَك في `ContentWorkflowService.php:56` | `Inertia/Creator/ContentController.php:30` و`:65` — `['draft','changes_requested']` حرفيًّا | إضافة حالة ثالثة تترك زرّ التحرير معطّلًا بينما الخدمة تقبل التحرير |
| A2 | آلة الحالات (أي إجراء متاح في أي حالة) | `ALLOWED` في ثماني خدمات: `ContentWorkflowService.php:20`, `ContractWorkflowService.php:20`, `CollaborationWorkflowService.php:21`, `PayoutWorkflowService.php:21`, `InvoiceService.php:23`, `ServiceRequestWorkflowService.php:20`, `CampaignWorkflowService.php:20`, `BrandWorkflowService.php:17` | خرائط `ACTIONS` في ثمانية متحكّمات: `ContentDetailController.php:22`, `ContractDetailController.php:21`, `CollaborationDetailController.php:21`, `PayoutDetailController.php:21`, `ServiceRequestDetailController.php:27`, `CampaignDetailController.php:21`, `BrandDetailController.php:22`, `PartnersController.php:30` | نسختان متوازيتان لآلة الحالات نفسها. `SubscriptionService.php:29` وحده يكشف `canTransition` علنًا — وهو النمط الصحيح الذي لم تتبعه بقية الخدمات |
| **A3** | **انحراف A2 صار عطلًا حيًّا — التعاونات** | `CollaborationWorkflowService.php:25-26`: `'submitted' => ['approved','in_progress']`، `'approved' => ['completed']` — لا `cancelled` في أيّهما | `CollaborationDetailController.php:25` و`:26` يعرضان `['cancel','إلغاء','danger',false]` | زرّ «إلغاء» ظاهر في `submitted`/`approved`؛ الضغط عليه يصل `$wf->cancel()` (`CollaborationDetailController.php:67`) فيُرمى «انتقال غير مسموح» من `CollaborationWorkflowService.php:121` |
| A4 | أهلية انتقالات الفاتورة | `InvoiceService.php:23-30` (`ALLOWED`) + `assertTransition()` عند `:234` | `InvoicesController.php:99-104` — قراءة يدوية ثانية لنفس الجدول | تفعيل الأزرار يُصان يدويًّا بمعزل عن القاعدة |
| **A5** | **قاعدة اعتماد المحتوى لدى العميل** | `app/Domain/CRM/Support/ClientPortalAbilities.php:5-9` — كل قدرة ثابت مُسمّى؛ وكل متحكّمات بوابة العميل تستعمله: `Client/BrandController.php:24`, `Client/TeamController.php:28`, `Client/DocumentController.php:62`, `Client/AccountController.php:52`,`:176` | `Inertia/Client/ContentController.php:27-28`: `ClientPortalAbilities::can($role, MANAGE_BRANDS) \|\| in_array($role, ['client_content_reviewer','client_admin'], true)` | قاعدة اعتماد المحتوى الوحيدة في النظام تعيش كمصفوفة حرفية في متحكّم. لا ثابت `REVIEW_CONTENT` في `ClientPortalAbilities`، والدور `client_content_reviewer` لا يظهر في `app/Domain` إطلاقًا |

### 5.2 منطق خلفي أُعيد بناؤه في React/TypeScript

| # | المفهوم | المصدر الشرعي (PHP) | النسخة في TSX | الأثر |
|---|---|---|---|---|
| **B1** | **حساب الضريبة والإجماليات** | `InvoiceService.php:212-224` (`recalculate()`) — وحدات صحيحة دنيا، `intdiv($taxable * $invoice->tax_rate_bp, 10000)`، والنسبة عمود لكل فاتورة بافتراضي `1500` bp (`:44`) | `resources/js/Pages/Invoices/Index.tsx:80-84` — `const tax = afterDiscount * 0.15` بحساب عشري | يُثبّت نسبةً جعلها الخادم عمودًا متغيّرًا، ويحسب بالعشري حيث الخدمة صحيحة. `Pages/Invoices/Show.tsx:161` يقرأ `invoice.taxRateBp` من الخادم بشكل صحيح — فالشاشتان تتناقضان لأي فاتورة بنسبة غير 15% |
| B2 | قاموس تسميات الحالات | `lang/ar/statuses.php:20-35` (`'lead' => 'عميل محتمل'`, `'active' => 'نشِط'`…) مُقدَّم عبر `__("statuses.{$status}")` | `Pages/Clients/Index.tsx:25` (`lead: 'مهتم'`, `active: 'نشط'`)، `Pages/Creators/Index.tsx:28` (`prospect: 'مبدئي'`, `blocked: 'محظور'`) | متباعد فعلًا: نفس العميل يظهر «مهتم» في المرشّح و«عميل محتمل» في شارته على الصفحة نفسها |
| B3 | تنسيق المبالغ والأرقام | `resources/js/Components/ui.tsx:8-19` — `sarShort()` و`numFmt()` مُصدَّرتان | `Pages/Clients/Index.tsx:27-32`, `Contracts/Index.tsx:27-32`, `Collaborations/Index.tsx:27-32`, `Campaigns/Index.tsx:26-31` (`kfmt` متطابقة بايتًا)، و`Creators/Index.tsx:30-37`, `Creators/Show.tsx:35-40` (`fnum`/`sar`) | ليستا مكافئتين: `sarShort` تُقرّب دون كسور والنسخ المحلّية لا تفعل — فالمبالغ دون 1000 تظهر بكسور في صفحات ودونها في أخرى |
| B4 | تنقية معاملات الاستعلام | لا نسخة مشتركة في `resources/js/lib/` | `clean()` متطابقة في تسع صفحات: `Clients/Index.tsx:33-37`, `Brands/Index.tsx`, `Contracts/Index.tsx:33-37`, `Collaborations/Index.tsx:33-37`, `ServiceRequests/Index.tsx`, `Content/Index.tsx`, `Creators/Index.tsx`, `Campaigns/Index.tsx`, `Payouts/Index.tsx` | النصف العميل من عقد التصفية، ينحرف مستقلًّا عن قراءة الخادم |
| B5 | خريطة الحالة → اللون | `resources/views/components/status-badge.blade.php:1-9` — يحسم النبرة عبر `__('statuses.tone.'.$status)` ويُخرج `ih-status-{tone}` | `resources/js/Components/ui.tsx:21-32` — سجلّ `TONE` بقيم hex صريحة (`changes_requested: '#FFEDD5'/'#C2410C'`, `completed: '#D1FAE5'/'#047857'`) يتجاوز رموز `--ih-*` | النبرة تأتي من الخادم صحيحةً لكن حسم اللون انقسم: تغيير رمز CSS يُحدّث شارات Blade دون شارات React |

### 5.3 استعلامات مكرّرة بين المتحكّمات

| # | السطح | النسخة أ | النسخة ب | ملاحظة |
|---|---|---|---|---|
| C1 | الإشعارات | `Inertia/Client/NotificationController.php:19-73` | `Inertia/Creator/NotificationController.php:19-73` | `index()` وحارس `of()` ضد IDOR و`read()` و`readAll()` وشكل الصف و`paginate(20)` متطابقة بايتًا؛ الفرق مفتاح السمة (`activeClient` مقابل `creator`) واسم صفحة Inertia فقط. أي إصلاح لحارس IDOR يجب أن يُكتب مرتين |
| C2 | العقود | `Inertia/Client/ContractController.php:21-83` | `Inertia/Creator/ContractController.php:21-79` | نفس قائمة `VISIBLE` ونفس `paginate(15)->through(row())` ونفس `statusHistory->sortByDesc->take(12)`. **والأهم:** نسخة العميل تحرس التوقيع عند `Client/ContractController.php:66` (`role === 'client_admin'` — وهو بدوره حرف لا ثابت)، **ونسخة المبدع بلا حارس مكافئ** |
| C3 | طلبات الخدمة | `Inertia/Client/RequestController.php:22-145` | `Inertia/Partner/RequestController.php:22-124` | `index()` وإسقاط `comments()->where('is_internal', false)` وخريطة `statusHistory` و`requestOf()` و`row()` مكرّرة بتبديل `requester_type` والمفتاح الأجنبي فقط |

---

## 6. الأجزاء القديمة (dead / legacy)

### 6.1 مسارات تشير إلى صنف محذوف — عطل حيّ

`routes/web.php:497-498`:

```php
Route::get('/campaigns/{campaign}/deliverables/{deliverable}/suggest',  [\App\Http\Controllers\Web\CollaborationController::class, 'suggest']);
Route::post('/campaigns/{campaign}/deliverables/{deliverable}/offer', [\App\Http\Controllers\Web\CollaborationController::class, 'offerFromDeliverable']);
```

`App\Http\Controllers\Web\CollaborationController` **غير موجود**. `app/Http/Controllers/Web/`
يحوي ملفًا واحدًا: `PreviewCenterController.php`. والبحث في المستودع كله عن الاسم يعطي
هذين السطرين فقط.

**والأسوأ من كونه ميتًا:** البديل الحيّ مُسجَّل لاحقًا داخل المجموعة نفسها —
`routes/web.php:594-595` يربط `DeliverableMatchController::suggest` و`::offer` بنفس المسارين
بالضبط. ولأن Laravel يحسم بأوّل تطابق، فإن `/app/campaigns/{c}/deliverables/{d}/suggest`
يصل إلى الصنف المحذوف فيرمي خطأ 500، **ومسار React السليم محجوب لا يُبلَغ إطلاقًا**.
التعليق فوق السطرين («ما زالت Blade — لا مقابل React بعد») صار خاطئًا: المقابل مبنيّ
وموجود في `resources/js/Pages/Campaigns/Suggest.tsx`، ومحجوب بسطر ميت.

### 6.2 عرض يتيم

`resources/views/welcome.blade.php` — 278 سطرًا، **صفر مراجع** في `app/` و`routes/`
و`resources/`. الجذر `/` يذهب إلى `SiteController::home` (`routes/web.php:10`) الذي يُصيّر
`Pages/Public/Home.tsx`. الملف بقيّة هيكل Laravel الافتراضي بعد التعديل عليه.

### 6.3 تخطيطات ومكوّنات لا تخدم إلا الأكعُب

`client/layout.blade.php` و`creator/layout.blade.php` و`partner/layout.blade.php`
و`layouts/app.blade.php` بُنيت لبوابات Blade التي لم تعد موجودة. اليوم:

- الثلاثة الأولى تُوسَّع حصرًا من `*/not-available.blade.php` (3 و9 و3 أسطر).
- `layouts/app.blade.php` (83 سطرًا) لا يُوسَّع إلا من `dev/design-system` و`dev/preview-center`.

ومعها تبقى حيّةً مكوّنات `app-nav` (55 سطرًا) و`app-bottom-nav` (40) و`command-palette` (71)
— أي ملاحة كاملة بديلة لملاحة React في `resources/js/lib/nav.ts`، لا يراها مستخدم إلا في
صفحة «غير متاح».

`app/Providers/AppServiceProvider.php:45` ما زال يربط مُركِّب عرض بالأربعة
(`['layouts.app','client.layout','partner.layout','creator.layout']`) — استعلامات تُنفَّذ
لصفحات كعب.

### 6.4 `abs: true` في `nav.ts` — نظيف

**لا بقايا.** البحث عن `abs: true` في `resources/js/` يعطي صفر نتائج. الباقي هو تعريف الحقل
وحده في `resources/js/lib/nav.ts:11` مع تعليقه «مسار مطلق (ما زال على Blade)». الحقل
`abs?: boolean` صار ميتًا في الواجهة `NavItem` ويمكن حذفه.

لكن التعليق التوثيقي في `resources/js/lib/nav.ts:20-24` ما زال يقول «ما لم يُهاجَر بعد يشير
إلى Blade `/app`» — وصف لواقع انتهى.

### 6.5 تبعيات بلا مستهلك

| التبعية | المرجع | الحالة |
|---|---|---|
| `predis/predis ^3.5` | `composer.json:13` | لا سائق يستعملها (§2) |
| `alpinejs ^3.14.1` | `package.json:26` | تخدم `resources/js/app.js` الذي لا يُحمَّل إلا في صفحات الدخول وتدفّق `join` وأدوات dev |
| `CreateTenantNoteJob` | `app/Domain/Tenancy/Jobs/CreateTenantNoteJob.php` | «مثال» بلا مُطلِق إنتاجي (§3.1) |

---

## 7. الثغرات مقابل البنية المستهدفة

| البند المستهدَف | الواقع | الفجوة | الدليل |
|---|---|---|---|
| كل منطق العمل في Laravel | منطق مالي وصلاحيات وحالات مُعاد في المتحكّمات وفي TSX | **كبيرة** — أخطرها حساب الضريبة في `Invoices/Index.tsx:80-84` وقاعدة الاعتماد في `Client/ContentController.php:27-28` | §5 |
| PostgreSQL | مستعمَل فعلًا في `.env` و`.env.e2e` | صغيرة — `.env.example` و`config/database.php:20` يقولان `sqlite` | §1.2 |
| Redis للذاكرة والطابور | **غير مستعمَل إطلاقًا** — الثلاثة على قاعدة البيانات | **الفجوة الأكبر** — البند مُعلَن ولم يُنفَّذ منه شيء | §2 |
| Inertia + React + TS للواجهة | مُطبَّق على كل أسطح المنتَج تقريبًا | متوسطة — تدفّق انضمام المبدع بأكمله على Blade | §4.3 |
| Blade للمصادقة والبريد والصفحات البسيطة فقط | صحيح لـ 11 ملفًا؛ و6 صفحات منتَج تتجاوزه؛ و11 ملف تخطيط/مكوّن ميت | متوسطة | §4.3، §6.3 |
| سطح URL واحد لكل بوابة | بادئتان لكل بوابة (`/app`+`/beta`, `/client`+`/beta/client`…) و`MountPrefix` يُقنّن ذلك | **كبيرة** — و`/beta` نسخة ناقصة مفتوحة للمصادَق عليهم | §4.5 |
| العامل والمجدوِل يعملان | لا `Procfile` ولا supervisor ولا cron في المستودع | **كبيرة** — OTP وترحيل الملفات ومحرّك SLA بلا مستهلك خارج `composer dev` | §3.3 |
| عربية RTL أولًا | `config/app.php:81` و`.env.example` كلاهما `en` | متوسطة — و`.env` الفعلي فيه `APP_LOCALE` **مرّتين** (`en` ثم `ar`)؛ الأخير يفوز، لكنه هشّ. استنساخ نظيف يُنتج تطبيقًا إنجليزيًّا LTR لأن `HandleInertiaRequests.php:52` يشتقّ `dir` من اللغة | §1.1 |

---

## 8. خطة التصحيح (مرتّبة بالأولوية)

### أولوية 0 — أعطال حيّة

| # | البند | الملفات | الجهد |
|---|---|---|---|
| 0.1 | حذف السطرين الميتين اللذين يحجبان مسار React السليم ويرميان 500 | `routes/web.php:497-498` (يُبقى `:594-595`) | **ساعة** |
| 0.2 | إزالة زرّي «إلغاء» غير المسموحين، أو إضافة الحافة إلى `ALLOWED` — قرار منتَج لا تقني | `Inertia/CollaborationDetailController.php:25-26` مقابل `CollaborationWorkflowService.php:25-26` | **ساعة** |
| 0.3 | إضافة حارس صلاحية لتوقيع عقد المبدع (لا حارس اليوم مقابل حارس نسخة العميل) | `Inertia/Creator/ContractController.php` مقابل `Client/ContractController.php:66` | **ساعة** |
| 0.4 | نقل حساب الضريبة إلى الخادم وقراءة `taxRateBp` كما تفعل `Show.tsx:161` | `resources/js/Pages/Invoices/Index.tsx:80-84` | **ساعة** |

### أولوية 1 — قرارات بنيوية

| # | البند | الملفات | الجهد |
|---|---|---|---|
| 1.1 | **حسم Redis**: إمّا تفعيله (`CACHE_STORE`/`QUEUE_CONNECTION`/`SESSION_DRIVER=redis` + توحيد `REDIS_CLIENT=predis`) وإمّا شطبه من البنية المستهدفة وحذف `predis`. الحالة الراهنة — تبعية وتوثيق بلا استعمال — أسوأ من كلا الخيارين | `.env.example`, `config/{cache,queue,session}.php`, `composer.json:13` | **يوم** (التفعيل)، **ساعة** (الشطب) |
| 1.2 | تشغيل العامل والمجدوِل: `Procfile`/supervisor + `schedule:run` كل دقيقة، وتوثيقهما في `docs/DEPLOYMENT-GUIDE.md` | ملف نشر جديد + `docs/DEPLOYMENT-GUIDE.md` | **يوم** |
| 1.3 | إغلاق سطح `/beta`: إعادة توجيه 301 من كل بادئة `beta*` إلى مقابلها، ونقل `/beta/admin` إلى `/admin`، ثم تبسيط `MountPrefix` | `routes/web.php:282-479`, `app/Support/Http/MountPrefix.php:19-21` | **يوم** |
| 1.4 | إصلاح إعدادات `.env.example`: `pgsql` + `APP_LOCALE=ar`، وإزالة `APP_LOCALE` المكرّر من `.env` | `.env.example` | **ساعة** |

### أولوية 2 — إزالة الازدواج

| # | البند | الملفات | الجهد |
|---|---|---|---|
| 2.1 | كشف `canTransition(): array` علنًا في خدمات سير العمل الثماني (كما في `SubscriptionService.php:29`) وحذف خرائط `ACTIONS` من المتحكّمات الثمانية | 8 خدمات + 8 متحكّمات (§5.1-A2) | **أسبوع** |
| 2.2 | إضافة `REVIEW_CONTENT` إلى `ClientPortalAbilities` واستهلاكه بدل المصفوفة الحرفية | `app/Domain/CRM/Support/ClientPortalAbilities.php`, `Inertia/Client/ContentController.php:27-28` | **ساعة** |
| 2.3 | استهلاك `ContentItem::CREATOR_EDITABLE` بدل الحرفيات | `Inertia/Creator/ContentController.php:30,65` | **ساعة** |
| 2.4 | تجريد `NotificationController` و`ContractController` و`RequestController` إلى سِمة/صنف أساس بمعامل الطرف | 6 ملفات (§5.3) | **أسبوع** |
| 2.5 | توحيد `kfmt`/`fnum`/`sar` على `sarShort`/`numFmt`، ونقل `clean()` إلى `resources/js/lib/` | `Components/ui.tsx` + 9 صفحات (§5.2-B3,B4) | **يوم** |
| 2.6 | اشتقاق تسميات الحالات ونبراتها من الخادم بدل قواميس TSX، وتوحيد `TONE` على رموز `--ih-*` | `lang/ar/statuses.php`, `Components/ui.tsx:21-32`, `Clients/Index.tsx:25`, `Creators/Index.tsx:28` | **يوم** |

### أولوية 3 — تنظيف

| # | البند | الملفات | الجهد |
|---|---|---|---|
| 3.1 | نقل تدفّق انضمام المبدع إلى React ليطابق تدفّق الوكالة | `views/join/*.blade.php` (4)، `Public/JoinController.php`، صفحات React جديدة | **أسبوع** |
| 3.2 | نقل صفحتَي دعوة الشريك إلى React | `views/partner/accept-invite.blade.php`, `invite-invalid.blade.php`, `PartnerInvitationController.php` | **يوم** |
| 3.3 | حذف `welcome.blade.php` (يتيم، 278 سطرًا) | `resources/views/welcome.blade.php` | **ساعة** |
| 3.4 | استبدال الأكعُب الثلاثة بصفحة React واحدة، ثم حذف `client/layout`, `creator/layout`, `partner/layout`, `layouts/app`, `app-nav`, `app-bottom-nav`, `command-palette`، وتنظيف `AppServiceProvider.php:45` | 11 ملفًا (§6.3) | **يوم** |
| 3.5 | حذف الحقل الميت `abs?: boolean` وتصحيح التعليق الذي يحيل إلى Blade | `resources/js/lib/nav.ts:11,20-24` | **ساعة** |
| 3.6 | تقييم حذف `alpinejs` و`resources/js/app.js` بعد 3.1 و3.2 و3.4 | `package.json:26`, `vite.config.js:11` | **يوم** |
| 3.7 | نقل `CreateTenantNoteJob` إلى `tests/` أو حذفه | `app/Domain/Tenancy/Jobs/CreateTenantNoteJob.php` | **ساعة** |
| 3.8 | تنفيذ `TODO(Queue)` لإشعار دعوة عضو العميل (بعد 1.2) | `app/Domain/CRM/Actions/InviteClientMember.php:21` | **يوم** |

---

## 9. ما تم تنفيذه فعلًا

**لا شيء سوى هذا الملف.**

هذا التدقيق قراءة فقط. لم تُعدَّل ولم تُحذف ولم تُنشأ أي ملفات في `app/` أو `resources/` أو
`routes/` أو `database/` أو `tests/` أو `config/`. الإضافة الوحيدة هي
`docs/TECHNICAL-ARCHITECTURE-AUDIT.md`.

لم تُشغَّل `php artisan test` (قاعدة الاختبار مشتركة مع عمل متزامن). و`php artisan route:list`
لم يُنفَّذ لغياب `vendor/` في شجرة العمل — وكل ما ورد عن المسارات مقروء من `routes/web.php`
و`routes/api.php` مباشرةً.

الأعطال الموصوفة في §6.1 و§5.1-A3 و§5.3-C2 **ما زالت قائمة في الشيفرة** ولم تُصلَح ضمن هذا التدقيق.
