<?php

/**
 * تفاصيل طلب الخدمة (Show) + تسميات الأولوية والنوع — عربي، المصدر الافتراضي.
 * القيم مطابقة حرفيًّا للنصوص التي كانت مضمّنة في الصفحة/التعدادات حتى لا تتغيّر
 * اللغة الافتراضية؛ الإنجليزية مرآةٌ في en/service_requests.php.
 */
return [
    // صفحة التفصيل (Show)
    'show_heading' => 'طلب',
    'show_eyebrow' => 'طلب · :num',
    'back_all' => 'كل الطلبات',
    'm_client' => 'العميل',
    'm_type' => 'النوع',
    'm_assignee' => 'المُسنَد',
    'unassigned' => 'غير مسند',
    'm_due' => 'الاستحقاق',
    'open_campaign' => 'افتح الحملة :num',
    'convert_to_campaign' => 'تحويل إلى حملة',

    // موجز الحملة
    'brief_title' => 'موجز الحملة — ينتقل إلى الحملة عند التحويل',
    'brief_brand' => 'العلامة',
    'brief_budget' => 'الميزانية',
    'brief_start' => 'البداية المفضّلة',
    'brief_end' => 'النهاية المفضّلة',
    'brief_platforms' => 'المنصّات المطلوبة',
    'brief_scope' => 'نطاق العمل',
    'brief_moved' => 'نُقل هذا الموجز إلى الحملة بالفعل.',
    'brief_will_move' => 'سينتقل هذا كلّه تلقائيًا إلى الحملة — لا يُعاد إدخاله.',

    // شريط الملخّص
    'ss_priority' => 'الأولوية',
    'ss_created' => 'أُنشئ',
    'ss_resolved' => 'أُنجز',

    // SLA (:n ساعات)
    'sla_overdue' => 'متأخر :nس',
    'sla_soon' => 'خلال :nس',
    'sla_ok' => ':nس متبقية',

    // الأقسام
    'sec_details' => 'تفاصيل الطلب',
    'no_description' => 'لا وصف.',
    'sec_comments' => 'التعليقات',
    'sec_comments_n' => 'التعليقات (:n)',
    'comment_placeholder' => 'أضِف تعليقًا داخليًا…',
    'add' => 'إضافة',
    'no_comments' => 'لا تعليقات بعد.',
    'internal_tag' => 'داخلي',
    'sec_assign' => 'الإسناد',
    'choose_member' => '— اختر عضوًا —',
    'assign_btn' => 'إسناد',
    'sec_history' => 'سجل الحالة',
    'no_history' => 'لا سجل بعد.',

    // نافذة السبب
    'reason_placeholder' => 'السبب / الملاحظة',
    'confirm' => 'تأكيد',
    'cancel' => 'إلغاء',

    // تسميات إجراءات سير العمل
    'act_triage' => 'بدء الفرز',
    'act_cancel' => 'إلغاء',
    'act_start' => 'بدء التنفيذ',
    'act_request_info' => 'طلب معلومة',
    'act_resolve' => 'إنجاز الطلب',
    'act_resume' => 'استئناف التنفيذ',
    'act_close' => 'إغلاق الطلب',
    'act_reopen' => 'إعادة الفتح',

    // الأولوية (ServiceRequestPriority)
    'prio_low' => 'منخفضة',
    'prio_normal' => 'عادية',
    'prio_high' => 'عالية',
    'prio_urgent' => 'عاجلة',

    // النوع (ServiceRequestType)
    'type_campaign' => 'حملة',
    'type_content' => 'محتوى',
    'type_report' => 'تقرير',
    'type_consultation' => 'استشارة',
    'type_other' => 'أخرى',
];
