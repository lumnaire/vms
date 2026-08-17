<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Forecast;
use App\Models\FishType;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorInventory;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalVendors = User::where('role', 'vendor')->where('status', 'active')->count();
        $totalStalls  = VendorProfile::count();
        $activeStaff  = User::where('role', 'staff')->where('status', 'active')->count();

        // Today's confirmed stock total (kg)
        $totalStockKg = VendorInventory::where('status', 'confirmed')
            ->whereDate('entry_date', today())
            ->sum('stock_kg');

        // ── Forecast chart: all active fish types, First Class, price ──
        $fishTypes = FishType::where('is_active', true)->orderBy('name')->get();

        $rawForecasts = Forecast::whereIn('fish_type_id', $fishTypes->pluck('id'))
            ->where('quality_class', 'First Class')
            ->where('metric', 'price')
            ->where('forecast_date', '>=', today())
            ->orderBy('forecast_date')
            ->get();

        // Build sorted date labels from available forecast rows
        $chartLabels = $rawForecasts
            ->pluck('forecast_date')
            ->unique()
            ->sort()
            ->values()
            ->map(fn($d) => Carbon::parse($d)->format('M d'));

        // One dataset per fish type (skip fish types with no forecast rows)
        $chartDatasets = $fishTypes->map(function ($fish) use ($rawForecasts, $chartLabels) {
            $byDate = $rawForecasts
                ->where('fish_type_id', $fish->id)
                ->keyBy(fn($r) => Carbon::parse($r->forecast_date)->format('M d'));

            $data = $chartLabels->map(
                fn($label) => isset($byDate[$label])
                    ? round($byDate[$label]->predicted_value, 2)
                    : null
            )->values();

            return ['label' => $fish->name, 'data' => $data];
        })->filter(fn($ds) => collect($ds['data'])->contains(fn($v) => $v !== null))
          ->values();

        $hasForecastData = $rawForecasts->isNotEmpty();

        // Recent activity: latest 10 logs with user
        $recentActivity = ActivityLog::with('user')->latest()->take(10)->get();

        return view('supervisor.dashboard', compact(
            'totalVendors',
            'totalStalls',
            'activeStaff',
            'totalStockKg',
            'chartLabels',
            'chartDatasets',
            'hasForecastData',
            'recentActivity',
        ));
    }
}