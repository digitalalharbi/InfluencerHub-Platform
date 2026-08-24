<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"></head>
{{-- مستند عقد — RTL عربي. --}}
<body style="font-family:dejavusans;direction:rtl;text-align:right;color:#101828;font-size:10pt;line-height:1.6;">

  <table width="100%" style="border-collapse:collapse;margin-bottom:16px;">
    <tr>
      <td style="width:60%;vertical-align:top;">
        <div style="font-size:17pt;font-weight:800;color:#6252e5;">{{ $workspace }}</div>
        <div style="color:#667085;font-size:9pt;margin-top:2px;">عقد</div>
      </td>
      <td style="width:40%;vertical-align:top;text-align:left;">
        <div style="font-size:13pt;font-weight:800;">{{ $title }}</div>
        <div style="direction:ltr;color:#475467;font-size:10pt;">{{ $number }}</div>
        <div style="margin-top:6px;display:inline-block;padding:3px 10px;border-radius:6px;font-size:9pt;font-weight:700;background:{{ $statusColor[0] }};color:{{ $statusColor[1] }};">{{ $statusLabel }}</div>
      </td>
    </tr>
  </table>

  <table width="100%" style="border-collapse:collapse;margin-bottom:14px;font-size:9pt;">
    <tr>
      <td style="width:50%;vertical-align:top;background:#f6f7fb;padding:10px;border-radius:6px;">
        <div style="color:#667085;">الطرف ({{ $partyType }})</div>
        <div style="font-weight:700;font-size:10.5pt;">{{ $party }}</div>
        @if($campaign)<div style="color:#475467;">الحملة: {{ $campaign }}</div>@endif
      </td>
      <td style="width:4%;"></td>
      <td style="width:46%;vertical-align:top;padding:10px;">
        <table width="100%" style="font-size:9pt;">
          @if($value)<tr><td style="color:#667085;padding:2px 0;">القيمة</td><td style="text-align:left;direction:ltr;font-weight:700;">{{ $value }}</td></tr>@endif
          <tr><td style="color:#667085;padding:2px 0;">البداية</td><td style="text-align:left;direction:ltr;">{{ $start ?? '—' }}</td></tr>
          <tr><td style="color:#667085;padding:2px 0;">النهاية</td><td style="text-align:left;direction:ltr;">{{ $end ?? '—' }}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  @if($terms)
    <div style="margin-bottom:14px;">
      <div style="font-weight:700;font-size:10.5pt;margin-bottom:4px;">البنود</div>
      <div style="color:#344054;white-space:pre-wrap;">{{ $terms }}</div>
    </div>
  @endif

  <table width="100%" style="border-collapse:collapse;margin-top:26px;font-size:9pt;">
    <tr>
      <td style="width:48%;border-top:1px solid #98a2b3;padding-top:6px;">
        <div style="color:#667085;">الوكالة</div>
        <div style="font-weight:700;">{{ $workspace }}</div>
      </td>
      <td style="width:4%;"></td>
      <td style="width:48%;border-top:1px solid #98a2b3;padding-top:6px;">
        <div style="color:#667085;">التوقيع{{ $signedBy ? ' — '.$signedBy : '' }}</div>
        <div style="font-weight:700;">{{ $party }}</div>
        @if($signedAt)<div style="color:#475467;direction:ltr;text-align:right;">وُقّع: {{ $signedAt }}</div>@endif
      </td>
    </tr>
  </table>

  <div style="margin-top:22px;color:#98a2b3;font-size:8pt;text-align:center;">{{ $workspace }} · أُنشئ في {{ $generatedAt }}</div>
</body>
</html>
