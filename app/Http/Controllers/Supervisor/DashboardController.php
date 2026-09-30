<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorInventory;

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

        // The activity log is rendered on My Account, not here.
        return view('supervisor.dashboard', compact(
            'totalVendors',
            'totalStalls',
            'activeStaff',
            'totalStockKg',
        ));
    }
}
