<?php

namespace App\Console\Commands;

use App\Models\FishType;
use App\Models\Forecast;
use App\Models\VendorInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * GenerateForecasts
 *
 * Runs a simple ARIMA(1,1,1)-style forecast using PHP arithmetic on historical
 * confirmed vendor_inventories data.  For each active fish type × quality class
 * × metric (price / volume) combination that has at least 7 days of history,
 * it generates 14 daily forecast rows and upserts them into the forecasts table.
 *
 * Schedule: daily at midnight (registered in bootstrap/app.php or Console Kernel).
 *
 * Usage:
 *   php artisan forecast:generate
 *   php artisan forecast:generate --fish_type_id=3
 *   php artisan forecast:generate --quality_class="First Class"
 */
class GenerateForecasts extends Command
{
    protected $signature = 'forecast:generate
                            {--fish_type_id= : Limit to a specific fish type ID}
                            {--quality_class= : Limit to a specific quality class}';

    protected $description = 'Generate 14-day ARIMA(1,1,1) rolling forecasts for fish price and supply volume.';

    // Quality classes defined in the system
    private const QUALITY_CLASSES = [
        'First Class',
        'Second Class',
        'Third Class',
        'Fourth Class',
        'Special Class',
    ];

    private const METRICS = ['price', 'volume'];

    // Minimum historical data points needed before forecasting
    private const MIN_HISTORY = 7;

    // Forecast horizon
    private const HORIZON = 14;

    // ─────────────────────────────────────────────────────────────
    public function handle(): int
    {
        $this->info('[VPM] Starting forecast generation — ' . now()->toDateTimeString());

        $fishTypes = FishType::where('is_active', true)
            ->when($this->option('fish_type_id'), fn($q) => $q->where('id', $this->option('fish_type_id')))
            ->orderBy('name')
            ->get();

        if ($fishTypes->isEmpty()) {
            $this->warn('No active fish types found.');
            return self::SUCCESS;
        }

        $qualityFilter = $this->option('quality_class');
        $qualities     = $qualityFilter ? [$qualityFilter] : self::QUALITY_CLASSES;

        $generated = 0;
        $skipped   = 0;

        foreach ($fishTypes as $fishType) {
            foreach ($qualities as $quality) {
                foreach (self::METRICS as $metric) {
                    $result = $this->forecastSeries($fishType->id, $quality, $metric);
                    if ($result) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        $this->info("[VPM] Done. Generated: {$generated} series | Skipped (insufficient data): {$skipped} series.");
        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────
    /**
     * Build and save a 14-day forecast for one fish_type × quality × metric.
     * Returns true on success, false if there is not enough history.
     */
    private function forecastSeries(int $fishTypeId, string $quality, string $metric): bool
    {
        // ── 1. Pull historical daily values (last 90 confirmed days) ──
        $raw = VendorInventory::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('status', 'confirmed')
            ->whereDate('entry_date', '<', today())
            ->whereDate('entry_date', '>=', today()->subDays(90))
            ->orderBy('entry_date')
            ->get();

        if ($raw->isEmpty()) {
            return false;
        }

        // Aggregate per day: avg price or sum volume
        $byDay = $raw->groupBy(fn($e) => $e->entry_date->toDateString())
            ->map(function ($entries) use ($metric) {
                return $metric === 'price'
                    ? (float) $entries->avg('price_per_kg')
                    : (float) $entries->sum('stock_kg');
            });

        $series = $byDay->values()->toArray();   // chronological float array

        if (count($series) < self::MIN_HISTORY) {
            return false;
        }

        // ── 2. ARIMA(1,1,1) estimation via method of moments ──────────
        // Step A – first difference  Δy[t] = y[t] − y[t−1]
        $diff = [];
        for ($i = 1; $i < count($series); $i++) {
            $diff[] = $series[$i] - $series[$i - 1];
        }

        $n       = count($diff);
        $meanD   = array_sum($diff) / $n;

        // AR(1) coefficient φ via lag-1 autocorrelation of the differenced series
        $phi = $this->ar1Coefficient($diff);

        // MA(1) coefficient θ estimated from residuals
        $residuals = [];
        $prevRes   = 0.0;
        foreach ($diff as $d) {
            $predicted   = $meanD + $phi * ($d - $meanD);
            $residuals[] = $d - $predicted;
            $prevRes     = end($residuals);
        }
        $theta = $this->ar1Coefficient($residuals);

        // ── 3. Roll forward 14 days ───────────────────────────────────
        $lastValue   = end($series);
        $lastDiff    = end($diff);
        $lastResidual = end($residuals) ?: 0.0;

        // Variance of residuals → used for confidence interval (±1.96σ)
        $variance = $this->variance($residuals);
        $sigma    = sqrt($variance);

        $forecasts  = [];
        $prevDiff   = $lastDiff;
        $prevRes    = $lastResidual;
        $currentVal = $lastValue;

        for ($h = 1; $h <= self::HORIZON; $h++) {
            // ARIMA(1,1,1) step: Δŷ[t+h] = μ + φ·Δy[t] + θ·ε[t]
            $forecastDiff = $meanD + $phi * ($prevDiff - $meanD) + $theta * $prevRes;
            $nextVal      = $currentVal + $forecastDiff;

            // Clamp to non-negative (prices/kg cannot go below 0)
            $nextVal = max(0.0, $nextVal);

            // Widen CI with horizon (multi-step uncertainty)
            $ci          = 1.96 * $sigma * sqrt($h);
            $forecasts[] = [
                'value' => round($nextVal, 2),
                'min'   => round(max(0, $nextVal - $ci), 2),
                'max'   => round($nextVal + $ci, 2),
            ];

            // Prepare for next step: residual becomes 0 for multi-step
            $prevDiff   = $forecastDiff;
            $prevRes    = 0.0;
            $currentVal = $nextVal;
        }

        // ── 4. Determine overall trend ────────────────────────────────
        $first = $forecasts[0]['value'];
        $last  = end($forecasts)['value'];
        $trend = match (true) {
            $last > $first * 1.02  => 'upward',
            $last < $first * 0.98  => 'downward',
            default                => 'stable',
        };

        // ── 5. Upsert into forecasts table ────────────────────────────
        $generatedAt = now();
        $arimaParams = [
            'p' => 1, 'd' => 1, 'q' => 1,
            'phi' => round($phi, 4),
            'theta' => round($theta, 4),
            'mean_diff' => round($meanD, 4),
        ];

        // Delete stale rows for this series (today onward) then insert fresh ones
        Forecast::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('metric', $metric)
            ->where('forecast_date', '>=', today())
            ->delete();

        foreach ($forecasts as $i => $f) {
            Forecast::create([
                'fish_type_id'    => $fishTypeId,
                'quality_class'   => $quality,
                'metric'          => $metric,
                'forecast_date'   => today()->addDays($i + 1),
                'predicted_value' => $f['value'],
                'predicted_min'   => $f['min'],
                'predicted_max'   => $f['max'],
                'trend'           => $trend,
                'arima_params'    => $arimaParams,
                'generated_at'    => $generatedAt,
            ]);
        }

        $this->line("  ✓ {$fishTypeId} | {$quality} | {$metric} → {$trend}");
        return true;
    }

    // ─────────────────────────────────────────────────────────────
    /** Estimate AR(1) coefficient via lag-1 autocorrelation */
    private function ar1Coefficient(array $series): float
    {
        $n    = count($series);
        if ($n < 2) return 0.0;

        $mean = array_sum($series) / $n;
        $num  = 0.0;
        $den  = 0.0;

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
        if ($n < 2) return 0.0;
        $mean = array_sum($series) / $n;
        $sum  = 0.0;
        foreach ($series as $v) {
            $sum += ($v - $mean) ** 2;
        }
        return $sum / ($n - 1);
    }
}
