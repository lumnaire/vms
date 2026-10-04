@extends('layouts.app')

@section('title', 'Vendor Sale Reports')
@section('subtitle', 'What vendors declared they sold, by trading day')

@push('styles')
<style>
    /* ── Calendar ─────────────────────────────────────────────────────
       A day cell carries one of three states: it has declarations, it is the
       selected day, or it is outside the current month. Colour intensity
       scales with kg sold so a busy day reads at a glance. */
    .sr-cal {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 4px;
    }
    .sr-cal-head {
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--color-slate-400);
        text-align: center;
        padding-bottom: 2px;
    }
    .sr-day {
        position: relative;
        aspect-ratio: 1 / 1;
        border-radius: 7px;
        border: 1px solid var(--color-slate-200);
        background: var(--color-surface);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1px;
        text-decoration: none;
        transition: border-color 0.12s ease, background-color 0.12s ease;
    }
    .sr-day:hover { border-color: var(--color-brand-300); background: var(--color-brand-50); }
    .sr-day.is-out { opacity: 0.38; }
    .sr-day.is-future { opacity: 0.28; cursor: not-allowed; pointer-events: none; }
    .sr-day.is-today { border-color: var(--color-brand-500); border-width: 2px; }
    .sr-day.is-selected {
        background: var(--color-brand-600);
        border-color: var(--color-brand-600);
    }
    .sr-day-num { font-size: 11.5px; font-weight: 700; color: var(--color-slate-700); line-height: 1; }
    .sr-day.is-selected .sr-day-num { color: #fff; }
    .sr-day-bar {
        width: 15px; height: 3px; border-radius: 2px;
        background: var(--color-brand-300);
    }
    .sr-day.is-selected .sr-day-bar { background: rgb(255 255 255 / 0.85); }
    /* Two tiers is enough: "someone filed" and "a lot was sold". */
    .sr-day.has-few  .sr-day-bar { background: var(--color-brand-300); }
    .sr-day.has-many .sr-day-bar { background: var(--color-brand-600); }
    .sr-day.is-selected.has-few  .sr-day-bar { background: rgb(255 255 255 / 0.7); }
    .sr-day.is-selected.has-many .sr-day-bar { background: #fff; }

    /* Fully sold vs. leftover from the day. */
    .sr-soldout { color: #065f46; background: #ecfdf5; border: 1px solid #a7f3d0; }
    .sr-leftover { color: #92400e; background: #fffbeb; border: 1px solid #fde68a; }

    @media (max-width: 420px) {
        .sr-day { aspect-ratio: auto; padding: 5px 0; }
    }
</style>
@endpush

@section('content')

@php
    $prevDate = \Carbon\Carbon::parse($reportDate)->subDay()->toDateString();
    $nextDate = \Carbon\Carbon::parse($reportDate)->addDay()->toDateString();

    // Base query string, so the calendar and day stepper keep the vendor filter.
    $qs = fn ($extra = []) => request()->fullUrlWithQuery(array_merge(
        request()->query(),
        $extra
    ));

    $manyKg = max(1.0, (float) $calendarCounts->max(fn ($d) => $d['kg'] ?? 0));
@endphp

{{-- ── Day Stepper + Filters ─────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 shadow-card p-4 mb-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1.5">Trading Day</label>
            <input type="date" name="date" value="{{ $reportDate->toDateString() }}"
                   class="vpm-input" style="width:160px">
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1.5">Vendor</label>
            <select name="vendor_id" class="vpm-filter-select" style="min-width:190px">
                <option value="">All vendors</option>
                @foreach($vendors as $v)
                    <option value="{{ $v['id'] }}" @selected($selectedVendorId === $v['id'])>
                        {{ $v['name'] }}@if($v['stall']) ({{ $v['stall'] }})@endif
                    </option>
                @endforeach
            </select>
        </div>

        <x-btn type="submit" variant="secondary" icon="bi-funnel">Apply</x-btn>

        <div class="flex items-center gap-1.5 ml-auto">
            <a href="{{ $qs(['date' => $prevDate]) }}" class="vpm-btn vpm-btn-secondary vpm-btn-sm"
               title="Previous day" style="text-decoration:none">
                <x-icon name="bi-chevron-left" size="sm" />
            </a>
            <span class="px-2 text-[12.5px] font-bold text-slate-700">
                {{ $reportDate->format('M j, Y') }}
            </span>
            <a href="{{ $qs(['date' => $nextDate]) }}" class="vpm-btn vpm-btn-secondary vpm-btn-sm"
               title="Next day" style="text-decoration:none">
                <x-icon name="bi-chevron-right" size="sm" />
            </a>
        </div>
    </form>
</div>

{{-- ── Stat Cards ────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
    <x-stat-card label="Vendors Filed" :value="$totals['vendors']" :decimals="0"
                 icon="bi-shop-window" tone="brand" />
    <x-stat-card label="Released" :value="$totals['stock_kg']" unit="kg"
                 icon="bi-box-seam" tone="neutral" />
    <x-stat-card label="Sold" :value="$totals['sold_kg']" unit="kg"
                 icon="bi-bag-check" tone="success" />
    <x-stat-card label="Unsold Left" :value="$totals['unsold_kg']" unit="kg"
                 icon="bi-exclamation-diamond"
                 :tone="$totals['unsold_kg'] > 0 ? 'warning' : 'neutral'" />
    {{-- The peso sign is passed as the character itself, NOT as the &amp;#8369;
         entity: the component echoes the prefix through {{ }}, which escapes an
         ampersand and would render the entity's own characters to the user. --}}
    <x-stat-card label="Declared Value" :value="$totals['value']" prefix="₱"
                 icon="bi-cash-coin" tone="brand" />
</div>

{{-- Sell-through strip: reads faster than the two kg cards side by side. --}}
@if($totals['stock_kg'] > 0)
<div class="bg-white rounded-xl border border-slate-100 shadow-card px-5 py-3 mb-4">
    <div class="flex items-center justify-between gap-3 mb-2">
        <span class="text-[11px] font-semibold uppercase tracking-[0.07em] text-slate-400">Sell-through</span>
        <span class="text-[12.5px] font-bold {{ $totals['sell_through_pct'] >= 90 ? 'text-success-600' : 'text-warning-600' }}">
            {{ number_format($totals['sell_through_pct'], 1) }}%
        </span>
    </div>
    <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full rounded-full {{ $totals['sell_through_pct'] >= 90 ? 'bg-success-500' : 'bg-warning-500' }}"
             style="width: {{ min(100, $totals['sell_through_pct']) }}%"></div>
    </div>
</div>
@endif

{{-- ── Calendar ──────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card mb-4">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-slate-700 font-bold text-[13.5px]">
                <x-icon name="bi-calendar3" size="sm" class="text-brand-600" /> Daily Operations
            </h2>
            <p class="text-slate-400 text-[11px] mt-px">
                {{ $reportDate->format('F Y') }} &middot; a marked day has at least one declaration
            </p>
        </div>
        <a href="{{ route(request()->routeIs('staff.*') ? 'staff.sale-reports.index' : 'supervisor.sale-reports.index', ['date' => today()->toDateString()]) }}"
           class="text-[11.5px] font-semibold text-brand-600 hover:underline" style="text-decoration:none">
            Jump to today
        </a>
    </div>

    <div class="p-4">
        <div class="sr-cal mb-1">
            @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                <div class="sr-cal-head">{{ $d }}</div>
            @endforeach
        </div>
        <div class="sr-cal">
            @foreach($calendar as $week)
                @foreach($week as $day)
                    @php
                        // Two tiers of "how much": under half the month's busiest
                        // day, or at least half of it.
                        $tier = $day['kg'] <= 0 ? '' : ($day['kg'] >= $manyKg / 2 ? 'has-many' : 'has-few');
                    @endphp
                    @if($day['is_future'])
                        <div class="sr-day is-out is-future {{ $tier }}" title="Not yet traded">
                            <span class="sr-day-num">{{ $day['day'] }}</span>
                        </div>
                    @else
                        <a href="{{ $qs(['date' => $day['date']]) }}"
                           class="sr-day {{ $day['in_month'] ? '' : 'is-out' }} {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['is_sel'] ? 'is-selected' : '' }} {{ $tier }}"
                           style="text-decoration:none"
                           title="{{ \Carbon\Carbon::parse($day['date'])->format('M j') }} — {{ $day['reports'] }} declaration(s), {{ number_format($day['kg'], 1) }} kg sold">
                            <span class="sr-day-num">{{ $day['day'] }}</span>
                            <span class="sr-day-bar"></span>
                        </a>
                    @endif
                @endforeach
            @endforeach
        </div>

        <div class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-100 flex-wrap text-[11px] text-slate-400">
            <span class="flex items-center gap-1.5">
                <span class="sr-day-bar" style="background:var(--color-brand-300)"></span> some filed
            </span>
            <span class="flex items-center gap-1.5">
                <span class="sr-day-bar" style="background:var(--color-brand-600)"></span> heavy day
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded" style="background:var(--color-brand-600); display:inline-block"></span> selected
            </span>
        </div>
    </div>
</div>

{{-- ── Outstanding Filings ───────────────────────────────────────── --}}
@if($missingVendors->isNotEmpty())
<div class="rounded-xl px-4 py-3 mb-4 bg-warning-50 flex items-start gap-3"
     style="border:1px solid #fde68a">
    <x-icon name="bi-exclamation-triangle-fill" size="md" class="flex-shrink-0 mt-0.5 text-warning-600" />
    <div class="min-w-0">
        <p class="text-[12.5px] font-bold text-warning-800">
            {{ $missingVendors->count() }} vendor{{ $missingVendors->count() === 1 ? '' : 's' }} with confirmed stock
            {{ $missingVendors->count() === 1 ? 'has' : 'have' }} not filed for this day
        </p>
        <p class="text-[11.5px] text-warning-700 mt-1">
            @foreach($missingVendors as $v)
                {{ $v['name'] }}@if($v['stall']) ({{ $v['stall'] }})@endif{{ $loop->last ? '' : ', ' }}
            @endforeach
        </p>
    </div>
</div>
@endif

{{-- ── Declaration Table ─────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-slate-700 font-bold text-[13.5px]">Declared Sales</h2>
            <p class="text-slate-400 text-[11px] mt-px">
                {{ $totals['rows'] }} line{{ $totals['rows'] === 1 ? '' : 's' }}
                @if($selectedVendorId)
                    &middot; filtered to one vendor
                @endif
            </p>
        </div>
        @if($selectedVendorId)
            <a href="{{ $qs(['vendor_id' => null]) }}"
               class="text-[11.5px] font-semibold text-brand-600 hover:underline" style="text-decoration:none">
                Clear vendor filter
            </a>
        @endif
    </div>

    @if($rows->isEmpty())
        <x-empty-state icon="bi-clipboard-x"
                       title="No sale reports for this day"
                       text="Nothing was declared for {{ $reportDate->format('M j, Y') }}{{ $selectedVendorId ? ' by this vendor' : '' }}." />
    @else
    <div class="overflow-x-auto">
    <table style="width:100%; border-collapse:collapse; min-width: 900px;">
        <thead>
            <tr class="bg-surface-subtle" style="border-bottom:1px solid #f1f5f9">
                <th class="text-left text-slate-400 font-semibold px-5 py-3 text-[11px] uppercase tracking-[0.07em]">Vendor</th>
                <th class="text-left text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Fish</th>
                <th class="text-left text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Quality</th>
                <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Price / kg</th>
                <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Released</th>
                <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Sold</th>
                <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Unsold</th>
                <th class="text-right text-slate-400 font-semibold px-5 py-3 text-[11px] uppercase tracking-[0.07em]">Total Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php $item = $row['item']; @endphp
                <tr class="hover:bg-slate-50 transition-colors" style="border-bottom:1px solid #f1f5f9">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 bg-brand-50 text-[10px] font-bold text-brand-600">
                                {{ \Illuminate\Support\Str::of($row['vendor']?->name ?? '?')->explode(' ')->take(2)->map(fn($w) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($w, 0, 1)))->implode('') }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-[13px] text-slate-700 truncate">
                                    {{ $row['vendor']?->name ?? 'Unknown vendor' }}
                                </p>
                                @if($row['stall'])
                                    <p class="text-[10.5px] text-slate-400">Stall {{ $row['stall'] }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-[13px] font-semibold text-slate-700">{{ $item->fish_type_name }}</td>
                    <td class="px-4 py-3"><div class="flex items-center gap-1.5 flex-wrap"><x-quality-badge :quality="$item->quality_class" /><x-session-badge :session="$item->market_session ?? 'AM'" /></div></td>
                    <td class="px-4 py-3 text-right text-[13px] text-slate-600">
                        ₱{{ number_format((float) $item->price_per_kg, 2) }}
                    </td>
                    <td class="px-4 py-3 text-right text-[13px] text-slate-500">
                        {{ number_format((float) $item->released_kg, 2) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-[13.5px] font-bold text-slate-800">{{ number_format((float) $item->total_kg, 2) }} kg</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($row['unsold_kg'] > 0)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold sr-leftover"
                                  title="Left unsold on {{ $row['report']->report_date->format('M j') }}">
                                <x-icon name="bi-exclamation-diamond-fill" size="2xs" />
                                {{ number_format($row['unsold_kg'], 2) }} kg
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold sr-soldout">
                                <x-icon name="bi-check-circle-fill" size="2xs" /> Sold out
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-[13.5px] font-bold text-slate-900">₱{{ number_format((float) $item->total_price, 2) }}</span>
                        @if($row['unsold_kg'] > 0)
                            <p class="text-[10.5px] text-slate-400">
                                ₱{{ number_format($row['unsold_value'], 2) }} unsold
                            </p>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background:#f8fafc; border-top:2px solid #e2e8f0">
                <td colspan="4" class="px-5 py-3 text-[11px] uppercase tracking-[0.07em] font-semibold text-slate-500">
                    Day total
                </td>
                <td class="px-4 py-3 text-right text-[13px] font-semibold text-slate-600">
                    {{ number_format($totals['stock_kg'], 2) }} kg
                </td>
                <td class="px-4 py-3 text-right text-[14px] font-bold text-slate-800">
                    {{ number_format($totals['sold_kg'], 2) }} kg
                </td>
                <td class="px-4 py-3 text-right text-[13px] font-semibold text-slate-600">
                    {{ number_format($totals['unsold_kg'], 2) }} kg
                </td>
                <td class="px-5 py-3 text-right text-[14px] font-bold text-brand-700">
                    ₱{{ number_format($totals['value'], 2) }}
                </td>
            </tr>
        </tfoot>
    </table>
    </div>
    @endif
</div>

@endsection
