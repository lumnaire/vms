<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batch Freshness
    |--------------------------------------------------------------------------
    |
    | A batch of fish stays on sale across days until it is sold out or reaches
    | this many days old, counted from the day it was submitted (entry_date).
    |
    | The vendor's table counts down the days left, e.g. "2 days left". On the
    | day a batch reaches the limit with fish still on it, it expires: it leaves
    | the vendor's tables and the public board, but the row is kept because
    | supply reports and forecasts read it. Batches still waiting for staff are
    | deleted. See VendorInventory::expireOldBatches, run by the inventory:expire
    | command and the ExpireOldBatches middleware.
    |
    */

    'stale_after_days' => (int) env('STALE_STOCK_AFTER_DAYS', 3),

];
