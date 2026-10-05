<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,       // supervisor, staff, sample vendors
            FishTypeSeeder::class,        // 125 active fish types with quality classes
            PriceGuideSeeder::class,      // class-based price brackets per fish
            VendorInventorySeeder::class, // May 1 2026 – today historical vendor data
            ForecastDemoSeeder::class,    // 90 days of realistic history for 6 showcase fish
            ForecastSeeder::class,        // ARIMA 3-day price & supply forecast from tomorrow onward
        ]);
    }
}
