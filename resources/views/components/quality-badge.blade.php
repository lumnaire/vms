@props([
    'quality' => null,
    'class' => '',
])

{{--
    Quality-class pill — the canonical five-tier colour ramp.

    This is the single source of truth for how a quality class looks. The public
    priceboard and both admin price-guide pages previously disagreed: the board
    rendered Third as amber / Special as purple while the admin pages rendered
    Third as orange / Fourth as pink / Special as amber. All three now call this.

        <x-quality-badge :quality="$entry->quality_class" />

    @param string $quality  First Class|Second Class|Third Class|Fourth Class|Special Class
--}}

@php
    // Keyed on a normalised slug so "First Class", "first class" and "First"
    // all resolve to the same pill.
    $slug = str((string) ($quality ?? ''))->lower()->replace(' class', '')->squish()->toString();

    $tones = [
        'first'   => 'vpm-quality-first',
        'second'  => 'vpm-quality-second',
        'third'   => 'vpm-quality-third',
        'fourth'  => 'vpm-quality-fourth',
        'special' => 'vpm-quality-special',
    ];

    $tone  = $tones[$slug] ?? 'vpm-quality-neutral';
    $label = $quality ? ucwords($quality) : '—';
@endphp

<span {{ $attributes->merge(['class' => 'vpm-quality ' . $tone . ' ' . $class]) }}>{{ $label }}</span>
