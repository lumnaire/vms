<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\FishType;
use App\Models\VendorInventory;
use Carbon\Carbon;

/**
 * VendorInventorySeeder
 *
 * Populates vendor_inventories with realistic data:
 *   • Historical  : May 1, 2026 → yesterday  (all CONFIRMED, locked)
 *   • Gap fill    : Any days between June 6 cutoff and today (all CONFIRMED, locked)
 *   • Today       : Mixed CONFIRMED + PENDING entries
 *       – Vendors 0-6  (FS-46 to FS-52) → all items CONFIRMED (staff processed morning batch)
 *       – Vendors 7-8  (FS-53 to FS-54) → alternating CONFIRMED / PENDING per item
 *       – Vendors 9-11 (FS-55 to FS-57) → all items PENDING (awaiting staff review)
 *
 * Price basis: BFAR Region 5 / BusinessMirror / GMA News May–Jun 2026.
 * Catanduanes island prices are ~10-15% above NCR baseline (logistics premium).
 * Oil price shock factor applied from May 15 onward (+0.3%/day, capped at ~+6.3%).
 *
 * Usage:
 *   php artisan migrate:fresh --seed        ← runs everything, today's data always fresh
 *   php artisan db:seed --class=VendorInventorySeeder
 */
class VendorInventorySeeder extends Seeder
{
    public function run(): void
    {
        VendorInventory::truncate();

        $staff   = User::where('role', 'staff')->first();
        $vendors = User::where('role', 'vendor')->orderBy('id')->get();

        if ($vendors->isEmpty() || !$staff) {
            $this->command->error('Run UserSeeder first!');
            return;
        }

        $ft = FishType::pluck('id', 'name'); // name => id map

        // ── Base retail prices (PHP/kg) — Catanduanes wet market ───────────────
        // Format: quality_class => [min, max]
        $prices = [
            'Bangus (Milkfish)' => [
                'First Class'   => [200, 240],
                'Second Class'  => [170, 200],
                'Third Class'   => [140, 170],
            ],
            'Tilapia' => [
                'First Class'   => [140, 170],
                'Second Class'  => [120, 145],
                'Third Class'   => [100, 125],
            ],
            'Maya-maya (Red Snapper)' => [
                'First Class'   => [360, 440],
                'Second Class'  => [290, 350],
                'Special Class' => [460, 560],
            ],
            'Galunggong (Round Scad)' => [
                'First Class'   => [210, 270],
                'Second Class'  => [170, 215],
                'Third Class'   => [140, 175],
            ],
            'Alumahan (Indian Mackerel)' => [
                'First Class'   => [260, 310],
                'Second Class'  => [210, 260],
                'Third Class'   => [170, 210],
            ],
            'Tanigue (Spanish Mackerel)' => [
                'First Class'   => [390, 480],
                'Second Class'  => [320, 390],
                'Special Class' => [500, 650],
            ],
            'Tulingan (Bullet Tuna)' => [
                'First Class'   => [250, 300],
                'Second Class'  => [210, 255],
                'Third Class'   => [175, 215],
            ],
            'Talakitok (Trevally)' => [
                'First Class'   => [290, 360],
                'Second Class'  => [240, 295],
                'Special Class' => [380, 480],
            ],
            'Hipon (Shrimp)' => [
                'First Class'   => [410, 520],
                'Second Class'  => [330, 410],
                'Special Class' => [560, 700],
            ],
            'Pusit (Squid)' => [
                'First Class'   => [260, 320],
                'Second Class'  => [210, 265],
                'Third Class'   => [170, 215],
            ],
            'Alimasag (Blue Crab)' => [
                'First Class'   => [360, 450],
                'Second Class'  => [290, 365],
                'Special Class' => [470, 580],
            ],
            'Lapu-lapu (Grouper)' => [
                'First Class'   => [420, 510],
                'Second Class'  => [340, 425],
                'Special Class' => [560, 700],
            ],
            'Dilis (Anchovies)' => [
                'First Class'   => [210, 270],
                'Second Class'  => [170, 215],
                'Third Class'   => [140, 175],
            ],
            'Espada (Ribbonfish)' => [
                'First Class'   => [190, 240],
                'Second Class'  => [155, 195],
                'Third Class'   => [125, 160],
            ],
            'Hito (Catfish)' => [
                'First Class'   => [195, 240],
                'Second Class'  => [160, 200],
                'Third Class'   => [130, 165],
            ],
        ];

        // ── Per-vendor fish specialisations (12 vendors = indices 0-11) ────────
        // Each vendor brings 5 fish items; daily rotation selects 3-5 of them.
        // Matches stall assignments: FS-46 (idx 0) → FS-57 (idx 11)
        $vendorFish = [

            // 0 — Sally Tatualia FS-46 — premium coastal fish
            [
                ['fish' => 'Maya-maya (Red Snapper)',    'quality' => 'First Class',   'stock' => [12, 28], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Maya-maya (Red Snapper)',    'quality' => 'Second Class',  'stock' => [10, 22], 'released_pct' => [0.60, 0.90]],
                ['fish' => 'Hipon (Shrimp)',             'quality' => 'Special Class', 'stock' => [5,  14], 'released_pct' => [0.55, 0.85]],
                ['fish' => 'Bangus (Milkfish)',          'quality' => 'Second Class',  'stock' => [20, 40], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Lapu-lapu (Grouper)',        'quality' => 'First Class',   'stock' => [8,  18], 'released_pct' => [0.60, 0.90]],
            ],

            // 1 — Folcar Mancams FS-47 — pelagic fish specialist
            [
                ['fish' => 'Tulingan (Bullet Tuna)',     'quality' => 'First Class',   'stock' => [22, 50], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Galunggong (Round Scad)',    'quality' => 'First Class',   'stock' => [25, 55], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Alumahan (Indian Mackerel)', 'quality' => 'First Class',   'stock' => [18, 40], 'released_pct' => [0.70, 0.95]],
                ['fish' => 'Galunggong (Round Scad)',    'quality' => 'Second Class',  'stock' => [18, 45], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Espada (Ribbonfish)',        'quality' => 'First Class',   'stock' => [15, 35], 'released_pct' => [0.65, 0.90]],
            ],

            // 2 — Arnel Sarmiento FS-48 — bangus & freshwater specialist
            [
                ['fish' => 'Bangus (Milkfish)',          'quality' => 'First Class',   'stock' => [30, 65], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Bangus (Milkfish)',          'quality' => 'Second Class',  'stock' => [20, 50], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Bangus (Milkfish)',          'quality' => 'Third Class',   'stock' => [15, 35], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Tilapia',                   'quality' => 'First Class',   'stock' => [20, 45], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Hito (Catfish)',             'quality' => 'First Class',   'stock' => [10, 25], 'released_pct' => [0.65, 0.90]],
            ],

            // 3 — Meamie Torres FS-49 — affordable everyday fish
            [
                ['fish' => 'Galunggong (Round Scad)',    'quality' => 'Second Class',  'stock' => [20, 50], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Galunggong (Round Scad)',    'quality' => 'Third Class',   'stock' => [15, 40], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Dilis (Anchovies)',          'quality' => 'First Class',   'stock' => [12, 28], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Alumahan (Indian Mackerel)', 'quality' => 'Second Class',  'stock' => [15, 35], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Tilapia',                   'quality' => 'Second Class',  'stock' => [18, 42], 'released_pct' => [0.75, 1.00]],
            ],

            // 4 — Elena Ibatan FS-50 — shellfish & seafood
            [
                ['fish' => 'Hipon (Shrimp)',             'quality' => 'First Class',   'stock' => [10, 25], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Alimasag (Blue Crab)',       'quality' => 'First Class',   'stock' => [8,  20], 'released_pct' => [0.55, 0.85]],
                ['fish' => 'Pusit (Squid)',              'quality' => 'First Class',   'stock' => [14, 32], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Alimasag (Blue Crab)',       'quality' => 'Special Class', 'stock' => [4,  12], 'released_pct' => [0.50, 0.80]],
                ['fish' => 'Dilis (Anchovies)',          'quality' => 'Second Class',  'stock' => [10, 25], 'released_pct' => [0.65, 0.95]],
            ],

            // 5 — Rubina Banti FS-51 — premium species (lapu-lapu, tanigue)
            [
                ['fish' => 'Lapu-lapu (Grouper)',        'quality' => 'First Class',   'stock' => [8,  20], 'released_pct' => [0.55, 0.85]],
                ['fish' => 'Lapu-lapu (Grouper)',        'quality' => 'Special Class', 'stock' => [4,  10], 'released_pct' => [0.50, 0.80]],
                ['fish' => 'Tanigue (Spanish Mackerel)', 'quality' => 'First Class',   'stock' => [10, 25], 'released_pct' => [0.60, 0.90]],
                ['fish' => 'Talakitok (Trevally)',       'quality' => 'First Class',   'stock' => [10, 22], 'released_pct' => [0.60, 0.90]],
                ['fish' => 'Hipon (Shrimp)',             'quality' => 'Special Class', 'stock' => [5,  14], 'released_pct' => [0.55, 0.85]],
            ],

            // 6 — Cary Glenn Ercola FS-52 — mixed common fish (high volume)
            [
                ['fish' => 'Bangus (Milkfish)',          'quality' => 'First Class',   'stock' => [25, 55], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Tilapia',                   'quality' => 'First Class',   'stock' => [18, 45], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Galunggong (Round Scad)',    'quality' => 'First Class',   'stock' => [22, 50], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Tulingan (Bullet Tuna)',     'quality' => 'Second Class',  'stock' => [16, 38], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Alumahan (Indian Mackerel)', 'quality' => 'Third Class',   'stock' => [14, 32], 'released_pct' => [0.65, 0.95]],
            ],

            // 7 — Gemma Sarmiento FS-53 — trevally & mackerel [TODAY: MIXED]
            [
                ['fish' => 'Talakitok (Trevally)',       'quality' => 'First Class',   'stock' => [12, 28], 'released_pct' => [0.65, 0.90]],
                ['fish' => 'Tanigue (Spanish Mackerel)', 'quality' => 'Second Class',  'stock' => [10, 24], 'released_pct' => [0.60, 0.90]],
                ['fish' => 'Tulingan (Bullet Tuna)',     'quality' => 'First Class',   'stock' => [18, 42], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Pusit (Squid)',              'quality' => 'Second Class',  'stock' => [12, 28], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Espada (Ribbonfish)',        'quality' => 'Second Class',  'stock' => [14, 30], 'released_pct' => [0.65, 0.90]],
            ],

            // 8 — Sherly Calibin FS-54 — premium & mid-range [TODAY: MIXED]
            [
                ['fish' => 'Maya-maya (Red Snapper)',    'quality' => 'First Class',   'stock' => [10, 24], 'released_pct' => [0.60, 0.90]],
                ['fish' => 'Lapu-lapu (Grouper)',        'quality' => 'Second Class',  'stock' => [8,  20], 'released_pct' => [0.55, 0.85]],
                ['fish' => 'Hipon (Shrimp)',             'quality' => 'First Class',   'stock' => [10, 22], 'released_pct' => [0.60, 0.90]],
                ['fish' => 'Alimasag (Blue Crab)',       'quality' => 'Second Class',  'stock' => [7,  18], 'released_pct' => [0.55, 0.85]],
                ['fish' => 'Tanigue (Spanish Mackerel)', 'quality' => 'Special Class', 'stock' => [4,  10], 'released_pct' => [0.50, 0.80]],
            ],

            // 9 — Nida Fernandez FS-55 — small fish & anchovies [TODAY: ALL PENDING]
            [
                ['fish' => 'Dilis (Anchovies)',          'quality' => 'First Class',   'stock' => [12, 28], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Dilis (Anchovies)',          'quality' => 'Second Class',  'stock' => [10, 24], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Espada (Ribbonfish)',        'quality' => 'First Class',   'stock' => [14, 32], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Alumahan (Indian Mackerel)', 'quality' => 'Second Class',  'stock' => [14, 30], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Galunggong (Round Scad)',    'quality' => 'Third Class',   'stock' => [16, 36], 'released_pct' => [0.70, 1.00]],
            ],

            // 10 — Agnes Baba FS-56 — squid & crab [TODAY: ALL PENDING]
            [
                ['fish' => 'Pusit (Squid)',              'quality' => 'First Class',   'stock' => [14, 32], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Pusit (Squid)',              'quality' => 'Third Class',   'stock' => [10, 25], 'released_pct' => [0.65, 0.95]],
                ['fish' => 'Alimasag (Blue Crab)',       'quality' => 'First Class',   'stock' => [7,  18], 'released_pct' => [0.55, 0.85]],
                ['fish' => 'Hipon (Shrimp)',             'quality' => 'Second Class',  'stock' => [10, 22], 'released_pct' => [0.60, 0.90]],
                ['fish' => 'Dilis (Anchovies)',          'quality' => 'Third Class',   'stock' => [10, 22], 'released_pct' => [0.70, 1.00]],
            ],

            // 11 — Sarah Jane Tafe FS-57 — bangus & tilapia [TODAY: ALL PENDING]
            [
                ['fish' => 'Bangus (Milkfish)',          'quality' => 'Second Class',  'stock' => [22, 48], 'released_pct' => [0.70, 1.00]],
                ['fish' => 'Tilapia',                   'quality' => 'Second Class',  'stock' => [18, 40], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Tilapia',                   'quality' => 'Third Class',   'stock' => [14, 35], 'released_pct' => [0.75, 1.00]],
                ['fish' => 'Hito (Catfish)',             'quality' => 'First Class',   'stock' => [10, 24], 'released_pct' => [0.65, 0.90]],
                ['fish' => 'Hito (Catfish)',             'quality' => 'Second Class',  'stock' => [8,  20], 'released_pct' => [0.65, 0.90]],
            ],
        ];

        // ── Date range: May 1, 2026 → TODAY (dynamic — always fresh on migrate) ─
        $startDate = Carbon::create(2026, 5, 1);
        $endDate   = Carbon::today(); // ← key: always seeds up to current date

        $inserted  = 0;
        $confirmed = 0;
        $pending   = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {

            $dateStr   = $date->toDateString();
            $dayOfYear = $date->dayOfYear;
            $isToday   = $date->isToday();

            // Past dates are locked; today is editable until day ends
            $isLocked = $date->lt(Carbon::today());

            // Oil price shock factor: after May 15, prices creep up ~0.3%/day
            $oilFactor = 1.0;
            if ($date->gte(Carbon::create(2026, 5, 15))) {
                $daysAfterShock = $date->diffInDays(Carbon::create(2026, 5, 15));
                $oilFactor = 1.0 + ($daysAfterShock * 0.003); // max ~+9% by today
            }

            foreach ($vendors as $vIndex => $vendor) {

                // ~88% attendance per day using deterministic pseudo-random
                $attendance = (($dayOfYear * 7 + $vIndex * 13) % 100);
                if ($attendance < 12) continue; // 12% skip rate

                $fishList = $vendorFish[$vIndex] ?? [];
                if (empty($fishList)) continue;

                // Each vendor brings 3-5 items (rotates daily)
                $itemsToday = min(count($fishList), 3 + (($dayOfYear + $vIndex) % 3));
                $offset     = ($dayOfYear + $vIndex * 3) % count($fishList);
                $selected   = [];
                for ($i = 0; $i < $itemsToday; $i++) {
                    $selected[] = $fishList[($offset + $i) % count($fishList)];
                }

                $itemIdx = 0; // used for alternating confirmed/pending logic today

                foreach ($selected as $item) {

                    $fishName   = $item['fish'];
                    $qualClass  = $item['quality'];
                    $fishTypeId = $ft[$fishName] ?? null;

                    if (!$fishTypeId) continue;
                    if (!isset($prices[$fishName][$qualClass])) continue;

                    [$priceMin, $priceMax] = $prices[$fishName][$qualClass];

                    // Deterministic daily price variation (avoids randomness between seeds)
                    $priceShift = (($dayOfYear + $vIndex + strlen($fishName)) % 20) - 10;
                    $rawPrice   = $priceMin + (($priceMax - $priceMin) * (($dayOfYear * 3 + $vIndex * 7) % 100) / 100);
                    $rawPrice  += $priceShift;
                    $finalPrice = round($rawPrice * $oilFactor, 2);
                    $finalPrice = max($priceMin * 0.92, min($priceMax * 1.15, $finalPrice));

                    // Stock quantity
                    [$stockMin, $stockMax] = $item['stock'];
                    $stockSeed  = ($dayOfYear * 11 + $vIndex * 5 + strlen($fishName)) % 100;
                    $stockKg    = round($stockMin + (($stockMax - $stockMin) * $stockSeed / 100), 1);

                    // Released kg (portion put on display)
                    [$relMin, $relMax] = $item['released_pct'];
                    $relPct     = $relMin + (($relMax - $relMin) * (($dayOfYear + $vIndex * 3) % 100) / 100);
                    $releasedKg = max(0.5, round($stockKg * $relPct, 1));

                    // Sold kg (70-95% of released)
                    $soldPct = 0.70 + ((($dayOfYear * 3 + $vIndex * 7 + strlen($fishName)) % 26) / 100);
                    $soldKg  = min(round($releasedKg * $soldPct, 1), $releasedKg);

                    // ── Status logic ───────────────────────────────────────────
                    // Historical & gap dates → always confirmed
                    // Today → depends on vendor index:
                    //   vIndex 0-6  : all CONFIRMED  (staff processed morning batch)
                    //   vIndex 7-8  : ALTERNATING confirmed/pending per item
                    //   vIndex 9-11 : all PENDING     (awaiting staff review)
                    if (!$isToday) {
                        $entryStatus     = 'confirmed';
                        $confirmedById   = $staff->id;
                        $confirmedAtTime = Carbon::parse($dateStr)->setTime(8, 30, 0);
                        $soldKgFinal     = $soldKg; // historical has sales data
                    } elseif ($vIndex <= 6) {
                        // Morning batch already confirmed
                        $entryStatus     = 'confirmed';
                        $confirmedById   = $staff->id;
                        $confirmedAtTime = Carbon::today()->setTime(8, 30, 0);
                        $soldKgFinal     = round($releasedKg * 0.35, 1); // partial sales (morning only)
                    } elseif ($vIndex <= 8) {
                        // Mixed: even item index = confirmed, odd = pending
                        $isConfirmedItem = ($itemIdx % 2 === 0);
                        $entryStatus     = $isConfirmedItem ? 'confirmed' : 'pending';
                        $confirmedById   = $isConfirmedItem ? $staff->id : null;
                        $confirmedAtTime = $isConfirmedItem ? Carbon::today()->setTime(9, 0, 0) : null;
                        $soldKgFinal     = $isConfirmedItem ? round($releasedKg * 0.25, 1) : 0;
                    } else {
                        // Pending — submitted but not yet reviewed by staff
                        $entryStatus     = 'pending';
                        $confirmedById   = null;
                        $confirmedAtTime = null;
                        $soldKgFinal     = 0; // no sales until confirmed
                    }

                    VendorInventory::create([
                        'vendor_id'    => $vendor->id,
                        'fish_type_id' => $fishTypeId,
                        'quality_class'=> $qualClass,
                        'price_per_kg' => $finalPrice,
                        'stock_kg'     => $stockKg,
                        'released_kg'  => $isToday ? $releasedKg : $releasedKg,
                        'sold_kg'      => $soldKgFinal,
                        'status'       => $entryStatus,
                        'confirmed_by' => $confirmedById,
                        'confirmed_at' => $confirmedAtTime,
                        'entry_date'   => $dateStr,
                        'is_locked'    => $isLocked,
                    ]);

                    ($entryStatus === 'confirmed') ? $confirmed++ : $pending++;
                    $inserted++;
                    $itemIdx++;
                }
            }
        }

        $days = $startDate->diffInDays(Carbon::today()) + 1;
        $this->command->info("✅ VendorInventorySeeder: {$inserted} entries over {$days} days.");
        $this->command->info("   ✔ Confirmed : {$confirmed}");
        $this->command->info("   ⏳ Pending  : {$pending}  ← visible in staff confirmation queue");
        $this->command->info("   📋 Today's confirmed entries are live on /prices.");
    }
}