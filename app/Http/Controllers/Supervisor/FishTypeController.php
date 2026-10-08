<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * FishTypeController
 *
 * Allows the Supervisor to add new fish types and edit their name, quality
 * class. There is no activate/deactivate or delete action: a fish
 * type that exists is in use, and the only lifecycle rule is that editing one
 * keeps its price brackets and inventory in step with the new class.
 */
class FishTypeController extends Controller
{
    /**
     * Title-cases a fish type name and squeezes internal whitespace, so that
     * cosmetic variations cannot slip past the `unique` check and become two
     * near-identical rows. Str::title() also capitalises after "(", which
     * ucwords() does not.
     */
    private function normaliseName(?string $name): string
    {
        return Str::title(preg_replace('/\s+/u', ' ', trim((string) $name)));
    }

    // ─── List all fish types ──────────────────────────────────────
    public function index()
    {
        $fishTypes = FishType::withCount('priceGuides')
            ->orderByRaw($this->classOrderSql())
            ->orderBy('name')
            ->get();

        return view('supervisor.fish-types', compact('fishTypes'));
    }

    /**
     * Sort rows by quality class cheapest-first, so the list reads the same way
     * the price guide does: Fourth Class (the cheapest tier) at the top through
     * to Special Class. Ordering by the name alone used to scatter the classes
     * alphabetically, which made the catalogue impossible to scan.
     *
     * CASE falls back to the enum position for any class outside the four
     * cheapest-first tiers, so an unexpected value never drops a row.
     */
    private function classOrderSql(): string
    {
        return 'CASE quality_class '
            .implode(' ', array_map(
                fn ($i, $class) => "WHEN '{$class}' THEN {$i}",
                array_keys($cheapestFirst = ['Fourth Class', 'Third Class', 'Second Class', 'First Class', 'Special Class']),
                $cheapestFirst
            ))
            .' ELSE 99 END';
    }

    // ─── Store a new fish type ────────────────────────────────────
    public function store(Request $request)
    {
        // Collapse runs of whitespace before validating. The name is title-cased
        // on the way in, so without this "Bangkulis  (White Fin)" and
        // "Bangkulis (White Fin)" both pass `unique` and land as two rows.
        $request->merge(['name' => $this->normaliseName($request->name)]);

        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:fish_types,name'],
            'quality_class' => ['required', 'in:'.implode(',', FishType::QUALITY_CLASSES)],
        ], [
            'name.unique' => 'A fish type with that name already exists.',
            'quality_class.required' => 'Please select a quality class.',
        ]);

        FishType::create([
            'name' => $this->normaliseName($request->name),
            'quality_class' => $request->quality_class,
            'is_active' => true,
        ]);

        return redirect()->route('supervisor.fish-types.index')
            ->with('success', 'Fish type "'.$this->normaliseName($request->name).'" added successfully.');
    }

    // ─── Update a fish type name ──────────────────────────────────
    public function update(Request $request, FishType $fishType)
    {
        $request->merge(['name' => $this->normaliseName($request->name)]);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', 'unique:fish_types,name,'.$fishType->id],
            'quality_class' => ['required', 'in:'.implode(',', FishType::QUALITY_CLASSES)],
        ], [
            'name.unique' => 'A fish type with that name already exists.',
            'quality_class.required' => 'Please select a quality class.',
        ]);

        // Hand-rolled so the redirect can name the modal to reopen: a fish type
        // is edited inside a per-row modal, and a plain validation redirect
        // would drop the supervisor on the list with the message hidden.
        if ($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator)
                ->with('open_edit_modal', $fishType->id);
        }

        $previousClass = $fishType->quality_class;

        $fishType->update([
            'name' => $this->normaliseName($request->name),
            'quality_class' => $request->quality_class,
        ]);

        // A fish type owns its price brackets and its quality class is stored
        // alongside them. Leaving them behind would orphan the brackets: the
        // vendor price check and the staff Cheap/Moderate/Expensive label are
        // both keyed on "<fish_type_id>_<quality class>", so a stale class
        // makes every guide invisible to the features that consume it.
        if ($previousClass !== $request->quality_class) {
            PriceGuide::where('fish_type_id', $fishType->id)
                ->update(['quality_class' => $request->quality_class]);

            VendorInventory::where('fish_type_id', $fishType->id)
                ->update(['quality_class' => $request->quality_class]);
        }

        return redirect()->route('supervisor.fish-types.index')
            ->with('success', 'Fish type updated successfully.');
    }
}
