<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AM / PM trading sessions and repeat submissions, end to end.
 *
 * The scenario the market described: a vendor logs 15 kg of Hipon in the morning,
 * another 5 kg later that morning, and 5 kg after lunch. Each is its own line.
 * Staff see the lines side by side, the consumer board shows one card per vendor
 * with every line and the total still for sale, and declaring the morning's sales
 * takes those kilograms off the board. More of a confirmed fish is added by the
 * vendor without a second staff review.
 */
class MarketSessionTest extends TestCase
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

    private function makeFishType(string $name = 'Hipon', string $quality = 'First Class'): FishType
    {
        return FishType::create(['name' => $name, 'quality_class' => $quality, 'is_active' => true]);
    }

    private function line(User $vendor, FishType $fish, string $session, float $kg, string $status = 'confirmed', float $price = 300.0): VendorInventory
    {
        return VendorInventory::create([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => $fish->quality_class,
            'market_session' => $session,
            'price_per_kg' => $price,
            'stock_kg' => $kg,
            'released_kg' => $kg,
            'sold_kg' => 0,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'entry_date' => today()->toDateString(),
            'is_locked' => false,
        ]);
    }

    private function submit(User $vendor, FishType $fish, string $session, float $kg)
    {
        return $this->actingAs($vendor)->post('/vendor/inventory', [
            'fish_type_id' => $fish->id,
            'quality_class' => $fish->quality_class,
            'market_session' => $session,
            'price_per_kg' => 300,
            'stock_kg' => $kg,
            'released_kg' => $kg,
        ]);
    }

    // ─── Vendor: submitting ──────────────────────────────────────

    public function test_vendor_can_submit_the_same_fish_several_times_in_both_sessions(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();

        $this->submit($vendor, $fish, 'AM', 15)->assertSessionHasNoErrors();
        $this->submit($vendor, $fish, 'AM', 5)->assertSessionHasNoErrors();
        $this->submit($vendor, $fish, 'PM', 5)->assertSessionHasNoErrors();

        $lines = VendorInventory::where('vendor_id', $vendor->id)->get();
        $this->assertCount(3, $lines);
        $this->assertSame(2, $lines->where('market_session', 'AM')->count());
        $this->assertSame(1, $lines->where('market_session', 'PM')->count());
        $this->assertTrue($lines->every(fn ($l) => $l->status === 'pending'));
    }

    public function test_a_submission_needs_a_session(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();

        $this->actingAs($vendor)->post('/vendor/inventory', [
            'fish_type_id' => $fish->id,
            'quality_class' => $fish->quality_class,
            'price_per_kg' => 300,
            'stock_kg' => 5,
            'released_kg' => 5,
        ])->assertSessionHasErrors('market_session');

        $this->assertDatabaseCount('vendor_inventories', 0);
    }

    public function test_inventory_page_renders_the_session_picker_and_add_stock_control(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $confirmed = $this->line($vendor, $fish, 'AM', 15);
        $this->line($vendor, $fish, 'PM', 5, 'pending');

        $html = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk()->getContent();

        $this->assertStringContainsString('name="market_session" value="AM"', $html);
        $this->assertStringContainsString('name="market_session" value="PM"', $html);
        $this->assertStringContainsString(route('vendor.inventory.add-stock', $confirmed), $html);
        $this->assertStringNotContainsString('{{', $html);

        // Same control on the dashboard's table of today's lines.
        $this->actingAs($vendor)->get('/vendor/dashboard')->assertOk()
            ->assertSee(route('vendor.inventory.add-stock', $confirmed), false);
    }

    // ─── Vendor: adding stock without staff ───────────────────────

    public function test_adding_stock_in_the_same_session_tops_the_line_up_without_review(): void
    {
        $vendor = $this->makeUser('vendor');
        $line = $this->line($vendor, $this->makeFishType(), 'AM', 15);

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$line->id}/stock", [
                'stock_kg' => 5, 'released_kg' => 5, 'market_session' => 'AM',
            ])
            ->assertRedirect('/vendor/inventory')
            ->assertSessionHasNoErrors();

        $line->refresh();
        $this->assertSame(20.0, (float) $line->released_kg);
        $this->assertSame('confirmed', $line->status);
        $this->assertSame('AM', $line->market_session);
        $this->assertSame(1, VendorInventory::count());
    }

    public function test_adding_stock_in_the_other_session_opens_a_confirmed_line_and_leaves_the_original(): void
    {
        $vendor = $this->makeUser('vendor');
        $line = $this->line($vendor, $this->makeFishType(), 'AM', 15, price: 320);

        $this->actingAs($vendor)->from('/vendor/dashboard')
            ->post("/vendor/inventory/{$line->id}/stock", [
                'stock_kg' => 5, 'released_kg' => 5, 'market_session' => 'PM',
            ])
            ->assertRedirect('/vendor/dashboard')
            ->assertSessionHasNoErrors();

        $this->assertSame(15.0, (float) $line->fresh()->released_kg);
        $this->assertSame('AM', $line->fresh()->market_session);

        $pm = VendorInventory::where('market_session', 'PM')->sole();
        $this->assertSame('confirmed', $pm->status);
        $this->assertSame(5.0, (float) $pm->released_kg);
        $this->assertSame(320.0, (float) $pm->price_per_kg, 'The staff-confirmed price carries over.');
    }

    public function test_stock_cannot_be_added_to_a_pending_line(): void
    {
        $vendor = $this->makeUser('vendor');
        $line = $this->line($vendor, $this->makeFishType(), 'AM', 15, 'pending');

        $this->actingAs($vendor)->from('/vendor/inventory')
            ->post("/vendor/inventory/{$line->id}/stock", ['stock_kg' => 5, 'released_kg' => 5])
            ->assertSessionHasErrors('stock');

        $this->assertSame(15.0, (float) $line->fresh()->released_kg);
    }

    public function test_a_vendor_cannot_add_stock_to_another_vendors_line(): void
    {
        $line = $this->line($this->makeUser('vendor'), $this->makeFishType(), 'AM', 15);

        $this->actingAs($this->makeUser('vendor'))
            ->post("/vendor/inventory/{$line->id}/stock", ['stock_kg' => 5, 'released_kg' => 5])
            ->assertForbidden();
    }

    // ─── Staff: seeing repeat submissions ─────────────────────────

    public function test_staff_dashboard_groups_a_vendors_repeat_submissions(): void
    {
        $vendor = $this->makeUser('vendor', 'Aling Rosa');
        $fish = $this->makeFishType();
        $this->line($vendor, $fish, 'AM', 15);
        $this->line($vendor, $fish, 'AM', 5);
        $this->line($vendor, $fish, 'PM', 5, 'pending');

        // A single line of another fish is not a repeat.
        $this->line($vendor, $this->makeFishType('Bangus', 'Second Class'), 'AM', 8);

        $response = $this->actingAs($this->makeUser('staff'))->get('/staff/dashboard')->assertOk();

        $groups = $response->viewData('repeatSubmissions');
        $this->assertCount(1, $groups);
        $this->assertSame(3, $groups[0]['lines']->count());
        $this->assertSame(20.0, $groups[0]['confirmed_kg']);
        $this->assertSame(5.0, $groups[0]['pending_kg']);

        $response->assertSee('Multiple Submissions Today')->assertSee('Aling Rosa');
    }

    public function test_confirmations_page_flags_a_pending_repeat_submission(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->line($vendor, $fish, 'AM', 15);
        $this->line($vendor, $fish, 'PM', 5, 'pending');

        $this->actingAs($this->makeUser('staff'))->get('/staff/confirmations')->assertOk()
            ->assertSee('Repeat submission')
            ->assertSee('20.0 kg');
    }

    // ─── Consumer board ───────────────────────────────────────────

    public function test_board_shows_one_card_per_vendor_with_every_session_line_and_the_total(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->line($vendor, $fish, 'AM', 15);
        $this->line($vendor, $fish, 'AM', 5);
        $this->line($vendor, $fish, 'PM', 5);
        $this->line($vendor, $fish, 'PM', 4, 'pending'); // not on the board yet

        $vendors = $this->get('/')->assertOk()->assertSee('Hipon')->viewData('vendors');

        $this->assertCount(1, $vendors);
        $this->assertCount(1, $vendors[0]['fish'], 'All lines of the same fish share one row.');

        $hipon = $vendors[0]['fish'][0];
        $this->assertCount(3, $hipon['lines']);
        $this->assertSame(['AM', 'AM', 'PM'], array_column($hipon['lines'], 'session'));
        $this->assertSame(20.0, $hipon['am_kg']);
        $this->assertSame(5.0, $hipon['pm_kg']);
        $this->assertSame(25.0, $hipon['remaining_kg']);
        $this->assertSame(25.0, $vendors[0]['remaining_kg']);
    }

    public function test_declaring_the_morning_sales_takes_them_off_the_board(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $am = $this->line($vendor, $fish, 'AM', 15);
        $pm = $this->line($vendor, $fish, 'PM', 5);

        // Midday: the vendor files the report with what the morning sold.
        $this->actingAs($vendor)->post('/vendor/sale-report', [
            'items' => [
                $am->id => ['total_kg' => 10],
                $pm->id => ['total_kg' => 0],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vendor_sale_report_items', [
            'vendor_inventory_id' => $am->id,
            'market_session' => 'AM',
        ]);

        $hipon = $this->get('/')->viewData('vendors')[0]['fish'][0];

        $this->assertSame(5.0, $hipon['am_kg']);
        $this->assertSame(5.0, $hipon['pm_kg']);
        $this->assertSame(10.0, $hipon['remaining_kg']);
        $this->assertSame(10.0, $hipon['sold_kg']);
    }

    public function test_board_leaves_out_stock_carried_to_another_day(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish = $this->makeFishType();
        $this->line($vendor, $fish, 'AM', 15)->update(['carried_out_at' => now()]);
        $this->line($vendor, $fish, 'PM', 5);

        $hipon = $this->get('/')->viewData('vendors')[0]['fish'][0];

        $this->assertCount(1, $hipon['lines']);
        $this->assertSame(5.0, $hipon['remaining_kg']);
    }
}
