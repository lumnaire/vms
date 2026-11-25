<form method="GET" action="{{ route('supervisor.reports.index') }}" id="entranceReportForm"
      class="bg-white rounded-xl border border-slate-100 p-4 mb-5 flex flex-wrap items-end gap-3">
    <x-filter-field label="Report">
        <select name="period" class="vpm-input" id="entrancePeriod">
            @foreach(\App\Services\SupplyReport::PERIODS as $period)
                <option value="{{ $period }}" @selected($entranceReport->range->period === $period)>{{ ucfirst($period) }}</option>
            @endforeach
        </select>
    </x-filter-field>
    <x-filter-field label="Date" data-entrance-period="daily" class="{{ $entranceReport->range->period === 'daily' ? '' : 'hidden' }}">
        <input type="date" name="date" class="vpm-input" value="{{ $reportDate }}">
    </x-filter-field>
    <x-filter-field label="Month" data-entrance-period="monthly" class="{{ $entranceReport->range->period === 'monthly' ? '' : 'hidden' }}">
        <input type="month" name="month" class="vpm-input" value="{{ $entranceReport->range->start->format('Y-m') }}">
    </x-filter-field>
    <x-filter-field label="Year" data-entrance-period="yearly" class="{{ $entranceReport->range->period === 'yearly' ? '' : 'hidden' }}">
        <input type="number" name="year" min="1900" max="2100" step="1" class="vpm-input" value="{{ $entranceReport->range->start->year }}" style="width:110px">
    </x-filter-field>
    <x-filter-field label="Vendor">
        <select name="vendor_id" class="vpm-input" style="max-width:250px">
            <option value="">All vendors</option>
            @foreach($reportVendors as $vendor)
                <option value="{{ $vendor->id }}" @selected($entranceReport->vendorId === $vendor->id)>{{ $vendor->name }}</option>
            @endforeach
        </select>
    </x-filter-field>
    <button type="submit" class="btn-filter">Filter</button>
    <a class="btn-pdf" href="{{ route('supervisor.reports.pdf', $entranceReport->parameters()) }}">Generate PDF</a>
    <a class="btn-filter" href="{{ route('supervisor.reports.history') }}">History</a>
</form>

<div class="report-card" style="margin-bottom:1.5rem; overflow:hidden">
    <div class="section-heading">{{ ucfirst($entranceReport->range->period) }} Fish Entrance Report &middot; {{ $entranceReport->range->label() }}</div>
    <div style="padding:.75rem 1.25rem; font-size:.8125rem; color:#4b5563">
        {{ number_format($reportTotals['supply_kg'], 2) }} kg &middot; {{ $reportTotals['vendors'] }} vendors
        @if($entranceReport->range->period === 'yearly')
            <span style="display:block; margin-top:.25rem">Total Price is the value of fish entered: kilograms &times; price per kilogram.</span>
        @endif
    </div>
    <iframe title="{{ ucfirst($entranceReport->range->period) }} fish entrance report preview"
            src="{{ route('supervisor.reports.pdf', $entranceReport->parameters() + ['preview' => 1]) }}"
            style="width:100%; height:760px; border:0; display:block; background:#e5e7eb">
    </iframe>
    <p style="padding:.75rem 1.25rem; font-size:.8125rem">
        <a href="{{ route('supervisor.reports.pdf', $entranceReport->parameters() + ['preview' => 1]) }}" target="_blank" rel="noopener">Open report preview</a>
    </p>
</div>

@push('scripts')
<script>
document.getElementById('entrancePeriod').addEventListener('change', function () {
    document.querySelectorAll('[data-entrance-period]').forEach(field => {
        field.classList.toggle('hidden', field.dataset.entrancePeriod !== this.value);
    });
});
</script>
@endpush
