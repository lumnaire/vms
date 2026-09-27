@props([
    'title' => null,
    'subtitle' => null,
    'padded' => true,
    'hover' => false,
    'class' => '',
])

{{--
    Card — the workhorse surface. Header, body and footer slots.

        <x-card title="Vendors" subtitle="12 registered">
            ...table...
        </x-card>

    @param string|null $title    Section heading; omit for a bare surface
    @param string|null $subtitle Muted line under the heading
    @param bool $padded          Set false when the body holds a full-bleed table
    @param bool $hover           Lift on hover (use for clickable cards)
    @slot header  Replaces the generated title block
    @slot footer  Trailing action area
--}}

@php
    $classes = 'vpm-card ' . ($hover ? 'vpm-card-hover ' : '') . $class;
    $hasHeader = isset($header) || $title !== null;
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>

    @if($hasHeader)
        <div class="vpm-card-header">
            @if(isset($header))
                {{ $header }}
            @else
                <div class="min-w-0">
                    <h2 class="vpm-section-title">{{ $title }}</h2>
                    @if($subtitle)
                        <p class="vpm-section-sub">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif

            @isset($footer)
                <div class="flex items-center gap-2 flex-shrink-0">{{ $footer }}</div>
            @endisset
        </div>
    @elseif(isset($footer))
        <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
            {{ $footer }}
        </div>
    @endif

    <div class="{{ $padded ? 'vpm-card-body' : '' }}">{{ $slot }}</div>

    @if(isset($actions))
        <div class="vpm-modal-footer">{{ $actions }}</div>
    @endif

</div>
