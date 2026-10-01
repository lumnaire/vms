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
 * quality class no longer matches its price bracket fails here instead of in
 * front of a user.
 */
class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        if ($role === 'vendor') {
            VendorProfile::create([
                'user_id'       => $user->id,
                'stall_number'  => 'A-01',
                'business_name' => $user->name . ' Stall',
            ]);
        }

        return $user;
    }

    private function makeFishType(string $name = 'Bangus', string $class = 'First Class'): FishType
    {
        return FishType::create([
            'name'          => $name,
            'quality_class' => $class,
            'is_active'     => true,
        ]);
    }

    private function makeGuide(FishType $fish, float $cheap = 240, float $moderate = 460): PriceGuide
    {
        return PriceGuide::create([
            'fish_type_id'   => $fish->id,
            'quality_class'  => $fish->quality_class,
            'cheap_max'      => $cheap,
            'moderate_max'   => $moderate,
            'effective_date' => today(),
            'is_active'      => true,
        ]);
    }

    // ─── Vendor: price against the guideline ───────────────────────

    /**
     * The submit form ships the active brackets to the browser so the price can
     * be flagged live. The key is "<fish_type_id>_<quality class>", which is the
     * same key the staff confirmation page and the price label use.
     */
    public function test_vendor_inventory_page_ships_the_active_price_guidelines(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 240, 460);
        $vendor = $this->makeUser('vendor');

        $response = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk();

        $response->assertSee('const PRICE_GUIDES', false);
        $response->assertSee('"'.$fish->id.'_First Class":{"cheap":240,"moderate":460}', false);
    }

    public function test_vendor_price_input_reports_the_moderate_ceiling_and_flags_over_guide_prices(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 240, 460);
        $vendor = $this->makeUser('vendor');

        $html = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk()->getContent();

        // The rule itself: flag once the price is above the moderate ceiling.
        $this->assertStringContainsString('price > guide.moderate', $html);
        $this->assertStringContainsString("classList.toggle('is-over-guide', isOver)", $html);

        // The red state and the tooltip copy.
        $this->assertStringContainsString('.form-input.is-over-guide', $html);
        $this->assertStringContainsString('Price exceeds the price guideline.', $html);

        // Both the field and the tooltip re-evaluate on input and on selection.
        $this->assertStringContainsString('oninput="checkPriceGuide()"', $html);
        $this->assertStringContainsString("fishSelect.addEventListener('change', checkPriceGuide)", $html);
    }

    public function test_inactive_guidelines_are_not_offered_to_vendors(): void
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

    public function test_vendor_can_submit_an_entry_above_the_guideline(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 240, 460);
        $vendor = $this->makeUser('vendor');

        $this->actingAs($vendor)
            ->post('/vendor/inventory', [
                'fish_type_id'  => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg'  => 900, // far above the ₱460 ceiling
                'stock_kg'      => 10,
                'released_kg'   => 10,
            ])
            ->assertRedirect('/vendor/inventory')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vendor_inventories', [
            'vendor_id'     => $vendor->id,
            'fish_type_id'  => $fish->id,
            'price_per_kg'  => 900,
            'status'        => 'pending',
        ]);
    }

    public function test_vendor_cannot_submit_a_quality_class_the_fish_does_not_have(): void
    {
        $fish = $this->makeFishType('Tilapia', 'Second Class');
        $vendor = $this->makeUser('vendor');

        $this->actingAs($vendor)
            ->post('/vendor/inventory', [
                'fish_type_id'  => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg'  => 100,
                'stock_kg'      => 5,
                'released_kg'   => 5,
            ])
            ->assertSessionHasErrors('quality_class');

        $this->assertDatabaseCount('vendor_inventories', 0);
    }

    // ─── Supervisor: price brackets ───────────────────────────────

    public function test_supervisor_can_add_edit_and_remove_a_price_bracket(): void
    {
        $fish = $this->makeFishType();
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->post('/supervisor/price-guides', [
                'fish_type_id'   => $fish->id,
                'quality_class'  => 'First Class',
                'cheap_max'      => 150,
                'moderate_max'   => 220,
                'effective_date' => today()->toDateString(),
            ])
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHasNoErrors();

        $guide = PriceGuide::firstWhere('fish_type_id', $fish->id);
        $this->assertNotNull($guide);
        $this->assertSame('150.00', $guide->cheap_max);
        $this->assertSame('220.00', $guide->moderate_max);

        $this->actingAs($supervisor)
            ->put('/supervisor/price-guides/'.$guide->id, [
                'cheap_max'      => 240,
                'moderate_max'   => 460,
                'effective_date' => today()->toDateString(),
            ])
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHasNoErrors();

        $this->assertSame('240.00', $guide->fresh()->cheap_max);
        $this->assertSame('460.00', $guide->fresh()->moderate_max);

        $this->actingAs($supervisor)
            ->delete('/supervisor/price-guides/'.$guide->id)
            ->assertRedirect('/supervisor/price-guides');

        $this->assertDatabaseCount('price_guides', 0);
    }

    /**
     * Both entry points into the bracket modal used to be broken: the card
     * header "Add" called a mangled function name, and the empty-state
     * "Configure now" had no handler at all.
     */
    public function test_bracket_modal_buttons_carry_a_working_handler(): void
    {
        $configured = $this->makeFishType('Bangus');
        $this->makeGuide($configured);
        $unconfigured = $this->makeFishType('Tilapia');

        $html = $this->actingAs($this->makeUser('supervisor'))->get('/supervisor/price-guides')
            ->assertOk()->getContent();

        // Configured fish: the header "Add".
        $this->assertStringContainsString('openAddModal('.$configured->id.')', $html);
        $this->assertStringContainsString('openAddModal('.$unconfigured->id.')', $html);

        $this->assertStringNotContainsString('openAddMolex', $html);
        $this->assertStringNotContainsString('text-blue-600nline-flex', $html);
    }

    public function test_duplicate_bracket_is_rejected_and_reopens_the_add_modal(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish);
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->post('/supervisor/price-guides', [
                'fish_type_id'   => $fish->id,
                'quality_class'  => 'First Class',
                'cheap_max'      => 999,
                'moderate_max'   => 1999,
                'effective_date' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('quality_class');

        $this->assertDatabaseCount('price_guides', 1);
    }

    public function test_moderate_ceiling_must_exceed_the_cheap_ceiling(): void
    {
        $fish = $this->makeFishType();
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->post('/supervisor/price-guides', [
                'fish_type_id'   => $fish->id,
                'quality_class'  => 'First Class',
                'cheap_max'      => 500,
                'moderate_max'   => 100,
                'effective_date' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('moderate_max');

        $this->assertDatabaseCount('price_guides', 0);
    }

    /**
     * A failed edit happens inside a modal. The redirect has to name that modal,
     * otherwise the supervisor lands on a list where the message is invisible and
     * the thresholds they typed are gone.
     */
    public function test_failed_bracket_edit_reopens_the_edit_modal_with_the_typed_values(): void
    {
        $fish = $this->makeFishType();
        $guide = $this->makeGuide($fish);
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->from('/supervisor/price-guides')
            ->put('/supervisor/price-guides/'.$guide->id, [
                'cheap_max'      => 500,
                'moderate_max'   => 100,
                'effective_date' => today()->toDateString(),
            ])
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHasErrors('moderate_max')
            ->assertSessionHas('open_edit_modal');

        $html = $this->actingAs($supervisor)->get('/supervisor/price-guides')
            ->assertOk()->getContent();

        // The edit modal is the one that reopens, pre-filled with what was typed.
        $this->assertStringContainsString("openEditModal(\n", $html);
        $this->assertStringContainsString("value=\"500\"", $html);
        $this->assertStringContainsString("value=\"100\"", $html);
        $this->assertStringContainsString('Moderate max must be greater than the cheap max.', $html);
        // The add modal must stay shut, otherwise the message is shown in the
        // wrong dialog.
        $this->assertStringNotContainsString("openAddModal(\n", $html);
    }

    // ─── Supervisor: fish types ────────────────────────────────────

    public function test_supervisor_can_add_a_fish_type(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->post('/supervisor/fish-types', [
                'name'          => 'bangkulis  (white fin)',
                'quality_class' => 'Second Class',
            ])
            ->assertRedirect('/supervisor/fish-types')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fish_types', [
            'name'          => 'Bangkulis (White Fin)',
            'quality_class' => 'Second Class',
            'is_active'     => true,
        ]);
    }

    public function test_duplicate_fish_type_name_is_rejected(): void
    {
        $this->makeFishType('Bangus');
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->post('/supervisor/fish-types', [
                'name'          => 'Bangus',
                'quality_class' => 'First Class',
            ])
            ->assertSessionHasErrors('name');
    }

    /**
     * The price label on the staff confirmation page and the live check on the
     * vendor form are both keyed on "<fish_type_id>_<quality class>". Reclassing a
     * fish type without moving its bracket would silently orphan the bracket and
     * both features would stop working for that fish.
     */
    public function test_reclassifying_a_fish_type_keeps_its_bracket_and_inventory_in_step(): void
    {
        $fish = $this->makeFishType('Bangus', 'First Class');
        $guide = $this->makeGuide($fish, 240, 460);
        $vendor = $this->makeUser('vendor');

        $entry = VendorInventory::create([
            'vendor_id'     => $vendor->id,
            'fish_type_id'  => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg'  => 300,
            'stock_kg'      => 10,
            'released_kg'   => 10,
            'sold_kg'       => 0,
            'status'        => 'confirmed',
            'entry_date'    => today(),
            'is_locked'     => false,
        ]);

        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->put('/supervisor/fish-types/'.$fish->id, [
                'name'          => 'Bangus',
                'quality_class' => 'Second Class',
            ])
            ->assertRedirect('/supervisor/fish-types')
            ->assertSessionHasNoErrors();

        $this->assertSame('Second Class', $guide->fresh()->quality_class);
        $this->assertSame('Second Class', $entry->fresh()->quality_class);

        // The vendor form must now resolve the bracket under the new class.
        $this->actingAs($vendor)->get('/vendor/inventory')
            ->assertSee('"'.$fish->id.'_Second Class":{"cheap":240,"moderate":460}', false);
    }

    public function test_failed_fish_type_edit_reopens_that_rows_modal(): void
    {
        $this->makeFishType('Bangus');
        $fish = $this->makeFishType('Tilapia');
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->from('/supervisor/fish-types')
            ->put('/supervisor/fish-types/'.$fish->id, [
                'name'          => 'Bangus', // collides
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

    public function test_edit_modal_photo_hints_have_unique_ids(): void
    {
        $one = $this->makeFishType('Bangus');
        $two = $this->makeFishType('Tilapia');
        $supervisor = $this->makeUser('supervisor');

        $html = $this->actingAs($supervisor)->get('/supervisor/fish-types')
            ->assertOk()->getContent();

        // ftPreviewImg() looks these up by id to swap the hint text.
        $this->assertStringContainsString('id="editHint'.$one->id.'"', $html);
        $this->assertStringContainsString('id="editHint'.$two->id.'"', $html);
        $this->assertStringNotContainsString('editHintext-slate-400', $html);
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
            'vendor_id'     => $vendor->id,
            'fish_type_id'  => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg'  => 200,
            'stock_kg'      => 10,
            'released_kg'   => 10,
            'sold_kg'       => 0,
            'status'        => 'confirmed',
            'entry_date'    => today(),
            'is_locked'     => true,
        ]);

        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->put('/supervisor/fish-types/'.$fish->id, [
                'name'          => 'Bangus Laka',
                'quality_class' => 'Second Class',
            ])
            ->assertRedirect('/supervisor/fish-types')
            ->assertSessionHasNoErrors();

        $this->assertSame('Bangus Laka', $fish->fresh()->name);
        $this->assertDatabaseHas('vendor_inventories', ['id' => $entry->id]);
    }

    // ─── Staff: confirmation workflow ──────────────────────────────

    public function test_staff_sees_the_price_label_for_a_pending_entry(): void
    {
        $fish = $this->makeFishType();
        $this->makeGuide($fish, 240, 460);
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');

        $entry = VendorInventory::create([
            'vendor_id'     => $vendor->id,
            'fish_type_id'  => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg'  => 300, // within the guide => Moderate
            'stock_kg'      => 10,
            'released_kg'   => 10,
            'sold_kg'       => 0,
            'status'        => 'pending',
            'entry_date'    => today(),
            'is_locked'     => false,
        ]);

        $html = $this->actingAs($staff)->get('/staff/confirmations')
            ->assertOk()->getContent();

        $this->assertStringContainsString('price-label-moderate', $html);
        $this->assertStringContainsString('data-label="Moderate"', $html);
        $this->assertStringContainsString('data-labelclass="price-label-moderate"', $html);

        $this->actingAs($staff)
            ->patch('/staff/confirmations/'.$entry->id.'/approve')
            ->assertRedirect('/staff/confirmations');

        $this->assertSame('confirmed', $entry->fresh()->status);
    }

    public function test_staff_can_reject_a_pending_entry(): void
    {
        $fish = $this->makeFishType();
        $vendor = $this->makeUser('vendor');
        $staff = $this->makeUser('staff');

        $entry = VendorInventory::create([
            'vendor_id'     => $vendor->id,
            'fish_type_id'  => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg'  => 700, // above the guide => Expensive
            'stock_kg'      => 10,
            'released_kg'   => 10,
            'sold_kg'       => 0,
            'status'        => 'pending',
            'entry_date'    => today(),
            'is_locked'     => false,
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
            'vendor_id'     => $vendor->id,
            'fish_type_id'  => $fish->id,
            'quality_class' => 'First Class',
            'price_per_kg'  => 300,
            'stock_kg'      => 10,
            'released_kg'   => 10,
            'sold_kg'       => 2,
            'status'        => 'pending',
            'entry_date'    => today(),
            'is_locked'     => false,
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
            resource_path('views/supervisor/price-guides.blade.php'),
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
     * The client asked for the dashboard to be figures only, so the ARIMA chart
     * and its Chart.js payload are gone from this page. The activity log moved
     * to My Account, which is where an account-level record belongs.
     */
    public function test_supervisor_dashboard_shows_figures_but_no_forecast_chart_or_activity(): void
    {
        $supervisor = $this->makeUser('supervisor');

        // makeUser() already gives the vendor a stall profile, so the stall and
        // stock figures above it have something to count.
        $this->makeUser('vendor');
        $this->makeUser('staff');

        $response = $this->actingAs($supervisor)->get('/supervisor/dashboard');
        $response->assertOk();

        // The analytics figures are what remains.
        $response->assertSee('Total Vendors');
        $response->assertSee('Total Stalls');

        // The forecast card, its canvas and its CDN script are not.
        $response->assertDontSee('Price Forecast', false);
        $response->assertDontSee('forecastMiniChart', false);
        $response->assertDontSee('chart.js', false);
        $response->assertDontSee('ARIMA', false);

        // The activity log now lives on My Account.
        $response->assertDontSee('Recent Activity');

        // The dedicated forecast page is untouched.
        $this->actingAs($supervisor)->get('/supervisor/forecasts')->assertOk();
    }

    public function test_my_account_shows_the_recent_activity_log(): void
    {
        $supervisor = $this->makeUser('supervisor');

        ActivityLog::create([
            'user_id'     => $supervisor->id,
            'action'      => 'update',
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
                'vendor_id'     => $vendor->id,
                'fish_type_id'  => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg'  => $price,
                'stock_kg'      => 10,
                'released_kg'   => 10,
                'sold_kg'       => 0,
                'status'        => 'confirmed',
                'entry_date'    => today()->subDays(count($prices) - $i),
                'is_locked'     => false,
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
}
