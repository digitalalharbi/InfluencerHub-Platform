<x-mail.layout title="بخصوص طلبك — إنفلونسر هَب" preheader="تمت مراجعة طلبك في إنفلونسر هَب.">
  <h1 style="margin:0 0 10px;font-size:24px;line-height:1.35;color:#111827;font-weight:800;">مرحبًا {{ $name }}</h1>
  <p style="margin:0 0 18px;font-size:15px;line-height:1.9;color:#475467;">راجعنا طلبك لفتح مساحة «{{ $company }}» ولم نتمكن من اعتماده حاليًا.</p>
  <div style="margin:0 0 22px;padding:16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;color:#9a3412;font-size:14px;line-height:1.8;">
    <strong style="display:block;margin-bottom:4px;color:#7c2d12;">السبب</strong>
    {{ $reason }}
  </div>
  <p style="margin:0;font-size:13px;line-height:1.8;color:#667085;">يسعدنا تواصلك معنا إذا رغبت بمراجعة القرار أو إرسال بيانات إضافية.</p>
</x-mail.layout>