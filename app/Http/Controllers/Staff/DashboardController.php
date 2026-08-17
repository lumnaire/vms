<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\VendorInventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $pendingCount   = VendorInventory::where('status', 'pending')->whereDate('entry_date', today())->count();
        $pendingEntries = VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->where('status', 'pending')
            ->whereDate('entry_date', today())
            ->latest()
            ->take(5)
            ->get();
        $confirmedToday = VendorInventory::where('status', 'confirmed')->whereDate('entry_date', today())->count();
        $rejectedToday  = VendorInventory::where('status', 'rejected')->whereDate('entry_date', today())->count();
        $totalVendors   = User::where('role', 'vendor')->where('status', 'active')->count();

        // ── Price Trend Chart: avg confirmed price/kg per fish type, last 7 days ──
        $fishTypes = FishType::where('is_active', true)->orderBy('name')->get();

        $rawTrends = VendorInventory::where('status', 'confirmed')
            ->whereBetween('entry_date', [today()->subDays(6), today()])
            ->select('fish_type_id', 'entry_date', DB::raw('AVG(price_per_kg) as avg_price'))
            ->groupBy('fish_type_id', 'entry_date')
            ->orderBy('entry_date')
            ->get();

        // Build 7-day label array (e.g. "Jun 04" … "Jun 10")
        $chartLabels = collect();
        for ($i = 6; $i >= 0; $i--) {
            $chartLabels->push(today()->subDays($i)->format('M d'));
        }

        // Build per-fish-type dataset with null for missing days; skip fish types with no data
        $chartDatasets = $fishTypes->map(function ($fish) use ($rawTrends, $chartLabels) {
            $byDate = $rawTrends
                ->where('fish_type_id', $fish->id)
                ->keyBy(fn($r) => Carbon::parse($r->entry_date)->format('M d'));

            $data = $chartLabels->map(
                fn($label) => isset($byDate[$label]) ? round($byDate[$label]->avg_price, 2) : null
            )->values();

            return ['label' => $fish->name, 'data' => $data];
        })->filter(fn($ds) => collect($ds['data'])->contains(fn($v) => $v !== null))
          ->values();

        $hasPriceTrendData = $rawTrends->isNotEmpty();

        return view('staff.dashboard', compact(
            'pendingCount',
            'pendingEntries',
            'confirmedToday',
            'rejectedToday',
            'totalVendors',
            'chartLabels',
            'chartDatasets',
            'hasPriceTrendData',
        ));
    }
}