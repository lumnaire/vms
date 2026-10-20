@props([
    'comparison' => null,
    'showTariff' => false,
    'empty' => 'No tariff',
    'align' => 'end',
])

{{--
    Tariff difference — how a price compares with the official tariff.

        <x-tariff-diff :comparison="$entry->tariffComparison()" />
        <x-tariff-diff :comparison="App\Models\PriceGuide::compare($price, $tariff)" show-tariff />

    Above the tariff reads as the vendor's markup ("tubo"): +₱10.00 (+8.3%).
    Below reads as a discount, at reads "At tariff". With no tariff set for the
    fish, the empty text is shown instead.

    @param array|null $comparison PriceGuide::compare() result
    @param bool       $showTariff Also print "Tariff ₱X" under the difference
    @param string     $align      end (right-aligned cells) or start
--}}

@if($comparison === null)
    <span {{ $attributes->merge(['class' => 'text-slate-300 text-[11px]']) }}>{{ $empty }}</span>
@else
    @php
        $tone = match ($comparison['state']) {
            'above' => 'vpm-badge-warning',
            'below' => 'vpm-badge-success',
            default => 'vpm-badge-neutral',
        };
        $sign = $comparison['diff'] > 0 ? '+' : '−';
    @endphp
    <span {{ $attributes->merge(['class' => 'inline-flex flex-col gap-0.5 ' . ($align === 'start' ? 'items-start' : 'items-end')]) }}>
        <span class="vpm-badge {{ $tone }} vpm-num" title="Tariff ₱{{ number_format($comparison['tariff'], 2) }}/kg">
            @if($comparison['state'] === 'at')
                At tariff
            @else
                {{ $sign }}₱{{ number_format(abs($comparison['diff']), 2) }}
                <span class="font-medium opacity-75">({{ $sign }}{{ number_format(abs($comparison['pct']), 1) }}%)</span>
            @endif
        </span>
        @if($showTariff)
            <span class="text-slate-400 text-[10.5px] vpm-num">Tariff ₱{{ number_format($comparison['tariff'], 2) }}</span>
        @endif
    </span>
@endif
