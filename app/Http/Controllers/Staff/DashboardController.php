<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorInventory;

class DashboardController extends Controller
{
    public function index()
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

        return view('staff.dashboard', compact(
            'pendingCount',
            'pendingEntries',
            'confirmedToday',
            'rejectedToday',
            'totalVendors',
            'repeatSubmissions',
        ));
    }
}
