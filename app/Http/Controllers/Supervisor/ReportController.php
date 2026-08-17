<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\User;
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

        // Per-vendor sales summary for the selected date
        $vendorSummaries = User::where('role', 'vendor')
            ->with([
                'vendorProfile',
                'vendorInventories' => fn($q) =>
                    $q->with('fishType')
                      ->where('status', 'confirmed')
                      ->whereDate('entry_date', $reportDate),
            ])
            ->get()
            ->map(function ($vendor) {
                $inv = $vendor->vendorInventories;
                return [
                    'vendor'         => $vendor,
                    'total_stock_kg' => $inv->sum('stock_kg'),
                    'total_entries'  => $inv->count(),
                    'avg_price'      => $inv->avg('price_per_kg'),
                    'fish_list'      => $inv->map(fn($e) => $e->fishType?->name)->unique()->filter()->implode(', '),
                ];
            })
            ->filter(fn($v) => $v['total_entries'] > 0)
            ->sortByDesc('total_stock_kg')
            ->values();

        // Market-wide aggregates
        $marketTotalKg  = $vendorSummaries->sum('total_stock_kg');
        $activeVendors  = $vendorSummaries->count();

        // Price summary per fish type + quality class
        $priceBreakdown = VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->where('status', 'confirmed')
            ->whereDate('entry_date', $reportDate)
            ->get()
            ->groupBy(fn($e) => $e->fishType?->name . '|||' . $e->quality_class)
            ->map(fn($group) => [
                'fish_name'     => $group->first()->fishType?->name,
                'quality_class' => $group->first()->quality_class,
                'avg_price'     => round($group->avg('price_per_kg'), 2),
                'min_price'     => $group->min('price_per_kg'),
                'max_price'     => $group->max('price_per_kg'),
                'total_kg'      => $group->sum('stock_kg'),
                'vendor_count'  => $group->pluck('vendor_id')->unique()->count(),
            ])
            ->sortBy('fish_name')
            ->values();

        // Archive this snapshot to the reports table
        if ($vendorSummaries->isNotEmpty()) {
            Report::updateOrCreate(
                [
                    'generated_by' => Auth::id(),
                    'report_type'  => 'vendor_performance',
                    'report_date'  => $reportDate,
                ],
                [
                    'report_data' => [
                        'market_total_kg' => $marketTotalKg,
                        'active_vendors'  => $activeVendors,
                        'vendor_summaries' => $vendorSummaries->map(fn($v) => [
                            'vendor'         => $v['vendor']->name,
                            'stall'          => $v['vendor']->vendorProfile?->stall_number,
                            'total_stock_kg' => $v['total_stock_kg'],
                            'total_entries'  => $v['total_entries'],
                            'avg_price'      => $v['avg_price'],
                            'fish_list'      => $v['fish_list'],
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
}