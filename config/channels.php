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
    ],
    'sms' => [
        'enabled' => (bool) env('CHANNEL_SMS_ENABLED', false),
    ],
];
