<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FishType;
use App\Models\Forecast;
use App\Models\PriceGuide;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage of the pages a supervisor, staff member and vendor use.
 *
 * The frontend smoke test only proves a route returns 200. These tests drive the
 * real forms, so a broken modal, a lost validation message or a fish type whose
 * quality class no longer matches its tariff fails here instead of in front of
 * a user.
 */
class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        if ($role === 'vendor') {
            VendorProfile::create([
                'user_id' => $user->id,
                'stall_number' => 'A-01',
                'business_name' => $user->name.' Stall',
            ]);
        }

        return $user;
    }

    private function makeFishType(string $name = 'Bangus', string $class = 'First Class'): FishType
    {
        return FishType::create([
            'name' => $name,
            'quality_class' => $class,
            'is_active' => true,
        ]);
    }

    private function makeGuide(FishType $fish, float $tariff = 240): PriceGuide
    {
        return PriceGuide::create([
            'fish_type_id' => $fish->id,
            'quality_class' => $fish->quality_class,
            'tariff_price' => $tariff,
            'effective_date' => today(),
            'is_active' => true,
        ]);
    }

    // ─── Vendor: price against the tariff ──────────────────────────

    /**
     * The submit form ships the current tariffs to the browser so the price can
     * be compared live. The key is "<fish_type_id>_<quality class>", the same key
     * the staff confirmation page and the board use.
     */
    public function test_vendor_inventory_page_ships_the_current_tariffs(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 240);
        $vendor = $this->makeUser('vendor');

        $response = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk();

        $response->assertSee('const TARIFFS', false);
        $response->assertSee('"'.$fish->id.'_First Class":240', false);
    }

    public function test_vendor_price_input_shows_the_markup_over_the_tariff_without_blocking(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 240);
        $vendor = $this->makeUser('vendor');

        $html = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk()->getContent();

        // Above the tariff is flagged amber and offers the optional reason box.
        $this->assertStringContainsString("classList.toggle('is-above-tariff', isAbove)", $html);
        $this->assertStringContainsString("noteBox.classList.toggle('hidden', !isAbove)", $html);
        $this->assertStringContainsString('name="price_note"', $html);
        $this->assertStringContainsString('Above the tariff.', $html);

        // Both the field and the hint re-evaluate on input and on selection.
        $this->assertStringContainsString('oninput="checkPriceGuide()"', $html);
        $this->assertStringContainsString("fishSelect.addEventListener('change', checkPriceGuide)", $html);
    }

    public function test_removed_tariffs_are_not_offered_to_vendors(): void
    {
        $fish = $this->makeFishType();
        $guide = $this->makeGuide($fish);
        $vendor = $this->makeUser('vendor');

        $key = '"'.$fish->id.'_First Class"';

        $this->actingAs($vendor)->get('/vendor/inventory')
            ->assertSee($key, false);

        $guide->update(['is_active' => false]);

        $this->actingAs($vendor)->get('/vendor/inventory')
            ->assertDontSee($key, false);
    }

    /**
     * Pricing above the tariff is never blocked. The tariff in force is kept on
     * the batch with the vendor's optional reason, so the markup (tubo) is on
     * record for staff and the supervisor.
     */
    public function test_vendor_can_submit_above_the_tariff_and_the_markup_is_recorded(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 120);
        $vendor = $this->makeUser('vendor');

        $this->actingAs($vendor)
            ->post('/vendor/inventory', [
                'fish_type_id' => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg' => 130,
                'stock_kg' => 10,
                'price_note' => 'Caught this morning, bigger size',
            ])
            ->assertRedirect('/vendor/inventory')
            ->assertSessionHasNoErrors();

        $entry = VendorInventory::firstWhere('vendor_id', $vendor->id);
        $this->assertSame('130.00', $entry->price_per_kg);
        $this->assertSame('120.00', $entry->tariff_price);
        $this->assertSame('Caught this morning, bigger size', $entry->price_note);
        $this->assertSame(10.0, $entry->markupPerKg());
        $this->assertSame('pending', $entry->status);
        $this->assertStringContainsString('₱10.00 above the ₱120.00 tariff', ActivityLog::latest('id')->value('description'));
    }

    public function test_a_reason_is_not_kept_when_the_price_is_not_above_the_tariff(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 120);
        $vendor = $this->makeUser('vendor');

        $this->actingAs($vendor)->post('/vendor/inventory', [
            'fish_type_id' => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg' => 115,
            'stock_kg' => 10,
            'price_note' => 'Leftover text from an earlier price',
        ])->assertSessionHasNoErrors();

        $entry = VendorInventory::firstWhere('vendor_id', $vendor->id);
        $this->assertSame('120.00', $entry->tariff_price);
        $this->assertNull($entry->price_note);
        $this->assertSame(-5.0, $entry->markupPerKg());
    }

    public function test_vendor_cannot_submit_a_quality_class_the_fish_does_not_have(): void
    {
        $fish = $this->makeFishType('Tilapia', 'Second Class');
        $vendor = $this->makeUser('vendor');

        $this->actingAs($vendor)
            ->post('/vendor/inventory', [
                'fish_type_id' => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg' => 100,
                'stock_kg' => 5,
            ])
            ->assertSessionHasErrors('quality_class');

        $this->assertDatabaseCount('vendor_inventories', 0);
    }

    // ─── Supervisor: tariff ────────────────────────────────────────

    /**
     * The whole tariff table saves at once. A changed tariff adds a version and
     * switches the old one off, so the history is kept; an unchanged one adds
     * nothing.
     */
    public function test_supervisor_sets_and_changes_tariffs_keeping_the_history(): void
    {
        $bangus = $this->makeFishType('Bangus');
        $tilapia = $this->makeFishType('Tilapia', 'Third Class');
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->post('/supervisor/price-guides', [
                'tariffs' => [$bangus->id => '180', $tilapia->id => ''],
                'english' => [$bangus->id => 'Milkfish', $tilapia->id => ''],
                'effective_date' => today()->toDateString(),
            ])
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHasNoErrors();

        $this->assertSame(180.0, PriceGuide::tariffFor($bangus->id, 'First Class'));
        $this->assertNull(PriceGuide::tariffFor($tilapia->id, 'Third Class'));
        $this->assertSame('Milkfish', $bangus->fresh()->english_name);
        $this->assertSame($supervisor->id, PriceGuide::current()->first()->set_by);

        // Same price again: nothing new.
        $this->actingAs($supervisor)->post('/supervisor/price-guides', [
            'tariffs' => [$bangus->id => '180.00'],
            'effective_date' => today()->toDateString(),
        ]);
        $this->assertSame(1, PriceGuide::where('fish_type_id', $bangus->id)->count());

        // A new price: a second version, the first kept but switched off.
        $this->actingAs($supervisor)->post('/supervisor/price-guides', [
            'tariffs' => [$bangus->id => '200'],
            'effective_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(200.0, PriceGuide::tariffFor($bangus->id, 'First Class'));
        $this->assertSame(['200.00', '180.00'], $bangus->tariffHistory()->pluck('tariff_price')->all());
        $this->assertSame(1, PriceGuide::current()->where('fish_type_id', $bangus->id)->count());
        $this->assertStringContainsString('Bangus ₱180.00 → ₱200.00', ActivityLog::latest('id')->value('description'));
    }

    public function test_tariff_values_and_dates_are_validated(): void
    {
        $fish = $this->makeFishType();
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->from('/supervisor/price-guides')
            ->post('/supervisor/price-guides', [
                'tariffs' => [$fish->id => '-5'],
                'effective_date' => today()->addDay()->toDateString(),
            ])
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHasErrors(['tariffs.'.$fish->id, 'effective_date']);

        $this->assertDatabaseCount('price_guides', 0);
    }

    public function test_removing_a_tariff_keeps_its_history(): void
    {
        $fish = $this->makeFishType();
        $guide = $this->makeGuide($fish, 150);
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->delete('/supervisor/price-guides/'.$guide->id)
            ->assertRedirect('/supervisor/price-guides');

        $this->assertNull(PriceGuide::tariffFor($fish->id, 'First Class'));
        $this->assertDatabaseHas('price_guides', ['id' => $guide->id, 'tariff_price' => 150, 'is_active' => false]);
    }

    public function test_tariff_table_renders_inputs_history_and_the_board_comparison(): void
    {
        $fish = $this->makeFishType('Bangus');
        $this->makeGuide($fish, 120);
        $unset = $this->makeFishType('Tilapia');
        $vendor = $this->makeUser('vendor');

        VendorInventory::create([
            'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'First Class',
            'price_per_kg' => 135, 'tariff_price' => 120, 'price_note' => 'Fresh catch',
            'stock_kg' => 10, 'released_kg' => 10, 'sold_kg' => 0,
            'status' => 'confirmed', 'entry_date' => today(), 'is_locked' => false,
        ]);

        $html = $this->actingAs($this->makeUser('supervisor'))->get('/supervisor/price-guides')
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="tariffs['.$fish->id.']"', $html);
        $this->assertStringContainsString('name="tariffs['.$unset->id.']"', $html);
        $this->assertStringContainsString('value="120.00"', $html);
        $this->assertStringContainsString('openTariffHistory('.$fish->id.')', $html);
        // The board comparison: stall A-01 is ₱15 over the tariff, with its reason.
        $this->assertStringContainsString('+₱15.00', $html);
        $this->assertStringContainsString('Stall A-01', $html);
        $this->assertStringContainsString('Fresh catch', $html);

        // Staff see the same table without inputs.
        $staffHtml = $this->actingAs($this->makeUser('staff'))->get('/staff/price-guides')->assertOk()->getContent();
        $this->assertStringNotContainsString('name="tariffs[', $staffHtml);
        $this->assertStringContainsString('₱120.00', $staffHtml);
        $this->assertStringContainsString('+₱15.00', $staffHtml);
    }

    // ─── Supervisor: fish types ────────────────────────────────────

    public function test_supervisor_can_add_a_fish_type(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->post('/supervisor/fish-types', [
                'name' => 'bangkulis  (white fin)',
                'quality_class' => 'Second Class',
            ])
            ->assertRedirect('/supervisor/fish-types')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fish_types', [
            'name' => 'Bangkulis (White Fin)',
            'quality_class' => 'Second Class',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_fish_type_name_is_rejected(): void
    {
        $this->makeFishType('Bangus');
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->post('/supervisor/fish-types', [
                'name' => 'Bangus',
                'quality_class' => 'First Class',
            ])
            ->assertSessionHasErrors('name');
    }

    /**
     * The tariff on the staff confirmation page and the live check on the vendor
     * form are both keyed on "<fish_type_id>_<quality class>". Reclassing a fish
     * type without moving its tariff would silently orphan it and both features
     * would stop working for that fish.
     */
    public function test_reclassifying_a_fish_type_keeps_its_tariff_and_inventory_in_step(): void
    {
        $fish = $this->makeFishType('Bangus', 'First Class');
        $guide = $this->makeGuide($fish, 240);
        $vendor = $this->makeUser('vendor');

        $entry = VendorInventory::create([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg' => 300,
            'stock_kg' => 10,
            'released_kg' => 10,
            'sold_kg' => 0,
            'status' => 'confirmed',
            'entry_date' => today(),
            'is_locked' => false,
        ]);

        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->put('/supervisor/fish-types/'.$fish->id, [
                'name' => 'Bangus',
                'quality_class' => 'Second Class',
            ])
            ->assertRedirect('/supervisor/fish-types')
            ->assertSessionHasNoErrors();

        $this->assertSame('Second Class', $guide->fresh()->quality_class);
        $this->assertSame('Second Class', $entry->fresh()->quality_class);

        // The vendor form must now resolve the tariff under the new class.
        $this->actingAs($vendor)->get('/vendor/inventory')
            ->assertSee('"'.$fish->id.'_Second Class":240', false);
    }

    public function test_failed_fish_type_edit_reopens_that_rows_modal(): void
    {
        $this->makeFishType('Bangus');
        $fish = $this->makeFishType('Tilapia');
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->from('/supervisor/fish-types')
            ->put('/supervisor/fish-types/'.$fish->id, [
                'name' => 'Bangus', // collides
                'quality_class' => 'First Class',
            ])
            ->assertRedirect('/supervisor/fish-types')
            ->assertSessionHasErrors('name')
            ->assertSessionHas('open_edit_modal', $fish->id);

        $html = $this->actingAs($supervisor)->get('/supervisor/fish-types')
            ->assertOk()->getContent();

        $this->assertStringContainsString("ftOpenModal('editModal".$fish->id."')", $html);
        $this->assertStringContainsString('A fish type with that name already exists.', $html);
    }

    public function test_fish_type_page_offers_no_photo_upload(): void
    {
        $this->makeFishType('Bangus');
        $supervisor = $this->makeUser('supervisor');

        $html = $this->actingAs($supervisor)->get('/supervisor/fish-types')
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringNotContainsString('remove_image', $html);
    }

    /**
     * A fish type that is listed is in use, so it is corrected rather than
     * switched off. There is no activate/deactivate and no delete: the
     * endpoints are gone, not merely hidden from the UI.
     */
    public function test_fish_types_cannot_be_toggled_or_deleted(): void
    {
        $fish = $this->makeFishType();
        $supervisor = $this->makeUser('supervisor');

        // Only GET/POST/PUT exist on this URI now, so a DELETE is rejected on
        // the method rather than reaching a controller.
        $this->actingAs($supervisor)
            ->delete('/supervisor/fish-types/'.$fish->id)
            ->assertStatus(405);

        // The toggle route was removed outright, so nothing matches the URI.
        $this->actingAs($supervisor)
            ->patch('/supervisor/fish-types/'.$fish->id.'/toggle')
            ->assertNotFound();

        $this->assertDatabaseHas('fish_types', ['id' => $fish->id, 'is_active' => true]);
    }

    public function test_the_fish_type_page_offers_edit_and_nothing_else(): void
    {
        $this->makeFishType();
        $this->makeFishType();
        $supervisor = $this->makeUser('supervisor');

        $html = $this->actingAs($supervisor)->get('/supervisor/fish-types')
            ->assertOk()->getContent();

        // One edit form per row, and nothing that spoofs a different verb.
        $this->assertSame(2, substr_count($html, 'value="PUT"'));
        $this->assertStringNotContainsString('value="DELETE"', $html);
        $this->assertStringNotContainsString('value="PATCH"', $html);

        // Edit is the only action rendered.
        $this->assertMatchesRegularExpression('/>\s*Edit\s*</', $html);

        // No lifecycle affordances, and no dead references to the helpers that
        // used to drive their modals.
        foreach (['Deactivate', 'Activate', 'Delete', 'ftOpenDeactivate', 'ftOpenActivate', 'ftOpenDelete'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }

        foreach (['fish-types.toggle', 'fish-types.destroy'] as $route) {
            $this->assertStringNotContainsString($route, $html);
        }
    }

    /**
     * vendor_inventories.fish_type_id is restrictOnDelete, so the old delete
     * endpoint raised a 500 rather than an explanation. With delete removed the
     * row can no longer be touched at all, and editing still works.
     */
    public function test_a_fish_type_with_inventory_can_still_be_edited(): void
    {
        $fish = $this->makeFishType();
        $vendor = $this->makeUser('vendor');

        $entry = VendorInventory::create([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg' => 200,
            'stock_kg' => 10,
            'released_kg' => 10,
            'sold_kg' => 0,
            'status' => 'confirmed',
            'entry_date' => today(),
            'is_locked' => true,
        ]);

        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->put('/supervisor/fish-types/'.$fish->id, [
                'name' => 'Bangus Laka',
                'quality_class' => 'Second Class',
            ])
            ->assertRedirect('/supervisor/fish-types')
            ->assertSessionHasNoErrors();

        $this->assertSame('Bangus Laka', $fish->fresh()->name);
        $this->assertDatabaseHas('vendor_inventories', ['id' => $entry->id]);
    }

    // ─── Staff: confirmation workflow ──────────────────────────────

    public function test_staff_see_the_tariff_the_vendor_price_and_the_markup(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 240);
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');

        $entry = VendorInventory::create([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg' => 300,
            'tariff_price' => 240,
            'price_note' => 'Large size only',
            'stock_kg' => 10,
            'released_kg' => 10,
            'sold_kg' => 0,
            'status' => 'pending',
            'entry_date' => today(),
            'is_locked' => false,
        ]);

        $html = $this->actingAs($staff)->get('/staff/confirmations')
            ->assertOk()->getContent();

        $this->assertStringContainsString('Tariff (standard)', $html);
        $this->assertStringContainsString('₱240.00', $html);
        $this->assertStringContainsString('+₱60.00', $html);
        $this->assertStringContainsString('(+25.0%)', $html);
        $this->assertStringContainsString('Large size only', $html);
        $this->assertStringContainsString('data-label="+₱60.00 over tariff"', $html);

        $this->actingAs($staff)
            ->patch('/staff/confirmations/'.$entry->id.'/approve')
            ->assertRedirect('/staff/confirmations');

        $this->assertSame('confirmed', $entry->fresh()->status);
        $this->assertStringContainsString('₱60.00 above the ₱240.00 tariff', ActivityLog::latest('id')->value('description'));
    }

    public function test_staff_can_reject_a_pending_entry(): void
    {
        $fish = $this->makeFishType();
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');

        $entry = VendorInventory::create([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg' => 700, // above the guide => Expensive
            'stock_kg' => 10,
            'released_kg' => 10,
            'sold_kg' => 0,
            'status' => 'pending',
            'entry_date' => today(),
            'is_locked' => false,
        ]);

        $this->actingAs($staff)
            ->patch('/staff/confirmations/'.$entry->id.'/reject')
            ->assertRedirect('/staff/confirmations');

        $this->assertSame('rejected', $entry->fresh()->status);
    }

    // ─── Every page, with real data behind it ──────────────────────

    /**
     * The pages that read the catalogue render differently once there is actual
     * data, so each one is visited with a full set of rows rather than empty.
     */
    public function test_every_page_renders_with_data_and_no_broken_markup(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish);
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');
        $supervisor = $this->makeUser('supervisor');

        $entry = VendorInventory::create([
            'vendor_id' => $vendor->id,
            'fish_type_id' => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg' => 300,
            'stock_kg' => 10,
            'released_kg' => 10,
            'sold_kg' => 2,
            'status' => 'pending',
            'entry_date' => today(),
            'is_locked' => false,
        ]);

        $pages = [
            'supervisor' => [
                '/supervisor/dashboard', '/supervisor/vendors', '/supervisor/staff',
                '/supervisor/fish-types', '/supervisor/price-guides',
                '/supervisor/forecasts', '/supervisor/reports', '/supervisor/account',
            ],
            'staff' => [
                '/staff/dashboard', '/staff/confirmations', '/staff/vendors',
                '/staff/price-guides', '/staff/reports',
            ],
            'vendor' => ['/vendor/dashboard', '/vendor/inventory'],
        ];

        $broken = [];

        foreach ($pages as $role => $uris) {
            $user = ${$role};

            foreach ($uris as $uri) {
                $response = $this->actingAs($user)->get($uri);
                $response->assertOk();
                $response->assertDontSee('Whoops', false);

                // A leftover '{{' means a mangled attribute in a view. '}}' is
                // deliberately not checked: JSON payloads render legitimate '}}'.
                if (str_contains($response->getContent(), '{{')) {
                    $broken[] = $uri.' contains an uncompiled Blade echo';
                }
            }
        }

        $this->assertSame([], $broken, "Uncompiled Blade output:\n  ".implode("\n  ", $broken));

        // The public board reflects a confirmed entry.
        $this->actingAs($staff)->patch('/staff/confirmations/'.$entry->id.'/approve');
        $this->get('/')->assertOk()->assertSee($fish->name);
    }

    /**
     * `.hidden` has to keep winning over a page's own overlay CSS. Both supervisor
     * pages used to declare `display:flex` in an unlayered <style> block, which
     * outranks Tailwind's layered utility and left every modal open on load.
     */
    public function test_modal_overlays_do_not_define_display_in_page_styles(): void
    {
        foreach ([
            resource_path('views/supervisor/fish-types.blade.php'),
            resource_path('views/partials/tariff-table.blade.php'),
        ] as $file) {
            $contents = file_get_contents($file);

            preg_match('/\.([\w-]*modal-overlay)\s*\{([^}]*)\}/', $contents, $m);
            $this->assertNotEmpty($m, basename($file).': no overlay rule found');
            $this->assertDoesNotMatchRegularExpression(
                '/(^|;|\s)display\s*:/',
                $m[2],
                basename($file).': the overlay sets display, which beats the .hidden utility.'
            );

            // The markup carries the layout utilities instead.
            $this->assertStringContainsString('hidden flex', $contents);
        }
    }

    // ─── Supervisor dashboard scope ────────────────────────────────

    /**
     * The client now asks for charts on the dashboard (supply, sales, tubo,
     * top vendors, quality classes, records). The ARIMA forecast stays on its own
     * page and the activity log stays on My Account.
     */
    public function test_supervisor_dashboard_shows_market_charts_but_no_forecast_or_activity(): void
    {
        $supervisor = $this->makeUser('supervisor');
        $this->makeUser('vendor');
        $this->makeUser('staff');

        $response = $this->actingAs($supervisor)->get('/supervisor/dashboard');
        $response->assertOk();

        $response->assertSee('Total Vendors');
        $response->assertSee('Total Stalls');
        $response->assertSee('Sales this month');
        $response->assertSee('Tubo Leaderboard');
        $response->assertSee('id="supplySoldChart"', false);
        $response->assertSee('Chart.js/4.4.1', false);

        $response->assertDontSee('ARIMA', false);
        $response->assertDontSee('forecastChart', false);
        $response->assertDontSee('Recent Activity');

        $this->actingAs($supervisor)->get('/supervisor/forecasts')->assertOk();
    }

    public function test_my_account_shows_the_recent_activity_log(): void
    {
        $supervisor = $this->makeUser('supervisor');

        ActivityLog::create([
            'user_id' => $supervisor->id,
            'action' => 'update',
            'description' => 'Something worth seeing in the log.',
        ]);

        $this->actingAs($supervisor)->get('/supervisor/account')
            ->assertOk()
            ->assertSee('Recent Activity')
            ->assertSee('Something worth seeing in the log.', false);
    }

    // ─── Supervisor: ARIMA forecast ────────────────────────────────

    /**
     * The forecast page reads pre-computed rows out of the forecasts table, so an
     * empty table renders "No data". On shared hosting nothing regenerates those
     * rows without a cron job, and a deploy migration truncates the table — which
     * is why the forecast was empty on Hostinger while working locally. The page
     * has to fit the model itself when it finds no stored rows.
     */
    public function test_forecast_page_generates_on_demand_when_no_stored_rows_exist(): void
    {
        $fish = $this->makeFishType('Bangus', 'First Class');
        $this->makeGuide($fish);
        $vendor = $this->makeUser('vendor');

        // Enough confirmed history for the model to fit, spread over the past
        // week and all strictly before today, which is what buildSeries() reads.
        $prices = [100, 101, 103, 102, 105, 104, 108, 107];

        foreach ($prices as $i => $price) {
            VendorInventory::create([
                'vendor_id' => $vendor->id,
                'fish_type_id' => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg' => $price,
                'stock_kg' => 10,
                'released_kg' => 10,
                'sold_kg' => 0,
                'status' => 'confirmed',
                'entry_date' => today()->subDays(count($prices) - $i),
                'is_locked' => false,
            ]);
        }

        // Nothing has ever been generated: no seeder, no cron, no command.
        $this->assertDatabaseCount('forecasts', 0);

        $query = 'fish_type_id='.$fish->id.'&quality_class=First+Class&metric=price';

        $response = $this->actingAs($this->makeUser('supervisor'))
            ->get('/supervisor/forecasts?'.$query)
            ->assertOk();

        // The page fitted and persisted the series instead of showing "No data".
        $this->assertGreaterThan(0, Forecast::where('fish_type_id', $fish->id)->count());

        $response->assertDontSee('No forecasts yet');
    }

    /**
     * Most fish types have never been sold, so the first fish alphabetically
     * usually has no history. The page must open on a fish that has a forecast,
     * and say which ones do not, rather than greet the supervisor with an empty
     * chart.
     */
    /**
     * The chart's historical line has exactly one dot per day of the 30-day
     * window. A day with no batch is filled: 0 kg of supply, or the last known
     * price (carried in from before the window when needed), flagged as filled.
     */
    public function test_forecast_history_has_one_point_for_each_of_the_last_30_days(): void
    {
        $fish = $this->makeFishType('Bangus', 'First Class');
        $vendor = $this->makeUser('vendor');
        $days = config('forecast.history_chart_days');
        $this->assertSame(30, $days);

        foreach ([40 => 90, 25 => 100, 10 => 120] as $ago => $price) {
            VendorInventory::create([
                'vendor_id' => $vendor->id,
                'fish_type_id' => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg' => $price,
                'stock_kg' => 10,
                'released_kg' => 10,
                'sold_kg' => 10,
                'status' => 'confirmed',
                'entry_date' => today()->subDays($ago),
                'is_locked' => true,
            ]);
        }

        $supervisor = $this->makeUser('supervisor');
        $get = fn (string $metric) => $this->actingAs($supervisor)
            ->get("/supervisor/forecasts?fish_type_id={$fish->id}&quality_class=First+Class&metric={$metric}")
            ->assertOk()
            ->viewData('historical');

        $price = $get('price');
        $this->assertCount(30, $price);
        $this->assertSame(today()->subDays(30)->toDateString(), $price->keys()->first());
        $this->assertSame(today()->subDay()->toDateString(), $price->keys()->last());
        $this->assertSame(['value' => 90.0, 'filled' => true], $price[today()->subDays(30)->toDateString()]);
        $this->assertSame(['value' => 100.0, 'filled' => false], $price[today()->subDays(25)->toDateString()]);
        $this->assertSame(['value' => 100.0, 'filled' => true], $price[today()->subDays(24)->toDateString()]);
        $this->assertSame(['value' => 120.0, 'filled' => true], $price[today()->subDay()->toDateString()]);
        $this->assertSame(2, $price->where('filled', false)->count());

        $supply = $get('supply');
        $this->assertCount(30, $supply);
        $this->assertSame(['value' => 0.0, 'filled' => true], $supply[today()->subDays(24)->toDateString()]);
        $this->assertSame(['value' => 10.0, 'filled' => false], $supply[today()->subDays(10)->toDateString()]);
    }

    public function test_forecast_history_is_empty_without_a_batch_in_the_window(): void
    {
        $fish = $this->makeFishType('Bangus', 'First Class');

        $historical = $this->actingAs($this->makeUser('supervisor'))
            ->get("/supervisor/forecasts?fish_type_id={$fish->id}&quality_class=First+Class&metric=supply")
            ->assertOk()
            ->viewData('historical');

        $this->assertCount(0, $historical);
    }

    public function test_forecast_page_opens_on_a_fish_that_has_a_forecast(): void
    {
        $empty = $this->makeFishType('Agoot', 'First Class');
        $fish = $this->makeFishType('Tilapia', 'First Class');
        $vendor = $this->makeUser('vendor');

        foreach ([120, 121, 119, 120, 122, 121, 120, 119, 121, 120] as $i => $price) {
            VendorInventory::create([
                'vendor_id' => $vendor->id,
                'fish_type_id' => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg' => $price,
                'stock_kg' => 10,
                'released_kg' => 10,
                'sold_kg' => 10,
                'status' => 'confirmed',
                'entry_date' => today()->subDays(10 - $i),
                'is_locked' => true,
            ]);
        }

        $this->artisan('forecast:generate')->assertSuccessful();

        $response = $this->actingAs($this->makeUser('supervisor'))->get('/supervisor/forecasts')->assertOk();

        $this->assertSame($fish->id, $response->viewData('selectedFishTypeId'));
        $this->assertTrue($response->viewData('forecasts')->isNotEmpty());
        $response->assertSee('Agoot · not enough data');
        $response->assertDontSee('Tilapia · not enough data');
    }
}
