<?php

/** قائمة المستحقات — عربي. سطح كامل الترجمة (الحالة من statuses المشتركة). */
return [
    'title' => 'المستحقات',
    'eyebrow' => 'المالية',
    'sub' => 'مستحقات المبدعين: اعتماد، جدولة، وتسجيل الصرف — النظام لا ينفّذ تحويلات (تسجيل يدوي)',
    'new_payout' => 'مستحق جديد',

    // مؤشرات
    'kpi_open' => 'مستحق مفتوح',
    'kpi_open_sub' => ':n دفعة',
    'kpi_ready' => 'جاهز للصرف',
    'kpi_ready_sub' => ':n معتمدة/مجدولة',
    'kpi_paid' => 'مدفوع',
    'kpi_paid_sub' => ':n دفعة',
    'kpi_waiting' => 'بانتظار المزوّد',
    'kpi_waiting_sub' => ':n فاشلة',

    // نظرة الصرف (دونات المراحل)
    'stage_in_process' => 'قيد الإجراء',
    'stage_ready' => 'جاهز للصرف',
    'stage_paid' => 'مدفوع',
    'donut_center' => 'إجمالي ر.س',
    'donut_title' => 'المستحقات حسب المرحلة',

    // شرائح
    'seg_all' => 'الكل',
    'seg_open' => 'مفتوحة',
    'seg_ready' => 'جاهزة للصرف',
    'seg_pending' => 'قيد الاعتماد',
    'seg_waiting' => 'بانتظار المزوّد',
    'seg_paid' => 'مدفوعة',
    'seg_failed' => 'فاشلة',

    'search_placeholder' => 'ابحث برقم المستحق أو المبدع…',

    // حالات فارغة
    'empty_filtered_title' => 'لا مستحقات مطابقة',
    'empty_filtered_text' => 'لا نتائج للبحث أو الشريحة الحالية.',
    'clear_filters' => 'مسح الفلاتر',
    'empty_title' => 'لا مستحقات بعد',
    'empty_text' => 'تظهر هنا مستحقات المبدعين عند إنشائها.',

    // مجموعات الصرف
    'b_ready' => 'جاهز للصرف',
    'b_pending' => 'بانتظار الاعتماد',
    'b_paid' => 'مدفوع',
    'b_closed' => 'مغلق',
    'overdue_prefix' => 'تأخر',
    'count_item' => ':n مستحق',

    // نافذة الإنشاء
    'modal_note' => 'يُسجَّل المستحق للمتابعة والاعتماد فقط — لا ينفّذ النظام أي تحويل مالي.',
    'f_creator' => 'المبدع',
    'choose' => '— اختر —',
    'f_amount' => 'المبلغ (ر.س)',
    'f_due' => 'تاريخ الاستحقاق',
    'f_description' => 'الوصف',
    'desc_placeholder' => 'أجر تعاون حملة…',
    'create' => 'إنشاء المستحق',
    'cancel' => 'إلغاء',

    // صفحة التفصيل (Show)
    'show_heading' => 'مستحق',
    'show_eyebrow' => 'مستحق · :num',
    'back_all' => 'كل المستحقات',
    'm_amount' => 'المبلغ',
    'm_due' => 'الاستحقاق',
    'm_paid' => 'دُفع',
    'm_reference' => 'مرجع الدفع',
    'ss_paid_at' => 'دُفع في',
    'stmt_pdf' => 'كشف PDF',
    'stmt_preview_title' => 'معاينة كشف المستحق (PDF)',
    'provider_note' => 'بانتظار ربط مزوّد دفع. النظام لا ينفّذ التحويل — تُسجَّل «مدفوع» يدويًا بمرجع تحويل بعد التسوية الفعلية.',
    'sec_details' => 'تفاصيل المستحق',
    'failure_label' => 'سبب الفشل:',
    'no_description' => 'لا وصف إضافي.',
    'sec_history' => 'سجل الحالة',
    'no_history' => 'لا سجل بعد.',
    'ref_placeholder' => 'مرجع التحويل (إلزامي)',
    'reason_placeholder' => 'السبب',
    'confirm' => 'تأكيد',

    // تسميات إجراءات سير الصرف
    'act_approve' => 'اعتماد',
    'act_cancel' => 'إلغاء',
    'act_schedule' => 'جدولة الصرف',
    'act_reschedule' => 'إعادة الجدولة',
    'act_send_provider' => 'إرسال للمزوّد',
    'act_mark_paid' => 'تسجيل الدفع',
    'act_mark_failed' => 'تسجيل الفشل',
];
