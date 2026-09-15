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
            ForecastSeeder::class,        // ARIMA 14-day forecast from tomorrow onward
        ]);
    }
}