<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FishType;

class FishTypeSeeder extends Seeder
{
    public function run(): void
    {
        $fishTypes = [
            'Bangus (Milkfish)',
            'Tilapia',
            'Maya-maya (Red Snapper)',
            'Galunggong (Round Scad)',
            'Alumahan (Indian Mackerel)',
            'Tanigue (Spanish Mackerel)',
            'Tulingan (Bullet Tuna)',
            'Talakitok (Trevally)',
            'Hipon (Shrimp)',
            'Pusit (Squid)',
            'Alimasag (Blue Crab)',
            'Lapu-lapu (Grouper)',
            'Dilis (Anchovies)',
            'Espada (Ribbonfish)',
            'Hito (Catfish)',
        ];

        foreach ($fishTypes as $name) {
            FishType::create([
                'name'      => $name,
                'is_active' => true,
            ]);
        }
    }
}