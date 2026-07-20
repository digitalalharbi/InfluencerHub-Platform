@props(['status' => 'draft'])
@php
    // شارة حالة موحّدة: التسمية من قاموس الحالات، واللون من خريطة tone → ih-status-*.
    $tone = __('statuses.tone.' . $status);
    if (str_starts_with($tone, 'statuses.')) $tone = $status;       // غير معرّف → استخدم المفتاح كما هو
    $label = __('statuses.' . $status);
    if (str_starts_with($label, 'statuses.')) $label = $status;     // احتياطي: أظهر المفتاح الخام
@endphp
<span {{ $attributes->merge(['class' => 'badge ih-status-' . $tone]) }}>{{ $label }}</span>
