<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FishType;
use App\Models\Forecast;
use App\Models\VendorInventory;
use Carbon\Carbon;

/**
 * ForecastSeeder
 *
 * Pre-populates the forecasts table with 14-day ARIMA(1,1,1) projections
 * starting from June 7, 2026 (the day after the last seeded inventory date).
 *
 * Run AFTER VendorInventorySeeder so historical data exists.
 * Usage: php artisan db:seed --class=ForecastSeeder
 *
 * Or add to DatabaseSeeder after VendorInventorySeeder.
 */
class ForecastSeeder extends Seeder
{
    private const QUALITY_CLASSES = [
        'First Class', 'Second Class', 'Third Class', 'Fourth Class', 'Special Class',
    ];
    private const METRICS   = ['price', 'volume'];
    private const MIN_HISTORY = 7;
    private const HORIZON   = 14;

    public function run(): void
    {
        Forecast::truncate();

        $fishTypes = FishType::where('is_active', true)->orderBy('name')->get();
        $generated = 0;
        $skipped   = 0;

        foreach ($fishTypes as $fishType) {
            foreach (self::QUALITY_CLASSES as $quality) {
                foreach (self::METRICS as $metric) {
                    if ($this->forecastSeries($fishType->id, $quality, $metric)) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        $this->command->info("✅ ForecastSeeder: Generated {$generated} forecast series | Skipped {$skipped} (no data).");
    }

    private function forecastSeries(int $fishTypeId, string $quality, string $metric): bool
    {
        $raw = VendorInventory::where('fish_type_id', $fishTypeId)
            ->where('quality_class', $quality)
            ->where('status', 'confirmed')
            ->whereDate('entry_date', '<', Carbon::create(2026, 6, 7))
            ->whereDate('entry_date', '>=', Carbon::create(2026, 5, 1))
            ->orderBy('entry_date')
            ->get();

        if ($raw->isEmpty()) return false;

        $byDay = $raw->groupBy(fn($e) => Carbon::parse($e->entry_date)->toDateString())
            ->map(function ($entries) use ($metric) {
                return $metric === 'price'
                    ? (float) $entries->avg('price_per_kg')
                    : (float) $entries->sum('stock_kg');
            });

        $series = $byDay->values()->toArray();
        if (count($series) < self::MIN_HISTORY) return false;

        // First difference
        $diff = [];
        for ($i = 1; $i < count($series); $i++) {
            $diff[] = $series[$i] - $series[$i - 1];
        }

        $meanD = array_sum($diff) / count($diff);
        $phi   = $this->ar1($diff);

        $residuals = [];
        foreach ($diff as $d) {
            $predicted   = $meanD + $phi * ($d - $meanD);
            $residuals[] = $d - $predicted;
        }
        $theta = $this->ar1($residuals);

        $lastValue    = end($series);
        $lastDiff     = end($diff);
        $lastResidual = end($residuals) ?: 0.0;
        $sigma        = sqrt($this->variance($residuals));

        $forecasts  = [];
        $prevDiff   = $lastDiff;
        $prevRes    = $lastResidual;
        $currentVal = $lastValue;

        for ($h = 1; $h <= self::HORIZON; $h++) {
            $forecastDiff = $meanD + $phi * ($prevDiff - $meanD) + $theta * $prevRes;
            $nextVal      = max(0.0, $currentVal + $forecastDiff);
            $ci           = 1.96 * $sigma * sqrt($h);
            $forecasts[]  = [
                'value' => round($nextVal, 2),
                'min'   => round(max(0, $nextVal - $ci), 2),
                'max'   => round($nextVal + $ci, 2),
            ];
            $prevDiff   = $forecastDiff;
            $prevRes    = 0.0;
            $currentVal = $nextVal;
        }

        $first = $forecasts[0]['value'];
        $last  = end($forecasts)['value'];
        $trend = match(true) {
            $last > $first * 1.02 => 'upward',
            $last < $first * 0.98 => 'downward',
            default               => 'stable',
        };

        $arimaParams = [
            'p' => 1, 'd' => 1, 'q' => 1,
            'phi'       => round($phi, 4),
            'theta'     => round($theta, 4),
            'mean_diff' => round($meanD, 4),
        ];

        // Forecasts start June 7, 2026 (day after last seeded inventory)
        $forecastStart = Carbon::create(2026, 6, 7);

        foreach ($forecasts as $i => $f) {
            Forecast::create([
                'fish_type_id'    => $fishTypeId,
                'quality_class'   => $quality,
                'metric'          => $metric,
                'forecast_date'   => $forecastStart->copy()->addDays($i),
                'predicted_value' => $f['value'],
                'predicted_min'   => $f['min'],
                'predicted_max'   => $f['max'],
                'trend'           => $trend,
                'arima_params'    => json_encode($arimaParams),
                'generated_at'    => now(),
            ]);
        }

        return true;
    }

    private function ar1(array $s): float
    {
        $n = count($s);
        if ($n < 2) return 0.0;
        $mean = array_sum($s) / $n;
        $num = $den = 0.0;
        for ($i = 0; $i < $n - 1; $i++) $num += ($s[$i] - $mean) * ($s[$i + 1] - $mean);
        for ($i = 0; $i < $n; $i++)     $den += ($s[$i] - $mean) ** 2;
        return $den > 0 ? max(-0.99, min(0.99, $num / $den)) : 0.0;
    }

    private function variance(array $s): float
    {
        $n = count($s);
        if ($n < 2) return 0.0;
        $mean = array_sum($s) / $n;
        $sum  = 0.0;
        foreach ($s as $v) $sum += ($v - $mean) ** 2;
        return $sum / ($n - 1);
    }
}
