<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FishType;
use App\Models\PriceGuide;

class PriceGuideSeeder extends Seeder
{
    public function run(): void
    {
        PriceGuide::truncate();

        // Price brackets (PHP per kg) per quality class. Every fish type belongs
        // to exactly one class, so each fish type gets a single bracket.
        //
        // cheap_max    = up to this price → Cheap
        // moderate_max = up to this price → Moderate  (above = Expensive)
        $brackets = [
            'Special Class' => ['cheap_max' => 360.00, 'moderate_max' => 640.00],
            'First Class'   => ['cheap_max' => 240.00, 'moderate_max' => 460.00],
            'Second Class'  => ['cheap_max' => 150.00, 'moderate_max' => 300.00],
            'Third Class'   => ['cheap_max' =>  90.00, 'moderate_max' => 190.00],
            'Fourth Class'  => ['cheap_max' =>  55.00, 'moderate_max' => 130.00],
        ];

        $effectiveDate = now()->toDateString();
        $created       = 0;
        $skipped       = 0;

        FishType::where('is_active', true)
            ->whereNotNull('quality_class')
            ->orderBy('name')
            ->get()
            ->each(function (FishType $fishType) use ($brackets, $effectiveDate, &$created, &$skipped) {
                $bracket = $brackets[$fishType->quality_class] ?? null;
                if (!$bracket) {
                    $skipped++;
                    return;
                }

                PriceGuide::create([
                    'fish_type_id'   => $fishType->id,
                    'quality_class'  => $fishType->quality_class,
                    'cheap_max'      => $bracket['cheap_max'],
                    'moderate_max'   => $bracket['moderate_max'],
                    'effective_date' => $effectiveDate,
                    'is_active'      => true,
                ]);

                $created++;
            });

        $this->command->info("✅ PriceGuideSeeder: {$created} brackets created | Skipped: {$skipped}.");
    }
}