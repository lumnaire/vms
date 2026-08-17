<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceGuide extends Model
{
    protected $fillable = [
        'fish_type_id',
        'quality_class',
        'cheap_max',
        'moderate_max',
        'effective_date',
        'is_active',
    ];

    protected $casts = [
        'cheap_max'      => 'decimal:2',
        'moderate_max'   => 'decimal:2',
        'effective_date' => 'date',
        'is_active'      => 'boolean',
    ];

    public function fishType()
    {
        return $this->belongsTo(FishType::class);
    }

    // Returns Cheap / Moderate / Expensive label for a given price
    public function getPriceLabel(float $price): string
    {
        if ($price <= $this->cheap_max) {
            return 'Cheap';
        } elseif ($price <= $this->moderate_max) {
            return 'Moderate';
        } else {
            return 'Expensive';
        }
    }
}