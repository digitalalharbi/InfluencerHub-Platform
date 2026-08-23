<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"></head>
<body style="font-family:dejavusans;direction:rtl;text-align:right;color:#101828;font-size:9pt;">
  <h2 style="margin:0 0 4px;font-size:15pt;color:#101828;">{{ $data->title }}</h2>
  @if($data->generatedAt)
    <div style="color:#667085;font-size:8pt;margin-bottom:6px;">تاريخ التوليد: {{ $data->generatedAt }}</div>
  @endif

  @if(!empty($data->meta))
    <table style="margin-bottom:10px;font-size:8.5pt;color:#475467;">
      @foreach($data->meta as $k => $v)
        <tr><td style="padding:1px 8px 1px 0;font-weight:bold;">{{ $k }}:</td><td style="padding:1px 0;">{{ $v }}</td></tr>
      @endforeach
    </table>
  @endif

  <table width="100%" cellspacing="0" cellpadding="5" style="border-collapse:collapse;font-size:8.5pt;">
    <thead>
      <tr>
        @foreach($data->headings() as $h)
          <th style="background:#6252e5;color:#fff;border:0.5pt solid #4c3fce;text-align:right;padding:5px 7px;">{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach($data->rows as $i => $row)
        <tr style="background:{{ $i % 2 ? '#f6f7fb' : '#ffffff' }};">
          @foreach($data->rowValues($row) as $v)
            <td style="border:0.5pt solid #e5e7eb;padding:4px 7px;text-align:right;">{{ $v }}</td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
