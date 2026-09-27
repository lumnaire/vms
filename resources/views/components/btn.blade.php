@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconSize' => 'sm',
    'class' => '',
    'disabled' => false,
])

{{--
    Button — the single button style for the whole product.

    Renders an <a> when `href` is given, otherwise a <button>.

        <x-btn variant="primary" icon="bi-plus-lg">Add Vendor</x-btn>
        <x-btn variant="secondary" :href="route('x')" icon="bi-download">Export</x-btn>
        <x-btn variant="danger-soft" size="sm" icon="bi-trash3">Delete</x-btn>

    @param string $variant  primary|secondary|ghost|danger|danger-soft|success
    @param string $size     md|sm
    @param string $icon     Bootstrap Icons name rendered before the label
--}}

@php
    $variants = [
        'primary'     => 'vpm-btn-primary',
        'secondary'   => 'vpm-btn-secondary',
        'ghost'       => 'vpm-btn-ghost',
        'danger'      => 'vpm-btn-danger',
        'danger-soft' => 'vpm-btn-danger-soft',
        'success'     => 'vpm-btn-success',
    ];

    $classes = 'vpm-btn '
        . ($variants[$variant] ?? $variants['primary']) . ' '
        . ($size === 'sm' ? 'vpm-btn-sm ' : '')
        . $class;
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
            @if($disabled) disabled @endif
            {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-icon :name="$icon" :size="$iconSize" />@endif
        {{ $slot }}
    </button>
@endif
