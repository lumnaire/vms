<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * VendorSaleReportItem
 *
 * One line of a VendorSaleReport: the kg the vendor declared sold for one
 * confirmed inventory entry.
 *
 * Fish name, quality class and price are snapshots taken at submission time,
 * not joins to the live records. A sale report is a statement about a trading
 * day that has already closed, so it must keep showing the facts as they stood
 * that day even after a price guide or a fish type's quality class is corrected.
 */
class VendorSaleReportItem extends Model
{
    protected $fillable = [
        'vendor_sale_report_id',
        'vendor_inventory_id',
        'fish_type_id',
        'fish_type_name',
        'quality_class',
        'market_session',
        'price_per_kg',
        'released_kg',
        'total_kg',
        'total_price',
    ];

    protected $casts = [
        'price_per_kg' => 'decimal:2',
        'released_kg' => 'decimal:2',
        'total_kg' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function saleReport()
    {
        return $this->belongsTo(VendorSaleReport::class, 'vendor_sale_report_id');
    }

    public function vendorInventory()
    {
        return $this->belongsTo(VendorInventory::class);
    }

    public function fishType()
    {
        return $this->belongsTo(FishType::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /** Kg released but not declared sold — unsold stock left over from the day. */
    public function getUnsoldKg(): float
    {
        return max(0, (float) $this->released_kg - (float) $this->total_kg);
    }

    /** Whether the day sold out completely. */
    public function isSoldOut(): bool
    {
        return $this->getUnsoldKg() <= 0;
    }

    public function getUnsoldValue(): float
    {
        return round($this->getUnsoldKg() * (float) $this->price_per_kg, 2);
    }
}
