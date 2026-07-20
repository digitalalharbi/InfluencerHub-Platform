@extends('layouts.auth')
@section('title', 'دخول العميل')
@section('portal_tag', 'بوابة العميل')
@section('headline', 'تابع حملاتك وموافقاتك وتقاريرك بكل وضوح')
@section('sub', 'بوابة احترافية لإدارة علاماتك، مراجعة المحتوى واعتماده، متابعة طلباتك، واستعراض التقارير والأداء — من مكان واحد.')
@section('benefits')
    <x-ih-benefit icon="grid" title="علاماتك ومحتواك" text="إدارة العلامات ومراجعة المحتوى واعتماده بوضوح."/>
    <x-ih-benefit icon="file" title="طلبات وعقود" text="أنشئ الطلبات وتابع العقود والفواتير في مكان واحد."/>
    <x-ih-benefit icon="chart" title="تقارير الأداء" text="نتائج الحملات ومؤشرات الأداء بشفافية."/>
@endsection
@section('form_title', 'تسجيل الدخول')
@section('form_sub', 'ادخل إلى بوابة علامتك التجارية.')
@section('form')
    <form method="POST" action="/client/login">@csrf
        <label class="label" for="email">البريد الإلكتروني</label>
        <input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" style="margin-bottom:1rem;">
        <label class="label" for="password">كلمة المرور</label>
        <input id="password" class="field" type="password" name="password" required autocomplete="current-password" style="margin-bottom:1rem;">
        <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--ih-text-secondary); margin-bottom:1.3rem;"><input type="checkbox" name="remember"> تذكّرني</label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">دخول إلى بوابة العميل</button>
    </form>
@endsection
@section('portal_switch')
    <a href="/login">الوكالة</a>
    <a href="/creator/login">المؤثر · صانع المحتوى</a>
    <a href="/partner/login">الوكالة الشريكة</a>
@endsection
