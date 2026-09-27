@props([
    'label',
    'value' => null,
    'unit' => null,
    'prefix' => '',
    'decimals' => 2,
    'foot' => null,
    'icon' => null,
    'tone' => 'brand',
    'raw' => false,
    'tinted' => false,
])

{{--
    Stat card — label / value / footnote plus a tinted icon chip.

    The tone prop is the only way the icon chip gets its colour, which is what
    makes every dashboard read the same. Previously each page hardcoded its own
    hex for the same card.

        <x-stat-card label="Total Vendors" :value="$count" icon="bi-shop-window" />

        <x-stat-card label="Pending" :value="$pending" icon="bi-hourglass-split"
                     tone="warning" foot="Awaiting confirmation" />

    Set tinted for the compact, centred tile used in the analytics rows. The tone
    then tints the whole tile instead of just the icon chip, and the value sits
    above the label:

        <x-stat-card tinted tone="danger" label="Rejected" :value="$rejected" />

    Both variants share one markup contract, so a row of tiles looks identical
    wherever it appears.

    Values are formatted to 2 decimals by default, which suits quantities like
    kilograms. Whole-number counts need decimals="0" or they render as "42.00":

        <x-stat-card tinted tone="neutral" label="Total" :value="$total" decimals="0" />

    @param string $tone     brand|success|warning|danger|neutral
    @param string $prefix   Currency symbol or similar
    @param int    $decimals Decimal places; 0 for integer counts
    @param bool   $raw      Set true when $value is pre-formatted markup
    @param bool   $tinted   Compact centred tile instead of the full card
    @slot foot    Replaces the footnote line
--}}

@php
    $iconTones = [
        'brand'   => '',
        'success' => 'vpm-stat-icon-success',
        'warning' => 'vpm-stat-icon-warning',
        'danger'  => 'vpm-stat-icon-danger',
        'neutral' => 'vpm-stat-icon-neutral',
    ];

    $tileTones = [
        'brand'   => 'vpm-stat-tinted-brand',
        'success' => 'vpm-stat-tinted-success',
        'warning' => 'vpm-stat-tinted-warning',
        'danger'  => 'vpm-stat-tinted-danger',
        'neutral' => 'vpm-stat-tinted-neutral',
    ];
@endphp

@if($tinted)
    <div {{ $attributes->merge(['class' => 'vpm-stat-tinted ' . ($tileTones[$tone] ?? $tileTones['neutral'])]) }}>
        <p class="vpm-stat-value">
            @if($raw)
                {!! $value !!}
            @elseif($value !== null)
                {{ $prefix }}{{ number_format((float) $value, (int) $decimals) }}
                @if($unit)
                    <span class="vpm-stat-value-unit">{{ $unit }}</span>
                @endif
            @else
                <span class="text-slate-300">&mdash;</span>
            @endif
        </p>

        <p class="vpm-stat-label">{{ $label }}</p>

        @if(isset($foot))
            <p class="vpm-stat-foot">{{ $foot }}</p>
        @elseif($foot !== null)
            <p class="vpm-stat-foot">{{ $foot }}</p>
        @endif
    </div>
@else
<div {{ $attributes->merge(['class' => 'vpm-card vpm-card-hover']) }}>
    <div class="vpm-stat">

        <div class="min-w-0">
            <p class="vpm-stat-label">{{ $label }}</p>

            <p class="vpm-stat-value">
                @if($raw)
                    {!! $value !!}
                @elseif($value !== null)
                    {{ $prefix }}{{ number_format((float) $value, (int) $decimals) }}
                    @if($unit)
                        <span class="vpm-stat-value-unit">{{ $unit }}</span>
                    @endif
                @else
                    <span class="text-slate-300">&mdash;</span>
                @endif
            </p>

            @if(isset($foot))
                <p class="vpm-stat-foot">{{ $foot }}</p>
            @elseif($foot !== null)
                <p class="vpm-stat-foot">{{ $foot }}</p>
            @endif
        </div>

        @if($icon)
            <div class="vpm-stat-icon {{ $iconTones[$tone] ?? '' }} flex-shrink-0">
                <i class="bi {{ $icon }}" aria-hidden="true"></i>
            </div>
        @endif

    </div>
</div>
@endif
