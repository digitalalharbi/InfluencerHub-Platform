<?php

/**
 * سجل المنصّات (Platform Registry) — مصدر واحد للمنصّات وقدراتها وتوفّرها.
 * القاعدة: لا يُعرض للمستخدم إلا ما هو متاح فعلًا (status متاح + القدرة مدعومة).
 * الأولوية للسوق السعودي: Snapchat → TikTok → X → LinkedIn → YouTube → Instagram.
 *
 * status: draft | configured | waiting_for_credentials | waiting_for_platform_approval
 *         | sandbox | available_manual | available_import | available_api | connected
 *         | degraded | disconnected | suspended | deprecated
 * لا تظهر المنصّة في الطلبات إلا بحالة: available_manual|available_import|available_api|connected.
 *
 * capabilities: قائمة القدرات المدعومة فعلًا (تقرؤها النماذج والـWorkflow).
 * لا يوجد ربط API مباشر بعد → البيانات "إدخال يدوي"، والقدرات المباشرة waiting_for_credentials.
 */
return [

    'available_statuses' => ['available_manual', 'available_import', 'available_api', 'connected'],

    // مرتّبة حسب الأولوية (order تصاعدي)
    'registry' => [
        'snapchat'  => ['order' => 1, 'label_ar' => 'سناب شات', 'label_en' => 'Snapchat', 'status' => 'available_manual',
            'capabilities' => ['creator_profile', 'creator_application', 'ugc_creator_application', 'influencer_campaign', 'ugc_campaign', 'audience_data', 'content_publishing', 'publishing_verification']],
        'tiktok'    => ['order' => 2, 'label_ar' => 'تيك توك', 'label_en' => 'TikTok', 'status' => 'available_manual',
            'capabilities' => ['creator_profile', 'creator_application', 'ugc_creator_application', 'influencer_campaign', 'ugc_campaign', 'audience_data', 'content_publishing', 'publishing_verification']],
        'x'         => ['order' => 3, 'label_ar' => 'إكس', 'label_en' => 'X', 'status' => 'available_manual',
            'capabilities' => ['creator_profile', 'creator_application', 'influencer_campaign', 'audience_data', 'content_publishing']],
        'linkedin'  => ['order' => 4, 'label_ar' => 'لينكدإن', 'label_en' => 'LinkedIn', 'status' => 'available_manual',
            'capabilities' => ['creator_profile', 'creator_application', 'influencer_campaign', 'audience_data']],
        'youtube'   => ['order' => 5, 'label_ar' => 'يوتيوب', 'label_en' => 'YouTube', 'status' => 'available_manual',
            'capabilities' => ['creator_profile', 'creator_application', 'ugc_creator_application', 'influencer_campaign', 'ugc_campaign', 'audience_data', 'content_publishing', 'publishing_verification']],
        'instagram' => ['order' => 6, 'label_ar' => 'إنستغرام', 'label_en' => 'Instagram', 'status' => 'available_manual',
            'capabilities' => ['creator_profile', 'creator_application', 'ugc_creator_application', 'influencer_campaign', 'ugc_campaign', 'audience_data', 'content_publishing', 'publishing_verification']],

        // مثال منصّة مستقبلية — مخفية تمامًا حتى اكتمال التهيئة والاعتماد.
        'threads'   => ['order' => 99, 'label_ar' => 'ثريدز', 'label_en' => 'Threads', 'status' => 'draft',
            'capabilities' => []],
    ],
];
