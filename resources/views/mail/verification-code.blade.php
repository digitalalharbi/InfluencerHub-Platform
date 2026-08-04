<x-mail.layout title="تأكيد البريد — إنفلونسر هَب" preheader="أكد بريدك الإلكتروني للمتابعة في إنفلونسر هَب.">
  <h1 style="margin:0 0 10px;font-size:24px;line-height:1.35;color:#111827;font-weight:800;">تأكيد البريد الإلكتروني</h1>
  <p style="margin:0 0 22px;font-size:15px;line-height:1.9;color:#475467;">اضغط على الزر التالي لتأكيد بريدك الإلكتروني في إنفلونسر هَب.</p>
  <p style="margin:0 0 24px;text-align:center;">
    <a href="{{ $verifyUrl }}" style="display:inline-block;background:#6252e5;color:#ffffff;text-decoration:none;font-weight:800;border-radius:10px;padding:14px 28px;">تأكيد البريد</a>
  </p>
  <p style="margin:0 0 10px;font-size:14px;line-height:1.8;color:#475467;">يمكنك أيضًا إدخال رمز التحقق يدويًا:</p>
  <div style="margin:0 0 20px;padding:16px;background:#f7f5ff;border:1px solid #ddd6fe;border-radius:12px;text-align:center;">
    <div style="font-size:30px;font-weight:800;letter-spacing:6px;direction:ltr;color:#33257f;font-family:Arial,Tahoma,sans-serif;">{{ $code }}</div>
  </div>
  <p style="margin:0;font-size:13px;line-height:1.8;color:#667085;">الرابط والرمز صالحان لمدة محدودة.</p>
</x-mail.layout>