@extends('layouts.auth')
@section('title', 'دخول الشريك')
@section('portal_tag', 'بوابة الوكالة الشريكة')
@section('headline', 'أدر عملك المشترك مع الوكالة بكفاءة ووضوح')
@section('sub', 'بوابة مخصّصة للشركاء لمتابعة العملاء المرتبطين والمهام والمحتوى والمستندات ضمن إطار صلاحيات منظّم.')
@section('benefits')
    <x-ih-benefit icon="grid" title="عملاء مرتبطون" text="تابع العملاء والعلامات المرتبطة بك بنطاقات واضحة."/>
    <x-ih-benefit icon="zap" title="طلبات ومهام" text="أنشئ الطلبات وتابع المهام والمحتوى بكفاءة."/>
    <x-ih-benefit icon="plug" title="صلاحيات منظّمة" text="وصول مُنطّق آمن لما صُرّح لك به فقط."/>
@endsection
@section('form_title', 'تسجيل الدخول')
@section('form_sub', 'ادخل إلى بوابة الوكالة الشريكة.')
@section('form')
    <form method="POST" action="/partner/login">@csrf
        <label class="label" for="email">البريد الإلكتروني</label>
        <input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" style="margin-bottom:1rem;">
        <label class="label" for="password">كلمة المرور</label>
        <input id="password" class="field" type="password" name="password" required autocomplete="current-password" style="margin-bottom:1rem;">
        <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--ih-text-secondary); margin-bottom:1.3rem;"><input type="checkbox" name="remember"> تذكّرني</label>
        <button type="submit" class="btn btn-primary btn-lg btn-block">دخول إلى بوابة الشريك</button>
    </form>
@endsection
@section('portal_switch')
    <a href="/login">الوكالة</a>
    <a href="/client/login">العميل</a>
    <a href="/creator/login">المؤثر · صانع المحتوى</a>
@endsection
