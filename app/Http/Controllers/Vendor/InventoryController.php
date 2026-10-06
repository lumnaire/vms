<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BatchRelease;
use App\Models\FishType;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    // ─── Submission form + batch monitoring table ─────────────────
    public function index()
    {
        $vendorId = Auth::id();
        $fishTypes = FishType::where('is_active', true)->orderBy('name')->get();

        // Batches to monitor: everything submitted today, plus every earlier batch
        // still on the stall. Fish stays on sale across days until it is sold out
        // or reaches the freshness limit and expires, so yesterday's batch with
        // 4 kg left belongs here too.
        $batches = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->where(fn (Builder $q) => $q->whereDate('entry_date', today())->orWhere(fn (Builder $q) => $q->open()))
            ->get()
            ->sortBy([
                fn ($a, $b) => strcmp($a->fishType?->name ?? '', $b->fishType?->name ?? ''),
                fn ($a, $b) => $a->entry_date <=> $b->entry_date,
                fn ($a, $b) => $a->batch_no <=> $b->batch_no,
            ])
            ->values();

        // Closed batches from the past week: sold out, released or rejected. Expired batches
        // are kept in the database for reports but gone from the vendor's view.
        $history = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereNull('disposed_at')
            ->whereDate('entry_date', '<', today())
            ->whereDate('entry_date', '>=', today()->subDays(7))
            ->whereNotIn('id', $batches->pluck('id'))
            ->orderByDesc('entry_date')
            ->orderBy('batch_no')
            ->get();

        $open = $batches->filter(fn ($b) => $b->isOpen());
        $remainingKg = (float) $open->sum(fn ($b) => $b->getRemainingStock());
        $onSaleCount = $open->count();
        $pendingCount = $batches->filter(fn ($b) => $b->isPending())->count();

        // For the form: what this vendor already has of each fish, so choosing the
        // same fish again shows a reminder, "you have 15 kg — this will be Batch 2".
        // Keyed by fish type + quality class.
        $existingBatches = $batches
            ->filter(fn ($b) => $b->isOpen() || ($b->isPending() && $b->entry_date->isToday()))
            ->groupBy(fn ($b) => $b->fish_type_id.'_'.$b->quality_class)
            ->map(fn ($group) => [
                'remaining' => round((float) $group->filter(fn ($b) => $b->isOpen())->sum(fn ($b) => $b->getRemainingStock()), 2),
                'next_batch' => VendorInventory::nextBatchNo($vendorId, $group->first()->fish_type_id, $group->first()->quality_class, today()),
                'batches' => $group->map(fn ($b) => [
                    'label' => $b->batchLabel().($b->entry_date->isToday() ? '' : ' ('.$b->entry_date->format('M j').')'),
                    'kg' => $b->isPending() ? (float) $b->stock_kg : $b->getRemainingStock(),
                    'status' => $b->isPending() ? 'pending' : 'on sale',
                ])->values(),
            ]);

        // Active price guidelines keyed by fish type + quality class, used by the
        // live price check in the submit form
        $priceGuides = PriceGuide::where('is_active', true)
            ->get()
            ->keyBy(fn ($g) => $g->fish_type_id.'_'.$g->quality_class)
            ->map(fn ($g) => [
                'cheap' => (float) $g->cheap_max,
                'moderate' => (float) $g->moderate_max,
            ]);

        return view('vendor.inventory', compact(
            'fishTypes',
            'batches',
            'history',
            'remainingKg',
            'onSaleCount',
            'pendingCount',
            'existingBatches',
            'priceGuides',
        ));
    }

    // ─── Submit a batch ───────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'fish_type_id' => ['required', 'exists:fish_types,id'],
            'quality_class' => [
                'required',
                'in:'.implode(',', FishType::QUALITY_CLASSES),
                function ($attribute, $value, $fail) use ($request) {
                    $fish = FishType::find($request->fish_type_id);
                    if ($fish && $fish->quality_class !== $value) {
                        $fail('The selected quality class does not match this fish type.');
                    }
                },
            ],
            'price_per_kg' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'stock_kg' => ['required', 'numeric', 'min:0.1', 'max:99999.99'],
        ], [
            'fish_type_id.required' => 'Please select a fish type.',
            'fish_type_id.exists' => 'Selected fish type is invalid.',
            'quality_class.required' => 'Please select a quality class.',
            'quality_class.in' => 'Invalid quality class selected.',
            'price_per_kg.required' => 'Price per kg is required.',
            'price_per_kg.min' => 'Price must be at least ₱0.01.',
            'stock_kg.required' => 'Stock quantity is required.',
            'stock_kg.min' => 'Stock must be at least 0.1 kg.',
        ]);

        $vendorId = Auth::id();
        $fishName = FishType::find($request->fish_type_id)?->name ?? 'Unknown';

        // Submitting a fish the vendor already has makes the next batch. The form
        // only reminds them of what is already there; nothing to confirm.
        $entry = VendorInventory::create([
            'vendor_id' => $vendorId,
            'fish_type_id' => $request->fish_type_id,
            'quality_class' => $request->quality_class,
            'batch_no' => VendorInventory::nextBatchNo($vendorId, (int) $request->fish_type_id, $request->quality_class, today()),
            'price_per_kg' => $request->price_per_kg,
            // What the vendor brings is what is for sale.
            'stock_kg' => $request->stock_kg,
            'released_kg' => $request->stock_kg,
            'sold_kg' => 0,
            'status' => 'pending',
            'entry_date' => today(),
            'is_locked' => false,
        ]);

        ActivityLog::create([
            'user_id' => $vendorId,
            'action' => 'submit_inventory',
            'description' => "Submitted {$fishName} ({$request->quality_class}) {$entry->batchLabel()} — ₱"
                .number_format($request->price_per_kg, 2).'/kg, '.$request->stock_kg.' kg.',
        ]);

        return redirect()->route('vendor.inventory.index')
            ->with('success', "{$fishName} {$entry->batchLabel()} submitted. Awaiting staff confirmation.");
    }

    // ─── Release: kilograms sold or pulled out from a batch ───────
    public function release(Request $request, VendorInventory $inventory)
    {
        abort_if($inventory->vendor_id !== Auth::id(), 403);

        $request->validate([
            'release_kg' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'release_kind' => ['nullable', 'in:'.implode(',', BatchRelease::KINDS)],
            'release_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'release_kg.required' => 'Enter how many kilograms to release.',
            'release_kg.min' => 'Enter at least 0.01 kg.',
            'release_kind.in' => 'Choose whether the fish was sold or pulled out.',
        ]);

        $kg = (float) $request->input('release_kg');
        $kind = $request->input('release_kind') ?: BatchRelease::SOLD;
        $reason = $request->input('release_reason');
        $inventory->release($kg, $kind, $reason);

        $label = ($inventory->fishType?->name ?? 'Fish').' '.$inventory->batchLabel();
        $how = $kind === BatchRelease::PULLED_OUT ? 'pulled out (not sold)' : 'sold';
        $left = $inventory->getRemainingStock();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $kind === BatchRelease::PULLED_OUT ? 'pull_out_stock' : 'release_stock',
            'description' => 'Released '.number_format($kg, 2)." kg of {$label} as {$how}"
                .($reason ? " ({$reason})" : '').' — '.number_format($left, 2).' kg left.',
        ]);

        $message = number_format($kg, 2)." kg of {$label} released as {$how}. "
            .($left > 0 ? number_format($left, 2).' kg left.' : 'Nothing is left in this batch.');

        return back()->with('success', $message);
    }

    // ─── Cancel a pending batch ───────────────────────────────────
    public function destroy(VendorInventory $inventory)
    {
        abort_if($inventory->vendor_id !== Auth::id(), 403);

        if (! $inventory->isPending()) {
            return back()->withErrors(['cancel' => 'Only pending batches can be cancelled.']);
        }

        $inventory->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'cancel_inventory',
            'description' => 'Cancelled inventory entry ID '.$inventory->id.'.',
        ]);

        return redirect()->route('vendor.inventory.index')->with('success', 'Batch cancelled.');
    }
}
