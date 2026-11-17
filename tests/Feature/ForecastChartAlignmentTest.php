<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\Forecast;
use App\Models\User;
use App\Models\VendorInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastChartAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private function renderForecast(string $metric, array $values)
    {
        $fish = FishType::create(['name' => 'Chart Alignment Fish '.$metric, 'quality_class' => 'First Class', 'is_active' => true]);
        $vendor = User::factory()->create(['role' => 'vendor']);

        // A historical high and a wide uncertainty range deliberately exceed
        // the forecast high. Neither belongs in the forecast extrema cards.
        VendorInventory::create([
            'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'First Class',
            'price_per_kg' => 300, 'stock_kg' => 300, 'released_kg' => 300,
            'sold_kg' => 300, 'status' => 'confirmed', 'entry_date' => today()->subDay(),
        ]);

        $generatedAt = now();
        foreach ($values as $index => $value) {
            Forecast::create([
                'fish_type_id' => $fish->id, 'quality_class' => 'First Class', 'metric' => $metric,
                'forecast_date' => today()->addDays($index + 1), 'predicted_value' => $value,
                'predicted_min' => $value - 30, 'predicted_max' => $value + 40,
                'trend' => 'downward', 'generated_at' => $generatedAt,
                'arima_params' => ['p' => 1, 'd' => 1, 'q' => 1, 'trend_baseline' => 300],
            ]);
        }

        return $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/forecasts?'.http_build_query([
                'fish_type_id' => $fish->id, 'quality_class' => 'First Class', 'metric' => $metric,
            ]))->assertOk();
    }

    private function chartData($response): array
    {
        $this->assertSame(1, preg_match(
            '#<script type="application/json" id="forecast-chart-data">(.*?)</script>#s',
            $response->getContent(), $match,
        ));

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_chart_extrema_match_the_cards_and_table_for_price_and_supply(): void
    {
        foreach (['price', 'supply'] as $metric) {
            $response = $this->renderForecast($metric, [112.92, 111.41, 114.95]);
            $data = $this->chartData($response);
            $summary = $response->viewData('summary');

            $this->assertSame([112.92, 111.41, 114.95], $data['fcVals']);
            $this->assertSame($summary['high']['value'], $data['highValue']);
            $this->assertSame($summary['low']['value'], $data['lowValue']);
            $this->assertSame(today()->addDays(3)->toDateString(), $data['highDate']);
            $this->assertSame(today()->addDays(2)->toDateString(), $data['lowDate']);
            $this->assertSame($summary['avg'], $data['avgFc']);
            $this->assertGreaterThan($data['highValue'], max($data['histVals']));
            $this->assertGreaterThan($data['highValue'], max($data['fcMaxs']));
            $this->assertLessThan($data['lowValue'], min($data['fcMins']));
            $response->assertSee('112.92')->assertSee('111.41')->assertSee('114.95')
                ->assertSee('Highest and lowest refer only to the 3 forecast values');
        }
    }

    public function test_the_graph_keeps_today_between_history_and_future_predictions(): void
    {
        $data = $this->chartData($this->renderForecast('price', [100, 110, 90]));

        $todayIndex = array_search(today()->toDateString(), $data['allLabels'], true);
        $this->assertIsInt($todayIndex);
        $this->assertSame(today()->subDay()->toDateString(), $data['allLabels'][$todayIndex - 1]);
        $this->assertSame(today()->addDay()->toDateString(), $data['allLabels'][$todayIndex + 1]);
        $this->assertNotContains(today()->toDateString(), $data['histLabels']);
        $this->assertNotContains(today()->toDateString(), $data['fcLabels']);
    }

    public function test_equal_forecasts_share_one_value_without_conflicting_extrema_markers(): void
    {
        $data = $this->chartData($this->renderForecast('supply', [100, 100, 100]));

        $this->assertTrue($data['flat']);
        $this->assertEquals(100, $data['highValue']);
        $this->assertEquals(100, $data['lowValue']);
        $this->assertNull($data['highDate']);
        $this->assertNull($data['lowDate']);
    }

    public function test_tied_forecast_extrema_keep_the_earliest_day_used_by_the_cards(): void
    {
        $data = $this->chartData($this->renderForecast('price', [110, 100, 110]));

        $this->assertFalse($data['flat']);
        $this->assertSame(today()->addDay()->toDateString(), $data['highDate']);
        $this->assertSame(today()->addDays(2)->toDateString(), $data['lowDate']);
    }
}
