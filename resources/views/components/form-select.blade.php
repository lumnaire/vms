@props([
    'name' => null,
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'class' => '',
])

{{--
    Form select.

        <x-form-select name="quality_class" label="Quality Class"
                      :options="$qualityClasses" :selected="$selected" />

    The options array may be a plain list (value === label) or key => label.

    @param array  $options  list of values, or key => label map
    @param mixed  $selected currently selected value
--}}

@php
    $isAssoc = ! array_is_list($options);
    $inputId = $attributes->get('id', $name);
    $hasError = $name && $errors->has($name);
@endphp

<div class="{{ $class }}">

    @if($label)
        <label for="{{ $inputId }}" class="vpm-label-text">
            {{ $label }}
            @if($required)
                <span class="text-danger-500" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <select {{ $attributes->merge([
        'id'       => $inputId,
        'name'     => $name,
        'required' => $required,
        'disabled' => $disabled,
        'class'    => 'vpm-select' . ($hasError ? ' is-invalid' : ''),
    ]) }}>

        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($options as $key => $value)
            @php
                $optValue = $isAssoc ? $key : $value;
                $optLabel = $value;
            @endphp
            <option value="{{ $optValue }}"
                    {{ (string) $optValue === (string) $selected ? 'selected' : '' }}>
                {{ $optLabel }}
            </option>
        @endforeach

    </select>

    @if($hasError)
        <p class="mt-1.5 flex items-center gap-1 text-[11.5px] font-medium" style="color: var(--color-danger-600);">
            <x-icon name="bi-exclamation-circle" size="xs" />
            {{ $errors->first($name) }}
        </p>
    @endif

</div>
