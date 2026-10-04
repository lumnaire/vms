<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class VendorInventory extends Model
{
    /**
     * The two trading sessions a market day is split into.
     *
     * A vendor may land fish in both — 15 kg at dawn and 5 kg after lunch is two
     * deliveries, not one entry submitted twice — so this is what tells those
     * lines apart on the board and in the declaration.
     */
    public const SESSIONS = ['AM', 'PM'];

    public const SESSION_AM = 'AM';

    public const SESSION_PM = 'PM';

    protected $fillable = [
        'vendor_id',
        'fish_type_id',
        'quality_class',
        'market_session',
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

    // ─── Trading Session ──────────────────────────────────────────

    /** The session this entry was logged in, falling back to AM on legacy rows. */
    public function session(): string
    {
        return in_array($this->market_session, self::SESSIONS, true)
            ? $this->market_session
            : self::SESSION_AM;
    }

    public function isAmSession(): bool
    {
        return $this->session() === self::SESSION_AM;
    }

    public function isPmSession(): bool
    {
        return $this->session() === self::SESSION_PM;
    }

    /** "Morning" / "Afternoon", for prose that should not shout abbreviations. */
    public function sessionLabel(): string
    {
        return $this->isAmSession() ? 'Morning' : 'Afternoon';
    }

    /**
     * Lines sharing this key are the same vendor selling the same fish in the same
     * quality class. More than one of them on a day is a repeat submission —
     * several deliveries, in either session — which staff see grouped together and
     * the price board adds up into one total.
     */
    public function repeatKey(): string
    {
        return $this->vendor_id.'_'.$this->fish_type_id.'_'.$this->quality_class;
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

    public const STATE_OPEN = 'open';

    public const STATE_SOLD_OUT = 'sold_out';

    public const STATE_CARRIED = 'carried';

    public const STATE_DISPOSED = 'disposed';

    public const STATE_STALE = 'stale';

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

            return 'Handed over to another day'.($carriedOn ? " on {$carriedOn}" : '').'.';
        }

        if (! $this->isConfirmed()) {
            return 'Not confirmed by staff yet.';
        }

        if (! $this->hasRemainingStock()) {
            return 'Nothing left to resubmit.';
        }

        if ($this->isStale()) {
            return $this->getAgeInDays().' days old — too old to sell. Report it as written off.';
        }

        // Already sitting on the day it would be resubmitted onto.
        //
        // A resubmission creates a new entry rather than re-dating this one, so
        // resubmitting stock that is already dated on that day does not make it
        // sellable — it is sellable already — it just writes a second entry for
        // the same kilograms on the same day. Staff would confirm that as fresh
        // stock and the fish would be counted twice.
        //
        // The remedy is Add Stock, which tops this very entry up instead of
        // opening a new line beside it.
        $on ??= today();

        if ($this->entry_date->isSameDay($on)) {
            return 'Already on '.$on->format('M j').' — use Add Stock on that entry instead.';
        }

        return null;
    }

    public function canResubmit(?CarbonInterface $on = null): bool
    {
        return $this->resubmitBlocker($on) === null;
    }

    // ─── Adding Stock ─────────────────────────────────────────────
    //
    // More fish of the same kind turns up after staff have already approved the
    // entry. Rather than opening a second entry for it — which would need a second
    // approval for a fact staff can already see — the vendor tops the confirmed
    // line up, and the board's figure moves with it.
    //
    // The session decides where the kilograms land. More fish in the same session
    // tops this line up. Fish arriving in the other session becomes its own
    // confirmed line beside it, so 15 kg AM plus 5 kg PM stays two truthful lines
    // on the board instead of the morning delivery being relabelled as afternoon.

    /**
     * Why more stock cannot be added to this entry right now, or null when it can.
     *
     * Same shape as resubmitBlocker() and for the same reason: the My Inventory
     * page and this method must never disagree about why a control is missing.
     */
    public function addStockBlocker(): ?string
    {
        // Only today's stock is on the board and only today's stock is declared,
        // so topping up yesterday's line would change numbers nothing reads.
        if (! $this->entry_date->isSameDay(today())) {
            return 'Only today\'s stock can be added to. Older entries are locked after their trading day.';
        }

        if (! $this->isConfirmed()) {
            return $this->isPending()
                ? 'Not confirmed by staff yet.'
                : 'Rejected entries cannot be added to.';
        }

        if ($this->isDisposed()) {
            return 'Reported as written off.';
        }

        if ($this->isCarriedOut()) {
            return 'Handed over to another trading day.';
        }

        return null;
    }

    public function canAddStock(): bool
    {
        return $this->addStockBlocker() === null;
    }

    /**
     * Add $stockKg to the entry, of which $releasedKg goes up for sale.
     *
     * Both figures are asked for because they answer different questions and a
     * vendor bringing 20 kg to the stall does not always put all of it out: the
     * difference is kept back, which is exactly what stock_kg versus released_kg
     * has always meant on this row.
     *
     * Returns the line that received the stock: this entry when the session
     * matches, otherwise the new line opened for the other session.
     *
     * @throws ValidationException
     */
    public function addStock(float $stockKg, float $releasedKg, ?string $session = null): self
    {
        if ($blocker = $this->addStockBlocker()) {
            throw ValidationException::withMessages(['stock' => $blocker]);
        }

        if ($session !== null && $session !== '' && ! in_array($session, self::SESSIONS, true)) {
            throw ValidationException::withMessages([
                'market_session' => 'Choose a trading session: AM or PM.',
            ]);
        }

        $session = $session ?: $this->session();

        $stockKg = round($stockKg, 2);
        $releasedKg = round($releasedKg, 2);

        if ($stockKg < 0.1) {
            throw ValidationException::withMessages([
                'stock_kg' => 'Enter how many kilograms you are adding.',
            ]);
        }

        // The release cannot outrun the delivery, on the same terms as a fresh
        // submission — otherwise the row would claim more fish for sale than the
        // vendor admits to having brought.
        if ($releasedKg > $stockKg + 0.001) {
            throw ValidationException::withMessages([
                'released_kg' => 'Released cannot be more than the stock you added.',
            ]);
        }

        // What the declaration has already claimed sold must survive the top-up.
        // It always will: sold_kg is untouched and only released_kg grows, so
        // remaining rises rather than the day being rewritten. Checked anyway so a
        // future change cannot quietly break that.
        if ((float) $this->released_kg + $releasedKg < (float) $this->sold_kg) {
            throw ValidationException::withMessages([
                'released_kg' => 'That would leave less released than you already declared sold.',
            ]);
        }

        if ($session !== $this->session()) {
            // Already confirmed on arrival: the price is the one staff agreed on
            // this entry, and only the weight and the session are new.
            return self::create([
                'vendor_id' => $this->vendor_id,
                'fish_type_id' => $this->fish_type_id,
                'quality_class' => $this->quality_class,
                'market_session' => $session,
                'price_per_kg' => $this->price_per_kg,
                'stock_kg' => $stockKg,
                'released_kg' => $releasedKg,
                'sold_kg' => 0,
                'status' => 'confirmed',
                'confirmed_by' => $this->confirmed_by,
                'confirmed_at' => now(),
                'entry_date' => $this->entry_date->toDateString(),
                'is_locked' => false,
            ]);
        }

        $this->update([
            'stock_kg' => round((float) $this->stock_kg + $stockKg, 2),
            'released_kg' => round((float) $this->released_kg + $releasedKg, 2),
        ]);

        return $this;
    }
}
