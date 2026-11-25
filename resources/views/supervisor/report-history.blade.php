@extends('layouts.app')

@section('title', 'Market History')
@section('subtitle', 'Daily supply, sales and prices')

@section('content')
<div class="mb-5">
    <a href="{{ route('supervisor.reports.index') }}" class="text-blue-600 text-sm font-semibold">&larr; Back to reports</a>
</div>
<form method="GET" action="{{ route('supervisor.reports.history') }}" class="vpm-filter-bar mb-5">
    <div class="vpm-filter-field">
        <label for="history-from" class="vpm-filter-label">From</label>
        <input id="history-from" type="date" name="from" value="{{ $from }}" class="vpm-input" required>
        @error('from') <p class="text-rose-600 text-sm">{{ $message }}</p> @enderror
    </div>
    <div class="vpm-filter-field">
        <label for="history-to" class="vpm-filter-label">To</label>
        <input id="history-to" type="date" name="to" value="{{ $to }}" class="vpm-input" required>
        @error('to') <p class="text-rose-600 text-sm">{{ $message }}</p> @enderror
    </div>
    <button type="submit" class="vpm-btn vpm-btn-primary">Filter</button>
    <a href="{{ route('supervisor.reports.history') }}" class="vpm-btn vpm-btn-secondary">Reset</a>
</form>
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
    <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="text-slate-700 font-bold text-sm">Market History</h2>
        <p class="text-slate-500 text-xs mt-1">Confirmed batches grouped by date, fish and quality class. {{ number_format($rows->total()) }} records.</p>
    </div>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Daily market history">
        <table class="vpm-table">
            <thead><tr>
                <th scope="col">Date</th><th scope="col">Fish</th><th scope="col">English name</th><th scope="col">Quality class</th>
                <th scope="col">Batches</th><th scope="col">Vendors</th><th scope="col">Supply (kg)</th><th scope="col">Sold (kg)</th><th scope="col">Pull-outs (kg)</th>
                <th scope="col">Average price (PHP/kg)</th><th scope="col">Min price (PHP/kg)</th><th scope="col">Max price (PHP/kg)</th>
                <th scope="col">Tariff (PHP/kg)</th><th scope="col">Average markup (PHP/kg)</th><th scope="col">Sales value (PHP)</th>
            </tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="whitespace-nowrap">{{ $row->day }}</td>
                        <td class="whitespace-nowrap font-semibold">{{ \App\Models\FishType::displayName($row->fish) }}</td>
                        <td>{{ $row->english ?: '?' }}</td><td class="whitespace-nowrap">{{ $row->quality_class }}</td>
                        <td class="vpm-num">{{ $row->batches }}</td><td class="vpm-num">{{ $row->vendors }}</td>
                        @foreach(['supply_kg', 'sold_kg', 'pulled_out_kg', 'avg_price', 'min_price', 'max_price', 'tariff'] as $field)
                            <td class="vpm-num">{{ $row->$field === null ? '?' : number_format((float) $row->$field, 2) }}</td>
                        @endforeach
                        <td class="vpm-num">{{ $row->tariff === null ? '?' : number_format((float) $row->avg_price - (float) $row->tariff, 2) }}</td>
                        <td class="vpm-num">{{ number_format((float) $row->sales_value, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="text-center text-slate-500 py-8">No confirmed market history for this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-slate-100">{{ $rows->links() }}</div>
</div>
@endsection
