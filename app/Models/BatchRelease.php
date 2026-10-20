<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One release from a batch: kilograms the vendor took off its remaining stock,
 * either sold or pulled out without being sold.
 */
class BatchRelease extends Model
{
    public const SOLD = 'sold';

    public const PULLED_OUT = 'pulled_out';

    public const KINDS = [self::SOLD, self::PULLED_OUT];

    protected $fillable = [
        'vendor_inventory_id',
        'vendor_id',
        'kind',
        'kg',
        'reason',
    ];

    protected $casts = [
        'kg' => 'decimal:2',
    ];

    public function batch()
    {
        return $this->belongsTo(VendorInventory::class, 'vendor_inventory_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function isPulledOut(): bool
    {
        return $this->kind === self::PULLED_OUT;
    }

    public static function kindLabel(string $kind): string
    {
        return $kind === self::PULLED_OUT ? 'Pulled out (not sold)' : 'Sold';
    }

    /**
     * Add the releases missing behind a batch's sold_kg / pulled_out_kg totals.
     *
     * Sales charts read releases, dated by when they were recorded. Batches from
     * before releases existed, and the demo seeders, only carry the totals, so the
     * difference is added as one release of each kind dated on the batch's own day.
     * Safe to run again: a batch whose releases already add up gets nothing.
     *
     * Returns how many releases were added.
     */
    public static function backfillFromTotals(): int
    {
        $recorded = DB::table('batch_releases')
            ->selectRaw("vendor_inventory_id, SUM(CASE WHEN kind = 'sold' THEN kg ELSE 0 END) AS sold, SUM(CASE WHEN kind = 'pulled_out' THEN kg ELSE 0 END) AS pulled")
            ->groupBy('vendor_inventory_id')
            ->get()
            ->keyBy('vendor_inventory_id');

        $added = 0;

        DB::table('vendor_inventories')
            ->where('status', 'confirmed')
            ->where(fn ($q) => $q->where('sold_kg', '>', 0)->orWhere('pulled_out_kg', '>', 0))
            ->select('id', 'vendor_id', 'entry_date', 'sold_kg', 'pulled_out_kg')
            ->orderBy('id')
            ->chunk(1000, function ($batches) use ($recorded, &$added) {
                $rows = [];

                foreach ($batches as $batch) {
                    $have = $recorded->get($batch->id);
                    $at = Carbon::parse($batch->entry_date)->setTime(12, 0);

                    foreach ([self::SOLD => ['sold_kg', 'sold'], self::PULLED_OUT => ['pulled_out_kg', 'pulled']] as $kind => [$column, $field]) {
                        $gap = round((float) $batch->{$column} - (float) ($have->{$field} ?? 0), 2);

                        if ($gap >= 0.01) {
                            $rows[] = [
                                'vendor_inventory_id' => $batch->id,
                                'vendor_id' => $batch->vendor_id,
                                'kind' => $kind,
                                'kg' => $gap,
                                'reason' => null,
                                'created_at' => $at,
                                'updated_at' => $at,
                            ];
                        }
                    }
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('batch_releases')->insert($chunk);
                }

                $added += count($rows);
            });

        return $added;
    }
}
