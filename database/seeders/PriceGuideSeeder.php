<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FishType;
use App\Models\PriceGuide;

class PriceGuideSeeder extends Seeder
{
    public function run(): void
    {
        // Sample price brackets (PHP per kg) for First Class only as baseline.
        // Adjust values based on actual Virac market rates.
        //
        // cheap_max    = up to this price → Cheap
        // moderate_max = up to this price → Moderate  (above = Expensive)

        $guides = [
            'Bangus (Milkfish)' => [
                'First Class'   => ['cheap_max' => 150.00, 'moderate_max' => 220.00],
                'Second Class'  => ['cheap_max' => 120.00, 'moderate_max' => 180.00],
                'Third Class'   => ['cheap_max' => 90.00,  'moderate_max' => 140.00],
            ],
            'Tilapia' => [
                'First Class'   => ['cheap_max' => 100.00, 'moderate_max' => 160.00],
                'Second Class'  => ['cheap_max' => 80.00,  'moderate_max' => 130.00],
                'Third Class'   => ['cheap_max' => 60.00,  'moderate_max' => 100.00],
            ],
            'Maya-maya (Red Snapper)' => [
                'First Class'   => ['cheap_max' => 220.00, 'moderate_max' => 320.00],
                'Second Class'  => ['cheap_max' => 180.00, 'moderate_max' => 270.00],
                'Special Class' => ['cheap_max' => 300.00, 'moderate_max' => 450.00],
            ],
            'Galunggong (Round Scad)' => [
                'First Class'   => ['cheap_max' => 120.00, 'moderate_max' => 180.00],
                'Second Class'  => ['cheap_max' => 90.00,  'moderate_max' => 140.00],
                'Third Class'   => ['cheap_max' => 70.00,  'moderate_max' => 110.00],
            ],
            'Hipon (Shrimp)' => [
                'First Class'   => ['cheap_max' => 300.00, 'moderate_max' => 450.00],
                'Second Class'  => ['cheap_max' => 220.00, 'moderate_max' => 350.00],
                'Special Class' => ['cheap_max' => 400.00, 'moderate_max' => 600.00],
            ],
        ];

        $effectiveDate = now()->toDateString();

        foreach ($guides as $fishName => $classes) {
            $fishType = FishType::where('name', $fishName)->first();

            if (!$fishType) continue;

            foreach ($classes as $qualityClass => $prices) {
                PriceGuide::create([
                    'fish_type_id'   => $fishType->id,
                    'quality_class'  => $qualityClass,
                    'cheap_max'      => $prices['cheap_max'],
                    'moderate_max'   => $prices['moderate_max'],
                    'effective_date' => $effectiveDate,
                    'is_active'      => true,
                ]);
            }
        }
    }
}