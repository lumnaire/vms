<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * One batch of fish a vendor brought to the stall.
 *
 * Every submission is a batch. Submitting the same fish and quality class again on
 * the same day is the next batch (Batch 1, Batch 2, …), each with its own price,
 * stock and age. A batch stays on sale across days until it is sold out or passes
 * the freshness window, after which the vendor writes it off.
 *
 * Stock and what is for sale are the same figure: released_kg is set to stock_kg
 * on submission. sold_kg is what the vendor has recorded as sold through Release,
 * so remaining stock is always released − sold.
 */
class VendorInventory extends Model
{
    protected $fillable = [
        'vendor_id',
        'fish_type_id',
        'quality_class',
        'batch_no',
        'price_per_kg',
        'stock_kg',
        'released_kg',
        'sold_kg',
        'status',
        'confirmed_by',
        'confirmed_at',
        'entry_date',
        'is_locked',
        'disposed_at',
        'disposed_reason',
    ];

    protected $casts = [
        'batch_no' => 'integer',
        'price_per_kg' => 'decimal:2',
        'stock_kg' => 'decimal:2',
        'released_kg' => 'decimal:2',
        'sold_kg' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'entry_date' => 'date',
        'is_locked' => 'boolean',
        'carried_out_at' => 'datetime',
        'disposed_at' => 'datetime',
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

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Confirmed batches that still have fish left: what the vendor is selling.
     *
     * Includes stale batches, which are still on the vendor's stall until they are
     * written off; the public board filters those out separately.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'confirmed')
            ->whereNull('disposed_at')
            // Legacy: stock handed to another day before carrying was removed.
            ->whereNull('carried_out_at')
            ->whereColumn('sold_kg', '<', 'released_kg');
    }

    // ─── Batches ─────────────────────────────────────────────────

    /** The number the next submission of this fish gets on that day. */
    public static function nextBatchNo(int $vendorId, int $fishTypeId, string $qualityClass, CarbonInterface $date): int
    {
        return (int) static::where('vendor_id', $vendorId)
            ->where('fish_type_id', $fishTypeId)
            ->where('quality_class', $qualityClass)
            ->whereDate('entry_date', $date->toDateString())
            ->max('batch_no') + 1;
    }

    public function batchLabel(): string
    {
        return 'Batch '.max(1, (int) $this->batch_no);
    }

    /**
     * Lines sharing this key are the same vendor selling the same fish in the same
     * quality class. Staff see them grouped, and the board lists them as batches
     * under one fish with a combined total.
     */
    public function repeatKey(): string
    {
        return $this->vendor_id.'_'.$this->fish_type_id.'_'.$this->quality_class;
    }

    // ─── Status ──────────────────────────────────────────────────

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

    /** Stock handed to another day under the old carry-forward. No longer created. */
    public function isCarriedOut(): bool
    {
        return $this->carried_out_at !== null;
    }

    /** The vendor wrote the batch off as unsellable. */
    public function isDisposed(): bool
    {
        return $this->disposed_at !== null;
    }

    // ─── Stock ───────────────────────────────────────────────────

    public function getRemainingStock(): float
    {
        // Written-off (or legacy carried) stock has left the stall even though
        // released_kg and sold_kg still describe the batch as it was.
        if ($this->isCarriedOut() || $this->isDisposed()) {
            return 0.0;
        }

        return round(max(0, (float) $this->released_kg - (float) $this->sold_kg), 2);
    }

    public function hasRemainingStock(): bool
    {
        return $this->getRemainingStock() > 0;
    }

    public function getSoldKg(): float
    {
        return round((float) $this->sold_kg, 2);
    }

    public function getRemainingStockValue(): float
    {
        return round($this->getRemainingStock() * (float) $this->price_per_kg, 2);
    }

    public function isSoldOut(): bool
    {
        return (float) $this->released_kg > 0 && (float) $this->sold_kg >= (float) $this->released_kg;
    }

    /** Confirmed with fish left: on the vendor's stall. */
    public function isOpen(): bool
    {
        return $this->isConfirmed() && $this->hasRemainingStock();
    }

    /** On the public board: open and still fresh. */
    public function isOnBoard(): bool
    {
        return $this->isOpen() && ! $this->isStale();
    }

    // ─── Freshness ───────────────────────────────────────────────
    //
    // A batch is measured from entry_date, the day it was submitted. It can be
    // sold for `inventory.stale_after_days` days; on that day it turns stale,
    // leaves the public board, and the vendor writes it off.

    public static function freshnessDays(): int
    {
        return max(1, (int) config('inventory.stale_after_days'));
    }

    public function getAgeInDays(): int
    {
        return (int) $this->entry_date->copy()->startOfDay()->diffInDays(today()->startOfDay());
    }

    /** "1d / 3d": days on the stall out of the days it can be sold. */
    public function ageLabel(): string
    {
        return $this->getAgeInDays().'d / '.self::freshnessDays().'d';
    }

    /** Unsold confirmed stock that has been held too long to sell fresh. */
    public function isStale(): bool
    {
        if (! $this->isConfirmed() || ! $this->hasRemainingStock()) {
            return false;
        }

        return $this->getAgeInDays() >= self::freshnessDays();
    }

    /** Days of freshness left. Zero once stale. */
    public function getDaysUntilStale(): int
    {
        return max(0, self::freshnessDays() - $this->getAgeInDays());
    }

    // ─── State ───────────────────────────────────────────────────

    public const STATE_PENDING = 'pending';

    public const STATE_REJECTED = 'rejected';

    public const STATE_ON_SALE = 'on_sale';

    public const STATE_STALE = 'stale';

    public const STATE_SOLD_OUT = 'sold_out';

    public const STATE_WRITTEN_OFF = 'written_off';

    public function getStockState(): string
    {
        return match (true) {
            $this->isPending() => self::STATE_PENDING,
            $this->isRejected() => self::STATE_REJECTED,
            $this->isDisposed() || $this->isCarriedOut() => self::STATE_WRITTEN_OFF,
            $this->isSoldOut() => self::STATE_SOLD_OUT,
            $this->isStale() => self::STATE_STALE,
            default => self::STATE_ON_SALE,
        };
    }

    // ─── Release (recording a sale) ──────────────────────────────

    /** Why kilograms cannot be released from this batch, or null when they can. */
    public function releaseBlocker(): ?string
    {
        if (! $this->isConfirmed()) {
            return $this->isPending()
                ? 'Wait for staff to confirm this batch first.'
                : 'Rejected batches cannot be sold.';
        }

        if ($this->isDisposed() || $this->isCarriedOut()) {
            return 'This batch has been written off.';
        }

        if (! $this->hasRemainingStock()) {
            return 'This batch is sold out.';
        }

        return null;
    }

    public function canRelease(): bool
    {
        return $this->releaseBlocker() === null;
    }

    /**
     * Record $kg of this batch as sold. It comes off the remaining stock, and so
     * off the public board.
     *
     * @throws ValidationException
     */
    public function release(float $kg): self
    {
        if ($blocker = $this->releaseBlocker()) {
            throw ValidationException::withMessages(['release_kg' => $blocker]);
        }

        $kg = round($kg, 2);
        $remaining = $this->getRemainingStock();

        if ($kg < 0.01) {
            throw ValidationException::withMessages(['release_kg' => 'Enter how many kilograms were sold.']);
        }

        if ($kg > $remaining + 0.001) {
            throw ValidationException::withMessages([
                'release_kg' => 'Only '.number_format($remaining, 2).' kg is left in this batch.',
            ]);
        }

        $this->update(['sold_kg' => round((float) $this->sold_kg + $kg, 2)]);

        return $this;
    }

    // ─── Write-off ───────────────────────────────────────────────

    public function canWriteOff(): bool
    {
        return $this->isStale() && ! $this->isDisposed();
    }

    /**
     * Clear a stale batch off the stall. Only stale stock qualifies, so fresh fish
     * cannot be erased from the vendor's remaining stock.
     *
     * @throws ValidationException
     */
    public function writeOff(?string $reason = null): self
    {
        if (! $this->canWriteOff()) {
            throw ValidationException::withMessages([
                'write_off' => 'Only batches past the '.self::freshnessDays().'-day freshness window can be written off.',
            ]);
        }

        $this->update([
            'disposed_at' => now(),
            'disposed_reason' => $reason ?: null,
        ]);

        return $this;
    }
}
