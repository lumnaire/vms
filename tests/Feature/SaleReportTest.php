<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorProfile;
use App\Models\VendorSaleReport;
use App\Models\VendorSaleReportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Coverage for the two vendor-facing workflows added on top of the price board:
 * the end-of-day sale declaration and the stale-stock alert.
 *
 * The sale report is the ledger of what a vendor says they sold, so these tests
 * are deliberately adversarial: they check that a hand-built request cannot
 * declare another vendor's fish, cannot inflate quantities past what was
 * released, cannot silently omit part of the day, and cannot file after the
 * trading day closes.
 */
class SaleReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        if ($role === 'vendor') {
            VendorProfile::create([
                'user_id'       => $user->id,
                // Stall numbers are unique, so each vendor in a test gets its own.
                'stall_number'  => 'A-'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'business_name' => $user->name . ' Stall',
            ]);
        }

        return $user;
    }

    private function makeFishType(string $name = 'Bangus'): FishType
    {
        return FishType::create([
            'name'          => $name,
            'quality_class' => 'First Class',
            'is_active'     => true,
        ]);
    }

    /**
     * A confirmed inventory entry for a given day. $released is what staff let
     * the vendor put on the stall, which is the ceiling on a declaration.
     */
    private function makeEntry(
        User $vendor,
        FishType $fish,
        string $date,
        float $released = 20.0,
        string $status = 'confirmed',
        float $price = 250.0,
        float $sold = 0.0
    ): VendorInventory {
        return VendorInventory::create([
            'vendor_id'     => $vendor->id,
            'fish_type_id'  => $fish->id,
            'quality_class' => $fish->quality_class,
            'price_per_kg'  => $price,
            'stock_kg'      => $released * 1.2,
            'released_kg'   => $released,
            'sold_kg'       => $sold,
            'status'        => $status,
            'entry_date'    => $date,
            'is_locked'     => $date !== today()->toDateString(),
        ]);
    }

    private function payload(array $entries, array $kgByEntryId): array
    {
        $items = [];

        foreach ($entries as $entry) {
            $items[$entry->id] = ['total_kg' => $kgByEntryId[$entry->id] ?? 0];
        }

        return ['items' => $items];
    }

    /** Count the rows inside the stale-stock alert table. */
    private function viewStaleLines(string $html): int
    {
        return substr_count($html, 'bi-clock-history');
    }

    /**
     * Just the outstanding-filings notice, isolated from the rest of the page.
     *
     * Every vendor name also appears in the filter dropdown, so a page-wide
     * search for a name proves nothing about who the notice is blaming.
     */
    private function outstandingFilings(string $html): string
    {
        // bg-warning-50 belongs to the notice alone, so it is a reliable fence
        // on both sides of the block.
        $start = strpos($html, 'bg-warning-50 flex items-start');
        $end   = strpos($html, 'Declared Sales');

        if ($start === false || $end === false || $end <= $start) {
            return '';
        }

        return substr($html, $start, $end - $start);
    }


    // ─── The declaration itself ───────────────────────────────────

    public function test_vendor_declares_the_day_against_confirmed_entries(): void
    {
        $vendor = $this->makeUser('vendor');
        $fishA  = $this->makeFishType('Bangus');
        $fishB  = $this->makeFishType('Tilapia');

        $a = $this->makeEntry($vendor, $fishA, today()->toDateString(), released: 20.0, price: 250.0);
        $b = $this->makeEntry($vendor, $fishB, today()->toDateString(), released: 10.0, price: 120.0);

        $response = $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload(
            [$a, $b],
            [$a->id => 12.5, $b->id => 4.0]
        ));

        $response->assertRedirect('/vendor/sale-report')->assertSessionHas('success');

        $report = VendorSaleReport::where('vendor_id', $vendor->id)->sole();

        $this->assertSame(30.0, (float) $report->total_stock_kg);   // 20 + 10 released
        $this->assertSame(16.5, (float) $report->total_sold_kg);   // 12.5 + 4 declared
        $this->assertSame(3605.0, (float) $report->total_value);   // 12.5*250 + 4*120
        $this->assertSame(2, $report->item_count);
        $this->assertNotNull($report->submitted_at);

        // The declaration is the source of truth for sold_kg, so the price
        // board's remaining stock agrees with it.
        $this->assertSame(12.5, (float) $a->fresh()->sold_kg);
        $this->assertSame(4.0, (float) $b->fresh()->sold_kg);
        $this->assertSame(7.5, (float) $a->fresh()->getRemainingStock());
    }

    public function test_items_snapshot_the_trading_day_rather_than_pointing_at_it(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bisugo');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString(), price: 300.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 5.0]));

        $item = VendorSaleReportItem::where('vendor_inventory_id', $entry->id)->sole();

        $this->assertSame('Bisugo', $item->fish_type_name);
        $this->assertSame('First Class', $item->quality_class);
        $this->assertSame(300.0, (float) $item->price_per_kg);
        $this->assertSame(1500.0, (float) $item->total_price);

        // Reclassifying the fish afterwards must not rewrite a statement about
        // a day that has already been declared.
        $fish->update(['quality_class' => 'Second Class']);

        $this->assertSame('First Class', $item->fresh()->quality_class);
    }

    public function test_unconfirmed_entries_are_not_reportable(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Hito');

        $pending = $this->makeEntry($vendor, $fish, today()->toDateString(), status: 'pending');

        // The form never offers it...
        $this->actingAs($vendor)->get('/vendor/sale-report')
            ->assertOk()
            ->assertDontSee('name="items['.$pending->id.'][total_kg]"', false);

        // ...and a hand-built request naming it is refused.
        $this->actingAs($vendor)
            ->from('/vendor/sale-report')
            ->post('/vendor/sale-report', $this->payload([$pending], [$pending->id => 3.0]))
            ->assertRedirect('/vendor/sale-report')
            ->assertSessionHasErrors('items.'.$pending->id.'.total_kg');

        $this->assertSame(0, VendorSaleReport::count());
        $this->assertSame(0.0, (float) $pending->fresh()->sold_kg);
    }

    public function test_vendor_cannot_declare_more_than_was_released(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Pusit');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 10.0);

        $this->actingAs($vendor)
            ->from('/vendor/sale-report')
            ->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 10.01]))
            ->assertRedirect('/vendor/sale-report')
            ->assertSessionHasErrors('items.'.$entry->id.'.total_kg');

        $this->assertSame(0, VendorSaleReport::count());
        $this->assertSame(0.0, (float) $entry->fresh()->sold_kg);
    }

    public function test_a_partial_declaration_is_rejected(): void
    {
        $vendor = $this->makeUser('vendor');
        $fishA  = $this->makeFishType('Bangus');
        $fishB  = $this->makeFishType('Tilapia');

        $a = $this->makeEntry($vendor, $fishA, today()->toDateString());
        $b = $this->makeEntry($vendor, $fishB, today()->toDateString());

        // Declare only the first fish. Totals are summed from what arrives, so
        // without a completeness check the day's stock and revenue would quietly
        // understate themselves.
        $this->actingAs($vendor)
            ->from('/vendor/sale-report')
            ->post('/vendor/sale-report', ['items' => [$a->id => ['total_kg' => 5.0]]])
            ->assertRedirect('/vendor/sale-report')
            ->assertSessionHasErrors('items.'.$b->id.'.total_kg');

        $this->assertSame(0, VendorSaleReport::count());
    }

    public function test_vendor_cannot_declare_another_vendors_stock(): void
    {
        $vendor = $this->makeUser('vendor');
        $other  = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Galunggong');

        $theirs = $this->makeEntry($other, $fish, today()->toDateString(), released: 50.0);

        $this->actingAs($vendor)
            ->from('/vendor/sale-report')
            ->post('/vendor/sale-report', $this->payload([$theirs], [$theirs->id => 50.0]))
            ->assertRedirect('/vendor/sale-report')
            ->assertSessionHasErrors('items.'.$theirs->id.'.total_kg');

        $this->assertSame(0, VendorSaleReport::count());
        $this->assertSame(0.0, (float) $theirs->fresh()->sold_kg);
    }

    public function test_declaring_zero_sold_is_valid_and_still_records_the_day(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Maya-Maya');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 8.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 0.0]))
            ->assertRedirect('/vendor/sale-report')
            ->assertSessionHas('success');

        $report = VendorSaleReport::where('vendor_id', $vendor->id)->sole();

        $this->assertSame(8.0, (float) $report->total_stock_kg);
        $this->assertSame(0.0, (float) $report->total_sold_kg);
        $this->assertSame(0.0, (float) $report->total_value);
        $this->assertSame(1, $report->item_count);
    }

    // ─── Revising before the deadline ─────────────────────────────

    public function test_the_day_can_be_revised_and_is_not_duplicated(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 20.0, price: 250.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 5.0]));

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 9.0]))
            ->assertRedirect('/vendor/sale-report');

        // One row for the vendor for the day, revised in place.
        $this->assertSame(1, VendorSaleReport::count());
        $this->assertSame(1, VendorSaleReportItem::count());

        $report = VendorSaleReport::where('vendor_id', $vendor->id)->sole();
        $this->assertSame(9.0, (float) $report->total_sold_kg);
        $this->assertSame(2250.0, (float) $report->total_value);
        $this->assertSame(9.0, (float) $entry->fresh()->sold_kg);
    }

    public function test_an_entry_rejected_after_filing_drops_out_of_the_report(): void
    {
        $vendor = $this->makeUser('vendor');
        $fishA  = $this->makeFishType('Bangus');
        $fishB  = $this->makeFishType('Tilapia');

        $a = $this->makeEntry($vendor, $fishA, today()->toDateString(), released: 10.0, price: 200.0);
        $b = $this->makeEntry($vendor, $fishB, today()->toDateString(), released: 10.0, price: 100.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$a, $b], [
            $a->id => 4.0, $b->id => 5.0,
        ]));

        // 4 kg * 200 + 5 kg * 100
        $this->assertSame(1300.0, (float) VendorSaleReport::where('vendor_id', $vendor->id)->sole()->total_value);

        // Staff withdraw an approval after the vendor had already declared it.
        $b->update(['status' => 'rejected']);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$a], [$a->id => 6.0]))
            ->assertRedirect('/vendor/sale-report');

        // The line is gone rather than left counting fish that may not be sold.
        $this->assertNull(VendorSaleReportItem::where('vendor_inventory_id', $b->id)->first());
        $this->assertSame(1, VendorSaleReportItem::count());

        $report = VendorSaleReport::where('vendor_id', $vendor->id)->sole();
        $this->assertSame(10.0, (float) $report->total_stock_kg);
        $this->assertSame(6.0, (float) $report->total_sold_kg);
        $this->assertSame(1200.0, (float) $report->total_value);
        $this->assertSame(1, $report->item_count);
    }

    public function test_the_day_closes_at_the_configured_deadline(): void
    {
        // Cut the window short so the guard is observable inside one calendar
        // day — after midnight the day's entries simply stop being today's, so
        // the deadline itself would never be reached.
        config(['inventory.sale_report_deadline' => '17:00']);

        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString());

        $this->travelTo(now()->setTime(16, 59, 0));

        // Just inside the window the declaration stands.
        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 2.0]))
            ->assertRedirect('/vendor/sale-report')
            ->assertSessionHas('success');

        $this->assertSame(1, VendorSaleReport::count());

        // Past the cutoff the day is closed and nothing more is taken.
        $this->travelTo(now()->setTime(17, 1, 0));

        $this->actingAs($vendor)
            ->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 99.0]))
            ->assertSessionHasErrors('report_date');

        // The figures already on record are left exactly as they were.
        $this->assertSame(1, VendorSaleReport::count());
        $this->assertSame(2.0, (float) $entry->fresh()->sold_kg);
        $this->assertSame(2.0, (float) VendorSaleReport::sole()->total_sold_kg);
    }

    public function test_the_form_reports_a_closed_window(): void
    {
        config(['inventory.sale_report_deadline' => '17:00']);

        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString());

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 2.0]));

        $this->travelTo(now()->setTime(17, 1, 0));

        $html = $this->actingAs($vendor)->get('/vendor/sale-report')->assertOk()->getContent();

        $this->assertStringContainsString('5:00 PM', $html);
    }

    public function test_a_previous_day_cannot_be_declared(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->subDay()->toDateString(), released: 10.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 5.0]))
            ->assertSessionHasErrors('items.'.$entry->id.'.total_kg');

        $this->assertSame(0, VendorSaleReport::count());
    }

    public function test_the_form_states_the_deadline_and_prefills_a_revision(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 20.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 6.0]));

        $html = $this->actingAs($vendor)->get('/vendor/sale-report')->assertOk()->getContent();

        $this->assertStringContainsString('11:59 PM', $html);
        $this->assertStringContainsString('value="6.00"', $html);
    }

    // ─── Staff and supervisor view ────────────────────────────────

    public function test_staff_and_supervisor_see_the_report_with_totals(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 20.0, price: 250.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 8.0]));

        foreach (['staff', 'supervisor'] as $role) {
            $user = $this->makeUser($role);
            $url  = $role === 'staff' ? '/staff/sale-reports' : '/supervisor/sale-reports';

            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee($vendor->name)
                ->assertSee('Bangus')
                ->assertSee('2,000.00'); // 8 kg * 250
        }
    }

/**
     * Guards the whole class of bug rather than one page.
     *
     * Blade escapes {{ }}, so an HTML entity is safe as raw markup but becomes
     * visible text the moment it is passed through an echo or a string prop such
     * as prefix="/foot. A page-level assertion only catches the page that
     * happened to break; this catches the next one before a user sees it.
     */
    public function test_no_blade_template_echoes_or_passes_an_html_entity_as_a_prop(): void
    {
        $props  = 'prefix|foot|label|unit|title|placeholder';
        $entity = '&(?!(?:amp|lt|gt|quot|#39);)(?:[a-zA-Z]{2,10}|#\d+|#x[0-9a-fA-F]+);';

        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (preg_split('/\R/', $file->getContents()) ?: [] as $i => $line) {
                $insideEcho = preg_match('/\{\{[^}]*'.$entity.'[^}]*\}\}/', $line);
                $inProp     = preg_match('/\b(?:'.$props.')="\s*'.$entity.'/', $line);

                if ($insideEcho || $inProp) {
                    $offenders[] = $file->getRelativePathname().':'.($i + 1).'  '.trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, "Entities inside {{ }} or string props render as visible text:\n".implode("\n", $offenders));
    }

    /**
     * Currency must render as ₱, never as an HTML entity.
     *
     * A prefix passed as &#8369; reaches the stat-card component as those nine
     * literal characters, and the component echoes it through {{ }}, which escapes
     * the ampersand into &amp;#8369; — so the browser draws the entity's own
     * characters on the page instead of a peso sign. Asserting on the sign itself
     * is what pins this down.
     */

    public function test_currency_renders_as_a_peso_sign_not_an_html_entity(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 20.0, price: 250.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 8.0]));

        foreach ([['/staff/sale-reports', 'staff'], ['/supervisor/sale-reports', 'supervisor']] as [$url, $role]) {
            $html = $this->actingAs($this->makeUser($role))->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('₱', $html, $url.' should show a peso sign');
            $this->assertStringNotContainsString('&amp;#8369;', $html, $url.' leaks an escaped entity');
            $this->assertStringNotContainsString('&#8369;', $html, $url.' leaks a raw entity');
        }
    }

    public function test_the_report_filters_by_date_and_vendor(): void
    {
        $vendorA = $this->makeUser('vendor');
        $vendorB = $this->makeUser('vendor');
        $fish    = $this->makeFishType('Bangus');

        $todayA = $this->makeEntry($vendorA, $fish, today()->toDateString(), price: 100.0);
        $oldB   = $this->makeEntry($vendorB, $fish, today()->subDays(4)->toDateString(), price: 100.0);

        $this->actingAs($vendorA)->post('/vendor/sale-report', $this->payload([$todayA], [$todayA->id => 2.0]));

        // The table renders declared lines, so the second filing needs one.
        $oldReport = VendorSaleReport::create([
            'vendor_id'      => $vendorB->id,
            'report_date'    => today()->subDays(4)->toDateString(),
            'total_stock_kg' => 10.0,
            'total_sold_kg'  => 5.0,
            'total_value'    => 500.0,
            'item_count'     => 1,
            'submitted_at'   => now(),
        ]);

        VendorSaleReportItem::create([
            'vendor_sale_report_id' => $oldReport->id,
            'vendor_inventory_id'   => $oldB->id,
            'fish_type_id'          => $fish->id,
            'fish_type_name'        => $fish->name,
            'quality_class'         => $fish->quality_class,
            'price_per_kg'          => 100.0,
            'released_kg'           => 10.0,
            'total_kg'              => 5.0,
            'total_price'           => 500.0,
        ]);

        $supervisor = $this->makeUser('supervisor');

        // Narrowed to one day: only that day's filing is in scope.
        $this->actingAs($supervisor)
            ->get('/supervisor/sale-reports?date='.today()->toDateString())
            ->assertOk()
            ->assertSee('200.00')    // 2 kg * 100
            ->assertDontSee('500.00');

        // Narrowed to one vendor on that day: only that vendor's filing is in
        // scope, and the outstanding-filings notice is unaffected by the filter.
        $this->actingAs($supervisor)
            ->get('/supervisor/sale-reports?vendor_id='.$vendorB->id.'&date='.today()->subDays(4)->toDateString())
            ->assertOk()
            ->assertSee('500.00')
            ->assertDontSee('200.00')
            ->assertDontSee('not filed for this day');

        // With no query string the page opens on today, so only today's filing
        // is on the table — the older one is reachable by date or the calendar.
        $this->actingAs($supervisor)
            ->get('/supervisor/sale-reports')
            ->assertOk()
            ->assertSee('200.00')
            ->assertDontSee('500.00')
            ->assertSee('1 declaration(s)', false);

        // The calendar still carries the older day, so it is not lost.
        $this->actingAs($supervisor)
            ->get('/supervisor/sale-reports')
            ->assertOk()
            ->assertSee(today()->subDays(4)->toDateString(), false)
            ->assertSee('is-today', false);

        $this->assertSame(2, VendorSaleReport::count());
    }

    public function test_a_vendor_that_has_not_filed_is_called_out(): void
    {
        $filer    = $this->makeUser('vendor');
        $nonFiler = $this->makeUser('vendor');
        $fish     = $this->makeFishType('Bangus');

        $filerEntry    = $this->makeEntry($filer, $fish, today()->toDateString());
        $nonFilerEntry = $this->makeEntry($nonFiler, $fish, today()->toDateString());

        $this->actingAs($filer)->post('/vendor/sale-report', $this->payload([$filerEntry], [$filerEntry->id => 3.0]));

        $notice = $this->outstandingFilings(
            $this->actingAs($this->makeUser('supervisor'))
                ->get('/supervisor/sale-reports?date='.today()->toDateString())
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString('1 vendor', $notice);
        $this->assertStringContainsString($nonFiler->name, $notice);

        // The vendor who did file is not named as outstanding.
        $this->assertStringNotContainsString($filer->name, $notice);
    }

    public function test_the_outstanding_notice_survives_the_vendor_filter(): void
    {
        $filer    = $this->makeUser('vendor');
        $nonFiler = $this->makeUser('vendor');
        $fish     = $this->makeFishType('Bangus');

        $filerEntry    = $this->makeEntry($filer, $fish, today()->toDateString());
        $nonFilerEntry = $this->makeEntry($nonFiler, $fish, today()->toDateString());

        $this->actingAs($filer)->post('/vendor/sale-report', $this->payload([$filerEntry], [$filerEntry->id => 3.0]));

        // Narrowing the table to the filer must not make anyone else vanish
        // from the notice — it answers who is still missing, not what this
        // vendor declared.
        $notice = $this->outstandingFilings(
            $this->actingAs($this->makeUser('supervisor'))
                ->get('/supervisor/sale-reports?date='.today()->toDateString().'&vendor_id='.$filer->id)
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString($nonFiler->name, $notice);
        $this->assertStringNotContainsString($filer->name, $notice);
    }

    public function test_a_vendor_with_no_confirmed_stock_is_not_expected_to_file(): void
    {
        $filer   = $this->makeUser('vendor');
        $idle    = $this->makeUser('vendor');
        $fish    = $this->makeFishType('Bangus');
        $entry   = $this->makeEntry($filer, $fish, today()->toDateString());

        $this->actingAs($filer)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 3.0]));

        // The idle vendor brought nothing, so there is nothing to declare and
        // nothing to chase them for.
        $this->actingAs($this->makeUser('supervisor'))
            ->get('/supervisor/sale-reports?date='.today()->toDateString())
            ->assertOk()
            ->assertDontSee('not filed for this day');
    }

    public function test_the_calendar_marks_a_day_that_has_reports(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString());

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 1.0]));

        $this->actingAs($this->makeUser('staff'))
            ->get('/staff/sale-reports')
            ->assertOk()
            ->assertSee('1 declaration(s)', false);
    }

    public function test_the_report_page_is_closed_to_vendors_and_the_public(): void
    {
        $this->get('/supervisor/sale-reports')->assertRedirect('/login');
        $this->actingAs($this->makeUser('vendor'))->get('/supervisor/sale-reports')->assertForbidden();
    }

    // ─── Stale stock ──────────────────────────────────────────────

    public function test_unsold_stock_is_stale_once_it_passes_the_freshness_window(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $window = config('inventory.stale_after_days');

        $fresh = $this->makeEntry($vendor, $fish, today()->subDays($window - 1)->toDateString(), released: 10.0, sold: 4.0);
        $old   = $this->makeEntry($vendor, $fish, today()->subDays($window)->toDateString(), released: 10.0, sold: 4.0);

        $this->assertFalse($fresh->isStale());
        $this->assertSame($window - 1, $fresh->getAgeInDays());

        $this->assertTrue($old->isStale());
        $this->assertSame($window, $old->getAgeInDays());

        // Remaining stock is what was released minus what was sold.
        $this->assertSame(6.0, (float) $old->getRemainingStock());
        $this->assertSame(1500.0, (float) $old->getRemainingStockValue()); // 6 * 250
    }

    public function test_sold_out_or_unconfirmed_stock_is_never_stale(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $old    = today()->subDays(30)->toDateString();

        $soldOut = $this->makeEntry($vendor, $fish, $old, released: 10.0, sold: 10.0);
        $pending = $this->makeEntry($vendor, $fish, $old, released: 10.0, sold: 0.0, status: 'pending');

        $this->assertFalse($soldOut->isStale());
        $this->assertFalse($pending->isStale());
        $this->assertSame(0.0, (float) $soldOut->getRemainingStock());
    }

    public function test_the_vendor_dashboard_alerts_on_stale_stock_only(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $window = config('inventory.stale_after_days');

        $fresh = $this->makeEntry($vendor, $fish, today()->subDays(1)->toDateString(), released: 10.0, sold: 1.0);
        $old   = $this->makeEntry($vendor, $fish, today()->subDays($window + 1)->toDateString(), released: 12.0, sold: 2.0);
        $soldOut = $this->makeEntry($vendor, $fish, today()->subDays($window + 4)->toDateString(), released: 9.0, sold: 9.0);

        $response = $this->actingAs($vendor)->get('/vendor/dashboard')->assertOk();

        $response->assertSee('of unsold stock');
        $response->assertSee($old->fishType->name);
        $response->assertSee($old->getAgeInDays().'d');

        // The alert is exactly the stale line: a leftover from yesterday is not
        // included, and neither is stock that already sold through.
        $this->assertFalse($fresh->fresh()->isStale());
        $this->assertFalse($soldOut->fresh()->isStale());
        $this->assertSame(1, $this->viewStaleLines($response->getContent()));
    }

    public function test_the_dashboard_alert_caps_the_table_and_says_how_many_more(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $window = config('inventory.stale_after_days');

        // More stale lines than the panel is willing to render.
        for ($i = 0; $i < 13; $i++) {
            $this->makeEntry(
                $vendor,
                $fish,
                today()->subDays($window + $i)->toDateString(),
                released: 5.0,
                sold: 1.0,
            );
        }

        $html = $this->actingAs($vendor)->get('/vendor/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('13 items of unsold stock', $html);
        $this->assertStringContainsString('in the inventory history', $html);
    }

    public function test_the_inventory_history_flags_each_stale_line(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $window = config('inventory.stale_after_days');

        $old = $this->makeEntry($vendor, $fish, today()->subDays($window + 2)->toDateString(), released: 10.0, sold: 3.0);

        $html = $this->actingAs($vendor)->get('/vendor/inventory')->assertOk()->getContent();

        // The remaining quantity and the age are both on the row.
        $this->assertStringContainsString('7.0 kg', $html);
        $this->assertStringContainsString('· '.$old->getAgeInDays().'d', $html);
    }

    public function test_remaining_stock_on_the_board_comes_from_the_declaration(): void
    {
        $vendor = $this->makeUser('vendor');
        $fish   = $this->makeFishType('Bangus');
        $entry  = $this->makeEntry($vendor, $fish, today()->toDateString(), released: 20.0, price: 250.0);

        $this->actingAs($vendor)->post('/vendor/sale-report', $this->payload([$entry], [$entry->id => 14.0]));

        $this->actingAs($vendor)->get('/vendor/dashboard')->assertOk()->assertSee('6.0');
        $this->assertSame(6.0, (float) $entry->fresh()->getRemainingStock());
    }
}
