<?php

/**
 * توفّر قنوات التسليم — أعلام صريحة لا تعتمد على APP_ENV، افتراضها false.
 * القناة لا تُرسِل ولا تظهر «مهيّأة» ما لم يُفعَّل عَلَمها وتوجد بيانات اعتمادها.
 * in_app متاحة دائمًا (لا مزوّد خارجي).
 */
return [
    'email' => [
        'enabled' => (bool) env('CHANNEL_EMAIL_ENABLED', false),
    ],
    'whatsapp' => [
        'enabled' => (bool) env('CHANNEL_WHATSAPP_ENABLED', false),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'lang' => env('WHATSAPP_TEMPLATE_LANG', 'ar'),
        // قالب افتراضي عام + خرائط لأنواع الأحداث (تُطابق قوالب Meta المعتمدة).
        // القوالب نفسها تتطلّب موافقة Meta (BLOCKED_EXTERNAL) — هذه الخريطة جاهزة.
        'default_template' => env('WHATSAPP_DEFAULT_TEMPLATE', 'notification'),
        'templates' => [
            'service_request.assigned' => 'task_assignment',
            'content.submitted' => 'content_revision',
            'content.update' => 'content_revision',
            'content.client_review' => 'content_revision',
            'brand.approved' => 'content_approved',
            'brand.changes_requested' => 'content_revision',
            'campaign.update' => 'campaign_update',
            'creator.invitation' => 'creator_invitation',
            'invoice.issued' => 'invoice_ready',
            'payment.received' => 'payment_received',
            'payout.status' => 'payout_status',
        ],
    ],
    'sms' => [
        'enabled' => (bool) env('CHANNEL_SMS_ENABLED', false),
    ],
];
