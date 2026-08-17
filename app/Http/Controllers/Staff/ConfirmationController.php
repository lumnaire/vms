<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ConfirmationController extends Controller
{
    // ─── List today's entries pending confirmation ────────────────
    public function index()
    {
        // Pending entries for today
        $pendingEntries = VendorInventory::with(['vendor.vendorProfile', 'fishType'])
            ->where('status', 'pending')
            ->whereDate('entry_date', today())
            ->latest()
            ->get();

        // Progress counts
        $totalToday     = VendorInventory::whereDate('entry_date', today())->count();
        $pendingCount   = VendorInventory::where('status', 'pending')->whereDate('entry_date', today())->count();
        $confirmedCount = VendorInventory::where('status', 'confirmed')->whereDate('entry_date', today())->count();
        $rejectedCount  = VendorInventory::where('status', 'rejected')->whereDate('entry_date', today())->count();

        // Active price guides keyed by fish_type_id + '_' + quality_class
        $priceGuides = PriceGuide::with('fishType')
            ->where('is_active', true)
            ->get()
            ->keyBy(fn($g) => $g->fish_type_id . '_' . $g->quality_class);

        // Confirmed entries today for price comparison (grouped by fish_type + quality)
        $confirmedToday = VendorInventory::with(['vendor.vendorProfile'])
            ->where('status', 'confirmed')
            ->whereDate('entry_date', today())
            ->get()
            ->groupBy(fn($e) => $e->fish_type_id . '_' . $e->quality_class);

        return view('staff.confirmations', compact(
            'pendingEntries',
            'totalToday',
            'pendingCount',
            'confirmedCount',
            'rejectedCount',
            'priceGuides',
            'confirmedToday',
        ));
    }

    // ─── Approve a pending inventory entry ───────────────────────
    public function approve(VendorInventory $inventory)
    {
        abort_if($inventory->status !== 'pending', 422, 'This entry is no longer pending.');

        $inventory->update([
            'status'       => 'confirmed',
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        $label = "{$inventory->fishType->name} ({$inventory->quality_class} Class)";

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'confirm_price',
            'description' => "Confirmed: {$label} — ₱" . number_format($inventory->price_per_kg, 2) . '/kg for vendor ' . $inventory->vendor->name . '.',
        ]);

        return redirect()->route('staff.confirmations.index')
            ->with('success', "✓ Entry confirmed: {$label} — ₱" . number_format($inventory->price_per_kg, 2) . '/kg.');
    }

    // ─── Reject a pending inventory entry ────────────────────────
    public function reject(VendorInventory $inventory)
    {
        abort_if($inventory->status !== 'pending', 422, 'This entry is no longer pending.');

        $inventory->update(['status' => 'rejected']);

        $label = "{$inventory->fishType->name} ({$inventory->quality_class} Class)";

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'reject_price',
            'description' => "Rejected: {$label} from {$inventory->vendor->name}.",
        ]);

        return redirect()->route('staff.confirmations.index')
            ->with('error', "✗ Entry rejected: {$label} from {$inventory->vendor->name}.");
    }
}