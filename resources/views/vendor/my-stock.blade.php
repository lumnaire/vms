@extends('layouts.app')

@section('title', 'My Stock')
@section('subtitle', 'Remaining fish · freshness countdown · resubmit or write off')

@php
    // Everything on this page is an answer to one question: what can I still sell?
    // The freshness window is read from config rather than restated as a number, so
    // the copy cannot disagree with the rule the model actually enforces.
    $window     = (int) config('inventory.stale_after_days');
    $staleTotal = $staleEntries->count();
@endphp

@section('content')

<x-alert />
@if($errors->any())
    <div class="mb-5 px-4 py-3 rounded-xl text-[13px] bg-danger-50 border border-danger-200 text-danger-800">
        <div class="flex items-center gap-2 font-semibold mb-1.5 text-[13.5px]">
            <i class="bi bi-exclamation-circle-fill text-danger-500" aria-hidden="true"></i>
            Please correct the following:
        </div>
        <ul class="space-y-0.5 pl-6 text-[12.5px] text-danger-700" style="list-style: disc">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ── Totals ─────────────────────────────────────────────────────
     Four different questions, four different numbers: what the vendor has ever
     brought, what staff have approved and is still live, what is left unsold, and
     what sold through completely. --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Total Stock" :value="$totalStockKg" unit="kg" :decimals="1"
                 icon="bi-basket2" tone="neutral" foot="Brought to market, all time" />

    <x-stat-card label="Confirmed Stock" :value="$confirmedStockKg" unit="kg" :decimals="1"
                 icon="bi-check-circle" tone="success" foot="Approved by staff, still on your stall" />

    <x-stat-card label="Remaining Stock" :value="$remainingStockKg" unit="kg" :decimals="1"
                 icon="bi-box-seam" tone="brand" foot="Unsold and still sellable" />

    <x-stat-card label="Sold Out" :value="$soldOutKg" unit="kg" :decimals="1"
                 icon="bi-bag-check" tone="neutral"
                 :foot="$soldOutLines . ' ' . Str::plural('line', $soldOutLines) . ' bought completely'" />
</div>

{{-- ── Waiting on staff ────────────────────────────────────────────
     Stock that has been resubmitted leaves its original line the moment the button
     is pressed, so until staff answer it sits in neither the open book nor the
     totals. Saying so is the difference between "my fish disappeared" and "my fish
     is with the staff". --}}
@if($awaitingConfirmation->isNotEmpty())
<div class="rounded-xl px-4 py-3.5 mb-5 bg-warning-50" style="border:1px solid #fde68a">
    <div class="flex items-start gap-3">
        <i class="bi bi-hourglass-split flex-shrink-0 mt-0.5 text-warning-600" aria-hidden="true"></i>
        <div class="min-w-0">
            <p class="text-[13px] font-bold text-warning-800">
                {{ $awaitingConfirmation->count() }} {{ Str::plural('line', $awaitingConfirmation->count()) }}
                waiting on staff
            </p>
            <p class="text-[11.5px] text-warning-800 mt-0.5">
                Resubmitted and not yet on your confirmed stock. It counts as your remaining
                stock again as soon as staff confirm it.
            </p>
            <ul class="mt-1.5 space-y-0.5 text-[11.5px] text-slate-600">
                @foreach($awaitingConfirmation as $entry)
                    <li>
                        {{ number_format((float) $entry->carriedTo->released_kg, 2) }} kg
                        {{ $entry->fishType?->name ?? 'fish' }} submitted for
                        {{ $entry->carriedTo->entry_date->format('M j, Y') }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endif

{{-- ── Stale Stock ───────────────────────────────────────────────
     Past the freshness window there is nothing left to sell, so resubmitting is
     refused. What the vendor can still do is get the fish off the stall and off
     their books, which is why the write-off is offered here and not just left to
     rot in a total. --}}
@if($staleTotal > 0)
<div class="rounded-xl overflow-hidden mb-5" style="border:1px solid #fecaca">
    <div class="px-4 py-3.5 flex items-start gap-3 bg-danger-50 flex-wrap">
        <i class="bi bi-exclamation-octagon-fill flex-shrink-0 mt-0.5 text-danger-600" aria-hidden="true"></i>
        <div class="min-w-0 flex-1">
            <p class="text-[13px] font-bold text-danger-800">
                {{ $staleTotal }} {{ Str::plural('line', $staleTotal) }} of stock
                {{ $staleTotal === 1 ? 'is' : 'are' }} {{ $window }}+ days old and cannot be sold
            </p>
            <p class="text-[11.5px] text-danger-700 mt-0.5">
                {{ number_format($disposableKg, 2) }} kg unsold, {{ number_format($disposableLoss, 2) }}
                of fish on the stall. Report them as written off below so they leave your
                remaining stock.
            </p>
        </div>
        <a href="{{ route('vendor.my-stock.index') }}#stale"
           class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11.5px] font-semibold text-danger-800 border transition-colors"
           style="background:#fff; border-color:#fecaca">
            <i class="bi bi-arrow-down" aria-hidden="true"></i> Act on them
        </a>
    </div>
</div>
@endif

{{-- ── Stock on the Stall ───────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card" id="stale">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-slate-700 font-bold text-[13.5px]">
                <i class="bi bi-box-seam text-brand-600" aria-hidden="true"></i>
                Stock on Your Stall
            </h2>
            <p class="text-slate-400 text-[11px] mt-px">
                Every confirmed line with fish left on it, oldest held first
            </p>
        </div>
        <a href="{{ route('vendor.inventory.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11.5px] font-semibold text-slate-600 border border-slate-200 hover:bg-slate-50 transition-colors"
           style="text-decoration:none">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Submit new stock
        </a>
    </div>

    @if($openStock->isEmpty())
        <x-empty-state icon="bi-check2-all"
                       title="Nothing left on your stall"
                       text="Every confirmed line has been sold through, handed to another day, or written off.">
            <x-slot:action>
                <a href="{{ route('vendor.inventory.index') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-[12.5px] font-semibold bg-brand-600 text-white"
                   style="text-decoration:none">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i> Submit stock for today
                </a>
            </x-slot:action>
        </x-empty-state>
    @else
    <div class="overflow-x-auto">
        <table style="width:100%; border-collapse:collapse; min-width:860px;">
            <thead>
                <tr class="bg-surface-subtle" style="border-bottom:1px solid #f1f5f9">
                    <th class="text-left text-slate-400 font-semibold px-5 py-3 text-[10.5px] uppercase tracking-[0.07em]">Fish</th>
                    <th class="text-left text-slate-400 font-semibold px-4 py-3 text-[10.5px] uppercase tracking-[0.07em]">Logged</th>
                    <th class="text-center text-slate-400 font-semibold px-4 py-3 text-[10.5px] uppercase tracking-[0.07em]">Days Old</th>
                    <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[10.5px] uppercase tracking-[0.07em]">Released</th>
                    <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[10.5px] uppercase tracking-[0.07em]">Sold</th>
                    <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[10.5px] uppercase tracking-[0.07em]">Remaining</th>
                    <th class="text-right text-slate-400 font-semibold px-5 py-3 text-[10.5px] uppercase tracking-[0.07em]">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($openStock as $entry)
                    @php
                        $stale   = $entry->isStale();
                        $age     = $entry->getAgeInDays();
                        $left    = $entry->getDaysUntilStale();
                        $blocker = $entry->resubmitBlocker();

                        // Built outside the markup: a ">" inside {{ }} within an HTML
                        // attribute makes Blade terminate the echo early and emit
                        // broken PHP. Same reason as the inventory history table.
                        $rowTitle = $stale
                            ? 'Too old to sell: held ' . $age . ' days against a ' . $window . '-day window. '
                                . number_format($entry->getRemainingStockValue(), 2) . ' at stake.'
                            : number_format($entry->getRemainingStockValue(), 2) . ' of fish still to sell.';
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors"
                        style="border-bottom:1px solid #f1f5f9; {{ $stale ? 'background:#fef2f2;' : '' }}">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                @if($entry->fishType?->image_path)
                                    <img src="{{ asset('storage/' . $entry->fishType->image_path) }}"
                                         alt="{{ $entry->fishType->name }}"
                                         class="w-8 h-8 rounded-lg object-cover flex-shrink-0">
                                @else
                                    <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 text-white text-[13px]"
                                          style="background:linear-gradient(135deg,#0f2d5e,#1d4ed8)">
                                        <i class="bi bi-fish" aria-hidden="true"></i>
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-semibold text-[13px] text-slate-700 truncate">
                                        {{ $entry->fishType?->name ?? 'Unknown' }}
                                    </p>
                                    <p class="text-[11px] text-slate-400">
                                        ₱{{ number_format((float) $entry->price_per_kg, 2) }} per kg &middot;
                                        {{ $entry->session() }} &middot;
                                        {{ $entry->carried_from_id ? 'carried forward' : 'new stock' }}
                                    </p>
                                </div>
                            </div>
                        </td>

                        <td class="px-4 py-3 text-[12.5px] text-slate-600">
                            {{ $entry->entry_date->format('M j, Y') }}
                        </td>

                        {{-- The countdown itself. Under the window it says how many
                             days are left; at the window it turns red and says the
                             stock can no longer be sold. --}}
                        <td class="px-4 py-3 text-center">
                            @if($stale)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-danger-100 text-danger-800"
                                      style="border:1px solid #fecaca" title="{{ $rowTitle }}">
                                    <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                                    {{ $age }}d &middot; too old
                                </span>
                            @elseif($left === 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-warning-50 text-warning-700"
                                      style="border:1px solid #fde68a" title="{{ $rowTitle }}">
                                    <i class="bi bi-alarm-fill" aria-hidden="true"></i>
                                    {{ $age }}d &middot; last day
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-brand-50 text-brand-700"
                                      style="border:1px solid #bfdbfe" title="{{ $rowTitle }}">
                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                    {{ $age }}d &middot; {{ $left }}d left
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-right text-[12.5px] text-slate-600">
                            {{ number_format((float) $entry->released_kg, 2) }}
                        </td>

                        <td class="px-4 py-3 text-right text-[12.5px] text-slate-600">
                            {{ number_format((float) $entry->sold_kg, 2) }}
                        </td>

                        <td class="px-4 py-3 text-right">
                            <span class="text-[13.5px] font-bold {{ $stale ? 'text-danger-700' : 'text-slate-800' }}"
                                  title="{{ $rowTitle }}">
                                {{ number_format($entry->getRemainingStock(), 2) }}
                                <span class="text-[11px] font-semibold text-slate-400">kg</span>
                            </span>
                        </td>

                        {{-- Fresh stock gets the button. Stock past the window gets the
                             write-off instead, because resubmitting it is refused and
                             saying so on the row beats a dead control. --}}
                        <td class="px-5 py-3 text-right">
                            @if($entry->canResubmit())
                                <form method="POST" class="inline-block"
                                      action="{{ route('vendor.my-stock.carry', $entry) }}"
                                      onsubmit="return confirm('Submit the remaining {{ number_format($entry->getRemainingStock(), 2) }} kg of {{ $entry->fishType?->name ?? 'this fish' }} for sale today? Staff will confirm it.')">
                                    @csrf
                                    <button type="submit" class="vpm-btn vpm-btn-success vpm-btn-sm">
                                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                                        Submit again today
                                    </button>
                                </form>
                                <p class="text-[10.5px] text-slate-400 mt-1">
                                    Sends the whole {{ number_format($entry->getRemainingStock(), 2) }} kg to staff
                                </p>
                            @elseif($stale)
                                <form method="POST" class="inline-block"
                                      action="{{ route('vendor.my-stock.dispose', $entry) }}"
                                      onsubmit="return confirm('Report this stock as written off? It cannot be sold and will be taken off your remaining stock.')">
                                    @csrf
                                    <input type="text" name="reason" maxlength="120"
                                           placeholder="Reason (optional)"
                                           class="mb-1 w-full px-2 py-1 rounded-md text-[11px] border border-slate-200 focus:border-red-300 outline-none"
                                           style="max-width:150px; display:inline-block;">
                                    <button type="submit" class="vpm-btn vpm-btn-danger-soft vpm-btn-sm">
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                        Report written off
                                    </button>
                                </form>
                                <p class="text-[10.5px] text-danger-600 mt-1">
                                    Too old to sell &mdash; clear it instead
                                </p>
                            @else
                                <span class="text-[11px] text-slate-400 italic">{{ $blocker }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ── Settled lines ──────────────────────────────────────────────
     The other half of the answer: what happened to the fish that is no longer on
     the stall. Bought completely, handed to a later day, or written off — the same
     three endings a line can have, read straight off the row. --}}
@if($settled->isNotEmpty())
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card mt-5">
    <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="text-slate-700 font-bold text-[13.5px]">
            <i class="bi bi-archive text-slate-400" aria-hidden="true"></i>
            Settled Lines
        </h2>
        <p class="text-slate-400 text-[11px] mt-px">
            Stock that has left your stall in the last {{ $settledHistoryDays }}
            days &mdash; bought completely, carried to another day, or written off
        </p>
    </div>

    <div class="overflow-x-auto">
        <table style="width:100%; border-collapse:collapse; min-width:720px;">
            <thead>
                <tr class="bg-surface-subtle" style="border-bottom:1px solid #f1f5f9">
                    <th class="text-left text-slate-400 font-semibold px-5 py-3 text-[10.5px] uppercase tracking-[0.07em]">Fish</th>
                    <th class="text-left text-slate-400 font-semibold px-4 py-3 text-[10.5px] uppercase tracking-[0.07em]">Logged</th>
                    <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[10.5px] uppercase tracking-[0.07em]">Released</th>
                    <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[10.5px] uppercase tracking-[0.07em]">Sold</th>
                    <th class="text-left text-slate-400 font-semibold px-5 py-3 text-[10.5px] uppercase tracking-[0.07em]">How it ended</th>
                </tr>
            </thead>
            <tbody>
                @foreach($settled as $entry)
                    @php
                        $state = $entry->getStockState();
                        $notes = [
                            \App\Models\VendorInventory::STATE_SOLD_OUT => [
                                'Sold out &mdash; bought completely',
                                'text-success-700', 'bg-success-50', 'bi-bag-check',
                            ],
                            \App\Models\VendorInventory::STATE_CARRIED => [
                                'Carried to another day',
                                'text-brand-700', 'bg-brand-50', 'bi-arrow-repeat',
                            ],
                            \App\Models\VendorInventory::STATE_DISPOSED => [
                                'Reported as written off',
                                'text-danger-700', 'bg-danger-50', 'bi-trash3',
                            ],
                        ];

                        [$label, $tone, $chip, $icon] = $notes[$state] ?? [
                            'No longer on the stall', 'text-slate-500', 'bg-slate-50', 'bi-dash-circle',
                        ];

                        // Where the stock went, when it is not the obvious answer.
                        $detail = $state === \App\Models\VendorInventory::STATE_CARRIED && $entry->carriedTo
                            ? 'Now on ' . $entry->carriedTo->entry_date->format('M j, Y')
                              . ($entry->carriedTo->isPending() ? ', awaiting confirmation' : '')
                            : ($state === \App\Models\VendorInventory::STATE_DISPOSED
                                ? ($entry->disposed_reason ?: 'Held ' . $entry->getAgeInDays() . ' days')
                                : '');
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors" style="border-bottom:1px solid #f1f5f9">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-[13px] text-slate-700">
                                {{ $entry->fishType?->name ?? 'Unknown' }}
                            </p>
                            <p class="text-[11px] text-slate-400">{{ $entry->quality_class }} &middot; {{ $entry->session() }}</p>
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-slate-600">
                            {{ $entry->entry_date->format('M j, Y') }}
                        </td>
                        <td class="px-4 py-3 text-right text-[12.5px] text-slate-600">
                            {{ number_format((float) $entry->released_kg, 2) }} kg
                        </td>
                        <td class="px-4 py-3 text-right text-[12.5px] font-semibold text-slate-700">
                            {{ number_format((float) $entry->sold_kg, 2) }} kg
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $tone }} {{ $chip }}">
                                <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
                            </span>
                            @if($detail)
                                <p class="text-[11px] text-slate-400 mt-0.5">{{ $detail }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── What happens next ─────────────────────────────────────────
     The rule in words, on the page where it is acted on. --}}
<div class="mt-5 rounded-xl px-4 py-3.5 bg-surface-subtle border border-slate-200">
    <p class="text-[12px] font-bold text-slate-600 mb-1.5">
        <i class="bi bi-info-circle text-slate-400" aria-hidden="true"></i>
        How resubmitting works
    </p>
    <ul class="text-[11.5px] text-slate-500 leading-[1.7] pl-4" style="list-style: disc">
        <li>
            <strong class="text-slate-600">Submit again today</strong> sends the remaining kilograms to
            market staff as a fresh entry for today. Once they confirm it you can declare it
            on the <a href="{{ route('vendor.sale-report.index') }}" class="text-brand-600 font-semibold">sale report</a>,
            and the {{ $window }}-day countdown starts again from today.
        </li>
        <li>
            Stock held {{ $window }} days or more turns red and cannot be resubmitted &mdash; it is no
            longer fresh enough to sell. Report it as written off so it comes off your remaining stock.
        </li>
        <li>
            Carry stock forward from the
            <a href="{{ route('vendor.sale-report.index') }}" class="text-brand-600 font-semibold">sale report</a>
            to load a later trading day in one go.
        </li>
    </ul>
</div>

@endsection
