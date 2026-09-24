@php($cur = app()->getLocale())
{{-- مبدّل لغة الواجهة لأسطح Blade — أزرار type="button" (لا submit) كي لا تتصادم مع
     محدّد زرّ الإرسال في صفحات الدخول؛ تُرسِل النموذج بجافاسكربت بعد ضبط اللغة المختارة. --}}
<form method="POST" action="/locale" class="ih-langswitch" role="group" aria-label="{{ __('common.language') }}">
    @csrf
    <input type="hidden" name="locale" value="{{ $cur }}">
    <button type="button" class="{{ $cur === 'ar' ? 'is-active' : '' }}" lang="ar"
        onclick="this.closest('form').querySelector('input[name=locale]').value='ar';this.closest('form').submit();">العربية</button>
    <button type="button" class="{{ $cur === 'en' ? 'is-active' : '' }}" lang="en"
        onclick="this.closest('form').querySelector('input[name=locale]').value='en';this.closest('form').submit();">English</button>
</form>
