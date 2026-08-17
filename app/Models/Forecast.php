<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    protected $fillable = [
        'fish_type_id',
        'quality_class',
        'metric',
        'forecast_date',
        'predicted_value',
        'predicted_min',
        'predicted_max',
        'trend',
        'arima_params',
        'generated_at',
    ];

    protected $casts = [
        'forecast_date'   => 'date',
        'predicted_value' => 'decimal:2',
        'predicted_min'   => 'decimal:2',
        'predicted_max'   => 'decimal:2',
        'arima_params'    => 'array',
        'generated_at'    => 'datetime',
    ];

    public function fishType()
    {
        return $this->belongsTo(FishType::class);
    }
}