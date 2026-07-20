# جرد صفحات النظام القديم (35 صفحة)

| الصفحة | الوحدة | مصدر البيانات (قديم) | ملاحظة V2 |
|---|---|---|---|
| login | المصادقة | Backend (JWT) | يُعاد بناؤه Blade+Alpine |
| dashboard | لوحة مؤشرات | localStorage | أرقام من PostgreSQL |
| customers / customer-add / customer-detail | العملاء | Backend (مربوط) | Domain: CRM |
| influencers / influencer-add / influencer-detail / influencer-portal / influencer-booking | المؤثرون | localStorage | Domain: Creators + بوابة |
| campaigns? / campaign-detail / campaign-approval | الحملات | localStorage | Domain: Campaigns |
| orders-campaigns | الطلبات/الحملات | localStorage | Requests + Campaigns |
| requests / request-detail / requests-users / requests-portal | الطلبات | localStorage(+flag) | Domain: Requests + بوابة |
| client-approval | موافقة العميل | localStorage | Collaborations |
| tasks / calendar | المهام/التقويم | localStorage | Domain: Collaborations |
| content | المحتوى | localStorage | Domain: Content |
| finance / transfer-request / transfer-detail | المالية | Backend جزئي | Domain: Finance + Ledger |
| document | مستند/عقد | localStorage | Domain: Contracts |
| analytics / monthly-report | التحليلات | localStorage | Domain: Analytics/Reporting |
| ugc-admin / ugc-portal | UGC | localStorage (لا backend) | Domain: Creators (UGC) |
| whatsapp | التواصل | Backend جزئي | Domain: Communications |
| publishers / publisher-detail | الناشرون | localStorage | Creators |
| settings | الإعدادات/المستخدمون/التكاملات | Backend جزئي (مربوط) | Identity/Tenancy/Integrations |
| index / 404 | تحويل/خطأ | — | layout |

**التصميم/الأصول:** يُعاد استخدام CSS (`shared/design.css`) والهوية والأيقونات (`shared/icons.js`) والخطوط دون تغيير بصري.
