<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>انتهت الجلسة — إنفلونسر هَب</title>
    <style>
        :root{--ih-primary:#6d5df6;--ih-ink:#111827;--ih-muted:#64748b;--ih-border:#e2e8f0;--ih-bg:#f8fafc;--ih-card:#fff}
        *{box-sizing:border-box} body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--ih-bg);color:var(--ih-ink);font-family:"IBM Plex Sans Arabic",Inter,system-ui,sans-serif;padding:24px}
        .card{width:min(520px,100%);background:var(--ih-card);border:1px solid var(--ih-border);border-radius:16px;padding:34px 30px;text-align:center;box-shadow:0 24px 70px rgba(15,23,42,.08)}
        .brand{display:inline-flex;align-items:center;gap:.65rem;font-weight:800;margin-bottom:22px}.brand svg{flex:0 0 auto}
        .code{display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:18px;background:#f1efff;color:var(--ih-primary);font-weight:900;font-size:1.35rem;margin-bottom:18px}
        h1{margin:0 0 10px;font-size:clamp(1.8rem,5vw,2.5rem);line-height:1.2}p{margin:0 auto 24px;color:var(--ih-muted);line-height:1.9;max-width:38ch}.actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap}
        a,button{appearance:none;border:0;border-radius:12px;padding:.85rem 1.1rem;font:inherit;font-weight:800;text-decoration:none;cursor:pointer}.primary{background:var(--ih-primary);color:#fff}.ghost{background:#fff;color:var(--ih-ink);border:1px solid var(--ih-border)}
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <svg width="28" height="28" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect x="1.25" y="1.25" width="29.5" height="29.5" rx="8.5" stroke="var(--ih-primary)" stroke-width="1.6" opacity=".28"/>
                <path d="M9 22V10" stroke="var(--ih-primary)" stroke-width="2.4" stroke-linecap="round"/>
                <path d="M23 10v12" stroke="var(--ih-primary)" stroke-width="2.4" stroke-linecap="round"/>
                <path d="M9 16h14" stroke="var(--ih-primary)" stroke-width="2.4" stroke-linecap="round" opacity=".55"/>
                <circle cx="16" cy="16" r="3.4" fill="var(--ih-primary)"/>
                <circle cx="9" cy="10" r="2" fill="var(--ih-primary)"/>
                <circle cx="23" cy="22" r="2" fill="var(--ih-primary)"/>
            </svg>
            <span>إنفلونسر هَب</span>
        </div>
        <div class="code">419</div>
        <h1>انتهت الجلسة</h1>
        <p>حفاظًا على أمان حسابك، انتهت صلاحية الصفحة أو رمز الحماية. حدّث الصفحة ثم أعد تنفيذ العملية.</p>
        <div class="actions">
            <button class="primary" type="button" onclick="window.location.reload()">إعادة المحاولة</button>
            <a class="ghost" href="/">العودة للرئيسية</a>
        </div>
    </main>
</body>
</html>