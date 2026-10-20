<?php

use App\Models\BatchRelease;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History for reporting and for retraining the forecast model.
 *
 *  - Forecast runs are no longer overwritten. Each day's run is kept beside the
 *    earlier ones (re-running on the same day replaces only that day's run), so
 *    a past prediction can be compared with what actually came in. The index
 *    finds a series' latest run.
 *  - Sales are read from batch_releases, dated by when the vendor recorded them.
 *    Batches recorded before releases existed (and the demo seeders) only carry
 *    sold_kg / pulled_out_kg totals, so the missing releases are added for them,
 *    dated on the batch's own day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->index(['fish_type_id', 'quality_class', 'metric', 'generated_at'], 'forecasts_series_run_index');
        });

        BatchRelease::backfillFromTotals();
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropIndex('forecasts_series_run_index');
        });

        // Backfilled releases are left in place: they agree with the batch totals.
    }
};
