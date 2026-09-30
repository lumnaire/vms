<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorInventory extends Model
{
    protected $fillable = [
        'vendor_id',
        'fish_type_id',
        'quality_class',
        'price_per_kg',
        'stock_kg',
        'released_kg',
        'sold_kg',
        'status',
        'confirmed_by',
        'confirmed_at',
        'entry_date',
        'is_locked',
    ];

    protected $casts = [
        'price_per_kg'  => 'decimal:2',
        'stock_kg'      => 'decimal:2',
        'released_kg'   => 'decimal:2',
        'sold_kg'       => 'decimal:2',
        'confirmed_at'  => 'datetime',
        'entry_date'    => 'date',
        'is_locked'     => 'boolean',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function fishType()
    {
        return $this->belongsTo(FishType::class);
    }

    public function confirmedByStaff()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ─── Helper Methods ──────────────────────────────────────────

    public function getRemainingStock(): float
    {
        return max(0, $this->released_kg - $this->sold_kg);
    }

    public function getEstimatedSales(): float
    {
        return $this->sold_kg * $this->price_per_kg;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    // ─── Stock Age ────────────────────────────────────────────────
    //
    // Remaining stock is released minus what the vendor declared sold, so it
    // always reflects the day's own submission rather than a separate tally.

    /** Whether any kg is still unsold. */
    public function hasRemainingStock(): bool
    {
        return $this->getRemainingStock() > 0;
    }

    /**
     * Trading days this entry has been sitting on the stall.
     *
     * Measured from entry_date, the day the vendor declared the stock, rather
     * than created_at — a back-filled entry is as old as the day it claims.
     */
    public function getAgeInDays(): int
    {
        return (int) $this->entry_date->startOfDay()->diffInDays(today()->startOfDay());
    }

    /**
     * Unsold stock that has been held too long to sell fresh.
     *
     * Only confirmed stock counts: pending or rejected entries were never
     * released for sale, so flagging them would cry wolf.
     */
    public function isStale(): bool
    {
        if (! $this->isConfirmed() || ! $this->hasRemainingStock()) {
            return false;
        }

        return $this->getAgeInDays() >= config('inventory.stale_after_days');
    }

    /** Value of the unsold stock, for the loss figure on the stale alert. */
    public function getRemainingStockValue(): float
    {
        return round($this->getRemainingStock() * (float) $this->price_per_kg, 2);
    }
}
