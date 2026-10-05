<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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
        // or goes stale, so yesterday's batch with 4 kg left belongs here too.
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

        // Closed batches from the past week: sold out, written off or rejected.
        $history = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', '<', today())
            ->whereDate('entry_date', '>=', today()->subDays(7))
            ->whereNotIn('id', $batches->pluck('id'))
            ->orderByDesc('entry_date')
            ->orderBy('batch_no')
            ->get();

        $open = $batches->filter(fn ($b) => $b->isOpen());
        $remainingKg = (float) $open->sum(fn ($b) => $b->getRemainingStock());
        $onSaleCount = $open->reject(fn ($b) => $b->isStale())->count();
        $pendingCount = $batches->filter(fn ($b) => $b->isPending())->count();
        $staleCount = $open->filter(fn ($b) => $b->isStale())->count();

        // For the form: what this vendor already has of each fish, so submitting the
        // same fish again shows "you have 15 kg — this will be Batch 2" before it is
        // sent. Keyed by fish type + quality class.
        $existingBatches = $batches
            ->filter(fn ($b) => $b->isOpen() || ($b->isPending() && $b->entry_date->isToday()))
            ->groupBy(fn ($b) => $b->fish_type_id.'_'.$b->quality_class)
            ->map(fn ($group) => [
                'remaining' => round((float) $group->filter(fn ($b) => $b->isOpen())->sum(fn ($b) => $b->getRemainingStock()), 2),
                'next_batch' => VendorInventory::nextBatchNo($vendorId, $group->first()->fish_type_id, $group->first()->quality_class, today()),
                'batches' => $group->map(fn ($b) => [
                    'label' => $b->batchLabel().($b->entry_date->isToday() ? '' : ' ('.$b->entry_date->format('M j').')'),
                    'kg' => $b->isPending() ? (float) $b->stock_kg : $b->getRemainingStock(),
                    'status' => $b->isPending() ? 'pending' : ($b->isStale() ? 'stale' : 'on sale'),
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
            'staleCount',
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

        // Submitting a fish the vendor already has on the stall (or waiting for
        // staff) makes a second batch, which is allowed — but it has to be meant.
        // The form shows what is already there and asks the vendor to confirm.
        $existing = VendorInventory::where('vendor_id', $vendorId)
            ->where('fish_type_id', $request->fish_type_id)
            ->where('quality_class', $request->quality_class)
            ->where(fn (Builder $q) => $q->open()->orWhere(
                fn (Builder $q) => $q->where('status', 'pending')->whereDate('entry_date', today())
            ))
            ->get();

        if ($existing->isNotEmpty() && ! $request->boolean('confirm_new_batch')) {
            $left = $existing->filter(fn ($b) => $b->isOpen())->sum(fn ($b) => $b->getRemainingStock());

            return back()->withInput()->withErrors([
                'confirm_new_batch' => "You already have {$fishName} ("
                    .number_format($left, 1).' kg remaining). Confirm that this is another batch.',
            ]);
        }

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

    // ─── Release: record kilograms sold from a batch ──────────────
    public function release(Request $request, VendorInventory $inventory)
    {
        abort_if($inventory->vendor_id !== Auth::id(), 403);

        $request->validate([
            'release_kg' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
        ], [
            'release_kg.required' => 'Enter how many kilograms were sold.',
            'release_kg.min' => 'Enter at least 0.01 kg.',
        ]);

        $kg = (float) $request->input('release_kg');
        $inventory->release($kg);

        $label = ($inventory->fishType?->name ?? 'Fish').' '.$inventory->batchLabel();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'release_stock',
            'description' => 'Released (sold) '.number_format($kg, 2)." kg of {$label} — "
                .number_format($inventory->getRemainingStock(), 2).' kg left.',
        ]);

        $message = number_format($kg, 2)." kg of {$label} released as sold. "
            .($inventory->hasRemainingStock()
                ? number_format($inventory->getRemainingStock(), 2).' kg left.'
                : 'This batch is now sold out.');

        return back()->with('success', $message);
    }

    // ─── Write off a stale batch ──────────────────────────────────
    public function writeOff(Request $request, VendorInventory $inventory)
    {
        abort_if($inventory->vendor_id !== Auth::id(), 403);

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $kg = $inventory->getRemainingStock();
        $inventory->writeOff($request->input('reason'));

        $label = ($inventory->fishType?->name ?? 'Fish').' '.$inventory->batchLabel();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'dispose_stock',
            'description' => 'Wrote off '.number_format($kg, 2)." kg of {$label} from "
                .$inventory->entry_date->format('M j, Y').', held '.$inventory->getAgeInDays().' days'
                .($request->filled('reason') ? ': '.$request->input('reason') : '.'),
        ]);

        return back()->with('success', number_format($kg, 2)." kg of {$label} written off.");
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
