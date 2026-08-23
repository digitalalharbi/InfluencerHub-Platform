<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"></head>
<body style="font-family:dejavusans;direction:rtl;text-align:right;color:#101828;font-size:10pt;">

  <table width="100%" style="border-collapse:collapse;margin-bottom:16px;">
    <tr>
      <td style="width:60%;vertical-align:top;">
        <div style="font-size:17pt;font-weight:800;color:#6252e5;">{{ $workspace }}</div>
        <div style="color:#667085;font-size:9pt;margin-top:2px;">منصّة إدارة عمليات المؤثرين</div>
      </td>
      <td style="width:40%;vertical-align:top;text-align:left;">
        <div style="font-size:15pt;font-weight:800;">فاتورة</div>
        <div style="direction:ltr;color:#475467;font-size:10pt;">{{ $inv['number'] }}</div>
        <div style="margin-top:6px;display:inline-block;padding:3px 10px;border-radius:6px;font-size:9pt;font-weight:700;
          background:{{ $inv['statusColor'][0] }};color:{{ $inv['statusColor'][1] }};">{{ $inv['statusLabel'] }}</div>
      </td>
    </tr>
  </table>

  <table width="100%" style="border-collapse:collapse;margin-bottom:14px;font-size:9pt;">
    <tr>
      <td style="width:50%;vertical-align:top;background:#f6f7fb;padding:10px;border-radius:6px;">
        <div style="color:#667085;">إلى</div>
        <div style="font-weight:700;font-size:10.5pt;">{{ $inv['client'] }}</div>
        @if($inv['brand'])<div style="color:#475467;">العلامة: {{ $inv['brand'] }}</div>@endif
        @if($inv['campaign'])<div style="color:#475467;">الحملة: {{ $inv['campaign'] }}</div>@endif
      </td>
      <td style="width:4%;"></td>
      <td style="width:46%;vertical-align:top;padding:10px;">
        <table width="100%" style="font-size:9pt;">
          <tr><td style="color:#667085;padding:2px 0;">تاريخ الإصدار</td><td style="text-align:left;direction:ltr;">{{ $inv['issueDate'] ?? '—' }}</td></tr>
          <tr><td style="color:#667085;padding:2px 0;">تاريخ الاستحقاق</td><td style="text-align:left;direction:ltr;">{{ $inv['dueDate'] ?? '—' }}</td></tr>
          <tr><td style="color:#667085;padding:2px 0;">العملة</td><td style="text-align:left;direction:ltr;">{{ $inv['currency'] }}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <table width="100%" cellspacing="0" cellpadding="7" style="border-collapse:collapse;font-size:9.5pt;">
    <thead>
      <tr>
        <th style="background:#6252e5;color:#fff;text-align:right;padding:7px;">الوصف</th>
        <th style="background:#6252e5;color:#fff;text-align:center;padding:7px;width:12%;">الكمية</th>
        <th style="background:#6252e5;color:#fff;text-align:left;padding:7px;width:20%;">سعر الوحدة</th>
        <th style="background:#6252e5;color:#fff;text-align:left;padding:7px;width:22%;">الإجمالي</th>
      </tr>
    </thead>
    <tbody>
      @foreach($inv['items'] as $i => $it)
        <tr style="background:{{ $i % 2 ? '#f6f7fb' : '#fff' }};">
          <td style="border:0.5pt solid #e5e7eb;padding:6px;">{{ $it['description'] }}</td>
          <td style="border:0.5pt solid #e5e7eb;padding:6px;text-align:center;direction:ltr;">{{ $it['quantity'] }}</td>
          <td style="border:0.5pt solid #e5e7eb;padding:6px;text-align:left;direction:ltr;">{{ $it['unitPrice'] }}</td>
          <td style="border:0.5pt solid #e5e7eb;padding:6px;text-align:left;direction:ltr;">{{ $it['lineTotal'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table width="100%" style="margin-top:12px;">
    <tr>
      <td style="width:55%;"></td>
      <td style="width:45%;">
        <table width="100%" style="font-size:10pt;">
          <tr><td style="color:#667085;padding:3px 0;">المجموع الفرعي</td><td style="text-align:left;direction:ltr;">{{ $inv['subtotal'] }}</td></tr>
          @if($inv['discount'])<tr><td style="color:#667085;padding:3px 0;">الخصم</td><td style="text-align:left;direction:ltr;color:#b42318;">−{{ $inv['discount'] }}</td></tr>@endif
          <tr><td style="color:#667085;padding:3px 0;">الضريبة ({{ $inv['taxRate'] }})</td><td style="text-align:left;direction:ltr;">{{ $inv['tax'] }}</td></tr>
          <tr><td style="font-weight:800;padding:6px 0;border-top:1.5px solid #101828;font-size:12pt;">الإجمالي</td><td style="text-align:left;direction:ltr;font-weight:800;border-top:1.5px solid #101828;font-size:12pt;">{{ $inv['total'] }}</td></tr>
          @if($inv['paid'])<tr><td style="color:#067647;padding:3px 0;">المدفوع</td><td style="text-align:left;direction:ltr;color:#067647;">{{ $inv['paid'] }}</td></tr>@endif
          @if($inv['outstanding'])<tr><td style="color:#b42318;padding:3px 0;font-weight:700;">المتبقّي</td><td style="text-align:left;direction:ltr;color:#b42318;font-weight:700;">{{ $inv['outstanding'] }}</td></tr>@endif
        </table>
      </td>
    </tr>
  </table>

  @if($inv['notes'])<div style="margin-top:14px;padding:10px;background:#f6f7fb;border-radius:6px;font-size:9pt;color:#475467;"><b>ملاحظات:</b> {{ $inv['notes'] }}</div>@endif
</body>
</html>
