<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorInventory;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $vendorId = Auth::id();

        $todayEntries = VendorInventory::where('vendor_id', $vendorId)->whereDate('entry_date', today())->count();
        $confirmedEntries = VendorInventory::where('vendor_id', $vendorId)->where('status', 'confirmed')->whereDate('entry_date', today())->count();
        $pendingEntries = VendorInventory::where('vendor_id', $vendorId)->where('status', 'pending')->whereDate('entry_date', today())->count();

        // Remaining stock comes from today's confirmed entries, so the figure is
        // released minus what the vendor declared sold on the sale report — never
        // a separately maintained total that can drift.
        $todayConfirmed = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->where('status', 'confirmed')
            ->whereDate('entry_date', today())
            ->get();

        $remainingStock = (float) $todayConfirmed->sum(fn ($item) => $item->getRemainingStock());

        // Today's inventory rows for the dashboard table, AM before PM
        $todayInventory = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', today())
            ->orderBy('market_session')
            ->latest()
            ->get();

        // Unsold stock that has been held past the freshness window. Surfaced
        // rather than left to age silently inside a total. Oldest first, so the
        // worst offenders lead the list instead of being buried under the
        // recent ones, and the panel stays readable by capping what it renders.
        $staleEntries = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->where('status', 'confirmed')
            ->whereDate('entry_date', '<=', today()->subDays(config('inventory.stale_after_days')))
            ->get()
            ->filter(fn ($item) => $item->isStale())
            ->sortByDesc(fn ($item) => $item->getAgeInDays())
            ->values();

        $staleTotal = $staleEntries->count();
        $staleTotalValue = $staleEntries->sum(fn ($item) => $item->getRemainingStockValue());
        $staleShownEntries = $staleEntries->take(10);
        $staleHiddenCount = $staleTotal - $staleShownEntries->count();

        return view('vendor.dashboard', compact(
            'todayEntries',
            'confirmedEntries',
            'pendingEntries',
            'remainingStock',
            'todayInventory',
            'staleEntries',
            'staleTotal',
            'staleTotalValue',
            'staleShownEntries',
            'staleHiddenCount',
        ));
    }
}
