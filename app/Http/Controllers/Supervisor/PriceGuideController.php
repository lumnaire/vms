<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FishType;
use App\Models\PriceGuide;
use App\Services\MarketAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The official tariff (taripa): one standard price per kilo for each fish, typed
 * in by the supervisor from the municipal tariff list.
 *
 * The page is one table, laid out like the paper list (local name, price per
 * kilo, English name), so the whole list can be entered and saved at once. A
 * tariff is never overwritten: each change adds a version and the old one stays
 * as history. Vendors may still price above it; the difference is their markup.
 *
 * The fish list itself is managed here too, so a fish missing from a class can
 * be added on the spot (with its tariff), renamed or moved to another class,
 * and removed. Removing a fish that has batches only hides it, because those
 * batches feed reports and forecasts; it can be restored.
 */
class PriceGuideController extends Controller
{
    public function index(Request $request, MarketAnalytics $analytics)
    {
        return view('supervisor.price-guides', $this->tablePayload($request, $analytics));
    }

    /**
     * Shared with the staff tariff page: fish types (filtered), their tariff and
     * history, and how the board compares right now.
     */
    public static function tablePayload(Request $request, MarketAnalytics $analytics): array
    {
        $qualityClasses = FishType::QUALITY_CLASSES;

        $allFishTypes = FishType::where('is_active', true)
            ->with(['currentTariff', 'tariffHistory.setBy'])
            ->withCount('vendorInventories')
            ->orderBy('name')
            ->get();

        $selectedCategory = $request->input('quality_class');
        if ($selectedCategory && ! in_array($selectedCategory, $qualityClasses, true)) {
            $selectedCategory = null;
        }

        $matchedFishType = $request->filled('fish_type_id')
            ? $allFishTypes->firstWhere('id', (int) $request->input('fish_type_id'))
            : null;
        $selectedFishType = $matchedFishType
            && (! $selectedCategory || $matchedFishType->quality_class === $selectedCategory)
                ? $matchedFishType->id
                : null;

        $categoryFishTypes = $selectedCategory
            ? $allFishTypes->where('quality_class', $selectedCategory)->values()
            : $allFishTypes;

        $fishTypes = $selectedFishType
            ? $categoryFishTypes->where('id', (int) $selectedFishType)->values()
            : $categoryFishTypes;

        // Grouped by class in tariff-list order (Special → Fourth is how the
        // municipal list reads: most expensive first).
        $groups = collect(['Special Class', 'First Class', 'Second Class', 'Third Class', 'Fourth Class'])
            ->mapWithKeys(fn ($c) => [$c => $fishTypes->where('quality_class', $c)->values()])
            ->filter(fn ($g) => $g->isNotEmpty());

        $board = $analytics->boardByFish();

        $stats = [
            'set' => $allFishTypes->filter(fn ($f) => $f->currentTariff)->count(),
            'missing' => $allFishTypes->filter(fn ($f) => ! $f->currentTariff)->count(),
            'changed_month' => PriceGuide::tariffHistory()->where('created_at', '>=', today()->startOfMonth())->count(),
            'above_now' => $board->sum(fn ($b) => $b['stalls']->filter(fn ($s) => ($s['comparison']['state'] ?? null) === 'above')->count()),
        ];

        // Fish taken off the list that still have history, for restoring.
        $removedFishTypes = FishType::where('is_active', false)
            ->withCount('vendorInventories')
            ->orderBy('name')
            ->get();

        return compact(
            'allFishTypes', 'fishTypes', 'groups', 'board', 'stats', 'removedFishTypes',
            'qualityClasses', 'selectedCategory', 'selectedFishType', 'categoryFishTypes'
        );
    }

    // ─── Save the table: every changed tariff and English name ────
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tariffs' => ['nullable', 'array'],
            'tariffs.*' => ['nullable', 'numeric', 'min:0.01', 'max:99999.99'],
            'english' => ['nullable', 'array'],
            'english.*' => ['nullable', 'string', 'max:100'],
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'tariffs.*.numeric' => 'Enter the tariff as a number.',
            'tariffs.*.min' => 'A tariff must be at least ₱0.01.',
            'tariffs.*.max' => 'A tariff cannot exceed ₱99,999.99.',
            'effective_date.before_or_equal' => 'The effective date cannot be in the future.',
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $fishTypes = FishType::with('currentTariff')
            ->whereIn('id', array_keys(($request->input('tariffs') ?? []) + ($request->input('english') ?? [])))
            ->get()
            ->keyBy('id');

        $changedTariffs = [];
        $renamed = 0;

        DB::transaction(function () use ($request, $fishTypes, &$changedTariffs, &$renamed) {
            foreach ($request->input('tariffs', []) as $fishId => $value) {
                $fish = $fishTypes->get((int) $fishId);
                if (! $fish || $value === null || $value === '') {
                    continue;
                }

                $old = $fish->currentTariff?->tariff_price;
                if (PriceGuide::setTariff($fish, (float) $value, $request->effective_date, Auth::id())) {
                    $changedTariffs[] = $fish->name.' '.($old !== null ? '₱'.number_format((float) $old, 2).' → ' : '')
                        .'₱'.number_format((float) $value, 2);
                }
            }

            foreach ($request->input('english', []) as $fishId => $value) {
                $fish = $fishTypes->get((int) $fishId);
                $value = trim((string) $value) ?: null;
                if ($fish && $fish->english_name !== $value) {
                    $fish->update(['english_name' => $value]);
                    $renamed++;
                }
            }
        });

        if ($changedTariffs) {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_tariff',
                'description' => 'Updated the tariff: '.implode('; ', array_slice($changedTariffs, 0, 15))
                    .(count($changedTariffs) > 15 ? ' and '.(count($changedTariffs) - 15).' more' : '').'.',
            ]);
        }

        $parts = array_filter([
            $changedTariffs ? count($changedTariffs).' '.str('tariff')->plural(count($changedTariffs)).' updated' : null,
            $renamed ? $renamed.' English '.str('name')->plural($renamed).' saved' : null,
        ]);

        return redirect()->route('supervisor.price-guides.index', $request->only('quality_class', 'fish_type_id'))
            ->with('success', $parts ? implode(' · ', $parts).'.' : 'No changes to save.');
    }

    // ─── Change one tariff (adds a new version) ───────────────────
    public function update(Request $request, PriceGuide $priceGuide)
    {
        $request->validate([
            'tariff_price' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $fish = $priceGuide->fishType;
        PriceGuide::setTariff($fish, (float) $request->tariff_price, $request->effective_date, Auth::id());

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update_tariff',
            'description' => "Updated the tariff: {$fish->name} ₱".number_format((float) $request->tariff_price, 2).'.',
        ]);

        return redirect()->route('supervisor.price-guides.index')
            ->with('success', "Tariff for {$fish->name} updated.");
    }

    // ─── Remove a tariff: switched off, kept as history ───────────
    public function destroy(PriceGuide $priceGuide)
    {
        $fish = $priceGuide->fishType;

        PriceGuide::where('fish_type_id', $priceGuide->fish_type_id)->where('is_active', true)->update(['is_active' => false]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'remove_tariff',
            'description' => "Removed the tariff for {$fish?->name}. Its history is kept.",
        ]);

        return redirect()->route('supervisor.price-guides.index')
            ->with('success', "Tariff for {$fish?->name} removed. Its history is kept.");
    }

    // ─── The fish list: add, edit, remove, restore ────────────────

    /** Name / English name / class rules shared by add and edit. */
    private function fishRules(?FishType $fish = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('fish_types', 'name')->ignore($fish?->id)],
            'english_name' => ['nullable', 'string', 'max:100'],
            'quality_class' => ['required', Rule::in(FishType::QUALITY_CLASSES)],
        ];
    }

    private function fishMessages(): array
    {
        return [
            'name.required' => 'Enter the fish name.',
            'name.unique' => 'A fish type with that name already exists.',
            'quality_class.required' => 'Choose a class.',
            'quality_class.in' => 'Choose a class from the list.',
            'tariff_price.min' => 'A tariff must be at least ₱0.01.',
        ];
    }

    /** Back to the table with the fish modal reopened on the errors. */
    private function fishFailed($validator, array $modal)
    {
        return back()->withInput()->withErrors($validator, 'fish')->with('fish_modal', $modal);
    }

    public function storeFish(Request $request)
    {
        $request->merge(['name' => FishType::normaliseName($request->name)]);

        $validator = Validator::make($request->all(), $this->fishRules() + [
            'tariff_price' => ['nullable', 'numeric', 'min:0.01', 'max:99999.99'],
        ], $this->fishMessages());

        // A removed fish keeps its name; point the supervisor at Restore instead.
        $validator->after(function ($v) use ($request) {
            if (FishType::where('name', $request->name)->where('is_active', false)->exists()) {
                $v->errors()->forget('name');
                $v->errors()->add('name', "\"{$request->name}\" was removed earlier. Restore it from Removed Fish at the bottom of the page.");
            }
        });

        if ($validator->fails()) {
            return $this->fishFailed($validator, ['mode' => 'add']);
        }

        $fish = FishType::create([
            'name' => $request->name,
            'english_name' => trim((string) $request->english_name) ?: null,
            'quality_class' => $request->quality_class,
            'is_active' => true,
        ]);

        if ($request->filled('tariff_price')) {
            PriceGuide::setTariff($fish, (float) $request->tariff_price, today()->toDateString(), Auth::id());
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'add_fish_type',
            'description' => "Added {$fish->name} to {$fish->quality_class}"
                .($request->filled('tariff_price') ? ' with a ₱'.number_format((float) $request->tariff_price, 2).' tariff' : '').'.',
        ]);

        return redirect()->route('supervisor.price-guides.index', $request->only('quality_class'))
            ->with('success', "{$fish->name} added to {$fish->quality_class}.");
    }

    public function updateFish(Request $request, FishType $fishType)
    {
        $request->merge(['name' => FishType::normaliseName($request->name)]);

        $validator = Validator::make($request->all(), $this->fishRules($fishType), $this->fishMessages());

        if ($validator->fails()) {
            return $this->fishFailed($validator, ['mode' => 'edit', 'id' => $fishType->id]);
        }

        $before = "{$fishType->name} ({$fishType->quality_class})";

        $fishType->update([
            'name' => $request->name,
            'english_name' => trim((string) $request->english_name) ?: null,
        ]);
        // Its tariff versions and batches move to the new class with it.
        $fishType->changeClass($request->quality_class);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update_fish_type',
            'description' => "Edited {$before} → {$fishType->name} ({$fishType->quality_class}).",
        ]);

        return redirect()->route('supervisor.price-guides.index')
            ->with('success', "{$fishType->name} updated.");
    }

    public function destroyFish(FishType $fishType)
    {
        // Fish still on a stall or waiting for staff would vanish from under the
        // vendors selling it, so it has to clear the board first.
        if ($live = $fishType->liveBatchCount()) {
            return redirect()->route('supervisor.price-guides.index')
                ->with('error', "{$fishType->name} cannot be removed while {$live} "
                    .str('batch')->plural($live).' of it '.($live === 1 ? 'is' : 'are')
                    .' on sale or waiting for staff. Remove it once they are sold or expire.');
        }

        $name = $fishType->name;
        $how = $fishType->removeFromCatalogue();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'remove_fish_type',
            'description' => $how === 'deleted'
                ? "Deleted {$name} (it had no batches)."
                : "Removed {$name} from the list. Its batches are kept for reports and forecasts.",
        ]);

        return redirect()->route('supervisor.price-guides.index')
            ->with('success', $how === 'deleted'
                ? "{$name} deleted."
                : "{$name} removed from the list. Its sales history is kept, and it can be restored below.");
    }

    public function restoreFish(FishType $fishType)
    {
        $fishType->update(['is_active' => true]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'restore_fish_type',
            'description' => "Restored {$fishType->name} to the list.",
        ]);

        return redirect()->route('supervisor.price-guides.index')
            ->with('success', "{$fishType->name} is back on the list.");
    }
}
