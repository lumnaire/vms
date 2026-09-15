<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FishType extends Model
{
    public const QUALITY_CLASSES = [
        'First Class',
        'Second Class',
        'Third Class',
        'Fourth Class',
        'Special Class',
    ];

    protected $fillable = [
        'name',
        'quality_class',
        'is_active',
        'image_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function priceGuides()
    {
        return $this->hasMany(PriceGuide::class);
    }

    public function vendorInventories()
    {
        return $this->hasMany(VendorInventory::class);
    }

    public function forecasts()
    {
        return $this->hasMany(Forecast::class);
    }
}