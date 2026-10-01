<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\Forecast;
use App\Services\ArimaService;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function index(Request $request, ArimaService $arima)
    {
        $fishTypes = FishType::where('is_active', true)->orderBy('name')->get();
        $qualityClasses = FishType::QUALITY_CLASSES;
        $metrics = $this->metricOptions();
        $horizon = config('forecast.horizon');

        $selectedFishTypeId = (int) $request->input('fish_type_id', $fishTypes->first()?->id);
        $selectedFishType = $fishTypes->firstWhere('id', $selectedFishTypeId);
        $selectedQuality = $request->input('quality_class', $selectedFishType?->quality_class ?? 'First Class');
        $selectedMetric = $this->resolveMetric($request->input('metric'), array_keys($metrics));

        // Confirmed daily history overlaid behind the forecast
        $historical = $arima->historicalSeries($selectedFishTypeId, $selectedQuality, $selectedMetric);

        $forecasts = $this->forecastSeries($selectedFishTypeId, $selectedQuality, $selectedMetric, $horizon);

        // Self-healing fallback: the stored rows are derived state that only the
        // scheduled `forecast:generate` run rebuilds. On shared hosting the cron
        // job is often absent, and a migration that clears the table (see
        // switch_forecasts_to_three_day_supply_demand) wipes it entirely, leaving
        // the page permanently empty even though history exists to fit the model.
        // Generating on demand keeps the page correct without relying on a cron.
        if ($forecasts->isEmpty() && $selectedFishType !== null) {
            if ($arima->generate($selectedFishTypeId, $selectedQuality, $selectedMetric) > 0) {
                $forecasts = $this->forecastSeries($selectedFishTypeId, $selectedQuality, $selectedMetric, $horizon);
            }
        }

        // Trend indicator carried on every row of the generated series
        $latestForecast = $forecasts->first();
        $trendLabel = $latestForecast?->trend ?? null;

        return view('supervisor.forecasts', compact(
            'fishTypes',
            'qualityClasses',
            'metrics',
            'horizon',
            'selectedFishTypeId',
            'selectedQuality',
            'selectedMetric',
            'forecasts',
            'historical',
            'latestForecast',
            'trendLabel',
        ));
    }

    /** Stored forecast rows inside the live horizon for one series */
    private function forecastSeries(int $fishTypeId, string $quality, string $metric, int $horizon)
    {
        return Forecast::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('metric', $metric)
            ->where('forecast_date', '>=', today())
            ->orderBy('forecast_date')
            ->take($horizon)
            ->get();
    }

    /** Metric keys mapped to their display labels, e.g. 'price' => 'Price (₱/kg)' */
    private function metricOptions(): array
    {
        $meta = config('forecast.metric_meta');

        return collect(config('forecast.metrics'))
            ->mapWithKeys(fn ($key) => [$key => $meta[$key]['label'] ?? ucfirst($key)])
            ->all();
    }

    /** Guard the metric query parameter against unknown values */
    private function resolveMetric(?string $requested, array $allowed): string
    {
        return in_array($requested, $allowed, true) ? $requested : $allowed[0];
    }
}
