@props([
    'title' => 'InfluencerHub',
    'preheader' => '',
    'locale' => null,
])
@php
    // لغة العرض تُمرَّر صراحةً من الـMailable (لغة المستقبِل)؛ fallback للغة الحالية.
    $locale = $locale ?: app()->getLocale();
    $rtl = $locale === 'ar';
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
    $startBorder = $rtl ? 'border-right' : 'border-left';
    $endBorder = $rtl ? 'border-left' : 'border-right';
    $brand = \App\Support\Brand::name();
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;color:#101828;font-family:Tahoma,Arial,sans-serif;direction:{{ $dir }};text-align:{{ $align }};">
  @if($preheader !== '')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;line-height:1px;font-size:1px;">{{ $preheader }}</div>
  @endif
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#eef2f7;border-collapse:collapse;">
    <tr>
      <td align="center" style="padding:34px 14px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:620px;border-collapse:collapse;">
          <tr>
            <td style="background:#0b1220;border-radius:18px 18px 0 0;padding:24px 28px;text-align:{{ $align }};">
              <table role="presentation" cellspacing="0" cellpadding="0" align="{{ $align }}" style="border-collapse:collapse;">
                <tr>
                  <td style="width:38px;height:38px;vertical-align:middle;{{ $rtl ? 'padding-left' : 'padding-right' }}:10px;">
                    <span style="display:inline-block;width:38px;height:38px;line-height:38px;background:#ffffff;border-radius:11px;text-align:center;">
                      <svg width="30" height="30" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:middle;">
                        <rect x="1.25" y="1.25" width="29.5" height="29.5" rx="8.5" stroke="#6252e5" stroke-width="1.6" opacity=".28"/>
                        <path d="M9 22V10" stroke="#6252e5" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M23 10v12" stroke="#6252e5" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M9 16h14" stroke="#6252e5" stroke-width="2.4" stroke-linecap="round" opacity=".55"/>
                        <circle cx="16" cy="16" r="3.4" fill="#6252e5"/>
                        <circle cx="9" cy="10" r="2" fill="#6252e5"/>
                        <circle cx="23" cy="22" r="2" fill="#6252e5"/>
                      </svg>
                    </span>
                  </td>
                  <td style="vertical-align:middle;text-align:{{ $align }};">
                    <div style="font-size:18px;line-height:1.2;font-weight:800;color:#ffffff;letter-spacing:0;direction:ltr;text-align:{{ $align }};">{{ $brand }}</div>
                    <div style="margin-top:5px;font-size:12px;line-height:1.4;color:#c7d2fe;">{{ \App\Support\Brand::tagline() }}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="background:#ffffff;{{ $startBorder }}:1px solid #e5e7eb;{{ $endBorder }}:1px solid #e5e7eb;padding:32px 30px 30px;text-align:{{ $align }};">
              {{ $slot }}
            </td>
          </tr>
          <tr>
            <td style="background:#ffffff;border-right:1px solid #e5e7eb;border-left:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;border-radius:0 0 18px 18px;padding:0 30px 26px;">
              <div style="border-top:1px solid #edf0f5;padding-top:18px;color:#667085;font-size:12px;line-height:1.8;text-align:center;">
                {{ trans('mail.automated_notice', ['brand' => $brand], $locale) }}
                <div style="margin-top:8px;">
                  <a href="{{ \App\Support\Brand::url() }}/" style="color:#6252e5;text-decoration:none;font-weight:700;direction:ltr;display:inline-block;">{{ \App\Support\Brand::domain() }}</a>
                  <span style="color:#cbd5e1;"> · </span>
                  <a href="mailto:{{ \App\Support\Brand::publicEmail() }}" style="color:#6252e5;text-decoration:none;direction:ltr;display:inline-block;">{{ \App\Support\Brand::publicEmail() }}</a>
                </div>
                <div style="margin-top:6px;">
                  <a href="{{ \App\Support\Brand::privacyUrl() }}" style="color:#667085;text-decoration:none;">{{ trans('mail.footer.privacy', [], $locale) }}</a>
                  <span style="color:#cbd5e1;"> · </span>
                  <a href="{{ \App\Support\Brand::termsUrl() }}" style="color:#667085;text-decoration:none;">{{ trans('mail.footer.terms', [], $locale) }}</a>
                  <span style="color:#cbd5e1;"> · </span>
                  <a href="{{ \App\Support\Brand::helpUrl() }}" style="color:#667085;text-decoration:none;">{{ trans('mail.footer.help', [], $locale) }}</a>
                </div>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
