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

        // ── Repeat submissions ────────────────────────────────────
        // A vendor may log the same fish and class several times a day, in either
        // session. Each line is reviewed on its own, so staff need one place that
        // shows the lines side by side with what they add up to — otherwise a
        // vendor quietly declaring the same 15 kg three times is invisible.
        $repeatSubmissions = VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->whereDate('entry_date', today())
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($e) => $e->repeatKey())
            ->filter(fn ($lines) => $lines->count() > 1)
            ->map(function ($lines) {
                $confirmed = $lines->filter(fn ($e) => $e->isConfirmed());

                return [
                    'vendor' => $lines->first()->vendor,
                    'fish' => $lines->first()->fishType,
                    'quality_class' => $lines->first()->quality_class,
                    'lines' => $lines->sortBy(fn ($e) => $e->session().$e->created_at)->values(),
                    'am_kg' => (float) $confirmed->filter(fn ($e) => $e->isAmSession())->sum('released_kg'),
                    'pm_kg' => (float) $confirmed->filter(fn ($e) => $e->isPmSession())->sum('released_kg'),
                    'confirmed_kg' => (float) $confirmed->sum('released_kg'),
                    'remaining_kg' => (float) $confirmed->sum(fn ($e) => $e->getRemainingStock()),
                    'pending_kg' => (float) $lines->filter(fn ($e) => $e->isPending())->sum('released_kg'),
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
