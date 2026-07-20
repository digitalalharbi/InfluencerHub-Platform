@extends('layouts.auth')
@section('title', 'دخول الوكالة')
@section('portal_tag', 'بوابة الوكالة')
@section('headline', 'أدر عمليات المؤثرين والعملاء من منصّة واحدة')
@section('sub', 'منظومة تشغيل متكاملة لإدارة الطلبات والحملات والمحتوى والعقود والمدفوعات والتقارير والتكاملات — في بيئة موحّدة مصمّمة لوكالات التسويق الحديثة.')
@section('benefits')
    <x-ih-benefit icon="grid" title="إدارة موحّدة" text="العملاء والعلامات والمؤثرون والطلبات والحملات في بيئة واحدة."/>
    <x-ih-benefit icon="zap" title="أتمتة وتشغيل" text="سير عمل بالأحداث وتذكيرات SLA تقلّل المتابعة اليدوية."/>
    <x-ih-benefit icon="chart" title="تحليلات وتقارير" text="لوحات أداء لحظية وقياس نتائج الحملات والتعاونات."/>
    <x-ih-benefit icon="file" title="عقود ومدفوعات" text="إدارة احترافية للعقود والفواتير والمستحقات."/>
@endsection
@section('form_title', 'تسجيل الدخول')
@section('form_sub', 'ادخل إلى مساحة عمل وكالتك.')
@section('form')
    <form method="POST" action="/login">@csrf
        <label class="label" for="email">البريد الإلكتروني</label>
        <input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" style="margin-bottom:1rem;">
        <label class="label" for="password">كلمة المرور</label>
        <input id="password" class="field" type="password" name="password" required autocomplete="current-password" style="margin-bottom:1rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.3rem;">
            <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:var(--ih-text-secondary);"><input type="checkbox" name="remember"> تذكّرني</label>
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">دخول إلى الوكالة</button>
    </form>
@endsection
@section('portal_switch')
    <a href="/client/login">العميل</a>
    <a href="/creator/login">المؤثر · صانع المحتوى</a>
    <a href="/partner/login">الوكالة الشريكة</a>
    <a href="/join">طلب انضمام</a>
@endsection
