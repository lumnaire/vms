<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use App\Services\CarryForwardStock;
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

        // Active price guidelines keyed by fish type + quality class, used by the
        // live price check in the submit form
        $priceGuides = PriceGuide::where('is_active', true)
            ->get()
            ->keyBy(fn($g) => $g->fish_type_id . '_' . $g->quality_class)
            ->map(fn($g) => [
                'cheap'    => (float) $g->cheap_max,
                'moderate' => (float) $g->moderate_max,
            ]);

        return view('vendor.inventory', compact(
            'fishTypes',
            'todayEntries',
            'recentEntries',
            'totalStockToday',
            'pendingCount',
            'confirmedCount',
            'rejectedCount',
            'submittedCombos',
            'priceGuides',
        ));
    }

    // ─── Submit a new inventory entry ─────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'fish_type_id'  => ['required', 'exists:fish_types,id'],
            'quality_class' => [
                'required',
                'in:' . implode(',', FishType::QUALITY_CLASSES),
                function ($attribute, $value, $fail) use ($request) {
                    $fish = FishType::find($request->fish_type_id);
                    if ($fish && $fish->quality_class !== $value) {
                        $fail('The selected quality class does not match this fish type.');
                    }
                },
            ],
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
    public function destroy(VendorInventory $inventory, CarryForwardStock $carry)
    {
        if ($inventory->vendor_id !== Auth::id()) {
            abort(403);
        }

        if ($inventory->status !== 'pending') {
            return back()->withErrors(['cancel' => 'Only pending entries can be cancelled.']);
        }

        // Cancelling a resubmission returns the unsold stock to the entry it was
        // carried from. The source stopped counting those kilograms the moment the
        // replacement was created, so the handover has to be undone with it.
        $wasCarried = $inventory->carried_from_id !== null;

        $inventory->delete();

        if ($wasCarried) {
            $carry->release($inventory);
        }

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'cancel_inventory',
            'description' => 'Cancelled inventory entry ID ' . $inventory->id . '.',
        ]);

        return redirect()->route($wasCarried ? 'vendor.my-stock.index' : 'vendor.inventory.index')
            ->with('success', $wasCarried
                ? 'Resubmission cancelled. The unsold stock is back on your original entry.'
                : 'Inventory entry cancelled successfully.');
    }

    // ─── Update sold quantity for a confirmed entry ───────────────
    /**
     * Removed: sold kg is no longer edited entry-by-entry.
     *
     * Doing it per entry let the numbers drift out of step with each other and
     * left no record of who declared what. The vendor now closes the day in one
     * place — see Vendor\SaleReportController — which writes sold_kg back onto
     * each entry from the declared totals.
     */
}
