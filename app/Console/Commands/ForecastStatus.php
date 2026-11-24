<?php

namespace App\Console\Commands;

use App\Models\FishType;
use App\Models\Forecast;
use App\Services\ArimaMaximumLikelihood;
use App\Services\ArimaService;
use Illuminate\Console\Command;

class ForecastStatus extends Command
{
    protected $signature = 'forecast:status';

    protected $description = 'Inspect forecast history coverage and saved model versions without changing records.';

    public function handle(ArimaService $arima): int
    {
        $fish = FishType::where('is_active', true)->pluck('name', 'id');
        $coverage = $arima->historyCoverage($fish->keys()->all());
        $stored = Forecast::where('forecast_date', '>=', today())
            ->where('arima_params->model_version', ArimaMaximumLikelihood::VERSION)
            ->where('arima_params->history_days', config('forecast.history_days'))
            ->get(['fish_type_id', 'quality_class']);
        $minimum = (int) config('forecast.min_history');
        $window = (int) config('forecast.history_days');
        $this->info('Server date: '.today()->toDateString().' | Time zone: '.config('app.timezone'));
        $this->line("Training window: {$window} days | Required: {$minimum} consecutive recorded days ending at the latest observation");
        $this->line('Current model: '.ArimaMaximumLikelihood::VERSION);
        $this->table(['Fish', 'Class', "Days / {$window}", 'Consecutive days', 'Latest record', 'Age in days', 'Current forecast rows'],
            $coverage->sortByDesc('consecutive_days')->map(fn ($row) => [
                $fish[$row['fish_type_id']], $row['quality_class'], $row['recorded_days'], $row['consecutive_days'],
                $row['latest_record'], $row['age_days'], $stored->where('fish_type_id', $row['fish_type_id'])->where('quality_class', $row['quality_class'])->count(),
            ])->values()->all());
        $ready = $coverage->where('consecutive_days', '>=', $minimum)->count();
        $this->info("Series meeting the history requirement: {$ready}");
        $this->line('Upcoming rows from the current model: '.$stored->count());
        $this->line('Active fish without confirmed records in the window: '.($fish->count() - $coverage->pluck('fish_type_id')->unique()->count()));
        $this->line('Older history is advanced through each unobserved day; no missing inventory records are created.');

        return self::SUCCESS;
    }
}
