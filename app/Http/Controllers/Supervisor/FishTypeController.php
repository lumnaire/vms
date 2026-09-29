<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use App\Models\PriceGuide;
use App\Models\VendorInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * FishTypeController
 *
 * Allows the Supervisor to add new fish types, edit their names,
 * and toggle their active/inactive status.
 * Deactivated fish types are hidden from all dropdowns and the public board.
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
        $fishTypes = FishType::orderBy('name')->get();

        $totalActive   = $fishTypes->where('is_active', true)->count();
        $totalInactive = $fishTypes->where('is_active', false)->count();

        return view('supervisor.fish-types', compact('fishTypes', 'totalActive', 'totalInactive'));
    }

    // ─── Store a new fish type ────────────────────────────────────
    public function store(Request $request)
    {
        // Collapse runs of whitespace before validating. The name is title-cased
        // on the way in, so without this "Bangkulis  (White Fin)" and
        // "Bangkulis (White Fin)" both pass `unique` and land as two rows.
        $request->merge(['name' => $this->normaliseName($request->name)]);

        $request->validate([
            'name'          => ['required', 'string', 'max:100', 'unique:fish_types,name'],
            'quality_class' => ['required', 'in:' . implode(',', FishType::QUALITY_CLASSES)],
            'image'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.unique'          => 'A fish type with that name already exists.',
            'quality_class.required' => 'Please select a quality class.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('fish-types', 'public');
        }

        FishType::create([
            'name'          => $this->normaliseName($request->name),
            'quality_class' => $request->quality_class,
            'is_active'     => true,
            'image_path'    => $imagePath,
        ]);

        return redirect()->route('supervisor.fish-types.index')
            ->with('success', 'Fish type "' . $this->normaliseName($request->name) . '" added successfully.');
    }

    // ─── Update a fish type name ──────────────────────────────────
    public function update(Request $request, FishType $fishType)
    {
        $request->merge(['name' => $this->normaliseName($request->name)]);

        $validator = Validator::make($request->all(), [
            'name'          => ['required', 'string', 'max:100', 'unique:fish_types,name,' . $fishType->id],
            'quality_class' => ['required', 'in:' . implode(',', FishType::QUALITY_CLASSES)],
            'image'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image'  => ['nullable', 'boolean'],
        ], [
            'name.unique'          => 'A fish type with that name already exists.',
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

        $imagePath = $fishType->image_path;

        if ($request->boolean('remove_image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = null;
        }

        if ($request->hasFile('image')) {
            if ($fishType->image_path) {
                Storage::disk('public')->delete($fishType->image_path);
            }
            $imagePath = $request->file('image')->store('fish-types', 'public');
        }

        $previousClass = $fishType->quality_class;

        $fishType->update([
            'name'          => $this->normaliseName($request->name),
            'quality_class' => $request->quality_class,
            'image_path'    => $imagePath,
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

    // ─── Toggle active / inactive ─────────────────────────────────
    public function toggleStatus(FishType $fishType)
    {
        $fishType->update(['is_active' => !$fishType->is_active]);

        $label = $fishType->is_active ? 'activated' : 'deactivated';

        return redirect()->route('supervisor.fish-types.index')
            ->with('success', "Fish type \"{$fishType->name}\" has been {$label}.");
    }

    // ─── Permanently delete (inactive only) ──────────────────────
    public function destroy(FishType $fishType)
    {
        if ($fishType->is_active) {
            return redirect()->route('supervisor.fish-types.index')
                ->with('error', 'Only inactive fish types can be deleted.');
        }

        // vendor_inventories.fish_type_id is restrictOnDelete, so a fish type
        // that has been sold cannot be removed without failing the delete.
        if ($fishType->vendorInventories()->exists()) {
            return redirect()->route('supervisor.fish-types.index')
                ->with('error', "\"{$fishType->name}\" has recorded inventory and cannot be deleted. Deactivate it instead to keep its history.");
        }

        $name = $fishType->name;

        if ($fishType->image_path) {
            Storage::disk('public')->delete($fishType->image_path);
        }

        $fishType->delete();

        return redirect()->route('supervisor.fish-types.index')
            ->with('success', "Fish type \"{$name}\" has been permanently deleted.");
    }
}