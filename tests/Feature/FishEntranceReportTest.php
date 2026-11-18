<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FishType;
use App\Models\Report;
use App\Models\User;
use App\Models\VendorInventory;
use App\Services\FishEntrancePdf;
use App\Services\FishEntranceReport;
use App\Services\SupplyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FishEntranceReportTest extends TestCase
{
    use RefreshDatabase;

    private function batch(User $vendor, string $day, float $kg, float $price, string $status = 'confirmed'): VendorInventory
    {
        $fish = FishType::firstOrCreate(['name' => 'Bammer'], ['quality_class' => 'First Class', 'is_active' => true]);

        return VendorInventory::create([
            'vendor_id' => $vendor->id, 'fish_type_id' => $fish->id, 'quality_class' => 'First Class',
            'stock_kg' => $kg, 'released_kg' => $kg, 'price_per_kg' => $price,
            'entry_date' => $day, 'status' => $status,
        ]);
    }

    private function pages(string $pdf): int
    {
        return preg_match_all('~/Type\s*/Page\b~', $pdf);
    }

    private function contentStreams(string $pdf): string
    {
        preg_match_all('~stream\r?\n(.*?)\r?\nendstream~s', $pdf, $matches);

        $text = [];
        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream) ?: $stream;
            preg_match_all('~\(([^()]*)\)\]\s*TJ~s', $decoded, $strings);
            foreach ($strings[1] as $string) {
                $text[] = str_contains($string, "\0") ? mb_convert_encoding($string, 'UTF-8', 'UTF-16BE') : $string;
            }
        }

        return implode(' ', $text);
    }

    public function test_yearly_grid_totals_confirmed_entrance_by_vendor_date_and_month(): void
    {
        $vendor = User::factory()->vendor()->create();
        $other = User::factory()->vendor()->create();
        $expired = $this->batch($vendor, '2024-01-01', 10, 100);
        $expired->update(['disposed_at' => now(), 'disposed_reason' => 'Expired']);
        $this->batch($vendor, '2024-01-01', 5, 200);
        $this->batch($vendor, '2024-02-29', 2.5, 80);
        $this->batch($vendor, '2024-12-31', 3, 50);
        $this->batch($vendor, '2025-01-01', 100, 100);
        $this->batch($vendor, '2024-01-01', 100, 100, 'pending');
        $this->batch($vendor, '2024-01-01', 100, 100, 'rejected');
        $this->batch($other, '2024-01-01', 500, 100);
        $report = new FishEntranceReport(SupplyReport::for('yearly', '2024'), $vendor->id);

        $grid = $report->yearlyGrid($vendor->id);
        $this->assertSame(['kg' => 15.0, 'price' => 2000.0], $grid['days'][1][1]);
        $this->assertSame(['kg' => 2.5, 'price' => 200.0], $grid['days'][29][2]);
        $this->assertSame(['kg' => 3.0, 'price' => 150.0], $grid['months'][12]);
        $this->assertArrayNotHasKey(30, $grid['days']);
        $this->assertSame(20.5, $report->totals()['supply_kg']);
        $this->assertSame(2350.0, $report->totals()['total_price']);
    }

    public function test_supervisor_can_select_each_template_and_preview_without_archiving(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $vendor = User::factory()->vendor()->create(['name' => 'Template Vendor']);
        $this->batch($vendor, '2026-01-03', 10, 150);
        foreach (['daily&date=2026-01-03', 'monthly&month=2026-01', 'yearly&year=2026'] as $filter) {
            $this->actingAs($supervisor)->get('/supervisor/reports?period='.$filter.'&vendor_id='.$vendor->id)
                ->assertOk()->assertSee('Template Vendor')->assertSee('Open report preview')
                ->assertSee('preview=1', false);
        }
        $before = Report::count();
        $beforeLogs = ActivityLog::count();
        $preview = $this->get('/supervisor/reports/pdf?period=yearly&year=2026&preview=1&vendor_id='.$vendor->id)
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $preview->headers->get('Content-Disposition'));
        $this->assertSame(1, $this->pages($preview->getContent()));
        $this->assertSame($before, Report::count());
        $this->assertSame($beforeLogs, ActivityLog::count());
    }

    public function test_yearly_download_is_one_landscape_grid_per_vendor_for_both_roles(): void
    {
        foreach (['Alpha Vendor', 'Beta Vendor'] as $name) {
            $this->batch(User::factory()->vendor()->create(['name' => $name]), '2026-04-10', 15, 120);
        }
        foreach (['staff', 'supervisor'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get('/'.$role.'/reports/pdf?period=yearly&year=2026')->assertOk();
            $pdf = $response->getContent();
            $this->assertSame(2, $this->pages($pdf));
            $this->assertMatchesRegularExpression('~/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+1008(?:\.0+)?\s+612(?:\.0+)?\s*\]~', $pdf);
            $text = $this->contentStreams($pdf);
            foreach (['January', 'December', 'Total Price', 'Total Kg'] as $heading) {
                $this->assertStringContainsString($heading, $text);
            }
            $archive = Report::latest('id')->first();
            $this->assertSame('yearly', $archive->report_data['period']);
            $this->assertSame(30, $archive->report_data['totals']['supply_kg']);
        }
    }

    public function test_daily_form_has_only_the_reference_columns_and_fifty_rows_per_page(): void
    {
        $vendor = User::factory()->vendor()->create();
        for ($i = 0; $i < 51; $i++) {
            $this->batch($vendor, '2026-01-03', 1, 200);
        }
        $report = new FishEntranceReport(SupplyReport::for('daily', '2026-01-03'));
        $pdf = app(FishEntrancePdf::class)->render($report);
        $this->assertSame(2, $this->pages($pdf));
        $text = $this->contentStreams($pdf);
        $this->assertStringContainsString('Price', $text);
        $this->assertStringContainsString('Fish Type', $text);
        $this->assertStringNotContainsString('NET', $text);
        $this->assertStringNotContainsString('GROSS', $text);
        $this->assertStringNotContainsString('Signature', $text);
    }

    public function test_monthly_form_keeps_four_certificates_per_landscape_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->batch(User::factory()->vendor()->create(), '2026-11-03', 2, 120);
        }
        $pdf = app(FishEntrancePdf::class)->render(new FishEntranceReport(SupplyReport::for('monthly', '2026-11')));
        $this->assertSame(2, $this->pages($pdf));
        $this->assertStringContainsString('FOR THE PERIOD OF NOVEMBER 2026', $this->contentStreams($pdf));
    }

    public function test_empty_periods_and_a_selected_vendor_without_entries_still_produce_a_form(): void
    {
        $vendor = User::factory()->vendor()->create();
        foreach (['daily' => '2026-01-01', 'monthly' => '2026-01', 'yearly' => '2026'] as $period => $value) {
            foreach ([null, $vendor->id] as $vendorId) {
                $pdf = app(FishEntrancePdf::class)->render(new FishEntranceReport(SupplyReport::for($period, $value), $vendorId));
                $this->assertSame(1, $this->pages($pdf));
            }
        }
    }

    public function test_a_large_year_exports_without_hydrating_batches_or_growing_the_page_count(): void
    {
        $vendor = User::factory()->vendor()->create();
        $seed = $this->batch($vendor, '2026-06-01', 1, 100)->getAttributes();
        unset($seed['id']);
        for ($i = 0; $i < 48; $i++) {
            DB::table('vendor_inventories')->insert(array_fill(0, 250, $seed));
        }
        $hydrated = 0;
        VendorInventory::retrieved(function () use (&$hydrated) {
            $hydrated++;
        });
        $before = memory_get_usage(true);
        $response = $this->actingAs(User::factory()->supervisor()->create())
            ->get('/supervisor/reports/pdf?period=yearly&year=2026')->assertOk();
        $this->assertSame(0, $hydrated);
        $this->assertSame(1, $this->pages($response->getContent()));
        $this->assertLessThan(32 * 1024 * 1024, memory_get_usage(true) - $before);
        $this->assertSame(12001, Report::latest('id')->first()->report_data['totals']['supply_kg']);
    }

    public function test_invalid_filters_and_unauthorized_roles_cannot_export(): void
    {
        $user = User::factory()->supervisor()->create();
        $this->actingAs($user)->getJson('/supervisor/reports/pdf?period=yearly&year=invalid')->assertUnprocessable();
        $this->getJson('/supervisor/reports/pdf?period=invalid')->assertUnprocessable();
        $this->getJson('/supervisor/reports/pdf?date=2026-02-30')->assertUnprocessable();
        $this->getJson('/supervisor/reports/pdf?vendor_id='.$user->id)->assertUnprocessable();
        $this->actingAs(User::factory()->vendor()->create())->get('/supervisor/reports/pdf')->assertForbidden();
        $this->get('/staff/reports/pdf')->assertForbidden();
    }
}
