<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fish_type_id')
                  ->constrained('fish_types')
                  ->cascadeOnDelete(); // delete guide if fish type is deleted
            $table->enum('quality_class', [
                'First Class',
                'Second Class',
                'Third Class',
                'Fourth Class',
                'Special Class'
            ]);
            $table->decimal('cheap_max', 10, 2);    // up to this price = Cheap
            $table->decimal('moderate_max', 10, 2); // up to this = Moderate, above = Expensive
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_guides');
    }
};