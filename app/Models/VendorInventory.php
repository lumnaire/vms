<?php

namespace App\Models;

use Carbon\CarbonInterface;
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
        'carried_from_id',
        'carried_out_at',
        'disposed_at',
        'disposed_reason',
    ];

    protected $casts = [
        'price_per_kg'    => 'decimal:2',
        'stock_kg'        => 'decimal:2',
        'released_kg'     => 'decimal:2',
        'sold_kg'         => 'decimal:2',
        'confirmed_at'    => 'datetime',
        'entry_date'      => 'date',
        'is_locked'       => 'boolean',
        'carried_out_at'  => 'datetime',
        'disposed_at'     => 'datetime',
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

    /** The entry this one's stock was carried forward from, if it is a resubmission. */
    public function carriedFrom()
    {
        return $this->belongsTo(self::class, 'carried_from_id');
    }

    /** The entry this one's leftover stock was handed to, once it has been. */
    public function carriedTo()
    {
        return $this->hasOne(self::class, 'carried_from_id');
    }

    // ─── Helper Methods ──────────────────────────────────────────

    public function getRemainingStock(): float
    {
        // Stock handed to another entry, or written off, has left this line even
        // though released_kg and sold_kg still describe the day it was declared on.
        // Counting it here as well would put the same kilograms on two lines and
        // double every remaining-stock total the moment a vendor resubmits.
        if ($this->isCarriedOut() || $this->isDisposed()) {
            return 0.0;
        }

        return max(0, (float) $this->released_kg - (float) $this->sold_kg);
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
     *
     * Carrying stock forward resets the clock, because the replacement entry is
     * dated the day it is resubmitted. That is deliberate: the fish is fresh
     * again in the vendor's hands at that point, and it is the resubmission that
     * makes it sellable, so the countdown tracks the current claim on the stock
     * rather than the original one.
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

    /** Days of freshness left before this stock stops being sellable. Zero once stale. */
    public function getDaysUntilStale(): int
    {
        return max(0, (int) config('inventory.stale_after_days') - $this->getAgeInDays());
    }

    /** Value of the unsold stock, for the loss figure on the stale alert. */
    public function getRemainingStockValue(): float
    {
        return round($this->getRemainingStock() * (float) $this->price_per_kg, 2);
    }

    // ─── Stock Lifecycle ──────────────────────────────────────────
    //
    // A line of stock ends in exactly one of four ways, and My Stock reads that
    // state straight off the row rather than inferring it from four separate
    // figures a page could contradict itself about.

    public const STATE_OPEN     = 'open';
    public const STATE_SOLD_OUT = 'sold_out';
    public const STATE_CARRIED  = 'carried';
    public const STATE_DISPOSED = 'disposed';
    public const STATE_STALE    = 'stale';

    /** Every kg released was declared sold — the fish was bought completely. */
    public function isSoldThrough(): bool
    {
        return (float) $this->released_kg > 0
            && (float) $this->sold_kg >= (float) $this->released_kg;
    }

    /** The leftover stock has been resubmitted on another day. */
    public function isCarriedOut(): bool
    {
        return $this->carried_out_at !== null;
    }

    /** The vendor reported the stock as unsellable and off the stall. */
    public function isDisposed(): bool
    {
        return $this->disposed_at !== null;
    }

    /**
     * Where this line of stock stands. Ordered so the endings win: a line that was
     * carried forward and then sold through on its new day reads as carried, which
     * is the fact the vendor needs — this is where those kilograms went.
     */
    public function getStockState(): string
    {
        if ($this->isDisposed()) {
            return self::STATE_DISPOSED;
        }

        if ($this->isCarriedOut()) {
            return self::STATE_CARRIED;
        }

        if ($this->isSoldThrough()) {
            return self::STATE_SOLD_OUT;
        }

        return $this->isStale() ? self::STATE_STALE : self::STATE_OPEN;
    }

    /**
     * Why this stock cannot be resubmitted, or null when it can.
     *
     * A single reason rather than a boolean, because the vendor needs to know
     * which one applies and every blocked row has a different remedy: wait for
     * staff, nothing is left, it is already on another day, or it has to be
     * written off. CarryForwardStock and the My Stock page both render this.
     *
     * $on is the trading day the stock would be resubmitted onto. It defaults to
     * today because that is what the My Stock button does; the sale report passes
     * the day the vendor picked, which is normally not today.
     */
    public function resubmitBlocker(?CarbonInterface $on = null): ?string
    {
        if ($this->isDisposed()) {
            return 'Reported as written off.';
        }

        if ($this->isCarriedOut()) {
            $carriedOn = $this->carried_out_at?->format('M j, Y');

            return 'Handed over to another day' . ($carriedOn ? " on {$carriedOn}" : '') . '.';
        }

        if (! $this->isConfirmed()) {
            return 'Not confirmed by staff yet.';
        }

        if (! $this->hasRemainingStock()) {
            return 'Nothing left to resubmit.';
        }

        if ($this->isStale()) {
            return $this->getAgeInDays() . ' days old — too old to sell. Report it as written off.';
        }

        // Already sitting on the day it would be resubmitted onto.
        //
        // A resubmission creates a new entry rather than re-dating this one, so
        // resubmitting stock that is already dated on that day does not make it
        // sellable — it is sellable already — it just writes a second entry for
        // the same kilograms on the same day. Staff would confirm that as fresh
        // stock and the fish would be counted twice.
        $on ??= today();

        if ($this->entry_date->isSameDay($on)) {
            return 'Already on ' . $on->format('M j') . " — declare it in that day's sale report.";
        }

        return null;
    }

    public function canResubmit(?CarbonInterface $on = null): bool
    {
        return $this->resubmitBlocker($on) === null;
    }
}
