<?php

namespace Tests\Feature;

use App\Models\BatchRelease;
use App\Models\FishType;
use App\Models\Forecast;
use App\Models\User;
use App\Models\VendorInventory;
use App\Services\ArimaMaximumLikelihood;
use App\Services\ArimaService;
use Database\Seeders\ForecastPanelDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ForecastPanelDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_data_runs_the_production_model_preserves_real_records_and_reruns_without_duplicates(): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 19:00:00', config('app.timezone')));
        $vendor = User::factory()->create(['role' => 'vendor']);
        $fish = FishType::create(['name' => 'Hipon', 'quality_class' => 'Special Class', 'is_active' => true]);
        $actual = VendorInventory::create([
            'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'Special Class',
            'entry_date' => today()->subDay(), 'price_per_kg' => 501, 'stock_kg' => 21, 'released_kg' => 21,
            'sold_kg' => 3, 'status' => 'confirmed', 'price_note' => 'Researcher observation',
        ]);
        $actualForecast = Forecast::create([
            'fish_type_id' => $fish->id, 'quality_class' => 'Special Class', 'metric' => 'price',
            'forecast_date' => today()->addDay(), 'predicted_value' => 502, 'predicted_min' => 490,
            'predicted_max' => 514, 'trend' => 'stable', 'generated_at' => now(),
            'arima_params' => ['model_version' => ArimaMaximumLikelihood::VERSION, 'history_days' => 30],
        ]);
        $actualBefore = $actual->fresh()->getRawOriginal();
        $forecastBefore = $actualForecast->fresh()->getRawOriginal();

        $this->seed(ForecastPanelDemoSeeder::class);
        $demoFish = FishType::where('name', 'like', '[DEMO] %')->get();
        $this->assertCount(6, $demoFish);
        $mockBatches = VendorInventory::whereIn('fish_type_id', $demoFish->modelKeys())->get();
        $this->assertCount(720, $mockBatches);
        $this->assertSame('2026-11-23', $mockBatches->max('entry_date')->toDateString());
        $this->assertSame(0, VendorInventory::open()->whereIn('fish_type_id', $demoFish->modelKeys())->count());
        $this->assertSame(720, BatchRelease::where('reason', 'like', ForecastPanelDemoSeeder::MARKER.'%')->count());
        $this->assertSame($actualBefore, $actual->fresh()->getRawOriginal());
        $this->assertSame($forecastBefore, $actualForecast->fresh()->getRawOriginal());
        $this->assertSame('active', $vendor->fresh()->status);
        $this->assertSame(4, User::where('username', 'like', 'forecast_panel_%')->where('status', 'inactive')->count());

        $current = Forecast::whereIn('fish_type_id', $demoFish->modelKeys())->whereDate('forecast_date', '>', today())->get();
        $this->assertCount(36, $current);
        $this->assertSame(216, Forecast::whereIn('fish_type_id', $demoFish->modelKeys())->whereDate('forecast_date', '<', today())->count());
        foreach ($current as $point) {
            $this->assertSame('synthetic_panel_demo', $point->arima_params['data_source']);
            $this->assertSame(ArimaMaximumLikelihood::VERSION, $point->arima_params['model_version']);
            $this->assertSame(30, $point->arima_params['observations']);
            $this->assertGreaterThan(0, $point->arima_params['sigma']);
        }
        $this->assertContains('upward', $current->where('metric', 'price')->pluck('trend')->all());
        $this->assertContains('downward', $current->where('metric', 'price')->pluck('trend')->all());
        $this->assertContains('stable', $current->where('metric', 'price')->pluck('trend')->all());

        $demo = $demoFish->firstWhere('name', '[DEMO] Hipon');
        $arima = app(ArimaService::class);
        $expected = $arima->project($arima->buildSeries($demo->id, 'Special Class', 'price'), 1);
        $saved = $arima->latestRun($demo->id, 'Special Class', 'price');
        $this->assertSame(array_column($expected, 'value'), $saved->map(fn ($f) => (float) $f->predicted_value)->all());

        $past = Forecast::where('fish_type_id', $demo->id)->where('metric', 'price')
            ->whereDate('forecast_date', '<', today())->orderBy('generated_at')->firstOrFail();
        $params = $past->arima_params;
        $this->assertSame('retrospective_rolling_origin', $params['evaluation']);
        $this->assertLessThan($past->forecast_date->toDateString(), $params['training_end']);
        $this->assertLessThan($past->forecast_date, $past->generated_at);
        $training = VendorInventory::where('fish_type_id', $demo->id)
            ->whereDate('entry_date', '>=', $params['training_start'])->whereDate('entry_date', '<=', $params['training_end'])
            ->orderBy('entry_date')->get()->groupBy(fn ($batch) => $batch->entry_date->toDateString())
            ->map(fn ($batches) => (float) $batches->avg('price_per_kg'))->values()->all();
        $reference = $arima->project($training, 1);
        $index = (int) $past->forecast_date->diffInDays($past->generated_at->copy()->startOfDay(), true) - 1;
        $this->assertSame($reference[$index]['value'], (float) $past->predicted_value);

        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts?fish_type_id='.$demo->id.'&quality_class=Special+Class&metric=price')->assertOk();
        $response->assertDontSee('[DEMO]')->assertDontSee('Synthetic panel demonstration.')->assertDontSee('History through')->assertSee('Hipon');
        $this->assertSame(7, $response->viewData('accuracy')['days']);
        $this->assertNotNull($response->viewData('accuracy')['rmse']);
        $this->assertNotNull($response->viewData('accuracy')['mape']);

        $batchBefore = $mockBatches->first()->getRawOriginal();
        $forecastCount = Forecast::count();
        $releaseCount = BatchRelease::count();
        $this->seed(ForecastPanelDemoSeeder::class);
        $this->assertSame(721, VendorInventory::count());
        $this->assertSame($forecastCount, Forecast::count());
        $this->assertSame($releaseCount, BatchRelease::count());
        $this->assertSame($batchBefore, $mockBatches->first()->fresh()->getRawOriginal());
        $this->assertSame($actualBefore, $actual->fresh()->getRawOriginal());
        $this->assertSame($forecastBefore, $actualForecast->fresh()->getRawOriginal());
    }

    public function test_a_conflict_with_unmarked_records_rolls_back_the_demo_without_overwriting_them(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $fish = FishType::create(['name' => '[DEMO] Pusit', 'quality_class' => 'Special Class', 'is_active' => true]);
        $batch = VendorInventory::create([
            'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'Special Class',
            'entry_date' => today()->subDay(), 'price_per_kg' => 215, 'stock_kg' => 20,
            'released_kg' => 20, 'status' => 'confirmed', 'price_note' => 'Existing manually recorded observation',
        ]);
        $before = $batch->fresh()->getRawOriginal();
        try {
            $this->seed(ForecastPanelDemoSeeder::class);
            $this->fail('The seeder must reject a reserved fish name with unmarked observations.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('unmarked records', $error->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('fish_types', 1);
        $this->assertDatabaseCount('vendor_inventories', 1);
        $this->assertDatabaseCount('forecasts', 0);
        $this->assertDatabaseCount('batch_releases', 0);
        $this->assertSame($before, $batch->fresh()->getRawOriginal());
    }
}
