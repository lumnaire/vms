<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\User;
use App\Models\VendorInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorReportHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_a_filtered_read_only_page_with_daily_aggregates(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $fish = FishType::create(['name' => '[DEMO] Hipon', 'quality_class' => 'Special Class', 'is_active' => true]);
        foreach (['confirmed', 'confirmed', 'pending'] as $status) {
            VendorInventory::create([
                'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'Special Class',
                'entry_date' => '2026-11-23', 'price_per_kg' => 200, 'tariff_price' => 180,
                'stock_kg' => 10, 'sold_kg' => 3, 'pulled_out_kg' => 1, 'released_kg' => 10, 'status' => $status,
            ]);
        }
        $before = VendorInventory::all()->map->getRawOriginal()->all();
        $response = $this->actingAs($supervisor)->get('/supervisor/reports/history?from=2026-11-23&to=2026-11-23');
        $response->assertOk()->assertViewIs('supervisor.report-history')->assertSee('Hipon')->assertDontSee('[DEMO]');
        $this->assertFalse($response->headers->has('Content-Disposition'));
        $rows = $response->viewData('rows');
        $this->assertSame(1, $rows->total());
        $this->assertEquals(20, $rows[0]->supply_kg);
        $this->assertEquals(6, $rows[0]->sold_kg);
        $this->assertEquals(1200, $rows[0]->sales_value);
        $this->assertSame($before, VendorInventory::all()->map->getRawOriginal()->all());
        $this->get('/supervisor/reports/history?from=2026-11-24&to=2026-11-24')->assertOk()->assertSee('No confirmed market history');
        $this->get('/supervisor/reports/history?from=2026-11-24&to=2026-11-23')->assertSessionHasErrors('to');
        $this->get('/supervisor/reports/history.csv?from=2026-11-23&to=2026-11-23')
            ->assertRedirect('/supervisor/reports/history?from=2026-11-23&to=2026-11-23');
    }

    public function test_history_is_supervisor_only(): void
    {
        $this->get('/supervisor/reports/history')->assertRedirect();
        foreach (['vendor', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/supervisor/reports/history')->assertForbidden();
        }
    }

    public function test_history_paginates_and_preserves_the_date_range(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $fish = FishType::create(['name' => 'Hipon', 'quality_class' => 'Special Class', 'is_active' => true]);
        for ($day = 1; $day <= 26; $day++) {
            VendorInventory::create([
                'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'Special Class',
                'entry_date' => sprintf('2026-10-%02d', $day), 'price_per_kg' => 200, 'stock_kg' => 10, 'status' => 'confirmed',
            ]);
        }
        $response = $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/reports/history?from=2026-10-01&to=2026-10-31&page=2')->assertOk();
        $this->assertSame(26, $response->viewData('rows')->total());
        $this->assertCount(1, $response->viewData('rows'));
        $response->assertSee('from=2026-10-01', false)->assertSee('to=2026-10-31', false);
    }
}
