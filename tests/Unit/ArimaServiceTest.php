<?php

namespace Tests\Unit;

use App\Services\ArimaMaximumLikelihood;
use App\Services\ArimaService;
use Tests\TestCase;

class ArimaServiceTest extends TestCase
{
    private ArimaService $arima;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arima = app(ArimaService::class);
    }

    private function exampleSeries(): array
    {
        return [100, 101, 103, 102, 105, 104, 108, 107, 111, 110];
    }

    public function test_fitting_and_forecasting_match_independent_statsmodels_references(): void
    {
        $reference = json_decode(file_get_contents(base_path('tests/Fixtures/arima_mle_reference.json')), true);
        foreach ($reference['cases'] as $name => $case) {
            $trace = $this->arima->explain($case['series']);
            $this->assertTrue($trace['fit']['converged'], $name);
            foreach (['phi', 'theta', 'mean', 'sigma2', 'log_likelihood'] as $parameter) {
                $this->assertEqualsWithDelta($case[$parameter], $trace['fit'][$parameter], 0.00002, "$name: $parameter");
            }
            foreach ($trace['forecast_steps'] as $index => $step) {
                $this->assertEqualsWithDelta($case['forecast'][$index], $step['unclipped_value'], 0.00005, $name);
                $this->assertEqualsWithDelta($case['forecast_variance'][$index], $step['forecast_variance'], 0.00005, $name);
            }
            $this->assertSame(ArimaMaximumLikelihood::VERSION, $trace['projections'][0]['params']['model_version']);
            $this->assertSame('exact_gaussian_maximum_likelihood', $trace['projections'][0]['params']['estimation']);
        }
    }

    public function test_affine_changes_of_units_preserve_the_model_and_scale_its_uncertainty(): void
    {
        $original = $this->arima->explain($this->exampleSeries());
        $scaled = $this->arima->explain(array_map(fn ($value) => 100 + 2 * $value, $this->exampleSeries()));
        $this->assertEqualsWithDelta($original['fit']['phi'], $scaled['fit']['phi'], 1e-7);
        $this->assertEqualsWithDelta($original['fit']['theta'], $scaled['fit']['theta'], 1e-7);
        foreach ($original['forecast_steps'] as $index => $step) {
            $this->assertEqualsWithDelta(100 + 2 * $step['value'], $scaled['forecast_steps'][$index]['value'], 1e-6);
            $this->assertEqualsWithDelta(4 * $step['forecast_variance'], $scaled['forecast_steps'][$index]['forecast_variance'], 1e-6);
        }
    }

    public function test_a_steady_climb_is_labelled_upward(): void
    {
        $series = array_map(fn ($i) => 200 * (1.01 ** $i) + (($i % 2) ? 0.6 : -0.6), range(0, 29));
        $this->assertSame('upward', $this->arima->project($series)[0]['trend']);
        $this->assertSame('downward', $this->arima->project(array_reverse($series))[0]['trend']);
    }

    public function test_a_flat_series_is_labelled_stable(): void
    {
        $series = array_map(fn ($i) => 150 + (($i % 2) ? 1 : -1), range(0, 29));
        $this->assertSame('stable', $this->arima->project($series)[0]['trend']);
    }

    public function test_it_skips_series_below_the_minimum_history(): void
    {
        $this->assertSame([], $this->arima->project([]));
        $this->assertSame([], $this->arima->project(range(1, config('forecast.min_history') - 1)));
    }

    public function test_the_history_window_matches_chapters_one_to_three(): void
    {
        $this->assertSame(30, config('forecast.history_days'));
        $this->assertSame(30, config('forecast.history_chart_days'));
    }

    public function test_it_forecasts_exactly_the_configured_horizon(): void
    {
        config(['forecast.horizon' => 4]);
        $this->assertCount(4, $this->arima->project($this->exampleSeries()));
    }

    public function test_the_current_day_bridge_advances_the_mean_and_variance_before_tomorrow(): void
    {
        $reference = json_decode(file_get_contents(base_path('tests/Fixtures/arima_mle_reference.json')), true)['cases']['worked'];
        $trace = $this->arima->explain($this->exampleSeries(), 1);
        $this->assertCount(1, $trace['bridge_steps']);
        $this->assertCount(3, $trace['projections']);
        foreach ($trace['forecast_steps'] as $index => $step) {
            $this->assertSame($index + 2, $step['model_step']);
            $this->assertEqualsWithDelta($reference['forecast'][$index + 1], $step['unclipped_value'], 0.00005);
            $this->assertEqualsWithDelta($reference['forecast_variance'][$index + 1], $step['forecast_variance'], 0.00005);
        }
    }

    public function test_constant_changes_are_explicitly_marked_as_a_deterministic_limiting_case(): void
    {
        $trace = $this->arima->explain(range(1, 10));
        $this->assertSame('deterministic', $trace['fit']['status']);
        $this->assertNull($trace['fit']['log_likelihood']);
        $this->assertSame(0.0, $trace['fit']['sigma2']);
        $this->assertSame([11.0, 12.0, 13.0], array_column($trace['projections'], 'value'));
    }

    public function test_coefficients_obey_stationarity_and_invertibility_constraints(): void
    {
        $fit = $this->arima->explain($this->exampleSeries())['fit'];
        $this->assertLessThan(1, abs($fit['phi']));
        $this->assertLessThan(1, abs($fit['theta']));
    }

    public function test_fitted_drift_is_not_replaced_by_the_sample_mean_of_changes(): void
    {
        $trace = $this->arima->explain($this->exampleSeries());
        $this->assertEqualsWithDelta(10 / 9, $trace['sample_mean_diff'], 1e-12);
        $this->assertEqualsWithDelta(1.251848319, $trace['mean_diff'], 1e-6);
    }

    public function test_the_prediction_interval_uses_arma_variance_instead_of_sigma_squared_times_h(): void
    {
        $trace = $this->arima->explain($this->exampleSeries());
        $second = $trace['forecast_steps'][1];
        $this->assertEqualsWithDelta(0.480019966, $second['forecast_variance'], 1e-6);
        $this->assertNotEqualsWithDelta(2 * $trace['fit']['sigma2'], $second['forecast_variance'], 0.1);
    }

    public function test_predictions_and_bounds_are_never_negative(): void
    {
        $trace = $this->arima->explain([50, 44, 41, 33, 28, 19, 12, 6, 2, 0]);
        foreach ($trace['projections'] as $point) {
            $this->assertGreaterThanOrEqual(0, $point['value']);
            $this->assertGreaterThanOrEqual(0, $point['min']);
            $this->assertGreaterThanOrEqual($point['min'], $point['max']);
        }
        $this->assertLessThan(0, $trace['forecast_steps'][0]['unclipped_value']);
        $this->assertSame($trace['forecast_steps'][0]['unclipped_value'], $trace['forecast_steps'][1]['previous_value']);
    }

    public function test_it_reproduces_the_updated_documented_worked_example(): void
    {
        $out = $this->arima->project($this->exampleSeries());
        $this->assertSame([113.41, 112.63, 115.79], array_column($out, 'value'));
        $this->assertSame([112.07, 111.27, 113.93], array_column($out, 'min'));
        $this->assertSame([114.74, 113.99, 117.66], array_column($out, 'max'));
        $this->assertSame(['upward'], array_values(array_unique(array_column($out, 'trend'))));
    }

    public function test_demand_is_no_longer_a_forecast_metric(): void
    {
        $this->assertSame(['price', 'supply'], config('forecast.metrics'));
        $this->assertSame(0, $this->arima->generate(1, 'First Class', 'demand'));
    }
}
