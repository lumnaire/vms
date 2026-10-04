<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorSaleReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * SaleReportController
 *
 * Read-only view of what vendors declared they sold, shared by market staff and
 * the supervisor. Both roles see the same data; only the surrounding nav differs.
 *
 * The page answers three questions at once:
 *  - a table of vendor / fish / quality / price / kg / value, filterable by date
 *    and vendor
 *  - how much is still unsold from each declaration
 *  - which trading days have declarations at all, shown as a calendar so a gap
 *    is visible without hunting through dates
 */
class SaleReportController extends Controller
{
    public function index(Request $request)
    {
        $reportDate = $this->resolveDate($request->input('date'));

        // Only confirmed stock can have been sold, so the eligible set and the
        // declarations are both scoped to confirmed entries.
        $reports = VendorSaleReport::with([
            'vendor.vendorProfile',
            'items' => fn ($q) => $q->orderBy('quality_class')->orderBy('fish_type_name'),
        ])
            ->whereDate('report_date', $reportDate)
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->get()
            ->sortBy(fn ($r) => $r->vendor?->name ?? '');

        // Flatten to the table rows: one row per declared fish entry.
        $rows = $reports->flatMap(fn ($report) => $report->items->map(fn ($item) => [
            'report' => $report,
            'item' => $item,
            'vendor' => $report->vendor,
            'stall' => $report->vendor?->vendorProfile?->stall_number,
            'unsold_kg' => $item->getUnsoldKg(),
            'unsold_value' => $item->getUnsoldValue(),
        ]));

        // ── Totals ───────────────────────────────────────────────────
        $totals = [
            'vendors' => $reports->count(),
            'rows' => $rows->count(),
            'stock_kg' => round((float) $reports->sum('total_stock_kg'), 2),
            'sold_kg' => round((float) $reports->sum('total_sold_kg'), 2),
            'value' => round((float) $reports->sum('total_value'), 2),
            'unsold_kg' => round((float) $rows->sum('unsold_kg'), 2),
        ];
        $totals['sell_through_pct'] = $totals['stock_kg'] > 0
            ? round(($totals['sold_kg'] / $totals['stock_kg']) * 100, 1)
            : 0.0;

        // ── Vendor filter options ────────────────────────────────────
        $vendors = User::where('role', 'vendor')
            ->with('vendorProfile')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'stall' => $u->vendorProfile?->stall_number,
            ]);

        // ── Calendar ─────────────────────────────────────────────────
        [$calendar, $calendarCounts] = $this->buildCalendar($reportDate);

        // Vendors with confirmed stock on this date but no declaration: the
        // outstanding filings the page is really about.
        //
        // Deliberately resolved from the whole day's filings rather than from
        // $reports. The notice answers "who still owes the market a
        // declaration for this day", so scoping it to the vendor filter would
        // make every other filer look like a defaulter the moment a filter is
        // applied.
        $filedVendorIds = VendorSaleReport::whereDate('report_date', $reportDate)
            ->distinct()
            ->pluck('vendor_id');

        $missingVendorIds = VendorInventory::where('status', 'confirmed')
            ->whereDate('entry_date', $reportDate)
            ->whereNotIn('vendor_id', $filedVendorIds)
            ->distinct()
            ->pluck('vendor_id');

        $missingVendors = User::whereIn('id', $missingVendorIds)
            ->with('vendorProfile')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'stall' => $u->vendorProfile?->stall_number,
            ]);

        return view('sale-reports.index', [
            'reportDate' => $reportDate,
            'reports' => $reports,
            'rows' => $rows,
            'totals' => $totals,
            'vendors' => $vendors,
            'calendar' => $calendar,
            'calendarCounts' => $calendarCounts,
            'missingVendors' => $missingVendors,
            'selectedVendorId' => $request->integer('vendor_id') ?: null,
        ]);
    }

    /**
     * Clamp a user-supplied date to something sane. An empty or unparsable value
     * falls back to today; a far-future or pre-market date is still honoured,
     * because "show me a day with nothing on it" is a legitimate question when
     * checking whether vendors filed.
     */
    private function resolveDate(?string $value): Carbon
    {
        if (! $value) {
            return today();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return today();
        }
    }

    /**
     * Build the month grid around the selected date, plus how many declarations
     * landed on each day so the calendar can show where the filings are.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: Collection<string, int>}
     */
    private function buildCalendar(Carbon $selected): array
    {
        $monthStart = $selected->copy()->startOfMonth();
        $monthEnd = $selected->copy()->endOfMonth();

        // Pad to whole weeks so every row of the grid has seven cells.
        $gridStart = $monthStart->copy()->subDays($monthStart->dayOfWeekIso % 7);
        $gridEnd = $monthEnd->copy()->addDays(6 - $monthEnd->dayOfWeekIso % 7);

        $counts = VendorSaleReport::whereBetween('report_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->selectRaw('report_date, COUNT(*) as c, SUM(total_sold_kg) as kg')
            ->groupBy('report_date')
            ->get()
            ->mapWithKeys(fn ($r) => [
                // MySQL and SQLite both hand back a date string here.
                Carbon::parse($r->report_date)->toDateString() => [
                    'reports' => (int) $r->c,
                    'kg' => round((float) $r->kg, 2),
                ],
            ]);

        $weeks = [];
        for ($cursor = $gridStart->copy(); $cursor->lessThanOrEqualTo($gridEnd); $cursor->addDay()) {
            $date = $cursor->toDateString();

            $weeks[] = [
                'date' => $cursor->toDateString(),
                'day' => $cursor->day,
                'in_month' => $cursor->month === $selected->month && $cursor->year === $selected->year,
                'is_today' => $date === today()->toDateString(),
                'is_sel' => $date === $selected->toDateString(),
                'is_future' => $cursor->greaterThan(today()),
                'reports' => $counts[$date]['reports'] ?? 0,
                'kg' => $counts[$date]['kg'] ?? 0.0,
            ];
        }

        return [array_chunk($weeks, 7), $counts];
    }
}
