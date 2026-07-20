<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بوابة العميل') — إنفلونسر هَب</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/icons/ih-icon.svg"><meta name="theme-color" content="#6252E5">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="ih-shell has-bottom-nav" x-data="{ nav:false }" :class="{ 'nav-open': nav }" @keydown.escape.window="nav=false"><div class="ih-scrim" @click="nav=false"></div>
    <aside class="sidebar" style="display:flex; flex-direction:column; padding:1.2rem 1rem;" @click="nav=false">
        <div style="font-weight:800; font-size:1.1rem; color:var(--brand); padding:.4rem .5rem 1rem;">◆ بوابة العميل</div>
        {{-- مبدّل العميل --}}
        <div x-data="{ open:false }" style="position:relative; margin-bottom:.8rem;">
            <button @click="open=!open" class="card" style="width:100%; padding:.6rem .8rem; text-align:right; cursor:pointer; border:1px solid var(--border);">
                <div style="font-weight:700; font-size:.9rem;">{{ $activeClient->display_name }}</div>
                <div style="color:var(--text-muted); font-size:.72rem;">{{ $activeClient->client_number }} · تبديل ▾</div>
            </button>
            <div x-show="open" x-cloak @click.outside="open=false" class="card" style="position:absolute; top:100%; inset-inline:0; z-index:20; margin-top:.3rem; padding:.3rem;">
                @foreach($myClients as $mc)
                    <form method="POST" action="/client/switch">@csrf<input type="hidden" name="client_id" value="{{ $mc->id }}">
                        <button class="nav-link {{ $mc->id===$activeClient->id ? 'active' : '' }}" style="width:100%; border:0; background:none; text-align:right; cursor:pointer;">{{ $mc->display_name }}</button>
                    </form>
                @endforeach
            </div>
        </div>
        <x-app-nav portal="client" :badges="['client_notifications' => (int) ($clientUnread ?? 0)]"/>
        <form method="POST" action="/client/logout">@csrf<button class="nav-link" style="width:100%; border:0; background:none; cursor:pointer; text-align:right;">تسجيل الخروج</button></form>
    </aside>
    <main style="flex:1; display:flex; flex-direction:column; min-width:0;">
        <div class="ih-topbar-mobile"><button class="ih-icon-btn" @click="nav=true" aria-label="فتح القائمة"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg></button><span style="font-weight:800; color:var(--ih-primary);">◆ إنفلونسر هَب</span><span style="margin-inline-start:auto; font-weight:700; font-size:.9rem;">@yield('heading','')</span></div>
        <header style="height:62px; background:var(--surface); border-bottom:1px solid var(--border); display:flex; align-items:center; padding:0 1.5rem; gap:1rem;">
            <div style="font-weight:700;">@yield('heading','')</div>
            <button type="button" class="ih-cmdk__trigger" style="margin-inline-start:auto;" @click="$dispatch('ih-open-command-palette')" aria-label="لوحة الأوامر">
                <x-icon name="search" :size="16"/><span>بحث سريع</span><kbd class="ih-kbd" style="direction:ltr;">⌘K</kbd>
            </button>
            <div style="color:var(--text-muted); font-size:.85rem;">{{ auth()->user()?->name }} · {{ $clientMembership->role }}</div>
        </header>
        <div style="padding:1.5rem; flex:1;">
            @if(session('ok'))<div class="card" style="padding:.8rem 1rem; margin-bottom:1rem; border-inline-start:3px solid var(--success); color:#15803d;">{{ session('ok') }}</div>@endif
            @if($errors->any())<div class="card" style="padding:.8rem 1rem; margin-bottom:1rem; border-inline-start:3px solid var(--danger); color:#b91c1c;">{{ $errors->first() }}</div>@endif
            @yield('content')
        </div>
    </main>
    <x-app-bottom-nav portal="client" :badges="['client_notifications' => (int) ($clientUnread ?? 0)]"/>
    <x-command-palette portal="client"/>
</div>
</body></html>
