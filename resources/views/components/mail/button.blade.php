@props([
    'url',
    'label',
    'fallback' => true,
    'locale' => null,
])
@php $locale = $locale ?: app()->getLocale(); @endphp
{{-- زرّ الإجراء الأساسي الموحّد للبريد — مصدر واحد بدل تكراره في كل قالب. --}}
<table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:6px 0 4px;">
  <tr>
    <td style="border-radius:10px;background:#6252e5;">
      <a href="{{ $url }}" target="_blank" rel="noopener"
         style="display:inline-block;padding:12px 26px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">
        {{ $label }}
      </a>
    </td>
  </tr>
</table>
@if($fallback)
  <p style="margin:16px 0 0;font-size:12px;line-height:1.7;color:#98a2b3;">
    {{ trans('mail.fallback_hint', [], $locale) }}<br>
    <span style="direction:ltr;display:inline-block;color:#6252e5;word-break:break-all;">{{ $url }}</span>
  </p>
@endif
