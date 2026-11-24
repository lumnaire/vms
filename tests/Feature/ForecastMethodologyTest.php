<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\Forecast;
use App\Models\User;
use App\Models\VendorInventory;
use App\Services\ArimaMaximumLikelihood;
use App\Services\ArimaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastMethodologyTest extends TestCase
{
    use RefreshDatabase;

    private function fish(): FishType
    {
        return FishType::create(['name' => 'Methodology Fish', 'quality_class' => 'First Class', 'is_active' => true]);
    }

    private function batch(User $vendor, FishType $fish, int $daysAgo, float $price, float $stock = 10): void
    {
        VendorInventory::create([
            'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'First Class',
            'price_per_kg' => $price, 'stock_kg' => $stock, 'released_kg' => $stock,
            'sold_kg' => 0, 'status' => 'confirmed', 'entry_date' => today()->subDays($daysAgo),
        ]);
    }

    private function params(): array
    {
        return ['p' => 1, 'd' => 1, 'q' => 1,
            'model_version' => ArimaMaximumLikelihood::VERSION, 'history_days' => 30];
    }

    public function test_training_uses_only_the_last_thirty_calendar_days_and_excludes_today(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(0, 35) as $ago) {
            $this->batch($vendor, $fish, $ago, 100 + $ago);
        }
        $series = app(ArimaService::class)->buildSeries($fish->id, 'First Class', 'price');
        $this->assertCount(30, $series);
        $this->assertSame(130.0, $series[0]);
        $this->assertSame(101.0, $series[29]);
    }

    public function test_missing_dates_are_not_compressed_into_adjacent_model_steps(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach ([1, 2, 4, 5, 6, 7, 8, 9, 10] as $ago) {
            $this->batch($vendor, $fish, $ago, 100 + $ago);
        }
        $service = app(ArimaService::class);
        $this->assertSame([102.0, 101.0], $service->buildSeries($fish->id, 'First Class', 'price'));
        $this->assertSame(0, $service->generate($fish->id, 'First Class', 'price'));
        $this->assertDatabaseCount('forecasts', 0);
    }

    public function test_a_missing_yesterday_does_not_relabel_old_data_as_current(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(2, 12) as $ago) {
            $this->batch($vendor, $fish, $ago, 100);
        }
        $this->assertSame([], app(ArimaService::class)->buildSeries($fish->id, 'First Class', 'price'));
    }

    public function test_published_targets_bridge_today_and_record_the_estimation_method(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(1, 10) as $ago) {
            $this->batch($vendor, $fish, $ago, 110 - $ago);
        }
        $service = app(ArimaService::class);
        $this->assertSame(3, $service->generate($fish->id, 'First Class', 'price'));
        $rows = $service->latestRun($fish->id, 'First Class', 'price');
        $this->assertSame([111.0, 112.0, 113.0], $rows->map(fn ($f) => (float) $f->predicted_value)->all());
        $this->assertSame(today()->addDay()->toDateString(), $rows[0]->forecast_date->toDateString());
        $this->assertSame(2, $rows[0]->arima_params['model_step']);
        $this->assertSame(30, $rows[0]->arima_params['history_days']);
        $this->assertSame('exact_gaussian_maximum_likelihood', $rows[0]->arima_params['estimation']);
        $this->assertSame(today()->subDay()->toDateString(), $rows[0]->arima_params['training_end']);
    }

    public function test_accuracy_excludes_legacy_models_and_reports_rmse_and_mape(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $this->batch($vendor, $fish, 1, 100);
        foreach ([[$this->params(), 110, 3], [['p' => 1, 'd' => 1, 'q' => 1], 999, 2]] as [$params, $value, $ago]) {
            Forecast::create([
                'fish_type_id' => $fish->id, 'quality_class' => 'First Class', 'metric' => 'price',
                'forecast_date' => today()->subDay(), 'predicted_value' => $value,
                'predicted_min' => 90, 'predicted_max' => 120, 'trend' => 'stable',
                'generated_at' => today()->subDays($ago), 'arima_params' => $params,
            ]);
        }
        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts?fish_type_id='.$fish->id.'&quality_class=First+Class&metric=price')->assertOk();
        $this->assertSame(['days' => 1, 'mape_days' => 1, 'mape' => 10.0, 'mae' => 10.0, 'rmse' => 10.0], $response->viewData('accuracy'));
        $this->assertDatabaseCount('forecasts', 2);
    }

    public function test_zero_actual_supply_is_scored_in_rmse_but_excluded_from_mape(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $this->batch($vendor, $fish, 1, 100, 0);
        Forecast::create([
            'fish_type_id' => $fish->id, 'quality_class' => 'First Class', 'metric' => 'supply',
            'forecast_date' => today()->subDay(), 'predicted_value' => 2,
            'predicted_min' => 0, 'predicted_max' => 3, 'trend' => 'stable',
            'generated_at' => today()->subDays(2), 'arima_params' => $this->params(),
        ]);
        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts?fish_type_id='.$fish->id.'&quality_class=First+Class&metric=supply')->assertOk();
        $this->assertSame(['days' => 1, 'mape_days' => 0, 'mape' => null, 'mae' => 2.0, 'rmse' => 2.0], $response->viewData('accuracy'));
    }
}
