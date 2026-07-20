# تدقيق النظام القديم (Legacy) — للقراءة فقط

المصدر: `~/Desktop/smartcode-laravel-work` (Smart Code v5). لا يُعدَّل. مرجع تصميم/وظائف فقط.

## البنية
- **Backend:** Laravel (رُقّي إلى 12.64)، PostgreSQL، Redis، JWT (tymon)، 19 Controller، 34 Model، multi-tenant عبر `AgencyScope` (fail-closed) + `BelongsToAgency` + `SetCurrentAgency`.
- **Frontend:** 35 صفحة HTML ثابتة (RTL عربي)، `window.SC` namespace، طبقة بيانات `shared/data.js` (localStorage/IndexedDB)، منطق `shared/api.js` (~5000 سطر) على localStorage. جسر backend `shared/modules/` (customers/users مربوطة فعليًا).
- **الاختبارات:** 49 Feature test (PHPUnit) + Playwright.

## ما يعمل فعليًا (Backend + مُختبَر)
JWT auth، RBAC، عزل مؤسسات، customers CRUD (مربوط UI)، users (مربوط UI)، organizations backend (AgencyController)، finance transfers + audit، queues/worker/scheduler، health، طبقة تكاملات (Mock)، Google OAuth (Socialite، feature-flag).

## شكلي/محلي (localStorage — يحتاج إعادة بناء)
معظم صفحات الواجهة (campaigns، tasks، content، requests، analytics، whatsapp، ugc، إلخ) تعمل على localStorage كمصدر تشغيلي؛ أرقام Dashboard محلية.

## بلا Backend في القديم (يُبنى في V2)
ugc_*، calendar_events، approval_tokens، contracts كاملة، campaign sub-collections، webhooks infra، billing/subscriptions/entitlements، double-entry ledger، automation engine، analytics pipeline، attribution.

## قرار V2
إعادة بناء نظيفة على Laravel 12 + Modular Monolith (Domains)، إعادة استخدام التصميم/HTML/CSS فقط، PostgreSQL مصدر الحقيقة الوحيد، بلا localStorage تشغيلي/Demo/Mock (عدا تكاملات غير مفعّلة موسومة).
