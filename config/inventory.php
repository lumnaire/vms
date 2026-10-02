<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stale Stock
    |--------------------------------------------------------------------------
    |
    | Fresh fish does not keep. Unsold stock that has been sitting on a stall
    | for several days is a loss for the vendor and a health risk for the
    | market, so it is surfaced rather than left to rot quietly in a total.
    |
    | An entry is flagged when BOTH hold:
    |   - it is at least `stale_after_days` old (entry_date, not created_at)
    |   - it still has unsold stock remaining
    |
    | Age is measured from entry_date because that is the trading day the vendor
    | declared. created_at would make a back-filled entry look fresh.
    |
    | The same number is the resubmission cutoff. Stock that is still fresh can be
    | put back on the stall on a later day (see App\Services\CarryForwardStock),
    | which creates a new entry dated that day and so restarts the clock. Stock at
    | or past the window cannot: the only thing left to do with it is report it as
    | written off, so it leaves the vendor's remaining stock instead of sitting on
    | the stall forever.
    |
    */

    'stale_after_days' => (int) env('STALE_STOCK_AFTER_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Sale Report Deadline
    |--------------------------------------------------------------------------
    |
    | Vendors declare the day's sales by this time on the report date. Kept here
    | rather than hard-coded so the market can change its cutoff without a code
    | change; the value is interpreted in the app timezone (Asia/Manila).
    |
    */

    'sale_report_deadline' => env('SALE_REPORT_DEADLINE', '23:59'),

];
