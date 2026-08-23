<x-mail.layout :title="$title" :preheader="$body ? \Illuminate\Support\Str::limit($body, 90) : $title">
  <h1 style="margin:0 0 14px;font-size:20px;line-height:1.4;font-weight:800;color:#101828;">{{ $title }}</h1>

  @if($body)
    <p style="margin:0 0 20px;font-size:15px;line-height:1.9;color:#344054;white-space:pre-line;">{{ $body }}</p>
  @endif

  @if($url)
    <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:6px 0 4px;">
      <tr>
        <td style="border-radius:10px;background:#6252e5;">
          <a href="{{ $url }}" target="_blank" rel="noopener"
             style="display:inline-block;padding:12px 26px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">
            {{ $cta }}
          </a>
        </td>
      </tr>
    </table>
    <p style="margin:16px 0 0;font-size:12px;line-height:1.7;color:#98a2b3;">
      إن لم يعمل الزر، انسخ هذا الرابط في المتصفّح:<br>
      <span style="direction:ltr;display:inline-block;color:#6252e5;word-break:break-all;">{{ $url }}</span>
    </p>
  @endif
</x-mail.layout>
