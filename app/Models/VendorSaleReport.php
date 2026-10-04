<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * VendorSaleReport
 *
 * A vendor's end-of-day declaration of what they sold, one row per vendor per
 * trading day. The per-fish lines live in VendorSaleReportItem.
 *
 * This replaces editing `sold_kg` entry-by-entry on vendor_inventories: the
 * vendor states the day's numbers in one place, against the entries staff
 * actually confirmed, and the submission is timestamped.
 */
class VendorSaleReport extends Model
{
    protected $fillable = [
        'vendor_id',
        'report_date',
        'total_stock_kg',
        'total_sold_kg',
        'total_value',
        'item_count',
        'submitted_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'total_stock_kg' => 'decimal:2',
        'total_sold_kg' => 'decimal:2',
        'total_value' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function items()
    {
        return $this->hasMany(VendorSaleReportItem::class);
    }

    // ─── Deadline ────────────────────────────────────────────────
    //
    // Sales close at 11:59 PM on the report date. Everything the vendor
    // submits before then can still be corrected; after midnight the day is
    // closed and only staff or the supervisor can amend it.

    /**
     * The last instant the vendor may submit or edit this report.
     *
     * The cutoff is read from config('inventory.sale_report_deadline'), which
     * defaults to 23:59 — 11:59 PM in the market's own timezone
     * (Asia/Manila), so it matches what the vendor sees on the clock.
     */
    public static function deadlineFor(Carbon|string $date): Carbon
    {
        $day = Carbon::parse($date)->startOfDay();
        $cutoff = config('inventory.sale_report_deadline', '23:59');

        [$h, $m] = array_pad(array_map('intval', explode(':', (string) $cutoff)), 2, 0);

        return $day->copy()->setTime($h, $m, 59);
    }

    public function isPastDeadline(?Carbon $now = null): bool
    {
        return ($now ?? now())->greaterThan(static::deadlineFor($this->report_date));
    }

    /** The vendor may still change this report. */
    public function isOpenForVendor(?Carbon $now = null): bool
    {
        return ! $this->isPastDeadline($now);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
