<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorSaleReport;
use App\Models\VendorSaleReportItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * VendorSaleReportSeeder
 *
 * Backfills vendor_sale_reports from the confirmed inventory that
 * VendorInventorySeeder already generated, so the staff and supervisor pages
 * open on a real fortnight of history instead of an empty calendar.
 *
 * Two things are deliberately NOT seeded:
 *
 *  - today. Today's declaration belongs to a vendor who is still trading; a
 *    seeded one would make the deadline countdown look already-used.
 *  - one vendor on a handful of days, so the "has not filed" warning and the
 *    gaps in the calendar have something real to point at. A seed where
 *    everyone filed perfectly would hide the page's whole reason for existing.
 */
class VendorSaleReportSeeder extends Seeder
{
    /** How many days back to reconstruct. */
    private const DAYS = 14;

    public function run(): void
    {
        $vendors = User::where('role', 'vendor')->orderBy('id')->get();

        if ($vendors->isEmpty()) {
            $this->command->error('Run UserSeeder and VendorInventorySeeder first!');

            return;
        }

        $reports = 0;
        $items = 0;
        $skippedDays = 0;

        for ($daysAgo = self::DAYS; $daysAgo >= 1; $daysAgo--) {
            $date = Carbon::today()->subDays($daysAgo);

            foreach ($vendors as $vendorIndex => $vendor) {
                // Deterministic gaps: roughly one vendor in seven misses a day,
                // which is what an unfilled calendar month actually looks like.
                $misses = ((int) $date->dayOfWeek * 3 + $vendorIndex) % 7 === 0;

                if ($misses) {
                    $skippedDays++;

                    continue;
                }

                $entries = VendorInventory::with('fishType')
                    ->where('vendor_id', $vendor->id)
                    ->whereDate('entry_date', $date->toDateString())
                    ->where('status', 'confirmed')
                    ->orderBy('id')
                    ->get();

                if ($entries->isEmpty()) {
                    continue;
                }

                $report = VendorSaleReport::updateOrCreate(
                    ['vendor_id' => $vendor->id, 'report_date' => $date->toDateString()],
                    ['submitted_at' => $date->copy()->setTime(21, 30, 0)]
                );

                $totalStock = 0.0;
                $totalSold = 0.0;
                $totalValue = 0.0;

                foreach ($entries as $entry) {
                    $price = (float) $entry->price_per_kg;
                    $released = (float) $entry->released_kg;
                    // sold_kg is already set by the inventory seeder, so the
                    // report is a faithful record rather than a fresh guess.
                    $kg = round(min((float) $entry->sold_kg, $released), 2);
                    $value = round($kg * $price, 2);

                    VendorSaleReportItem::updateOrCreate(
                        ['vendor_inventory_id' => $entry->id],
                        [
                            'vendor_sale_report_id' => $report->id,
                            'fish_type_id' => $entry->fish_type_id,
                            'fish_type_name' => $entry->fishType?->name ?? 'Unknown',
                            'quality_class' => $entry->quality_class,
                            'price_per_kg' => $price,
                            'released_kg' => $released,
                            'total_kg' => $kg,
                            'total_price' => $value,
                        ]
                    );

                    $totalStock += $released;
                    $totalSold += $kg;
                    $totalValue += $value;
                    $items++;
                }

                $report->update([
                    'total_stock_kg' => round($totalStock, 2),
                    'total_sold_kg' => round($totalSold, 2),
                    'total_value' => round($totalValue, 2),
                    'item_count' => $entries->count(),
                ]);

                $reports++;
            }
        }

        $this->command->info("✅ VendorSaleReportSeeder: {$reports} reports · {$items} declared lines.");
        $this->command->info("   ⏭ {$skippedDays} vendor-days intentionally left unfiled.");
        $this->command->info('   📅 Today is left blank on purpose — vendors are still trading.');
    }
}
