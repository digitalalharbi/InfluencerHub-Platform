@php($cur = app()->getLocale())
{{-- مبدّل لغة الواجهة لأسطح Blade (مصادقة/بوّابات) — ينشر إلى /locale ثم يعود للصفحة. --}}
<form method="POST" action="/locale" class="ih-langswitch" role="group" aria-label="{{ __('common.language') }}">
    @csrf
    <button type="submit" name="locale" value="ar" class="{{ $cur === 'ar' ? 'is-active' : '' }}" lang="ar">العربية</button>
    <button type="submit" name="locale" value="en" class="{{ $cur === 'en' ? 'is-active' : '' }}" lang="en">English</button>
</form>
