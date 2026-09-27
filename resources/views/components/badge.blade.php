@props([
    'variant' => 'neutral',
    'icon' => null,
    'dot' => false,
    'class' => '',
])

{{--
    Badge — status pill.

        <x-badge variant="success" icon="bi-check-circle-fill">Confirmed</x-badge>
        <x-badge variant="warning" icon="bi-hourglass-split">Pending</x-badge>
        <x-badge variant="danger"  icon="bi-x-circle-fill">Rejected</x-badge>

    @param string $variant neutral|success|warning|danger|info
    @param bool   $dot     Show a filled status dot instead of an icon
--}}

@php
    $variants = [
        'neutral' => 'vpm-badge-neutral',
        'success' => 'vpm-badge-success',
        'warning' => 'vpm-badge-warning',
        'danger'  => 'vpm-badge-danger',
        'info'    => 'vpm-badge-info',
    ];

    $classes = 'vpm-badge ' . ($variants[$variant] ?? $variants['neutral']) . ' ' . $class;
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full bg-current flex-shrink-0"></span>
    @endif
    @if($icon)
        <x-icon :name="$icon" size="xs" />
    @endif
    {{ $slot }}
</span>
