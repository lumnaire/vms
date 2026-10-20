<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One version of a fish's official tariff (taripa): the standard price per kilo
 * set by the supervisor from the municipal tariff list.
 *
 * A tariff is never edited in place. Setting a new price switches the current
 * version off and adds a new one (see setTariff()), so the rows of a fish read
 * as its tariff history. The active row with a tariff_price is the one in force.
 *
 * cheap_max / moderate_max belong to the retired Cheap/Moderate/Expensive
 * brackets and are kept only on old rows.
 */
class PriceGuide extends Model
{
    protected $fillable = [
        'fish_type_id',
        'quality_class',
        'tariff_price',
        'cheap_max',
        'moderate_max',
        'effective_date',
        'is_active',
        'set_by',
    ];

    protected $casts = [
        'tariff_price' => 'decimal:2',
        'cheap_max' => 'decimal:2',
        'moderate_max' => 'decimal:2',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function fishType()
    {
        return $this->belongsTo(FishType::class);
    }

    public function setBy()
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    /** The tariff versions in force: one per fish. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('tariff_price');
    }

    /** Every tariff a fish has had, legacy brackets excluded. */
    public function scopeTariffHistory(Builder $query): Builder
    {
        return $query->whereNotNull('tariff_price');
    }

    /** The key every page uses to match a tariff to a batch: "<fish_type_id>_<quality class>". */
    public static function key(int $fishTypeId, ?string $qualityClass): string
    {
        return $fishTypeId.'_'.$qualityClass;
    }

    /**
     * Current tariffs keyed by key(), as floats.
     *
     * @return Collection<string, float>
     */
    public static function tariffMap(): Collection
    {
        return static::current()
            ->get(['fish_type_id', 'quality_class', 'tariff_price'])
            ->mapWithKeys(fn ($g) => [static::key($g->fish_type_id, $g->quality_class) => (float) $g->tariff_price]);
    }

    public static function tariffFor(int $fishTypeId, ?string $qualityClass): ?float
    {
        $price = static::current()
            ->where('fish_type_id', $fishTypeId)
            ->where('quality_class', $qualityClass)
            ->latest('id')
            ->value('tariff_price');

        return $price === null ? null : (float) $price;
    }

    /**
     * Put a new tariff in force for a fish, keeping the previous one as history.
     * Returns null when the price is unchanged.
     */
    public static function setTariff(FishType $fish, float $price, string $effectiveDate, ?int $userId): ?self
    {
        $price = round($price, 2);
        $current = static::current()->where('fish_type_id', $fish->id)->latest('id')->first();

        if ($current && abs((float) $current->tariff_price - $price) < 0.005) {
            return null;
        }

        return DB::transaction(function () use ($fish, $price, $effectiveDate, $userId) {
            static::where('fish_type_id', $fish->id)->where('is_active', true)->update(['is_active' => false]);

            return static::create([
                'fish_type_id' => $fish->id,
                'quality_class' => $fish->quality_class,
                'tariff_price' => $price,
                'effective_date' => $effectiveDate,
                'is_active' => true,
                'set_by' => $userId,
            ]);
        });
    }

    /**
     * How a price compares with a tariff, for the badges on every page:
     * markup in pesos and percent, and a tone. Null when there is no tariff.
     *
     * @return array{tariff: float, diff: float, pct: float, state: string}|null
     */
    public static function compare(float $price, ?float $tariff): ?array
    {
        if ($tariff === null || $tariff <= 0) {
            return null;
        }

        $diff = round($price - $tariff, 2);

        return [
            'tariff' => $tariff,
            'diff' => $diff,
            'pct' => round($diff / $tariff * 100, 1),
            'state' => $diff > 0.004 ? 'above' : ($diff < -0.004 ? 'below' : 'at'),
        ];
    }
}
