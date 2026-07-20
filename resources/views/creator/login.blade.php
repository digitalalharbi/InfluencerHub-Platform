@extends('layouts.auth')
@section('title', 'دخول المبدع')
@section('portal_tag', 'بوابة المؤثر وصانع المحتوى')
@section('headline', 'أدر فرصك وتعاوناتك ومستحقاتك باحتراف')
@section('sub', 'بوابة مخصّصة للمؤثرين وصنّاع المحتوى لاستقبال الفرص، إدارة الحسابات والخدمات، رفع الأعمال، متابعة العقود، واستعراض المستحقات.')
@section('benefits')
    <x-ih-benefit icon="rocket" title="فرص وتعاونات" text="استقبل عروض التعاون وتابع حالتها خطوة بخطوة."/>
    <x-ih-benefit icon="file" title="محتوى وعقود" text="ارفع أعمالك وراجع عقودك داخل المنصّة."/>
    <x-ih-benefit icon="chart" title="مستحقاتك" text="تابع مستحقاتك وحالتها بشفافية."/>
@endsection
@section('form_title', 'تسجيل الدخول')
@section('form_sub', 'ادخل لإدارة ملفك وخدماتك وفرصك.')
@section('form')
    <form method="POST" action="/creator/login">@csrf
        <label class="label" for="email">البريد الإلكتروني</label>
        <input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" style="margin-bottom:1rem;">
        <label class="label" for="password">كلمة المرور</label>
        <input id="password" class="field" type="password" name="password" required autocomplete="current-password" style="margin-bottom:1rem;">
        <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--ih-text-secondary); margin-bottom:1.3rem;"><input type="checkbox" name="remember"> تذكّرني</label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">دخول</button>
    </form>
    <div style="text-align:center; margin-top:1rem; font-size:.85rem;"><span style="color:var(--ih-text-muted);">لست مسجّلًا؟</span> <a href="/join/creator" style="color:var(--ih-primary-700); font-weight:700; text-decoration:none;">انضم الآن</a></div>
@endsection
@section('portal_switch')
    <a href="/login">الوكالة</a>
    <a href="/client/login">العميل</a>
    <a href="/partner/login">الوكالة الشريكة</a>
@endsection
