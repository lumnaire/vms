<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $report->title() }} — {{ $report->label() }}</title>
<style>
    /* dompdf: plain tables and inline-friendly CSS only. DejaVu Sans carries the ₱ sign. */
    @page { margin: 28px 32px 40px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1e293b; }
    .header { border-bottom: 2px solid #0f2d5e; padding-bottom: 8px; margin-bottom: 14px; }
    .org { font-size: 9.5px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
    h1 { font-size: 18px; color: #0f2d5e; margin: 4px 0 2px; }
    .period { font-size: 12px; color: #334155; }
    .meta { font-size: 9px; color: #64748b; margin-top: 4px; }
    table { width: 100%; border-collapse: collapse; }
    .stats td { width: 25%; border: 1px solid #e2e8f0; padding: 8px 10px; }
    .stat-label { font-size: 8.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-value { font-size: 15px; font-weight: bold; color: #0f172a; margin-top: 2px; }
    h2 { font-size: 12.5px; color: #0f2d5e; margin: 18px 0 6px; }
    .data th { background: #0f2d5e; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; padding: 6px 8px; text-align: left; }
    .data td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
    .data tr:nth-child(even) td { background: #f8fafc; }
    .data tfoot td { font-weight: bold; border-top: 2px solid #0f2d5e; background: #eff6ff; }
    .r { text-align: right; }
    .muted { color: #64748b; font-size: 9px; }
    .empty { padding: 18px; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; }
    .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>

<div class="footer">
    Virac Public Market — Commodity Supply Projection &amp; Price Monitoring System · Catanduanes State University
</div>

<div class="header">
    <div class="org">Virac Public Market · Fish Section</div>
    <h1>{{ $report->title() }}</h1>
    <div class="period">{{ $report->label() }}</div>
    <div class="meta">
        Confirmed batches only · Generated {{ $generatedAt->format('M j, Y g:i A') }}
        by {{ $generatedBy?->name ?? 'Staff' }}
    </div>
</div>

<table class="stats">
    <tr>
        <td><div class="stat-label">Total Supply</div><div class="stat-value">{{ number_format($totals['supply_kg'], 2) }} kg</div></td>
        <td><div class="stat-label">Batches</div><div class="stat-value">{{ $totals['batches'] }}</div></td>
        <td><div class="stat-label">Vendors</div><div class="stat-value">{{ $totals['vendors'] }}</div></td>
        <td><div class="stat-label">Avg Price / kg</div><div class="stat-value">₱{{ number_format($totals['avg_price'], 2) }}</div></td>
    </tr>
</table>

<h2>Supply by Fish</h2>
@if($byFish->isEmpty())
    <div class="empty">No batches were confirmed in this period.</div>
@else
<table class="data">
    <thead>
        <tr>
            <th>Fish</th>
            <th>Quality Class</th>
            <th class="r">Batches</th>
            <th class="r">Vendors</th>
            <th class="r">Supply (kg)</th>
            <th class="r">Price Range / kg</th>
            <th class="r">Avg / kg</th>
        </tr>
    </thead>
    <tbody>
        @foreach($byFish as $row)
        <tr>
            <td><strong>{{ $row['fish'] }}</strong></td>
            <td>{{ $row['quality_class'] }}</td>
            <td class="r">{{ $row['batches'] }}</td>
            <td class="r">{{ $row['vendors'] }}</td>
            <td class="r">{{ number_format($row['supply_kg'], 2) }}</td>
            <td class="r">
                ₱{{ number_format($row['min_price'], 2) }}@if($row['min_price'] !== $row['max_price']) – ₱{{ number_format($row['max_price'], 2) }}@endif
            </td>
            <td class="r">₱{{ number_format($row['avg_price'], 2) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">Total</td>
            <td class="r">{{ $totals['batches'] }}</td>
            <td class="r">{{ $totals['vendors'] }}</td>
            <td class="r">{{ number_format($totals['supply_kg'], 2) }}</td>
            <td></td>
            <td class="r">₱{{ number_format($totals['avg_price'], 2) }}</td>
        </tr>
    </tfoot>
</table>
@endif

<h2>Supply by {{ $report->breakdownLabel() }}</h2>
@if($breakdown->isEmpty())
    <div class="empty">No confirmed batches in this period.</div>
@else
<table class="data">
    <thead>
        <tr>
            <th>{{ $report->breakdownLabel() }}</th>
            <th class="r">Batches</th>
            <th class="r">Vendors</th>
            <th class="r">Fish Types</th>
            <th class="r">Supply (kg)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($breakdown as $row)
        <tr>
            <td>
                <strong>{{ $row['label'] }}</strong>
                @if($row['sub'])<span class="muted"> · {{ $row['sub'] }}</span>@endif
            </td>
            <td class="r">{{ $row['batches'] }}</td>
            <td class="r">{{ $row['vendors'] }}</td>
            <td class="r">{{ $row['fish_types'] }}</td>
            <td class="r">{{ number_format($row['supply_kg'], 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

</body>
</html>
