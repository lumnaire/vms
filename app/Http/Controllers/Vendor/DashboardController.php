<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\VendorInventory;

class DashboardController extends Controller
{
    public function index()
    {
        $vendorId = Auth::id();

        $todayEntries    = VendorInventory::where('vendor_id', $vendorId)->whereDate('entry_date', today())->count();
        $confirmedEntries = VendorInventory::where('vendor_id', $vendorId)->where('status', 'confirmed')->whereDate('entry_date', today())->count();
        $pendingEntries  = VendorInventory::where('vendor_id', $vendorId)->where('status', 'pending')->whereDate('entry_date', today())->count();

        // Remaining stock = sum of (released_kg - sold_kg) for today's confirmed entries
        $remainingStock  = VendorInventory::where('vendor_id', $vendorId)
            ->where('status', 'confirmed')
            ->whereDate('entry_date', today())
            ->get()
            ->sum(fn($item) => $item->getRemainingStock());

        // Today's inventory rows for the dashboard table
        $todayInventory = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', today())
            ->latest()
            ->get();

        return view('vendor.dashboard', compact(
            'todayEntries',
            'confirmedEntries',
            'pendingEntries',
            'remainingStock',
            'todayInventory',
        ));
    }
}