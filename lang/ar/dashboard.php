<?php

/** لوحة تشغيل الوكالة (عربي) — نصوص العميل والخادم لسطح واحد كامل الترجمة. */
return [
    // العنوان والترويسة
    'title' => 'لوحة التحكم',
    'eyebrow' => 'مساحة عملي',
    'greeting' => 'مرحبًا، :name',
    'sub' => ':workspace · مركز عملك اليومي والمتابعة',
    'workspace_fallback' => 'وكالتك',
    'requests' => 'الطلبات',

    // إجراءات سريعة
    'qa_publishers' => 'اكتشف ناشرين',
    'qa_shortlist' => 'ابدأ ترشيحًا',
    'qa_new_campaign' => 'حملة جديدة',
    'qa_creators' => 'صناع المحتوى',

    // الملخّص اليومي
    'brief_eyebrow' => 'الملخّص اليومي',
    'brief_tasks' => ':n مهمة',
    'brief_approvals' => ':n بانتظار موافقتك',
    'brief_overdue' => ':n متأخر/حرج',
    'brief_today' => 'لديك اليوم: :parts.',
    'brief_setup' => 'مساحتك جاهزة للبدء — التالي: :next.',
    'brief_clear' => 'لا مهام معلّقة تحتاج تدخّلك الآن — كل شيء تحت السيطرة.',
    'brief_first_client' => 'أضِف أوّل عميل',
    'start_work' => 'ابدأ العمل',

    // لوحة القيادة المرئية
    'insight_urgent' => 'عاجل',
    'insight_approval' => 'بانتظار موافقتك',
    'insight_upcoming' => 'قادم',
    'insight_required' => 'مطلوب',
    'insight_no_tasks' => 'لا مهام معلّقة',
    'insight_by_priority' => 'المطلوب حسب الأولوية',
    'insight_avg_completion' => 'متوسط الإكمال',

    // مؤشرات
    'kpi_clients' => 'العملاء',
    'kpi_clients_sub' => ':n حملة نشطة',
    'kpi_revenue' => 'الإيراد (تقديري)',
    'kpi_margin' => 'هامش',
    'kpi_profit' => 'ربح',
    'kpi_pending_payouts' => 'مستحقات معلّقة',
    'kpi_pending_payouts_sub' => ':n دفعة بانتظار الإجراء',
    'kpi_creators' => 'المبدعون',
    'kpi_creators_sub' => ':verified موثّق · :tierA فئة A',

    // قائمة التهيئة
    'setup_title' => 'لنُجهّز مساحتك',
    'setup_progress' => ':done من :total',
    'setup_optional' => 'اختياري',

    // الأقسام
    'my_work' => 'المطلوب مني الآن',
    'my_work_empty_title' => 'لا شيء عاجل الآن',
    'my_work_empty_text' => 'لا مهام أو موافقات معلّقة ضمن صلاحياتك.',
    'campaigns_follow' => 'حملات تحتاج متابعة',
    'all_campaigns' => 'كل الحملات ←',
    'late' => 'متأخرة',
    'campaign_meta' => ':creators مبدع · :deliverables مخرج',
    'team_follow' => 'متابعة الفريق',
    'team_unassigned' => 'غير مُسنَد:',
    'team_sla' => 'تجاوز SLA:',
    'team_empty' => 'لا أعمال مسندة حاليًا.',
    'top_clients' => 'أبرز العملاء',
    'all' => 'الكل ←',
    'client_meta' => ':campaigns حملة نشطة · :brands علامة',
    'vip' => 'VIP',

    // الخادم — عناصر العمل
    'entity_service_request' => 'طلب خدمة · :number',
    'entity_queue' => 'طابور تشغيلي',
    'sr_title_fallback' => 'طلب :number',
    'sr_reason_sla' => 'تجاوز مهلة SLA',
    'sr_reason_assigned' => 'طلب مسند إليك',
    'sr_action' => 'فتح الطلب',

    // طوابير العمل (عنوان/سبب/إجراء)
    'g_content_title' => 'محتوى بانتظار مراجعتك',
    'g_content_reason' => ':n عنصر مُرسَل للوكالة',
    'g_content_action' => 'راجع المحتوى',
    'g_brands_title' => 'علامات بانتظار الاعتماد',
    'g_brands_reason' => ':n علامة مُرسَلة',
    'g_brands_action' => 'اعتماد العلامات',
    'g_client_reviews_title' => 'مراجعات العملاء',
    'g_client_reviews_reason' => ':n تغيير/مستند بانتظار المراجعة',
    'g_client_reviews_action' => 'مراجعة',
    'g_payouts_title' => 'مستحقات بانتظار الاعتماد',
    'g_payouts_reason' => ':n دفعة تحتاج اعتمادك',
    'g_payouts_action' => 'اعتماد الصرف',
    'g_applications_title' => 'طلبات انضمام المبدعين',
    'g_applications_reason' => ':n طلب جديد',
    'g_applications_action' => 'مراجعة الطلبات',
    'g_late_title' => 'حملات متأخرة عن الموعد',
    'g_late_reason' => ':n حملة تجاوزت تاريخ الانتهاء',
    'g_late_action' => 'عرض الحملات',
    'g_nom_alt_title' => 'العميل طلب بديلًا',
    'g_nom_alt_reason' => ':n قائمة ترشيح يطلب العميل لها بديلًا',
    'g_nom_alt_action' => 'راجع الطلب',
    'g_nom_convert_title' => 'معتمَدون جاهزون للتنفيذ',
    'g_nom_convert_reason' => ':n قائمة معتمدة بانتظار تحويل المعتمَدين',
    'g_nom_convert_action' => 'تحويل للتنفيذ',
    'g_nom_send_title' => 'قوائم ترشيح جاهزة للإرسال',
    'g_nom_send_reason' => ':n قائمة فيها مرشّحون بانتظار الإرسال للعميل',
    'g_nom_send_action' => 'مراجعة القوائم',

    // أولويات
    'prio_overdue' => 'متأخر',
    'prio_critical' => 'حرج',
    'prio_today' => 'مستحق اليوم',
    'prio_approval' => 'بانتظار موافقتك',
    'prio_soon' => 'مستحق قريبًا',
    'prio_normal' => 'متابعة',

    // أدوار الفريق
    'role_agency_admin' => 'مدير الوكالة',
    'role_operations_manager' => 'مدير العمليات',
    'role_campaign_manager' => 'مدير حملات',
    'role_creator_manager' => 'مسؤول مبدعين',
    'role_content_reviewer' => 'مراجع محتوى',
    'role_finance' => 'مالية',
    'role_agency_employee' => 'موظف',
    'role_super_admin' => 'مدير عام',
];
