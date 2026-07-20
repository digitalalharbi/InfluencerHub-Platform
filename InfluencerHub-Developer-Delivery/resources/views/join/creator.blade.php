@extends('layouts.public')
@section('title', 'طلب انضمام مبدع')
@section('content')
<div style="margin-bottom:1.2rem;">
    <div style="color:var(--brand); font-weight:700; font-size:.85rem;">الخطوة 1 من 5</div>
    <h1 style="font-size:1.5rem; font-weight:800; margin:.3rem 0;">البيانات الأساسية</h1>
    <p style="color:var(--text-muted); font-size:.9rem;">سيُحفظ طلبك كمسودة، ويمكنك استكماله لاحقًا عبر رابط المتابعة.</p>
</div>
<form method="POST" action="/join/creator{{ $slug ? '?a='.$slug : '' }}" class="card" style="padding:1.5rem;">@csrf
    {{-- اختيار متعدّد: الصانع يجمع قدرات ولا يُجبَر على هوية واحدة. --}}
    @php $chosen = (array) old('capabilities', []); @endphp
    <div style="margin-bottom:1.1rem;">
        <label class="label">ماذا تقدّم؟ *</label>
        <p style="color:var(--text-muted); font-size:.82rem; margin:.15rem 0 .6rem;">اختر كل ما ينطبق عليك — قدرة واحدة على الأقل.</p>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:.5rem;">
            @foreach ($capabilityOptions as $key => $label)
                <label style="display:flex; align-items:center; gap:.5rem; padding:.6rem .75rem; border:1px solid var(--border); border-radius:.6rem; cursor:pointer; font-size:.88rem;">
                    <input type="checkbox" name="capabilities[]" value="{{ $key }}"
                           @checked(in_array($key, $chosen, true))
                           style="width:1rem; height:1rem; flex:none;">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('capabilities')<div style="color:var(--danger, #c0392b); font-size:.8rem; margin-top:.4rem;">{{ $message }}</div>@enderror
        @error('capabilities.*')<div style="color:var(--danger, #c0392b); font-size:.8rem; margin-top:.4rem;">{{ $message }}</div>@enderror
    </div>
    <div style="margin-bottom:1.1rem;"><label class="label">الاسم الكامل *</label><input class="field" name="full_name" value="{{ old('full_name') }}" required></div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.1rem;">
        <div><label class="label">البريد الإلكتروني *</label><input class="field" type="email" name="email" value="{{ old('email') }}" required></div>
        <div><label class="label">رقم الجوال *</label><input class="field" name="phone" value="{{ old('phone') }}" placeholder="+9665xxxxxxxx" required style="direction:ltr;"></div>
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.3rem;">
        <div><label class="label">الدولة</label>
            <select class="field" name="country_code">
                <option value="SA">السعودية</option><option value="AE">الإمارات</option><option value="KW">الكويت</option>
                <option value="QA">قطر</option><option value="BH">البحرين</option><option value="OM">عُمان</option><option value="EG">مصر</option>
            </select>
        </div>
        <div><label class="label">المدينة</label><input class="field" name="city" value="{{ old('city') }}"></div>
    </div>
    <div style="border-top:1px solid var(--border); padding-top:1.1rem; margin-bottom:1.3rem;">
        <label style="display:flex; gap:.5rem; align-items:flex-start; font-size:.88rem; margin-bottom:.6rem;">
            <input type="checkbox" name="terms" value="1" required style="margin-top:.2rem;"> أوافق على <a href="#" style="color:var(--brand);">الشروط والأحكام</a>.
        </label>
        <label style="display:flex; gap:.5rem; align-items:flex-start; font-size:.88rem;">
            <input type="checkbox" name="privacy" value="1" required style="margin-top:.2rem;"> أوافق على <a href="#" style="color:var(--brand);">سياسة الخصوصية</a>.
        </label>
    </div>
    <button type="submit" class="btn btn-primary" style="font-size:1rem;">إنشاء الطلب ومتابعة ←</button>
</form>
@endsection
