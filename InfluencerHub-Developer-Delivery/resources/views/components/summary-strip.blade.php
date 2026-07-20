@props(['items' => []])
{{-- شريط ملخّص: مؤشرات موجزة أعلى مساحة العمل. items: [['label'=>, 'value'=>, 'tone'=>?, 'icon'=>?], ...] --}}
@if(! empty($items))
<div {{ $attributes->merge(['class' => 'ih-summary']) }}>
    @foreach($items as $it)
        <div class="ih-summary__cell">
            <div class="ih-summary__label">
                @if(! empty($it['icon']))<x-icon :name="$it['icon']" :size="15"/>@endif
                {{ $it['label'] }}
            </div>
            <div class="ih-summary__value {{ ! empty($it['tone']) ? 'ih-summary__value--' . $it['tone'] : '' }}">{{ $it['value'] }}</div>
        </div>
    @endforeach
</div>
@endif
