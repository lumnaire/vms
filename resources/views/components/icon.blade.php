@props([
    'name',
    'size' => 'base',
    'class' => '',
])

{{--
    Icon — Bootstrap Icons, sized from the shared scale.

    Replaces the ~40 ad-hoc `style="font-size: Npx"` declarations that were
    spread across every view. Bootstrap Icons itself has no size utilities, so
    this is the only sanctioned way to size an icon.

    @param string $name  Bootstrap Icons name, e.g. "bi-shop-window"
    @param string $size  2xs|xs|sm|md|base|lg|xl|2xl|3xl|4xl|5xl
--}}

@php
    $sizes = [
        '2xs'  => 'vpm-icon-2xs',
        'xs'   => 'vpm-icon-xs',
        'sm'   => 'vpm-icon-sm',
        'md'   => 'vpm-icon-md',
        'base' => 'vpm-icon-base',
        'lg'   => 'vpm-icon-lg',
        'xl'   => 'vpm-icon-xl',
        '2xl'  => 'vpm-icon-2xl',
        '3xl'  => 'vpm-icon-3xl',
        '4xl'  => 'vpm-icon-4xl',
        '5xl'  => 'vpm-icon-5xl',
    ];
@endphp

<i class="bi {{ $name }} {{ $sizes[$size] ?? $sizes['base'] }} {{ $class }}" aria-hidden="true"></i>
