<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FishType extends Model
{
    public const QUALITY_CLASSES = [
        'First Class',
        'Second Class',
        'Third Class',
        'Fourth Class',
        'Special Class',
    ];

    protected $fillable = [
        'name',
        'english_name',
        'quality_class',
        'is_active',
        'image_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function priceGuides()
    {
        return $this->hasMany(PriceGuide::class);
    }

    /** The tariff version in force, if the supervisor has set one. */
    public function currentTariff()
    {
        return $this->hasOne(PriceGuide::class)->where('is_active', true)->whereNotNull('tariff_price')->latestOfMany();
    }

    /** Every tariff this fish has had, newest first. */
    public function tariffHistory()
    {
        return $this->hasMany(PriceGuide::class)->whereNotNull('tariff_price')->latest('id');
    }

    public function vendorInventories()
    {
        return $this->hasMany(VendorInventory::class);
    }

    public function forecasts()
    {
        return $this->hasMany(Forecast::class);
    }

    // ─── Catalogue upkeep ────────────────────────────────────────

    /**
     * Title-cases a fish type name and squeezes internal whitespace, so that
     * cosmetic variations cannot slip past the `unique` check and become two
     * near-identical rows. Str::title() also capitalises after "(", which
     * ucwords() does not.
     */
    public static function normaliseName(?string $name): string
    {
        return Str::title(preg_replace('/\s+/u', ' ', trim((string) $name)));
    }

    /**
     * Move the fish to another quality class. Its tariff versions and batches
     * carry the class too, and the vendor price check, staff confirmation and
     * board are all keyed on "<fish_type_id>_<quality class>", so they move with
     * it or the tariff would be orphaned.
     */
    public function changeClass(string $qualityClass): void
    {
        if ($this->quality_class === $qualityClass) {
            return;
        }

        DB::transaction(function () use ($qualityClass) {
            $this->update(['quality_class' => $qualityClass]);
            PriceGuide::where('fish_type_id', $this->id)->update(['quality_class' => $qualityClass]);
            VendorInventory::where('fish_type_id', $this->id)->update(['quality_class' => $qualityClass]);
        });
    }

    /** Batches waiting for staff or still on sale: the fish cannot be removed while any exist. */
    public function liveBatchCount(): int
    {
        return $this->vendorInventories()
            ->where(fn (Builder $q) => $q->where('status', 'pending')->orWhere(fn (Builder $q) => $q->open()))
            ->count();
    }

    /**
     * Take the fish off the catalogue. One that was never batched is deleted
     * outright (its tariffs and forecasts go with it). One with batches is only
     * hidden: those batches feed reports and forecast training, so the fish
     * stays in the database and can be restored.
     *
     * Returns 'deleted' or 'hidden'.
     */
    public function removeFromCatalogue(): string
    {
        if ($this->vendorInventories()->doesntExist()) {
            DB::transaction(function () {
                $this->priceGuides()->delete();
                $this->forecasts()->delete();
                $this->delete();
            });

            return 'deleted';
        }

        $this->update(['is_active' => false]);

        return 'hidden';
    }
}
