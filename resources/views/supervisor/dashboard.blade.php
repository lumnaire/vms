@extends('layouts.app')

@section('title', 'Supervisor Dashboard')
@section('subtitle', 'Market Overview · Virac Public Market')

@section('content')

    {{-- ── Greeting + Account Shortcut ───────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-slate-800 font-bold" style="font-size: 16px;">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
                {{ auth()->user()->name }}
            </p>
            <p class="text-slate-400 text-[11.5px] mt-px">
                Signed in as <span class="font-mono bg-surface-muted text-slate-600"
                    style="padding:1px 6px; border-radius:4px">{{ '@' . auth()->user()->username }}</span>
            </p>
        </div>
        <a href="{{ route('supervisor.account.edit') }}" class="vpm-btn vpm-btn-secondary flex-shrink-0">
            <i class="bi bi-person-gear"></i> My Account
        </a>
    </div>

    {{-- ── This month ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        <div class="vpm-card vpm-card-body">
            <p class="vpm-stat-label">Supply this month</p>
            <p class="vpm-stat-value vpm-num">{{ number_format($kpis['supply']['current'], 1) }} <span class="vpm-stat-value-unit">kg</span></p>
            <x-delta :change="$kpis['supply']['change']" class="mt-1.5" />
        </div>
        <div class="vpm-card vpm-card-body">
            <p class="vpm-stat-label">Sales this month</p>
            <p class="vpm-stat-value vpm-num">₱{{ number_format($kpis['sales']['current'], 2) }}</p>
            <x-delta :change="$kpis['sales']['change']" class="mt-1.5" />
        </div>
        <div class="vpm-card vpm-card-body">
            <p class="vpm-stat-label">Tubo this month</p>
            <p class="vpm-stat-value vpm-num">₱{{ number_format($kpis['tubo']['current'], 2) }}</p>
            <p class="vpm-stat-foot">Vendors' markup over the tariff on {{ number_format($kpis['sold']['current'], 1) }} kg sold</p>
        </div>
        <div class="vpm-card vpm-card-body">
            <p class="vpm-stat-label">Today's Stock</p>
            <p class="vpm-stat-value vpm-num">{{ number_format($totalStockKg ?? 0, 1) }} <span class="vpm-stat-value-unit">kg</span></p>
            <p class="vpm-stat-foot">Confirmed supply today</p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-5">
        <x-stat-card tinted tone="brand" label="Total Vendors" :value="$totalVendors" decimals="0" />
        <x-stat-card tinted tone="success" label="Total Stalls" :value="$totalStalls" decimals="0" />
        <x-stat-card tinted tone="neutral" label="Active Staff" :value="$activeStaff" decimals="0" />
    </div>

    {{-- ── Period toggle ─────────────────────────────────────────── --}}
    <div class="vpm-card px-4 py-3 mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="vpm-section-title">Market Trends</h2>
            <p class="vpm-section-sub" id="periodCaption">&nbsp;</p>
        </div>
        <div class="vpm-segment" id="supPeriods" role="group" aria-label="Period">
            @foreach(App\Services\MarketAnalytics::PERIODS as $key => [$label])
                <button type="button" data-period="{{ $key }}" aria-pressed="false">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
        <x-card title="Supply vs Sold (kg)" subtitle="Confirmed supply against kilograms recorded as sold">
            <div class="relative h-[280px]"><canvas id="supplySoldChart" aria-label="Supply versus sold, kilograms"></canvas></div>
        </x-card>
        <x-card title="Sales (₱)" subtitle="Sold kilograms × the batch price">
            <div class="relative h-[280px]"><canvas id="salesChart" aria-label="Sales in pesos"></canvas></div>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <x-card title="Top 5 Vendors (kg)" subtitle="Most confirmed supply in the period">
            <div class="relative h-[240px]"><canvas id="topVendorsChart" aria-label="Top five vendors by supply"></canvas></div>
        </x-card>
        <x-card title="Supply by Quality Class" subtitle="Share of confirmed kilograms">
            <div class="relative h-[240px]"><canvas id="classChart" aria-label="Supply by quality class"></canvas></div>
        </x-card>
        <x-card title="Where the Stock Went" subtitle="Batches submitted in the period">
            <div class="relative h-[240px]"><canvas id="outcomeChart" aria-label="Sold, pulled out and expired stock"></canvas></div>
            <p class="vpm-meta mt-2 text-center" id="outcomeFoot"></p>
        </x-card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
        {{-- Who makes the most tubo --}}
        <x-card class="xl:col-span-2" :padded="false" title="Tubo Leaderboard · This Month"
                subtitle="Vendors ranked by markup over the tariff on what they sold">
            @if($tuboBoard->isEmpty())
                <x-empty-state icon="bi-tags" title="No markups recorded yet"
                               text="This fills in once tariffs are set and batches are priced against them." />
            @else
                <div class="overflow-x-auto">
                    <table class="vpm-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vendor</th>
                                <th class="vpm-th-right">Tubo earned</th>
                                <th class="vpm-th-right">Avg markup</th>
                                <th class="vpm-th-right">Largest</th>
                                <th class="vpm-th-right">Above tariff</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tuboBoard as $i => $v)
                                <tr class="{{ $i === 0 && $v['tubo'] > 0 ? 'is-highlighted' : '' }}">
                                    <td class="vpm-cell-muted">{{ $i + 1 }}</td>
                                    <td>
                                        <p class="vpm-cell-strong">{{ $v['name'] }}</p>
                                        <p class="vpm-cell-muted">Stall {{ $v['stall'] ?? '—' }}</p>
                                    </td>
                                    <td class="vpm-td-right vpm-num vpm-cell-strong">₱{{ number_format($v['tubo'], 2) }}</td>
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

        {{-- Largest markups on the board right now --}}
        <x-card :padded="false" title="Markup Watch · Board Now" subtitle="Highest markup per fish over the tariff">
            @if($markupWatch->isEmpty())
                <x-empty-state icon="bi-check2-circle" title="No stall is above the tariff" text="Or no tariffs are set yet." />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($markupWatch as $f)
                        <li class="px-5 py-3 flex items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-slate-700 font-semibold text-[12.5px] truncate">{{ $f['fish_name'] }}</p>
                                <p class="vpm-meta">Stall {{ $f['top_markup']['stall'] }} · ₱{{ number_format($f['top_markup']['price'], 2) }} vs tariff ₱{{ number_format($f['tariff'], 2) }}</p>
                            </div>
                            <x-tariff-diff :comparison="$f['top_markup']['comparison']" />
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-slot:footer>
                <a href="{{ route('supervisor.price-guides.index') }}" class="text-brand-600 font-semibold text-[12px] hover:underline">Open the tariff table <i class="bi bi-arrow-right"></i></a>
            </x-slot:footer>
        </x-card>
    </div>

    {{-- ── All-time records ──────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @php
            $hp = $records['highest_price'];
            $hm = $records['highest_markup'];
        @endphp
        <div class="vpm-card vpm-card-body">
            <div class="flex items-center justify-between gap-2"><p class="vpm-stat-label">Highest supply in a day</p><x-badge variant="warning">All-time high</x-badge></div>
            @if($records['supply_day'])
                <p class="vpm-stat-value vpm-num">{{ number_format($records['supply_day']['value'], 1) }} <span class="vpm-stat-value-unit">kg</span></p>
                <p class="vpm-stat-foot">{{ $records['supply_day']['date']->format('M j, Y') }}</p>
            @else
                <p class="vpm-stat-value text-slate-300">—</p>
            @endif
        </div>
        <div class="vpm-card vpm-card-body">
            <div class="flex items-center justify-between gap-2"><p class="vpm-stat-label">Best sales day</p><x-badge variant="warning">All-time high</x-badge></div>
            @if($records['sales_day'])
                <p class="vpm-stat-value vpm-num">₱{{ number_format($records['sales_day']['value'], 2) }}</p>
                <p class="vpm-stat-foot">{{ $records['sales_day']['date']->format('M j, Y') }}</p>
            @else
                <p class="vpm-stat-value text-slate-300">—</p>
            @endif
        </div>
        <div class="vpm-card vpm-card-body">
            <div class="flex items-center justify-between gap-2"><p class="vpm-stat-label">Highest fish price</p><x-badge variant="warning">All-time high</x-badge></div>
            @if($hp)
                <p class="vpm-stat-value vpm-num">₱{{ number_format((float) $hp->price_per_kg, 2) }} <span class="vpm-stat-value-unit">/kg</span></p>
                <p class="vpm-stat-foot">{{ $hp->fishType?->name }} · Stall {{ $hp->vendor?->vendorProfile?->stall_number ?? '—' }} · {{ $hp->entry_date->format('M j, Y') }}</p>
            @else
                <p class="vpm-stat-value text-slate-300">—</p>
            @endif
        </div>
        <div class="vpm-card vpm-card-body">
            <div class="flex items-center justify-between gap-2"><p class="vpm-stat-label">Largest markup</p><x-badge variant="warning">All-time high</x-badge></div>
            @if($hm)
                <p class="vpm-stat-value vpm-num">+₱{{ number_format($hm->markupPerKg(), 2) }} <span class="vpm-stat-value-unit">/kg</span></p>
                <p class="vpm-stat-foot">{{ $hm->fishType?->name }} · Stall {{ $hm->vendor?->vendorProfile?->stall_number ?? '—' }} · {{ $hm->entry_date->format('M j, Y') }}</p>
            @else
                <p class="vpm-stat-value text-slate-300">—</p>
                <p class="vpm-stat-foot">Appears once tariffs are set</p>
            @endif
        </div>
    </div>

    {{-- The activity log lives on My Account (see supervisor/account.blade.php). --}}

@endsection

@push('scripts')
@include('partials.chart-kit')
<script>
(function () {
    const DATA = @json($charts);
    const CLASS_COLORS = @json(App\Services\MarketAnalytics::CLASS_COLORS);
    const { C, peso, kg } = VPM;

    const supplySold = new Chart(document.getElementById('supplySoldChart'), {
        type: 'line',
        data: { labels: [], datasets: [
            VPM.line('Supply', [], C.blue, { fill: true }),
            VPM.line('Sold', [], C.green, { borderDash: [6, 4] }),
        ] },
        options: VPM.axes({ fmt: kg }),
    });

    const sales = new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: { labels: [], datasets: [VPM.bar('Sales', [], C.blue)] },
        options: VPM.axes({ prefix: '₱', fmt: v => peso(v), legend: false }),
    });

    const top = new Chart(document.getElementById('topVendorsChart'), {
        type: 'bar',
        data: { labels: [], datasets: [VPM.bar('Supply', [], C.blue, { borderSkipped: 'start' })] },
        options: VPM.axes({ fmt: kg, horizontal: true, legend: false }),
    });

    const classes = VPM.doughnut(document.getElementById('classChart'), [], [], Object.values(CLASS_COLORS), kg);
    const outcome = VPM.doughnut(document.getElementById('outcomeChart'), ['Sold', 'Pulled out', 'Expired'], [], [C.green, C.amber, C.red], kg);

    VPM.periods('supPeriods', p => {
        const d = DATA[p];
        document.getElementById('periodCaption').textContent = d.caption;
        VPM.update(supplySold, d.labels, [d.series.supply, d.series.sold]);
        VPM.update(sales, d.labels, [d.series.sales]);
        VPM.update(top, d.top_vendors.map(v => v.name + (v.stall ? ' (' + v.stall + ')' : '')), [d.top_vendors.map(v => v.kg)]);

        const classNames = Object.keys(d.by_class).filter(c => d.by_class[c] > 0);
        classes.data.labels = classNames;
        classes.data.datasets[0].data = classNames.map(c => d.by_class[c]);
        classes.data.datasets[0].backgroundColor = classNames.map(c => CLASS_COLORS[c]);
        classes.update();

        const o = d.outcome;
        VPM.update(outcome, ['Sold', 'Pulled out', 'Expired'], [[o.sold, o.pulled_out, o.expired]]);
        document.getElementById('outcomeFoot').textContent =
            (o.sell_through !== null ? 'Sell-through ' + o.sell_through + '% · ' : '') + kg(o.on_stall) + ' still on the stalls';
    });
})();
</script>
@endpush
