<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\PriceGuide;
use App\Models\Report;
use App\Models\User;
use App\Models\VendorInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reportDate = $request->input('date', today()->toDateString());
        $fishTypes = FishType::where('is_active', true)->orderBy('name')->get();

        // Per-vendor sales summary for the selected date
        $vendorSummaries = User::where('role', 'vendor')
            ->with([
                'vendorProfile',
                'vendorInventories' => fn ($q) => $q->with('fishType')
                    ->where('status', 'confirmed')
                    ->whereDate('entry_date', $reportDate),
            ])
            ->get()
            ->map(function ($vendor) {
                $inv = $vendor->vendorInventories;
                $priced = $inv->whereNotNull('tariff_price');

                return [
                    'vendor' => $vendor,
                    'total_stock_kg' => $inv->sum('stock_kg'),
                    'total_entries' => $inv->count(),
                    'avg_price' => $inv->avg('price_per_kg'),
                    // Average markup over the tariff, per kilo, on batches that had one.
                    'avg_markup' => $priced->isEmpty() ? null : round($priced->avg(fn ($e) => $e->markupPerKg()), 2),
                    'above_tariff' => $priced->filter(fn ($e) => $e->isAboveTariff())->count(),
                    'fish_list' => $inv->map(fn ($e) => $e->fishType?->name)->unique()->filter()->implode(', '),
                ];
            })
            ->filter(fn ($v) => $v['total_entries'] > 0)
            ->sortByDesc('total_stock_kg')
            ->values();

        // Market-wide aggregates
        $marketTotalKg = $vendorSummaries->sum('total_stock_kg');
        $activeVendors = $vendorSummaries->count();

        // Price summary per fish type + quality class, against the tariff
        $tariffs = PriceGuide::tariffMap();
        $priceBreakdown = VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->where('status', 'confirmed')
            ->whereDate('entry_date', $reportDate)
            ->get()
            ->groupBy(fn ($e) => $e->fishType?->name.'|||'.$e->quality_class)
            ->map(fn ($group) => [
                'fish_name' => $group->first()->fishType?->name,
                'quality_class' => $group->first()->quality_class,
                // The tariff kept on that day's batches, else the current one.
                'tariff' => $group->pluck('tariff_price')->filter()->map(fn ($t) => (float) $t)->last()
                    ?? ($tariffs[PriceGuide::key($group->first()->fish_type_id, $group->first()->quality_class)] ?? null),
                'avg_price' => round($group->avg('price_per_kg'), 2),
                'min_price' => $group->min('price_per_kg'),
                'max_price' => $group->max('price_per_kg'),
                'total_kg' => $group->sum('stock_kg'),
                'vendor_count' => $group->pluck('vendor_id')->unique()->count(),
            ])
            ->sortBy('fish_name')
            ->values();

        // Archive this snapshot to the reports table
        if ($vendorSummaries->isNotEmpty()) {
            Report::updateOrCreate(
                [
                    'generated_by' => Auth::id(),
                    'report_type' => 'vendor_performance',
                    'report_date' => $reportDate,
                ],
                [
                    'report_data' => [
                        'market_total_kg' => $marketTotalKg,
                        'active_vendors' => $activeVendors,
                        'vendor_summaries' => $vendorSummaries->map(fn ($v) => [
                            'vendor' => $v['vendor']->name,
                            'stall' => $v['vendor']->vendorProfile?->stall_number,
                            'total_stock_kg' => $v['total_stock_kg'],
                            'total_entries' => $v['total_entries'],
                            'avg_price' => $v['avg_price'],
                            'avg_markup' => $v['avg_markup'],
                            'above_tariff' => $v['above_tariff'],
                            'fish_list' => $v['fish_list'],
                        ])->toArray(),
                        'price_breakdown' => $priceBreakdown->toArray(),
                    ],
                ]
            );
        }

        return view('supervisor.reports', compact(
            'reportDate',
            'vendorSummaries',
            'marketTotalKg',
            'activeVendors',
            'priceBreakdown',
            'fishTypes',
        ));
    }

    /**
     * Daily market history as CSV, one row per day, fish and quality class:
     * supply, sales, pull-outs, prices against the tariff. Built from every
     * confirmed batch kept in the database, for reports and for retraining the
     * forecast model outside the system.
     */
    public function history(Request $request): StreamedResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->input('from', today()->subYear()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $rows = DB::table('vendor_inventories as v')
            ->join('fish_types as f', 'f.id', '=', 'v.fish_type_id')
            ->where('v.status', 'confirmed')
            ->whereDate('v.entry_date', '>=', $from)
            ->whereDate('v.entry_date', '<=', $to)
            ->selectRaw('DATE(v.entry_date) AS day, f.name AS fish, f.english_name AS english, v.quality_class, '
                .'COUNT(*) AS batches, COUNT(DISTINCT v.vendor_id) AS vendors, '
                .'SUM(v.stock_kg) AS supply_kg, SUM(v.sold_kg) AS sold_kg, SUM(v.pulled_out_kg) AS pulled_out_kg, '
                .'AVG(v.price_per_kg) AS avg_price, MIN(v.price_per_kg) AS min_price, MAX(v.price_per_kg) AS max_price, '
                .'AVG(v.tariff_price) AS tariff, SUM(v.sold_kg * v.price_per_kg) AS sales_value')
            ->groupByRaw('DATE(v.entry_date), f.name, f.english_name, v.quality_class')
            ->orderBy('day')
            ->orderBy('fish')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'fish', 'english_name', 'quality_class', 'batches', 'vendors', 'supply_kg', 'sold_kg',
                'pulled_out_kg', 'avg_price', 'min_price', 'max_price', 'tariff', 'avg_markup', 'sales_value']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->day, $r->fish, $r->english, $r->quality_class, $r->batches, $r->vendors,
                    round((float) $r->supply_kg, 2), round((float) $r->sold_kg, 2), round((float) $r->pulled_out_kg, 2),
                    round((float) $r->avg_price, 2), round((float) $r->min_price, 2), round((float) $r->max_price, 2),
                    $r->tariff === null ? '' : round((float) $r->tariff, 2),
                    $r->tariff === null ? '' : round((float) $r->avg_price - (float) $r->tariff, 2),
                    round((float) $r->sales_value, 2),
                ]);
            }
            fclose($out);
        }, "market-history-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }
}
