# تقرير التحقق من النظام القديم (فحص برمجي فعلي)

المصدر (للقراءة فقط، لم يُعدَّل): `~/Desktop/smartcode-laravel-work`
تاريخ الفحص: 2026-07-16 · الطريقة: فحص ملفات فعلي (ls/grep/find)، لا اعتماد على الذاكرة.

## 1. الواجهة الأمامية
- **35 صفحة HTML** في `frontend/*.html` (مطابقة في `backend/public/*.html`).
- الأصول البصرية لإعادة الاستخدام: `frontend/shared/design.css` + `frontend/shared/icons.js` (+ الخطوط/الثيم داخلها).
- **localStorage/IndexedDB (تشغيلي):** getItem×75، setItem×63، removeItem×23، indexedDB×7 (في `frontend/shared/*.js`).
- **32 مجموعة تشغيلية** عبر `makeEntity(...)` في `frontend/shared/api.js`:
  customers, influencers, campaigns, transfers, daily_ads, ad_tasks, influencer_notifications, ugc_creators, ugc_applications, ugc_submissions, ugc_transactions, ugc_packages, ugc_campaigns, ugc_notifications, content, whatsapp_numbers, whatsapp_templates, whatsapp_conversations, whatsapp_messages, whatsapp_broadcasts, whatsapp_automations, team, campaign_nominations, calendar_events, campaign_tasks, campaign_timeline, approval_tokens, campaign_documents, requests, request_users, request_timeline, request_messages.

## 2. الخلفية (Backend) — الموجود فعليًا
- **19 migration** في `backend/database/migrations/` (قائمة فعلية):
  laravel_support_tables, roles_permissions_tables, users, customers, influencers, campaigns, ads_tables, transfers_tables, tasks_notifications_tables, contents_whatsapp_tables, whatsapp_business_tables, saas_tables, requests_tables, audit_logs, add_agency_id_to_existing_tables, add_security_columns_to_users, integration_tables, add_google_id_to_users, align_customers_with_frontend_model.
- **21 Controller** في `backend/app/Http/Controllers/Api/V1/`:
  Agency, Analytics, Auth, Campaign, Content, Customer, DailyAd, Dashboard, Health, Influencer, Integration, Notification, Portal, Request, RequestUser, Search, Task, Transfer, User, WhatsApp, WhatsAppWebhook.
- **40 Model** في `backend/app/Models/`.
- **86 نقطة نهاية API** في `backend/routes/api.php`.

## 3. الوحدات ذات Backend فعلي (مصدرها PostgreSQL في القديم)
customers، influencers، campaigns، transfers (مالية جزئية)، tasks، content، requests، request_users، users، agencies (organizations)، notifications، analytics، dashboard، integrations (Mock)، whatsapp (جزئي)، audit_logs.

## 4. الوحدات الشكلية/بلا Backend كامل (localStorage فقط — تُبنى في V2)
ugc_* (creators/applications/submissions/transactions/packages/campaigns/notifications)، calendar_events، campaign_tasks، campaign_timeline، campaign_documents، approval_tokens، request_timeline، request_messages، ad_tasks، influencer_notifications، whatsapp_numbers/messages/automations.

## 5. حقول تحتاج ترحيلًا (عيّنات فعلية من migrations القديمة)
- customers: name, brand, coordinator, cr_number, vat_number, activity_type, is_vat_registered, is_complete, kyc_documents (من `align_customers_with_frontend_model`).
- influencers: platform, followers, category, rating, cost_price, sale_price, iban, social_links.
- transfers: direction, amount_base, vat, amount_total, recipients.
- users: username, role, agency_id, must_change_password, two_factor_*, google_id.

## 6. وظائف لا يجوز فقدها
مصادقة JWT، عزل المؤسسات (AgencyScope fail-closed)، دورة الطلبات/الترشيحات، تحويلات مالية + audit، health/queues/scheduler، طبقة تكاملات + Mock، Google OAuth (Socialite)، بوابة خارجية (PortalController).

## 7. التصميم المُعاد استخدامه (بلا تغيير بصري)
`design.css` (نظام الألوان/الثيم/RTL)، `icons.js` (الأيقونات)، بنية الصفحات (sidebar/topbar/content). ستُحوَّل إلى Blade Components في Phase 17 دون تغيير الهوية.

## 8. قرار الترحيل
لا ترحيل مباشر لطبقة localStorage/IndexedDB. الترحيل عبر CSV/Excel/DB export بأوامر `legacy:*` (Dry-run + validation + rollback) في Phase 14.
