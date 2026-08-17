<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\VendorInventory;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    // ─── List inventory entries ───────────────────────────────────
    public function index()
    {
        $vendorId  = Auth::id();
        $fishTypes = FishType::where('is_active', true)->orderBy('name')->get();

        // Today's entries
        $todayEntries = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', today())
            ->latest()
            ->get();

        // Past 7 days (excluding today)
        $recentEntries = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', '<', today())
            ->whereDate('entry_date', '>=', today()->subDays(7))
            ->latest()
            ->get();

        // Today's summary stats
        $totalStockToday = $todayEntries->sum('stock_kg');
        $pendingCount    = $todayEntries->where('status', 'pending')->count();
        $confirmedCount  = $todayEntries->where('status', 'confirmed')->count();
        $rejectedCount   = $todayEntries->where('status', 'rejected')->count();

        // Fish types already submitted today (to disable duplicates in the form)
        $submittedCombos = $todayEntries
            ->map(fn($e) => $e->fish_type_id . '_' . $e->quality_class)
            ->toArray();

        return view('vendor.inventory', compact(
            'fishTypes',
            'todayEntries',
            'recentEntries',
            'totalStockToday',
            'pendingCount',
            'confirmedCount',
            'rejectedCount',
            'submittedCombos',
        ));
    }

    // ─── Submit a new inventory entry ─────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'fish_type_id'  => ['required', 'exists:fish_types,id'],
            'quality_class' => ['required', 'in:First Class,Second Class,Third Class,Fourth Class,Special Class'],
            'price_per_kg'  => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'stock_kg'      => ['required', 'numeric', 'min:0.1',  'max:99999.99'],
            'released_kg'   => ['required', 'numeric', 'min:0.1',  'lte:stock_kg'],
        ], [
            'fish_type_id.required'  => 'Please select a fish type.',
            'fish_type_id.exists'    => 'Selected fish type is invalid.',
            'quality_class.required' => 'Please select a quality class.',
            'quality_class.in'       => 'Invalid quality class selected.',
            'price_per_kg.required'  => 'Price per kg is required.',
            'price_per_kg.min'       => 'Price must be at least ₱0.01.',
            'stock_kg.required'      => 'Stock quantity is required.',
            'stock_kg.min'           => 'Stock must be at least 0.1 kg.',
            'released_kg.required'   => 'Released quantity is required.',
            'released_kg.min'        => 'Released kg must be at least 0.1.',
            'released_kg.lte'        => 'Released quantity cannot exceed total stock.',
        ]);

        // Prevent duplicate: same fish type + quality class on the same day
        $alreadyExists = VendorInventory::where('vendor_id', Auth::id())
            ->where('fish_type_id',  $request->fish_type_id)
            ->where('quality_class', $request->quality_class)
            ->whereDate('entry_date', today())
            ->exists();

        if ($alreadyExists) {
            return back()
                ->withInput()
                ->withErrors([
                    'fish_type_id' => 'You already have an entry for this fish type and quality class today.',
                ]);
        }

        VendorInventory::create([
            'vendor_id'     => Auth::id(),
            'fish_type_id'  => $request->fish_type_id,
            'quality_class' => $request->quality_class,
            'price_per_kg'  => $request->price_per_kg,
            'stock_kg'      => $request->stock_kg,
            'released_kg'   => $request->released_kg,
            'sold_kg'       => 0,
            'status'        => 'pending',
            'entry_date'    => today(),
            'is_locked'     => false,
        ]);

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'submit_inventory',
            'description' => 'Submitted inventory: ' . ($fishType = \App\Models\FishType::find($request->fish_type_id)?->name ?? 'Unknown')
                           . ' (' . $request->quality_class . ') — ₱' . number_format($request->price_per_kg, 2) . '/kg, ' . $request->stock_kg . ' kg.',
        ]);

        return redirect()->route('vendor.inventory.index')
            ->with('success', 'Inventory entry submitted successfully. Awaiting staff confirmation.');
    }

    // ─── Cancel a pending inventory entry ───────────────────────────
    public function destroy(VendorInventory $inventory)
    {
        if ($inventory->vendor_id !== Auth::id()) {
            abort(403);
        }

        if ($inventory->status !== 'pending') {
            return back()->withErrors(['cancel' => 'Only pending entries can be cancelled.']);
        }

        $inventory->delete();

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'cancel_inventory',
            'description' => 'Cancelled inventory entry ID ' . $inventory->id . '.',
        ]);

        return redirect()->route('vendor.inventory.index')
            ->with('success', 'Inventory entry cancelled successfully.');
    }

    // ─── Update sold quantity for a confirmed entry ───────────────
    public function updateSold(Request $request, VendorInventory $inventory)
    {
        // Only the owning vendor may update
        if ($inventory->vendor_id !== Auth::id()) {
            abort(403);
        }

        // Only confirmed (and unlocked) entries can have sold_kg updated
        if ($inventory->status !== 'confirmed') {
            return back()->withErrors(['sold_kg' => 'Only confirmed entries can be updated.']);
        }

        if ($inventory->is_locked) {
            return back()->withErrors(['sold_kg' => 'This entry is locked and can no longer be edited.']);
        }

        $request->validate([
            'sold_kg' => [
                'required',
                'numeric',
                'min:0',
                // sold_kg cannot exceed released_kg
                'max:' . $inventory->released_kg,
            ],
        ], [
            'sold_kg.max' => 'Sold quantity cannot exceed released quantity (' . $inventory->released_kg . ' kg).',
        ]);

        $inventory->update(['sold_kg' => $request->sold_kg]);

        return redirect()->route('vendor.inventory.index')
            ->with('success', 'Sold quantity updated successfully.');
    }
}
