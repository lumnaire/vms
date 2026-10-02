<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorInventory;
use App\Services\CarryForwardStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * MyStockController
 *
 * The vendor's answer to "what fish do I still have, and what can I do about it?".
 *
 * The sale report can only report on the entries staff confirmed for the current
 * day, so stock left over from an earlier day had no home: it showed up as a
 * number in a history table but had no action attached to it, and no way to declare
 * it sold. This page gives every line of confirmed stock one of two outs — put it
 * back on the stall, or report it written off — and shows how long each line has
 * been held, so the vendor can tell which is which before the freshness window
 * closes.
 *
 * How old a line is the whole point of the page. Held for `stale_after_days` and it
 * is red and cannot be resubmitted; short of that and it is one tap from being
 * sellable again. Carrying it forward resets the clock, because the replacement
 * entry is dated the day it is resubmitted.
 */
class MyStockController extends Controller
{
    /** How much settled history to keep on the page; anything older is the sale report's. */
    public const SETTLED_HISTORY_DAYS = 14;

    // ─── The stock book ──────────────────────────────────────────
    public function index()
    {
        $vendorId = Auth::id();

        // Every confirmed line the vendor has ever logged. No date cut: an entry
        // held for a fortnight is exactly the one that needs looking at, and a
        // "recent only" list would hide it behind its own label.
        $entries = VendorInventory::with(['fishType', 'carriedTo'])
            ->where('vendor_id', $vendorId)
            ->where('status', 'confirmed')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $released = fn ($entry) => (float) $entry->released_kg;

        // Still on the stall, oldest held first so the lines closest to going stale
        // lead instead of sitting at the bottom of the list.
        $openStock = $entries
            ->filter(fn (VendorInventory $entry) => $entry->hasRemainingStock())
            ->sortBy(fn (VendorInventory $entry) => $entry->getAgeInDays())
            ->values();

        // Lines that have ended, so the page answers "what happened to it" for the
        // fish as well as "what do I have". Bounded, or the settled list would grow
        // without limit on a long-running account.
        $settled = $entries
            ->filter(fn (VendorInventory $entry) => ! $entry->hasRemainingStock())
            ->filter(fn (VendorInventory $entry) => $entry->getAgeInDays() <= self::SETTLED_HISTORY_DAYS)
            ->sortByDesc(fn (VendorInventory $entry) => $entry->entry_date)
            ->values();

        // ── Totals ────────────────────────────────────────────────
        //
        // Each figure answers a different question, and the distinction between the
        // first two is worth spelling out because resubmitting stock would otherwise
        // inflate both of them:
        //
        //   Total stock     every kilogram the vendor has brought to the stall.
        //                   Counted once, on the entry that first logged it, so
        //                   carrying 6 kg forward does not make 16 kg of fish.
        //   Confirmed stock what staff have approved that is still live here — not
        //                   handed to another day and not written off.
        //   Remaining stock what is left unsold of it, and therefore still sellable.
        //   Sold out        what was declared sold, i.e. bought completely.

        $totalStockKg = round((float) $entries
            ->filter(fn (VendorInventory $entry) => $entry->carried_from_id === null)
            ->sum($released), 2);

        $liveLines = $openStock->filter(fn (VendorInventory $entry) => ! $entry->isDisposed());

        $confirmedStockKg = round((float) $liveLines->sum($released), 2);
        $remainingStockKg = round((float) $liveLines->sum(fn ($e) => $e->getRemainingStock()), 2);

        $soldOutEntries = $entries->filter(fn (VendorInventory $entry) => $entry->isSoldThrough());
        $soldOutKg      = round((float) $soldOutEntries->sum(fn ($e) => (float) $e->sold_kg), 2);
        $soldOutLines   = $soldOutEntries->count();

        $staleEntries   = $openStock->filter(fn (VendorInventory $entry) => $entry->isStale())->values();
        $disposableKg   = round((float) $staleEntries->sum(fn ($e) => $e->getRemainingStock()), 2);
        $disposableLoss = round((float) $staleEntries->sum(fn ($e) => $e->getRemainingStockValue()), 2);

        // Handovers staff have not answered yet.
        //
        // The moment stock is carried forward it stops counting on its original line
        // and the replacement is only pending, so it is in neither the open book nor
        // any total above. Saying nothing here would leave the vendor watching
        // kilograms vanish off their stall with no sign of where they went.
        $awaitingConfirmation = $entries
            ->filter(fn (VendorInventory $entry) => $entry->isCarriedOut() && $entry->carriedTo?->isPending())
            ->sortByDesc(fn (VendorInventory $entry) => $entry->carried_out_at)
            ->values();

        return view('vendor.my-stock', [
            'openStock'           => $openStock,
            'settled'             => $settled,
            'settledHistoryDays'  => self::SETTLED_HISTORY_DAYS,
            'totalStockKg'        => $totalStockKg,
            'confirmedStockKg'    => $confirmedStockKg,
            'remainingStockKg'    => $remainingStockKg,
            'soldOutKg'           => $soldOutKg,
            'soldOutLines'        => $soldOutLines,
            'staleEntries'        => $staleEntries,
            'disposableKg'        => $disposableKg,
            'disposableLoss'      => $disposableLoss,
            'awaitingConfirmation' => $awaitingConfirmation,
        ]);
    }

    // ─── Put leftover stock back on the stall ────────────────────
    /**
     * Resubmit the entry's remaining stock as a new entry for today.
     *
     * No quantity field: the point is to make the whole remaining figure sellable
     * again in one tap. A vendor working a stall with one hand should not have to
     * retype a number the system already holds exactly.
     */
    public function carry(VendorInventory $inventory, CarryForwardStock $carry)
    {
        $this->authorizeEntry($inventory);

        $name    = $inventory->fishType?->name ?? 'fish';
        $carried = $carry->carry($inventory, today());

        return redirect()->route('vendor.my-stock.index')->with(
            'success',
            number_format((float) $carried->released_kg, 2) . ' kg of ' . $name
            . ' submitted for today. Awaiting staff confirmation — the freshness countdown has restarted.'
        );
    }

    // ─── Report unsellable stock as written off ──────────────────
    public function dispose(Request $request, VendorInventory $inventory, CarryForwardStock $carry)
    {
        $this->authorizeEntry($inventory);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:120'],
        ], [
            'reason.max' => 'Keep the reason under 120 characters.',
        ]);

        // Read the figure before the write-off, not after: reporting the stock is
        // exactly what takes it out of the remaining total.
        $kg    = $inventory->getRemainingStock();
        $value = $inventory->getRemainingStockValue();

        $carry->dispose($inventory, $request->input('reason'));

        return redirect()->route('vendor.my-stock.index')->with(
            'success',
            number_format($kg, 2) . ' kg of ' . ($inventory->fishType?->name ?? 'fish')
            . ' reported as written off — ' . number_format($value, 2)
            . ' taken off your remaining stock.'
        );
    }

    /**
     * A vendor may only ever act on their own entries.
     *
     * Route model binding resolves the id out of the URL, so without this a vendor
     * could carry forward — or write off — another vendor's stock by guessing ids.
     */
    private function authorizeEntry(VendorInventory $inventory): void
    {
        abort_if($inventory->vendor_id !== Auth::id(), 403);
    }
}
