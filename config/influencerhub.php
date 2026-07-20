<?php
/*
| InfluencerHub — إعدادات المنتج. لا يُربط منطق النظام بنطاق ثابت في الكود.
| DEPLOYMENT_MODE: saas | dedicated | self_hosted
*/
return [
    'product_name' => 'InfluencerHub',
    'deployment_mode' => env('DEPLOYMENT_MODE', 'saas'),
    'is_saas' => env('DEPLOYMENT_MODE', 'saas') === 'saas',
    'is_dedicated' => env('DEPLOYMENT_MODE') === 'dedicated',
    'is_self_hosted' => env('DEPLOYMENT_MODE') === 'self_hosted',
    'self_hosted_entitlements' => [], // فارغ = غير محدود في self_hosted
];
