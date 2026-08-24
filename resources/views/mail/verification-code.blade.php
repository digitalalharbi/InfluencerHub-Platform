<x-mail.layout title="تأكيد البريد — InfluencerHub" preheader="أكد بريدك الإلكتروني للمتابعة في InfluencerHub.">
  <div style="display:inline-block;margin:0 0 16px;padding:7px 12px;background:#f7f5ff;border:1px solid #e8e3ff;border-radius:999px;color:#6252e5;font-size:12px;font-weight:700;">تفعيل البريد</div>
  <h1 style="margin:0 0 12px;font-size:25px;line-height:1.45;color:#101828;font-weight:800;letter-spacing:0;">أكد بريدك الإلكتروني</h1>
  <p style="margin:0 0 24px;font-size:15px;line-height:1.9;color:#475467;">اضغط على الزر التالي لتأكيد بريدك الإلكتروني ومتابعة إعداد حسابك في InfluencerHub.</p>

  <p style="margin:0 0 24px;text-align:center;">
    <a href="{{ $verifyUrl }}" style="display:inline-block;background:#6252e5;color:#ffffff;text-decoration:none;font-weight:800;border-radius:10px;padding:14px 30px;min-width:160px;text-align:center;">تأكيد البريد</a>
  </p>

  <p style="margin:0 0 10px;font-size:14px;line-height:1.8;color:#475467;">أو أدخل رمز التحقق يدويًا:</p>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 22px;">
    <tr>
      <td align="center" style="background:#f7f5ff;border:1px solid #ded7ff;border-radius:14px;padding:18px 16px;">
        <div style="font-size:32px;font-weight:800;letter-spacing:7px;direction:ltr;color:#33257f;font-family:Arial,Tahoma,sans-serif;line-height:1;">{{ $code }}</div>
      </td>
    </tr>
  </table>

  <p style="margin:0;padding:14px 16px;background:#f8fafc;border:1px solid #edf0f5;border-radius:12px;font-size:13px;line-height:1.8;color:#667085;">الرابط والرمز صالحان لمدة محدودة. إذا لم تطلب التفعيل، يمكنك تجاهل الرسالة.</p>
</x-mail.layout>