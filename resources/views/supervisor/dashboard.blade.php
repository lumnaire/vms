@extends('layouts.app')

@section('title', 'Supervisor Dashboard')
@section('subtitle', 'Market Overview · Virac Public Market')

@section('content')

{{-- ── Stat Cards ────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Total Vendors --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Total Vendors</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $totalVendors ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Registered in system</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #eff6ff;">
                <i class="bi bi-people-fill text-blue-600" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Total Stalls --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Total Stalls</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $totalStalls ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Active stall assignments</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #f0fdf4;">
                <i class="bi bi-shop text-emerald-600" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Total Stock --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Today's Stock</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ number_format($totalStockKg ?? 0, 1) }} <span style="font-size: 14px; color: #94a3b8; font-weight: 600;">kg</span>
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Confirmed supply today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fefce8;">
                <i class="bi bi-box-seam-fill text-amber-500" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Active Staff --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Active Staff</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $activeStaff ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Market staff accounts</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fdf4ff;">
                <i class="bi bi-person-badge-fill text-purple-600" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

</div>

{{-- ── Bottom Row: Charts + Recent Activity ─────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- Forecast Chart --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-slate-700 font-bold" style="font-size: 13.5px;">Price Forecast</h2>
                <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">
                    ARIMA 14-day rolling projection &mdash; First Class &middot; All species
                </p>
            </div>
            @if($hasForecastData)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold"
                      style="font-size: 10.5px; color:#0369a1; background:#eff6ff; border: 1px solid #bfdbfe;">
                    <i class="bi bi-graph-up-arrow"></i> Live
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-slate-400 bg-slate-50"
                      style="font-size: 10.5px; font-weight: 600; border: 1px solid #e2e8f0;">
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
                    <i class="bi bi-graph-up text-blue-400" style="font-size: 22px;"></i>
                </div>
                <p class="text-slate-500 font-semibold" style="font-size: 13px;">No forecast data yet</p>
                <p class="text-slate-400 text-center mt-1" style="font-size: 11.5px; max-width: 260px;">
                    ARIMA forecasts will appear here once vendors start submitting inventory data.
                </p>
            </div>
        @endif
    </div>

    {{-- Recent Activity --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-slate-700 font-bold" style="font-size: 13.5px;">Recent Activity</h2>
                <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">System activity log</p>
            </div>
            @if($recentActivity->isNotEmpty())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-50 text-slate-400"
                      style="font-size: 10px; font-weight: 600; border: 1px solid #e2e8f0;">
                    {{ $recentActivity->count() }} entries
                </span>
            @endif
        </div>

        @if($recentActivity->isNotEmpty())
            <ul class="divide-y divide-slate-50 overflow-y-auto" style="max-height: 360px;">
                @foreach($recentActivity as $log)
                    @php
                        $iconMap = [
                            'login'             => ['bi-box-arrow-in-right', '#0369a1', '#eff6ff'],
                            'logout'            => ['bi-box-arrow-right',    '#64748b', '#f8fafc'],
                            'confirm_price'     => ['bi-patch-check-fill',   '#16a34a', '#f0fdf4'],
                            'submit_inventory'  => ['bi-archive-fill',       '#d97706', '#fefce8'],
                            'create'            => ['bi-plus-circle-fill',   '#7c3aed', '#fdf4ff'],
                            'update'            => ['bi-pencil-fill',        '#0369a1', '#eff6ff'],
                            'delete'            => ['bi-trash-fill',         '#dc2626', '#fef2f2'],
                        ];
                        $action = strtolower($log->action);
                        [$icon, $color, $bg] = $iconMap[$action] ?? ['bi-activity', '#64748b', '#f8fafc'];
                    @endphp
                    <li class="flex items-start gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                             style="background: {{ $bg }};">
                            <i class="bi {{ $icon }}" style="font-size: 12px; color: {{ $color }};"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-slate-700 font-medium truncate" style="font-size: 12px;">
                                {{ $log->description ?? ucwords(str_replace('_', ' ', $log->action)) }}
                            </p>
                            <p class="text-slate-400 mt-0.5" style="font-size: 10.5px;">
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
                    <i class="bi bi-clock-history text-slate-300" style="font-size: 18px;"></i>
                </div>
                <p class="text-slate-400 font-medium" style="font-size: 12px;">No recent activity</p>
                <p class="text-slate-300 mt-0.5" style="font-size: 11px;">Activity will appear here</p>
            </div>
        @endif
    </div>

</div>

@endsection