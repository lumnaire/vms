<?php

namespace App\Console\Commands;

use App\Models\VendorInventory;
use Illuminate\Console\Command;

/**
 * ExpireOldInventory
 *
 * Takes every batch that reached the freshness limit (inventory.stale_after_days)
 * off the stall: unsold confirmed batches are marked expired and kept for
 * reports and forecasts, unconfirmed ones are deleted.
 * See VendorInventory::expireOldBatches().
 *
 * Schedule: daily at 00:02 in bootstrap/app.php. The ExpireOldBatches
 * middleware also runs it on the first request of the day.
 *
 * Usage:
 *   php artisan inventory:expire
 */
class ExpireOldInventory extends Command
{
    protected $signature = 'inventory:expire';

    protected $description = 'Take unsold batches that reached the freshness limit off the stall.';

    public function handle(): int
    {
        $count = VendorInventory::expireOldBatches();

        $this->info("[VPM] Took {$count} expired batch(es) off the stall.");

        return self::SUCCESS;
    }
}
