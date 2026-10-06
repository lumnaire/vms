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
     * at another 15 kg — each approved at its own time and price. The card does
     * not list the batches: it stacks them into one total under the fish,
     * 15 + 15 = 30 kg available, with the price range across them.
     *
     * Every figure is what is LEFT: stock minus what the vendor has released as
     * sold. Batches stay on the board across days until they sell out or reach
     * the freshness limit, when they expire.
     */
    public function index()
    {
        $fishTypes = FishType::where('is_active', true)->get();

        $lines = VendorInventory::with(['vendor.vendorProfile', 'fishType'])
            ->open()
            ->orderBy('entry_date')
            ->orderBy('batch_no')
            ->get();

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

    /** One vendor's card: their fish, each with the total of its batches. */
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

        $prices = $fishLines->map(fn ($e) => (float) $e->price_per_kg);

        return [
            'key' => $first->fish_type_id.'_'.$first->quality_class,
            'fish_name' => $first->fishType?->name ?? '—',
            'quality_class' => $first->quality_class,
            'min_price' => $prices->min(),
            'max_price' => $prices->max(),
            'remaining_kg' => round($fishLines->sum(fn ($e) => $e->getRemainingStock()), 2),
        ];
    }
}
