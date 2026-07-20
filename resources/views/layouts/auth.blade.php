<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#080D1A">
    <title>@yield('title', 'تسجيل الدخول') — InfluencerHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.webmanifest"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/icons/ih-icon.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="ih-auth">
    {{-- ============ القسم التسويقي ============ --}}
    <section class="ih-auth__brand">
        <a href="/" class="ih-auth__logo"><x-ih-logo :size="30" color="#FFFFFF"/> <span>InfluencerHub</span></a>
        <h1 class="ih-auth__headline">@yield('headline', 'منصّة تشغيل متكاملة لوكالات المؤثرين')</h1>
        <p class="ih-auth__sub">@yield('sub', 'أدر العملاء والعلامات والمؤثرين والطلبات والحملات والمحتوى والعقود والمدفوعات والتقارير — في بيئة تشغيل موحّدة بدل الرسائل والملفات المتفرّقة.')</p>
        <div class="ih-auth__benefits">
            @hasSection('benefits')
                @yield('benefits')
            @else
                <x-ih-benefit title="إدارة موحّدة" text="العملاء والعلامات والمؤثرون والطلبات والحملات في بيئة واحدة."/>
                <x-ih-benefit title="أتمتة وسير عمل" text="إجراءات ومراحل تقلّل المتابعة اليدوية والرسائل المتفرّقة."/>
                <x-ih-benefit title="تحليلات وتقارير" text="لوحات أداء لحظية وقياس نتائج الحملات والتعاونات."/>
            @endif
        </div>
        <div style="margin-top:1.6rem; font-size:.78rem; color:#8891A9; display:flex; align-items:center; gap:.5rem;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            بيئة تشغيل آمنة · صلاحيات منظّمة · سجل نشاط
        </div>
    </section>

    {{-- ============ قسم الدخول ============ --}}
    <section class="ih-auth__form">
        <div class="ih-auth__card">
            <a href="/" style="text-decoration:none; color:var(--ih-primary); display:inline-flex;"><x-ih-logo :size="26" color="var(--ih-primary)"/> <span style="font-weight:800; margin-inline-start:.4rem; color:var(--ih-text);">InfluencerHub</span></a>
            <div style="margin:1.2rem 0 .4rem;"><span class="ih-auth__portal-tag">@yield('portal_tag', 'بوابة الوكالة')</span></div>
            <h2 style="font-size:1.35rem; font-weight:800; margin:.6rem 0 .3rem;">@yield('form_title', 'تسجيل الدخول')</h2>
            <p style="color:var(--ih-text-muted); font-size:.88rem; margin:0 0 1.4rem;">@yield('form_sub', 'ادخل إلى حسابك لإدارة عملياتك اليومية.')</p>

            @if($errors->any())
                <div class="card" style="padding:.7rem 1rem; margin-bottom:1rem; border-inline-start:3px solid var(--ih-danger); color:var(--ih-danger-ink); font-size:.85rem; background:var(--ih-danger-soft);">{{ $errors->first() }}</div>
            @endif
            @if(session('status'))
                <div class="card" style="padding:.7rem 1rem; margin-bottom:1rem; border-inline-start:3px solid var(--ih-success); color:var(--ih-success-ink); font-size:.85rem; background:var(--ih-success-soft);">{{ session('status') }}</div>
            @endif

            @yield('form')

            @hasSection('portal_switch')
                <div style="margin-top:1.6rem; padding-top:1.2rem; border-top:1px solid var(--ih-border);">
                    <div style="font-size:.78rem; color:var(--ih-text-muted); margin-bottom:.5rem;">بوابة أخرى؟</div>
                    <div class="ih-portal-switch">@yield('portal_switch')</div>
                </div>
            @endif
        </div>
    </section>
</div>
</body>
</html>
