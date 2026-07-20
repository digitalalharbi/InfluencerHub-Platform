@extends('layouts.public')
@section('title', 'الانضمام')
@section('content')
<div style="text-align:center; margin-bottom:2rem;">
    <h1 style="font-size:1.8rem; font-weight:800; margin:.5rem 0;">انضم إلى شبكة المبدعين</h1>
    <p style="color:var(--text-muted);">قدّم طلبك للانضمام كمؤثّر أو صانع محتوى UGC. تُراجع الطلبات من فريق الوكالة.</p>
</div>
<div class="card" style="padding:2rem; text-align:center;">
    <div style="font-size:2.5rem;">🎬</div>
    <h2 style="font-weight:800; margin:.6rem 0;">مبدع (مؤثّر / صانع UGC)</h2>
    <p style="color:var(--text-muted); margin-bottom:1.2rem;">أنشئ ملفك، أضِف منصّاتك وخدماتك وأسعارك، وارفع نماذج أعمالك.</p>
    <a href="/join/creator" class="btn btn-primary" style="font-size:1rem;">ابدأ طلب الانضمام ←</a>
</div>
<p style="text-align:center; color:var(--text-muted); font-size:.85rem; margin-top:1.5rem;">
    لديك طلب سابق؟ استخدم رابط المتابعة الذي حصلت عليه عند التقديم.
</p>
@endsection
