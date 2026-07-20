<?php

/**
 * مسميات التنقّل (عربي) — عناوين المجموعات + تسميات عناصر القوائم.
 * مصدر واحد للحقيقة؛ config/navigation.php يشير لهذه المفاتيح.
 * قاعدة المصطلحات: تسمية الكيان مباشرة (العملاء) لا وصف الفعل (إدارة العملاء).
 */
return [
    // ==== عناوين المجموعات ====
    'groups' => [
        'overview'      => 'الرئيسية',
        'relationships' => 'العلاقات',
        'creators'      => 'المبدعون',
        'operations'    => 'التشغيل',
        'reviews'       => 'المراجعات والامتثال',
        'finance'       => 'المالية',
        'insights'      => 'البيانات والتقارير',
        'admin'         => 'الإدارة',
        'account'       => 'الحساب',
    ],

    // ==== عناصر القوائم ====
    'items' => [
        'dashboard'          => 'لوحة التحكم',
        'clients'            => 'العملاء',
        'brands'             => 'العلامات التجارية',
        'partner_agencies'   => 'الوكالات الشريكة',
        'influencers'        => 'المؤثرون',
        'ugc_creators'       => 'صنّاع المحتوى',
        'all_creators'       => 'كل المبدعين',
        'creator_applications' => 'طلبات الانضمام',
        'service_requests'   => 'طلبات الخدمة',
        'campaigns'          => 'الحملات',
        'collaborations'     => 'التعاونات',
        'content'            => 'المحتوى والموافقات',
        'contracts'          => 'العقود',
        'brand_reviews'      => 'مراجعة العلامات',
        'client_reviews'     => 'مراجعات العملاء',
        'payouts'            => 'المستحقات',
        'reports'            => 'التقارير',
        'platforms' => 'المنصّات والتكاملات',
        'settings'           => 'الإعدادات',
        'preview_center'     => 'مركز المعاينة',
        'design_system'      => 'نظام التصميم',
        'logout'             => 'تسجيل الخروج',

        // بوابة العميل
        'client_home'        => 'الرئيسية',
        'client_profile'     => 'ملف العميل',
        'client_addresses'   => 'العناوين',
        'client_documents'   => 'المستندات',
        'client_brands'      => 'العلامات',
        'client_team'        => 'الفريق',
        'client_requests'    => 'طلبات الخدمة',
        'client_campaigns'   => 'الحملات',
        'client_content'     => 'المحتوى والموافقات',
        'client_contracts'   => 'العقود',
        'client_notifications' => 'الإشعارات',
        'client_billing'     => 'الملف المالي',
        'client_settings'    => 'الإعدادات',

        // بوابة المبدع
        'creator_home'       => 'الرئيسية',
        'creator_profile'    => 'ملفي',
        'creator_collaborations' => 'تعاوناتي',
        'creator_content'    => 'محتواي',
        'creator_payouts'    => 'مستحقاتي',

        // بوابة الشريك
        'partner_home'       => 'الرئيسية',
        'partner_requests'   => 'طلبات الخدمة',
        'partner_clients'    => 'العملاء',
        'partner_briefs'     => 'البريفات',
        'partner_content'    => 'المحتوى',
        'partner_reports'    => 'التقارير',
        'partner_team'       => 'الفريق',
        'partner_settings'   => 'الإعدادات',
    ],

    // نص مساعد اختياري لكل عنصر (وصف موجز يظهر كتلميح)
    'descriptions' => [
        'clients'          => 'حسابات العملاء وملفاتهم وفرقهم',
        'brands'           => 'العلامات التجارية التابعة للعملاء',
        'service_requests' => 'الطلبات الواردة وفرزها وإسنادها ومهل الاستجابة',
        'campaigns'        => 'الحملات ومخرجاتها وميزانياتها',
        'content'          => 'مراجعة المحتوى واعتماده قبل النشر',
        'brand_reviews'    => 'اعتماد العلامات المُرسلة من العملاء',
        'client_reviews'   => 'مراجعة تغييرات ملفات العملاء والمستندات',
        'payouts'          => 'مستحقات المبدعين والمدفوعات',
        'reports'          => 'مؤشرات الأداء والتجميعات',
    ],
];
