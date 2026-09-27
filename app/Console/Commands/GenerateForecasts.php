<?php

namespace App\Console\Commands;

use App\Models\FishType;
use App\Services\ArimaService;
use Illuminate\Console\Command;

/**
 * GenerateForecasts
 *
 * Generates rolling ARIMA(1,1,1) forecasts for fish price, supply volume and
 * consumer demand. For each active fish type x quality class x metric that has
 * enough confirmed history, it writes the configured forecast horizon
 * (see config/forecast.php) into the forecasts table.
 *
 * Schedule: daily at midnight (registered in bootstrap/app.php).
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
                            {--quality_class= : Limit to a specific quality class}
                            {--metric= : Limit to a specific metric (price, supply, demand)}';

    protected $description = 'Generate rolling ARIMA(1,1,1) forecasts for fish price, supply and demand.';

    public function handle(ArimaService $arima): int
    {
        $horizon   = config('forecast.horizon');
        $this->info('[VPM] Starting forecast generation — ' . now()->toDateTimeString());
        $this->info("[VPM] Horizon: {$horizon} day(s) · Metrics: " . implode(', ', config('forecast.metrics')));

        $fishTypes = FishType::where('is_active', true)
            ->when($this->option('fish_type_id'), fn($q) => $q->where('id', $this->option('fish_type_id')))
            ->orderBy('name')
            ->get();

        if ($fishTypes->isEmpty()) {
            $this->warn('No active fish types found.');
            return self::SUCCESS;
        }

        $qualityFilter = $this->option('quality_class');
        $qualities     = $qualityFilter ? [$qualityFilter] : FishType::QUALITY_CLASSES;

        $metricFilter = $this->option('metric');
        $metrics      = $metricFilter ? [$metricFilter] : config('forecast.metrics');

        $generated = 0;
        $skipped   = 0;

        foreach ($fishTypes as $fishType) {
            foreach ($qualities as $quality) {
                foreach ($metrics as $metric) {
                    $rows = $arima->generate($fishType->id, $quality, $metric);

                    if ($rows > 0) {
                        $generated++;
                        $this->line("  ✓ {$fishType->name} | {$quality} | {$metric} → {$rows} day(s)");
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        $this->info("[VPM] Done. Generated: {$generated} series | Skipped (insufficient data): {$skipped} series.");
        return self::SUCCESS;
    }
}
