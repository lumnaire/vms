<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\VendorInventory;

class PriceboardController extends Controller
{
    /**
     * The public board: one card per vendor, listing every fish they sell today.
     *
     * A vendor may log the same fish several times in a day — 15 kg at dawn,
     * another 5 kg later in the morning, 5 kg after lunch — so each fish carries
     * its AM / PM lines and the total still for sale across them.
     *
     * Every figure is what is LEFT, not what was delivered: released minus what
     * the vendor has declared sold on the sale report. Declaring the morning's
     * sales therefore takes those kilograms off the board straight away.
     */
    public function index()
    {
        $fishTypes = FishType::where('is_active', true)->get();

        $lines = VendorInventory::with(['vendor.vendorProfile', 'fishType'])
            ->where('status', 'confirmed')
            ->whereDate('entry_date', today())
            // Carried to another day or written off: no longer on today's stall.
            ->whereNull('carried_out_at')
            ->whereNull('disposed_at')
            ->orderBy('created_at')
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

    /** One vendor's card: their fish, each with its session lines and totals. */
    private function vendorCard($vendorLines): array
    {
        $first = $vendorLines->first();

        $fish = $vendorLines
            ->groupBy(fn ($e) => $e->fish_type_id.'_'.$e->quality_class)
            ->map(fn ($fishLines) => $this->fishRow($fishLines))
            // Still for sale first, sold-out last; alphabetical within each.
            ->sortBy(fn ($f) => ($f['remaining_kg'] > 0 ? '0' : '1').strtolower($f['fish_name']))
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

        $sessionLines = $fishLines
            ->sortBy(fn ($e) => $e->session().$e->created_at)
            ->map(fn ($e) => [
                'id' => $e->id,
                'session' => $e->session(),
                'price_per_kg' => (float) $e->price_per_kg,
                'released_kg' => (float) $e->released_kg,
                'sold_kg' => (float) $e->sold_kg,
                'remaining_kg' => $e->getRemainingStock(),
                'time' => ($e->confirmed_at ?? $e->created_at)?->format('g:i A'),
            ])
            ->values()
            ->all();

        $sum = fn (string $session) => round(array_sum(array_column(
            array_filter($sessionLines, fn ($l) => $l['session'] === $session),
            'remaining_kg'
        )), 2);

        $prices = array_column($sessionLines, 'price_per_kg');

        return [
            'key' => $first->fish_type_id.'_'.$first->quality_class,
            'fish_name' => $first->fishType?->name ?? '—',
            'fish_image' => $first->fishType?->image_path ? asset('storage/'.$first->fishType->image_path) : null,
            'quality_class' => $first->quality_class,
            'min_price' => min($prices),
            'max_price' => max($prices),
            'am_kg' => $sum(VendorInventory::SESSION_AM),
            'pm_kg' => $sum(VendorInventory::SESSION_PM),
            'released_kg' => round(array_sum(array_column($sessionLines, 'released_kg')), 2),
            'sold_kg' => round(array_sum(array_column($sessionLines, 'sold_kg')), 2),
            'remaining_kg' => round(array_sum(array_column($sessionLines, 'remaining_kg')), 2),
            'lines' => $sessionLines,
        ];
    }
}
