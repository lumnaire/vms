<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The supervisor's price guide becomes the market's official tariff (taripa):
 * one standard price per kilo for each fish, entered by the supervisor from the
 * municipal tariff list.
 *
 *  - price_guides.tariff_price is that standard price. Cheap/Moderate brackets
 *    are retired: their columns become nullable and stay only so old rows keep
 *    their values. Every row that has no tariff is switched off, so the tariff
 *    list starts empty and the supervisor fills it in.
 *  - A tariff is never edited in place. Changing it switches the current row off
 *    and adds a new one, so price_guides is the tariff's full history.
 *    set_by records which supervisor made the change.
 *  - vendor_inventories.tariff_price copies the tariff in force when the batch
 *    was submitted. The vendor's markup over it (their "tubo") stays correct
 *    after the tariff later changes.
 *  - vendor_inventories.price_note is the vendor's optional reason for pricing
 *    above the tariff, shown to staff, the supervisor and consumers.
 *  - fish_types.english_name is the English column of the tariff list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_guides', function (Blueprint $table) {
            $table->decimal('tariff_price', 10, 2)->nullable()->after('quality_class');
            // Audit only, not a foreign key: SQLite cannot add one to an existing table.
            $table->unsignedBigInteger('set_by')->nullable()->after('is_active');
            $table->decimal('cheap_max', 10, 2)->nullable()->change();
            $table->decimal('moderate_max', 10, 2)->nullable()->change();
            $table->index(['fish_type_id', 'is_active']);
        });

        DB::table('price_guides')->whereNull('tariff_price')->update(['is_active' => false]);

        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->decimal('tariff_price', 10, 2)->nullable()->after('price_per_kg');
            $table->string('price_note', 500)->nullable()->after('tariff_price');
        });

        Schema::table('fish_types', function (Blueprint $table) {
            $table->string('english_name', 100)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('fish_types', function (Blueprint $table) {
            $table->dropColumn('english_name');
        });

        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->dropColumn(['tariff_price', 'price_note']);
        });

        Schema::table('price_guides', function (Blueprint $table) {
            $table->dropIndex(['fish_type_id', 'is_active']);
            $table->dropColumn(['tariff_price', 'set_by']);
        });
    }
};
