<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\PriceGuide;
use Illuminate\Http\Request;

class PriceGuideController extends Controller
{
    private array $qualityClasses = [
        'First Class', 'Second Class', 'Third Class', 'Fourth Class', 'Special Class',
    ];

    // ─── List all fish types with their price brackets ───────────
    public function index()
    {
        $fishTypes = FishType::where('is_active', true)
            ->with(['priceGuides' => fn($q) => $q->where('is_active', true)->orderBy('quality_class')])
            ->orderBy('name')
            ->get();

        $totalGuides     = PriceGuide::where('is_active', true)->count();
        $totalConfigured = $fishTypes->filter(fn($f) => $f->priceGuides->isNotEmpty())->count();
        $totalMissing    = $fishTypes->filter(fn($f) => $f->priceGuides->isEmpty())->count();
        $qualityClasses  = $this->qualityClasses;

        return view('supervisor.price-guides', compact(
            'fishTypes', 'totalGuides', 'totalConfigured', 'totalMissing', 'qualityClasses'
        ));
    }

    // ─── Store a new price bracket ────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'fish_type_id'   => ['required', 'exists:fish_types,id'],
            'quality_class'  => ['required', 'in:' . implode(',', $this->qualityClasses)],
            'cheap_max'      => ['required', 'numeric', 'min:0.01'],
            'moderate_max'   => ['required', 'numeric', 'gt:cheap_max'],
            'effective_date' => ['required', 'date'],
        ], [
            'moderate_max.gt' => 'Moderate max must be greater than the cheap max.',
        ]);

        // Block duplicates: same fish + same quality class
        $alreadyExists = PriceGuide::where('fish_type_id', $request->fish_type_id)
            ->where('quality_class', $request->quality_class)
            ->where('is_active', true)
            ->exists();

        if ($alreadyExists) {
            return back()
                ->withInput()
                ->withErrors(['quality_class' => 'A bracket for this fish type and quality class already exists. Edit it instead.'])
                ->with('open_add_modal', $request->fish_type_id);
        }

        PriceGuide::create([
            'fish_type_id'   => $request->fish_type_id,
            'quality_class'  => $request->quality_class,
            'cheap_max'      => $request->cheap_max,
            'moderate_max'   => $request->moderate_max,
            'effective_date' => $request->effective_date,
            'is_active'      => true,
        ]);

        return redirect()->route('supervisor.price-guides.index')
            ->with('success', 'Price bracket added successfully.');
    }

    // ─── Update an existing price bracket ────────────────────────
    public function update(Request $request, PriceGuide $priceGuide)
    {
        $request->validate([
            'cheap_max'      => ['required', 'numeric', 'min:0.01'],
            'moderate_max'   => ['required', 'numeric', 'gt:cheap_max'],
            'effective_date' => ['required', 'date'],
        ], [
            'moderate_max.gt' => 'Moderate max must be greater than the cheap max.',
        ]);

        $priceGuide->update([
            'cheap_max'      => $request->cheap_max,
            'moderate_max'   => $request->moderate_max,
            'effective_date' => $request->effective_date,
        ]);

        return redirect()->route('supervisor.price-guides.index')
            ->with('success', 'Price bracket updated successfully.');
    }

    // ─── Delete a price bracket ───────────────────────────────────
    public function destroy(PriceGuide $priceGuide)
    {
        $priceGuide->delete();

        return redirect()->route('supervisor.price-guides.index')
            ->with('success', 'Price bracket removed.');
    }
}