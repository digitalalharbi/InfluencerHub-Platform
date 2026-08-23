<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"></head>
{{-- مقترح ترشيحات آمن للعميل — لا درجة مطابقة/أسباب داخلية/تكلفة مبدع/تواصل خاص. --}}
<body style="font-family:dejavusans;direction:rtl;text-align:right;color:#101828;font-size:10pt;">

  <table width="100%" style="border-collapse:collapse;margin-bottom:16px;">
    <tr>
      <td style="width:60%;vertical-align:top;">
        <div style="font-size:17pt;font-weight:800;color:#6252e5;">{{ $workspace }}</div>
        <div style="color:#667085;font-size:9pt;margin-top:2px;">مقترح المؤثرين</div>
      </td>
      <td style="width:40%;vertical-align:top;text-align:left;">
        <div style="font-size:13pt;font-weight:800;">{{ $campaign }}</div>
        <div style="direction:ltr;color:#475467;font-size:10pt;">{{ $number }} · إصدار {{ $versionNo }}</div>
      </td>
    </tr>
  </table>

  <table width="100%" style="border-collapse:collapse;margin-bottom:14px;font-size:9pt;background:#f6f7fb;">
    <tr>
      <td style="padding:10px;">
        <span style="color:#667085;">العميل:</span> <span style="font-weight:700;">{{ $client }}</span>
        @if($brand)<span style="color:#667085;margin-inline-start:14px;">العلامة:</span> <span style="font-weight:700;">{{ $brand }}</span>@endif
      </td>
    </tr>
  </table>

  <table width="100%" cellspacing="0" cellpadding="7" style="border-collapse:collapse;font-size:9.5pt;">
    <tr style="background:#6252e5;color:#fff;">
      <td style="border:1px solid #e4e7ec;">المؤثر</td>
      <td style="border:1px solid #e4e7ec;direction:ltr;">الحساب</td>
      <td style="border:1px solid #e4e7ec;">المنصّة</td>
      <td style="border:1px solid #e4e7ec;text-align:center;">الجمهور</td>
      <td style="border:1px solid #e4e7ec;text-align:center;">السعر المقترح</td>
      <td style="border:1px solid #e4e7ec;">القرار</td>
    </tr>
    @forelse($items as $it)
      <tr @if($it['backup'])style="background:#fafafa;color:#667085;"@endif>
        <td style="border:1px solid #e4e7ec;">
          {{ $it['creator'] }}
          @if($it['backup'])<span style="font-size:7.5pt;background:#eef0fb;color:#6252e5;padding:1px 5px;border-radius:4px;">احتياطي</span>@endif
        </td>
        <td style="border:1px solid #e4e7ec;text-align:left;direction:ltr;">{{ $it['handle'] ? '@'.$it['handle'] : '—' }}</td>
        <td style="border:1px solid #e4e7ec;">{{ $it['platform'] }}</td>
        <td style="border:1px solid #e4e7ec;text-align:center;direction:ltr;">{{ $it['followers'] }}</td>
        <td style="border:1px solid #e4e7ec;text-align:center;direction:ltr;font-weight:700;">{{ $it['fee'] }}</td>
        <td style="border:1px solid #e4e7ec;">{{ $it['decision'] }}</td>
      </tr>
    @empty
      <tr><td colspan="6" style="border:1px solid #e4e7ec;text-align:center;color:#667085;">لا مؤثرين في هذا المقترح بعد.</td></tr>
    @endforelse
  </table>

  <div style="margin-top:14px;color:#667085;font-size:8.5pt;line-height:1.6;">
    الأسعار المقترحة شاملة، وقابلة للاعتماد أو الاستبدال حسب قراركم. المؤثرون الاحتياطيون بدائل جاهزة عند الحاجة.
  </div>

  <div style="margin-top:20px;color:#98a2b3;font-size:8pt;text-align:center;">
    {{ $workspace }} · أُنشئ في {{ $generatedAt }}
  </div>
</body>
</html>
