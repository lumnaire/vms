<?php
// ─────────────────────────────────────────────────────────────────
// Staff/PriceGuideController.php
// ─────────────────────────────────────────────────────────────────
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\PriceGuide;

class PriceGuideController extends Controller
{
    public function index()
    {
        $fishTypes = FishType::where('is_active', true)
            ->with(['priceGuides' => fn($q) => $q->where('is_active', true)->orderBy('quality_class')])
            ->orderBy('name')
            ->get();

        $totalGuides  = PriceGuide::where('is_active', true)->count();
        $totalSpecies = $fishTypes->count();

        return view('staff.price-guides', compact('fishTypes', 'totalGuides', 'totalSpecies'));
    }
}