<?php
/*
| InfluencerHub — إعدادات المنتج. لا يُربط منطق النظام بنطاق ثابت في الكود.
| DEPLOYMENT_MODE: saas | dedicated | self_hosted
*/
return [
    // ---- هوية المنتج القانونية الوحيدة (مصدر واحد لكل واجهة/مستند/بريد) ----
    'product_name' => 'InfluencerHub',                       // العلامة اللاتينية القانونية — تُستعمل كما هي حتى في الواجهة العربية
    'tagline' => 'منصة إدارة حملات المؤثرين وصناع المحتوى',
    // رابط المنتج العام الحقيقي — مستقلّ عن APP_URL (قد يكون نطاقًا فرعيًّا للتوجيه).
    // كل الروابط في المستندات/البريد تُبنى من هنا، لا من localhost.
    'url' => rtrim(env('PRODUCT_URL', 'https://influencerhub.io'), '/'),
    'domain' => env('PRODUCT_DOMAIN', 'influencerhub.io'),
    'locale' => 'ar',
    'timezone' => 'Asia/Riyadh',                             // لعرض التواريخ في سياق المنتج (التخزين UTC)
    'info_path' => '/info',                                  // صفحة «عن InfluencerHub» العامة

    // البريد — المُرسِل الفعليّ يأتي من البيئة/المزوّد المُتحقَّق؛ هذه أسماء الأدوار فقط.
    'mail' => [
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@influencerhub.io'),
        'from_name' => env('MAIL_FROM_NAME', 'InfluencerHub'),
    ],

    // الدعم — لا نُعلن قناة غير مُتحقَّقة كأنها عاملة. تبقى null حتى تُهيَّأ فعلًا.
    // العرض البديل: «زيارة influencerhub.io» حتى توجد قناة دعم مُتحقَّقة.
    'support' => [
        'email' => env('SUPPORT_EMAIL'),                     // null ما لم يُهيَّأ صندوق حقيقي
        'url' => env('PRODUCT_URL', 'https://influencerhub.io'),
    ],

    'deployment_mode' => env('DEPLOYMENT_MODE', 'saas'),
    'is_saas' => env('DEPLOYMENT_MODE', 'saas') === 'saas',
    'is_dedicated' => env('DEPLOYMENT_MODE') === 'dedicated',
    'is_self_hosted' => env('DEPLOYMENT_MODE') === 'self_hosted',
    'self_hosted_entitlements' => [], // فارغ = غير محدود في self_hosted
];
