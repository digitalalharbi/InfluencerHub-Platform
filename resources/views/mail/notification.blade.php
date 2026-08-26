@php
    // القيم الاختياريّة تُمرَّر مُهيّأة من الـMailable؛ نعرض الموجود فقط ولا نختلق حقلًا.
    $meta = $meta ?? [];
    $priority = $priority ?? null;
    $secondary = $secondary ?? null;
    $locale = $locale ?? app()->getLocale();
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
@endphp
<x-mail.layout :title="$title" :locale="$locale" :preheader="$body ? \Illuminate\Support\Str::limit($body, 90) : $title">
  @if($priority)
    <div style="margin:0 0 12px;">
      <span style="display:inline-block;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:800;color:{{ $priority['fg'] }};background:{{ $priority['bg'] }};">{{ $priority['label'] }}</span>
    </div>
  @endif

  <h1 style="margin:0 0 14px;font-size:20px;line-height:1.4;font-weight:800;color:#101828;">{{ $title }}</h1>

  @if($body)
    <p style="margin:0 0 18px;font-size:15px;line-height:1.9;color:#344054;white-space:pre-line;">{{ $body }}</p>
  @endif

  @if(!empty($meta))
    {{-- بطاقة السياق — صفوف الحقول الموجودة فقط (كيان/حالة/طالب/موعد). --}}
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;background:#f8fafc;border:1px solid #eef0f4;border-radius:12px;margin:0 0 20px;">
      @foreach($meta as $row)
        <tr>
          <td style="padding:10px 16px;font-size:12px;color:#667085;white-space:nowrap;vertical-align:top;text-align:{{ $align }};width:34%;">{{ $row['label'] }}</td>
          <td style="padding:10px 16px;font-size:13px;color:#101828;font-weight:600;vertical-align:top;text-align:{{ $align }};">{{ $row['value'] }}</td>
        </tr>
      @endforeach
    </table>
  @endif

  @if($url)
    <x-mail.button :url="$url" :label="$cta" :locale="$locale" />
  @endif

  @if($secondary)
    <p style="margin:14px 0 0;font-size:13px;line-height:1.7;">
      <a href="{{ $secondary['url'] }}" target="_blank" rel="noopener" style="color:#6252e5;text-decoration:none;font-weight:600;">{{ $secondary['label'] }}</a>
    </p>
  @endif
</x-mail.layout>
