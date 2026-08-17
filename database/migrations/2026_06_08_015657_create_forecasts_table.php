<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fish_type_id')
                  ->constrained('fish_types')
                  ->cascadeOnDelete();
            $table->enum('quality_class', [
                'First Class',
                'Second Class',
                'Third Class',
                'Fourth Class',
                'Special Class'
            ])->nullable();
            $table->enum('metric', ['price', 'volume']); // forecasting price or supply volume
            $table->date('forecast_date');
            $table->decimal('predicted_value', 10, 2);
            $table->decimal('predicted_min', 10, 2)->nullable();
            $table->decimal('predicted_max', 10, 2)->nullable();
            $table->enum('trend', ['upward', 'downward', 'stable'])->nullable();
            $table->json('arima_params')->nullable(); // stores p, d, q values
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};