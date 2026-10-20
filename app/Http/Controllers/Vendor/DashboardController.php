<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use App\Services\MarketAnalytics;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(MarketAnalytics $analytics)
    {
        $vendorId = Auth::id();

        $todayEntries = VendorInventory::where('vendor_id', $vendorId)->whereDate('entry_date', today())->count();
        $confirmedEntries = VendorInventory::where('vendor_id', $vendorId)->where('status', 'confirmed')->whereDate('entry_date', today())->count();
        $pendingEntries = VendorInventory::where('vendor_id', $vendorId)->where('status', 'pending')->whereDate('entry_date', today())->count();

        // Remaining stock across every batch still on the stall, whichever day it
        // was submitted: stock minus what the vendor has released as sold.
        $openBatches = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->open()
            ->get();
        $remainingStock = (float) $openBatches->sum(fn ($item) => $item->getRemainingStock());

        // Today's batches for the dashboard table
        $todayInventory = VendorInventory::with('fishType')
            ->where('vendor_id', $vendorId)
            ->whereDate('entry_date', today())
            ->orderBy('fish_type_id')
            ->orderBy('batch_no')
            ->get();

        // ── Sales, from the releases this vendor recorded as sold ─────
        $sales = $analytics->dailySales($vendorId);
        $value = fn ($d) => $d['value'];
        $salesToday = $analytics->sumBetween($sales, today(), today(), $value);
        $salesYesterday = $analytics->sumBetween($sales, today()->subDay(), today()->subDay(), $value);
        $salesChange = $analytics->change($salesToday, $salesYesterday);
        $salesMonth = $analytics->monthToDate($sales, $value);
        $tuboMonth = $analytics->monthToDate($sales, fn ($d) => $d['tubo']);
        $bestDay = $analytics->bestDay($sales, $value);

        $charts = $analytics->periodPayload([
            'sales' => [$sales, $value],
            'tubo' => [$sales, fn ($d) => $d['tubo']],
        ]);
        foreach (array_keys(MarketAnalytics::PERIODS) as $period) {
            $charts[$period]['outcome'] = $analytics->stockOutcome($analytics->windowStart($period), $vendorId);
        }

        // ── My prices against the tariff and the rest of the board ────
        $board = $analytics->boardByFish();
        $tariffs = PriceGuide::tariffMap();
        $myPrices = $openBatches
            ->groupBy(fn ($b) => PriceGuide::key($b->fish_type_id, $b->quality_class))
            ->map(function ($batches, $key) use ($board, $tariffs, $vendorId) {
                $price = (float) $batches->max('price_per_kg');
                $live = $board->get($key);

                return [
                    'fish' => $batches->first()->fishType?->name ?? '—',
                    'quality_class' => $batches->first()->quality_class,
                    'price' => $price,
                    'comparison' => PriceGuide::compare($price, $tariffs[$key] ?? null),
                    'market_min' => $live['min'] ?? null,
                    'market_max' => $live['max'] ?? null,
                    'stalls' => $live ? $live['stalls']->count() : 0,
                    'is_top_markup' => ($live['top_markup']['vendor_id'] ?? null) === $vendorId,
                ];
            })
            ->sortBy('fish')
            ->values();

        return view('vendor.dashboard', compact(
            'todayEntries',
            'confirmedEntries',
            'pendingEntries',
            'remainingStock',
            'todayInventory',
            'salesToday',
            'salesChange',
            'salesMonth',
            'tuboMonth',
            'bestDay',
            'charts',
            'myPrices',
        ));
    }
}
