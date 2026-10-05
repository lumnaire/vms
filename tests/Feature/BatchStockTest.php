<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batches, release and the public board, end to end.
 *
 * The flow the market described: a vendor submits 15 kg of bammer (Batch 1),
 * later submits another 15 kg (Batch 2) after confirming they mean to, staff
 * approve each, and the consumer card lists both batches — each with its own
 * price and approval time — under one total of 30 kg. Release records what sold
 * and takes it off that total. A batch stays on sale across days until it sells
 * out or passes the freshness window.
 */
class BatchStockTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?string $name = null): User
    {
        $user = User::factory()->create(['role' => $role] + ($name ? ['name' => $name] : []));

        if ($role === 'vendor') {
            VendorProfile::create([
                'user_id' => $user->id,
                'stall_number' => 'B-'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'business_name' => $user->name.' Stall',
            ]);
        }

        return $user;
    }

    private function makeFishType(string $name = 'Bammer', string $quality = 'First Class'): FishType
    {
        return FishType::create(['name' => $name, 'quality_class' => $quality, 'is_active' => true]);
    }

    private function batch(
        User $vendor,
        FishType $fish,
        float $kg,
        int $no = 1,
        string $status = 'confirmed',
        float $price = 200.0,
        ?string $date = null,
        float $sold = 0.0,
    ): VendorInventory {
        return VendorInventory::create([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => $fish->quality_class,
            'batch_no' => $no,
            'price_per_kg' => $price,
            'stock_kg' => $kg,
            'released_kg' => $kg,
            'sold_kg' => $sold,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'entry_date' => $date ?? today()->toDateString(),
            'is_locked' => false,
        ]);
    }

    private function submit(User $vendor, FishType $fish, float $kg, array $extra = [])
    {
        return $this->actingAs($vendor)->from('/vendor/inventory')->post('/vendor/inventory', [
            'fish_type_id' => $fish->id,
            'quality_class' => $fish->quality_class,
            'price_per_kg' => 200,
            'stock_kg' => $kg,
        ] + $extra);
    }

    // ─── Submitting batches ──────────────────────────────────────

    public function test_the_stock_submitted_is_the_stock_for_sale(): void
    {
        $vendor = $this->makeUser('vendor');

        $this->submit($vendor, $this->makeFishType(), 15)->assertSessionHasNoErrors();

        $entry = VendorInventory::sole();
        $this->assertSame(15.0, (float) $entry->stock_kg);
        $this->assertSame(15.0, (float) $entry->released_kg);
        $this->assertSame(1, $entry->batch_no);
        $this->assertSame('pending', $entry->status);
    }

    public function test_a_second_batch_of_the_same_fish_must_be_confirmed_and_becomes_batch_two(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->batch($vendor, $fish, 15);

        // Without confirming, the vendor is told what they already have.
        $this->submit($vendor, $fish, 15)
            ->assertSessionHasErrors('confirm_new_batch');
        $this->assertSame(1, VendorInventory::count());

        $this->submit($vendor, $fish, 15, ['confirm_new_batch' => 1])
            ->assertSessionHasNoErrors();

        $second = VendorInventory::latest('id')->first();
        $this->assertSame(2, $second->batch_no);
        $this->assertSame('pending', $second->status, 'Every batch goes to staff.');
    }

    public function test_a_different_fish_needs_no_confirmation(): void
    {
        $vendor = $this->makeUser('vendor');
        $this->batch($vendor, $this->makeFishType('Bammer'), 15);

        $this->submit($vendor, $this->makeFishType('Pusit'), 10)->assertSessionHasNoErrors();

        $this->assertSame(1, VendorInventory::latest('id')->first()->batch_no);
    }

    public function test_the_form_knows_what_the_vendor_already_has_of_each_fish(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->batch($vendor, $fish, 15);

        $response = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk();

        $existing = $response->viewData('existingBatches');
        $key = $fish->id.'_'.$fish->quality_class;
        $this->assertSame(15.0, $existing[$key]['remaining']);
        $this->assertSame(2, $existing[$key]['next_batch']);

        $html = $response->getContent();
        $this->assertStringContainsString('name="confirm_new_batch"', $html);
        $this->assertStringNotContainsString('name="released_kg"', $html);
        $this->assertStringNotContainsString('market_session', $html);
    }

    // ─── Monitoring days ─────────────────────────────────────────

    public function test_earlier_batches_still_on_the_stall_are_monitored_with_their_age(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Pusit');
        $old = $this->batch($vendor, $fish, 10, date: today()->subDay()->toDateString(), sold: 4);
        $this->batch($vendor, $fish, 5, no: 1);

        $response = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk();

        $this->assertTrue($response->viewData('batches')->contains('id', $old->id));
        $limit = VendorInventory::freshnessDays();
        $response->assertSee('1d / '.$limit.'d')->assertSee('0d / '.$limit.'d');
    }

    public function test_a_batch_turns_stale_at_the_freshness_limit(): void
    {
        $vendor = $this->makeUser('vendor');
        $limit = VendorInventory::freshnessDays();

        $fresh = $this->batch($vendor, $this->makeFishType(), 10, date: today()->subDays($limit - 1)->toDateString());
        $stale = $this->batch($vendor, $this->makeFishType('Pusit'), 10, date: today()->subDays($limit)->toDateString());

        $this->assertFalse($fresh->isStale());
        $this->assertSame(1, $fresh->getDaysUntilStale());
        $this->assertTrue($stale->isStale());
    }

    // ─── Release (recording a sale) ──────────────────────────────

    public function test_release_takes_the_sold_kilograms_off_the_batch(): void
    {
        $vendor = $this->makeUser('vendor');
        $entry = $this->batch($vendor, $this->makeFishType(), 15);

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$entry->id}/release", ['release_kg' => 4])
            ->assertRedirect('/vendor/inventory')
            ->assertSessionHasNoErrors();

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$entry->id}/release", ['release_kg' => 1.5]);

        $entry->refresh();
        $this->assertSame(5.5, $entry->getSoldKg());
        $this->assertSame(9.5, $entry->getRemainingStock());
    }

    public function test_release_cannot_exceed_what_is_left(): void
    {
        $vendor = $this->makeUser('vendor');
        $entry = $this->batch($vendor, $this->makeFishType(), 15, sold: 12);

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$entry->id}/release", ['release_kg' => 4, 'release_entry' => $entry->id])
            ->assertSessionHasErrors('release_kg');

        $this->assertSame(3.0, $entry->fresh()->getRemainingStock());
    }

    public function test_a_pending_batch_cannot_be_released(): void
    {
        $vendor = $this->makeUser('vendor');
        $entry = $this->batch($vendor, $this->makeFishType(), 15, status: 'pending');

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$entry->id}/release", ['release_kg' => 1])
            ->assertSessionHasErrors('release_kg');
    }

    public function test_a_vendor_cannot_release_another_vendors_batch(): void
    {
        $entry = $this->batch($this->makeUser('vendor'), $this->makeFishType(), 15);

        $this->actingAs($this->makeUser('vendor'))
            ->post("/vendor/inventory/{$entry->id}/release", ['release_kg' => 1])
            ->assertForbidden();
    }

    // ─── Write-off ───────────────────────────────────────────────

    public function test_only_a_stale_batch_can_be_written_off(): void
    {
        $vendor = $this->makeUser('vendor');
        $fresh = $this->batch($vendor, $this->makeFishType(), 10);
        $stale = $this->batch($vendor, $this->makeFishType('Pusit'), 10,
            date: today()->subDays(VendorInventory::freshnessDays())->toDateString());

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$fresh->id}/write-off")
            ->assertSessionHasErrors('write_off');

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$stale->id}/write-off", ['reason' => 'Spoiled'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($stale->fresh()->disposed_at);
        $this->assertSame(0.0, $stale->fresh()->getRemainingStock());
    }

    // ─── Consumer board ──────────────────────────────────────────

    public function test_board_lists_each_batch_with_its_price_and_adds_them_up(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->batch($vendor, $fish, 15, no: 1, price: 200);
        $this->batch($vendor, $fish, 15, no: 2, price: 150);
        $this->batch($vendor, $fish, 9, no: 3, status: 'pending'); // not approved yet

        $vendors = $this->get('/')->assertOk()->assertSee('Bammer')->viewData('vendors');

        $this->assertCount(1, $vendors, 'One card per vendor.');
        $bammer = $vendors[0]['fish'][0];
        $this->assertCount(2, $bammer['batches']);
        $this->assertSame([200.0, 150.0], array_column($bammer['batches'], 'price_per_kg'));
        $this->assertSame(30.0, $bammer['remaining_kg']);
        $this->assertNotNull($bammer['batches'][0]['approved']);
        $this->assertArrayNotHasKey('am_kg', $bammer);
    }

    public function test_release_comes_off_the_board_total(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $first = $this->batch($vendor, $fish, 15, no: 1);
        $this->batch($vendor, $fish, 15, no: 2);

        $this->actingAs($vendor)->post("/vendor/inventory/{$first->id}/release", ['release_kg' => 10]);

        $this->assertSame(20.0, $this->get('/')->viewData('vendors')[0]['fish'][0]['remaining_kg']);
    }

    public function test_board_carries_fresh_batches_across_days_but_drops_stale_and_sold_out_ones(): void
    {
        $vendor = $this->makeUser('vendor');
        $limit = VendorInventory::freshnessDays();

        $this->batch($vendor, $this->makeFishType('Bammer'), 6, date: today()->subDay()->toDateString());
        $this->batch($vendor, $this->makeFishType('Pusit'), 6, date: today()->subDays($limit)->toDateString());
        $this->batch($vendor, $this->makeFishType('Hipon'), 6, sold: 6);

        $fish = $this->get('/')->viewData('vendors')[0]['fish'];

        $this->assertSame(['Bammer'], array_column($fish, 'fish_name'));
    }

    // ─── Staff ───────────────────────────────────────────────────

    public function test_staff_see_multiple_batches_side_by_side_without_sales(): void
    {
        $vendor = $this->makeUser('vendor', 'Aling Rosa');
        $fish = $this->makeFishType();
        $this->batch($vendor, $fish, 15, no: 1, sold: 7);
        $this->batch($vendor, $fish, 15, no: 2, status: 'pending');

        $response = $this->actingAs($this->makeUser('staff'))->get('/staff/dashboard')->assertOk();

        $groups = $response->viewData('repeatSubmissions');
        $this->assertCount(1, $groups);
        $this->assertSame(15.0, $groups[0]['confirmed_kg']);
        $this->assertSame(15.0, $groups[0]['pending_kg']);
        $this->assertArrayNotHasKey('remaining_kg', $groups[0]);

        $response->assertSee('Multiple Batches Today')->assertSee('Batch 2');

        $this->actingAs($this->makeUser('staff'))->get('/staff/confirmations')->assertOk()
            ->assertSee('Batch 2')
            ->assertSee('30.0 kg');
    }

    public function test_staff_supply_report_previews_each_period_and_downloads_a_pdf(): void
    {
        $vendor = $this->makeUser('vendor');
        $this->batch($vendor, $this->makeFishType(), 15, price: 200);
        $this->batch($vendor, $this->makeFishType('Pusit'), 5, price: 300, status: 'pending'); // not supply yet
        $staff = $this->makeUser('staff');

        $daily = $this->actingAs($staff)->get('/staff/reports?period=daily&date='.today()->toDateString())->assertOk();
        $this->assertSame(15.0, $daily->viewData('totals')['supply_kg']);
        $this->assertSame(1, $daily->viewData('totals')['batches']);

        $this->actingAs($staff)->get('/staff/reports?period=monthly&month='.today()->format('Y-m'))
            ->assertOk()->assertSee('Monthly Supply Report');
        $this->actingAs($staff)->get('/staff/reports?period=yearly&year='.today()->year)
            ->assertOk()->assertSee('Yearly Supply Report');

        $pdf = $this->actingAs($staff)->get('/staff/reports/pdf?period=monthly&month='.today()->format('Y-m'))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertDatabaseHas('reports', ['report_type' => 'supply_summary']);
    }

    // ─── Removed features ────────────────────────────────────────

    public function test_sale_reports_and_my_stock_are_gone(): void
    {
        $vendor = $this->makeUser('vendor');
        $this->actingAs($vendor)->get('/vendor/sale-report')->assertNotFound();
        $this->actingAs($vendor)->get('/vendor/my-stock')->assertNotFound();
        $this->actingAs($this->makeUser('staff'))->get('/staff/sale-reports')->assertNotFound();
        $this->actingAs($this->makeUser('supervisor'))->get('/supervisor/sale-reports')->assertNotFound();
    }
}
