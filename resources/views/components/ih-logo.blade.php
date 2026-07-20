@props(['size' => 28, 'color' => 'currentColor', 'withWordmark' => false])
{{-- علامة InfluencerHub الأصلية: عقدة Hub مركزية تربط عقدتين (علامة ↔ مبدع). قابلة للاستبدال بـSVG لاحقًا دون تعديل الصفحات. --}}
<span {{ $attributes->merge(['style' => 'display:inline-flex; align-items:center; gap:.5rem;']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect x="1.25" y="1.25" width="29.5" height="29.5" rx="8.5" stroke="{{ $color }}" stroke-width="1.6" opacity=".28"/>
        <path d="M9 22V10" stroke="{{ $color }}" stroke-width="2.4" stroke-linecap="round"/>
        <path d="M23 10v12" stroke="{{ $color }}" stroke-width="2.4" stroke-linecap="round"/>
        <path d="M9 16h14" stroke="{{ $color }}" stroke-width="2.4" stroke-linecap="round" opacity=".55"/>
        <circle cx="16" cy="16" r="3.4" fill="{{ $color }}"/>
        <circle cx="9" cy="10" r="2" fill="{{ $color }}"/>
        <circle cx="23" cy="22" r="2" fill="{{ $color }}"/>
    </svg>
    @if($withWordmark)
        <span style="font-weight:800; letter-spacing:-.01em;">Influencer<span style="opacity:.7;">Hub</span></span>
    @endif
</span>
