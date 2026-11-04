<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>DAILY RECORDS OF FISH ENTRANCE</title>
<style>
@page { margin: 14mm 4mm 8mm; }
body { font-family: 'DejaVu Sans', sans-serif; color: black; font-size: 9px; }
header { position: relative; text-align: center; line-height: 16px; margin-bottom: 12px; }
img { position: absolute; right: 26mm; top: 0; width: 62px; height: 62px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th, td { border: 0.6pt solid black; text-align: center; padding: 2px; font-size: 8px; overflow-wrap: break-word; }
th { height: 26px; } td { height: 18px; }
.page { page-break-after: always; } .page:last-child { page-break-after: auto; }
.note { font-size: 7px; margin-top: 5px; }
</style></head><body>
@php
$pages = $entries->isEmpty() ? collect([collect()]) : $entries->chunk(36);
$logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('virac_logo.png')));
@endphp
@foreach($pages as $rows)
<div class="page">
<header>
<img src="{{ $logo }}" alt="Virac seal">
<div>Office of the Mayor</div>
<div>Market and Slaughterhouse Operations</div>
<div><strong>VIRAC PUBLIC MARKET</strong></div>
<div style="margin-top: 18px"><strong>DAILY RECORDS OF FISH ENTRANCE</strong></div>
<div>For the Month of {{ $report->period === 'yearly' ? 'January – December '.$report->start->format('Y') : $report->start->format('F Y') }}</div>
</header>
<table>
<colgroup><col style="width:8.5%"><col style="width:9%"><col style="width:23%"><col style="width:10%"><col style="width:8.5%"><col style="width:8.5%"><col style="width:8%"><col style="width:12.5%"><col style="width:12%"></colgroup>
<thead><tr><th>DATE</th><th>Time of<br>Arrival</th><th>Name of Supplier/ Vendor</th><th>Quantity</th><th>NET</th><th>GROSS</th><th>Fish<br>Class</th><th>Recorded by</th><th>Signature</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
<td>{{ $row->entry_date->format('m/d/Y') }}</td>
<td>{{ $row->created_at?->timezone(config('app.timezone'))->format('g:i A') ?? 'Not recorded' }}</td>
<td style="text-align:left">{{ $row->vendor?->name ?? 'Unknown vendor' }}</td>
<td>{{ number_format($row->stock_kg, 2) }} kg</td>
<td></td><td></td>
<td>{{ $row->quality_class }}</td><td></td><td></td>
</tr>
@endforeach
@for($i = $rows->count(); $i < 36; $i++)
<tr>@for($j = 0; $j < 9; $j++)<td>&nbsp;</td>@endfor</tr>
@endfor
</tbody></table>
<div class="note">Period: {{ $report->label() }}. Confirmed entries only. Quantity is initial stock in kg. Time of Arrival uses submission time. Actual arrival time is not separately recorded. NET/GROSS weights are reserved for officials.</div>
</div>
@endforeach
</body></html>
