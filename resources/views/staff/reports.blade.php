@extends('layouts.app')

@section('title', 'Supply Reports')
@section('subtitle', 'Confirmed Fish Supply · Daily, Monthly & Yearly')

@section('content')

{{-- ── Period picker: the preview reloads as soon as a filter changes ── --}}
<form method="GET" action="{{ route('staff.reports.index') }}" id="reportForm"
      class="bg-white rounded-xl border border-slate-100 shadow-card p-4 mb-5 flex flex-wrap items-end gap-3">

    <x-filter-field label="Report">
        <div class="vpm-segment" role="radiogroup" aria-label="Report period">
            @foreach(\App\Services\SupplyReport::PERIODS as $p)
                <button type="button" data-period="{{ $p }}" aria-pressed="{{ $report->period === $p ? 'true' : 'false' }}">
                    {{ ucfirst($p) }}
                </button>
            @endforeach
        </div>
        <input type="hidden" name="period" id="periodInput" value="{{ $report->period }}">
    </x-filter-field>

    <x-filter-field label="Date" data-for="daily" class="{{ $report->period === 'daily' ? '' : 'hidden' }}">
        <input type="date" name="date" class="vpm-input" max="{{ today()->toDateString() }}"
               value="{{ $report->period === 'daily' ? $report->value() : today()->toDateString() }}">
    </x-filter-field>

    <x-filter-field label="Month" data-for="monthly" class="{{ $report->period === 'monthly' ? '' : 'hidden' }}">
        <input type="month" name="month" class="vpm-input" max="{{ today()->format('Y-m') }}"
               value="{{ $report->period === 'monthly' ? $report->value() : today()->format('Y-m') }}">
    </x-filter-field>

    <x-filter-field label="Year" data-for="yearly" class="{{ $report->period === 'yearly' ? '' : 'hidden' }}">
        <input type="number" name="year" class="vpm-input" min="2020" max="{{ today()->year }}" step="1" style="width: 110px"
               value="{{ $report->period === 'yearly' ? $report->value() : today()->year }}">
    </x-filter-field>

    <div class="flex items-center gap-2 ml-auto">
        <button type="submit" formaction="{{ route('staff.reports.pdf') }}" class="vpm-btn vpm-btn-primary">
            <x-icon name="bi-file-earmark-pdf-fill" size="sm" /> Download PDF
        </button>
    </div>
</form>

{{-- ── Report preview ──────────────────────────────────────────── --}}
<div class="flex items-end justify-between gap-3 flex-wrap mb-4">
    <div>
        <h1 class="text-slate-800 font-bold text-[18px]">{{ $report->title() }}</h1>
        <p class="text-slate-500 text-[12.5px]">{{ $report->label() }} &middot; confirmed batches only</p>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
    <x-stat-card tinted tone="brand"   label="Total Supply"     :value="$totals['supply_kg']" unit="kg" />
    <x-stat-card tinted tone="success" label="Batches"          :value="$totals['batches']" decimals="0" />
    <x-stat-card tinted tone="warning" label="Vendors"          :value="$totals['vendors']" decimals="0" />
    <x-stat-card tinted tone="neutral" label="Avg Price / kg"   :value="$totals['avg_price']" prefix="₱" />
    <x-stat-card tinted tone="danger"  label="Pulled Out"       :value="$totals['pulled_out_kg']" unit="kg" />
</div>

<div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

    {{-- Supply by fish --}}
    <div class="xl:col-span-3 bg-white rounded-xl border border-slate-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-slate-700 font-bold text-[13.5px]">Supply by Fish</h2>
            <p class="text-slate-400 text-[11px] mt-px">{{ $totals['fish_types'] }} fish {{ Str::plural('type', $totals['fish_types']) }}</p>
        </div>
        @if($byFish->isEmpty())
            <x-empty-state icon="bi-fish" title="No confirmed supply" text="No batches were confirmed in this period." />
        @else
        <div class="overflow-x-auto">
            <table class="vpm-table">
                <thead>
                    <tr>
                        <th>Fish</th>
                        <th>Class</th>
                        <th class="vpm-th-right">Batches</th>
                        <th class="vpm-th-right">Vendors</th>
                        <th class="vpm-th-right">Supply</th>
                        <th class="vpm-th-right">Price / kg</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byFish as $row)
                    <tr>
                        <td class="vpm-cell-strong">{{ $row['fish'] }}</td>
                        <td><x-quality-badge :quality="$row['quality_class']" /></td>
                        <td class="vpm-td-right">{{ $row['batches'] }}</td>
                        <td class="vpm-td-right">{{ $row['vendors'] }}</td>
                        <td class="vpm-td-right font-semibold">{{ number_format($row['supply_kg'], 2) }} kg</td>
                        <td class="vpm-td-right vpm-cell-muted whitespace-nowrap">
                            @if($row['min_price'] === $row['max_price'])
                                ₱{{ number_format($row['min_price'], 2) }}
                            @else
                                ₱{{ number_format($row['min_price'], 2) }} – ₱{{ number_format($row['max_price'], 2) }}
                            @endif
                            <span class="block text-[11px]">avg ₱{{ number_format($row['avg_price'], 2) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Breakdown by vendor / day / month --}}
    <div class="xl:col-span-2 bg-white rounded-xl border border-slate-100 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-slate-700 font-bold text-[13.5px]">Supply by {{ $report->breakdownLabel() }}</h2>
        </div>
        @if($breakdown->isEmpty())
            <x-empty-state icon="bi-calendar-x" title="Nothing to break down" text="No confirmed batches in this period." />
        @else
        <div class="overflow-x-auto">
            <table class="vpm-table">
                <thead>
                    <tr>
                        <th>{{ $report->breakdownLabel() }}</th>
                        <th class="vpm-th-right">Batches</th>
                        <th class="vpm-th-right">Supply</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($breakdown as $row)
                    <tr>
                        <td>
                            <span class="vpm-cell-strong">{{ $row['label'] }}</span>
                            @if($row['sub'])<span class="block text-[11px] text-slate-400">{{ $row['sub'] }}</span>@endif
                        </td>
                        <td class="vpm-td-right">{{ $row['batches'] }}</td>
                        <td class="vpm-td-right font-semibold">{{ number_format($row['supply_kg'], 2) }} kg</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Every release of stock pulled out unsold in this period --}}
<div class="mt-5 bg-white rounded-xl border border-slate-100 shadow-card overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="text-slate-700 font-bold text-[13.5px]">Released Stock (Pulled Out, Not Sold)</h2>
        <p class="text-slate-400 text-[11px] mt-px">
            {{ $totals['pull_outs'] }} {{ Str::plural('release', $totals['pull_outs']) }}
            &middot; {{ number_format($totals['pulled_out_kg'], 2) }} kg
        </p>
    </div>
    @if($pullOuts->isEmpty())
        <x-empty-state icon="bi-box-arrow-right" title="No pull-outs" text="No vendor pulled out unsold stock in this period." />
    @else
    <div class="overflow-x-auto">
        <table class="vpm-table">
            <thead>
                <tr>
                    <th>{{ $report->period === 'daily' ? 'Time' : 'Date' }}</th>
                    <th>Vendor</th>
                    <th>Fish</th>
                    <th>Batch</th>
                    <th class="vpm-th-right">Released</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pullOuts as $row)
                <tr>
                    <td class="whitespace-nowrap">{{ $row['when'] }}</td>
                    <td>
                        <span class="vpm-cell-strong">{{ $row['vendor'] }}</span>
                        @if($row['stall'])<span class="block text-[11px] text-slate-400">Stall {{ $row['stall'] }}</span>@endif
                    </td>
                    <td>
                        {{ $row['fish'] }}
                        @if($row['quality_class'])<span class="block text-[11px] text-slate-400">{{ $row['quality_class'] }}</span>@endif
                    </td>
                    <td class="vpm-cell-muted">{{ $row['batch'] }}</td>
                    <td class="vpm-td-right font-semibold">{{ number_format($row['kg'], 2) }} kg</td>
                    <td class="vpm-cell-muted">{{ $row['reason'] ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
    // Every filter change reloads the preview; there is no Preview button.
    // form.submit() goes to the form's own action (the preview), not the PDF.
    (function () {
        const form = document.getElementById('reportForm');
        const input = document.getElementById('periodInput');
        const preview = () => form.submit();

        // Period buttons switch which date input the form sends.
        form.querySelectorAll('[data-period]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (input.value === btn.dataset.period) return;
                input.value = btn.dataset.period;
                form.querySelectorAll('[data-period]').forEach(b => b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'));
                form.querySelectorAll('[data-for]').forEach(f => f.classList.toggle('hidden', f.dataset.for !== btn.dataset.period));
                preview();
            });
        });

        form.querySelectorAll('input[name="date"], input[name="month"]').forEach(el => {
            el.addEventListener('change', () => el.value && preview());
        });

        // Typing a year: wait for all four digits and a short pause.
        const year = form.querySelector('input[name="year"]');
        let timer;
        year.addEventListener('input', () => {
            clearTimeout(timer);
            if (/^\d{4}$/.test(year.value)) timer = setTimeout(preview, 500);
        });
    })();
</script>
@endpush
