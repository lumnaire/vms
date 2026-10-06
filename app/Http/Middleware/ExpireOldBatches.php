<?php

namespace App\Http\Middleware;

use App\Models\VendorInventory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes batches that reached the freshness limit off the stall, on the first
 * request of each day.
 *
 * The scheduled inventory:expire command does the same just after midnight,
 * but only when the scheduler is running, which a local XAMPP install usually
 * is not. Batches only expire when the date changes, so once a day is enough.
 */
class ExpireOldBatches
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'inventory:expired:'.today()->toDateString();

        if (! Cache::has($key)) {
            VendorInventory::expireOldBatches();
            Cache::put($key, true, now()->endOfDay());
        }

        return $next($request);
    }
}
