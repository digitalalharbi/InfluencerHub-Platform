@props(['code' => '', 'title' => '', 'message' => '', 'action' => 'reload'])
@php($brand = \App\Support\Brand::name())
@php($domain = \App\Support\Brand::domain())
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $brand }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <meta name="theme-color" content="#5B45E0">
    <style>
        :root{--ih-primary:#5B45E0;--ih-ink:#14123A;--ih-muted:#6A6690;--ih-border:#E8E6F7;--ih-bg:#F6F5FF;--ih-card:#fff}
        *{box-sizing:border-box} body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--ih-bg);color:var(--ih-ink);font-family:"IBM Plex Sans Arabic","IBM Plex Sans",system-ui,sans-serif;padding:24px}
        .card{width:min(520px,100%);background:var(--ih-card);border:1px solid var(--ih-border);border-radius:16px;padding:34px 30px;text-align:center;box-shadow:0 24px 70px rgba(15,23,42,.08)}
        .brand{display:inline-flex;align-items:center;gap:.65rem;font-weight:800;margin-bottom:22px}.brand svg{flex:0 0 auto}
        .code{display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:18px;background:#f1efff;color:var(--ih-primary);font-weight:900;font-size:1.35rem;margin-bottom:18px}
        h1{margin:0 0 10px;font-size:clamp(1.8rem,5vw,2.5rem);line-height:1.2}p{margin:0 auto 24px;color:var(--ih-muted);line-height:1.9;max-width:40ch}.actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap}
        a,button{appearance:none;border:0;border-radius:12px;padding:.85rem 1.1rem;font:inherit;font-weight:800;text-decoration:none;cursor:pointer}.primary{background:var(--ih-primary);color:#fff}.ghost{background:#fff;color:var(--ih-ink);border:1px solid var(--ih-border)}
        .foot{margin-top:22px;color:var(--ih-muted);font-size:.8rem}.foot a{padding:0;color:var(--ih-primary);direction:ltr;display:inline-block}
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <svg width="30" height="30" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:block">
                <rect width="100" height="100" rx="23.33" fill="#5B45E0"/>
                <g transform="translate(20 20) scale(.6)">
                    <rect x="14" y="12" width="22" height="76" rx="7" fill="#FFFFFF"/>
                    <rect x="64" y="12" width="22" height="76" rx="7" fill="#FFFFFF"/>
                    <circle cx="50" cy="50" r="11" fill="#22D3EE"/>
                </g>
            </svg>
            <span>إنفلونسر <span style="color:var(--ih-primary)">هب</span></span>
        </div>
        <div class="code">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            @if($action === 'reload')
                <button class="primary" type="button" onclick="window.location.reload()">إعادة المحاولة</button>
            @endif
            <a class="primary" href="/app">الذهاب إلى التطبيق</a>
            <a class="ghost" href="/">العودة للرئيسية</a>
        </div>
        <div class="foot"><a href="https://{{ $domain }}/">{{ $domain }}</a></div>
    </main>
</body>
</html>
