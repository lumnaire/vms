<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\VendorInventory;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reportDate = $request->input('date', today()->toDateString());
        $fishTypes  = FishType::where('is_active', true)->orderBy('name')->get();

        // Confirmed entries for the selected date
        $confirmedEntries = VendorInventory::with(['vendor.vendorProfile', 'fishType'])
            ->where('status', 'confirmed')
            ->whereDate('entry_date', $reportDate)
            ->orderBy('fish_type_id')
            ->orderBy('quality_class')
            ->get();

        // Group by fish type → quality class for the price summary table
        $summaryByType = $confirmedEntries
            ->groupBy(fn($e) => $e->fishType->name . '|||' . $e->quality_class);

        // Market-wide totals for the day
        $totalStockKg  = $confirmedEntries->sum('stock_kg');
        $totalVendors  = $confirmedEntries->pluck('vendor_id')->unique()->count();
        $totalEntries  = $confirmedEntries->count();

        // Archive this report snapshot to the DB (upsert so refreshing doesn't duplicate)
        if ($confirmedEntries->isNotEmpty()) {
            Report::updateOrCreate(
                [
                    'generated_by' => Auth::id(),
                    'report_type'  => 'daily_price',
                    'report_date'  => $reportDate,
                ],
                [
                    'report_data' => [
                        'total_stock_kg'  => $totalStockKg,
                        'total_vendors'   => $totalVendors,
                        'total_entries'   => $totalEntries,
                        'summary_by_type' => $summaryByType->map(fn($g) => $g->map(fn($e) => [
                            'vendor'        => $e->vendor?->name,
                            'fish_type'     => $e->fishType?->name,
                            'quality_class' => $e->quality_class,
                            'price_per_kg'  => $e->price_per_kg,
                            'stock_kg'      => $e->stock_kg,
                        ]))->toArray(),
                    ],
                ]
            );
        }

        return view('staff.reports', compact(
            'reportDate',
            'confirmedEntries',
            'summaryByType',
            'totalStockKg',
            'totalVendors',
            'totalEntries',
            'fishTypes',
        ));
    }
}