<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;

/** Data for the official entrance forms, independent of the volume of batch history. */
class FishEntranceReport
{
    public function __construct(
        public readonly SupplyReport $range,
        public readonly ?int $vendorId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $input = $request->validate([
            'period' => ['nullable', Rule::in(SupplyReport::PERIODS)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'vendor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'vendor')],
            'preview' => ['nullable', 'boolean'],
        ]);
        $period = $input['period'] ?? 'daily';
        $value = $input[match ($period) {
            'monthly' => 'month',
            'yearly' => 'year',
            default => 'date',
        }] ?? null;

        return new self(SupplyReport::for($period, $value === null ? null : (string) $value),
            isset($input['vendor_id']) ? (int) $input['vendor_id'] : null);
    }

    public function parameters(): array
    {
        // Keep the existing daily download URL compatible with bookmarks.
        $parameters = [match ($this->range->period) {
            'monthly' => 'month',
            'yearly' => 'year',
            default => 'date',
        } => $this->range->value(), 'period' => $this->range->period];

        return $this->vendorId === null ? $parameters : $parameters + ['vendor_id' => $this->vendorId];
    }

    private function batches(): Builder
    {
        return DB::table('vendor_inventories as v')
            ->where('v.status', 'confirmed')
            ->where('v.entry_date', '>=', $this->range->start->toDateString())
            ->where('v.entry_date', '<', $this->range->end->copy()->addDay()->toDateString())
            ->when($this->vendorId !== null, fn (Builder $q) => $q->where('v.vendor_id', $this->vendorId));
    }

    public function totals(): array
    {
        $row = $this->batches()->selectRaw('COALESCE(SUM(v.stock_kg), 0) AS kg, '
            .'COALESCE(SUM(v.stock_kg * v.price_per_kg), 0) AS value, COUNT(*) AS batches, '
            .'COUNT(DISTINCT v.vendor_id) AS vendors, COUNT(DISTINCT v.fish_type_id) AS fish_types')->first();
        $pullOuts = DB::table('batch_releases')->where('kind', 'pulled_out')
            ->whereBetween('created_at', [$this->range->start, $this->range->end])
            ->when($this->vendorId !== null, fn (Builder $q) => $q->where('vendor_id', $this->vendorId))
            ->selectRaw('COALESCE(SUM(kg), 0) AS kg, COUNT(*) AS count')->first();

        return [
            'supply_kg' => round((float) $row->kg, 2),
            'total_price' => round((float) $row->value, 2),
            'batches' => (int) $row->batches,
            'vendors' => (int) $row->vendors,
            'fish_types' => (int) $row->fish_types,
            'avg_price' => $row->kg > 0 ? round((float) $row->value / $row->kg, 2) : 0.0,
            'pulled_out_kg' => round((float) $pullOuts->kg, 2),
            'pull_outs' => (int) $pullOuts->count,
        ];
    }

    /** Only the daily form needs individual batches; keep at most a chunk in memory. */
    public function dailyRows(): LazyCollection
    {
        return $this->batches()->join('users as u', 'u.id', '=', 'v.vendor_id')
            ->leftJoin('fish_types as f', 'f.id', '=', 'v.fish_type_id')
            ->select('v.id', 'v.entry_date', 'v.created_at', 'u.name as vendor', 'v.stock_kg',
                'v.price_per_kg', 'v.quality_class', 'f.name as fish')
            ->orderBy('v.entry_date')->orderBy('v.created_at')->orderBy('v.id')->lazy(200);
    }

    /** One summary per vendor, including a selected vendor who has no entries. */
    public function vendors(): LazyCollection
    {
        $totals = $this->batches()->select('v.vendor_id')->selectRaw('SUM(v.stock_kg) AS kg')->groupBy('v.vendor_id');

        return DB::table('users as u')->leftJoinSub($totals, 't', 't.vendor_id', '=', 'u.id')
            ->where('u.role', 'vendor')
            ->when($this->vendorId !== null,
                fn (Builder $q) => $q->where('u.id', $this->vendorId),
                fn (Builder $q) => $q->whereNotNull('t.vendor_id'))
            ->select('u.id', 'u.name')->selectRaw('COALESCE(t.kg, 0) AS kg')
            ->orderBy('u.name')->orderBy('u.id')->lazy(100);
    }

    /** At most 366 aggregates per vendor, instead of loading a year's Eloquent models. */
    public function yearlyGrid(int $vendorId): array
    {
        $days = $this->batches()->where('v.vendor_id', $vendorId)
            ->selectRaw('DATE(v.entry_date) AS day, SUM(v.stock_kg) AS kg, '
                .'SUM(v.stock_kg * v.price_per_kg) AS price')
            ->groupByRaw('DATE(v.entry_date)')->orderBy('day')->get();
        $grid = ['days' => [], 'months' => []];
        foreach ($days as $row) {
            $month = (int) substr($row->day, 5, 2);
            $day = (int) substr($row->day, 8, 2);
            $grid['days'][$day][$month] = ['kg' => (float) $row->kg, 'price' => (float) $row->price];
            foreach (['kg', 'price'] as $key) {
                $grid['months'][$month][$key] = ($grid['months'][$month][$key] ?? 0) + (float) $row->{$key};
            }
        }

        return $grid;
    }
}
