<?php

use App\Models\Forecast;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switches the forecasts table from a 14-day price/volume horizon to a
 * 3-day price/supply/demand horizon.
 *
 *  - `volume` is replaced by `supply` (same underlying stock_kg series)
 *  - `demand` is added as a new metric driven by sold_kg
 *  - stored projections are cleared, because the existing rows were produced by
 *    a 14-day model that no longer applies
 *
 * Run `php artisan forecast:generate` after migrating to repopulate.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Widen the enum first so the narrowing below cannot reject rows
        //    that still carry the legacy `volume` value.
        Schema::table('forecasts', function (Blueprint $table) {
            $table->enum('metric', ['price', 'volume', 'supply', 'demand'])
                ->default('price')
                ->change();
        });

        // 2. Every row is derived state that `forecast:generate` rebuilds from
        //    confirmed vendor inventory, so clearing it is safe and avoids
        //    leaving orphaned 14-day values behind for series the new run
        //    cannot regenerate.
        Forecast::truncate();

        // 3. Drop `volume` now that no row references it.
        Schema::table('forecasts', function (Blueprint $table) {
            $table->enum('metric', ['price', 'supply', 'demand'])
                ->default('price')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->enum('metric', ['price', 'supply', 'demand', 'volume'])
                ->default('price')
                ->change();
        });

        Forecast::truncate();

        Schema::table('forecasts', function (Blueprint $table) {
            $table->enum('metric', ['price', 'volume'])
                ->default('price')
                ->change();
        });
    }
};
