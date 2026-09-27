@extends('layouts.app')

@section('title', 'Staff Dashboard')
@section('subtitle', 'Price Confirmation & Vendor Management')

@section('content')

{{-- ── Stat Cards ────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Pending Confirmations (Action Required) --}}
    <div class="stat-card rounded-xl p-5 border overflow-hidden relative"
         style="background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); border-color: #1d4ed8; box-shadow: 0 4px 14px rgba(29,78,216,0.3);">
        {{-- Decorative circle --}}
        <div class="absolute" style="top: -12px; right: -12px; width: 70px; height: 70px; border-radius: 50%; background: rgba(255,255,255,0.08)"></div>
        <div class="flex items-start justify-between relative">
            <div>
                <p class="text-blue-200 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Pending</p>
                <p class="text-white font-bold mt-1 text-[28px] leading-[1]">
                    {{ $pendingCount ?? 0 }}
                </p>
                <p class="text-blue-200 mt-1 text-[11px]">Awaiting your review</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: rgba(255,255,255,0.15);">
                <x-icon name="bi-hourglass-split" size="lg" class="text-white" />
            </div>
        </div>
        @if(($pendingCount ?? 0) > 0)
            <a href="{{ route('staff.confirmations.index') }}"
               class="inline-flex items-center gap-1 mt-3 text-blue-100 hover:text-white transition-colors text-[11.5px] font-semibold"
              >
                Review now <i class="bi bi-arrow-right"></i>
            </a>
        @endif
    </div>

    {{-- Confirmed Today --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Confirmed Today</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $confirmedToday ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Entries approved today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-success-50"
                >
                <x-icon name="bi-check-circle-fill" size="lg" class="text-emerald-500" />
            </div>
        </div>
    </div>

    {{-- Rejected Today --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Rejected Today</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $rejectedToday ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Entries rejected today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fff1f2;">
                <x-icon name="bi-x-circle-fill" size="lg" class="text-rose-500" />
            </div>
        </div>
    </div>

    {{-- Total Vendors --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Total Vendors</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $totalVendors ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Managed by you</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-brand-50"
                >
                <x-icon name="bi-people-fill" size="lg" class="text-blue-600" />
            </div>
        </div>
    </div>

</div>

{{-- ── Bottom Row: Price Chart + Pending Entries ────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- Price Trend Chart --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-slate-700 font-bold text-[13.5px]">Price Trends</h2>
                <p class="text-slate-400 text-[11px] mt-px">Avg confirmed price/kg — last 7 days</p>
            </div>
            @if($hasPriceTrendData)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold text-info-700 bg-info-50 border border-info-200 text-[10.5px]">
                    <i class="bi bi-bar-chart-line"></i> Live
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-slate-400 bg-slate-50 border border-slate-200 text-[10.5px] font-semibold">
                    <i class="bi bi-bar-chart-line"></i> No Data
                </span>
            @endif
        </div>

        @if($hasPriceTrendData)
            {{-- Live Chart --}}
            <div class="px-5 py-4" style="height: 360px;">
                <canvas id="priceTrendChart"></canvas>
            </div>

            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
            <script>
            (function () {
                const labels   = @json($chartLabels);
                const datasets = @json($chartDatasets);

                const palette = ['#0ea5e9', '#10b981', '#f59e0b', '#a855f7'];

                const ctx = document.getElementById('priceTrendChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: datasets.map((ds, i) => ({
                            label: ds.label,
                            data: ds.data,
                            borderColor: palette[i % palette.length],
                            backgroundColor: palette[i % palette.length] + '18',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: palette[i % palette.length],
                            fill: false,
                            tension: 0.4,
                            spanGaps: true,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: { font: { size: 10 }, color: '#64748b', boxWidth: 10, padding: 10 },
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ctx.parsed.y !== null
                                        ? ` ${ctx.dataset.label}: ₱${ctx.parsed.y.toFixed(2)}/kg`
                                        : null,
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { color: '#f1f5f9' },
                                ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 0 },
                            },
                            y: {
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    font: { size: 10 }, color: '#94a3b8',
                                    callback: v => '₱' + v.toFixed(0),
                                },
                            },
                        },
                    },
                });
            })();
            </script>
            @endpush

        @else
            {{-- Empty state --}}
            <div class="flex flex-col items-center justify-center"
                 style="height: 360px; background: repeating-linear-gradient(0deg, transparent, transparent 39px, #f1f5f9 39px, #f1f5f9 40px), repeating-linear-gradient(90deg, transparent, transparent 39px, #f1f5f9 39px, #f1f5f9 40px);">
                <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-3">
                    <x-icon name="bi-bar-chart-line" size="2xl" class="text-blue-400" />
                </div>
                <p class="text-slate-500 font-semibold text-[13px]">No chart data yet</p>
                <p class="text-slate-400 text-center mt-1 text-[11.5px]" style="max-width: 260px">
                    Price trend charts will appear here once inventory entries are confirmed.
                </p>
            </div>
        @endif
    </div>

    {{-- Pending Queue --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-slate-700 font-bold text-[13.5px]">Pending Queue</h2>
                <p class="text-slate-400 text-[11px] mt-px">Awaiting confirmation</p>
            </div>
            <a href="{{ route('staff.confirmations.index') }}"
               class="text-blue-600 hover:text-blue-700 font-semibold transition-colors text-[11.5px]"
              >
                View all <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        @if($pendingEntries->isNotEmpty())
            <ul class="divide-y divide-slate-50 overflow-y-auto" style="max-height: 360px;">
                @foreach($pendingEntries as $entry)
                    <li class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 bg-brand-50"
                            >
                            <x-icon name="bi-hourglass-split" size="sm" class="text-brand-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-slate-700 font-medium truncate text-[12px]">
                                {{ $entry->fishType->name ?? '—' }}
                                <span class="text-slate-400 font-normal">&bull; {{ $entry->quality_class }}</span>
                            </p>
                            <p class="text-slate-400 mt-0.5 text-[10.5px]">
                                {{ $entry->vendor->name ?? 'Unknown vendor' }}
                                @if($entry->vendor->vendorProfile)
                                    &bull; Stall {{ $entry->vendor->vendorProfile->stall_number }}
                                @endif
                                &bull; ₱{{ number_format($entry->price_per_kg, 2) }}/kg
                            </p>
                        </div>
                        <a href="{{ route('staff.confirmations.index') }}"
                           class="flex-shrink-0 text-blue-500 hover:text-blue-700 transition-colors text-[11px]"
                          >
                            Review <i class="bi bi-arrow-right"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="flex flex-col items-center justify-center" style="height: 360px;">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 bg-success-50"
                    >
                    <x-icon name="bi-check2-all" size="lg" class="text-emerald-400" />
                </div>
                <p class="text-slate-500 font-medium text-[12px]">All caught up!</p>
                <p class="text-slate-300 mt-0.5 text-[11px]">No pending entries today</p>
            </div>
        @endif
    </div>

</div>

@endsection