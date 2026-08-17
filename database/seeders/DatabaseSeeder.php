<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,       // supervisor, staff, sample vendors
            FishTypeSeeder::class,   // common fish types in Catanduanes
            PriceGuideSeeder::class,        // sample price brackets per fish + quality
            VendorInventorySeeder::class,   // May 1 – June 6 2026 historical data (37 days)
            ForecastSeeder::class,          // ARIMA 14-day forecast from June 7 onward
        ]);
    }
}