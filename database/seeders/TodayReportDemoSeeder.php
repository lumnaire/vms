<?php

namespace Database\Seeders;

use App\Models\FishType;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TodayReportDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('This demo seeder is for the local environment only.');
        }

        DB::transaction(function () {
            $staff = User::whereIn('role', ['staff', 'supervisor'])->first();
            $fish = FishType::where('is_active', true)->orderBy('id')->first()
                ?? FishType::create(['name' => 'Demo Bangus', 'quality_class' => 'First Class', 'is_active' => true]);

            foreach (['Ana', 'Ben', 'Carla'] as $index => $name) {
                $vendor = User::firstOrCreate(
                    ['username' => 'report_demo_'.strtolower($name)],
                    ['name' => 'Demo Vendor '.$name, 'password' => 'LocalDemoOnly2026!', 'role' => 'vendor', 'status' => 'active']
                );
                VendorProfile::firstOrCreate(
                    ['user_id' => $vendor->id],
                    ['stall_number' => 'DEMO-'.($index + 1)]
                );
                foreach ([1, 2] as $batchNo) {
                    $kg = 10 + $index * 10 + $batchNo * 5;
                    VendorInventory::firstOrCreate(
                        ['vendor_id' => $vendor->id, 'entry_date' => today()->toDateString(), 'batch_no' => $batchNo],
                        [
                            'fish_type_id' => $fish->id,
                            'quality_class' => $batchNo === 1 ? 'First Class' : 'Second Class',
                            'stock_kg' => $kg, 'released_kg' => $kg,
                            'sold_kg' => 0, 'pulled_out_kg' => 0,
                            'price_per_kg' => 180 + $index * 20,
                            'status' => 'confirmed', 'confirmed_by' => $staff?->id,
                            'confirmed_at' => now(), 'is_locked' => false,
                            'created_at' => today()->setTime(6 + $index, $batchNo * 10),
                        ]
                    );
                }
            }
        });

        $this->command->info('Demo report data ready for '.today()->toDateString().': 3 demo vendors, 6 confirmed batches, 165 kg.');
    }
}
