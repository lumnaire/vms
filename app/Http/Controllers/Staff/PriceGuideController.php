<?php

// ─────────────────────────────────────────────────────────────────
// Staff/PriceGuideController.php
// ─────────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Supervisor\PriceGuideController as SupervisorPriceGuideController;
use App\Services\MarketAnalytics;
use Illuminate\Http\Request;

/**
 * The official tariff, read-only, beside what vendors are charging on the board
 * right now: the standard price against each vendor's modified price.
 */
class PriceGuideController extends Controller
{
    public function index(Request $request, MarketAnalytics $analytics)
    {
        return view('staff.price-guides', SupervisorPriceGuideController::tablePayload($request, $analytics));
    }
}
