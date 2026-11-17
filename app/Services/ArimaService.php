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
 * The custom moment-based fit uses lag-1 autocorrelations: first-difference
 * the series, take the AR(1) coefficient from the lag-1 autocorrelation of the
 * differenced series, then take the MA(1) coefficient from the lag-1
 * autocorrelation of the resulting lagged AR(1) residuals. Forecasts are rolled
 * forward recursively, with the MA term contributing to the one-step-ahead
 * expectation only, since future innovations have a mean of zero.
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

        $projections = $this->project($series);

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

        return $raw->groupBy(fn ($e) => $e->entry_date->toDateString())
            ->map(function ($entries) use ($metric) {
                return match ($metric) {
                    'price' => (float) $entries->avg('price_per_kg'),
                    'supply' => (float) $entries->sum('stock_kg'),
                    default => 0.0,
                };
            })
            ->values()
            ->toArray();
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
    public function project(array $series): array
    {
        return $this->explain($series)['projections'];
    }

    /**
     * Run the production calculation and expose its working for the public
     * research guide. This is read-only: no inventory or forecasts are queried
     * or saved. Keep unrounded intermediate values for reproducible arithmetic.
     */
    public function explain(array $series): array
    {
        if (count($series) < config('forecast.min_history')) {
            return ['observations' => $series, 'projections' => []];
        }

        // ── Step A — first difference  Δy[t] = y[t] − y[t−1] ──
        $diff = [];
        for ($i = 1, $n = count($series); $i < $n; $i++) {
            $diff[] = $series[$i] - $series[$i - 1];
        }

        $meanD = array_sum($diff) / count($diff);

        // ── Step B — AR(1) coefficient from lag-1 autocorrelation ──
        $phiFit = $this->autocorrelation($diff);
        $phi = $phiFit['coefficient'];

        // ── Step C — MA(1) coefficient from the AR(1) residuals ──
        // The residual for day t compares the *lagged* change against the current
        // one: e[t] = (d[t] - mu) - phi * (d[t-1] - mu). Pairing d[t] with itself
        // instead would make the residual a constant multiple of (d[t] - mu), and
        // since autocorrelation() is a scale-invariant ratio the estimator would
        // return phi for theta as well, collapsing the model to a single term.
        $residuals = [];
        $differenceSteps = [];
        $prevD = $meanD;
        foreach ($diff as $i => $d) {
            $residual = ($d - $meanD) - $phi * ($prevD - $meanD);
            $residuals[] = $residual;
            $differenceSteps[] = [
                'observation' => $i + 2,
                'previous_value' => $series[$i],
                'value' => $series[$i + 1],
                'difference' => $d,
                'centered' => $d - $meanD,
                'previous_difference' => $prevD,
                'residual' => $residual,
            ];
            $prevD = $d;
        }
        $thetaFit = $this->autocorrelation($residuals);
        $theta = $thetaFit['coefficient'];

        $sigma = sqrt($this->variance($residuals));
        $z = config('forecast.z');

        $currentVal = end($series);
        $prevDiff = end($diff);
        $prevRes = end($residuals) ?: 0.0;

        // ── Step D — roll forward the horizon ──
        $projections = [];
        $forecastSteps = [];
        for ($h = 1; $h <= config('forecast.horizon'); $h++) {
            // ARIMA(1,1,1) step: δ[h] = μ + φ·(previous δ − μ) + θ·previous e
            $forecastDiff = $meanD + $phi * ($prevDiff - $meanD) + $theta * $prevRes;
            $nextVal = max(0.0, $currentVal + $forecastDiff);

            // Multi-step uncertainty widens with the square root of the horizon
            $ci = $z * $sigma * sqrt($h);

            $forecastSteps[] = [
                'h' => $h,
                'previous_value' => $currentVal,
                'previous_difference' => $prevDiff,
                'previous_residual' => $prevRes,
                'ar_contribution' => $phi * ($prevDiff - $meanD),
                'ma_contribution' => $theta * $prevRes,
                'difference' => $forecastDiff,
                'unclipped_value' => $currentVal + $forecastDiff,
                'value' => $nextVal,
                'band' => $ci,
            ];

            $projections[] = [
                'value' => round($nextVal, 2),
                'min' => round(max(0.0, $nextVal - $ci), 2),
                'max' => round($nextVal + $ci, 2),
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
                ],
            ];

            $prevDiff = $forecastDiff;
            $prevRes = 0.0;
            $currentVal = $nextVal;
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
            'phi_fit' => $phiFit,
            'residuals' => $residuals,
            'theta_fit' => $thetaFit,
            'residual_mean' => array_sum($residuals) / count($residuals),
            'residual_variance' => $this->variance($residuals),
            'sigma' => $sigma,
            'z' => $z,
            'forecast_steps' => $forecastSteps,
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

    /** Estimate a coefficient via lag-1 autocorrelation, retaining its working. */
    private function autocorrelation(array $series): array
    {
        $n = count($series);
        if ($n < 2) {
            return [
                'mean' => $n ? array_sum($series) / $n : 0.0,
                'numerator' => 0.0,
                'denominator' => 0.0,
                'ratio' => 0.0,
                'coefficient' => 0.0,
            ];
        }

        $mean = array_sum($series) / $n;
        $num = 0.0;
        $den = 0.0;

        for ($i = 0; $i < $n - 1; $i++) {
            $num += ($series[$i] - $mean) * ($series[$i + 1] - $mean);
        }
        for ($i = 0; $i < $n; $i++) {
            $den += ($series[$i] - $mean) ** 2;
        }

        $ratio = $den > 0 ? $num / $den : 0.0;

        return [
            'mean' => $mean,
            'numerator' => $num,
            'denominator' => $den,
            'ratio' => $ratio,
            'coefficient' => max(-0.99, min(0.99, $ratio)),
        ];
    }

    /** Sample variance */
    private function variance(array $series): float
    {
        $n = count($series);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($series) / $n;
        $sum = 0.0;
        foreach ($series as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return $sum / ($n - 1);
    }
}
