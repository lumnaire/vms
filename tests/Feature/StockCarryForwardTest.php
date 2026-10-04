<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the stock lifecycle: what a vendor still has, how old it is, and
 * the two ways out of a line that is not going to sell itself.
 *
 * The gap these close is concrete. The sale report only reports on the entries
 * staff confirmed for the current day, so fish left over from an earlier day could
 * be seen but never declared, never sold, and never cleared — it sat in every
 * remaining figure forever. Carrying it forward gives it a fresh entry; the
 * freshness window is what stops that becoming a way to sell old fish as new.
 *
 * The adversarial cases here follow the ledger argument: a carry must not let the
 * same kilograms be counted twice, must not resurrect stock past the window, must
 * not move another vendor's fish, and must hand the stock back when the replacement
 * entry never happens.
 */
class StockCarryForwardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        if ($role === 'vendor') {
            VendorProfile::create([
                'user_id' => $user->id,
                'stall_number' => 'B-'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'business_name' => $user->name.' Stall',
            ]);
        }

        return $user;
    }

    private function makeFishType(string $name = 'Bangus', string $quality = 'First Class'): FishType
    {
        return FishType::create([
            'name' => $name,
            'quality_class' => $quality,
            'is_active' => true,
        ]);
    }

    private function makeEntry(
        User $vendor,
        FishType $fish,
        string $date,
        float $released = 20.0,
        float $sold = 0.0,
        string $status = 'confirmed',
        float $price = 250.0
    ): VendorInventory {
        return VendorInventory::create([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => $fish->quality_class,
            'price_per_kg' => $price,
            'stock_kg' => $released * 1.2,
            'released_kg' => $released,
            'sold_kg' => $sold,
            'status' => $status,
            'entry_date' => $date,
            'is_locked' => $date !== today()->toDateString(),
        ]);
    }

    private function yesterday(int $daysAgo = 1): string
    {
        return today()->subDays($daysAgo)->toDateString();
    }

    private function window(): int
    {
        return (int) config('inventory.stale_after_days');
    }

    /** Just the Stock on Your Stall table, isolated from the panels around it. */
    /**
     * Just the rows of the open-stock table.
     *
     * Slicing the page by heading breaks as soon as the instructions below the
     * table name a control ("Submit again today" is spelled out there too), which
     * would make "this row has no such button" untestable. The table body is the
     * only place a button can actually be.
     */
    private function openStockTable(string $html): string
    {
        if (! preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $matches)) {
            return '';
        }

        return $matches[1];
    }

    // ─── Seeing what is left ─────────────────────────────────────

    public function test_leftover_stock_shows_on_my_stock_with_its_age(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $entry = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        $table = $this->openStockTable(
            $this->actingAs($vendor)->get('/vendor/my-stock')->assertOk()->getContent()
        );

        $this->assertStringContainsString('Bangus', $table);
        $this->assertStringContainsString('6.00', $table);              // remaining
        $this->assertStringContainsString('4.00', $table);              // sold
        $this->assertStringContainsString('10.00', $table);             // released
        $this->assertStringContainsString($entry->getAgeInDays().'d', $table);
        $this->assertStringContainsString('Submit again today', $table);
    }

    public function test_my_stock_summarises_confirmed_total_and_remaining_stock(): void
    {
        $vendor = $this->makeUser('vendor');
        $fishA = $this->makeFishType('Bangus');
        $fishB = $this->makeFishType('Tilapia');

        $this->makeEntry($vendor, $fishA, $this->yesterday(), released: 10.0, sold: 4.0);
        $this->makeEntry($vendor, $fishB, $this->yesterday(2), released: 6.0, sold: 6.0);

        $html = $this->actingAs($vendor)->get('/vendor/my-stock')->assertOk()->getContent();

        // Total stock is everything ever brought: 10 + 6.
        $this->assertStringContainsString('16.0', $html);
        // Confirmed and still live: the sold-out line holds nothing.
        $this->assertStringContainsString('10.0', $html);
        // Remaining: only what is left unsold.
        $this->assertStringContainsString('6.0', $html);
        // Sold out is reported in kilograms, not as a share of the total.
        $this->assertStringContainsString('6.0', $html);
        $this->assertStringContainsString('bought completely', $html);
    }

    public function test_my_stock_is_in_the_vendor_menu_only(): void
    {
        $vendor = $this->makeUser('vendor');

        // Guest first: actingAs() leaves the user on the guard for the rest of the
        // test, so checking the redirect afterwards would pass as a signed-in visit.
        $this->get('/vendor/my-stock')->assertRedirect('/login');

        $this->actingAs($vendor)->get('/vendor/dashboard')->assertOk()->assertSee('My Stock');

        $this->actingAs($this->makeUser('staff'))->get('/vendor/my-stock')->assertForbidden();
        $this->actingAs($this->makeUser('supervisor'))->get('/vendor/my-stock')->assertForbidden();
    }

    // ─── Submitting the leftover again today ─────────────────────

    public function test_submitting_again_today_creates_a_pending_entry_for_staff(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');
        $entry = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0, price: 250.0);

        $this->actingAs($vendor)
            ->post("/vendor/my-stock/{$entry->id}/carry")
            ->assertRedirect('/vendor/my-stock')
            ->assertSessionHas('success');

        $carried = VendorInventory::where('carried_from_id', $entry->id)->sole();

        // A fresh claim on a fresh day: dated today, unsold, and not sellable until
        // staff agree a price for it, exactly like any other new entry.
        $this->assertSame('pending', $carried->status);
        $this->assertSame(today()->toDateString(), $carried->entry_date->toDateString());
        $this->assertSame(0.0, (float) $carried->sold_kg);
        $this->assertSame(6.0, (float) $carried->released_kg);
        $this->assertSame(250.0, (float) $carried->price_per_kg);
        $this->assertSame($fish->id, $carried->fish_type_id);
        $this->assertSame('First Class', $carried->quality_class);
    }

    public function test_carried_stock_is_counted_once_and_the_old_line_stops_holding_it(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');
        $entry = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        $this->actingAs($vendor)->post("/vendor/my-stock/{$entry->id}/carry");

        // The kilograms moved, so the old line no longer counts them...
        $this->assertTrue($entry->fresh()->isCarriedOut());
        $this->assertSame(0.0, (float) $entry->fresh()->getRemainingStock());

        // ...and until staff confirm the replacement, nothing in the confirmed book
        // holds them. The vendor is told where they went rather than left guessing.
        $this->actingAs($vendor)->get('/vendor/my-stock')
            ->assertOk()
            ->assertSee('waiting on staff');

        $carried = VendorInventory::where('carried_from_id', $entry->id)->sole();
        $carried->update(['status' => 'confirmed']);

        // Once confirmed, the book holds 6 kg — not 12.
        $this->assertSame(6.0, (float) $carried->getRemainingStock());
        $this->assertSame(0.0, (float) $entry->fresh()->getRemainingStock());
        $this->assertSame(6.0, (float) $carried->getRemainingStock() + $entry->fresh()->getRemainingStock());

        // Two rows now hold 16 kg between them, but the page reports 10: the total
        // is counted once, on the line that first logged the fish, so resubmitting
        // is not the same as bringing more stock to the market.
        $this->assertSame(16.0, (float) VendorInventory::sum('released_kg'));

        $html = $this->actingAs($vendor)->get('/vendor/my-stock')->assertOk()->getContent();

        $this->assertStringContainsString('10.0', $html);
        $this->assertStringNotContainsString('16.0', $html);
    }

    public function test_carrying_stock_forward_restarts_the_freshness_countdown(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        // One day short of the window: still sellable, but close to it.
        $entry = $this->makeEntry($vendor, $fish, $this->yesterday($this->window() - 1), released: 10.0, sold: 2.0);

        $this->assertSame($this->window() - 1, $entry->getAgeInDays());
        $this->assertFalse($entry->isStale());
        $this->assertSame(1, $entry->getDaysUntilStale());

        $this->actingAs($vendor)->post("/vendor/my-stock/{$entry->id}/carry");

        $carried = VendorInventory::where('carried_from_id', $entry->id)->sole();

        // Dated today, so the countdown starts again — the vendor gets the full
        // freshness window for fish they have put back on the stall.
        $this->assertSame(0, $carried->getAgeInDays());
        $this->assertFalse($carried->isStale());
        $this->assertSame($this->window(), $carried->getDaysUntilStale());
    }

    public function test_stock_past_the_freshness_window_cannot_be_resubmitted(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $stale = $this->makeEntry($vendor, $fish, $this->yesterday($this->window()), released: 10.0, sold: 1.0);

        $this->assertTrue($stale->isStale());

        // The page says so instead of offering a button that would be refused...
        $table = $this->openStockTable(
            $this->actingAs($vendor)->get('/vendor/my-stock')->assertOk()->getContent()
        );

        $this->assertStringContainsString('too old', $table);
        $this->assertStringNotContainsString('Submit again today', $table);
        $this->assertStringContainsString('Report written off', $table);

        // ...and a hand-built request is refused, creating nothing.
        $this->actingAs($vendor)
            ->from('/vendor/my-stock')
            ->post("/vendor/my-stock/{$stale->id}/carry")
            ->assertRedirect('/vendor/my-stock')
            ->assertSessionHasErrors('stock');

        $this->assertSame(0, VendorInventory::where('carried_from_id', $stale->id)->count());
        $this->assertSame(9.0, (float) $stale->fresh()->getRemainingStock());
    }

    public function test_stock_that_sold_completely_has_nothing_to_resubmit(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $soldOut = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 10.0);

        $this->actingAs($vendor)
            ->from('/vendor/my-stock')
            ->post("/vendor/my-stock/{$soldOut->id}/carry")
            ->assertSessionHasErrors('stock');

        $this->assertSame(0, VendorInventory::where('carried_from_id', $soldOut->id)->count());
        $this->assertStringContainsString('bought completely', $this->actingAs($vendor)->get('/vendor/my-stock')->getContent());
    }

    // ─── Declaring the carried stock on its new day ──────────────

    public function test_a_carried_entry_can_be_declared_and_then_reads_as_bought_completely(): void
    {
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');
        $fish = $this->makeFishType('Bangus');

        // Yesterday: 5 kg released, 2 kg sold, 3 kg left over.
        $entry = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 5.0, sold: 2.0);

        $this->actingAs($vendor)->post("/vendor/my-stock/{$entry->id}/carry");

        $carried = VendorInventory::where('carried_from_id', $entry->id)->sole();

        // Pending until staff agree it, then it is today's stock to sell.
        $this->actingAs($vendor)->get('/vendor/sale-report')
            ->assertOk()
            ->assertDontSee('name="items['.$carried->id.'][total_kg]"', false);

        $this->actingAs($staff)->patch("/staff/confirmations/{$carried->id}/approve");

        $this->assertSame('confirmed', $carried->fresh()->status);

        // All 3 kg sold: the line's total stock goes to 0 with nothing left.
        $this->actingAs($vendor)->post('/vendor/sale-report', [
            'items' => [$carried->id => ['total_kg' => 3.0]],
        ])->assertRedirect('/vendor/sale-report')->assertSessionHas('success');

        $this->assertSame(3.0, (float) $carried->fresh()->sold_kg);
        $this->assertSame(0.0, (float) $carried->fresh()->getRemainingStock());
        $this->assertTrue($carried->fresh()->isSoldThrough());
        $this->assertSame(VendorInventory::STATE_SOLD_OUT, $carried->fresh()->getStockState());

        // And the vendor's remaining stock on My Stock is empty again.
        $this->actingAs($vendor)->get('/vendor/my-stock')->assertOk()->assertSee('Nothing left on your stall');
    }

    // ─── The same kilograms cannot be resubmitted twice ───────────
    //
    // A resubmission writes a new entry instead of re-dating the old one, so
    // resubmitting a stock that is already dated today would write a second
    // entry for the same kilograms on the same day. Staff confirm it as fresh
    // stock and the fish is counted twice.

    public function test_stock_resubmitted_today_cannot_be_submitted_again_once_staff_confirm_it(): void
    {
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');
        $fish = $this->makeFishType('Bangus');

        // Yesterday: 10 kg released, 4 kg sold, 6 kg left over.
        $original = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        // The vendor resubmits the leftover.
        $this->actingAs($vendor)
            ->post("/vendor/my-stock/{$original->id}/carry")
            ->assertSessionHas('success');

        $carried = VendorInventory::where('carried_from_id', $original->id)->sole();

        // Staff confirm it.
        $this->actingAs($staff)->patch("/staff/confirmations/{$carried->id}/approve");

        $this->assertSame('confirmed', $carried->fresh()->status);

        // It is now today's own stock: dated today, holding the 6 kg, sellable
        // against today's sale report. There is nothing left to resubmit.
        $this->assertTrue($carried->fresh()->entry_date->isSameDay(today()));
        $this->assertSame(6.0, (float) $carried->fresh()->getRemainingStock());
        $this->assertFalse($carried->fresh()->canResubmit());

        // The row says why instead of offering a button that would be refused.
        $table = $this->openStockTable(
            $this->actingAs($vendor)->get('/vendor/my-stock')->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('Submit again today', $table);
        $this->assertStringContainsString('Already on', $table);

        // And a hand-built repeat submission is refused, writing nothing.
        $this->actingAs($vendor)
            ->from('/vendor/my-stock')
            ->post("/vendor/my-stock/{$carried->id}/carry")
            ->assertSessionHasErrors('stock');

        // Still exactly the two entries the resubmission was supposed to make.
        $this->assertSame(2, VendorInventory::count());
        $this->assertSame(0, VendorInventory::where('carried_from_id', $carried->id)->count());
        $this->assertFalse($carried->fresh()->isCarriedOut());

        // The 6 kg is still counted once, on the entry that holds it.
        $this->assertSame(6.0, (float) $carried->fresh()->getRemainingStock());
        $this->assertSame(0.0, (float) $original->fresh()->getRemainingStock());
    }

    public function test_a_confirmed_resubmission_is_still_declared_on_todays_sale_report(): void
    {
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');
        $fish = $this->makeFishType('Bangus');

        $original = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        $this->actingAs($vendor)->post("/vendor/my-stock/{$original->id}/carry");

        $carried = VendorInventory::where('carried_from_id', $original->id)->sole();

        $this->actingAs($staff)->patch("/staff/confirmations/{$carried->id}/approve");

        // Not resubmitting it does not lock it out of being sold: the whole point
        // of the confirmation is that today's report can declare it.
        $this->actingAs($vendor)
            ->post('/vendor/sale-report', ['items' => [$carried->id => ['total_kg' => 6.0]]])
            ->assertRedirect('/vendor/sale-report')
            ->assertSessionHas('success');

        $this->assertSame(6.0, (float) $carried->fresh()->sold_kg);
        $this->assertTrue($carried->fresh()->isSoldThrough());
    }

    public function test_the_sale_report_cannot_restock_stock_onto_a_day_it_already_sits_on(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $entry = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 5.0, sold: 0.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', ['items' => [$entry->id => ['total_kg' => 2.0]]]);

        // The picker starts at tomorrow, but a hand-built payload naming today
        // would otherwise be the same duplicate under a different route.
        $this->actingAs($vendor)
            ->from('/vendor/sale-report')
            ->post('/vendor/sale-report/restock', [
                'carry_date' => today()->toDateString(),
                'entries' => [$entry->id],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1, VendorInventory::count());
        $this->assertSame(0, VendorInventory::whereNotNull('carried_from_id')->count());
        $this->assertFalse($entry->fresh()->isCarriedOut());
        $this->assertSame(3.0, (float) $entry->fresh()->getRemainingStock());
    }

    // ─── When the replacement never happens ──────────────────────

    public function test_a_refused_resubmission_returns_the_stock_to_the_original_entry(): void
    {
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');
        $fish = $this->makeFishType('Bangus');

        $entry = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        $this->actingAs($vendor)->post("/vendor/my-stock/{$entry->id}/carry");
        $carried = VendorInventory::where('carried_from_id', $entry->id)->sole();

        $this->actingAs($staff)->patch("/staff/confirmations/{$carried->id}/reject");

        // The handover is undone, or those 6 kg would be on the vendor's books and
        // on nobody's stall.
        $this->assertFalse($entry->fresh()->isCarriedOut());
        $this->assertSame(6.0, (float) $entry->fresh()->getRemainingStock());
        $this->assertTrue($entry->fresh()->canResubmit());

        $this->assertStringContainsString('Submit again today', $this->actingAs($vendor)->get('/vendor/my-stock')->getContent());
    }

    public function test_cancelling_a_pending_resubmission_returns_the_stock_to_the_original_entry(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $entry = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        $this->actingAs($vendor)->post("/vendor/my-stock/{$entry->id}/carry");
        $carried = VendorInventory::where('carried_from_id', $entry->id)->sole();

        $this->actingAs($vendor)
            ->delete("/vendor/inventory/{$carried->id}")
            ->assertRedirect('/vendor/my-stock');

        $this->assertFalse($entry->fresh()->isCarriedOut());
        $this->assertSame(6.0, (float) $entry->fresh()->getRemainingStock());
    }

    // ─── Writing off what is too old to sell ─────────────────────

    public function test_stale_stock_can_be_reported_as_written_off_and_leaves_the_book(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $stale = $this->makeEntry($vendor, $fish, $this->yesterday($this->window() + 1), released: 10.0, sold: 2.0);

        $this->actingAs($vendor)
            ->post("/vendor/my-stock/{$stale->id}/dispose", ['reason' => 'Spoiled overnight'])
            ->assertRedirect('/vendor/my-stock')
            ->assertSessionHas('success');

        $this->assertNotNull($stale->fresh()->disposed_at);
        $this->assertSame('Spoiled overnight', $stale->fresh()->disposed_reason);
        $this->assertSame(0.0, (float) $stale->fresh()->getRemainingStock());
        $this->assertFalse($stale->fresh()->isStale());
        $this->assertSame(VendorInventory::STATE_DISPOSED, $stale->fresh()->getStockState());

        // It comes off the totals and is listed as written off, with the reason.
        $html = $this->actingAs($vendor)->get('/vendor/my-stock')->assertOk()->getContent();
        $this->assertStringContainsString('Reported as written off', $html);
        $this->assertStringContainsString('Spoiled overnight', $html);
        $this->assertStringContainsString('Nothing left on your stall', $html);
    }

    public function test_fresh_stock_cannot_be_written_off(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $fresh = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        // Otherwise the write-off would be a way to erase live stock from the totals.
        $this->actingAs($vendor)
            ->from('/vendor/my-stock')
            ->post("/vendor/my-stock/{$fresh->id}/dispose")
            ->assertSessionHasErrors('stock');

        $this->assertNull($fresh->fresh()->disposed_at);
        $this->assertSame(6.0, (float) $fresh->fresh()->getRemainingStock());
    }

    // ─── Ownership and collision ─────────────────────────────────

    public function test_a_vendor_cannot_carry_or_write_off_another_vendors_stock(): void
    {
        $vendor = $this->makeUser('vendor');
        $other = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $theirs = $this->makeEntry($other, $fish, $this->yesterday(), released: 10.0, sold: 1.0);
        $stale = $this->makeEntry($other, $this->makeFishType('Tilapia'), $this->yesterday($this->window() + 2), released: 5.0);

        $this->actingAs($vendor)->post("/vendor/my-stock/{$theirs->id}/carry")->assertForbidden();
        $this->actingAs($vendor)->post("/vendor/my-stock/{$stale->id}/dispose")->assertForbidden();

        $this->assertFalse($theirs->fresh()->isCarriedOut());
        $this->assertNull($stale->fresh()->disposed_at);
    }

    public function test_stock_can_be_carried_onto_a_day_that_already_has_that_fish(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $leftover = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        // The vendor already has fresh Bangus logged for today, which is where a
        // resubmission from My Stock lands. Several lines of the same fish on one
        // day are allowed, so the carried line sits beside the fresh one.
        $fresh = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 8.0);

        $this->actingAs($vendor)
            ->from('/vendor/my-stock')
            ->post("/vendor/my-stock/{$leftover->id}/carry")
            ->assertSessionHasNoErrors();

        $carried = VendorInventory::where('carried_from_id', $leftover->id)->sole();
        $this->assertSame(6.0, (float) $carried->released_kg);
        $this->assertSame(8.0, (float) $fresh->fresh()->released_kg);

        // ...but the same kilograms still cannot be carried twice.
        $this->assertSame(0.0, (float) $leftover->fresh()->getRemainingStock());
        $this->actingAs($vendor)
            ->from('/vendor/my-stock')
            ->post("/vendor/my-stock/{$leftover->id}/carry")
            ->assertSessionHasErrors('stock');
        $this->assertSame(1, VendorInventory::where('carried_from_id', $leftover->id)->count());
    }

    // ─── Restocking a later trading day from the sale report ────

    public function test_the_sale_report_carries_the_leftover_onto_a_later_day(): void
    {
        $vendor = $this->makeUser('vendor');
        $fishA = $this->makeFishType('Bangus');
        $fishB = $this->makeFishType('Tilapia');

        $a = $this->makeEntry($vendor, $fishA, today()->toDateString(), released: 5.0, sold: 0.0, price: 250.0);
        $b = $this->makeEntry($vendor, $fishB, today()->toDateString(), released: 4.0, sold: 0.0, price: 120.0);

        // Restocking before the day is filed would be guessing at the leftover.
        $this->actingAs($vendor)
            ->post('/vendor/sale-report/restock', [
                'carry_date' => today()->addDay()->toDateString(),
                'entries' => [$a->id],
            ])
            ->assertSessionHasErrors('carry_date');

        $this->actingAs($vendor)->post('/vendor/sale-report', [
            'items' => [$a->id => ['total_kg' => 3.0], $b->id => ['total_kg' => 4.0]],
        ])->assertSessionHas('success');

        // Bangus has 2 kg left to carry, Tilapia sold through.
        $tomorrow = today()->addDay()->toDateString();

        // A sold-through line has nothing to restock. It is refused rather than
        // dropped on the floor, so the vendor is never left believing stock is
        // loaded for tomorrow when it is not.
        $this->actingAs($vendor)
            ->from('/vendor/sale-report')
            ->post('/vendor/sale-report/restock', [
                'carry_date' => $tomorrow,
                'entries' => [$a->id, $b->id],
            ])
            ->assertSessionHasErrors('entries.'.$b->id);

        $this->assertSame(0, VendorInventory::whereDate('entry_date', $tomorrow)->count());

        // With just the line that still has fish, the restock goes through.
        $this->actingAs($vendor)
            ->from('/vendor/sale-report')
            ->post('/vendor/sale-report/restock', [
                'carry_date' => $tomorrow,
                'entries' => [$a->id],
            ])
            ->assertRedirect('/vendor/sale-report')
            ->assertSessionHas('success');

        $carried = VendorInventory::whereDate('entry_date', $tomorrow)->get();

        $this->assertCount(1, $carried);
        $this->assertSame($fishA->id, $carried->first()->fish_type_id);
        $this->assertSame(2.0, (float) $carried->first()->released_kg);
        $this->assertSame('pending', $carried->first()->status);
        $this->assertSame($a->id, $carried->first()->carried_from_id);

        // And the line it came from is settled at zero.
        $this->assertSame(0.0, (float) $a->fresh()->getRemainingStock());
    }

    public function test_the_sale_report_offers_a_date_picker_for_the_restock(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $entry = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 5.0, sold: 0.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', ['items' => [$entry->id => ['total_kg' => 2.0]]]);

        $html = $this->actingAs($vendor)->get('/vendor/sale-report')->assertOk()->getContent();

        $this->assertStringContainsString('Restock a Trading Day', $html);
        $this->assertStringContainsString('name="carry_date"', $html);
        $this->assertStringContainsString(today()->addDay()->toDateString(), $html);
        // Today itself is never offered: the day is declared and closed.
        $this->assertStringContainsString('min="'.today()->addDay()->toDateString().'"', $html, false);
    }

    public function test_restock_cannot_be_pushed_past_the_trading_horizon(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $entry = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 5.0, sold: 0.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', ['items' => [$entry->id => ['total_kg' => 2.0]]]);

        $this->actingAs($vendor)
            ->post('/vendor/sale-report/restock', [
                'carry_date' => today()->addDays(30)->toDateString(),
                'entries' => [$entry->id],
            ])
            ->assertSessionHasErrors('carry_date');

        $this->assertSame(0, VendorInventory::where('carried_from_id', $entry->id)->count());
    }

    public function test_restock_only_takes_todays_confirmed_entries(): void
    {
        $vendor = $this->makeUser('vendor');
        $other = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $mine = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 5.0, sold: 0.0);
        $theirs = $this->makeEntry($other, $fish, today()->toDateString(), released: 5.0, sold: 0.0);
        $yesterday = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 5.0, sold: 1.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', ['items' => [$mine->id => ['total_kg' => 2.0]]]);

        $this->actingAs($vendor)->post('/vendor/sale-report/restock', [
            'carry_date' => today()->addDay()->toDateString(),
            'entries' => [$theirs->id, $yesterday->id],
        ])->assertSessionHasErrors();

        $this->assertFalse($theirs->fresh()->isCarriedOut());
        $this->assertFalse($yesterday->fresh()->isCarriedOut());
    }

    public function test_staff_see_the_resubmitted_entry_waiting_to_be_confirmed(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType('Bangus');

        $entry = $this->makeEntry($vendor, $fish, $this->yesterday(), released: 10.0, sold: 4.0);

        $this->actingAs($vendor)->post("/vendor/my-stock/{$entry->id}/carry");
        $carried = VendorInventory::where('carried_from_id', $entry->id)->sole();

        // Dated today, so it is in front of staff alongside everything else.
        $this->actingAs($this->makeUser('staff'))
            ->get('/staff/confirmations')
            ->assertOk()
            ->assertSee('Bangus')
            ->assertSee($vendor->name);

        $this->assertSame('pending', $carried->fresh()->status);
    }
}
