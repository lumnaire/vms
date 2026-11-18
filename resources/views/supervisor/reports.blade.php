@extends('layouts.app')

@section('title', ucfirst($entranceReport->range->period) . ' Fish Entrance Report — ' . $entranceReport->range->label())

@push('styles')
<style>
    .report-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0/.06);
    }
    .section-heading {
        font-size: 0.8125rem; font-weight: 600; color: #374151;
        padding: 0.875rem 1.25rem; border-bottom: 1px solid #f3f4f6;
    }
    .rpt-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
    .rpt-table thead th {
        background: #f9fafb; padding: 0.625rem 1rem;
        font-size: 0.7rem; font-weight: 700; letter-spacing: .05em;
        text-transform: uppercase; color: #6b7280;
        border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    .rpt-table thead th:not(:first-child) { text-align: right; }
    .rpt-table thead th:first-child,
    .rpt-table thead th:nth-child(2),
    .rpt-table thead th:nth-child(3),
    .rpt-table thead th:nth-child(4) { text-align: left; }
    .rpt-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .1s; }
    .rpt-table tbody tr:hover   { background: #eff6ff; }
    .rpt-table tbody tr:nth-child(even) { background: #f9fafb; }
    .rpt-table tbody tr:nth-child(even):hover { background: #eff6ff; }
    .rpt-table td { padding: 0.625rem 1rem; color: #374151; }
    .rpt-table tfoot td {
        padding: 0.625rem 1rem; background: #f3f4f6;
        border-top: 2px solid #d1d5db; font-weight: 700; color: #111827;
    }
    .badge {
        display: inline-flex; align-items: center;
        padding: 0.125rem 0.5rem; border-radius: 0.25rem;
        font-size: 0.7rem; font-weight: 600;
    }
    .badge-a  { background: #dcfce7; color: #166534; }
    .badge-b  { background: #fef9c3; color: #854d0e; }
    .badge-c  { background: #f3f4f6; color: #374151; }
    .btn-pdf {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: #1e3a8a; color: #fff; padding: 0.5rem 1.125rem;
        border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600;
        border: none; cursor: pointer; transition: background .15s;
    }
    .btn-pdf:hover { background: #1e40af; }
    .btn-pdf:disabled { background: #93c5fd; cursor: not-allowed; }
    .btn-filter {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: #2563eb; color: #fff; padding: 0.5rem 1rem;
        border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600;
        border: none; cursor: pointer; transition: background .15s;
    }
    .btn-filter:hover { background: #1d4ed8; }
    .date-input {
        border: 1px solid #d1d5db; border-radius: 0.5rem;
        padding: 0.45rem 0.75rem; font-size: 0.8125rem; color: #374151;
        outline: none;
    }
    .date-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px #bfdbfe55; }
    @media print { body * { visibility: hidden; } }
</style>
@endpush



@section('content')
<div class="bg-surface-subtle" style="min-height:100vh; padding:1.5rem">
<div style="max-width:1200px; margin:0 auto;">

    {{-- ── Page Header ───────────────────────────────────────── --}}
    <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="font-bold uppercase tracking-[0.08em] mb-1" style="font-size:0.7rem; color:#2563eb">
                Supervisor Portal
            </div>
            <h1 class="font-bold" style="font-size:1.5rem; color:#111827; margin:0 0 .25rem">
                Fish Entrance Reports
            </h1>
            <p style="font-size:0.8125rem; color:#6b7280; margin:0;">
                {{ $entranceReport->range->label() }}
            </p>
        </div>

    </div>

    @include('supervisor.report-forms')

    @if($entranceReport->range->period === 'daily')
    {{-- ── Summary Cards ──────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <x-stat-card tinted tone="brand"
                     label="Total Market Volume"
                     :value="$marketTotalKg" unit="kg" />
        <x-stat-card tinted tone="success"
                     label="Active Vendors Today"
                     :value="$activeVendors" decimals="0" />
        <x-stat-card tinted tone="warning"
                     label="Fish Types Recorded"
                     :value="$priceBreakdown->pluck('fish_name')->unique()->count()"
                     decimals="0" />
        <x-stat-card tinted tone="neutral"
                     label="Report Date"
                     :raw="true"
                     :value="\Carbon\Carbon::parse($reportDate)->format('M d, Y')" />
    </div>

    {{-- ── Vendor Sales Summary ────────────────────────────────── --}}
    <div class="report-card overflow-hidden" style="margin-bottom:1.5rem">
        <div class="section-heading">
            <svg style="display:inline; width:14px; height:14px; margin-right:6px; vertical-align:-2px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
            </svg>
            Vendor Sales Summary
        </div>
        <div style="overflow-x:auto;">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width:50px">#</th>
                        <th>Stall No.</th>
                        <th>Vendor Name</th>
                        <th>Fish Types</th>
                        <th>Total Stock (kg)</th>
                        <th>Avg. Price / kg</th>
                        <th>Avg. Markup / kg</th>
                        <th>Entries</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorSummaries as $i => $vs)
                    <tr>
                        <td class="text-center" style="color:#9ca3af; font-size:0.75rem">{{ $i + 1 }}</td>
                        <td style="font-family:monospace; font-size:.8rem; color:#4b5563;">
                            {{ $vs['vendor']->vendorProfile?->stall_number ?? '—' }}
                        </td>
                        <td class="font-semibold" style="color:#111827">{{ $vs['vendor']->name }}</td>
                        <td style="color:#6b7280; font-size:.775rem;">{{ $vs['fish_list'] ?: '—' }}</td>
                        <td class="text-right font-semibold">{{ number_format($vs['total_stock_kg'], 2) }}</td>
                        <td class="text-right" style="color:#374151">₱{{ number_format($vs['avg_price'] ?? 0, 2) }}</td>
                        <td class="text-right" style="color:#374151">
                            @if($vs['avg_markup'] === null)
                                <span style="color:#9ca3af">—</span>
                            @else
                                {{ $vs['avg_markup'] >= 0 ? '+' : '−' }}₱{{ number_format(abs($vs['avg_markup']), 2) }}
                                <span style="display:block; color:#9ca3af; font-size:.7rem">{{ $vs['above_tariff'] }} above tariff</span>
                            @endif
                        </td>
                        <td class="text-right" style="color:#6b7280">{{ $vs['total_entries'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td class="text-center" colspan="8" style="padding:2.5rem; color:#9ca3af; font-size:.875rem">
                            No vendor activity recorded for this date.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($vendorSummaries->count())
                <tfoot>
                    <tr>
                        <td class="text-right uppercase" colspan="4" style="font-size:.75rem; letter-spacing:.05em">
                            Market Total
                        </td>
                        <td class="text-right">{{ number_format($marketTotalKg, 2) }} kg</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ── Price Breakdown ─────────────────────────────────────── --}}
    <div class="report-card overflow-hidden">
        <div class="section-heading">
            <svg style="display:inline; width:14px; height:14px; margin-right:6px; vertical-align:-2px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
            </svg>
            Price Breakdown by Fish Type &amp; Quality Class
        </div>
        <div style="overflow-x:auto;">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th>Fish Type</th>
                        <th>Quality Class</th>
                        <th>Tariff / kg</th>
                        <th>Avg. Price / kg</th>
                        <th>Min. Price / kg</th>
                        <th>Max. Price / kg</th>
                        <th>Total Volume (kg)</th>
                        <th>Vendors</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($priceBreakdown as $i => $pb)
                    <tr>
                        <td class="font-semibold" style="color:#111827">{{ $pb['fish_name'] ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $pb['quality_class'] === 'A' ? 'badge-a' : ($pb['quality_class'] === 'B' ? 'badge-b' : 'badge-c') }}">
                                Class {{ $pb['quality_class'] }}
                            </span>
                        </td>
                        <td class="text-right" style="color:#7e22ce">{{ $pb['tariff'] !== null ? '₱' . number_format($pb['tariff'], 2) : '—' }}</td>
                        <td class="text-right font-semibold" style="color:#111827">
                            ₱{{ number_format($pb['avg_price'], 2) }}
                            @if($pb['tariff'] !== null)
                                <x-tariff-diff :comparison="App\Models\PriceGuide::compare($pb['avg_price'], $pb['tariff'])" class="flex mt-0.5" />
                            @endif
                        </td>
                        <td class="text-right" style="color:#6b7280">₱{{ number_format($pb['min_price'], 2) }}</td>
                        <td class="text-right" style="color:#6b7280">₱{{ number_format($pb['max_price'], 2) }}</td>
                        <td class="text-right font-semibold">{{ number_format($pb['total_kg'], 2) }}</td>
                        <td class="text-right" style="color:#6b7280">{{ $pb['vendor_count'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td class="text-center" colspan="8" style="padding:2.5rem; color:#9ca3af; font-size:.875rem">
                            No price data available for this date.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @endif
</div>{{-- /max-width --}}
</div>{{-- /min-height --}}
@endsection

