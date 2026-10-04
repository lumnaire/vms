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
 * Estimation is by method of moments (Hannan-Rissanen style): first-difference
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
     * Aggregate the historical overlay shown behind the forecast on the chart.
     * Shares the same metric definitions as buildSeries().
     *
     * @return Collection<string, float> date => value
     */
    public function historicalSeries(int $fishTypeId, string $quality, string $metric): Collection
    {
        $lookback = config('forecast.history_chart_days');

        return VendorInventory::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('status', 'confirmed')
            ->whereDate('entry_date', '<', today())
            ->whereDate('entry_date', '>=', today()->subDays($lookback))
            ->orderBy('entry_date')
            ->get()
            ->groupBy(fn ($e) => $e->entry_date->toDateString())
            ->map(function ($entries) use ($metric) {
                $value = match ($metric) {
                    'price' => (float) $entries->avg('price_per_kg'),
                    'supply' => (float) $entries->sum('stock_kg'),
                    default => 0.0,
                };

                return round($value, 2);
            });
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
        if (count($series) < config('forecast.min_history')) {
            return [];
        }

        // ── Step A — first difference  Δy[t] = y[t] − y[t−1] ──
        $diff = [];
        for ($i = 1, $n = count($series); $i < $n; $i++) {
            $diff[] = $series[$i] - $series[$i - 1];
        }

        $meanD = array_sum($diff) / count($diff);

        // ── Step B — AR(1) coefficient from lag-1 autocorrelation ──
        $phi = $this->ar1Coefficient($diff);

        // ── Step C — MA(1) coefficient from the AR(1) residuals ──
        // The residual for day t compares the *lagged* change against the current
        // one: e[t] = (d[t] - mu) - phi * (d[t-1] - mu). Pairing d[t] with itself
        // instead would make the residual a constant multiple of (d[t] - mu), and
        // since ar1Coefficient() is a scale-invariant ratio the estimator would
        // return phi for theta as well, collapsing the model to a single term.
        $residuals = [];
        $prevD = $meanD;
        foreach ($diff as $d) {
            $residuals[] = ($d - $meanD) - $phi * ($prevD - $meanD);
            $prevD = $d;
        }
        $theta = $this->ar1Coefficient($residuals);

        $sigma = sqrt($this->variance($residuals));
        $z = config('forecast.z');

        $currentVal = end($series);
        $prevDiff = end($diff);
        $prevRes = end($residuals) ?: 0.0;

        // ── Step D — roll forward the horizon ──
        $projections = [];
        for ($h = 1; $h <= config('forecast.horizon'); $h++) {
            // ARIMA(1,1,1) step: Δŷ[t+h] = μ + φ·Δy[t] + θ·ε[t]
            $forecastDiff = $meanD + $phi * ($prevDiff - $meanD) + $theta * $prevRes;
            $nextVal = max(0.0, $currentVal + $forecastDiff);

            // Multi-step uncertainty widens with the square root of the horizon
            $ci = $z * $sigma * sqrt($h);

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

        $trend = $this->classifyTrend(
            $projections[0]['value'],
            $projections[count($projections) - 1]['value']
        );

        foreach ($projections as $i => $point) {
            $projections[$i]['trend'] = $trend;
        }

        return $projections;
    }

    // ─────────────────────────────────────────────────────────────
    /**
     * Replace the stored rows for a series so the table holds exactly the live
     * horizon. The whole series is cleared rather than only the rows outside
     * the window, which keeps this idempotent: re-running the generator cannot
     * duplicate rows inside the horizon, and projections left over from an
     * earlier run or a previous horizon length are always discarded.
     */
    private function replaceForecastRows(int $fishTypeId, string $quality, string $metric, array $rows): void
    {
        Forecast::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('metric', $metric)
            ->delete();

        foreach ($rows as $row) {
            Forecast::create($row);
        }
    }

    /**
     * Classify a projected window as upward, downward or stable using the
     * configured fractional band.
     */
    private function classifyTrend(float $first, float $last): string
    {
        $threshold = config('forecast.trend_threshold');

        return match (true) {
            $last > $first * (1 + $threshold) => 'upward',
            $last < $first * (1 - $threshold) => 'downward',
            default => 'stable',
        };
    }

    /** Estimate an AR(1) coefficient via lag-1 autocorrelation */
    private function ar1Coefficient(array $series): float
    {
        $n = count($series);
        if ($n < 2) {
            return 0.0;
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

        return $den > 0 ? max(-0.99, min(0.99, $num / $den)) : 0.0;
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
