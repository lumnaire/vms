<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_by')
                  ->constrained('users')
                  ->restrictOnDelete(); // prevent deleting user who has reports
            $table->enum('report_type', [
                'daily_price',
                'supply_summary',
                'forecast_summary',
                'vendor_performance'
            ]);
            $table->date('report_date');
            $table->json('report_data'); // stores the full report content
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};