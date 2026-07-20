<?php
return [
    // فئات ملفات الطلب: mimes/extensions/الحد الأقصى بالبايت
    'uploads' => [
        'avatar'            => ['mimes' => ['image/png','image/jpeg','image/webp'], 'ext' => ['png','jpg','jpeg','webp'], 'max' => 10 * 1024 * 1024],
        'portfolio_image'   => ['mimes' => ['image/png','image/jpeg','image/webp'], 'ext' => ['png','jpg','jpeg','webp'], 'max' => 10 * 1024 * 1024],
        'portfolio_video'   => ['mimes' => ['video/mp4','video/quicktime'], 'ext' => ['mp4','mov'], 'max' => (int) env('CREATOR_VIDEO_MAX_MB', 200) * 1024 * 1024],
        'iban_document'     => ['mimes' => ['application/pdf','image/png','image/jpeg'], 'ext' => ['pdf','png','jpg','jpeg'], 'max' => 15 * 1024 * 1024],
        'mowthooq_document' => ['mimes' => ['application/pdf','image/png','image/jpeg'], 'ext' => ['pdf','png','jpg','jpeg'], 'max' => 15 * 1024 * 1024],
        'identity_document' => ['mimes' => ['application/pdf','image/png','image/jpeg'], 'ext' => ['pdf','png','jpg','jpeg'], 'max' => 15 * 1024 * 1024],
        'additional_document' => ['mimes' => ['application/pdf','image/png','image/jpeg'], 'ext' => ['pdf','png','jpg','jpeg'], 'max' => 15 * 1024 * 1024],
    ],
    // امتدادات تنفيذية ممنوعة قطعًا (طبقة دفاع إضافية)
    'blocked_ext' => ['php','phtml','exe','sh','bat','cmd','js','jar','com','msi','dll','app','bin','pl','py','rb','htaccess'],
];
