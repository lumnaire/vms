<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\Forecast;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use App\Services\ArimaService;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function index(Request $request, ArimaService $arima)
    {
        $qualityClasses = FishType::QUALITY_CLASSES;
        $metrics = $this->metricOptions();
        $horizon = config('forecast.horizon');

        // Fish that currently have a forecast are listed first, so the dropdown
        // does not open on one of the many fish nobody has sold yet.
        $forecastFishIds = Forecast::where('forecast_date', '>=', today())
            ->distinct()
            ->pluck('fish_type_id')
            ->flip();

        $fishTypes = FishType::where('is_active', true)
            ->orderBy('name')
            ->get()
            // A block body, not an arrow fn: each() stops at a callback that
            // returns false, which the assignment would for the first fish
            // without a forecast.
            ->each(function ($ft) use ($forecastFishIds) {
                $ft->has_forecast = $forecastFishIds->has($ft->id);
            })
            ->sortBy(fn ($ft) => ($ft->has_forecast ? '0' : '1').strtolower($ft->name))
            ->values();

        // With nothing picked, open on the forecast fish with the most history:
        // the series the model has learned the most from.
        $defaultFishTypeId = $forecastFishIds->isEmpty()
            ? $fishTypes->first()?->id
            : VendorInventory::where('status', 'confirmed')
                ->whereIn('fish_type_id', $forecastFishIds->keys())
                ->whereDate('entry_date', '>=', today()->subDays(config('forecast.history_days')))
                ->selectRaw('fish_type_id, COUNT(*) as n')
                ->groupBy('fish_type_id')
                ->orderByDesc('n')
                ->value('fish_type_id');

        $selectedFishTypeId = (int) $request->input('fish_type_id', $defaultFishTypeId);
        $selectedFishType = $fishTypes->firstWhere('id', $selectedFishTypeId);
        $selectedQuality = $request->input('quality_class', $selectedFishType?->quality_class ?? 'First Class');
        $selectedMetric = $this->resolveMetric($request->input('metric'), array_keys($metrics));

        // Confirmed daily history overlaid behind the forecast
        $historical = $arima->historicalSeries($selectedFishTypeId, $selectedQuality, $selectedMetric);

        $forecasts = $this->forecastSeries($arima, $selectedFishTypeId, $selectedQuality, $selectedMetric, $horizon);

        // Self-healing fallback: the stored rows are derived state that only the
        // scheduled `forecast:generate` run rebuilds. On shared hosting the cron
        // job is often absent, so the page fits the model itself whenever the
        // series has no run from today. That keeps the horizon on tomorrow and the
        // next days instead of showing a run that has partly passed. Earlier runs
        // are kept as history either way.
        $ranToday = $forecasts->isNotEmpty() && $forecasts->first()->generated_at->isToday();
        if (! $ranToday && $selectedFishType !== null) {
            if ($arima->generate($selectedFishTypeId, $selectedQuality, $selectedMetric) > 0) {
                $forecasts = $this->forecastSeries($arima, $selectedFishTypeId, $selectedQuality, $selectedMetric, $horizon);
                $selectedFishType->has_forecast = true;
            }
        }

        // Trend indicator carried on every row of the generated series
        $latestForecast = $forecasts->first();
        $trendLabel = $latestForecast?->trend ?? null;

        // The summary cards, the chart markers and the table badges all read this
        // one summary, so they cannot disagree.
        $summary = $this->summarise($forecasts);

        // Price forecasts are drawn against the fish's current tariff.
        $tariff = $selectedMetric === 'price' && $selectedFishType
            ? PriceGuide::tariffFor($selectedFishTypeId, $selectedQuality)
            : null;

        // What earlier runs predicted for the days that have since passed, beside
        // what actually came in.
        $pastPredictions = $arima->pastPredictions($selectedFishTypeId, $selectedQuality, $selectedMetric);
        $accuracy = $this->accuracy($pastPredictions, $historical);

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
            'summary',
            'tariff',
            'pastPredictions',
            'accuracy',
        ));
    }

    /** The latest run's rows inside the live horizon for one series */
    private function forecastSeries(ArimaService $arima, int $fishTypeId, string $quality, string $metric, int $horizon)
    {
        return $arima->latestRun($fishTypeId, $quality, $metric)
            ->filter(fn ($f) => $f->forecast_date->gte(today()))
            ->take($horizon)
            ->values();
    }

    /**
     * Average, highest and lowest of the forecast days, with the day each one
     * falls on. Highest and lowest are the predicted values themselves, never the
     * confidence band, so they are points the chart and the table both show.
     *
     * @return array{avg: float|null, high: array|null, low: array|null, flat: bool, high_day: int|null, low_day: int|null}
     */
    private function summarise($forecasts): array
    {
        if ($forecasts->isEmpty()) {
            return ['avg' => null, 'high' => null, 'low' => null, 'flat' => false, 'high_day' => null, 'low_day' => null];
        }

        $points = $forecasts->values()->map(fn ($f, $i) => [
            'day' => $i + 1,
            'date' => $f->forecast_date,
            'value' => round((float) $f->predicted_value, 2),
        ]);

        // Ties go to the earliest day.
        $high = $points->sortBy([['value', 'desc'], ['day', 'asc']])->first();
        $low = $points->sortBy([['value', 'asc'], ['day', 'asc']])->first();

        return [
            'avg' => round($points->avg('value'), 2),
            'high' => $high,
            'low' => $low,
            'flat' => abs($high['value'] - $low['value']) < 0.005,
            'high_day' => $high['day'],
            'low_day' => $low['day'],
        ];
    }

    /**
     * Mean absolute percentage error of past predictions against the days that
     * had real batches (filled days are not actuals).
     *
     * @return array{days: int, mape: float|null}
     */
    private function accuracy($pastPredictions, $historical): array
    {
        $errors = $pastPredictions
            ->filter(fn ($v, $date) => isset($historical[$date]) && ! $historical[$date]['filled'] && $historical[$date]['value'] > 0)
            ->map(fn ($v, $date) => abs($v - $historical[$date]['value']) / $historical[$date]['value'] * 100);

        return [
            'days' => $errors->count(),
            'mape' => $errors->isEmpty() ? null : round($errors->avg(), 1),
        ];
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
