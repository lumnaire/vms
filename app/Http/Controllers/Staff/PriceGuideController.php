<?php
// ─────────────────────────────────────────────────────────────────
// Staff/PriceGuideController.php
// ─────────────────────────────────────────────────────────────────
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use Illuminate\Http\Request;

class PriceGuideController extends Controller
{
    public function index(Request $request)
    {
        $allFishTypes = FishType::where('is_active', true)
            ->with(['priceGuides' => fn($q) => $q->where('is_active', true)->orderBy('quality_class')])
            ->orderBy('name')
            ->get();

        $qualityClasses = FishType::QUALITY_CLASSES;

        // ── Filters: category (quality class) → fish type ────────
        $selectedCategory = $request->input('quality_class');
        if ($selectedCategory && !in_array($selectedCategory, $qualityClasses, true)) {
            $selectedCategory = null;
        }

        $requestedFishType = $request->input('fish_type_id');
        $matchedFishType   = $requestedFishType
            ? $allFishTypes->firstWhere('id', (int) $requestedFishType)
            : null;
        $selectedFishType = $matchedFishType
            && (!$selectedCategory || $matchedFishType->quality_class === $selectedCategory)
                ? $matchedFishType->id
                : null;

        // Fish type dropdown options: narrowed to the selected category
        $categoryFishTypes = $selectedCategory
            ? $allFishTypes->where('quality_class', $selectedCategory)->values()
            : $allFishTypes;

        // Cards: narrowed to the selected category + fish type
        $fishTypes = $selectedFishType
            ? $categoryFishTypes->where('id', (int) $selectedFishType)->values()
            : $categoryFishTypes;

        $totalGuides  = $fishTypes->sum(fn($f) => $f->priceGuides->count());
        $totalSpecies = $fishTypes->count();

        return view('staff.price-guides', compact(
            'fishTypes', 'totalGuides', 'totalSpecies',
            'qualityClasses', 'selectedCategory', 'selectedFishType', 'categoryFishTypes'
        ));
    }
}