<?php

namespace Database\Seeders;

use App\Models\BatchRelease;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,       // supervisor, staff, sample vendors
            FishTypeSeeder::class,        // 125 active fish types with quality classes
            // No tariff seeder: the supervisor enters the official tariff by hand.
            VendorInventorySeeder::class, // May 1 2026 – today historical vendor data
            ForecastDemoSeeder::class,    // 90 days of realistic history for 6 showcase fish
            ForecastSeeder::class,        // ARIMA 3-day price & supply forecast from tomorrow onward
        ]);

        // The demo batches carry sold / pulled-out totals only; add the release
        // records the sales charts read.
        $added = BatchRelease::backfillFromTotals();
        $this->command->info("✅ Release records backfilled: {$added}.");
    }
}
