<?php

namespace Tests\Feature;

use App\Models\BatchRelease;
use App\Models\FishType;
use App\Models\Forecast;
use App\Models\PriceGuide;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use App\Services\ArimaService;
use App\Services\MarketAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 2026-10-20 revision: the tariff and vendor markup (tubo), the forecast's
 * highest/lowest days, kept history, and the dashboard figures.
 */
class TariffAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function vendor(string $stall): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        VendorProfile::create(['user_id' => $user->id, 'stall_number' => $stall]);

        return $user;
    }

    private function fish(string $name = 'Bangus', string $class = 'First Class'): FishType
    {
        return FishType::create(['name' => $name, 'quality_class' => $class, 'is_active' => true]);
    }

    private function tariff(FishType $fish, float $price): void
    {
        PriceGuide::setTariff($fish, $price, today()->toDateString(), null);
    }

    private function batch(User $vendor, FishType $fish, float $price, array $extra = []): VendorInventory
    {
        return VendorInventory::create(array_merge([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => $fish->quality_class,
            'price_per_kg' => $price,
            'tariff_price' => PriceGuide::tariffFor($fish->id, $fish->quality_class),
            'stock_kg' => 10,
            'released_kg' => 10,
            'sold_kg' => 0,
            'status' => 'confirmed',
            'entry_date' => today(),
            'is_locked' => false,
        ], $extra));
    }

    // ─── Public board ───────────────────────────────────────────

    public function test_board_shows_the_tariff_the_difference_and_the_highest_markup_stall(): void
    {
        $fish = $this->fish();
        $this->tariff($fish, 120);
        $low = $this->vendor('A-01');
        $high = $this->vendor('B-07');
        $this->batch($low, $fish, 125);
        $this->batch($high, $fish, 140, ['price_note' => 'Bigger size']);

        $response = $this->get('/')->assertOk();

        $vendors = collect($response->viewData('vendors'));
        $highRow = $vendors->firstWhere('stall', 'B-07')['fish'][0];
        $lowRow = $vendors->firstWhere('stall', 'A-01')['fish'][0];

        $this->assertSame(120.0, $highRow['tariff']);
        $this->assertSame(20.0, $highRow['diff']);
        $this->assertSame('above', $highRow['state']);
        $this->assertTrue($highRow['top_markup']);
        $this->assertSame('Bigger size', $highRow['note']);
        $this->assertFalse($lowRow['top_markup']);

        $compare = $response->viewData('comparison')->first();
        $this->assertSame(125.0, $compare['min']);
        $this->assertSame(140.0, $compare['max']);
        $this->assertSame('B-07', $compare['top']['stall']);
        $this->assertSame(2, $response->viewData('stats')['above_tariff']);

        $response->assertSee('Highest markup');
        $response->assertSee('BOARD_COMPARE', false);
    }

    public function test_no_stall_is_flagged_when_every_price_is_at_or_below_the_tariff(): void
    {
        $fish = $this->fish();
        $this->tariff($fish, 150);
        $this->batch($this->vendor('A-01'), $fish, 150);

        $row = collect($this->get('/')->viewData('vendors'))->first()['fish'][0];

        $this->assertSame('at', $row['state']);
        $this->assertFalse($row['top_markup']);
    }

    // ─── Forecast: highest and lowest are forecast days ────────

    public function test_forecast_highest_and_lowest_name_the_day_they_fall_on(): void
    {
        $fish = $this->fish();
        $vendor = $this->vendor('A-01');
        $this->tariff($fish, 110);

        foreach ([100, 104, 99, 106, 101, 108, 103, 110] as $i => $price) {
            $this->batch($vendor, $fish, $price, ['entry_date' => today()->subDays(8 - $i)]);
        }

        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get("/supervisor/forecasts?fish_type_id={$fish->id}&quality_class=First+Class&metric=price")
            ->assertOk();

        $forecasts = $response->viewData('forecasts');
        $summary = $response->viewData('summary');
        $values = $forecasts->map(fn ($f) => (float) $f->predicted_value)->values();

        $this->assertCount(3, $forecasts);
        $this->assertSame(round($values->avg(), 2), $summary['avg']);
        $this->assertSame($values->max(), $summary['high']['value']);
        $this->assertSame($values->min(), $summary['low']['value']);
        $this->assertSame($values->search($values->max()) + 1, $summary['high']['day']);
        $this->assertSame($values->search($values->min()) + 1, $summary['low']['day']);

        // The cards and the table name the same day.
        $highLabel = 'Day '.$summary['high']['day'].' · '.$summary['high']['date']->format('D, M j');
        if (! $summary['flat']) {
            $response->assertSee($highLabel);
            $response->assertSee('>Highest<', false);
            $response->assertSee('>Lowest<', false);
        }
        $response->assertSee('Lowest in Period');
        $response->assertSee('Highest in Period');
        $this->assertSame(110.0, $response->viewData('tariff'));
    }

    /**
     * Each day's run is kept beside the earlier ones. The page shows the latest
     * run, and the earlier predictions line up against the days that passed.
     */
    public function test_forecast_runs_are_kept_as_history(): void
    {
        $fish = $this->fish();
        $vendor = $this->vendor('A-01');
        foreach (range(1, 10) as $ago) {
            $this->batch($vendor, $fish, 100 + $ago, ['entry_date' => today()->subDays($ago)]);
        }

        $arima = app(ArimaService::class);
        $this->travel(-2)->days();
        $arima->generate($fish->id, 'First Class', 'price');
        $this->travelBack();

        $arima->generate($fish->id, 'First Class', 'price');
        $arima->generate($fish->id, 'First Class', 'price'); // same day: replaces, never duplicates

        $this->assertSame(6, Forecast::where('fish_type_id', $fish->id)->count());

        $latest = $arima->latestRun($fish->id, 'First Class', 'price');
        $this->assertCount(3, $latest);
        $this->assertTrue($latest->first()->forecast_date->isTomorrow());

        // The older run predicted yesterday; that prediction is now history.
        $past = $arima->pastPredictions($fish->id, 'First Class', 'price');
        $this->assertTrue($past->has(today()->subDay()->toDateString()));
    }

    // ─── History: releases behind old totals ───────────────────

    public function test_backfill_adds_the_releases_missing_behind_batch_totals_once(): void
    {
        $fish = $this->fish();
        $vendor = $this->vendor('A-01');
        $batch = $this->batch($vendor, $fish, 100, ['sold_kg' => 6, 'pulled_out_kg' => 1, 'entry_date' => today()->subDays(2)]);
        $recorded = $this->batch($vendor, $fish, 100, ['entry_date' => today()->subDay()]);
        $recorded->release(4);

        $this->assertSame(2, BatchRelease::backfillFromTotals());
        $this->assertSame(0, BatchRelease::backfillFromTotals());

        $sold = BatchRelease::where('vendor_inventory_id', $batch->id)->where('kind', 'sold')->first();
        $this->assertSame('6.00', $sold->kg);
        $this->assertTrue($sold->created_at->isSameDay(today()->subDays(2)));
        $this->assertSame(1, BatchRelease::where('vendor_inventory_id', $recorded->id)->count());
    }

    // ─── Dashboards ────────────────────────────────────────────

    public function test_sales_and_tubo_come_from_releases_at_the_batch_price(): void
    {
        $fish = $this->fish();
        $this->tariff($fish, 120);
        $vendor = $this->vendor('A-01');
        $this->batch($vendor, $fish, 130)->release(4);         // ₱520 sales, ₱40 tubo
        $this->batch($vendor, $fish, 110)->release(2);         // ₱220 sales, −₱20 tubo
        $this->batch($vendor, $fish, 130)->release(1, BatchRelease::PULLED_OUT);

        $analytics = app(MarketAnalytics::class);
        $today = $analytics->dailySales()[today()->toDateString()];

        $this->assertSame(6.0, $today['kg']);
        $this->assertSame(740.0, $today['value']);
        $this->assertSame(20.0, $today['tubo']);

        $board = $analytics->tuboLeaderboard(today()->startOfMonth())->first();
        $this->assertSame(20.0, $board['tubo']);
        $this->assertSame(2, $board['above']);
        $this->assertSame(3, $board['batches']);

        $outcome = $analytics->stockOutcome(today()->startOfMonth());
        $this->assertSame(6.0, $outcome['sold']);
        $this->assertSame(1.0, $outcome['pulled_out']);
        $this->assertSame(23.0, $outcome['on_stall']);
    }

    public function test_period_buckets_cover_the_window_and_sum_each_day_once(): void
    {
        $analytics = app(MarketAnalytics::class);

        $this->assertCount(30, $analytics->buckets('daily'));
        $this->assertCount(12, $analytics->buckets('monthly'));
        $this->assertCount(8, $analytics->buckets('quarterly'));
        $this->assertCount(5, $analytics->buckets('yearly'));

        $daily = [
            today()->toDateString() => 5.0,
            today()->subDay()->toDateString() => 2.0,
            today()->subYears(10)->toDateString() => 99.0, // outside every window
        ];

        $this->assertSame(7.0, array_sum($analytics->bucketize($daily, 'yearly')));
        $this->assertSame(5.0, last($analytics->bucketize($daily, 'daily')));
    }

    public function test_each_dashboard_renders_its_charts_and_staff_see_no_sales(): void
    {
        $fish = $this->fish();
        $this->tariff($fish, 120);
        $vendor = $this->vendor('A-01');
        $this->batch($vendor, $fish, 150)->release(2); // ₱300 sold, ₱60 tubo
        $idle = $this->vendor('C-03');

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $staff = User::factory()->create(['role' => 'staff']);

        $sup = $this->actingAs($supervisor)->get('/supervisor/dashboard')->assertOk();
        $this->assertSame(300.0, $sup->viewData('kpis')['sales']['current']);
        $this->assertSame(60.0, $sup->viewData('kpis')['tubo']['current']);
        $sup->assertSee('Highest fish price');
        $sup->assertSee('Markup Watch');

        $st = $this->actingAs($staff)->get('/staff/dashboard')->assertOk();
        $st->assertSee('id="weekChart"', false);
        $st->assertSee('Not Submitted Today');
        $st->assertSee($idle->name);
        $st->assertDontSee('Sales');
        $st->assertDontSee('₱300.00');
        $this->assertNull($st->viewData('markupBoard')->first()['tubo']);
        $this->assertSame(1, $st->viewData('aboveTariffToday'));

        $ven = $this->actingAs($vendor)->get('/vendor/dashboard')->assertOk();
        $this->assertSame(300.0, $ven->viewData('salesToday'));
        $this->assertSame(60.0, $ven->viewData('tuboMonth')['current']);
        $ven->assertSee('My Prices vs Tariff');
        $ven->assertSee('Highest markup on the board');
    }
}
