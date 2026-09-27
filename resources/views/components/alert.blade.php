@props([
    'variant' => 'success',
    'message' => null,
    'dismissible' => true,
    'class' => '',
])

{{--
    Alert — flash message.

    With no arguments it reads session('success') or session('error') and picks
    the matching variant, so most pages only need a bare tag:

        <x-alert />

    Pass an explicit message to override, e.g. to surface a validation error:

        <x-alert :message="$errors->first('email')" variant="error" />

    NOTE: do not nest a Blade comment inside this docblock. Blade has no
    nested comment syntax, so an inner comment closes the outer one early and
    the remaining example markup gets compiled as real output.

    @param string $variant success|error|warning|info
    @param string $message Overrides the session message
    @param bool   $dismissible Render the close control
--}}

@php
    $variants = [
        'success' => 'vpm-alert-success',
        'error'   => 'vpm-alert-error',
        'warning' => 'vpm-alert-warning',
        'info'    => 'vpm-alert-info',
    ];

    $icons = [
        'success' => 'bi-check-circle-fill',
        'error'   => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ];

    // Default message comes from the matching session key, falling back to
    // the other half so a single component can render either flash type.
    $message = $message ?? session($variant === 'error' ? 'error' : 'success');

    if (! $message && $variant === 'error') {
        $message = session('success');
    }
    if (! $message && $variant === 'success') {
        $message = session('error');
    }
@endphp

@if($message)
    <div role="alert"
         {{ $attributes->merge(['class' => 'vpm-alert ' . ($variants[$variant] ?? $variants['success']) . ' mb-5 ' . $class]) }}>
        <i class="bi {{ $icons[$variant] ?? $icons['success'] }} vpm-alert-icon" aria-hidden="true"></i>
        <span class="font-medium">{{ $message }}</span>

        @if($dismissible)
            <button type="button" class="vpm-alert-dismiss"
                    onclick="this.parentElement.remove()"
                    aria-label="Dismiss">
                <x-icon name="bi-x-lg" size="xs" />
            </button>
        @endif
    </div>
@endif
