<?php

use App\Models\Forecast;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the `demand` metric from forecasts, leaving price and supply.
 *
 * `demand` was derived from sold_kg, which is itself filled in from the vendor's
 * end-of-day sale report, so projecting it fed the model its own output.
 *
 *  - stored demand projections are cleared
 *  - the enum is narrowed to price/supply
 *  - the legacy `volume` value is not reinstated; it predates the supply metric
 *    and nothing generates it
 *
 * Run `php artisan forecast:generate` after migrating to repopulate.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Every row is derived state that `forecast:generate` rebuilds from
        // confirmed vendor inventory, so deleting only the demand rows is safe
        // and leaves the still-valid price/supply projections in place.
        Forecast::where('metric', 'demand')->delete();

        Schema::table('forecasts', function (Blueprint $table) {
            $table->enum('metric', ['price', 'supply'])
                ->default('price')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->enum('metric', ['price', 'supply', 'demand'])
                ->default('price')
                ->change();
        });

        // Demand rows are projections, not history: they are regenerated rather
        // than restored, so `forecast:generate` has to run again to refill them.
    }
};
