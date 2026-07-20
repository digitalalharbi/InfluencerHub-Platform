@extends('layouts.app')
@section('title', 'مركز المعاينة')
@section('heading', 'مركز المعاينة — InfluencerHub Preview Center')
@section('content')
@php
    $labels = ['browser_verified'=>['مُتحقَّق بالمتصفّح','#dcfce7','#15803d'], 'ui_ready'=>['واجهة جاهزة','#e0f2fe','#0369a1'],
        'backend_ready'=>['خلفية جاهزة','#fef9c3','#a16207'], 'in_progress'=>['قيد التنفيذ','#f1f5f9','#475569'], 'blocked'=>['لاحقًا','#fee2e2','#b91c1c']];
@endphp
<div class="card" style="padding:1rem 1.2rem; margin-bottom:1rem; color:var(--text-muted); font-size:.85rem;">
    المرحلة الحالية: <b style="color:var(--text);">Phase 6 — طلبات الخدمة (مكتملة)</b> · Phase 5 (بوابتا العميل والشريك) مكتملة ومُتحقَّقة · التالي: Phase 7 (منشئ الحملات). صفحة تطوير فقط (محجوبة في الإنتاج).
</div>
<div style="margin-bottom:1rem;">
    <a href="/app/preview/design-system" class="btn btn-primary btn-sm">🎨 نظام التصميم — Design System</a>
</div>

@if(session('ok'))
    <div class="card" style="padding:.8rem 1rem; margin-bottom:1rem; border-inline-start:3px solid var(--ih-success); background:var(--ih-success-soft); color:var(--ih-success-ink); font-size:.85rem;">{{ session('ok') }}</div>
@endif

{{-- ============ بيانات العرض التجريبية (Showcase) ============ --}}
<div class="card" style="padding:1.2rem 1.4rem; margin-bottom:1.4rem;">
    <div style="display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin-bottom:.4rem;">
        <h3 style="font-weight:800; margin:0; font-size:1.05rem;">بيانات العرض التجريبية — Showcase Data</h3>
        <span class="ih-showcase-badge">● بيانات تجريبية</span>
    </div>
    <p style="color:var(--ih-text-muted); font-size:.83rem; margin:.2rem 0 1rem;">
        بيئة عرض مترابطة على مستأجر مستقل <code>InfluencerHub Showcase Agency</code> (وهمية بوضوح، محلية فقط).
        للتصفّح: سجّل الدخول بحساب <code style="direction:ltr;">showcase_admin@showcase.test</code> — كلمة المرور في
        <code style="direction:ltr;">storage/app/private/showcase-credentials.txt</code>.
    </p>

    @if($showcase['exists'])
        <div style="display:flex; gap:1.2rem; flex-wrap:wrap; margin-bottom:1rem; font-size:.85rem;">
            <span>العملاء: <b>{{ $showcase['clients'] }}</b></span>
            <span>المبدعون: <b>{{ $showcase['creators'] }}</b></span>
            <span>الحملات: <b>{{ $showcase['campaigns'] }}</b></span>
            <span>المحتوى: <b>{{ $showcase['content'] }}</b></span>
        </div>
    @else
        <div style="color:var(--ih-text-muted); font-size:.85rem; margin-bottom:1rem;">لم تُولَّد بعد.</div>
    @endif

    <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.1rem;">
        <form method="POST" action="/app/preview/showcase/seed">@csrf<button class="btn btn-primary btn-sm">{{ $showcase['exists'] ? '↻ إعادة توليد البيانات' : '⚡ توليد البيانات' }}</button></form>
        @if($showcase['exists'])
            <form method="POST" action="/app/preview/showcase/reset" x-data="{ c:false }" @submit="if(!c){$event.preventDefault(); c=true;}">@csrf
                <button class="btn btn-danger btn-sm" x-text="c ? 'اضغط للتأكيد' : 'حذف البيانات'"></button>
            </form>
        @endif
    </div>

    <div style="font-size:.8rem; color:var(--ih-text-muted); margin-bottom:.4rem;">روابط سريعة (بعد الدخول كـ showcase_admin):</div>
    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <a href="/app/clients" class="ih-seg">العملاء</a>
        <a href="/app/clients?seg=vip" class="ih-seg">عملاء VIP</a>
        <a href="/app/clients?seg=incomplete" class="ih-seg">ملفات غير مكتملة</a>
        <a href="/app/creators?seg=tier_a" class="ih-seg">مؤثرون فئة A</a>
        <a href="/app/campaigns?seg=active" class="ih-seg">حملات نشطة</a>
        <a href="/app/campaigns?seg=late" class="ih-seg">حملات متأخرة</a>
        <a href="/app/campaigns?seg=awaiting_client" class="ih-seg">بانتظار العميل</a>
        <a href="/app/service-requests" class="ih-seg">طلبات الخدمة</a>
    </div>
</div>
<div class="card" style="padding:0;">
    <table class="table">
        <thead><tr><th>الوحدة</th><th>المسار</th><th>الدور</th><th>الحالة</th></tr></thead>
        <tbody>
            @foreach($modules as [$name, $path, $role, $status])
                @php [$lbl,$bg,$fg] = $labels[$status]; @endphp
                <tr>
                    <td style="font-weight:600;">{{ $name }}</td>
                    <td>@if($path && str_starts_with($path,'/app') || $path==='/login' || $path && str_starts_with($path,'/api'))<a href="{{ $path }}" style="color:var(--brand); direction:ltr; display:inline-block; text-decoration:none;">{{ $path }}</a>@elseif($path)<span style="direction:ltr; display:inline-block; color:var(--text-muted);">{{ $path }}</span>@else <span style="color:var(--text-muted);">—</span>@endif</td>
                    <td style="color:var(--text-muted); font-size:.82rem;">{{ $role }}</td>
                    <td><span class="badge" style="background:{{ $bg }}; color:{{ $fg }};">{{ $lbl }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
