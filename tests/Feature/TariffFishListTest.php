<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\PriceGuide;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The supervisor manages the fish list from the tariff table: add a fish that
 * is missing from a class, rename or move it, remove it, restore it.
 */
class TariffFishListTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supervisor = User::factory()->create(['role' => 'supervisor']);
    }

    private function fish(string $name, string $class = 'First Class'): FishType
    {
        return FishType::create(['name' => $name, 'quality_class' => $class, 'is_active' => true]);
    }

    private function batch(FishType $fish, array $extra = []): VendorInventory
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::create(['user_id' => $vendor->id, 'stall_number' => 'A-'.$vendor->id]);

        return VendorInventory::create(array_merge([
            'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => $fish->quality_class,
            'price_per_kg' => 100, 'stock_kg' => 10, 'released_kg' => 10, 'sold_kg' => 10,
            'status' => 'confirmed', 'entry_date' => today()->subDays(5), 'is_locked' => true,
        ], $extra));
    }

    public function test_the_table_offers_add_per_class_and_edit_and_remove_per_fish(): void
    {
        $fish = $this->fish('Bangus');
        $this->fish('Sugpo', 'Special Class');

        $html = $this->actingAs($this->supervisor)->get('/supervisor/price-guides')->assertOk()->getContent();

        $this->assertStringContainsString('Add to Special Class', $html);
        $this->assertStringContainsString('Add to First Class', $html);
        $this->assertStringContainsString('id="fishModal"', $html);
        // One edit and one remove control per fish (URLs are JSON-escaped in the handlers).
        $this->assertSame(2, substr_count($html, "openFishModal('edit'"));
        $this->assertSame(2, substr_count($html, 'openRemoveFish(JSON.parse('));
        $this->assertStringContainsString('"name":"'.$fish->name.'"', $html);

        // Staff see the table but cannot manage the list.
        $staffHtml = $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/staff/price-guides')->getContent();
        $this->assertStringNotContainsString('Add to First Class', $staffHtml);
        $this->assertStringNotContainsString('openRemoveFish', $staffHtml);
    }

    public function test_a_missing_fish_can_be_added_to_a_class_with_its_tariff(): void
    {
        $this->actingAs($this->supervisor)
            ->post('/supervisor/price-guides/fish', [
                'name' => 'hipon  (medium)',
                'english_name' => 'Shrimp',
                'quality_class' => 'Special Class',
                'tariff_price' => '450',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fish = FishType::firstWhere('name', 'Hipon (Medium)');
        $this->assertNotNull($fish);
        $this->assertSame('Shrimp', $fish->english_name);
        $this->assertSame('Special Class', $fish->quality_class);
        $this->assertSame(450.0, PriceGuide::tariffFor($fish->id, 'Special Class'));
    }

    public function test_a_duplicate_name_reopens_the_dialog_with_the_error(): void
    {
        $this->fish('Bangus');

        $this->actingAs($this->supervisor)
            ->from('/supervisor/price-guides')
            ->post('/supervisor/price-guides/fish', ['name' => 'Bangus', 'quality_class' => 'First Class'])
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHasErrorsIn('fish', 'name')
            ->assertSessionHas('fish_modal', ['mode' => 'add']);

        $this->actingAs($this->supervisor)->get('/supervisor/price-guides')
            ->assertSee('A fish type with that name already exists.');

        $this->assertSame(1, FishType::count());
    }

    public function test_editing_moves_the_tariff_and_batches_to_the_new_class(): void
    {
        $fish = $this->fish('Lapu-Lapu', 'First Class');
        PriceGuide::setTariff($fish, 380, today()->toDateString(), null);
        $batch = $this->batch($fish);

        $this->actingAs($this->supervisor)
            ->put('/supervisor/price-guides/fish/'.$fish->id, [
                'name' => 'Lapu-Lapu (Baraca)',
                'english_name' => 'Grouper',
                'quality_class' => 'Special Class',
            ])
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHasNoErrors();

        $fish->refresh();
        $this->assertSame('Lapu-Lapu (Baraca)', $fish->name);
        $this->assertSame('Grouper', $fish->english_name);
        $this->assertSame(380.0, PriceGuide::tariffFor($fish->id, 'Special Class'));
        $this->assertSame('Special Class', $batch->fresh()->quality_class);
    }

    public function test_a_fish_that_was_never_sold_is_deleted_with_its_tariff(): void
    {
        $fish = $this->fish('Typo Fish');
        PriceGuide::setTariff($fish, 99, today()->toDateString(), null);

        $this->actingAs($this->supervisor)
            ->delete('/supervisor/price-guides/fish/'.$fish->id)
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHas('success', 'Typo Fish deleted.');

        $this->assertDatabaseMissing('fish_types', ['id' => $fish->id]);
        $this->assertDatabaseCount('price_guides', 0);
    }

    public function test_a_fish_with_history_is_hidden_and_can_be_restored(): void
    {
        $fish = $this->fish('Maya-Maya');
        $batch = $this->batch($fish);

        $this->actingAs($this->supervisor)
            ->delete('/supervisor/price-guides/fish/'.$fish->id)
            ->assertRedirect('/supervisor/price-guides');

        $this->assertFalse($fish->fresh()->is_active);
        $this->assertDatabaseHas('vendor_inventories', ['id' => $batch->id]);

        $page = $this->actingAs($this->supervisor)->get('/supervisor/price-guides')->assertOk();
        $page->assertSee('Removed Fish');
        $this->assertFalse($page->viewData('allFishTypes')->contains('id', $fish->id));

        // Adding the same name again points at Restore instead.
        $this->actingAs($this->supervisor)
            ->post('/supervisor/price-guides/fish', ['name' => 'Maya-Maya', 'quality_class' => 'First Class'])
            ->assertSessionHasErrorsIn('fish', 'name');
        $this->assertStringContainsString('was removed earlier', session('errors')->getBag('fish')->first('name'));

        $this->actingAs($this->supervisor)
            ->patch('/supervisor/price-guides/fish/'.$fish->id.'/restore')
            ->assertRedirect('/supervisor/price-guides');

        $this->assertTrue($fish->fresh()->is_active);
    }

    public function test_a_fish_on_sale_cannot_be_removed(): void
    {
        $fish = $this->fish('Tilapia');
        $this->batch($fish, ['entry_date' => today(), 'sold_kg' => 0]);

        $this->actingAs($this->supervisor)
            ->delete('/supervisor/price-guides/fish/'.$fish->id)
            ->assertRedirect('/supervisor/price-guides')
            ->assertSessionHas('error');

        $this->assertTrue($fish->fresh()->is_active);
    }

    public function test_vendors_cannot_submit_a_removed_fish(): void
    {
        $fish = $this->fish('Maya-Maya');
        $fish->update(['is_active' => false]);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $this->actingAs($vendor)
            ->post('/vendor/inventory', [
                'fish_type_id' => $fish->id,
                'quality_class' => 'First Class',
                'price_per_kg' => 200,
                'stock_kg' => 5,
            ])
            ->assertSessionHasErrors('fish_type_id');

        $this->assertDatabaseCount('vendor_inventories', 0);
    }
}
