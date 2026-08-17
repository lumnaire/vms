<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\FishType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * FishTypeController
 *
 * Allows the Supervisor to add new fish types, edit their names,
 * and toggle their active/inactive status.
 * Deactivated fish types are hidden from all dropdowns and the public board.
 */
class FishTypeController extends Controller
{
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
        $request->validate([
            'name'  => ['required', 'string', 'max:100', 'unique:fish_types,name'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.unique' => 'A fish type with that name already exists.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('fish-types', 'public');
        }

        FishType::create([
            'name'       => ucwords(strtolower(trim($request->name))),
            'is_active'  => true,
            'image_path' => $imagePath,
        ]);

        return redirect()->route('supervisor.fish-types.index')
            ->with('success', 'Fish type "' . $request->name . '" added successfully.');
    }

    // ─── Update a fish type name ──────────────────────────────────
    public function update(Request $request, FishType $fishType)
    {
        $request->validate([
            'name'         => ['required', 'string', 'max:100', 'unique:fish_types,name,' . $fishType->id],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'A fish type with that name already exists.',
        ]);

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

        $fishType->update([
            'name'       => ucwords(strtolower(trim($request->name))),
            'image_path' => $imagePath,
        ]);

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

        $name = $fishType->name;

        if ($fishType->image_path) {
            Storage::disk('public')->delete($fishType->image_path);
        }

        $fishType->delete();

        return redirect()->route('supervisor.fish-types.index')
            ->with('success', "Fish type \"{$name}\" has been permanently deleted.");
    }
}