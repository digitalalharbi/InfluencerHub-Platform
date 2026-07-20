<?php

/**
 * إعداد التنقّل المركزي — Central Navigation Configuration.
 * مصدر واحد لكل القوائم عبر البوابات. تُبنى منه الشرائط الجانبية والتنقّل السفلي للجوال.
 *
 * حقول العنصر:
 *   key        مفتاح التسمية في ملفات lang navigation ضمن items.KEY
 *   icon       اسم أيقونة Lucide (SVG داخلي عبر x-icon)؛ لا إيموجي
 *   route      المسار
 *   match      نمط request is() لتحديد التبويب النشِط (افتراضيًا نفس route)
 *   badge      مفتاح عدّاد من NavigationBadges (اختياري) — رقم حقيقي من PostgreSQL
 *   mobile     true = يظهر ضمن التنقّل السفلي على الجوال (4–5 وجهات فقط)
 *   permission صلاحية مطلوبة (اختياري)
 *   soon       true = قيد البناء (غير قابل للنقر)
 *   desc       مفتاح وصف اختياري في navigation.descriptions
 */
return [

    'agency' => [
        'logout_route' => '/logout',
        'groups' => [
            ['key' => 'overview', 'items' => [
                ['key' => 'dashboard', 'icon' => 'layout-dashboard', 'route' => '/app', 'match' => 'app', 'mobile' => true],
            ]],
            ['key' => 'relationships', 'items' => [
                ['key' => 'clients', 'icon' => 'building-2', 'route' => '/app/clients', 'match' => 'app/clients*', 'desc' => 'clients', 'mobile' => true],
                ['key' => 'brands', 'icon' => 'bookmark', 'route' => '/app/brands', 'match' => 'app/brands*', 'desc' => 'brands'],
                ['key' => 'partner_agencies', 'icon' => 'handshake', 'route' => '/app/partner-agencies', 'match' => 'app/partner-agencies*'],
            ]],
            ['key' => 'creators', 'items' => [
                ['key' => 'influencers', 'icon' => 'megaphone', 'route' => '/app/creators?type=influencer', 'match' => 'app/creators'],
                ['key' => 'ugc_creators', 'icon' => 'video', 'route' => '/app/creators?type=ugc_creator', 'match' => null],
                ['key' => 'all_creators', 'icon' => 'users', 'route' => '/app/creators', 'match' => null, 'mobile' => true],
                ['key' => 'creator_applications', 'icon' => 'user-plus', 'route' => '/app/creator-applications', 'match' => 'app/creator-applications*', 'badge' => 'creator_applications'],
            ]],
            ['key' => 'operations', 'items' => [
                ['key' => 'service_requests', 'icon' => 'inbox', 'route' => '/app/service-requests', 'match' => 'app/service-requests*', 'badge' => 'service_requests', 'desc' => 'service_requests', 'mobile' => true],
                ['key' => 'campaigns', 'icon' => 'rocket', 'route' => '/app/campaigns', 'match' => 'app/campaigns*', 'desc' => 'campaigns'],
                ['key' => 'collaborations', 'icon' => 'git-merge', 'route' => '/app/collaborations', 'match' => 'app/collaborations*'],
                ['key' => 'content', 'icon' => 'image', 'route' => '/app/content', 'match' => 'app/content*', 'badge' => 'content', 'desc' => 'content'],
                ['key' => 'contracts', 'icon' => 'file-text', 'route' => '/app/contracts', 'match' => 'app/contracts*'],
            ]],
            ['key' => 'reviews', 'items' => [
                ['key' => 'brand_reviews', 'icon' => 'shield-check', 'route' => '/app/brand-reviews', 'match' => 'app/brand-reviews*', 'badge' => 'brand_reviews', 'desc' => 'brand_reviews'],
                ['key' => 'client_reviews', 'icon' => 'clipboard-check', 'route' => '/app/client-reviews', 'match' => 'app/client-reviews*', 'badge' => 'client_reviews', 'desc' => 'client_reviews'],
            ]],
            ['key' => 'finance', 'items' => [
                ['key' => 'payouts', 'icon' => 'wallet', 'route' => '/app/payouts', 'match' => 'app/payouts*', 'desc' => 'payouts'],
            ]],
            ['key' => 'insights', 'items' => [
                ['key' => 'reports', 'icon' => 'bar-chart-3', 'route' => '/app/reports', 'match' => 'app/reports*', 'desc' => 'reports'],
            ]],
            ['key' => 'admin', 'items' => [
                ['key' => 'platforms', 'icon' => 'plug', 'route' => '/app/settings/platforms', 'match' => 'app/settings/platforms'],
                ['key' => 'settings', 'icon' => 'settings', 'route' => '#', 'soon' => true],
            ]],
        ],
    ],

    'client' => [
        'logout_route' => '/client/logout',
        'groups' => [
            ['key' => 'overview', 'items' => [
                ['key' => 'client_home', 'icon' => 'layout-dashboard', 'route' => '/client/dashboard', 'match' => 'client/dashboard', 'mobile' => true],
            ]],
            ['key' => 'relationships', 'items' => [
                ['key' => 'client_profile', 'icon' => 'building-2', 'route' => '/client/profile', 'match' => 'client/profile'],
                ['key' => 'client_brands', 'icon' => 'bookmark', 'route' => '/client/brands', 'match' => 'client/brands*', 'mobile' => true],
                ['key' => 'client_addresses', 'icon' => 'map-pin', 'route' => '/client/addresses', 'match' => 'client/addresses'],
                ['key' => 'client_documents', 'icon' => 'folder', 'route' => '/client/documents', 'match' => 'client/documents'],
                ['key' => 'client_team', 'icon' => 'users', 'route' => '/client/team', 'match' => 'client/team'],
            ]],
            ['key' => 'operations', 'items' => [
                ['key' => 'client_requests', 'icon' => 'inbox', 'route' => '/client/requests', 'match' => 'client/requests*', 'mobile' => true],
                ['key' => 'client_campaigns', 'icon' => 'rocket', 'route' => '/client/campaigns', 'match' => 'client/campaigns*'],
                ['key' => 'client_content', 'icon' => 'image', 'route' => '/client/content', 'match' => 'client/content*'],
                ['key' => 'client_contracts', 'icon' => 'file-text', 'route' => '/client/contracts', 'match' => 'client/contracts*'],
            ]],
            ['key' => 'account', 'items' => [
                ['key' => 'client_notifications', 'icon' => 'bell', 'route' => '/client/notifications', 'match' => 'client/notifications', 'badge' => 'client_notifications', 'mobile' => true],
                ['key' => 'client_billing', 'icon' => 'wallet', 'route' => '/client/billing-profile', 'match' => 'client/billing-profile'],
                ['key' => 'client_settings', 'icon' => 'settings', 'route' => '/client/settings', 'match' => 'client/settings'],
            ]],
        ],
    ],

    'partner' => [
        'logout_route' => '/partner/logout',
        'groups' => [
            ['key' => 'overview', 'items' => [
                ['key' => 'partner_home', 'icon' => 'layout-dashboard', 'route' => '/partner/dashboard', 'match' => 'partner/dashboard', 'mobile' => true],
            ]],
            ['key' => 'operations', 'items' => [
                ['key' => 'partner_requests', 'icon' => 'inbox', 'route' => '/partner/requests', 'match' => 'partner/requests*', 'mobile' => true],
            ]],
            ['key' => 'admin', 'items' => [
                ['key' => 'partner_briefs', 'icon' => 'file-text', 'route' => '/partner/briefs', 'soon' => true],
                ['key' => 'partner_content', 'icon' => 'image', 'route' => '/partner/content', 'soon' => true],
                ['key' => 'partner_reports', 'icon' => 'bar-chart-3', 'route' => '/partner/reports', 'soon' => true],
                ['key' => 'partner_team', 'icon' => 'users', 'route' => '/partner/team', 'soon' => true],
                ['key' => 'partner_settings', 'icon' => 'settings', 'route' => '/partner/settings', 'soon' => true],
            ]],
        ],
    ],

    'creator' => [
        'logout_route' => '/creator/logout',
        'groups' => [],  // يُبنى ديناميكيًا من روابط البوابة الحالية (تبقى كما هي مؤقتًا)
    ],
];
