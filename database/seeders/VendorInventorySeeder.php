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
 * Populates vendor_inventories with realistic data using the current (new)
 * fish type catalogue:
 *   • Historical  : May 1, 2026 → yesterday  (all CONFIRMED, locked)
 *   • Gap fill    : Any days between the historical cutoff and today (CONFIRMED, locked)
 *   • Today       : Mixed CONFIRMED + PENDING entries
 *       – Vendors 0-6  (FS-46 to FS-52) → all items CONFIRMED (staff processed morning batch)
 *       – Vendors 7-8  (FS-53 to FS-54) → alternating CONFIRMED / PENDING per item
 *       – Vendors 9-11 (FS-55 to FS-57) → all items PENDING (awaiting staff review)
 *
 * Prices are generated per quality class using the price-guide brackets so the
 * classification (Cheap / Moderate / Expensive) stays coherent across modules.
 *
 * Usage:
 *   php artisan migrate:fresh --seed
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

        /** @var \Illuminate\Support\Collection<int, FishType> $fishTypes */
        $fishTypes = FishType::where('is_active', true)
            ->whereNotNull('quality_class')
            ->get()
            ->keyBy('name');

        // ── Price & stock basis (PHP/kg) per quality class ─────────────────
        // Mirrors the PriceGuideSeeder brackets so prices fall into bracket bands.
        $classBasis = [
            'Special Class' => ['cheap_max' => 360, 'moderate_max' => 640, 'stock' => [2,  10]],
            'First Class'   => ['cheap_max' => 240, 'moderate_max' => 460, 'stock' => [8,  22]],
            'Second Class'  => ['cheap_max' => 150, 'moderate_max' => 300, 'stock' => [12, 34]],
            'Third Class'   => ['cheap_max' =>  90, 'moderate_max' => 190, 'stock' => [12, 42]],
            'Fourth Class'  => ['cheap_max' =>  55, 'moderate_max' => 130, 'stock' => [8,  30]],
        ];

        // ── Per-vendor fish specialisations (12 vendors = indices 0-11) ────
        // Each vendor brings 5 fish items; daily rotation selects 3-5 of them.
        // Quality class is derived from the fish type itself.
        $vendorFish = [

            // 0 — Sally Tatualia FS-46 — premium coastal fish
            ['Maya-Maya', 'Lapu-Lapu (baraca)', 'Hipon', 'Tangigue (natural)', 'Banagan (lobster) headless'],

            // 1 — Folcar Mancams FS-47 — pelagic fish specialist
            ['Galunggong (dakula payo)', 'Galunggong (saday payo)', 'Turingan', 'Bisugo', 'Cataway (medium)'],

            // 2 — Arnel Sarmiento FS-48 — bangus & freshwater specialist
            ['Bangus (large)', 'Bangus (medium)', 'Bangus (small)', 'Tilapia', 'Hito'],

            // 3 — Meamie Torres FS-49 — affordable everyday fish
            ['Galunggong (saday payo)', 'Bisugo', 'Sapsap (large)', 'Tilapia', 'Bolinaw (puti)'],

            // 4 — Elena Ibatan FS-50 — shellfish & seafood
            ['Pusit', 'Hipon', 'Sugpo', 'Tahong', 'Kano-os/Squid'],

            // 5 — Rubina Banti FS-51 — premium species (lapu-lapu, tangigue)
            ['Lapu-Lapu (baraca)', 'Maya-Maya', 'Tangigue (natural)', 'Banagan (lobster) headless', 'Hipon'],

            // 6 — Cary Glenn Ercola FS-52 — mixed common fish (high volume)
            ['Bangus (medium)', 'Tilapia', 'Galunggong (saday payo)', 'Hito', 'Duwal-lapad'],

            // 7 — Gemma Sarmiento FS-53 — trevally & mackerel [TODAY: MIXED]
            ['Calapion/Talakitok (malagimago)', 'Maya-Maya', 'Tangigue (batang)', 'Pusit', 'Turingan'],

            // 8 — Sherly Calibin FS-54 — premium & mid-range [TODAY: MIXED]
            ['Maya-Maya', 'Lapu-Lapu (baraca)', 'Hipon', 'Atoloy (local)', 'Salay-salay'],

            // 9 — Nida Fernandez FS-55 — small fish & anchovies [TODAY: ALL PENDING]
            ['Bolinaw (puti)', 'Bolinaw (itom)', 'Manamsi', 'Sapsap (small/medium)', 'Lambungayaw'],

            // 10 — Agnes Baba FS-56 — squid & octopus [TODAY: ALL PENDING]
            ['Pusit', 'Kano-os/Squid', 'Cugita', 'Bungkang/Aringawon', 'Moting-Tabagwang'],

            // 11 — Sarah Jane Tafe FS-57 — bangus & tilapia [TODAY: ALL PENDING]
            ['Bangus (medium)', 'Bangus (small)', 'Tilapia', 'Hito', 'Sapsap (small/medium)'],
        ];

        // ── Date range: May 1, 2026 → TODAY (dynamic — always fresh) ───────
        $startDate = Carbon::create(2026, 5, 1);
        $endDate   = Carbon::today();

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

                foreach ($selected as $fishName) {

                    $fishType = $fishTypes[$fishName] ?? null;
                    if (!$fishType) continue;
                    if (!isset($classBasis[$fishType->quality_class])) continue;

                    $qualClass = $fishType->quality_class;
                    $basis     = $classBasis[$qualClass];
                    [$priceMin, $priceMax] = [$basis['cheap_max'], $basis['moderate_max']];
                    [$stockMin, $stockMax] = $basis['stock'];

                    // Deterministic daily price variation (avoids randomness between seeds)
                    $priceShift = (($dayOfYear + $vIndex + strlen($fishName)) % 20) - 10;
                    $rawPrice   = $priceMin + (($priceMax - $priceMin) * (($dayOfYear * 3 + $vIndex * 7) % 100) / 100);
                    $rawPrice  += $priceShift;
                    $finalPrice = round($rawPrice * $oilFactor, 2);
                    $finalPrice = max($priceMin * 0.85, min($priceMax * 1.10, $finalPrice));

                    // Stock quantity
                    $stockSeed = ($dayOfYear * 11 + $vIndex * 5 + strlen($fishName)) % 100;
                    $stockKg   = round($stockMin + (($stockMax - $stockMin) * $stockSeed / 100), 1);

                    // Released kg (portion put on display)
                    $relPct     = 0.6 + (($dayOfYear + $vIndex * 3) % 41) / 100; // 60-100%
                    $releasedKg = max(0.5, round($stockKg * $relPct, 1));

                    // Sold kg (70-95% of released)
                    $soldPct = 0.70 + ((($dayOfYear * 3 + $vIndex * 7 + strlen($fishName)) % 26) / 100);
                    $soldKg  = min(round($releasedKg * $soldPct, 1), $releasedKg);

                    // ── Status logic ───────────────────────────────────────────
                    // Historical dates → always confirmed
                    // Today     → depends on vendor index (0-6 confirmed, 7-8 mixed, 9-11 pending)
                    if (!$isToday) {
                        $entryStatus     = 'confirmed';
                        $confirmedById   = $staff->id;
                        $confirmedAtTime = Carbon::parse($dateStr)->setTime(8, 30, 0);
                        $soldKgFinal     = $soldKg;
                    } elseif ($vIndex <= 6) {
                        $entryStatus     = 'confirmed';
                        $confirmedById   = $staff->id;
                        $confirmedAtTime = Carbon::today()->setTime(8, 30, 0);
                        $soldKgFinal     = round($releasedKg * 0.35, 1); // partial sales (morning only)
                    } elseif ($vIndex <= 8) {
                        $isConfirmedItem = ($itemIdx % 2 === 0);
                        $entryStatus     = $isConfirmedItem ? 'confirmed' : 'pending';
                        $confirmedById   = $isConfirmedItem ? $staff->id : null;
                        $confirmedAtTime = $isConfirmedItem ? Carbon::today()->setTime(9, 0, 0) : null;
                        $soldKgFinal     = $isConfirmedItem ? round($releasedKg * 0.25, 1) : 0;
                    } else {
                        $entryStatus     = 'pending';
                        $confirmedById   = null;
                        $confirmedAtTime = null;
                        $soldKgFinal     = 0;
                    }

                    VendorInventory::create([
                        'vendor_id'    => $vendor->id,
                        'fish_type_id' => $fishType->id,
                        'quality_class'=> $qualClass,
                        'price_per_kg' => $finalPrice,
                        'stock_kg'     => $stockKg,
                        'released_kg'  => $releasedKg,
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