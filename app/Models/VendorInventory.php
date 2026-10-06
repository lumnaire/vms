<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One batch of fish a vendor brought to the stall.
 *
 * Every submission is a batch. Submitting the same fish and quality class again
 * while earlier batches of it are still on the stall is the next batch (Batch 1,
 * Batch 2, …), even across days, each with its own price, stock and age. A batch
 * stays on sale across days until it is sold out or reaches the freshness limit,
 * when it expires (see expireOldBatches()).
 *
 * Stock and what is for sale are the same figure: released_kg is set to stock_kg
 * on submission. Release takes kilograms off it in one of two kinds: sold_kg is
 * what the vendor recorded as sold, pulled_out_kg what they took off the stall
 * unsold. Remaining stock is always released − sold − pulled out, and every
 * release is also kept as a BatchRelease record.
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
        'pulled_out_kg',
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
        'pulled_out_kg' => 'decimal:2',
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

    public function releases()
    {
        return $this->hasMany(BatchRelease::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /** Confirmed batches that still have fish left: what the vendor is selling. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'confirmed')
            ->whereNull('disposed_at')
            // Legacy: stock handed to another day before carrying was removed.
            ->whereNull('carried_out_at')
            ->whereRaw('sold_kg + pulled_out_kg < released_kg');
    }

    // ─── Batches ─────────────────────────────────────────────────

    /**
     * The number the next submission of this fish gets on that day.
     *
     * Numbering continues past every batch of this fish still on the stall or
     * waiting for staff, whatever day it came in. Batches carry across days, so
     * restarting at 1 each morning would put yesterday's Batch 1 and today's
     * Batch 1 side by side on the board. Once all of them are gone, the next
     * day starts again at Batch 1.
     */
    public static function nextBatchNo(int $vendorId, int $fishTypeId, string $qualityClass, CarbonInterface $date): int
    {
        return (int) static::where('vendor_id', $vendorId)
            ->where('fish_type_id', $fishTypeId)
            ->where('quality_class', $qualityClass)
            ->where(fn (Builder $q) => $q->whereDate('entry_date', $date->toDateString())
                ->orWhere('status', 'pending')
                ->orWhere(fn (Builder $q) => $q->open()))
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

    /**
     * Reached the freshness limit unsold and was taken off the stall, or (legacy)
     * written off by hand. The row is kept for supply reports and forecasts.
     */
    public function isExpired(): bool
    {
        return $this->disposed_at !== null;
    }

    // ─── Stock ───────────────────────────────────────────────────

    public function getRemainingStock(): float
    {
        // Expired (or legacy carried) stock has left the stall even though
        // released_kg and sold_kg still describe the batch as it was.
        if ($this->isCarriedOut() || $this->isExpired()) {
            return 0.0;
        }

        return round(max(0, (float) $this->released_kg - (float) $this->sold_kg - (float) $this->pulled_out_kg), 2);
    }

    public function hasRemainingStock(): bool
    {
        return $this->getRemainingStock() > 0;
    }

    public function getSoldKg(): float
    {
        return round((float) $this->sold_kg, 2);
    }

    /** Kilograms released without being sold. */
    public function getPulledOutKg(): float
    {
        return round((float) $this->pulled_out_kg, 2);
    }

    public function getRemainingStockValue(): float
    {
        return round($this->getRemainingStock() * (float) $this->price_per_kg, 2);
    }

    /** Every kilogram has been released, sold or pulled out. */
    public function isFullyReleased(): bool
    {
        return (float) $this->released_kg > 0
            && (float) $this->sold_kg + (float) $this->pulled_out_kg >= (float) $this->released_kg;
    }

    /** Confirmed with fish left: on the vendor's stall. */
    public function isOpen(): bool
    {
        return $this->isConfirmed() && $this->hasRemainingStock();
    }

    // ─── Freshness countdown ─────────────────────────────────────
    //
    // A batch is measured from entry_date, the day it was submitted. It can be
    // sold for `inventory.stale_after_days` days. On the day it reaches that age
    // with fish still on it, it expires: it leaves the vendor's tables and the
    // public board, but the row stays for supply reports and forecasts.

    public static function freshnessDays(): int
    {
        return max(1, (int) config('inventory.stale_after_days'));
    }

    public function getAgeInDays(): int
    {
        return (int) $this->entry_date->copy()->startOfDay()->diffInDays(today()->startOfDay());
    }

    /** Waiting for staff or on sale: the batches that count down to expiry. */
    public function isCountingDown(): bool
    {
        return $this->isPending() || $this->isOpen();
    }

    /** Days until the batch expires. */
    public function getDaysLeft(): int
    {
        return max(0, self::freshnessDays() - $this->getAgeInDays());
    }

    /** "2 days left" */
    public function countdownLabel(): string
    {
        $days = $this->getDaysLeft();

        return $days.' '.Str::plural('day', $days).' left';
    }

    /** The day the batch expires if it still has fish. */
    public function expiresOn(): Carbon
    {
        return $this->entry_date->copy()->startOfDay()->addDays(self::freshnessDays());
    }

    /**
     * Take every batch that reached the freshness limit off the stall.
     *
     * Confirmed batches with fish left are marked expired, not deleted: supply
     * reports and forecasts read each confirmed batch's price and kilograms, so
     * deleting them would erase that history. They leave the vendor's tables and
     * the public board all the same. Pending batches staff never confirmed are
     * part of no history, so those are deleted outright.
     *
     * Returns how many batches were taken off.
     */
    public static function expireOldBatches(): int
    {
        $cutoff = today()->subDays(self::freshnessDays())->toDateString();

        $expired = static::query()->open()
            ->whereDate('entry_date', '<=', $cutoff)
            ->update(['disposed_at' => now(), 'disposed_reason' => 'Expired']);

        $deleted = static::query()->where('status', 'pending')
            ->whereDate('entry_date', '<=', $cutoff)
            ->delete();

        if ($expired + $deleted > 0) {
            ActivityLog::create([
                'user_id' => null,
                'action' => 'expire_inventory',
                'description' => "Took {$expired} unsold and {$deleted} unconfirmed "
                    .Str::plural('batch', $expired + $deleted).' off the stall after '.self::freshnessDays().' days.',
            ]);
        }

        return $expired + $deleted;
    }

    // ─── State ───────────────────────────────────────────────────

    public const STATE_PENDING = 'pending';

    public const STATE_REJECTED = 'rejected';

    public const STATE_ON_SALE = 'on_sale';

    public const STATE_SOLD_OUT = 'sold_out';

    /** Nothing left, and at least part of it was pulled out rather than sold. */
    public const STATE_RELEASED = 'released';

    public const STATE_EXPIRED = 'expired';

    public function getStockState(): string
    {
        return match (true) {
            $this->isPending() => self::STATE_PENDING,
            $this->isRejected() => self::STATE_REJECTED,
            $this->isExpired() || $this->isCarriedOut() => self::STATE_EXPIRED,
            $this->isFullyReleased() => $this->getPulledOutKg() > 0 ? self::STATE_RELEASED : self::STATE_SOLD_OUT,
            default => self::STATE_ON_SALE,
        };
    }

    // ─── Release: sold or pulled out ─────────────────────────────

    /** Why kilograms cannot be released from this batch, or null when they can. */
    public function releaseBlocker(): ?string
    {
        if (! $this->isConfirmed()) {
            return $this->isPending()
                ? 'Wait for staff to confirm this batch first.'
                : 'Rejected batches cannot be released.';
        }

        if ($this->isExpired() || $this->isCarriedOut()) {
            return 'This batch has expired.';
        }

        if (! $this->hasRemainingStock()) {
            return 'Nothing is left in this batch.';
        }

        return null;
    }

    public function canRelease(): bool
    {
        return $this->releaseBlocker() === null;
    }

    /**
     * Release $kg of this batch, either sold or pulled out unsold. Either way it
     * comes off the remaining stock, and so off the public board, and is kept as
     * its own BatchRelease record.
     *
     * @throws ValidationException
     */
    public function release(float $kg, string $kind = BatchRelease::SOLD, ?string $reason = null): BatchRelease
    {
        if ($blocker = $this->releaseBlocker()) {
            throw ValidationException::withMessages(['release_kg' => $blocker]);
        }

        if (! in_array($kind, BatchRelease::KINDS, true)) {
            throw ValidationException::withMessages(['release_kind' => 'Choose whether the fish was sold or pulled out.']);
        }

        $kg = round($kg, 2);
        $remaining = $this->getRemainingStock();

        if ($kg < 0.01) {
            throw ValidationException::withMessages(['release_kg' => 'Enter how many kilograms to release.']);
        }

        if ($kg > $remaining + 0.001) {
            throw ValidationException::withMessages([
                'release_kg' => 'Only '.number_format($remaining, 2).' kg is left in this batch.',
            ]);
        }

        $column = $kind === BatchRelease::PULLED_OUT ? 'pulled_out_kg' : 'sold_kg';

        return DB::transaction(function () use ($column, $kg, $kind, $reason) {
            $this->update([$column => round((float) $this->{$column} + $kg, 2)]);

            return $this->releases()->create([
                'vendor_id' => $this->vendor_id,
                'kind' => $kind,
                'kg' => $kg,
                'reason' => $reason ?: null,
            ]);
        });
    }
}
