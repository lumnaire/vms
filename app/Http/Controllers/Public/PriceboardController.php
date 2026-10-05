<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\VendorInventory;

class PriceboardController extends Controller
{
    /**
     * The public board: one card per vendor, listing every fish they have for sale.
     *
     * A vendor may submit the same fish several times — Batch 1 at 15 kg, Batch 2
     * at another 15 kg — each approved at its own time and price. The card lists
     * each batch under the fish and adds them up: 15 + 15 = 30 kg available.
     *
     * Every figure is what is LEFT: stock minus what the vendor has released as
     * sold. Batches stay on the board across days until they sell out or pass the
     * freshness window.
     */
    public function index()
    {
        $fishTypes = FishType::where('is_active', true)->get();

        $lines = VendorInventory::with(['vendor.vendorProfile', 'fishType'])
            ->open()
            ->orderBy('entry_date')
            ->orderBy('batch_no')
            ->get()
            // Past the freshness window: off the board until the vendor writes it off.
            ->reject(fn ($e) => $e->isStale());

        $vendors = $lines
            ->groupBy('vendor_id')
            ->map(fn ($vendorLines) => $this->vendorCard($vendorLines))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $stats = [
            'vendors' => $vendors->count(),
            'listings' => $vendors->sum(fn ($v) => count($v['fish'])),
            'varieties' => $lines->pluck('fish_type_id')->unique()->count(),
            'remaining_kg' => round($vendors->sum('remaining_kg'), 2),
        ];

        return view('public.priceboard', compact('fishTypes', 'vendors', 'stats'));
    }

    /** One vendor's card: their fish, each with its batches and total. */
    private function vendorCard($vendorLines): array
    {
        $first = $vendorLines->first();

        $fish = $vendorLines
            ->groupBy(fn ($e) => $e->fish_type_id.'_'.$e->quality_class)
            ->map(fn ($fishLines) => $this->fishRow($fishLines))
            ->sortBy(fn ($f) => strtolower($f['fish_name']))
            ->values()
            ->all();

        return [
            'id' => $first->vendor_id,
            'name' => $first->vendor?->name ?? '—',
            'stall' => $first->vendor?->vendorProfile?->stall_number ?? '—',
            'remaining_kg' => round(array_sum(array_column($fish, 'remaining_kg')), 2),
            'fish' => $fish,
        ];
    }

    private function fishRow($fishLines): array
    {
        $first = $fishLines->first();

        $batches = $fishLines
            ->map(function ($e) {
                $approved = $e->confirmed_at ?? $e->created_at;

                return [
                    'id' => $e->id,
                    'label' => $e->batchLabel(),
                    'price_per_kg' => (float) $e->price_per_kg,
                    'remaining_kg' => $e->getRemainingStock(),
                    // Today's batches show the time; older ones the day as well.
                    'approved' => $approved
                        ? ($approved->isToday() ? $approved->format('g:i A') : $approved->format('M j, g:i A'))
                        : null,
                ];
            })
            ->values()
            ->all();

        $prices = array_column($batches, 'price_per_kg');

        return [
            'key' => $first->fish_type_id.'_'.$first->quality_class,
            'fish_name' => $first->fishType?->name ?? '—',
            'fish_image' => $first->fishType?->image_path ? asset('storage/'.$first->fishType->image_path) : null,
            'quality_class' => $first->quality_class,
            'min_price' => min($prices),
            'max_price' => max($prices),
            'remaining_kg' => round(array_sum(array_column($batches, 'remaining_kg')), 2),
            'batches' => $batches,
        ];
    }
}
