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

    // ─────────────────────────────────────────────────────────────
    // صفحة التفاصيل (Show) — سطح كامل الترجمة لملف العميل الواحد
    // ─────────────────────────────────────────────────────────────

    // العملة
    'currency_sar' => 'ر.س',

    // عام
    'save' => 'حفظ',

    // رأس الصفحة
    'show_heading' => 'ملف العميل',
    'show_eyebrow' => 'عميل · :num',
    'back_all' => 'كل العملاء',
    'm_sector' => 'القطاع',
    'm_manager' => 'مدير الحساب',
    'm_city' => 'المدينة',
    'm_classification' => 'التصنيف',
    'm_vip' => 'VIP',
    'm_regular' => 'عادي',
    'status' => 'الحالة',
    'archive' => 'أرشفة',
    'archive_confirm' => 'أرشفة العميل «:name»؟ يمكن استرجاعه بتغيير حالته لاحقًا.',

    // شريط الملخّص
    'ss_revenue' => 'الإيراد',
    'ss_cost' => 'التكلفة',
    'ss_profit' => 'الربح',
    'ss_margin' => 'الهامش',
    'ss_campaigns' => 'الحملات',
    'ss_creators' => 'صناع المحتوى',
    'ss_receivable' => 'المستحق',
    'ss_completion' => 'الاكتمال',

    // التبويبات
    'tab_overview' => 'نظرة عامة',
    'tab_campaigns' => 'الحملات',
    'tab_creators' => 'صناع المحتوى',
    'tab_content' => 'المحتوى',
    'tab_requests' => 'الطلبات',
    'tab_docs' => 'العقود والمستندات',
    'tab_finance' => 'المالية',
    'tab_brands' => 'العلامات',
    'tab_contacts' => 'جهات الاتصال',
    'tab_team' => 'الفريق',
    'tab_custom' => 'حقول مخصّصة',

    // نظرة عامة — الخطوة التالية
    'next_step' => 'الخطوة التالية',
    'process' => 'معالجة',
    'all_clear' => 'لا شيء يحتاج تدخّلًا الآن.',

    // نظرة عامة — الأقسام
    'sec_risks' => 'المخاطر',
    'risk_handle' => 'معالجة ←',
    'sec_active_campaigns' => 'الحملات النشطة',
    'link_all_campaigns' => 'كل الحملات',
    'no_campaigns_yet' => 'لا حملات بعد.',
    'ov_deliverables' => ':n مخرج',
    'sec_activity' => 'آخر نشاط',
    'no_activity' => 'لا نشاط مسجّل.',
    'sec_completion' => 'اكتمال الملف',
    'profile_complete' => 'الملف مكتمل.',
    'profile_incomplete' => 'أكمل البيانات القانونية والمالية.',
    'sec_contact_info' => 'بيانات التواصل',
    'ci_email' => 'البريد',
    'ci_phone' => 'الهاتف',
    'ci_website' => 'الموقع',
    'ci_city' => 'المدينة',
    'ci_cr' => 'السجل التجاري',
    'ci_tax' => 'الرقم الضريبي',
    'link_all' => 'الكل',
    'contact_message' => 'مراسلة',

    // تبويب الحملات (Pipeline)
    'empty_campaigns_title' => 'لا حملات بعد',
    'empty_campaigns_hint' => 'ستظهر حملات هذا العميل هنا فور إنشائها.',
    'stage_planning' => 'التخطيط',
    'stage_running' => 'التنفيذ',
    'stage_closed' => 'المنتهية',
    'no_campaigns_in_stage' => 'لا حملات في هذه المرحلة.',
    'wcard_published' => 'المحتوى المنشور',
    'wcard_budget' => 'الميزانية',
    'awaiting_review_n' => ':n بانتظار مراجعة',

    // تبويب العلامات
    'brand_add' => 'إضافة علامة',
    'brand_name' => 'اسم العلامة',
    'empty_brands_title' => 'لا علامات بعد',
    'empty_brands_hint' => 'علامات هذا العميل تظهر هنا مع نشاطها.',
    'brand_stat_campaigns' => 'حملات',
    'brand_stat_active' => 'نشطة',
    'brand_stat_budget' => 'ميزانية',

    // تبويب المحتوى
    'empty_content_title' => 'لا محتوى بعد',
    'empty_content_hint' => 'محتوى حملات هذا العميل يظهر هنا للمراجعة والاعتماد.',
    'needs_action' => 'يحتاج إجراء',

    // تبويب العقود والمستندات
    'n_contract_awaiting_sign' => ':n عقد بانتظار التوقيع',
    'n_contract_expiring' => ':n عقد ينتهي خلال 30 يومًا',
    'n_doc_expired' => ':n مستند منتهٍ',
    'n_doc_pending' => ':n مستند بانتظار المراجعة',
    'sec_contracts' => 'العقود',
    'no_contracts' => 'لا عقود بعد.',
    'sign_sent' => 'أُرسل',
    'sign_signed' => 'وُقّع',
    'sign_active' => 'سارٍ',
    'expires_on' => 'ينتهي :date',
    'awaiting_signature' => 'بانتظار التوقيع',
    'expiring_30' => 'ينتهي خلال 30 يومًا',
    'expired' => 'منتهٍ',
    'sec_documents' => 'المستندات',
    'doc_upload' => 'رفع مستند',
    'doc_title' => 'العنوان',
    'doc_category' => 'التصنيف',
    'doc_cat_contract' => 'عقد',
    'doc_cat_cr' => 'سجل تجاري',
    'doc_cat_tax' => 'شهادة ضريبية',
    'doc_cat_other' => 'أخرى',
    'doc_file' => 'الملف (حتى 20 ميغابايت)',
    'no_documents' => 'لا مستندات.',
    'expiring_soon' => 'قارب الانتهاء',

    // تبويب المالية
    'fin_revenue_sub' => 'من الحملات',
    'fin_cost_sub' => 'أتعاب المبدعين',
    'fin_margin_sub' => 'هامش :n%',
    'fin_overdue_payout' => 'متأخر الصرف',
    'fin_overdue_needs' => 'يحتاج معالجة',
    'fin_overdue_none' => 'لا متأخرات',
    'sec_by_campaign' => 'التوزيع حسب الحملة',
    'no_finance_data' => 'لا بيانات مالية بعد.',
    'fin_bars_note' => 'الشريط العلوي: الميزانية · السفلي: التكلفة الفعلية',
    'sec_receivables_status' => 'حالة المستحقات',
    'fin_paid' => 'مدفوع',
    'fin_pending' => 'بانتظار الصرف',
    'fin_overdue' => 'متأخر',
    'sec_recent_payments' => 'آخر الدفعات',
    'no_payments' => 'لا دفعات منفّذة.',
    'sec_payouts' => 'المستحقات',
    'no_payouts' => 'لا مستحقات بعد.',
    'th_payout' => 'المستحق',
    'th_creator' => 'المبدع',
    'th_campaign' => 'الحملة',
    'th_due' => 'الاستحقاق',
    'th_amount' => 'المبلغ',
    'th_status' => 'الحالة',

    // تبويب صناع المحتوى
    'empty_creators_title' => 'لا صنّاع محتوى مرتبطين',
    'empty_creators_hint' => 'صناع المحتوى الذين تعاونوا في حملات هذا العميل يظهرون هنا.',
    'rel_active' => 'متعاونون الآن',
    'rel_recent' => 'تعاونوا مؤخّرًا',
    'rel_dormant' => 'متوقفون',
    'cr_stat_collabs' => 'تعاون',
    'cr_stat_published' => 'منشور',
    'cr_stat_value' => 'القيمة',
    'cr_quality' => 'جودة التعاون',
    'cr_last_collab' => 'آخر تعاون :date',
    'cr_no_collab' => 'لا تعاون سابق',
    'cr_new_collab' => 'تعاون جديد',

    // تبويب الطلبات
    'empty_requests_title' => 'لا طلبات',
    'empty_requests_hint' => 'طلبات هذا العميل تظهر هنا مرتّبة حسب الإلحاح.',
    'bucket_overdue' => 'متأخرة',
    'bucket_new' => 'جديدة',
    'bucket_open' => 'قيد العمل',
    'bucket_done' => 'منتهية',
    'unassigned' => 'غير مُسنَد',
    'due_at' => 'يستحق :date',

    // تبويب جهات الاتصال
    'contact_add' => 'إضافة جهة اتصال',
    'contact_name' => 'الاسم',
    'contact_title' => 'المسمّى',
    'empty_contacts_title' => 'لا جهات اتصال',
    'empty_contacts_hint' => 'أضِف جهات اتصال العميل للتواصل المباشر.',
    'contact_primary' => 'أساسي',
    'contact_preferred_channel' => 'القناة المفضّلة',
    'contact_email_btn' => 'بريد',
    'contact_call_btn' => 'اتصال',
    'contact_whatsapp_btn' => 'واتساب',
    'contact_has_portal' => 'له وصول للبوابة',

    // تبويب الفريق
    'invite_member' => 'دعوة عضو بوابة',
    'role_label' => 'الدور',
    'role_client_admin_opt' => 'مدير حساب العميل',
    'role_member_opt' => 'عضو',
    'invite_token_title' => 'رمز الدعوة — يُعرض مرة واحدة',
    'invite_token_hint' => 'انسخه الآن وسلّمه للعضو؛ لا يمكن استرجاعه بعد مغادرة الصفحة.',
    'th_member' => 'العضو',
    'no_members' => 'لا أعضاء.',

    // تبويب الحقول المخصّصة
    'field_define' => 'تعريف حقل',
    'field_key' => 'المفتاح',
    'field_label' => 'التسمية',
    'field_type' => 'النوع',
    'ftype_text' => 'نص',
    'ftype_textarea' => 'نص طويل',
    'ftype_number' => 'رقم',
    'ftype_date' => 'تاريخ',
    'ftype_boolean' => 'نعم/لا',
    'ftype_url' => 'رابط',
    'ftype_email' => 'بريد',
    'ftype_phone' => 'هاتف',
    'field_set_values' => 'ضبط القيم',
    'empty_custom_title' => 'لا حقول مخصّصة',
    'empty_custom_hint' => 'تُعرّف الحقول من الإعدادات وتظهر هنا لكل عميل.',
    'field_required' => 'إلزامي',
    'field_empty' => '— غير مُعبّأ',

    // الخادم — أدوار الفريق (CLIENT_ROLE)
    'role_client_admin' => 'مدير',
    'role_client_finance' => 'مالية',
    'role_client_campaign_manager' => 'مدير حملات',
    'role_client_content_reviewer' => 'مراجع محتوى',
    'role_client_viewer' => 'مُطّلع',

    // الخادم — المخاطر/الخطوة التالية
    'risk_sla' => ':n طلب متأخر عن SLA',
    'risk_awaiting_client' => ':n محتوى بانتظار العميل',
    'risk_ready_payout' => ':n مستحق جاهز للصرف',

    // الخادم — آخر نشاط
    'act_campaign' => 'حملة: :name',
    'act_content' => 'محتوى: :title · :status',
    'act_contract' => 'عقد: :title · :status',
    'act_request' => 'طلب: :title · :status',

    // الخادم — مخاطر بطاقة الحملة (Pipeline)
    'camp_risk_late' => 'متأخرة عن موعدها',
    'camp_risk_over_budget' => 'تجاوز الميزانية',
    'camp_risk_awaiting' => ':n محتوى بانتظار مراجعة',

    // الخادم — مراحل المحتوى (contentStages)
    'cs_draft' => 'مسودة',
    'cs_agency_review' => 'مراجعة الوكالة',
    'cs_client_review' => 'مراجعة العميل',
    'cs_changes_requested' => 'تعديلات مطلوبة',
    'cs_approved' => 'معتمد',
    'cs_scheduled' => 'مجدول',
    'cs_published' => 'منشور',

    // الخادم — أولوية الطلب (priorityLabel)
    'prio_low' => 'منخفضة',
    'prio_normal' => 'عادية',
    'prio_high' => 'عالية',
    'prio_urgent' => 'عاجلة',

    // الخادم — سبب تعطّل الطلب (blocked)
    'blk_sla' => 'تجاوز مهلة SLA',
    'blk_needs_info' => 'بانتظار معلومة',
];
