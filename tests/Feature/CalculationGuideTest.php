<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\User;
use App\Models\VendorInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_researchers_can_read_the_complete_guide_without_signing_in(): void
    {
        $response = $this->get('/calculation')->assertOk();

        foreach (['overview', 'stock-supply', 'sales-tariffs', 'arima-method', 'arima-lab',
            'trends-accuracy', 'reports', 'glossary', 'research-notes'] as $section) {
            $response->assertSee('id="'.$section.'"', false);
        }

        $response->assertSee('weighted average')
            ->assertSee('MAPE')
            ->assertSee('Step 8')
            ->assertSee('maximum-likelihood')
            ->assertViewHas('trace', fn ($trace) => $trace['projections'][0]['value'] === 113.41
                && $trace['projections'][2]['value'] === 115.79
                && $trace['trend'] === 'upward');
    }

    public function test_examples_exercise_upward_downward_stable_and_zero_baselines(): void
    {
        foreach (['upward' => 'upward', 'downward' => 'downward', 'stable' => 'stable', 'zero' => 'stable'] as $example => $trend) {
            $this->get('/calculation?example='.$example.'&metric=supply')
                ->assertOk()
                ->assertViewHas('metric', 'supply')
                ->assertViewHas('trace', fn ($trace) => $trace['trend'] === $trend);
        }

        $this->get('/calculation?example=zero')->assertOk()
            ->assertSee('Percentage change is undefined')
            ->assertViewHas('trace', fn ($trace) => $trace['change_percent'] === null
                && $trace['projections'][0]['value'] === 0.0);
    }

    public function test_the_method_section_shows_all_ten_worked_computations_without_expanding_details(): void
    {
        $response = $this->get('/calculation')->assertOk()
            ->assertViewHas('methodExample', fn ($sample) => $sample['projections'][0]['value'] === 326.81
                && $sample['projections'][1]['value'] === 325.25
                && $sample['projections'][2]['value'] === 331.59);

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $sample = $xpath->query('//section[@id="arima-method"]//div[@id="arima-worked-example"]')->item(0);

        $this->assertNotNull($sample, 'The worked computation must be in the method section itself.');
        $this->assertSame(0, $xpath->query('ancestor::details', $sample)->length);
        $this->assertSame(10, $xpath->query('.//h3[starts-with(normalize-space(.), "Step ")]', $sample)->length);
        $text = preg_replace('/\s+/u', ' ', $sample->textContent);
        foreach (['302.00 − 300.00 = 2.000000', '-0.942699',
            '16.783136 / 9', '1.365574', '313.428571',
            '(326.81 + 325.25 + 331.59) ÷ 3', '₱327.88/kg',
            '₱325.25/kg on Day 2', '₱331.59/kg on Day 3'] as $calculation) {
            $this->assertStringContainsString($calculation, $text);
        }
    }

    public function test_the_fixed_price_computation_stays_independent_of_the_interactive_supply_example(): void
    {
        $this->get('/calculations?metric=supply&example=zero')->assertOk()
            ->assertViewHas('metric', 'supply')
            ->assertViewHas('trace', fn ($trace) => $trace['projections'][0]['value'] === 0.0)
            ->assertViewHas('methodExample', fn ($sample) => $sample['observations'][0] === 300
                && $sample['projections'][0]['value'] === 326.81)
            ->assertSee('Sample computation: Pusit')
            ->assertSee('illustrative daily average prices in ₱/kg');
    }

    public function test_custom_observations_are_calculated_by_the_production_model(): void
    {
        $this->get('/calculation?'.http_build_query([
            'metric' => 'supply', 'series' => "100, 101; 103\n102 105 104 108 107 111 110",
        ]))->assertOk()->assertViewHas('custom', true)
            ->assertViewHas('trace', fn ($trace) => $trace['projections'][0]['value'] === 113.41
                && round($trace['phi_fit']['coefficient'], 4) === -0.9427
                && round($trace['theta_fit']['coefficient'], 4) === 0.1149);
    }

    public function test_invalid_input_gets_a_readable_error_and_never_an_invalid_calculation(): void
    {
        foreach ([
            ['series' => '1 2 3'],
            ['series' => '1 2 3 4 5 6 -7'],
            ['series' => '1 2 3 4 5 6 NaN'],
            ['series' => '1 2 3 4 5 6 1e309'],
            ['series' => '<script>alert(1)</script>'],
            ['series' => implode(' ', range(1, 91))],
            ['series' => ['1', '2']],
            ['metric' => 'demand'],
            ['example' => ['worked']],
        ] as $query) {
            $this->get('/calculation?'.http_build_query($query))
                ->assertStatus(422)->assertSee('Your input needs a correction.')
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertViewHas('custom', false)
                ->assertViewHas('trace', fn ($trace) => ! empty($trace['projections']));
        }
    }

    public function test_the_guide_uses_active_settings_instead_of_hardcoded_defaults(): void
    {
        config(['forecast.horizon' => 4, 'forecast.min_history' => 12,
            'forecast.trend_threshold' => 0.05, 'inventory.stale_after_days' => 5]);

        $this->get('/calculation')->assertOk()->assertSee('5.0%')
            ->assertViewHas('settings', fn ($settings) => $settings['minimum'] === 12
                && $settings['freshness'] === 5)
            ->assertViewHas('trace', fn ($trace) => count($trace['projections']) === 4
                && count($trace['observations']) >= 12)
            ->assertViewHas('methodExample', fn ($sample) => count($sample['projections']) === 4
                && count($sample['observations']) >= 12);
    }

    public function test_the_public_guide_neither_exposes_private_data_nor_expires_batches(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'name' => 'Private Vendor Identity']);
        $fish = FishType::create(['name' => 'Private Fish Example', 'quality_class' => 'First Class', 'is_active' => true]);
        $batch = VendorInventory::create([
            'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'First Class',
            'batch_no' => 1, 'price_per_kg' => 200, 'stock_kg' => 20, 'released_kg' => 20,
            'sold_kg' => 0, 'status' => 'confirmed', 'entry_date' => today()->subDays(10),
        ]);

        $this->get('/calculation')->assertOk()->assertDontSee($vendor->name)->assertDontSee($fish->name);

        $this->assertNull($batch->fresh()->disposed_at);
        $this->assertDatabaseCount('forecasts', 0);
        $this->assertDatabaseCount('batch_releases', 0);
    }
}
