@props(['size' => 28, 'color' => 'currentColor', 'withWordmark' => false, 'inverted' => false])
{{-- الهوية الرسمية «الجسر (H)»: بلاطة إنديغو + عمودان أبيضان + نقطة سماوية.
     مصدر واحد للشعار في أسطح Blade. البلاطة ثابتة اللون (تصلح على الفاتح والداكن)؛
     على أسطح الإنديغو/التدرّج مرّر inverted لعكسها (بلاطة بيضاء + أعمدة إنديغو). --}}
<span {{ $attributes->merge(['style' => 'display:inline-flex; align-items:center; gap:.5rem;']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:block; flex-shrink:0">
        <rect width="100" height="100" rx="23.33" fill="{{ $inverted ? '#FFFFFF' : '#5B45E0' }}"/>
        <g transform="translate(20 20) scale(.6)">
            <rect x="14" y="12" width="22" height="76" rx="7" fill="{{ $inverted ? '#5B45E0' : '#FFFFFF' }}"/>
            <rect x="64" y="12" width="22" height="76" rx="7" fill="{{ $inverted ? '#5B45E0' : '#FFFFFF' }}"/>
            <circle cx="50" cy="50" r="11" fill="#22D3EE"/>
        </g>
    </svg>
    @if($withWordmark)
        <span style="font-weight:700; letter-spacing:-.01em; color:{{ $color }}">InfluencerHub</span>
    @endif
</span>
