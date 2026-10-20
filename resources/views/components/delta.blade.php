@props([
    'change' => null,
    'label' => 'vs same days last month',
])

{{--
    Period-over-period change: ▲ 12% / ▼ 4%, with an icon and the comparison in
    words so the direction never rests on colour alone.

        <x-delta :change="$kpis['supply']['change']" />

    @param float|null $change Percent change; null when there is nothing to compare with
--}}

@if($change === null)
    <span {{ $attributes->merge(['class' => 'text-slate-400 text-[11px]']) }}>No figure to compare with yet</span>
@else
    @php $up = $change >= 0; @endphp
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-[11px]']) }}>
        <span class="vpm-badge {{ $up ? 'vpm-badge-success' : 'vpm-badge-danger' }} vpm-num">
            <x-icon :name="$up ? 'bi-caret-up-fill' : 'bi-caret-down-fill'" size="2xs" />
            {{ number_format(abs($change), 1) }}%
        </span>
        <span class="text-slate-400">{{ $label }}</span>
    </span>
@endif
