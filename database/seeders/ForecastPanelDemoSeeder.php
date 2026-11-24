<?php

namespace Database\Seeders;

use App\Models\BatchRelease;
use App\Models\FishType;
use App\Models\Forecast;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use App\Services\ArimaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Opt-in panel demonstration: generated observations, not market measurements.
 * Dedicated [DEMO] fish and inactive demo users keep researcher records intact.
 * The production ARIMA service fits every current and retrospective forecast.
 *
 * php artisan db:seed --class=ForecastPanelDemoSeeder
 */
class ForecastPanelDemoSeeder extends Seeder
{
    public const PREFIX = '[DEMO] ';

    public const MARKER = 'MOCK forecast-panel:v1';

    public const SEED = 20261124;

    private const BACKTEST_DAYS = 7;

    /** name => [class, scenario, price level, drift, noise, supply level, drift, noise] */
    private const SCENARIOS = [
        'Hipon' => ['Special Class', 'rising price / falling supply', 480, 3.6, 0.8, 55, -0.35, 0.12],
        'Pusit' => ['Special Class', 'falling price / rising supply', 300, -2.5, 0.7, 25, 0.75, 0.2],
        'Bangus (medium)' => ['Second Class', 'stable price / stable supply', 220, 0.0, 0.45, 60, 0.0, 0.2],
        'Tilapia' => ['Third Class', 'small daily fluctuations', 120, 0.08, 0.8, 80, 0.1, 1.2],
        'Tangigue (natural)' => ['First Class', 'more variable price and supply', 380, 1.0, 3.5, 50, 0.25, 1.3],
        'Galunggong (saday payo)' => ['Third Class', 'gradual decline with noise', 150, -0.6, 1.6, 90, -0.4, 2.0],
    ];

    public function run(ArimaService $arima): void
    {
        $window = (int) config('forecast.history_days');
        if ($window < (int) config('forecast.min_history') || $window < 7) {
            throw new RuntimeException('The forecast training window must allow at least 7 daily observations for the panel demo.');
        }
        $days = $window + self::BACKTEST_DAYS + 3;
        $this->command?->warn('SYNTHETIC PANEL DEMO: generated data and retrospective simulations; these are not actual market observations.');

        $summary = DB::transaction(function () use ($arima, $window, $days) {
            $staff = $this->demoUser('forecast_panel_staff', 'Forecast panel staff', 'staff');
            $vendors = [];
            for ($number = 1; $number <= 3; $number++) {
                $vendor = $this->demoUser('forecast_panel_vendor_'.$number, 'Forecast panel vendor '.$number, 'vendor');
                VendorProfile::firstOrCreate(['user_id' => $vendor->id], ['stall_number' => 'PANEL-DEMO-'.$number]);
                $vendors[] = $vendor;
            }

            $summary = [];
            foreach (self::SCENARIOS as $name => [$quality, $scenario, $price0, $priceDrift, $priceNoise, $supply0, $supplyDrift, $supplyNoise]) {
                $fish = FishType::firstOrCreate(['name' => self::PREFIX.$name], ['quality_class' => $quality, 'is_active' => true]);
                if ($fish->quality_class !== $quality) {
                    throw new RuntimeException('The reserved demo fish '.$fish->name.' has a different quality class.');
                }
                if (VendorInventory::where('fish_type_id', $fish->id)
                    ->where(fn ($query) => $query->whereNull('price_note')->orWhere('price_note', 'not like', self::MARKER.'%'))->exists()) {
                    throw new RuntimeException('The reserved demo fish '.$fish->name.' contains unmarked records; no records have been changed.');
                }
                if (! $fish->is_active) {
                    $fish->update(['is_active' => true]);
                }

                $seed = self::SEED + count($summary) * 100;
                $prices = $this->simulate($days, $price0, $priceDrift, $priceNoise, $seed);
                $supplies = $this->simulate($days, $supply0, $supplyDrift, $supplyNoise, $seed + 1);
                $daily = ['price' => [], 'supply' => []];
                $batchNumbers = [];
                foreach ($vendors as $vendor) {
                    $batchNumbers[$vendor->id] = (int) VendorInventory::where('vendor_id', $vendor->id)
                        ->where('fish_type_id', $fish->id)->where('quality_class', $quality)->max('batch_no');
                }

                for ($index = 0; $index < $days; $index++) {
                    $date = today()->subDays($days - $index);
                    $key = $date->toDateString();
                    $total = round($supplies[$index], 2);
                    $quantities = [round($total * 0.3, 2), round($total * 0.33, 2)];
                    $quantities[] = round($total - array_sum($quantities), 2);
                    $batchPrices = [];

                    foreach ($vendors as $seller => $vendor) {
                        $price = round($prices[$index] * (1 + ($seller - 1) * 0.01), 2);
                        $batchPrices[] = $price;
                        $identity = ['vendor_id' => $vendor->id, 'fish_type_id' => $fish->id,
                            'quality_class' => $quality];
                        $existing = VendorInventory::where($identity)->whereDate('entry_date', $key)->first();
                        if ($existing && ! str_starts_with((string) $existing->price_note, self::MARKER)) {
                            throw new RuntimeException('An unmarked inventory record uses a reserved demo identity; no records have been changed.');
                        }
                        $at = $date->copy()->setTime(6 + $seller, 0);
                        $batch = $existing ?? new VendorInventory;
                        $batch->fill($identity + [
                            'entry_date' => $key,
                            'batch_no' => $existing?->batch_no ?? ++$batchNumbers[$vendor->id],
                            'price_per_kg' => $price, 'tariff_price' => null,
                            'price_note' => self::MARKER.'; seed='.self::SEED.'; '.$scenario,
                            'stock_kg' => $quantities[$seller], 'released_kg' => $quantities[$seller],
                            'sold_kg' => 0, 'pulled_out_kg' => $quantities[$seller],
                            'status' => 'confirmed', 'confirmed_by' => $staff->id, 'confirmed_at' => $at,
                            'is_locked' => true,
                        ]);
                        $batch->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
                        // Close demo stock without inventing sales or leaving
                        // historical batches on the live public price board.
                        $release = BatchRelease::firstOrNew(['vendor_inventory_id' => $batch->id, 'kind' => BatchRelease::PULLED_OUT]);
                        if ($release->exists && ! str_starts_with((string) $release->reason, self::MARKER)) {
                            throw new RuntimeException('A reserved demo batch has an unmarked release; no records have been changed.');
                        }
                        $release->fill([
                            'vendor_id' => $vendor->id, 'kg' => $quantities[$seller],
                            'reason' => self::MARKER.'; closed synthetic batch',
                        ]);
                        $release->forceFill(['created_at' => $date->copy()->setTime(18, 0), 'updated_at' => $date->copy()->setTime(18, 0)])->save();
                    }
                    $daily['price'][$key] = array_sum($batchPrices) / count($batchPrices);
                    $daily['supply'][$key] = $total;
                }

                // Refresh only this dedicated demo fish's derived forecasts.
                // Real fish IDs, inventories, accounts and forecast rows remain intact.
                Forecast::where('fish_type_id', $fish->id)->delete();
                $retrospective = 0;
                foreach (config('forecast.metrics') as $metric) {
                    $retrospective += $this->backtest($arima, $fish, $quality, $metric, $daily[$metric], $window, $scenario);
                    if ($arima->generate($fish->id, $quality, $metric) !== (int) config('forecast.horizon')) {
                        throw new RuntimeException('The production model could not fit the demo series '.$fish->name.' / '.$metric.'.');
                    }
                    foreach ($arima->latestRun($fish->id, $quality, $metric) as $forecast) {
                        $forecast->update(['arima_params' => $forecast->arima_params + $this->source($scenario, 'current_demo_forecast')]);
                    }
                }
                $summary[] = [$fish->name, $quality, $scenario, $days,
                    $arima->latestRun($fish->id, $quality, 'price')->first()->trend,
                    $arima->latestRun($fish->id, $quality, 'supply')->first()->trend, $retrospective];
            }

            return $summary;
        });

        $this->command?->table(['Synthetic fish', 'Class', 'Scenario', 'Days', 'Price trend', 'Supply trend', 'Past forecast rows'], $summary);
        $current = count($summary) * count(config('forecast.metrics')) * (int) config('forecast.horizon');
        $this->command?->info('Panel demo ready: '.count($summary)." [DEMO] fish, {$days} history days, {$current} current forecast points.");
        $this->command?->line('Open /supervisor/forecasts and select a [DEMO] fish. Historical scores are retrospective simulations on synthetic data.');
        $this->command?->line('Use real market data and operational checks to assess deployment readiness.');
    }

    private function demoUser(string $username, string $name, string $role): User
    {
        $user = User::firstOrCreate(['username' => $username], [
            'name' => self::PREFIX.$name, 'role' => $role,
            'status' => 'inactive', 'password' => Str::random(64),
        ]);
        if ($user->role !== $role || $user->name !== self::PREFIX.$name) {
            throw new RuntimeException('The reserved demo username '.$username.' is already used by another account.');
        }

        return $user;
    }

    /** Simulate an ARIMA(1,1,1) process; production fitting estimates its own parameters. */
    private function simulate(int $days, float $level, float $drift, float $sigma, int $seed): array
    {
        $random = new Randomizer(new Mt19937($seed));
        $deviation = 0.0;
        $previousNoise = 0.0;
        $values = [];
        for ($index = -60; $index < $days; $index++) {
            $u = $random->getInt(1, 1000000) / 1000001;
            $v = $random->getInt(0, 1000000) / 1000001;
            $noise = $sigma * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
            $deviation = 0.35 * $deviation + $noise + 0.2 * $previousNoise;
            $previousNoise = $noise;
            if ($index >= 0) {
                $level += $drift + $deviation;
                $values[] = max(1.0, $level);
            }
        }

        return $values;
    }

    /** Retrospective rolling-origin simulations; each fit sees only earlier dates. */
    private function backtest(ArimaService $arima, FishType $fish, string $quality, string $metric, array $daily, int $window, string $scenario): int
    {
        $written = 0;
        for ($daysAgo = self::BACKTEST_DAYS + 1; $daysAgo >= 2; $daysAgo--) {
            $origin = today()->subDays($daysAgo);
            $start = $origin->copy()->subDays($window)->toDateString();
            $end = $origin->toDateString();
            $training = array_filter($daily, fn ($date) => $date >= $start && $date < $end, ARRAY_FILTER_USE_KEY);
            $points = $arima->project(array_values($training), 1);
            if (count($points) !== (int) config('forecast.horizon')) {
                throw new RuntimeException('A synthetic retrospective fit did not converge for '.$fish->name.' / '.$metric.'.');
            }
            foreach ($points as $index => $point) {
                $target = $origin->copy()->addDays($index + 1);
                if (! $target->lt(today())) {
                    continue;
                }
                $params = array_replace($point['params'], $this->source($scenario, 'retrospective_rolling_origin'), [
                    'training_start' => array_key_first($training), 'training_end' => array_key_last($training),
                    'as_of_date' => $origin->toDateString(),
                ]);
                Forecast::create([
                    'fish_type_id' => $fish->id, 'quality_class' => $quality, 'metric' => $metric,
                    'forecast_date' => $target, 'predicted_value' => $point['value'],
                    'predicted_min' => $point['min'], 'predicted_max' => $point['max'],
                    'trend' => $point['trend'], 'arima_params' => $params,
                    'generated_at' => $origin->copy()->setTime(0, 1),
                ]);
                $written++;
            }
        }

        return $written;
    }

    private function source(string $scenario, string $evaluation): array
    {
        return ['data_source' => 'synthetic_panel_demo', 'demo_seed' => self::SEED,
            'scenario' => $scenario, 'evaluation' => $evaluation,
            'simulation_created_at' => now()->toIso8601String()];
    }
}
