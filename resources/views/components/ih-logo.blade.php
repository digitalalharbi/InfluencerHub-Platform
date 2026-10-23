@props([
    'size' => 28,
    'color' => 'currentColor',   // لون نصّ الكلمة الأساسي
    'hubColor' => 'currentColor', // «هب/Hub» موحّد مع الكلمة (IDENTITY-02 — لا لون منفصل)
    'withWordmark' => false,
    'inverted' => false,          // على الأسطح الداكنة (الجرافيت): بلاطة بيضاء + أعمدة جرافيت
    'lang' => null,               // افتراضيًّا لغة الواجهة (app locale)
])
@php
    // الهوية «الجسر (H)» — IDENTITY-02: بلاطة جرافيت + عمودان أبيضان + نقطة زمرّدية.
    // الكلمة تتبع اللغة: عربي «إنفلونسر هب» · إنجليزي «InfluencerHub»، بلون موحّد بلا بنفسجي.
    $lang = $lang ?: app()->getLocale();
    $ar = $lang === 'ar';
@endphp
<span {{ $attributes->merge(['style' => 'display:inline-flex; align-items:center; gap:.5rem;']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:block; flex-shrink:0">
        <rect width="100" height="100" rx="23.33" fill="{{ $inverted ? '#FFFFFF' : '#171A1D' }}"/>
        <g transform="translate(20 20) scale(.6)">
            <rect x="14" y="12" width="22" height="76" rx="7" fill="{{ $inverted ? '#171A1D' : '#FFFFFF' }}"/>
            <rect x="64" y="12" width="22" height="76" rx="7" fill="{{ $inverted ? '#171A1D' : '#FFFFFF' }}"/>
            <circle cx="50" cy="50" r="11" fill="#3FAE86"/>
        </g>
    </svg>
    @if($withWordmark)
        <span style="font-weight:700; letter-spacing:-.01em; color:{{ $color }}; white-space:nowrap; {{ $ar ? '' : 'direction:ltr;' }}">@if($ar)إنفلونسر <span style="color:{{ $hubColor }}">هب</span>@else Influencer<span style="color:{{ $hubColor }}">Hub</span>@endif</span>
    @endif
</span>
