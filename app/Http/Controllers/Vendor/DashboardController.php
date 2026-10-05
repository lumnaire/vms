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

        // Remaining stock across every batch still on the stall, whichever day it
        // was submitted: stock minus what the vendor has released as sold.
        $remainingStock = (float) VendorInventory::where('vendor_id', $vendorId)
            ->open()
            ->get()
            ->sum(fn ($item) => $item->getRemainingStock());

        // Today's batches for the dashboard table
        $todayInventory = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', today())
            ->orderBy('fish_type_id')
            ->orderBy('batch_no')
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
