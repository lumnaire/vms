<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\Forecast;
use App\Models\VendorInventory;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $fishTypes      = FishType::where('is_active', true)->orderBy('name')->get();
        $qualityClasses = ['First Class', 'Second Class', 'Third Class', 'Fourth Class', 'Special Class'];
        $metrics        = ['price' => 'Price (₱/kg)', 'volume' => 'Supply (kg)'];

        $selectedFishTypeId = (int) $request->input('fish_type_id', $fishTypes->first()?->id);
        $selectedQuality    = $request->input('quality_class', 'First Class');
        $selectedMetric     = $request->input('metric', 'price');

        // 14-day rolling forecast from the forecasts table
        $forecasts = Forecast::where('fish_type_id', $selectedFishTypeId)
            ->where('quality_class', $selectedQuality)
            ->where('metric', $selectedMetric)
            ->where('forecast_date', '>=', today())
            ->orderBy('forecast_date')
            ->take(14)
            ->get();

        // Historical data: last 30 confirmed days, averaged per day
        $historicalRaw = VendorInventory::where('fish_type_id', $selectedFishTypeId)
            ->where('quality_class', $selectedQuality)
            ->where('status', 'confirmed')
            ->whereDate('entry_date', '>=', today()->subDays(30))
            ->whereDate('entry_date', '<', today())
            ->orderBy('entry_date')
            ->get();

        $historical = $historicalRaw
            ->groupBy(fn($e) => $e->entry_date->toDateString())
            ->map(function ($dayEntries) use ($selectedMetric) {
                return $selectedMetric === 'price'
                    ? round($dayEntries->avg('price_per_kg'), 2)
                    : round($dayEntries->sum('stock_kg'), 2);
            });

        // Latest trend indicator from the forecast
        $latestForecast = $forecasts->first();
        $trendLabel     = $latestForecast?->trend ?? null;

        return view('supervisor.forecasts', compact(
            'fishTypes',
            'qualityClasses',
            'metrics',
            'selectedFishTypeId',
            'selectedQuality',
            'selectedMetric',
            'forecasts',
            'historical',
            'latestForecast',
            'trendLabel',
        ));
    }
}