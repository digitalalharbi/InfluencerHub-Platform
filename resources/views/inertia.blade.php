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
    {{-- أيقونات المنتج — عائلة واحدة (favicon.ico + SVG + apple-touch) --}}
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/icons/ih-icon.svg">
    <link rel="apple-touch-icon" href="/icons/ih-icon.svg">
    {{-- OpenGraph / Twitter — هوية InfluencerHub --}}
    <meta property="og:site_name" content="{{ $brand['name'] }}">
    <meta property="og:title" content="{{ $brand['name'] }} — {{ $brand['tagline'] }}">
    <meta property="og:description" content="{{ $brand['tagline'] }}">
    <meta property="og:url" content="{{ $brand['url'] }}/">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_SA">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $brand['name'] }}">
    <meta name="twitter:description" content="{{ $brand['tagline'] }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="theme-color" content="#0D1424">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/inertia.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
