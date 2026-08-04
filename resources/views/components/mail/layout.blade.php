@props([
    'title' => 'إنفلونسر هَب',
    'preheader' => '',
])
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f4f7fb;color:#101828;font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right;">
  @if($preheader !== '')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;line-height:1px;font-size:1px;">{{ $preheader }}</div>
  @endif
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;border-collapse:collapse;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;border-collapse:collapse;">
          <tr>
            <td style="padding:0 4px 14px;">
              <div style="display:inline-flex;align-items:center;gap:10px;color:#6252e5;font-weight:800;font-size:18px;line-height:1;">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;vertical-align:middle;">
                  <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="1.25" y="1.25" width="29.5" height="29.5" rx="8.5" stroke="#6252e5" stroke-width="1.6" opacity=".28"/>
                    <path d="M9 22V10" stroke="#6252e5" stroke-width="2.4" stroke-linecap="round"/>
                    <path d="M23 10v12" stroke="#6252e5" stroke-width="2.4" stroke-linecap="round"/>
                    <path d="M9 16h14" stroke="#6252e5" stroke-width="2.4" stroke-linecap="round" opacity=".55"/>
                    <circle cx="16" cy="16" r="3.4" fill="#6252e5"/>
                    <circle cx="9" cy="10" r="2" fill="#6252e5"/>
                    <circle cx="23" cy="22" r="2" fill="#6252e5"/>
                  </svg>
                </span>
                <span style="display:inline-block;color:#111827;vertical-align:middle;">إنفلونسر هَب</span>
              </div>
            </td>
          </tr>
          <tr>
            <td style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:30px;box-shadow:0 18px 45px rgba(15,23,42,.08);">
              {{ $slot }}
            </td>
          </tr>
          <tr>
            <td style="padding:16px 4px 0;color:#667085;font-size:12px;line-height:1.8;text-align:center;">
              هذه رسالة آلية من إنفلونسر هَب. يمكنك تجاهلها إذا لم تطلب ذلك.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>