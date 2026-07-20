<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>قبول دعوة الشريك — إنفلونسر هَب</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="manifest" href="/manifest.webmanifest"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="/icons/ih-icon.svg"><meta name="theme-color" content="#6252E5">
    @vite(['resources/css/app.css','resources/js/app.js'])</head>
<body style="display:flex; align-items:center; justify-content:center; min-height:100vh; background:linear-gradient(180deg,#eef2ff,#f8fafc);">
<div class="card" style="width:min(420px,92vw); padding:2rem;">
    <div style="text-align:center; margin-bottom:1.3rem;"><div style="font-weight:800; font-size:1.25rem; color:var(--brand);">◆ قبول دعوة الشريك</div>
        <div style="color:var(--text-muted); font-size:.85rem; margin-top:.3rem;">أنشئ حسابك للانضمام لبوابة الشريك</div></div>
    @if($errors->any())<div class="card" style="padding:.7rem; margin-bottom:1rem; border-inline-start:3px solid var(--danger); color:#b91c1c; font-size:.85rem;">{{ $errors->first() }}</div>@endif
    <form method="POST" action="/partner/invite/{{ $token }}">@csrf
        <div style="margin-bottom:.9rem;"><label class="label">البريد المدعو</label><input class="field" type="email" value="{{ $email }}" disabled style="direction:ltr; text-align:right; background:#f8fafc;"></div>
        <div style="margin-bottom:.9rem;"><label class="label">الاسم</label><input class="field" name="name" value="{{ old('name') }}" required autofocus></div>
        <div style="margin-bottom:.9rem;"><label class="label">كلمة المرور</label><input class="field" type="password" name="password" required autocomplete="new-password"></div>
        <div style="margin-bottom:1rem;"><label class="label">تأكيد كلمة المرور</label><input class="field" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <div style="color:var(--text-muted); font-size:.76rem; margin-bottom:1rem;">8 أحرف على الأقل، تتضمن أحرفًا وأرقامًا.</div>
        <button class="btn btn-primary" style="width:100%; justify-content:center;">قبول وإنشاء الحساب</button>
    </form>
</div></body></html>
