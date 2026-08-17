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
}