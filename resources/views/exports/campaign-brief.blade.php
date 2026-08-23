<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"></head>
{{-- ملخّص حملة آمن للعميل — لا تكلفة مبدع/هامش/ملاحظات داخلية. --}}
<body style="font-family:dejavusans;direction:rtl;text-align:right;color:#101828;font-size:10pt;">

  <table width="100%" style="border-collapse:collapse;margin-bottom:16px;">
    <tr>
      <td style="width:60%;vertical-align:top;">
        <div style="font-size:17pt;font-weight:800;color:#6252e5;">{{ $workspace }}</div>
        <div style="color:#667085;font-size:9pt;margin-top:2px;">ملخّص الحملة</div>
      </td>
      <td style="width:40%;vertical-align:top;text-align:left;">
        <div style="font-size:14pt;font-weight:800;">{{ $name }}</div>
        <div style="direction:ltr;color:#475467;font-size:10pt;">{{ $number }}</div>
        <div style="margin-top:6px;display:inline-block;padding:3px 10px;border-radius:6px;font-size:9pt;font-weight:700;background:#eef0fb;color:#6252e5;">{{ $statusLabel }}</div>
      </td>
    </tr>
  </table>

  <table width="100%" style="border-collapse:collapse;margin-bottom:14px;font-size:9pt;">
    <tr>
      <td style="width:48%;vertical-align:top;background:#f6f7fb;padding:10px;border-radius:6px;">
        <div style="color:#667085;">العميل</div>
        <div style="font-weight:700;font-size:10.5pt;">{{ $client }}</div>
        @if($brand)<div style="color:#475467;">العلامة: {{ $brand }}</div>@endif
      </td>
      <td style="width:4%;"></td>
      <td style="width:48%;vertical-align:top;padding:10px;">
        <table width="100%" style="font-size:9pt;">
          @if($budget)<tr><td style="color:#667085;padding:2px 0;">الميزانية</td><td style="text-align:left;direction:ltr;font-weight:700;">{{ $budget }}</td></tr>@endif
          <tr><td style="color:#667085;padding:2px 0;">البداية</td><td style="text-align:left;direction:ltr;">{{ $start ?? '—' }}</td></tr>
          <tr><td style="color:#667085;padding:2px 0;">النهاية</td><td style="text-align:left;direction:ltr;">{{ $end ?? '—' }}</td></tr>
          <tr><td style="color:#667085;padding:2px 0;">نسبة الإنجاز</td><td style="text-align:left;direction:ltr;">{{ $progress }}%</td></tr>
        </table>
      </td>
    </tr>
  </table>

  @if($objective)
    <div style="margin-bottom:12px;">
      <div style="font-weight:700;font-size:10.5pt;margin-bottom:3px;">الهدف</div>
      <div style="color:#344054;line-height:1.6;">{{ $objective }}</div>
    </div>
  @endif

  @if($brief)
    <div style="margin-bottom:14px;">
      <div style="font-weight:700;font-size:10.5pt;margin-bottom:3px;">الموجز</div>
      <div style="color:#344054;line-height:1.6;">{{ $brief }}</div>
    </div>
  @endif

  <div style="font-weight:700;font-size:10.5pt;margin-bottom:6px;">المخرجات المتّفق عليها</div>
  <table width="100%" cellspacing="0" cellpadding="7" style="border-collapse:collapse;font-size:9.5pt;">
    <tr style="background:#6252e5;color:#fff;">
      <td style="border:1px solid #e4e7ec;">المنصّة</td>
      <td style="border:1px solid #e4e7ec;">النوع</td>
      <td style="border:1px solid #e4e7ec;text-align:center;">العدد</td>
      <td style="border:1px solid #e4e7ec;direction:ltr;">الاستحقاق</td>
      <td style="border:1px solid #e4e7ec;">الحالة</td>
    </tr>
    @forelse($deliverables as $d)
      <tr>
        <td style="border:1px solid #e4e7ec;">{{ $d['platform'] }}</td>
        <td style="border:1px solid #e4e7ec;">{{ $d['type'] }}</td>
        <td style="border:1px solid #e4e7ec;text-align:center;">{{ $d['quantity'] }}</td>
        <td style="border:1px solid #e4e7ec;text-align:left;direction:ltr;">{{ $d['due'] }}</td>
        <td style="border:1px solid #e4e7ec;">{{ $d['status'] }}</td>
      </tr>
    @empty
      <tr><td colspan="5" style="border:1px solid #e4e7ec;text-align:center;color:#667085;">لا مخرجات مُسجّلة بعد.</td></tr>
    @endforelse
  </table>

  <div style="margin-top:22px;color:#98a2b3;font-size:8pt;text-align:center;">
    {{ $workspace }} · أُنشئ في {{ $generatedAt }}
  </div>
</body>
</html>
