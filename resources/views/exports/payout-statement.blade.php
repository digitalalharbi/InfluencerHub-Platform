<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"></head>
{{-- كشف مستحق مبدع — مستند مالي داخلي (RBAC مالية). --}}
<body style="font-family:dejavusans;direction:rtl;text-align:right;color:#101828;font-size:10pt;">

  <table width="100%" style="border-collapse:collapse;margin-bottom:16px;">
    <tr>
      <td style="width:60%;vertical-align:top;">
        <div style="font-size:17pt;font-weight:800;color:#6252e5;">{{ $workspace }}</div>
        <div style="color:#667085;font-size:9pt;margin-top:2px;">كشف مستحق مبدع</div>
      </td>
      <td style="width:40%;vertical-align:top;text-align:left;">
        <div style="font-size:14pt;font-weight:800;">مستحق</div>
        <div style="direction:ltr;color:#475467;font-size:10pt;">{{ $number }}</div>
        <div style="margin-top:6px;display:inline-block;padding:3px 10px;border-radius:6px;font-size:9pt;font-weight:700;background:{{ $statusColor[0] }};color:{{ $statusColor[1] }};">{{ $statusLabel }}</div>
      </td>
    </tr>
  </table>

  <table width="100%" style="border-collapse:collapse;margin-bottom:14px;font-size:9pt;">
    <tr>
      <td style="width:50%;vertical-align:top;background:#f6f7fb;padding:10px;border-radius:6px;">
        <div style="color:#667085;">المستفيد</div>
        <div style="font-weight:700;font-size:10.5pt;">{{ $creator }}</div>
        @if($campaign)<div style="color:#475467;">الحملة: {{ $campaign }}</div>@endif
        @if($iban4)<div style="color:#475467;direction:ltr;text-align:right;">IBAN •••• {{ $iban4 }}</div>@endif
      </td>
      <td style="width:4%;"></td>
      <td style="width:46%;vertical-align:top;padding:10px;">
        <table width="100%" style="font-size:9pt;">
          <tr><td style="color:#667085;padding:2px 0;">الاستحقاق</td><td style="text-align:left;direction:ltr;">{{ $due ?? '—' }}</td></tr>
          <tr><td style="color:#667085;padding:2px 0;">تاريخ الصرف</td><td style="text-align:left;direction:ltr;">{{ $paid ?? '—' }}</td></tr>
          <tr><td style="color:#667085;padding:2px 0;">العملة</td><td style="text-align:left;direction:ltr;">{{ $currency }}</td></tr>
          @if($reference)<tr><td style="color:#667085;padding:2px 0;">مرجع الدفع</td><td style="text-align:left;direction:ltr;">{{ $reference }}</td></tr>@endif
        </table>
      </td>
    </tr>
  </table>

  @if($description)
    <div style="margin-bottom:12px;"><div style="color:#667085;font-size:9pt;">الوصف</div><div style="color:#344054;line-height:1.6;">{{ $description }}</div></div>
  @endif

  <table width="100%" cellspacing="0" cellpadding="10" style="border-collapse:collapse;margin-top:8px;">
    <tr>
      <td style="background:#6252e5;color:#fff;font-size:12pt;font-weight:800;border-radius:6px;">
        المبلغ المستحق
        <span style="float:left;direction:ltr;">{{ $amount }}</span>
      </td>
    </tr>
  </table>

  @if($failure)
    <div style="margin-top:12px;padding:9px 11px;background:#fef3f2;color:#b42318;border-radius:6px;font-size:9pt;">سبب الفشل: {{ $failure }}</div>
  @endif

  <div style="margin-top:22px;color:#98a2b3;font-size:8pt;text-align:center;">{{ $workspace }} · أُنشئ في {{ $generatedAt }}</div>
</body>
</html>
