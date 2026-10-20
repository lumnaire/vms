<?php

namespace App\Services;

use App\Models\BatchRelease;
use App\Models\FishType;
use App\Models\PriceGuide;
use App\Models\User;
use App\Models\VendorInventory;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures behind the supervisor, staff and vendor dashboards and the tariff
 * comparison tables.
 *
 * Every chart and card reads from here so the same number is the same
 * everywhere. Definitions:
 *
 *   supply   — kilograms of confirmed batches, dated by the batch's entry_date.
 *   sold     — kilograms released as sold (batch_releases), dated by when the
 *              vendor recorded the release.
 *   sales    — sold kilograms × that batch's price.
 *   tubo     — sold kilograms × (batch price − the tariff in force when the batch
 *              was submitted). Batches of a fish with no tariff then count 0.
 *   markup   — batch price − its tariff, per kilo.
 *
 * Sums are done in SQL per day and bucketed in PHP, so the same daily totals
 * feed the daily, monthly, quarterly and yearly views.
 */
class MarketAnalytics
{
    /** Period toggle on the dashboards: key => [label, window caption]. */
    public const PERIODS = [
        'daily' => ['Daily', 'Last 30 days'],
        'monthly' => ['Monthly', 'Last 12 months'],
        'quarterly' => ['Quarterly', 'Last 8 quarters'],
        'yearly' => ['Yearly', 'Last 5 years'],
    ];

    /** Chart colours: validated categorical order, and the stock-state status colours. */
    public const CLASS_COLORS = [
        'First Class' => '#2563eb',
        'Second Class' => '#059669',
        'Third Class' => '#d97706',
        'Fourth Class' => '#7e22ce',
        'Special Class' => '#dc2626',
    ];

    // ─── Daily totals ────────────────────────────────────────────

    /** @return array<string, float> date => supplied kg */
    public function dailySupply(?int $vendorId = null): array
    {
        return DB::table('vendor_inventories')
            ->where('status', 'confirmed')
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->selectRaw('DATE(entry_date) AS d, SUM(stock_kg) AS kg')
            ->groupByRaw('DATE(entry_date)')
            ->pluck('kg', 'd')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /** @return array<string, array{kg: float, value: float, tubo: float}> date => sales */
    public function dailySales(?int $vendorId = null): array
    {
        return $this->releases(BatchRelease::SOLD, $vendorId)
            ->selectRaw('DATE(r.created_at) AS d, SUM(r.kg) AS kg, SUM(r.kg * v.price_per_kg) AS value, '
                .'SUM(CASE WHEN v.tariff_price IS NULL THEN 0 ELSE r.kg * (v.price_per_kg - v.tariff_price) END) AS tubo')
            ->groupByRaw('DATE(r.created_at)')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->d => ['kg' => (float) $r->kg, 'value' => (float) $r->value, 'tubo' => (float) $r->tubo]])
            ->all();
    }

    private function releases(string $kind, ?int $vendorId = null)
    {
        return DB::table('batch_releases as r')
            ->join('vendor_inventories as v', 'v.id', '=', 'r.vendor_inventory_id')
            ->where('r.kind', $kind)
            ->when($vendorId, fn ($q) => $q->where('r.vendor_id', $vendorId));
    }

    // ─── Periods ─────────────────────────────────────────────────

    /** First day of the window a period's chart covers. */
    public function windowStart(string $period): Carbon
    {
        return match ($period) {
            'daily' => today()->subDays(29),
            'monthly' => today()->startOfMonth()->subMonths(11),
            'quarterly' => today()->firstOfQuarter()->subQuarters(7),
            default => today()->startOfYear()->subYears(4),
        };
    }

    private function bucketKey(CarbonInterface $date, string $period): string
    {
        return match ($period) {
            'daily' => $date->format('Y-m-d'),
            'monthly' => $date->format('Y-m'),
            'quarterly' => $date->year.'-Q'.$date->quarter,
            default => (string) $date->year,
        };
    }

    /** @return array<string, string> bucket key => label, oldest first */
    public function buckets(string $period): array
    {
        $buckets = [];
        $cursor = $this->windowStart($period);

        while ($cursor->lte(today())) {
            $buckets[$this->bucketKey($cursor, $period)] = match ($period) {
                'daily' => $cursor->format('M j'),
                'monthly' => $cursor->format('M Y'),
                'quarterly' => 'Q'.$cursor->quarter.' '.$cursor->year,
                default => (string) $cursor->year,
            };

            $cursor = match ($period) {
                'daily' => $cursor->copy()->addDay(),
                'monthly' => $cursor->copy()->addMonth(),
                'quarterly' => $cursor->copy()->addQuarter(),
                default => $cursor->copy()->addYear(),
            };
        }

        return $buckets;
    }

    /**
     * Sum a date-keyed map into a period's buckets.
     *
     * @param  array<string, mixed>  $daily
     * @param  callable|null  $pick  value from each day's entry (default: the entry itself)
     * @return array<int, float> one value per bucket, oldest first
     */
    public function bucketize(array $daily, string $period, ?callable $pick = null): array
    {
        $buckets = array_fill_keys(array_keys($this->buckets($period)), 0.0);
        $start = $this->windowStart($period)->toDateString();

        foreach ($daily as $date => $entry) {
            if ($date < $start || $date > today()->toDateString()) {
                continue;
            }
            $key = $this->bucketKey(Carbon::parse($date), $period);
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += (float) ($pick ? $pick($entry) : $entry);
            }
        }

        return array_map(fn ($v) => round($v, 2), array_values($buckets));
    }

    /** Sum of a date-keyed map between two dates, inclusive. */
    public function sumBetween(array $daily, CarbonInterface $from, CarbonInterface $to, ?callable $pick = null): float
    {
        $total = 0.0;
        foreach ($daily as $date => $entry) {
            if ($date >= $from->toDateString() && $date <= $to->toDateString()) {
                $total += (float) ($pick ? $pick($entry) : $entry);
            }
        }

        return round($total, 2);
    }

    /**
     * This month so far against the same days of last month.
     *
     * @return array{current: float, previous: float, change: float|null}
     */
    public function monthToDate(array $daily, ?callable $pick = null): array
    {
        $current = $this->sumBetween($daily, today()->startOfMonth(), today(), $pick);
        $prevStart = today()->startOfMonth()->subMonthNoOverflow();
        $prevEnd = $prevStart->copy()->addDays(min(today()->day, $prevStart->daysInMonth) - 1);
        $previous = $this->sumBetween($daily, $prevStart, $prevEnd, $pick);

        return ['current' => $current, 'previous' => $previous, 'change' => $this->change($current, $previous)];
    }

    public function change(float $current, float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    /** The highest day of a date-keyed map. @return array{date: Carbon, value: float}|null */
    public function bestDay(array $daily, ?callable $pick = null): ?array
    {
        $best = null;
        foreach ($daily as $date => $entry) {
            $value = (float) ($pick ? $pick($entry) : $entry);
            if ($value > 0 && ($best === null || $value > $best['value'])) {
                $best = ['date' => Carbon::parse($date), 'value' => round($value, 2)];
            }
        }

        return $best;
    }

    // ─── Rankings and splits per window ──────────────────────────

    /** Top vendors by supplied kg since $from. */
    public function topSuppliers(CarbonInterface $from, int $limit = 5): Collection
    {
        $rows = DB::table('vendor_inventories')
            ->where('status', 'confirmed')
            ->whereDate('entry_date', '>=', $from->toDateString())
            ->selectRaw('vendor_id, SUM(stock_kg) AS kg')
            ->groupBy('vendor_id')
            ->orderByDesc('kg')
            ->limit($limit)
            ->get();

        $users = User::with('vendorProfile')->whereIn('id', $rows->pluck('vendor_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'name' => $users[$r->vendor_id]?->name ?? 'Vendor #'.$r->vendor_id,
            'stall' => $users[$r->vendor_id]?->vendorProfile?->stall_number,
            'kg' => round((float) $r->kg, 2),
        ]);
    }

    /** Supplied kg per quality class since $from, in class order. @return array<string, float> */
    public function supplyByClass(CarbonInterface $from, ?int $vendorId = null): array
    {
        $kg = DB::table('vendor_inventories')
            ->where('status', 'confirmed')
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->whereDate('entry_date', '>=', $from->toDateString())
            ->selectRaw('quality_class, SUM(stock_kg) AS kg')
            ->groupBy('quality_class')
            ->pluck('kg', 'quality_class');

        return collect(FishType::QUALITY_CLASSES)
            ->mapWithKeys(fn ($c) => [$c => round((float) ($kg[$c] ?? 0), 2)])
            ->all();
    }

    /**
     * What happened to the stock of batches submitted since $from: sold, pulled
     * out, expired (left on the batch when it reached the freshness limit) and
     * still on the stall. Sell-through is sold over the stock that has left.
     */
    public function stockOutcome(CarbonInterface $from, ?int $vendorId = null): array
    {
        $r = DB::table('vendor_inventories')
            ->where('status', 'confirmed')
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->whereDate('entry_date', '>=', $from->toDateString())
            ->selectRaw('SUM(released_kg) AS released, SUM(sold_kg) AS sold, SUM(pulled_out_kg) AS pulled, '
                .'SUM(CASE WHEN disposed_at IS NOT NULL OR carried_out_at IS NOT NULL THEN released_kg - sold_kg - pulled_out_kg ELSE 0 END) AS expired')
            ->first();

        $sold = round((float) $r->sold, 2);
        $pulled = round((float) $r->pulled, 2);
        $expired = round(max(0, (float) $r->expired), 2);
        $onStall = round(max(0, (float) $r->released - $sold - $pulled - $expired), 2);
        $gone = $sold + $pulled + $expired;

        return [
            'sold' => $sold,
            'pulled_out' => $pulled,
            'expired' => $expired,
            'on_stall' => $onStall,
            'sell_through' => $gone > 0 ? round($sold / $gone * 100, 1) : null,
        ];
    }

    /**
     * Vendors ranked by markup over the tariff since $from.
     *
     * Per vendor: batches priced against a tariff, how many were above it, the
     * kilogram-weighted average markup (₱/kg and %), the largest markup, and the
     * tubo earned on what was sold. The staff view leaves tubo out (no sales).
     */
    public function tuboLeaderboard(CarbonInterface $from, int $limit = 10, bool $withSales = true): Collection
    {
        $markup = DB::table('vendor_inventories')
            ->where('status', 'confirmed')
            ->whereNotNull('tariff_price')
            ->where('tariff_price', '>', 0)
            ->whereDate('entry_date', '>=', $from->toDateString())
            ->selectRaw('vendor_id, COUNT(*) AS batches, '
                .'SUM(CASE WHEN price_per_kg > tariff_price THEN 1 ELSE 0 END) AS above, '
                .'SUM(stock_kg) AS kg, '
                .'SUM((price_per_kg - tariff_price) * stock_kg) AS markup_kg, '
                .'SUM((price_per_kg - tariff_price) / tariff_price * stock_kg) AS pct_kg, '
                .'MAX(price_per_kg - tariff_price) AS max_markup')
            ->groupBy('vendor_id')
            ->get()
            ->keyBy('vendor_id');

        $tubo = $withSales
            ? $this->releases(BatchRelease::SOLD)
                ->whereNotNull('v.tariff_price')
                ->where('r.created_at', '>=', $from->copy()->startOfDay())
                ->selectRaw('r.vendor_id, SUM(r.kg * (v.price_per_kg - v.tariff_price)) AS tubo')
                ->groupBy('r.vendor_id')
                ->pluck('tubo', 'r.vendor_id')
            : collect();

        $users = User::with('vendorProfile')->whereIn('id', $markup->keys())->get()->keyBy('id');

        return $markup->map(function ($m, $vendorId) use ($users, $tubo, $withSales) {
            $kg = (float) $m->kg;

            return [
                'vendor_id' => $vendorId,
                'name' => $users[$vendorId]?->name ?? 'Vendor #'.$vendorId,
                'stall' => $users[$vendorId]?->vendorProfile?->stall_number,
                'batches' => (int) $m->batches,
                'above' => (int) $m->above,
                'avg_markup' => $kg > 0 ? round((float) $m->markup_kg / $kg, 2) : 0.0,
                'avg_pct' => $kg > 0 ? round((float) $m->pct_kg / $kg * 100, 1) : 0.0,
                'max_markup' => round((float) $m->max_markup, 2),
                'tubo' => $withSales ? round((float) ($tubo[$vendorId] ?? 0), 2) : null,
            ];
        })
            ->sortByDesc(fn ($v) => $withSales ? [$v['tubo'], $v['avg_markup']] : [$v['avg_markup'], $v['max_markup']])
            ->take($limit)
            ->values();
    }

    // ─── All-time records ────────────────────────────────────────

    /** The highest confirmed price ever recorded, with fish, stall and date. */
    public function highestPriceEver(?int $vendorId = null): ?VendorInventory
    {
        return VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->where('status', 'confirmed')
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->orderByDesc('price_per_kg')
            ->orderBy('entry_date')
            ->first();
    }

    /** The largest markup over the tariff ever recorded. */
    public function highestMarkupEver(): ?VendorInventory
    {
        return VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->where('status', 'confirmed')
            ->whereNotNull('tariff_price')
            ->whereColumn('price_per_kg', '>', 'tariff_price')
            ->orderByRaw('price_per_kg - tariff_price DESC')
            ->first();
    }

    // ─── The board right now, against the tariff ─────────────────

    /**
     * Every fish on the public board now, against its current tariff: lowest,
     * highest and average vendor price, the stalls selling it, and the stall with
     * the largest markup. Keyed by PriceGuide::key().
     */
    public function boardByFish(): Collection
    {
        $tariffs = PriceGuide::tariffMap();

        return VendorInventory::with(['fishType', 'vendor.vendorProfile'])
            ->open()
            ->get()
            ->groupBy(fn ($e) => PriceGuide::key($e->fish_type_id, $e->quality_class))
            ->map(function ($lines, $key) use ($tariffs) {
                $tariff = $tariffs[$key] ?? null;
                $first = $lines->first();

                // One entry per stall: its highest price for the fish.
                $stalls = $lines->groupBy('vendor_id')->map(function ($vendorLines) use ($tariff) {
                    $price = (float) $vendorLines->max('price_per_kg');
                    $vendor = $vendorLines->first()->vendor;

                    return [
                        'vendor_id' => $vendor?->id,
                        'name' => $vendor?->name ?? '—',
                        'stall' => $vendor?->vendorProfile?->stall_number ?? '—',
                        'price' => $price,
                        'comparison' => PriceGuide::compare($price, $tariff),
                        'note' => $vendorLines->pluck('price_note')->filter()->last(),
                    ];
                })->sortByDesc('price')->values();

                $top = $stalls->first();

                return [
                    'key' => $key,
                    'fish_type_id' => $first->fish_type_id,
                    'fish_name' => $first->fishType?->name ?? '—',
                    'english_name' => $first->fishType?->english_name,
                    'quality_class' => $first->quality_class,
                    'tariff' => $tariff,
                    'min' => (float) $lines->min('price_per_kg'),
                    'max' => (float) $lines->max('price_per_kg'),
                    'avg' => round((float) $lines->avg('price_per_kg'), 2),
                    'remaining_kg' => round($lines->sum(fn ($e) => $e->getRemainingStock()), 2),
                    'stalls' => $stalls,
                    // Only a stall actually above the tariff is "the highest markup".
                    'top_markup' => $top && ($top['comparison']['state'] ?? null) === 'above' ? $top : null,
                ];
            });
    }

    // ─── Chart payloads ──────────────────────────────────────────

    /**
     * Labels and values for every period, so the page can switch periods without
     * a reload.
     *
     * @param  array<string, array<string, mixed>>  $series  name => [daily map, picker|null]
     * @return array<string, array{labels: array, caption: string, series: array<string, array>}>
     */
    public function periodPayload(array $series): array
    {
        $out = [];
        foreach (array_keys(self::PERIODS) as $period) {
            $out[$period] = [
                'labels' => array_values($this->buckets($period)),
                'caption' => self::PERIODS[$period][1],
                'series' => collect($series)->map(fn ($s) => $this->bucketize($s[0], $period, $s[1] ?? null))->all(),
            ];
        }

        return $out;
    }
}
