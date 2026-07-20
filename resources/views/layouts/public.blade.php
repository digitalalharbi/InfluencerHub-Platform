@php $lang = request('lang')==='en' ? 'en' : 'ar'; $dir = $lang==='en' ? 'ltr' : 'rtl'; @endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'انضمام المبدعين') — إنفلونسر هَب</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/icons/ih-icon.svg"><meta name="theme-color" content="#6252E5">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="background:linear-gradient(180deg,#f0fdf9,#f8fafc);">
<header style="background:var(--surface); border-bottom:1px solid var(--border); padding:1rem 1.5rem; display:flex; align-items:center; gap:1rem;">
    <a href="/join" style="font-weight:800; font-size:1.15rem; color:var(--brand); text-decoration:none;">◆ إنفلونسر هَب</a>
    <div style="margin-inline-start:auto; display:flex; gap:.5rem; align-items:center;">
        <a href="?lang=ar" class="btn btn-sm {{ $lang==='ar' ? 'btn-primary' : 'btn-ghost' }}">ع</a>
        <a href="?lang=en" class="btn btn-sm {{ $lang==='en' ? 'btn-primary' : 'btn-ghost' }}">EN</a>
    </div>
</header>
<main style="max-width:760px; margin:0 auto; padding:2rem 1.2rem 4rem;">
    @if(session('ok'))
        <div class="card" style="padding:.8rem 1rem; margin-bottom:1rem; border-inline-start:3px solid var(--success); color:#15803d;">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
        <div class="card" style="padding:.8rem 1rem; margin-bottom:1rem; border-inline-start:3px solid var(--danger); color:#b91c1c;">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</main>
</body>
</html>
