# InfluencerHub — Information Architecture

النظام منصّة SaaS لإدارة علاقات المؤثرين وصنّاع المحتوى + اكتشاف/تحليل الناشرين + تشغيل الحملات من الطلب حتى المستحق.

## القائمة الرئيسية (حسب رحلة العمل، مُصفّاة بالدور/الصلاحية)

المجموعات تظهر فقط إن كان فيها عنصر واحد على الأقل متاح للمستخدم. لا مجموعة فارغة، ولا عنصر غير متاح ظاهر.

### العمل (Work)
- لوحة التحكم — `/beta`
- الطلبات — `/beta/service-requests` (badge: service_requests)
- مهامي — `/beta/my-tasks`
- التقويم — `/beta/calendar`

### العلاقات (Relationships)
- العملاء — `/beta/clients`
- العلامات — `/beta/brands` (badge: brand_reviews)
- المؤثرون — `/beta/creators?type=influencer`
- صنّاع المحتوى — `/beta/creators?type=ugc_creator`
- الناشرون — `/beta/publishers`  ← منصّة ذكاء/اكتشاف

### الحملات (Campaigns)
- الحملات — `/beta/campaigns`
- الترشيحات — `/beta/shortlisting`  ← وحدة مركزية
- التعاونات — `/beta/collaborations`
- المحتوى — `/beta/content` (badge: content)
- العقود — `/beta/contracts`

### المالية (Finance)
- الفواتير — `/beta/invoices` (عند توفّر الوحدة)
- المدفوعات — `/beta/payments` (عند توفّر الوحدة)
- المستحقات — `/beta/payouts`

### الذكاء (Intelligence)
- تحليل الناشرين — `/beta/publishers` (نفس مساحة الناشرين، منظور تحليلي)
- التقارير — `/beta/reports`
- التحليلات — `/beta/analytics`
- التكاملات — `/beta/integrations`

### الإدارة (Admin)
- مراجعة العلامات — `/beta/brands` reviews (badge)
- مراجعات العملاء — `/beta/client-reviews` (badge: client_reviews)
- الفريق — `/beta/settings` (قسم الفريق)
- الاشتراك — `/beta/settings`
- الإعدادات — `/beta/settings`

## الفصل الوظيفي: المؤثرون vs الناشرون vs صنّاع المحتوى

- **المؤثرون / صنّاع المحتوى (CRM):** أشخاص مسجّلون/متعامَل معهم — ملف، تواصل، منصّات، خدمات/أسعار، عقود، حملات، محتوى، مستحقات، مهام، جودة تعاون، جاهزية ملف. المصدر: قاعدة البيانات الداخلية.
- **الناشرون (Intelligence):** اكتشاف/تحليل حسابات المنصّات حتى قبل التسجيل في CRM — بحث، هوية رقمية، جمهور، نمو، تفاعل، أنواع محتوى، علامات تعاون معها، فئات، مدن/لغة، مؤشرات جودة/موثوقية، مقارنة، حفظ في قائمة، **تحويل إلى مؤثر** دون تكرار، إضافة إلى ترشيح، ربط بحملة، مصدر البيانات + آخر تحديث.

## سلسلة الربط (بلا إعادة إدخال أو تكرار)
الناشرون → (حفظ / تحويل إلى مؤثر) → الترشيح → اعتماد العميل → التعاون → الحملة → المحتوى → العقد → المستحق.

`Publisher.convertToInfluencer()` يربط `publisher_id` على سجل Creator الناتج (idempotent) لمنع التكرار.

## Publisher Intelligence Architecture
- مبنية فوق `PlatformRegistry` الحالي عبر `PublisherConnector` (Adapter لكل منصّة رسمية مسموح بها: Snapchat/TikTok/Instagram-Meta/YouTube/X/LinkedIn).
- كل Connector يصرّح: القدرات، البيانات القابلة للجلب، الصلاحيات، آخر مزامنة، معدّل التحديث، الأخطاء، حدود الاستخدام، مصدر كل معلومة (live/import/manual)، والحالة (Connected/Manual/Import/Sandbox/Waiting for Credentials/Waiting for Approval/Limited/Degraded/Unavailable).
- **لا أرقام أو تحليلات وهمية.** عند غياب الاعتماد: بنية + واجهة + اختبارات على Sandbox صادق، والعائق موثّق في `docs/EXTERNAL-BLOCKERS.md`.

## بوابة القبول لإعادة الهيكلة (قبل أي `/app` cutover)
قائمة مختصرة مفهومة · فصل واضح مؤثرون/ناشرون/صنّاع · الترشيحات مركزية · روابط الوحدات بلا تكرار بيانات · صلاحيات من الخادم · Desktop+Mobile · RTL+LTR · لا مفاتيح خام · لا وحدات غير متاحة ظاهرة · لا تكاملات وهمية · بحث/تنقّل يعمل · اختبارات كاملة · لقطات مُراجَعة · تصميم أصلي · Git نظيف.
