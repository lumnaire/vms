<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\VendorProfile;

class LoadTestUserSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Load Test Accounts
        |--------------------------------------------------------------------------
        |
        | These accounts are ONLY for Locust/load testing.
        |
        | Username:
        |   loadtest_supervisor
        |   loadtest_staff
        |   loadtest_vendor
        |
        | Password:
        |   TEST_PASSWORD
        |
        */

        // ── Load Test Supervisor ─────────────────────────────────────────────

        User::updateOrCreate(
            ['username' => 'loadtest_supervisor'],
            [
                'name'       => 'Load Test Supervisor',
                'password'   => Hash::make('TEST_PASSWORD'),
                'role'       => 'supervisor',
                'status'     => 'active',
                'created_by' => null,
            ]
        );

        // ── Load Test Staff ──────────────────────────────────────────────────

        $staff = User::updateOrCreate(
            ['username' => 'loadtest_staff'],
            [
                'name'       => 'Load Test Staff',
                'password'   => Hash::make('TEST_PASSWORD'),
                'role'       => 'staff',
                'status'     => 'active',
                'created_by' => null,
            ]
        );

        // ── Load Test Vendor ─────────────────────────────────────────────────

        $vendor = User::updateOrCreate(
            ['username' => 'loadtest_vendor'],
            [
                'name'       => 'Load Test Vendor',
                'password'   => Hash::make('TEST_PASSWORD'),
                'role'       => 'vendor',
                'status'     => 'active',
                'created_by' => $staff->id,
            ]
        );

        VendorProfile::updateOrCreate(
            ['user_id' => $vendor->id],
            [
                'stall_number' => 'LOAD-TEST',
            ]
        );

        $this->command->info('Load test accounts created/updated successfully.');

        $this->command->info('');
        $this->command->info('Supervisor: loadtest_supervisor / TEST_PASSWORD');
        $this->command->info('Staff:      loadtest_staff      / TEST_PASSWORD');
        $this->command->info('Vendor:     loadtest_vendor     / TEST_PASSWORD');
    }
}