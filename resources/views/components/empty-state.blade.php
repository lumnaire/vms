@props([
    'title' => 'No data available',
    'text' => null,
    'icon' => 'bi-inbox',
    'class' => '',
])

{{--
    Empty state — the "nothing to show" panel.

        <x-empty-state icon="bi-shop-window"
                       title="No vendors yet"
                       text="Vendors appear here once they register." />

    @param string $icon  Bootstrap Icons name
--}}

<div {{ $attributes->merge(['class' => 'vpm-empty ' . $class]) }}>
    <div class="vpm-empty-icon">
        <i class="bi {{ $icon }}" aria-hidden="true"></i>
    </div>
    <p class="vpm-empty-title">{{ $title }}</p>
    @if($text)
        <p class="vpm-empty-text">{{ $text }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
