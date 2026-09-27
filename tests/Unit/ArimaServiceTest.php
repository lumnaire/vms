<?php

namespace Tests\Unit;

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

    /** The series behind the worked example in README.md. */
    private function exampleSeries(): array
    {
        return [100, 101, 103, 102, 105, 104, 108, 107, 111, 110];
    }

    public function test_it_skips_series_below_the_minimum_history(): void
    {
        $this->assertSame([], $this->arima->project([]));
        $this->assertSame([], $this->arima->project([1, 2, 3, 4, 5, 6]));
    }

    public function test_it_skips_a_series_exactly_at_the_minimum_history_boundary_minus_one(): void
    {
        $short = range(1, config('forecast.min_history') - 1);

        $this->assertCount(config('forecast.min_history') - 1, $short);
        $this->assertSame([], $this->arima->project($short));
    }

    public function test_it_forecasts_exactly_the_configured_horizon(): void
    {
        $out = $this->arima->project($this->exampleSeries());

        $this->assertCount(config('forecast.horizon'), $out);
    }

    /**
     * Regression guard: the MA(1) residual must be built from the *lagged*
     * change. Pairing each change with itself makes the residual a constant
     * multiple of the centred series, and since ar1Coefficient() is a
     * scale-invariant ratio the estimator then returns phi for theta too,
     * silently collapsing the model to a single term.
     */
    public function test_the_ma_coefficient_is_estimated_independently_of_the_ar_coefficient(): void
    {
        $params = $this->arima->project($this->exampleSeries())[0]['params'];

        $this->assertNotEquals(
            $params['phi'],
            $params['theta'],
            'theta collapsed onto phi — the AR(1) residual has lost its lag.'
        );
    }

    public function test_the_ma_coefficient_stays_independent_across_many_series(): void
    {
        mt_srand(20260927);

        for ($i = 0; $i < 200; $i++) {
            $n = mt_rand(8, 40);
            $series = [];
            for ($d = 0; $d < $n; $d++) {
                $series[] = mt_rand(0, 4000) / 100;
            }

            $params = $this->arima->project($series)[0]['params'];

            $this->assertNotEquals(
                $params['phi'],
                $params['theta'],
                "theta collapsed onto phi for series: " . implode(', ', $series)
            );
        }
    }

    public function test_coefficients_stay_inside_their_clamped_range(): void
    {
        foreach ($this->arima->project($this->exampleSeries()) as $point) {
            $this->assertGreaterThanOrEqual(-0.99, $point['params']['phi']);
            $this->assertLessThanOrEqual(0.99, $point['params']['phi']);
            $this->assertGreaterThanOrEqual(-0.99, $point['params']['theta']);
            $this->assertLessThanOrEqual(0.99, $point['params']['theta']);
        }
    }

    public function test_the_prediction_interval_widens_with_the_horizon(): void
    {
        $out = $this->arima->project($this->exampleSeries());

        $widths = array_map(
            fn ($p) => $p['max'] - $p['min'],
            $out
        );

        $this->assertGreaterThan($widths[0], $widths[1]);
        $this->assertGreaterThan($widths[1], $widths[2]);
    }

    public function test_the_interval_is_centred_on_the_prediction(): void
    {
        foreach ($this->arima->project($this->exampleSeries()) as $point) {
            $this->assertEqualsWithDelta(
                $point['value'],
                ($point['min'] + $point['max']) / 2,
                0.01
            );
        }
    }

    public function test_predictions_and_bounds_are_never_negative(): void
    {
        $out = $this->arima->project([50, 44, 41, 33, 28, 19, 12, 6, 2, 0]);

        foreach ($out as $point) {
            $this->assertGreaterThanOrEqual(0, $point['value']);
            $this->assertGreaterThanOrEqual(0, $point['min']);
            $this->assertGreaterThanOrEqual(0, $point['max']);
        }
    }

    public function test_every_projection_shares_one_trend_label(): void
    {
        $out = $this->arima->project($this->exampleSeries());
        $trends = array_unique(array_column($out, 'trend'));

        $this->assertCount(1, $trends);
        $this->assertContains($trends[0], ['upward', 'downward', 'stable']);
    }

    /**
     * Pins the numbers quoted in the README worked example so the
     * documentation cannot drift away from the implementation.
     */
    public function test_it_reproduces_the_documented_worked_example(): void
    {
        $out = $this->arima->project($this->exampleSeries());
        $params = $out[0]['params'];

        $this->assertEquals(-0.8832, $params['phi']);
        $this->assertEquals(-0.1366, $params['theta']);
        $this->assertEquals(1.1111, $params['mean_diff']);
        $this->assertEquals(0.7659, $params['sigma']);
        $this->assertSame([1, 1, 1], [$params['p'], $params['d'], $params['q']]);

        $this->assertSame(112.92, $out[0]['value']);
        $this->assertSame(111.41, $out[0]['min']);
        $this->assertSame(114.42, $out[0]['max']);

        $this->assertSame(112.43, $out[1]['value']);
        $this->assertSame(110.31, $out[1]['min']);
        $this->assertSame(114.56, $out[1]['max']);

        $this->assertSame(114.95, $out[2]['value']);
        $this->assertSame(112.35, $out[2]['min']);
        $this->assertSame(117.55, $out[2]['max']);

        $this->assertSame('stable', $out[0]['trend']);
    }
}
