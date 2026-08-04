<x-mail.layout title="رمز التحقق — إنفلونسر هَب" preheader="رمز التحقق الخاص بك في إنفلونسر هَب.">
  <div style="display:inline-block;margin:0 0 16px;padding:7px 12px;background:#f7f5ff;border:1px solid #e8e3ff;border-radius:999px;color:#6252e5;font-size:12px;font-weight:700;">تأكيد الحساب</div>
  <h1 style="margin:0 0 12px;font-size:25px;line-height:1.45;color:#101828;font-weight:800;letter-spacing:0;">رمز التحقق الخاص بك</h1>
  <p style="margin:0 0 24px;font-size:15px;line-height:1.9;color:#475467;">استخدم الرمز التالي لتأكيد بريدك وإكمال طلب الانضمام في إنفلونسر هَب.</p>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 24px;">
    <tr>
      <td align="center" style="background:#f7f5ff;border:1px solid #ded7ff;border-radius:14px;padding:20px 16px;">
        <div style="font-size:34px;font-weight:800;letter-spacing:7px;direction:ltr;color:#33257f;font-family:Arial,Tahoma,sans-serif;line-height:1;">{{ $code }}</div>
      </td>
    </tr>
  </table>

  <p style="margin:0;padding:14px 16px;background:#f8fafc;border:1px solid #edf0f5;border-radius:12px;font-size:13px;line-height:1.8;color:#667085;">الرمز صالح لمدة 10 دقائق فقط. إذا لم تطلب هذا الرمز، تجاهل الرسالة بأمان.</p>
</x-mail.layout>