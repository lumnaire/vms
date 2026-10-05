<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batches replace the AM / PM split.
 *
 * Every time a vendor submits the same fish and quality class on the same day,
 * that submission is the next batch: 10 kg of pusit is Batch 1, another 5 kg later
 * is Batch 2, and so on. Each batch keeps its own price, stock and age, so the
 * vendor can monitor how many days each one has left and the board can list them
 * separately under one fish.
 *
 * The number is stored rather than worked out on every page, because the vendor,
 * staff and the public board all show it, and a cancelled batch must not quietly
 * renumber the ones after it.
 *
 * market_session is left in place but no longer used: dropping it would throw
 * away which half of the day older rows were logged in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->unsignedSmallInteger('batch_no')->default(1)->after('quality_class');
        });

        // Number the rows that already exist, oldest first, per vendor, fish,
        // quality class and trading day.
        $counters = [];

        DB::table('vendor_inventories')
            ->orderBy('entry_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->select(['id', 'vendor_id', 'fish_type_id', 'quality_class', 'entry_date'])
            ->chunk(500, function ($rows) use (&$counters) {
                foreach ($rows as $row) {
                    $key = $row->vendor_id.'|'.$row->fish_type_id.'|'.$row->quality_class.'|'.substr((string) $row->entry_date, 0, 10);
                    $counters[$key] = ($counters[$key] ?? 0) + 1;

                    DB::table('vendor_inventories')->where('id', $row->id)->update(['batch_no' => $counters[$key]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->dropColumn('batch_no');
        });
    }
};
