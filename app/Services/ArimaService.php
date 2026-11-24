<?php

namespace App\Services;

use App\Models\Forecast;
use App\Models\VendorInventory;
use Illuminate\Support\Collection;

/**
 * ArimaService
 *
 * Single source of truth for the ARIMA(1,1,1) forecasting engine used across
 * the application. Both `forecast:generate` (scheduled daily) and
 * `ForecastSeeder` delegate here so the model is defined exactly once.
 *
 * Two metrics are forecast for every fish_type x quality_class pair:
 *   price   — average price_per_kg per day
 *   supply  — total stock_kg brought into the market per day
 *
 * Coefficients are jointly fitted by exact Gaussian maximum likelihood using
 * ArimaMaximumLikelihood. The fixed order and rolling history follow the
 * thesis; Mardesci & Fitriani (2025), section 2.1, supports the MLE procedure.
 */
class ArimaService
{
    // ─────────────────────────────────────────────────────────────
    /**
     * Forecast one series and persist it. Returns the number of rows written,
     * or 0 when there is not enough history.
     */
    public function generate(int $fishTypeId, string $quality, string $metric): int
    {
        if (! in_array($metric, config('forecast.metrics'), true)) {
            return 0;
        }

        $series = $this->buildSeries($fishTypeId, $quality, $metric);

        if (count($series) < config('forecast.min_history')) {
            return 0;
        }

        // History ends yesterday. Predict through the unobserved current day
        // before publishing tomorrow and the following days.
        $projections = $this->project($series, 1);

        if (empty($projections)) {
            return 0;
        }

        $generatedAt = now();
        $startDate = today()->addDay();

        $rows = [];
        foreach ($projections as $i => $point) {
            $rows[] = [
                'fish_type_id' => $fishTypeId,
                'quality_class' => $quality,
                'metric' => $metric,
                'forecast_date' => $startDate->copy()->addDays($i),
                'predicted_value' => $point['value'],
                'predicted_min' => $point['min'],
                'predicted_max' => $point['max'],
                'trend' => $point['trend'],
                'arima_params' => $point['params'],
                'generated_at' => $generatedAt,
            ];
        }

        $this->replaceForecastRows($fishTypeId, $quality, $metric, $rows);

        return count($rows);
    }

    // ─────────────────────────────────────────────────────────────
    /**
     * Build the daily history used to fit the model: average price for
     * `price`, total stock for `supply`.
     *
     * @return array<int, float> chronological values
     */
    public function buildSeries(int $fishTypeId, string $quality, string $metric): array
    {
        $lookback = config('forecast.history_days');

        $raw = VendorInventory::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('status', 'confirmed')
            ->whereDate('entry_date', '<', today())
            ->whereDate('entry_date', '>=', today()->subDays($lookback))
            ->orderBy('entry_date')
            ->get();

        if ($raw->isEmpty()) {
            return [];
        }

        $daily = $raw->groupBy(fn ($e) => $e->entry_date->toDateString())
            ->map(function ($entries) use ($metric) {
                return match ($metric) {
                    'price' => (float) $entries->avg('price_per_kg'),
                    'supply' => (float) $entries->sum('stock_kg'),
                    default => 0.0,
                };
            });

        // ARIMA steps must represent equally spaced days. Use the most recent
        // consecutive segment ending yesterday; never compress missing dates
        // or manufacture zero deliveries from an absence of recorded batches.
        $series = [];
        for ($date = today()->subDay(); count($series) < $lookback; $date->subDay()) {
            if (! $daily->has($date->toDateString())) {
                break;
            }
            $series[] = $daily[$date->toDateString()];
        }

        return array_reverse($series);
    }

    /**
     * The historical overlay shown behind the forecast on the chart: one point
     * for every one of the last `history_chart_days` days (yesterday back), so
     * the chart always shows that many dots. Shares the metric definitions of
     * buildSeries().
     *
     * A day with no confirmed batch of the fish is filled and flagged:
     *   supply — 0 kg, because nothing came in;
     *   price  — the last known price, from earlier in the window or the most
     *            recent batch before it. With no price at all yet, null (blank).
     *
     * Empty when the window has no confirmed batch at all, so a fish with no
     * recent history does not draw a flat line of filled points.
     *
     * @return Collection<string, array{value: float|null, filled: bool}> date => point
     */
    public function historicalSeries(int $fishTypeId, string $quality, string $metric): Collection
    {
        $days = (int) config('forecast.history_chart_days');
        $start = today()->subDays($days);

        $base = fn () => VendorInventory::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('status', 'confirmed');

        $actual = $base()
            ->whereDate('entry_date', '<', today())
            ->whereDate('entry_date', '>=', $start->toDateString())
            ->orderBy('entry_date')
            ->get()
            ->groupBy(fn ($e) => $e->entry_date->toDateString())
            ->map(fn ($entries) => round(match ($metric) {
                'price' => (float) $entries->avg('price_per_kg'),
                'supply' => (float) $entries->sum('stock_kg'),
                default => 0.0,
            }, 2));

        if ($actual->isEmpty()) {
            return collect();
        }

        // The price carried into the window from the last batch before it.
        $lastPrice = null;
        if ($metric === 'price') {
            $lastDay = $base()->whereDate('entry_date', '<', $start->toDateString())
                ->orderByDesc('entry_date')
                ->first()?->entry_date->toDateString();
            $lastPrice = $lastDay
                ? round((float) $base()->whereDate('entry_date', $lastDay)->avg('price_per_kg'), 2)
                : null;
        }

        $series = collect();
        for ($date = $start->copy(); $date->lt(today()); $date->addDay()) {
            $key = $date->toDateString();

            if ($actual->has($key)) {
                $series[$key] = ['value' => $actual[$key], 'filled' => false];
                $lastPrice = $actual[$key];

                continue;
            }

            $series[$key] = [
                'value' => $metric === 'price' ? $lastPrice : 0.0,
                'filled' => true,
            ];
        }

        return $series;
    }

    // ─────────────────────────────────────────────────────────────
    /**
     * Fit ARIMA(1,1,1) on a chronological series and roll the model forward
     * for the configured horizon.
     *
     * @param  array<int, float>  $series
     * @return array<int, array{value: float, min: float, max: float, trend: string, params: array}>
     */
    public function project(array $series, int $offset = 0): array
    {
        return $this->explain($series, $offset)['projections'];
    }

    /**
     * Run the production calculation and expose its working for the public
     * research guide. This is read-only: no inventory or forecasts are queried
     * or saved. Keep unrounded intermediate values for reproducible arithmetic.
     */
    public function explain(array $series, int $offset = 0): array
    {
        if (count($series) < config('forecast.min_history')) {
            return ['observations' => $series, 'projections' => []];
        }

        // ── Step A — first difference  Δy[t] = y[t] − y[t−1] ──
        $diff = [];
        for ($i = 1, $n = count($series); $i < $n; $i++) {
            $diff[] = $series[$i] - $series[$i - 1];
        }

        $fit = (new ArimaMaximumLikelihood)->fit($diff);
        if (! $fit['converged']) {
            return ['observations' => $series, 'projections' => [], 'fit' => $fit];
        }
        $meanD = $fit['mean'];
        $phi = $fit['phi'];
        $theta = $fit['theta'];
        $residuals = $fit['innovations'];
        $differenceSteps = [];
        $prevD = $meanD;
        foreach ($diff as $i => $d) {
            $differenceSteps[] = [
                'observation' => $i + 2,
                'previous_value' => $series[$i],
                'value' => $series[$i + 1],
                'difference' => $d,
                'centered' => $d - $meanD,
                'previous_difference' => $prevD,
                'residual' => $residuals[$i],
                'fitted_difference' => $d - $residuals[$i],
                'innovation_factor' => $fit['factors'][$i],
            ];
            $prevD = $d;
        }
        $sigma = sqrt($fit['sigma2']);
        $z = config('forecast.z');

        $currentVal = end($series);
        $prevDiff = end($diff);
        $prevRes = $fit['last_shock'];

        // ── Step D — roll forward the horizon ──
        $projections = [];
        $forecastSteps = [];
        $bridgeSteps = [];
        $shockSum = 0.0;
        $stateMultiplier = 0.0;
        $impulseSum = 0.0;
        for ($step = 1; $step <= config('forecast.horizon') + $offset; $step++) {
            // ARIMA(1,1,1) step: δ[h] = μ + φ·(previous δ − μ) + θ·previous e
            $forecastDiff = $meanD + $phi * ($prevDiff - $meanD) + $theta * $prevRes;
            $rawNextVal = $currentVal + $forecastDiff;
            $nextVal = max(0.0, $rawNextVal);

            // Integrated ARMA impulse responses, plus uncertainty in the last
            // filtered shock. This is the fitted model's forecast variance.
            $impulseSum += $step === 1 ? 1.0 : ($phi + $theta) * $phi ** ($step - 2);
            $shockSum += $impulseSum ** 2;
            $stateMultiplier += $phi ** ($step - 1);
            $forecastVariance = $fit['sigma2'] * $shockSum
                + ($theta * $stateMultiplier) ** 2 * $fit['last_shock_variance'];
            $ci = $z * sqrt(max(0.0, $forecastVariance));

            $working = [
                'h' => $step - $offset,
                'model_step' => $step,
                'previous_value' => $currentVal,
                'previous_difference' => $prevDiff,
                'previous_residual' => $prevRes,
                'ar_contribution' => $phi * ($prevDiff - $meanD),
                'ma_contribution' => $theta * $prevRes,
                'difference' => $forecastDiff,
                'unclipped_value' => $currentVal + $forecastDiff,
                'value' => $nextVal,
                'band' => $ci,
                'forecast_variance' => $forecastVariance,
            ];

            $prevDiff = $forecastDiff;
            $prevRes = 0.0;
            $currentVal = $rawNextVal;
            if ($step <= $offset) {
                $bridgeSteps[] = $working;

                continue;
            }
            $forecastSteps[] = $working;

            $projections[] = [
                'value' => round($nextVal, 2),
                'min' => round(max(0.0, $rawNextVal - $ci), 2),
                'max' => round(max(0.0, $rawNextVal + $ci), 2),
                'trend' => null,
                'params' => [
                    'p' => config('forecast.order.p'),
                    'd' => config('forecast.order.d'),
                    'q' => config('forecast.order.q'),
                    'phi' => round($phi, 4),
                    'theta' => round($theta, 4),
                    'mean_diff' => round($meanD, 4),
                    'sigma' => round($sigma, 4),
                    'horizon' => config('forecast.horizon'),
                    'model_version' => ArimaMaximumLikelihood::VERSION,
                    'estimation' => 'exact_gaussian_maximum_likelihood',
                    'fit_status' => $fit['status'],
                    'log_likelihood' => $fit['log_likelihood'],
                    'aic' => $fit['aic'],
                    'history_days' => config('forecast.history_days'),
                    'observations' => count($series),
                    'model_step' => $step,
                    'coefficients' => ['phi' => $phi, 'theta' => $theta, 'drift' => $meanD, 'innovation_variance' => $fit['sigma2']],
                    'training_start' => today()->subDays(count($series))->toDateString(),
                    'training_end' => today()->subDay()->toDateString(),
                ],
            ];
        }

        // The label compares where the forecast ends against the average of the
        // last few real days, i.e. "is this heading above or below this past
        // week?". Comparing forecast day 3 with forecast day 1 instead put only two
        // days between the two values, so a ±2% band could almost never be crossed
        // and every series read as stable.
        $baselineDays = max(1, (int) config('forecast.trend_baseline_days', 7));
        $recent = array_slice($series, -$baselineDays);
        $baseline = array_sum($recent) / count($recent);

        $trend = $this->classifyTrend($baseline, $projections[count($projections) - 1]['value']);

        foreach ($projections as $i => $point) {
            $projections[$i]['trend'] = $trend;
            $projections[$i]['params']['trend_baseline'] = round($baseline, 2);
        }

        $final = $projections[count($projections) - 1]['value'];

        return [
            'observations' => $series,
            'differences' => $diff,
            'difference_steps' => $differenceSteps,
            'mean_diff' => $meanD,
            'fit' => $fit,
            'sample_mean_diff' => array_sum($diff) / count($diff),
            'phi_fit' => ['coefficient' => $phi],
            'residuals' => $residuals,
            'theta_fit' => ['coefficient' => $theta],
            'residual_mean' => array_sum($residuals) / count($residuals),
            'residual_variance' => $fit['sigma2'],
            'sigma' => $sigma,
            'z' => $z,
            'forecast_steps' => $forecastSteps,
            'bridge_steps' => $bridgeSteps,
            'baseline_values' => $recent,
            'baseline' => $baseline,
            'lower_threshold' => $baseline * (1 - config('forecast.trend_threshold')),
            'upper_threshold' => $baseline * (1 + config('forecast.trend_threshold')),
            'final' => $final,
            'change_percent' => $baseline > 0 ? ($final - $baseline) / $baseline * 100 : null,
            'trend' => $trend,
            'projections' => $projections,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    /**
     * Store a run without losing the earlier ones. Each day's run is kept so a
     * past prediction can be compared with what actually came in, and so the
     * history is there when the model is retrained. Re-running on the same day
     * replaces only that day's run, which keeps the generator idempotent.
     */
    private function replaceForecastRows(int $fishTypeId, string $quality, string $metric, array $rows): void
    {
        $this->series($fishTypeId, $quality, $metric)
            ->whereDate('generated_at', today())
            ->delete();

        foreach ($rows as $row) {
            Forecast::create($row);
        }
    }

    /** Stored forecast rows of one series, every run. */
    private function series(int $fishTypeId, string $quality, string $metric)
    {
        return Forecast::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('metric', $metric);
    }

    /**
     * The rows of a series' most recent run, earliest day first.
     *
     * @return Collection<int, Forecast>
     */
    public function latestRun(int $fishTypeId, string $quality, string $metric): Collection
    {
        $latest = $this->series($fishTypeId, $quality, $metric)->max('generated_at');

        if ($latest === null) {
            return collect();
        }

        return $this->series($fishTypeId, $quality, $metric)
            ->where('generated_at', $latest)
            ->orderBy('forecast_date')
            ->get();
    }

    /**
     * What the model predicted for each past day of the chart window, from the
     * latest run made before that day. Lets the page put the forecast beside the
     * actual figure once the day has passed.
     *
     * @return Collection<string, float> date => predicted value
     */
    public function pastPredictions(int $fishTypeId, string $quality, string $metric): Collection
    {
        $start = today()->subDays((int) config('forecast.history_chart_days'));

        return $this->series($fishTypeId, $quality, $metric)
            ->whereDate('forecast_date', '>=', $start->toDateString())
            ->whereDate('forecast_date', '<', today()->toDateString())
            ->where('arima_params->model_version', ArimaMaximumLikelihood::VERSION)
            ->where('arima_params->history_days', config('forecast.history_days'))
            ->orderBy('generated_at')
            ->get()
            ->filter(fn (Forecast $f) => $f->generated_at->lt($f->forecast_date->copy()->startOfDay()))
            ->groupBy(fn (Forecast $f) => $f->forecast_date->toDateString())
            ->map(fn ($runs) => round((float) $runs->last()->predicted_value, 2))
            ->sortKeys();
    }

    /**
     * Classify the final projected value against the recent actual baseline
     * using the configured fractional band.
     */
    private function classifyTrend(float $baseline, float $last): string
    {
        $threshold = config('forecast.trend_threshold');

        return match (true) {
            $last > $baseline * (1 + $threshold) => 'upward',
            $last < $baseline * (1 - $threshold) => 'downward',
            default => 'stable',
        };
    }
}
