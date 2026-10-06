<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
