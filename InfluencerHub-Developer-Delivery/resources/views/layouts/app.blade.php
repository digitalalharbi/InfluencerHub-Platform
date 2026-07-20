<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'InfluencerHub') — إنفلونسر هَب</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/icons/ih-icon.svg"><meta name="theme-color" content="#0D1424">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $ihUser = auth()->user();
    $ihWorkspace = null;
    try {
        $oid = \App\Domain\Tenancy\Support\TenantContext::organizationId();
        if ($oid) $ihWorkspace = \App\Domain\Tenancy\Models\Organization::withoutGlobalScopes()->find($oid)?->name;
    } catch (\Throwable $e) { $ihWorkspace = null; }
    $ihWorkspace = $ihWorkspace ?: 'مساحة عمل الوكالة';
@endphp
<body>
<div class="ih-shell has-bottom-nav" x-data="{ nav:false }" :class="{ 'nav-open': nav }" @keydown.escape.window="nav=false">
    <div class="ih-scrim" @click="nav=false"></div>
    <aside class="sidebar ih-side" @click="nav=false">
        <a href="/app" class="ih-side__brand">
            <span class="ih-side__mark">◆</span> إنفلونسر هَب
        </a>
        <div class="ih-side__workspace">
            <span class="ih-side__ws-avatar">{{ mb_substr($ihWorkspace, 0, 1) }}</span>
            <div style="min-width:0; flex:1;">
                <div class="ih-side__ws-name">{{ $ihWorkspace }}</div>
                <div class="ih-side__ws-plan">وكالة · الخطة النشطة</div>
            </div>
        </div>
        <div class="ih-side__scroll">
            <x-app-nav portal="agency"/>
        </div>
        <div class="ih-side__foot">
            <a href="/app/preview" class="nav-link ih-nav__link {{ request()->is('app/preview') ? 'active' : '' }}" style="font-size:.84rem;">
                <x-icon name="layout-dashboard" :size="18"/> <span>مركز المعاينة</span>
            </a>
            <form method="POST" action="/logout">@csrf
                <button class="nav-link ih-nav__link" style="width:100%; border:0; background:none; cursor:pointer; text-align:start; font-size:.84rem;">
                    <x-icon name="log-out" :size="18"/> <span>تسجيل الخروج</span>
                </button>
            </form>
        </div>
    </aside>
    <main style="flex:1; display:flex; flex-direction:column; min-width:0;">
        <div class="ih-topbar-mobile">
            <button class="ih-icon-btn" @click="nav=true" aria-label="فتح القائمة">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg>
            </button>
            <span style="font-weight:800; color:var(--ih-primary);">◆ إنفلونسر هَب</span>
            <span style="margin-inline-start:auto; font-weight:700; font-size:.9rem;">@yield('heading', '')</span>
            <button type="button" class="ih-icon-btn" @click="$dispatch('ih-open-command-palette')" aria-label="بحث سريع"><x-icon name="search" :size="18"/></button>
        </div>
        <header class="ih-topbar ih-only-desktop">
            <div class="ih-topbar__title">@yield('heading', 'لوحة التحكم')</div>
            <div class="ih-topbar__spacer"></div>
            @if($ihShowcase ?? false)<span class="ih-showcase-badge" title="بيئة عرض تجريبية">● بيانات تجريبية</span>@endif
            <button type="button" class="ih-topbar__search" @click="$dispatch('ih-open-command-palette')" aria-label="لوحة الأوامر">
                <x-icon name="search" :size="16"/>
                <span>بحث سريع…</span>
                <kbd class="ih-kbd" style="direction:ltr;">⌘K</kbd>
            </button>
            <div class="ih-topbar__user">
                <span class="ih-topbar__user-name ih-only-desktop">{{ $ihUser?->name }}</span>
                <span class="ih-topbar__avatar">{{ mb_substr($ihUser?->name ?? '؟', 0, 1) }}</span>
            </div>
        </header>
        <div class="ih-content">
            @if(session('ok'))
                <div class="card" style="padding:.8rem 1rem; margin-bottom:1rem; border-inline-start:3px solid var(--ih-success); background:var(--ih-success-soft); color:var(--ih-success-ink);">{{ session('ok') }}</div>
            @endif
            @yield('content')
        </div>
    </main>
    <x-app-bottom-nav portal="agency"/>
    <x-command-palette portal="agency"/>
</div>
</body>
</html>
