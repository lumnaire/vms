<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_inventories', function (Blueprint $table) {
            $table->id();

            // vendor who submitted this entry
            $table->foreignId('vendor_id')
                  ->constrained('users')
                  ->restrictOnDelete(); // prevent deleting vendor with existing records

            // fish type being sold
            $table->foreignId('fish_type_id')
                  ->constrained('fish_types')
                  ->restrictOnDelete(); // prevent deleting fish type with existing records

            $table->enum('quality_class', [
                'First Class',
                'Second Class',
                'Third Class',
                'Fourth Class',
                'Special Class'
            ]);

            $table->decimal('price_per_kg', 10, 2);
            $table->decimal('stock_kg', 10, 2)->default(0);    // initial stock brought to market
            $table->decimal('released_kg', 10, 2)->default(0); // amount released for sale
            $table->decimal('sold_kg', 10, 2)->default(0);     // amount sold

            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');

            // staff who confirmed this entry (nullable - not yet confirmed)
            $table->foreignId('confirmed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete(); // set to null if staff account is deleted

            $table->timestamp('confirmed_at')->nullable();
            $table->date('entry_date');
            $table->boolean('is_locked')->default(false); // locked after same day of entry
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_inventories');
    }
};