<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($brand = ['name' => \App\Support\Brand::name(), 'tagline' => \App\Support\Brand::tagline(), 'url' => \App\Support\Brand::url(), 'domain' => \App\Support\Brand::domain()])
    {{-- عنوان افتراضي — تتجاوزه Inertia لكلّ صفحة، ويبقى هوية المنتج قبل تحميل React --}}
    <title inertia>{{ $brand['name'] }} — {{ $brand['tagline'] }}</title>
    <meta name="description" content="{{ $brand['tagline'] }}">
    {{-- الرابط القانوني على نطاق المنتج influencerhub.io ومسار الطلب — لا على مضيف
         الخدمة (crmv2.…) الذي يعطيه url()->current(). حاضر في HTML الأوّليّ (لزواحف
         لا تُشغّل JS)، وعنصر واحد لا يتكرّر. العنوان لكلّ صفحة يضبطه Inertia. --}}
    <link rel="canonical" href="{{ \App\Support\Brand::url() }}{{ request()->getPathInfo() === '/' ? '' : request()->getPathInfo() }}">
    {{-- أيقونات المنتج الرسمية «الجسر (H)» — SVG + PNG (16/32) + apple-touch --}}
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    {{-- OpenGraph / Twitter — هوية InfluencerHub --}}
    <meta property="og:site_name" content="{{ $brand['name'] }}">
    <meta property="og:title" content="{{ $brand['name'] }} — {{ $brand['tagline'] }}">
    <meta property="og:description" content="{{ $brand['tagline'] }}">
    <meta property="og:url" content="{{ $brand['url'] }}/">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US' }}">
    <meta property="og:image" content="{{ $brand['url'] }}/og-image-1200x630.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $brand['name'] }}">
    <meta name="twitter:description" content="{{ $brand['tagline'] }}">
    <meta name="twitter:image" content="{{ $brand['url'] }}/og-image-1200x630.png">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="manifest" href="/site.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="theme-color" content="#5B45E0">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/inertia.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
