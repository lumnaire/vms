<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use App\Services\MarketAnalytics;

/**
 * Market overview for the supervisor: supply, sales and markup over the tariff,
 * with charts for every period and the all-time records.
 */
class DashboardController extends Controller
{
    public function index(MarketAnalytics $analytics)
    {
        $totalVendors = User::where('role', 'vendor')->where('status', 'active')->count();
        $totalStalls = VendorProfile::count();
        $activeStaff = User::where('role', 'staff')->where('status', 'active')->count();

        // Today's confirmed stock total (kg)
        $totalStockKg = VendorInventory::where('status', 'confirmed')
            ->whereDate('entry_date', today())
            ->sum('stock_kg');

        $supply = $analytics->dailySupply();
        $sales = $analytics->dailySales();

        $kpis = [
            'supply' => $analytics->monthToDate($supply),
            'sales' => $analytics->monthToDate($sales, fn ($d) => $d['value']),
            'sold' => $analytics->monthToDate($sales, fn ($d) => $d['kg']),
            'tubo' => $analytics->monthToDate($sales, fn ($d) => $d['tubo']),
        ];

        // Every period's chart series, so the toggle needs no reload.
        $charts = $analytics->periodPayload([
            'supply' => [$supply],
            'sold' => [$sales, fn ($d) => $d['kg']],
            'sales' => [$sales, fn ($d) => $d['value']],
            'tubo' => [$sales, fn ($d) => $d['tubo']],
        ]);

        foreach (array_keys(MarketAnalytics::PERIODS) as $period) {
            $from = $analytics->windowStart($period);
            $charts[$period]['top_vendors'] = $analytics->topSuppliers($from)->values()->all();
            $charts[$period]['by_class'] = $analytics->supplyByClass($from);
            $charts[$period]['outcome'] = $analytics->stockOutcome($from);
        }

        $tuboBoard = $analytics->tuboLeaderboard(today()->startOfMonth(), 8);

        $records = [
            'supply_day' => $analytics->bestDay($supply),
            'sales_day' => $analytics->bestDay($sales, fn ($d) => $d['value']),
            'highest_price' => $analytics->highestPriceEver(),
            'highest_markup' => $analytics->highestMarkupEver(),
        ];

        // Largest markups on the board right now.
        $markupWatch = $analytics->boardByFish()
            ->filter(fn ($f) => $f['top_markup'])
            ->sortByDesc(fn ($f) => $f['top_markup']['comparison']['pct'])
            ->take(5)
            ->values();

        // The activity log is rendered on My Account, not here.
        return view('supervisor.dashboard', compact(
            'totalVendors',
            'totalStalls',
            'activeStaff',
            'totalStockKg',
            'kpis',
            'charts',
            'tuboBoard',
            'records',
            'markupWatch',
        ));
    }
}
