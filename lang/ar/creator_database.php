<?php

/**
 * قاعدة المؤثرين (اكتشاف المبدعين). المصدر العربي — النسخة الافتراضية.
 * تسميات الخادم (النوع/الملاحظات) + نصوص واجهتَي Index/Show. المفاتيح ثابتة،
 * والقيم مطابقة حرفيًّا للنصوص المضمّنة سابقًا. أسماء المنصّات من PlatformRegistry.
 */
return [
    // تسميات مبنيّة على الخادم (تُستعمل أيضًا كخيارات فلتر النوع)
    'type_celebrity' => 'مؤثّر',
    'type_ugc' => 'صانع UGC',
    'reference_rate_note' => 'سعر مرجعي مسجّل — غير مضمون؛ يُتفاوَض عليه مع المبدع',
    'data_freshness' => 'بيانات مسجّلة',

    // الترويسة
    'title' => 'قاعدة المؤثرين',
    'eyebrow' => 'اكتشاف المبدعين · منتج مميّز',
    'sub' => 'قاعدة مؤثرين واسعة داخل المنصّة — ابحث، تواصل، واحفظ علاقتك ورشّح مباشرةً لحملتك.',
    'count_item' => ':n مبدع',

    // شريط سياق الحملة
    'ctx_aria' => 'سياق الترشيح للحملة',
    'ctx_label' => 'ترشيح لحملة',
    'ctx_primary' => 'الأساسي :n',
    'ctx_backup' => 'الاحتياط :n',
    'ctx_primary_title' => 'المرشّحون الأساسيّون',
    'ctx_backup_title' => 'مرشّحو الاحتياط',
    'ctx_review' => 'مراجعة القائمة',
    'ctx_locked' => 'هذه القائمة أُرسلت للعميل — لإضافة مرشّحين أنشئ إصدارًا جديدًا من مساحة الترشيح.',

    // البحث والفلاتر
    'search_placeholder' => 'ابحث بالاسم أو المدينة أو الحساب…',
    'f_platform' => 'المنصّة',
    'all_platforms' => 'كل المنصّات',
    'f_region' => 'الموقع',
    'all_regions' => 'كل المناطق',
    'f_sort' => 'ترتيب النتائج',
    'sort_followers' => 'الأكثر متابعة',
    'sort_price' => 'الأعلى سعرًا',
    'sort_recent' => 'الأحدث بيانات',
    'more_filters' => 'فلاتر إضافية',
    'clear_filters' => 'مسح الفلاتر',
    'clear_filters_title' => 'مسح كل الفلاتر',
    'f_type' => 'نوع المبدع',
    'all_types' => 'كل الأنواع',
    'f_tier' => 'الفئة',
    'all_tiers' => 'كل الفئات',
    'tier_label' => 'فئة :t',
    'f_gender' => 'الجنس',
    'gender_female' => 'أنثى',
    'gender_male' => 'ذكر',
    'f_price' => 'السعر',
    'f_price_aria' => 'توفّر السعر',
    'price_available' => 'سعر متاح',

    // التصنيفات
    'cat_all' => 'الكل',
    'cat_less' => 'عرض أقل',
    'cat_all_count' => 'عرض جميع التصنيفات (:n)',
    'filter_by' => 'تصفية: :cat',

    // فلاتر Phase G
    'f_category' => 'التصنيف',
    'all_categories' => 'كل التصنيفات',
    'clear_all' => 'مسح الكل',
    'active_filters' => 'الفلاتر النشطة',

    // درج المعاينة السريعة
    'preview_aria' => 'معاينة المبدع',
    'view_profile' => 'الملف الكامل',
    'add_to_campaign' => 'أضِف للحملة',
    'sec_about' => 'نبذة',
    'sec_reach' => 'الجمهور',
    'sec_pricing' => 'السعر',
    'sec_notes' => 'ملاحظاتك الخاصّة',
    'notes_none' => 'لا ملاحظات بعد.',
    'loading' => 'جارٍ التحميل…',

    // الحالة الفارغة
    'empty_title' => 'لا مبدعين مطابقين',
    'empty_text' => 'لا نتائج للبحث أو الفلاتر الحالية.',
    'empty_hint' => 'جرّب إزالة بعض الفلاتر أو توسيع نطاق البحث.',

    // بطاقة المبدع
    'm_followers' => 'متابع',
    'm_likes' => 'إعجاب',
    'm_shows_face' => 'يظهر الوجه',
    'yes' => 'نعم',
    'no' => 'لا',
    'reference_rate' => 'السعر المرجعي',
    'rate_missing' => 'السعر غير مضاف',
    'profile' => 'الملف',
    'compare' => 'قارن',
    'in_compare' => '✓ في المقارنة',
    'compare_add' => 'أضِف للمقارنة',
    'compare_max' => 'الحد الأقصى 4 للمقارنة',
    'account' => 'الحساب',
    'copy_phone' => 'نسخ الجوال',
    'whatsapp' => 'واتساب',
    'role_primary' => 'أساسي',
    'role_backup' => 'احتياط',
    'in_shortlist_toggle' => '✓ :role — اضغط للتبديل',
    'role_toggle_title' => 'بدّل بين أساسي/احتياط',
    'add_primary' => '+ أساسي',
    'add_backup' => '+ احتياط',
    'nominate_to_campaign' => 'ترشيح لحملة',

    // شريط المقارنة
    'comparebar_aria' => 'شريط المقارنة',
    'selected_one' => 'تم اختيار مؤثر واحد',
    'selected_many' => 'تم اختيار :n مؤثرين',
    'remove_x' => 'إزالة :name',
    'compare_btn' => 'مقارنة (:n)',
    'compare_min' => 'اختر مؤثرَين على الأقل',
    'clear_selection' => 'إلغاء التحديد',

    // لوحة المقارنة
    'compare_modal_aria' => 'مقارنة المؤثرين',
    'compare_title' => 'مقارنة :n مؤثرين',
    'close' => 'إغلاق',
    'dim' => 'البُعد',
    'dim_platform' => 'المنصّة',
    'dim_type' => 'النوع',
    'dim_followers' => 'المتابعون',
    'dim_likes' => 'الإعجابات',
    'dim_tier' => 'الفئة',
    'dim_location' => 'الموقع',
    'dim_shows_face' => 'يظهر الوجه',
    'dim_rating' => 'التقييم',
    'dim_categories' => 'التصنيفات',
    'dim_rate' => 'السعر المرجعي',
    'rate_not_added' => 'غير مضاف',
    'compare_note' => 'أبعاد من بيانات القاعدة الفعلية فقط. القيم الغائبة تظهر «—» ولا تُقدَّر.',

    // صفحة التفصيل (Show)
    'back_all' => '← كل المبدعين',
    'fav_on' => '★ مفضّل',
    'fav_off' => '☆ إضافة للمفضّلة',
    'info' => 'معلومات',
    'info_region' => 'المنطقة',
    'info_city' => 'المدينة',
    'reference_rate_short' => 'سعر مرجعي',
    'not_guaranteed' => 'غير مضمون',
    'last_updated' => 'آخر تحديث: :date',
    'contact' => 'التواصل',
    'copy' => 'نسخ',
    'call' => 'اتصال',
    'social_account' => 'الحساب الاجتماعي',
    'your_notes' => 'ملاحظاتك الخاصّة',
    'notes_placeholder' => 'ملاحظات خاصّة بمؤسستك عن هذا المبدع…',
    'save_notes' => 'حفظ الملاحظات',
    'nominate_hint' => 'يُضاف المبدع إلى قاعدة علاقاتك ويُرشَّح مباشرةً — يتقدّم بذلك المرحلة الثانية للحملة.',
    'no_campaigns' => 'لا حملات متاحة للترشيح.',
    'add_and_nominate' => 'إضافة وترشيح',
];
