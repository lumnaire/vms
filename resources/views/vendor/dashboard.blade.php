@extends('layouts.app')

@section('title', 'My Dashboard')
@section('subtitle')
    Personal Inventory Overview · {{ auth()->user()->vendorProfile->stall_number ?? 'No Stall Assigned' }}
@endsection

@section('content')

<x-alert />

{{-- ── Welcome Banner ──────────────────────────────────────── --}}
<div class="rounded-xl p-5 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 overflow-hidden relative"
     style="background: linear-gradient(135deg, #0f2d5e 0%, #1a4a8a 100%); box-shadow: 0 4px 14px rgba(15,45,94,0.3);">
    {{-- Decorative circles --}}
    <div class="absolute" style="right: -20px; top: -20px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.05)"></div>
    <div class="absolute" style="right: 60px; bottom: -30px; width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.04)"></div>

    <div class="relative">
        <p class="text-blue-300 text-[11.5px] font-semibold">Good {{ now()->setTimezone('Asia/Manila')->hour < 12 ? 'morning' : (now()->setTimezone('Asia/Manila')->hour < 17 ? 'afternoon' : 'evening') }},</p>
        <h2 class="text-white font-bold mt-0.5" style="font-size: 18px;">{{ auth()->user()->name }}</h2>
        <div class="flex items-center gap-2 mt-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-blue-200 text-[11px] font-semibold"
                  style="background: rgba(255,255,255,0.1)">
                <i class="bi bi-shop"></i>
                {{ auth()->user()->vendorProfile->stall_number ?? 'No Stall Assigned' }}
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold"
                  style="background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.7)">
                <i class="bi bi-calendar3"></i>
                {{ now()->setTimezone('Asia/Manila')->format('M j, Y') }}
            </span>
        </div>
    </div>

    <div class="relative flex-shrink-0">
        <a href="{{ route('vendor.inventory.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-blue-900 transition-all hover:bg-blue-50 text-[12.5px]"
           style="background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15)">
            <i class="bi bi-plus-circle"></i>
            Submit Inventory
        </a>
    </div>
</div>

{{-- ── Sales and stock ───────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
    <div class="vpm-card vpm-card-body">
        <p class="vpm-stat-label">Sales today</p>
        <p class="vpm-stat-value vpm-num">₱{{ number_format($salesToday, 2) }}</p>
        <x-delta :change="$salesChange" label="vs yesterday" class="mt-1.5" />
    </div>
    <div class="vpm-card vpm-card-body">
        <p class="vpm-stat-label">Sales this month</p>
        <p class="vpm-stat-value vpm-num">₱{{ number_format($salesMonth['current'], 2) }}</p>
        <x-delta :change="$salesMonth['change']" class="mt-1.5" />
    </div>
    <div class="vpm-card vpm-card-body">
        <p class="vpm-stat-label">My tubo this month</p>
        <p class="vpm-stat-value vpm-num">₱{{ number_format($tuboMonth['current'], 2) }}</p>
        <p class="vpm-stat-foot">Your markup over the tariff on what you sold</p>
    </div>
    <div class="vpm-card vpm-card-body">
        <p class="vpm-stat-label">Remaining Stock</p>
        <p class="vpm-stat-value vpm-num">{{ number_format($remainingStock ?? 0, 1) }} <span class="vpm-stat-value-unit">kg</span></p>
        <p class="vpm-stat-foot">Across every batch still on sale</p>
    </div>
</div>

<div class="grid grid-cols-3 gap-3 mb-5">
    <x-stat-card tinted tone="brand" label="Today's Entries" :value="$todayEntries ?? 0" decimals="0" />
    <x-stat-card tinted tone="success" label="Confirmed" :value="$confirmedEntries ?? 0" decimals="0" />
    <x-stat-card tinted tone="warning" label="Pending" :value="$pendingEntries ?? 0" decimals="0" />
</div>

{{-- ── Charts ────────────────────────────────────────────────── --}}
<div class="vpm-card px-4 py-3 mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="vpm-section-title">My Sales</h2>
        <p class="vpm-section-sub" id="vendorPeriodCaption">&nbsp;</p>
    </div>
    <div class="vpm-segment" id="vendorPeriods" role="group" aria-label="Period">
        @foreach(App\Services\MarketAnalytics::PERIODS as $key => [$label])
            <button type="button" data-period="{{ $key }}" aria-pressed="false">{{ $label }}</button>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
    <x-card class="xl:col-span-2" title="My Sales (₱)" subtitle="What you recorded as sold × your price">
        <div class="relative h-[270px]"><canvas id="vendorSalesChart" aria-label="My sales in pesos"></canvas></div>
    </x-card>
    <x-card title="Where My Stock Went" subtitle="Batches you submitted in the period">
        <div class="relative h-[220px]"><canvas id="vendorOutcomeChart" aria-label="Sold, pulled out and expired stock"></canvas></div>
        <p class="text-center mt-2">
            <span class="vpm-stat-label">Sell-through</span>
            <span class="text-slate-800 font-bold text-[18px] vpm-num block" id="sellThrough">—</span>
            <span class="vpm-meta" id="vendorOnStall"></span>
        </p>
    </x-card>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    <div class="vpm-card vpm-card-body">
        <div class="flex items-center justify-between gap-2"><p class="vpm-stat-label">My best day</p><x-badge variant="warning">All-time high</x-badge></div>
        @if($bestDay)
            <p class="vpm-stat-value vpm-num">₱{{ number_format($bestDay['value'], 2) }}</p>
            <p class="vpm-stat-foot">{{ $bestDay['date']->format('M j, Y') }}</p>
        @else
            <p class="vpm-stat-value text-slate-300">—</p>
            <p class="vpm-stat-foot">Use ⋮ → Release on a batch to record sales</p>
        @endif
        <div class="relative h-[120px] mt-3"><canvas id="vendorTuboChart" aria-label="My tubo in pesos"></canvas></div>
        <p class="vpm-meta text-center">Tubo (markup over tariff) per period</p>
    </div>

    {{-- My prices against the tariff and the other stalls --}}
    <x-card class="xl:col-span-2" :padded="false" title="My Prices vs Tariff"
            subtitle="Fish on your stall now, against the official tariff and other stalls">
        @if($myPrices->isEmpty())
            <x-empty-state icon="bi-tags" title="Nothing on your stall right now" text="Confirmed batches with fish left show here." />
        @else
            <div class="overflow-x-auto">
                <table class="vpm-table">
                    <thead>
                        <tr>
                            <th>Fish</th>
                            <th class="vpm-th-right">My price</th>
                            <th class="vpm-th-right">vs Tariff</th>
                            <th class="vpm-th-right">Other stalls</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($myPrices as $row)
                            <tr>
                                <td>
                                    <p class="vpm-cell-strong">{{ $row['fish'] }}</p>
                                    <p class="vpm-cell-muted">{{ $row['quality_class'] }}</p>
                                </td>
                                <td class="vpm-td-right vpm-num vpm-cell-strong">₱{{ number_format($row['price'], 2) }}</td>
                                <td class="vpm-td-right">
                                    <x-tariff-diff :comparison="$row['comparison']" show-tariff />
                                    @if($row['is_top_markup'])
                                        <x-badge variant="danger" class="mt-1">Highest markup on the board</x-badge>
                                    @endif
                                </td>
                                <td class="vpm-td-right vpm-num">
                                    @if($row['stalls'] > 1)
                                        ₱{{ number_format($row['market_min'], 2) }} – ₱{{ number_format($row['market_max'], 2) }}
                                        <span class="vpm-cell-muted block">{{ $row['stalls'] }} stalls selling</span>
                                    @else
                                        <span class="vpm-cell-muted">Only you</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>

{{-- ── Today's Inventory Table ──────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-slate-700 font-bold text-[13.5px]">Today's Batches</h2>
            <p class="text-slate-400 text-[11px] mt-px">
                {{ now()->setTimezone('Asia/Manila')->format('F j, Y') }}
            </p>
        </div>
        <a href="{{ route('vendor.inventory.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-blue-600 font-semibold transition-colors hover:bg-blue-50 text-[11.5px] border border-brand-200"
          >
            <i class="bi bi-plus"></i> Add Entry
        </a>
    </div>

    @if($todayInventory->isEmpty())
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center" style="height: 200px;">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mb-3 bg-surface-subtle"
                >
                <x-icon name="bi-inbox" size="2xl" class="text-slate-300" />
            </div>
            <p class="text-slate-500 font-semibold text-[13px]">No entries yet today</p>
            <p class="text-slate-400 text-center mt-1 text-[11.5px]">
                Click "Add Entry" to submit your fish inventory for today.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-100 text-left">
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Fish Type</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Quality</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Price / kg</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Stock (kg)</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Remaining</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Status</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Days Left</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($todayInventory as $item)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-3 text-slate-700 font-medium text-[12.5px]">
                            {{ $item->fishType->name ?? '—' }}
                            <span class="block text-[11px] text-slate-400 font-normal">{{ $item->batchLabel() }}</span>
                        </td>
                        <td class="px-5 py-3 text-slate-500 text-[12px]">
                            {{ $item->quality_class }}
                        </td>
                        <td class="px-5 py-3 text-slate-700 text-[12px]">
                            ₱{{ number_format($item->price_per_kg, 2) }}
                            @if($item->tariff_price !== null)
                                <x-tariff-diff :comparison="$item->tariffComparison()" align="start" class="mt-0.5 flex" />
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-700 text-[12px]">
                            {{ number_format($item->stock_kg, 1) }} kg
                        </td>
                        {{-- Stock minus what the vendor released as sold, the same figure the price board shows. --}}
                        <td class="px-5 py-3 text-[12px] font-semibold {{ $item->isConfirmed() ? 'text-slate-700' : 'text-slate-300' }}">
                            {{ $item->isConfirmed() ? number_format($item->getRemainingStock(), 1) . ' kg' : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            @if($item->status === 'confirmed')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-emerald-700 font-semibold text-[10.5px]"
                                      style="background: #dcfce7">
                                    <i class="bi bi-check-circle-fill"></i> Confirmed
                                </span>
                            @elseif($item->status === 'pending')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-amber-700 font-semibold text-[10.5px]"
                                      style="background: #fef9c3">
                                    <i class="bi bi-hourglass-split"></i> Pending
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-red-600 font-semibold text-[10.5px]"
                                      style="background: #fee2e2">
                                    <i class="bi bi-x-circle-fill"></i> Rejected
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-500 text-[11.5px] font-semibold whitespace-nowrap">{{ $item->isCountingDown() ? $item->countdownLabel() : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>


@endsection

@push('scripts')
@include('partials.chart-kit')
<script>
(function () {
    const DATA = @json($charts);
    const { C, peso, kg } = VPM;

    const sales = new Chart(document.getElementById('vendorSalesChart'), {
        type: 'line',
        data: { labels: [], datasets: [VPM.line('Sales', [], C.blue, { fill: true })] },
        options: VPM.axes({ prefix: '₱', fmt: v => peso(v), legend: false }),
    });

    const tubo = new Chart(document.getElementById('vendorTuboChart'), {
        type: 'bar',
        data: { labels: [], datasets: [VPM.bar('Tubo', [], C.purple)] },
        options: Object.assign(VPM.axes({ prefix: '₱', fmt: v => peso(v), legend: false }), {}),
    });
    tubo.options.scales.x.ticks.display = false;

    const outcome = VPM.doughnut(document.getElementById('vendorOutcomeChart'), ['Sold', 'Pulled out', 'Expired'], [], [C.green, C.amber, C.red], kg);

    VPM.periods('vendorPeriods', p => {
        const d = DATA[p];
        document.getElementById('vendorPeriodCaption').textContent = d.caption;
        VPM.update(sales, d.labels, [d.series.sales]);
        VPM.update(tubo, d.labels, [d.series.tubo]);
        const o = d.outcome;
        VPM.update(outcome, ['Sold', 'Pulled out', 'Expired'], [[o.sold, o.pulled_out, o.expired]]);
        document.getElementById('sellThrough').textContent = o.sell_through !== null ? o.sell_through + '%' : '—';
        document.getElementById('vendorOnStall').textContent = 'of the stock that has left your stall was sold · ' + kg(o.on_stall) + ' still on sale';
    });
})();
</script>
@endpush
