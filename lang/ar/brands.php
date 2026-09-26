<?php

/** قائمة العلامات — عربي. سطح كامل الترجمة: عميل + خادم. */
return [
    'title' => 'العلامات',
    'eyebrow' => 'إدارة العلاقات',
    'sub' => 'ملفات العلامات المرتبطة بالعملاء وطابور اعتمادها ومراجعتها',

    // مؤشرات
    'kpi_total' => 'إجمالي العلامات',
    'kpi_total_sub' => ':approved معتمدة',
    'kpi_needs_review' => 'بانتظار مراجعتك',
    'kpi_needs_review_sub' => ':submitted مُرسلة · :under_review قيد المراجعة',
    'kpi_changes' => 'تعديلات مطلوبة',
    'kpi_changes_sub' => 'بانتظار تعديل العميل',
    'kpi_suspended' => 'معلّقة',
    'kpi_suspended_sub' => 'علامات موقوفة',

    // شرائح
    'seg_all' => 'الكل',
    'seg_needs_review' => 'بانتظار المراجعة',
    'seg_submitted' => 'مُرسلة',
    'seg_under_review' => 'قيد المراجعة',
    'seg_changes_requested' => 'تعديلات مطلوبة',
    'seg_approved' => 'معتمدة',
    'seg_suspended' => 'معلّقة',
    'seg_draft' => 'مسودة',

    'search_placeholder' => 'ابحث باسم العلامة أو العميل…',

    // حالات فارغة
    'empty_filtered_title' => 'لا علامات مطابقة',
    'empty_filtered_text' => 'لا نتائج للبحث أو الشريحة الحالية.',
    'clear_filters' => 'مسح الفلاتر',
    'empty_title' => 'لا علامات بعد',
    'empty_text_no_clients' => 'العلامة تتبع عميلًا، وليس لديك عملاء بعد. ابدأ بإضافة عميل ثم أضِف علاماته.',
    'empty_text_has_clients' => 'العلامة تُنشأ من صفحة العميل التابعة له، وتظهر هنا بعد إرسالها للاعتماد.',
    'add_client' => 'إضافة عميل',

    // جدول/بطاقات
    'th_brand' => 'العلامة',
    'th_client' => 'العميل',
    'th_sector' => 'القطاع',
    'th_version' => 'الإصدار',
    'th_submitted' => 'أُرسلت',
    'th_status' => 'الحالة',
    'needs_review' => 'يحتاج مراجعة',
    'needs_your_review' => '● يحتاج مراجعتك',
    'open' => 'فتح',
    'count_item' => ':n علامة',
    'filtered_suffix' => ' · مُرشَّح',

    // منشئ العلامة (الخادم — createHint)
    'add_brand_for' => 'أضِف علامة لـ:client',
    'choose_client' => 'اختر عميلًا',

    // صفحة التفصيل (Show)
    'show_heading' => 'علامة تجارية',
    'show_eyebrow' => 'علامة · :client',
    'back_all' => 'كل العلامات',
    'm_sector' => 'القطاع',
    'm_version' => 'الإصدار',
    'm_submitted' => 'أُرسلت',
    'm_reviewed' => 'روجعت',
    'changes_label' => 'تعديلات مطلوبة:',
    'ss_campaigns' => 'الحملات',
    'ss_budget' => 'الميزانية',
    'ss_content' => 'المحتوى',
    'ss_awaiting' => 'بانتظار المراجعة',
    'currency_sar' => 'ر.س',

    // التبويبات
    'tab_overview' => 'نظرة عامة',
    'tab_campaigns' => 'الحملات',
    'tab_content' => 'المحتوى',
    'tab_accounts' => 'الحسابات',
    'tab_review' => 'المراجعة',

    // تبويب الحملات
    'no_campaigns' => 'لا حملات لهذه العلامة',
    'open_nomination_title' => 'فتح ترشيح هذه الحملة',
    'nomination_prefix' => 'الترشيح:',
    'published_content' => 'المحتوى المنشور',
    'deliverables_count' => ':n مخرج',

    // تبويب المحتوى
    'no_content' => 'لا محتوى مرتبط',
    'needs_action' => 'يحتاج إجراء',

    // نظرة عامة — ملف العلامة
    'sec_profile' => 'ملف العلامة',
    'f_website' => 'الموقع',
    'f_language' => 'اللغة المفضّلة',
    'f_tone' => 'نبرة الصوت',
    'f_audience' => 'الجمهور المستهدف',
    'prohibited_topics' => 'مواضيع محظورة',
    'required_messages' => 'رسائل إلزامية',
    'visual_guidelines' => 'إرشادات بصرية',

    // جاهزية الاعتماد
    'sec_readiness' => 'جاهزية الاعتماد',
    'critical_complete' => 'مكتملة البنود الحرِجة',
    'critical_missing' => ':n بند حرِج ناقص',
    'critical_tag' => 'حرِج',
    'sec_last_decision' => 'آخر قرار',
    'all_decisions' => 'كل القرارات',
    'no_decisions' => 'لا قرارات بعد.',

    // تبويب الحسابات
    'no_accounts' => 'لا حسابات مسجّلة',
    'no_accounts_hint' => 'أضِف حسابات العلامة لمتابعة نشاطها.',
    'open_account' => 'فتح الحساب',

    // تبويب المراجعة
    'sec_review_decisions' => 'قرارات المراجعة',
    'sec_history' => 'سجل الحالة',
    'no_history' => 'لا سجل بعد.',

    // نافذة القرار
    'approve_warning' => 'تنبيه: :n من البنود الحرِجة ناقصة — راجِع جاهزية الاعتماد قبل المتابعة.',
    'reason_required_ph' => 'السبب (يظهر للعميل) — إلزامي',
    'note_optional_ph' => 'ملاحظة الاعتماد (اختياري)',
    'confirm' => 'تأكيد',
    'cancel' => 'إلغاء',

    // تسميات إجراءات الاعتماد
    'act_start_review' => 'بدء المراجعة',
    'act_approve' => 'اعتماد العلامة',
    'act_request_changes' => 'طلب تعديل',
    'act_suspend' => 'تعليق العلامة',
    'act_reapprove' => 'إعادة الاعتماد',
    'act_submit' => 'إرسال للاعتماد',
    'act_resubmit' => 'إعادة الإرسال للاعتماد',

    // بنود جاهزية الاعتماد
    'ck_name' => 'اسم العلامة',
    'ck_client' => 'مِلْكية العميل',
    'ck_sector' => 'القطاع',
    'ck_description' => 'وصف العلامة',
    'ck_logo' => 'الشعار',
    'ck_website' => 'الموقع/النطاق',
    'ck_cr' => 'السجل التجاري',
    'ck_contact' => 'بيانات التواصل',
    'ck_guidelines' => 'إرشادات العلامة',
    'ck_voice' => 'نبرة الصوت والجمهور',
    'ck_accounts' => 'حساب اجتماعي واحد على الأقل',

    // قرارات المراجعة
    'dec_approved' => 'موافقة',
    'dec_changes_requested' => 'طلب تعديل',
    'dec_rejected' => 'رفض',

    // حالة الترشيح (تُعرَض على بطاقة الحملة)
    'nom_status_draft' => 'مسودة',
    'nom_status_submitted' => 'بانتظار العميل',
    'nom_status_approved' => 'مُعتمَد',
    'nom_status_partially_approved' => 'اعتماد جزئي',
    'nom_status_changes_requested' => 'مطلوب بديل',
    'nom_status_rejected' => 'مرفوض',
];
