@extends('layouts.public')
@section('title', 'استعادة الوصول')
@section('content')
<div style="margin-bottom:1.2rem;">
    <h1 style="font-size:1.5rem; font-weight:800; margin:.3rem 0;">استعادة الوصول لطلبك</h1>
    <p style="color:var(--text-muted); font-size:.9rem;">أدخل بريدك المستخدم في التقديم؛ سنرسل رابط وصول آمنًا إن وُجد طلب.</p>
</div>
@if(session('dev_recover_link'))
    <div class="card" style="padding:.7rem .9rem; margin-bottom:1rem; background:#fffbeb; border:1px dashed #d97706; font-size:.82rem;">
        (وضع تطوير) رابط الاستعادة: <a href="{{ session('dev_recover_link') }}" style="direction:ltr; color:var(--brand);">{{ session('dev_recover_link') }}</a>
    </div>
@endif
<form method="POST" action="/join/recover" class="card" style="padding:1.5rem;">@csrf
    <div style="margin-bottom:1.2rem;"><label class="label">البريد الإلكتروني</label><input class="field" type="email" name="email" required></div>
    <button class="btn btn-primary">إرسال رابط الاستعادة</button>
</form>
@endsection
