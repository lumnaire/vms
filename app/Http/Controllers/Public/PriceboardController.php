<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use App\Services\MarketAnalytics;

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
     *
     * Each fish also shows the official tariff and how far the vendor's price is
     * from it, and the stall adding the most above the tariff for that fish is
     * flagged, so consumers can see the real standard price and who marks it up.
     */
    public function index(MarketAnalytics $analytics)
    {
        $fishTypes = FishType::where('is_active', true)->get();

        $lines = VendorInventory::with(['vendor.vendorProfile', 'fishType'])
            ->open()
            ->orderBy('entry_date')
            ->orderBy('batch_no')
            ->get();

        $tariffs = PriceGuide::tariffMap();

        // The stall with the largest markup on each fish, from the same board
        // figures the staff and supervisor tariff tables use.
        $comparison = $analytics->boardByFish()
            ->sortBy(fn ($f) => strtolower($f['fish_name']))
            ->values();
        $topMarkup = $comparison->mapWithKeys(fn ($f) => [$f['key'] => $f['top_markup']['vendor_id'] ?? null]);

        $vendors = $lines
            ->groupBy('vendor_id')
            ->map(fn ($vendorLines) => $this->vendorCard($vendorLines, $tariffs, $topMarkup))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $stats = [
            'vendors' => $vendors->count(),
            'listings' => $vendors->sum(fn ($v) => count($v['fish'])),
            'varieties' => $lines->pluck('fish_type_id')->unique()->count(),
            'remaining_kg' => round($vendors->sum('remaining_kg'), 2),
            'above_tariff' => $vendors->sum(fn ($v) => collect($v['fish'])->where('state', 'above')->count()),
        ];

        $comparison = $comparison->map(fn ($f) => [
            'key' => $f['key'],
            'fish_name' => $f['fish_name'],
            'english_name' => $f['english_name'],
            'quality_class' => $f['quality_class'],
            'tariff' => $f['tariff'],
            'min' => $f['min'],
            'max' => $f['max'],
            'stalls' => $f['stalls']->count(),
            'cheapest' => $f['stalls']->sortBy('price')->first()['stall'] ?? null,
            'top' => $f['top_markup'] ? [
                'stall' => $f['top_markup']['stall'],
                'name' => $f['top_markup']['name'],
                'price' => $f['top_markup']['price'],
                'diff' => $f['top_markup']['comparison']['diff'],
                'pct' => $f['top_markup']['comparison']['pct'],
                'note' => $f['top_markup']['note'],
            ] : null,
        ])->values();

        return view('public.priceboard', compact('fishTypes', 'vendors', 'stats', 'comparison'));
    }

    /** One vendor's card: their fish, each with the total of its batches. */
    private function vendorCard($vendorLines, $tariffs, $topMarkup): array
    {
        $first = $vendorLines->first();

        $fish = $vendorLines
            ->groupBy(fn ($e) => $e->fish_type_id.'_'.$e->quality_class)
            ->map(fn ($fishLines) => $this->fishRow($fishLines, $tariffs, $topMarkup))
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

    private function fishRow($fishLines, $tariffs, $topMarkup): array
    {
        $first = $fishLines->first();
        $key = PriceGuide::key($first->fish_type_id, $first->quality_class);

        $prices = $fishLines->map(fn ($e) => (float) $e->price_per_kg);

        // Compared at the highest price the stall charges for this fish.
        $cmp = PriceGuide::compare($prices->max(), $tariffs[$key] ?? null);

        return [
            'key' => $key,
            'fish_name' => $first->fishType?->name ?? '—',
            'english_name' => $first->fishType?->english_name,
            'quality_class' => $first->quality_class,
            'min_price' => $prices->min(),
            'max_price' => $prices->max(),
            'remaining_kg' => round($fishLines->sum(fn ($e) => $e->getRemainingStock()), 2),
            'tariff' => $cmp['tariff'] ?? null,
            'diff' => $cmp['diff'] ?? null,
            'pct' => $cmp['pct'] ?? null,
            'state' => $cmp['state'] ?? null,
            'note' => $fishLines->pluck('price_note')->filter()->last(),
            'top_markup' => ($topMarkup[$key] ?? null) === $first->vendor_id,
        ];
    }
}
