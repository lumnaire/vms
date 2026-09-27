@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'readonly' => false,
    'disabled' => false,
    'min' => null,
    'max' => null,
    'step' => null,
    'rows' => null,
    'icon' => null,
    'class' => '',
])

{{--
    Form input.

        <x-form-input name="email" label="Email" type="email" :required="true" />
        <x-form-input name="notes" label="Notes" type="textarea" :rows="4" />

    One input style for the whole product. The project previously carried three
    near-identical ones (.form-input, .form-input-pg, .ft-form-input) at
    different paddings, radii and font sizes.

    @param string $icon Optional Bootstrap Icons name rendered inside the field
--}}

@php
    $isTextarea = $type === 'textarea';
    $inputId = $attributes->get('id', $name);
    $hasError = $errors->has($name);
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

    @if($icon)
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                <x-icon :name="$icon" size="sm" />
            </span>
            <input {{ $attributes->merge([
                'id'     => $inputId,
                'name'   => $name,
                'type'   => $type,
                'value'  => $value,
                'placeholder' => $placeholder,
                'required' => $required,
                'readonly' => $readonly,
                'disabled' => $disabled,
                'min'    => $min,
                'max'    => $max,
                'step'   => $step,
                'class'  => 'vpm-input pl-9' . ($hasError ? ' is-invalid' : ''),
            ]) }}>
        </div>
    @elseif($isTextarea)
        <textarea {{ $attributes->merge([
            'id'          => $inputId,
            'name'        => $name,
            'rows'        => $rows ?? 3,
            'placeholder' => $placeholder,
            'required'    => $required,
            'readonly'    => $readonly,
            'disabled'    => $disabled,
            'class'       => 'vpm-input' . ($hasError ? ' is-invalid' : ''),
        ]) }}>{{ $value }}</textarea>
    @else
        <input {{ $attributes->merge([
            'id'          => $inputId,
            'name'        => $name,
            'type'        => $type,
            'value'       => $value,
            'placeholder' => $placeholder,
            'required'    => $required,
            'readonly'    => $readonly,
            'disabled'    => $disabled,
            'min'         => $min,
            'max'         => $max,
            'step'        => $step,
            'class'       => 'vpm-input' . ($hasError ? ' is-invalid' : ''),
        ]) }}>
    @endif

    @if($hasError)
        <p class="mt-1.5 flex items-center gap-1 text-[11.5px] font-medium" style="color: var(--color-danger-600);">
            <x-icon name="bi-exclamation-circle" size="xs" />
            {{ $errors->first($name) }}
        </p>
    @endif

</div>
