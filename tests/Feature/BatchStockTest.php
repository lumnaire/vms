<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BatchRelease;
use App\Models\FishType;
use App\Models\Report;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batches, release and the public board, end to end.
 *
 * The flow the market described: a vendor submits 15 kg of bammer (Batch 1),
 * later submits another 15 kg (Batch 2) with only a reminder of what they
 * already have, staff approve each, and the consumer card stacks both into one
 * total of 30 kg. Release records what sold and takes it off that total. A batch
 * stays on sale across days until it sells out or reaches the freshness limit,
 * when it is deleted from the database.
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

    public function test_a_second_batch_of_the_same_fish_needs_no_confirmation_and_becomes_batch_two(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->batch($vendor, $fish, 15);

        $this->submit($vendor, $fish, 15)->assertSessionHasNoErrors();

        $second = VendorInventory::latest('id')->first();
        $this->assertSame(2, $second->batch_no);
        $this->assertSame('pending', $second->status, 'Every batch goes to staff.');
    }

    public function test_a_different_fish_starts_at_batch_one(): void
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

        // A reminder only: nothing to tick before submitting.
        $html = $response->getContent();
        $this->assertStringContainsString('id="existingBatches"', $html);
        $this->assertStringNotContainsString('confirm_new_batch', $html);
        $this->assertStringNotContainsString('name="released_kg"', $html);
        $this->assertStringNotContainsString('market_session', $html);
    }

    /**
     * Yesterday's Batch 1 is still on sale, so today's submission of the same fish
     * is Batch 2, not a second Batch 1 sitting next to it on the board.
     */
    public function test_numbering_continues_past_batches_carried_over_from_yesterday(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $old = $this->batch($vendor, $fish, 10, price: 180, date: today()->subDay()->toDateString(), sold: 4);
        $old->update(['confirmed_at' => now()->subDay()]);

        $this->submit($vendor, $fish, 15)->assertSessionHasNoErrors();

        $new = VendorInventory::latest('id')->first();
        $this->assertSame(2, $new->batch_no);

        $new->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $bammer = $this->get('/')->viewData('vendors')[0]['fish'][0];

        $this->assertSame(21.0, $bammer['remaining_kg'], '6 kg left of yesterday plus 15 kg today.');
        $this->assertSame([180.0, 200.0], [$bammer['min_price'], $bammer['max_price']]);
    }

    public function test_numbering_restarts_once_earlier_batches_are_gone(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->batch($vendor, $fish, 10, date: today()->subDay()->toDateString(), sold: 10);

        $this->submit($vendor, $fish, 15)->assertSessionHasNoErrors();

        $this->assertSame(1, VendorInventory::latest('id')->first()->batch_no);
    }

    // ─── Countdown and automatic deletion ────────────────────────

    public function test_batches_on_the_stall_count_down_the_days_left(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Pusit');
        $old = $this->batch($vendor, $fish, 10, date: today()->subDay()->toDateString(), sold: 4);
        $this->batch($vendor, $fish, 5, no: 2);

        $response = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk();

        $this->assertTrue($response->viewData('batches')->contains('id', $old->id));
        $limit = VendorInventory::freshnessDays();
        $response->assertSee(($limit - 1).' days left')->assertSee($limit.' days left');
    }

    /**
     * At the limit an unsold batch leaves the vendor's tables and the board, but
     * the row stays: supply reports and forecasts read its price and kilograms.
     */
    public function test_batches_reaching_the_limit_unsold_expire_but_stay_in_the_history(): void
    {
        $vendor = $this->makeUser('vendor');
        $limit = VendorInventory::freshnessDays();
        $expiredOn = today()->subDays($limit)->toDateString();

        $lastDay = $this->batch($vendor, $this->makeFishType('Bammer'), 10, date: today()->subDays($limit - 1)->toDateString());
        $unsold = $this->batch($vendor, $this->makeFishType('Pusit'), 10, date: $expiredOn, sold: 3);
        $pending = $this->batch($vendor, $this->makeFishType('Hipon'), 10, date: $expiredOn, status: 'pending');
        $soldOut = $this->batch($vendor, $this->makeFishType('Tulingan'), 10, date: $expiredOn, sold: 10);

        $this->assertSame(1, $lastDay->getDaysLeft());

        // The first page of the day expires them, before anything is shown.
        $response = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk();

        $this->assertTrue($unsold->fresh()->isExpired());
        $this->assertSame(0.0, $unsold->fresh()->getRemainingStock());
        $this->assertNull($pending->fresh(), 'Never confirmed, so nothing to keep.');
        $this->assertFalse($lastDay->fresh()->isExpired());
        $this->assertFalse($soldOut->fresh()->isExpired());

        // Gone from both of the vendor's tables and from the board.
        $this->assertSame([$lastDay->id], $response->viewData('batches')->pluck('id')->all());
        $this->assertSame([$soldOut->id], $response->viewData('history')->pluck('id')->all());
        $this->assertSame(['Bammer'], array_column($this->get('/')->viewData('vendors')[0]['fish'], 'fish_name'));

        // Still supply on the day it came in.
        $report = $this->actingAs($this->makeUser('staff'))->get('/staff/reports?period=daily&date='.$expiredOn);
        $this->assertSame(20.0, $report->viewData('totals')['supply_kg']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expire_inventory']);
    }

    public function test_the_expire_command_takes_old_batches_off_the_stall(): void
    {
        $vendor = $this->makeUser('vendor');
        $old = $this->batch($vendor, $this->makeFishType(), 10,
            date: today()->subDays(VendorInventory::freshnessDays())->toDateString());

        $this->artisan('inventory:expire')->assertSuccessful();

        $this->assertTrue($old->fresh()->isExpired());
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

    /**
     * The client's example: Batch 1 has 4 kg left, Batch 2 has 2 kg, Batch 3 has
     * 6 kg. The vendor releases every leftover without selling it; it comes off
     * remaining stock and the board, and each release is on record.
     */
    public function test_leftover_stock_can_be_pulled_out_without_a_sale(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $b1 = $this->batch($vendor, $fish, 10, no: 1, sold: 6);
        $b2 = $this->batch($vendor, $fish, 10, no: 2, sold: 8);
        $b3 = $this->batch($vendor, $fish, 10, no: 3, sold: 4);
        $this->assertSame(12.0, $this->get('/')->viewData('vendors')[0]['fish'][0]['remaining_kg']);

        foreach ([[$b1, 4], [$b2, 2], [$b3, 6]] as [$batch, $kg]) {
            $this->actingAs($vendor)->from('/vendor/inventory')
                ->post("/vendor/inventory/{$batch->id}/release", [
                    'release_kg' => $kg,
                    'release_kind' => 'pulled_out',
                    'release_reason' => 'Spoiled',
                ])
                ->assertSessionHasNoErrors();
        }

        foreach ([[$b1, 4.0, 6.0], [$b2, 2.0, 8.0], [$b3, 6.0, 4.0]] as [$batch, $pulled, $sold]) {
            $batch->refresh();
            $this->assertSame(0.0, $batch->getRemainingStock());
            $this->assertSame($pulled, $batch->getPulledOutKg());
            $this->assertSame($sold, $batch->getSoldKg(), 'A pull-out is not a sale.');
            $this->assertSame(VendorInventory::STATE_RELEASED, $batch->getStockState());
        }

        $this->assertSame([], $this->get('/')->viewData('vendors')->all(), 'Gone from the board.');
        $this->assertSame(3, BatchRelease::where('kind', 'pulled_out')->count());
        $this->assertSame(3, ActivityLog::where('action', 'pull_out_stock')->count());
        $this->assertStringContainsString('pulled out (not sold) (Spoiled)', ActivityLog::where('action', 'pull_out_stock')->first()->description);
    }

    public function test_a_sale_is_kept_as_a_release_record_too(): void
    {
        $vendor = $this->makeUser('vendor');
        $entry = $this->batch($vendor, $this->makeFishType(), 15);

        $this->actingAs($vendor)->post("/vendor/inventory/{$entry->id}/release", ['release_kg' => 5, 'release_kind' => 'sold']);

        $this->assertSame(5.0, $entry->fresh()->getSoldKg());
        $this->assertSame(0.0, $entry->fresh()->getPulledOutKg());
        $this->assertDatabaseHas('batch_releases', ['vendor_inventory_id' => $entry->id, 'kind' => 'sold']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'release_stock']);
    }

    public function test_a_pull_out_cannot_exceed_what_is_left_or_use_an_unknown_kind(): void
    {
        $vendor = $this->makeUser('vendor');
        $entry = $this->batch($vendor, $this->makeFishType(), 10, sold: 7);

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$entry->id}/release", ['release_kg' => 4, 'release_kind' => 'pulled_out'])
            ->assertSessionHasErrors('release_kg');
        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$entry->id}/release", ['release_kg' => 1, 'release_kind' => 'gift'])
            ->assertSessionHasErrors('release_kind');

        $this->assertSame(3.0, $entry->fresh()->getRemainingStock());
        $this->assertSame(0, BatchRelease::count());
    }

    public function test_the_release_modal_offers_sold_or_pulled_out(): void
    {
        $vendor = $this->makeUser('vendor');
        $this->batch($vendor, $this->makeFishType(), 10);

        $this->actingAs($vendor)->get('/vendor/inventory')->assertOk()
            ->assertSee('name="release_kind" value="sold"', false)
            ->assertSee('name="release_kind" value="pulled_out"', false)
            ->assertSee('Release all remaining')
            ->assertSee('name="release_reason"', false);
    }

    // ─── Cancel ──────────────────────────────────────────────────

    public function test_a_pending_batch_can_be_cancelled_but_a_confirmed_one_cannot(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $confirmed = $this->batch($vendor, $fish, 10);
        $pending = $this->batch($vendor, $fish, 5, no: 2, status: 'pending');

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->delete("/vendor/inventory/{$pending->id}")
            ->assertRedirect('/vendor/inventory')
            ->assertSessionHas('success');
        $this->assertNull($pending->fresh());

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->delete("/vendor/inventory/{$confirmed->id}")
            ->assertSessionHasErrors('cancel');
        $this->assertNotNull($confirmed->fresh());
    }

    /**
     * The ⋮ menu hides the actions a batch does not offer with .hidden. The page's
     * own unlayered .menu-item rule sets display, which beats Tailwind's layered
     * utility, so the menu needs its own .hidden rule — without it "Cancel batch"
     * showed on confirmed batches and posted DELETE to the inventory index.
     */
    public function test_the_batch_menu_only_offers_cancel_on_pending_batches(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->batch($vendor, $fish, 10);
        $this->batch($vendor, $fish, 5, no: 2, status: 'pending');

        $html = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/#batchMenu \.hidden\s*\{\s*display:\s*none;?\s*\}/', $html);
        preg_match_all('/data-cancel-url="([^"]*)"/', $html, $m);
        $this->assertCount(1, array_filter($m[1]), 'Only the pending batch carries a cancel URL.');
    }

    // ─── Consumer board ──────────────────────────────────────────

    public function test_board_stacks_every_batch_of_a_fish_into_one_total(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->batch($vendor, $fish, 15, no: 1, price: 200);
        $this->batch($vendor, $fish, 15, no: 2, price: 150);
        $this->batch($vendor, $fish, 9, no: 3, status: 'pending'); // not approved yet

        $response = $this->get('/')->assertOk()->assertSee('Bammer');
        $vendors = $response->viewData('vendors');

        $this->assertCount(1, $vendors, 'One card per vendor.');
        $bammer = $vendors[0]['fish'][0];
        $this->assertSame(30.0, $bammer['remaining_kg']);
        $this->assertSame([150.0, 200.0], [$bammer['min_price'], $bammer['max_price']]);
        $this->assertArrayNotHasKey('batches', $bammer, 'No per-batch rows on the consumer card.');
        $this->assertArrayNotHasKey('fish_image', $bammer);
        $this->assertArrayNotHasKey('am_kg', $bammer);

        // The card is headed by the stall number, not a vendor icon.
        $response->assertSee('Stall No.')->assertDontSee('pb-vavatar')->assertDontSee('pb-thumb');
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

    public function test_board_carries_fresh_batches_across_days_but_drops_expired_and_sold_out_ones(): void
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
        // Filters preview on change; there is no Preview button.
        $daily->assertDontSee('bi-eye')->assertSee('Download PDF');

        $this->actingAs($staff)->get('/staff/reports?period=monthly&month='.today()->format('Y-m'))
            ->assertOk()->assertSee('Monthly Supply Report');
        $this->actingAs($staff)->get('/staff/reports?period=yearly&year='.today()->year)
            ->assertOk()->assertSee('Yearly Supply Report');

        $pdf = $this->actingAs($staff)->get('/staff/reports/pdf?period=monthly&month='.today()->format('Y-m'))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertDatabaseHas('reports', ['report_type' => 'supply_summary']);
    }

    /** Pull-outs are on the staff report; sales stay the vendor's own record. */
    public function test_supply_report_lists_each_pull_out_but_not_sales(): void
    {
        $vendor = $this->makeUser('vendor', 'Aling Rosa');
        $fish = $this->makeFishType();
        $b1 = $this->batch($vendor, $fish, 10, no: 1);
        $b2 = $this->batch($vendor, $fish, 10, no: 2);
        $b1->release(4, BatchRelease::PULLED_OUT, 'Spoiled');
        $b2->release(2, BatchRelease::PULLED_OUT);
        $b2->release(5, BatchRelease::SOLD);
        $staff = $this->makeUser('staff');

        $daily = $this->actingAs($staff)->get('/staff/reports?period=daily&date='.today()->toDateString())->assertOk();

        $this->assertSame(6.0, $daily->viewData('totals')['pulled_out_kg']);
        $this->assertSame(2, $daily->viewData('totals')['pull_outs']);
        $this->assertSame([4.0, 2.0], $daily->viewData('pullOuts')->pluck('kg')->all());
        $this->assertSame(['Batch 1', 'Batch 2'], $daily->viewData('pullOuts')->pluck('batch')->all());
        $daily->assertSee('Released Stock (Pulled Out, Not Sold)')->assertSee('Spoiled');

        $this->actingAs($staff)->get('/staff/reports?period=daily&date='.today()->subDay()->toDateString())
            ->assertOk()->assertSee('No pull-outs');

        $pdf = $this->actingAs($staff)->get('/staff/reports/pdf?period=daily&date='.today()->toDateString())->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertEquals(6.0, Report::latest('id')->first()->report_data['totals']['pulled_out_kg']);
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
