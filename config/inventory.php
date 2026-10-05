<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batch Freshness
    |--------------------------------------------------------------------------
    |
    | A batch of fish stays on sale across days until it is sold out or reaches
    | this many days old, counted from the day it was submitted (entry_date).
    | The vendor's table shows it as "age / limit", e.g. "1d / 3d".
    |
    | On the day a batch reaches the limit with fish still left it is stale: it
    | leaves the public board, is flagged in red for the vendor, and the vendor
    | writes it off so it stops counting toward their remaining stock.
    |
    */

    'stale_after_days' => (int) env('STALE_STOCK_AFTER_DAYS', 3),

];
