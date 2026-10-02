<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\VendorInventory;
use App\Models\VendorSaleReport;
use App\Models\VendorSaleReportItem;
use App\Services\CarryForwardStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SaleReportController
 *
 * The vendor's end-of-day declaration: how much of each fish they sold, entered
 * against the entries staff confirmed that day.
 *
 * Rules the rest of the system depends on:
 *  - only CONFIRMED inventory is reportable; unconfirmed stock has no price yet
 *  - the day closes at 11:59 PM (Asia/Manila) on the report date
 *  - before the deadline the vendor may submit, then revise, until the deadline
 *  - submitting writes the declared kg back onto vendor_inventories.sold_kg, so
 *    remaining stock on the public board stays consistent with the declaration
 *
 * Once the day is filed, restock() loads a later day with whatever the declaration
 * says is left. That is the other half of the loop: the declaration settles what
 * moved, and the restock keeps what did not from being stranded on a closed day.
 */
class SaleReportController extends Controller
{
    // ─── Today's report form ─────────────────────────────────────
    public function index()
    {
        $vendorId = Auth::id();
        $today    = today();

        // The day being declared. Only today is open to the vendor; anything
        // earlier is already closed.
        $report = VendorSaleReport::firstOrNew([
            'vendor_id'  => $vendorId,
            'report_date' => $today,
        ]);

        // Only confirmed stock is reportable: an entry still awaiting staff
        // approval has no agreed price, so there is nothing to sell against yet.
        $entries = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', $today)
            ->where('status', 'confirmed')
            ->get();

        // Sort by fish name in PHP rather than SQL so this behaves identically
        // on MySQL and on the SQLite test database.
        $entries = $entries->sortBy(fn ($e) => $e->fishType?->name ?? '')->values();

        // Previous declarations, so the vendor can see their own history.
        $history = VendorSaleReport::where('vendor_id', $vendorId)
            ->whereDate('report_date', '<', $today)
            ->orderByDesc('report_date')
            ->limit(14)
            ->get();

        // Bounds for the restock date picker. Defaulting to tomorrow matches how a
        // vendor actually works — today is nearly over and its numbers are already
        // on the declaration.
        return view('vendor.sale-report', [
            'report'             => $report,
            'entries'            => $entries,
            'history'            => $history,
            'restockMinDate'     => $today->copy()->addDay()->toDateString(),
            'restockMaxDate'     => $today->copy()->addDays(CarryForwardStock::MAX_DAYS_AHEAD)->toDateString(),
            'restockMaxDays'     => CarryForwardStock::MAX_DAYS_AHEAD,
        ]);
    }

    // ─── Submit / revise the day ─────────────────────────────────
    public function store(Request $request)
    {
        $vendorId = Auth::id();
        $today    = today();

        // The report is for today, so it is open right now by definition. The
        // guard is kept explicit because it is the rule that matters most: after
        // 23:59 the day is closed.
        if (VendorSaleReport::deadlineFor($today)->isPast()) {
            return redirect()->route('vendor.sale-report.index')
                ->withErrors(['report_date' => 'The sale window for today has closed.']);
        }

        $entries = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', $today)
            ->where('status', 'confirmed')
            ->get()
            ->keyBy('id');

        $request->validate([
            'items'   => ['required', 'array'],
            'items.*' => ['required', 'array'],
            'items.*.total_kg' => [
                'required',
                'numeric',
                'min:0',
                // Cannot sell more than was released for sale.
                'max:99999.99',
            ],
        ], [
            'items.required'        => 'Enter the quantity sold for at least one confirmed entry.',
            'items.*.total_kg.required' => 'Enter a quantity for every confirmed entry (use 0 if none sold).',
            'items.*.total_kg.min'  => 'Quantity sold cannot be negative.',
            'items.*.total_kg.numeric' => 'Quantity sold must be a number.',
        ]);

        // Only confirmed entries belonging to this vendor may be declared, and
        // only up to what was released. Anything else is a stale or forged
        // client payload and must not reach the ledger.
        $errors = [];
        $clean  = [];

        foreach ($request->input('items', []) as $inventoryId => $payload) {
            $entry = $entries->get((int) $inventoryId);

            if (! $entry) {
                $errors["items.{$inventoryId}.total_kg"] =
                    'That entry is not a confirmed entry for today.';
                continue;
            }

            $kg = round((float) ($payload['total_kg'] ?? 0), 2);

            if ($kg > (float) $entry->released_kg) {
                $errors["items.{$inventoryId}.total_kg"] =
                    'You declared ' . $kg . ' kg but only ' . $entry->released_kg . ' kg was released for sale.';
                continue;
            }

            $clean[$entry->id] = ['entry' => $entry, 'total_kg' => $kg];
        }

        // Every confirmed entry must be declared on. The form renders all of
        // them, so a payload that is missing one is either a dropped field or a
        // hand-built request trying to declare a day that is only part-reported.
        // Totals are computed from what arrives, so without this the day's
        // stock and revenue would silently understate themselves.
        foreach ($entries as $entryId => $entry) {
            if (! array_key_exists($entryId, $clean)) {
                $errors["items.{$entryId}.total_kg"] =
                    'Declare every confirmed entry. Enter 0 if none of this fish sold.';
            }
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $report = DB::transaction(function () use ($vendorId, $today, $clean) {
            $report = VendorSaleReport::firstOrNew([
                'vendor_id'   => $vendorId,
                'report_date' => $today,
            ]);

            // Persist before its items exist, so every line has a real parent id
            // to point at.
            $report->submitted_at = now();
            $report->save();

            // Lines for entries that are no longer reportable are dropped rather
            // than left behind: if staff rejected an entry after the vendor had
            // already declared it, the day must not keep counting fish the vendor
            // is no longer allowed to sell.
            $report->items()
                ->whereNotIn('vendor_inventory_id', array_keys($clean))
                ->delete();

            $totalStock = 0.0;
            $totalSold  = 0.0;
            $totalValue = 0.0;

            foreach ($clean as $inventoryId => ['entry' => $entry, 'total_kg' => $kg]) {
                $price = (float) $entry->price_per_kg;
                $value = round($kg * $price, 2);

                VendorSaleReportItem::updateOrCreate(
                    ['vendor_inventory_id' => $entry->id],
                    [
                        'vendor_sale_report_id' => $report->id,
                        'fish_type_id'          => $entry->fish_type_id,
                        'fish_type_name'        => $entry->fishType?->name ?? 'Unknown',
                        'quality_class'         => $entry->quality_class,
                        'price_per_kg'          => $price,
                        'released_kg'           => $entry->released_kg,
                        'total_kg'              => $kg,
                        'total_price'           => $value,
                    ]
                );

                // The declaration is the source of truth for sold_kg, so the
                // price board's remaining stock agrees with it.
                $entry->update(['sold_kg' => $kg]);

                $totalStock += (float) $entry->released_kg;
                $totalSold  += $kg;
                $totalValue += $value;
            }

            $report->total_stock_kg = round($totalStock, 2);
            $report->total_sold_kg  = round($totalSold, 2);
            $report->total_value    = round($totalValue, 2);
            $report->item_count     = count($clean);
            $report->save();

            return $report;
        });

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'submit_sale_report',
            'description' => "Submitted sale report for {$today->format('M j, Y')}: "
                . $report->item_count . ' item(s), ' . $report->total_sold_kg . ' kg sold, '
                . '₱' . number_format((float) $report->total_value, 2) . '.',
        ]);

        return redirect()->route('vendor.sale-report.index')
            ->with('success', 'Sale report submitted. You can revise it until 11:59 PM.');
    }

    // ─── Load tomorrow with what today did not sell ───────────────
    /**
     * Carry the leftover from today's declaration onto a later trading day.
     *
     * The declaration is what finally says how much of each fish really moved, so
     * it is also the only moment the leftover is a fact rather than a guess — which
     * is why restocking waits for the day to be filed instead of being offered
     * alongside the form.
     *
     * The destination is always a future day. Today's entries are already declared
     * and already counted; putting the same kilograms back on today would either be
     * a no-op or a second entry contradicting the first.
     */
    public function restock(Request $request, CarryForwardStock $carry)
    {
        $vendorId = Auth::id();
        $today    = today();

        $report = VendorSaleReport::where('vendor_id', $vendorId)
            ->whereDate('report_date', $today)
            ->first();

        // Without a filed declaration the leftover is whatever the vendor last typed,
        // which they can still be editing. Restocking off that would load a later
        // day with a number the vendor themselves have not committed to.
        if (! $report?->isSubmitted()) {
            return back()->withErrors([
                'carry_date' => 'File today\'s sale report first. The restock is based on what you declared sold.',
            ]);
        }

        $request->validate([
            'carry_date' => ['required', 'date'],
            'entries'    => ['required', 'array', 'min:1'],
            'entries.*'  => ['integer'],
        ], [
            'carry_date.required' => 'Choose the trading day to load.',
            'carry_date.date'     => 'Choose a valid trading day.',
            'entries.required'    => 'Tick at least one line to restock.',
            'entries.min'         => 'Tick at least one line to restock.',
            'entries.*.integer'   => 'Invalid line selected.',
        ]);

        $carryDate = Carbon::parse($request->input('carry_date'))->startOfDay();

        // Only today's confirmed entries may be restocked, so a hand-built payload
        // naming another vendor's fish or an entry from a closed day is refused
        // rather than quietly ignored.
        $entries = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', $today)
            ->where('status', 'confirmed')
            ->whereIn('id', $request->input('entries', []))
            ->get()
            ->keyBy('id');

        $errors = [];

        // Check every ticked line before writing any of them.
        //
        // The tick list is one instruction — "load these onto that day" — so it is
        // either all of it or none. Carrying as it goes would leave the stall half
        // loaded on a failure: the vendor reads "restock failed", fixes the one bad
        // line, submits again, and the four that already worked are now rejected as
        // sold through.
        foreach ($request->input('entries', []) as $id) {
            $entry = $entries->get((int) $id);

            if (! $entry) {
                $errors["entries.{$id}"] = 'That line is not a confirmed entry for today.';
                continue;
            }

            try {
                $carry->assertCanCarry($entry, $carryDate);
            } catch (ValidationException $e) {
                // Reported per line rather than as one flat failure: a vendor
                // restocking five kinds of fish should see which four worked.
                // Anything that is not a line-level problem (the trading day
                // itself) keeps its own key so it is not pinned to one fish.
                foreach ($e->errors() as $field => $messages) {
                    $key = $field === 'stock' ? "entries.{$id}" : $field;

                    $errors[$key] = $messages[0];
                }
            }
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        // Now that the whole batch is known good, write it. Still one transaction,
        // so a failure part-way through cannot leave two lines of the same restock
        // on two different days.
        $made = DB::transaction(function () use ($request, $entries, $carry, $carryDate) {
            $carried = [];

            foreach ($request->input('entries', []) as $id) {
                $carried[] = $carry->carry($entries->get((int) $id), $carryDate);
            }

            return $carried;
        });

        $kg = round(array_sum(array_map(fn ($e) => (float) $e->released_kg, $made)), 2);

        return redirect()->route('vendor.sale-report.index')->with(
            'success',
            number_format($kg, 2) . ' kg across ' . count($made) . ' '
            . Str::plural('line', count($made)) . ' submitted for '
            . $carryDate->format('M j, Y') . '. Awaiting staff confirmation on that day.'
        );
    }
}
