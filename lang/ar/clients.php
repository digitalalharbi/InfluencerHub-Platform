<?php

/** قائمة العملاء — عربي. سطح كامل الترجمة: عميل + خادم (بلا التصدير المنفصل). */
return [
    'title' => 'العملاء',
    'eyebrow' => 'إدارة العلاقات',
    'sub' => 'حسابات العملاء وملفاتهم وحملاتهم ومتابعتهم المالية في بيئة تشغيل موحّدة',
    'new_client' => 'عميل جديد',

    // مؤشرات
    'kpi_revenue' => 'الإيراد الكلي',
    'kpi_revenue_sub' => ':vip عميل VIP',
    'kpi_active_campaigns' => 'الحملات الجارية',
    'kpi_active_campaigns_sub' => ':n عميل نشط الحملات',
    'kpi_pending_payouts' => 'مستحقات معلّقة',
    'kpi_pending_payouts_sub' => 'بانتظار الاعتماد أو الصرف',
    'kpi_completion' => 'اكتمال الملفات',

    // شرائح
    'seg_all' => 'الكل',
    'seg_active' => 'نشط',
    'seg_inactive' => 'غير نشط',
    'seg_complete' => 'مكتمل الملف',
    'seg_incomplete' => 'غير مكتمل',
    'seg_vip' => 'VIP',
    'seg_needs_action' => 'يحتاج إجراءً',
    'seg_with_active_campaigns' => 'لديه حملات نشطة',

    // بحث/فلترة
    'search_placeholder' => 'ابحث بالاسم أو السجل…',
    'all_statuses' => 'كل الحالات',
    'all_sectors' => 'كل القطاعات',
    'all_managers' => 'كل المدراء',

    // حالات فارغة
    'empty_filtered_title' => 'لا عملاء مطابقون',
    'empty_filtered_text' => 'لا نتائج للبحث أو الفلاتر الحالية.',
    'clear_filters' => 'مسح الفلاتر',
    'empty_title' => 'ابدأ بإضافة أول عميل',
    'empty_text' => 'أنشئ ملف عميل لتتابع علاماته وحملاته ومستحقاته من مكان واحد.',

    // جدول/بطاقات
    'th_client' => 'العميل',
    'th_sector' => 'القطاع',
    'th_brands' => 'العلامات',
    'th_manager' => 'مدير الحساب',
    'th_campaigns' => 'الحملات',
    'th_completion' => 'اكتمال الملف',
    'th_status' => 'الحالة',
    'th_revenue' => 'الإيراد',
    'active_count' => ':n نشطة',
    'open' => 'فتح',
    'count_item' => ':n عميل',
    'filtered_suffix' => ' · مُرشَّح',
    'm_active_campaigns' => 'حملة نشطة',
    'm_revenue' => 'الإيراد',
    'm_completion' => 'اكتمال',
    'needs_action_note' => ':n عنصر يحتاج إجراءً',
    'needs_action_tooltip' => 'عناصر تحتاج إجراءً',

    // نافذة الإنشاء
    'f_name' => 'اسم العميل',
    'f_type' => 'النوع',
    'f_status' => 'الحالة',
    'f_sector' => 'القطاع',
    'f_email' => 'البريد',
    'create' => 'إنشاء العميل',
    'cancel' => 'إلغاء',

    // أنواع العميل
    'type_company' => 'شركة',
    'type_brand_owner' => 'مالك علامة',
    'type_government' => 'جهة حكومية',
    'type_nonprofit' => 'غير ربحية',
    'type_agency' => 'وكالة',
    'type_individual' => 'فرد',
    'type_other' => 'أخرى',

    // حالات العميل (خادم c.statusLabel + فلتر/نافذة)
    's_lead' => 'مهتم',
    's_qualified' => 'مؤهّل',
    's_active' => 'نشط',
    's_inactive' => 'غير نشط',
    's_suspended' => 'موقوف',
    's_archived' => 'مؤرشف',
];
