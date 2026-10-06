<?php

namespace App\Services;

use App\Models\BatchRelease;
use App\Models\VendorInventory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * SupplyReport
 *
 * The fish supply staff confirmed over a day, a month or a year: how many kilograms
 * of each fish came into the market, in how many batches, from how many vendors,
 * and at what prices.
 *
 * Plus every release of stock that was pulled out unsold, one line each, so
 * fish that left the market without being sold is on record. What vendors
 * sold is their own record and deliberately not part of a staff report.
 *
 * Used for both the on-screen preview and the PDF, so the two cannot disagree.
 */
class SupplyReport
{
    public const PERIODS = ['daily', 'monthly', 'yearly'];

    public function __construct(
        public readonly string $period,
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {}

    /**
     * Resolve the period from request input, falling back to today / this month /
     * this year when the value is missing or malformed.
     */
    public static function for(?string $period, ?string $value): self
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'daily';

        try {
            $start = match ($period) {
                'daily' => Carbon::createFromFormat('Y-m-d', (string) $value)->startOfDay(),
                'monthly' => Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth(),
                'yearly' => Carbon::createFromFormat('Y-m-d', $value.'-01-01')->startOfYear(),
            };
        } catch (\Throwable) {
            $start = match ($period) {
                'daily' => today(),
                'monthly' => today()->startOfMonth(),
                'yearly' => today()->startOfYear(),
            };
        }

        $end = match ($period) {
            'daily' => $start->copy()->endOfDay(),
            'monthly' => $start->copy()->endOfMonth(),
            'yearly' => $start->copy()->endOfYear(),
        };

        return new self($period, $start, $end);
    }

    /** The value the period's input holds: 2026-10-05, 2026-10 or 2026. */
    public function value(): string
    {
        return match ($this->period) {
            'daily' => $this->start->format('Y-m-d'),
            'monthly' => $this->start->format('Y-m'),
            'yearly' => $this->start->format('Y'),
        };
    }

    public function label(): string
    {
        return match ($this->period) {
            'daily' => $this->start->format('F j, Y'),
            'monthly' => $this->start->format('F Y'),
            'yearly' => $this->start->format('Y'),
        };
    }

    public function title(): string
    {
        return ucfirst($this->period).' Supply Report';
    }

    public function filename(): string
    {
        return 'supply-report-'.$this->period.'-'.$this->value().'.pdf';
    }

    /** What the breakdown table is grouped by for this period. */
    public function breakdownLabel(): string
    {
        return match ($this->period) {
            'daily' => 'Vendor',
            'monthly' => 'Day',
            'yearly' => 'Month',
        };
    }

    public function build(): array
    {
        $batches = VendorInventory::with(['vendor.vendorProfile', 'fishType'])
            ->where('status', 'confirmed')
            ->whereDate('entry_date', '>=', $this->start->toDateString())
            ->whereDate('entry_date', '<=', $this->end->toDateString())
            ->get();

        // Pull-outs by when they happened, whichever day the batch came in.
        $pullOuts = BatchRelease::with(['batch.fishType', 'vendor.vendorProfile'])
            ->where('kind', BatchRelease::PULLED_OUT)
            ->whereBetween('created_at', [$this->start, $this->end])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return [
            'totals' => $this->totals($batches) + [
                'pulled_out_kg' => round((float) $pullOuts->sum('kg'), 2),
                'pull_outs' => $pullOuts->count(),
            ],
            'byFish' => $this->byFish($batches),
            'breakdown' => $this->breakdown($batches),
            'pullOuts' => $this->pullOuts($pullOuts),
        ];
    }

    private function totals(Collection $batches): array
    {
        $kg = (float) $batches->sum('stock_kg');

        return [
            'supply_kg' => round($kg, 2),
            'batches' => $batches->count(),
            'vendors' => $batches->pluck('vendor_id')->unique()->count(),
            'fish_types' => $batches->pluck('fish_type_id')->unique()->count(),
            // Weighted by kilograms, so a 2 kg batch does not move the average
            // as much as a 50 kg one.
            'avg_price' => $kg > 0
                ? round($batches->sum(fn ($b) => (float) $b->stock_kg * (float) $b->price_per_kg) / $kg, 2)
                : 0.0,
        ];
    }

    private function byFish(Collection $batches): Collection
    {
        return $batches
            ->groupBy(fn ($b) => $b->fish_type_id.'|'.$b->quality_class)
            ->map(function (Collection $group) {
                $kg = (float) $group->sum('stock_kg');

                return [
                    'fish' => $group->first()->fishType?->name ?? 'Unknown',
                    'quality_class' => $group->first()->quality_class,
                    'batches' => $group->count(),
                    'vendors' => $group->pluck('vendor_id')->unique()->count(),
                    'supply_kg' => round($kg, 2),
                    'min_price' => (float) $group->min('price_per_kg'),
                    'max_price' => (float) $group->max('price_per_kg'),
                    'avg_price' => $kg > 0
                        ? round($group->sum(fn ($b) => (float) $b->stock_kg * (float) $b->price_per_kg) / $kg, 2)
                        : 0.0,
                ];
            })
            ->sortBy([['fish', 'asc'], ['quality_class', 'asc']])
            ->values();
    }

    private function pullOuts(Collection $releases): Collection
    {
        return $releases->map(fn (BatchRelease $r) => [
            'when' => $r->created_at->format($this->period === 'daily' ? 'g:i A' : 'M j, g:i A'),
            'vendor' => $r->vendor?->name ?? 'Unknown vendor',
            'stall' => $r->vendor?->vendorProfile?->stall_number,
            'fish' => $r->batch?->fishType?->name ?? 'Unknown',
            'quality_class' => $r->batch?->quality_class,
            'batch' => $r->batch?->batchLabel(),
            'kg' => round((float) $r->kg, 2),
            'reason' => $r->reason,
        ])->values();
    }

    private function breakdown(Collection $batches): Collection
    {
        $rows = match ($this->period) {
            'daily' => $batches->groupBy('vendor_id')->map(fn ($g) => [
                'label' => $g->first()->vendor?->name ?? 'Unknown vendor',
                'sub' => ($stall = $g->first()->vendor?->vendorProfile?->stall_number) ? 'Stall '.$stall : null,
                'sort' => strtolower($g->first()->vendor?->name ?? ''),
                'group' => $g,
            ]),
            'monthly' => $batches->groupBy(fn ($b) => $b->entry_date->format('Y-m-d'))->map(fn ($g, $day) => [
                'label' => Carbon::parse($day)->format('M j (D)'),
                'sub' => null,
                'sort' => $day,
                'group' => $g,
            ]),
            'yearly' => $batches->groupBy(fn ($b) => $b->entry_date->format('Y-m'))->map(fn ($g, $month) => [
                'label' => Carbon::parse($month.'-01')->format('F'),
                'sub' => null,
                'sort' => $month,
                'group' => $g,
            ]),
        };

        return $rows
            ->sortBy('sort')
            ->map(fn ($row) => [
                'label' => $row['label'],
                'sub' => $row['sub'],
                'batches' => $row['group']->count(),
                'vendors' => $row['group']->pluck('vendor_id')->unique()->count(),
                'fish_types' => $row['group']->pluck('fish_type_id')->unique()->count(),
                'supply_kg' => round((float) $row['group']->sum('stock_kg'), 2),
            ])
            ->values();
    }
}
