<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PriceGuide;
use App\Models\User;
use App\Models\VendorInventory;
use App\Services\MarketAnalytics;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(MarketAnalytics $analytics)
    {
        $pendingCount = VendorInventory::where('status', 'pending')->whereDate('entry_date', today())->count();
        $pendingEntries = VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->where('status', 'pending')
            ->whereDate('entry_date', today())
            ->latest()
            ->take(5)
            ->get();
        $confirmedToday = VendorInventory::where('status', 'confirmed')->whereDate('entry_date', today())->count();
        $rejectedToday = VendorInventory::where('status', 'rejected')->whereDate('entry_date', today())->count();
        $totalVendors = User::where('role', 'vendor')->where('status', 'active')->count();

        // ── Multiple batches ──────────────────────────────────────
        // A vendor may submit the same fish and class several times a day; each
        // submission is its own batch and is reviewed on its own. Staff need one
        // place that shows the batches side by side with the supply they add up
        // to — otherwise a vendor declaring the same 15 kg three times is
        // invisible. Supply only: what vendors sold is the vendor's own record.
        $repeatSubmissions = VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->whereDate('entry_date', today())
            ->orderBy('batch_no')
            ->get()
            ->groupBy(fn ($e) => $e->repeatKey())
            ->filter(fn ($lines) => $lines->count() > 1)
            ->map(function ($lines) {
                return [
                    'vendor' => $lines->first()->vendor,
                    'fish' => $lines->first()->fishType,
                    'quality_class' => $lines->first()->quality_class,
                    'lines' => $lines->values(),
                    'confirmed_kg' => (float) $lines->filter(fn ($e) => $e->isConfirmed())->sum('stock_kg'),
                    'pending_kg' => (float) $lines->filter(fn ($e) => $e->isPending())->sum('stock_kg'),
                    'pending_count' => $lines->filter(fn ($e) => $e->isPending())->count(),
                ];
            })
            // Groups with something to review first, then by vendor.
            ->sortBy(fn ($g) => ($g['pending_count'] > 0 ? '0' : '1').($g['vendor']?->name ?? ''))
            ->values();

        // ── Entries this week: one stacked bar per day by status ──────
        $weekStart = today()->subDays(6);
        $counts = DB::table('vendor_inventories')
            ->whereDate('entry_date', '>=', $weekStart->toDateString())
            ->selectRaw('DATE(entry_date) AS d, status, COUNT(*) AS n')
            ->groupByRaw('DATE(entry_date), status')
            ->get()
            ->groupBy('d');

        $week = ['labels' => [], 'confirmed' => [], 'rejected' => [], 'pending' => []];
        for ($day = $weekStart->copy(); $day->lte(today()); $day->addDay()) {
            $rows = $counts->get($day->toDateString(), collect())->pluck('n', 'status');
            $week['labels'][] = $day->isToday() ? 'Today' : $day->format('D j');
            foreach (['confirmed', 'rejected', 'pending'] as $status) {
                $week[$status][] = (int) ($rows[$status] ?? 0);
            }
        }

        // ── Vendors who have not submitted anything today ─────────────
        $notSubmitted = User::with('vendorProfile')
            ->where('role', 'vendor')
            ->where('status', 'active')
            ->whereDoesntHave('vendorInventories', fn ($q) => $q->whereDate('entry_date', today()))
            ->orderBy('name')
            ->get();

        // ── Tariff: today's batches priced above it, and who marks up most ──
        $tariffs = PriceGuide::tariffMap();
        $aboveTariffToday = VendorInventory::whereDate('entry_date', today())
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->filter(function ($e) use ($tariffs) {
                $tariff = $e->tariff_price ?? ($tariffs[PriceGuide::key($e->fish_type_id, $e->quality_class)] ?? null);

                return $tariff !== null && (float) $e->price_per_kg > (float) $tariff;
            })
            ->count();

        // Markup only: sales stay with the vendor and the supervisor.
        $markupBoard = $analytics->tuboLeaderboard(today()->startOfMonth(), 8, withSales: false);

        // Supply trend for every period (kilograms only).
        $dailySupply = $analytics->dailySupply();
        $supplyCharts = $analytics->periodPayload(['supply' => [$dailySupply]]);
        $supplyMonth = $analytics->monthToDate($dailySupply);

        return view('staff.dashboard', compact(
            'pendingCount',
            'pendingEntries',
            'confirmedToday',
            'rejectedToday',
            'totalVendors',
            'repeatSubmissions',
            'week',
            'notSubmitted',
            'aboveTariffToday',
            'markupBoard',
            'supplyCharts',
            'supplyMonth',
        ));
    }
}
