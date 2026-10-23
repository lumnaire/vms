<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vendor_inventories.reject_reason is staff's optional message to the vendor
 * when a batch is rejected, shown to the vendor next to the rejected batch.
 *
 * price_note (the vendor's note on their price) is now kept on every
 * submission, not only when the price is above the tariff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->string('reject_reason', 500)->nullable()->after('price_note');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->dropColumn('reject_reason');
        });
    }
};
