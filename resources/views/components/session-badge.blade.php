@props([
    'session' => 'AM',
    'class' => '',
])

{{--
    Session badge — which half of the trading day a line belongs to.

        <x-session-badge :session="$entry->session()" />

    AM reads warm (sunrise) and PM reads cool (sunset) so the two are told apart
    at a glance in a long list, not only by their two letters.

    @param string $session AM|PM — anything else renders as AM, matching
                           VendorInventory::session() on legacy rows.
--}}

@php
    $isPm = strtoupper((string) $session) === 'PM';
@endphp

<x-badge :variant="$isPm ? 'info' : 'warning'"
         :icon="$isPm ? 'bi-sunset-fill' : 'bi-sunrise-fill'"
         :class="$class"
         title="{{ $isPm ? 'Afternoon delivery' : 'Morning delivery' }}">{{ $isPm ? 'PM' : 'AM' }}</x-badge>
