@extends('layouts.app')

@section('title', 'Supervisor Dashboard')
@section('subtitle', 'Market Overview · Virac Public Market')

@section('content')

{{-- ── Greeting + Account Shortcut ───────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-slate-800 font-bold" style="font-size: 16px;">
            Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}
        </p>
        <p class="text-slate-400 text-[11.5px] mt-px">
            Signed in as <span class="font-mono bg-surface-muted text-slate-600" style="padding:1px 6px; border-radius:4px">{{ '@' . auth()->user()->username }}</span>
        </p>
    </div>
    <a href="{{ route('supervisor.account.edit') }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold transition-colors flex-shrink-0 text-[13px] border border-slate-200 text-slate-600 bg-white"
      
       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
       onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
        <i class="bi bi-person-gear"></i> My Account
    </a>
</div>

{{-- ── Stat Cards ────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Total Vendors --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Total Vendors</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $totalVendors ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Registered in system</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-brand-50"
                >
                <x-icon name="bi-people-fill" size="lg" class="text-blue-600" />
            </div>
        </div>
    </div>

    {{-- Total Stalls --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Total Stalls</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $totalStalls ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Active stall assignments</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-success-50"
                >
                <x-icon name="bi-shop" size="lg" class="text-emerald-600" />
            </div>
        </div>
    </div>

    {{-- Total Stock --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Today's Stock</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ number_format($totalStockKg ?? 0, 1) }} <span class="text-[14px] font-semibold text-slate-400">kg</span>
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Confirmed supply today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fefce8;">
                <x-icon name="bi-box-seam-fill" size="lg" class="text-amber-500" />
            </div>
        </div>
    </div>

    {{-- Active Staff --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Active Staff</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $activeStaff ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Market staff accounts</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fdf4ff;">
                <x-icon name="bi-person-badge-fill" size="lg" class="text-purple-600" />
            </div>
        </div>
    </div>

</div>

{{-- ── Bottom Row: Charts + Recent Activity ─────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- Forecast Chart --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-slate-700 font-bold text-[13.5px]">Price Forecast</h2>
                <p class="text-slate-400 text-[11px] mt-px">
                    ARIMA {{ config('forecast.horizon') }}-day rolling projection &mdash; First Class &middot; All species
                </p>
            </div>
            @if($hasForecastData)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold text-info-700 bg-info-50 border border-info-200 text-[10.5px]">
                    <i class="bi bi-graph-up-arrow"></i> Live
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-slate-400 bg-slate-50 border border-slate-200 text-[10.5px] font-semibold">
                    <i class="bi bi-graph-up-arrow"></i> No Data
                </span>
            @endif
        </div>

        @if($hasForecastData)
            <div class="px-5 py-4" style="height: 360px;">
                <canvas id="forecastMiniChart"></canvas>
            </div>

            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
            <script>
            (function () {
                const labels   = @json($chartLabels);
                const datasets = @json($chartDatasets);

                const palette = [
                    '#0ea5e9','#10b981','#f59e0b','#a855f7',
                    '#ef4444','#06b6d4','#84cc16','#f97316',
                ];

                const ctx = document.getElementById('forecastMiniChart').getContext('2d');
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
                    <x-icon name="bi-graph-up" size="2xl" class="text-blue-400" />
                </div>
                <p class="text-slate-500 font-semibold text-[13px]">No forecast data yet</p>
                <p class="text-slate-400 text-center mt-1 text-[11.5px]" style="max-width: 260px">
                    ARIMA forecasts will appear here once vendors start submitting inventory data.
                </p>
            </div>
        @endif
    </div>

    {{-- Recent Activity --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-slate-700 font-bold text-[13.5px]">Recent Activity</h2>
                <p class="text-slate-400 text-[11px] mt-px">System activity log</p>
            </div>
            @if($recentActivity->isNotEmpty())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-50 text-slate-400 text-[10px] font-semibold border border-slate-200"
                     >
                    {{ $recentActivity->count() }} entries
                </span>
            @endif
        </div>

        @if($recentActivity->isNotEmpty())
            <ul class="divide-y divide-slate-50 overflow-y-auto" style="max-height: 360px;">
                @foreach($recentActivity as $log)
                    @php
                        // Token classes rather than raw hex so the values come from
                        // the design system and stay purgeable by Tailwind.
                        $iconMap = [
                            'login'             => ['bi-box-arrow-in-right', 'text-info-600',    'bg-info-50'],
                            'logout'            => ['bi-box-arrow-right',    'text-slate-500',   'bg-slate-50'],
                            'confirm_price'     => ['bi-patch-check-fill',   'text-success-600', 'bg-success-50'],
                            'submit_inventory'  => ['bi-archive-fill',       'text-warning-600', 'bg-warning-50'],
                            'create'            => ['bi-plus-circle-fill',   'text-brand-600',   'bg-brand-50'],
                            'update'            => ['bi-pencil-fill',        'text-info-600',    'bg-info-50'],
                            'delete'            => ['bi-trash-fill',         'text-danger-600',  'bg-danger-50'],
                        ];
                        $action = strtolower($log->action);
                        [$icon, $textClass, $bgClass] = $iconMap[$action] ?? ['bi-activity', 'text-slate-500', 'bg-slate-50'];
                    @endphp
                    <li class="flex items-start gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 {{ $bgClass }}">
                            <i class="bi {{ $icon }} {{ $textClass }} text-[12px]"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-slate-700 font-medium truncate text-[12px]">
                                {{ $log->description ?? ucwords(str_replace('_', ' ', $log->action)) }}
                            </p>
                            <p class="text-slate-400 mt-0.5 text-[10.5px]">
                                {{ $log->user?->name ?? 'System' }}
                                &bull;
                                {{ $log->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            {{-- Empty state --}}
            <div class="flex flex-col items-center justify-center" style="height: 360px;">
                <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center mb-3">
                    <x-icon name="bi-clock-history" size="lg" class="text-slate-300" />
                </div>
                <p class="text-slate-400 font-medium text-[12px]">No recent activity</p>
                <p class="text-slate-300 mt-0.5 text-[11px]">Activity will appear here</p>
            </div>
        @endif
    </div>

</div>

@endsection