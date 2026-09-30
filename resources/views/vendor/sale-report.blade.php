@extends('layouts.app')

@php
    // The sale window closes at 23:59 on the report date, in the app timezone
    // (Asia/Manila), which is the clock the vendor sees in the market. Derived
    // here rather than hardcoded, so a configured cutoff shows the real time
    // everywhere instead of contradicting the countdown below.
    $deadline  = \App\Models\VendorSaleReport::deadlineFor(today());
    $deadlineText = $deadline->format('g:i A');
@endphp

@section('title', 'Sale Report')
@section('subtitle', 'Declare what you sold today · closes '.$deadlineText)

@push('styles')
<style>
    /* Quantity inputs are the point of this page, so they are wide enough to
       type into on a phone at the stall and still line up in a column. */
    .sr-kg {
        width: 100%;
        min-width: 96px;
        padding: 9px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
        outline: none;
        text-align: right;
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: white;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .sr-kg:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.15);
    }
    .sr-kg.is-over {
        border-color: #f87171;
        color: #991b1b;
        box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.15);
    }
    .sr-kg.is-soldout { background: #f0fdf4; border-color: #86efac; color: #065f46; }

    /* Running total row, sticky to the bottom of the table on long lists. */
    .sr-total-row {
        position: sticky;
        bottom: 0;
        background: #f8fafc;
        border-top: 2px solid #e2e8f0;
        z-index: 5;
    }

    /* Deadline banner */
    .sr-deadline { font-variant-numeric: tabular-nums; }
</style>
@endpush

@section('content')

@php
    // The sale window closes at 23:59 on the report date, in the app timezone
    // (Asia/Manila), which is the clock the vendor sees in the market.
    $remaining = now()->diffInSeconds($deadline, false);
    $closed    = $remaining <= 0;
    $submitted = $report->exists && $report->isSubmitted();

    // kg already declared per entry, so the form reopens with the last numbers.
    $declared = $report->exists
        ? $report->items()->pluck('total_kg', 'vendor_inventory_id')
        : collect();
@endphp

{{-- ── Deadline Banner ──────────────────────────────────────────── --}}
<div class="flex items-center gap-3 px-4 py-3 rounded-xl mb-5 {{ $closed ? 'bg-danger-50' : 'bg-info-50' }}"
     style="border: 1px solid {{ $closed ? '#fecaca' : '#bae6fd' }}">
    <x-icon name="{{ $closed ? 'bi-lock-fill' : 'bi-alarm-fill' }}"
            size="md"
            class="flex-shrink-0 {{ $closed ? 'text-danger-500' : 'text-info-500' }}" />
    <div class="min-w-0">
        <p class="font-bold text-[13px] {{ $closed ? 'text-danger-800' : 'text-info-900' }}">
            @if($closed)
                Today's sale window has closed.
            @else
                Today's sale window closes at {{ $deadlineText }}.
            @endif
        </p>
        <p class="text-[11.5px] mt-0.5 {{ $closed ? 'text-danger-600' : 'text-info-700' }} sr-deadline">
            @if($closed)
                Ask market staff if a correction is needed.
            @else
                {{ $submitted ? 'You can revise this until then.' : 'Submit before then.' }}
                Time left:
                <span class="font-semibold sr-deadline" data-deadline-countdown
                      data-seconds-left="{{ (int) $remaining }}">--:--:--</span>
            @endif
        </p>
    </div>
</div>

{{-- ── Flash Messages ───────────────────────────────────────────── --}}
@if(session('success'))
<div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl text-[13.5px] bg-success-50"
     style="border: 1px solid #a7f3d0; color: #065f46">
    <x-icon name="bi-check-circle-fill" size="base" class="flex-shrink-0 text-success-500" />
    <span class="font-medium">{{ session('success') }}</span>
    <button onclick="this.parentElement.remove()"
            class="ml-auto hover:opacity-60 transition-opacity" style="color: #34d399;">
        <x-icon name="bi-x-lg" size="md" />
    </button>
</div>
@endif

@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl text-[13px] bg-danger-50 border border-danger-200 text-danger-800">
    <div class="flex items-center gap-2 font-semibold mb-1.5 text-[13.5px]">
        <x-icon name="bi-exclamation-circle-fill" class="flex-shrink-0 text-danger-500" />
        Please correct the following:
    </div>
    <ul class="space-y-0.5 pl-6 text-[12.5px] text-danger-700" style="list-style: disc">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Today's Confirmed Stock ───────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-slate-700 font-bold text-[13.5px]">
                <x-icon name="bi-clipboard-check" size="sm" class="text-brand-600" />
                Today's Confirmed Fish
            </h2>
            <p class="text-slate-400 text-[11px] mt-px">
                {{ today()->format('l, F j, Y') }} &middot;
                only entries confirmed by market staff can be declared
            </p>
        </div>
        @if($submitted)
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-success-50"
                  style="color: #065f46; border: 1px solid #a7f3d0">
                <x-icon name="bi-check-circle-fill" size="xs" /> Submitted
            </span>
        @endif
    </div>

    @if($entries->isEmpty())
        <div class="flex flex-col items-center justify-center py-14">
            <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-3">
                <x-icon name="bi-hourglass-split" size="2xl" class="text-slate-300" />
            </div>
            <p class="text-slate-500 font-semibold text-[13px]">No confirmed fish for today yet</p>
            <p class="text-slate-400 text-[12px] mt-0.5">
                Submit inventory first, then wait for staff to confirm it.
            </p>
            <a href="{{ route('vendor.inventory.index') }}"
               class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-[12.5px] font-semibold bg-brand-600 text-white"
               style="text-decoration:none">
                <x-icon name="bi-box-seam" size="sm" /> Go to My Inventory
            </a>
        </div>
    @else
    <form method="POST" action="{{ route('vendor.sale-report.store') }}">
        @csrf
        <div class="overflow-x-auto">
        <table style="width:100%; border-collapse:collapse; min-width: 720px;">
            <thead>
                <tr class="bg-surface-subtle" style="border-bottom:1px solid #f1f5f9">
                    <th class="text-left text-slate-400 font-semibold px-5 py-3 text-[11px] uppercase tracking-[0.07em]">Fish</th>
                    <th class="text-left text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Quality</th>
                    <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Price / kg</th>
                    <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Released</th>
                    <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]" style="width:140px">Sold (kg)</th>
                    <th class="text-right text-slate-400 font-semibold px-5 py-3 text-[11px] uppercase tracking-[0.07em]" style="width:130px">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entries as $entry)
                    @php
                        $released = (float) $entry->released_kg;
                        $price    = (float) $entry->price_per_kg;
                        $value    = old("items.{$entry->id}.total_kg", $declared[$entry->id] ?? 0);
                        $value    = round((float) $value, 2);
                        // Fixed 2dp rather than a bare cast: pluck() bypasses the
                        // model's decimal cast, so MySQL returns "6.00" while
                        // SQLite returns 6. Formatting here keeps the prefilled
                        // field identical on every driver.
                        $valueText = number_format($value, 2, '.', '');
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors" style="border-bottom:1px solid #f1f5f9">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                @if($entry->fishType?->image_path)
                                    <img src="{{ asset('storage/' . $entry->fishType->image_path) }}"
                                         alt="{{ $entry->fishType->name }}" class="w-8 h-8 rounded-lg object-cover flex-shrink-0">
                                @else
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                         style="background:linear-gradient(135deg,#0f2d5e,#1d4ed8)">
                                        <i class="bi bi-fish" style="color:#fff"></i>
                                    </div>
                                @endif
                                <span class="font-semibold text-[13.5px] text-slate-700">
                                    {{ $entry->fishType?->name ?? 'Unknown' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <x-quality-badge :quality="$entry->quality_class" />
                        </td>
                        <td class="px-4 py-3 text-right text-[13px] font-semibold text-slate-700">
                            ₱{{ number_format($price, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right text-[13px] text-slate-500">
                            {{ number_format($released, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <input type="number"
                                   name="items[{{ $entry->id }}][total_kg]"
                                   value="{{ $valueText }}"
                                   step="0.01"
                                   min="0"
                                   max="{{ $released }}"
                                   class="sr-kg {{ $value > $released ? 'is-over' : ($value >= $released && $released > 0 ? 'is-soldout' : '') }}"
                                   data-max="{{ $released }}"
                                   data-price="{{ $price }}"
                                   aria-label="Kilograms sold for {{ $entry->fishType?->name }}"
                                   @disabled($closed)
                                   required>
                            @error("items.{$entry->id}.total_kg")
                                <p class="text-[11px] text-danger-600 mt-1 text-right">{{ $message }}</p>
                            @enderror
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-[13.5px] font-bold text-slate-800" data-line-total>
                                ₱{{ number_format($value * $price, 2) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="sr-total-row">
                    <td colspan="3" class="px-5 py-3 text-[11px] uppercase tracking-[0.07em] font-semibold text-slate-500">
                        Day total
                    </td>
                    <td class="px-4 py-3 text-right text-[13px] font-semibold text-slate-600" data-total-released></td>
                    <td class="px-4 py-3 text-right text-[15px] font-bold text-slate-800" data-total-kg></td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-[15px] font-bold text-brand-700" data-grand-total></span>
                    </td>
                </tr>
            </tfoot>
        </table>
        </div>

        @if(! $closed)
        <div class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <p class="text-[11.5px] text-slate-400">
                <i class="bi bi-info-circle"></i>
                Leave an entry at 0 if you did not sell that fish. Sold kg cannot exceed
                the amount released for sale.
            </p>
            <x-btn type="submit" icon="bi-send-fill" class="flex-shrink-0">
                {{ $submitted ? 'Update Sale Report' : 'Submit Sale Report' }}
            </x-btn>
        </div>
        @endif
    </form>
    @endif
</div>

{{-- ── Previous Reports ──────────────────────────────────────────── --}}
@if($history->isNotEmpty())
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card mt-4">
    <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="text-slate-700 font-bold text-[13.5px]">
            <x-icon name="bi-clock-history" size="sm" class="text-slate-400" /> Previous Reports
        </h2>
        <p class="text-slate-400 text-[11px] mt-px">Your last {{ $history->count() }} closed trading days</p>
    </div>
    <div class="overflow-x-auto">
    <table style="width:100%; border-collapse:collapse; min-width: 520px;">
        <thead>
            <tr class="bg-surface-subtle" style="border-bottom:1px solid #f1f5f9">
                <th class="text-left text-slate-400 font-semibold px-5 py-3 text-[11px] uppercase tracking-[0.07em]">Date</th>
                <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Items</th>
                <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Released</th>
                <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Sold</th>
                <th class="text-right text-slate-400 font-semibold px-5 py-3 text-[11px] uppercase tracking-[0.07em]">Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($history as $day)
            <tr class="hover:bg-slate-50 transition-colors" style="border-bottom:1px solid #f1f5f9">
                <td class="px-5 py-3 text-[13px] font-semibold text-slate-700">
                    {{ $day->report_date->format('M j, Y') }}
                </td>
                <td class="px-4 py-3 text-right text-[13px] text-slate-500">{{ $day->item_count }}</td>
                <td class="px-4 py-3 text-right text-[13px] text-slate-500">{{ number_format((float) $day->total_stock_kg, 2) }} kg</td>
                <td class="px-4 py-3 text-right text-[13px] font-semibold text-slate-700">{{ number_format((float) $day->total_sold_kg, 2) }} kg</td>
                <td class="px-5 py-3 text-right text-[13px] font-semibold text-slate-700">₱{{ number_format((float) $day->total_value, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    // ── Live totals ───────────────────────────────────────────────────
    // The vendor is typing quantities all afternoon; making them read the day
    // total as they type is the difference between one pass and several.
    (function () {
        const peso = n => '₱' + n.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const inputs = [...document.querySelectorAll('.sr-kg')];

        const totalReleasedCell = document.querySelector('[data-total-released]');
        const totalKgCell       = document.querySelector('[data-total-kg]');
        const grandTotalCell    = document.querySelector('[data-grand-total]');

        let totalReleased = 0;
        inputs.forEach(input => { totalReleased += parseFloat(input.dataset.max) || 0; });
        if (totalReleasedCell) {
            totalReleasedCell.textContent = totalReleased.toLocaleString('en-PH', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            }) + ' kg';
        }

        function recalc() {
            let totalKg = 0;
            let totalValue = 0;

            inputs.forEach(input => {
                const max = parseFloat(input.dataset.max) || 0;
                const price = parseFloat(input.dataset.price) || 0;
                let kg = parseFloat(input.value);
                if (isNaN(kg) || kg < 0) kg = 0;

                // Flag an over-declaration rather than silently clamping it, so
                // the vendor sees the mistake instead of losing their number.
                const over = kg > max + 0.0001;
                const soldOut = max > 0 && !over && kg >= max - 0.0001;

                input.classList.toggle('is-over', over);
                input.classList.toggle('is-soldout', soldOut);

                const lineTotal = input.closest('tr').querySelector('[data-line-total]');
                if (lineTotal) lineTotal.textContent = peso(kg * price);

                totalKg += kg;
                totalValue += kg * price;
            });

            if (totalKgCell) {
                totalKgCell.textContent = totalKg.toLocaleString('en-PH', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2,
                }) + ' kg';
            }
            if (grandTotalCell) {
                grandTotalCell.textContent = peso(totalValue);
            }
        }

        inputs.forEach(input => {
            input.addEventListener('input', recalc);
            input.addEventListener('blur', recalc);
        });

        recalc();
    })();

    // ── Deadline countdown ───────────────────────────────────────────
    // Counts down to 23:59 local. Purely a reminder: the server refuses
    // anything submitted after the deadline regardless of what this shows.
    (function () {
        const el = document.querySelector('[data-deadline-countdown]');
        if (!el) return;

        let left = parseInt(el.dataset.secondsLeft, 10) || 0;

        function render() {
            if (left <= 0) { el.textContent = 'closed'; return; }

            const h = Math.floor(left / 3600);
            const m = Math.floor((left % 3600) / 60);
            const s = left % 60;

            el.textContent = [h, m, s]
                .map(n => String(n).padStart(2, '0'))
                .join(':');
        }

        render();
        setInterval(() => { left--; render(); }, 1000);
    })();
</script>
@endpush
