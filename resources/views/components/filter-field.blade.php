@props([
    'label' => null,
    'width' => null,
    'class' => '',
])

{{--
    Filter field wrapper — a labelled control inside the filter bar.

        <x-filter-field label="Fish Type" width="170px">
            <select name="fish_type_id" class="vpm-filter-select">...</select>
        </x-filter-field>

    @param string $label  Small uppercase caption above the control
    @param string $width  Min width, e.g. "170px"
--}}

<div {{ $attributes->merge([
    'class' => 'vpm-filter-field ' . $class,
    'style' => $width ? 'min-width:' . $width : null,
]) }}>
    @if($label)
        <span class="vpm-filter-label">{{ $label }}</span>
    @endif
    {{ $slot }}
</div>
