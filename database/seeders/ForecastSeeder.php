<?php

namespace Database\Seeders;

use App\Models\FishType;
use App\Models\Forecast;
use App\Services\ArimaService;
use Illuminate\Database\Seeder;

/**
 * ForecastSeeder
 *
 * Pre-populates the forecasts table with rolling ARIMA(1,1,1) projections for
 * price and supply, starting the day after the current date.
 *
 * Run AFTER VendorInventorySeeder so historical data exists.
 * Usage: php artisan db:seed --class=ForecastSeeder
 */
class ForecastSeeder extends Seeder
{
    public function run(ArimaService $arima): void
    {
        Forecast::truncate();

        $fishTypes = FishType::where('is_active', true)->orderBy('name')->get();
        $generated = 0;
        $skipped = 0;

        foreach ($fishTypes as $fishType) {
            foreach (FishType::QUALITY_CLASSES as $quality) {
                foreach (config('forecast.metrics') as $metric) {
                    if ($arima->generate($fishType->id, $quality, $metric) > 0) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        $this->command->info("✅ ForecastSeeder: Generated {$generated} forecast series | Skipped {$skipped} (no data).");
        $this->command->info('   📈 Horizon: '.config('forecast.horizon').' day(s) per series.');
    }
}
