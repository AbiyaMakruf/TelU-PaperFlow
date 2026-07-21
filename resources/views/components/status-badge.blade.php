@props(['status'])
@php
    $color = method_exists($status, 'color') ? $status->color() : 'primary';
    $label = method_exists($status, 'label') ? $status->label() : ucfirst((string) $status);
@endphp
<span {{ $attributes->merge(['class' => "badge badge-{$color}"]) }}>{{ $label }}</span>
