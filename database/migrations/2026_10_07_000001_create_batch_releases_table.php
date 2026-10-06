<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Release used to mean only "record a sale" (sold_kg). Vendors can now also
 * release leftover stock that was not sold: pulled off the stall, spoiled,
 * taken home. Both take kilograms off the batch's remaining stock.
 *
 * pulled_out_kg is the running total of non-sale releases on the batch, next to
 * sold_kg, so remaining = released_kg − sold_kg − pulled_out_kg.
 *
 * batch_releases keeps every release as its own record: who, which batch, how
 * many kilograms, which kind, why and when. Supply reports list the pull-outs
 * from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->decimal('pulled_out_kg', 10, 2)->default(0)->after('sold_kg');
        });

        Schema::create('batch_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_inventory_id')->constrained('vendor_inventories')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 20); // sold | pulled_out
            $table->decimal('kg', 10, 2);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_releases');

        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->dropColumn('pulled_out_kg');
        });
    }
};
