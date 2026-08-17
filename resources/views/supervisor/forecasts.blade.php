@extends('layouts.app')

@section('title', 'ARIMA Forecasts')
@section('subtitle', '14-Day Supply & Price Projection · Virac Public Market')

@push('styles')
<style>
    .trend-badge-up     { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
    .trend-badge-down   { background:#fff1f2; color:#e11d48; border:1px solid #fecdd3; }
    .trend-badge-stable { background:#f8fafc; color:#64748b; border:1px solid #e2e8f0; }

    .filter-sel {
        background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
        padding:7px 12px; font-size:13px; font-weight:500; color:#334155;
        transition:border-color .15s, box-shadow .15s; appearance:none;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%2394a3b8' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat:no-repeat; background-position:right 10px center; padding-right:30px;
        cursor:pointer;
    }
    .filter-sel:focus { outline:none; border-color:#93c5fd; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }

    .fc-table td, .fc-table th { padding:10px 16px; }
    .fc-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
    .fc-table tbody tr:hover td { background:#f8fafc; }
    .fc-table tbody tr:last-child { border-bottom:none; }

    #forecastChart { display:block; }
</style>
@endpush

@section('content')

@php
    /* ── Resolved names & flags ──────────────────────────────── */
    $selectedFishType = $fishTypes->firstWhere('id', $selectedFishTypeId);
    $fishTypeName     = $selectedFishType?->name ?? 'Unknown';
    $isMetricPrice    = $selectedMetric === 'price';
    $metricUnit       = $isMetricPrice ? '₱/kg' : 'kg';
    $hasForecasts     = $forecasts->isNotEmpty();
    $hasHistorical    = $historical->isNotEmpty();

    /* ── Summary stats ───────────────────────────────────────── */
    $avgForecast  = $hasForecasts ? round($forecasts->avg('predicted_value'), 2) : null;
    $minForecast  = $hasForecasts ? $forecasts->min('predicted_value')           : null;
    $maxForecast  = $hasForecasts ? $forecasts->max('predicted_value')           : null;

    /* ── Chart data arrays (PHP → JS) ────────────────────────── */
    $histLabels = $historical->keys()->values()->toArray();
    $histVals   = $historical->values()->map(fn($v) => round((float)$v, 2))->values()->toArray();

    $fcLabels   = $forecasts->map(fn($f) => $f->forecast_date->format('Y-m-d'))->values()->toArray();
    $fcVals     = $forecasts->pluck('predicted_value')->map(fn($v) => (float)$v)->values()->toArray();
    $fcMins     = $forecasts->pluck('predicted_min')->map(fn($v) => $v !== null ? (float)$v : null)->values()->toArray();
    $fcMaxs     = $forecasts->pluck('predicted_max')->map(fn($v) => $v !== null ? (float)$v : null)->values()->toArray();

    /* ── Merged, sorted label set ────────────────────────────── */
    $allLabels  = collect(array_unique(array_merge($histLabels, $fcLabels)))->sort()->values()->toArray();

    /* ── ARIMA model params ──────────────────────────────────── */
    $arimaParams  = $latestForecast?->arima_params;
    $arimaString  = $arimaParams
        ? 'AR(' . ($arimaParams['p'] ?? '?') . ')·I(' . ($arimaParams['d'] ?? '?') . ')·MA(' . ($arimaParams['q'] ?? '?') . ')'
        : null;

    /* ── Quality label helper ────────────────────────────────── */
    $qualityLabel = fn($qc) => str_ends_with($qc, 'Class') ? $qc : $qc . ' Class';
@endphp


{{-- ═══════════════════════════════════════════════════════════════
     FILTER BAR
═══════════════════════════════════════════════════════════════════ --}}
<form method="GET" action="{{ route('supervisor.forecasts.index') }}" id="filterForm">
<div class="bg-white rounded-xl border border-slate-100 px-4 py-3.5 mb-5 flex flex-wrap items-end gap-3"
     style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">

    {{-- Fish Type --}}
    <div class="flex flex-col gap-1.5 flex-shrink-0" style="min-width:170px;">
        <label class="text-slate-400 font-semibold"
               style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;">
            <i class="bi bi-water" style="margin-right:4px;"></i>Fish Type
        </label>
        <select name="fish_type_id"
                onchange="document.getElementById('filterForm').submit()"
                class="filter-sel">
            @foreach($fishTypes as $ft)
                <option value="{{ $ft->id }}"
                        {{ $selectedFishTypeId == $ft->id ? 'selected' : '' }}>
                    {{ $ft->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Quality Class --}}
    <div class="flex flex-col gap-1.5 flex-shrink-0" style="min-width:155px;">
        <label class="text-slate-400 font-semibold"
               style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;">
            Quality Class
        </label>
        <select name="quality_class"
                onchange="document.getElementById('filterForm').submit()"
                class="filter-sel">
            @foreach($qualityClasses as $qc)
                <option value="{{ $qc }}"
                        {{ $selectedQuality === $qc ? 'selected' : '' }}>
                    {{ $qualityLabel($qc) }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Metric Toggle --}}
    <div class="flex flex-col gap-1.5 flex-shrink-0">
        <label class="text-slate-400 font-semibold"
               style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;">
            Metric
        </label>
        <div class="flex items-stretch rounded-lg border border-slate-200 overflow-hidden"
             style="background:#f8fafc;">
            @foreach($metrics as $key => $label)
                <button type="submit" name="metric" value="{{ $key }}"
                        style="padding:7px 16px; font-size:12.5px; font-weight:600; transition:all .15s; cursor:pointer;
                               {{ $selectedMetric === $key
                                   ? 'background:#2563eb; color:#fff; box-shadow:inset 0 1px 3px rgba(0,0,0,0.15);'
                                   : 'background:transparent; color:#64748b;' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ARIMA badge / meta text --}}
    <div class="ml-auto flex-shrink-0 hidden md:flex items-end pb-0.5">
        @if($arimaString)
            <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-indigo-200 text-indigo-600"
                  style="background:#eef2ff; font-size:12px; font-weight:700; font-family:monospace; letter-spacing:.03em;">
                <i class="bi bi-cpu" style="font-size:13px;"></i>
                {{ $arimaString }}
            </span>
        @else
            <span class="flex items-center gap-1.5 text-slate-400" style="font-size:11.5px;">
                <i class="bi bi-cpu"></i> ARIMA model · Updated daily
            </span>
        @endif
    </div>

</div>
</form>


{{-- ═══════════════════════════════════════════════════════════════
     SUMMARY STAT CARDS
═══════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">

    {{-- Trend Indicator --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100"
         style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold"
                   style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;">
                    14-Day Trend
                </p>

                @if($trendLabel === 'upward')
                    <p class="font-bold mt-2 flex items-center gap-1.5"
                       style="font-size:17px;line-height:1;color:#16a34a;">
                        <i class="bi bi-arrow-up-circle-fill"></i> Upward
                    </p>
                    <p class="text-slate-400 mt-1.5" style="font-size:11px;">
                        {{ $isMetricPrice ? 'Prices trending up' : 'Supply increasing' }}
                    </p>
                @elseif($trendLabel === 'downward')
                    <p class="font-bold mt-2 flex items-center gap-1.5"
                       style="font-size:17px;line-height:1;color:#e11d48;">
                        <i class="bi bi-arrow-down-circle-fill"></i> Downward
                    </p>
                    <p class="text-slate-400 mt-1.5" style="font-size:11px;">
                        {{ $isMetricPrice ? 'Prices trending down' : 'Supply declining' }}
                    </p>
                @elseif($trendLabel === 'stable')
                    <p class="font-bold mt-2 flex items-center gap-1.5"
                       style="font-size:17px;line-height:1;color:#64748b;">
                        <i class="bi bi-dash-circle-fill"></i> Stable
                    </p>
                    <p class="text-slate-400 mt-1.5" style="font-size:11px;">
                        No significant movement
                    </p>
                @else
                    <p class="font-bold mt-2 text-slate-300"
                       style="font-size:17px;line-height:1;">— No data</p>
                    <p class="text-slate-400 mt-1.5" style="font-size:11px;">No forecasts yet</p>
                @endif
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background:#eff6ff;">
                <i class="bi bi-graph-up-arrow text-blue-600" style="font-size:17px;"></i>
            </div>
        </div>
    </div>

    {{-- 14-Day Average --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100"
         style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold"
                   style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;">
                    Avg Forecast
                </p>
                <p class="text-slate-800 font-bold mt-2" style="font-size:24px;line-height:1;">
                    @if($avgForecast !== null)
                        {{ $isMetricPrice ? '₱' : '' }}{{ number_format($avgForecast, 2) }}
                        <span style="font-size:12px;color:#94a3b8;font-weight:600;">{{ $metricUnit }}</span>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </p>
                <p class="text-slate-400 mt-1.5" style="font-size:11px;">Over 14 days</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background:#fefce8;">
                <i class="bi bi-calculator text-amber-500" style="font-size:17px;"></i>
            </div>
        </div>
    </div>

    {{-- Projected Low --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100"
         style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold"
                   style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;">
                    Projected Low
                </p>
                <p class="text-slate-800 font-bold mt-2" style="font-size:24px;line-height:1;">
                    @if($minForecast !== null)
                        {{ $isMetricPrice ? '₱' : '' }}{{ number_format($minForecast, 2) }}
                        <span style="font-size:12px;color:#94a3b8;font-weight:600;">{{ $metricUnit }}</span>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </p>
                <p class="text-slate-400 mt-1.5" style="font-size:11px;">Lowest in period</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background:#fff1f2;">
                <i class="bi bi-arrow-down text-rose-400" style="font-size:17px;"></i>
            </div>
        </div>
    </div>

    {{-- Projected High --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100"
         style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold"
                   style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;">
                    Projected High
                </p>
                <p class="text-slate-800 font-bold mt-2" style="font-size:24px;line-height:1;">
                    @if($maxForecast !== null)
                        {{ $isMetricPrice ? '₱' : '' }}{{ number_format($maxForecast, 2) }}
                        <span style="font-size:12px;color:#94a3b8;font-weight:600;">{{ $metricUnit }}</span>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </p>
                <p class="text-slate-400 mt-1.5" style="font-size:11px;">Highest in period</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background:#f0fdf4;">
                <i class="bi bi-arrow-up text-emerald-500" style="font-size:17px;"></i>
            </div>
        </div>
    </div>

</div>


{{-- ═══════════════════════════════════════════════════════════════
     MAIN CHART CARD
═══════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden mb-5"
     style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">

    {{-- Card header --}}
    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center gap-x-4 gap-y-2">

        {{-- Title --}}
        <div class="flex-1 min-w-0">
            <h2 class="text-slate-700 font-bold" style="font-size:14px;">
                {{ $fishTypeName }}
                <span class="text-slate-400 font-medium">
                    — {{ $isMetricPrice ? 'Price Forecast' : 'Supply Forecast' }}
                </span>
            </h2>
            <p class="text-slate-400 mt-0.5" style="font-size:11px;">
                30-day historical · 14-day ARIMA projection ·
                {{ $qualityLabel($selectedQuality) }}
            </p>
        </div>

        {{-- Trend badge --}}
        @if($trendLabel === 'upward')
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full trend-badge-up flex-shrink-0"
                  style="font-size:11.5px;font-weight:700;">
                <i class="bi bi-arrow-up-short" style="font-size:15px;"></i> Upward
            </span>
        @elseif($trendLabel === 'downward')
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full trend-badge-down flex-shrink-0"
                  style="font-size:11.5px;font-weight:700;">
                <i class="bi bi-arrow-down-short" style="font-size:15px;"></i> Downward
            </span>
        @elseif($trendLabel === 'stable')
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full trend-badge-stable flex-shrink-0"
                  style="font-size:11.5px;font-weight:700;">
                <i class="bi bi-dash" style="font-size:14px;"></i> Stable
            </span>
        @endif

        {{-- Last updated --}}
        @if($latestForecast?->generated_at)
            <span class="text-slate-400 flex-shrink-0 hidden sm:block" style="font-size:11px;">
                <i class="bi bi-clock" style="margin-right:3px;"></i>
                Updated {{ $latestForecast->generated_at->diffForHumans() }}
            </span>
        @endif

        {{-- Legend --}}
        <div class="hidden sm:flex items-center gap-4 flex-shrink-0 border-l border-slate-100 pl-4">
            {{-- Historical --}}
            <div class="flex items-center gap-1.5">
                <div style="width:22px; height:2.5px; background:#2563eb; border-radius:2px;"></div>
                <span class="text-slate-500" style="font-size:11px;">Historical</span>
            </div>
            {{-- Forecast --}}
            <div class="flex items-center gap-1.5">
                <svg width="22" height="5" viewBox="0 0 22 5" style="flex-shrink:0;">
                    <line x1="0" y1="2.5" x2="22" y2="2.5"
                          stroke="#2563eb" stroke-width="2.5"
                          stroke-dasharray="6,4"/>
                </svg>
                <span class="text-slate-500" style="font-size:11px;">Forecast</span>
            </div>
            {{-- CI band --}}
            <div class="flex items-center gap-1.5">
                <div style="width:16px; height:10px; background:rgba(59,130,246,0.15);
                            border-radius:3px; border:1px solid rgba(59,130,246,0.25);"></div>
                <span class="text-slate-500" style="font-size:11px;">95% CI</span>
            </div>
        </div>

    </div>

    {{-- Chart body / Empty state --}}
    @if($hasForecasts || $hasHistorical)
        <div style="padding:20px 20px 14px;">
            <div style="position:relative; height:360px;">
                <canvas id="forecastChart"></canvas>
            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center"
             style="height:340px;
                    background: repeating-linear-gradient(0deg,transparent,transparent 39px,#f1f5f9 39px,#f1f5f9 40px),
                                repeating-linear-gradient(90deg,transparent,transparent 39px,#f1f5f9 39px,#f1f5f9 40px);">
            <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center mb-4">
                <i class="bi bi-graph-up text-blue-300" style="font-size:26px;"></i>
            </div>
            <p class="text-slate-500 font-semibold" style="font-size:14px;">
                No forecast data available
            </p>
            <p class="text-slate-400 text-center mt-2" style="font-size:12px; max-width:300px; line-height:1.6;">
                ARIMA forecasts will appear once vendors submit and confirm
                inventory entries for <strong>{{ $fishTypeName }}</strong>.
            </p>
        </div>
    @endif

</div>


{{-- ═══════════════════════════════════════════════════════════════
     14-DAY FORECAST BREAKDOWN TABLE
═══════════════════════════════════════════════════════════════════ --}}
@if($hasForecasts)
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden"
     style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">

    {{-- Table header --}}
    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
            <h2 class="text-slate-700 font-bold" style="font-size:14px;">
                14-Day Forecast Breakdown
            </h2>
            <p class="text-slate-400 mt-0.5" style="font-size:11px;">
                Predicted values with confidence intervals ·
                {{ $fishTypeName }} · {{ $metrics[$selectedMetric] }}
            </p>
        </div>
        @if($arimaString)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-200
                         text-indigo-600 hidden sm:flex flex-shrink-0"
                  style="background:#eef2ff; font-size:12px; font-weight:700;
                         font-family:monospace; letter-spacing:.02em;">
                <i class="bi bi-cpu" style="font-size:13px;"></i>
                {{ $arimaString }}
            </span>
        @endif
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full fc-table" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <th class="text-left text-slate-500 font-semibold"
                        style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;">
                        Day
                    </th>
                    <th class="text-left text-slate-500 font-semibold"
                        style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;">
                        Date
                    </th>
                    <th class="text-right text-slate-500 font-semibold"
                        style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;">
                        Predicted {{ $metrics[$selectedMetric] }}
                    </th>
                    <th class="text-right text-slate-500 font-semibold hidden md:table-cell"
                        style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;">
                        Lower CI
                    </th>
                    <th class="text-right text-slate-500 font-semibold hidden md:table-cell"
                        style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;">
                        Upper CI
                    </th>
                    <th class="text-right text-slate-500 font-semibold hidden sm:table-cell"
                        style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;">
                        Δ Change
                    </th>
                    <th class="text-center text-slate-500 font-semibold"
                        style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;">
                        Trend
                    </th>
                </tr>
            </thead>
            <tbody>
                @php
                    $dayNum  = 1;
                    $prevVal = (float)($forecasts->first()?->predicted_value ?? 0);
                @endphp

                @foreach($forecasts as $fc)
                    @php
                        $val      = (float)$fc->predicted_value;
                        $diff     = $dayNum > 1 ? $val - $prevVal : null;
                        $prevVal  = $val;
                        $isToday  = $fc->forecast_date->isToday();
                    @endphp

                    <tr class="{{ $isToday ? 'bg-blue-50/40' : '' }}">

                        {{-- Day number --}}
                        <td>
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full
                                         font-bold text-slate-500"
                                  style="background:#f1f5f9; font-size:10.5px;">
                                {{ $dayNum }}
                            </span>
                        </td>

                        {{-- Date --}}
                        <td>
                            <p class="text-slate-700 font-semibold" style="font-size:13px;">
                                {{ $fc->forecast_date->format('D, M j') }}
                                @if($isToday)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full
                                                 text-blue-600 font-bold ml-1"
                                          style="background:#eff6ff; font-size:9px; letter-spacing:.04em;
                                                 text-transform:uppercase;">
                                        Today
                                    </span>
                                @endif
                            </p>
                            <p class="text-slate-400" style="font-size:10.5px;">
                                {{ $fc->forecast_date->format('Y-m-d') }}
                            </p>
                        </td>

                        {{-- Predicted value --}}
                        <td class="text-right">
                            <p class="text-slate-800 font-bold" style="font-size:13.5px;">
                                {{ $isMetricPrice ? '₱' : '' }}{{ number_format($val, 2) }}
                                <span style="font-size:11px;color:#94a3b8;font-weight:500;">
                                    {{ $metricUnit }}
                                </span>
                            </p>
                        </td>

                        {{-- Lower CI --}}
                        <td class="text-right hidden md:table-cell text-slate-500"
                            style="font-size:12.5px;">
                            @if($fc->predicted_min !== null)
                                {{ $isMetricPrice ? '₱' : '' }}{{ number_format($fc->predicted_min, 2) }}
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>

                        {{-- Upper CI --}}
                        <td class="text-right hidden md:table-cell text-slate-500"
                            style="font-size:12.5px;">
                            @if($fc->predicted_max !== null)
                                {{ $isMetricPrice ? '₱' : '' }}{{ number_format($fc->predicted_max, 2) }}
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>

                        {{-- Δ change --}}
                        <td class="text-right hidden sm:table-cell">
                            @if($diff === null)
                                <span class="text-slate-300" style="font-size:11.5px;">—</span>
                            @elseif($diff > 0.001)
                                <span class="font-semibold" style="color:#16a34a; font-size:12.5px;">
                                    +{{ $isMetricPrice ? '₱' : '' }}{{ number_format(abs($diff), 2) }}
                                </span>
                            @elseif($diff < -0.001)
                                <span class="font-semibold" style="color:#e11d48; font-size:12.5px;">
                                    −{{ $isMetricPrice ? '₱' : '' }}{{ number_format(abs($diff), 2) }}
                                </span>
                            @else
                                <span class="text-slate-400" style="font-size:12.5px;">±0.00</span>
                            @endif
                        </td>

                        {{-- Trend badge --}}
                        <td class="text-center">
                            @if($fc->trend === 'upward')
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5
                                             rounded-full trend-badge-up"
                                      style="font-size:10.5px;font-weight:700;">
                                    <i class="bi bi-arrow-up-short" style="font-size:13px;"></i> Up
                                </span>
                            @elseif($fc->trend === 'downward')
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5
                                             rounded-full trend-badge-down"
                                      style="font-size:10.5px;font-weight:700;">
                                    <i class="bi bi-arrow-down-short" style="font-size:13px;"></i> Down
                                </span>
                            @elseif($fc->trend === 'stable')
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5
                                             rounded-full trend-badge-stable"
                                      style="font-size:10.5px;font-weight:700;">
                                    <i class="bi bi-dash" style="font-size:13px;"></i> Stable
                                </span>
                            @else
                                <span class="text-slate-300" style="font-size:11px;">—</span>
                            @endif
                        </td>
                    </tr>
                    @php $dayNum++; @endphp
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endif

@endsection


{{-- ═══════════════════════════════════════════════════════════════
     CHART.JS INITIALIZATION
═══════════════════════════════════════════════════════════════════ --}}
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
(function () {
    const canvasEl = document.getElementById('forecastChart');
    if (!canvasEl) return;

    // ── Raw PHP data ───────────────────────────────────────────
    const allLabels  = @json($allLabels);
    const histLabels = @json($histLabels);
    const histVals   = @json($histVals);
    const fcLabels   = @json($fcLabels);
    const fcVals     = @json($fcVals);
    const fcMins     = @json($fcMins);
    const fcMaxs     = @json($fcMaxs);
    const todayStr   = '{{ today()->format('Y-m-d') }}';
    const isPrice    = {{ $isMetricPrice ? 'true' : 'false' }};
    const unit       = {!! json_encode($metricUnit) !!};

    if (!allLabels.length) return;

    // ── Build date-keyed maps for O(1) lookup ─────────────────
    const histMap   = Object.fromEntries(histLabels.map((d, i) => [d, histVals[i]]));
    const fcMap     = Object.fromEntries(fcLabels.map((d, i) => [d, fcVals[i]]));
    const fcMinMap  = Object.fromEntries(
        fcLabels.map((d, i) => [d, fcMins[i]]).filter(([, v]) => v !== null)
    );
    const fcMaxMap  = Object.fromEntries(
        fcLabels.map((d, i) => [d, fcMaxs[i]]).filter(([, v]) => v !== null)
    );

    // ── Align to the full label axis (null = gap in line) ─────
    const histData  = allLabels.map(d => histMap[d]  ?? null);
    const fcData    = allLabels.map(d => fcMap[d]    ?? null);
    const fcMinData = allLabels.map(d => fcMinMap[d] ?? null);
    const fcMaxData = allLabels.map(d => fcMaxMap[d] ?? null);

    // ── Date formatter ────────────────────────────────────────
    function fmtDate(s) {
        const p = s.split('-');
        const months = ['Jan','Feb','Mar','Apr','May','Jun',
                        'Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[parseInt(p[1]) - 1] + ' ' + parseInt(p[2]);
    }

    // ── "Today" divider inline plugin ────────────────────────
    const todayLinePlugin = {
        id: 'todayLine',
        afterDraw(chart) {
            const idx = chart.data.labels.indexOf(todayStr);
            if (idx < 0) return;

            const xScale = chart.scales.x;
            const yScale = chart.scales.y;
            const x      = xScale.getPixelForValue(idx);
            const yTop   = yScale.top;
            const yBot   = yScale.bottom;
            const ctx    = chart.ctx;

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(x, yTop);
            ctx.lineTo(x, yBot);
            ctx.strokeStyle = 'rgba(100,116,139,0.35)';
            ctx.lineWidth   = 1.5;
            ctx.setLineDash([5, 4]);
            ctx.stroke();
            ctx.setLineDash([]);

            // "Today" label above the divider
            ctx.fillStyle  = '#94a3b8';
            ctx.font       = '10px "Plus Jakarta Sans", sans-serif';
            ctx.textAlign  = 'center';
            ctx.fillText('Today', x, yTop - 5);
            ctx.restore();
        }
    };

    // ── Chart config ─────────────────────────────────────────
    new Chart(canvasEl, {
        type: 'line',
        plugins: [todayLinePlugin],
        data: {
            labels: allLabels,
            datasets: [

                // Dataset 0 — Confidence upper bound (fill target)
                {
                    label: 'Upper CI',
                    data: fcMaxData,
                    fill: '+1',                          // fill DOWN to dataset 1
                    backgroundColor: 'rgba(59,130,246,0.09)',
                    borderColor: 'transparent',
                    borderWidth: 0,
                    pointRadius: 0,
                    tension: 0.4,
                    spanGaps: false,
                },

                // Dataset 1 — Confidence lower bound
                {
                    label: 'Lower CI',
                    data: fcMinData,
                    fill: false,
                    borderColor: 'rgba(59,130,246,0.20)',
                    borderWidth: 1,
                    borderDash: [3, 3],
                    pointRadius: 0,
                    tension: 0.4,
                    spanGaps: false,
                },

                // Dataset 2 — Historical (solid line)
                {
                    label: 'Historical',
                    data: histData,
                    fill: false,
                    borderColor: '#2563eb',
                    backgroundColor: '#2563eb',
                    borderWidth: 2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    tension: 0.35,
                    spanGaps: false,
                },

                // Dataset 3 — Forecast center line (dashed)
                {
                    label: 'Forecast',
                    data: fcData,
                    fill: false,
                    borderColor: '#2563eb',
                    backgroundColor: '#2563eb',
                    borderWidth: 2,
                    borderDash: [7, 4],
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#93c5fd',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 1.5,
                    tension: 0.35,
                    spanGaps: false,
                },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#94a3b8',
                    bodyColor: '#f1f5f9',
                    borderColor: '#1e293b',
                    borderWidth: 1,
                    padding: 12,
                    titleFont: {
                        size: 11, weight: '600',
                        family: '"Plus Jakarta Sans", sans-serif'
                    },
                    bodyFont: {
                        size: 12,
                        family: '"Plus Jakarta Sans", sans-serif'
                    },
                    callbacks: {
                        title(items) {
                            return fmtDate(items[0].label);
                        },
                        // Show only meaningful datasets, skip band lines
                        filter(item) {
                            return item.datasetIndex >= 2 && item.parsed.y !== null;
                        },
                        label(item) {
                            const prefix = isPrice ? '₱' : '';
                            const val    = item.parsed.y.toFixed(2);
                            return ` ${item.dataset.label}: ${prefix}${val} ${unit}`;
                        },
                        // Append CI range for forecast points
                        afterBody(items) {
                            if (!items.length) return;
                            const label  = items[0].label;
                            const fcIdx  = fcLabels.indexOf(label);
                            if (fcIdx < 0 || fcMins[fcIdx] === null) return;
                            const prefix = isPrice ? '₱' : '';
                            const lo     = Number(fcMins[fcIdx]).toFixed(2);
                            const hi     = Number(fcMaxs[fcIdx]).toFixed(2);
                            return [` 95% CI: ${prefix}${lo} – ${prefix}${hi} ${unit}`];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(226,232,240,0.6)',
                        drawBorder: false,
                    },
                    border: { display: false },
                    ticks: {
                        callback(value) {
                            return fmtDate(this.getLabelForValue(value));
                        },
                        color: '#94a3b8',
                        font: {
                            size: 11,
                            family: '"Plus Jakarta Sans", sans-serif'
                        },
                        maxTicksLimit: 13,
                        maxRotation: 0,
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(226,232,240,0.5)',
                        drawBorder: false,
                    },
                    border: { display: false },
                    ticks: {
                        callback(value) {
                            const prefix = isPrice ? '₱' : '';
                            if (value >= 1000) return prefix + (value / 1000).toFixed(1) + 'k';
                            return prefix + value.toFixed(isPrice ? 0 : 1);
                        },
                        color: '#94a3b8',
                        font: {
                            size: 11,
                            family: '"Plus Jakarta Sans", sans-serif'
                        },
                    }
                }
            }
        }
    });
})();
</script>
@endpush