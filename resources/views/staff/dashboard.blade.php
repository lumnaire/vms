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

    {{-- Above the tariff today --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Above Tariff Today</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $aboveTariffToday }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Batches priced over the tariff</p>
            </div>
            <div class="vpm-stat-icon vpm-stat-icon-warning flex-shrink-0">
                <x-icon name="bi-graph-up-arrow" size="lg" />
            </div>
        </div>
        <a href="{{ route('staff.price-guides.index') }}" class="inline-flex items-center gap-1 mt-3 text-brand-600 hover:underline text-[11.5px] font-semibold">
            Compare with the tariff <i class="bi bi-arrow-right"></i>
        </a>
    </div>

</div>

{{-- ── This week + who has not submitted ─────────────────────── --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
    <x-card class="xl:col-span-2" title="Entries This Week" subtitle="Batches submitted per day, by review status">
        <div class="relative h-[260px]"><canvas id="weekChart" aria-label="Entries this week by status"></canvas></div>
    </x-card>

    <x-card :padded="false" title="Not Submitted Today"
            subtitle="{{ $notSubmitted->count() }} of {{ $totalVendors }} active {{ Str::plural('vendor', $totalVendors) }}">
        @if($notSubmitted->isEmpty())
            <x-empty-state icon="bi-check2-all" title="Every vendor has submitted" text="All active vendors logged a batch today." />
        @else
            <ul class="divide-y divide-slate-100 overflow-y-auto" style="max-height: 290px;">
                @foreach($notSubmitted as $vendor)
                    <li class="px-5 py-2.5 flex items-center justify-between gap-3">
                        <span class="text-slate-700 text-[12.5px] font-medium truncate">{{ $vendor->name }}</span>
                        <span class="vpm-badge vpm-badge-neutral flex-shrink-0">{{ $vendor->vendorProfile?->stall_number ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>

{{-- ── Supply trend + markup leaderboard ────────────────────── --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
    <x-card>
        <x-slot:header>
            <div class="min-w-0">
                <h2 class="vpm-section-title">Supply (kg)</h2>
                <p class="vpm-section-sub">
                    <span id="staffPeriodCaption">&nbsp;</span> ·
                    this month {{ number_format($supplyMonth['current'], 1) }} kg
                    @if($supplyMonth['change'] !== null)
                        ({{ $supplyMonth['change'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($supplyMonth['change']), 1) }}% vs same days last month)
                    @endif
                </p>
            </div>
            <div class="vpm-segment" id="staffPeriods" role="group" aria-label="Period">
                @foreach(App\Services\MarketAnalytics::PERIODS as $key => [$label])
                    <button type="button" data-period="{{ $key }}" aria-pressed="false">{{ $label }}</button>
                @endforeach
            </div>
        </x-slot:header>
        <div class="relative h-[260px]"><canvas id="staffSupplyChart" aria-label="Confirmed supply in kilograms"></canvas></div>
    </x-card>

    <x-card :padded="false" title="Highest Markups · This Month"
            subtitle="Vendors ranked by average markup (tubo) over the tariff, per kilo">
        @if($markupBoard->isEmpty())
            <x-empty-state icon="bi-tags" title="No markups recorded yet"
                           text="This fills in once the supervisor sets tariffs and vendors price against them." />
        @else
            <div class="overflow-x-auto">
                <table class="vpm-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th class="vpm-th-right">Avg markup</th>
                            <th class="vpm-th-right">Largest</th>
                            <th class="vpm-th-right">Above tariff</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($markupBoard as $i => $v)
                            <tr class="{{ $i === 0 && $v['avg_markup'] > 0 ? 'is-highlighted' : '' }}">
                                <td>
                                    <p class="vpm-cell-strong">{{ $v['name'] }}</p>
                                    <p class="vpm-cell-muted">Stall {{ $v['stall'] ?? '—' }}</p>
                                </td>
                                <td class="vpm-td-right vpm-num">
                                    {{ $v['avg_markup'] >= 0 ? '+' : '−' }}₱{{ number_format(abs($v['avg_markup']), 2) }}/kg
                                    <span class="vpm-cell-muted block">{{ $v['avg_pct'] >= 0 ? '+' : '−' }}{{ number_format(abs($v['avg_pct']), 1) }}%</span>
                                </td>
                                <td class="vpm-td-right vpm-num">{{ $v['max_markup'] >= 0 ? '+' : '−' }}₱{{ number_format(abs($v['max_markup']), 2) }}</td>
                                <td class="vpm-td-right vpm-num">{{ $v['above'] }} of {{ $v['batches'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>

{{-- ── Pending Entries + Repeat Submissions ───────────────────── --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

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
                                <span class="text-slate-400 font-normal">&bull; {{ $entry->quality_class }} &bull; {{ $entry->batchLabel() }}</span>
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

    {{-- Multiple Batches: the same vendor submitting the same fish and class
         more than once today, with the supply the batches add up to. --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-slate-700 font-bold text-[13.5px]">
                    Multiple Batches Today
                    @if($repeatSubmissions->isNotEmpty())
                        <span class="ml-1.5 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-white font-bold text-[10px] bg-info-600">{{ $repeatSubmissions->count() }}</span>
                    @endif
                </h2>
                <p class="text-slate-400 text-[11px] mt-px">Same vendor, same fish and class &mdash; every batch side by side</p>
            </div>
        </div>

        @if($repeatSubmissions->isNotEmpty())
            <div class="divide-y divide-slate-100 overflow-y-auto" style="max-height: 360px;">
                @foreach($repeatSubmissions as $group)
                    <div class="px-5 py-3.5">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0">
                                <p class="text-slate-700 font-semibold text-[12.5px] truncate">
                                    {{ $group['fish']?->name ?? '—' }}
                                    <span class="text-slate-400 font-normal">&bull; {{ $group['quality_class'] }}</span>
                                </p>
                                <p class="text-slate-400 text-[10.5px]">
                                    {{ $group['vendor']?->name ?? 'Unknown vendor' }}
                                    @if($group['vendor']?->vendorProfile)
                                        &bull; Stall {{ $group['vendor']->vendorProfile->stall_number }}
                                    @endif
                                    &bull; {{ $group['lines']->count() }} batches
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-slate-800 font-bold text-[13px]">{{ number_format($group['confirmed_kg'], 1) }} kg</p>
                                <p class="text-slate-400 text-[10px]">confirmed total</p>
                            </div>
                        </div>

                        <ul class="space-y-1">
                            @foreach($group['lines'] as $line)
                                <li class="flex items-center gap-2 text-[11.5px] {{ $line->isRejected() ? 'opacity-50 line-through' : '' }}">
                                    <span class="font-semibold text-slate-600 w-14">{{ $line->batchLabel() }}</span>
                                    <span class="font-semibold text-slate-700">{{ number_format((float) $line->stock_kg, 1) }} kg</span>
                                    <span class="text-slate-400">@ ₱{{ number_format((float) $line->price_per_kg, 2) }}</span>
                                    <span class="text-slate-300">&bull;</span>
                                    <span class="text-slate-400">{{ $line->created_at->format('g:i A') }}</span>
                                    <span class="ml-auto">
                                        @if($line->isConfirmed())
                                            <x-badge variant="success">Confirmed</x-badge>
                                        @elseif($line->isPending())
                                            <x-badge variant="warning">Pending</x-badge>
                                        @else
                                            <x-badge variant="danger">Rejected</x-badge>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-2 text-[10.5px] text-slate-500">
                            <span class="font-semibold text-slate-600">{{ number_format($group['confirmed_kg'], 1) }} kg confirmed supply</span>
                            @if($group['pending_count'] > 0)
                                &bull; <a href="{{ route('staff.confirmations.index') }}" class="text-warning-700 font-semibold hover:underline">{{ number_format($group['pending_kg'], 1) }} kg pending review</a>
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center" style="height: 360px;">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 bg-surface-subtle">
                    <x-icon name="bi-layers" size="lg" class="text-slate-300" />
                </div>
                <p class="text-slate-500 font-medium text-[12px]">No multiple batches</p>
                <p class="text-slate-300 mt-0.5 text-[11px]">Each vendor has logged each fish once today</p>
            </div>
        @endif
    </div>

</div>

@endsection

@push('scripts')
@include('partials.chart-kit')
<script>
(function () {
    const WEEK = @json($week);
    const SUPPLY = @json($supplyCharts);
    const { C, kg } = VPM;

    new Chart(document.getElementById('weekChart'), {
        type: 'bar',
        data: { labels: WEEK.labels, datasets: [
            VPM.bar('Confirmed', WEEK.confirmed, C.green),
            VPM.bar('Rejected', WEEK.rejected, C.red),
            VPM.bar('Pending', WEEK.pending, C.amber),
        ] },
        options: VPM.axes({ stacked: true, fmt: v => v + (v === 1 ? ' batch' : ' batches') }),
    });

    const supply = new Chart(document.getElementById('staffSupplyChart'), {
        type: 'line',
        data: { labels: [], datasets: [VPM.line('Supply', [], C.blue, { fill: true })] },
        options: VPM.axes({ fmt: kg, legend: false }),
    });

    VPM.periods('staffPeriods', p => {
        document.getElementById('staffPeriodCaption').textContent = SUPPLY[p].caption;
        VPM.update(supply, SUPPLY[p].labels, [SUPPLY[p].series.supply]);
    }, 'daily');
})();
</script>
@endpush