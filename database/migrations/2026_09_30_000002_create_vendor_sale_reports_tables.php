<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor sale reports.
 *
 * A vendor closes the trading day by declaring how much of each fish they
 * actually sold. Previously `sold_kg` was edited entry-by-entry straight onto
 * vendor_inventories, which meant the figure could drift away from the day's
 * confirmed entries and there was no record of who declared it or when.
 *
 * The report is the declaration of record:
 *   vendor_sale_reports       — one row per vendor per day, with the day's totals
 *   vendor_sale_report_items  — one row per confirmed inventory entry, with the
 *                               vendor's declared kg and the resulting value
 *
 * Fish, quality and price are copied onto the item rather than joined at read
 * time. A sale report is a statement about a past trading day, so it must keep
 * showing what was true that day even after a price guide or quality class is
 * later corrected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_sale_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')
                ->constrained('users')
                ->restrictOnDelete(); // a vendor with sale history is never deleted

            // The trading day being reported on. One report per vendor per day.
            $table->date('report_date');

            // Denormalised day totals, kept on the report so the staff/supervisor
            // tables and the calendar do not have to aggregate items on every read.
            $table->decimal('total_stock_kg', 12, 2)->default(0); // kg released for sale that day
            $table->decimal('total_sold_kg', 12, 2)->default(0);  // kg the vendor declared sold
            $table->decimal('total_value', 14, 2)->default(0);     // sum of item total_price

            $table->unsignedInteger('item_count')->default(0);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'report_date']);
            $table->index('report_date');
        });

        Schema::create('vendor_sale_report_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_sale_report_id')
                ->constrained('vendor_sale_reports')
                ->cascadeOnDelete();

            // The confirmed entry this line declares sales against.
            $table->foreignId('vendor_inventory_id')
                ->unique() // one declaration per entry, so totals cannot double-count
                ->constrained('vendor_inventories')
                ->restrictOnDelete();

            // Snapshots of the trading-day facts. See the migration docblock.
            $table->foreignId('fish_type_id')->constrained('fish_types')->restrictOnDelete();
            $table->string('fish_type_name');
            $table->enum('quality_class', [
                'First Class',
                'Second Class',
                'Third Class',
                'Fourth Class',
                'Special Class',
            ]);
            $table->decimal('price_per_kg', 10, 2);  // price as confirmed that day
            $table->decimal('released_kg', 10, 2);   // kg released for sale that day
            $table->decimal('total_kg', 10, 2);      // kg the vendor declared sold
            $table->decimal('total_price', 12, 2);  // total_kg * price_per_kg

            $table->timestamps();

            // MySQL caps identifiers at 64 characters, so the auto-generated
            // name for this pair would be rejected. Named explicitly.
            $table->index(
                ['vendor_sale_report_id', 'quality_class'],
                'vsri_report_quality_idx'
            );

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_sale_report_items');
        Schema::dropIfExists('vendor_sale_reports');
    }
};
