<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\VendorInventory;
use App\Models\User;

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

        return view('staff.dashboard', compact(
            'pendingCount',
            'pendingEntries',
            'confirmedToday',
            'rejectedToday',
            'totalVendors',
        ));
    }
}