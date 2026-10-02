<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\VendorInventory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CarryForwardStock
 *
 * Moves a vendor's unsold fish onto another trading day.
 *
 * Without this, stock that was not sold simply stops: yesterday's entry is closed
 * and immutable, the sale report only reports on today's entries, so yesterday's
 * leftover can never be declared, can never be sold, and sits in every remaining
 * figure forever. That is the gap this closes.
 *
 * How a carry works
 * -----------------
 * It creates a NEW entry dated on the target day rather than re-dating the old
 * one. Re-dating would rewrite a trading day that has already been declared — and
 * a sale report item is bound one-to-one to its entry, so the same kilograms could
 * never be declared twice anyway. A new row keeps each day its own statement.
 *
 * Two consequences follow, and both matter:
 *
 *  - The source line is marked carried_out_at in the same transaction. The stock
 *    now lives on the new line, so counting it on both would double every
 *    remaining-stock total from that moment on.
 *
 *  - The new entry starts life as pending, because a resubmission is a fresh
 *    claim on a fresh day and has no agreed price until staff confirm it, exactly
 *    like any other entry. And because it is dated today, it is sellable the same
 *    day it is carried.
 *
 * If that new entry is cancelled or refused, release() hands the stock back to the
 * line it came from. Without that, a rejection would delete the vendor's kilograms
 * from the book entirely.
 */
class CarryForwardStock
{
    /**
     * How far ahead a vendor may load the stall.
     *
     * A day or two of notice is what a market actually plans on; a vendor queuing
     * stock a fortnight out is guessing at a day that has not been traded yet.
     */
    public const MAX_DAYS_AHEAD = 7;

    /**
     * Resubmit $source's leftover stock as a new pending entry on $date.
     *
     * @param  float|null  $kg  How much to carry. Defaults to everything left, which
     *                          is what both entry points want: My Stock carries the
     *                          whole remaining figure, and the sale report carries
     *                          what the declaration showed was left.
     *
     * @throws ValidationException
     */
    public function carry(VendorInventory $source, CarbonInterface $date, ?float $kg = null): VendorInventory
    {
        $date  = Carbon::parse($date)->startOfDay();
        $kg    = $this->assertCanCarry($source, $date, $kg);

        $carried = DB::transaction(function () use ($source, $date, $kg) {
            $entry = VendorInventory::create([
                'vendor_id'       => $source->vendor_id,
                'fish_type_id'    => $source->fish_type_id,
                'quality_class'   => $source->quality_class,
                // The price staff agreed is the price this fish is still worth, so
                // the carry-forward carries it across rather than inventing one.
                // A vendor who wants to re-price their stock submits a new entry;
                // staff see this one as the same fish at the same price, which is
                // exactly what it is.
                'price_per_kg'    => $source->price_per_kg,
                'stock_kg'        => $kg,
                'released_kg'     => $kg,
                'sold_kg'         => 0,
                'status'          => 'pending',
                'entry_date'      => $date->toDateString(),
                'is_locked'       => false,
                'carried_from_id' => $source->id,
            ]);

            $source->update(['carried_out_at' => now()]);

            return $entry;
        });

        ActivityLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'carry_forward_stock',
            'description' => 'Resubmitted ' . number_format($kg, 2) . ' kg of '
                . ($source->fishType?->name ?? 'fish') . ' (' . $source->quality_class . ') from '
                . $source->entry_date->format('M j, Y') . ' to ' . $date->format('M j, Y')
                . ' — entry #' . $carried->id . ' awaits staff confirmation.',
        ]);

        return $carried;
    }

    /**
     * Check every rule a carry would break and return the resolved quantity.
     *
     * Split out from carry() so a caller carrying several entries can check the
     * whole batch first and write nothing until all of it passes. Otherwise a
     * five-line restock with one bad line leaves four lines already carried, and
     * the vendor is told the restock failed while their stall quietly gained
     * stock they never confirmed.
     *
     * @throws ValidationException
     */
    public function assertCanCarry(VendorInventory $source, CarbonInterface $date, ?float $kg = null): float
    {
        $date = Carbon::parse($date)->startOfDay();

        // The entry's own rules come first, so a hand-built request is refused for
        // the same reason the page hides the button. The day matters: today's
        // stock is already sellable today and cannot be resubmitted onto today,
        // but it can still be loaded onto a later trading day.
        if ($blocker = $source->resubmitBlocker($date)) {
            throw ValidationException::withMessages(['stock' => $blocker]);
        }

        $remaining = $source->getRemainingStock();
        $kg       = $kg === null ? $remaining : round($kg, 2);

        if ($kg <= 0) {
            throw ValidationException::withMessages([
                'stock' => 'Enter how many kilograms you are putting back on the stall.',
            ]);
        }

        if ($kg > $remaining + 0.001) {
            throw ValidationException::withMessages([
                'stock' => "You only have " . number_format($remaining, 2) . ' kg left on that entry.',
            ]);
        }

        $this->guardDate($source, $date);

        $this->guardNoClashOnTargetDay($source, $date);

        return $kg;
    }

    /**
     * Hand the stock back to the line it was carried from.
     *
     * Called when a carried entry never happens — the vendor cancels it, or staff
     * refuse it. The source has been holding nothing for the whole time the
     * replacement was pending, so without this the kilograms would be on the
     * vendor's bill and on nobody's stall.
     */
    public function release(VendorInventory $carried): void
    {
        if ($carried->carried_from_id === null) {
            return;
        }

        $source = $carried->carriedFrom()->first();

        if ($source?->isCarriedOut()) {
            $source->update(['carried_out_at' => null]);
        }
    }

    /**
     * Report unsellable stock as written off, so it leaves the stall and the book.
     *
     * Only stock past the freshness window qualifies. Fresh leftover stock is not
     * written off, it is sold or carried forward — so allowing it here would give
     * the vendor a way to erase live stock from every total.
     */
    public function dispose(VendorInventory $entry, ?string $reason = null): VendorInventory
    {
        if (! $entry->isStale()) {
            throw ValidationException::withMessages([
                'stock' => 'Only stock that is past the '
                    . config('inventory.stale_after_days') . '-day freshness window can be reported as written off. '
                    . 'Sell it or resubmit it on another day instead.',
            ]);
        }

        $writtenOff = $entry->getRemainingStock();

        $entry->update([
            'disposed_at'     => now(),
            'disposed_reason' => $reason ?: null,
        ]);

        ActivityLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'dispose_stock',
            'description' => 'Reported ' . number_format($writtenOff, 2) . ' kg of '
                . ($entry->fishType?->name ?? 'fish') . ' (' . $entry->quality_class . ') from '
                . $entry->entry_date->format('M j, Y') . ' as written off'
                . ($reason ? ": {$reason}" : '.') . ' Held ' . $entry->getAgeInDays() . ' days.',
        ]);

        return $entry;
    }

    /** The target day has to be a real trading day this vendor could actually use. */
    private function guardDate(VendorInventory $source, Carbon $date): void
    {
        // Whether the stock already sits on the target day is answered by
        // resubmitBlocker($date) above, which is where the same reason lives for
        // the page. It is checked there rather than here so the button and the
        // request cannot disagree about why a line is blocked.

        if ($date->lt($source->entry_date->startOfDay())) {
            throw ValidationException::withMessages([
                'carry_date' => 'Stock cannot be moved back to an earlier trading day.',
            ]);
        }

        if ($date->gt(today()->startOfDay()->addDays(self::MAX_DAYS_AHEAD))) {
            throw ValidationException::withMessages([
                'carry_date' => 'Stock can only be loaded up to ' . self::MAX_DAYS_AHEAD . ' days ahead.',
            ]);
        }
    }

    /**
     * One entry per fish and quality class per day, on submission and on carry.
     *
     * The source is excluded from the lookup on purpose. It is normally the entry
     * already sitting on the target day, and counting it would make a same-day
     * resubmission look like a duplicate of itself.
     */
    private function guardNoClashOnTargetDay(VendorInventory $source, Carbon $date): void
    {
        $clashes = VendorInventory::where('vendor_id', $source->vendor_id)
            ->where('fish_type_id', $source->fish_type_id)
            ->where('quality_class', $source->quality_class)
            ->whereDate('entry_date', $date->toDateString())
            ->where('id', '!=', $source->id)
            ->exists();

        if ($clashes) {
            throw ValidationException::withMessages([
                'stock' => 'You already have a ' . $source->quality_class . ' entry for '
                    . ($source->fishType?->name ?? 'this fish') . ' on '
                    . $date->format('M j, Y') . '. Cancel it first, or add to it on My Inventory.',
            ]);
        }
    }
}
