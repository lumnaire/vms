<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\VendorProfile;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Supervisor ─────────────────────────────────────────────────────────
        $supervisor = User::create([
            'name'       => 'Market Supervisor',
            'username'   => 'supervisor',
            'password'   => Hash::make('supervisor123'),
            'role'       => 'supervisor',
            'status'     => 'active',
            'created_by' => null,
        ]);

        // ── Market Staff ────────────────────────────────────────────────────────
        $staff = User::create([
            'name'       => 'Market Staff Officer',
            'username'   => 'staff',
            'password'   => Hash::make('staff123'),
            'role'       => 'staff',
            'status'     => 'active',
            'created_by' => $supervisor->id,
        ]);

        // ── Vendors (based on actual Virac Public Market fish section records) ─
        // Names and stall numbers match the screenshots in the capstone paper.
        // All use password: vendor123
        $vendors = [
            ['name' => 'Sally Tatualia',      'username' => 'vendor46', 'stall' => 'FS-46'],
            ['name' => 'Folcar Mancams',       'username' => 'vendor47', 'stall' => 'FS-47'],
            ['name' => 'Arnel Sarmiento',      'username' => 'vendor48', 'stall' => 'FS-48'],
            ['name' => 'Meamie Torres',        'username' => 'vendor49', 'stall' => 'FS-49'],
            ['name' => 'Elena Ibatan',         'username' => 'vendor50', 'stall' => 'FS-50'],
            ['name' => 'Rubina Banti',         'username' => 'vendor51', 'stall' => 'FS-51'],
            ['name' => 'Cary Glenn Ercola',    'username' => 'vendor52', 'stall' => 'FS-52'],
            ['name' => 'Gemma Sarmiento',      'username' => 'vendor53', 'stall' => 'FS-53'],
            ['name' => 'Sherly Calibin',       'username' => 'vendor54', 'stall' => 'FS-54'],
            ['name' => 'Nida Fernandez',       'username' => 'vendor55', 'stall' => 'FS-55'],
            ['name' => 'Agnes Baba',           'username' => 'vendor56', 'stall' => 'FS-56'],
            ['name' => 'Sarah Jane Tafe',      'username' => 'vendor57', 'stall' => 'FS-57'],
        ];

        foreach ($vendors as $data) {
            $vendor = User::create([
                'name'       => $data['name'],
                'username'   => $data['username'],
                'password'   => Hash::make('vendor123'),
                'role'       => 'vendor',
                'status'     => 'active',
                'created_by' => $staff->id,
            ]);

            VendorProfile::create([
                'user_id'      => $vendor->id,
                'stall_number' => $data['stall'],
            ]);
        }
    }
}