@props([
    'title',
    'status' => null,       // مفتاح حالة → شارة موحّدة
    'eyebrow' => null,       // سطر علوي صغير (نوع الكيان / الرقم المرجعي)
    'meta' => [],            // حقائق موجزة: ['label' => 'value', ...]
    'back' => null,          // رابط الرجوع (اختياري)
    'backLabel' => 'رجوع',
])
{{-- رأس مساحة العمل: هوية الكيان + حالته + حقائق موجزة + لوحة إجراءات سياقية (slot) --}}
<div {{ $attributes->merge(['class' => 'ih-ws-header']) }}>
    <div class="ih-ws-header__main">
        @if($back)
            <a href="{{ $back }}" class="ih-ws-header__back">
                <x-icon name="circle" :size="4" style="display:none"/>
                <span aria-hidden="true">→</span> {{ $backLabel }}
            </a>
        @endif
        @if($eyebrow)<div class="ih-ws-header__eyebrow">{{ $eyebrow }}</div>@endif
        <div class="ih-ws-header__title-row">
            <h1 class="ih-ws-header__title">{{ $title }}</h1>
            @if($status)<x-status-badge :status="$status"/>@endif
        </div>
        @if(! empty($meta))
            <div class="ih-ws-header__meta">
                @foreach($meta as $label => $value)
                    <span class="ih-ws-header__fact"><span class="ih-ws-header__fact-k">{{ $label }}</span> {{ $value }}</span>
                @endforeach
            </div>
        @endif
    </div>
    @isset($actions)
        <div class="ih-ws-header__actions">{{ $actions }}</div>
    @endisset
</div>
