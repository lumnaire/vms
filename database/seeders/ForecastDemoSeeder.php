<?php

namespace Database\Seeders;

use App\Models\FishType;
use App\Models\Forecast;
use App\Models\User;
use App\Models\VendorInventory;
use App\Services\ArimaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ForecastDemoSeeder
 *
 * Gives a handful of showcase fish 90 days of realistic, confirmed supply history
 * so the supervisor's ARIMA forecast page has a genuine pattern to learn from.
 *
 * The general VendorInventorySeeder builds its prices from a modulo formula, which
 * jumps up and down like a saw blade. ARIMA cannot find a pattern in that — it
 * forecasts it about as well as "same as yesterday" and draws enormous ranges.
 * Real market data moves differently: a slow drift up or down, a little higher on
 * weekends, and small day-to-day noise. That is what this seeder produces.
 *
 * Each showcase fish has its own story, so the page shows every trend label:
 *   Hipon                     — lean season: the last 3 weeks the catch thins
 *                               and the price climbs            → upward
 *   Pusit                     — oversupply: the last 3 weeks boats land more
 *                               and the price slides            → downward
 *   Tangigue (natural)        — slow, steady price creep        → stable
 *   Bangus (medium)           — steady price, weekend bump      → stable
 *   Galunggong (saday payo)   — steady, busier weekends         → stable
 *   Tilapia                   — farmed, very steady             → stable
 *
 * Re-runnable: it replaces only the PAST rows of these six fish, leaves today and
 * every other fish untouched, then regenerates their forecasts.
 *
 * Usage:
 *   php artisan db:seed --class=ForecastDemoSeeder
 */
class ForecastDemoSeeder extends Seeder
{
    private const DAYS = 90;

    /** The "recent" part of a story: the last this-many days. */
    private const RECENT_DAYS = 21;

    /**
     * name => [start price, price change per day, extra price change per day in
     *          the recent window, start supply kg (market total), supply change
     *          per day, extra supply change per day in the recent window,
     *          weekend price bump, weekend supply bump]
     */
    private const SHOWCASE = [
        'Hipon' => [480, 0.20, 4.00, 30, 0.00, -0.35, 0.02, -0.08],
        'Pusit' => [270, 0.10, -2.50, 32, 0.00, 0.70, 0.01, 0.05],
        'Tangigue (natural)' => [380, 0.35, 0.00, 42, 0.00, 0.00, 0.03, -0.10],
        'Bangus (medium)' => [220, 0.00, 0.00, 60, 0.00, 0.00, 0.03, 0.15],
        'Galunggong (saday payo)' => [150, 0.02, 0.00, 75, 0.00, 0.00, 0.02, 0.12],
        'Tilapia' => [120, 0.00, 0.00, 50, 0.00, 0.00, 0.00, 0.00],
    ];

    public function run(ArimaService $arima): void
    {
        $staff = User::where('role', 'staff')->orderBy('id')->first();
        $vendors = User::where('role', 'vendor')->where('status', 'active')->orderBy('id')->get();

        if (! $staff || $vendors->count() < 3) {
            $this->command->error('ForecastDemoSeeder needs a staff account and at least 3 active vendors. Run UserSeeder first.');

            return;
        }

        $fishTypes = FishType::whereIn('name', array_keys(self::SHOWCASE))->where('is_active', true)->get()->keyBy('name');

        if ($fishTypes->isEmpty()) {
            $this->command->error('None of the showcase fish exist. Run FishTypeSeeder first.');

            return;
        }

        // Same numbers on every run, so the documentation and the page agree.
        mt_srand(20261005);

        $this->clearPastRows($fishTypes->pluck('id')->all());

        $today = today();
        $fishIndex = 0;
        $inserted = 0;

        foreach (self::SHOWCASE as $name => [$price0, $priceSlope, $priceRecent, $supply0, $supplySlope, $supplyRecent, $wkPrice, $wkSupply]) {
            $fish = $fishTypes->get($name);
            if (! $fish) {
                $this->command->warn("   – {$name} not found, skipped.");

                continue;
            }

            // Four regular sellers per fish, a different four for each fish.
            $sellers = $vendors->slice(($fishIndex * 2) % $vendors->count())->concat($vendors)->take(4)->values();
            $fishIndex++;

            $rows = [];
            for ($daysAgo = self::DAYS; $daysAgo >= 1; $daysAgo--) {
                $date = $today->copy()->subDays($daysAgo);
                $t = self::DAYS - $daysAgo; // 0 … 89
                $r = max(0, $t - (self::DAYS - self::RECENT_DAYS)); // days into the recent window
                $weekend = $date->isWeekend();

                // The day's market price and total supply: long drift + recent
                // move + weekend effect + small noise.
                $dayPrice = ($price0 + $priceSlope * $t + $priceRecent * $r)
                    * (1 + ($weekend ? $wkPrice : 0)) * (1 + $this->noise(0.012));
                $daySupply = max(5, ($supply0 + $supplySlope * $t + $supplyRecent * $r)
                    * (1 + ($weekend ? $wkSupply : 0)) * (1 + $this->noise(0.06)));

                // Split the day's supply across the sellers who came in. Most days
                // all four; now and then one stays home.
                $present = $sellers->filter(fn ($vendor, $i) => $i < 3 || mt_rand(1, 100) <= 80)->values();
                $shares = $present->map(fn () => mt_rand(80, 120))->all();
                $shareSum = array_sum($shares);

                foreach ($present as $i => $vendor) {
                    $kg = round($daySupply * $shares[$i] / $shareSum, 1);
                    // Each vendor prices a little above or below the market.
                    $price = round($dayPrice * (1 + ($i - 1.5) * 0.01) * (1 + $this->noise(0.008)), 2);

                    // Roughly one day in five a vendor brings the fish in two batches.
                    $batches = mt_rand(1, 100) <= 20 ? [round($kg * 0.6, 1), round($kg * 0.4, 1)] : [$kg];

                    foreach ($batches as $b => $batchKg) {
                        $confirmedAt = $date->copy()->setTime(6 + $b * 4, mt_rand(0, 59));

                        $rows[] = [
                            'vendor_id' => $vendor->id,
                            'fish_type_id' => $fish->id,
                            'quality_class' => $fish->quality_class,
                            'batch_no' => $b + 1,
                            'price_per_kg' => $b === 0 ? $price : round($price * 0.98, 2),
                            'stock_kg' => $batchKg,
                            'released_kg' => $batchKg,
                            // Past days are sold through, so these batches are closed
                            // history and never show up as stale stock.
                            'sold_kg' => $batchKg,
                            'status' => 'confirmed',
                            'confirmed_by' => $staff->id,
                            'confirmed_at' => $confirmedAt,
                            'entry_date' => $date->toDateString(),
                            'is_locked' => true,
                            'created_at' => $confirmedAt->copy()->subMinutes(20),
                            'updated_at' => $confirmedAt,
                        ];
                    }
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                VendorInventory::insert($chunk);
            }
            $inserted += count($rows);

            // Fit and store the forecasts straight away so the page has them.
            $series = 0;
            foreach (config('forecast.metrics') as $metric) {
                $series += $arima->generate($fish->id, $fish->quality_class, $metric) > 0 ? 1 : 0;
            }

            $trend = Forecast::where('fish_type_id', $fish->id)->where('metric', 'price')->value('trend') ?? '—';
            $this->command->info(sprintf('   ✔ %-26s %4d batches · price trend: %s', $name, count($rows), $trend));
        }

        $this->command->info("✅ ForecastDemoSeeder: {$inserted} confirmed batches over ".self::DAYS.' days. Open /supervisor/forecasts.');
    }

    /** Remove the past rows of the showcase fish so a re-run starts clean. */
    private function clearPastRows(array $fishTypeIds): void
    {
        $ids = VendorInventory::whereIn('fish_type_id', $fishTypeIds)
            ->whereDate('entry_date', '<', today())
            ->pluck('id');

        // The retired sale-report tables still hold a restrictive foreign key to
        // vendor_inventories, so their references go first.
        if (Schema::hasTable('vendor_sale_report_items')) {
            foreach ($ids->chunk(1000) as $chunk) {
                DB::table('vendor_sale_report_items')->whereIn('vendor_inventory_id', $chunk)->delete();
            }
        }

        foreach ($ids->chunk(1000) as $chunk) {
            VendorInventory::whereIn('id', $chunk)->delete();
        }
    }

    /** Roughly normal noise with the given standard deviation (sum of uniforms). */
    private function noise(float $sd): float
    {
        $u = 0.0;
        for ($i = 0; $i < 6; $i++) {
            $u += mt_rand() / mt_getrandmax();
        }

        // Six uniforms sum to mean 3, variance 0.5 → scale to the requested sd.
        return ($u - 3) / sqrt(0.5) * $sd;
    }
}
