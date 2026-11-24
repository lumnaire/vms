<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\Forecast;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use App\Services\ArimaMaximumLikelihood;
use App\Services\ArimaService;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function index(Request $request, ArimaService $arima)
    {
        $qualityClasses = FishType::QUALITY_CLASSES;
        $metrics = $this->metricOptions();
        $horizon = config('forecast.horizon');
        $selectedMetric = $this->resolveMetric($request->input('metric'), array_keys($metrics));

        $storedSeries = Forecast::where('forecast_date', '>=', today())
            ->where('arima_params->model_version', ArimaMaximumLikelihood::VERSION)
            ->where('arima_params->history_days', config('forecast.history_days'))
            ->where('metric', $selectedMetric)
            ->select('fish_type_id', 'quality_class')
            ->distinct()
            ->get();
        $forecastFishIds = $storedSeries->pluck('fish_type_id')->flip();
        $storedKeys = $storedSeries->mapWithKeys(fn ($row) => [$row->fish_type_id.'|'.$row->quality_class => true]);

        $fishTypes = FishType::where('is_active', true)
            ->orderBy('name')
            ->get();
        $coverage = $arima->historyCoverage($fishTypes->modelKeys());
        $preferredHistory = $coverage
            ->when($request->filled('quality_class'), fn ($rows) => $rows->where('quality_class', $request->input('quality_class')))
            ->sort(fn ($a, $b) => [min($b['consecutive_days'], config('forecast.min_history')), -$b['age_days'], $b['consecutive_days']]
                <=> [min($a['consecutive_days'], config('forecast.min_history')), -$a['age_days'], $a['consecutive_days']])
            ->first();

        // After a model upgrade there may be no saved forecasts yet. Prefer a
        // fish with recent history so on-demand generation can actually run.
        $defaultFishTypeId = $forecastFishIds->isEmpty()
            ? ($preferredHistory['fish_type_id'] ?? $fishTypes->first()?->id)
            : VendorInventory::where('status', 'confirmed')
                ->whereIn('fish_type_id', $forecastFishIds->keys())
                ->whereDate('entry_date', '<', today())
                ->whereDate('entry_date', '>=', today()->subDays(config('forecast.history_days')))
                ->selectRaw('fish_type_id, COUNT(*) as n')
                ->groupBy('fish_type_id')
                ->orderByDesc('n')
                ->value('fish_type_id');

        $selectedFishTypeId = (int) $request->input('fish_type_id', $defaultFishTypeId);
        $selectedFishType = $fishTypes->firstWhere('id', $selectedFishTypeId);
        $defaultQuality = $storedSeries->firstWhere('fish_type_id', $selectedFishTypeId)?->quality_class
            ?? (($preferredHistory['fish_type_id'] ?? null) === $selectedFishTypeId ? $preferredHistory['quality_class'] : null)
            ?? $selectedFishType?->quality_class ?? 'First Class';
        $selectedQuality = $request->input('quality_class', $defaultQuality);

        $fishTypes->each(function ($fish) use ($coverage, $storedKeys, $selectedQuality) {
            $history = $coverage->get($fish->id.'|'.$selectedQuality);
            $fish->has_forecast = $storedKeys->has($fish->id.'|'.$selectedQuality);
            $fish->consecutive_history_days = $history['consecutive_days'] ?? 0;
            $fish->latest_history_date = $history['latest_record'] ?? null;
            $fish->history_age_days = $history['age_days'] ?? null;
            $fish->forecast_ready = $fish->consecutive_history_days >= config('forecast.min_history');
            $fish->forecast_label = $fish->has_forecast ? '' : ($fish->forecast_ready ? 'ready to forecast'
                : ($history === null ? 'no confirmed history' : $fish->consecutive_history_days.'/'.config('forecast.min_history').' consecutive days'));
        });

        // Confirmed daily history overlaid behind the forecast
        $historical = $arima->historicalSeries($selectedFishTypeId, $selectedQuality, $selectedMetric);

        $forecasts = $this->forecastSeries($arima, $selectedFishTypeId, $selectedQuality, $selectedMetric, $horizon);

        // Self-healing fallback: the stored rows are derived state that only the
        // scheduled `forecast:generate` run rebuilds. On shared hosting the cron
        // job is often absent, so the page fits the model itself whenever the
        // series has no run from today. That keeps the horizon on tomorrow and the
        // next days instead of showing a run that has partly passed. Earlier runs
        // are kept as history either way.
        $ranToday = $forecasts->isNotEmpty() && $forecasts->first()->generated_at->isToday()
            && ($forecasts->first()->arima_params['model_version'] ?? null) === ArimaMaximumLikelihood::VERSION
            && ($forecasts->first()->arima_params['history_days'] ?? null) === config('forecast.history_days');
        if (! $ranToday && $selectedFishType !== null) {
            if ($arima->generate($selectedFishTypeId, $selectedQuality, $selectedMetric) > 0) {
                $forecasts = $this->forecastSeries($arima, $selectedFishTypeId, $selectedQuality, $selectedMetric, $horizon);
                $selectedFishType->has_forecast = true;
                $selectedFishType->forecast_label = '';
            } elseif ($selectedFishType->forecast_ready) {
                $selectedFishType->forecast_label = 'model fit unavailable';
            }
        }

        $fishTypes = $fishTypes->sortBy(fn ($fish) => ($fish->has_forecast ? '0' : ($fish->forecast_ready ? '1' : '2')).strtolower($fish->name))->values();

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
            ->filter(fn ($f) => ($f->arima_params['model_version'] ?? null) === ArimaMaximumLikelihood::VERSION
                && ($f->arima_params['history_days'] ?? null) === config('forecast.history_days'))
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
     * @return array{days: int, mape_days: int, mape: float|null, mae: float|null, rmse: float|null}
     */
    private function accuracy($pastPredictions, $historical): array
    {
        $pairs = $pastPredictions->filter(fn ($v, $date) => isset($historical[$date])
            && ! $historical[$date]['filled'] && $historical[$date]['value'] !== null);
        $errors = $pairs->map(fn ($v, $date) => $v - $historical[$date]['value']);
        $percentageErrors = $pairs
            ->filter(fn ($v, $date) => $historical[$date]['value'] > 0)
            ->map(fn ($v, $date) => abs($v - $historical[$date]['value']) / $historical[$date]['value'] * 100);

        return [
            'days' => $errors->count(),
            'mape_days' => $percentageErrors->count(),
            'mape' => $percentageErrors->isEmpty() ? null : round($percentageErrors->avg(), 1),
            'mae' => $errors->isEmpty() ? null : round($errors->map(fn ($e) => abs($e))->avg(), 2),
            'rmse' => $errors->isEmpty() ? null : round(sqrt($errors->map(fn ($e) => $e ** 2)->avg()), 2),
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
