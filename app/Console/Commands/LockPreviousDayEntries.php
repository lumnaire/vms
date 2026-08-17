<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\VendorInventory;
use Illuminate\Console\Command;

/**
 * LockPreviousDayEntries
 *
 * Sets is_locked = true on all vendor_inventory rows whose entry_date is
 * before today.  This enforces the "same-day entry" rule: vendors may only
 * edit or cancel on the day they submitted; once midnight passes the record
 * is immutable.
 *
 * Schedule: daily at 00:05 (just after midnight) in bootstrap/app.php.
 *
 * Usage:
 *   php artisan inventory:lock
 */
class LockPreviousDayEntries extends Command
{
    protected $signature   = 'inventory:lock';
    protected $description = 'Lock all vendor inventory entries from previous days (is_locked = true).';

    public function handle(): int
    {
        $count = VendorInventory::where('is_locked', false)
            ->whereDate('entry_date', '<', today())
            ->update(['is_locked' => true]);

        // Activity log entry (system-level, no user_id)
        ActivityLog::create([
            'user_id'     => null,
            'action'      => 'inventory_lock',
            'description' => "Locked {$count} inventory entries from previous days.",
            'logged_at'   => now(),
        ]);

        $this->info("[VPM] Locked {$count} inventory entries.");
        return self::SUCCESS;
    }
}
