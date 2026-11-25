<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\Forecast;
use App\Models\User;
use App\Models\VendorInventory;
use App\Services\ArimaMaximumLikelihood;
use App\Services\ArimaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_older_history_retains_its_actual_origin_instead_of_being_relabelled_current(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(2, 12) as $ago) {
            $this->batch($vendor, $fish, $ago, 100);
        }
        $service = app(ArimaService::class);
        $this->assertCount(11, $service->buildSeries($fish->id, 'First Class', 'price'));
        $this->assertSame(3, $service->generate($fish->id, 'First Class', 'price'));
        $params = $service->latestRun($fish->id, 'First Class', 'price')->first()->arima_params;
        $this->assertSame(today()->subDays(2)->toDateString(), $params['training_end']);
        $this->assertSame(3, $params['model_step']);
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

    public function test_no_saved_forecasts_defaults_to_a_fish_with_history_instead_of_an_empty_fish(): void
    {
        FishType::create(['name' => 'AAA Empty Fish', 'quality_class' => 'First Class', 'is_active' => true]);
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(1, 8) as $ago) {
            $this->batch($vendor, $fish, $ago, 100 + $ago);
        }
        $this->assertDatabaseCount('forecasts', 0);
        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts')->assertOk();
        $this->assertSame($fish->id, $response->viewData('selectedFishTypeId'));
        $this->assertCount(3, $response->viewData('forecasts'));
        $response->assertSee('AAA Empty Fish · no confirmed history');
    }

    public function test_eligible_fish_without_a_saved_run_are_labelled_ready_to_forecast(): void
    {
        $fish = $this->fish();
        $second = FishType::create(['name' => 'Other Ready Fish', 'quality_class' => 'First Class', 'is_active' => true]);
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(1, 8) as $ago) {
            $this->batch($vendor, $fish, $ago, 100 + $ago);
            $this->batch($vendor, $second, $ago, 200 + $ago);
        }
        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts?fish_type_id='.$fish->id)->assertOk();
        $response->assertSee('Other Ready Fish · ready to forecast')->assertDontSee('not enough data');
        $this->assertSame(0, Forecast::where('fish_type_id', $second->id)->count());
    }

    public function test_filter_status_is_specific_to_the_selected_quality_class(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(1, 8) as $ago) {
            $this->batch($vendor, $fish, $ago, 100 + $ago);
        }
        app(ArimaService::class)->generate($fish->id, 'First Class', 'price');
        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts?fish_type_id='.$fish->id.'&quality_class=Special+Class')->assertOk();
        $response->assertSee('Methodology Fish · no confirmed history');
        $this->assertCount(0, $response->viewData('forecasts'));
    }

    public function test_partial_history_shows_the_latest_continuous_segment_length(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach ([1, 2, 4, 5, 6, 7] as $ago) {
            $this->batch($vendor, $fish, $ago, 100 + $ago);
        }
        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts?fish_type_id='.$fish->id)->assertOk();
        $response->assertSee('Methodology Fish · 2/7 consecutive days');
        $this->assertCount(0, $response->viewData('forecasts'));
    }

    public function test_hosting_status_command_does_not_generate_or_modify_forecasts(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(1, 8) as $ago) {
            $this->batch($vendor, $fish, $ago, 100 + $ago);
        }
        $this->artisan('forecast:status')
            ->expectsOutputToContain('Series meeting the history requirement: 1')
            ->expectsOutputToContain('Upcoming rows from the current model: 0')
            ->assertSuccessful();
        $this->assertDatabaseCount('forecasts', 0);
        $this->assertDatabaseCount('vendor_inventories', 8);
    }

    public function test_november_seventeenth_history_can_forecast_november_twenty_fifth_without_inventing_records(): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 19:00:00', config('app.timezone')));
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(7, 30) as $ago) {
            $this->batch($vendor, $fish, $ago, 200 - $ago);
        }
        $service = app(ArimaService::class);
        $coverage = $service->historyCoverage([$fish->id])->first();
        $this->assertSame(24, $coverage['consecutive_days']);
        $this->assertSame(7, $coverage['age_days']);
        $this->assertSame('2026-11-17', $coverage['latest_record']);
        $this->artisan('forecast:status')->expectsOutputToContain('Series meeting the history requirement: 1')->assertSuccessful();

        $this->assertSame(3, $service->generate($fish->id, 'First Class', 'price'));
        $rows = $service->latestRun($fish->id, 'First Class', 'price');
        $this->assertSame([201.0, 202.0, 203.0], $rows->map(fn ($f) => (float) $f->predicted_value)->all());
        $this->assertSame(['2026-11-25', '2026-11-26', '2026-11-27'], $rows->map(fn ($f) => $f->forecast_date->toDateString())->all());
        $this->assertSame([8, 9, 10], $rows->map(fn ($f) => $f->arima_params['model_step'])->all());
        $this->assertSame('2026-11-17', $rows[0]->arima_params['training_end']);
        $this->assertSame('2026-10-25', $rows[0]->arima_params['training_start']);
        $this->assertDatabaseCount('vendor_inventories', 24);
        $this->assertSame('2026-11-17', substr(VendorInventory::max('entry_date'), 0, 10));

        $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts?fish_type_id='.$fish->id)->assertOk()
            ->assertDontSee('History through')
            ->assertDontSee('first displayed forecast is');
    }

    public function test_old_history_outside_the_thirty_day_window_is_still_excluded(): void
    {
        $fish = $this->fish();
        $vendor = User::factory()->create(['role' => 'vendor']);
        foreach (range(31, 45) as $ago) {
            $this->batch($vendor, $fish, $ago, 100);
        }
        $service = app(ArimaService::class);
        $this->assertSame([], $service->buildSeries($fish->id, 'First Class', 'price'));
        $this->assertSame(0, $service->generate($fish->id, 'First Class', 'price'));
    }
}
